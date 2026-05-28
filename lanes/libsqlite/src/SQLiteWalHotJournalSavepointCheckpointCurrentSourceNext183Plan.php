<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext183Plan
{
    /**
     * @param array<string,mixed> $applyResult
     * @param array<string,string|null> $files
     * @param list<string> $readerCacheTokens
     * @return array<string,mixed>
     */
    public static function verify(array $applyResult, array $files, array $readerCacheTokens = [], int $readerEpoch = 1): array
    {
        self::assertApplyResult($applyResult);
        if ($readerEpoch <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next183 reader epoch must be positive');
        }

        if (($applyResult['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next180') {
            return self::blocked($applyResult, $files, ['next180_apply_result_required'], $readerCacheTokens, $readerEpoch);
        }
        if (!(bool) ($applyResult['published'] ?? false) || (bool) ($applyResult['rolled_back'] ?? false)) {
            return self::blocked($applyResult, $files, ['published_non_rolled_back_next180_apply_required'], $readerCacheTokens, $readerEpoch);
        }

        $normalized = self::normalizeFiles($files);
        $rows = self::verificationRows($applyResult, $normalized);
        $blocked = [];
        if (in_array(false, array_column($rows, 'matches'), true)) {
            $blocked[] = 'published_file_payload_mismatch_after_restart';
        }
        if (self::fileDigest($normalized) !== (string) $applyResult['file_digest_after']) {
            $blocked[] = 'published_file_digest_mismatch_after_restart';
        }
        if (!self::hasDurableDirectorySync($applyResult)) {
            $blocked[] = 'directory_sync_missing_for_post_apply_current_source';
        }
        if ((bool) ($applyResult['hot_journal_deleted'] ?? false) && self::journalPresent($applyResult, $normalized)) {
            $blocked[] = 'hot_journal_reappeared_after_verified_delete';
        }

        $token = self::sourceToken($applyResult, $rows, $readerEpoch);
        $staleTokens = array_values(array_filter(
            $readerCacheTokens,
            static fn (string $candidate): bool => $candidate !== $token
        ));
        $retainedTokens = array_values(array_filter(
            $readerCacheTokens,
            static fn (string $candidate): bool => $candidate === $token
        ));
        if ($staleTokens !== []) {
            $blocked[] = 'reader_cache_token_predates_post_apply_current_source';
        }

        if ($blocked !== []) {
            return self::blocked($applyResult, $normalized, array_values(array_unique($blocked)), $readerCacheTokens, $readerEpoch, $rows, $token);
        }

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next183',
            'reason' => 'post_apply_current_source_verified_for_checkpoint_reader',
            'database_path' => $applyResult['database_path'],
            'journal_path' => $applyResult['journal_path'],
            'wal_path' => $applyResult['wal_path'],
            'reader_epoch' => $readerEpoch,
            'reader_source_token' => $token,
            'reader_cache_tokens' => $readerCacheTokens,
            'retained_reader_cache_tokens' => $retainedTokens,
            'stale_reader_cache_tokens' => [],
            'requires_reader_reopen' => false,
            'file_digest' => self::fileDigest($normalized),
            'expected_file_digest' => $applyResult['file_digest_after'],
            'digest_matches_apply_result' => self::fileDigest($normalized) === (string) $applyResult['file_digest_after'],
            'verified_rows' => $rows,
            'verified_roles' => array_column($rows, 'role'),
            'verified_paths' => array_column($rows, 'path'),
            'verified_all_match' => true,
            'hot_journal_deleted' => !self::journalPresent($applyResult, $normalized),
            'durable_paths' => $applyResult['durable_paths'],
            'synced_paths' => $applyResult['synced_paths'],
            'directory_sync_verified' => self::hasDurableDirectorySync($applyResult),
            'blocked_reasons' => [],
            'dependencies' => self::dependencies($applyResult),
            'dependency_closure' => 'no new support component needed; reuses next180 published file-map evidence to admit a post-restart WAL reader current source',
            'non_overlap' => 'next183 verifies post-apply current-source admission and reader cache tokens; it does not repeat next180 atomic publication, next177 operation planning, hot-journal recovery, checkpoint transactions, WAL byte truncation, or VFS writer/sync application',
        ];
    }

    /**
     * @param array<string,mixed> $applyResult
     * @param array<string,string|null> $files
     * @param list<string> $readerCacheTokens
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function blocked(
        array $applyResult,
        array $files,
        array $reasons,
        array $readerCacheTokens,
        int $readerEpoch,
        array $rows = [],
        ?string $token = null
    ): array {
        $normalized = self::normalizeFiles($files);
        $token ??= self::sourceToken($applyResult, $rows, $readerEpoch);
        $staleTokens = array_values(array_filter(
            $readerCacheTokens,
            static fn (string $candidate): bool => $candidate !== $token
        ));
        $retainedTokens = array_values(array_filter(
            $readerCacheTokens,
            static fn (string $candidate): bool => $candidate === $token
        ));

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next183',
            'reason' => 'post_apply_current_source_not_admitted',
            'database_path' => $applyResult['database_path'] ?? null,
            'journal_path' => $applyResult['journal_path'] ?? null,
            'wal_path' => $applyResult['wal_path'] ?? null,
            'reader_epoch' => $readerEpoch,
            'reader_source_token' => $token,
            'reader_cache_tokens' => $readerCacheTokens,
            'retained_reader_cache_tokens' => $retainedTokens,
            'stale_reader_cache_tokens' => $staleTokens,
            'requires_reader_reopen' => $staleTokens !== [],
            'file_digest' => self::fileDigest($normalized),
            'expected_file_digest' => $applyResult['file_digest_after'] ?? null,
            'digest_matches_apply_result' => isset($applyResult['file_digest_after']) && self::fileDigest($normalized) === (string) $applyResult['file_digest_after'],
            'verified_rows' => $rows,
            'verified_roles' => array_column($rows, 'role'),
            'verified_paths' => array_column($rows, 'path'),
            'verified_all_match' => $rows !== [] && !in_array(false, array_column($rows, 'matches'), true),
            'hot_journal_deleted' => !self::journalPresent($applyResult, $normalized),
            'durable_paths' => $applyResult['durable_paths'] ?? [],
            'synced_paths' => $applyResult['synced_paths'] ?? [],
            'directory_sync_verified' => self::hasDurableDirectorySync($applyResult),
            'blocked_reasons' => $reasons,
            'dependencies' => self::dependencies($applyResult),
            'dependency_closure' => 'no new support component needed; verifier blocked before admitting a post-apply reader current source',
            'non_overlap' => 'next183 blocked post-apply current-source admission without repeating accepted WAL apply or checkpoint planning surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $applyResult
     * @param array<string,string|null> $files
     * @return list<array<string,mixed>>
     */
    private static function verificationRows(array $applyResult, array $files): array
    {
        $rows = [];
        foreach ($applyResult['verified_rows'] as $row) {
            $path = (string) $row['path'];
            $bytes = $files[$path] ?? null;
            $expectedSha = $row['expected_sha256'] ?? null;
            $expectedLength = $row['expected_length'] ?? null;
            $expectsPresent = $expectedSha !== null || $expectedLength !== null;
            $matches = $expectsPresent
                ? $bytes !== null
                    && strlen($bytes) === (int) $expectedLength
                    && hash_equals((string) $expectedSha, hash('sha256', $bytes))
                : ($bytes === null || !array_key_exists($path, $files));

            $rows[] = [
                'path' => $path,
                'role' => $row['role'] ?? 'payload',
                'present' => $bytes !== null,
                'actual_sha256' => $bytes === null ? null : hash('sha256', $bytes),
                'expected_sha256' => $expectedSha,
                'actual_length' => $bytes === null ? null : strlen($bytes),
                'expected_length' => $expectedLength,
                'matches' => $matches,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $applyResult
     * @param list<array<string,mixed>> $rows
     */
    private static function sourceToken(array $applyResult, array $rows, int $epoch): string
    {
        $parts = [
            (string) ($applyResult['database_path'] ?? ''),
            (string) ($applyResult['wal_path'] ?? ''),
            (string) ($applyResult['file_digest_after'] ?? ''),
            (string) $epoch,
        ];
        foreach ($rows as $row) {
            $parts[] = (string) $row['path'] . ':' . (string) ($row['actual_sha256'] ?? 'missing');
        }

        return 'wal-hot-journal-savepoint-checkpoint-next183:current:' . substr(hash('sha256', implode('|', $parts)), 0, 32);
    }

    /**
     * @param array<string,mixed> $applyResult
     */
    private static function hasDurableDirectorySync(array $applyResult): bool
    {
        $databasePath = (string) ($applyResult['database_path'] ?? '');
        $directory = $databasePath === '' ? '' : dirname($databasePath);

        return $directory !== '' && in_array($directory, $applyResult['durable_paths'] ?? [], true);
    }

    /**
     * @param array<string,mixed> $applyResult
     * @param array<string,string|null> $files
     */
    private static function journalPresent(array $applyResult, array $files): bool
    {
        $journalPath = (string) ($applyResult['journal_path'] ?? '');

        return $journalPath !== '' && array_key_exists($journalPath, $files) && $files[$journalPath] !== null;
    }

    /**
     * @param array<string,mixed> $applyResult
     * @return list<string>
     */
    private static function dependencies(array $applyResult): array
    {
        return array_values(array_unique(array_merge($applyResult['dependencies'] ?? [], [
            'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next183',
            'sqlite-post-apply-current-source-reader-admission',
        ])));
    }

    /**
     * @param array<string,string|null> $files
     * @return array<string,string|null>
     */
    private static function normalizeFiles(array $files): array
    {
        ksort($files, SORT_STRING);
        foreach ($files as $path => $bytes) {
            if (!is_string($path) || $path === '') {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next183 file paths must be non-empty strings');
            }
            if ($bytes !== null && !is_string($bytes)) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next183 file bytes must be strings or null');
            }
        }

        return $files;
    }

    /**
     * @param array<string,string|null> $files
     */
    private static function fileDigest(array $files): string
    {
        $parts = [];
        foreach (self::normalizeFiles($files) as $path => $bytes) {
            $parts[] = $path . ':' . ($bytes === null ? 'deleted' : strlen($bytes) . ':' . hash('sha256', $bytes));
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param array<string,mixed> $applyResult
     */
    private static function assertApplyResult(array $applyResult): void
    {
        foreach (['status', 'published', 'rolled_back', 'dependencies'] as $key) {
            if (!array_key_exists($key, $applyResult)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next183 missing {$key}");
            }
        }
        if (!is_array($applyResult['dependencies'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next183 dependencies must be an array');
        }
        if (($applyResult['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next180') {
            return;
        }
        foreach (['database_path', 'journal_path', 'wal_path', 'file_digest_after', 'verified_rows', 'durable_paths', 'synced_paths'] as $key) {
            if (!array_key_exists($key, $applyResult)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next183 missing {$key}");
            }
        }
        foreach (['verified_rows', 'durable_paths', 'synced_paths'] as $key) {
            if (!is_array($applyResult[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next183 {$key} must be an array");
            }
        }
    }
}
