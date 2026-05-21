<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TaskCompletionPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TaskCompletionPolicyTest extends TestCase
{
    public function testLeafTaskCanBeMarkedComplete(): void
    {
        $policy = new TaskCompletionPolicy();

        $policy->assertCanMarkComplete([
            'id' => 1,
            'completed' => false,
            'children' => [],
        ]);

        self::assertTrue(true);
    }

    public function testTaskWithCompletedDescendantsCanBeMarkedComplete(): void
    {
        $policy = new TaskCompletionPolicy();

        $policy->assertCanMarkComplete([
            'id' => 1,
            'completed' => false,
            'children' => [
                [
                    'id' => 2,
                    'completed' => true,
                    'children' => [
                        [
                            'id' => 3,
                            'completed' => true,
                            'children' => [],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertTrue(true);
    }

    public function testTaskWithIncompleteChildCannotBeMarkedComplete(): void
    {
        $policy = new TaskCompletionPolicy();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot mark task complete while child tasks are incomplete.');

        $policy->assertCanMarkComplete([
            'id' => 1,
            'completed' => false,
            'children' => [
                [
                    'id' => 2,
                    'completed' => false,
                    'children' => [],
                ],
            ],
        ]);
    }

    public function testTaskWithIncompleteNestedDescendantCannotBeMarkedComplete(): void
    {
        $policy = new TaskCompletionPolicy();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot mark task complete while child tasks are incomplete.');

        $policy->assertCanMarkComplete([
            'id' => 1,
            'completed' => false,
            'children' => [
                [
                    'id' => 2,
                    'completed' => true,
                    'children' => [
                        [
                            'id' => 3,
                            'completed' => false,
                            'children' => [],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
