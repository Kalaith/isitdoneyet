<?php

declare(strict_types=1);

use App\External\DatabaseService;
use Dotenv\Dotenv;

$autoloadCandidates = [
    __DIR__ . '/../../../../vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];

$autoloader = null;
foreach ($autoloadCandidates as $candidate) {
    if (file_exists($candidate)) {
        $autoloader = $candidate;
        break;
    }
}

if ($autoloader === null) {
    throw new RuntimeException('Composer autoload.php not found for isitdoneyet init script.');
}

$loader = require $autoloader;
$projectSrc = realpath(__DIR__ . '/../src') ?: (__DIR__ . '/../src');
if (is_object($loader) && method_exists($loader, 'addPsr4')) {
    $loader->addPsr4('App\\', rtrim($projectSrc, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, true);
}
if (is_object($loader) && method_exists($loader, 'addClassMap')) {
    $loader->addClassMap(buildLocalAppClassMap($projectSrc));
}

Dotenv::createImmutable(__DIR__ . '/../')->load();

echo "Initializing Is It Done Yet database...\n";

$db = (new DatabaseService())->getConnection();

$db->exec(
    'CREATE TABLE IF NOT EXISTS projects (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_id VARCHAR(191) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        completed TINYINT(1) NOT NULL DEFAULT 0,
        parent_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        CONSTRAINT fk_projects_parent
            FOREIGN KEY (parent_id) REFERENCES projects(id)
            ON DELETE CASCADE,
        INDEX idx_projects_owner_parent_completed (owner_id, parent_id, completed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

echo "Projects table is ready.\n";

$seedOwnerId = trim((string) ($_ENV['SEED_OWNER_ID'] ?? $_SERVER['SEED_OWNER_ID'] ?? getenv('SEED_OWNER_ID') ?: ''));
if ($seedOwnerId === '') {
    echo "Skipping sample data. Set SEED_OWNER_ID to seed projects for a WebHatchery user.\n";
    exit(0);
}

$now = date('Y-m-d H:i:s');
$db->beginTransaction();

try {
    $existing = $db->prepare(
        'SELECT id FROM projects WHERE owner_id = :owner_id AND title = :title AND parent_id IS NULL LIMIT 1'
    );
    $existing->execute([
        'owner_id' => $seedOwnerId,
        'title' => 'Launch Personal Website',
    ]);

    if ($existing->fetchColumn() === false) {
        $insert = $db->prepare(
            'INSERT INTO projects (owner_id, title, description, completed, parent_id, created_at, updated_at)
             VALUES (:owner_id, :title, :description, :completed, :parent_id, :created_at, :updated_at)'
        );

        $insert->execute([
            'owner_id' => $seedOwnerId,
            'title' => 'Launch Personal Website',
            'description' => 'Create and deploy a personal portfolio website',
            'completed' => 0,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $parentId = (int) $db->lastInsertId();
        foreach (['Design the website layout', 'Write content for all pages', 'Set up hosting and deploy'] as $index => $title) {
            $insert->execute([
                'owner_id' => $seedOwnerId,
                'title' => $title,
                'description' => '',
                'completed' => $index === 0 ? 1 : 0,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "Sample project seeded for configured owner.\n";
    }

    $db->commit();
} catch (Throwable $exception) {
    $db->rollBack();
    throw $exception;
}

echo "Database initialization completed successfully.\n";

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
