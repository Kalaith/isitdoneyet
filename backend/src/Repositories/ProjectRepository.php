<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class ProjectRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllProjects(string $ownerId): array
    {
        $rows = $this->fetchOwnerProjects($ownerId);
        return $this->buildRootTree($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProjectById(string $ownerId, int $id): ?array
    {
        $rows = $this->fetchOwnerProjects($ownerId);
        $treeById = $this->buildTreeById($rows);

        return $treeById[$id] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createProject(string $ownerId, array $data): array
    {
        if (
            array_key_exists('parent_id', $data)
            && $data['parent_id'] !== null
            && $this->projectExists($ownerId, (int) $data['parent_id']) === false
        ) {
            throw new InvalidArgumentException('Parent project not found.');
        }

        $now = $this->now();
        $statement = $this->db->prepare(
            'INSERT INTO projects (owner_id, title, description, completed, parent_id, created_at, updated_at)
             VALUES (:owner_id, :title, :description, 0, :parent_id, :created_at, :updated_at)'
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'parent_id' => $data['parent_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $project = $this->getProjectById($ownerId, (int) $this->db->lastInsertId());
        if ($project === null) {
            throw new \RuntimeException('Project was created but could not be reloaded.');
        }

        return $project;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateProject(string $ownerId, int $id, array $data): ?array
    {
        if ($this->projectExists($ownerId, $id) === false) {
            return null;
        }

        $fields = [];
        $params = [
            'owner_id' => $ownerId,
            'id' => $id,
            'updated_at' => $this->now(),
        ];

        foreach (['title', 'description', 'completed'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $field === 'completed' ? (int) $data[$field] : $data[$field];
            }
        }

        if ($fields !== []) {
            $fields[] = 'updated_at = :updated_at';
            $statement = $this->db->prepare(
                'UPDATE projects SET ' . implode(', ', $fields) . '
                 WHERE id = :id AND owner_id = :owner_id'
            );
            $statement->execute($params);
        }

        return $this->getProjectById($ownerId, $id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function markProjectComplete(string $ownerId, int $id): ?array
    {
        return $this->updateProject($ownerId, $id, ['completed' => true]);
    }

    public function deleteProject(string $ownerId, int $id): bool
    {
        if ($this->projectExists($ownerId, $id) === false) {
            return false;
        }

        $ids = $this->collectOwnedSubtreeIds($ownerId, $id);
        if ($ids === []) {
            return false;
        }

        $deleted = 0;
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare('DELETE FROM projects WHERE id = :id AND owner_id = :owner_id');
            foreach ($ids as $projectId) {
                $statement->execute([
                    'id' => $projectId,
                    'owner_id' => $ownerId,
                ]);
                $deleted += $statement->rowCount();
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $deleted > 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function addSubtask(string $ownerId, int $parentId, array $data): ?array
    {
        if ($this->projectExists($ownerId, $parentId) === false) {
            return null;
        }

        return $this->createProject($ownerId, [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'parent_id' => $parentId,
        ]);
    }

    private function projectExists(string $ownerId, int $id): bool
    {
        $statement = $this->db->prepare('SELECT 1 FROM projects WHERE id = :id AND owner_id = :owner_id LIMIT 1');
        $statement->execute([
            'id' => $id,
            'owner_id' => $ownerId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Returns descendant ids before the root id so deletes do not require a cascade constraint.
     *
     * @return array<int, int>
     */
    private function collectOwnedSubtreeIds(string $ownerId, int $rootId): array
    {
        $statement = $this->db->prepare(
            'SELECT id, parent_id
             FROM projects
             WHERE owner_id = :owner_id
             ORDER BY parent_id IS NULL, parent_id ASC, id ASC'
        );
        $statement->execute(['owner_id' => $ownerId]);

        $knownIds = [];
        $childrenByParent = [];

        foreach ($statement->fetchAll() as $row) {
            $id = (int) $row['id'];
            $parentKey = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
            $knownIds[$id] = true;
            $childrenByParent[$parentKey][] = $id;
        }

        if (!isset($knownIds[$rootId])) {
            return [];
        }

        $ids = [];
        $visit = function (int $id) use (&$visit, &$ids, $childrenByParent): void {
            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $visit($childId);
            }

            $ids[] = $id;
        };

        $visit($rootId);

        return $ids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchOwnerProjects(string $ownerId): array
    {
        $statement = $this->db->prepare(
            'SELECT id, owner_id, title, description, completed, parent_id, created_at, updated_at
             FROM projects
             WHERE owner_id = :owner_id
             ORDER BY parent_id IS NOT NULL, created_at ASC, id ASC'
        );
        $statement->execute(['owner_id' => $ownerId]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $statement->fetchAll()
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildRootTree(array $rows): array
    {
        $treeById = $this->buildTreeById($rows);
        $roots = [];

        foreach ($treeById as $project) {
            if ($project['parent_id'] === null) {
                $roots[] = $project;
            }
        }

        usort(
            $roots,
            fn (array $left, array $right): int => strcmp((string) $right['created_at'], (string) $left['created_at'])
        );

        return $roots;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildTreeById(array $rows): array
    {
        $nodes = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $nodes[(int) $row['id']] = $row;
        }

        foreach ($nodes as $id => $node) {
            $parentId = $node['parent_id'];
            if ($parentId !== null && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$nodes[$id];
            }
        }
        unset($node);

        foreach (array_keys($nodes) as $id) {
            $nodes[$id] = $this->withProgress($nodes[$id]);
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    private function withProgress(array $project): array
    {
        $children = array_map(
            fn (array $child): array => $this->withProgress($child),
            $project['children']
        );

        $project['children'] = $children;
        $project['progress'] = $this->calculateProgress($project);

        unset($project['owner_id']);

        return $project;
    }

    /**
     * @param array<string, mixed> $project
     */
    private function calculateProgress(array $project): int
    {
        if ($project['children'] === []) {
            return $project['completed'] ? 100 : 0;
        }

        [$total, $completed] = $this->descendantCounts($project);

        return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }

    /**
     * @param array<string, mixed> $project
     * @return array{0: int, 1: int}
     */
    private function descendantCounts(array $project): array
    {
        $total = 0;
        $completed = 0;

        foreach ($project['children'] as $child) {
            $total++;
            if ($child['completed']) {
                $completed++;
            }

            [$childTotal, $childCompleted] = $this->descendantCounts($child);
            $total += $childTotal;
            $completed += $childCompleted;
        }

        return [$total, $completed];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'owner_id' => (string) $row['owner_id'],
            'title' => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'completed' => (bool) $row['completed'],
            'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
