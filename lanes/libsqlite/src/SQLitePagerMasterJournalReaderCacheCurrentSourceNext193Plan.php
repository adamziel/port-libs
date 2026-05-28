<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext193Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_path?:string,member_journal_digest?:string,stable_master_read_token?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_path?:string,member_journal_digest?:string,stable_master_read_token?:string}> $nextReads
     * @param array<string,string> $currentMemberJournalDigests
     * @param list<string> $currentMasterReadDigests
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
        array $currentMasterReadDigests,
    ): array {
        $stable = self::stableRead($masterJournalPath, $currentMasterJournalBytes, $currentMasterReadDigests);
        $cacheFence = self::normalizeStableCache($readerCache);
        $readFence = self::normalizeStableReads($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext189Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripStableFence($readerCache),
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
                'member_journal_path' => $read['member_journal_path'],
                'member_journal_digest' => $read['member_journal_digest'],
            ], $readFence),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalDigests,
        );

        $stableInvalidated = [];
        $stableRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $fence = $cacheFence[$pageNumber];
            $reason = null;
            if (!hash_equals($fence['stable_master_read_token'], $stable['token'])) {
                $reason = 'reader_cache_stable_master_read_token_predates_current_source';
            }

            if ((bool) ($row['member_journal_admitted'] ?? false) && $reason !== null) {
                $stableInvalidated[] = $pageNumber;
            }

            $stableRows[] = $row + [
                'stable_master_read_admitted' => (bool) ($row['member_journal_admitted'] ?? false) && $reason === null,
                'stable_master_read_reason' => (bool) ($row['member_journal_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_stable_master_read_token_matches_current_source')
                    : ($row['member_journal_reason'] ?? $row['reason']),
                'cache_stable_master_read_token' => $fence['stable_master_read_token'],
                'current_stable_master_read_token' => $stable['token'],
                'stable_master_read_token_matches' => hash_equals($fence['stable_master_read_token'], $stable['token']),
            ];
        }

        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $stableInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $stableInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $stableInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $readById = [];
        foreach ($readFence as $read) {
            $readById[$read['reader_id']] = $read;
        }

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $ticket = $readById[$read['reader_id']];
            $stableCurrent = hash_equals($ticket['stable_master_read_token'], $stable['token']);
            $pageInvalidated = in_array($read['page_number'], $stableInvalidated, true);
            $read['stable_master_read_current'] = $stableCurrent;
            $read['stable_master_read_token'] = $stable['token'];
            if (!$stableCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-stable-read-fence-current-source-next193';
                $read['stable_master_read_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_stable_master_read_token_change'
                    : 'reader_ticket_stable_master_read_token_predates_current_source';
                $reopenReaders[$read['reader_id']] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_stable_master_read_after_current_source_next193',
                    'page_number' => $read['page_number'],
                    'reader_id' => $read['reader_id'],
                    'reason' => $read['stable_master_read_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next193';
        $base['reason'] = 'stable_repeated_master_journal_read_token_fences_reader_cache_reuse';
        $base['stable_master_read'] = $stable;
        $base['reader_rows'] = $stableRows;
        $base['stable_master_read_invalidated_cache_page_numbers'] = $stableInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $stable['token'] . '|' . implode(',', $stableInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next193';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-stable-master-journal-read-token';
        $base['non_overlap'] = 'next193 fences reader-cache reuse on a stable repeated master-journal read token; it does not repeat next189 member rollback-journal digest checks, next187 complete-read membership ordinals, next184 file read tokens, next183 publication generation, or next180 format-ticket fences.';

        return $base;
    }

    /**
     * @param list<string> $digests
     * @return array{token:string,digest:string,read_count:int,byte_digest:string}
     */
    private static function stableRead(string $path, string $bytes, array $digests): array
    {
        if ($path === '' || $bytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next193 requires path and bytes for stable read token');
        }
        if (count($digests) < 2) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next193 requires at least two master-journal read digests');
        }
        $byteDigest = hash('sha256', $bytes);
        $first = null;
        foreach ($digests as $digest) {
            if (!is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next193 read digests must be non-empty strings');
            }
            $first ??= $digest;
            if (!hash_equals($first, $digest)) {
                throw new \RuntimeException('SQLite pager master-journal reader-cache next193 master-journal read digests are not stable');
            }
        }
        if (!hash_equals($byteDigest, $first)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next193 stable read digest does not match current master-journal bytes');
        }

        return [
            'token' => 'stable-master-read:' . substr(hash('sha256', $path . '|' . $byteDigest . '|' . count($digests)), 0, 40),
            'digest' => $first,
            'read_count' => count($digests),
            'byte_digest' => $byteDigest,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array{stable_master_read_token:string}>
     */
    private static function normalizeStableCache(array $readerCache): array
    {
        $normalized = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next193 cache page numbers must be one-based integers');
            }
            $token = $entry['stable_master_read_token'] ?? '';
            if (!is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next193 cache entries require stable master read token');
            }
            $normalized[$pageNumber] = ['stable_master_read_token' => $token];
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @return list<array<string,mixed>>
     */
    private static function normalizeStableReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $token = $read['stable_master_read_token'] ?? '';
            if (!is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next193 reads require stable master read token');
            }
            $read['stable_master_read_token'] = $token;
            $read['reader_id'] = (string) ($read['reader_id'] ?? '');
            $normalized[] = $read;
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array<string,mixed>>
     */
    private static function stripStableFence(array $readerCache): array
    {
        $stripped = [];
        foreach ($readerCache as $pageNumber => $entry) {
            unset($entry['stable_master_read_token']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
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
