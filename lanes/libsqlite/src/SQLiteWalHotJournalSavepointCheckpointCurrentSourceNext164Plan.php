<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext164Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string}> $readerCachePages
     * @param list<int> $checkpointPages
     * @param list<array{name:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,closed?:bool}> $readers
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
        array $readers,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
    ): array {
        if ($readers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next164 requires reader admission candidates');
        }

        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext161Plan::plan(
            $databasePath,
            $databaseBytes,
            $pageSize,
            $savepoint,
            $hotJournalPages,
            $savepointBeforePages,
            $currentWal,
            $currentWalBytes,
            $nextWal,
            $nextWalBytes,
            $readerCachePages,
            $checkpointPages,
            $mode,
            $readerEndFrame,
            $currentSourceEpoch
        );

        $currentToken = $base['current_source_token'];
        $nextToken = $base['next_source_token'];
        $readerRows = [];
        $admitted = [];
        $reopen = [];
        foreach ($readers as $reader) {
            $row = self::readerAdmission($reader, $currentToken, $nextToken, (bool) $base['requires_reader_reopen']);
            $readerRows[] = $row;
            if ($row['admitted']) {
                $admitted[] = $row['name'];
            } else {
                $reopen[] = $row['name'];
            }
        }

        $operationNames = $base['operation_names'];
        foreach ($readerRows as $row) {
            $operationNames[] = $row['admitted']
                ? 'admit_reader_on_checkpoint_current_source_next164'
                : 'force_reader_reopen_for_next_wal_source_next164';
        }

        $status = $admitted !== [] && $reopen !== [] && (bool) $base['current_matches_checkpoint']
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next164'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next164';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next164'
                ? 'checkpoint_current_source_readers_admitted_after_hot_journal_savepoint_rollback'
                : 'checkpoint_current_source_reader_admission_incomplete',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'mode' => $base['mode'],
            'reader_end_frame' => $base['reader_end_frame'],
            'base_status' => $base['status'],
            'base_reason' => $base['reason'],
            'current_source_token' => $currentToken,
            'next_source_token' => $nextToken,
            'current_durable' => $base['current_durable'],
            'next_durable' => $base['next_durable'],
            'checkpoint_page_numbers' => $base['checkpoint_page_numbers'],
            'hot_journal_page_numbers' => $base['hot_journal_page_numbers'],
            'savepoint_rollback_page_numbers' => $base['savepoint_rollback_page_numbers'],
            'retained_cache_page_numbers' => $base['retained_cache_page_numbers'],
            'invalidated_cache_page_numbers' => $base['invalidated_cache_page_numbers'],
            'requires_reader_reopen' => $base['requires_reader_reopen'] || $reopen !== [],
            'reader_rows' => $readerRows,
            'admitted_reader_names' => $admitted,
            'reopen_reader_names' => $reopen,
            'reader_reopen_count' => count($reopen),
            'reader_admission_reasons' => array_column($readerRows, 'reason'),
            'operation_names' => $operationNames,
            'source_digest' => hash('sha256', $base['source_digest'] . '|' . implode('|', array_column($readerRows, 'transition'))),
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next164',
                'sqlite-wal-reader-admission-current-source-token',
            ]))),
            'dependency_closure' => 'no new support component needed; composes native WAL parsing, hot-journal recovery, savepoint rollback, checkpoint source tokens, and reader admission fences',
            'non_overlap' => 'does not repeat next161 cache-token rebasing or VFS writer byte application; this slice decides reader admission across checkpoint current-source and next WAL source tokens',
        ];
    }

    /**
     * @param array{name:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,closed?:bool} $reader
     * @param array{id:string,epoch:int} $currentToken
     * @param array{id:string,epoch:int} $nextToken
     * @return array{name:string,admitted:bool,reason:string,source_id:string,epoch:int,pinned:bool,dirty:bool,closed:bool,transition:string}
     */
    private static function readerAdmission(array $reader, array $currentToken, array $nextToken, bool $cacheRequiresReopen): array
    {
        $name = trim((string) ($reader['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next164 reader name is required');
        }

        $sourceId = (string) ($reader['source_id'] ?? '');
        $epoch = $reader['epoch'] ?? 0;
        if ($sourceId === '' || !is_int($epoch) || $epoch < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next164 reader source token and epoch are required');
        }

        $pinned = (bool) ($reader['pinned'] ?? false);
        $dirty = (bool) ($reader['dirty'] ?? false);
        $closed = (bool) ($reader['closed'] ?? false);
        $admitted = true;
        $reason = 'reader_matches_checkpoint_current_source_token';

        if ($closed) {
            $admitted = false;
            $reason = 'reader_closed_before_checkpoint_publish';
        } elseif ($dirty) {
            $admitted = false;
            $reason = 'reader_dirty_after_failed_savepoint';
        } elseif ($sourceId === (string) $nextToken['id'] && $epoch === (int) $nextToken['epoch']) {
            $admitted = false;
            $reason = 'reader_already_reopened_on_next_wal_source';
        } elseif ($sourceId !== (string) $currentToken['id']) {
            $admitted = false;
            $reason = 'reader_source_token_predates_checkpoint_current_source';
        } elseif ($epoch !== (int) $currentToken['epoch']) {
            $admitted = false;
            $reason = 'reader_epoch_predates_checkpoint_current_source';
        } elseif ($pinned && $cacheRequiresReopen) {
            $admitted = false;
            $reason = 'pinned_reader_must_reopen_after_cache_rebase';
        }

        return [
            'name' => $name,
            'admitted' => $admitted,
            'reason' => $reason,
            'source_id' => $sourceId,
            'epoch' => $epoch,
            'pinned' => $pinned,
            'dirty' => $dirty,
            'closed' => $closed,
            'transition' => $sourceId . '@' . $epoch . '>' . ($admitted ? 'checkpoint-current' : 'reopen-next'),
        ];
    }
}
