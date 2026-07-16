<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class DeduplicateMode
{
    public const INTERACTIVE = 'interactive';
    public const SKIP = 'skip';
    public const FIRST = 'first';
    public const NEWEST = 'newest';
    public const OLDEST = 'oldest';
    public const RENAME = 'rename';
    public const LARGEST = 'largest';
    public const SMALLEST = 'smallest';
    public const LIST = 'list';

    public static function normalize(string $mode): string
    {
        return match (strtolower($mode)) {
            self::INTERACTIVE => self::INTERACTIVE,
            self::SKIP => self::SKIP,
            self::FIRST => self::FIRST,
            self::NEWEST => self::NEWEST,
            self::OLDEST => self::OLDEST,
            self::RENAME => self::RENAME,
            self::LARGEST => self::LARGEST,
            self::SMALLEST => self::SMALLEST,
            self::LIST => self::LIST,
            default => throw new \InvalidArgumentException('unknown mode for dedupe "' . $mode . '"'),
        };
    }
}
