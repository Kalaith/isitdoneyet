# Is It Done Yet? - Backend

A PHP backend for the "Is It Done Yet?" recursive project management application.

## Features

- **RESTful API** for project management
- **Hierarchical task structure** with unlimited nesting
- **Automatic progress calculation** based on completed subtasks
- **Completion propagation** - parent tasks auto-complete when all children are done
- **Robust error handling** and logging
- **CORS support** for frontend integration

## API Endpoints

### Projects
- `GET /api/v1/projects` - Get all projects
- `GET /api/v1/projects/{id}` - Get project by ID
- `POST /api/v1/projects` - Create new project
- `PUT /api/v1/projects/{id}` - Update project
- `DELETE /api/v1/projects/{id}` - Delete project
- `POST /api/v1/projects/{id}/complete` - Mark project complete
- `POST /api/v1/projects/{id}/subtasks` - Add subtask to project

Compatibility routes (still available):

- `GET /api/projects`
- `GET /api/projects/{id}`
- `POST /api/projects`
- `PUT /api/projects/{id}`
- `DELETE /api/projects/{id}`
- `POST /api/projects/{id}/complete`
- `POST /api/projects/{id}/subtasks`

### System
- `GET /api/v1/health` - Versioned health endpoint
- `GET /health` - Root health endpoint
- `GET /api/v1/status` - Versioned status endpoint
- `GET /api/status` - Legacy status endpoint

## Quick Start

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Environment Setup**
   ```bash
   cp .env.example .env
   # Edit .env with your database settings
   ```

3. **Initialize Database**
   ```bash
   php scripts/initialize-database.php
   ```

4. **Start Server**
   ```bash
   composer start
   # Server runs on http://localhost:3001
   ```

## Project Structure

```
backend/
├── public/
│   └── index.php           # Application entry point
├── src/
│   ├── Actions/
│   │   └── ProjectActions.php     # Business logic
│   ├── Controllers/
│   │   └── ProjectController.php  # HTTP handlers
│   ├── External/
│   │   └── DatabaseService.php    # Database connection
│   ├── Models/
│   │   └── Project.php             # Eloquent model
│   ├── Routes/
│   │   └── api.php                 # API routes
│   └── Utils/
│       └── Logger.php              # Logging utility
├── scripts/
│   └── initialize-database.php    # Database setup
├── storage/
│   └── logs/                       # Application logs
├── composer.json
└── README.md
```

## Architecture

The backend follows the **Actions Pattern** used in the Mytherra project:

- **Controllers** handle HTTP requests/responses
- **Actions** contain business logic and database operations
- **Models** define data structure and relationships
- **External** services handle third-party integrations
- **Utils** provide common functionality

## Database Schema

### Projects Table
```sql
id              BIGINT AUTO_INCREMENT PRIMARY KEY
title           VARCHAR(255) NOT NULL
description     TEXT NULL
completed       BOOLEAN DEFAULT FALSE
parent_id       BIGINT NULL REFERENCES projects(id)
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

## Frontend Integration

Update your frontend's API base URL to point to the backend:

```javascript
// In your frontend app.js or config
const API_BASE_URL = 'http://localhost:3001/api/v1';
```

## Development

### Code Standards
- Follows PSR-12 coding standards
- Uses Actions pattern for business logic separation
- Comprehensive error handling and logging
- Type hints and proper documentation

### Testing
```bash
composer test        # Run PHPUnit tests
composer cs-check    # Check code standards
composer cs-fix      # Fix code formatting
```

## Environment Variables

```env
# Application Configuration
APP_ENV=development
APP_BASE_PATH=/isitdoneyet

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=isitdoneyet
DB_USER=root
DB_PASSWORD=

# Server Configuration
PORT=3001
DEBUG=true

# CORS Configuration
CORS_ALLOWED_ORIGINS="http://localhost:3000,http://127.0.0.1:3000"

# API Configuration
API_PREFIX=/api
API_VERSION=v1

# Security
JWT_SECRET=replace_with_long_random_secret
JWT_EXPIRY=86400
```

## Bootstrap Notes

- `public/index.php` supports both versioned (`/api/v1/*`) and legacy (`/api/*`) routes.
- Health/status endpoints are intentionally lightweight and can respond without database connectivity.
- Autoloading prefers the central WebHatchery `vendor` folder with a local fallback.
