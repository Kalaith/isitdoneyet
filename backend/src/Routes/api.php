<?php

use Slim\Routing\RouteCollectorProxy;
use App\Controllers\ProjectController;
use App\Actions\ProjectActions;
use App\Utils\Logger;
use DI\Container;

// API Routes - Apache strips /api prefix, so routes start from root
$app->group('', function (RouteCollectorProxy $group) use ($container) {
      // API status endpoint
    $group->get('/status', function($request, $response) {
        $response->getBody()->write(json_encode([
            'status' => 'OK',
            'service' => 'Is It Done Yet API',
            'version' => '1.0.0',
            'base_url' => 'https://webhatchery.au/isitdoneyet/api',
            'endpoints' => [
                'GET /api/projects - Get all projects',
                'GET /api/projects/{id} - Get project by ID',
                'POST /api/projects - Create new project',
                'PUT /api/projects/{id} - Update project',
                'DELETE /api/projects/{id} - Delete project',
                'POST /api/projects/{id}/complete - Mark project complete',
                'POST /api/projects/{id}/subtasks - Add subtask to project'
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Debug endpoint to check raw database content
    $group->get('/debug/database', function($request, $response) use ($container) {
        $databaseService = $container->get('DatabaseService');
        $pdo = $databaseService->getConnection();
        
        $stmt = $pdo->query('SELECT * FROM projects ORDER BY id');
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response->getBody()->write(json_encode([
            'raw_database_projects' => $projects,
            'count' => count($projects)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Project Routes
    $group->get('/projects', function($request, $response) use ($container) {
        $controller = new ProjectController(
            $container->get(ProjectActions::class),
            $container->get(Logger::class)
        );
        return $controller->getAllProjects($request, $response);
    });
    
    $group->get('/projects/{id:[0-9]+}', function($request, $response, $args) use ($container) {
        $request = $request->withAttribute('id', $args['id']);
        $controller = new ProjectController(
            $container->get(ProjectActions::class),
            $container->get(Logger::class)
        );
        return $controller->getProjectById($request, $response);
    });
    
    $group->post('/projects', function($request, $response) use ($container) {
        $controller = new ProjectController(
            $container->get(ProjectActions::class),
            $container->get(Logger::class)
        );
        return $controller->createProject($request, $response);
    });
    
    $group->put('/projects/{id:[0-9]+}', function($request, $response, $args) use ($container) {
        $request = $request->withAttribute('id', $args['id']);
        $controller = new ProjectController(
            $container->get(ProjectActions::class),
            $container->get(Logger::class)
        );
        return $controller->updateProject($request, $response);
    });
    
    $group->delete('/projects/{id:[0-9]+}', function($request, $response, $args) use ($container) {
        $request = $request->withAttribute('id', $args['id']);
        $controller = new ProjectController(
            $container->get(ProjectActions::class),
            $container->get(Logger::class)
        );
        return $controller->deleteProject($request, $response);
    });
    
    $group->post('/projects/{id:[0-9]+}/complete', function($request, $response, $args) use ($container) {
        $request = $request->withAttribute('id', $args['id']);
        $controller = new ProjectController(
            $container->get(ProjectActions::class),
            $container->get(Logger::class)
        );
        return $controller->markProjectComplete($request, $response);
    });
    
    $group->post('/projects/{id:[0-9]+}/subtasks', function($request, $response, $args) use ($container) {
        $request = $request->withAttribute('id', $args['id']);
        $controller = new ProjectController(
            $container->get(ProjectActions::class),
            $container->get(Logger::class)
        );
        return $controller->addSubtask($request, $response);
    });
});

// Set up dependency injection for ProjectActions
$container->set(ProjectActions::class, function (Container $container) {
    return new ProjectActions($container->get(Logger::class));
});
