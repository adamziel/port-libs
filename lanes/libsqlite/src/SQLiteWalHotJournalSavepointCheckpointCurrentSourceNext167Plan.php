<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext167Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string}> $readerCachePages
     * @param list<int> $checkpointPages
     * @param list<array{name:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,closed?:bool}> $readers
     * @param array{id:string,epoch:int}|null $expectedCurrentToken
     * @param array{id:string,epoch:int}|null $expectedNextToken
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
        ?array $expectedCurrentToken = null,
        ?array $expectedNextToken = null,
        ?string $expectedPublicationFingerprint = null,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
    ): array {
        if ($currentWalBytes === '' || $nextWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next167 requires current and next WAL bytes');
        }
        if ($currentWal->toBytes() !== $currentWalBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next167 current WAL bytes do not match parsed WAL');
        }
        if ($nextWal->toBytes() !== $nextWalBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next167 next WAL bytes do not match parsed WAL');
        }

        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext164Plan::plan(
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
            $readers,
            $mode,
            $readerEndFrame,
            $currentSourceEpoch
        );

        $currentToken = $base['current_source_token'];
        $nextToken = $base['next_source_token'];
        self::assertToken($currentToken, 'current');
        self::assertToken($nextToken, 'next');
        if ($expectedCurrentToken !== null) {
            self::assertToken($expectedCurrentToken, 'expected current');
        }
        if ($expectedNextToken !== null) {
            self::assertToken($expectedNextToken, 'expected next');
        }

        $publicationFingerprint = self::fingerprint(
            $databasePath,
            $savepoint,
            $currentWalBytes,
            $nextWalBytes,
            $hotJournalPages,
            $savepointBeforePages,
            $base
        );
        $expectedPublicationFingerprint ??= $publicationFingerprint;

        $guardRows = [
            [
                'name' => 'current_token',
                'expected' => $expectedCurrentToken['id'] ?? $currentToken['id'],
                'actual' => $currentToken['id'],
                'matched' => ($expectedCurrentToken['id'] ?? $currentToken['id']) === $currentToken['id']
                    && (int) ($expectedCurrentToken['epoch'] ?? $currentToken['epoch']) === (int) $currentToken['epoch'],
                'reason' => 'checkpoint_current_source_token_matches_prepared_statement',
            ],
            [
                'name' => 'next_token',
                'expected' => $expectedNextToken['id'] ?? $nextToken['id'],
                'actual' => $nextToken['id'],
                'matched' => ($expectedNextToken['id'] ?? $nextToken['id']) === $nextToken['id']
                    && (int) ($expectedNextToken['epoch'] ?? $nextToken['epoch']) === (int) $nextToken['epoch'],
                'reason' => 'next_wal_source_token_matches_retry_generation',
            ],
            [
                'name' => 'publication_fingerprint',
                'expected' => $expectedPublicationFingerprint,
                'actual' => $publicationFingerprint,
                'matched' => $expectedPublicationFingerprint === $publicationFingerprint,
                'reason' => 'hot_journal_savepoint_checkpoint_inputs_match_current_source',
            ],
            [
                'name' => 'reader_admission',
                'expected' => 'mixed-admit-reopen',
                'actual' => $base['admitted_reader_names'] !== [] && $base['reopen_reader_names'] !== [] ? 'mixed-admit-reopen' : 'incomplete',
                'matched' => $base['admitted_reader_names'] !== [] && $base['reopen_reader_names'] !== [],
                'reason' => 'checkpoint_publication_keeps_only_current_source_readers',
            ],
        ];

        $mismatches = array_values(array_filter($guardRows, static fn (array $row): bool => !$row['matched']));
        $status = $mismatches === [] && $base['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next164'
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next167'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next167';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next167'
                ? 'current_source_publication_guard_admits_checkpoint_after_hot_journal_savepoint'
                : 'current_source_publication_guard_detected_stale_checkpoint_inputs',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'mode' => $base['mode'],
            'reader_end_frame' => $base['reader_end_frame'],
            'base_status' => $base['status'],
            'current_source_token' => $currentToken,
            'next_source_token' => $nextToken,
            'publication_fingerprint' => $publicationFingerprint,
            'expected_publication_fingerprint' => $expectedPublicationFingerprint,
            'publication_guard_rows' => $guardRows,
            'publication_guard_names' => array_column($guardRows, 'name'),
            'publication_guard_reasons' => array_column($guardRows, 'reason'),
            'publication_guard_matches' => array_column($guardRows, 'matched'),
            'stale_guard_names' => array_column($mismatches, 'name'),
            'stale_guard_count' => count($mismatches),
            'admitted_reader_names' => $base['admitted_reader_names'],
            'reopen_reader_names' => $base['reopen_reader_names'],
            'reader_reopen_count' => $base['reader_reopen_count'],
            'reader_admission_reasons' => $base['reader_admission_reasons'],
            'retained_cache_page_numbers' => $base['retained_cache_page_numbers'],
            'invalidated_cache_page_numbers' => $base['invalidated_cache_page_numbers'],
            'operation_names' => array_values(array_merge($base['operation_names'], ['publish_guarded_current_source_next167'])),
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next167',
                'sqlite-wal-current-source-publication-fingerprint',
            ]))),
            'dependency_closure' => 'no new support component needed; composes existing native WAL parsing, hot-journal recovery, savepoint rollback, checkpoint current-source tokens, and reader admission fences',
            'non_overlap' => 'does not repeat next164 reader admission or VFS byte application; this slice guards publication against stale WAL/hot-journal/savepoint current-source inputs before readers are admitted',
        ];
    }

    /**
     * @param array<string,mixed> $token
     */
    private static function assertToken(array $token, string $label): void
    {
        if (($token['id'] ?? '') === '' || !isset($token['epoch']) || !is_int($token['epoch']) || $token['epoch'] < 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next167 {$label} token is invalid");
        }
    }

    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<string,mixed> $base
     */
    private static function fingerprint(
        string $databasePath,
        string $savepoint,
        string $currentWalBytes,
        string $nextWalBytes,
        array $hotJournalPages,
        array $savepointBeforePages,
        array $base
    ): string {
        return hash('sha256', json_encode([
            'databasePath' => $databasePath,
            'savepoint' => $savepoint,
            'currentWal' => hash('sha256', $currentWalBytes),
            'nextWal' => hash('sha256', $nextWalBytes),
            'hot' => self::pageHashes($hotJournalPages),
            'savepointBefore' => self::pageHashes($savepointBeforePages),
            'currentToken' => $base['current_source_token'],
            'nextToken' => $base['next_source_token'],
            'sourceDigest' => $base['source_digest'],
            'admitted' => $base['admitted_reader_names'],
            'reopen' => $base['reopen_reader_names'],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function pageHashes(array $pages): array
    {
        $hashes = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || !is_string($image)) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next167 page fingerprints require integer page numbers and byte strings');
            }
            $hashes[$pageNumber] = hash('sha256', $image);
        }
        ksort($hashes, SORT_NUMERIC);

        return $hashes;
    }
}
