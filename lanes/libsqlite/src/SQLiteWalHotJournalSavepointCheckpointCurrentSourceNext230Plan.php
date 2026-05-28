<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext230Plan
{
    /**
     * @param array<string,mixed> $publishPlan
     * @param list<array<string,mixed>> $readerTickets
     * @return array<string,mixed>
     */
    public static function plan(array $publishPlan, array $readerTickets): array
    {
        self::assertPublishPlan($publishPlan);
        if ($readerTickets === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next230 requires reader tickets');
        }

        $expectedToken = $publishPlan['current_source_token'];
        $expectedTokenId = (string) $expectedToken['id'];
        $expectedEpoch = (int) $publishPlan['next_source_epoch'];
        $expectedCheckpointFrame = (int) $publishPlan['checkpoint_frame'];
        $expectedCheckpointCookie = (int) $publishPlan['checkpoint_cookie'];
        $expectedSchemaCookie = (int) $publishPlan['schema_cookie'];
        $scopeRows = self::scopeRowsByName($publishPlan['receipt_rows']);

        $ticketRows = [];
        foreach ($readerTickets as $ticket) {
            $ticketRows[] = self::ticketRow(
                $ticket,
                $scopeRows,
                $expectedTokenId,
                $expectedEpoch,
                $expectedCheckpointFrame,
                $expectedCheckpointCookie,
                $expectedSchemaCookie
            );
        }

        $ticketNames = array_column($ticketRows, 'reader_name');
        $duplicateReaders = self::duplicateValues($ticketNames);
        $staleRows = array_values(array_filter($ticketRows, static fn (array $row): bool => !$row['admitted']));
        $blockedReasons = [];
        foreach ($staleRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateReaders !== []) {
            $blockedReasons[] = 'duplicate_reopened_reader_ticket';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next227_publish_receipts_admitted' => ($publishPlan['status'] ?? null) === 'wal-hot-journal-savepoint-checkpoint-current-source-next227'
                && ($publishPlan['checkpoint_publish_allowed'] ?? false) === true,
            'all_reader_tickets_admitted' => $staleRows === [],
            'no_duplicate_reader_tickets' => $duplicateReaders === [],
            'all_readers_use_next_source_epoch' => self::allEpochsMatch($ticketRows, $expectedEpoch),
            'all_readers_hide_hot_journal_and_wal_tail' => self::allStorageFencesMatch($ticketRows),
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next230'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next230',
            'reason' => $admitted
                ? 'reopened_readers_observe_checkpoint_next_source_after_hot_journal_savepoint'
                : 'reopened_readers_still_observe_stale_hot_journal_savepoint_checkpoint_source',
            'database_path' => (string) $publishPlan['database_path'],
            'wal_path' => (string) $publishPlan['wal_path'],
            'journal_path' => (string) $publishPlan['journal_path'],
            'current_source_token' => $expectedToken,
            'reader_epoch' => $expectedEpoch,
            'checkpoint_frame' => $expectedCheckpointFrame,
            'checkpoint_cookie' => $expectedCheckpointCookie,
            'schema_cookie' => $expectedSchemaCookie,
            'ticket_rows' => $ticketRows,
            'admitted_reader_names' => array_values(array_column(array_filter($ticketRows, static fn (array $row): bool => $row['admitted']), 'reader_name')),
            'blocked_reader_names' => array_values(array_unique(array_column($staleRows, 'reader_name'))),
            'duplicate_reader_names' => $duplicateReaders,
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'can_serve_next_source_readers' => $admitted,
            'reader_ticket_digest' => hash('sha256', json_encode([$expectedToken, $ticketRows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($publishPlan['operation_names'] ?? null) ? $publishPlan['operation_names'] : [],
                [
                    'verify_reopened_reader_next_source_tickets_next230',
                    'fence_hot_journal_and_wal_tail_from_reopened_readers_next230',
                    $admitted ? 'serve_checkpoint_next_source_readers_next230' : 'hold_reopened_reader_current_source_next230',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($publishPlan['dependencies'] ?? null) ? $publishPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next230',
                    'sqlite-wal-reopened-reader-next-source-ticket-fence',
                    'wordpress-import-hot-journal-checkpoint-reader-reopen',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next227 publish receipts and adds a bounded reopened-reader ticket fence',
            'non_overlap' => 'next230 validates reopened reader tickets after next227 publish receipts; it does not repeat next226 file-state receipts, next227 publish receipt sealing, WAL byte truncation, rollback-journal commit/apply, VFS savepoint rollback, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function assertPublishPlan(array $plan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch', 'checkpoint_publish_allowed', 'receipt_rows'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next230 missing publish {$key}");
            }
        }
        if (($plan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next227' || ($plan['checkpoint_publish_allowed'] ?? false) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next230 requires an admitted next227 publish plan');
        }
        if (!is_array($plan['current_source_token']) || (string) ($plan['current_source_token']['id'] ?? '') === '' || (int) ($plan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next230 token is invalid');
        }
        foreach (['checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch'] as $key) {
            if (!is_int($plan[$key]) || $plan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next230 {$key} must be positive");
            }
        }
    }

    /**
     * @param mixed $rows
     * @return array<string,array<string,mixed>>
     */
    private static function scopeRowsByName($rows): array
    {
        if (!is_array($rows) || $rows === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next230 requires receipt rows');
        }

        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['scope_name'] ?? null) || $row['scope_name'] === '') {
                throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next230 receipt rows are malformed');
            }
            if (($row['publishable'] ?? false) !== true) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next230 requires publishable receipt rows');
            }
            $indexed[$row['scope_name']] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string,mixed> $ticket
     * @param array<string,array<string,mixed>> $scopeRows
     * @return array<string,mixed>
     */
    private static function ticketRow(
        array $ticket,
        array $scopeRows,
        string $expectedTokenId,
        int $expectedEpoch,
        int $expectedCheckpointFrame,
        int $expectedCheckpointCookie,
        int $expectedSchemaCookie
    ): array {
        foreach (['reader_name', 'scope_name', 'source_token_id', 'source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'visible_page_digests', 'hot_journal_visible', 'wal_tail_visible'] as $key) {
            if (!array_key_exists($key, $ticket)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next230 missing reader ticket {$key}");
            }
        }
        $readerName = (string) $ticket['reader_name'];
        $scopeName = (string) $ticket['scope_name'];
        if ($readerName === '' || $scopeName === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next230 reader and scope names are required');
        }
        foreach (['source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie'] as $key) {
            if (!is_int($ticket[$key]) || $ticket[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next230 {$readerName} {$key} must be positive");
            }
        }

        $scope = $scopeRows[$scopeName] ?? null;
        $ticketDigests = self::pageDigestMap($ticket['visible_page_digests'], $readerName);
        $scopeDigests = is_array($scope['page_digests'] ?? null) ? self::pageDigestMap($scope['page_digests'], $scopeName) : [];
        $reasons = [];
        if ($scope === null) {
            $reasons[] = 'reader_scope_not_published';
        }
        if (($ticket['source_token_id'] ?? null) !== $expectedTokenId) {
            $reasons[] = 'reader_source_token_mismatch';
        }
        if ((int) $ticket['source_epoch'] !== $expectedEpoch) {
            $reasons[] = 'reader_next_source_epoch_mismatch';
        }
        if ((int) $ticket['checkpoint_frame'] !== $expectedCheckpointFrame) {
            $reasons[] = 'reader_checkpoint_frame_mismatch';
        }
        if ((int) $ticket['checkpoint_cookie'] !== $expectedCheckpointCookie) {
            $reasons[] = 'reader_checkpoint_cookie_mismatch';
        }
        if ((int) $ticket['schema_cookie'] !== $expectedSchemaCookie) {
            $reasons[] = 'reader_schema_cookie_mismatch';
        }
        if (($ticket['hot_journal_visible'] ?? true) !== false) {
            $reasons[] = 'reader_hot_journal_still_visible';
        }
        if (($ticket['wal_tail_visible'] ?? true) !== false) {
            $reasons[] = 'reader_wal_tail_still_visible';
        }
        if (array_keys($ticketDigests) !== array_keys($scopeDigests)) {
            $reasons[] = 'reader_page_number_mismatch';
        }
        if ($ticketDigests !== $scopeDigests) {
            $reasons[] = 'reader_page_digest_mismatch';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'reader_name' => $readerName,
            'scope_name' => $scopeName,
            'source_token_id' => $ticket['source_token_id'],
            'source_epoch' => $ticket['source_epoch'],
            'checkpoint_frame' => $ticket['checkpoint_frame'],
            'checkpoint_cookie' => $ticket['checkpoint_cookie'],
            'schema_cookie' => $ticket['schema_cookie'],
            'page_numbers' => array_keys($ticketDigests),
            'hot_journal_visible' => ($ticket['hot_journal_visible'] ?? true) === true,
            'wal_tail_visible' => ($ticket['wal_tail_visible'] ?? true) === true,
            'admitted' => $reasons === [],
            'ticket_reason' => $reasons === [] ? 'reader_ticket_matches_published_checkpoint_source' : implode(',', $reasons),
            'blocked_reasons' => $reasons,
            'ticket_digest' => hash('sha256', json_encode([$readerName, $scopeName, $ticketDigests, $reasons], JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param mixed $digests
     * @return array<int,string>
     */
    private static function pageDigestMap($digests, string $owner): array
    {
        if (!is_array($digests) || $digests === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next230 {$owner} page digests are required");
        }
        $map = [];
        foreach ($digests as $page => $digest) {
            if (!is_int($page) || $page <= 0 || !is_string($digest) || !self::isDigest($digest)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next230 {$owner} page digests are malformed");
            }
            $map[$page] = $digest;
        }
        ksort($map);

        return $map;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allEpochsMatch(array $rows, int $expectedEpoch): bool
    {
        foreach ($rows as $row) {
            if (($row['source_epoch'] ?? null) !== $expectedEpoch) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allStorageFencesMatch(array $rows): bool
    {
        foreach ($rows as $row) {
            if (($row['hot_journal_visible'] ?? false) !== false || ($row['wal_tail_visible'] ?? false) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private static function duplicateValues(array $values): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }
            if (isset($seen[$value])) {
                $duplicates[$value] = $value;
            }
            $seen[$value] = true;
        }

        return array_values($duplicates);
    }

    private static function isDigest(string $value): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }
}
