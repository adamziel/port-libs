<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalCacheCurrentNextPlan
{
    /**
     * @param list<array{database_path?:string,journal_path?:string,current_journal_bytes?:string|null,next_journal_bytes?:string|null,current_reserved_lock?:bool,next_reserved_lock?:bool}> $journals
     * @return array{status:string,reason:string,master_journal_path:string,current:array<string,mixed>,next:array<string,mixed>,member_delta:array{added:list<string>,removed:list<string>,retained:list<string>},cache_invalidated:bool,journal_rechecks:array<string,array<string,mixed>>,operations:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentNext(
        string $masterJournalPath,
        ?string $currentMasterJournalBytes,
        ?string $nextMasterJournalBytes,
        array $journals,
    ): array {
        if ($masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal cache current-next requires a master-journal path');
        }

        $currentMembers = self::memberList($currentMasterJournalBytes);
        $nextMembers = self::memberList($nextMasterJournalBytes);
        $currentSet = array_fill_keys($currentMembers, true);
        $nextSet = array_fill_keys($nextMembers, true);
        $allJournalPaths = array_values(array_unique(array_merge($currentMembers, $nextMembers, self::journalPaths($journals))));
        sort($allJournalPaths, SORT_STRING);

        $journalInputs = self::journalInputs($journals);
        $rechecks = [];
        $operations = [];
        $hotBefore = 0;
        $hotAfter = 0;
        $cacheInvalidated = self::cacheKey($currentMasterJournalBytes, $currentMembers) !== self::cacheKey($nextMasterJournalBytes, $nextMembers);

        foreach ($allJournalPaths as $journalPath) {
            $input = $journalInputs[$journalPath] ?? [];
            $currentBytes = array_key_exists('current_journal_bytes', $input) ? $input['current_journal_bytes'] : null;
            $nextBytes = array_key_exists('next_journal_bytes', $input) ? $input['next_journal_bytes'] : $currentBytes;
            $currentMember = isset($currentSet[$journalPath]);
            $nextMember = isset($nextSet[$journalPath]);

            $currentHot = self::hotCandidate($currentBytes, (bool) ($input['current_reserved_lock'] ?? false), $currentMember);
            $nextHot = self::hotCandidate($nextBytes, (bool) ($input['next_reserved_lock'] ?? false), $nextMember);
            if ($currentHot['hot']) {
                $hotBefore++;
            }
            if ($nextHot['hot']) {
                $hotAfter++;
            }

            $action = self::recheckAction($currentMember, $nextMember, $currentHot, $nextHot);
            $rechecks[$journalPath] = [
                'journal_path' => $journalPath,
                'database_path' => self::databasePathForJournal($journalPath, $input['database_path'] ?? null),
                'current_member' => $currentMember,
                'next_member' => $nextMember,
                'current_journal_exists' => is_string($currentBytes) && $currentBytes !== '',
                'next_journal_exists' => is_string($nextBytes) && $nextBytes !== '',
                'current_hot' => $currentHot,
                'next_hot' => $nextHot,
                'cache_action' => $action,
            ];

            if ($action !== 'reuse_cached_non_hot_state') {
                $operations[] = [
                    'op' => 'recheck_hot_journal_cache',
                    'path' => $journalPath,
                    'reason' => $action,
                    'current_member' => $currentMember,
                    'next_member' => $nextMember,
                ];
            }
        }

        $removed = array_values(array_diff($currentMembers, $nextMembers));
        $added = array_values(array_diff($nextMembers, $currentMembers));
        $retained = array_values(array_intersect($currentMembers, $nextMembers));

        if ($cacheInvalidated) {
            array_unshift($operations, [
                'op' => 'invalidate_master_journal_cache',
                'path' => $masterJournalPath,
                'reason' => 'master_journal_membership_changed_between_current_and_next',
                'current_cache_key' => self::cacheKey($currentMasterJournalBytes, $currentMembers),
                'next_cache_key' => self::cacheKey($nextMasterJournalBytes, $nextMembers),
            ]);
        }

        $status = $cacheInvalidated
            ? ($nextMembers === [] ? 'master_journal_cache_cleared_current_next' : 'master_journal_cache_refreshed_current_next')
            : 'master_journal_cache_current';

        return [
            'status' => $status,
            'reason' => 'pager_hot_journal_master_cache_current_next77',
            'master_journal_path' => $masterJournalPath,
            'current' => self::cacheSnapshot($currentMasterJournalBytes, $currentMembers, $hotBefore),
            'next' => self::cacheSnapshot($nextMasterJournalBytes, $nextMembers, $hotAfter),
            'member_delta' => [
                'added' => $added,
                'removed' => $removed,
                'retained' => $retained,
            ],
            'cache_invalidated' => $cacheInvalidated,
            'journal_rechecks' => $rechecks,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-master-journal-cache-current-next77',
                'sqlite-rollback-journal-hot-candidate',
                'sqlite-super-journal-hot-recovery',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function memberList(?string $bytes): array
    {
        if ($bytes === null || trim($bytes) === '') {
            return [];
        }

        $members = [];
        foreach (preg_split('/\r?\n/', $bytes) ?: [] as $line) {
            $path = trim($line);
            if ($path !== '' && !isset($members[$path])) {
                $members[$path] = $path;
            }
        }

        return array_values($members);
    }

    /**
     * @param list<array<string,mixed>> $journals
     * @return list<string>
     */
    private static function journalPaths(array $journals): array
    {
        $paths = [];
        foreach ($journals as $index => $journal) {
            $path = isset($journal['journal_path']) ? (string) $journal['journal_path'] : '';
            if ($path === '' && isset($journal['database_path'])) {
                $path = (string) $journal['database_path'] . '-journal';
            }
            if ($path === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal cache journal {$index} requires journal_path or database_path");
            }
            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * @param list<array<string,mixed>> $journals
     * @return array<string,array<string,mixed>>
     */
    private static function journalInputs(array $journals): array
    {
        $inputs = [];
        foreach ($journals as $index => $journal) {
            $path = isset($journal['journal_path']) ? (string) $journal['journal_path'] : '';
            if ($path === '' && isset($journal['database_path'])) {
                $path = (string) $journal['database_path'] . '-journal';
            }
            if ($path === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal cache journal {$index} requires journal_path or database_path");
            }
            if (isset($inputs[$path])) {
                throw new \InvalidArgumentException("SQLite pager master-journal cache duplicate journal path: {$path}");
            }
            $inputs[$path] = $journal;
        }

        return $inputs;
    }

    /**
     * @return array<string,mixed>
     */
    private static function hotCandidate(?string $bytes, bool $reservedLock, bool $masterJournalExists): array
    {
        if ($bytes === null || $bytes === '') {
            return [
                'hot' => false,
                'reason' => 'journal_missing',
                'journal_bytes' => 0,
                'header_valid' => false,
                'page_count' => null,
                'initial_database_page_count' => null,
                'requires_super_journal' => true,
                'super_journal_exists' => $masterJournalExists,
                'database_reserved_lock' => $reservedLock,
            ];
        }

        return SQLiteRollbackJournal::hotJournalCandidate($bytes, $reservedLock, true, $masterJournalExists);
    }

    /**
     * @param array<string,mixed> $currentHot
     * @param array<string,mixed> $nextHot
     */
    private static function recheckAction(bool $currentMember, bool $nextMember, array $currentHot, array $nextHot): string
    {
        if ($currentMember && !$nextMember) {
            return $nextHot['hot'] ? 'master_removed_but_journal_still_hot' : 'clear_cached_hot_journal';
        }
        if (!$currentMember && $nextMember) {
            return $nextHot['hot'] ? 'candidate_new_hot_journal' : 'cache_new_master_member';
        }
        if ($currentHot['reason'] !== $nextHot['reason']) {
            return 'refresh_hot_journal_reason';
        }
        if ((bool) $currentHot['hot'] !== (bool) $nextHot['hot']) {
            return 'refresh_hot_journal_state';
        }

        return $nextHot['hot'] ? 'retain_cached_hot_journal' : 'reuse_cached_non_hot_state';
    }

    private static function cacheKey(?string $bytes, array $members): string
    {
        return hash('sha256', ($bytes ?? '') . "\0" . implode("\n", $members));
    }

    /**
     * @return array{exists:bool,member_count:int,members:list<string>,cache_key:string,hot_candidate_count:int}
     */
    private static function cacheSnapshot(?string $bytes, array $members, int $hotCandidateCount): array
    {
        return [
            'exists' => $bytes !== null && trim($bytes) !== '',
            'member_count' => count($members),
            'members' => $members,
            'cache_key' => self::cacheKey($bytes, $members),
            'hot_candidate_count' => $hotCandidateCount,
        ];
    }

    private static function databasePathForJournal(string $journalPath, mixed $databasePath): string
    {
        if (is_string($databasePath) && $databasePath !== '') {
            return $databasePath;
        }
        return str_ends_with($journalPath, '-journal') ? substr($journalPath, 0, -8) : $journalPath;
    }
}
