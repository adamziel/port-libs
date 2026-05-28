<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext187Plan
{
    /**
     * @param array<string,mixed> $postApply
     * @param array<string,mixed> $reopen
     * @param list<string> $readerTokens
     * @return array<string,mixed>
     */
    public static function plan(array $postApply, array $reopen, array $readerTokens, bool $hotJournalObserved = true): array
    {
        self::assertInput($postApply, $reopen, $readerTokens);

        $postApplyReady = ($postApply['status'] ?? '') === 'wal-hot-journal-savepoint-checkpoint-current-source-next183'
            && ($postApply['verified_all_match'] ?? false) === true
            && ($postApply['directory_sync_verified'] ?? false) === true
            && ($postApply['hot_journal_deleted'] ?? false) === true;
        $reopenReady = ($reopen['status'] ?? '') === 'wal-hot-journal-savepoint-checkpoint-current-source-next184'
            && ($reopen['can_reuse_reader_marks'] ?? false) === true
            && ($reopen['all_reader_pages_separated'] ?? false) === true
            && ($reopen['salt_pair_rotated'] ?? false) === true
            && ($reopen['checkpoint_sequence_advanced'] ?? false) === true;

        $postToken = (string) $postApply['reader_source_token'];
        $transitionToken = 'wal-hot-journal-savepoint-checkpoint-next187:retry:' . substr(hash('sha256', implode('|', [
            $postToken,
            (string) $reopen['source_transition_digest'],
            (string) $postApply['file_digest'],
            (string) $reopen['next_wal_sha256'],
            implode(',', array_map('strval', $reopen['reader_page_numbers'])),
        ])), 0, 32);

        $tokenRows = [];
        foreach ($readerTokens as $token) {
            $classification = 'stale';
            if ($token === $transitionToken) {
                $classification = 'retry-current';
            } elseif ($token === $postToken) {
                $classification = 'post-apply-current';
            }
            $tokenRows[] = [
                'token' => $token,
                'classification' => $classification,
                'retained_for_retry' => $classification === 'retry-current',
                'requires_reopen' => $classification !== 'retry-current',
            ];
        }

        $staleTokens = array_values(array_map(
            static fn (array $row): string => $row['token'],
            array_filter($tokenRows, static fn (array $row): bool => $row['classification'] !== 'retry-current')
        ));
        $retainedTokens = array_values(array_map(
            static fn (array $row): string => $row['token'],
            array_filter($tokenRows, static fn (array $row): bool => $row['classification'] === 'retry-current')
        ));
        $postApplyTokenRetired = !in_array($postToken, $readerTokens, true);

        $blocked = [];
        if (!$hotJournalObserved) {
            $blocked[] = 'hot_journal_recovery_observation_required';
        }
        if (!$postApplyReady) {
            $blocked[] = 'next183_post_apply_current_source_not_verified';
        }
        if (!$reopenReady) {
            $blocked[] = 'next184_reopened_wal_source_not_publishable';
        }
        if (!$postApplyTokenRetired) {
            $blocked[] = 'post_apply_reader_token_must_be_retired_before_retry_wal_reuse';
        }
        if ($staleTokens !== []) {
            $blocked[] = 'stale_reader_tokens_require_reopen_before_retry_wal_reuse';
        }
        if ($retainedTokens !== [] && !in_array($transitionToken, $retainedTokens, true)) {
            $blocked[] = 'unexpected_retry_reader_token_retained';
        }
        if (count($retainedTokens) > 1) {
            $blocked[] = 'duplicate_retry_reader_tokens';
        }

        $ready = $blocked === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next187'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next187',
            'reason' => $ready
                ? 'hot_journal_post_apply_reader_token_retired_before_retry_wal_checkpoint_source'
                : 'retry_wal_checkpoint_source_waits_for_reader_token_retirement',
            'database_path' => (string) $postApply['database_path'],
            'wal_path' => (string) $postApply['wal_path'],
            'post_apply_reader_token' => $postToken,
            'retry_reader_token' => $transitionToken,
            'reader_tokens' => $readerTokens,
            'token_rows' => $tokenRows,
            'retained_reader_tokens' => $retainedTokens,
            'stale_reader_tokens' => $staleTokens,
            'post_apply_token_retired' => $postApplyTokenRetired,
            'requires_reader_reopen' => $staleTokens !== [],
            'hot_journal_observed' => $hotJournalObserved,
            'post_apply_ready' => $postApplyReady,
            'reopen_ready' => $reopenReady,
            'can_admit_retry_checkpoint_source' => $ready,
            'post_apply_file_digest' => (string) $postApply['file_digest'],
            'retry_transition_digest' => (string) $reopen['source_transition_digest'],
            'next_wal_sha256' => (string) $reopen['next_wal_sha256'],
            'reader_page_numbers' => $reopen['reader_page_numbers'],
            'reader_next_sources' => $reopen['reader_next_sources'],
            'blocked_reasons' => array_values(array_unique($blocked)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($postApply['dependencies'] ?? null) ? $postApply['dependencies'] : [],
                is_array($reopen['dependencies'] ?? null) ? $reopen['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next187',
                    'sqlite-hot-journal-reader-token-retirement-before-wal-retry',
                    'wordpress-wal-import-retry-reader-token-fence',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; composes accepted post-apply current-source and reopened WAL source evidence',
            'non_overlap' => 'next187 fences reader-token handoff between next183 post-apply current source and next184 reopened WAL source; it does not repeat atomic file-map apply, WAL byte truncation, rollback-journal apply, checkpoint transactions, reader-cache verification, or WAL salt/checkpoint parsing',
        ];
    }

    /**
     * @param array<string,mixed> $postApply
     * @param array<string,mixed> $reopen
     * @param list<string> $readerTokens
     */
    private static function assertInput(array $postApply, array $reopen, array $readerTokens): void
    {
        foreach (['database_path', 'wal_path', 'reader_source_token', 'file_digest'] as $key) {
            if (!array_key_exists($key, $postApply)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next187 missing post-apply {$key}");
            }
        }
        foreach (['source_transition_digest', 'next_wal_sha256', 'reader_page_numbers', 'reader_next_sources'] as $key) {
            if (!array_key_exists($key, $reopen)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next187 missing reopen {$key}");
            }
        }
        foreach ($readerTokens as $token) {
            if (!is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next187 reader tokens must be non-empty strings');
            }
        }
        if (!is_array($reopen['reader_page_numbers']) || !is_array($reopen['reader_next_sources'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next187 reopen reader rows must be arrays');
        }
    }
}
