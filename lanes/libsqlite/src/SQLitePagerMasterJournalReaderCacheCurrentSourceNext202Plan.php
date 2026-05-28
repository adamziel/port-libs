<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext202Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,member_journal_header_digests?:array<string,string>,member_journal_playback_digests?:array<string,string>,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string,member_journal_header_digest?:string,member_journal_playback_digest?:string}> $nextReads
     * @param array<string,string> $currentMemberJournalTokens
     * @param array<string,string> $currentMemberJournalHeaderDigests
     * @param array<string,string> $currentMemberJournalPlaybackDigests
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
        array $currentMemberJournalTokens,
        array $currentMemberJournalHeaderDigests,
        array $currentMemberJournalPlaybackDigests,
    ): array {
        $members = self::members($currentMasterJournalBytes);
        $currentPlayback = self::normalizeMemberMap($currentMemberJournalPlaybackDigests, $members, 'playback digest');
        $currentPlaybackDigest = self::memberMapDigest($currentPlayback);
        $cachePlayback = self::normalizeCachePlayback($readerCache, $members);
        $reads = self::normalizeReads($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext196Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripPlaybackDigests($readerCache),
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
                'member_journal_token_digest' => $read['member_journal_token_digest'],
                'member_journal_header_digest' => $read['member_journal_header_digest'],
            ], $reads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
            $currentMemberJournalHeaderDigests,
        );

        $playbackInvalidated = [];
        $playbackRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $entryPlayback = $cachePlayback[$pageNumber];
            $mismatchedMembers = [];
            foreach ($currentPlayback as $member => $digest) {
                if (($entryPlayback[$member] ?? null) !== $digest) {
                    $mismatchedMembers[] = $member;
                }
            }

            $reason = $mismatchedMembers === [] ? null : 'reader_cache_attached_member_journal_playback_changed';
            if ((bool) ($row['member_header_admitted'] ?? false) && $reason !== null) {
                $playbackInvalidated[] = $pageNumber;
            }

            $playbackRows[] = $row + [
                'member_playback_admitted' => (bool) ($row['member_header_admitted'] ?? false) && $reason === null,
                'member_playback_reason' => (bool) ($row['member_header_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_attached_member_journal_playback_matches_current_source')
                    : ($row['member_header_reason'] ?? $row['member_token_reason'] ?? $row['recovery_reason'] ?? $row['reason']),
                'cache_member_journal_playback_digests' => $entryPlayback,
                'current_member_journal_playback_digests' => $currentPlayback,
                'cache_member_journal_playback_digest' => self::memberMapDigest($entryPlayback),
                'current_member_journal_playback_digest' => $currentPlaybackDigest,
                'mismatched_member_journal_playbacks' => $mismatchedMembers,
            ];
        }

        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $playbackInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $playbackInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $playbackInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $readById = [];
        foreach ($reads as $read) {
            $readById[$read['reader_id']] = $read;
        }

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $ticket = $readById[$read['reader_id']];
            $ticketCurrent = $ticket['member_journal_playback_digest'] === $currentPlaybackDigest;
            $pageInvalidated = in_array($read['page_number'], $playbackInvalidated, true);
            $read['member_journal_playback_current'] = $ticketCurrent;
            $read['member_journal_playback_digest'] = $currentPlaybackDigest;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-member-playback-fence-current-source-next202';
                $read['member_playback_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_attached_member_journal_playback_change'
                    : 'reader_ticket_attached_member_journal_playback_predates_current_source';
                $reopenReaders[$read['reader_id']] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_attached_member_playback_after_master_current_source_next202',
                    'page_number' => $read['page_number'],
                    'reader_id' => $read['reader_id'],
                    'reason' => $read['member_playback_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next202';
        $base['reason'] = 'master_journal_reader_cache_rechecks_attached_member_journal_playback_before_current_source_reuse';
        $base['current_member_journal_playback_digests'] = $currentPlayback;
        $base['current_member_journal_playback_digest'] = $currentPlaybackDigest;
        $base['reader_rows'] = $playbackRows;
        $base['member_playback_invalidated_cache_page_numbers'] = $playbackInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentPlaybackDigest . '|' . implode(',', $playbackInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next202';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-attached-member-journal-playback-fence';
        $base['non_overlap'] = 'next202 fences reader-cache reuse on attached rollback-journal playback/body digests when member tokens and headers still match; it does not repeat next196 header fencing, next192 token fencing, next191 delete/sync fencing, or accepted super-journal commit/apply paths.';

        return $base;
    }

    /** @return list<string> */
    private static function members(string $bytes): array
    {
        $members = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $bytes) ?: []), static fn (string $member): bool => $member !== ''));
        if ($members === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next202 requires master-journal members');
        }

        return $members;
    }

    /**
     * @param array<string,string> $values
     * @param list<string> $members
     * @return array<string,string>
     */
    private static function normalizeMemberMap(array $values, array $members, string $label): array
    {
        $normalized = [];
        foreach ($members as $member) {
            $value = $values[$member] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next202 requires {$label} for member {$member}");
            }
            $normalized[$member] = $value;
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @param list<string> $members
     * @return array<int,array<string,string>>
     */
    private static function normalizeCachePlayback(array $readerCache, array $members): array
    {
        $normalized = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next202 cache page numbers must be one-based integers');
            }
            $playback = $entry['member_journal_playback_digests'] ?? null;
            if (!is_array($playback)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next202 cache entries require member journal playback digests');
            }
            $normalized[$pageNumber] = self::normalizeMemberMap($playback, $members, 'cache playback digest');
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,format_signature:string,publication_generation:int,master_source_digest:string,recovery_sequence:int,recovered_page_set_digest:string,member_journal_token_digest:string,member_journal_header_digest:string,member_journal_playback_digest:string}>
     */
    private static function normalizeReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $tokenDigest = $read['member_journal_token_digest'] ?? '';
            $headerDigest = $read['member_journal_header_digest'] ?? '';
            $playbackDigest = $read['member_journal_playback_digest'] ?? '';
            if (!is_string($tokenDigest) || $tokenDigest === '' || !is_string($headerDigest) || $headerDigest === '' || !is_string($playbackDigest) || $playbackDigest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next202 reads require member journal token, header, and playback digests');
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
                'member_journal_token_digest' => $tokenDigest,
                'member_journal_header_digest' => $headerDigest,
                'member_journal_playback_digest' => $playbackDigest,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array<string,mixed>>
     */
    private static function stripPlaybackDigests(array $readerCache): array
    {
        $stripped = [];
        foreach ($readerCache as $pageNumber => $entry) {
            unset($entry['member_journal_playback_digests']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,string> $values */
    private static function memberMapDigest(array $values): string
    {
        ksort($values, SORT_STRING);
        $parts = [];
        foreach ($values as $member => $value) {
            $parts[] = $member . '=' . $value;
        }

        return hash('sha256', implode('|', $parts));
    }

    /** @param list<int> $values @return list<int> */
    private static function sortedUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /** @param list<string> $readerIds @return list<string> */
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
