<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext229Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext224Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rows($base['current_source_rows_next224'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next224'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_returning_source_next224'] ?? false);
        $sourceGeneration = self::token((string) ($options['current_returning_source_generation_next229'] ?? 'wp.current.returning.source.generation.229'), 'current returning source generation');
        $expectedSourceGeneration = self::token((string) ($options['expected_current_returning_source_generation_next229'] ?? $sourceGeneration), 'expected current returning source generation');
        $viewGeneration = self::token((string) ($options['current_returning_view_generation_next229'] ?? ($currentView['source'] ?? 'main@view-generation-229-current')), 'current returning view generation');
        $expectedViewGeneration = self::token((string) ($options['expected_current_returning_view_generation_next229'] ?? $viewGeneration), 'expected current returning view generation');
        $triggerGeneration = self::token((string) ($options['current_returning_trigger_generation_next229'] ?? ($currentView['trigger_source'] ?? 'main@trigger-generation-229-current')), 'current returning trigger generation');
        $expectedTriggerGeneration = self::token((string) ($options['expected_current_returning_trigger_generation_next229'] ?? $triggerGeneration), 'expected current returning trigger generation');
        $requireOrder = (bool) ($options['require_current_returning_generation_order_next229'] ?? true);
        $requiredSeals = self::generationSeals($currentRows, $sourceGeneration, $viewGeneration, $triggerGeneration);
        $acknowledgedSeals = self::acknowledgedSeals($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $sourceMatches = hash_equals($sourceGeneration, $expectedSourceGeneration);
        $viewMatches = hash_equals($viewGeneration, $expectedViewGeneration);
        $triggerMatches = hash_equals($triggerGeneration, $expectedTriggerGeneration);
        $orderMatches = !$requireOrder || $requiredSeals === $acknowledgedSeals;
        $sealComplete = $requiredSeals !== []
            && $sourceMatches
            && $viewMatches
            && $triggerMatches
            && $missingSeals === []
            && $unexpectedSeals === []
            && $orderMatches;
        $nextVisible = $baseVisible && $sealComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next224'] ?? [],
            $baseVisible,
            $sourceMatches,
            $viewMatches,
            $triggerMatches,
            $missingSeals,
            $unexpectedSeals,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredSeals, $sourceGeneration, $viewGeneration, $triggerGeneration, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $sourceGeneration, $viewGeneration, $triggerGeneration, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_returning_generation_next229'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_returning_generation_next229'],
        ));

        return [
            'status_next229' => self::status($nextVisible, $baseVisible, $sourceMatches, $viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next229' => $baseVisible,
            'current_returning_source_generation_next229' => $sourceGeneration,
            'expected_current_returning_source_generation_next229' => $expectedSourceGeneration,
            'current_returning_source_generation_matches_next229' => $sourceMatches,
            'current_returning_view_generation_next229' => $viewGeneration,
            'expected_current_returning_view_generation_next229' => $expectedViewGeneration,
            'current_returning_view_generation_matches_next229' => $viewMatches,
            'current_returning_trigger_generation_next229' => $triggerGeneration,
            'expected_current_returning_trigger_generation_next229' => $expectedTriggerGeneration,
            'current_returning_trigger_generation_matches_next229' => $triggerMatches,
            'required_current_returning_generation_seals_next229' => $requiredSeals,
            'acknowledged_current_returning_generation_seals_next229' => $acknowledgedSeals,
            'missing_current_returning_generation_seals_next229' => $missingSeals,
            'unexpected_current_returning_generation_seals_next229' => $unexpectedSeals,
            'require_current_returning_generation_order_next229' => $requireOrder,
            'current_returning_generation_order_matches_next229' => $orderMatches,
            'current_returning_generation_complete_next229' => $sealComplete,
            'next_source_visible_after_current_returning_generation_next229' => $nextVisible,
            'current_source_rows_next229' => $currentRows,
            'attempted_next_source_rows_next229' => $nextRows,
            'visible_returning_rows_next229' => $visibleRows,
            'held_next_source_rows_next229' => $heldRows,
            'visible_returning_payloads_next229' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next229' => array_column($heldRows, 'returning'),
            'current_source_row_count_next229' => count($currentRows),
            'attempted_next_source_row_count_next229' => count($nextRows),
            'visible_row_count_next229' => count($visibleRows),
            'held_next_row_count_next229' => count($heldRows),
            'blocked_reasons_next229' => $blockedReasons,
            'current_returning_source_plan_next229' => [
                'base_next_source_visible' => $baseVisible,
                'source_generation_matches' => $sourceMatches,
                'view_generation_matches' => $viewMatches,
                'trigger_generation_matches' => $triggerMatches,
                'required_seals' => $requiredSeals,
                'acknowledged_seals' => $acknowledgedSeals,
                'missing_seals' => $missingSeals,
                'unexpected_seals' => $unexpectedSeals,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'generation_complete' => $sealComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-generation-seal'
                    : 'hold-next-source-until-current-returning-generation-seal',
            ],
            'yield_boundary_next229' => $nextVisible
                ? 'recursive-view-returning-next229-current-source-generation-sealed-then-next'
                : 'recursive-view-returning-next229-current-source-generation-seal-fences-next',
            'dependency_closure_next229' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-seal-and-adds-generation-seal',
            'dependencies_next229' => array_values(array_unique(array_merge($base['dependencies_next224'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next229',
                'sqlite-returning-current-source-generation-seal',
                'wordpress-recursive-view-returning-current-source-next229',
            ]))),
            'non_overlap_next229' => 'adds ordered current returning source/view/trigger generation seals after next224 source seals; avoids accepted next208 cursor close, next212 yield receipts, next218 epoch receipts, next224 source seals, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function generationSeals(array $rows, string $sourceGeneration, string $viewGeneration, string $triggerGeneration): array
    {
        $seals = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sourceGeneration,
                $viewGeneration,
                $triggerGeneration,
                (string) ($row['current_returning_source_seal_next224'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $seals[] = substr(hash('sha256', implode('|', $parts)), 0, 44);
        }

        return $seals;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedSeals(array $options, array $required): array
    {
        if (($options['auto_ack_current_returning_generation_seals_next229'] ?? false) === true) {
            return $required;
        }

        return self::sealList($options['acknowledged_current_returning_generation_seals_next229'] ?? [], 'acknowledged current returning generation seals');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sealList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{44}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} contain a malformed generation seal");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $seals
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $seals, string $sourceGeneration, string $viewGeneration, string $triggerGeneration, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'returning_generation_phase_next229' => $phase,
                'current_returning_source_generation_next229' => $sourceGeneration,
                'current_returning_view_generation_next229' => $viewGeneration,
                'current_returning_trigger_generation_next229' => $triggerGeneration,
                'current_returning_generation_seal_next229' => $seals[$index] ?? null,
                'visible_after_current_returning_generation_next229' => $visible,
                'held_by_current_returning_generation_reasons_next229' => $visible ? [] : $reasons,
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next229 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next224-current-returning-source-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-returning-source-generation-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-returning-view-generation-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-returning-trigger-generation-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-returning-generation-seal-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-returning-generation-seal-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-returning-generation-seal-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $nextVisible, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next229-generation-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next229-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next229-source-generation-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-returning-current-source-next229-view-generation-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-returning-current-source-next229-trigger-generation-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next229-generation-seal-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next229-generation-order-held';
        }

        return 'trigger-recursive-view-returning-current-source-next229-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next229 {$label} is malformed");
        }

        return $token;
    }
}
