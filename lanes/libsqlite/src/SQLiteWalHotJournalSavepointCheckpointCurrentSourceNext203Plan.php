<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext203Plan
{
    /**
     * @param array<string,mixed> $basePlan
     * @param list<array{name:string,root_pages:list<int>,observed_wal_digest:string,observed_page_digests:array<int,string>,closed?:bool,dirty?:bool,requires_wal_sidecar?:bool}> $leases
     * @return array<string,mixed>
     */
    public static function plan(array $basePlan, string $checkpointedDatabaseBytes, array $leases): array
    {
        if (($basePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next196') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next203 requires an admitted next196 base plan');
        }

        $pageSize = $basePlan['page_size'] ?? null;
        if (!is_int($pageSize) || $pageSize <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next203 requires a positive page size');
        }
        if ($checkpointedDatabaseBytes === '' || strlen($checkpointedDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next203 requires complete checkpointed database pages');
        }
        if ($leases === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next203 requires page-cache lease rows');
        }

        $expectedWalDigest = $basePlan['sidecar']['actual_digest'] ?? $basePlan['persisted_wal_digest'] ?? null;
        if (!is_string($expectedWalDigest) || strlen($expectedWalDigest) !== 64) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next203 requires the published WAL sidecar digest');
        }

        $expectedPageDigests = self::pageDigests($checkpointedDatabaseBytes, $pageSize);
        $leaseRows = [];
        $admitted = [];
        $reopen = [];
        foreach ($leases as $lease) {
            $row = self::leaseDecision($lease, $expectedWalDigest, $expectedPageDigests, $pageSize);
            $leaseRows[] = $row;
            if ($row['admitted']) {
                $admitted[] = $row['name'];
            } else {
                $reopen[] = $row['name'];
            }
        }

        $sidecarMatched = (bool) ($basePlan['sidecar']['matched'] ?? false);
        $guardRows = [
            [
                'name' => 'base_wal_sidecar_publication',
                'matched' => $sidecarMatched,
                'reason' => 'next196 WAL sidecar publication must be durable before page-cache leases are reused',
            ],
            [
                'name' => 'checkpointed_database_image',
                'matched' => $expectedPageDigests !== [],
                'reason' => 'checkpointed database image supplies the canonical page digests for cache reuse',
            ],
            [
                'name' => 'lease_reuse_mix',
                'matched' => $admitted !== [] && $reopen !== [],
                'reason' => 'current leases are retained while stale WAL/page-cache leases are reopened',
            ],
        ];
        $staleGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $status = $staleGuards === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next203'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next203';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next203'
                ? 'wal_sidecar_and_checkpoint_page_cache_leases_match_current_source'
                : 'wal_sidecar_or_checkpoint_page_cache_leases_block_current_source_reuse',
            'base_status' => $basePlan['status'],
            'database_path' => $basePlan['database_path'] ?? null,
            'journal_path' => $basePlan['journal_path'] ?? null,
            'wal_path' => $basePlan['wal_path'] ?? null,
            'page_size' => $pageSize,
            'mode' => $basePlan['mode'] ?? null,
            'checkpointed_database_digest' => hash('sha256', $checkpointedDatabaseBytes),
            'checkpointed_page_count' => count($expectedPageDigests),
            'expected_wal_digest' => $expectedWalDigest,
            'expected_page_digests' => $expectedPageDigests,
            'lease_rows' => $leaseRows,
            'admitted_lease_names' => $admitted,
            'reopen_lease_names' => $reopen,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'stale_guard_names' => $staleGuards,
            'operation_names' => array_values(array_merge(
                $basePlan['operation_names'] ?? [],
                ['verify_checkpoint_page_cache_leases_current_source_next203'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'retain_checkpoint_page_cache_lease_next203'
                        : 'reopen_checkpoint_page_cache_lease_next203',
                    $leaseRows
                )
            )),
            'lease_digest' => hash('sha256', implode('|', array_merge(
                [$expectedWalDigest, hash('sha256', $checkpointedDatabaseBytes)],
                array_column($leaseRows, 'lease_transition')
            ))),
            'dependencies' => array_values(array_unique(array_merge($basePlan['dependencies'] ?? [], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next203',
                'sqlite-checkpoint-page-cache-lease-fence',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses the accepted WAL sidecar publication and checkpointed database page digests to fence page-cache lease reuse',
            'non_overlap' => 'next203 validates page-cache lease reuse after next196 WAL sidecar publication; it does not repeat next196 sidecar restart/truncate/preserve decisions, next192 checkpoint page-image publication, reader retry admission, VFS savepoint rollback, rollback-journal apply, or WAL byte truncation planning',
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function pageDigests(string $databaseBytes, int $pageSize): array
    {
        $digests = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $offset = ($pageNumber - 1) * $pageSize;
            $digests[$pageNumber] = hash('sha256', substr($databaseBytes, $offset, $pageSize));
        }

        return $digests;
    }

    /**
     * @param array<string,mixed> $lease
     * @param array<int,string> $expectedPageDigests
     * @return array<string,mixed>
     */
    private static function leaseDecision(array $lease, string $expectedWalDigest, array $expectedPageDigests, int $pageSize): array
    {
        $name = $lease['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next203 lease name is required');
        }
        $observedWalDigest = $lease['observed_wal_digest'] ?? null;
        if (!is_string($observedWalDigest) || strlen($observedWalDigest) !== 64) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next203 {$name} observed WAL digest is required");
        }
        $rootPages = $lease['root_pages'] ?? null;
        if (!is_array($rootPages) || $rootPages === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next203 {$name} root pages are required");
        }
        $observedPageDigests = $lease['observed_page_digests'] ?? null;
        if (!is_array($observedPageDigests) || $observedPageDigests === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next203 {$name} observed page digests are required");
        }

        $pageRows = [];
        $missingPages = [];
        $stalePages = [];
        foreach ($rootPages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next203 {$name} root page must be positive");
            }
            $expectedDigest = $expectedPageDigests[$pageNumber] ?? null;
            if ($expectedDigest === null) {
                $missingPages[] = $pageNumber;
                $pageRows[] = [
                    'page' => $pageNumber,
                    'matched' => false,
                    'reason' => 'page_outside_checkpointed_database_image',
                    'expected_digest' => null,
                    'observed_digest' => $observedPageDigests[$pageNumber] ?? null,
                ];
                continue;
            }
            $observedDigest = $observedPageDigests[$pageNumber] ?? null;
            if (!is_string($observedDigest) || strlen($observedDigest) !== 64) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next203 {$name} page digest is required");
            }
            $matched = hash_equals($expectedDigest, $observedDigest);
            if (!$matched) {
                $stalePages[] = $pageNumber;
            }
            $pageRows[] = [
                'page' => $pageNumber,
                'matched' => $matched,
                'reason' => $matched ? 'checkpoint_page_digest_matches' : 'checkpoint_page_digest_stale',
                'expected_digest' => $expectedDigest,
                'observed_digest' => $observedDigest,
            ];
        }

        $admitted = empty($lease['closed']) && empty($lease['dirty']);
        $reason = $admitted ? 'lease_matches_wal_sidecar_and_checkpoint_pages' : 'lease_closed_or_dirty_before_checkpoint_page_publication';
        if ($admitted && $observedWalDigest !== $expectedWalDigest) {
            $admitted = false;
            $reason = 'lease_observed_wal_sidecar_predates_checkpoint_publication';
        } elseif ($admitted && ($missingPages !== [] || $stalePages !== [])) {
            $admitted = false;
            $reason = 'lease_observed_checkpoint_page_digest_is_stale';
        } elseif ($admitted && (bool) ($lease['requires_wal_sidecar'] ?? false) && ($observedWalDigest === hash('sha256', ''))) {
            $admitted = false;
            $reason = 'lease_requires_wal_sidecar_after_truncate_checkpoint';
        }

        return array_merge($lease, [
            'admitted' => $admitted,
            'lease_reason' => $reason,
            'expected_wal_digest' => $expectedWalDigest,
            'observed_wal_digest' => $observedWalDigest,
            'root_pages' => array_values($rootPages),
            'page_size' => $pageSize,
            'page_rows' => $pageRows,
            'stale_pages' => $stalePages,
            'missing_pages' => $missingPages,
            'lease_transition' => $name . '>' . ($admitted ? 'retain-checkpoint-page-cache' : 'reopen-checkpoint-page-cache'),
        ]);
    }
}
