<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

use InvalidArgumentException;
use RuntimeException;

final class GitSecurity
{
    public const TRUST_REDUCED = 'reduced';
    public const TRUST_FULL = 'full';

    public const PERMISSION_FORBID = 'forbid';
    public const PERMISSION_DENY = 'deny';
    public const PERMISSION_ALLOW = 'allow';

    private const TRUST_ORDER = [
        self::TRUST_REDUCED => 0,
        self::TRUST_FULL => 1,
    ];

    private function __construct()
    {
    }

    public static function trustCompare(string $left, string $right): int
    {
        return self::trustRank($left) <=> self::trustRank($right);
    }

    public static function permissionIsAllowed(string $permission): bool
    {
        self::assertPermission($permission);

        return $permission === self::PERMISSION_ALLOW;
    }

    public static function permissionCheck(string $permission, mixed $resource): mixed
    {
        return match ($permission) {
            self::PERMISSION_ALLOW => $resource,
            self::PERMISSION_DENY => null,
            self::PERMISSION_FORBID => throw new RuntimeException(
                'Not allowed to handle resource ' . self::debugValue($resource) . ': permission denied'
            ),
            default => throw new InvalidArgumentException("Invalid Git security permission: {$permission}"),
        };
    }

    public static function permissionCheckOpt(string $permission, mixed $resource): mixed
    {
        return match ($permission) {
            self::PERMISSION_ALLOW => $resource,
            self::PERMISSION_DENY, self::PERMISSION_FORBID => null,
            default => throw new InvalidArgumentException("Invalid Git security permission: {$permission}"),
        };
    }

    private static function trustRank(string $trust): int
    {
        if (!array_key_exists($trust, self::TRUST_ORDER)) {
            throw new InvalidArgumentException("Invalid Git security trust level: {$trust}");
        }

        return self::TRUST_ORDER[$trust];
    }

    private static function assertPermission(string $permission): void
    {
        if (
            $permission !== self::PERMISSION_ALLOW
            && $permission !== self::PERMISSION_DENY
            && $permission !== self::PERMISSION_FORBID
        ) {
            throw new InvalidArgumentException("Invalid Git security permission: {$permission}");
        }
    }

    private static function debugValue(mixed $value): string
    {
        if (is_string($value)) {
            return '"' . addcslashes($value, "\\\"\n\r\t") . '"';
        }

        return var_export($value, true);
    }
}
