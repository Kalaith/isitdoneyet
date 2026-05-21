<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProjectCompletionAnalyzer;
use PHPUnit\Framework\TestCase;

final class ProjectCompletionAnalyzerTest extends TestCase
{
    public function testAnalyzeReturnsLowestOpenTasksForIncompleteProject(): void
    {
        $status = (new ProjectCompletionAnalyzer())->analyze([
            'id' => 1,
            'title' => 'Ship agent API',
            'description' => '',
            'completed' => false,
            'parent_id' => null,
            'progress' => 50,
            'children' => [
                [
                    'id' => 2,
                    'title' => 'Define contract',
                    'description' => '',
                    'completed' => false,
                    'parent_id' => 1,
                    'progress' => 50,
                    'children' => [
                        [
                            'id' => 3,
                            'title' => 'Document response schema',
                            'description' => '',
                            'completed' => true,
                            'parent_id' => 2,
                            'progress' => 100,
                            'children' => [],
                        ],
                        [
                            'id' => 4,
                            'title' => 'Add examples',
                            'description' => '',
                            'completed' => false,
                            'parent_id' => 2,
                            'progress' => 0,
                            'children' => [],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('no', $status['answer']);
        self::assertFalse($status['is_done']);
        self::assertSame('work', $status['next_action']);
        self::assertSame(['total' => 4, 'completed' => 1, 'open' => 3], $status['counts']);
        self::assertSame([4], array_column($status['next_tasks'], 'id'));
        self::assertSame([], $status['reassessment_questions']);
        self::assertSame([1, 2], array_column($status['why_not'], 'id'));
    }

    public function testParentBecomesReassessmentQuestionWhenChildrenAreComplete(): void
    {
        $status = (new ProjectCompletionAnalyzer())->analyze([
            'id' => 1,
            'title' => 'Release app',
            'description' => '',
            'completed' => false,
            'parent_id' => null,
            'progress' => 100,
            'children' => [
                [
                    'id' => 2,
                    'title' => 'Run verification',
                    'description' => '',
                    'completed' => true,
                    'parent_id' => 1,
                    'progress' => 100,
                    'children' => [],
                ],
            ],
        ]);

        self::assertSame('no', $status['answer']);
        self::assertSame('reassess', $status['next_action']);
        self::assertSame([], $status['next_tasks']);
        self::assertSame([1], array_column($status['reassessment_questions'], 'id'));
        self::assertSame('Is it done yet?', $status['reassessment_questions'][0]['question']);
    }

    public function testCompleteTreeAnswersYes(): void
    {
        $status = (new ProjectCompletionAnalyzer())->analyze([
            'id' => 1,
            'title' => 'Release app',
            'description' => '',
            'completed' => true,
            'parent_id' => null,
            'progress' => 100,
            'children' => [],
        ]);

        self::assertSame('yes', $status['answer']);
        self::assertTrue($status['is_done']);
        self::assertSame('done', $status['next_action']);
        self::assertSame([], $status['next_tasks']);
        self::assertSame([], $status['reassessment_questions']);
        self::assertSame([], $status['why_not']);
    }
}
