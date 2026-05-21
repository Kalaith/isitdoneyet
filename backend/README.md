# Is It Done Yet Backend

PHP API for the Is It Done Yet recursive project tracker.

## Security Model

Projects are private, user-owned records. Every project route requires a shared WebHatchery Bearer token signed with the configured `JWT_SECRET`. Unauthenticated requests return HTTP 401 with `login_url`; the API does not provide local login endpoints or redirects.

Use `GET /api/v1/me` to confirm which owner id the current token maps to before an agent creates tracking work.

Health and status routes are public and do not require database connectivity.

## Required Environment

Copy `.env.example` to the backend environment file used by publish or local development and set every required value explicitly:

```env
APP_ENV=development
APP_BASE_PATH=/isitdoneyet
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=isitdoneyet
DB_USER=isitdoneyet_user
DB_PASSWORD=
JWT_SECRET=replace_with_shared_webhatchery_jwt_secret
WEBHATCHERY_LOGIN_URL=https://webhatchery.au/login
CORS_ALLOWED_ORIGINS=http://127.0.0.1,http://localhost:5173,https://webhatchery.au
```

`CORS_ALLOWED_ORIGINS` must be an explicit comma-separated allow-list. Wildcard CORS is rejected at startup.

## Database

Migrations live in `backend/database/migrations/`.

For an existing public `projects` table, run `001_add_project_ownership.sql`. Before running it, set `@legacy_owner_id` to the WebHatchery user id that should own existing rows:

```sql
SET @legacy_owner_id = 'webhatchery-user-id';
SOURCE backend/database/migrations/001_add_project_ownership.sql;
```

For a new database:

```powershell
composer install --working-dir=../../..
php scripts/initialize-database.php
```

Set `SEED_OWNER_ID` only if you want sample data created for a specific WebHatchery user.

## API

- `GET /api/v1/health`
- `GET /api/v1/status`
- `GET /api/v1/me`
- `GET /api/v1/projects`
- `GET /api/v1/projects/{id}`
- `POST /api/v1/projects`
- `PUT /api/v1/projects/{id}`
- `DELETE /api/v1/projects/{id}`
- `POST /api/v1/projects/{id}/complete`
- `POST /api/v1/projects/{id}/subtasks`
- `GET /api/v1/agent/projects/{id}/done-check`
- `GET /api/v1/agent/projects/{id}/next-tasks`
- `POST /api/v1/agent/projects/{id}/breakdown`
- `POST /api/v1/agent/tasks/{id}/complete`
- `POST /api/v1/agent/tokens`

Legacy `/api/*` project routes are still accepted for deployed compatibility, but new clients should use `/api/v1/*`.

Agent breakdown requests use the recursive "is it done yet?" loop:

```json
{
  "reason": "The project is not complete because the API contract is missing.",
  "tasks": [
    {
      "title": "Define agent response schema",
      "description": "Document the deterministic JSON shape agents can consume."
    }
  ]
}
```

The done-check response answers `yes` only when the selected project and every descendant are complete. `next_tasks` returns the lowest actionable incomplete leaf nodes so an agent can keep drilling down until work is concrete.

If every known child task is complete but the parent is not, the parent is returned in `reassessment_questions`. That means the agent must ask the parent question again instead of marking it complete by checklist inertia. The reassessment can produce another `breakdown`, or it can justify `POST /api/v1/agent/tasks/{id}/complete` when the answer is genuinely yes.

Completion routes reject a task with HTTP 400 while any child or descendant task is incomplete.

The frontend displays agent state by calling `done-check` for visible root projects. Agent-created child tasks use the same project records as manual child tasks, so API-driven work is visible in the standard UI.

Agent token requests use:

```json
{
  "agent_name": "Codex",
  "project_id": 123,
  "expires_in_seconds": 86400
}
```

`project_id` is optional. If present, it must belong to the signed-in user. The returned bearer token keeps the user's `user_id` and adds agent metadata claims.

## Verification

```powershell
composer test
composer cs-check
```
