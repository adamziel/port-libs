<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalFrame
{
    public function __construct(
        public readonly int $index,
        public readonly int $pageNumber,
        public readonly int $databasePageCountAfterCommit,
        public readonly int $salt1,
        public readonly int $salt2,
        public readonly int $checksum1,
        public readonly int $checksum2,
        public readonly string $pageImage,
    ) {
        if ($index < 1) {
            throw new \InvalidArgumentException('SQLite WAL frame index must be one-based');
        }
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL frame page number must be one-based');
        }
    }

    public static function parse(int $index, string $bytes, int $pageSize): self
    {
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL frame page size is invalid');
        }
        if (strlen($bytes) !== 24 + $pageSize) {
            throw new \InvalidArgumentException('SQLite WAL frame length does not match the configured page size');
        }

        /** @var array{page:int,commit:int,salt1:int,salt2:int,checksum1:int,checksum2:int} $fields */
        $fields = unpack('Npage/Ncommit/Nsalt1/Nsalt2/Nchecksum1/Nchecksum2', substr($bytes, 0, 24));

        return new self(
            $index,
            $fields['page'],
            $fields['commit'],
            $fields['salt1'],
            $fields['salt2'],
            $fields['checksum1'],
            $fields['checksum2'],
            substr($bytes, 24),
        );
    }

    public function isCommitFrame(): bool
    {
        return $this->databasePageCountAfterCommit > 0;
    }

    /**
     * @return array<string, int|bool>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'page_number' => $this->pageNumber,
            'database_page_count_after_commit' => $this->databasePageCountAfterCommit,
            'salt1' => $this->salt1,
            'salt2' => $this->salt2,
            'checksum1' => $this->checksum1,
            'checksum2' => $this->checksum2,
            'is_commit_frame' => $this->isCommitFrame(),
        ];
    }
}
