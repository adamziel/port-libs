<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext174Plan
{
    /**
     * @param list<int> $pageNumbers
     * @param list<string> $completedOperationReasons
     * @param array<string,string|null> $files
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $dirtyDatabaseBytes,
        string $journalBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        array $pageNumbers,
        array $completedOperationReasons,
        array $files,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext169Plan::plan(
            $databasePath,
            $dirtyDatabaseBytes,
            $journalBytes,
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $pageNumbers,
            $completedOperationReasons,
            $mode,
            $readerEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists,
        );

        if (($base['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next169') {
            return array_merge($base, [
                'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next174',
                'reason' => $base['reason'] ?? 'resume_plan_not_admitted',
                'recovery_admitted' => false,
                'dependencies' => array_values(array_unique(array_merge(
                    $base['dependencies'] ?? [],
                    ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next174']
                ))),
            ]);
        }

        $journalPath = (string) $base['journal_path'];
        $walPath = (string) $base['wal_path'];
        $expected = self::expectedFiles($base, $journalBytes);
        $actual = self::normalizeFiles($files, [$databasePath, $journalPath, $walPath]);
        $rows = [];
        foreach ($expected as $path => $rule) {
            $bytes = $actual[$path] ?? null;
            $present = $bytes !== null;
            $sha = $present ? hash('sha256', $bytes) : null;
            $matches = self::matchesRule($bytes, $rule);
            $rows[] = [
                'path' => $path,
                'role' => $rule['role'],
                'required' => $rule['required'],
                'present' => $present,
                'actual_sha256' => $sha,
                'expected_sha256' => $rule['sha256'],
                'expected_length' => $rule['length'],
                'actual_length' => $present ? strlen((string) $bytes) : null,
                'matches' => $matches,
                'replay_action' => $matches ? 'verify_persisted' : $rule['replay_action'],
                'delete_admitted' => $rule['role'] !== 'hot-journal' || self::hotJournalDeleteAdmitted($base, $actual, $expected),
                'reader_release_admitted' => self::readerReleaseAdmitted($base, $actual, $expected),
                'wal_reset_admitted' => self::walResetAdmitted($base, $actual, $expected),
            ];
        }

        $missingRequired = array_values(array_filter($rows, static fn (array $row): bool => (bool) $row['required'] && !$row['present']));
        $mismatched = array_values(array_filter($rows, static fn (array $row): bool => !$row['matches']));
        $needsReplay = $mismatched !== [];

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next174',
            'reason' => 'verify_partial_hot_journal_savepoint_checkpoint_files_before_resume',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'wal_path' => $walPath,
            'savepoint' => $savepoint,
            'mode' => $base['mode'],
            'page_size' => $base['page_size'],
            'page_numbers' => $base['page_numbers'],
            'recovery_admitted' => true,
            'resume_complete' => $base['resume_complete'],
            'needs_replay' => $needsReplay,
            'missing_required_paths' => array_column($missingRequired, 'path'),
            'mismatched_paths' => array_column($mismatched, 'path'),
            'replay_actions' => array_values(array_unique(array_column($mismatched, 'replay_action'))),
            'hot_journal_delete_admitted' => self::hotJournalDeleteAdmitted($base, $actual, $expected),
            'journal_required_for_recovery' => !self::journalDeleted($actual, $journalPath),
            'reader_release_admitted' => self::readerReleaseAdmitted($base, $actual, $expected),
            'wal_reset_admitted' => self::walResetAdmitted($base, $actual, $expected),
            'file_rows' => $rows,
            'file_roles' => array_column($rows, 'role'),
            'file_digest' => hash('sha256', implode('|', array_map(
                static fn (array $row): string => $row['path'] . ':' . $row['role'] . ':' . ($row['actual_sha256'] ?? 'missing') . ':' . ($row['matches'] ? 'match' : 'replay'),
                $rows
            ))),
            'base_phase_digest' => $base['phase_digest'],
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next174',
                'sqlite-wal-hot-journal-checkpoint-file-resume',
                'wordpress-import-hot-journal-savepoint-file-replay',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses native PHP WAL, hot-journal recovery, savepoint rollback, checkpoint payload, and VFS file-state primitives',
            'non_overlap' => 'extends next169 crash-resume with concrete file-state replay admission; avoids duplicating WAL byte truncation, VFS writer/sync/lock, rollback-journal commit/apply, checkpoint transaction, and reader-token cache surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,array{role:string,required:bool,sha256:string|null,length:int|null,replay_action:string}>
     */
    private static function expectedFiles(array $base, string $journalBytes): array
    {
        $payloads = $base['base_plan']['payloads'];
        $databasePath = (string) $base['database_path'];
        $journalPath = (string) $base['journal_path'];
        $walPath = (string) $base['wal_path'];
        $currentDatabaseKey = $databasePath . '#next165-current-checkpoint';
        $currentWalKey = $walPath . '#next165-current-reader';
        $releasedDatabaseKey = $databasePath . '#next165-released-checkpoint';
        $releasedWalKey = $walPath . '#next165-released-reader';

        $databaseKey = $base['released_database_synced'] ? $releasedDatabaseKey : $currentDatabaseKey;
        $walKey = $base['released_database_synced'] ? $releasedWalKey : $currentWalKey;
        $walPayload = (string) $payloads[$walKey];

        return [
            $databasePath => [
                'role' => $base['released_database_synced'] ? 'released-checkpoint-database' : 'current-checkpoint-database',
                'required' => true,
                'sha256' => hash('sha256', (string) $payloads[$databaseKey]),
                'length' => strlen((string) $payloads[$databaseKey]),
                'replay_action' => $base['released_database_synced'] ? 'rewrite_released_checkpoint_database_payload' : 'rewrite_current_checkpoint_database_payload',
            ],
            $journalPath => [
                'role' => 'hot-journal',
                'required' => !$base['journal_delete_completed'],
                'sha256' => $base['journal_delete_completed'] ? null : hash('sha256', $journalBytes),
                'length' => $base['journal_delete_completed'] ? null : strlen($journalBytes),
                'replay_action' => $base['journal_delete_completed'] ? 'delete_hot_journal_after_current_payloads_durable' : 'restore_hot_journal_for_resume',
            ],
            $walPath => [
                'role' => $walPayload === '' ? 'released-empty-wal' : ($base['released_database_synced'] ? 'released-wal' : 'current-reader-wal'),
                'required' => $walPayload !== '',
                'sha256' => $walPayload === '' ? null : hash('sha256', $walPayload),
                'length' => $walPayload === '' ? 0 : strlen($walPayload),
                'replay_action' => $walPayload === '' ? 'truncate_released_wal_sidecar' : ($base['released_database_synced'] ? 'rewrite_released_wal_payload' : 'rewrite_retained_wal_for_reader'),
            ],
        ];
    }

    /**
     * @param array<string,string|null> $files
     * @param list<string> $knownPaths
     * @return array<string,string|null>
     */
    private static function normalizeFiles(array $files, array $knownPaths): array
    {
        $normalized = array_fill_keys($knownPaths, null);
        foreach ($files as $path => $bytes) {
            if (!is_string($path) || $path === '') {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next174 file paths must be non-empty strings');
            }
            if ($bytes !== null && !is_string($bytes)) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next174 file bytes must be strings or null');
            }
            $normalized[$path] = $bytes;
        }

        return $normalized;
    }

    /**
     * @param array{required:bool,sha256:string|null,length:int|null} $rule
     */
    private static function matchesRule(?string $bytes, array $rule): bool
    {
        if ($bytes === null) {
            return !$rule['required'];
        }
        if ($rule['sha256'] === null) {
            return $bytes === '';
        }

        return strlen($bytes) === $rule['length'] && hash_equals($rule['sha256'], hash('sha256', $bytes));
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,string|null> $actual
     * @param array<string,array{role:string,required:bool,sha256:string|null,length:int|null,replay_action:string}> $expected
     */
    private static function hotJournalDeleteAdmitted(array $base, array $actual, array $expected): bool
    {
        return (bool) $base['current_database_synced']
            && self::matchesRule($actual[(string) $base['database_path']] ?? null, $expected[(string) $base['database_path']])
            && self::matchesRule($actual[(string) $base['wal_path']] ?? null, $expected[(string) $base['wal_path']]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,string|null> $actual
     * @param array<string,array{role:string,required:bool,sha256:string|null,length:int|null,replay_action:string}> $expected
     */
    private static function readerReleaseAdmitted(array $base, array $actual, array $expected): bool
    {
        return self::hotJournalDeleteAdmitted($base, $actual, $expected)
            && self::journalDeleted($actual, (string) $base['journal_path']);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,string|null> $actual
     * @param array<string,array{role:string,required:bool,sha256:string|null,length:int|null,replay_action:string}> $expected
     */
    private static function walResetAdmitted(array $base, array $actual, array $expected): bool
    {
        return self::readerReleaseAdmitted($base, $actual, $expected)
            && (bool) $base['wal_reset_admitted']
            && self::matchesRule($actual[(string) $base['database_path']] ?? null, $expected[(string) $base['database_path']]);
    }

    /**
     * @param array<string,string|null> $actual
     */
    private static function journalDeleted(array $actual, string $journalPath): bool
    {
        return !array_key_exists($journalPath, $actual) || $actual[$journalPath] === null;
    }
}
