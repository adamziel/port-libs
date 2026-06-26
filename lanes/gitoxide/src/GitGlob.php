<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitGlob
{
    public const NO_SUB_DIR = 1 << 0;
    public const ENDS_WITH = 1 << 1;
    public const MUST_BE_DIR = 1 << 2;
    public const NEGATIVE = 1 << 3;
    public const ABSOLUTE = 1 << 4;

    private const GLOB_CHARACTERS = '*?[\\';

    public function __construct(
        public readonly string $text,
        public readonly int $mode,
        public readonly ?int $firstWildcardPosition,
    ) {
    }

    public static function parse(string $pattern): ?self
    {
        return self::parsePattern($pattern, true);
    }

    public static function fromBytesWithoutNegation(string $pattern): ?self
    {
        return self::parsePattern($pattern, false);
    }

    public function hasMode(int $mode): bool
    {
        return ($this->mode & $mode) === $mode;
    }

    private static function parsePattern(string $pattern, bool $mayAlter): ?self
    {
        if ($pattern === '') {
            return null;
        }

        $mode = 0;
        if ($mayAlter) {
            if ($pattern[0] === '!') {
                $mode |= self::NEGATIVE;
                $pattern = substr($pattern, 1);
            } elseif ($pattern[0] === '\\') {
                $second = $pattern[1] ?? null;
                if ($second === '!' || $second === '#') {
                    $pattern = substr($pattern, 1);
                }
            }
        }

        if (self::isAsciiWhitespaceOnly($pattern)) {
            return null;
        }

        if (($pattern[0] ?? null) === '/') {
            $mode |= self::ABSOLUTE;
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '/')) {
            $mode |= self::MUST_BE_DIR;
            $pattern = substr($pattern, 0, -1);
        }

        if (!str_contains($pattern, '/')) {
            $mode |= self::NO_SUB_DIR;
        }

        if (($pattern[0] ?? null) === '*' && self::firstWildcardPosition(substr($pattern, 1)) === null) {
            $mode |= self::ENDS_WITH;
        }

        return new self($pattern, $mode, self::firstWildcardPosition($pattern));
    }

    private static function firstWildcardPosition(string $pattern): ?int
    {
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            if (str_contains(self::GLOB_CHARACTERS, $pattern[$i])) {
                return $i;
            }
        }

        return null;
    }

    private static function isAsciiWhitespaceOnly(string $pattern): bool
    {
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($pattern[$i]);
            if ($byte !== 32 && ($byte < 9 || $byte > 13)) {
                return false;
            }
        }

        return true;
    }
}
