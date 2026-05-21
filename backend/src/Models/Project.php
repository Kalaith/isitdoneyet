<?php

declare(strict_types=1);

namespace App\Models;

final class Project
{
    /**
     * @param array<int, Project> $children
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $description,
        public readonly bool $completed,
        public readonly ?int $parentId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly int $progress,
        public readonly array $children = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'completed' => $this->completed,
            'parent_id' => $this->parentId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'progress' => $this->progress,
            'children' => array_map(
                fn (Project $project): array => $project->toArray(),
                $this->children
            ),
        ];
    }
}
