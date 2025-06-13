#!/usr/bin/env node

/**
 * MCP Server for Is It Done Yet? Project
 * Model Context Protocol server implementation
 */

const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

class IsItDoneYetMCPServer {
    constructor() {
        this.projectRoot = process.env.PROJECT_ROOT || __dirname;
        this.apiBaseUrl = process.env.API_BASE_URL || 'http://localhost:8080';
        this.frontendUrl = process.env.FRONTEND_URL || 'http://localhost:3000';
        this.config = this.loadConfig();
    }

    loadConfig() {
        try {
            const configPath = path.join(this.projectRoot, '.mcp');
            if (fs.existsSync(configPath)) {
                return JSON.parse(fs.readFileSync(configPath, 'utf8'));
            }
        } catch (error) {
            console.warn('Could not load .mcp config:', error.message);
        }
        return null;
    }

    async getProjectInfo() {
        return {
            name: 'Is It Done Yet?',
            description: 'Recursive project management application',
            version: '1.0.0',
            status: await this.getProjectStatus(),
            endpoints: this.getApiEndpoints(),
            structure: this.getProjectStructure()
        };
    }

    async getProjectStatus() {
        const status = {
            backend: await this.checkBackendHealth(),
            frontend: await this.checkFrontendHealth(),
            database: await this.checkDatabaseHealth()
        };
        return status;
    }

    async checkBackendHealth() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/api`);
            return {
                status: response.ok ? 'running' : 'error',
                port: 8080,
                url: this.apiBaseUrl
            };
        } catch (error) {
            return {
                status: 'offline',
                error: error.message,
                port: 8080,
                url: this.apiBaseUrl
            };
        }
    }

    async checkFrontendHealth() {
        try {
            const response = await fetch(this.frontendUrl);
            return {
                status: response.ok ? 'running' : 'error',
                port: 3000,
                url: this.frontendUrl
            };
        } catch (error) {
            return {
                status: 'offline',
                error: error.message,
                port: 3000,
                url: this.frontendUrl
            };
        }
    }

    async checkDatabaseHealth() {
        try {
            // Check if we can connect to the projects API endpoint
            const response = await fetch(`${this.apiBaseUrl}/api/projects`);
            return {
                status: response.ok ? 'connected' : 'error',
                type: 'MySQL'
            };
        } catch (error) {
            return {
                status: 'disconnected',
                error: error.message,
                type: 'MySQL'
            };
        }
    }

    getApiEndpoints() {
        return [
            'GET /api - API status',
            'GET /api/projects - Get all projects',
            'GET /api/projects/{id} - Get project by ID',
            'POST /api/projects - Create project',
            'PUT /api/projects/{id} - Update project',
            'DELETE /api/projects/{id} - Delete project',
            'POST /api/projects/{id}/complete - Mark complete',
            'POST /api/projects/{id}/subtasks - Add subtask'
        ];
    }

    getProjectStructure() {
        return {
            frontend: {
                files: ['index.html', 'app.js', 'api-service.js', 'style.css'],
                server: 'server.js',
                config: 'package.json'
            },
            backend: {
                entry: 'public/index.php',
                models: ['src/Models/Project.php'],
                controllers: ['src/Controllers/ProjectController.php'],
                actions: ['src/Actions/ProjectActions.php'],
                routes: ['src/Routes/api.php'],
                config: 'composer.json'
            }
        };
    }

    startBackend() {
        const backendPath = path.join(this.projectRoot, 'backend');
        console.log('Starting backend server...');
        
        const backend = spawn('php', ['-S', 'localhost:8080', '-t', 'public'], {
            cwd: backendPath,
            stdio: 'inherit'
        });

        backend.on('error', (error) => {
            console.error('Backend startup error:', error);
        });

        return backend;
    }

    startFrontend() {
        console.log('Starting frontend server...');
        
        const frontend = spawn('node', ['server.js'], {
            cwd: this.projectRoot,
            stdio: 'inherit'
        });

        frontend.on('error', (error) => {
            console.error('Frontend startup error:', error);
        });

        return frontend;
    }

    async runCommand(command, args = []) {
        switch (command) {
            case 'status':
                return await this.getProjectInfo();
            
            case 'start':
                if (args[0] === 'backend') {
                    return this.startBackend();
                } else if (args[0] === 'frontend') {
                    return this.startFrontend();
                } else {
                    // Start both
                    const backend = this.startBackend();
                    const frontend = this.startFrontend();
                    return { backend, frontend };
                }
            
            case 'health':
                return await this.getProjectStatus();
            
            case 'endpoints':
                return this.getApiEndpoints();
            
            default:
                throw new Error(`Unknown command: ${command}`);
        }
    }
}

// CLI interface
if (require.main === module) {
    const server = new IsItDoneYetMCPServer();
    const command = process.argv[2] || 'status';
    const args = process.argv.slice(3);

    server.runCommand(command, args)
        .then(result => {
            if (typeof result === 'object' && result !== null) {
                console.log(JSON.stringify(result, null, 2));
            }
        })
        .catch(error => {
            console.error('Error:', error.message);
            process.exit(1);
        });
}

module.exports = IsItDoneYetMCPServer;
