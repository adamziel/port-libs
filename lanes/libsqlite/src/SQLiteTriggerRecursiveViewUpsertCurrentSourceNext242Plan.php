<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext242Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext239Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_next239'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next239'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next239'] ?? [], 'attempted next source rows');
        $statementEpoch = self::token((string) ($options['current_source_statement_epoch_next242'] ?? 'wp.current.source.statement.epoch.242'), 'statement epoch');
        $expectedStatementEpoch = self::token((string) ($options['expected_current_source_statement_epoch_next242'] ?? $statementEpoch), 'expected statement epoch');
        $viewProgram = self::token((string) ($options['current_source_view_program_next242'] ?? ($currentView['source'] ?? 'main@view-cookie-242-current')), 'view program');
        $triggerProgram = self::token((string) ($options['current_source_trigger_program_next242'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-242-current')), 'trigger program');
        $schemaCookie = self::token((string) ($options['current_source_schema_cookie_next242'] ?? 'main.schema.cookie.242'), 'schema cookie');
        $sqlHash = self::token((string) ($options['current_source_upsert_sql_hash_next242'] ?? 'insert-into-recursive-view-upsert-242'), 'UPSERT SQL hash');
        $requireOrder = (bool) ($options['require_current_source_statement_epoch_order_next242'] ?? true);

        $epochMatches = hash_equals($statementEpoch, $expectedStatementEpoch);
        $requiredEpochs = self::statementReceipts($currentRows, $statementEpoch, $viewProgram, $triggerProgram, $schemaCookie, $sqlHash);
        $acknowledgedEpochs = self::acknowledgedReceipts($options, $requiredEpochs);
        $missingEpochs = array_values(array_diff($requiredEpochs, $acknowledgedEpochs));
        $unexpectedEpochs = array_values(array_diff($acknowledgedEpochs, $requiredEpochs));
        $orderMatches = !$requireOrder || $requiredEpochs === $acknowledgedEpochs;
        $statementComplete = $requiredEpochs !== []
            && $epochMatches
            && $missingEpochs === []
            && $unexpectedEpochs === []
            && $orderMatches;
        $nextVisible = $baseVisible && $statementComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next239'] ?? [],
            $baseVisible,
            $epochMatches,
            $missingEpochs,
            $unexpectedEpochs,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current-statement', true, $requiredEpochs, $statementEpoch, $viewProgram, $triggerProgram, $schemaCookie, $sqlHash, []);
        $nextRows = self::tagRows($nextRows, 'next-source', $nextVisible, [], $statementEpoch, $viewProgram, $triggerProgram, $schemaCookie, $sqlHash, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($currentRows, $nextRows) : $currentRows;
        $heldRows = $nextVisible ? [] : $nextRows;

        return [
            'status_next242' => self::status($baseVisible, $epochMatches, $missingEpochs, $unexpectedEpochs, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next242' => $baseVisible,
            'current_source_statement_epoch_next242' => $statementEpoch,
            'expected_current_source_statement_epoch_next242' => $expectedStatementEpoch,
            'current_source_statement_epoch_matches_next242' => $epochMatches,
            'current_source_view_program_next242' => $viewProgram,
            'current_source_trigger_program_next242' => $triggerProgram,
            'current_source_schema_cookie_next242' => $schemaCookie,
            'current_source_upsert_sql_hash_next242' => $sqlHash,
            'required_current_source_statement_receipts_next242' => $requiredEpochs,
            'acknowledged_current_source_statement_receipts_next242' => $acknowledgedEpochs,
            'missing_current_source_statement_receipts_next242' => $missingEpochs,
            'unexpected_current_source_statement_receipts_next242' => $unexpectedEpochs,
            'require_current_source_statement_epoch_order_next242' => $requireOrder,
            'current_source_statement_epoch_order_matches_next242' => $orderMatches,
            'current_source_statement_epoch_complete_next242' => $statementComplete,
            'next_source_visible_after_current_source_statement_epoch_next242' => $nextVisible,
            'current_source_rows_next242' => $currentRows,
            'attempted_next_source_rows_next242' => $nextRows,
            'visible_returning_rows_next242' => $visibleRows,
            'held_next_source_rows_next242' => $heldRows,
            'visible_returning_payloads_next242' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next242' => array_column($heldRows, 'returning'),
            'current_source_row_count_next242' => count($currentRows),
            'attempted_next_source_row_count_next242' => count($nextRows),
            'visible_row_count_next242' => count($visibleRows),
            'held_next_row_count_next242' => count($heldRows),
            'blocked_reasons_next242' => $blockedReasons,
            'current_source_statement_epoch_plan_next242' => [
                'base_next_source_visible' => $baseVisible,
                'statement_epoch_matches' => $epochMatches,
                'view_program' => $viewProgram,
                'trigger_program' => $triggerProgram,
                'schema_cookie' => $schemaCookie,
                'sql_hash' => $sqlHash,
                'required_receipts' => $requiredEpochs,
                'acknowledged_receipts' => $acknowledgedEpochs,
                'missing_receipts' => $missingEpochs,
                'unexpected_receipts' => $unexpectedEpochs,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'statement_complete' => $statementComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-statement-epoch'
                    : 'hold-next-source-until-current-upsert-statement-epoch',
            ],
            'yield_boundary_next242' => $nextVisible
                ? 'recursive-view-upsert-next242-current-statement-epoch-then-next'
                : 'recursive-view-upsert-next242-current-statement-epoch-fence-next',
            'dependency_closure_next242' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-target-receipts-and-adds-statement-epoch-fencing',
            'dependencies_next242' => array_values(array_unique(array_merge($base['dependencies_next239'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next242',
                'sqlite-instead-of-view-upsert-current-statement-epoch',
                'wordpress-recursive-view-upsert-current-source-next242',
            ]))),
            'non_overlap_next242' => 'adds current-source statement-epoch fencing after accepted next239 UPSERT target receipts; avoids accepted next238/next239 recursive-view UPSERT yield and target receipt behavior, recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function statementReceipts(array $rows, string $epoch, string $viewProgram, string $triggerProgram, string $schemaCookie, string $sqlHash): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                $epoch,
                $viewProgram,
                $triggerProgram,
                $schemaCookie,
                $sqlHash,
                (string) ($row['current_source_upsert_target_receipt_next239'] ?? ''),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['trigger_source_alias'] ?? ''),
            ])), 0, 44);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_statement_epochs_next242'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_statement_receipts_next242'] ?? [], 'acknowledged current source statement receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{44}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} contain a malformed statement receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $epoch,
        string $viewProgram,
        string $triggerProgram,
        string $schemaCookie,
        string $sqlHash,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'statement_epoch_phase_next242' => $phase,
                'current_source_statement_epoch_next242' => $epoch,
                'current_source_view_program_next242' => $viewProgram,
                'current_source_trigger_program_next242' => $triggerProgram,
                'current_source_schema_cookie_next242' => $schemaCookie,
                'current_source_upsert_sql_hash_next242' => $sqlHash,
                'current_source_statement_receipt_next242' => $receipts[$index] ?? null,
                'visible_after_current_source_statement_epoch_next242' => $visible,
                'held_by_current_source_statement_epoch_reasons_next242' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasons(
        mixed $baseReasons,
        bool $baseVisible,
        bool $epochMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next242 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next239-current-source-upsert-targets-not-published';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-source-statement-epoch-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-statement-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-statement-receipt-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-statement-receipt-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(
        bool $baseVisible,
        bool $epochMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next242-statement-epoch-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next242-base-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-upsert-current-source-next242-statement-epoch-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next242-statement-receipts-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next242-statement-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next242-empty-held';
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} is malformed");
        }

        return $value;
    }
}
