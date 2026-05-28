<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext170Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,array{image:string,checkpoint_sequence?:int|null,salt?:array{0:int,1:int}|null,frame_count?:int,dirty?:bool,label?:string}> $readerCachePages
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepoint,
        array $hotJournalPages,
        array $savepointBeforePages,
        SQLiteWal $wal,
        string $walBytes,
        array $readerCachePages,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
    ): array {
        $mode = strtolower(trim($mode));
        if ($databasePath === '' || $databaseBytes === '' || $savepoint === '' || $walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next170 requires database path, bytes, savepoint, and WAL bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next170 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next170 database bytes must be page-size aligned');
        }
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next170 mode must be passive, full, restart, or truncate');
        }
        if ($readerEndFrame !== null && ($readerEndFrame < 0 || $readerEndFrame > $wal->frameCount())) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next170 reader frame is outside the WAL range');
        }
        if ($hotJournalPages === [] || $savepointBeforePages === [] || $readerCachePages === [] || $pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next170 requires hot-journal, savepoint, cache, and page lists');
        }

        $database = self::pagesFromBytes($databaseBytes, $pageSize);
        $hotJournalPages = self::normalizeImages($hotJournalPages, $pageSize, 'hot-journal');
        $savepointBeforePages = self::normalizeImages($savepointBeforePages, $pageSize, 'savepoint-before');
        $readerCachePages = self::normalizeCache($readerCachePages, $pageSize);
        self::assertPageList($pageNumbers);

        $operations = [];
        foreach ($hotJournalPages as $pageNumber => $image) {
            self::assertPageExists($database, $pageNumber, 'hot-journal recovery');
            $database[$pageNumber] = $image;
            $operations[] = ['op' => 'recover_hot_journal_page_before_checkpoint', 'page_number' => $pageNumber];
        }
        foreach ($savepointBeforePages as $pageNumber => $image) {
            self::assertPageExists($database, $pageNumber, 'savepoint rollback');
            $database[$pageNumber] = $image;
            $operations[] = ['op' => 'rollback_savepoint_page_before_checkpoint', 'savepoint' => $savepoint, 'page_number' => $pageNumber];
        }
        ksort($database, SORT_NUMERIC);

        $rolledBackBytes = implode('', $database);
        $currentGeneration = [
            'checkpoint_sequence' => $wal->header->checkpointSequence,
            'salt' => [$wal->header->salt1, $wal->header->salt2],
            'frame_count' => $wal->frameCount(),
        ];
        $durable = $wal->durableCheckpointResult($rolledBackBytes, $mode, $readerEndFrame);
        $afterGeneration = self::afterGeneration($durable, $currentGeneration);
        $afterWal = is_string($durable['wal_bytes']) && $durable['wal_bytes'] !== ''
            ? SQLiteWal::parse((string) $durable['wal_bytes'], $pageSize, true)
            : null;
        $databaseAfterCheckpoint = (string) $durable['database_bytes'];

        $retained = [];
        $invalidated = [];
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            self::assertPageExists($database, $pageNumber, 'checkpoint page');
            $current = $wal->readerSnapshotPageImage($rolledBackBytes, $pageNumber, $readerEndFrame);
            $after = $afterWal === null || $afterWal->frameCount() === 0
                ? self::databasePageImage($databaseAfterCheckpoint, $pageSize, $pageNumber)
                : $afterWal->readerSnapshotPageImage($databaseAfterCheckpoint, $pageNumber, $afterWal->frameCount());
            $cache = $readerCachePages[$pageNumber] ?? null;
            $admission = self::admitCache($cache, (string) $current['image'], $currentGeneration, $afterGeneration);
            if ($admission['admitted']) {
                $retained[] = $pageNumber;
            } else {
                $invalidated[] = $pageNumber;
            }
            $operations[] = [
                'op' => $admission['admitted'] ? 'retain_reader_cache_after_checkpoint_generation' : 'invalidate_reader_cache_after_checkpoint_generation',
                'page_number' => $pageNumber,
                'reason' => $admission['reason'],
            ];

            $rows[] = [
                'page_number' => $pageNumber,
                'current_source' => $current['source'],
                'current_frame' => $current['frame_index'],
                'current_label' => self::label((string) $current['image']),
                'after_source' => $after['source'],
                'after_frame' => $after['frame_index'],
                'after_label' => self::label((string) $after['image']),
                'cache_label' => $cache['label'] ?? null,
                'cache_admitted' => $admission['admitted'],
                'cache_reason' => $admission['reason'],
                'image_stable' => hash_equals((string) $current['image'], (string) $after['image']),
                'generation_changed' => $currentGeneration !== $afterGeneration,
            ];
        }

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next170',
            'reason' => 'reader_cache_generation_is_fenced_when_checkpoint_resets_or_truncates_wal_after_hot_journal_savepoint',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'mode' => $mode,
            'reader_end_frame' => $readerEndFrame,
            'current_generation' => $currentGeneration,
            'after_generation' => $afterGeneration,
            'generation_changed' => $currentGeneration !== $afterGeneration,
            'wal_action' => $durable['wal_action'],
            'checkpoint_reason' => $durable['reason'],
            'checkpoint_busy' => $durable['busy'],
            'checkpointed_frame_count' => $durable['checkpointed_frame_count'],
            'remaining_committed_frame_count' => $durable['remaining_committed_frame_count'],
            'wal_bytes_length_after_checkpoint' => $durable['wal_bytes_length'],
            'retained_cache_page_numbers' => $retained,
            'invalidated_cache_page_numbers' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [],
            'page_numbers' => $pageNumbers,
            'rows' => $rows,
            'current_labels' => array_column($rows, 'current_label'),
            'after_labels' => array_column($rows, 'after_label'),
            'cache_reasons' => array_column($rows, 'cache_reason'),
            'operation_names' => array_column($operations, 'op'),
            'operations' => $operations,
            'source_digest' => hash('sha256', $databasePath . '|' . $savepoint . '|' . $mode . '|' . serialize($currentGeneration) . '|' . serialize($afterGeneration)),
            'dependencies' => [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next170',
                'sqlite-wal-durable-checkpoint-result',
                'sqlite-wal-reader-generation-fence',
            ],
            'dependency_closure' => 'no new support component needed; reuses WAL durable checkpoint results and reader snapshot page images',
            'non_overlap' => 'does not repeat next161 reader source-token fencing; this slice invalidates identical-image reader cache pages when restart/truncate checkpoint changes WAL generation',
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function pagesFromBytes(string $bytes, int $pageSize): array
    {
        $pages = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            if (strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next170 database image is not page-size aligned');
            }
            $pages[$index + 1] = $image;
        }

        return $pages;
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
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next170 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next170 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,checkpoint_sequence?:int|null,salt?:array{0:int,1:int}|null,frame_count?:int,dirty?:bool,label?:string}> $cache
     * @return array<int,array{image:string,checkpoint_sequence:int|null,salt:array{0:int,1:int}|null,frame_count:int,dirty:bool,label:string|null}>
     */
    private static function normalizeCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next170 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next170 cache page {$pageNumber} must contain a page-size image");
            }
            $salt = $entry['salt'] ?? null;
            if ($salt !== null && (!is_array($salt) || count($salt) !== 2 || !is_int($salt[0]) || !is_int($salt[1]))) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next170 cache page {$pageNumber} salt must be a two-integer tuple");
            }
            $checkpoint = $entry['checkpoint_sequence'] ?? null;
            if ($checkpoint !== null && (!is_int($checkpoint) || $checkpoint < 0)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next170 cache page {$pageNumber} checkpoint sequence must be non-negative");
            }
            $frameCount = (int) ($entry['frame_count'] ?? 0);
            if ($frameCount < 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next170 cache page {$pageNumber} frame count must be non-negative");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'checkpoint_sequence' => $checkpoint,
                'salt' => $salt,
                'frame_count' => $frameCount,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'label' => isset($entry['label']) ? (string) $entry['label'] : null,
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
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next170 page list must contain one-based integers');
            }
        }
    }

    /**
     * @param array<int,string> $database
     */
    private static function assertPageExists(array $database, int $pageNumber, string $context): void
    {
        if (!isset($database[$pageNumber])) {
            throw new \OutOfBoundsException("SQLite WAL hot-journal savepoint checkpoint next170 {$context} page {$pageNumber} is outside the database image");
        }
    }

    /**
     * @return array{source:string,frame_index:int|null,image:string}
     */
    private static function databasePageImage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $offset = ($pageNumber - 1) * $pageSize;
        $image = substr($databaseBytes, $offset, $pageSize);
        if (strlen($image) !== $pageSize) {
            throw new \OutOfBoundsException("SQLite WAL hot-journal savepoint checkpoint next170 checkpoint database page {$pageNumber} is missing");
        }

        return ['source' => 'checkpoint-database', 'frame_index' => null, 'image' => $image];
    }

    /**
     * @param array<string,mixed> $durable
     * @param array{checkpoint_sequence:int,salt:array{0:int,1:int},frame_count:int} $currentGeneration
     * @return array{checkpoint_sequence:int|null,salt:array{0:int,1:int}|null,frame_count:int}
     */
    private static function afterGeneration(array $durable, array $currentGeneration): array
    {
        if (($durable['wal_action'] ?? null) === 'preserve_wal') {
            return $currentGeneration;
        }

        $header = $durable['wal_header'] ?? null;
        if (!is_array($header)) {
            return ['checkpoint_sequence' => null, 'salt' => null, 'frame_count' => 0];
        }

        return [
            'checkpoint_sequence' => (int) $header['checkpoint_sequence'],
            'salt' => [(int) $header['salt1'], (int) $header['salt2']],
            'frame_count' => 0,
        ];
    }

    /**
     * @param array{image:string,checkpoint_sequence:int|null,salt:array{0:int,1:int}|null,frame_count:int,dirty:bool,label:string|null}|null $cache
     * @param array{checkpoint_sequence:int,salt:array{0:int,1:int},frame_count:int} $currentGeneration
     * @param array{checkpoint_sequence:int|null,salt:array{0:int,1:int}|null,frame_count:int} $afterGeneration
     * @return array{admitted:bool,reason:string}
     */
    private static function admitCache(?array $cache, string $currentImage, array $currentGeneration, array $afterGeneration): array
    {
        if ($cache === null) {
            return ['admitted' => false, 'reason' => 'reader_cache_missing_after_checkpoint_generation'];
        }
        if ($cache['dirty']) {
            return ['admitted' => false, 'reason' => 'reader_cache_dirty_after_failed_savepoint'];
        }
        if (!hash_equals($currentImage, $cache['image'])) {
            return ['admitted' => false, 'reason' => 'reader_cache_image_predates_hot_journal_savepoint_checkpoint'];
        }
        if ($cache['checkpoint_sequence'] !== $currentGeneration['checkpoint_sequence'] || $cache['salt'] !== $currentGeneration['salt'] || $cache['frame_count'] !== $currentGeneration['frame_count']) {
            return ['admitted' => false, 'reason' => 'reader_cache_not_from_current_wal_generation'];
        }
        if ($currentGeneration !== $afterGeneration) {
            return ['admitted' => false, 'reason' => 'reader_cache_generation_predates_checkpoint_reset'];
        }

        return ['admitted' => true, 'reason' => 'reader_cache_generation_matches_checkpoint'];
    }

    private static function label(string $image): string
    {
        return rtrim(strtok($image, "\0") ?: $image, ".\0 ");
    }
}
