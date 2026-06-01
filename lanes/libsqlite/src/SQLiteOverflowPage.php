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
        $pageCount = self::requiredPageCount(strlen($payload), $pageSize, $usableSize);
        if ($firstPageNumber + $pageCount - 1 > 0xffffffff) {
            throw new \InvalidArgumentException('SQLite overflow chain exceeds the 32-bit page number range');
        }

        if ($pageCount === 0) {
            return [];
        }

        return array_values(self::encodeChainAtPages(
            $payload,
            range($firstPageNumber, $firstPageNumber + $pageCount - 1),
            $pageSize,
            $usableSize,
        ));
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<int, string>
     */
    public static function encodeChainAtPages(
        string $payload,
        array $pageNumbers,
        int $pageSize = 512,
        ?int $usableSize = null,
    ): array {
        $usableSize ??= $pageSize;
        self::validatePageShape($pageSize, $usableSize);

        $pageCount = self::requiredPageCount(strlen($payload), $pageSize, $usableSize);
        if ($pageCount === 0) {
            if ($pageNumbers !== []) {
                throw new \InvalidArgumentException('SQLite empty overflow payloads cannot reserve overflow page numbers');
            }

            return [];
        }
        if (count($pageNumbers) !== $pageCount) {
            throw new \InvalidArgumentException("SQLite overflow payload requires exactly {$pageCount} page numbers");
        }

        $seen = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 2) {
                throw new \InvalidArgumentException('SQLite overflow page numbers must be integers at page 2 or later');
            }
            if ($pageNumber > 0xffffffff) {
                throw new \InvalidArgumentException('SQLite overflow page numbers must fit in 32 bits');
            }
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite overflow page {$pageNumber} appears more than once in the chain");
            }
            $seen[$pageNumber] = true;
        }

        $chunkSize = $usableSize - 4;
        $pages = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageNumber = $pageNumbers[$i];
            $nextPage = $i === $pageCount - 1 ? 0 : $pageNumbers[$i + 1];
            $chunk = substr($payload, $i * $chunkSize, $chunkSize);
            $page = str_repeat("\0", $pageSize);
            $page = substr_replace($page, pack('N', $nextPage), 0, 4);
            $page = substr_replace($page, $chunk, 4, strlen($chunk));
            $pages[$pageNumber] = $page;
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
