<?php

declare(strict_types=1);

namespace App\Services;

final class ProjectCompletionAnalyzer
{
    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    public function analyze(array $project): array
    {
        $counts = $this->countTasks($project);
        $nextTasks = $this->nextTasks($project);
        $reassessmentQuestions = $this->reassessmentQuestions($project);
        $blockers = $this->blockers($project);
        $isDone = $this->isTreeComplete($project);

        return [
            'project_id' => (int) $project['id'],
            'project_title' => (string) $project['title'],
            'question' => 'Is it done yet?',
            'answer' => $isDone ? 'yes' : 'no',
            'is_done' => $isDone,
            'progress' => (int) ($project['progress'] ?? 0),
            'counts' => $counts,
            'next_action' => $this->nextAction($isDone, $nextTasks, $reassessmentQuestions),
            'why_not' => $blockers,
            'next_tasks' => array_map(
                fn (array $task): array => $this->taskSummary($task),
                $nextTasks
            ),
            'reassessment_questions' => array_map(
                fn (array $task): array => [
                    ...$this->taskSummary($task),
                    'question' => 'Is it done yet?',
                    'reason' => 'All known subtasks are complete; reassess before completing this task.',
                ],
                $reassessmentQuestions
            ),
        ];
    }

    /**
     * @param array<string, mixed> $project
     */
    private function isTreeComplete(array $project): bool
    {
        if (!$this->isComplete($project)) {
            return false;
        }

        foreach ($this->children($project) as $child) {
            if (!$this->isTreeComplete($child)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $project
     * @return array{total: int, completed: int, open: int}
     */
    private function countTasks(array $project): array
    {
        $total = 1;
        $completed = $this->isComplete($project) ? 1 : 0;

        foreach ($this->children($project) as $child) {
            $childCounts = $this->countTasks($child);
            $total += $childCounts['total'];
            $completed += $childCounts['completed'];
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'open' => $total - $completed,
        ];
    }

    /**
     * Returns concrete incomplete leaves only. Parents with complete children
     * are questions to reassess, not tasks to blindly complete.
     *
     * @param array<string, mixed> $project
     * @return array<int, array<string, mixed>>
     */
    private function nextTasks(array $project): array
    {
        if ($this->isComplete($project)) {
            $next = [];
            foreach ($this->children($project) as $child) {
                array_push($next, ...$this->nextTasks($child));
            }
            return $next;
        }

        $children = $this->children($project);
        if ($children === []) {
            return [$project];
        }

        if ($this->childrenAreComplete($children)) {
            return [];
        }

        $next = [];
        foreach ($children as $child) {
            array_push($next, ...$this->nextTasks($child));
        }

        return $next;
    }

    /**
     * Returns incomplete non-leaf tasks whose known subtasks are complete.
     * These should trigger another "is it done yet?" evaluation.
     *
     * @param array<string, mixed> $project
     * @return array<int, array<string, mixed>>
     */
    private function reassessmentQuestions(array $project): array
    {
        $questions = [];
        $children = $this->children($project);

        if (!$this->isComplete($project) && $children !== [] && $this->childrenAreComplete($children)) {
            $questions[] = $project;
        }

        foreach ($children as $child) {
            array_push($questions, ...$this->reassessmentQuestions($child));
        }

        return $questions;
    }

    /**
     * @param array<int, array<string, mixed>> $nextTasks
     * @param array<int, array<string, mixed>> $reassessmentQuestions
     */
    private function nextAction(bool $isDone, array $nextTasks, array $reassessmentQuestions): string
    {
        if ($isDone) {
            return 'done';
        }

        if ($nextTasks !== []) {
            return 'work';
        }

        if ($reassessmentQuestions !== []) {
            return 'reassess';
        }

        return 'breakdown';
    }

    /**
     * @param array<int, array<string, mixed>> $children
     */
    private function childrenAreComplete(array $children): bool
    {
        foreach ($children as $child) {
            if (!$this->isTreeComplete($child)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $project
     * @return array<int, array<string, mixed>>
     */
    private function blockers(array $project): array
    {
        $blockers = [];

        if (!$this->isComplete($project)) {
            $children = $this->children($project);
            $blockers[] = [
                ...$this->taskSummary($project),
                'reason' => $children !== [] && $this->childrenAreComplete($children)
                    ? 'This task must be reassessed before it can be marked complete.'
                    : 'This project has not been marked complete.',
            ];
        }

        foreach ($this->children($project) as $child) {
            if (!$this->isTreeComplete($child)) {
                $blockers[] = [
                    ...$this->taskSummary($child),
                    'reason' => 'This subtask is not complete.',
                ];
            }
        }

        return $blockers;
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
            'completed' => $this->isComplete($task),
            'progress' => (int) ($task['progress'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $project
     * @return array<int, array<string, mixed>>
     */
    private function children(array $project): array
    {
        return is_array($project['children'] ?? null) ? $project['children'] : [];
    }

    /**
     * @param array<string, mixed> $project
     */
    private function isComplete(array $project): bool
    {
        return (bool) ($project['completed'] ?? false);
    }
}
