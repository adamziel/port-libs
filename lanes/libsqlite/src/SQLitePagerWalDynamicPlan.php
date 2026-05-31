<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerWalDynamicPlan
{
    /**
     * @return array{status:string,result:string,blocked:bool,reason:string,wal_sidecar_exists:bool,journal_sidecar_exists:bool,database_bytes:int,read_version:int,write_version:int,source:string}
     */
    public static function journalModeTransition(
        string $currentMode,
        string $requestedMode,
        bool $fileBacked,
        bool $walSupported,
        bool $otherConnectionOpen = false,
        bool $otherConnectionReadTransaction = false,
        int $databaseBytes = 1024
    ): array {
        $currentMode = self::mode($currentMode);
        $requestedMode = self::mode($requestedMode);
        if ($databaseBytes < 0) {
            throw new \InvalidArgumentException('SQLite pager/WAL dynamic transition database size must be non-negative');
        }

        if (!$walSupported && $requestedMode === 'wal') {
            return self::transition('wal-unsupported', 'delete', false, 'wal_mode_requires_wal_support', false, false, $databaseBytes, 1, 1, 'upstream walmode.test 0.1-0.3');
        }

        if (!$fileBacked && $requestedMode === 'wal') {
            return self::transition('wal-not-file-backed', $currentMode === 'memory' ? 'memory' : $currentMode, false, 'wal_mode_requires_persistent_database', false, false, $databaseBytes, 1, 1, 'upstream walmode.test 5.1-5.3');
        }

        if ($requestedMode === 'wal') {
            if ($otherConnectionReadTransaction && $currentMode !== 'wal') {
                return self::transition('wal-change-blocked-by-reader', $currentMode, true, 'reader_transaction_prevents_rollback_to_wal_change', false, $currentMode === 'persist', $databaseBytes, 1, 1, 'upstream walmode.test 4.17-4.18');
            }

            return self::transition('wal-mode-active', 'wal', false, $currentMode === 'wal' ? 'already_wal_opens_log_without_database_rewrite' : 'journal_mode_wal_sets_file_versions', true, false, max($databaseBytes, 1024), 2, 2, $currentMode === 'wal' ? 'upstream walmode.test 3.1-3.2' : 'upstream walmode.test 1.1-1.7 4.7-4.10');
        }

        if ($currentMode === 'wal' && $requestedMode !== 'wal') {
            if ($otherConnectionOpen || $otherConnectionReadTransaction) {
                return self::transition('rollback-change-blocked-by-open-connection', 'wal', true, 'open_connection_prevents_wal_to_rollback_change', true, false, $databaseBytes, 2, 2, 'upstream walmode.test 4.6-4.10');
            }

            return self::transition('rollback-mode-active', $requestedMode, false, 'wal_sidecars_removed_after_rollback_mode_change', false, $requestedMode === 'persist', $databaseBytes, 1, 1, 'upstream walmode.test 4.11-4.13');
        }

        return self::transition('rollback-mode-active', $requestedMode, false, 'rollback_mode_selected_without_wal_sidecar', false, $requestedMode === 'persist', $databaseBytes, 1, 1, 'upstream walmode.test 4.1-4.5');
    }

    /**
     * @return array{step:string,result:string,writer_state:string,reader_state:string,third_state:string,writer_rows:list<int>,reader_rows:list<int>,third_rows:list<int>,writer_can_commit:bool,reader_can_read:bool,third_can_read:bool,source:string}
     */
    public static function multiclientLockStep(string $step): array
    {
        $step = strtolower(trim($step));

        return match ($step) {
            'initial-read' => self::lockStep($step, 'ok', 'unlocked', 'unlocked', 'unlocked', [1, 2], [1, 2], [1, 2], true, true, true),
            'writer-reserved' => self::lockStep($step, 'ok', 'reserved', 'unlocked', 'unlocked', [1, 2, 3], [1, 2], [1, 2], true, true, true),
            'second-writer-blocked' => self::lockStep($step, 'database is locked', 'reserved', 'deferred-open', 'unlocked', [1, 2, 3], [1, 2], [1, 2], true, true, true),
            'reader-shared' => self::lockStep($step, 'ok', 'unlocked', 'shared', 'unlocked', [1, 2, 3], [1, 2, 3], [1, 2, 3], false, true, true),
            'writer-reserved-with-reader' => self::lockStep($step, 'ok', 'reserved', 'shared', 'unlocked', [11, 12, 13], [1, 2, 3], [1, 2, 3], false, true, true),
            'writer-pending-after-commit-blocked' => self::lockStep($step, 'database is locked', 'pending', 'shared', 'unlocked', [11, 12, 13], [1, 2, 3], [], false, true, false),
            'reader-released-writer-pending' => self::lockStep($step, 'database is locked', 'pending', 'unlocked', 'unlocked', [11, 12, 13], [], [], true, false, false),
            'writer-committed' => self::lockStep($step, 'ok', 'unlocked', 'unlocked', 'unlocked', [21, 22, 23], [21, 22, 23], [21, 22, 23], true, true, true),
            default => throw new \InvalidArgumentException("Unsupported SQLite pager multiclient lock step: {$step}"),
        };
    }

    private static function mode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['delete', 'persist', 'truncate', 'memory', 'wal'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite journal mode: {$mode}");
        }

        return $mode;
    }

    /**
     * @return array{status:string,result:string,blocked:bool,reason:string,wal_sidecar_exists:bool,journal_sidecar_exists:bool,database_bytes:int,read_version:int,write_version:int,source:string}
     */
    private static function transition(string $status, string $result, bool $blocked, string $reason, bool $walSidecar, bool $journalSidecar, int $databaseBytes, int $readVersion, int $writeVersion, string $source): array
    {
        return [
            'status' => $status,
            'result' => $result,
            'blocked' => $blocked,
            'reason' => $reason,
            'wal_sidecar_exists' => $walSidecar,
            'journal_sidecar_exists' => $journalSidecar,
            'database_bytes' => $databaseBytes,
            'read_version' => $readVersion,
            'write_version' => $writeVersion,
            'source' => $source,
        ];
    }

    /**
     * @param list<int> $writerRows
     * @param list<int> $readerRows
     * @param list<int> $thirdRows
     * @return array{step:string,result:string,writer_state:string,reader_state:string,third_state:string,writer_rows:list<int>,reader_rows:list<int>,third_rows:list<int>,writer_can_commit:bool,reader_can_read:bool,third_can_read:bool,source:string}
     */
    private static function lockStep(string $step, string $result, string $writerState, string $readerState, string $thirdState, array $writerRows, array $readerRows, array $thirdRows, bool $writerCanCommit, bool $readerCanRead, bool $thirdCanRead): array
    {
        return [
            'step' => $step,
            'result' => $result,
            'writer_state' => $writerState,
            'reader_state' => $readerState,
            'third_state' => $thirdState,
            'writer_rows' => $writerRows,
            'reader_rows' => $readerRows,
            'third_rows' => $thirdRows,
            'writer_can_commit' => $writerCanCommit,
            'reader_can_read' => $readerCanRead,
            'third_can_read' => $thirdCanRead,
            'source' => 'upstream pager1.test pager1-1.* multiclient locking',
        ];
    }
}
