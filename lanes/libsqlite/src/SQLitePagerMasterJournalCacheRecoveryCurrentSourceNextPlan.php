<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan
{
    /**
     * @param list<array{database_path:string,current_database_bytes:string,current_journal_bytes:string,stale_database_bytes?:string,stale_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @param array<int,string> $retryPageWrites
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        array $databases,
        int $pageSize,
        string $primaryDatabasePath,
        string $savepointName,
        SQLiteSavepointStack $savepoints,
        array $retryPageWrites,
        bool $readOnly = false,
        bool $immutable = false,
    ): array {
        $journalInputs = [];
        foreach ($databases as $database) {
            $path = isset($database['database_path']) ? (string) $database['database_path'] : '';
            if ($path === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal cache recovery requires database paths');
            }
            $journalInputs[] = [
                'database_path' => $path,
                'journal_path' => $path . '-journal',
                'current_journal_bytes' => $database['stale_journal_bytes'] ?? $database['current_journal_bytes'] ?? null,
                'next_journal_bytes' => $database['current_journal_bytes'] ?? null,
                'current_reserved_lock' => $database['reserved_lock'] ?? false,
                'next_reserved_lock' => $database['reserved_lock'] ?? false,
            ];
        }

        $cache = SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext(
            $masterJournalPath,
            $cachedMasterJournalBytes,
            $currentMasterJournalBytes,
            $journalInputs
        );

        $cachedRecovery = SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext(
            $masterJournalPath,
            $cachedMasterJournalBytes,
            $databases,
            $pageSize,
            $primaryDatabasePath,
            $savepointName,
            clone $savepoints,
            $retryPageWrites,
            $readOnly,
            $immutable
        );

        $currentRecovery = SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext(
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databases,
            $pageSize,
            $primaryDatabasePath,
            $savepointName,
            clone $savepoints,
            $retryPageWrites,
            $readOnly,
            $immutable
        );

        $cachedMembers = self::members($cachedMasterJournalBytes);
        $currentMembers = self::members($currentMasterJournalBytes);
        $cacheStale = $cache['cache_invalidated']
            || $cachedMembers !== $currentMembers
            || ($cachedRecovery['current_source_verified'] ?? false) !== ($currentRecovery['current_source_verified'] ?? false)
            || ($cachedRecovery['retry_recovery']['master_recovery']['recovered_database_count'] ?? null) !== ($currentRecovery['retry_recovery']['master_recovery']['recovered_database_count'] ?? null);

        $operations = $cache['operations'];
        if ($cacheStale) {
            array_unshift($operations, [
                'op' => 'discard_cached_master_journal_recovery',
                'path' => $masterJournalPath,
                'reason' => 'cached_master_journal_members_do_not_match_current_vfs_source',
            ]);
        }
        foreach ($currentRecovery['operations'] ?? [] as $operation) {
            $operation['reason'] = ($operation['reason'] ?? 'master_journal_recovery') . '_after_cache_refresh_current_source_next122';
            $operations[] = $operation;
        }

        return [
            'status' => $cacheStale
                ? 'master_journal_cache_recovery_current_source_next122'
                : 'master_journal_cache_recovery_current_source_unchanged_next122',
            'reason' => $cacheStale
                ? 'master_journal_recovery_uses_current_source_after_cache_refresh'
                : 'master_journal_cached_source_matches_current_source',
            'master_journal_path' => $masterJournalPath,
            'cache_stale_rejected' => $cacheStale,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'cache' => $cache,
            'cached_recovery_status' => $cachedRecovery['status'],
            'current_recovery_status' => $currentRecovery['status'],
            'cached_recovered_database_count' => $cachedRecovery['retry_recovery']['master_recovery']['recovered_database_count'] ?? 0,
            'current_recovered_database_count' => $currentRecovery['retry_recovery']['master_recovery']['recovered_database_count'] ?? 0,
            'current_source_verified' => ($currentRecovery['current_source_verified'] ?? false) === true,
            'recovery' => $currentRecovery,
            'rollback_preview' => $currentRecovery['rollback_preview'] ?? null,
            'operations' => $operations,
            'payloads' => $currentRecovery['payloads'] ?? [],
            'dependencies' => array_values(array_unique(array_merge(
                $cache['dependencies'],
                $currentRecovery['dependencies'] ?? [],
                ['sqlite-pager-master-journal-cache-recovery-current-source-next122']
            ))),
        ];
    }

    /**
     * @return list<string>
     */
    private static function members(?string $bytes): array
    {
        if ($bytes === null || trim($bytes) === '') {
            return [];
        }

        $members = [];
        foreach (preg_split('/\r?\n/', $bytes) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !isset($members[$line])) {
                $members[$line] = $line;
            }
        }

        return array_values($members);
    }
}
