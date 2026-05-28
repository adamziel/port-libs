<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext195Plan
{
    /**
     * @param array<string,mixed> $checkpoint
     * @param list<array<string,mixed>> $readers
     * @return array<string,mixed>
     */
    public static function plan(array $checkpoint, array $readers): array
    {
        self::assertCheckpoint($checkpoint);
        if ($readers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next195 requires reader rows');
        }

        $token = $checkpoint['current_source_token'];
        $tokenId = (string) $token['id'];
        $epoch = (int) $token['epoch'];
        $databasePath = (string) $checkpoint['database_path'];
        $walPath = (string) $checkpoint['wal_path'];
        $journalPath = (string) ($checkpoint['journal_path'] ?? ($databasePath . '-journal'));
        $checkpointCookie = (int) $checkpoint['checkpoint_cookie'];
        $schemaCookie = (int) $checkpoint['schema_cookie'];
        $walSalt = (string) $checkpoint['wal_salt'];
        $hotGeneration = (int) $checkpoint['hot_journal_generation'];
        $savepointGeneration = (int) $checkpoint['savepoint_generation'];
        $journalRemoved = (bool) $checkpoint['journal_removed'];
        $checkpointPublished = (bool) $checkpoint['checkpoint_published'];

        $rows = [];
        foreach ($readers as $index => $reader) {
            $rows[] = self::readerRow(
                $reader,
                $index,
                $tokenId,
                $epoch,
                $checkpointCookie,
                $schemaCookie,
                $walSalt,
                $hotGeneration,
                $savepointGeneration,
                $journalRemoved,
                $checkpointPublished
            );
        }

        $admitted = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name'));
        $reopened = array_values(array_column(array_filter($rows, static fn (array $row): bool => !$row['admitted']), 'name'));
        $guards = [
            'checkpoint_published' => $checkpointPublished,
            'hot_journal_removed' => $journalRemoved,
            'reader_mix' => $admitted !== [] && $reopened !== [],
            'current_source_token' => $tokenId !== '' && $epoch > 0,
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $passed): bool => !$passed));
        $canPublish = $blockedGuards === [];

        return [
            'status' => $canPublish
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next195'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next195',
            'reason' => $canPublish
                ? 'reader_retry_handles_match_hot_journal_savepoint_checkpoint_current_source'
                : 'reader_retry_handles_do_not_prove_hot_journal_savepoint_checkpoint_current_source',
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'current_source_token' => $token,
            'checkpoint_cookie' => $checkpointCookie,
            'schema_cookie' => $schemaCookie,
            'wal_salt' => $walSalt,
            'hot_journal_generation' => $hotGeneration,
            'savepoint_generation' => $savepointGeneration,
            'journal_removed' => $journalRemoved,
            'checkpoint_published' => $checkpointPublished,
            'reader_rows' => $rows,
            'admitted_reader_names' => $admitted,
            'reopen_reader_names' => $reopened,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($checkpoint['operation_names'] ?? null) ? $checkpoint['operation_names'] : [],
                ['admit_current_reader_retry_next195', 'force_reopen_stale_reader_retry_next195']
            ))),
            'reader_digest' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($checkpoint['dependencies'] ?? null) ? $checkpoint['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next195',
                    'sqlite-wal-hot-journal-reader-retry-current-source',
                    'wordpress-import-reader-reopen-after-hot-journal-checkpoint',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses lane-local WAL checkpoint tokens, hot-journal generation markers, and reader retry metadata',
            'non_overlap' => 'adds reader retry admission after hot-journal savepoint checkpoint publication; does not repeat WAL byte truncation, VFS writer apply, page-cache next191, or hot-journal checkpoint byte publication',
        ];
    }

    /**
     * @param array<string,mixed> $checkpoint
     */
    private static function assertCheckpoint(array $checkpoint): void
    {
        foreach (['database_path', 'wal_path', 'current_source_token', 'checkpoint_cookie', 'schema_cookie', 'wal_salt', 'hot_journal_generation', 'savepoint_generation', 'journal_removed', 'checkpoint_published'] as $key) {
            if (!array_key_exists($key, $checkpoint)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next195 missing checkpoint {$key}");
            }
        }
        if (!is_array($checkpoint['current_source_token'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next195 token must be an array');
        }
        $token = $checkpoint['current_source_token'];
        if (($token['id'] ?? '') === '' || (int) ($token['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next195 token is invalid');
        }
        if ((string) $checkpoint['database_path'] === '' || (string) $checkpoint['wal_path'] === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next195 paths are required');
        }
        if ((int) $checkpoint['checkpoint_cookie'] <= 0 || (int) $checkpoint['schema_cookie'] <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next195 cookies must be positive');
        }
        if ((string) $checkpoint['wal_salt'] === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next195 WAL salt is required');
        }
        if ((int) $checkpoint['hot_journal_generation'] <= 0 || (int) $checkpoint['savepoint_generation'] <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next195 generations must be positive');
        }
    }

    /**
     * @param array<string,mixed> $reader
     * @return array<string,mixed>
     */
    private static function readerRow(
        array $reader,
        int $index,
        string $tokenId,
        int $epoch,
        int $checkpointCookie,
        int $schemaCookie,
        string $walSalt,
        int $hotGeneration,
        int $savepointGeneration,
        bool $journalRemoved,
        bool $checkpointPublished
    ): array {
        foreach (['name', 'page', 'source_id', 'epoch', 'observed_checkpoint_cookie', 'observed_schema_cookie', 'observed_wal_salt', 'observed_hot_journal_generation', 'observed_savepoint_generation'] as $key) {
            if (!array_key_exists($key, $reader)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next195 missing reader {$key}");
            }
        }
        $name = (string) $reader['name'];
        $page = (int) $reader['page'];
        if ($name === '' || $page <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next195 reader name/page is invalid');
        }

        $checks = [
            'source_token' => (string) $reader['source_id'] === $tokenId,
            'source_epoch' => (int) $reader['epoch'] === $epoch,
            'checkpoint_cookie' => (int) $reader['observed_checkpoint_cookie'] === $checkpointCookie,
            'schema_cookie' => (int) $reader['observed_schema_cookie'] === $schemaCookie,
            'wal_salt' => (string) $reader['observed_wal_salt'] === $walSalt,
            'hot_journal_generation' => (int) $reader['observed_hot_journal_generation'] === $hotGeneration,
            'savepoint_generation' => (int) $reader['observed_savepoint_generation'] === $savepointGeneration,
            'journal_removed' => $journalRemoved,
            'checkpoint_published' => $checkpointPublished,
            'not_dirty' => ($reader['dirty'] ?? false) !== true,
            'not_closed' => ($reader['closed'] ?? false) !== true,
        ];
        $failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
        $admitted = $failed === [];

        return [
            'ordinal' => $index,
            'name' => $name,
            'page' => $page,
            'admitted' => $admitted,
            'requires_reopen' => !$admitted,
            'failed_checks' => $failed,
            'reason' => $admitted
                ? 'reader_retry_matches_current_hot_journal_checkpoint_source'
                : 'reader_retry_must_reopen_after_hot_journal_checkpoint',
            'transition' => $name . '>' . ($admitted ? 'admit-current-reader' : 'reopen-current-reader') . ':next195',
            'image_sha256' => isset($reader['image_sha256']) ? (string) $reader['image_sha256'] : null,
        ];
    }
}
