<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext245Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_target_next245'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next245'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next245'] ?? [], 'attempted next source rows');
        $guardToken = self::token((string) ($options['current_source_upsert_where_token_next248'] ?? 'wp.current.source.upsert.where.248'), 'where token');
        $expectedGuardToken = self::token((string) ($options['expected_current_source_upsert_where_token_next248'] ?? $guardToken), 'expected where token');
        $whereColumns = self::identifierList($options['current_source_upsert_where_columns_next248'] ?? ['option_value', 'autoload'], 'where columns');
        $expectedWhereColumns = self::identifierList($options['expected_current_source_upsert_where_columns_next248'] ?? $whereColumns, 'expected where columns');
        $expectedOutcomes = self::boolList($options['expected_current_source_upsert_where_outcomes_next248'] ?? self::outcomes($currentRows), 'expected where outcomes');
        $requireOrder = (bool) ($options['require_current_source_upsert_where_order_next248'] ?? true);

        $required = self::whereReceipts($currentRows, $guardToken, $whereColumns);
        $acknowledged = self::acknowledgedReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $tokenMatches = hash_equals($guardToken, $expectedGuardToken);
        $columnsMatch = $whereColumns === $expectedWhereColumns;
        $outcomes = self::outcomes($currentRows);
        $outcomesMatch = $outcomes === $expectedOutcomes;
        $whereComplete = $required !== []
            && $tokenMatches
            && $columnsMatch
            && $outcomesMatch
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $whereComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next245'] ?? [],
            $baseVisible,
            $tokenMatches,
            $columnsMatch,
            $outcomesMatch,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRows($currentRows, 'current-upsert-where', true, $required, $outcomes, $guardToken, $whereColumns, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], [], $guardToken, $whereColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next248' => self::status($nextVisible, $baseVisible, $tokenMatches, $columnsMatch, $outcomesMatch, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next248' => $baseVisible,
            'current_source_upsert_where_token_next248' => $guardToken,
            'expected_current_source_upsert_where_token_next248' => $expectedGuardToken,
            'current_source_upsert_where_token_matches_next248' => $tokenMatches,
            'current_source_upsert_where_columns_next248' => $whereColumns,
            'expected_current_source_upsert_where_columns_next248' => $expectedWhereColumns,
            'current_source_upsert_where_columns_match_next248' => $columnsMatch,
            'current_source_upsert_where_outcomes_next248' => $outcomes,
            'expected_current_source_upsert_where_outcomes_next248' => $expectedOutcomes,
            'current_source_upsert_where_outcomes_match_next248' => $outcomesMatch,
            'required_current_source_upsert_where_receipts_next248' => $required,
            'acknowledged_current_source_upsert_where_receipts_next248' => $acknowledged,
            'missing_current_source_upsert_where_receipts_next248' => $missing,
            'unexpected_current_source_upsert_where_receipts_next248' => $unexpected,
            'require_current_source_upsert_where_order_next248' => $requireOrder,
            'current_source_upsert_where_order_matches_next248' => $orderMatches,
            'current_source_upsert_where_complete_next248' => $whereComplete,
            'next_source_visible_after_current_source_upsert_where_next248' => $nextVisible,
            'current_source_rows_next248' => $taggedCurrent,
            'attempted_next_source_rows_next248' => $taggedNext,
            'visible_returning_rows_next248' => $visibleRows,
            'held_next_source_rows_next248' => $heldRows,
            'visible_returning_payloads_next248' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next248' => array_column($heldRows, 'returning'),
            'current_source_row_count_next248' => count($taggedCurrent),
            'attempted_next_source_row_count_next248' => count($taggedNext),
            'visible_row_count_next248' => count($visibleRows),
            'held_next_row_count_next248' => count($heldRows),
            'blocked_reasons_next248' => $blockedReasons,
            'current_source_upsert_where_plan_next248' => [
                'base_next_source_visible' => $baseVisible,
                'where_token_matches' => $tokenMatches,
                'where_columns_match' => $columnsMatch,
                'where_outcomes_match' => $outcomesMatch,
                'required_where_receipts' => $required,
                'acknowledged_where_receipts' => $acknowledged,
                'missing_where_receipts' => $missing,
                'unexpected_where_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'where_complete' => $whereComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-where'
                    : 'hold-next-source-until-current-recursive-view-upsert-where',
            ],
            'yield_boundary_next248' => $nextVisible
                ? 'recursive-view-upsert-next248-current-where-then-next'
                : 'recursive-view-upsert-next248-current-where-fence-next',
            'dependency_closure_next248' => 'no-new-support-component-reuses-native-recursive-view-upsert-target-receipts-and-adds-do-update-where-guard-receipts',
            'dependencies_next248' => array_values(array_unique(array_merge($base['dependencies_next245'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next248',
                'sqlite-instead-of-view-trigger-current-source-upsert-where-receipts',
                'wordpress-recursive-view-upsert-current-source-next248',
            ]))),
            'non_overlap_next248' => 'adds current-source UPSERT DO UPDATE WHERE guard receipt admission after accepted next245 conflict-target receipts; avoids next245 target/excluded-column duplication, recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $whereColumns
     * @return list<string>
     */
    private static function whereReceipts(array $rows, string $guardToken, array $whereColumns): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                $guardToken,
                implode(',', $whereColumns),
                (string) ($row['current_source_upsert_target_receipt_next245'] ?? ''),
                (string) $index,
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? $returning['event'] ?? ''),
                self::boolText(self::rowOutcome($row)),
            ])), 0, 64);
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<bool>
     */
    private static function outcomes(array $rows): array
    {
        return array_map(static fn (array $row): bool => self::rowOutcome($row), $rows);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowOutcome(array $row): bool
    {
        $returning = $row['returning'];

        return (string) ($returning['value'] ?? '') !== ''
            && ($returning['autoload'] ?? $returning['autoload_flag'] ?? 'yes') !== 'skip';
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_where_next248'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_upsert_where_receipts_next248'] ?? [], 'acknowledged current source upsert where receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} contain a malformed where receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function identifierList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} contains an invalid identifier");
            }
            $out[] = $value;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param mixed $values
     * @return list<bool>
     */
    private static function boolList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_bool($value)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must contain booleans");
            }
        }

        return $values;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<bool> $outcomes
     * @param list<string> $whereColumns
     * @param list<string> $blockedReasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, array $outcomes, string $guardToken, array $whereColumns, array $blockedReasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_where_phase_next248' => $phase,
                'current_source_upsert_where_token_next248' => $guardToken,
                'current_source_upsert_where_columns_next248' => $whereColumns,
                'current_source_upsert_where_receipt_next248' => $receipts[$index] ?? null,
                'current_source_upsert_where_outcome_next248' => $outcomes[$index] ?? null,
                'visible_after_current_source_upsert_where_next248' => $visible,
                'held_by_current_source_upsert_where_reasons_next248' => $visible ? [] : $blockedReasons,
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasons(array $baseReasons, bool $baseVisible, bool $tokenMatches, bool $columnsMatch, bool $outcomesMatch, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        $reasons = [];
        foreach ($baseReasons as $reason) {
            if (is_string($reason) && $reason !== '') {
                $reasons[] = $reason;
            }
        }
        if (!$baseVisible) {
            $reasons[] = 'next245-target-not-visible';
        }
        if (!$tokenMatches) {
            $reasons[] = 'where-token-mismatch';
        }
        if (!$columnsMatch) {
            $reasons[] = 'where-columns-mismatch';
        }
        if (!$outcomesMatch) {
            $reasons[] = 'where-outcomes-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'where-receipts-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'where-receipts-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'where-receipts-out-of-order';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $nextVisible, bool $baseVisible, bool $tokenMatches, bool $columnsMatch, bool $outcomesMatch, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next248-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-token-held';
        }
        if (!$columnsMatch) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-columns-held';
        }
        if (!$outcomesMatch) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-outcomes-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next248-held';
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must not be empty");
        }

        return $value;
    }

    private static function boolText(bool $value): string
    {
        return $value ? '1' : '0';
    }
}
