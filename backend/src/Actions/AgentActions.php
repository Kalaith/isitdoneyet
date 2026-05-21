<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\AgentTokenService;
use App\Services\ProjectCompletionAnalyzer;
use InvalidArgumentException;

final class AgentActions
{
    private AgentTokenService $tokens;

    public function __construct(
        private readonly ProjectActions $projects,
        private readonly ProjectCompletionAnalyzer $analyzer,
        ?AgentTokenService $tokens = null
    ) {
        $this->tokens = $tokens ?? new AgentTokenService();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function checkDone(string $ownerId, int $projectId): ?array
    {
        $project = $this->projects->getProjectById($ownerId, $projectId);
        if ($project === null) {
            return null;
        }

        return $this->analyzer->analyze($project);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function nextTasks(string $ownerId, int $projectId): ?array
    {
        $status = $this->checkDone($ownerId, $projectId);
        if ($status === null) {
            return null;
        }

        return [
            'project_id' => $status['project_id'],
            'answer' => $status['answer'],
            'is_done' => $status['is_done'],
            'next_action' => $status['next_action'],
            'next_tasks' => $status['next_tasks'],
            'reassessment_questions' => $status['reassessment_questions'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function recordBreakdown(string $ownerId, int $projectId, array $data): ?array
    {
        if ($this->projects->getProjectById($ownerId, $projectId) === null) {
            return null;
        }

        $reason = $this->normalizedReason($data);
        $tasks = $this->normalizedTasks($data);
        $created = [];

        foreach ($tasks as $task) {
            $subtask = $this->projects->addSubtask($ownerId, $projectId, $task);
            if ($subtask === null) {
                return null;
            }

            $created[] = $this->taskSummary($subtask);
        }

        $project = $this->projects->getProjectById($ownerId, $projectId);
        if ($project === null) {
            return null;
        }

        return [
            'project_id' => $projectId,
            'question' => 'Is it done yet?',
            'answer' => 'no',
            'reason' => $reason,
            'created_tasks' => $created,
            'status' => $this->analyzer->analyze($project),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function completeTask(string $ownerId, int $projectId): ?array
    {
        $project = $this->projects->markProjectComplete($ownerId, $projectId);
        if ($project === null) {
            return null;
        }

        return [
            'completed_task' => $this->taskSummary($project),
            'status' => $this->analyzer->analyze($project),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function createToken(string $ownerId, array $data): ?array
    {
        $agentName = $this->normalizedAgentName($data);
        $assignedProjectId = $this->normalizedAssignedProjectId($data);
        $expiresInSeconds = $this->normalizedExpiresInSeconds($data);

        if (
            $assignedProjectId !== null
            && $this->projects->getProjectById($ownerId, $assignedProjectId) === null
        ) {
            return null;
        }

        return $this->tokens->createToken($ownerId, $agentName, $assignedProjectId, $expiresInSeconds);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizedReason(array $data): string
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Reason is required.');
        }

        return $reason;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{title: string, description: string}>
     */
    private function normalizedTasks(array $data): array
    {
        if (!isset($data['tasks']) || !is_array($data['tasks']) || $data['tasks'] === []) {
            throw new InvalidArgumentException('At least one task is required.');
        }

        $tasks = [];
        foreach ($data['tasks'] as $index => $task) {
            if (!is_array($task)) {
                throw new InvalidArgumentException(sprintf('Task %d is invalid.', $index + 1));
            }

            $title = trim((string) ($task['title'] ?? ''));
            if ($title === '') {
                throw new InvalidArgumentException(sprintf('Task %d title is required.', $index + 1));
            }
            if (strlen($title) > 255) {
                throw new InvalidArgumentException(
                    sprintf('Task %d title must be 255 characters or fewer.', $index + 1)
                );
            }

            $tasks[] = [
                'title' => $title,
                'description' => trim((string) ($task['description'] ?? '')),
            ];
        }

        return $tasks;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizedAgentName(array $data): string
    {
        $agentName = trim((string) ($data['agent_name'] ?? ''));
        if ($agentName === '') {
            throw new InvalidArgumentException('Agent name is required.');
        }
        if (strlen($agentName) > 80) {
            throw new InvalidArgumentException('Agent name must be 80 characters or fewer.');
        }

        return $agentName;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizedAssignedProjectId(array $data): ?int
    {
        if (!array_key_exists('project_id', $data) || $data['project_id'] === null || $data['project_id'] === '') {
            return null;
        }

        if (!is_numeric($data['project_id']) || (int) $data['project_id'] < 1) {
            throw new InvalidArgumentException('Project ID is invalid.');
        }

        return (int) $data['project_id'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizedExpiresInSeconds(array $data): int
    {
        if (!isset($data['expires_in_seconds']) || !is_numeric($data['expires_in_seconds'])) {
            throw new InvalidArgumentException('Token expiry is required.');
        }

        $expiresInSeconds = (int) $data['expires_in_seconds'];
        if ($expiresInSeconds < 300 || $expiresInSeconds > 604800) {
            throw new InvalidArgumentException('Token expiry must be between 300 and 604800 seconds.');
        }

        return $expiresInSeconds;
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function taskSummary(array $task): array
    {
        return [
            'id' => (int) $task['id'],
            'title' => (string) $task['title'],
            'description' => (string) ($task['description'] ?? ''),
            'parent_id' => $task['parent_id'] === null ? null : (int) $task['parent_id'],
            'completed' => (bool) $task['completed'],
            'progress' => (int) ($task['progress'] ?? 0),
        ];
    }
}
