<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext192Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string}> $nextReads
     * @param array<string,string> $currentMemberJournalTokens
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
    ): array {
        $members = self::members($currentMasterJournalBytes);
        $currentTokens = self::normalizeMemberJournalTokens($currentMemberJournalTokens, $members);
        $currentTokenDigest = self::memberJournalTokenDigest($currentTokens);
        $cacheTokens = self::normalizeCacheTokens($readerCache, $members);
        $reads = self::normalizeReads($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext186Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripMemberTokens($readerCache),
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
            ], $reads),
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
            $entryTokens = $cacheTokens[$pageNumber];
            $reason = null;
            $mismatchedMembers = [];
            foreach ($currentTokens as $member => $token) {
                if (($entryTokens[$member] ?? null) !== $token) {
                    $mismatchedMembers[] = $member;
                }
            }
            if ($mismatchedMembers !== []) {
                $reason = 'reader_cache_attached_member_journal_token_changed';
            }

            if ((bool) ($row['recovery_admitted'] ?? false) && $reason !== null) {
                $memberInvalidated[] = $pageNumber;
            }

            $memberRows[] = $row + [
                'member_token_admitted' => (bool) ($row['recovery_admitted'] ?? false) && $reason === null,
                'member_token_reason' => (bool) ($row['recovery_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_attached_member_journal_tokens_match_current_source')
                    : ($row['recovery_reason'] ?? $row['reason']),
                'cache_member_journal_tokens' => $entryTokens,
                'current_member_journal_tokens' => $currentTokens,
                'cache_member_journal_token_digest' => self::memberJournalTokenDigest($entryTokens),
                'current_member_journal_token_digest' => $currentTokenDigest,
                'mismatched_member_journals' => $mismatchedMembers,
            ];
        }

        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $memberInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $memberInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $memberInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $readById = [];
        foreach ($reads as $read) {
            $readById[$read['reader_id']] = $read;
        }

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $ticket = $readById[$read['reader_id']];
            $ticketCurrent = $ticket['member_journal_token_digest'] === $currentTokenDigest;
            $pageInvalidated = in_array($read['page_number'], $memberInvalidated, true);
            $read['member_journal_token_current'] = $ticketCurrent;
            $read['member_journal_token_digest'] = $currentTokenDigest;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-member-token-fence-current-source-next192';
                $read['member_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_attached_member_journal_token_change'
                    : 'reader_ticket_attached_member_journal_token_predates_current_source';
                $reopenReaders[$read['reader_id']] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_attached_member_journal_after_master_current_source_next192',
                    'page_number' => $read['page_number'],
                    'reader_id' => $read['reader_id'],
                    'reason' => $read['member_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next192';
        $base['reason'] = 'master_journal_reader_cache_rechecks_attached_member_journal_tokens_before_current_source_reuse';
        $base['current_member_journal_tokens'] = $currentTokens;
        $base['current_member_journal_token_digest'] = $currentTokenDigest;
        $base['reader_rows'] = $memberRows;
        $base['member_token_invalidated_cache_page_numbers'] = $memberInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentTokenDigest . '|' . implode(',', $memberInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next192';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-attached-member-journal-token-fence';
        $base['non_overlap'] = 'next192 fences reader-cache reuse on attached member rollback-journal tokens after master-journal recovery; it does not repeat next186 recovered-page-set sequencing, next183 publication fencing, next185 finite truncation, or accepted super-journal commit/apply paths.';

        return $base;
    }

    /**
     * @return list<string>
     */
    private static function members(string $bytes): array
    {
        $members = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $bytes) ?: []), static fn (string $member): bool => $member !== ''));
        if ($members === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next192 requires master-journal members');
        }

        return $members;
    }

    /**
     * @param array<string,string> $tokens
     * @param list<string> $members
     * @return array<string,string>
     */
    private static function normalizeMemberJournalTokens(array $tokens, array $members): array
    {
        $normalized = [];
        foreach ($members as $member) {
            $token = $tokens[$member] ?? null;
            if (!is_string($token) || $token === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next192 requires token for member {$member}");
            }
            $normalized[$member] = $token;
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @param list<string> $members
     * @return array<int,array<string,string>>
     */
    private static function normalizeCacheTokens(array $readerCache, array $members): array
    {
        $normalized = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next192 cache page numbers must be one-based integers');
            }
            $tokens = $entry['member_journal_tokens'] ?? null;
            if (!is_array($tokens)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next192 cache entries require member journal tokens');
            }
            $normalized[$pageNumber] = self::normalizeMemberJournalTokens($tokens, $members);
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,format_signature:string,publication_generation:int,master_source_digest:string,recovery_sequence:int,recovered_page_set_digest:string,member_journal_token_digest:string}>
     */
    private static function normalizeReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $digest = $read['member_journal_token_digest'] ?? '';
            if (!is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next192 reads require member journal token digest');
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
                'member_journal_token_digest' => $digest,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array<string,mixed>>
     */
    private static function stripMemberTokens(array $readerCache): array
    {
        $stripped = [];
        foreach ($readerCache as $pageNumber => $entry) {
            unset($entry['member_journal_tokens']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /**
     * @param array<string,string> $tokens
     */
    private static function memberJournalTokenDigest(array $tokens): string
    {
        ksort($tokens, SORT_STRING);
        $parts = [];
        foreach ($tokens as $member => $token) {
            $parts[] = $member . '=' . $token;
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
