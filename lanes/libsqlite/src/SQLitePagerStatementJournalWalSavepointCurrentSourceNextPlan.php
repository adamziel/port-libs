<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerStatementJournalWalSavepointCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $currentSourcePages
     * @param list<array{frame:int,page_number:int,image:string,commit_frame?:bool}> $savepointWalFrames
     * @param array<int,string> $statementBeforeImages
     * @param list<array{frame:int,page_number:int,image:string,commit_frame?:bool}> $statementWalFrames
     * @param array<int,string> $nextStatementBeforeImages
     * @param list<array{frame:int,page_number:int,image:string,commit_frame?:bool}> $nextStatementWalFrames
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $walPath,
        int $walFrameStart,
        string $savepointName,
        string $statementName,
        string $nextStatementName,
        array $currentSourcePages,
        array $savepointWalFrames,
        array $statementBeforeImages,
        array $statementWalFrames,
        array $nextStatementBeforeImages,
        array $nextStatementWalFrames,
        bool $releaseSavepointAfterRetry = false,
    ): array {
        if ($databasePath === '' || $walPath === '') {
            throw new \InvalidArgumentException('SQLite pager statement WAL savepoint current-source next112 requires database and WAL paths');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager statement WAL savepoint current-source next112 database bytes must be page-size aligned');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager statement WAL savepoint current-source next112 page size must be a power of two at least 512');
        }
        if ($walFrameStart < 0) {
            throw new \InvalidArgumentException('SQLite pager statement WAL savepoint current-source next112 WAL frame start must be non-negative');
        }
        if ($savepointName === '' || $statementName === '' || $nextStatementName === '') {
            throw new \InvalidArgumentException('SQLite pager statement WAL savepoint current-source next112 requires savepoint and statement names');
        }
        if ($currentSourcePages === [] || $savepointWalFrames === [] || $statementBeforeImages === [] || $statementWalFrames === [] || $nextStatementBeforeImages === [] || $nextStatementWalFrames === []) {
            throw new \InvalidArgumentException('SQLite pager statement WAL savepoint current-source next112 requires non-empty current, savepoint, statement, and retry sets');
        }

        $currentSourcePages = self::normalizePages($currentSourcePages, $pageSize, 'current-source');
        $statementBeforeImages = self::normalizePages($statementBeforeImages, $pageSize, 'statement-before');
        $nextStatementBeforeImages = self::normalizePages($nextStatementBeforeImages, $pageSize, 'next-statement-before');
        $savepointWalFrames = self::normalizeFrames($savepointWalFrames, $pageSize, $walFrameStart, 'savepoint-wal');
        $lastSavepointFrame = max(array_column($savepointWalFrames, 'frame'));
        $statementWalFrames = self::normalizeFrames($statementWalFrames, $pageSize, $lastSavepointFrame, 'statement-wal');
        $lastStatementFrame = max(array_column($statementWalFrames, 'frame'));
        $nextStatementWalFrames = self::normalizeFrames($nextStatementWalFrames, $pageSize, $lastSavepointFrame, 'next-statement-wal');

        $source = self::sourceMap($databaseBytes, $pageSize, 'database-image');
        foreach ($currentSourcePages as $pageNumber => $pageImage) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint current-source next112 page {$pageNumber} is outside the database image");
            }
            if ($source[$pageNumber]['image'] !== $pageImage) {
                throw new \RuntimeException("SQLite pager statement WAL savepoint current-source next112 page {$pageNumber} is stale");
            }
            $source[$pageNumber]['source'] = 'current-source';
        }

        $operations = [];
        foreach ($savepointWalFrames as $frame) {
            $pageNumber = $frame['page_number'];
            if (!isset($currentSourcePages[$pageNumber]) || $currentSourcePages[$pageNumber] !== $frame['image']) {
                throw new \RuntimeException("SQLite pager statement WAL savepoint current-source next112 savepoint frame {$frame['frame']} is not the current source");
            }
            $operations[] = [
                'op' => 'retain_savepoint_wal_frame',
                'frame' => $frame['frame'],
                'page_number' => $pageNumber,
                'commit_frame' => $frame['commit_frame'],
                'reason' => 'outer_savepoint_frames_remain_current_after_statement_rollback',
            ];
        }

        foreach ($statementWalFrames as $frame) {
            $pageNumber = $frame['page_number'];
            if (!isset($currentSourcePages[$pageNumber]) || $currentSourcePages[$pageNumber] !== $frame['image']) {
                throw new \RuntimeException("SQLite pager statement WAL savepoint current-source next112 failed statement frame {$frame['frame']} is not the current source");
            }
            if (!isset($statementBeforeImages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint current-source next112 failed statement page {$pageNumber} needs a before image");
            }
            $operations[] = [
                'op' => 'discard_statement_wal_frame',
                'frame' => $frame['frame'],
                'page_number' => $pageNumber,
                'commit_frame' => $frame['commit_frame'],
                'reason' => 'statement_journal_rollback_discards_failed_wal_frame',
            ];
        }

        foreach ($statementBeforeImages as $pageNumber => $pageImage) {
            $source[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'statement-journal-before-image',
            ];
            $operations[] = [
                'op' => 'restore_statement_journal_before_image',
                'statement' => $statementName,
                'page_number' => $pageNumber,
                'reason' => 'rollback_failed_statement_inside_wal_savepoint',
            ];
        }

        $rollbackSource = $source;
        $rollbackBytes = self::sourceBytes($rollbackSource, $pageSize);
        foreach ($nextStatementBeforeImages as $pageNumber => $pageImage) {
            $actual = $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            if ($actual !== $pageImage) {
                throw new \RuntimeException("SQLite pager statement WAL savepoint current-source next112 retry page {$pageNumber} is not the restored current source");
            }
            $operations[] = [
                'op' => 'capture_retry_statement_before_image',
                'statement' => $nextStatementName,
                'page_number' => $pageNumber,
                'source' => $source[$pageNumber]['source'] ?? 'zero-fill',
            ];
        }

        $nextFrames = [];
        $nextFrameIndex = $lastSavepointFrame;
        foreach ($nextStatementWalFrames as $frame) {
            $nextFrameIndex++;
            if ($frame['frame'] !== $nextFrameIndex) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint current-source next112 retry WAL frames must restart after retained savepoint frame {$lastSavepointFrame}");
            }
            $pageNumber = $frame['page_number'];
            if (!isset($nextStatementBeforeImages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint current-source next112 retry page {$pageNumber} needs a before image");
            }
            $source[$pageNumber] = [
                'image' => $frame['image'],
                'source' => 'retry-wal-frame',
            ];
            $nextFrames[] = $frame;
            $operations[] = [
                'op' => 'append_retry_wal_frame',
                'statement' => $nextStatementName,
                'frame' => $frame['frame'],
                'page_number' => $pageNumber,
                'commit_frame' => $frame['commit_frame'],
                'reason' => 'retry_statement_appends_after_statement_wal_truncation',
            ];
        }

        $releaseMergedPages = [];
        if ($releaseSavepointAfterRetry) {
            $releaseMergedPages = array_values(array_unique(array_merge(
                array_column($savepointWalFrames, 'page_number'),
                array_keys($statementBeforeImages),
                array_column($nextFrames, 'page_number')
            )));
            sort($releaseMergedPages, SORT_NUMERIC);
            $operations[] = [
                'op' => 'release_savepoint',
                'savepoint' => $savepointName,
                'merged_page_numbers' => $releaseMergedPages,
                'reason' => 'merge_successful_retry_wal_pages_into_outer_transaction',
            ];
        }

        ksort($source, SORT_NUMERIC);
        $finalBytes = self::sourceBytes($source, $pageSize);

        return [
            'status' => 'pager_statement_journal_wal_savepoint_current_source_next112',
            'reason' => 'statement_journal_rollback_truncates_failed_wal_frames_before_retry_savepoint_frames',
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'page_size' => $pageSize,
            'wal_frame_start' => $walFrameStart,
            'savepoint' => $savepointName,
            'statement' => $statementName,
            'next_statement' => $nextStatementName,
            'release_savepoint_after_retry' => $releaseSavepointAfterRetry,
            'current_source_verified' => true,
            'current_source_page_numbers' => array_keys($currentSourcePages),
            'savepoint_wal_frame_numbers' => array_column($savepointWalFrames, 'frame'),
            'statement_wal_frame_numbers' => array_column($statementWalFrames, 'frame'),
            'discarded_statement_frame_numbers' => array_column($statementWalFrames, 'frame'),
            'wal_truncate_to_frame' => $lastSavepointFrame,
            'wal_discarded_after_frame' => $lastSavepointFrame,
            'wal_original_frame_count' => $lastStatementFrame,
            'next_statement_wal_frame_numbers' => array_column($nextFrames, 'frame'),
            'statement_restored_page_numbers' => array_keys($statementBeforeImages),
            'next_statement_page_numbers' => array_column($nextFrames, 'page_number'),
            'release_merged_page_numbers' => $releaseMergedPages,
            'current_source_prefixes' => self::prefixes($currentSourcePages),
            'statement_before_prefixes' => self::prefixes($statementBeforeImages),
            'statement_rollback_prefixes' => self::prefixesFromSource($rollbackSource, array_keys($statementBeforeImages), $pageSize),
            'next_statement_before_prefixes' => self::prefixes($nextStatementBeforeImages),
            'final_prefixes' => self::prefixesFromSource($source, array_keys($source), $pageSize),
            'final_sources' => self::sources($source),
            'reader_page_map_after_rollback' => self::readerMap($rollbackSource, array_keys($nextStatementBeforeImages), $pageSize),
            'statement_rollback_database_bytes' => $rollbackBytes,
            'final_database_bytes' => $finalBytes,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-statement-journal-wal-savepoint-current-source-next112',
                'sqlite-statement-journal-rollback-current-source',
                'sqlite-wal-savepoint-frame-truncation',
                'sqlite-retry-wal-frame-current-source',
            ],
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize, string $label): array
    {
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $pageImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint {$label} page numbers must be one-based integers");
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $pageImage;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<array{frame:int,page_number:int,image:string,commit_frame:bool}>
     */
    private static function normalizeFrames(array $frames, int $pageSize, int $afterFrame, string $label): array
    {
        $normalized = [];
        $lastFrame = $afterFrame;
        foreach ($frames as $index => $frame) {
            if (!is_array($frame)) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint {$label} frame {$index} must be an array");
            }
            $frameNumber = (int) ($frame['frame'] ?? 0);
            $pageNumber = (int) ($frame['page_number'] ?? 0);
            $image = $frame['image'] ?? null;
            if ($frameNumber <= $lastFrame) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint {$label} frames must be increasing after {$afterFrame}");
            }
            if ($pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint {$label} page numbers must be one-based");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager statement WAL savepoint {$label} frame {$frameNumber} image must match page size");
            }
            $normalized[] = [
                'frame' => $frameNumber,
                'page_number' => $pageNumber,
                'image' => $image,
                'commit_frame' => (bool) ($frame['commit_frame'] ?? false),
            ];
            $lastFrame = $frameNumber;
        }

        return $normalized;
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize, string $source): array
    {
        $map = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $map[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'source' => $source,
            ];
        }

        return $map;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     */
    private static function sourceBytes(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $maxPage = max(array_keys($source));
        $bytes = '';
        for ($pageNumber = 1; $pageNumber <= $maxPage; $pageNumber++) {
            $bytes .= $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
        }

        return $bytes;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function prefixes(array $pages): array
    {
        $prefixes = [];
        foreach ($pages as $pageNumber => $pageImage) {
            $prefixes[$pageNumber] = rtrim(substr($pageImage, 0, 64), ".\0");
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @param list<int> $pageNumbers
     * @return array<int,string>
     */
    private static function prefixesFromSource(array $source, array $pageNumbers, int $pageSize): array
    {
        $prefixes = [];
        foreach ($pageNumbers as $pageNumber) {
            $prefixes[$pageNumber] = rtrim(substr($source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize), 0, min(64, $pageSize)), ".\0");
        }
        ksort($prefixes, SORT_NUMERIC);

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @param list<int> $pageNumbers
     * @return array<int,array{source:string,prefix:string,zero_filled_short_read:bool}>
     */
    private static function readerMap(array $source, array $pageNumbers, int $pageSize): array
    {
        $map = [];
        foreach ($pageNumbers as $pageNumber) {
            $entry = $source[$pageNumber] ?? null;
            $map[$pageNumber] = [
                'source' => $entry['source'] ?? 'zero-fill',
                'prefix' => rtrim(substr($entry['image'] ?? str_repeat("\0", $pageSize), 0, min(64, $pageSize)), ".\0"),
                'zero_filled_short_read' => $entry === null,
            ];
        }
        ksort($map, SORT_NUMERIC);

        return $map;
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
        ksort($sources, SORT_NUMERIC);

        return $sources;
    }
}
