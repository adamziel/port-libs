<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext196Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,member_journal_header_digests?:array<string,string>,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string,member_journal_header_digest?:string}> $nextReads
     * @param array<string,string> $currentMemberJournalTokens
     * @param array<string,string> $currentMemberJournalHeaderDigests
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
    ): array {
        $members = self::members($currentMasterJournalBytes);
        $currentHeaders = self::normalizeMemberMap($currentMemberJournalHeaderDigests, $members, 'header digest');
        $currentHeaderDigest = self::memberMapDigest($currentHeaders);
        $cacheHeaders = self::normalizeCacheHeaders($readerCache, $members);
        $reads = self::normalizeReads($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext192Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripHeaderDigests($readerCache),
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
            ], $reads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
        );

        $headerInvalidated = [];
        $headerRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $entryHeaders = $cacheHeaders[$pageNumber];
            $reason = null;
            $mismatchedMembers = [];
            foreach ($currentHeaders as $member => $digest) {
                if (($entryHeaders[$member] ?? null) !== $digest) {
                    $mismatchedMembers[] = $member;
                }
            }
            if ($mismatchedMembers !== []) {
                $reason = 'reader_cache_attached_member_journal_header_changed';
            }

            if ((bool) ($row['member_token_admitted'] ?? false) && $reason !== null) {
                $headerInvalidated[] = $pageNumber;
            }

            $headerRows[] = $row + [
                'member_header_admitted' => (bool) ($row['member_token_admitted'] ?? false) && $reason === null,
                'member_header_reason' => (bool) ($row['member_token_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_attached_member_journal_headers_match_current_source')
                    : ($row['member_token_reason'] ?? $row['recovery_reason'] ?? $row['reason']),
                'cache_member_journal_header_digests' => $entryHeaders,
                'current_member_journal_header_digests' => $currentHeaders,
                'cache_member_journal_header_digest' => self::memberMapDigest($entryHeaders),
                'current_member_journal_header_digest' => $currentHeaderDigest,
                'mismatched_member_journal_headers' => $mismatchedMembers,
            ];
        }

        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $headerInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $headerInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $headerInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $readById = [];
        foreach ($reads as $read) {
            $readById[$read['reader_id']] = $read;
        }

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $ticket = $readById[$read['reader_id']];
            $ticketCurrent = $ticket['member_journal_header_digest'] === $currentHeaderDigest;
            $pageInvalidated = in_array($read['page_number'], $headerInvalidated, true);
            $read['member_journal_header_current'] = $ticketCurrent;
            $read['member_journal_header_digest'] = $currentHeaderDigest;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-member-header-fence-current-source-next196';
                $read['member_header_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_attached_member_journal_header_change'
                    : 'reader_ticket_attached_member_journal_header_predates_current_source';
                $reopenReaders[$read['reader_id']] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_attached_member_header_after_master_current_source_next196',
                    'page_number' => $read['page_number'],
                    'reader_id' => $read['reader_id'],
                    'reason' => $read['member_header_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next196';
        $base['reason'] = 'master_journal_reader_cache_rechecks_attached_member_journal_headers_before_current_source_reuse';
        $base['current_member_journal_header_digests'] = $currentHeaders;
        $base['current_member_journal_header_digest'] = $currentHeaderDigest;
        $base['reader_rows'] = $headerRows;
        $base['member_header_invalidated_cache_page_numbers'] = $headerInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentHeaderDigest . '|' . implode(',', $headerInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next196';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-attached-member-journal-header-fence';
        $base['non_overlap'] = 'next196 fences reader-cache reuse on attached member rollback-journal header digests when file tokens still match; it does not repeat next192 member token fencing, next191 master-delete directory sync, next186 recovered-page-set sequencing, or accepted super-journal commit/apply paths.';

        return $base;
    }

    /** @return list<string> */
    private static function members(string $bytes): array
    {
        $members = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $bytes) ?: []), static fn (string $member): bool => $member !== ''));
        if ($members === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next196 requires master-journal members');
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
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next196 requires {$label} for member {$member}");
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
    private static function normalizeCacheHeaders(array $readerCache, array $members): array
    {
        $normalized = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next196 cache page numbers must be one-based integers');
            }
            $headers = $entry['member_journal_header_digests'] ?? null;
            if (!is_array($headers)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next196 cache entries require member journal header digests');
            }
            $normalized[$pageNumber] = self::normalizeMemberMap($headers, $members, 'cache header digest');
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,format_signature:string,publication_generation:int,master_source_digest:string,recovery_sequence:int,recovered_page_set_digest:string,member_journal_token_digest:string,member_journal_header_digest:string}>
     */
    private static function normalizeReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $tokenDigest = $read['member_journal_token_digest'] ?? '';
            $headerDigest = $read['member_journal_header_digest'] ?? '';
            if (!is_string($tokenDigest) || $tokenDigest === '' || !is_string($headerDigest) || $headerDigest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next196 reads require member journal token and header digests');
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
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array<string,mixed>>
     */
    private static function stripHeaderDigests(array $readerCache): array
    {
        $stripped = [];
        foreach ($readerCache as $pageNumber => $entry) {
            unset($entry['member_journal_header_digests']);
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
