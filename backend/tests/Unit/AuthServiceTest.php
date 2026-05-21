<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\AuthException;
use App\Services\AuthService;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['JWT_SECRET'] = 'test-secret-with-enough-length-for-hmac';
        $_ENV['WEBHATCHERY_LOGIN_URL'] = 'https://webhatchery.au/login';
    }

    public function testRequireUserRejectsMissingBearerTokenWithLoginUrl(): void
    {
        $this->expectException(AuthException::class);

        try {
            (new AuthService())->requireUser([]);
        } catch (AuthException $exception) {
            self::assertSame(401, $exception->statusCode());
            self::assertSame('https://webhatchery.au/login', $exception->context()['login_url']);
            throw $exception;
        }
    }

    public function testRequireUserReturnsWebHatcheryUserIdFromBearerToken(): void
    {
        $token = JWT::encode([
            'sub' => 'subject-id',
            'user_id' => 'frontpage-user-123',
            'role' => 'user',
        ], $_ENV['JWT_SECRET'], 'HS256');

        $user = (new AuthService())->requireUser([
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame('frontpage-user-123', $user['id']);
        self::assertSame('user', $user['claims']['role']);
    }

    public function testRequireUserAcceptsSmallClockSkew(): void
    {
        $token = JWT::encode([
            'sub' => 'subject-id',
            'user_id' => 'frontpage-user-123',
            'iat' => time() + 120,
        ], $_ENV['JWT_SECRET'], 'HS256');

        $user = (new AuthService())->requireUser([
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame('frontpage-user-123', $user['id']);
    }

    public function testRequireUserFailsFastWhenJwtSecretIsMissing(): void
    {
        unset($_ENV['JWT_SECRET'], $_SERVER['JWT_SECRET']);
        putenv('JWT_SECRET');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET environment variable is required.');

        (new AuthService())->requireUser([
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ]);
    }
}
