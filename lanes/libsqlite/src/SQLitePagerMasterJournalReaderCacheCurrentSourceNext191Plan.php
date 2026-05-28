<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext191Plan
{
    /**
     * @param array<int,array{image:string,reader_id?:string,source_id?:string,epoch?:int,master_delete_token?:string,directory_sync_generation?:int,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<int> $readPages
     * @param array<int,string> $currentPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $readerCache,
        array $readPages,
        array $currentPages,
        string $currentSourceId,
        int $currentEpoch,
        int $directorySyncGeneration,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next191 requires non-empty paths and source id');
        }
        if (trim(str_replace("\0", '', $currentMasterJournalBytes)) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next191 requires master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next191 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next191 database bytes must be page-size aligned');
        }
        if ($readerCache === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next191 requires reader cache and read pages');
        }
        if ($currentEpoch < 1 || $directorySyncGeneration < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next191 requires positive epoch and directory sync generation');
        }

        $members = self::members($currentMasterJournalBytes);
        $mainJournal = $databasePath . '-journal';
        if (!in_array($mainJournal, $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next191 current master journal does not reference the database journal');
        }

        $database = self::pages($databaseBytes, $pageSize);
        $currentPages = self::images($currentPages, $pageSize, 'current');
        foreach ($currentPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next191 current page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-delete-synced-current-source-next191',
            ];
        }

        $cache = self::readerCache($readerCache, $pageSize);
        self::pageList($readPages, 'read');
        $memberDigest = hash('sha256', implode("\n", $members));
        $deleteToken = self::deleteToken($masterJournalPath, $members, $directorySyncGeneration);
        $nextSource = [
            'id' => 'master-journal-delete-synced-source:' . substr(hash('sha256', $deleteToken . '|' . $currentSourceId), 0, 32),
            'epoch' => $currentEpoch + 1,
        ];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        $operations = [[
            'op' => 'verify_master_journal_deleted_and_directory_synced_next191',
            'path' => $masterJournalPath,
            'members' => $members,
            'member_digest' => $memberDigest,
            'delete_token' => $deleteToken,
            'directory_sync_generation' => $directorySyncGeneration,
        ]];

        foreach ($cache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next191 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_master_journal_delete';
            } elseif ($entry['master_delete_token'] !== $deleteToken) {
                $reason = 'reader_cache_master_delete_token_mismatch';
            } elseif ($entry['directory_sync_generation'] !== $directorySyncGeneration) {
                $reason = 'reader_cache_directory_sync_generation_mismatch';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_predates_master_journal_delete';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_predates_master_journal_delete';
            } elseif ($entry['pinned'] && !hash_equals($entry['image'], $currentImage)) {
                $reason = 'pinned_reader_cache_image_predates_master_journal_delete';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = $reason;
                $operations[] = ['op' => 'invalidate_reader_cache_after_master_journal_delete_next191', 'page_number' => $pageNumber, 'reason' => $reason];
            } elseif (!hash_equals($entry['image'], $currentImage)) {
                $refreshed[$pageNumber] = $currentImage;
                $operations[] = ['op' => 'refresh_reader_cache_after_master_journal_delete_next191', 'page_number' => $pageNumber];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $operations[] = ['op' => 'retain_reader_cache_after_master_journal_delete_next191', 'page_number' => $pageNumber];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'admitted' => $reason === null,
                'reason' => $reason ?? (hash_equals($entry['image'], $currentImage) ? 'reader_cache_matches_master_journal_delete_source' : 'reader_cache_refreshed_after_master_journal_delete'),
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'delete_token_before' => $entry['master_delete_token'],
                'delete_token_current' => $deleteToken,
                'delete_token_matches' => $entry['master_delete_token'] === $deleteToken,
                'directory_sync_generation_before' => $entry['directory_sync_generation'],
                'directory_sync_generation_current' => $directorySyncGeneration,
                'directory_sync_generation_matches' => $entry['directory_sync_generation'] === $directorySyncGeneration,
                'source_id_before' => $entry['source_id'],
                'epoch_before' => $entry['epoch'],
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next191 read page {$pageNumber} is outside the database image");
            }
            $cacheImage = $retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null;
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheImage !== null,
                'reason' => $cacheImage !== null ? 'next_read_uses_delete_synced_master_journal_reader_cache' : 'next_read_reopens_after_master_journal_delete',
                'source_id' => $nextSource['id'],
                'epoch' => $nextSource['epoch'],
                'prefix' => self::prefix($cacheImage ?? $database[$pageNumber]['image']),
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next191',
            'reason' => 'master_journal_delete_and_directory_sync_fence_reader_cache_admission',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'current_member_digest' => $memberDigest,
            'current_master_delete_token' => $deleteToken,
            'current_directory_sync_generation' => $directorySyncGeneration,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'next_source' => $nextSource,
            'reader_rows' => $rows,
            'retained_page_numbers' => array_keys($retained),
            'refreshed_page_numbers' => array_keys($refreshed),
            'invalidated_page_numbers' => array_keys($invalidated),
            'invalidated_reasons' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [],
            'next_reads' => $reads,
            'operations' => $operations,
            'final_prefixes' => array_map(static fn (array $page): string => self::prefix($page['image']), $database),
            'final_sources' => array_map(static fn (array $page): string => $page['source'], $database),
            'source_digest' => hash('sha256', $deleteToken . '|' . implode(',', array_keys($retained)) . '|' . implode(',', array_keys($invalidated))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next191',
                'sqlite-master-journal-delete-directory-sync-fence',
            ],
            'non_overlap' => 'Adds master-journal delete proof plus directory-sync-generation fencing; avoids next188 NUL member parsing, next187 complete-read membership, next185 finite truncation, rollback-journal commit/apply, super-journal commit, and VFS sync-plan/apply paths.',
        ];
    }

    /** @return list<string> */
    private static function members(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/[\r\n\0]+/', $bytes) ?: [] as $member) {
            $member = trim($member);
            if ($member !== '') {
                $members[$member] = $member;
            }
        }
        ksort($members, SORT_STRING);

        return array_values($members);
    }

    /** @param list<string> $members */
    private static function deleteToken(string $path, array $members, int $directorySyncGeneration): string
    {
        return 'master-delete-synced:' . substr(hash('sha256', $path . '|' . $directorySyncGeneration . '|' . implode("\n", $members)), 0, 40);
    }

    /** @return array<int,array{image:string,source:string}> */
    private static function pages(string $bytes, int $pageSize): array
    {
        $pages = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            $pages[$index + 1] = ['image' => $image, 'source' => 'database-before-master-delete-reader-cache-next191'];
        }

        return $pages;
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function images(array $images, int $pageSize, string $label): array
    {
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next191 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next191 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,reader_id?:string,source_id?:string,epoch?:int,master_delete_token?:string,directory_sync_generation?:int,dirty?:bool,pinned?:bool}> $cache
     * @return array<int,array{image:string,reader_id:string,source_id:string,epoch:int,master_delete_token:string,directory_sync_generation:int,dirty:bool,pinned:bool}>
     */
    private static function readerCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next191 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next191 cache page {$pageNumber} must be page-size bytes");
            }
            $readerId = (string) ($entry['reader_id'] ?? ('reader-' . $pageNumber));
            $sourceId = (string) ($entry['source_id'] ?? '');
            $deleteToken = (string) ($entry['master_delete_token'] ?? '');
            $epoch = $entry['epoch'] ?? 0;
            $syncGeneration = $entry['directory_sync_generation'] ?? 0;
            if ($readerId === '' || $sourceId === '' || $deleteToken === '' || !is_int($epoch) || $epoch < 1 || !is_int($syncGeneration) || $syncGeneration < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next191 cache entries require reader, source, delete token, epoch, and directory sync generation');
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'reader_id' => $readerId,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'master_delete_token' => $deleteToken,
                'directory_sync_generation' => $syncGeneration,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /** @param list<int> $pages */
    private static function pageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next191 {$label} page numbers must be one-based integers");
            }
        }
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }
}
