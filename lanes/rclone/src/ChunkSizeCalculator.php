<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ChunkSizeCalculator
{
    public const MEBI = 1_048_576;

    /**
     * Calculate the minimum upload chunk size needed to stay within a
     * provider part limit, matching rclone fs/chunksize.Calculator.
     */
    public static function calculate(int $size, int $maxParts, int $defaultChunkSize): int
    {
        if ($maxParts <= 0) {
            throw new \InvalidArgumentException('max parts must be greater than zero');
        }
        if ($defaultChunkSize <= 0) {
            throw new \InvalidArgumentException('default chunk size must be greater than zero');
        }

        if ($size < 0) {
            return $defaultChunkSize;
        }

        $requiredChunks = intdiv($size, $defaultChunkSize);
        if (
            $requiredChunks < $maxParts
            || ($requiredChunks === $maxParts && $size % $defaultChunkSize === 0)
        ) {
            return $defaultChunkSize;
        }

        $minimumChunk = intdiv($size, $maxParts);
        $remainder = $minimumChunk % self::MEBI;
        if ($remainder !== 0) {
            $minimumChunk += self::MEBI - $remainder;
        }

        if (intdiv($size, $minimumChunk) === $maxParts && $size % $maxParts !== 0) {
            $minimumChunk += self::MEBI;
        }

        return $minimumChunk;
    }

    public static function partsFor(int $size, int $chunkSize): ?int
    {
        if ($chunkSize <= 0) {
            throw new \InvalidArgumentException('chunk size must be greater than zero');
        }
        if ($size < 0) {
            return null;
        }

        return intdiv($size, $chunkSize) + ($size % $chunkSize === 0 ? 0 : 1);
    }

    /**
     * @return list<array{offset: int, length: int}>
     */
    public static function partRanges(int $size, int $chunkSize): array
    {
        if ($size < 0) {
            throw new \InvalidArgumentException('cannot plan fixed ranges for an unknown-size stream');
        }
        if ($chunkSize <= 0) {
            throw new \InvalidArgumentException('chunk size must be greater than zero');
        }

        $ranges = [];
        for ($offset = 0; $offset < $size; $offset += $chunkSize) {
            $ranges[] = [
                'offset' => $offset,
                'length' => min($chunkSize, $size - $offset),
            ];
        }

        return $ranges;
    }

    public static function mebi(int $size): int
    {
        return $size * self::MEBI;
    }
}
