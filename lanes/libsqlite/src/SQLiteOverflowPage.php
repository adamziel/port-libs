<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOverflowPage
{
    /**
     * @param callable(int): string $pageReader
     * @return list<int>
     */
    public static function pageNumbersFromChain(
        int $firstPageNumber,
        int $overflowPayloadLength,
        callable $pageReader,
        int $pageSize = 512,
        ?int $usableSize = null,
    ): array {
        $usableSize ??= $pageSize;
        self::validatePageShape($pageSize, $usableSize);

        if ($overflowPayloadLength < 1) {
            throw new \InvalidArgumentException('SQLite overflow chain payload length must be positive');
        }
        if ($firstPageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow chain requires a valid first overflow page');
        }

        $pageCount = self::requiredPageCount($overflowPayloadLength, $pageSize, $usableSize);
        $pageNumbers = [];
        $seen = [];
        $pageNumber = $firstPageNumber;

        for ($index = 0; $index < $pageCount; $index++) {
            if ($pageNumber < 2) {
                throw new \InvalidArgumentException('SQLite overflow chain ended before the expected payload length');
            }
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite overflow chain loops at page {$pageNumber}");
            }

            $page = $pageReader($pageNumber);
            if (!is_string($page) || strlen($page) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite overflow page {$pageNumber} image length does not match page size");
            }

            $seen[$pageNumber] = true;
            $pageNumbers[] = $pageNumber;
            $pageNumber = self::readUInt32($page, 0);
        }

        if ($pageNumber !== 0) {
            throw new \InvalidArgumentException('SQLite overflow chain has trailing pages beyond the expected payload length');
        }

        return $pageNumbers;
    }

    /**
     * @return list<int>
     */
    public static function pageNumbersFromDatabase(
        SQLiteDatabase $database,
        int $firstPageNumber,
        int $overflowPayloadLength,
    ): array {
        return self::pageNumbersFromChain(
            $firstPageNumber,
            $overflowPayloadLength,
            static fn (int $pageNumber): string => $database->page($pageNumber),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
    }

    /**
     * @param callable(int): string $pageReader
     * @return list<array{current_page:int,next_page:int,payload_bytes:int,terminal:bool}>
     */
    public static function chainLinksFromChain(
        int $firstPageNumber,
        int $overflowPayloadLength,
        callable $pageReader,
        int $pageSize = 512,
        ?int $usableSize = null,
    ): array {
        $usableSize ??= $pageSize;
        self::validatePageShape($pageSize, $usableSize);

        $pageNumbers = self::pageNumbersFromChain($firstPageNumber, $overflowPayloadLength, $pageReader, $pageSize, $usableSize);
        $links = [];
        $remainingBytes = $overflowPayloadLength;
        $payloadCapacity = $usableSize - 4;
        foreach ($pageNumbers as $index => $pageNumber) {
            $page = $pageReader($pageNumber);
            if (!is_string($page) || strlen($page) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite overflow page {$pageNumber} image length does not match page size");
            }

            $nextPage = self::readUInt32($page, 0);
            $payloadBytes = min($payloadCapacity, $remainingBytes);
            $remainingBytes -= $payloadBytes;
            $links[] = [
                'current_page' => $pageNumber,
                'next_page' => $nextPage,
                'payload_bytes' => $payloadBytes,
                'terminal' => $index === count($pageNumbers) - 1,
            ];
        }

        return $links;
    }

    /**
     * @return list<array{current_page:int,next_page:int,payload_bytes:int,terminal:bool}>
     */
    public static function chainLinksFromDatabase(
        SQLiteDatabase $database,
        int $firstPageNumber,
        int $overflowPayloadLength,
    ): array {
        return self::chainLinksFromChain(
            $firstPageNumber,
            $overflowPayloadLength,
            static fn (int $pageNumber): string => $database->page($pageNumber),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
    }

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

    private static function readUInt32(string $bytes, int $offset): int
    {
        return unpack('N', substr($bytes, $offset, 4))[1];
    }
}
