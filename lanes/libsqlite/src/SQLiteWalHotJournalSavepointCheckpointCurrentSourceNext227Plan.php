<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext227Plan
{
    /**
     * @param array<string,mixed> $finalizationPlan
     * @param list<array<string,mixed>> $publishReceipts
     * @return array<string,mixed>
     */
    public static function plan(array $finalizationPlan, array $publishReceipts): array
    {
        self::assertFinalizationPlan($finalizationPlan);
        if ($publishReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next227 requires publish receipts');
        }

        $token = $finalizationPlan['current_source_token'];
        $expectedTokenId = (string) $token['id'];
        $expectedEpoch = (int) $token['epoch'];
        $expectedCheckpointFrame = (int) $finalizationPlan['checkpoint_frame'];
        $expectedCheckpointCookie = (int) $finalizationPlan['checkpoint_cookie'];
        $expectedSchemaCookie = (int) $finalizationPlan['schema_cookie'];
        $expectedNextEpoch = (int) $finalizationPlan['next_source_epoch'];
        $finalizedNames = self::stringList($finalizationPlan['finalized_scope_names'], 'finalized scope names');
        $scopeRows = self::scopeRowsByName($finalizationPlan['scope_rows']);

        $receiptRows = [];
        foreach ($publishReceipts as $receipt) {
            $receiptRows[] = self::receiptRow(
                $receipt,
                $scopeRows,
                $expectedTokenId,
                $expectedEpoch,
                $expectedCheckpointFrame,
                $expectedCheckpointCookie,
                $expectedSchemaCookie,
                $expectedNextEpoch
            );
        }

        $receiptNames = array_values(array_unique(array_column($receiptRows, 'scope_name')));
        $missingScopes = array_values(array_diff($finalizedNames, $receiptNames));
        $extraScopes = array_values(array_diff($receiptNames, $finalizedNames));
        $blockedRows = array_values(array_filter($receiptRows, static fn (array $row): bool => !$row['publishable']));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($missingScopes !== []) {
            $blockedReasons[] = 'finalized_scope_publish_receipt_missing';
        }
        if ($extraScopes !== []) {
            $blockedReasons[] = 'publish_receipt_for_unfinalized_scope';
        }
        $duplicateScopes = self::duplicateValues(array_column($receiptRows, 'scope_name'));
        if ($duplicateScopes !== []) {
            $blockedReasons[] = 'duplicate_scope_publish_receipt';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next219_finalization_admitted' => ($finalizationPlan['status'] ?? null) === 'wal-hot-journal-savepoint-checkpoint-current-source-next219'
                && ($finalizationPlan['checkpoint_next_source_published'] ?? false) === true,
            'all_finalized_scopes_have_receipts' => $missingScopes === [],
            'no_unfinalized_scope_receipts' => $extraScopes === [],
            'no_duplicate_scope_receipts' => $duplicateScopes === [],
            'all_receipts_publishable' => $blockedRows === [],
            'next_source_epoch_advances_once' => self::allNextEpochsMatch($receiptRows, $expectedNextEpoch),
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $publishable = $blockedGuards === [];

        return [
            'status' => $publishable
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next227'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next227',
            'reason' => $publishable
                ? 'publish_receipts_seal_hot_journal_savepoint_checkpoint_next_source'
                : 'publish_receipts_hold_hot_journal_savepoint_checkpoint_current_source',
            'database_path' => (string) $finalizationPlan['database_path'],
            'wal_path' => (string) $finalizationPlan['wal_path'],
            'journal_path' => (string) $finalizationPlan['journal_path'],
            'current_source_token' => $token,
            'checkpoint_frame' => $expectedCheckpointFrame,
            'checkpoint_cookie' => $expectedCheckpointCookie,
            'schema_cookie' => $expectedSchemaCookie,
            'next_source_epoch' => $expectedNextEpoch,
            'receipt_rows' => $receiptRows,
            'publishable_scope_names' => array_values(array_column(array_filter($receiptRows, static fn (array $row): bool => $row['publishable']), 'scope_name')),
            'blocked_scope_names' => array_values(array_unique(array_column($blockedRows, 'scope_name'))),
            'missing_scope_names' => $missingScopes,
            'extra_scope_names' => $extraScopes,
            'duplicate_scope_names' => $duplicateScopes,
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'checkpoint_publish_allowed' => $publishable,
            'publish_digest' => hash('sha256', json_encode([$token, $receiptRows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($finalizationPlan['operation_names'] ?? null) ? $finalizationPlan['operation_names'] : [],
                [
                    'seal_hot_journal_delete_receipt_current_source_next227',
                    'verify_savepoint_page_digest_receipts_current_source_next227',
                    $publishable ? 'publish_checkpoint_next_source_receipt_next227' : 'hold_checkpoint_current_source_receipt_next227',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($finalizationPlan['dependencies'] ?? null) ? $finalizationPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next227',
                    'sqlite-hot-journal-savepoint-publish-receipt-seal',
                    'wordpress-import-hot-journal-checkpoint-publish-receipts',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next219 finalized savepoint scopes plus per-scope hot-journal delete receipts and page digest seals',
            'non_overlap' => 'next227 validates publish receipts after next219 scope finalization; it does not repeat next211 reader acknowledgements, next219 finalization, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function assertFinalizationPlan(array $plan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch', 'finalized_scope_names', 'scope_rows'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 missing finalization {$key}");
            }
        }
        if (!is_array($plan['current_source_token']) || (string) ($plan['current_source_token']['id'] ?? '') === '' || (int) ($plan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next227 token is invalid');
        }
        foreach (['checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch'] as $key) {
            if (!is_int($plan[$key]) || $plan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 {$key} must be positive");
            }
        }
        if ((int) $plan['next_source_epoch'] <= (int) $plan['current_source_token']['epoch']) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next227 next source epoch must advance');
        }
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringList($values, string $label): array
    {
        if (!is_array($values)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 {$label} must be a list");
        }
        $list = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 {$label} must contain non-empty strings");
            }
            $list[] = $value;
        }

        return $list;
    }

    /**
     * @param mixed $rows
     * @return array<string,array<string,mixed>>
     */
    private static function scopeRowsByName($rows): array
    {
        if (!is_array($rows) || $rows === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next227 requires scope rows');
        }
        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null) || $row['name'] === '') {
                throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next227 scope rows are malformed');
            }
            $indexed[$row['name']] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string,mixed> $receipt
     * @param array<string,array<string,mixed>> $scopeRows
     * @return array<string,mixed>
     */
    private static function receiptRow(
        array $receipt,
        array $scopeRows,
        string $expectedTokenId,
        int $expectedEpoch,
        int $expectedCheckpointFrame,
        int $expectedCheckpointCookie,
        int $expectedSchemaCookie,
        int $expectedNextEpoch
    ): array {
        foreach (['scope_name', 'source_token_id', 'source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'journal_delete_receipt', 'page_digests', 'next_source_epoch'] as $key) {
            if (!array_key_exists($key, $receipt)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 missing receipt {$key}");
            }
        }
        $scopeName = (string) $receipt['scope_name'];
        if ($scopeName === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next227 receipt scope is required');
        }
        foreach (['source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch'] as $key) {
            if (!is_int($receipt[$key]) || $receipt[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 {$scopeName} {$key} must be positive");
            }
        }
        $receiptDigests = self::pageDigestMap($receipt['page_digests'], $scopeName);
        $scope = $scopeRows[$scopeName] ?? null;
        $scopeDigests = is_array($scope) ? self::pageDigestMapFromScope($scope, $scopeName) : [];

        $reasons = [];
        if (!is_array($scope)) {
            $reasons[] = 'publish_scope_missing_from_finalization';
        } elseif (($scope['finalized'] ?? false) !== true) {
            $reasons[] = 'publish_scope_not_finalized';
        }
        if ((string) $receipt['source_token_id'] !== $expectedTokenId) {
            $reasons[] = 'publish_source_token_mismatch';
        }
        if ((int) $receipt['source_epoch'] !== $expectedEpoch) {
            $reasons[] = 'publish_source_epoch_mismatch';
        }
        if ((int) $receipt['checkpoint_frame'] !== $expectedCheckpointFrame) {
            $reasons[] = 'publish_checkpoint_frame_mismatch';
        }
        if ((int) $receipt['checkpoint_cookie'] !== $expectedCheckpointCookie) {
            $reasons[] = 'publish_checkpoint_cookie_mismatch';
        }
        if ((int) $receipt['schema_cookie'] !== $expectedSchemaCookie) {
            $reasons[] = 'publish_schema_cookie_mismatch';
        }
        if (($receipt['journal_delete_receipt'] ?? false) !== true) {
            $reasons[] = 'publish_hot_journal_delete_receipt_missing';
        }
        if ((int) $receipt['next_source_epoch'] !== $expectedNextEpoch) {
            $reasons[] = 'publish_next_source_epoch_mismatch';
        }
        if (is_array($scope) && count($receiptDigests) !== (int) ($scope['page_digest_count'] ?? -1)) {
            $reasons[] = 'publish_page_digest_count_mismatch';
        }
        if (is_array($scope) && array_keys($receiptDigests) !== array_map('intval', $scope['page_numbers'] ?? [])) {
            $reasons[] = 'publish_page_number_mismatch';
        }
        if (is_array($scope) && $scopeDigests !== [] && $receiptDigests !== $scopeDigests) {
            $reasons[] = 'publish_page_digest_mismatch';
        }

        $publishable = $reasons === [];

        return [
            'scope_name' => $scopeName,
            'source_token_id' => (string) $receipt['source_token_id'],
            'source_epoch' => (int) $receipt['source_epoch'],
            'checkpoint_frame' => (int) $receipt['checkpoint_frame'],
            'checkpoint_cookie' => (int) $receipt['checkpoint_cookie'],
            'schema_cookie' => (int) $receipt['schema_cookie'],
            'journal_delete_receipt' => (bool) $receipt['journal_delete_receipt'],
            'next_source_epoch' => (int) $receipt['next_source_epoch'],
            'page_numbers' => array_keys($receiptDigests),
            'page_digest_count' => count($receiptDigests),
            'publishable' => $publishable,
            'blocked_reasons' => $reasons,
            'receipt_reason' => $publishable ? 'publish_receipt_matches_finalized_scope' : $reasons[0],
            'receipt_digest' => hash('sha256', json_encode([$scopeName, $receiptDigests, $receipt], JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param mixed $values
     * @return array<int,string>
     */
    private static function pageDigestMap($values, string $scopeName): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 {$scopeName} requires page digests");
        }
        $normalized = [];
        foreach ($values as $page => $digest) {
            $pageNumber = (int) $page;
            if ($pageNumber <= 0 || !is_string($digest) || !preg_match('/^[a-f0-9]{64}$/', $digest)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 {$scopeName} page digests must map positive pages to sha256 strings");
            }
            $normalized[$pageNumber] = $digest;
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,string>
     */
    private static function pageDigestMapFromScope(array $scope, string $scopeName): array
    {
        $pageNumbers = $scope['page_numbers'] ?? null;
        $digestCount = $scope['page_digest_count'] ?? null;
        $pageDigests = $scope['page_digests'] ?? null;
        if (!is_array($pageNumbers) || !is_int($digestCount) || !is_array($pageDigests)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 {$scopeName} finalized scope page metadata is malformed");
        }
        $numbers = array_map('intval', $pageNumbers);
        sort($numbers);
        if (count($numbers) !== $digestCount) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next227 {$scopeName} finalized scope digest count is malformed");
        }

        return self::pageDigestMap($pageDigests, $scopeName);
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
            $key = (string) $value;
            if (isset($seen[$key])) {
                $duplicates[$key] = true;
                continue;
            }
            $seen[$key] = true;
        }

        return array_keys($duplicates);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allNextEpochsMatch(array $rows, int $expectedNextEpoch): bool
    {
        foreach ($rows as $row) {
            if (($row['next_source_epoch'] ?? null) !== $expectedNextEpoch) {
                return false;
            }
        }

        return $rows !== [];
    }
}
