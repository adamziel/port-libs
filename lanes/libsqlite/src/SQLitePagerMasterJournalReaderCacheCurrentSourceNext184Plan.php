<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext184Plan
{
    /**
     * @param array{device?:int|string,inode?:int|string,size?:int,mtime?:int,ctime?:int,generation?:int|string,readOffset?:int,readLength?:int} $currentMasterStat
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,label?:string,master_members?:list<string>,master_read_token?:string,master_generation?:int|string,master_size?:int}> $readerCache
     * @param list<int> $readPages
     * @param array<int,string> $refreshedPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        array $currentMasterStat,
        string $databaseBytes,
        int $pageSize,
        array $readerCache,
        array $readPages,
        array $refreshedPages,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 requires non-empty paths and source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 requires master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 database bytes must be page-size aligned');
        }
        if ($readerCache === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 requires reader cache and read pages');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 current epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next184 current master journal does not reference the database journal');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $stat = self::normalizeMasterStat($currentMasterStat, strlen($currentMasterJournalBytes));
        $readToken = self::readToken($masterJournalPath, $members, $stat, $currentMasterJournalBytes);
        $memberDigest = hash('sha256', implode("\n", $members));
        $readerCache = self::normalizeReaderCache($readerCache, $pageSize);
        self::assertPageList($readPages, 'read');
        $refreshedPages = self::normalizeImages($refreshedPages, $pageSize, 'refreshed', true);

        foreach ($refreshedPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 refreshed page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-read-token-current-source-next184',
            ];
        }

        $nextSourceId = 'master-reader-cache-read-token:' . substr(hash('sha256', $masterJournalPath . '|' . $readToken), 0, 28);
        $nextEpoch = $currentEpoch + 1;
        $operations = [[
            'op' => 'read_current_master_journal_with_generation_token_next184',
            'path' => $masterJournalPath,
            'members' => $members,
            'read_token' => $readToken,
        ]];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_from_prior_master_journal_generation';
            } elseif ($entry['master_members'] !== $members) {
                $reason = 'reader_cache_master_member_set_changed_next184';
            } elseif ($entry['master_read_token'] !== $readToken) {
                $reason = 'reader_cache_master_read_token_changed';
            } elseif ((string) $entry['master_generation'] !== (string) $stat['generation']) {
                $reason = 'reader_cache_master_generation_changed';
            } elseif ($entry['master_size'] !== $stat['size']) {
                $reason = 'reader_cache_master_size_changed';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_predates_master_read_token';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_predates_master_read_token';
            } elseif ($entry['pinned'] && $entry['image'] !== $currentImage) {
                $reason = 'pinned_reader_cache_image_predates_master_read_token';
            }

            if ($reason !== null) {
                $invalidated[] = $pageNumber;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_after_master_read_token_recheck_next184',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                    'requires_reopen' => true,
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $refreshed[] = $pageNumber;
                $operations[] = [
                    'op' => 'refresh_reader_cache_after_master_read_token_recheck_next184',
                    'page_number' => $pageNumber,
                ];
            } else {
                $retained[] = $pageNumber;
                $operations[] = [
                    'op' => 'retain_reader_cache_after_master_read_token_recheck_next184',
                    'page_number' => $pageNumber,
                ];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'label' => $entry['label'],
                'admitted' => $reason === null,
                'reason' => $reason ?? ($entry['image'] === $currentImage ? 'reader_cache_matches_master_read_token_source' : 'reader_cache_refreshed_from_master_read_token_source'),
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'source_id_before' => $entry['source_id'],
                'epoch_before' => $entry['epoch'],
                'master_members_before' => $entry['master_members'],
                'master_members_current' => $members,
                'master_member_digest_current' => $memberDigest,
                'master_read_token_before' => $entry['master_read_token'],
                'master_read_token_current' => $readToken,
                'master_read_token_matches' => $entry['master_read_token'] === $readToken,
                'master_generation_before' => (string) $entry['master_generation'],
                'master_generation_current' => (string) $stat['generation'],
                'master_size_before' => $entry['master_size'],
                'master_size_current' => $stat['size'],
                'image_matches_current_source' => $entry['image'] === $currentImage,
                'cache_prefix' => self::label($entry['image']),
                'current_prefix' => self::label($currentImage),
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 read page {$pageNumber} is outside the database image");
            }
            $cacheHit = in_array($pageNumber, $retained, true) || in_array($pageNumber, $refreshed, true);
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheHit,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'master_read_token' => $readToken,
                'prefix' => self::label($database[$pageNumber]['image']),
            ];
            $operations[] = [
                'op' => $cacheHit ? 'next_read_uses_master_read_token_reader_cache_next184' : 'next_read_reopens_after_master_read_token_change_next184',
                'page_number' => $pageNumber,
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next184',
            'reason' => 'master_journal_read_token_fences_reader_cache_across_recreated_current_source',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'current_member_digest' => $memberDigest,
            'current_master_stat' => $stat,
            'current_master_read_token' => $readToken,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'next_source' => ['id' => $nextSourceId, 'epoch' => $nextEpoch],
            'reader_rows' => $rows,
            'retained_cache_page_numbers' => $retained,
            'refreshed_cache_page_numbers' => $refreshed,
            'invalidated_cache_page_numbers' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [],
            'next_reads' => $reads,
            'operations' => $operations,
            'final_prefixes' => self::prefixes($database),
            'final_sources' => self::sources($database),
            'source_digest' => hash('sha256', $readToken . '|' . implode(',', $retained) . '|' . implode(',', $refreshed) . '|' . implode(',', $invalidated)),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next184',
                'sqlite-pager-master-journal-read-token-source-fence-next184',
                'sqlite-pager-master-journal-reader-cache-current-source-next181',
            ],
            'non_overlap' => 'Adds master-journal file generation/read-token fencing for unlink/recreate current-source changes; avoids next181 pending membership, rollback-journal source digest/page-set, and member delete/recovered-state fences.',
        ];
    }

    /**
     * @return array{device:string,inode:string,size:int,mtime:int,ctime:int,generation:string,readOffset:int,readLength:int}
     */
    private static function normalizeMasterStat(array $stat, int $byteLength): array
    {
        foreach (['device', 'inode', 'generation'] as $name) {
            if (!array_key_exists($name, $stat) || (string) $stat[$name] === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 master stat requires {$name}");
            }
        }
        $size = $stat['size'] ?? $byteLength;
        $mtime = $stat['mtime'] ?? null;
        $ctime = $stat['ctime'] ?? null;
        $readOffset = $stat['readOffset'] ?? 0;
        $readLength = $stat['readLength'] ?? $byteLength;
        foreach (['size' => $size, 'mtime' => $mtime, 'ctime' => $ctime, 'readOffset' => $readOffset, 'readLength' => $readLength] as $name => $value) {
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 master stat {$name} must be a non-negative integer");
            }
        }
        if ($size !== $byteLength || $readLength !== $byteLength || $readOffset !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 master stat must describe a complete current read');
        }

        return [
            'device' => (string) $stat['device'],
            'inode' => (string) $stat['inode'],
            'size' => $size,
            'mtime' => $mtime,
            'ctime' => $ctime,
            'generation' => (string) $stat['generation'],
            'readOffset' => $readOffset,
            'readLength' => $readLength,
        ];
    }

    /**
     * @param list<string> $members
     * @param array{device:string,inode:string,size:int,mtime:int,ctime:int,generation:string,readOffset:int,readLength:int} $stat
     */
    private static function readToken(string $path, array $members, array $stat, string $bytes): string
    {
        return hash('sha256', implode('|', [
            $path,
            $stat['device'],
            $stat['inode'],
            $stat['generation'],
            $stat['size'],
            $stat['mtime'],
            $stat['ctime'],
            $stat['readOffset'],
            $stat['readLength'],
            implode("\n", $members),
            hash('sha256', $bytes),
        ]));
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

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $bytes, int $pageSize): array
    {
        $map = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            $map[$index + 1] = ['image' => $image, 'source' => 'database-before-master-read-token-reader-cache-next184'];
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
            throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 requires {$label} pages");
        }
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,label?:string,master_members?:list<string>,master_read_token?:string,master_generation?:int|string,master_size?:int}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,dirty:bool,pinned:bool,label:string,master_members:list<string>,master_read_token:string,master_generation:int|string,master_size:int}>
     */
    private static function normalizeReaderCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            $readToken = (string) ($entry['master_read_token'] ?? '');
            if ($sourceId === '' || $readToken === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 cache entries require source id and master read token');
            }
            $epoch = $entry['epoch'] ?? 0;
            $masterSize = $entry['master_size'] ?? null;
            if (!is_int($epoch) || $epoch < 1 || !is_int($masterSize) || $masterSize < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 cache epoch and master size must be valid integers');
            }
            $members = $entry['master_members'] ?? null;
            if (!is_array($members)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 cache master members must be a list');
            }
            $normalizedMembers = [];
            foreach ($members as $member) {
                if (!is_string($member) || trim($member) === '') {
                    throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 cache master members must be non-empty strings');
                }
                $normalizedMembers[] = trim($member);
            }
            $generation = $entry['master_generation'] ?? '';
            if ((string) $generation === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next184 cache master generation is required');
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'label' => (string) ($entry['label'] ?? ('reader-cache-page-' . $pageNumber)),
                'master_members' => $normalizedMembers,
                'master_read_token' => $readToken,
                'master_generation' => $generation,
                'master_size' => $masterSize,
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
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next184 {$label} page numbers must be one-based integers");
            }
        }
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
}
