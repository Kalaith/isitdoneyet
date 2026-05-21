<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Env
{
    public static function required(string $name, bool $allowEmpty = false): string
    {
        if (!self::exists($name)) {
            throw new RuntimeException("{$name} environment variable is required.");
        }

        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        $value = is_string($value) || is_numeric($value) ? trim((string) $value) : '';

        if (!$allowEmpty && $value === '') {
            throw new RuntimeException("{$name} environment variable is required.");
        }

        return $value;
    }

    public static function requiredInt(string $name): int
    {
        $value = self::required($name);

        if (!ctype_digit($value)) {
            throw new RuntimeException("{$name} environment variable must be an integer.");
        }

        return (int) $value;
    }

    private static function exists(string $name): bool
    {
        return array_key_exists($name, $_ENV)
            || array_key_exists($name, $_SERVER)
            || getenv($name) !== false;
    }
}
