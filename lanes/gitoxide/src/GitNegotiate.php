<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitNegotiate
{
    private const INITIAL_WINDOW_SIZE = 16;
    private const PIPESAFE_FLUSH = 32;
    private const LARGE_FLUSH = 16384;
    private const SHA1_ENTRY_BYTES = 56;
    private const SHA256_ENTRY_EXTRA_BYTES = 16;

    private function __construct()
    {
    }

    public static function windowSize(bool $transportIsStateless, ?int $previousWindowSize): int
    {
        if ($previousWindowSize === null) {
            return self::INITIAL_WINDOW_SIZE;
        }
        if ($previousWindowSize < 0) {
            throw new \InvalidArgumentException('Window size must not be negative');
        }

        if ($transportIsStateless) {
            if ($previousWindowSize < self::LARGE_FLUSH) {
                return $previousWindowSize * 2;
            }

            return intdiv($previousWindowSize * 11, 10);
        }

        if ($previousWindowSize < self::PIPESAFE_FLUSH) {
            return $previousWindowSize * 2;
        }

        return $previousWindowSize + self::PIPESAFE_FLUSH;
    }

    public static function estimatedCommitEntrySize(): int
    {
        return self::SHA1_ENTRY_BYTES + self::SHA256_ENTRY_EXTRA_BYTES;
    }

    public static function commitEntrySizeOk(int $actualSize, int $expected64BitSize): bool
    {
        return PHP_INT_SIZE >= 8
            ? $actualSize === $expected64BitSize
            : $actualSize <= $expected64BitSize;
    }
}
