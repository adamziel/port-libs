<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsFileWriter
{
    public function __construct(
        private readonly string $rootDirectory,
        private readonly bool $readOnly = false,
        private readonly bool $immutable = false,
    ) {
        if ($rootDirectory === '') {
            throw new \InvalidArgumentException('SQLite VFS file writer requires a root directory');
        }
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>}
     */
    public function applyWalCheckpoint(SQLiteWal $wal, string $databaseBytes, string $databasePath, string $mode = 'passive', ?int $readerEndFrame = null): array
    {
        $plan = SQLiteWalFileWritePlan::checkpoint($wal, $databaseBytes, $databasePath, $mode, $readerEndFrame, $this->readOnly, $this->immutable);
        $result = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $payloads = [
            $plan['database_path'] => $result['database_bytes'],
            $plan['wal_path'] => $result['wal_bytes'],
        ];

        return $this->applyOperations($plan['operations'], $payloads, $plan['dependencies']);
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>}
     */
    public function applyHotRollbackJournal(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        string $databasePath,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite rollback journal VFS recovery requires a database path');
        }

        $result = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes, $databaseReservedLock, $requiresSuperJournal, $superJournalExists);
        if (!$result['recovered']) {
            return [
                'status' => 'skipped',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => ['sqlite-rollback-journal-recovery', 'vfs-file-handle-write-application'],
                'recovery' => $result,
            ];
        }

        $journalPath = $databasePath . '-journal';
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($result['database_bytes']),
                'durable' => false,
                'reason' => 'restore_database_pages_from_hot_journal',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($result['database_bytes']),
                'durable' => false,
                'reason' => 'truncate_database_to_pretransaction_size',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_rollback_recovered_database',
            ],
            [
                'op' => 'delete',
                'path' => $journalPath,
                'durable' => false,
                'reason' => 'delete_hot_rollback_journal',
            ],
            [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_rollback_journal_deletion',
            ],
        ];

        $applied = $this->applyOperations(
            $operations,
            [$databasePath => $result['database_bytes']],
            ['sqlite-rollback-journal-recovery', 'hot-journal-delete', 'vfs-file-write-coordination']
        );
        $applied['recovery'] = $result;

        return $applied;
    }

    /**
     * @param array<int, string> $databasePages 1-indexed page numbers to page images.
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,commit:array<string, mixed>}
     */
    public function applyRollbackJournalCommit(
        string $databasePath,
        string $journalBytes,
        array $databasePages,
        int $pageSize,
        string $syncMode = 'full',
        string $journalMode = 'delete',
    ): array {
        $plan = SQLiteRollbackJournalCommitPlan::commit(
            $databasePath,
            $journalBytes,
            $databasePages,
            $pageSize,
            $syncMode,
            $journalMode,
            $this->readOnly,
            $this->immutable
        );

        $payloads = [$plan['journal_path'] => $journalBytes];
        foreach ($databasePages as $pageNumber => $pageImage) {
            $payloads[$databasePath . '#page:' . $pageNumber] = $pageImage;
        }
        if ($plan['journal_mode'] === 'persist') {
            $payloads[$plan['journal_path'] . '#persist-header'] = str_repeat("\0", min(28, strlen($journalBytes)));
        }

        $applied = $this->applyOperations($plan['operations'], $payloads, $plan['dependencies']);
        $applied['commit'] = $plan;

        return $applied;
    }

    /**
     * @param list<array{database_path:string,journal_bytes:string,database_pages:array<int,string>}> $databaseCommits
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,commit:array<string, mixed>}
     */
    public function applySuperJournalCommit(
        string $superJournalPath,
        array $databaseCommits,
        int $pageSize,
        string $syncMode = 'full',
        string $journalMode = 'delete',
    ): array {
        $plan = SQLiteSuperJournalCommitPlan::commit(
            $superJournalPath,
            $databaseCommits,
            $pageSize,
            $syncMode,
            $journalMode,
            $this->readOnly,
            $this->immutable
        );

        $applied = $this->applyOperations(
            $plan['operations'],
            SQLiteSuperJournalCommitPlan::payloads($superJournalPath, $databaseCommits),
            $plan['dependencies']
        );
        $applied['commit'] = $plan;

        return $applied;
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,savepoint:string,database_image:array<string, mixed>,wal_truncation:array<string, mixed>|null}
     */
    public function applySavepointRollback(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        string $databaseBytes,
        int $pageSize,
        string $databasePath,
        ?SQLiteWal $wal = null,
        ?string $walBytes = null,
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite savepoint VFS rollback requires a savepoint name');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite savepoint VFS rollback requires a database path');
        }

        $databaseImage = $savepoints->rollbackToDatabaseImage($savepoint, $databaseBytes, $pageSize);
        $imagePlan = $savepoints->rollbackToImagePlan($savepoint, $pageSize);
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($databaseImage),
                'durable' => false,
                'reason' => 'restore_savepoint_database_page_images',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($databaseImage),
                'durable' => false,
                'reason' => 'trim_savepoint_database_image',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_savepoint_database_rollback',
            ],
        ];
        $payloads = [$databasePath => $databaseImage];
        $walPlan = null;

        if (($wal === null) !== ($walBytes === null)) {
            throw new \InvalidArgumentException('SQLite savepoint VFS rollback requires both WAL object and WAL bytes');
        }
        if ($wal !== null && $walBytes !== null) {
            $walPath = $databasePath . '-wal';
            $walImage = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
            $walPlan = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
            $operations[] = [
                'op' => 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($walImage),
                'durable' => false,
                'reason' => 'restore_savepoint_wal_prefix',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $walPath,
                'bytes' => strlen($walImage),
                'durable' => false,
                'reason' => 'truncate_savepoint_wal_frames',
            ];
            $operations[] = [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_savepoint_wal_rollback',
            ];
            $payloads[$walPath] = $walImage;
        }

        $operations[] = [
            'op' => 'sync_directory',
            'path' => dirname($databasePath),
            'durable' => true,
            'reason' => 'persist_savepoint_rollback_sidecars',
        ];

        $applied = $this->applyOperations(
            $operations,
            $payloads,
            ['sqlite-savepoint-page-image-rollback', 'sqlite-savepoint-wal-rollback', 'vfs-file-write-coordination']
        );
        $applied['savepoint'] = $savepoint;
        $applied['database_image'] = $imagePlan;
        $applied['wal_truncation'] = $walPlan;

        return $applied;
    }

    /**
     * @param list<array<string, mixed>> $operations
     * @param array<string, string> $payloads
     * @param list<string> $dependencies
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>}
     */
    public function applyOperations(array $operations, array $payloads = [], array $dependencies = []): array
    {
        if ($this->readOnly || $this->immutable) {
            throw new \LogicException('SQLite VFS file writer requires a writable handle');
        }

        $applied = [];
        $bytesWritten = 0;
        $bytesTruncated = 0;
        $filesDeleted = 0;
        $durableSyncs = 0;
        $directorySyncs = 0;

        foreach ($operations as $index => $operation) {
            $op = isset($operation['op']) ? (string) $operation['op'] : '';
            $path = isset($operation['path']) ? (string) $operation['path'] : '';
            if ($path === '') {
                throw new \InvalidArgumentException('SQLite VFS operation requires a path');
            }

            $localPath = $this->localPath($path);
            if ($op === 'write') {
                $offset = $this->nonNegativeInt($operation['offset'] ?? 0, 'SQLite VFS write offset');
                $payloadKey = isset($operation['payload_key']) ? (string) $operation['payload_key'] : $path;
                if (!array_key_exists($payloadKey, $payloads)) {
                    throw new \InvalidArgumentException("SQLite VFS write payload is missing for {$path}");
                }
                $data = $payloads[$payloadKey];
                $expected = $this->nonNegativeInt($operation['bytes'] ?? strlen($data), 'SQLite VFS write byte count');
                if ($expected !== strlen($data)) {
                    throw new \InvalidArgumentException("SQLite VFS write payload length mismatch for {$path}");
                }
                $this->writeAt($localPath, $offset, $data);
                $bytesWritten += strlen($data);
                $applied[] = $this->applied($index, $operation, $localPath, strlen($data));
            } elseif ($op === 'truncate') {
                $size = $this->nonNegativeInt($operation['bytes'] ?? 0, 'SQLite VFS truncate size');
                $this->truncate($localPath, $size);
                $bytesTruncated += $size;
                $applied[] = $this->applied($index, $operation, $localPath, $size);
            } elseif ($op === 'delete') {
                if (is_file($localPath) && !unlink($localPath)) {
                    throw new \RuntimeException("SQLite VFS could not delete file: {$path}");
                }
                $filesDeleted++;
                $applied[] = $this->applied($index, $operation, $localPath, 0);
            } elseif ($op === 'sync') {
                if (!is_file($localPath)) {
                    throw new \RuntimeException("SQLite VFS sync target does not exist: {$path}");
                }
                $handle = @fopen($localPath, 'c+b');
                if (!is_resource($handle)) {
                    throw new \RuntimeException("SQLite VFS sync target is not writable: {$path}");
                }
                fflush($handle);
                fclose($handle);
                $durableSyncs++;
                $applied[] = $this->applied($index, $operation, $localPath, 0);
            } elseif ($op === 'sync_directory') {
                if (!is_dir($localPath)) {
                    throw new \RuntimeException("SQLite VFS directory sync target does not exist: {$path}");
                }
                $directorySyncs++;
                $applied[] = $this->applied($index, $operation, $localPath, 0);
            } else {
                throw new \InvalidArgumentException("Unsupported SQLite VFS operation: {$op}");
            }
        }

        return [
            'status' => 'applied',
            'root' => $this->rootDirectory,
            'applied' => count($applied),
            'bytes_written' => $bytesWritten,
            'bytes_truncated' => $bytesTruncated,
            'files_deleted' => $filesDeleted,
            'durable_syncs' => $durableSyncs,
            'directory_syncs' => $directorySyncs,
            'operations' => $applied,
            'dependencies' => array_values(array_unique(array_merge($dependencies, ['vfs-file-handle-write-application']))),
        ];
    }

    private function localPath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS path must not contain NUL bytes');
        }

        $root = rtrim($this->rootDirectory, DIRECTORY_SEPARATOR);
        $normalized = str_replace('\\', '/', $path);
        $relative = ltrim($normalized, '/');
        if ($relative === '' || str_contains($relative, '../') || str_starts_with($relative, '..')) {
            throw new \InvalidArgumentException("SQLite VFS path escapes writer root: {$path}");
        }

        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function writeAt(string $path, int $offset, string $data): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create directory: {$directory}");
        }

        $handle = @fopen($path, 'c+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS could not open file for writing: {$path}");
        }
        if (fseek($handle, $offset) !== 0) {
            fclose($handle);
            throw new \RuntimeException("SQLite VFS could not seek to offset {$offset}: {$path}");
        }
        $written = fwrite($handle, $data);
        fflush($handle);
        fclose($handle);
        if ($written !== strlen($data)) {
            throw new \RuntimeException("SQLite VFS short write: {$path}");
        }
    }

    private function truncate(string $path, int $size): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create directory: {$directory}");
        }

        $handle = @fopen($path, 'c+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS could not open file for truncation: {$path}");
        }
        if (!ftruncate($handle, $size)) {
            fclose($handle);
            throw new \RuntimeException("SQLite VFS truncate failed: {$path}");
        }
        fflush($handle);
        fclose($handle);
    }

    private function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("{$label} must be a non-negative integer");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function applied(int $index, array $operation, string $localPath, int $bytes): array
    {
        return [
            'index' => $index,
            'op' => (string) $operation['op'],
            'path' => (string) $operation['path'],
            'local_path' => $localPath,
            'bytes' => $bytes,
            'durable' => (bool) ($operation['durable'] ?? false),
            'reason' => isset($operation['reason']) ? (string) $operation['reason'] : null,
        ];
    }
}
