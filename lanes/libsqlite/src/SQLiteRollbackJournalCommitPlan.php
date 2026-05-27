<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRollbackJournalCommitPlan
{
    /**
     * @param array<int, string> $databasePages 1-indexed page numbers to page images.
     * @return array{database_path:string,journal_path:string,page_size:int,sync_mode:string,journal_mode:string,read_only:bool,immutable:bool,database_pages:list<int>,database_bytes:int,journal_bytes:int,operations:list<array{op:string,path:string,offset?:int,bytes?:int,durable?:bool,reason:string}>,dependencies:list<string>}
     */
    public static function commit(
        string $databasePath,
        string $journalBytes,
        array $databasePages,
        int $pageSize,
        string $syncMode = 'full',
        string $journalMode = 'delete',
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite rollback-journal commit requires a database path');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite rollback-journal commit requires rollback-journal bytes');
        }
        if ($databasePages === []) {
            throw new \InvalidArgumentException('SQLite rollback-journal commit requires at least one dirty database page');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite rollback-journal commit page size must be a power of two at least 512');
        }
        if ($readOnly || $immutable) {
            throw new \LogicException('SQLite rollback-journal commit requires a writable database handle');
        }

        $syncMode = strtolower($syncMode);
        if (!in_array($syncMode, ['off', 'normal', 'full', 'extra'], true)) {
            throw new \InvalidArgumentException('SQLite rollback-journal commit sync mode must be off, normal, full, or extra');
        }

        $journalMode = strtolower($journalMode);
        if (!in_array($journalMode, ['delete', 'truncate', 'persist'], true)) {
            throw new \InvalidArgumentException('SQLite rollback-journal commit journal mode must be delete, truncate, or persist');
        }

        ksort($databasePages);
        $operations = [];
        $journalPath = $databasePath . '-journal';
        $operations[] = [
            'op' => 'write',
            'path' => $journalPath,
            'offset' => 0,
            'bytes' => strlen($journalBytes),
            'durable' => false,
            'reason' => 'write_rollback_journal_before_database_pages',
        ];
        if ($syncMode !== 'off') {
            $operations[] = [
                'op' => 'sync',
                'path' => $journalPath,
                'durable' => true,
                'reason' => $syncMode === 'extra' ? 'sync_rollback_journal_fullfsync' : 'sync_rollback_journal',
            ];
        }

        $pageNumbers = [];
        $databaseBytes = 0;
        foreach ($databasePages as $pageNumber => $pageImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite rollback-journal commit page numbers must be positive integers');
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite rollback-journal commit page {$pageNumber} image must match page size");
            }

            $pageNumbers[] = $pageNumber;
            $databaseBytes += $pageSize;
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'payload_key' => $databasePath . '#page:' . $pageNumber,
                'offset' => ($pageNumber - 1) * $pageSize,
                'bytes' => $pageSize,
                'durable' => false,
                'reason' => "write_dirty_database_page_{$pageNumber}",
            ];
        }

        if ($syncMode !== 'off') {
            $operations[] = [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_committed_database_pages',
            ];
        }

        if ($journalMode === 'delete') {
            $operations[] = [
                'op' => 'delete',
                'path' => $journalPath,
                'durable' => false,
                'reason' => 'delete_rollback_journal_after_commit',
            ];
        } elseif ($journalMode === 'truncate') {
            $operations[] = [
                'op' => 'truncate',
                'path' => $journalPath,
                'bytes' => 0,
                'durable' => false,
                'reason' => 'truncate_rollback_journal_after_commit',
            ];
        } else {
            $operations[] = [
                'op' => 'write',
                'path' => $journalPath,
                'payload_key' => $journalPath . '#persist-header',
                'offset' => 0,
                'bytes' => strlen(str_repeat("\0", min(28, strlen($journalBytes)))),
                'durable' => false,
                'reason' => 'zero_rollback_journal_header_after_commit',
            ];
        }

        if ($syncMode !== 'off') {
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_rollback_journal_commit_sidecar',
            ];
        }

        return [
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'page_size' => $pageSize,
            'sync_mode' => $syncMode,
            'journal_mode' => $journalMode,
            'read_only' => $readOnly,
            'immutable' => $immutable,
            'database_pages' => $pageNumbers,
            'database_bytes' => $databaseBytes,
            'journal_bytes' => strlen($journalBytes),
            'operations' => $operations,
            'dependencies' => ['sqlite-rollback-journal-commit', 'durable-journal-before-database-write', 'vfs-file-write-coordination'],
        ];
    }
}
