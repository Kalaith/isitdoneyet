<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\External\DatabaseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "Initializing database...\n";

// Initialize database connection
$dbService = new DatabaseService();

try {
    // Test connection
    if (!$dbService->testConnection()) {
        throw new Exception('Could not connect to database');
    }
    
    echo "✓ Database connection established\n";
    
    // Create projects table
    if (!Capsule::schema()->hasTable('projects')) {
        Capsule::schema()->create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('completed')->default(false);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            
            $table->foreign('parent_id')->references('id')->on('projects')->onDelete('cascade');
            $table->index(['parent_id', 'completed']);
        });
        
        echo "✓ Created projects table\n";    } else {
        echo "✓ Projects table already exists\n";
    }
    
    // Seed with sample data
    $sampleProjects = [
        [
            'title' => 'Launch Personal Website',
            'description' => 'Create and deploy a personal portfolio website',
            'completed' => false,
            'parent_id' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    foreach ($sampleProjects as $project) {
        $existing = Capsule::table('projects')
            ->where('title', $project['title'])
            ->where('parent_id', $project['parent_id'])
            ->first();
            
        if (!$existing) {
            $projectId = Capsule::table('projects')->insertGetId($project);
            echo "✓ Created sample project: {$project['title']} (ID: $projectId)\n";
            
            // Add sample subtasks
            $subtasks = [                [
                    'title' => 'Design the website layout',
                    'description' => '',
                    'completed' => true,
                    'parent_id' => $projectId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                [
                    'title' => 'Write content for all pages',
                    'description' => '',
                    'completed' => false,
                    'parent_id' => $projectId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')                ],
                [
                    'title' => 'Set up hosting and deploy',
                    'description' => '',
                    'completed' => false,
                    'parent_id' => $projectId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ];
            
            foreach ($subtasks as $subtask) {
                $subtaskId = Capsule::table('projects')->insertGetId($subtask);
                echo "  ✓ Created subtask: {$subtask['title']} (ID: $subtaskId)\n";
                
                // Add sub-subtasks for "Write content for all pages"
                if ($subtask['title'] === 'Write content for all pages') {
                    $subSubtasks = [                        [
                            'title' => 'Write About page',
                            'description' => '',
                            'completed' => true,
                            'parent_id' => $subtaskId,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ],                        [
                            'title' => 'Write Portfolio page',
                            'description' => '',
                            'completed' => false,
                            'parent_id' => $subtaskId,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]
                    ];
                    
                    foreach ($subSubtasks as $subSubtask) {
                        $subSubtaskId = Capsule::table('projects')->insertGetId($subSubtask);
                        echo "    ✓ Created sub-subtask: {$subSubtask['title']} (ID: $subSubtaskId)\n";
                    }
                }
            }
        }
    }
    
    echo "\n✅ Database initialization completed successfully!\n";
    echo "Backend server can now be started with: composer start\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
