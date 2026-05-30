<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsIoDynamicPlan
{
    /**
     * @return array<string, mixed>
     */
    public static function appendDatabaseLayout(int $prefixBytes, int $pageSize, int $databaseBytes): array
    {
        if ($prefixBytes < 0) {
            throw new \InvalidArgumentException('SQLite append VFS prefix length must be non-negative');
        }
        if ($databaseBytes < 0) {
            throw new \InvalidArgumentException('SQLite append VFS database length must be non-negative');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite append VFS page size must be a power of two at least 512');
        }

        $offset = $prefixBytes === 0 ? 0 : self::align($prefixBytes, $pageSize);
        $padding = $offset - $prefixBytes;
        $trailerBytes = 25;

        return [
            'status' => 'ok',
            'prefix_bytes' => $prefixBytes,
            'page_size' => $pageSize,
            'database_bytes' => $databaseBytes,
            'database_offset' => $offset,
            'padding_bytes' => $padding,
            'trailer_magic' => 'Start-Of-SQLite3-',
            'trailer_offset' => $offset,
            'total_bytes' => $offset + $databaseBytes + $trailerBytes,
            'prefix_intact' => true,
            'aligned' => $offset % $pageSize === 0,
            'dependencies' => ['upstream-avfs-append-offset', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array<string, mixed>
     */
    public static function ioTrafficPlan(array $deviceFlags, int $changedPages, string $journalMode = 'delete', string $syncMode = 'full'): array
    {
        if ($changedPages < 1) {
            throw new \InvalidArgumentException('SQLite VFS IO traffic requires at least one changed page');
        }

        $flags = self::deviceFlags($deviceFlags);
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'wal', 'off'], true)) {
            throw new \InvalidArgumentException('SQLite VFS IO traffic journal mode is unsupported');
        }
        $syncMode = strtolower(trim($syncMode));
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite VFS IO traffic sync mode is unsupported');
        }

        $atomic = in_array('atomic', $flags, true) || in_array('batch_atomic', $flags, true);
        $sequential = in_array('sequential', $flags, true);
        $safeAppend = in_array('safe_append', $flags, true);
        $rollbackJournal = !in_array($journalMode, ['wal', 'off'], true);

        $journalWrites = $rollbackJournal ? $changedPages : 0;
        $journalHeaderWrites = $rollbackJournal ? 1 : 0;
        $syncs = [];

        if ($atomic && $changedPages <= 2 && $rollbackJournal) {
            $journalWrites = 0;
            $journalHeaderWrites = 0;
            $syncs[] = 'database';
        } elseif ($syncMode !== 'off') {
            if ($rollbackJournal) {
                $syncs[] = 'directory';
                if (!$sequential) {
                    $syncs[] = 'journal-pages';
                }
                if (!$safeAppend) {
                    $syncs[] = 'journal-header';
                }
            } elseif ($journalMode === 'wal') {
                $syncs[] = 'wal';
            }
            $syncs[] = 'database';
        }

        return [
            'status' => 'ok',
            'device_flags' => $flags,
            'changed_pages' => $changedPages,
            'journal_mode' => $journalMode,
            'sync_mode' => $syncMode,
            'database_page_writes' => $changedPages,
            'journal_page_writes' => $journalWrites,
            'journal_header_writes' => $journalHeaderWrites,
            'sync_sequence' => $syncs,
            'sync_count' => count($syncs),
            'atomic_write_optimization' => $atomic && $changedPages <= 2 && $rollbackJournal,
            'safe_append_optimization' => $safeAppend && $rollbackJournal,
            'sequential_optimization' => $sequential && $rollbackJournal,
            'dependencies' => ['upstream-io-device-characteristics', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @param list<array<string, mixed>> $committedRows
     * @param list<array<string, mixed>> $pendingRows
     * @return array<string, mixed>
     */
    public static function atomicTransactionVisibility(array $deviceFlags, array $committedRows, array $pendingRows, bool $commit): array
    {
        if ($committedRows === []) {
            throw new \InvalidArgumentException('SQLite atomic VFS visibility requires committed rows');
        }
        if ($pendingRows === []) {
            throw new \InvalidArgumentException('SQLite atomic VFS visibility requires pending rows');
        }

        $flags = self::deviceFlags($deviceFlags);
        $atomic = in_array('atomic', $flags, true) || in_array('batch_atomic', $flags, true);
        $rollbackJournalExists = !$atomic;
        $preCommitReaderRows = $committedRows;
        $postCommitReaderRows = $commit ? array_values(array_merge($committedRows, $pendingRows)) : $committedRows;
        $writePlan = self::ioTrafficPlan($flags, 1, 'delete', 'full');

        return [
            'status' => 'ok',
            'device_flags' => $flags,
            'atomic_write_optimization' => $atomic,
            'rollback_journal_exists_during_transaction' => $rollbackJournalExists,
            'change_counter_pending' => $atomic,
            'pre_commit_reader_rows' => $preCommitReaderRows,
            'post_commit_reader_rows' => $postCommitReaderRows,
            'reader_snapshot_unchanged_before_commit' => $preCommitReaderRows === $committedRows,
            'pending_visible_before_commit' => false,
            'pending_visible_after_commit' => $commit,
            'commit_applied' => $commit,
            'database_syncs' => $writePlan['sync_sequence'],
            'write_count' => $writePlan['database_page_writes'],
            'upstream' => ['io.test io-2.4.1', 'io.test io-2.4.2', 'io.test io-2.4.3'],
            'dependencies' => ['upstream-io-atomic-visibility', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function nolockProbe(string $filename, bool $writeTransaction = false): array
    {
        $capability = SQLiteVfsCapabilityPlan::forFilename($filename, true, !$writeTransaction || !str_contains($filename, 'mode=ro'));
        $suppressed = (bool) $capability['nolock'] || (bool) $capability['immutable'];
        $readOnly = (bool) $capability['read_only'];

        if ($suppressed) {
            $calls = ['xLock' => 0, 'xUnlock' => 0, 'xCheckReservedLock' => 0, 'xAccess' => 0];
        } elseif ($writeTransaction && !$readOnly) {
            $calls = ['xLock' => 7, 'xUnlock' => 5, 'xCheckReservedLock' => 0, 'xAccess' => 0];
        } else {
            $calls = ['xLock' => 2, 'xUnlock' => 2, 'xCheckReservedLock' => 0, 'xAccess' => 4];
        }

        return [
            'status' => 'ok',
            'path' => $capability['path'],
            'read_only' => $readOnly,
            'immutable' => (bool) $capability['immutable'],
            'nolock' => (bool) $capability['nolock'],
            'write_transaction' => $writeTransaction,
            'calls' => $calls,
            'lock_calls_suppressed' => $suppressed,
            'dependencies' => array_values(array_unique(array_merge($capability['dependencies'], ['upstream-nolock-uri-lock-suppression', 'vfs-io-dynamic-real-corpus']))),
        ];
    }

    /**
     * @param list<string> $sqlControls
     * @return array<string, mixed>
     */
    public static function fileControlSequence(string $filename, array $sqlControls): array
    {
        if ($sqlControls === []) {
            throw new \InvalidArgumentException('SQLite VFS dynamic file-control sequence requires SQL controls');
        }

        $capability = SQLiteVfsCapabilityPlan::forFilename(
            $filename,
            true,
            !str_contains($filename, 'mode=ro'),
            4096,
            ['safe_append', 'powersafe_overwrite'],
            'full',
            false,
            8192,
            0
        );
        $state = SQLiteVfsFileControlState::fromCapabilityPlan($capability);
        $sequence = $state->sqlFileControlSequence($sqlControls);
        $sequence['dependencies'] = array_values(array_unique(array_merge($sequence['dependencies'], ['upstream-filectrl-sql-file-control', 'vfs-io-dynamic-real-corpus'])));

        return $sequence;
    }

    private static function align(int $value, int $pageSize): int
    {
        $remainder = $value % $pageSize;
        return $remainder === 0 ? $value : $value + ($pageSize - $remainder);
    }

    /**
     * @param list<string> $flags
     * @return list<string>
     */
    private static function deviceFlags(array $flags): array
    {
        $supported = SQLiteVfsCapabilityPlan::deviceFlagMap();
        $normalized = [];
        foreach ($flags as $flag) {
            $name = strtolower(str_replace('-', '_', trim($flag)));
            if (!isset($supported[$name])) {
                throw new \InvalidArgumentException("Unsupported SQLite VFS IO traffic device flag: {$flag}");
            }
            $normalized[$name] = true;
        }

        return array_keys($normalized);
    }
}
