<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class TaskCompletionPolicy
{
    /**
     * @param array<string, mixed> $task
     */
    public function assertCanMarkComplete(array $task): void
    {
        if ($this->hasIncompleteDescendant($task)) {
            throw new InvalidArgumentException(
                'Cannot mark task complete while child tasks are incomplete.'
            );
        }
    }

    /**
     * @param array<string, mixed> $task
     */
    private function hasIncompleteDescendant(array $task): bool
    {
        foreach ($this->children($task) as $child) {
            if (!$this->isComplete($child) || $this->hasIncompleteDescendant($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $task
     * @return array<int, array<string, mixed>>
     */
    private function children(array $task): array
    {
        return is_array($task['children'] ?? null) ? $task['children'] : [];
    }

    /**
     * @param array<string, mixed> $task
     */
    private function isComplete(array $task): bool
    {
        return (bool) ($task['completed'] ?? false);
    }
}
