<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRollbackJournalCurrentNextPlan
{
    /**
     * @param array<int, string> $dirtyPages 1-indexed page numbers to committed page images.
     * @return array<string, mixed>
     */
    public static function importTransaction(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        array $dirtyPages,
        int $pageSize,
        string $syncMode = 'full',
        string $journalMode = 'delete',
    ): array {
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite rollback-journal current/next import requires database bytes');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite rollback-journal current/next import requires database bytes aligned to page size');
        }

        $commit = SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $dirtyPages, $pageSize, $syncMode, $journalMode);
        $currentBytes = $databaseBytes;
        $nextBytes = self::applyPages($databaseBytes, $dirtyPages, $pageSize);

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'journal_path' => $commit['journal_path'],
            'page_size' => $pageSize,
            'database_page_count_before' => intdiv(strlen($databaseBytes), $pageSize),
            'database_page_count_after' => intdiv(strlen($nextBytes), $pageSize),
            'dirty_page_count' => count($dirtyPages),
            'dirty_pages' => $commit['database_pages'],
            'current_reader' => self::pageSummaries($currentBytes, $dirtyPages, $pageSize, 'current_reader_pre_commit'),
            'next_reader' => self::pageSummaries($nextBytes, $dirtyPages, $pageSize, 'next_reader_after_commit'),
            'current_database_bytes' => $currentBytes,
            'next_database_bytes' => $nextBytes,
            'commit' => $commit,
            'visibility' => self::visibilityStages($databaseBytes, $nextBytes, $dirtyPages, $commit['operations'], $pageSize),
            'dependencies' => array_values(array_unique(array_merge(
                $commit['dependencies'],
                ['sqlite-rollback-journal-current-next-reader-boundary', 'application-import-rollback-journal-current-next']
            ))),
        ];
    }

    /**
     * @param array<int, string> $dirtyPages
     */
    private static function applyPages(string $databaseBytes, array $dirtyPages, int $pageSize): string
    {
        $nextBytes = $databaseBytes;
        foreach ($dirtyPages as $pageNumber => $pageImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite rollback-journal current/next dirty page numbers must be positive integers');
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite rollback-journal current/next page {$pageNumber} image must match page size");
            }

            $offset = ($pageNumber - 1) * $pageSize;
            if ($offset > strlen($nextBytes)) {
                $nextBytes = str_pad($nextBytes, $offset, "\0");
            }
            $nextBytes = substr_replace($nextBytes, $pageImage, $offset, $pageSize);
        }

        return $nextBytes;
    }

    /**
     * @param array<int, string> $dirtyPages
     * @return list<array{page_number:int,offset:int,source:string,image_prefix:string,image_length:int,changed:bool}>
     */
    private static function pageSummaries(string $databaseBytes, array $dirtyPages, int $pageSize, string $source): array
    {
        $summaries = [];
        foreach (array_keys($dirtyPages) as $pageNumber) {
            $offset = ($pageNumber - 1) * $pageSize;
            $image = substr($databaseBytes, $offset, $pageSize);
            $summaries[] = [
                'page_number' => $pageNumber,
                'offset' => $offset,
                'source' => $source,
                'image_prefix' => rtrim(substr($image, 0, 64), "\0."),
                'image_length' => strlen($image),
                'changed' => $image === $dirtyPages[$pageNumber],
            ];
        }

        return $summaries;
    }

    /**
     * @param array<int, string> $dirtyPages
     * @param list<array<string, mixed>> $operations
     * @return list<array<string, mixed>>
     */
    private static function visibilityStages(string $currentBytes, string $nextBytes, array $dirtyPages, array $operations, int $pageSize): array
    {
        $stages = [];
        $databaseWriteCount = 0;
        foreach ($operations as $index => $operation) {
            if (($operation['path'] ?? '') !== ($operations[0]['path'] ?? '') && ($operation['op'] ?? '') === 'write') {
                $databaseWriteCount++;
            }

            $committed = self::operationCompletesCommit($operation);
            $readerBytes = $committed ? $nextBytes : $currentBytes;
            $stages[] = [
                'operation_index' => $index,
                'operation' => $operation['op'] ?? 'unknown',
                'reason' => $operation['reason'] ?? 'unknown',
                'database_pages_written' => $databaseWriteCount,
                'reader_source' => $committed ? 'next_reader_after_commit' : 'current_reader_pre_commit',
                'reader_page_prefixes' => array_column(self::pageSummaries($readerBytes, $dirtyPages, $pageSize, $committed ? 'next_reader_after_commit' : 'current_reader_pre_commit'), 'image_prefix'),
                'commit_visible' => $committed,
            ];
        }

        return $stages;
    }

    /**
     * @param array<string, mixed> $operation
     */
    private static function operationCompletesCommit(array $operation): bool
    {
        return in_array($operation['reason'] ?? '', [
            'delete_rollback_journal_after_commit',
            'truncate_rollback_journal_after_commit',
            'zero_rollback_journal_header_after_commit',
            'persist_rollback_journal_commit_sidecar',
        ], true);
    }
}
