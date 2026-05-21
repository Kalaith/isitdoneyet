<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\ProjectActions;
use App\Utils\Logger;
use Exception;
use InvalidArgumentException;

class ProjectController
{
    public function __construct(
        private readonly ProjectActions $projectActions,
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function getAllProjects(string $ownerId): array
    {
        return $this->handle(
            fn (): array => $this->ok($this->projectActions->getAllProjects($ownerId)),
            'Failed to fetch projects'
        );
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function getProjectById(string $ownerId, int $id): array
    {
        return $this->handle(function () use ($ownerId, $id): array {
            $project = $this->projectActions->getProjectById($ownerId, $id);

            if (!$project) {
                return $this->error('Project not found', 404);
            }

            return $this->ok($project);
        }, 'Failed to fetch project');
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function createProject(string $ownerId, array $data): array
    {
        return $this->handle(
            fn (): array => $this->ok($this->projectActions->createProject($ownerId, $data), 201),
            'Failed to create project'
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function updateProject(string $ownerId, int $id, array $data): array
    {
        return $this->handle(function () use ($ownerId, $id, $data): array {
            $project = $this->projectActions->updateProject($ownerId, $id, $data);

            if (!$project) {
                return $this->error('Project not found', 404);
            }

            return $this->ok($project);
        }, 'Failed to update project');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function markProjectComplete(string $ownerId, int $id): array
    {
        return $this->handle(function () use ($ownerId, $id): array {
            $project = $this->projectActions->markProjectComplete($ownerId, $id);

            if (!$project) {
                return $this->error('Project not found', 404);
            }

            return $this->ok(['completed_projects' => [$project]]);
        }, 'Failed to mark project complete');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function deleteProject(string $ownerId, int $id): array
    {
        return $this->handle(function () use ($ownerId, $id): array {
            $deleted = $this->projectActions->deleteProject($ownerId, $id);

            if (!$deleted) {
                return $this->error('Project not found', 404);
            }

            return $this->ok(null, 200, 'Project deleted successfully');
        }, 'Failed to delete project');
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function addSubtask(string $ownerId, int $parentId, array $data): array
    {
        return $this->handle(function () use ($ownerId, $parentId, $data): array {
            $subtask = $this->projectActions->addSubtask($ownerId, $parentId, $data);

            if (!$subtask) {
                return $this->error('Parent project not found', 404);
            }

            return $this->ok($subtask, 201);
        }, 'Failed to add subtask');
    }

    /**
     * @param callable(): array{status: int, body: array<string, mixed>} $operation
     * @return array{status: int, body: array<string, mixed>}
     */
    private function handle(callable $operation, string $failureMessage): array
    {
        try {
            return $operation();
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 400);
        } catch (Exception $e) {
            $this->logger->error($failureMessage . ': ' . $e->getMessage());
            return $this->error($failureMessage, 500);
        }
    }

    /**
     * @param mixed $data
     * @return array{status: int, body: array<string, mixed>}
     */
    private function ok(mixed $data, int $status = 200, ?string $message = null): array
    {
        $body = ['success' => true];

        if ($data !== null) {
            $body['data'] = $data;
        }

        if ($message !== null) {
            $body['message'] = $message;
        }

        return [
            'status' => $status,
            'body' => $body,
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function error(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'message' => $message,
            ],
        ];
    }
}
