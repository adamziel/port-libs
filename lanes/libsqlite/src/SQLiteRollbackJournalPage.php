<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRollbackJournalPage
{
    public function __construct(
        public readonly int $index,
        public readonly int $pageNumber,
        public readonly string $pageImage,
        public readonly int $checksum,
    ) {
        if ($index < 1) {
            throw new \InvalidArgumentException('SQLite rollback journal page index must be one-based');
        }
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite rollback journal page number must be one-based');
        }
    }

    public static function parse(int $index, string $bytes, int $pageSize): self
    {
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite rollback journal page size is invalid');
        }
        if (strlen($bytes) !== 8 + $pageSize) {
            throw new \InvalidArgumentException('SQLite rollback journal page record length does not match the configured page size');
        }

        /** @var array{page:int,checksum:int} $fields */
        $fields = unpack('Npage/Nchecksum', substr($bytes, 0, 4) . substr($bytes, 4 + $pageSize, 4));

        return new self($index, $fields['page'], substr($bytes, 4, $pageSize), $fields['checksum']);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'page_number' => $this->pageNumber,
            'checksum' => $this->checksum,
        ];
    }
}
