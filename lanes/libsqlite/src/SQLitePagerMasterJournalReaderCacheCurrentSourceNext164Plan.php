<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext164Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,label?:string,master_journal_digest?:string,change_counter?:int,schema_cookie?:int,version_valid_for?:int}> $readerCache
     * @param list<int> $readPages
     * @param array<int,string> $writePages
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
        array $readPages,
        array $writePages,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 requires non-empty paths and source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 database bytes must be page-size aligned');
        }
        if ($recoveredPages === [] || $readerCache === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 requires recovered pages and reader cache');
        }
        if ($readPages === [] && $writePages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 requires read or write pages');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 current epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next164 current master journal does not reference the database journal');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $recoveredPages = self::normalizeImages($recoveredPages, $pageSize, 'recovered', false);
        $readerCache = self::normalizeReaderCache($readerCache, $pageSize);
        self::assertPageList($readPages, 'read');
        $writePages = self::normalizeImages($writePages, $pageSize, 'write', true);

        foreach ($recoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-header-current-source',
            ];
        }

        $header = self::headerState($database[1]['image']);
        $masterDigest = hash('sha256', implode("\n", $members));
        $recoveredSourceId = 'master-reader-header:' . hash('sha256', $masterJournalPath . '|' . implode('|', $members) . '|' . implode(':', $header));
        $recoveredEpoch = $currentEpoch + 1;
        $operations = [[
            'op' => 'read_current_master_journal_for_header_reader_cache',
            'path' => $masterJournalPath,
            'members' => $members,
            'header' => $header,
        ]];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        $validCache = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_from_aborted_master_recovery';
            } elseif ($entry['master_journal_digest'] !== $masterDigest) {
                $reason = 'reader_cache_master_journal_digest_mismatch';
            } elseif ($entry['change_counter'] !== $header['change_counter']) {
                $reason = 'reader_cache_change_counter_predates_current_header';
            } elseif ($entry['schema_cookie'] !== $header['schema_cookie']) {
                $reason = 'reader_cache_schema_cookie_predates_current_header';
            } elseif ($entry['version_valid_for'] !== $header['version_valid_for']) {
                $reason = 'reader_cache_version_valid_for_predates_current_header';
            } elseif ($entry['pinned'] && $entry['image'] !== $currentImage) {
                $reason = 'pinned_reader_cache_image_predates_current_header';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_predates_current_header';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_predates_current_header';
            }

            if ($reason !== null) {
                $invalidated[] = $pageNumber;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_after_master_header_recovery',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $refreshed[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $currentImage,
                    'source' => 'reader-cache-refreshed-master-header-source',
                ];
                $operations[] = [
                    'op' => 'refresh_reader_cache_after_master_header_recovery',
                    'page_number' => $pageNumber,
                ];
            } else {
                $retained[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $entry['image'],
                    'source' => 'reader-cache-retained-master-header-source',
                ];
                $operations[] = [
                    'op' => 'retain_reader_cache_after_master_header_recovery',
                    'page_number' => $pageNumber,
                ];
            }

            $rows[] = [
                'label' => $entry['label'],
                'page_number' => $pageNumber,
                'admitted' => $reason === null,
                'reason' => $reason ?? ($entry['image'] === $currentImage ? 'reader_cache_matches_current_header_source' : 'reader_cache_refreshed_from_current_header_source'),
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'cache_header' => [
                    'change_counter' => $entry['change_counter'],
                    'schema_cookie' => $entry['schema_cookie'],
                    'version_valid_for' => $entry['version_valid_for'],
                ],
                'current_header' => $header,
                'master_journal_digest_matches_current' => $entry['master_journal_digest'] === $masterDigest,
                'image_matches_current_source' => $entry['image'] === $currentImage,
                'cache_prefix' => self::label($entry['image']),
                'current_prefix' => self::label($currentImage),
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 read page {$pageNumber} is outside the database image");
            }
            $cache = $validCache[$pageNumber] ?? null;
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cache !== null,
                'source' => $cache['source'] ?? $database[$pageNumber]['source'],
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
                'prefix' => self::label($cache['image'] ?? $database[$pageNumber]['image']),
                'header_change_counter' => $header['change_counter'],
                'header_schema_cookie' => $header['schema_cookie'],
            ];
        }

        $writes = [];
        foreach ($writePages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 write page {$pageNumber} is outside the database image");
            }
            $before = $database[$pageNumber]['image'];
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'next-write-after-master-header-reader-cache',
            ];
            $writes[] = [
                'page_number' => $pageNumber,
                'before_prefix' => self::label($before),
                'after_prefix' => self::label($image),
                'journal_before_from_current_header_source' => true,
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
            ];
            $operations[] = [
                'op' => 'capture_next_write_before_image_after_master_header_recovery',
                'page_number' => $pageNumber,
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next164',
            'reason' => 'master_journal_recovery_header_state_fences_reader_cache_before_next_source',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'current_master_journal_digest' => $masterDigest,
            'current_header' => $header,
            'input_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'recovered_source' => ['id' => $recoveredSourceId, 'epoch' => $recoveredEpoch],
            'recovered_page_numbers' => array_keys($recoveredPages),
            'reader_rows' => $rows,
            'retained_cache_page_numbers' => $retained,
            'refreshed_cache_page_numbers' => $refreshed,
            'invalidated_cache_page_numbers' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [],
            'next_reads' => $reads,
            'next_writes' => $writes,
            'operations' => $operations,
            'final_prefixes' => self::prefixes($database),
            'final_sources' => self::sources($database),
            'final_database_bytes' => self::sourceBytes($database, $pageSize),
            'source_digest' => hash('sha256', $recoveredSourceId . '|' . implode(',', $retained) . '|' . implode(',', $refreshed) . '|' . implode(',', $invalidated)),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next164',
                'sqlite-pager-reader-cache-header-change-counter-fence',
                'sqlite-pager-reader-cache-schema-cookie-fence',
                'sqlite-pager-master-journal-reader-cache-current-source-next161',
            ],
        ];
    }

    /**
     * @return array{change_counter:int,schema_cookie:int,version_valid_for:int}
     */
    private static function headerState(string $pageOne): array
    {
        if (strlen($pageOne) < 96) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 page one is too short for header state');
        }

        return [
            'change_counter' => self::u32(substr($pageOne, 24, 4)),
            'schema_cookie' => self::u32(substr($pageOne, 40, 4)),
            'version_valid_for' => self::u32(substr($pageOne, 92, 4)),
        ];
    }

    private static function u32(string $bytes): int
    {
        $value = unpack('N', $bytes);
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 could not decode header integer');
        }

        return (int) $value[1];
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $bytes, int $pageSize): array
    {
        $map = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            $map[$index + 1] = ['image' => $image, 'source' => 'database-before-master-header-recovery'];
        }

        return $map;
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function normalizeImages(array $images, int $pageSize, string $label, bool $allowEmpty): array
    {
        if ($images === [] && !$allowEmpty) {
            throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 requires {$label} pages");
        }
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,label?:string,master_journal_digest?:string,change_counter?:int,schema_cookie?:int,version_valid_for?:int}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,dirty:bool,pinned:bool,label:string,master_journal_digest:string,change_counter:int,schema_cookie:int,version_valid_for:int}>
     */
    private static function normalizeReaderCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            $digest = (string) ($entry['master_journal_digest'] ?? '');
            if ($sourceId === '' || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next164 cache entries require source id and master-journal digest');
            }
            $epoch = $entry['epoch'] ?? 0;
            $changeCounter = $entry['change_counter'] ?? null;
            $schemaCookie = $entry['schema_cookie'] ?? null;
            $versionValidFor = $entry['version_valid_for'] ?? null;
            foreach (['epoch' => $epoch, 'change_counter' => $changeCounter, 'schema_cookie' => $schemaCookie, 'version_valid_for' => $versionValidFor] as $name => $value) {
                if (!is_int($value) || $value < 0 || ($name === 'epoch' && $value < 1)) {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 cache {$name} must be a valid integer");
                }
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'label' => (string) ($entry['label'] ?? ('reader-cache-page-' . $pageNumber)),
                'master_journal_digest' => $digest,
                'change_counter' => $changeCounter,
                'schema_cookie' => $schemaCookie,
                'version_valid_for' => $versionValidFor,
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next164 {$label} page numbers must be one-based integers");
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function members(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/\r?\n/', trim($bytes)) ?: [] as $member) {
            $member = trim($member);
            if ($member !== '' && !in_array($member, $members, true)) {
                $members[] = $member;
            }
        }

        return $members;
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @return array<int,string>
     */
    private static function prefixes(array $source): array
    {
        $prefixes = [];
        foreach ($source as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label($entry['image']);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @return array<int,string>
     */
    private static function sources(array $source): array
    {
        $sources = [];
        foreach ($source as $pageNumber => $entry) {
            $sources[$pageNumber] = $entry['source'];
        }

        return $sources;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     */
    private static function sourceBytes(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $bytes = '';
        foreach ($source as $entry) {
            if (strlen($entry['image']) !== $pageSize) {
                throw new \RuntimeException('SQLite pager master-journal reader-cache next164 final image is not page-size bytes');
            }
            $bytes .= $entry['image'];
        }

        return $bytes;
    }
}
