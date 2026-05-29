<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerWalSavepointMasterJournalCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        ?string $nextMasterJournalBytes,
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databasePath,
        array $pageNumbers,
        bool $databaseReservedLock = false,
    ): array {
        if ($masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint master-journal current-source next126 requires a master-journal path');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint master-journal current-source next126 requires a database path');
        }
        if ($currentMasterJournalBytes === null || trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint master-journal current-source next126 requires current master-journal bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint master-journal current-source next126 requires page numbers');
        }

        $journalPath = $databasePath . '-journal';
        $cachedReplay = SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext(
            $masterJournalPath,
            $cachedMasterJournalBytes,
            $cachedMasterJournalBytes,
            $journal,
            $databaseBytes,
            $journalBytes,
            clone $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databasePath,
            $pageNumbers,
            $databaseReservedLock
        );
        $currentReplay = SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext(
            $masterJournalPath,
            $currentMasterJournalBytes,
            $nextMasterJournalBytes,
            $journal,
            $databaseBytes,
            $journalBytes,
            clone $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databasePath,
            $pageNumbers,
            $databaseReservedLock
        );

        $cachedMembers = self::members($cachedMasterJournalBytes);
        $currentMembers = self::members($currentMasterJournalBytes);
        $nextMembers = self::members($nextMasterJournalBytes);
        $cachedStale = $cachedMembers !== $currentMembers
            || ($cachedReplay['next_master_member'] ?? false) !== ($currentReplay['next_master_member'] ?? false)
            || ($cachedReplay['replay']['hot_recovered'] ?? false) !== ($currentReplay['replay']['hot_recovered'] ?? false);

        $currentMember = in_array($journalPath, $currentMembers, true);
        $nextMember = in_array($journalPath, $nextMembers, true);
        if (!$currentMember) {
            throw new \RuntimeException('SQLite pager WAL savepoint master-journal current-source next126 current master journal does not name the database journal');
        }

        $operations = [];
        if ($cachedStale) {
            $operations[] = [
                'op' => 'discard_cached_master_journal_wal_savepoint_source',
                'path' => $masterJournalPath,
                'reason' => 'cached_master_journal_members_do_not_match_current_source_next126',
            ];
        }
        $operations[] = [
            'op' => 'read_current_master_journal',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'reason' => 'read_current_master_journal_before_wal_savepoint_replay_next126',
        ];
        foreach ($currentReplay['operations'] as $operation) {
            $operation['reason'] = ($operation['reason'] ?? 'wal_savepoint_master_journal') . '_after_current_master_source_next126';
            $operations[] = $operation;
        }

        $replay = $currentReplay['replay'];
        $status = ($replay['hot_recovered'] ?? false)
            ? 'pager_wal_savepoint_master_journal_current_source_next126'
            : 'pager_wal_savepoint_master_journal_current_source_blocked_next126';
        $reason = $cachedStale
            ? 'stale_cached_master_journal_rejected_before_wal_savepoint_replay'
            : 'cached_master_journal_matches_current_wal_savepoint_source';
        if (!$nextMember) {
            $reason = 'current_master_journal_missing_from_next_source_blocks_hot_recovery';
        }

        return [
            'status' => $status,
            'reason' => $reason,
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'master_journal_path' => $masterJournalPath,
            'savepoint' => $savepoint,
            'page_numbers' => $pageNumbers,
            'cached_stale_rejected' => $cachedStale,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'next_members' => $nextMembers,
            'current_master_member' => $currentMember,
            'next_master_member' => $nextMember,
            'cached_status' => $cachedReplay['status'],
            'current_status' => $currentReplay['status'],
            'cached_hot_recovered' => (bool) ($cachedReplay['replay']['hot_recovered'] ?? false),
            'current_hot_recovered' => (bool) ($replay['hot_recovered'] ?? false),
            'rollback_to_frame' => $replay['rollback_to_frame'] ?? null,
            'retained_frame_count' => $replay['retained_frame_count'] ?? null,
            'discarded_frame_count' => $replay['discarded_frame_count'] ?? null,
            'checkpoint_database_bytes' => $replay['wal_recovery']['checkpoint_database_bytes'] ?? null,
            'current_reader_sources' => $replay['current_reader_sources'] ?? [],
            'next_reader_sources' => $replay['next_reader_sources'] ?? [],
            'current_reader_frame_indexes' => $replay['current_reader_frame_indexes'] ?? [],
            'next_reader_frame_indexes' => $replay['next_reader_frame_indexes'] ?? [],
            'images_match' => $replay['images_match'] ?? false,
            'cached_replay' => $cachedReplay,
            'current_replay' => $currentReplay,
            'operations' => $operations,
            'payloads' => $currentReplay['payloads'],
            'dependencies' => array_values(array_unique(array_merge(
                $currentReplay['dependencies'],
                [
                    'sqlite-pager-wal-savepoint-master-journal-current-source-next126',
                    'sqlite-master-journal-current-source-recheck-before-wal-savepoint',
                    'sqlite-stale-master-journal-cache-rejected-before-wal-replay',
                ]
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
