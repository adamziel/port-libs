<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext239Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_close_next231'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next231'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next231'] ?? [], 'attempted next source rows');
        $target = self::token((string) ($options['current_source_upsert_target_next239'] ?? 'option_name'), 'upsert target');
        $policy = self::token((string) ($options['current_source_upsert_policy_next239'] ?? 'do-update-returning'), 'upsert policy');
        $cursor = self::token((string) ($options['current_source_upsert_cursor_next239'] ?? 'wp.returning.upsert.cursor.239'), 'upsert cursor');
        $generation = self::token((string) ($options['current_source_upsert_generation_next239'] ?? 'wp.current.source.upsert.generation.239'), 'upsert generation');
        $expectedGeneration = self::token((string) ($options['expected_current_source_upsert_generation_next239'] ?? $generation), 'expected upsert generation');
        $requireOrder = (bool) ($options['require_current_source_upsert_order_next239'] ?? true);
        $generationMatches = hash_equals($generation, $expectedGeneration);
        $requiredTargets = self::targetReceipts($currentRows, $target, $policy, $cursor, $generation);
        $acknowledgedTargets = self::acknowledgedTargets($options, $requiredTargets);
        $missingTargets = array_values(array_diff($requiredTargets, $acknowledgedTargets));
        $unexpectedTargets = array_values(array_diff($acknowledgedTargets, $requiredTargets));
        $orderMatches = !$requireOrder || $requiredTargets === $acknowledgedTargets;
        $upsertComplete = $requiredTargets !== []
            && $generationMatches
            && $missingTargets === []
            && $unexpectedTargets === []
            && $orderMatches;
        $nextVisible = $baseVisible && $upsertComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next231'] ?? [],
            $baseVisible,
            $generationMatches,
            $missingTargets,
            $unexpectedTargets,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredTargets, $target, $policy, $cursor, $generation, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $target, $policy, $cursor, $generation, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_upsert_next239'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_upsert_next239'],
        ));

        return [
            'status_next239' => self::status($baseVisible, $generationMatches, $missingTargets, $unexpectedTargets, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next239' => $baseVisible,
            'current_source_upsert_target_next239' => $target,
            'current_source_upsert_policy_next239' => $policy,
            'current_source_upsert_cursor_next239' => $cursor,
            'current_source_upsert_generation_next239' => $generation,
            'expected_current_source_upsert_generation_next239' => $expectedGeneration,
            'current_source_upsert_generation_matches_next239' => $generationMatches,
            'required_current_source_upsert_targets_next239' => $requiredTargets,
            'acknowledged_current_source_upsert_targets_next239' => $acknowledgedTargets,
            'missing_current_source_upsert_targets_next239' => $missingTargets,
            'unexpected_current_source_upsert_targets_next239' => $unexpectedTargets,
            'require_current_source_upsert_order_next239' => $requireOrder,
            'current_source_upsert_order_matches_next239' => $orderMatches,
            'current_source_upsert_complete_next239' => $upsertComplete,
            'next_source_visible_after_current_source_upsert_next239' => $nextVisible,
            'current_source_rows_next239' => $currentRows,
            'attempted_next_source_rows_next239' => $nextRows,
            'visible_returning_rows_next239' => $visibleRows,
            'held_next_source_rows_next239' => $heldRows,
            'visible_returning_payloads_next239' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next239' => array_column($heldRows, 'returning'),
            'current_source_row_count_next239' => count($currentRows),
            'attempted_next_source_row_count_next239' => count($nextRows),
            'visible_row_count_next239' => count($visibleRows),
            'held_next_row_count_next239' => count($heldRows),
            'blocked_reasons_next239' => $blockedReasons,
            'current_source_upsert_plan_next239' => [
                'base_next_source_visible' => $baseVisible,
                'target' => $target,
                'policy' => $policy,
                'generation_matches' => $generationMatches,
                'required_targets' => $requiredTargets,
                'acknowledged_targets' => $acknowledgedTargets,
                'missing_targets' => $missingTargets,
                'unexpected_targets' => $unexpectedTargets,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'upsert_complete' => $upsertComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-targets'
                    : 'hold-next-source-until-current-upsert-targets',
            ],
            'yield_boundary_next239' => $nextVisible
                ? 'recursive-view-upsert-next239-current-targets-then-next'
                : 'recursive-view-upsert-next239-current-targets-fence-next',
            'dependency_closure_next239' => 'no-new-support-component-reuses-native-recursive-view-returning-next231-and-adds-current-source-upsert-target-admission',
            'dependencies_next239' => array_values(array_unique(array_merge($base['dependencies_next231'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next239',
                'sqlite-upsert-current-source-target-receipts',
                'wordpress-recursive-view-upsert-current-source-next239',
            ]))),
            'non_overlap_next239' => 'adds current-source UPSERT target receipt admission after next231 cursor close; avoids accepted recursive view RETURNING next203-next231 surfaces, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function targetReceipts(array $rows, string $target, string $policy, string $cursor, string $generation): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $target,
                $policy,
                $cursor,
                $generation,
                (string) ($row['current_source_close_receipt_next231'] ?? ''),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 40);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedTargets(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_targets_next239'] ?? false) === true) {
            return $required;
        }

        return self::targetList($options['acknowledged_current_source_upsert_targets_next239'] ?? [], 'acknowledged current source upsert targets');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function targetList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} contain a malformed target receipt");
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
            throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} contain a malformed row");
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
        string $target,
        string $policy,
        string $cursor,
        string $generation,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_target_phase_next239' => $phase,
                'current_source_upsert_target_next239' => $target,
                'current_source_upsert_policy_next239' => $policy,
                'current_source_upsert_cursor_next239' => $cursor,
                'current_source_upsert_generation_next239' => $generation,
                'current_source_upsert_target_receipt_next239' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_next239' => $visible,
                'held_by_current_source_upsert_reasons_next239' => $visible ? [] : $reasons,
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
        bool $generationMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next239 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next231-current-source-close-not-published';
        }
        if (!$generationMatches) {
            $reasons[] = 'current-source-upsert-generation-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-target-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-target-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-upsert-target-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(
        bool $baseVisible,
        bool $generationMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next239-targets-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next239-base-held';
        }
        if (!$generationMatches) {
            return 'trigger-recursive-view-upsert-current-source-next239-generation-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next239-targets-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next239-target-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next239-empty-held';
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} is malformed");
        }

        return $value;
    }
}
