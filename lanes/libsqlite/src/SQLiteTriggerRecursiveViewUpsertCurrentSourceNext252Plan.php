<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext252Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext249Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_assignment_next249'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next249'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next249'] ?? [], 'attempted next source rows');
        $assignments = self::assignments($base['current_source_assignment_images_next249'] ?? []);
        $predicateToken = self::token((string) ($options['current_source_upsert_where_token_next252'] ?? 'wp.current.source.upsert.where.252'), 'where token');
        $expectedPredicateToken = self::token((string) ($options['expected_current_source_upsert_where_token_next252'] ?? $predicateToken), 'expected where token');
        $tokenMatches = hash_equals($predicateToken, $expectedPredicateToken);
        $decisions = self::predicateDecisions($options['current_source_upsert_where_decisions_next252'] ?? null, $assignments);
        $requireTrue = (bool) ($options['require_current_source_upsert_where_true_next252'] ?? false);
        $requiredReceipts = self::predicateReceipts($assignments, $decisions, $predicateToken);
        $acknowledgedReceipts = self::acknowledgedReceipts($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $allPredicatesTrue = !in_array(false, $decisions, true);
        $predicateComplete = $requiredReceipts !== []
            && $tokenMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && (!$requireTrue || $allPredicatesTrue);
        $nextVisible = $baseVisible && $predicateComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next249'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missingReceipts,
            $unexpectedReceipts,
            $requireTrue,
            $allPredicatesTrue,
        );

        $currentRows = self::tagRows($currentRows, 'current-where', true, $requiredReceipts, $predicateToken, $decisions, []);
        $nextRows = self::tagRows($nextRows, 'next-source', $nextVisible, [], $predicateToken, [], $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($currentRows, $nextRows) : $currentRows;
        $heldRows = $nextVisible ? [] : $nextRows;

        return [
            'status_next252' => self::status($baseVisible, $tokenMatches, $missingReceipts, $unexpectedReceipts, $requireTrue, $allPredicatesTrue, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next252' => $baseVisible,
            'current_source_upsert_where_token_next252' => $predicateToken,
            'expected_current_source_upsert_where_token_next252' => $expectedPredicateToken,
            'current_source_upsert_where_token_matches_next252' => $tokenMatches,
            'current_source_upsert_where_decisions_next252' => $decisions,
            'current_source_upsert_where_all_true_next252' => $allPredicatesTrue,
            'require_current_source_upsert_where_true_next252' => $requireTrue,
            'required_current_source_upsert_where_receipts_next252' => $requiredReceipts,
            'acknowledged_current_source_upsert_where_receipts_next252' => $acknowledgedReceipts,
            'missing_current_source_upsert_where_receipts_next252' => $missingReceipts,
            'unexpected_current_source_upsert_where_receipts_next252' => $unexpectedReceipts,
            'current_source_upsert_where_complete_next252' => $predicateComplete,
            'next_source_visible_after_current_source_upsert_where_next252' => $nextVisible,
            'current_source_rows_next252' => $currentRows,
            'attempted_next_source_rows_next252' => $nextRows,
            'visible_returning_rows_next252' => $visibleRows,
            'held_next_source_rows_next252' => $heldRows,
            'visible_returning_payloads_next252' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next252' => array_column($heldRows, 'returning'),
            'visible_row_count_next252' => count($visibleRows),
            'held_next_row_count_next252' => count($heldRows),
            'blocked_reasons_next252' => $blockedReasons,
            'current_source_upsert_where_plan_next252' => [
                'base_next_source_visible' => $baseVisible,
                'token_matches' => $tokenMatches,
                'decisions' => $decisions,
                'require_true' => $requireTrue,
                'all_true' => $allPredicatesTrue,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'predicate_complete' => $predicateComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-where'
                    : 'hold-next-source-until-current-upsert-where',
            ],
            'yield_boundary_next252' => $nextVisible
                ? 'recursive-view-upsert-next252-current-where-then-next'
                : 'recursive-view-upsert-next252-current-where-fence-next',
            'dependency_closure_next252' => 'no-new-support-component-reuses-native-recursive-view-upsert-assignment-receipts-and-adds-do-update-where-decision-receipts',
            'dependencies_next252' => array_values(array_unique(array_merge($base['dependencies_next249'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next252',
                'sqlite-instead-of-view-upsert-do-update-where-receipts',
                'wordpress-recursive-view-upsert-current-source-next252',
            ]))),
            'non_overlap_next252' => 'adds current-source UPSERT DO UPDATE WHERE predicate decision receipts after accepted next249 assignment receipts; avoids next249 assignment receipt fencing, next246 conflict images, recursive view RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next252 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param mixed $assignments
     * @return list<array<string,mixed>>
     */
    private static function assignments(mixed $assignments): array
    {
        if (!is_array($assignments) || !array_is_list($assignments) || $assignments === []) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next252 assignment images must be a non-empty list');
        }
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next252 assignment image is malformed');
            }
        }

        return $assignments;
    }

    /**
     * @param mixed $decisions
     * @param list<array<string,mixed>> $assignments
     * @return list<bool>
     */
    private static function predicateDecisions(mixed $decisions, array $assignments): array
    {
        if ($decisions === null) {
            return array_fill(0, count($assignments), true);
        }
        if (!is_array($decisions) || !array_is_list($decisions) || count($decisions) !== count($assignments)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next252 predicate decisions must match assignment count');
        }
        foreach ($decisions as $decision) {
            if (!is_bool($decision)) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next252 predicate decisions must be booleans');
            }
        }

        return $decisions;
    }

    /**
     * @param list<array<string,mixed>> $assignments
     * @param list<bool> $decisions
     * @return list<string>
     */
    private static function predicateReceipts(array $assignments, array $decisions, string $predicateToken): array
    {
        $receipts = [];
        foreach ($assignments as $index => $assignment) {
            $receipts[] = substr(hash('sha256', json_encode([
                $predicateToken,
                $assignment['ordinal'] ?? $index,
                $assignment['conflict_key'] ?? '',
                $assignment['upsert_action'] ?? '',
                $assignment['assignments'] ?? [],
                $decisions[$index],
            ], JSON_THROW_ON_ERROR)), 0, 44);
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
        if (($options['auto_ack_current_source_upsert_where_next252'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_upsert_where_receipts_next252'] ?? [], 'acknowledged where receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next252 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{44}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next252 {$label} contain a malformed where receipt");
            }
        }

        return array_values(array_unique($values));
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next252 {$label} is malformed");
        }

        return $token;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<bool> $decisions
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $predicateToken, array $decisions, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_where_phase_next252' => $phase,
                'current_source_upsert_where_token_next252' => $predicateToken,
                'current_source_upsert_where_decision_next252' => $decisions[$index] ?? null,
                'current_source_upsert_where_receipt_next252' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_where_next252' => $visible,
                'held_by_current_source_upsert_where_reasons_next252' => $visible ? [] : $reasons,
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireTrue, bool $allPredicatesTrue): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next252 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next249-current-source-upsert-assignments-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-upsert-where-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-where-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-where-receipt-unexpected';
        }
        if ($requireTrue && !$allPredicatesTrue) {
            $reasons[] = 'current-source-upsert-where-false';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireTrue, bool $allPredicatesTrue, bool $nextVisible): string
    {
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next252-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next252-where-token-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next252-where-receipts-held';
        }
        if ($requireTrue && !$allPredicatesTrue) {
            return 'trigger-recursive-view-upsert-current-source-next252-where-false-held';
        }

        return $nextVisible
            ? 'trigger-recursive-view-upsert-current-source-next252-where-released'
            : 'trigger-recursive-view-upsert-current-source-next252-held';
    }
}
