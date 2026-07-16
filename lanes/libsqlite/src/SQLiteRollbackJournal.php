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

        return self::parseWithHeader($bytes, $header, $validateChecksums);
    }

    public static function parseWithDatabasePageSize(string $bytes, int $databasePageSize, bool $validateChecksums = false): self
    {
        $header = SQLiteRollbackJournalHeader::parseWithDatabasePageSize($bytes, $databasePageSize);

        return self::parseWithHeader($bytes, $header, $validateChecksums);
    }

    private static function parseWithHeader(string $bytes, SQLiteRollbackJournalHeader $header, bool $validateChecksums): self
    {
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

    /**
     * @return array{hot:bool,reason:string,journal_bytes:int,header_valid:bool,page_count:int|null,initial_database_page_count:int|null,requires_super_journal:bool,super_journal_exists:bool|null,database_reserved_lock:bool}
     */
    public static function hotJournalCandidate(string $bytes, bool $databaseReservedLock = false, bool $requiresSuperJournal = false, ?bool $superJournalExists = null): array
    {
        $journalBytes = strlen($bytes);
        $base = [
            'hot' => false,
            'reason' => '',
            'journal_bytes' => $journalBytes,
            'header_valid' => false,
            'page_count' => null,
            'initial_database_page_count' => null,
            'requires_super_journal' => $requiresSuperJournal,
            'super_journal_exists' => $superJournalExists,
            'database_reserved_lock' => $databaseReservedLock,
        ];

        if ($journalBytes <= 512) {
            $base['reason'] = 'journal_too_small';
            return $base;
        }
        if ($databaseReservedLock) {
            $base['reason'] = 'database_has_reserved_lock';
            return $base;
        }

        try {
            $header = SQLiteRollbackJournalHeader::parse($bytes);
        } catch (\InvalidArgumentException) {
            $base['reason'] = 'invalid_journal_header';
            return $base;
        }

        $base['header_valid'] = true;
        $base['page_count'] = $header->pageCount;
        $base['initial_database_page_count'] = $header->initialDatabasePageCount;
        $base['requires_super_journal'] = $requiresSuperJournal;

        if ($base['requires_super_journal'] && $superJournalExists !== true) {
            $base['reason'] = $superJournalExists === false ? 'missing_super_journal' : 'super_journal_status_unknown';
            return $base;
        }

        $base['hot'] = true;
        $base['reason'] = 'hot_journal_recovery_required';

        return $base;
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

    public function toBytes(): string
    {
        $header = self::headerBytes($this->header);
        $bytes = str_pad($header, $this->header->sectorSize, "\0");

        foreach ($this->pages as $page) {
            $bytes .= pack('N', $page->pageNumber) . $page->pageImage . pack('N', $page->checksum);
        }

        return $bytes;
    }

    private static function headerBytes(SQLiteRollbackJournalHeader $header): string
    {
        return SQLiteRollbackJournalHeader::MAGIC . pack(
            'N*',
            $header->pageCount,
            $header->checksumNonce,
            $header->initialDatabasePageCount,
            $header->sectorSize,
            $header->pageSize
        );
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
     * @return array{initial_database_page_count:int,final_database_bytes:int,pages:list<array{page_number:int,database_offset:int,applied:bool,reason:string}>}
     */
    public function recoveryPlan(string $databaseBytes): array
    {
        $pageSize = $this->header->pageSize;
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite rollback journal recovery plan requires a database image aligned to the page size');
        }

        $pages = [];
        foreach ($this->pages as $page) {
            $applied = $page->pageNumber <= $this->header->initialDatabasePageCount;
            $pages[] = [
                'page_number' => $page->pageNumber,
                'database_offset' => ($page->pageNumber - 1) * $pageSize,
                'applied' => $applied,
                'reason' => $applied ? 'restored_from_journal' : 'beyond_initial_database_size',
            ];
        }

        return [
            'initial_database_page_count' => $this->header->initialDatabasePageCount,
            'final_database_bytes' => $this->header->initialDatabasePageCount * $pageSize,
            'pages' => $pages,
        ];
    }

    /**
     * @return array{hot_journal:array{hot:bool,reason:string,journal_bytes:int,header_valid:bool,page_count:int|null,initial_database_page_count:int|null,requires_super_journal:bool,super_journal_exists:bool|null,database_reserved_lock:bool},recovered:bool,reason:string,database_bytes:string,final_database_bytes:int,journal_action:string,recovery_plan:array{initial_database_page_count:int,final_database_bytes:int,pages:list<array{page_number:int,database_offset:int,applied:bool,reason:string}>}|null}
     */
    public function hotJournalRecoveryResult(
        string $databaseBytes,
        string $journalBytes,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        $hotJournal = self::hotJournalCandidate($journalBytes, $databaseReservedLock, $requiresSuperJournal, $superJournalExists);
        if (!$hotJournal['hot'] && $hotJournal['reason'] === 'invalid_journal_header') {
            $hotJournal = $this->hotJournalCandidateWithDatabasePageSize(
                $journalBytes,
                $databaseReservedLock,
                $requiresSuperJournal,
                $superJournalExists
            );
        }
        if (!$hotJournal['hot']) {
            return [
                'hot_journal' => $hotJournal,
                'recovered' => false,
                'reason' => $hotJournal['reason'],
                'database_bytes' => $databaseBytes,
                'final_database_bytes' => strlen($databaseBytes),
                'journal_action' => 'preserve_journal',
                'recovery_plan' => null,
            ];
        }

        $recoveryPlan = $this->recoveryPlan($databaseBytes);
        $recoveredBytes = $this->rollbackDatabaseImage($databaseBytes);

        return [
            'hot_journal' => $hotJournal,
            'recovered' => true,
            'reason' => 'hot_journal_recovered',
            'database_bytes' => $recoveredBytes,
            'final_database_bytes' => strlen($recoveredBytes),
            'journal_action' => 'delete_journal_after_recovery',
            'recovery_plan' => $recoveryPlan,
        ];
    }

    /**
     * @return array{hot_journal:array{hot:bool,reason:string,journal_bytes:int,header_valid:bool,page_count:int|null,initial_database_page_count:int|null,requires_super_journal:bool,super_journal_exists:bool|null,database_reserved_lock:bool},recovered:bool,reason:string,error:string|null,database_bytes:string,final_database_bytes:int,journal_action:string,applied_page_count:int,checksum_valid_page_count:int,first_checksum_mismatch_index:int|null,recovery_plan:array{initial_database_page_count:int,final_database_bytes:int,pages:list<array{index:int,page_number:int,database_offset:int,checksum_valid:bool,applied:bool,reason:string}>}|null}
     */
    public function hotJournalChecksumRecoveryResult(
        string $databaseBytes,
        string $journalBytes,
        bool $readOnlyConnection = false,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        $hotJournal = self::hotJournalCandidate($journalBytes, $databaseReservedLock, $requiresSuperJournal, $superJournalExists);
        if (!$hotJournal['hot'] && $hotJournal['reason'] === 'invalid_journal_header') {
            $hotJournal = $this->hotJournalCandidateWithDatabasePageSize(
                $journalBytes,
                $databaseReservedLock,
                $requiresSuperJournal,
                $superJournalExists
            );
        }
        if (!$hotJournal['hot']) {
            return [
                'hot_journal' => $hotJournal,
                'recovered' => false,
                'reason' => $hotJournal['reason'],
                'error' => null,
                'database_bytes' => $databaseBytes,
                'final_database_bytes' => strlen($databaseBytes),
                'journal_action' => 'preserve_journal',
                'applied_page_count' => 0,
                'checksum_valid_page_count' => 0,
                'first_checksum_mismatch_index' => null,
                'recovery_plan' => null,
            ];
        }
        if ($readOnlyConnection) {
            return [
                'hot_journal' => $hotJournal,
                'recovered' => false,
                'reason' => 'readonly_hot_journal_requires_write',
                'error' => 'attempt to write a readonly database',
                'database_bytes' => $databaseBytes,
                'final_database_bytes' => strlen($databaseBytes),
                'journal_action' => 'preserve_journal',
                'applied_page_count' => 0,
                'checksum_valid_page_count' => 0,
                'first_checksum_mismatch_index' => null,
                'recovery_plan' => null,
            ];
        }

        $pageSize = $this->header->pageSize;
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite rollback journal checksum recovery requires a database image aligned to the page size');
        }

        $recoveredBytes = substr(
            $databaseBytes . str_repeat("\0", max(0, ($this->header->initialDatabasePageCount * $pageSize) - strlen($databaseBytes))),
            0,
            $this->header->initialDatabasePageCount * $pageSize
        );
        $pages = [];
        $checksumValid = 0;
        $applied = 0;
        $firstMismatch = null;

        foreach ($this->pages as $page) {
            $valid = self::pageChecksum($page->pageImage, $this->header->checksumNonce) === $page->checksum;
            $applies = $valid && $firstMismatch === null && $page->pageNumber <= $this->header->initialDatabasePageCount;
            if (!$valid && $firstMismatch === null) {
                $firstMismatch = $page->index;
            }
            if ($valid && $firstMismatch === null) {
                $checksumValid++;
            }
            if ($applies) {
                $recoveredBytes = substr_replace($recoveredBytes, $page->pageImage, ($page->pageNumber - 1) * $pageSize, $pageSize);
                $applied++;
            }

            $pages[] = [
                'index' => $page->index,
                'page_number' => $page->pageNumber,
                'database_offset' => ($page->pageNumber - 1) * $pageSize,
                'checksum_valid' => $valid,
                'applied' => $applies,
                'reason' => $applies
                    ? 'restored_from_journal'
                    : ($valid ? 'after_checksum_mismatch_or_beyond_initial_size' : 'checksum_mismatch_stops_recovery'),
            ];
        }

        $reason = $firstMismatch === null
            ? 'hot_journal_recovered'
            : ($firstMismatch === 1 ? 'checksum_mismatch_before_first_page' : 'checksum_mismatch_after_prefix_recovery');

        return [
            'hot_journal' => $hotJournal,
            'recovered' => true,
            'reason' => $reason,
            'error' => null,
            'database_bytes' => $recoveredBytes,
            'final_database_bytes' => strlen($recoveredBytes),
            'journal_action' => 'delete_journal_after_recovery',
            'applied_page_count' => $applied,
            'checksum_valid_page_count' => $checksumValid,
            'first_checksum_mismatch_index' => $firstMismatch,
            'recovery_plan' => [
                'initial_database_page_count' => $this->header->initialDatabasePageCount,
                'final_database_bytes' => $this->header->initialDatabasePageCount * $pageSize,
                'pages' => $pages,
            ],
        ];
    }

    /**
     * @return array{hot:bool,reason:string,journal_bytes:int,header_valid:bool,page_count:int|null,initial_database_page_count:int|null,requires_super_journal:bool,super_journal_exists:bool|null,database_reserved_lock:bool}
     */
    private function hotJournalCandidateWithDatabasePageSize(
        string $bytes,
        bool $databaseReservedLock,
        bool $requiresSuperJournal,
        ?bool $superJournalExists
    ): array {
        $journalBytes = strlen($bytes);
        $base = [
            'hot' => false,
            'reason' => 'invalid_journal_header',
            'journal_bytes' => $journalBytes,
            'header_valid' => false,
            'page_count' => null,
            'initial_database_page_count' => null,
            'requires_super_journal' => $requiresSuperJournal,
            'super_journal_exists' => $superJournalExists,
            'database_reserved_lock' => $databaseReservedLock,
        ];

        if ($journalBytes <= 512) {
            $base['reason'] = 'journal_too_small';
            return $base;
        }
        if ($databaseReservedLock) {
            $base['reason'] = 'database_has_reserved_lock';
            return $base;
        }

        try {
            $journal = self::parseWithDatabasePageSize($bytes, $this->header->pageSize, false);
        } catch (\InvalidArgumentException) {
            return $base;
        }
        if (!$this->matchesParsedJournal($journal)) {
            return $base;
        }

        $base['header_valid'] = true;
        $base['page_count'] = $journal->header->pageCount;
        $base['initial_database_page_count'] = $journal->header->initialDatabasePageCount;

        if ($base['requires_super_journal'] && $superJournalExists !== true) {
            $base['reason'] = $superJournalExists === false ? 'missing_super_journal' : 'super_journal_status_unknown';
            return $base;
        }

        $base['hot'] = true;
        $base['reason'] = 'hot_journal_recovery_required';

        return $base;
    }

    private function matchesParsedJournal(self $journal): bool
    {
        if ($journal->header->toArray() !== $this->header->toArray()) {
            return false;
        }
        if (count($journal->pages) !== count($this->pages)) {
            return false;
        }

        foreach ($journal->pages as $index => $page) {
            $currentPage = $this->pages[$index];
            if (
                $page->index !== $currentPage->index
                || $page->pageNumber !== $currentPage->pageNumber
                || $page->pageImage !== $currentPage->pageImage
                || $page->checksum !== $currentPage->checksum
            ) {
                return false;
            }
        }

        return true;
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
