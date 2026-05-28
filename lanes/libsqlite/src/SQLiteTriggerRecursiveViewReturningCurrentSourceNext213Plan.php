<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext213Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext212Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $sealToken = self::token((string) ($options['current_source_payload_seal_token_next213'] ?? 'wp.current.source.payload.seal.213'), 'current source payload seal token');
        $sealCursor = self::token((string) ($options['current_source_payload_seal_cursor_next213'] ?? 'wp.returning.current.payload.cursor.213'), 'current source payload seal cursor');
        $requireOrder = (bool) ($options['require_current_source_payload_seal_order_next213'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_yield_next212'] ?? false);

        $currentRows = self::rows($base['current_source_rows_next212'] ?? [], 'current source rows');
        $attemptedNextRows = self::rows($base['attempted_next_source_rows_next212'] ?? [], 'attempted next source rows');
        $requiredSeals = self::payloadSeals($currentRows, $sealToken, $sealCursor);
        $acknowledgedSeals = self::acknowledgedSeals($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $orderMatches = !$requireOrder || $requiredSeals === $acknowledgedSeals;
        $sealComplete = $requiredSeals !== []
            && $missingSeals === []
            && $unexpectedSeals === []
            && $orderMatches;
        $nextVisible = $baseVisible && $sealComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next212'] ?? [],
            $baseVisible,
            $sealComplete,
            $missingSeals,
            $unexpectedSeals,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredSeals, $sealToken, $sealCursor, []);
        $nextRows = self::tagRows($attemptedNextRows, 'next', $nextVisible, [], $sealToken, $sealCursor, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_payload_seal_next213'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_payload_seal_next213'],
        ));

        return [
            'status_next213' => self::status($baseVisible, $sealComplete, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next213' => $baseVisible,
            'current_source_payload_seal_token_next213' => $sealToken,
            'current_source_payload_seal_cursor_next213' => $sealCursor,
            'required_current_source_payload_seals_next213' => $requiredSeals,
            'acknowledged_current_source_payload_seals_next213' => $acknowledgedSeals,
            'missing_current_source_payload_seals_next213' => $missingSeals,
            'unexpected_current_source_payload_seals_next213' => $unexpectedSeals,
            'require_current_source_payload_seal_order_next213' => $requireOrder,
            'current_source_payload_seal_order_matches_next213' => $orderMatches,
            'current_source_payload_seal_complete_next213' => $sealComplete,
            'next_source_visible_after_current_source_payload_seal_next213' => $nextVisible,
            'current_source_rows_next213' => $currentRows,
            'attempted_next_source_rows_next213' => $nextRows,
            'visible_returning_rows_next213' => $visibleRows,
            'held_next_source_rows_next213' => $heldRows,
            'visible_returning_payloads_next213' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next213' => array_column($heldRows, 'returning'),
            'current_source_row_count_next213' => count($currentRows),
            'attempted_next_source_row_count_next213' => count($nextRows),
            'visible_row_count_next213' => count($visibleRows),
            'held_next_row_count_next213' => count($heldRows),
            'blocked_reasons_next213' => $blockedReasons,
            'current_source_payload_seal_plan_next213' => [
                'base_next_source_visible' => $baseVisible,
                'required_payload_seals' => $requiredSeals,
                'acknowledged_payload_seals' => $acknowledgedSeals,
                'missing_payload_seals' => $missingSeals,
                'unexpected_payload_seals' => $unexpectedSeals,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'payload_seal_complete' => $sealComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-payload-seal'
                    : 'hold-next-source-until-current-payload-seal',
            ],
            'yield_boundary_next213' => $nextVisible
                ? 'recursive-view-returning-next213-current-payload-seal-then-next'
                : 'recursive-view-returning-next213-current-payload-seal-fences-next',
            'dependency_closure_next213' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-payload-seals',
            'dependencies_next213' => array_values(array_unique(array_merge($base['dependencies_next212'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next213',
                'sqlite-returning-current-source-payload-seal',
                'wordpress-recursive-view-returning-current-source-next213',
            ]))),
            'non_overlap_next213' => 'adds current-source RETURNING payload seals after next212 yield receipts; avoids accepted trigger recursive view RETURNING next172-next212 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function payloadSeals(array $rows, string $sealToken, string $sealCursor): array
    {
        $seals = [];
        foreach ($rows as $index => $row) {
            $payload = self::stablePayload($row['returning']);
            $parts = [
                $sealToken,
                $sealCursor,
                (string) ($row['current_source_yield_receipt_next212'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                $payload,
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
        if (($options['auto_ack_current_source_payload_seals_next213'] ?? false) === true) {
            return $required;
        }

        return self::sealList($options['acknowledged_current_source_payload_seals_next213'] ?? [], 'acknowledged current source payload seals');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sealList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} contain a malformed payload seal");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} contain a malformed row");
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
    private static function tagRows(
        array $rows,
        string $phase,
        bool $visible,
        array $seals,
        string $sealToken,
        string $sealCursor,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'payload_seal_phase_next213' => $phase,
                'current_source_payload_seal_token_next213' => $sealToken,
                'current_source_payload_seal_cursor_next213' => $sealCursor,
                'current_source_payload_seal_next213' => $seals[$index] ?? null,
                'visible_after_current_source_payload_seal_next213' => $visible,
                'held_by_current_source_payload_seal_reasons_next213' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function stablePayload(array $payload): string
    {
        ksort($payload);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next213 payload cannot be encoded');
        }

        return $json;
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
        bool $sealComplete,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next213 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next212-current-source-yield-not-published';
        }
        if (!$sealComplete) {
            if ($missing !== []) {
                $reasons[] = 'current-source-payload-seal-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-payload-seal-unexpected';
            }
            if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
                $reasons[] = 'current-source-payload-seal-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $baseVisible, bool $sealComplete, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next213-payload-seal-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next213-base-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && !$sealComplete) {
            return 'trigger-recursive-view-returning-current-source-next213-payload-seal-order-held';
        }
        if (!$sealComplete) {
            return 'trigger-recursive-view-returning-current-source-next213-payload-seal-held';
        }

        return 'trigger-recursive-view-returning-current-source-next213-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next213 {$label} is malformed");
        }

        return $token;
    }
}
