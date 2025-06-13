<?php
// Database initialization script for "Is It Done Yet?" application
$initializeMode = isset($_POST['action']) && $_POST['action'] === 'initialize';

if ($initializeMode) {
    require_once __DIR__ . '/backend/vendor/autoload.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Initialization - Is It Done Yet?</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-weight: bold;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .log {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn.danger {
            background: #dc3545;
        }
        .btn.danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Initialization</h1>
        <p>This tool will initialize the database for "Is It Done Yet?" application.</p>
        
        <?php if (!isset($_POST['action'])): ?>
            <div class="info">
                <strong>Before proceeding:</strong><br>
                • Ensure your database credentials are correct in backend/.env<br>
                • Make sure the database exists (webhatch_isitdoneyet)<br>
                • This will create the projects table and add sample data
            </div>
            
            <form method="POST">
                <button type="submit" name="action" value="initialize" class="btn">Initialize Database</button>
            </form>
            
        <?php else: ?>
              <?php
            // Initialize database
            if ($initializeMode) {
                $log = "";
                $success = false;
                
                try {
                    // Load environment variables
                    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/backend/');
                    $dotenv->load();
                    
                    $log .= "✓ Loaded environment configuration\n";
                    
                    // Initialize database connection
                    $dbService = new App\External\DatabaseService();
                    
                    // Test connection
                    if (!$dbService->testConnection()) {
                        throw new Exception('Could not connect to database. Check your .env credentials.');
                    }
                    
                    $log .= "✓ Database connection established\n";
                    
                    // Create projects table
                    if (!Illuminate\Database\Capsule\Manager::schema()->hasTable('projects')) {
                        Illuminate\Database\Capsule\Manager::schema()->create('projects', function (Illuminate\Database\Schema\Blueprint $table) {
                            $table->id();
                            $table->string('title');
                            $table->text('description')->nullable();
                            $table->boolean('completed')->default(false);
                            $table->unsignedBigInteger('parent_id')->nullable();
                            $table->timestamps();
                            
                            $table->foreign('parent_id')->references('id')->on('projects')->onDelete('cascade');
                            $table->index(['parent_id', 'completed']);
                        });
                        
                        $log .= "✓ Created projects table with proper structure\n";
                    } else {
                        $log .= "✓ Projects table already exists\n";
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
                        $existing = Illuminate\Database\Capsule\Manager::table('projects')
                            ->where('title', $project['title'])
                            ->where('parent_id', $project['parent_id'])
                            ->first();
                            
                        if (!$existing) {
                            $projectId = Illuminate\Database\Capsule\Manager::table('projects')->insertGetId($project);
                            $log .= "✓ Created sample project: {$project['title']} (ID: $projectId)\n";
                            
                            // Add sample subtasks
                            $subtasks = [
                                [
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
                                    'updated_at' => date('Y-m-d H:i:s')
                                ],
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
                                $subtaskId = Illuminate\Database\Capsule\Manager::table('projects')->insertGetId($subtask);
                                $log .= "  ✓ Created subtask: {$subtask['title']} (ID: $subtaskId)\n";
                                
                                // Add sub-subtasks for "Write content for all pages"
                                if ($subtask['title'] === 'Write content for all pages') {
                                    $subSubtasks = [
                                        [
                                            'title' => 'Write About page',
                                            'description' => '',
                                            'completed' => true,
                                            'parent_id' => $subtaskId,
                                            'created_at' => date('Y-m-d H:i:s'),
                                            'updated_at' => date('Y-m-d H:i:s')
                                        ],
                                        [
                                            'title' => 'Write Portfolio page',
                                            'description' => '',
                                            'completed' => false,
                                            'parent_id' => $subtaskId,
                                            'created_at' => date('Y-m-d H:i:s'),
                                            'updated_at' => date('Y-m-d H:i:s')
                                        ]
                                    ];
                                    
                                    foreach ($subSubtasks as $subSubtask) {
                                        $subSubtaskId = Illuminate\Database\Capsule\Manager::table('projects')->insertGetId($subSubtask);
                                        $log .= "    ✓ Created sub-subtask: {$subSubtask['title']} (ID: $subSubtaskId)\n";
                                    }
                                }
                            }
                        } else {
                            $log .= "✓ Sample project already exists: {$project['title']}\n";
                        }
                    }
                    
                    $log .= "\n✅ Database initialization completed successfully!\n";
                    $log .= "Your 'Is It Done Yet?' application is now ready to use.\n";
                    $success = true;
                    
                } catch (Exception $e) {
                    $log .= "\n❌ Error: " . $e->getMessage() . "\n";
                    $log .= "Please check your database configuration in backend/.env file.\n";
                    $success = false;
                }
            }
            ?>            
            <div class="<?php echo $initializeMode && isset($success) && $success ? 'success' : ($initializeMode ? 'error' : ''); ?>">
                <?php if ($initializeMode && isset($success)): ?>
                    <?php if ($success): ?>
                        ✅ Database initialization completed successfully!
                    <?php else: ?>
                        ❌ Database initialization failed. Please check the log below.
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($initializeMode && isset($log)): ?>
                <h3>Initialization Log:</h3>
                <div class="log"><?php echo htmlspecialchars($log); ?></div>
            <?php endif; ?>
            
            <?php if ($initializeMode && isset($success) && $success): ?>
                <div class="info">
                    <strong>Next Steps:</strong><br>
                    • Your database is now ready<br>
                    • You can test the API at: <a href="api/status" target="_blank">api/status</a><br>
                    • Access your application at: <a href="index.html">index.html</a><br>
                    • You can safely delete this initialization file for security
                </div>
                
                <form method="POST" style="margin-top: 20px;">
                    <button type="submit" name="action" value="delete" class="btn danger" 
                            onclick="return confirm('Are you sure you want to delete this initialization file?')">
                        🗑️ Delete This File (Recommended)
                    </button>
                </form>
            <?php else: ?>
                <form method="GET">
                    <button type="submit" class="btn">← Back to Setup</button>
                </form>
            <?php endif; ?>
            
        <?php endif; ?>
        
        <?php 
        // Handle file deletion
        if (isset($_POST['action']) && $_POST['action'] === 'delete'): 
            if (unlink(__FILE__)) {
                echo "<div class='success'>✅ Initialization file deleted successfully!</div>";
                echo "<p>You can now access your application at <a href='index.html'>index.html</a></p>";
            } else {
                echo "<div class='error'>❌ Could not delete file. Please remove it manually for security.</div>";
            }
        endif; 
        ?>
    </div>
</body>
</html>
