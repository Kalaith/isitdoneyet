<?php

declare(strict_types=1);

namespace App\Actions;

use App\Repositories\ProjectRepository;
use App\Services\TaskCompletionPolicy;
use App\Utils\Logger;
use Exception;
use InvalidArgumentException;

class ProjectActions
{
    private TaskCompletionPolicy $completionPolicy;

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly Logger $logger,
        ?TaskCompletionPolicy $completionPolicy = null
    ) {
        $this->completionPolicy = $completionPolicy ?? new TaskCompletionPolicy();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllProjects(string $ownerId): array
    {
        return $this->projects->getAllProjects($ownerId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProjectById(string $ownerId, int $id): ?array
    {
        return $this->projects->getProjectById($ownerId, $id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createProject(string $ownerId, array $data): array
    {
        try {
            $project = $this->projects->createProject($ownerId, $this->normalizeProjectInput($data, true));
            $this->logger->info('Project created', ['project_id' => $project['id'], 'owner_id' => $ownerId]);

            return $project;
        } catch (Exception $e) {
            $this->logger->error('Error creating project', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateProject(string $ownerId, int $id, array $data): ?array
    {
        try {
            $normalized = $this->normalizeProjectInput($data, false);

            if (($normalized['completed'] ?? false) === true) {
                $existingProject = $this->projects->getProjectById($ownerId, $id);
                if ($existingProject === null) {
                    return null;
                }

                $this->completionPolicy->assertCanMarkComplete($existingProject);
            }

            $project = $this->projects->updateProject($ownerId, $id, $normalized);
            if ($project !== null) {
                $this->logger->info('Project updated', ['project_id' => $project['id'], 'owner_id' => $ownerId]);
            }

            return $project;
        } catch (Exception $e) {
            $this->logger->error('Error updating project', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function markProjectComplete(string $ownerId, int $id): ?array
    {
        try {
            $existingProject = $this->projects->getProjectById($ownerId, $id);
            if ($existingProject === null) {
                return null;
            }

            $this->completionPolicy->assertCanMarkComplete($existingProject);

            $project = $this->projects->markProjectComplete($ownerId, $id);
            if ($project !== null) {
                $this->logger->info('Project marked complete', ['project_id' => $id, 'owner_id' => $ownerId]);
            }

            return $project;
        } catch (Exception $e) {
            $this->logger->error('Error marking project complete', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function deleteProject(string $ownerId, int $id): bool
    {
        try {
            $deleted = $this->projects->deleteProject($ownerId, $id);
            if ($deleted) {
                $this->logger->info('Project deleted', ['project_id' => $id, 'owner_id' => $ownerId]);
            }

            return $deleted;
        } catch (Exception $e) {
            $this->logger->error('Error deleting project', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function addSubtask(string $ownerId, int $parentId, array $data): ?array
    {
        try {
            $subtask = $this->projects->addSubtask($ownerId, $parentId, $this->normalizeProjectInput($data, true));
            if ($subtask !== null) {
                $this->logger->info('Subtask added', [
                    'parent_id' => $parentId,
                    'subtask_id' => $subtask['id'],
                    'owner_id' => $ownerId,
                ]);
            }

            return $subtask;
        } catch (Exception $e) {
            $this->logger->error('Error adding subtask', [
                'parent_id' => $parentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeProjectInput(array $data, bool $titleRequired): array
    {
        $normalized = [];

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                throw new InvalidArgumentException('Title is required.');
            }
            if (strlen($title) > 255) {
                throw new InvalidArgumentException('Title must be 255 characters or fewer.');
            }
            $normalized['title'] = $title;
        } elseif ($titleRequired) {
            throw new InvalidArgumentException('Title is required.');
        }

        if (array_key_exists('description', $data)) {
            $normalized['description'] = trim((string) $data['description']);
        } elseif ($titleRequired) {
            $normalized['description'] = '';
        }

        if (array_key_exists('completed', $data)) {
            $normalized['completed'] = filter_var($data['completed'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            if (!is_numeric($data['parent_id']) || (int) $data['parent_id'] < 1) {
                throw new InvalidArgumentException('Parent ID is invalid.');
            }
            $normalized['parent_id'] = (int) $data['parent_id'];
        }

        return $normalized;
    }
}
