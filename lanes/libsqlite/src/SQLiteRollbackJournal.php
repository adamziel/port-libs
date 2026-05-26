<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRollbackJournal
{
    /**
     * @param list<SQLiteRollbackJournalPage> $pages
     */
    public function __construct(
        public readonly SQLiteRollbackJournalHeader $header,
        public readonly array $pages,
        public readonly bool $checksumsValidated = false,
    ) {
    }

    public static function parse(string $bytes, bool $validateChecksums = false): self
    {
        $header = SQLiteRollbackJournalHeader::parse($bytes);
        if (strlen($bytes) < $header->sectorSize) {
            throw new \InvalidArgumentException('SQLite rollback journal is truncated before the first sector');
        }

        $recordSize = 8 + $header->pageSize;
        $recordBytes = strlen($bytes) - $header->sectorSize;
        if ($header->pageCount === SQLiteRollbackJournalHeader::UNKNOWN_PAGE_COUNT) {
            if ($recordBytes % $recordSize !== 0) {
                throw new \InvalidArgumentException('SQLite rollback journal has a truncated page record');
            }
            $declaredRecords = intdiv($recordBytes, $recordSize);
        } else {
            $declaredRecords = $header->pageCount;
        }

        $declaredRecordBytes = $declaredRecords * $recordSize;
        if ($declaredRecordBytes > $recordBytes) {
            throw new \InvalidArgumentException('SQLite rollback journal page count exceeds available page records');
        }
        $trailingBytes = substr($bytes, $header->sectorSize + $declaredRecordBytes);
        if ($trailingBytes !== '' && trim($trailingBytes, "\0") !== '') {
            throw new \InvalidArgumentException('SQLite rollback journal has non-zero trailing bytes after declared page records');
        }

        $pages = [];
        for ($offset = $header->sectorSize, $index = 1; $index <= $declaredRecords; $offset += $recordSize, $index++) {
            $page = SQLiteRollbackJournalPage::parse($index, substr($bytes, $offset, $recordSize), $header->pageSize);
            if ($validateChecksums && self::pageChecksum($page->pageImage, $header->checksumNonce) !== $page->checksum) {
                throw new \InvalidArgumentException("SQLite rollback journal page {$index} checksum does not match the page content");
            }
            $pages[] = $page;
        }

        return new self($header, $pages, $validateChecksums);
    }

    public static function fromFile(string $path, bool $validateChecksums = false): self
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \InvalidArgumentException("Unable to read SQLite rollback journal file: {$path}");
        }

        return self::parse($bytes, $validateChecksums);
    }

    public static function pageChecksum(string $pageImage, int $nonce): int
    {
        $checksum = $nonce & 0xffffffff;
        for ($offset = strlen($pageImage) - 200; $offset > 0; $offset -= 200) {
            $checksum = ($checksum + ord($pageImage[$offset])) & 0xffffffff;
        }

        return $checksum;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $pageImages = [];
        foreach ($this->pages as $page) {
            $pageImages[$page->pageNumber] = $page->pageImage;
        }
        ksort($pageImages);

        return $pageImages;
    }

    public function rollbackDatabaseImage(string $databaseBytes): string
    {
        $pageSize = $this->header->pageSize;
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite rollback journal requires a database image aligned to the page size');
        }

        $rollbackBytes = substr($databaseBytes . str_repeat("\0", max(0, ($this->header->initialDatabasePageCount * $pageSize) - strlen($databaseBytes))), 0, $this->header->initialDatabasePageCount * $pageSize);
        foreach ($this->pageImages() as $pageNumber => $pageImage) {
            if ($pageNumber > $this->header->initialDatabasePageCount) {
                continue;
            }
            $rollbackBytes = substr_replace($rollbackBytes, $pageImage, ($pageNumber - 1) * $pageSize, $pageSize);
        }

        return $rollbackBytes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'header' => $this->header->toArray(),
            'page_count' => $this->pageCount(),
            'checksums_validated' => $this->checksumsValidated,
            'page_numbers' => array_keys($this->pageImages()),
            'pages' => array_map(static fn (SQLiteRollbackJournalPage $page): array => $page->toArray(), $this->pages),
        ];
    }
}
