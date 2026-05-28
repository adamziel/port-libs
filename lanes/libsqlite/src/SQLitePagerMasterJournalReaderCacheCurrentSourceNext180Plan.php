<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext180Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string}> $nextReads
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
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 requires database path, master journal path, and source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 database bytes must be page-size aligned');
        }
        if ($recoveredPages === [] || $readerCache === [] || $nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 requires recovered pages, reader cache, and reads');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next180 current master journal does not reference the database journal');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $recoveredPages = self::normalizeImages($recoveredPages, $pageSize, 'recovered');
        $readerCache = self::normalizeReaderCache($readerCache, $pageSize);
        $nextReads = self::normalizeReads($nextReads);

        $operations = [[
            'op' => 'read_current_master_journal_for_format_ticket_reader_cache_next180',
            'path' => $masterJournalPath,
            'members' => $members,
        ]];

        foreach ($recoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next180 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-recovered-format-ticket-source-next180',
            ];
            $operations[] = [
                'op' => 'restore_master_journal_page_before_format_ticket_reader_cache_next180',
                'page_number' => $pageNumber,
            ];
        }
        if (!isset($database[1])) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 requires page 1 for format ticketing');
        }

        $format = self::formatTicket($database[1]['image'], $pageSize);
        $nextSourceId = 'master-reader-format-ticket:' . substr(hash('sha256', $databasePath . '|' . implode('|', $members) . '|' . $format['signature']), 0, 24);
        $nextEpoch = $currentEpoch + 1;

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next180 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_recovered_format_ticket';
            } elseif ($entry['format_signature'] !== $format['signature']) {
                $reason = 'reader_cache_format_signature_mismatch_after_master_recovery';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch_after_format_ticket';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch_after_format_ticket';
            } elseif ($entry['pinned'] && $entry['image'] !== $currentImage) {
                $reason = 'pinned_reader_cache_image_predates_format_ticket';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = [
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'label' => $entry['label'],
                    'reason' => $reason,
                    'format_signature_before' => $entry['format_signature'],
                    'format_signature_current' => $format['signature'],
                    'dirty' => $entry['dirty'],
                    'pinned' => $entry['pinned'],
                ];
                $operations[] = [
                    'op' => 'invalidate_reader_cache_after_format_ticket_recheck_next180',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $refreshed[$pageNumber] = [
                    'image' => $currentImage,
                    'source' => 'reader-cache-refreshed-format-ticket-current-source-next180',
                    'reader_id' => $entry['reader_id'],
                ];
                $operations[] = [
                    'op' => 'refresh_reader_cache_from_format_ticket_current_source_next180',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            } else {
                $retained[$pageNumber] = [
                    'image' => $entry['image'],
                    'source' => 'reader-cache-retained-format-ticket-current-source-next180',
                    'reader_id' => $entry['reader_id'],
                ];
                $operations[] = [
                    'op' => 'retain_reader_cache_after_format_ticket_current_source_next180',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'label' => $entry['label'],
                'admitted' => $reason === null,
                'reason' => $reason ?? ($entry['image'] === $currentImage ? 'reader_cache_matches_current_format_ticket_source' : 'reader_cache_refreshed_from_current_format_ticket_source'),
                'format_signature_before' => $entry['format_signature'],
                'format_signature_current' => $format['signature'],
                'format_signature_matches' => $entry['format_signature'] === $format['signature'],
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
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next180 read page {$pageNumber} is outside the database image");
            }
            $ticketCurrent = $read['source_id'] === $currentSourceId
                && $read['epoch'] === $currentEpoch
                && $read['format_signature'] === $format['signature'];
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
                'format_signature' => $format['signature'],
                'prefix' => self::label($image),
                'digest' => hash('sha256', $image),
            ];
            $operations[] = [
                'op' => is_array($cache) ? 'next180_reader_cache_hit_after_format_ticket' : 'next180_reader_cache_miss_after_format_ticket',
                'page_number' => $pageNumber,
                'reader_id' => $read['reader_id'],
                'ticket_current' => $ticketCurrent,
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next180',
            'reason' => 'master_journal_recovery_rechecks_page_one_format_ticket_before_reader_cache_reuse',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'current_members' => $members,
            'page_size' => $pageSize,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'next_source' => ['id' => $nextSourceId, 'epoch' => $nextEpoch],
            'format_ticket' => $format,
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
            'source_digest' => hash('sha256', implode('|', self::sources($database)) . '|' . $format['signature']),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next180',
                'sqlite-pager-master-journal-reader-cache-current-source-next177',
                'sqlite-page-one-format-ticket-reader-cache-fence',
            ],
            'non_overlap' => 'next180 adds page-1 format-ticket fencing for page size, reserved bytes, text encoding, user version, and application id before reader-cache reuse and does not repeat next177 change-counter/schema-cookie/freelist ticketing or next174 rollback-journal source fencing.',
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeImages(array $pages, int $pageSize, string $label): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next180 {$label} pages are required");
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next180 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next180 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $pages
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,format_signature:string,dirty:bool,pinned:bool,shared:bool,label:string}>
     */
    private static function normalizeReaderCache(array $pages, int $pageSize): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 reader cache pages are required');
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 reader cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next180 reader cache page {$pageNumber} image must match page size");
            }
            $sourceId = $entry['source_id'] ?? '';
            $readerId = $entry['reader_id'] ?? 'reader-' . $pageNumber;
            $signature = $entry['format_signature'] ?? '';
            $epoch = $entry['epoch'] ?? 0;
            if (!is_string($sourceId) || $sourceId === '' || !is_string($readerId) || $readerId === '' || !is_string($signature) || $signature === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next180 reader cache page {$pageNumber} requires source, reader, and format signature");
            }
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next180 reader cache page {$pageNumber} epoch must be positive");
            }
            $label = $entry['label'] ?? ('page-' . $pageNumber);
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_id' => $readerId,
                'format_signature' => $signature,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'shared' => (bool) ($entry['shared'] ?? false),
                'label' => is_string($label) && $label !== '' ? $label : ('page-' . $pageNumber),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string}> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,format_signature:string}>
     */
    private static function normalizeReads(array $reads): array
    {
        if ($reads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 reads are required');
        }
        $normalized = [];
        foreach ($reads as $read) {
            $readerId = $read['reader_id'] ?? '';
            $pageNumber = $read['page_number'] ?? 0;
            $sourceId = $read['source_id'] ?? '';
            $epoch = $read['epoch'] ?? 0;
            $signature = $read['format_signature'] ?? '';
            if (!is_string($readerId) || $readerId === '' || !is_int($pageNumber) || $pageNumber < 1 || !is_string($sourceId) || $sourceId === '' || !is_int($epoch) || $epoch < 1 || !is_string($signature) || $signature === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 reads require reader id, page number, source id, epoch, and format signature');
            }
            $normalized[] = [
                'reader_id' => $readerId,
                'page_number' => $pageNumber,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'format_signature' => $signature,
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
                'source' => 'database-before-master-journal-reader-cache-format-ticket-next180',
            ];
        }

        return $pages;
    }

    /**
     * @return array{header_page_size:int,reserved_bytes:int,text_encoding:int,user_version:int,application_id:int,signature:string}
     */
    private static function formatTicket(string $pageOne, int $pageSize): array
    {
        if (strlen($pageOne) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next180 page one image must match page size');
        }
        $headerPageSize = self::u16($pageOne, 16);
        if ($headerPageSize === 1) {
            $headerPageSize = 65536;
        }
        $fields = [
            'header_page_size' => $headerPageSize,
            'reserved_bytes' => ord($pageOne[20]),
            'text_encoding' => self::u32($pageOne, 56),
            'user_version' => self::u32($pageOne, 60),
            'application_id' => self::u32($pageOne, 68),
        ];
        $fields['signature'] = hash('sha256', implode('|', $fields));

        return $fields;
    }

    private static function u16(string $bytes, int $offset): int
    {
        /** @var array{1:int} $unpacked */
        $unpacked = unpack('n', substr($bytes, $offset, 2));

        return $unpacked[1];
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
