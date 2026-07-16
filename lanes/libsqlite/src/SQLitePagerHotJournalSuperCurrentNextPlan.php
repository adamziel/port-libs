<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalSuperCurrentNextPlan
{
    /**
     * @param list<array{database_path:string,database_bytes:string,journal_bytes:string,journal?:SQLiteRollbackJournal,reserved_lock?:bool}> $databases
     * @return array{status:string,reason:string,super_journal_path:string,super_journal_exists:bool,database_count:int,recovered_count:int,blocked_count:int,current_databases:array<string,string>,next_databases:array<string,string>,current_page_summaries:array<string,list<array<string,mixed>>>,next_page_summaries:array<string,list<array<string,mixed>>>,journal_actions:array<string,string>,hot_journals:array<string,array<string,mixed>>,operations:list<array<string,mixed>>,payloads:array<string,string>,dependencies:list<string>}
     */
    public static function currentNext(
        string $superJournalPath,
        ?string $superJournalBytes,
        array $databases,
        int $pageSize,
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        if ($superJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal super-journal recovery requires a super-journal path');
        }
        if ($databases === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal super-journal recovery requires at least one attached database');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager hot-journal super-journal page size must be a power of two at least 512');
        }
        if ($readOnly || $immutable) {
            throw new \LogicException('SQLite pager hot-journal super-journal recovery requires writable database handles');
        }

        $superJournalExists = $superJournalBytes !== null;
        $listedJournals = $superJournalExists ? self::journalList($superJournalBytes) : [];
        $operations = [];
        $payloads = [];
        $currentDatabases = [];
        $nextDatabases = [];
        $currentSummaries = [];
        $nextSummaries = [];
        $journalActions = [];
        $hotJournals = [];
        $recoveredCount = 0;
        $blockedCount = 0;
        $seen = [];

        foreach ($databases as $index => $entry) {
            $databasePath = isset($entry['database_path']) ? (string) $entry['database_path'] : '';
            $databaseBytes = isset($entry['database_bytes']) ? (string) $entry['database_bytes'] : '';
            $journalBytes = isset($entry['journal_bytes']) ? (string) $entry['journal_bytes'] : '';
            if ($databasePath === '') {
                throw new \InvalidArgumentException("SQLite pager hot-journal super-journal database {$index} requires a database path");
            }
            if (isset($seen[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager hot-journal super-journal duplicate database path: {$databasePath}");
            }
            $seen[$databasePath] = true;
            if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
                throw new \InvalidArgumentException("SQLite pager hot-journal super-journal database {$databasePath} image must be page aligned");
            }
            if ($journalBytes === '') {
                throw new \InvalidArgumentException("SQLite pager hot-journal super-journal database {$databasePath} requires rollback-journal bytes");
            }

            $journal = $entry['journal'] ?? SQLiteRollbackJournal::parse($journalBytes, true);
            if (!$journal instanceof SQLiteRollbackJournal) {
                throw new \InvalidArgumentException("SQLite pager hot-journal super-journal database {$databasePath} journal must be a SQLiteRollbackJournal");
            }

            $journalPath = $databasePath . '-journal';
            $reservedLock = (bool) ($entry['reserved_lock'] ?? false);
            $listed = in_array($journalPath, $listedJournals, true);
            $result = $journal->hotJournalRecoveryResult(
                $databaseBytes,
                $journalBytes,
                $reservedLock,
                true,
                $superJournalExists && $listed
            );

            $currentDatabases[$databasePath] = $databaseBytes;
            $nextDatabases[$databasePath] = $result['database_bytes'];
            $currentSummaries[$databasePath] = self::pageSummaries($databaseBytes, $pageSize);
            $nextSummaries[$databasePath] = self::pageSummaries($result['database_bytes'], $pageSize);
            $journalActions[$journalPath] = $result['journal_action'];
            $hotJournals[$journalPath] = array_merge($result['hot_journal'], ['listed_in_super_journal' => $listed]);

            if ($result['recovered']) {
                $recoveredCount++;
                $payloadKey = $databasePath . '#hot-super-journal';
                $operations[] = [
                    'op' => 'write',
                    'path' => $databasePath,
                    'payload_key' => $payloadKey,
                    'offset' => 0,
                    'bytes' => strlen($result['database_bytes']),
                    'durable' => false,
                    'reason' => 'restore_attached_database_from_hot_journal_super_journal',
                ];
                $operations[] = [
                    'op' => 'truncate',
                    'path' => $databasePath,
                    'bytes' => strlen($result['database_bytes']),
                    'durable' => false,
                    'reason' => 'trim_attached_database_after_hot_journal_super_journal',
                ];
                $operations[] = [
                    'op' => 'sync',
                    'path' => $databasePath,
                    'durable' => true,
                    'reason' => 'sync_attached_database_after_hot_journal_super_journal',
                ];
                $operations[] = [
                    'op' => 'delete',
                    'path' => $journalPath,
                    'durable' => false,
                    'reason' => 'delete_attached_hot_journal_after_super_recovery',
                ];
                $payloads[$payloadKey] = $result['database_bytes'];
            } else {
                $blockedCount++;
            }
        }

        if ($recoveredCount > 0) {
            $operations[] = [
                'op' => 'delete',
                'path' => $superJournalPath,
                'durable' => false,
                'reason' => 'delete_super_journal_after_attached_hot_recovery',
            ];
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($superJournalPath),
                'durable' => true,
                'reason' => 'persist_super_journal_hot_recovery_sidecars',
            ];
        }

        $status = $recoveredCount === 0
            ? ($superJournalExists ? 'super_journal_hot_recovery_blocked' : 'super_journal_missing_preserved_current')
            : ($blockedCount === 0 ? 'super_journal_hot_recovery_complete' : 'super_journal_hot_recovery_partial');

        return [
            'status' => $status,
            'reason' => 'current_dirty_attached_databases_next_super_journal_hot_recovery',
            'super_journal_path' => $superJournalPath,
            'super_journal_exists' => $superJournalExists,
            'database_count' => count($databases),
            'recovered_count' => $recoveredCount,
            'blocked_count' => $blockedCount,
            'current_databases' => $currentDatabases,
            'next_databases' => $nextDatabases,
            'current_page_summaries' => $currentSummaries,
            'next_page_summaries' => $nextSummaries,
            'journal_actions' => $journalActions,
            'hot_journals' => $hotJournals,
            'operations' => $operations,
            'payloads' => $payloads,
            'dependencies' => [
                'sqlite-pager-hot-journal-super-current-next',
                'sqlite-rollback-journal-recovery',
                'sqlite-super-journal-hot-recovery',
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

    /**
     * @return list<array{page_number:int,bytes:int,prefix:string,all_zero:bool}>
     */
    private static function pageSummaries(string $databaseBytes, int $pageSize): array
    {
        $summaries = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($page = 1; $page <= $pageCount; $page++) {
            $image = substr($databaseBytes, ($page - 1) * $pageSize, $pageSize);
            $summaries[] = [
                'page_number' => $page,
                'bytes' => strlen($image),
                'prefix' => rtrim(substr($image, 0, 48), "\0."),
                'all_zero' => trim($image, "\0") === '',
            ];
        }

        return $summaries;
    }
}
