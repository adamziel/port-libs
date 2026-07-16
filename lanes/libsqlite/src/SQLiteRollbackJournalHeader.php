<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRollbackJournalHeader
{
    public const MAGIC = "\xd9\xd5\x05\xf9 \xa1c\xd7";
    public const UNKNOWN_PAGE_COUNT = 0xffffffff;

    public function __construct(
        public readonly int $pageCount,
        public readonly int $checksumNonce,
        public readonly int $initialDatabasePageCount,
        public readonly int $sectorSize,
        public readonly int $pageSize,
    ) {
        if ($pageCount < 0 || $pageCount > self::UNKNOWN_PAGE_COUNT) {
            throw new \InvalidArgumentException('SQLite rollback journal page count is invalid');
        }
        if ($initialDatabasePageCount < 0) {
            throw new \InvalidArgumentException('SQLite rollback journal initial database size is invalid');
        }
        if ($sectorSize < 512 || ($sectorSize & ($sectorSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite rollback journal sector size must be a power of two at least 512');
        }
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite rollback journal page size must be a power of two between 512 and 65536');
        }
    }

    public static function parse(string $bytes): self
    {
        return self::fromFields(self::parseFields($bytes), null);
    }

    public static function parseWithDatabasePageSize(string $bytes, int $databasePageSize): self
    {
        return self::fromFields(self::parseFields($bytes), $databasePageSize);
    }

    /**
     * @return array{pageCount:int,nonce:int,databasePages:int,sectorSize:int,pageSize:int}
     */
    private static function parseFields(string $bytes): array
    {
        if (strlen($bytes) < 28) {
            throw new \InvalidArgumentException('SQLite rollback journal header requires 28 bytes');
        }
        if (substr($bytes, 0, 8) !== self::MAGIC) {
            throw new \InvalidArgumentException('SQLite rollback journal header has an unsupported magic value');
        }

        /** @var array{pageCount:int,nonce:int,databasePages:int,sectorSize:int,pageSize:int} $fields */
        $fields = unpack('NpageCount/Nnonce/NdatabasePages/NsectorSize/NpageSize', substr($bytes, 8, 20));

        return $fields;
    }

    /**
     * @param array{pageCount:int,nonce:int,databasePages:int,sectorSize:int,pageSize:int} $fields
     */
    private static function fromFields(array $fields, ?int $databasePageSize): self
    {
        $pageSize = $fields['pageSize'];
        if ($pageSize === 0 && $databasePageSize !== null) {
            $pageSize = $databasePageSize;
        }

        return new self(
            $fields['pageCount'],
            $fields['nonce'],
            $fields['databasePages'],
            $fields['sectorSize'],
            $pageSize,
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'page_count' => $this->pageCount,
            'checksum_nonce' => $this->checksumNonce,
            'initial_database_page_count' => $this->initialDatabasePageCount,
            'sector_size' => $this->sectorSize,
            'page_size' => $this->pageSize,
        ];
    }
}
