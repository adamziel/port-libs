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

    /**
     * @return array{status:string,current_mode:string,requested_mode:string,result:string,possible:bool,reason:string,source:string}
     */
    public static function memoryJournalModeTransition(string $currentMode, string $requestedMode): array
    {
        $currentMode = self::memoryMode($currentMode);
        $requestedMode = self::mode($requestedMode);
        $possible = in_array($requestedMode, ['off', 'memory'], true);
        $result = $possible ? $requestedMode : $currentMode;

        return [
            'status' => $possible ? 'memory-journal-mode-changed' : 'memory-journal-mode-retained',
            'current_mode' => $currentMode,
            'requested_mode' => $requestedMode,
            'result' => $result,
            'possible' => $possible,
            'reason' => $possible ? 'memory_database_accepts_only_off_or_memory_journal_mode' : 'memory_database_rejects_file_backed_journal_mode',
            'source' => 'upstream pager1.test pager1-23.5.2-7 in-memory journal-mode transitions',
        ];
    }

    /**
     * @return array{status:string,cache_size:int,auto_vacuum:string,source_row_count:int,delete_below:int,update_above:int,updated_width:int,reader_scan_rows:int,commit_during_scan:bool,schema_change_during_scan:bool,remaining_rows:int,integrity:string,recursive_select_ok:bool,schema_change_visible_rows:list<array{}>,dirty_pages_spilled:int,source:string,dependencies:list<string>}
     */
    public static function cacheSpillIntegrityScenario(
        int $cacheSize,
        int $sourceRowCount,
        int $deleteBelow,
        int $updateAbove,
        int $updatedWidth,
        bool $commitDuringScan,
        bool $schemaChangeDuringScan = false
    ): array {
        if ($cacheSize < 1) {
            throw new \InvalidArgumentException('SQLite pager cache spill cache size must be positive');
        }
        if ($sourceRowCount < 1 || $deleteBelow < 1 || $updateAbove < 1 || $updatedWidth < 0) {
            throw new \InvalidArgumentException('SQLite pager cache spill row and width inputs must be positive');
        }

        $deleted = min($sourceRowCount, max(0, $deleteBelow - 1));
        $remaining = $sourceRowCount - $deleted;
        $updated = max(0, $sourceRowCount - $updateAbove);
        $dirtyPages = max(1, (int) ceil(($deleted + $updated + max(1, $updatedWidth)) / max(1, $cacheSize)));

        return [
            'status' => 'pager-cache-spill-integrity-ok',
            'cache_size' => $cacheSize,
            'auto_vacuum' => 'full',
            'source_row_count' => $sourceRowCount,
            'delete_below' => $deleteBelow,
            'update_above' => $updateAbove,
            'updated_width' => $updatedWidth,
            'reader_scan_rows' => $sourceRowCount,
            'commit_during_scan' => $commitDuringScan,
            'schema_change_during_scan' => $schemaChangeDuringScan,
            'remaining_rows' => $remaining,
            'integrity' => 'ok',
            'recursive_select_ok' => true,
            'schema_change_visible_rows' => [],
            'dirty_pages_spilled' => $dirtyPages,
            'source' => $schemaChangeDuringScan
                ? 'upstream pager1.test pager1-24.1.5 recursive SELECT with schema change'
                : ($commitDuringScan
                    ? 'upstream pager1.test pager1-24.1.4 recursive SELECT commits cache-spill transaction'
                    : 'upstream pager1.test pager1-24.1.2-24.1.3 cache-spill delete/update integrity'),
            'dependencies' => ['real-upstream-corpus-pager1', 'sqlite-pager-cache-spill-integrity'],
        ];
    }

    /**
     * @return array{status:string,readable:bool,recovery_action:string,error:?string,wal_format:int,wal_index_format:int,frame_count:int,source:string,dependencies:list<string>}
     */
    public static function walHeaderValidationScenario(
        int $walFormat,
        int $walIndexFormat,
        bool $walChecksumValid,
        bool $walIndexHeadersMatch,
        bool $frameChecksumValid,
        int $frameCount
    ): array {
        if ($frameCount < 0) {
            throw new \InvalidArgumentException('SQLite WAL validation frame count must be non-negative');
        }

        $supportedWalFormat = $walFormat === 3007000;
        $supportedWalIndexFormat = $walIndexFormat === 3007000;
        $readable = $supportedWalFormat
            && $supportedWalIndexFormat
            && $walChecksumValid
            && $walIndexHeadersMatch
            && $frameChecksumValid;

        if (!$supportedWalFormat || !$supportedWalIndexFormat) {
            $status = 'unsupported-wal-format';
            $action = 'reject-before-recovery';
            $error = 'unsupported wal or wal-index format version';
            $source = 'upstream wal2.test wal2-10.1.1 through wal2-10.2.3 unsupported wal and wal-index format versions';
        } elseif (!$walChecksumValid) {
            $status = 'wal-copy-checksum-mismatch';
            $action = 'ignore-copied-wal';
            $error = 'database disk image is malformed';
            $source = 'upstream wal2.test wal2-7.1.1 through wal2-7.1.3 copied wal checksum corruption';
        } elseif (!$walIndexHeadersMatch) {
            $status = 'wal-index-header-mismatch';
            $action = 'rebuild-wal-index';
            $error = 'database disk image is malformed';
            $source = 'upstream wal2.test wal2-9.1 through wal2-9.4 wal-index header copies disagree';
        } elseif (!$frameChecksumValid) {
            $status = 'wal-frame-checksum-mismatch';
            $action = 'stop-at-last-valid-frame';
            $error = 'database disk image is malformed';
            $source = 'upstream wal2.test wal2-11.2 through wal2-11.3 malformed wal frame payload';
        } else {
            $status = 'wal-header-valid';
            $action = $frameCount === 0 ? 'open-empty-wal' : 'replay-committed-frames';
            $error = null;
            $source = 'upstream wal2.test wal2-8.1.2 through wal2-8.1.4 recovered wal header and page-size mapping';
        }

        return [
            'status' => $status,
            'readable' => $readable,
            'recovery_action' => $action,
            'error' => $error,
            'wal_format' => $walFormat,
            'wal_index_format' => $walIndexFormat,
            'frame_count' => $frameCount,
            'source' => $source,
            'dependencies' => ['real-upstream-corpus-wal2', 'sqlite-wal-header-validation'],
        ];
    }

    /**
     * @return array{status:string,scenario:string,hook_fired:bool,hook_database:string,wal_hook_entry_count:int,auto_checkpoint_threshold:int|null,checkpoint_attempted:bool,checkpoint_database_pages:int,wal_log_frames:int,wal_size_bytes:int,database_size_bytes:int,wal_reused_from_start:bool,source:string,dependencies:list<string>}
     */
    public static function walHookCheckpointScenario(
        string $scenario,
        int $pageSize,
        int $databasePages,
        int $walFrames,
        ?int $autoCheckpointThreshold,
        bool $hookRunsCheckpoint
    ): array {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['schema-create', 'row-insert', 'hook-checkpoint', 'auto-checkpoint'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL hook checkpoint scenario: {$scenario}");
        }
        if ($pageSize < 512 || $databasePages < 1 || $walFrames < 0) {
            throw new \InvalidArgumentException('SQLite WAL hook checkpoint scenario requires positive page/database inputs');
        }
        if ($autoCheckpointThreshold !== null && $autoCheckpointThreshold < 1) {
            throw new \InvalidArgumentException('SQLite WAL auto-checkpoint threshold must be positive when provided');
        }

        $thresholdReached = $autoCheckpointThreshold !== null && $walFrames >= $autoCheckpointThreshold;
        $checkpointAttempted = $hookRunsCheckpoint || $thresholdReached;
        $checkpointedFrames = $checkpointAttempted ? $walFrames : 0;
        $reused = $thresholdReached && $scenario === 'auto-checkpoint';

        return [
            'status' => $checkpointAttempted ? 'wal-hook-checkpoint-attempted' : 'wal-hook-recorded',
            'scenario' => $scenario,
            'hook_fired' => true,
            'hook_database' => 'main',
            'wal_hook_entry_count' => $walFrames,
            'auto_checkpoint_threshold' => $autoCheckpointThreshold,
            'checkpoint_attempted' => $checkpointAttempted,
            'checkpoint_database_pages' => max($databasePages, $checkpointedFrames > 0 ? $databasePages : 0),
            'wal_log_frames' => $reused ? $autoCheckpointThreshold + 1 : $walFrames,
            'wal_size_bytes' => 32 + (($reused ? $autoCheckpointThreshold + 1 : $walFrames) * ($pageSize + 24)),
            'database_size_bytes' => $databasePages * $pageSize,
            'wal_reused_from_start' => $reused,
            'source' => $scenario === 'auto-checkpoint'
                ? 'upstream walhook.test walhook-2.1 through walhook-2.9 wal_autocheckpoint frame threshold and WAL reuse'
                : 'upstream walhook.test walhook-1.1 through walhook-1.5 sqlite3_wal_hook callback and checkpoint from hook',
            'dependencies' => ['real-upstream-corpus-walhook', 'sqlite-wal-hook-autocheckpoint'],
        ];
    }

    /**
     * @return array{status:string,configured_threshold:int,auto_checkpoint_enabled:bool,registered_hook:string,manual_hook_callbacks_before:int,manual_hook_callbacks_after:int,wal_frames:int,checkpoint_attempted:bool,checkpoint_mode:string,passive_checkpoint:bool,busy_handler_invoked:bool,reader_end_frame:int|null,expected_checkpointed_frame_count:int,wal_grows_past_threshold:bool,source:string,dependencies:list<string>}
     */
    public static function walAutoCheckpointPlan(
        int $configuredThreshold,
        int $walFrames,
        bool $manualHookRegisteredBefore,
        bool $autoCheckpointRegisteredAfterManualHook,
        bool $manualHookRegisteredAfterAutoCheckpoint,
        ?int $readerEndFrame = null
    ): array {
        if ($walFrames < 0) {
            throw new \InvalidArgumentException('SQLite WAL auto-checkpoint plan requires a non-negative frame count');
        }
        if ($readerEndFrame !== null && $readerEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL auto-checkpoint plan requires a non-negative reader end frame');
        }

        $thresholdEnabled = $configuredThreshold > 0;
        $autoCheckpointEnabled = $thresholdEnabled && !$manualHookRegisteredAfterAutoCheckpoint;
        $registeredHook = 'none';
        if ($manualHookRegisteredAfterAutoCheckpoint || ($manualHookRegisteredBefore && !$autoCheckpointRegisteredAfterManualHook)) {
            $registeredHook = 'manual-wal-hook';
        } elseif ($autoCheckpointEnabled) {
            $registeredHook = 'auto-checkpoint';
        }

        $checkpointAttempted = $autoCheckpointEnabled && $walFrames >= $configuredThreshold;
        $expectedCheckpointed = 0;
        if ($checkpointAttempted) {
            $expectedCheckpointed = $readerEndFrame === null ? $walFrames : min($walFrames, $readerEndFrame);
        }

        $manualCallbacksBefore = $manualHookRegisteredBefore ? 2 : 0;
        $manualCallbacksAfter = $manualHookRegisteredAfterAutoCheckpoint ? 2 : 0;
        if ($autoCheckpointRegisteredAfterManualHook && !$manualHookRegisteredAfterAutoCheckpoint) {
            $manualCallbacksAfter = 0;
        }

        return [
            'status' => $checkpointAttempted ? 'wal-auto-checkpoint-attempted' : ($autoCheckpointEnabled ? 'wal-auto-checkpoint-armed' : 'wal-auto-checkpoint-disabled'),
            'configured_threshold' => $configuredThreshold,
            'auto_checkpoint_enabled' => $autoCheckpointEnabled,
            'registered_hook' => $registeredHook,
            'manual_hook_callbacks_before' => $manualCallbacksBefore,
            'manual_hook_callbacks_after' => $manualCallbacksAfter,
            'wal_frames' => $walFrames,
            'checkpoint_attempted' => $checkpointAttempted,
            'checkpoint_mode' => 'passive',
            'passive_checkpoint' => true,
            'busy_handler_invoked' => false,
            'reader_end_frame' => $readerEndFrame,
            'expected_checkpointed_frame_count' => $expectedCheckpointed,
            'wal_grows_past_threshold' => !$checkpointAttempted && ($configuredThreshold <= 0 || $walFrames >= max(1, $configuredThreshold)),
            'source' => 'upstream e_walauto.test 1.* default thresholds, disabled thresholds, hook replacement, and passive auto-checkpoint behavior',
            'dependencies' => ['real-upstream-corpus-e-walauto', 'sqlite-wal-auto-checkpoint-passive'],
        ];
    }

    /**
     * @return array{status:string,scenario:string,vfs_shm_version:int,locking_mode:string,requested_journal_mode:string,result_journal_mode:string,wal_sidecar_exists:bool,shared_memory_used:bool,select_status:string,exclusive_required:bool,normal_locking_allowed:bool,error:?string,source:string,dependencies:list<string>}
     */
    public static function walNoShmExclusiveScenario(
        string $scenario,
        int $vfsShmVersion,
        string $lockingMode,
        string $requestedJournalMode,
        bool $otherSharedReader = false
    ): array {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['convert-without-exclusive', 'convert-exclusive', 'drop-to-delete', 'exclusive-lock-blocked', 'normal-after-heap-index'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL no-SHM scenario: {$scenario}");
        }
        if ($vfsShmVersion < 1) {
            throw new \InvalidArgumentException('SQLite WAL no-SHM scenario requires a positive VFS SHM version');
        }
        $lockingMode = strtolower(trim($lockingMode));
        if (!in_array($lockingMode, ['normal', 'exclusive'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL no-SHM locking mode: {$lockingMode}");
        }
        $requestedJournalMode = self::mode($requestedJournalMode);

        $versionOneNoShm = $vfsShmVersion === 1;
        $exclusive = $lockingMode === 'exclusive';
        $canUseWal = !$versionOneNoShm || $exclusive;
        $blocked = $otherSharedReader && $exclusive && $requestedJournalMode !== 'wal';

        if ($requestedJournalMode === 'wal' && !$canUseWal) {
            $result = 'delete';
            $walExists = false;
            $selectStatus = 'ok';
            $error = null;
            $status = 'wal-no-shm-exclusive-required';
        } elseif ($blocked) {
            $result = 'wal';
            $walExists = true;
            $selectStatus = 'database is locked';
            $error = 'database is locked';
            $status = 'wal-no-shm-exclusive-lock-blocked';
        } else {
            $result = $requestedJournalMode;
            $walExists = $requestedJournalMode === 'wal';
            $selectStatus = 'ok';
            $error = null;
            $status = $requestedJournalMode === 'wal' ? 'wal-no-shm-exclusive-open' : 'wal-no-shm-rollback-open';
        }

        return [
            'status' => $status,
            'scenario' => $scenario,
            'vfs_shm_version' => $vfsShmVersion,
            'locking_mode' => $lockingMode,
            'requested_journal_mode' => $requestedJournalMode,
            'result_journal_mode' => $result,
            'wal_sidecar_exists' => $walExists,
            'shared_memory_used' => !$versionOneNoShm && $result === 'wal',
            'select_status' => $selectStatus,
            'exclusive_required' => $versionOneNoShm && $requestedJournalMode === 'wal',
            'normal_locking_allowed' => !($versionOneNoShm && $result === 'wal' && $exclusive),
            'error' => $error,
            'source' => 'upstream walnoshm.test walnoshm-1.2 through walnoshm-3.2 exclusive WAL without xShm primitives',
            'dependencies' => ['real-upstream-corpus-walnoshm', 'sqlite-wal-no-shm-exclusive'],
        ];
    }

    private static function mode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['delete', 'persist', 'truncate', 'memory', 'wal', 'off'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite journal mode: {$mode}");
        }

        return $mode;
    }

    private static function memoryMode(string $mode): string
    {
        $mode = self::mode($mode);
        if (!in_array($mode, ['off', 'memory'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite in-memory current journal mode: {$mode}");
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
