<?php

declare(strict_types=1);

use App\Actions\AgentActions;
use App\Actions\ProjectActions;
use App\Controllers\AgentController;
use App\Controllers\ProjectController;
use App\Core\Env;
use App\Exceptions\AuthException;
use App\External\DatabaseService;
use App\Repositories\ProjectRepository;
use App\Services\AuthService;
use App\Services\AgentTokenService;
use App\Services\ProjectCompletionAnalyzer;
use App\Utils\Logger;
use Dotenv\Dotenv;

$autoloader = null;
$searchDir = __DIR__;
for ($i = 0; $i < 8; $i++) {
    $candidate = $searchDir . '/vendor/autoload.php';
    if (file_exists($candidate)) {
        $autoloader = $candidate;
        break;
    }

    $parent = dirname($searchDir);
    if ($parent === $searchDir) {
        break;
    }
    $searchDir = $parent;
}

if ($autoloader === null) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Composer autoloader was not found.']);
    exit;
}

$loader = require $autoloader;
$projectSrc = realpath(__DIR__ . '/../src') ?: (__DIR__ . '/../src');
if (is_object($loader) && method_exists($loader, 'addPsr4')) {
    $loader->addPsr4('App\\', rtrim($projectSrc, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, true);
}
if (is_object($loader) && method_exists($loader, 'addClassMap')) {
    $loader->addClassMap(buildLocalAppClassMap($projectSrc));
}

try {
    Dotenv::createImmutable(__DIR__ . '/../')->load();
} catch (Throwable) {
}

try {
    configureCors();
} catch (Throwable $exception) {
    jsonResponse(['success' => false, 'message' => $exception->getMessage()], 500);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$route = resolveRoute((string) ($_SERVER['REQUEST_URI'] ?? '/'));

if ($route === 'health') {
    jsonResponse([
        'success' => true,
        'data' => [
            'status' => 'ok',
            'timestamp' => date('c'),
            'service' => 'Is It Done Yet API',
            'version' => '2.0.0',
            'base_path' => '/api/v1',
        ],
    ]);
}

if ($route === 'status') {
    jsonResponse([
        'success' => true,
        'data' => [
            'status' => 'OK',
            'service' => 'Is It Done Yet API',
            'version' => '2.0.0',
            'auth' => 'webhatchery_bearer',
        ],
    ]);
}

try {
    $authUser = (new AuthService())->requireUser($_SERVER);
    $ownerId = $authUser['id'];

    if ($route === 'me' || $route === 'auth/me') {
        jsonResponse([
            'success' => true,
            'data' => authUserPayload($authUser),
        ]);
    }

    $database = new DatabaseService();
    $database->testConnection();

    $logger = new Logger();
    $repository = new ProjectRepository($database->getConnection());
    $projectActions = new ProjectActions($repository, $logger);
    $controller = new ProjectController(
        $projectActions,
        $logger
    );
    $agentController = new AgentController(
        new AgentActions($projectActions, new ProjectCompletionAnalyzer(), new AgentTokenService()),
        $logger
    );

    $result = routeRequest($route, $requestMethod, $controller, $agentController, $ownerId);
    jsonResponse($result['body'], $result['status']);
} catch (AuthException $exception) {
    jsonResponse(
        array_merge([
            'success' => false,
            'message' => $exception->getMessage(),
        ], $exception->context()),
        $exception->statusCode()
    );
} catch (Throwable $exception) {
    error_log('IsItDoneYet API error: ' . $exception->getMessage());
    jsonResponse(['success' => false, 'message' => 'Internal server error.'], 500);
}

function configureCors(): void
{
    $allowedOrigins = array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', Env::required('CORS_ALLOWED_ORIGINS'))
    )));

    if ($allowedOrigins === [] || in_array('*', $allowedOrigins, true)) {
        throw new RuntimeException('CORS_ALLOWED_ORIGINS must be an explicit allow-list.');
    }

    $requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($requestOrigin !== '' && !in_array($requestOrigin, $allowedOrigins, true)) {
        jsonResponse(['success' => false, 'message' => 'Origin is not allowed.'], 403);
    }

    header('Access-Control-Allow-Origin: ' . ($requestOrigin !== '' ? $requestOrigin : $allowedOrigins[0]));
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
}

/**
 * @return array<string, string>
 */
function buildLocalAppClassMap(string $srcPath): array
{
    $classMap = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcPath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($srcPath) + 1);
        $className = 'App\\' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
        $classMap[$className] = $file->getPathname();
    }

    return $classMap;
}

function resolveRoute(string $requestUri): string
{
    $path = trim((string) parse_url($requestUri, PHP_URL_PATH), '/');
    $basePath = trim(Env::required('APP_BASE_PATH'), '/');

    if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath) + 1);
    }

    if ($path === 'health') {
        return 'health';
    }

    if (str_starts_with($path, 'api/v1/')) {
        return substr($path, strlen('api/v1/'));
    }

    if ($path === 'api/v1') {
        return 'status';
    }

    if (str_starts_with($path, 'api/')) {
        return substr($path, strlen('api/'));
    }

    return $path;
}

/**
 * @return array{status: int, body: array<string, mixed>}
 */
function routeRequest(
    string $route,
    string $method,
    ProjectController $controller,
    AgentController $agentController,
    string $ownerId
): array {
    if (preg_match('#^agent/projects/(\d+)/done-check$#', $route, $matches)) {
        return $method === 'GET'
            ? $agentController->checkDone($ownerId, (int) $matches[1])
            : methodNotAllowed();
    }

    if (preg_match('#^agent/projects/(\d+)/next-tasks$#', $route, $matches)) {
        return $method === 'GET'
            ? $agentController->nextTasks($ownerId, (int) $matches[1])
            : methodNotAllowed();
    }

    if (preg_match('#^agent/projects/(\d+)/breakdown$#', $route, $matches)) {
        return $method === 'POST'
            ? $agentController->recordBreakdown($ownerId, (int) $matches[1], jsonInput())
            : methodNotAllowed();
    }

    if (preg_match('#^agent/tasks/(\d+)/complete$#', $route, $matches)) {
        return $method === 'POST'
            ? $agentController->completeTask($ownerId, (int) $matches[1])
            : methodNotAllowed();
    }

    if ($route === 'agent/tokens') {
        return $method === 'POST'
            ? $agentController->createToken($ownerId, jsonInput())
            : methodNotAllowed();
    }

    if ($route === 'projects') {
        if ($method === 'GET') {
            return $controller->getAllProjects($ownerId);
        }

        if ($method === 'POST') {
            return $controller->createProject($ownerId, jsonInput());
        }

        return methodNotAllowed();
    }

    if (preg_match('#^projects/(\d+)$#', $route, $matches)) {
        $projectId = (int) $matches[1];

        return match ($method) {
            'GET' => $controller->getProjectById($ownerId, $projectId),
            'PUT' => $controller->updateProject($ownerId, $projectId, jsonInput()),
            'DELETE' => $controller->deleteProject($ownerId, $projectId),
            default => methodNotAllowed(),
        };
    }

    if (preg_match('#^projects/(\d+)/complete$#', $route, $matches)) {
        return $method === 'POST'
            ? $controller->markProjectComplete($ownerId, (int) $matches[1])
            : methodNotAllowed();
    }

    if (preg_match('#^projects/(\d+)/subtasks$#', $route, $matches)) {
        return $method === 'POST'
            ? $controller->addSubtask($ownerId, (int) $matches[1], jsonInput())
            : methodNotAllowed();
    }

    return [
        'status' => 404,
        'body' => [
            'success' => false,
            'message' => 'Route not found.',
        ],
    ];
}

/**
 * @param array{id: string, claims: array<string, mixed>} $authUser
 * @return array<string, mixed>
 */
function authUserPayload(array $authUser): array
{
    $claims = $authUser['claims'];

    return [
        'id' => $authUser['id'],
        'actor_type' => claimString($claims, 'actor_type') ?? 'user',
        'agent_name' => claimString($claims, 'agent_name'),
        'assigned_project_id' => claimInt($claims, 'assigned_project_id'),
        'username' => claimString($claims, 'username'),
        'display_name' => claimString($claims, 'display_name'),
        'email' => claimString($claims, 'email'),
        'role' => claimString($claims, 'role'),
    ];
}

/**
 * @param array<string, mixed> $claims
 */
function claimString(array $claims, string $key): ?string
{
    if (!isset($claims[$key])) {
        return null;
    }

    $value = $claims[$key];
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }

    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

/**
 * @param array<string, mixed> $claims
 */
function claimInt(array $claims, string $key): ?int
{
    if (!isset($claims[$key]) || !is_numeric($claims[$key])) {
        return null;
    }

    $value = (int) $claims[$key];
    return $value > 0 ? $value : null;
}

/**
 * @return array<string, mixed>
 */
function jsonInput(): array
{
    $input = file_get_contents('php://input');
    $data = json_decode($input ?: '{}', true);

    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['success' => false, 'message' => 'Invalid JSON data.'], 400);
    }

    return $data;
}

/**
 * @return array{status: int, body: array<string, mixed>}
 */
function methodNotAllowed(): array
{
    return [
        'status' => 405,
        'body' => [
            'success' => false,
            'message' => 'Method not allowed.',
        ],
    ];
}

/**
 * @param array<string, mixed> $payload
 */
function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
