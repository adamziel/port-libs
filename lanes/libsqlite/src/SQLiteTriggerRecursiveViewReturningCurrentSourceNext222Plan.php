<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext222Plan
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

        $sourceTicket = self::token((string) ($options['current_source_ticket_next222'] ?? 'wp.current.source.ticket.222'), 'current source ticket');
        $viewSource = self::token((string) ($options['current_view_source_next222'] ?? (string) ($currentView['source'] ?? 'main@view-cookie-222-current')), 'current view source');
        $triggerSource = self::token((string) ($options['current_trigger_source_next222'] ?? (string) ($currentView['trigger_source'] ?? 'main@trigger-cookie-222-current')), 'current trigger source');
        $expectedViewSource = self::token((string) ($options['expected_current_view_source_next222'] ?? $viewSource), 'expected current view source');
        $expectedTriggerSource = self::token((string) ($options['expected_current_trigger_source_next222'] ?? $triggerSource), 'expected current trigger source');
        $requireOrder = (bool) ($options['require_current_source_ticket_order_next222'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_epoch_next218'] ?? false);
        $sourceMatches = hash_equals($viewSource, $expectedViewSource) && hash_equals($triggerSource, $expectedTriggerSource);

        $currentRows = self::rows($base['current_source_rows_next218'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next218'] ?? [], 'attempted next source rows');
        $requiredTickets = self::sourceTickets($currentRows, $sourceTicket, $viewSource, $triggerSource);
        $acknowledgedTickets = self::acknowledgedTickets($options, $requiredTickets);
        $missingTickets = array_values(array_diff($requiredTickets, $acknowledgedTickets));
        $unexpectedTickets = array_values(array_diff($acknowledgedTickets, $requiredTickets));
        $orderMatches = !$requireOrder || $requiredTickets === $acknowledgedTickets;
        $ticketComplete = $requiredTickets !== []
            && $sourceMatches
            && $missingTickets === []
            && $unexpectedTickets === []
            && $orderMatches;
        $nextVisible = $baseVisible && $ticketComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next218'] ?? [],
            $baseVisible,
            $sourceMatches,
            $missingTickets,
            $unexpectedTickets,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredTickets, $sourceTicket, $viewSource, $triggerSource, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $sourceTicket, $viewSource, $triggerSource, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_ticket_next222'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_ticket_next222'],
        ));

        return [
            'status_next222' => self::status($baseVisible, $sourceMatches, $missingTickets, $unexpectedTickets, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next222' => $baseVisible,
            'current_source_ticket_next222' => $sourceTicket,
            'current_view_source_next222' => $viewSource,
            'current_trigger_source_next222' => $triggerSource,
            'expected_current_view_source_next222' => $expectedViewSource,
            'expected_current_trigger_source_next222' => $expectedTriggerSource,
            'current_source_matches_next222' => $sourceMatches,
            'required_current_source_tickets_next222' => $requiredTickets,
            'acknowledged_current_source_tickets_next222' => $acknowledgedTickets,
            'missing_current_source_tickets_next222' => $missingTickets,
            'unexpected_current_source_tickets_next222' => $unexpectedTickets,
            'require_current_source_ticket_order_next222' => $requireOrder,
            'current_source_ticket_order_matches_next222' => $orderMatches,
            'current_source_ticket_complete_next222' => $ticketComplete,
            'next_source_visible_after_current_source_ticket_next222' => $nextVisible,
            'current_source_rows_next222' => $currentRows,
            'attempted_next_source_rows_next222' => $nextRows,
            'visible_returning_rows_next222' => $visibleRows,
            'held_next_source_rows_next222' => $heldRows,
            'visible_returning_payloads_next222' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next222' => array_column($heldRows, 'returning'),
            'current_source_row_count_next222' => count($currentRows),
            'attempted_next_source_row_count_next222' => count($nextRows),
            'visible_row_count_next222' => count($visibleRows),
            'held_next_row_count_next222' => count($heldRows),
            'blocked_reasons_next222' => $blockedReasons,
            'current_source_ticket_plan_next222' => [
                'base_next_source_visible' => $baseVisible,
                'current_source_matches' => $sourceMatches,
                'required_tickets' => $requiredTickets,
                'acknowledged_tickets' => $acknowledgedTickets,
                'missing_tickets' => $missingTickets,
                'unexpected_tickets' => $unexpectedTickets,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'ticket_complete' => $ticketComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-ticket'
                    : 'hold-next-source-until-current-source-ticket',
            ],
            'yield_boundary_next222' => $nextVisible
                ? 'recursive-view-returning-next222-current-source-ticket-then-next'
                : 'recursive-view-returning-next222-current-source-ticket-fences-next',
            'dependency_closure_next222' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-ticket-handoff',
            'dependencies_next222' => array_values(array_unique(array_merge($base['dependencies_next218'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next222',
                'sqlite-returning-current-source-ticket-handoff',
                'wordpress-recursive-view-returning-current-source-next222',
            ]))),
            'non_overlap_next222' => 'adds current view/trigger source ticket admission after accepted next218 epoch handoff; avoids accepted trigger recursive view RETURNING next157-next218 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sourceTickets(array $rows, string $ticket, string $viewSource, string $triggerSource): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $ticket,
                $viewSource,
                $triggerSource,
                (string) ($row['current_source_epoch_receipt_next218'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 42);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedTickets(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_tickets_next222'] ?? false) === true) {
            return $required;
        }

        return self::ticketList($options['acknowledged_current_source_tickets_next222'] ?? [], 'acknowledged current source tickets');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function ticketList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{42}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} contain a malformed source ticket");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} contain a malformed row");
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
        string $ticket,
        string $viewSource,
        string $triggerSource,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_ticket_phase_next222' => $phase,
                'current_source_ticket_next222' => $ticket,
                'current_view_source_next222' => $viewSource,
                'current_trigger_source_next222' => $triggerSource,
                'current_source_ticket_receipt_next222' => $receipts[$index] ?? null,
                'visible_after_current_source_ticket_next222' => $visible,
                'held_by_current_source_ticket_reasons_next222' => $visible ? [] : $reasons,
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
        bool $sourceMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next222 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next218-current-source-epoch-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-source-ticket-source-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-ticket-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-ticket-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-ticket-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(
        bool $baseVisible,
        bool $sourceMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next222-source-ticket-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next222-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next222-source-mismatch-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next222-source-ticket-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next222-source-ticket-order-held';
        }

        return 'trigger-recursive-view-returning-current-source-next222-source-ticket-empty-held';
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next222 {$label} is malformed");
        }

        return $value;
    }
}
