# Is It Done Yet Agent API Test Report

## Purpose

This report is a handoff brief for an AI agent testing whether the Is It Done Yet API supports continual agent development.

The core product idea is recursive completion checking:

1. Ask: "Is it done yet?"
2. If no, ask why not.
3. Convert the reason into concrete child tasks.
4. Complete child tasks.
5. Reassess the parent question.
6. Repeat until the parent and all descendants can truthfully answer yes.

The API should not treat a completed checklist as automatic proof that the parent is complete. When known subtasks are done, the parent must be reassessed because new blockers may have emerged.

## Test Target

Use a local or preview deployment of the app.

Expected base URL:

```text
http://127.0.0.1/isitdoneyet/api/v1
```

All protected requests require:

```http
Authorization: Bearer <valid WebHatchery JWT>
Content-Type: application/json
```

The JWT must contain at least one user identifier claim:

```json
{
  "user_id": "agent-test-user"
}
```

Before creating or mutating test work, the agent must call:

```http
GET /me
```

Pass criteria:

- Response is `success: true`.
- `data.id` matches the owner shown in the UI as `Signed in as ...`, or the agent is explicitly using a token delegated by that same user.

Fail condition:

- If the agent uses a synthetic owner or a different user's token, the API may work but the project will not be visible to the intended end user. In that case the answer to "is AI usage visible to an end user on Is It Done Yet?" is `no`.

The backend must have:

- `JWT_SECRET` configured to match the token signer.
- Working database credentials.
- The project ownership migration applied, so `projects.owner_id` exists.

## Endpoints Under Test

```text
POST   /projects
DELETE /projects/{id}

POST   /agent/tokens
GET    /agent/projects/{id}/done-check
GET    /agent/projects/{id}/next-tasks
POST   /agent/projects/{id}/breakdown
POST   /agent/tasks/{id}/complete
```

## Expected Agent Response Fields

`done-check` should return:

```json
{
  "success": true,
  "data": {
    "project_id": 123,
    "project_title": "Example",
    "question": "Is it done yet?",
    "answer": "no",
    "is_done": false,
    "next_action": "work",
    "counts": {
      "total": 1,
      "completed": 0,
      "open": 1
    },
    "why_not": [],
    "next_tasks": [],
    "reassessment_questions": []
  }
}
```

`next_action` meanings:

- `work`: complete one or more concrete leaf tasks from `next_tasks`.
- `reassess`: ask the parent question again using `reassessment_questions`.
- `breakdown`: no concrete next task exists; add a breakdown.
- `done`: the selected project and descendants are complete.

## Required Test Scenario

### 1. Create A User-Delegated Agent Token

Request:

```http
POST /agent/tokens
```

Body:

```json
{
  "agent_name": "API Visibility Tester",
  "expires_in_seconds": 86400
}
```

Pass criteria:

- Response is `success: true`.
- `data.owner_id` matches `GET /me.data.id`.
- Decoded token claims include `actor_type: "agent"` and the same `user_id` as the signed-in user.

Use this token for the remaining agent requests. The end-user UI should still show the created work because the delegated token acts in the user's owner scope.

### 2. Create A Root Project

Request:

```http
POST /projects
```

Body:

```json
{
  "title": "Agent API Recursive Completion Test",
  "description": "Temporary project to verify an agent can drive the recursive done-check loop."
}
```

Pass criteria:

- Response is `success: true`.
- Response has `data.id`.
- Save this ID as `root_id`.

### 3. Ask If The Root Is Done

Request:

```http
GET /agent/projects/{root_id}/done-check
```

Pass criteria:

- `answer` is `no`.
- `is_done` is `false`.
- `next_action` is `work`.
- `next_tasks` includes the root project because it has no child tasks yet.

### 4. Break The Root Into Two Known Actions

Request:

```http
POST /agent/projects/{root_id}/breakdown
```

Body:

```json
{
  "reason": "It is not complete because two known actions must be finished first.",
  "tasks": [
    {
      "title": "Complete first known action",
      "description": "A concrete leaf task for the agent to finish."
    },
    {
      "title": "Complete second known action",
      "description": "Another concrete leaf task for the agent to finish."
    }
  ]
}
```

Pass criteria:

- Response is `success: true`.
- `created_tasks` contains exactly two task IDs.
- `status.next_tasks` contains those two task IDs.
- Save them as `child_id_1` and `child_id_2`.

### 5. Complete Both Known Actions

Before completing either child, verify the root cannot be completed early:

```http
POST /agent/tasks/{root_id}/complete
```

Pass criteria:

- Response is `success: false`.
- HTTP status is `400`.
- `message` explains that child tasks must be completed first.

Requests:

```http
POST /agent/tasks/{child_id_1}/complete
POST /agent/tasks/{child_id_2}/complete
```

Pass criteria:

- Each response is `success: true`.
- Each child task's returned status has `answer: yes`.

### 6. Reassess The Root

Request:

```http
GET /agent/projects/{root_id}/done-check
```

This is the critical behavior.

Pass criteria:

- `answer` is still `no`.
- `next_action` is `reassess`.
- `next_tasks` is empty.
- `reassessment_questions` contains `root_id`.

Fail condition:

- If `next_tasks` contains the root as a task to complete, the API is incorrectly treating checklist completion as parent completion.
- If `answer` is `yes`, the API skipped mandatory reassessment.

### 7. Simulate A New Question Found During Reassessment

Request:

```http
POST /agent/projects/{root_id}/breakdown
```

Body:

```json
{
  "reason": "Reassessment found one final validation task.",
  "tasks": [
    {
      "title": "Run final validation discovered during reassessment",
      "description": "This task was discovered only after the first two actions were complete."
    }
  ]
}
```

Pass criteria:

- Response is `success: true`.
- One new task is created.
- `status.next_tasks` contains the new task.

### 8. Complete The New Task

Request:

```http
POST /agent/tasks/{new_child_id}/complete
```

Pass criteria:

- Response is `success: true`.
- Rechecking the root still returns `next_action: reassess`, not `done`.

### 9. Explicitly Complete The Root After Reassessment

Only after the agent determines the reassessed answer is genuinely yes:

```http
POST /agent/tasks/{root_id}/complete
```

Then request:

```http
GET /agent/projects/{root_id}/done-check
```

Pass criteria:

- `answer` is `yes`.
- `is_done` is `true`.
- `next_action` is `done`.
- `next_tasks` is empty.
- `reassessment_questions` is empty.
- `counts.open` is `0`.

### 10. Cleanup

Request:

```http
DELETE /projects/{root_id}
```

Pass criteria:

- Response is `success: true`.
- A follow-up `GET /agent/projects/{root_id}/done-check` should return not found.

## Additional Negative Tests

An AI tester should also verify:

1. Missing bearer token returns `401` and includes `login_url`.
2. Invalid bearer token returns `401`.
3. A user cannot read or mutate another user's project.
4. `/me` returns the same owner id the UI shows for the signed-in user.
5. `breakdown` with no `reason` returns `400`.
6. `breakdown` with an empty `tasks` array returns `400`.
7. Task titles longer than 255 characters return `400`.
8. Deleting a parent removes owned children.
9. Completing a parent with any incomplete child or descendant returns `400`.

## Overall Pass Criteria

The API is agent-usable if:

- An agent can create a project, break it down, complete tasks, reassess parent questions, discover new tasks, and eventually complete the root.
- `next_tasks` only contains concrete work items.
- `reassessment_questions` is used whenever known child work is complete but the parent is not explicitly complete.
- The API never automatically marks a parent complete just because its children are complete.
- All mutation routes are owner-scoped.
- Test data can be cleaned up through the API.

## Known Environment Risks

The API contract can be correct while local testing still fails if:

- `JWT_SECRET` is missing or does not match the token signer.
- The local preview database credentials are stale.
- The ownership migration has not been applied to legacy `projects` tables.
- A stale local `backend/vendor` shadows the shared central Composer vendor during source-tree PHP server testing.

Prefer testing the published preview URL after `.\publish.ps1`, because that matches the normal WebHatchery deployment shape.
