<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext161Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string}> $readerCachePages
     * @param list<int> $checkpointPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepoint,
        array $hotJournalPages,
        array $savepointBeforePages,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        SQLiteWal $nextWal,
        string $nextWalBytes,
        array $readerCachePages,
        array $checkpointPages,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
    ): array {
        $mode = strtolower(trim($mode));
        if ($databasePath === '' || $databaseBytes === '' || $savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 requires database path, bytes, and savepoint');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 database bytes must be page-size aligned');
        }
        if (!in_array($mode, ['full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 requires full, restart, or truncate mode');
        }
        if ($currentWalBytes === '' || $nextWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 requires current and next WAL bytes');
        }
        if ($readerEndFrame < 0 || $readerEndFrame > $currentWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 reader frame is outside current WAL range');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 source epoch must be positive');
        }
        if ($hotJournalPages === [] || $savepointBeforePages === [] || $readerCachePages === [] || $checkpointPages === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 requires hot-journal, savepoint, reader-cache, and checkpoint pages');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $hotJournalPages = self::normalizeImages($hotJournalPages, $pageSize, 'hot-journal');
        $savepointBeforePages = self::normalizeImages($savepointBeforePages, $pageSize, 'savepoint before');
        $readerCachePages = self::normalizeReaderCache($readerCachePages, $pageSize);
        self::assertPageList($checkpointPages);

        $operations = [];
        foreach ($hotJournalPages as $pageNumber => $image) {
            self::assertDatabasePageExists($database, $pageNumber, 'hot-journal recovery');
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'hot-journal-recovered-current-source-next161',
            ];
            $operations[] = [
                'op' => 'restore_hot_journal_page_before_savepoint_checkpoint',
                'page_number' => $pageNumber,
            ];
        }

        foreach ($savepointBeforePages as $pageNumber => $image) {
            self::assertDatabasePageExists($database, $pageNumber, 'savepoint rollback');
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'rollback-to-savepoint-before-image-current-source-next161',
            ];
            $operations[] = [
                'op' => 'rollback_savepoint_page_before_checkpoint',
                'savepoint' => $savepoint,
                'page_number' => $pageNumber,
            ];
        }
        ksort($database, SORT_NUMERIC);

        $rolledBackDatabaseBytes = self::sourceBytes($database, $pageSize);
        $currentCheckpoint = $currentWal->checkpointModePlan($rolledBackDatabaseBytes, $mode, $readerEndFrame);
        $currentDurable = $currentWal->durableCheckpointResult($rolledBackDatabaseBytes, $mode, $readerEndFrame);
        $currentSourceId = self::sourceId('current', $databasePath, $savepoint, $currentWalBytes, $rolledBackDatabaseBytes, $mode, $readerEndFrame);
        $checkpointEpoch = $currentSourceEpoch + 1;
        $nextSourceId = self::sourceId('next', $databasePath, $savepoint, $nextWalBytes, (string) $currentDurable['database_bytes'], 'passive', $nextWal->frameCount());
        $nextEpoch = $checkpointEpoch + 1;

        $retainedCache = [];
        $invalidatedCache = [];
        $rows = [];
        foreach ($checkpointPages as $pageNumber) {
            self::assertDatabasePageExists($database, $pageNumber, 'checkpoint page');
            $current = $currentWal->readerSnapshotPageImage($rolledBackDatabaseBytes, $pageNumber, $readerEndFrame);
            $checkpoint = self::checkpointVisibility((string) $currentDurable['database_bytes'], (string) $currentDurable['wal_bytes'], $pageSize, $pageNumber, $readerEndFrame);
            $next = $nextWal->readerSnapshotPageImage((string) $currentDurable['database_bytes'], $pageNumber, $nextWal->frameCount());
            $cache = $readerCachePages[$pageNumber] ?? null;
            $admission = self::cacheAdmission($cache, (string) $current['image'], $currentSourceId, $checkpointEpoch, (string) $currentDurable['wal_action']);
            if ($admission['admitted']) {
                $retainedCache[] = $pageNumber;
            } else {
                $invalidatedCache[] = $pageNumber;
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'current_source' => $current['source'],
                'current_frame' => $current['frame_index'],
                'current_label' => self::label((string) $current['image']),
                'checkpoint_source' => $checkpoint['source'],
                'checkpoint_frame' => $checkpoint['frame_index'],
                'checkpoint_label' => self::label((string) $checkpoint['image']),
                'next_source' => $next['source'],
                'next_frame' => $next['frame_index'],
                'next_label' => self::label((string) $next['image']),
                'cache_admitted' => $admission['admitted'],
                'cache_reason' => $admission['reason'],
                'cache_label' => $cache['label'] ?? null,
                'cache_prefix' => is_array($cache) ? self::label($cache['image']) : null,
                'current_matches_checkpoint' => $current['image'] === $checkpoint['image'],
                'next_differs_from_current_source' => $next['image'] !== $current['image'] || $next['source'] !== $current['source'],
                'source_transition' => $current['source'] . '>checkpoint>' . $checkpoint['source'] . '>next>' . $next['source'],
            ];
            $operations[] = [
                'op' => $admission['admitted'] ? 'retain_reader_cache_for_checkpoint_current_source' : 'invalidate_reader_cache_for_checkpoint_current_source',
                'page_number' => $pageNumber,
                'reason' => $admission['reason'],
            ];
        }

        $nextDurable = $nextWal->durableCheckpointResult((string) $currentDurable['database_bytes'], 'passive', $nextWal->frameCount());
        $operations[] = [
            'op' => 'publish_checkpoint_current_source_token',
            'source_id' => $currentSourceId,
            'epoch' => $checkpointEpoch,
            'wal_action' => $currentDurable['wal_action'],
        ];
        $operations[] = [
            'op' => 'publish_next_wal_source_token',
            'source_id' => $nextSourceId,
            'epoch' => $nextEpoch,
            'wal_action' => $nextDurable['wal_action'],
        ];

        $allCurrentMatchesCheckpoint = !in_array(false, array_column($rows, 'current_matches_checkpoint'), true);
        $status = $allCurrentMatchesCheckpoint
            && $retainedCache !== []
            && $invalidatedCache !== []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next161'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next161';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next161'
                ? 'reader_cache_rebased_after_hot_journal_savepoint_checkpoint_current_source'
                : 'checkpoint_current_source_reader_cache_not_rebased',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'mode' => $mode,
            'reader_end_frame' => $readerEndFrame,
            'current_checkpoint' => $currentCheckpoint,
            'current_durable' => $currentDurable,
            'next_durable' => $nextDurable,
            'current_source_token' => ['id' => $currentSourceId, 'epoch' => $checkpointEpoch],
            'next_source_token' => ['id' => $nextSourceId, 'epoch' => $nextEpoch],
            'hot_journal_page_numbers' => array_keys($hotJournalPages),
            'savepoint_rollback_page_numbers' => array_keys($savepointBeforePages),
            'checkpoint_page_numbers' => $checkpointPages,
            'current_sources' => array_column($rows, 'current_source'),
            'checkpoint_sources' => array_column($rows, 'checkpoint_source'),
            'next_sources' => array_column($rows, 'next_source'),
            'current_frame_indexes' => array_column($rows, 'current_frame'),
            'checkpoint_frame_indexes' => array_column($rows, 'checkpoint_frame'),
            'next_frame_indexes' => array_column($rows, 'next_frame'),
            'current_labels' => array_column($rows, 'current_label'),
            'checkpoint_labels' => array_column($rows, 'checkpoint_label'),
            'next_labels' => array_column($rows, 'next_label'),
            'retained_cache_page_numbers' => $retainedCache,
            'invalidated_cache_page_numbers' => $invalidatedCache,
            'requires_reader_reopen' => $invalidatedCache !== [],
            'current_matches_checkpoint' => $allCurrentMatchesCheckpoint,
            'next_changes_pages' => in_array(true, array_column($rows, 'next_differs_from_current_source'), true),
            'rows' => $rows,
            'operations' => $operations,
            'operation_names' => array_column($operations, 'op'),
            'source_digest' => hash('sha256', $currentSourceId . '|' . $nextSourceId . '|' . implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next161',
                'sqlite-hot-journal-savepoint-checkpoint-reader-cache-token-fence',
                'sqlite-wal-reader-snapshot-page-image',
                'sqlite-wal-durable-checkpoint-result',
            ],
            'dependency_closure' => 'no new support component needed; reuses WAL parsing, reader snapshots, durable checkpoint result planning, and savepoint before-image materialization',
            'non_overlap' => 'does not repeat next159 checkpoint payload writes; this slice fences reader-cache source tokens across hot-journal recovery, rollback-to-savepoint, current checkpoint, and next WAL generation',
        ];
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $bytes, int $pageSize): array
    {
        $map = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            if (strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 database image is not page-size aligned');
            }
            $map[$index + 1] = [
                'image' => $image,
                'source' => 'database-before-hot-journal-next161',
            ];
        }

        return $map;
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function normalizeImages(array $images, int $pageSize, string $label): array
    {
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next161 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next161 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string}> $cachePages
     * @return array<int,array{image:string,source_id:string,epoch:int,pinned:bool,dirty:bool,label:string}>
     */
    private static function normalizeReaderCache(array $cachePages, int $pageSize): array
    {
        $normalized = [];
        foreach ($cachePages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 reader cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next161 reader cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            $epoch = $entry['epoch'] ?? 0;
            if ($sourceId === '' || !is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 reader cache entries require source id and positive epoch');
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'label' => (string) ($entry['label'] ?? ('cache-page-' . $pageNumber)),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next161 checkpoint pages must be one-based integers');
            }
        }
    }

    /**
     * @param array<int,array{image:string,source:string}> $database
     */
    private static function assertDatabasePageExists(array $database, int $pageNumber, string $label): void
    {
        if (!isset($database[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next161 {$label} page {$pageNumber} is outside the database image");
        }
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
                throw new \RuntimeException('SQLite WAL hot-journal savepoint checkpoint current-source next161 final image is not page-size bytes');
            }
            $bytes .= $entry['image'];
        }

        return $bytes;
    }

    /**
     * @return array{source:string,frame_index:int|null,image:string}
     */
    private static function checkpointVisibility(string $databaseBytes, string $walBytes, int $pageSize, int $pageNumber, int $readerEndFrame): array
    {
        if ($walBytes !== '') {
            $wal = SQLiteWal::parse($walBytes, $pageSize, true);
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, min($readerEndFrame, $wal->frameCount()));
        }
        $offset = ($pageNumber - 1) * $pageSize;
        $image = substr($databaseBytes, $offset, $pageSize);
        if (strlen($image) !== $pageSize) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next161 checkpoint page {$pageNumber} is outside durable database");
        }

        return [
            'source' => 'database',
            'frame_index' => null,
            'image' => $image,
        ];
    }

    /**
     * @param array{image:string,source_id:string,epoch:int,pinned:bool,dirty:bool,label:string}|null $cache
     * @return array{admitted:bool,reason:string}
     */
    private static function cacheAdmission(?array $cache, string $currentImage, string $sourceId, int $epoch, string $walAction): array
    {
        if ($cache === null) {
            return ['admitted' => false, 'reason' => 'reader_cache_missing_after_checkpoint'];
        }
        if ($cache['dirty']) {
            return ['admitted' => false, 'reason' => 'reader_cache_dirty_after_failed_savepoint'];
        }
        if ($cache['source_id'] !== $sourceId) {
            return ['admitted' => false, 'reason' => 'reader_cache_source_token_predates_checkpoint_current_source'];
        }
        if ($cache['epoch'] !== $epoch) {
            return ['admitted' => false, 'reason' => 'reader_cache_epoch_predates_checkpoint_current_source'];
        }
        if ($cache['image'] !== $currentImage) {
            return ['admitted' => false, 'reason' => 'reader_cache_image_predates_hot_journal_savepoint_checkpoint'];
        }
        if ($cache['pinned'] && in_array($walAction, ['restart_wal', 'truncate_wal'], true)) {
            return ['admitted' => false, 'reason' => 'pinned_reader_cache_must_reopen_after_wal_reset'];
        }

        return ['admitted' => true, 'reason' => 'reader_cache_matches_checkpoint_current_source_token'];
    }

    private static function sourceId(string $kind, string $databasePath, string $savepoint, string $walBytes, string $databaseBytes, string $mode, int $frame): string
    {
        return 'wal-hot-journal-savepoint-checkpoint-next161:' . $kind . ':' . substr(hash('sha256', $databasePath . '|' . $savepoint . '|' . $mode . '|' . $frame . '|' . $walBytes . '|' . $databaseBytes), 0, 24);
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }
}
