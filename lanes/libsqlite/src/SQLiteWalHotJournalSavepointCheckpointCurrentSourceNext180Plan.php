<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext180Plan
{
    /**
     * @param array<string,mixed> $applyPlan
     * @param array<string,string|null> $files
     * @param array<string,string> $payloadBytes
     * @return array<string,mixed>
     */
    public static function apply(array $applyPlan, array $files, array $payloadBytes, ?int $failAfterOperation = null): array
    {
        self::assertApplyPlan($applyPlan);

        if (($applyPlan['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next177') {
            return self::blocked($applyPlan, $files, ['next177_apply_plan_required']);
        }
        if (!(bool) ($applyPlan['can_apply'] ?? false)) {
            return self::blocked($applyPlan, $files, ['next177_apply_plan_not_admitted']);
        }

        $operations = $applyPlan['operations'];
        if ($failAfterOperation !== null && ($failAfterOperation < 0 || $failAfterOperation > count($operations))) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next180 failure index is outside the operation range');
        }

        $working = self::normalizeFiles($files);
        $originalDigest = self::fileDigest($working);
        $staged = [];
        $durable = [];
        $applied = 0;
        $writtenBytes = 0;
        $truncatedBytes = 0;
        $deletedPaths = [];
        $syncedPaths = [];

        foreach ($operations as $index => $operation) {
            if ($failAfterOperation !== null && $applied >= $failAfterOperation) {
                return [
                    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-failed-next180',
                    'reason' => 'atomic_apply_aborted_before_publication',
                    'applied_operation_count' => $applied,
                    'failed_before_operation' => $index,
                    'files' => $files,
                    'file_digest_before' => $originalDigest,
                    'file_digest_after' => self::fileDigest(self::normalizeFiles($files)),
                    'rolled_back' => true,
                    'published' => false,
                    'blocked_reasons' => ['simulated_failure_before_directory_sync'],
                    'dependencies' => self::dependencies($applyPlan),
                    'dependency_closure' => 'no new support component needed; simulated failure leaves the caller-visible file map unchanged',
                    'non_overlap' => 'next180 applies next177 operation metadata atomically without repeating next177 planning or hot-journal checkpoint admission',
                ];
            }

            $path = (string) $operation['path'];
            $op = (string) $operation['op'];
            if ($op === 'write') {
                $bytes = self::payloadFor($applyPlan, $payloadBytes, $path);
                $working[$path] = $bytes;
                $staged[] = $path;
                $writtenBytes += strlen($bytes);
            } elseif ($op === 'truncate') {
                $length = (int) $operation['bytes'];
                if ($length < 0) {
                    throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next180 truncate length must be non-negative');
                }
                $working[$path] = substr((string) ($working[$path] ?? ''), 0, $length);
                if (strlen((string) $working[$path]) < $length) {
                    $working[$path] = str_pad((string) $working[$path], $length, "\0");
                }
                $truncatedBytes += $length;
            } elseif ($op === 'delete') {
                $working[$path] = null;
                $deletedPaths[] = $path;
            } elseif ($op === 'sync') {
                $syncedPaths[] = $path;
                $durable[] = $path;
            } elseif ($op === 'sync_directory') {
                $syncedPaths[] = $path;
                $durable[] = $path;
            } else {
                throw new \InvalidArgumentException("Unsupported SQLite WAL hot-journal savepoint checkpoint next180 operation: {$op}");
            }

            $applied++;
        }

        $published = self::normalizeFiles($working);
        $verification = self::verifyRows($applyPlan, $published);

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next180',
            'reason' => $operations === [] ? 'no_vfs_changes_needed_after_next177_verification' : 'atomic_vfs_apply_published_for_hot_journal_savepoint_checkpoint',
            'database_path' => $applyPlan['database_path'],
            'journal_path' => $applyPlan['journal_path'],
            'wal_path' => $applyPlan['wal_path'],
            'files' => $published,
            'operation_names' => array_column($operations, 'op'),
            'applied_operation_count' => $applied,
            'staged_payload_paths' => array_values(array_unique($staged)),
            'deleted_paths' => array_values(array_unique($deletedPaths)),
            'synced_paths' => array_values(array_unique($syncedPaths)),
            'durable_paths' => array_values(array_unique($durable)),
            'written_bytes' => $writtenBytes,
            'truncated_bytes' => $truncatedBytes,
            'file_digest_before' => $originalDigest,
            'file_digest_after' => self::fileDigest($published),
            'published' => true,
            'rolled_back' => false,
            'verified_rows' => $verification['rows'],
            'verified_roles' => array_column($verification['rows'], 'role'),
            'verified_all_match' => $verification['all_match'],
            'hot_journal_deleted' => !array_key_exists((string) $applyPlan['journal_path'], $published) || $published[(string) $applyPlan['journal_path']] === null,
            'blocked_reasons' => [],
            'dependencies' => self::dependencies($applyPlan),
            'dependency_closure' => 'no new support component needed; reuses next177 VFS operation metadata and lane-local byte payloads for atomic publication',
            'non_overlap' => 'next180 materializes next177 ordered resume operations into a caller-visible file map and idempotence verifier; it does not repeat next174 file-state admission, next177 operation planning, VFS writer/sync implementation, rollback-journal apply/commit, WAL byte truncation, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $applyPlan
     * @param array<string,string|null> $files
     * @return array<string,mixed>
     */
    private static function blocked(array $applyPlan, array $files, array $reasons): array
    {
        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next180',
            'reason' => 'atomic_vfs_apply_not_admitted',
            'files' => $files,
            'file_digest_before' => self::fileDigest(self::normalizeFiles($files)),
            'file_digest_after' => self::fileDigest(self::normalizeFiles($files)),
            'published' => false,
            'rolled_back' => false,
            'blocked_reasons' => $reasons,
            'dependencies' => self::dependencies($applyPlan),
            'dependency_closure' => 'no new support component needed; blocked before publishing file changes',
            'non_overlap' => 'next180 refuses non-next177 input without repeating older WAL checkpoint planning surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $applyPlan
     * @return list<string>
     */
    private static function dependencies(array $applyPlan): array
    {
        return array_values(array_unique(array_merge($applyPlan['dependencies'] ?? [], [
            'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next180',
            'sqlite-vfs-atomic-file-map-publication',
        ])));
    }

    /**
     * @param array<string,mixed> $applyPlan
     * @param array<string,string> $payloadBytes
     */
    private static function payloadFor(array $applyPlan, array $payloadBytes, string $path): string
    {
        if (!array_key_exists($path, $payloadBytes)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next180 missing payload bytes for {$path}");
        }
        $payload = $payloadBytes[$path];
        if (!is_string($payload)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next180 payload bytes must be strings');
        }
        $metadata = $applyPlan['payloads'][$path] ?? null;
        if (!is_array($metadata)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next180 missing payload metadata for {$path}");
        }
        if ((int) ($metadata['bytes'] ?? -1) !== strlen($payload)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next180 payload length mismatch for {$path}");
        }
        if (!hash_equals((string) ($metadata['sha256'] ?? ''), hash('sha256', $payload))) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next180 payload digest mismatch for {$path}");
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $applyPlan
     * @param array<string,string|null> $files
     * @return array{rows:list<array<string,mixed>>,all_match:bool}
     */
    private static function verifyRows(array $applyPlan, array $files): array
    {
        $rows = [];
        foreach ($applyPlan['payloads'] as $path => $metadata) {
            $bytes = $files[$path] ?? null;
            $rows[] = [
                'path' => $path,
                'role' => self::roleForPath($applyPlan, (string) $path),
                'present' => $bytes !== null,
                'actual_sha256' => $bytes === null ? null : hash('sha256', $bytes),
                'expected_sha256' => $metadata['sha256'] ?? null,
                'actual_length' => $bytes === null ? null : strlen($bytes),
                'expected_length' => $metadata['bytes'] ?? null,
                'matches' => $bytes !== null
                    && strlen($bytes) === (int) ($metadata['bytes'] ?? -1)
                    && hash_equals((string) ($metadata['sha256'] ?? ''), hash('sha256', $bytes)),
            ];
        }

        $journalPath = (string) ($applyPlan['journal_path'] ?? '');
        if ($journalPath !== '' && in_array('delete', array_column($applyPlan['operations'], 'op'), true)) {
            $rows[] = [
                'path' => $journalPath,
                'role' => 'hot-journal',
                'present' => array_key_exists($journalPath, $files) && $files[$journalPath] !== null,
                'actual_sha256' => isset($files[$journalPath]) ? hash('sha256', (string) $files[$journalPath]) : null,
                'expected_sha256' => null,
                'actual_length' => isset($files[$journalPath]) ? strlen((string) $files[$journalPath]) : null,
                'expected_length' => null,
                'matches' => !array_key_exists($journalPath, $files) || $files[$journalPath] === null,
            ];
        }

        return [
            'rows' => $rows,
            'all_match' => !in_array(false, array_column($rows, 'matches'), true),
        ];
    }

    /**
     * @param array<string,mixed> $applyPlan
     */
    private static function roleForPath(array $applyPlan, string $path): string
    {
        if ($path === ($applyPlan['database_path'] ?? null)) {
            return 'database';
        }
        if ($path === ($applyPlan['wal_path'] ?? null)) {
            return 'wal';
        }
        if ($path === ($applyPlan['journal_path'] ?? null)) {
            return 'hot-journal';
        }

        return 'payload';
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
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next180 file paths must be non-empty strings');
            }
            if ($bytes !== null && !is_string($bytes)) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next180 file bytes must be strings or null');
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
     * @param array<string,mixed> $applyPlan
     */
    private static function assertApplyPlan(array $applyPlan): void
    {
        foreach (['status', 'operations', 'payloads', 'dependencies'] as $key) {
            if (!array_key_exists($key, $applyPlan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next180 missing {$key}");
            }
        }
        if (!is_array($applyPlan['operations']) || !is_array($applyPlan['payloads']) || !is_array($applyPlan['dependencies'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next180 operations, payloads, and dependencies must be arrays');
        }
    }
}
