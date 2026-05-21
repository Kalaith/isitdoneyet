<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use Firebase\JWT\JWT;

final class AgentTokenService
{
    /**
     * @return array<string, mixed>
     */
    public function createToken(
        string $ownerId,
        string $agentName,
        ?int $assignedProjectId,
        int $expiresInSeconds
    ): array {
        $issuedAt = time();
        $expiresAt = $issuedAt + $expiresInSeconds;

        $payload = [
            'user_id' => $ownerId,
            'sub' => $ownerId,
            'actor_type' => 'agent',
            'agent_name' => $agentName,
            'scope' => 'isitdoneyet:agent',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
        ];

        if ($assignedProjectId !== null) {
            $payload['assigned_project_id'] = $assignedProjectId;
        }

        return [
            'token' => JWT::encode($payload, Env::required('JWT_SECRET'), 'HS256'),
            'token_type' => 'Bearer',
            'owner_id' => $ownerId,
            'actor_type' => 'agent',
            'agent_name' => $agentName,
            'assigned_project_id' => $assignedProjectId,
            'scope' => 'isitdoneyet:agent',
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
        ];
    }
}
