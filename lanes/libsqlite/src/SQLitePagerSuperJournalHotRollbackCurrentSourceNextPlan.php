<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan
{
    /**
     * @param list<array{database_path:string,current_database_bytes:string,current_journal_bytes:string,stale_database_bytes?:string,stale_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @return array{status:string,reason:string,super_journal_path:string,super_journal_exists:bool,super_journal_members:list<string>,database_count:int,recovered_database_count:int,blocked_database_count:int,stale_candidate_count:int,current_source_bytes:array<string,array{database:int,journal:int}>,next_databases:array<string,string>,journal_actions:array<string,string>,hot_journals:array<string,array<string,mixed>>,operations:list<array<string,mixed>>,payloads:array<string,string>,dependencies:list<string>}
     */
    public static function currentSourceNext(
        string $superJournalPath,
        ?string $superJournalBytes,
        array $databases,
        int $pageSize,
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        if ($superJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager super-journal hot rollback current-source requires a super-journal path');
        }
        if ($databases === []) {
            throw new \InvalidArgumentException('SQLite pager super-journal hot rollback current-source requires at least one attached database');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager super-journal hot rollback current-source page size must be a power of two at least 512');
        }
        if ($readOnly || $immutable) {
            throw new \LogicException('SQLite pager super-journal hot rollback current-source requires writable database handles');
        }

        $superExists = $superJournalBytes !== null;
        $members = $superExists ? self::journalList($superJournalBytes) : [];
        $memberSet = array_fill_keys($members, true);
        $seen = [];
        $currentSourceBytes = [];
        $nextDatabases = [];
        $journalActions = [];
        $hotJournals = [];
        $operations = [];
        $payloads = [];
        $recovered = 0;
        $blocked = 0;
        $staleCandidates = 0;

        foreach ($databases as $index => $entry) {
            $databasePath = isset($entry['database_path']) ? (string) $entry['database_path'] : '';
            if ($databasePath === '') {
                throw new \InvalidArgumentException("SQLite pager super-journal hot rollback current-source database {$index} requires a database path");
            }
            if (isset($seen[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager super-journal hot rollback current-source duplicate database path: {$databasePath}");
            }
            $seen[$databasePath] = true;

            $databaseBytes = isset($entry['current_database_bytes']) ? (string) $entry['current_database_bytes'] : '';
            $journalBytes = isset($entry['current_journal_bytes']) ? (string) $entry['current_journal_bytes'] : '';
            if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
                throw new \InvalidArgumentException("SQLite pager super-journal hot rollback current-source database {$databasePath} image must be page aligned");
            }
            if ($journalBytes === '') {
                throw new \InvalidArgumentException("SQLite pager super-journal hot rollback current-source database {$databasePath} requires current rollback-journal bytes");
            }

            $journalPath = $databasePath . '-journal';
            $listed = isset($memberSet[$journalPath]);
            $staleDatabaseBytes = isset($entry['stale_database_bytes']) ? (string) $entry['stale_database_bytes'] : null;
            $staleJournalBytes = isset($entry['stale_journal_bytes']) ? (string) $entry['stale_journal_bytes'] : null;
            $staleIgnored = ($staleDatabaseBytes !== null && $staleDatabaseBytes !== $databaseBytes)
                || ($staleJournalBytes !== null && $staleJournalBytes !== $journalBytes);
            if ($staleIgnored) {
                $staleCandidates++;
            }

            $journal = SQLiteRollbackJournal::parse($journalBytes, true);
            $result = $journal->hotJournalRecoveryResult(
                $databaseBytes,
                $journalBytes,
                (bool) ($entry['reserved_lock'] ?? false),
                true,
                $superExists && $listed
            );

            $currentSourceBytes[$databasePath] = [
                'database' => strlen($databaseBytes),
                'journal' => strlen($journalBytes),
            ];
            $nextDatabases[$databasePath] = $result['database_bytes'];
            $journalActions[$journalPath] = $result['journal_action'];
            $hotJournals[$journalPath] = array_merge($result['hot_journal'], [
                'listed_in_super_journal' => $listed,
                'stale_candidate_ignored' => $staleIgnored,
                'current_source_database_prefix' => rtrim(substr($databaseBytes, 0, 56), "\0."),
                'next_database_prefix' => rtrim(substr($result['database_bytes'], 0, 56), "\0."),
            ]);

            if (!$result['recovered']) {
                $blocked++;
                continue;
            }

            $recovered++;
            $payloadKey = $databasePath . '#super-hot-rollback-current-source106';
            $payloads[$payloadKey] = $result['database_bytes'];
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'payload_key' => $payloadKey,
                'offset' => 0,
                'bytes' => strlen($result['database_bytes']),
                'durable' => false,
                'reason' => 'restore_current_source_database_from_super_hot_journal',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($result['database_bytes']),
                'durable' => false,
                'reason' => 'trim_current_source_database_after_super_hot_rollback',
            ];
            $operations[] = [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_current_source_database_after_super_hot_rollback',
            ];
            $operations[] = [
                'op' => 'delete',
                'path' => $journalPath,
                'durable' => false,
                'reason' => 'delete_current_source_hot_journal_after_super_rollback',
            ];
        }

        $allMembersCleared = $members !== [];
        foreach ($members as $member) {
            if (($journalActions[$member] ?? null) !== 'delete_journal_after_recovery') {
                $allMembersCleared = false;
                break;
            }
        }
        if ($allMembersCleared) {
            $operations[] = [
                'op' => 'delete',
                'path' => $superJournalPath,
                'durable' => false,
                'reason' => 'delete_super_journal_after_current_source_hot_rollback',
            ];
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($superJournalPath),
                'durable' => true,
                'reason' => 'persist_super_journal_current_source_hot_rollback',
            ];
        }

        return [
            'status' => $recovered === 0
                ? ($superExists ? 'super_journal_current_source_hot_rollback_blocked' : 'super_journal_missing_preserved_current_source')
                : ($blocked === 0 ? 'super_journal_current_source_hot_rollback_complete' : 'super_journal_current_source_hot_rollback_partial'),
            'reason' => 'current_vfs_source_controls_super_journal_hot_rollback',
            'super_journal_path' => $superJournalPath,
            'super_journal_exists' => $superExists,
            'super_journal_members' => $members,
            'database_count' => count($databases),
            'recovered_database_count' => $recovered,
            'blocked_database_count' => $blocked,
            'stale_candidate_count' => $staleCandidates,
            'current_source_bytes' => $currentSourceBytes,
            'next_databases' => $nextDatabases,
            'journal_actions' => $journalActions,
            'hot_journals' => $hotJournals,
            'operations' => $operations,
            'payloads' => $payloads,
            'dependencies' => [
                'sqlite-pager-super-journal-hot-rollback-current-source-next106',
                'sqlite-rollback-journal-recovery',
                'sqlite-super-journal-hot-rollback-current-source',
                'vfs-file-write-coordination',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function journalList(string $superJournalBytes): array
    {
        $paths = [];
        foreach (preg_split('/\r?\n/', $superJournalBytes) ?: [] as $path) {
            $path = trim($path);
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }
}
