<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan
{
    /**
     * @param array<string,mixed> $basePlan
     * @param list<array{name:string,observed_checkpoint_generation:int,observed_schema_cookie:int,observed_page_count:int,observed_database_digest:string,reader_epoch?:int,closed?:bool,dirty?:bool}> $leases
     * @return array<string,mixed>
     */
    public static function plan(array $basePlan, array $leases): array
    {
        if (($basePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next203') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next204 requires an admitted next203 lease plan');
        }
        if ($leases === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next204 requires generation lease rows');
        }

        $checkpointGeneration = self::positiveInt($basePlan, 'checkpoint_generation');
        $schemaCookie = self::positiveInt($basePlan, 'schema_cookie');
        $pageCount = self::positiveInt($basePlan, 'checkpointed_page_count');
        $databaseDigest = $basePlan['checkpointed_database_digest'] ?? null;
        if (!is_string($databaseDigest) || strlen($databaseDigest) !== 64) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next204 requires checkpointed database digest');
        }

        $rows = [];
        $admitted = [];
        $reopen = [];
        foreach ($leases as $lease) {
            $row = self::leaseRow($lease, $checkpointGeneration, $schemaCookie, $pageCount, $databaseDigest);
            $rows[] = $row;
            if ($row['admitted']) {
                $admitted[] = $row['name'];
            } else {
                $reopen[] = $row['name'];
            }
        }

        $guards = [
            [
                'name' => 'next203_page_digest_leases',
                'matched' => in_array('sqlite-checkpoint-page-cache-lease-fence', $basePlan['dependencies'] ?? [], true),
                'reason' => 'page-cache leases must already be digest-fenced by next203 before generation tickets are reused',
            ],
            [
                'name' => 'checkpoint_generation_ticket',
                'matched' => $checkpointGeneration > 0,
                'reason' => 'checkpoint publication generation must be positive',
            ],
            [
                'name' => 'generation_reuse_mix',
                'matched' => $admitted !== [] && $reopen !== [],
                'reason' => 'current generation tickets are retained while stale tickets are reopened',
            ],
        ];
        $blocked = array_values(array_column(
            array_filter($guards, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $status = $blocked === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next204'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next204';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next204'
                ? 'checkpoint_generation_tickets_admit_current_source_page_cache'
                : 'checkpoint_generation_tickets_block_current_source_page_cache',
            'base_status' => $basePlan['status'],
            'database_path' => $basePlan['database_path'] ?? null,
            'journal_path' => $basePlan['journal_path'] ?? null,
            'wal_path' => $basePlan['wal_path'] ?? null,
            'checkpoint_generation' => $checkpointGeneration,
            'schema_cookie' => $schemaCookie,
            'checkpointed_page_count' => $pageCount,
            'checkpointed_database_digest' => $databaseDigest,
            'lease_rows' => $rows,
            'admitted_lease_names' => $admitted,
            'reopen_lease_names' => $reopen,
            'guard_rows' => $guards,
            'guard_names' => array_column($guards, 'name'),
            'guard_matches' => array_column($guards, 'matched'),
            'blocked_guard_names' => $blocked,
            'operation_names' => array_values(array_merge(
                is_array($basePlan['operation_names'] ?? null) ? $basePlan['operation_names'] : [],
                ['verify_checkpoint_generation_tickets_current_source_next204'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'retain_checkpoint_generation_lease_next204'
                        : 'reopen_checkpoint_generation_lease_next204',
                    $rows
                )
            )),
            'generation_ticket_digest' => hash('sha256', json_encode([
                'generation' => $checkpointGeneration,
                'schema_cookie' => $schemaCookie,
                'page_count' => $pageCount,
                'database_digest' => $databaseDigest,
                'rows' => array_column($rows, 'transition'),
            ], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($basePlan['dependencies'] ?? null) ? $basePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next204',
                    'sqlite-checkpoint-generation-ticket-fence',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next203 checkpoint page-cache lease metadata plus lane-local checkpoint generation, schema-cookie, page-count, and database-digest receipts',
            'non_overlap' => 'next204 fences current-source page-cache reuse with checkpoint generation tickets after next203 page-digest leases; it does not repeat next203 WAL/page digest lease checks, next202 file-handle receipts, next196 sidecar publication, VFS savepoint rollback, rollback-journal apply, or WAL byte truncation planning',
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function positiveInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next204 {$key} must be a positive integer");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $lease
     * @return array<string,mixed>
     */
    private static function leaseRow(array $lease, int $checkpointGeneration, int $schemaCookie, int $pageCount, string $databaseDigest): array
    {
        $name = $lease['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next204 lease name is required');
        }

        $observedDatabaseDigest = $lease['observed_database_digest'] ?? null;
        if (!is_string($observedDatabaseDigest) || strlen($observedDatabaseDigest) !== 64) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next204 {$name} observed database digest is required");
        }

        $checks = [
            'checkpoint_generation' => self::positiveInt($lease, 'observed_checkpoint_generation') === $checkpointGeneration,
            'schema_cookie' => self::positiveInt($lease, 'observed_schema_cookie') === $schemaCookie,
            'page_count' => self::positiveInt($lease, 'observed_page_count') === $pageCount,
            'database_digest' => hash_equals($databaseDigest, $observedDatabaseDigest),
            'not_dirty' => ($lease['dirty'] ?? false) !== true,
            'not_closed' => ($lease['closed'] ?? false) !== true,
        ];

        if (array_key_exists('reader_epoch', $lease)) {
            $checks['reader_epoch_current'] = self::positiveInt($lease, 'reader_epoch') >= $checkpointGeneration;
        }

        $failed = array_keys(array_filter($checks, static fn (bool $matched): bool => !$matched));
        $admitted = $failed === [];

        return array_merge($lease, [
            'name' => $name,
            'admitted' => $admitted,
            'requires_reopen' => !$admitted,
            'failed_checks' => $failed,
            'lease_reason' => $admitted
                ? 'lease_generation_ticket_matches_checkpoint_current_source'
                : 'lease_generation_ticket_requires_reopen',
            'expected_checkpoint_generation' => $checkpointGeneration,
            'expected_schema_cookie' => $schemaCookie,
            'expected_page_count' => $pageCount,
            'expected_database_digest' => $databaseDigest,
            'transition' => $name . '>' . ($admitted ? 'retain-checkpoint-generation-ticket' : 'reopen-checkpoint-generation-ticket') . ':next204',
        ]);
    }
}
