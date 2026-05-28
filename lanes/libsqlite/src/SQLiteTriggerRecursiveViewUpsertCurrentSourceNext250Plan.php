<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext250Plan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext247Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_statement_sequence_next247'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next247'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next247'] ?? [], 'attempted next source rows');
        $rowidToken = self::token((string) ($options['current_source_rowid_provenance_token_next250'] ?? 'wp.current.source.rowid.provenance.250'), 'rowid provenance token');
        $expectedToken = self::token((string) ($options['expected_current_source_rowid_provenance_token_next250'] ?? $rowidToken), 'expected rowid provenance token');
        $rowidColumn = self::column((string) ($options['rowid_column_next250'] ?? 'option_id'), 'rowid column');
        $conflictKey = self::column((string) ($options['conflict_key_column_next250'] ?? 'option_name'), 'conflict key column');
        $requireExisting = (bool) ($options['require_existing_rowid_for_update_next250'] ?? false);
        $tokenMatches = hash_equals($rowidToken, $expectedToken);
        $oldRows = self::oldRows($baseRows, $conflictKey);
        $provenance = self::rowidProvenance($currentRows, $oldRows, $rowidColumn, $conflictKey);
        $required = self::rowidReceipts($provenance, $rowidToken);
        $acknowledged = self::acknowledgedReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $missingExisting = $requireExisting
            ? array_values(array_filter($provenance, static fn (array $row): bool => $row['old_rowid'] === null))
            : [];
        $rowidsComplete = $required !== []
            && $tokenMatches
            && $missing === []
            && $unexpected === []
            && $missingExisting === [];
        $nextVisible = $baseVisible && $rowidsComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next247'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireExisting,
            $missingExisting,
        );

        $taggedCurrent = self::tagRows($currentRows, 'current-rowid-provenance', true, $required, $rowidToken, $rowidColumn, $conflictKey, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], $rowidToken, $rowidColumn, $conflictKey, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next250' => self::status($nextVisible, $baseVisible, $tokenMatches, $missing, $unexpected, $requireExisting, $missingExisting),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next250' => $baseVisible,
            'current_source_rowid_provenance_token_next250' => $rowidToken,
            'expected_current_source_rowid_provenance_token_next250' => $expectedToken,
            'current_source_rowid_provenance_token_matches_next250' => $tokenMatches,
            'rowid_column_next250' => $rowidColumn,
            'conflict_key_column_next250' => $conflictKey,
            'require_existing_rowid_for_update_next250' => $requireExisting,
            'current_source_rowid_provenance_next250' => $provenance,
            'required_current_source_rowid_receipts_next250' => $required,
            'acknowledged_current_source_rowid_receipts_next250' => $acknowledged,
            'missing_current_source_rowid_receipts_next250' => $missing,
            'unexpected_current_source_rowid_receipts_next250' => $unexpected,
            'missing_existing_rowid_provenance_next250' => $missingExisting,
            'current_source_rowid_provenance_complete_next250' => $rowidsComplete,
            'next_source_visible_after_current_source_rowid_provenance_next250' => $nextVisible,
            'current_source_rows_next250' => $taggedCurrent,
            'attempted_next_source_rows_next250' => $taggedNext,
            'visible_returning_rows_next250' => $visibleRows,
            'held_next_source_rows_next250' => $heldRows,
            'visible_returning_payloads_next250' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next250' => array_column($heldRows, 'returning'),
            'current_source_row_count_next250' => count($taggedCurrent),
            'attempted_next_source_row_count_next250' => count($taggedNext),
            'visible_row_count_next250' => count($visibleRows),
            'held_next_row_count_next250' => count($heldRows),
            'blocked_reasons_next250' => $blockedReasons,
            'current_source_rowid_provenance_plan_next250' => [
                'base_next_source_visible' => $baseVisible,
                'rowid_token_matches' => $tokenMatches,
                'rowid_column' => $rowidColumn,
                'conflict_key_column' => $conflictKey,
                'require_existing_rowid_for_update' => $requireExisting,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'missing_existing_rowid_provenance' => $missingExisting,
                'rowid_provenance_complete' => $rowidsComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-rowids'
                    : 'hold-next-source-until-current-recursive-view-upsert-rowids',
            ],
            'yield_boundary_next250' => $nextVisible
                ? 'recursive-view-upsert-next250-current-rowids-then-next'
                : 'recursive-view-upsert-next250-current-rowids-fence-next',
            'dependency_closure_next250' => 'no-new-support-component-reuses-native-recursive-view-upsert-sequence-and-adds-current-rowid-provenance-receipts',
            'dependencies_next250' => array_values(array_unique(array_merge($base['dependencies_next247'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next250',
                'sqlite-instead-of-view-trigger-upsert-rowid-provenance',
                'wordpress-recursive-view-upsert-current-source-next250',
            ]))),
            'non_overlap_next250' => 'adds current-source UPSERT rowid-provenance receipt fencing after accepted next247 statement sequence fencing; avoids next246 conflict images, next247 sequence receipts, commit watermark/source-cookie surfaces, trigger RETURNING, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array<string,mixed>> $oldRows
     * @return list<array<string,mixed>>
     */
    private static function rowidProvenance(array $rows, array $oldRows, string $rowidColumn, string $conflictKey): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $key = self::scalarKey($returning['name'] ?? $returning[$conflictKey] ?? null);
            $old = $oldRows[$key] ?? null;
            $out[] = [
                'ordinal' => $index,
                'conflict_key' => $key,
                'old_rowid' => $old[$rowidColumn] ?? null,
                'new_rowid' => $returning[$rowidColumn] ?? $returning['import_id'] ?? null,
                'returning_name' => $returning['name'] ?? null,
                'returning_value' => $returning['value'] ?? null,
                'statement_sequence_receipt' => $row['current_source_sequence_receipt_next247'] ?? null,
                'upsert_rowid_action' => $old === null ? 'insert-rowid' : 'update-existing-rowid',
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $provenance
     * @return list<string>
     */
    private static function rowidReceipts(array $provenance, string $token): array
    {
        $receipts = [];
        foreach ($provenance as $row) {
            $receipts[] = substr(hash('sha256', json_encode([$token, $row], JSON_THROW_ON_ERROR)), 0, 50);
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function oldRows(array $rows, string $keyColumn): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($keyColumn, $row)) {
                continue;
            }
            $out[self::scalarKey($row[$keyColumn])] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_rowid_provenance_next250'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_rowid_receipts_next250'] ?? [], 'acknowledged rowid receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{50}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} contain a malformed rowid receipt");
            }
        }

        return array_values(array_unique($values));
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} is malformed");
        }

        return $value;
    }

    private static function column(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $token, string $rowidColumn, string $conflictKey, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'rowid_provenance_phase_next250' => $phase,
                'current_source_rowid_provenance_token_next250' => $token,
                'rowid_column_next250' => $rowidColumn,
                'conflict_key_column_next250' => $conflictKey,
                'current_source_rowid_receipt_next250' => $receipts[$index] ?? null,
                'visible_after_current_source_rowid_provenance_next250' => $visible,
                'held_by_current_source_rowid_provenance_reasons_next250' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @param list<array<string,mixed>> $missingExisting
     * @return list<string>
     */
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireExisting, array $missingExisting): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next250 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next247-statement-sequence-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-rowid-provenance-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-rowid-provenance-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-rowid-provenance-unexpected';
        }
        if ($requireExisting && $missingExisting !== []) {
            $reasons[] = 'current-source-rowid-provenance-existing-rowid-missing';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @param list<array<string,mixed>> $missingExisting
     */
    private static function status(bool $nextVisible, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireExisting, array $missingExisting): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-provenance-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next250-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-token-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-unexpected-held';
        }
        if ($requireExisting && $missingExisting !== []) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-existing-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next250-held';
    }

    private static function scalarKey(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => 'BOOL:' . ($value ? '1' : '0'),
            is_int($value) => 'INT:' . $value,
            is_float($value) => 'FLOAT:' . $value,
            default => 'TEXT:' . (string) $value,
        };
    }
}
