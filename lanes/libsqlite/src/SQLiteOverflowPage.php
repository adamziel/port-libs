<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOverflowPage
{
    /**
     * @return list<string>
     */
    public static function encodeChain(
        string $payload,
        int $firstPageNumber,
        int $pageSize = 512,
        ?int $usableSize = null,
    ): array {
        $usableSize ??= $pageSize;
        self::validatePageShape($pageSize, $usableSize);

        if ($payload === '') {
            return [];
        }
        if ($firstPageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow chains must start at page 2 or later');
        }

        $chunkSize = $usableSize - 4;
        $pageCount = self::requiredPageCount(strlen($payload), $pageSize, $usableSize);
        if ($firstPageNumber + $pageCount - 1 > 0xffffffff) {
            throw new \InvalidArgumentException('SQLite overflow chain exceeds the 32-bit page number range');
        }

        $pages = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $nextPage = $i === $pageCount - 1 ? 0 : $firstPageNumber + $i + 1;
            $chunk = substr($payload, $i * $chunkSize, $chunkSize);
            $page = str_repeat("\0", $pageSize);
            $page = substr_replace($page, pack('N', $nextPage), 0, 4);
            $page = substr_replace($page, $chunk, 4, strlen($chunk));
            $pages[] = $page;
        }

        return $pages;
    }

    public static function requiredPageCount(int $payloadLength, int $pageSize = 512, ?int $usableSize = null): int
    {
        $usableSize ??= $pageSize;
        self::validatePageShape($pageSize, $usableSize);

        if ($payloadLength < 0) {
            throw new \InvalidArgumentException('SQLite overflow payload length cannot be negative');
        }
        if ($payloadLength === 0) {
            return 0;
        }

        return intdiv($payloadLength + ($usableSize - 5), $usableSize - 4);
    }

    private static function validatePageShape(int $pageSize, int $usableSize): void
    {
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite page size must be a power of two between 512 and 65536 bytes');
        }
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite overflow usable size is outside the page');
        }
    }
}
