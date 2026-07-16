<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalStatementRecoveryPlan
{
    /**
     * @param list<array{database_path:string,database_bytes:string,journal_bytes:string,journal?:SQLiteRollbackJournal,reserved_lock?:bool}> $databases
     * @return array{status:string,reason:string,primary_database_path:string,super_recovery:array<string,mixed>,statement_recovery:array<string,mixed>|null,current_database_bytes:string,next_database_bytes:string,statement_database_bytes:string|null,operations:list<array<string,mixed>>,payloads:array<string,string>,dependencies:list<string>}
     */
    public static function currentNext(
        string $superJournalPath,
        ?string $superJournalBytes,
        array $databases,
        int $pageSize,
        SQLiteSavepointStack $savepoints,
        string $statementName,
        string $nextStatementName,
        int $nextPageNumber,
        string $nextBeforeImage,
        bool $nextCommitFrame = false,
        ?string $primaryDatabasePath = null,
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        if ($statementName === '') {
            throw new \InvalidArgumentException('SQLite master-journal statement recovery requires a statement name');
        }
        if ($nextStatementName === '') {
            throw new \InvalidArgumentException('SQLite master-journal statement recovery requires a next statement name');
        }
        if ($nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite master-journal statement recovery page numbers are one-based');
        }
        if ($nextBeforeImage === '') {
            throw new \InvalidArgumentException('SQLite master-journal statement recovery requires a next statement page image');
        }
        if (strlen($nextBeforeImage) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite master-journal statement recovery next page image must match the page size');
        }

        $superRecovery = SQLitePagerHotJournalSuperCurrentNextPlan::currentNext(
            $superJournalPath,
            $superJournalBytes,
            $databases,
            $pageSize,
            $readOnly,
            $immutable
        );
        $primaryPath = self::primaryPath($databases, $primaryDatabasePath);
        $currentDatabase = $superRecovery['current_databases'][$primaryPath] ?? null;
        $recoveredDatabase = $superRecovery['next_databases'][$primaryPath] ?? null;
        if (!is_string($currentDatabase) || !is_string($recoveredDatabase)) {
            throw new \InvalidArgumentException("SQLite master-journal statement recovery primary database is not part of the super-journal plan: {$primaryPath}");
        }

        if ($superRecovery['recovered_count'] === 0) {
            return [
                'status' => 'blocked',
                'reason' => 'master_journal_recovery_blocked_before_statement_rollback',
                'primary_database_path' => $primaryPath,
                'super_recovery' => $superRecovery,
                'statement_recovery' => null,
                'current_database_bytes' => $currentDatabase,
                'next_database_bytes' => $recoveredDatabase,
                'statement_database_bytes' => null,
                'operations' => [],
                'payloads' => [],
                'dependencies' => array_values(array_unique(array_merge(
                    $superRecovery['dependencies'],
                    ['sqlite-master-journal-statement-recovery-current-next75']
                ))),
            ];
        }

        $statementDatabase = $savepoints->rollbackStatementDatabaseImage($statementName, $recoveredDatabase, $pageSize);
        $statementRecovery = $savepoints->rollbackStatementAndBeginStatementJournal(
            $statementName,
            $nextStatementName,
            $nextPageNumber,
            $nextBeforeImage,
            $pageSize,
            $nextCommitFrame
        );

        $payloadKey = $primaryPath . '#statement-recovery75';
        $operations = $superRecovery['operations'];
        $operations[] = [
            'op' => 'write',
            'path' => $primaryPath,
            'payload_key' => $payloadKey,
            'offset' => 0,
            'bytes' => strlen($statementDatabase),
            'durable' => false,
            'reason' => 'restore_statement_subjournal_after_master_recovery',
        ];
        $operations[] = [
            'op' => 'truncate',
            'path' => $primaryPath,
            'bytes' => strlen($statementDatabase),
            'durable' => false,
            'reason' => 'trim_statement_recovered_database_after_master_recovery',
        ];
        $operations[] = [
            'op' => 'sync',
            'path' => $primaryPath,
            'durable' => true,
            'reason' => 'sync_statement_recovery_after_master_journal',
        ];
        $operations[] = [
            'op' => 'sync_directory',
            'path' => dirname($primaryPath),
            'durable' => true,
            'reason' => 'persist_statement_recovery_after_master_journal',
        ];

        return [
            'status' => $superRecovery['blocked_count'] === 0 ? 'recovered' : 'partial',
            'reason' => 'master_journal_recovered_before_statement_subjournal_retry',
            'primary_database_path' => $primaryPath,
            'super_recovery' => $superRecovery,
            'statement_recovery' => $statementRecovery,
            'current_database_bytes' => $currentDatabase,
            'next_database_bytes' => $recoveredDatabase,
            'statement_database_bytes' => $statementDatabase,
            'operations' => $operations,
            'payloads' => $superRecovery['payloads'] + [$payloadKey => $statementDatabase],
            'dependencies' => array_values(array_unique(array_merge(
                $superRecovery['dependencies'],
                $statementRecovery['dependencies'],
                [
                    'sqlite-master-journal-statement-recovery-current-next75',
                    'sqlite-statement-subjournal-after-hot-master-recovery',
                ]
            ))),
        ];
    }

    /**
     * @param list<array{database_path:string,database_bytes:string,journal_bytes:string,journal?:SQLiteRollbackJournal,reserved_lock?:bool}> $databases
     */
    private static function primaryPath(array $databases, ?string $primaryDatabasePath): string
    {
        if ($primaryDatabasePath !== null && $primaryDatabasePath !== '') {
            return $primaryDatabasePath;
        }
        $first = $databases[0]['database_path'] ?? '';
        if (!is_string($first) || $first === '') {
            throw new \InvalidArgumentException('SQLite master-journal statement recovery requires a primary database path');
        }

        return $first;
    }
}
