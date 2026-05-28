<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext177Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,header_signature?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,header_signature?:string}> $nextReads
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $recoveredPages,
        array $readerCache,
        array $nextReads,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 requires database path, master journal path, and source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 database bytes must be page-size aligned');
        }
        if ($recoveredPages === [] || $readerCache === [] || $nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 requires recovered pages, reader cache, and reads');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next177 current master journal does not reference the database journal');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $recoveredPages = self::normalizeImages($recoveredPages, $pageSize, 'recovered', false);
        $readerCache = self::normalizeReaderCache($readerCache, $pageSize);
        $nextReads = self::normalizeReads($nextReads);

        $operations = [[
            'op' => 'read_current_master_journal_for_header_ticket_reader_cache_next177',
            'path' => $masterJournalPath,
            'members' => $members,
        ]];

        foreach ($recoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next177 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-recovered-header-ticket-source-next177',
            ];
            $operations[] = [
                'op' => 'restore_master_journal_page_before_header_ticket_reader_cache_next177',
                'page_number' => $pageNumber,
            ];
        }

        if (!isset($database[1])) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 requires page 1 for header ticketing');
        }

        $header = self::headerTicket($database[1]['image'], $pageSize);
        $nextSourceId = 'master-reader-header-ticket:' . substr(hash('sha256', $databasePath . '|' . implode('|', $members) . '|' . $header['signature']), 0, 24);
        $nextEpoch = $currentEpoch + 1;

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next177 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_recovered_header_ticket';
            } elseif ($entry['header_signature'] !== $header['signature']) {
                $reason = 'reader_cache_header_signature_mismatch_after_master_recovery';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch_after_header_ticket';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch_after_header_ticket';
            } elseif ($entry['pinned'] && $entry['image'] !== $currentImage) {
                $reason = 'pinned_reader_cache_image_predates_header_ticket';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = [
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'label' => $entry['label'],
                    'reason' => $reason,
                    'header_signature_before' => $entry['header_signature'],
                    'header_signature_current' => $header['signature'],
                    'dirty' => $entry['dirty'],
                    'pinned' => $entry['pinned'],
                ];
                $operations[] = [
                    'op' => 'invalidate_reader_cache_after_header_ticket_recheck_next177',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $refreshed[$pageNumber] = [
                    'image' => $currentImage,
                    'source' => 'reader-cache-refreshed-header-ticket-current-source-next177',
                    'reader_id' => $entry['reader_id'],
                ];
                $operations[] = [
                    'op' => 'refresh_reader_cache_from_header_ticket_current_source_next177',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            } else {
                $retained[$pageNumber] = [
                    'image' => $entry['image'],
                    'source' => 'reader-cache-retained-header-ticket-current-source-next177',
                    'reader_id' => $entry['reader_id'],
                ];
                $operations[] = [
                    'op' => 'retain_reader_cache_after_header_ticket_current_source_next177',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'label' => $entry['label'],
                'admitted' => $reason === null,
                'reason' => $reason ?? ($entry['image'] === $currentImage ? 'reader_cache_matches_current_header_ticket_source' : 'reader_cache_refreshed_from_current_header_ticket_source'),
                'header_signature_before' => $entry['header_signature'],
                'header_signature_current' => $header['signature'],
                'header_signature_matches' => $entry['header_signature'] === $header['signature'],
                'source_id_before' => $entry['source_id'],
                'epoch_before' => $entry['epoch'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'shared' => $entry['shared'],
                'cache_prefix' => self::label($entry['image']),
                'current_prefix' => self::label($currentImage),
                'image_matches_current_source' => $entry['image'] === $currentImage,
            ];
        }

        ksort($retained, SORT_NUMERIC);
        ksort($refreshed, SORT_NUMERIC);
        ksort($invalidated, SORT_NUMERIC);

        $reads = [];
        $reopenReaders = [];
        foreach ($nextReads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next177 read page {$pageNumber} is outside the database image");
            }
            $ticketCurrent = $read['source_id'] === $currentSourceId
                && $read['epoch'] === $currentEpoch
                && $read['header_signature'] === $header['signature'];
            $cache = $ticketCurrent ? ($retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null) : null;
            if (!$ticketCurrent || isset($invalidated[$pageNumber])) {
                $reopenReaders[$read['reader_id']] = $read['reader_id'];
            }
            $image = is_array($cache) ? $cache['image'] : $database[$pageNumber]['image'];
            $reads[] = [
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => is_array($cache),
                'source' => is_array($cache) ? $cache['source'] : $database[$pageNumber]['source'],
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'header_signature' => $header['signature'],
                'prefix' => self::label($image),
                'digest' => hash('sha256', $image),
            ];
            $operations[] = [
                'op' => is_array($cache) ? 'next177_reader_cache_hit_after_header_ticket' : 'next177_reader_cache_miss_after_header_ticket',
                'page_number' => $pageNumber,
                'reader_id' => $read['reader_id'],
                'ticket_current' => $ticketCurrent,
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next177',
            'reason' => 'master_journal_recovery_rechecks_page_one_header_ticket_before_reader_cache_reuse',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'current_members' => $members,
            'page_size' => $pageSize,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'next_source' => ['id' => $nextSourceId, 'epoch' => $nextEpoch],
            'header_ticket' => $header,
            'recovered_page_numbers' => array_keys($recoveredPages),
            'retained_cache_page_numbers' => array_keys($retained),
            'refreshed_cache_page_numbers' => array_keys($refreshed),
            'invalidated_cache_page_numbers' => array_keys($invalidated),
            'invalidated_entries' => array_values($invalidated),
            'reader_rows' => $rows,
            'next_reads' => $reads,
            'read_cache_hits' => array_column($reads, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($reads, 'prefix', 'reader_id'),
            'reopen_reader_ids' => array_values($reopenReaders),
            'operations' => $operations,
            'final_prefixes' => self::prefixes($database),
            'final_sources' => self::sources($database),
            'final_database_bytes' => self::sourceBytes($database, $pageSize),
            'source_digest' => hash('sha256', implode('|', self::sources($database)) . '|' . $header['signature']),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next177',
                'sqlite-pager-master-journal-reader-cache-current-source-next174',
                'sqlite-page-one-header-ticket-reader-cache-fence',
            ],
            'non_overlap' => 'next177 adds page-1 header change-counter/schema-cookie/freelist ticket fencing before reader-cache reuse and does not repeat next174 rollback-journal source digest, next173 master-membership, next172 attached database scoping, or next158 stale page-image refresh behavior.',
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeImages(array $pages, int $pageSize, string $label, bool $allowEmpty): array
    {
        if ($pages === [] && !$allowEmpty) {
            throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next177 {$label} pages are required");
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next177 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next177 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,header_signature?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $pages
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,header_signature:string,dirty:bool,pinned:bool,shared:bool,label:string}>
     */
    private static function normalizeReaderCache(array $pages, int $pageSize): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 reader cache pages are required');
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 reader cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next177 reader cache page {$pageNumber} image must match page size");
            }
            $sourceId = $entry['source_id'] ?? '';
            $readerId = $entry['reader_id'] ?? 'reader-' . $pageNumber;
            $signature = $entry['header_signature'] ?? '';
            $epoch = $entry['epoch'] ?? 0;
            if (!is_string($sourceId) || $sourceId === '' || !is_string($readerId) || $readerId === '' || !is_string($signature) || $signature === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next177 reader cache page {$pageNumber} requires source, reader, and header signature");
            }
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next177 reader cache page {$pageNumber} epoch must be positive");
            }
            $label = $entry['label'] ?? ('page-' . $pageNumber);
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_id' => $readerId,
                'header_signature' => $signature,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'shared' => (bool) ($entry['shared'] ?? false),
                'label' => is_string($label) && $label !== '' ? $label : ('page-' . $pageNumber),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,header_signature?:string}> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,header_signature:string}>
     */
    private static function normalizeReads(array $reads): array
    {
        if ($reads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 reads are required');
        }
        $normalized = [];
        foreach ($reads as $read) {
            $readerId = $read['reader_id'] ?? '';
            $pageNumber = $read['page_number'] ?? 0;
            $sourceId = $read['source_id'] ?? '';
            $epoch = $read['epoch'] ?? 0;
            $signature = $read['header_signature'] ?? '';
            if (!is_string($readerId) || $readerId === '' || !is_int($pageNumber) || $pageNumber < 1 || !is_string($sourceId) || $sourceId === '' || !is_int($epoch) || $epoch < 1 || !is_string($signature) || $signature === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 reads require reader id, page number, source id, epoch, and header signature');
            }
            $normalized[] = [
                'reader_id' => $readerId,
                'page_number' => $pageNumber,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'header_signature' => $signature,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $bytes, int $pageSize): array
    {
        $pages = [];
        $count = intdiv(strlen($bytes), $pageSize);
        for ($i = 0; $i < $count; $i++) {
            $pages[$i + 1] = [
                'image' => substr($bytes, $i * $pageSize, $pageSize),
                'source' => 'database-before-master-journal-reader-cache-header-ticket-next177',
            ];
        }

        return $pages;
    }

    /**
     * @return array{change_counter:int,database_size:int,first_freelist_trunk:int,freelist_count:int,schema_cookie:int,signature:string}
     */
    private static function headerTicket(string $pageOne, int $pageSize): array
    {
        if (strlen($pageOne) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next177 page one image must match page size');
        }
        $fields = [
            'change_counter' => self::u32($pageOne, 24),
            'database_size' => self::u32($pageOne, 28),
            'first_freelist_trunk' => self::u32($pageOne, 32),
            'freelist_count' => self::u32($pageOne, 36),
            'schema_cookie' => self::u32($pageOne, 40),
        ];
        $fields['signature'] = hash('sha256', implode('|', $fields));

        return $fields;
    }

    private static function u32(string $bytes, int $offset): int
    {
        /** @var array{1:int} $unpacked */
        $unpacked = unpack('N', substr($bytes, $offset, 4));

        return $unpacked[1];
    }

    /**
     * @return list<string>
     */
    private static function members(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/\R+/', trim($bytes)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $members, true)) {
                $members[] = $line;
            }
        }

        return $members;
    }

    /**
     * @param array<int,array{image:string,source:string}> $database
     * @return array<int,string>
     */
    private static function prefixes(array $database): array
    {
        $prefixes = [];
        foreach ($database as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label($entry['image']);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $database
     * @return array<int,string>
     */
    private static function sources(array $database): array
    {
        $sources = [];
        foreach ($database as $pageNumber => $entry) {
            $sources[$pageNumber] = $entry['source'];
        }

        return $sources;
    }

    /**
     * @param array<int,array{image:string,source:string}> $database
     */
    private static function sourceBytes(array $database, int $pageSize): string
    {
        ksort($database, SORT_NUMERIC);
        $bytes = '';
        foreach ($database as $entry) {
            $bytes .= str_pad(substr($entry['image'], 0, $pageSize), $pageSize, "\0", STR_PAD_RIGHT);
        }

        return $bytes;
    }

    private static function label(string $image): string
    {
        return rtrim(strtok($image, "\0") ?: $image, ". \0");
    }
}
