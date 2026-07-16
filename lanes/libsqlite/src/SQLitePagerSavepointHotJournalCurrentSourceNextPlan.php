<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointHotJournalCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $currentSourcePages
     * @param array<int,string> $currentSavepointWrites
     * @param array<int,string> $nextSavepointWrites
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepoint,
        array $hotJournalPages,
        array $currentSourcePages,
        array $currentSavepointWrites,
        array $nextSavepointWrites,
        int $currentSourceEpoch = 1,
        bool $reservedLock = false,
        bool $superJournalRequired = false,
        bool $superJournalExists = false,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source requires a database path');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source database bytes must be page-size aligned');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source requires a savepoint name');
        }
        if ($hotJournalPages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source requires hot-journal pages');
        }
        if ($currentSourcePages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source requires current source pages');
        }
        if ($currentSavepointWrites === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source requires current savepoint writes');
        }
        if ($nextSavepointWrites === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source requires next savepoint writes');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source epoch must be positive');
        }

        $hotJournalPages = self::normalizePages($hotJournalPages, $pageSize, 'hot-journal');
        $currentSourcePages = self::normalizePages($currentSourcePages, $pageSize, 'current-source');
        $currentSavepointWrites = self::normalizePages($currentSavepointWrites, $pageSize, 'current-savepoint');
        $nextSavepointWrites = self::normalizePages($nextSavepointWrites, $pageSize, 'next-savepoint');

        $currentSourceVerification = self::verifyCurrentSource($databaseBytes, $currentSourcePages, $pageSize);
        $canRecoverHotJournal = !$reservedLock && (!$superJournalRequired || $superJournalExists);
        $nextEpoch = $currentSourceEpoch + 1;
        $hotDatabaseBytes = $canRecoverHotJournal
            ? self::restorePages($databaseBytes, $hotJournalPages, $pageSize)
            : $databaseBytes;

        $recoveredSource = self::sourceMap($databaseBytes, $pageSize, 'database', $currentSourceEpoch);
        if ($canRecoverHotJournal) {
            foreach ($hotJournalPages as $pageNumber => $pageImage) {
                $recoveredSource[$pageNumber] = [
                    'image' => $pageImage,
                    'source' => 'hot-journal',
                    'epoch' => $nextEpoch,
                ];
            }
        }

        $operations = [];
        $payloads = [];
        if ($canRecoverHotJournal) {
            $payloads[$databasePath . '#hot-journal'] = $hotDatabaseBytes;
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($hotDatabaseBytes),
                'payload_key' => $databasePath . '#hot-journal',
                'reason' => 'restore_hot_journal_before_savepoint_current_source',
            ];
            $operations[] = [
                'op' => 'delete',
                'path' => $databasePath . '-journal',
                'reason' => 'delete_hot_journal_before_retry_savepoint',
            ];
        }

        $savepointBefore = [];
        $workingSource = $recoveredSource;
        foreach ($currentSavepointWrites as $pageNumber => $pageImage) {
            $before = $workingSource[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            $savepointBefore[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => $workingSource[$pageNumber]['source'] ?? 'zero-fill',
                'epoch' => $workingSource[$pageNumber]['epoch'] ?? $nextEpoch,
                'reason' => 'capture_after_hot_journal_current_source',
            ];
            $workingSource[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'current-savepoint-write',
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'write_current_savepoint_page',
                'page_number' => $pageNumber,
                'source' => 'current-savepoint-write',
                'epoch' => $nextEpoch,
            ];
        }

        foreach ($savepointBefore as $pageNumber => $pageImage) {
            $workingSource[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'savepoint-rollback-before-image',
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'restore_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => 'savepoint-rollback-before-image',
                'epoch' => $nextEpoch,
            ];
        }

        $rolledBackDatabaseBytes = self::sourceBytes($workingSource, $pageSize);
        $payloads[$databasePath . '#savepoint-rollback'] = $rolledBackDatabaseBytes;

        $nextCaptured = [];
        foreach ($nextSavepointWrites as $pageNumber => $pageImage) {
            $before = $workingSource[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            $nextCaptured[] = [
                'page_number' => $pageNumber,
                'source' => $workingSource[$pageNumber]['source'] ?? 'zero-fill',
                'epoch' => $workingSource[$pageNumber]['epoch'] ?? $nextEpoch,
                'matches_current_savepoint_before_image' => isset($savepointBefore[$pageNumber]) && $savepointBefore[$pageNumber] === $before,
                'zero_filled_short_read' => !isset($workingSource[$pageNumber]),
            ];
            $operations[] = [
                'op' => 'capture_next_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => $workingSource[$pageNumber]['source'] ?? 'zero-fill',
                'epoch' => $workingSource[$pageNumber]['epoch'] ?? $nextEpoch,
                'reason' => 'next_retry_uses_rolled_back_current_source',
            ];
            $workingSource[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'next-savepoint-write',
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'write_next_savepoint_page',
                'page_number' => $pageNumber,
                'source' => 'next-savepoint-write',
                'epoch' => $nextEpoch,
            ];
        }

        ksort($workingSource, SORT_NUMERIC);

        return [
            'status' => $canRecoverHotJournal ? 'hot_journal_recovered_savepoint_current_source_next' : 'hot_journal_blocked_savepoint_current_source_preserved',
            'reason' => $canRecoverHotJournal ? 'hot_journal_recovery_precedes_current_savepoint_retry' : ($reservedLock ? 'reserved_lock_blocks_hot_journal_recovery' : 'missing_super_journal_blocks_hot_journal_recovery'),
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'current_source_epoch' => $currentSourceEpoch,
            'next_source_epoch' => $nextEpoch,
            'hot_recovered' => $canRecoverHotJournal,
            'reserved_lock' => $reservedLock,
            'super_journal_required' => $superJournalRequired,
            'super_journal_exists' => $superJournalExists,
            'current_source_verified' => $currentSourceVerification['verified'],
            'current_source_page_numbers' => array_keys($currentSourcePages),
            'current_source_prefixes' => self::prefixes($currentSourcePages),
            'hot_journal_page_numbers' => array_keys($hotJournalPages),
            'hot_journal_prefixes' => self::prefixes($hotJournalPages),
            'savepoint_captured_page_numbers' => array_keys($savepointBefore),
            'savepoint_captured_sources' => self::capturedSources($operations, 'capture_savepoint_before_image'),
            'rollback_restored_page_numbers' => array_keys($savepointBefore),
            'rolled_back_database_bytes' => $rolledBackDatabaseBytes,
            'next_written_page_numbers' => array_keys($nextSavepointWrites),
            'next_captured_pages' => $nextCaptured,
            'final_page_numbers' => array_keys($workingSource),
            'final_sources' => self::sources($workingSource),
            'operations' => $operations,
            'payloads' => $payloads,
            'dependencies' => [
                'sqlite-pager-savepoint-hot-journal-current-source-next88',
                'sqlite-hot-journal-recovery',
                'sqlite-savepoint-page-image-rollback',
                'sqlite-pager-current-source-retry',
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
                throw new \InvalidArgumentException("SQLite pager savepoint hot-journal {$label} page numbers must be one-based integers");
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint hot-journal {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $pageImage;
        }

        return $normalized;
    }

    /**
     * @param array<int,string> $pages
     * @return array{verified:bool,mismatches:list<int>}
     */
    private static function verifyCurrentSource(string $databaseBytes, array $pages, int $pageSize): array
    {
        $mismatches = [];
        foreach ($pages as $pageNumber => $pageImage) {
            if (substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize) !== $pageImage) {
                $mismatches[] = $pageNumber;
            }
        }
        if ($mismatches !== []) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal current-source pages must match the current database image');
        }

        return ['verified' => true, 'mismatches' => []];
    }

    /**
     * @param array<int,string> $pages
     */
    private static function restorePages(string $databaseBytes, array $pages, int $pageSize): string
    {
        $bytes = $databaseBytes;
        foreach ($pages as $pageNumber => $pageImage) {
            $bytes = substr_replace($bytes, $pageImage, ($pageNumber - 1) * $pageSize, $pageSize);
        }

        return $bytes;
    }

    /**
     * @return array<int,array{image:string,source:string,epoch:int}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize, string $source, int $epoch): array
    {
        $map = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $map[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'source' => $source,
                'epoch' => $epoch,
            ];
        }

        return $map;
    }

    /**
     * @param array<int,array{image:string,source:string,epoch:int}> $source
     */
    private static function sourceBytes(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $max = max(array_keys($source));
        $bytes = '';
        for ($pageNumber = 1; $pageNumber <= $max; $pageNumber++) {
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
            $prefixes[$pageNumber] = rtrim(substr($pageImage, 0, 48), ".\0");
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string,epoch:int}> $source
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
     * @param list<array<string,mixed>> $operations
     * @return array<int,string>
     */
    private static function capturedSources(array $operations, string $op): array
    {
        $sources = [];
        foreach ($operations as $operation) {
            if (($operation['op'] ?? null) === $op) {
                $sources[(int) $operation['page_number']] = (string) $operation['source'];
            }
        }

        return $sources;
    }
}
