<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\AgentActions;
use App\Actions\ProjectActions;
use App\Services\ProjectCompletionAnalyzer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

final class AgentActionsTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['JWT_SECRET'] = 'test-secret-with-enough-length-for-hmac';
    }

    public function testRecordBreakdownCreatesSubtasksAndReturnsUpdatedStatus(): void
    {
        $projects = new class () extends ProjectActions {
            /** @var array<int, array<string, mixed>> */
            public array $projects = [
                1 => [
                    'id' => 1,
                    'title' => 'Build agent API',
                    'description' => '',
                    'completed' => false,
                    'parent_id' => null,
                    'progress' => 0,
                    'children' => [],
                ],
            ];

            private int $nextId = 2;

            public function __construct()
            {
            }

            /**
             * @return array<string, mixed>|null
             */
            public function getProjectById(string $ownerId, int $id): ?array
            {
                return $this->projects[$id] ?? null;
            }

            /**
             * @param array<string, mixed> $data
             * @return array<string, mixed>|null
             */
            public function addSubtask(string $ownerId, int $parentId, array $data): ?array
            {
                if (!isset($this->projects[$parentId])) {
                    return null;
                }

                $subtask = [
                    'id' => $this->nextId++,
                    'title' => (string) $data['title'],
                    'description' => (string) ($data['description'] ?? ''),
                    'completed' => false,
                    'parent_id' => $parentId,
                    'progress' => 0,
                    'children' => [],
                ];

                $this->projects[$subtask['id']] = $subtask;
                $this->projects[$parentId]['children'][] = $subtask;

                return $subtask;
            }
        };

        $result = (new AgentActions($projects, new ProjectCompletionAnalyzer()))->recordBreakdown(
            'owner-1',
            1,
            [
                'reason' => 'The API still needs a contract and verification.',
                'tasks' => [
                    ['title' => 'Define agent response schema'],
                    ['title' => 'Add API verification tests'],
                ],
            ]
        );

        self::assertNotNull($result);
        self::assertSame('no', $result['answer']);
        self::assertSame([2, 3], array_column($result['created_tasks'], 'id'));
        self::assertSame([2, 3], array_column($result['status']['next_tasks'], 'id'));
        self::assertSame(['total' => 3, 'completed' => 0, 'open' => 3], $result['status']['counts']);
    }

    public function testCreateTokenUsesOwnerIdAndAssignedProject(): void
    {
        $projects = new class () extends ProjectActions {
            public function __construct()
            {
            }

            /**
             * @return array<string, mixed>|null
             */
            public function getProjectById(string $ownerId, int $id): ?array
            {
                if ($ownerId !== 'owner-1' || $id !== 9) {
                    return null;
                }

                return [
                    'id' => 9,
                    'title' => 'Assigned project',
                    'description' => '',
                    'completed' => false,
                    'parent_id' => null,
                    'progress' => 0,
                    'children' => [],
                ];
            }
        };

        $result = (new AgentActions($projects, new ProjectCompletionAnalyzer()))->createToken(
            'owner-1',
            [
                'agent_name' => 'Codex',
                'project_id' => 9,
                'expires_in_seconds' => 900,
            ]
        );

        self::assertNotNull($result);
        self::assertSame('owner-1', $result['owner_id']);
        self::assertSame('agent', $result['actor_type']);
        self::assertSame('Codex', $result['agent_name']);
        self::assertSame(9, $result['assigned_project_id']);
        self::assertSame('isitdoneyet:agent', $result['scope']);

        $claims = JWT::decode((string) $result['token'], new Key($_ENV['JWT_SECRET'], 'HS256'));
        self::assertSame('owner-1', $claims->user_id);
        self::assertSame('agent', $claims->actor_type);
        self::assertSame('Codex', $claims->agent_name);
        self::assertSame(9, $claims->assigned_project_id);
    }

    public function testCreateTokenRejectsMissingAssignedProject(): void
    {
        $projects = new class () extends ProjectActions {
            public function __construct()
            {
            }

            /**
             * @return array<string, mixed>|null
             */
            public function getProjectById(string $ownerId, int $id): ?array
            {
                return null;
            }
        };

        $result = (new AgentActions($projects, new ProjectCompletionAnalyzer()))->createToken(
            'owner-1',
            [
                'agent_name' => 'Codex',
                'project_id' => 9,
                'expires_in_seconds' => 900,
            ]
        );

        self::assertNull($result);
    }
}
