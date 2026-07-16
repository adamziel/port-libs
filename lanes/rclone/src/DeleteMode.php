<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class DeleteMode
{
    public const OFF = 'off';
    public const BEFORE = 'before';
    public const DURING = 'during';
    public const AFTER = 'after';
    public const ONLY = 'only';
    public const DEFAULT = self::AFTER;

    public static function normalize(string $mode): string
    {
        return match (strtolower($mode)) {
            self::OFF => self::OFF,
            self::BEFORE => self::BEFORE,
            self::DURING => self::DURING,
            self::AFTER => self::AFTER,
            self::ONLY => self::ONLY,
            default => throw new \InvalidArgumentException("unknown delete mode {$mode}"),
        };
    }
}
