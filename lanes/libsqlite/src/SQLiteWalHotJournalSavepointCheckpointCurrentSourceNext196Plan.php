<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext196Plan
{
    /**
     * @param array<string,mixed> $basePlan
     * @param list<array{name:string,observed_wal_digest?:string,requires_wal_sidecar?:bool,closed?:bool,dirty?:bool}> $statements
     * @param list<array{name:string,observed_wal_digest?:string,pinned?:bool,closed?:bool,dirty?:bool}> $readers
     * @return array<string,mixed>
     */
    public static function plan(
        array $basePlan,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $persistedWalBytes,
        string $mode,
        array $statements,
        array $readers,
        ?int $readerEndFrame = null
    ): array {
        if (($basePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next192') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next196 requires an admitted next192 base plan');
        }
        if (!in_array($mode, ['truncate', 'restart', 'preserve_busy'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next196 requires truncate, restart, or preserve_busy mode');
        }
        if ($statements === [] || $readers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next196 requires statement and reader rows');
        }
        if ($currentWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next196 requires current WAL bytes');
        }
        if (hash('sha256', $currentWal->toBytes()) !== hash('sha256', $currentWalBytes)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next196 current WAL bytes do not match parsed WAL');
        }
        if ($readerEndFrame !== null && ($readerEndFrame < 0 || $readerEndFrame > $currentWal->frameCount())) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next196 reader frame is outside the current WAL');
        }

        $sidecar = self::sidecarDecision($currentWal, $currentWalBytes, $persistedWalBytes, $mode, $readerEndFrame);
        $statementRows = [];
        $admittedStatements = [];
        $reprepareStatements = [];
        foreach ($statements as $statement) {
            $row = self::cacheDecision('statement', $statement, $sidecar);
            $statementRows[] = $row;
            if ($row['admitted']) {
                $admittedStatements[] = $row['name'];
            } else {
                $reprepareStatements[] = $row['name'];
            }
        }

        $readerRows = [];
        $admittedReaders = [];
        $reopenReaders = [];
        foreach ($readers as $reader) {
            $row = self::cacheDecision('reader', $reader, $sidecar);
            $readerRows[] = $row;
            if ($row['admitted']) {
                $admittedReaders[] = $row['name'];
            } else {
                $reopenReaders[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'base_checkpoint_page_images',
                'matched' => ($basePlan['status'] ?? null) === 'wal-hot-journal-savepoint-checkpoint-current-source-next192',
                'reason' => 'next192 checkpoint page-image publication must pass before WAL sidecar publication',
            ],
            [
                'name' => 'wal_sidecar_publication',
                'matched' => $sidecar['matched'],
                'reason' => 'persisted WAL sidecar must match the checkpoint mode after hot-journal savepoint recovery',
            ],
            [
                'name' => 'statement_sidecar_mix',
                'matched' => $admittedStatements !== [] && $reprepareStatements !== [],
                'reason' => 'statement cache retains rows that observed the published sidecar and reprepares stale rows',
            ],
            [
                'name' => 'reader_sidecar_mix',
                'matched' => $admittedReaders !== [] && $reopenReaders !== [],
                'reason' => 'reader cache retains rows that observed the published sidecar and reopens stale rows',
            ],
        ];
        $staleGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $status = $staleGuards === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next196'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next196';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next196'
                ? 'wal_sidecar_publication_matches_checkpoint_mode_after_hot_journal_savepoint'
                : 'wal_sidecar_publication_blocks_current_source_reuse_after_hot_journal_savepoint',
            'database_path' => $basePlan['database_path'] ?? null,
            'journal_path' => $basePlan['journal_path'] ?? null,
            'wal_path' => $basePlan['wal_path'] ?? null,
            'page_size' => $basePlan['page_size'] ?? $currentWal->header->pageSize,
            'base_status' => $basePlan['status'],
            'base_page_image_digest' => $basePlan['page_image_digest'] ?? null,
            'mode' => $mode,
            'reader_end_frame' => $readerEndFrame,
            'sidecar' => $sidecar,
            'current_wal_digest' => hash('sha256', $currentWalBytes),
            'persisted_wal_digest' => hash('sha256', $persistedWalBytes),
            'persisted_wal_bytes_length' => strlen($persistedWalBytes),
            'statement_rows' => $statementRows,
            'reader_rows' => $readerRows,
            'admitted_statement_names' => $admittedStatements,
            'reprepare_statement_names' => $reprepareStatements,
            'admitted_reader_names' => $admittedReaders,
            'reopen_reader_names' => $reopenReaders,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'stale_guard_names' => $staleGuards,
            'operation_names' => array_values(array_merge(
                $basePlan['operation_names'] ?? [],
                ['verify_wal_sidecar_publication_current_source_next196'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'admit_wal_sidecar_current_source_next196'
                        : 'reprepare_wal_sidecar_current_source_next196',
                    $statementRows
                ),
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'retain_reader_wal_sidecar_next196'
                        : 'reopen_reader_wal_sidecar_next196',
                    $readerRows
                ),
                ['publish_wal_sidecar_current_source_next196']
            )),
            'sidecar_digest' => hash('sha256', implode('|', array_merge(
                [(string) ($basePlan['page_image_digest'] ?? ''), $mode, $sidecar['expected_digest'], $sidecar['actual_digest']],
                array_column($statementRows, 'sidecar_transition'),
                array_column($readerRows, 'sidecar_transition')
            ))),
            'dependencies' => array_values(array_unique(array_merge($basePlan['dependencies'] ?? [], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next196',
                'sqlite-wal-sidecar-publication-after-checkpoint',
            ]))),
            'dependency_closure' => 'no new support component needed; composes the accepted WAL parser/checkpoint page-image guard with bounded WAL sidecar reset, truncate, and reader-pinned preserve admission',
            'non_overlap' => 'next196 verifies the persisted WAL sidecar after next192 page-image publication; it does not repeat next192 page digest checks, next188 hook checks, next185 generation checks, VFS savepoint rollback, rollback-journal apply, VFS sync/write wrappers, or WAL byte truncation planning',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function sidecarDecision(SQLiteWal $currentWal, string $currentWalBytes, string $persistedWalBytes, string $mode, ?int $readerEndFrame): array
    {
        $actualDigest = hash('sha256', $persistedWalBytes);
        $currentDigest = hash('sha256', $currentWalBytes);
        if ($mode === 'truncate') {
            return [
                'mode' => $mode,
                'matched' => $persistedWalBytes === '',
                'reason' => $persistedWalBytes === '' ? 'wal_sidecar_truncated_after_checkpoint' : 'wal_sidecar_not_truncated_after_checkpoint',
                'expected_action' => 'truncate_wal',
                'expected_digest' => hash('sha256', ''),
                'actual_digest' => $actualDigest,
                'actual_frame_count' => 0,
                'actual_checkpoint_sequence' => null,
                'next_checkpoint_sequence' => null,
                'reader_preserved_current_wal' => false,
            ];
        }

        if ($mode === 'preserve_busy') {
            $matched = $readerEndFrame !== null && $readerEndFrame > 0 && hash_equals($currentDigest, $actualDigest);

            return [
                'mode' => $mode,
                'matched' => $matched,
                'reason' => $matched ? 'reader_pin_preserves_current_wal_sidecar' : 'reader_pin_preserve_wal_sidecar_mismatch',
                'expected_action' => 'preserve_wal',
                'expected_digest' => $currentDigest,
                'actual_digest' => $actualDigest,
                'actual_frame_count' => $matched ? $currentWal->frameCount() : null,
                'actual_checkpoint_sequence' => $matched ? $currentWal->header->checkpointSequence : null,
                'next_checkpoint_sequence' => null,
                'reader_preserved_current_wal' => $matched,
            ];
        }

        if ($persistedWalBytes === '') {
            return [
                'mode' => $mode,
                'matched' => false,
                'reason' => 'restart_checkpoint_missing_restarted_wal_header',
                'expected_action' => 'restart_wal',
                'expected_digest' => 'restart-generation',
                'actual_digest' => $actualDigest,
                'actual_frame_count' => 0,
                'actual_checkpoint_sequence' => null,
                'next_checkpoint_sequence' => (($currentWal->header->checkpointSequence + 1) & 0xffffffff),
                'reader_preserved_current_wal' => false,
            ];
        }

        $restartWal = SQLiteWal::parse($persistedWalBytes, $currentWal->header->pageSize, $currentWal->checksumsValidated);
        $expectedSequence = (($currentWal->header->checkpointSequence + 1) & 0xffffffff);
        $matched = $restartWal->frameCount() === 0
            && $restartWal->header->checkpointSequence === $expectedSequence
            && ($restartWal->header->salt1 !== $currentWal->header->salt1 || $restartWal->header->salt2 !== $currentWal->header->salt2);

        return [
            'mode' => $mode,
            'matched' => $matched,
            'reason' => $matched ? 'wal_sidecar_restarted_after_checkpoint' : 'wal_sidecar_restart_generation_mismatch',
            'expected_action' => 'restart_wal',
            'expected_digest' => hash('sha256', $persistedWalBytes),
            'actual_digest' => $actualDigest,
            'actual_frame_count' => $restartWal->frameCount(),
            'actual_checkpoint_sequence' => $restartWal->header->checkpointSequence,
            'next_checkpoint_sequence' => $expectedSequence,
            'reader_preserved_current_wal' => false,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $sidecar
     * @return array<string,mixed>
     */
    private static function cacheDecision(string $kind, array $row, array $sidecar): array
    {
        $name = $row['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next196 {$kind} name is required");
        }
        $observedDigest = $row['observed_wal_digest'] ?? null;
        if (!is_string($observedDigest) || strlen($observedDigest) !== 64) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next196 {$kind} observed WAL digest is required");
        }

        $requiresWal = (bool) ($row['requires_wal_sidecar'] ?? $row['pinned'] ?? false);
        $admitted = (bool) $sidecar['matched'] && empty($row['closed']) && empty($row['dirty']);
        $reason = $admitted ? "{$kind}_wal_sidecar_matches_checkpoint_publication" : "{$kind}_closed_or_dirty_before_wal_sidecar_publication";
        if ($admitted && $observedDigest !== $sidecar['actual_digest']) {
            $admitted = false;
            $reason = "{$kind}_observed_wal_sidecar_predates_checkpoint_publication";
        } elseif ($admitted && $requiresWal && $sidecar['expected_action'] === 'truncate_wal') {
            $admitted = false;
            $reason = "{$kind}_requires_wal_sidecar_after_truncate_checkpoint";
        } elseif (!$sidecar['matched']) {
            $admitted = false;
            $reason = "{$kind}_wal_sidecar_publication_not_durable";
        }

        return array_merge($row, [
            'admitted' => $admitted,
            'sidecar_reason' => $reason,
            'expected_wal_digest' => $sidecar['actual_digest'],
            'observed_wal_digest' => $observedDigest,
            'requires_wal_sidecar' => $requiresWal,
            'sidecar_transition' => $name . '>' . ($admitted ? 'retain-wal-sidecar' : 'reprepare-wal-sidecar'),
        ]);
    }
}
