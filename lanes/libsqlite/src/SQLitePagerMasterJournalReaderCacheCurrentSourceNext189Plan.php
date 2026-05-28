<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext189Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_path?:string,member_journal_digest?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_path?:string,member_journal_digest?:string}> $nextReads
     * @param array<string,string> $currentMemberJournalDigests
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $recoveredPages,
        array $readerCache,
        array $nextReads,
        string $currentSourceId,
        int $currentEpoch,
        int $currentPublicationGeneration,
        string $currentMasterSourceDigest,
        int $currentRecoverySequence,
        array $currentMemberJournalDigests,
    ): array {
        $members = self::members($currentMasterJournalBytes);
        $memberDigests = self::normalizeMemberDigests($currentMemberJournalDigests, $members);
        $cacheFence = self::normalizeMemberCache($readerCache);
        $readFence = self::normalizeMemberReads($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext186Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripMemberFence($readerCache),
            array_map(static fn (array $read): array => [
                'reader_id' => $read['reader_id'],
                'page_number' => $read['page_number'],
                'source_id' => $read['source_id'],
                'epoch' => $read['epoch'],
                'format_signature' => $read['format_signature'],
                'publication_generation' => $read['publication_generation'],
                'master_source_digest' => $read['master_source_digest'],
                'recovery_sequence' => $read['recovery_sequence'],
                'recovered_page_set_digest' => $read['recovered_page_set_digest'],
            ], $readFence),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
        );

        $memberInvalidated = [];
        $memberRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $fence = $cacheFence[$pageNumber];
            $currentDigest = $memberDigests[$fence['member_journal_path']] ?? null;
            $reason = null;
            if ($currentDigest === null) {
                $reason = 'reader_cache_member_journal_not_in_current_master_source';
            } elseif (!hash_equals($fence['member_journal_digest'], $currentDigest)) {
                $reason = 'reader_cache_member_journal_digest_predates_current_source';
            }

            if ((bool) ($row['recovery_admitted'] ?? false) && $reason !== null) {
                $memberInvalidated[] = $pageNumber;
            }

            $memberRows[] = $row + [
                'member_journal_admitted' => (bool) ($row['recovery_admitted'] ?? false) && $reason === null,
                'member_journal_reason' => (bool) ($row['recovery_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_member_journal_digest_matches_current_source')
                    : ($row['recovery_reason'] ?? $row['reason']),
                'cache_member_journal_path' => $fence['member_journal_path'],
                'cache_member_journal_digest' => $fence['member_journal_digest'],
                'current_member_journal_digest' => $currentDigest,
                'member_journal_digest_matches' => $currentDigest !== null && hash_equals($fence['member_journal_digest'], $currentDigest),
            ];
        }

        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $memberInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $memberInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $memberInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $readById = [];
        foreach ($readFence as $read) {
            $readById[$read['reader_id']] = $read;
        }

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $ticket = $readById[$read['reader_id']];
            $currentDigest = $memberDigests[$ticket['member_journal_path']] ?? null;
            $memberCurrent = $currentDigest !== null && hash_equals($ticket['member_journal_digest'], $currentDigest);
            $pageInvalidated = in_array($read['page_number'], $memberInvalidated, true);
            $read['member_journal_current'] = $memberCurrent;
            $read['member_journal_path'] = $ticket['member_journal_path'];
            $read['member_journal_digest'] = $currentDigest;
            if (!$memberCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-member-journal-fence-current-source-next189';
                $read['member_journal_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_member_journal_digest_change'
                    : 'reader_ticket_member_journal_digest_predates_current_source';
                $reopenReaders[$read['reader_id']] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_member_journal_after_master_current_source_next189',
                    'page_number' => $read['page_number'],
                    'reader_id' => $read['reader_id'],
                    'member_journal_path' => $ticket['member_journal_path'],
                    'reason' => $read['member_journal_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next189';
        $base['reason'] = 'master_journal_reader_cache_member_journal_digest_fences_current_source_reuse';
        $base['current_member_journal_digests'] = $memberDigests;
        $base['reader_rows'] = $memberRows;
        $base['member_journal_invalidated_cache_page_numbers'] = $memberInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . self::digestMap($memberDigests) . '|' . implode(',', $memberInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next189';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-member-journal-digest-fence';
        $base['non_overlap'] = 'next189 fences reader-cache reuse on per-member rollback-journal digests from the current master-journal source; it does not repeat next186 recovered page-set sequence, next183 publication generation, next180 format ticket checks, or finite rollback truncation.';

        return $base;
    }

    /**
     * @return list<string>
     */
    private static function members(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/\r?\n/', trim($bytes)) ?: [] as $member) {
            $member = trim($member);
            if ($member !== '' && !in_array($member, $members, true)) {
                $members[] = $member;
            }
        }

        return $members;
    }

    /**
     * @param array<string,string> $digests
     * @param list<string> $members
     * @return array<string,string>
     */
    private static function normalizeMemberDigests(array $digests, array $members): array
    {
        if ($digests === [] || $members === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next189 requires current member journal digests');
        }
        $normalized = [];
        foreach ($digests as $path => $digest) {
            if (!is_string($path) || $path === '' || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next189 member digest map requires non-empty paths and digests');
            }
            if (in_array($path, $members, true)) {
                $normalized[$path] = $digest;
            }
        }
        foreach ($members as $member) {
            if (!isset($normalized[$member])) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next189 member digest map must cover every master-journal member');
            }
        }
        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array{member_journal_path:string,member_journal_digest:string}>
     */
    private static function normalizeMemberCache(array $readerCache): array
    {
        $normalized = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next189 cache page numbers must be one-based integers');
            }
            $path = $entry['member_journal_path'] ?? '';
            $digest = $entry['member_journal_digest'] ?? '';
            if (!is_string($path) || $path === '' || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next189 cache entries require member journal path and digest');
            }
            $normalized[$pageNumber] = [
                'member_journal_path' => $path,
                'member_journal_digest' => $digest,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,format_signature:string,publication_generation:int,master_source_digest:string,recovery_sequence:int,recovered_page_set_digest:string,member_journal_path:string,member_journal_digest:string}>
     */
    private static function normalizeMemberReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $path = $read['member_journal_path'] ?? '';
            $digest = $read['member_journal_digest'] ?? '';
            if (!is_string($path) || $path === '' || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next189 reads require member journal path and digest');
            }
            $normalized[] = [
                'reader_id' => (string) ($read['reader_id'] ?? ''),
                'page_number' => $read['page_number'] ?? 0,
                'source_id' => (string) ($read['source_id'] ?? ''),
                'epoch' => $read['epoch'] ?? 0,
                'format_signature' => (string) ($read['format_signature'] ?? ''),
                'publication_generation' => $read['publication_generation'] ?? 0,
                'master_source_digest' => (string) ($read['master_source_digest'] ?? ''),
                'recovery_sequence' => $read['recovery_sequence'] ?? 0,
                'recovered_page_set_digest' => (string) ($read['recovered_page_set_digest'] ?? ''),
                'member_journal_path' => $path,
                'member_journal_digest' => $digest,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array<string,mixed>>
     */
    private static function stripMemberFence(array $readerCache): array
    {
        $stripped = [];
        foreach ($readerCache as $pageNumber => $entry) {
            unset($entry['member_journal_path'], $entry['member_journal_digest']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /**
     * @param array<string,string> $digests
     */
    private static function digestMap(array $digests): string
    {
        $parts = [];
        foreach ($digests as $path => $digest) {
            $parts[] = $path . '=' . $digest;
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param list<int> $values
     * @return list<int>
     */
    private static function sortedUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /**
     * @param list<string> $readerIds
     * @return list<string>
     */
    private static function sortReaderIds(array $readerIds): array
    {
        usort($readerIds, static function (string $left, string $right): int {
            $leftNumber = preg_match('/(\d+)$/', $left, $leftMatch) === 1 ? (int) $leftMatch[1] : null;
            $rightNumber = preg_match('/(\d+)$/', $right, $rightMatch) === 1 ? (int) $rightMatch[1] : null;
            if ($leftNumber !== null && $rightNumber !== null && $leftNumber !== $rightNumber) {
                return $leftNumber <=> $rightNumber;
            }

            return $left <=> $right;
        });

        return $readerIds;
    }
}
