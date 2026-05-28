<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext177Plan
{
    /**
     * @param array<string,mixed> $resume
     * @return array<string,mixed>
     */
    public static function plan(array $resume, bool $exclusiveLockHeld = true, bool $directorySyncAvailable = true): array
    {
        self::assertResume($resume);

        if (($resume['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next174') {
            return self::blocked($resume, ['resume_state_not_current_source_next174'], $exclusiveLockHeld, $directorySyncAvailable);
        }
        if (!$exclusiveLockHeld) {
            return self::blocked($resume, ['exclusive_lock_required_before_hot_journal_checkpoint_resume'], $exclusiveLockHeld, $directorySyncAvailable);
        }

        $rows = $resume['file_rows'];
        $database = self::rowByRole($rows, ['current-checkpoint-database', 'released-checkpoint-database']);
        $journal = self::rowByRole($rows, ['hot-journal']);
        $wal = self::rowByRole($rows, ['current-reader-wal', 'released-wal', 'released-empty-wal']);
        $operations = [];
        $payloads = [];

        foreach ([$database, $wal] as $row) {
            if ($row['matches']) {
                continue;
            }
            if ($row['expected_sha256'] === null && (int) ($row['expected_length'] ?? 0) === 0) {
                $operations[] = self::op('truncate', (string) $row['path'], 0, (string) $row['replay_action']);
                continue;
            }

            $operations[] = self::op('write', (string) $row['path'], (int) $row['expected_length'], (string) $row['replay_action']);
            $operations[] = self::op('truncate', (string) $row['path'], (int) $row['expected_length'], 'trim_' . (string) $row['role'] . '_after_resume_next177');
            $operations[] = self::op('sync', (string) $row['path'], 0, 'sync_' . (string) $row['role'] . '_after_resume_next177', true);
            $payloads[(string) $row['path']] = [
                'sha256' => $row['expected_sha256'],
                'bytes' => $row['expected_length'],
            ];
        }

        if ((bool) $resume['hot_journal_delete_admitted'] && (bool) $journal['present']) {
            $operations[] = self::op('delete', (string) $journal['path'], 0, 'delete_hot_journal_after_verified_resume_next177');
        } elseif (!(bool) $resume['hot_journal_delete_admitted'] && !(bool) $journal['matches']) {
            $operations[] = self::op('write', (string) $journal['path'], (int) $journal['expected_length'], (string) $journal['replay_action']);
            $operations[] = self::op('sync', (string) $journal['path'], 0, 'sync_restored_hot_journal_for_resume_next177', true);
            $payloads[(string) $journal['path']] = [
                'sha256' => $journal['expected_sha256'],
                'bytes' => $journal['expected_length'],
            ];
        }

        if ($operations !== [] && $directorySyncAvailable) {
            $operations[] = self::op('sync_directory', dirname((string) $resume['database_path']), 0, 'persist_hot_journal_savepoint_checkpoint_resume_next177', true);
        }

        $blocked = [];
        if (!$directorySyncAvailable && $operations !== []) {
            $blocked[] = 'directory_sync_required_for_atomic_resume_publication';
        }
        if ((bool) $resume['reader_release_admitted'] && (bool) $journal['present']) {
            $blocked[] = 'reader_release_waits_for_hot_journal_delete';
        }
        if ((bool) $resume['wal_reset_admitted'] && !(bool) $wal['matches']) {
            $blocked[] = 'wal_reset_waits_for_released_wal_payload';
        }

        if ($blocked !== []) {
            return self::blocked($resume, $blocked, $exclusiveLockHeld, $directorySyncAvailable, $operations, $payloads);
        }

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next177',
            'reason' => $operations === [] ? 'verified_resume_files_need_no_vfs_apply' : 'ordered_atomic_resume_apply_for_verified_hot_journal_savepoint_checkpoint',
            'database_path' => $resume['database_path'],
            'journal_path' => $resume['journal_path'],
            'wal_path' => $resume['wal_path'],
            'exclusive_lock_held' => $exclusiveLockHeld,
            'directory_sync_available' => $directorySyncAvailable,
            'can_apply' => true,
            'noop' => $operations === [],
            'operations' => $operations,
            'operation_names' => array_column($operations, 'op'),
            'operation_reasons' => array_column($operations, 'reason'),
            'payloads' => $payloads,
            'payload_paths' => array_keys($payloads),
            'write_bytes' => array_sum(array_map(static fn (array $row): int => $row['op'] === 'write' ? (int) $row['bytes'] : 0, $operations)),
            'truncate_bytes' => array_sum(array_map(static fn (array $row): int => $row['op'] === 'truncate' ? (int) $row['bytes'] : 0, $operations)),
            'delete_count' => count(array_filter($operations, static fn (array $row): bool => $row['op'] === 'delete')),
            'durable_operation_count' => count(array_filter($operations, static fn (array $row): bool => (bool) $row['durable'])),
            'blocked_reasons' => [],
            'resume_digest' => $resume['file_digest'],
            'dependencies' => array_values(array_unique(array_merge($resume['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next177',
                'sqlite-vfs-atomic-resume-apply-order',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses native PHP WAL/hot-journal current-source verification and VFS operation metadata',
            'non_overlap' => 'extends next174 verified file-state replay with ordered atomic resume operations; does not repeat checkpoint transaction planning, VFS writer/sync/lock application, WAL byte truncation, or hot rollback-journal apply',
        ];
    }

    /**
     * @param array<string,mixed> $resume
     * @param list<string> $reasons
     * @param list<array<string,mixed>> $operations
     * @param array<string,array<string,mixed>> $payloads
     * @return array<string,mixed>
     */
    private static function blocked(array $resume, array $reasons, bool $exclusiveLockHeld, bool $directorySyncAvailable, array $operations = [], array $payloads = []): array
    {
        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next177',
            'reason' => 'atomic_resume_apply_blocked',
            'database_path' => $resume['database_path'] ?? null,
            'journal_path' => $resume['journal_path'] ?? null,
            'wal_path' => $resume['wal_path'] ?? null,
            'exclusive_lock_held' => $exclusiveLockHeld,
            'directory_sync_available' => $directorySyncAvailable,
            'can_apply' => false,
            'noop' => false,
            'operations' => $operations,
            'operation_names' => array_column($operations, 'op'),
            'operation_reasons' => array_column($operations, 'reason'),
            'payloads' => $payloads,
            'payload_paths' => array_keys($payloads),
            'blocked_reasons' => $reasons,
            'dependencies' => array_values(array_unique(array_merge($resume['dependencies'] ?? [], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next177',
            ]))),
            'dependency_closure' => 'no new support component needed; blocked before VFS apply because current-source resume prerequisites are incomplete',
            'non_overlap' => 'blocked next177 atomic resume apply without repeating accepted WAL checkpoint/savepoint byte or VFS writer surfaces',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $roles
     * @return array<string,mixed>
     */
    private static function rowByRole(array $rows, array $roles): array
    {
        foreach ($rows as $row) {
            if (in_array($row['role'] ?? null, $roles, true)) {
                return $row;
            }
        }

        throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next177 missing expected file role');
    }

    /**
     * @return array{op:string,path:string,bytes:int,durable:bool,reason:string}
     */
    private static function op(string $op, string $path, int $bytes, string $reason, bool $durable = false): array
    {
        return [
            'op' => $op,
            'path' => $path,
            'bytes' => $bytes,
            'durable' => $durable,
            'reason' => $reason,
        ];
    }

    /**
     * @param array<string,mixed> $resume
     */
    private static function assertResume(array $resume): void
    {
        foreach (['status', 'database_path', 'journal_path', 'wal_path', 'dependencies'] as $key) {
            if (!array_key_exists($key, $resume)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next177 missing {$key}");
            }
        }
        if (!is_array($resume['dependencies'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next177 dependencies must be an array');
        }
        if (($resume['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next174') {
            return;
        }
        foreach (['file_rows', 'file_digest'] as $key) {
            if (!array_key_exists($key, $resume)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next177 missing {$key}");
            }
        }
        if (!is_array($resume['file_rows'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next177 resume rows/dependencies must be arrays');
        }
    }
}
