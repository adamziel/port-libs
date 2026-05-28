<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext205Plan
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
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next205 requires reader rows');
        }

        $token = $checkpoint['current_source_token'];
        $tokenId = (string) $token['id'];
        $epoch = (int) $token['epoch'];
        $checkpointFrame = (int) $checkpoint['checkpoint_frame'];
        $checkpointCookie = (int) $checkpoint['checkpoint_cookie'];
        $schemaCookie = (int) $checkpoint['schema_cookie'];
        $walSalt = (string) $checkpoint['wal_salt'];
        $hotGeneration = (int) $checkpoint['hot_journal_generation'];
        $savepointGeneration = (int) $checkpoint['savepoint_generation'];
        $cacheGeneration = (int) $checkpoint['cache_generation'];
        $pageDigests = self::pageDigests($checkpoint['page_digests']);
        $published = (bool) $checkpoint['checkpoint_published'];
        $journalRemoved = (bool) $checkpoint['journal_removed'];

        $rows = [];
        foreach ($readers as $index => $reader) {
            $rows[] = self::readerRow(
                $reader,
                $index,
                $tokenId,
                $epoch,
                $checkpointFrame,
                $checkpointCookie,
                $schemaCookie,
                $walSalt,
                $hotGeneration,
                $savepointGeneration,
                $cacheGeneration,
                $pageDigests,
                $published,
                $journalRemoved
            );
        }

        $admitted = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name'));
        $reopened = array_values(array_column(array_filter($rows, static fn (array $row): bool => !$row['admitted']), 'name'));
        $reasons = [];
        foreach ($rows as $row) {
            foreach ($row['failed_checks'] as $reason) {
                $reasons[] = $reason;
            }
        }

        $guards = [
            'checkpoint_published' => $published,
            'hot_journal_removed' => $journalRemoved,
            'page_digest_map' => $pageDigests !== [],
            'reader_mix' => $admitted !== [] && $reopened !== [],
            'current_source_token' => $tokenId !== '' && $epoch > 0,
        ];
        $blocked = array_keys(array_filter($guards, static fn (bool $passed): bool => !$passed));

        return [
            'status' => $blocked === []
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next205'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next205',
            'reason' => $blocked === []
                ? 'reader_page_images_match_hot_journal_checkpoint_current_source'
                : 'reader_page_images_do_not_prove_hot_journal_checkpoint_current_source',
            'database_path' => (string) $checkpoint['database_path'],
            'wal_path' => (string) $checkpoint['wal_path'],
            'journal_path' => (string) ($checkpoint['journal_path'] ?? ((string) $checkpoint['database_path'] . '-journal')),
            'current_source_token' => $token,
            'checkpoint_frame' => $checkpointFrame,
            'checkpoint_cookie' => $checkpointCookie,
            'schema_cookie' => $schemaCookie,
            'wal_salt' => $walSalt,
            'hot_journal_generation' => $hotGeneration,
            'savepoint_generation' => $savepointGeneration,
            'cache_generation' => $cacheGeneration,
            'page_numbers' => array_keys($pageDigests),
            'checkpoint_published' => $published,
            'journal_removed' => $journalRemoved,
            'reader_rows' => $rows,
            'admitted_reader_names' => $admitted,
            'reopen_reader_names' => $reopened,
            'reopen_reasons' => array_values(array_unique($reasons)),
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blocked,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($checkpoint['operation_names'] ?? null) ? $checkpoint['operation_names'] : [],
                ['verify_reader_page_digest_next205', 'reopen_stale_page_cache_next205']
            ))),
            'reader_digest' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($checkpoint['dependencies'] ?? null) ? $checkpoint['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next205',
                    'sqlite-wal-hot-journal-reader-page-image-current-source',
                    'wordpress-import-reader-cache-reopen-after-hot-journal-checkpoint',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses checkpoint current-source tokens, WAL frame bounds, page-image digests, and reader cache metadata',
            'non_overlap' => 'next205 adds per-page reader cache image validation after hot-journal savepoint checkpoint publication; it does not repeat next195 token-only reader retry admission, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $checkpoint
     */
    private static function assertCheckpoint(array $checkpoint): void
    {
        foreach (['database_path', 'wal_path', 'current_source_token', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'wal_salt', 'hot_journal_generation', 'savepoint_generation', 'cache_generation', 'page_digests', 'checkpoint_published', 'journal_removed'] as $key) {
            if (!array_key_exists($key, $checkpoint)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next205 missing checkpoint {$key}");
            }
        }
        if ((string) $checkpoint['database_path'] === '' || (string) $checkpoint['wal_path'] === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next205 paths are required');
        }
        if (!is_array($checkpoint['current_source_token'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next205 token must be an array');
        }
        $token = $checkpoint['current_source_token'];
        if ((string) ($token['id'] ?? '') === '' || (int) ($token['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next205 token is invalid');
        }
        foreach (['checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'hot_journal_generation', 'savepoint_generation', 'cache_generation'] as $key) {
            if ((int) $checkpoint[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next205 {$key} must be positive");
            }
        }
        if ((string) $checkpoint['wal_salt'] === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next205 WAL salt is required');
        }
        if (!is_array($checkpoint['page_digests'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next205 page digests must be an array');
        }
    }

    /**
     * @param array<mixed,mixed> $digests
     * @return array<int,string>
     */
    private static function pageDigests(array $digests): array
    {
        $normalized = [];
        foreach ($digests as $page => $digest) {
            $pageNumber = (int) $page;
            if ($pageNumber <= 0 || !is_string($digest) || !preg_match('/^[a-f0-9]{64}$/', $digest)) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next205 page digest entries must map positive pages to sha256 strings');
            }
            $normalized[$pageNumber] = $digest;
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,mixed> $reader
     * @param array<int,string> $pageDigests
     * @return array<string,mixed>
     */
    private static function readerRow(
        array $reader,
        int $index,
        string $tokenId,
        int $epoch,
        int $checkpointFrame,
        int $checkpointCookie,
        int $schemaCookie,
        string $walSalt,
        int $hotGeneration,
        int $savepointGeneration,
        int $cacheGeneration,
        array $pageDigests,
        bool $published,
        bool $journalRemoved
    ): array {
        foreach (['name', 'page', 'source_id', 'epoch', 'observed_checkpoint_frame', 'observed_checkpoint_cookie', 'observed_schema_cookie', 'observed_wal_salt', 'observed_hot_journal_generation', 'observed_savepoint_generation', 'observed_cache_generation', 'image_sha256'] as $key) {
            if (!array_key_exists($key, $reader)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next205 missing reader {$key}");
            }
        }
        $name = (string) $reader['name'];
        $page = (int) $reader['page'];
        if ($name === '' || $page <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next205 reader name/page is invalid');
        }
        $imageSha = (string) $reader['image_sha256'];
        if (!preg_match('/^[a-f0-9]{64}$/', $imageSha)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next205 reader image digest must be a sha256 string');
        }
        $expectedDigest = $pageDigests[$page] ?? null;
        $checks = [
            'source_token' => (string) $reader['source_id'] === $tokenId,
            'source_epoch' => (int) $reader['epoch'] === $epoch,
            'checkpoint_frame' => (int) $reader['observed_checkpoint_frame'] === $checkpointFrame,
            'checkpoint_cookie' => (int) $reader['observed_checkpoint_cookie'] === $checkpointCookie,
            'schema_cookie' => (int) $reader['observed_schema_cookie'] === $schemaCookie,
            'wal_salt' => (string) $reader['observed_wal_salt'] === $walSalt,
            'hot_journal_generation' => (int) $reader['observed_hot_journal_generation'] === $hotGeneration,
            'savepoint_generation' => (int) $reader['observed_savepoint_generation'] === $savepointGeneration,
            'cache_generation' => (int) $reader['observed_cache_generation'] === $cacheGeneration,
            'page_known' => $expectedDigest !== null,
            'page_image' => $expectedDigest !== null && hash_equals($expectedDigest, $imageSha),
            'not_dirty' => ($reader['dirty'] ?? false) !== true,
            'not_closed' => ($reader['closed'] ?? false) !== true,
            'checkpoint_published' => $published,
            'journal_removed' => $journalRemoved,
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
            'expected_image_sha256' => $expectedDigest,
            'observed_image_sha256' => $imageSha,
            'reason' => $admitted
                ? 'reader_cache_page_image_matches_hot_journal_checkpoint_source'
                : 'reader_cache_page_image_requires_reopen_after_hot_journal_checkpoint',
            'transition' => $name . '>' . ($admitted ? 'reuse-page-cache' : 'reopen-page-cache') . ':next205',
        ];
    }
}
