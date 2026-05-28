<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext224Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext218Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rows($base['current_source_rows_next218'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next218'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_epoch_next218'] ?? false);
        $sourceToken = self::token((string) ($options['current_returning_source_token_next224'] ?? 'wp.current.returning.source.224'), 'current returning source token');
        $expectedSourceToken = self::token((string) ($options['expected_current_returning_source_token_next224'] ?? $sourceToken), 'expected current returning source token');
        $viewSource = self::token((string) ($options['current_returning_view_source_next224'] ?? ($currentView['source'] ?? 'main@view-cookie-224-current')), 'current returning view source');
        $expectedViewSource = self::token((string) ($options['expected_current_returning_view_source_next224'] ?? $viewSource), 'expected current returning view source');
        $triggerSource = self::token((string) ($options['current_returning_trigger_source_next224'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-224-current')), 'current returning trigger source');
        $expectedTriggerSource = self::token((string) ($options['expected_current_returning_trigger_source_next224'] ?? $triggerSource), 'expected current returning trigger source');
        $requiredSeals = self::sourceSeals($currentRows, $sourceToken, $viewSource, $triggerSource);
        $acknowledgedSeals = self::acknowledgedSeals($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $viewMatches = hash_equals($viewSource, $expectedViewSource);
        $triggerMatches = hash_equals($triggerSource, $expectedTriggerSource);
        $sealComplete = $requiredSeals !== []
            && $sourceMatches
            && $viewMatches
            && $triggerMatches
            && $missingSeals === []
            && $unexpectedSeals === [];
        $nextVisible = $baseVisible && $sealComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next218'] ?? [],
            $baseVisible,
            $sourceMatches,
            $viewMatches,
            $triggerMatches,
            $missingSeals,
            $unexpectedSeals,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredSeals, $sourceToken, $viewSource, $triggerSource, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $sourceToken, $viewSource, $triggerSource, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_returning_source_next224'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_returning_source_next224'],
        ));

        return [
            'status_next224' => self::status($nextVisible, $baseVisible, $sourceMatches, $viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next224' => $baseVisible,
            'current_returning_source_token_next224' => $sourceToken,
            'expected_current_returning_source_token_next224' => $expectedSourceToken,
            'current_returning_source_matches_next224' => $sourceMatches,
            'current_returning_view_source_next224' => $viewSource,
            'expected_current_returning_view_source_next224' => $expectedViewSource,
            'current_returning_view_source_matches_next224' => $viewMatches,
            'current_returning_trigger_source_next224' => $triggerSource,
            'expected_current_returning_trigger_source_next224' => $expectedTriggerSource,
            'current_returning_trigger_source_matches_next224' => $triggerMatches,
            'required_current_returning_source_seals_next224' => $requiredSeals,
            'acknowledged_current_returning_source_seals_next224' => $acknowledgedSeals,
            'missing_current_returning_source_seals_next224' => $missingSeals,
            'unexpected_current_returning_source_seals_next224' => $unexpectedSeals,
            'current_returning_source_complete_next224' => $sealComplete,
            'next_source_visible_after_current_returning_source_next224' => $nextVisible,
            'current_source_rows_next224' => $currentRows,
            'attempted_next_source_rows_next224' => $nextRows,
            'visible_returning_rows_next224' => $visibleRows,
            'held_next_source_rows_next224' => $heldRows,
            'visible_returning_payloads_next224' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next224' => array_column($heldRows, 'returning'),
            'current_source_row_count_next224' => count($currentRows),
            'attempted_next_source_row_count_next224' => count($nextRows),
            'visible_row_count_next224' => count($visibleRows),
            'held_next_row_count_next224' => count($heldRows),
            'blocked_reasons_next224' => $blockedReasons,
            'current_returning_source_plan_next224' => [
                'base_next_source_visible' => $baseVisible,
                'source_matches' => $sourceMatches,
                'view_source_matches' => $viewMatches,
                'trigger_source_matches' => $triggerMatches,
                'required_seals' => $requiredSeals,
                'acknowledged_seals' => $acknowledgedSeals,
                'missing_seals' => $missingSeals,
                'unexpected_seals' => $unexpectedSeals,
                'source_complete' => $sealComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-source-seal'
                    : 'hold-next-source-until-current-returning-source-seal',
            ],
            'yield_boundary_next224' => $nextVisible
                ? 'recursive-view-returning-next224-current-source-sealed-then-next'
                : 'recursive-view-returning-next224-current-source-seal-fences-next',
            'dependency_closure_next224' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-epoch-and-adds-source-seal',
            'dependencies_next224' => array_values(array_unique(array_merge($base['dependencies_next218'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next224',
                'sqlite-returning-current-source-seal',
                'wordpress-recursive-view-returning-current-source-next224',
            ]))),
            'non_overlap_next224' => 'adds current returning source/view/trigger source seals after next218 epoch receipts; avoids accepted next208 cursor close, next212 yield receipts, next218 epoch receipts, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sourceSeals(array $rows, string $sourceToken, string $viewSource, string $triggerSource): array
    {
        $seals = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sourceToken,
                $viewSource,
                $triggerSource,
                (string) ($row['current_source_epoch_receipt_next218'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $seals[] = substr(hash('sha256', implode('|', $parts)), 0, 40);
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
        if (($options['auto_ack_current_returning_source_seals_next224'] ?? false) === true) {
            return $required;
        }

        return self::sealList($options['acknowledged_current_returning_source_seals_next224'] ?? [], 'acknowledged current returning source seals');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sealList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} contain a malformed source seal");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} contain a malformed row");
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $seals, string $sourceToken, string $viewSource, string $triggerSource, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'returning_source_phase_next224' => $phase,
                'current_returning_source_token_next224' => $sourceToken,
                'current_returning_view_source_next224' => $viewSource,
                'current_returning_trigger_source_next224' => $triggerSource,
                'current_returning_source_seal_next224' => $seals[$index] ?? null,
                'visible_after_current_returning_source_next224' => $visible,
                'held_by_current_returning_source_reasons_next224' => $visible ? [] : $reasons,
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next224 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next218-current-source-epoch-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-returning-source-token-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-returning-view-source-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-returning-trigger-source-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-returning-source-seal-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-returning-source-seal-unexpected';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $nextVisible, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next224-source-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next224-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next224-source-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-returning-current-source-next224-view-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-returning-current-source-next224-trigger-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next224-seal-held';
        }

        return 'trigger-recursive-view-returning-current-source-next224-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next224 {$label} is malformed");
        }

        return $token;
    }
}
