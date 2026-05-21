# Is It Done Yet?

Recursive project tracking for WebHatchery users. The app is a React 19/Vite frontend with a PHP backend that stores private, owner-scoped project trees.

## Architecture

```text
isitdoneyet/
├── frontend/               # React, TypeScript, Vite, Zustand, Axios
├── backend/
│   ├── database/migrations # Ordered SQL migrations
│   ├── public/             # PHP API entry point
│   ├── scripts/            # Database initialization
│   ├── src/
│   │   ├── Actions/
│   │   ├── Controllers/
│   │   ├── Core/
│   │   ├── Exceptions/
│   │   ├── Models/
│   │   ├── Repositories/
│   │   ├── Services/
│   │   └── Utils/
│   └── tests/
└── publish.ps1             # Delegates to the shared WebHatchery publish script
```

## Authentication

The backend uses shared WebHatchery Bearer tokens only. There are no local login endpoints and no server-side redirects. Unauthenticated API requests return `401` with `login_url`; the frontend stores that URL and lets the user choose whether to open WebHatchery Login.

Project rows are scoped by `owner_id`, derived from the token `user_id` claim, then `sub`, then `id`.

Agents should call `GET /api/v1/me` before creating or mutating work and compare `data.id` with the signed-in user shown in the UI. Agent work created with a different owner token is valid API data, but it will not be visible to that end user.

## Agent API

The app exposes an agent-oriented loop on top of the project tree:

- `POST /api/v1/agent/tokens` creates a short-lived user-delegated agent token.
- `GET /api/v1/agent/projects/{id}/done-check` asks "Is it done yet?" and returns `answer`, `why_not`, and `next_tasks`.
- `POST /api/v1/agent/projects/{id}/breakdown` records why a project is not done and creates the next subtasks.
- `GET /api/v1/agent/projects/{id}/next-tasks` returns the lowest actionable incomplete nodes.
- `POST /api/v1/agent/tasks/{id}/complete` marks an actionable node complete.

Agent tokens keep the same `user_id` as the signed-in user and add `actor_type: "agent"`, `agent_name`, and optional `assigned_project_id` claims. That lets an agent work in the user's visible project space instead of an isolated synthetic owner.

`done-check` returns `yes` only after the selected project and every descendant are complete. Until then, agents should use `next_tasks` as the concrete work queue and `breakdown` whenever a task is still too large to finish directly.

When every known child task is complete but the parent itself is still open, the parent appears in `reassessment_questions`, not `next_tasks`. The agent must ask "is it done yet?" again for that parent; the answer may create another `breakdown`, or it may justify calling `POST /api/v1/agent/tasks/{id}/complete`.

Completion is guarded: `POST /api/v1/agent/tasks/{id}/complete` and the standard project complete route reject a parent task with `400` while any child or descendant remains incomplete.

The UI consumes `done-check` for visible root projects and shows an Agent Status panel with the current answer, next action, open count, next tasks, or reassessment questions. Child tasks injected through the agent API are stored in the same project tree, so they appear in the normal end-user task list after refresh.

The UI also includes Agent Access controls for creating a delegated agent token, or creating a new project and immediately assigning a token to it.

## Environment

Frontend:

```env
VITE_API_BASE_URL=http://127.0.0.1/isitdoneyet/api/v1
VITE_WEBHATCHERY_LOGIN_URL=https://webhatchery.au/login
```

Backend:

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

All backend env values are explicit. Wildcard CORS is rejected.

## Verification

```powershell
cd frontend
npm run lint
npm run type-check
npm run test:run
npm run build
npm audit --audit-level=moderate

cd ..\backend
composer test
composer cs-check
```

For normal local preview, run the app root publish script and inspect the shared preview URL:

```powershell
.\publish.ps1
```

Preview: `http://127.0.0.1/isitdoneyet/`
