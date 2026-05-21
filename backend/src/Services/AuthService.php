<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Exceptions\AuthException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthService
{
    private const JWT_LEEWAY_SECONDS = 300;

    /**
     * @param array<string, mixed> $server
     * @return array{id: string, claims: array<string, mixed>}
     */
    public function requireUser(array $server): array
    {
        $authorization = $this->authorizationHeader($server);
        if (!preg_match('/Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw $this->unauthorized('Authentication is required.');
        }

        $jwtSecret = Env::required('JWT_SECRET');

        try {
            JWT::$leeway = self::JWT_LEEWAY_SECONDS;
            $decoded = JWT::decode($matches[1], new Key($jwtSecret, 'HS256'));
        } catch (\Throwable) {
            throw $this->unauthorized('Invalid or expired token.');
        }

        $claims = json_decode((string) json_encode($decoded), true);
        if (!is_array($claims)) {
            throw $this->unauthorized('Invalid token claims.');
        }

        $userId = $this->claimString($claims, 'user_id')
            ?? $this->claimString($claims, 'sub')
            ?? $this->claimString($claims, 'id');

        if ($userId === null) {
            throw $this->unauthorized('Token is missing a user identifier.');
        }

        return [
            'id' => $userId,
            'claims' => $claims,
        ];
    }

    /**
     * @param array<string, mixed> $server
     */
    private function authorizationHeader(array $server): string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization'] as $key) {
            if (isset($server[$key]) && is_string($server[$key])) {
                return trim($server[$key]);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function claimString(array $claims, string $key): ?string
    {
        if (!isset($claims[$key])) {
            return null;
        }

        $value = $claims[$key];
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function unauthorized(string $message): AuthException
    {
        return new AuthException($message, 401, [
            'login_url' => Env::required('WEBHATCHERY_LOGIN_URL'),
        ]);
    }
}
