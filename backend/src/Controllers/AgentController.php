<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\AgentActions;
use App\Utils\Logger;
use Exception;
use InvalidArgumentException;

final class AgentController
{
    public function __construct(
        private readonly AgentActions $agentActions,
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function checkDone(string $ownerId, int $projectId): array
    {
        return $this->handle(function () use ($ownerId, $projectId): array {
            $status = $this->agentActions->checkDone($ownerId, $projectId);
            if ($status === null) {
                return $this->error('Project not found', 404);
            }

            return $this->ok($status);
        }, 'Failed to check project completion');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function nextTasks(string $ownerId, int $projectId): array
    {
        return $this->handle(function () use ($ownerId, $projectId): array {
            $nextTasks = $this->agentActions->nextTasks($ownerId, $projectId);
            if ($nextTasks === null) {
                return $this->error('Project not found', 404);
            }

            return $this->ok($nextTasks);
        }, 'Failed to fetch next tasks');
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function recordBreakdown(string $ownerId, int $projectId, array $data): array
    {
        return $this->handle(function () use ($ownerId, $projectId, $data): array {
            $breakdown = $this->agentActions->recordBreakdown($ownerId, $projectId, $data);
            if ($breakdown === null) {
                return $this->error('Project not found', 404);
            }

            return $this->ok($breakdown, 201);
        }, 'Failed to record project breakdown');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function completeTask(string $ownerId, int $projectId): array
    {
        return $this->handle(function () use ($ownerId, $projectId): array {
            $completed = $this->agentActions->completeTask($ownerId, $projectId);
            if ($completed === null) {
                return $this->error('Project not found', 404);
            }

            return $this->ok($completed);
        }, 'Failed to complete task');
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function createToken(string $ownerId, array $data): array
    {
        return $this->handle(function () use ($ownerId, $data): array {
            $token = $this->agentActions->createToken($ownerId, $data);
            if ($token === null) {
                return $this->error('Assigned project not found', 404);
            }

            return $this->ok($token, 201);
        }, 'Failed to create agent token');
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
        } catch (Exception $exception) {
            $this->logger->error($failureMessage . ': ' . $exception->getMessage());
            return $this->error($failureMessage, 500);
        }
    }

    /**
     * @param mixed $data
     * @return array{status: int, body: array<string, mixed>}
     */
    private function ok(mixed $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => true,
                'data' => $data,
            ],
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
