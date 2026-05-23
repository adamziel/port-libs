<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteHeader
{
    public function __construct(
        public readonly int $pageSize,
        public readonly int $writeVersion,
        public readonly int $readVersion,
        public readonly int $reservedSpace,
        public readonly int $databaseSizePages,
        public readonly int $firstFreelistTrunkPage,
        public readonly int $freelistPageCount,
        public readonly int $largestRootBtreePage,
        public readonly int $textEncoding,
        public readonly int $incrementalVacuum,
    ) {
    }

    public static function parse(string $firstPage): self
    {
        if (strlen($firstPage) < 100) {
            throw new \InvalidArgumentException('SQLite database header requires at least 100 bytes');
        }
        if (substr($firstPage, 0, 16) !== "SQLite format 3\0") {
            throw new \InvalidArgumentException('Missing SQLite format 3 magic header');
        }

        $rawPageSize = unpack('n', substr($firstPage, 16, 2))[1];
        $pageSize = $rawPageSize === 1 ? 65536 : $rawPageSize;
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException("Invalid SQLite page size: {$pageSize}");
        }

        return new self(
            $pageSize,
            ord($firstPage[18]),
            ord($firstPage[19]),
            ord($firstPage[20]),
            unpack('N', substr($firstPage, 28, 4))[1],
            unpack('N', substr($firstPage, 32, 4))[1],
            unpack('N', substr($firstPage, 36, 4))[1],
            unpack('N', substr($firstPage, 52, 4))[1],
            unpack('N', substr($firstPage, 56, 4))[1],
            unpack('N', substr($firstPage, 64, 4))[1],
        );
    }
}
