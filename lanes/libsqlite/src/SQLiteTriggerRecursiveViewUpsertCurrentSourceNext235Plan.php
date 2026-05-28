<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext235Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array{name:string,source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentViewRows,
        array $nextViewRows,
        array $view,
        array $uniqueColumns,
        array $triggers,
        array $options = [],
    ): array {
        $baseOptions = $options;
        $baseOptions['auto_ack_current_upsert_conflict_seals_next232'] = true;
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext232Plan::execute(
            $rows,
            $currentViewRows,
            $nextViewRows,
            $view,
            $uniqueColumns,
            $triggers,
            $baseOptions,
        );

        $currentTicketSource = self::token((string) ($options['current_yield_ticket_source_next235'] ?? 'wp.current.yield.ticket.source.235'), 'current yield ticket source');
        $expectedTicketSource = self::token((string) ($options['expected_current_yield_ticket_source_next235'] ?? $currentTicketSource), 'expected current yield ticket source');
        $resumeCursor = self::token((string) ($options['current_yield_resume_cursor_next235'] ?? 'wp.current.yield.cursor.235'), 'current yield resume cursor');
        $expectedResumeCursor = self::token((string) ($options['expected_current_yield_resume_cursor_next235'] ?? $resumeCursor), 'expected current yield resume cursor');
        $requireOrder = (bool) ($options['require_current_yield_ticket_order_next235'] ?? true);

        $currentRows = self::rows($base['current_yield_stream_next232'] ?? [], 'current yield stream');
        $attemptedNextRows = self::rows($base['attempted_next_yield_stream_next232'] ?? [], 'attempted next yield stream');
        $requiredTickets = self::tickets($currentRows, $currentTicketSource, $resumeCursor);
        $acknowledgedTickets = self::acknowledgedTickets($options, $requiredTickets);
        $missingTickets = array_values(array_diff($requiredTickets, $acknowledgedTickets));
        $unexpectedTickets = array_values(array_diff($acknowledgedTickets, $requiredTickets));
        $sourceMatches = hash_equals($currentTicketSource, $expectedTicketSource);
        $cursorMatches = hash_equals($resumeCursor, $expectedResumeCursor);
        $orderMatches = !$requireOrder || $requiredTickets === $acknowledgedTickets;
        $baseReleased = (bool) ($base['next_source_visible_after_current_upsert_conflict_next232'] ?? false);
        $ticketsComplete = $requiredTickets !== []
            && $baseReleased
            && $sourceMatches
            && $cursorMatches
            && $missingTickets === []
            && $unexpectedTickets === []
            && $orderMatches;

        $blockedReasons = self::blockedReasons($baseReleased, $sourceMatches, $cursorMatches, $missingTickets, $unexpectedTickets, $requireOrder, $orderMatches);
        $currentTagged = self::tagRows($currentRows, 'current', true, $requiredTickets, $currentTicketSource, $resumeCursor, []);
        $nextTagged = self::tagRows($attemptedNextRows, 'next', $ticketsComplete, [], $currentTicketSource, $resumeCursor, $ticketsComplete ? [] : $blockedReasons);
        $currentReturning = self::tagRows(self::rows($base['current_returning_rows_next232'] ?? [], 'current returning rows'), 'current', true, $requiredTickets, $currentTicketSource, $resumeCursor, []);
        $nextReturning = self::tagRows(self::rows($base['attempted_next_returning_rows_next232'] ?? [], 'attempted next returning rows'), 'next', $ticketsComplete, [], $currentTicketSource, $resumeCursor, $ticketsComplete ? [] : $blockedReasons);

        return [
            'status_next235' => self::status($ticketsComplete, $baseReleased, $sourceMatches, $cursorMatches, $missingTickets, $unexpectedTickets, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base_next235' => $base,
            'current_yield_ticket_source_next235' => $currentTicketSource,
            'expected_current_yield_ticket_source_next235' => $expectedTicketSource,
            'current_yield_ticket_source_matches_next235' => $sourceMatches,
            'current_yield_resume_cursor_next235' => $resumeCursor,
            'expected_current_yield_resume_cursor_next235' => $expectedResumeCursor,
            'current_yield_resume_cursor_matches_next235' => $cursorMatches,
            'required_current_yield_tickets_next235' => $requiredTickets,
            'acknowledged_current_yield_tickets_next235' => $acknowledgedTickets,
            'missing_current_yield_tickets_next235' => $missingTickets,
            'unexpected_current_yield_tickets_next235' => $unexpectedTickets,
            'require_current_yield_ticket_order_next235' => $requireOrder,
            'current_yield_ticket_order_matches_next235' => $orderMatches,
            'current_yield_ticket_complete_next235' => $ticketsComplete,
            'base_conflict_released_next235' => $baseReleased,
            'next_source_visible_after_current_yield_tickets_next235' => $ticketsComplete,
            'current_yield_stream_next235' => $currentTagged,
            'attempted_next_yield_stream_next235' => $nextTagged,
            'visible_yield_stream_next235' => $ticketsComplete ? array_merge($currentTagged, $nextTagged) : $currentTagged,
            'held_next_yield_stream_next235' => $ticketsComplete ? [] : $nextTagged,
            'current_returning_rows_next235' => $currentReturning,
            'attempted_next_returning_rows_next235' => $nextReturning,
            'visible_returning_rows_next235' => $ticketsComplete ? array_merge($currentReturning, $nextReturning) : $currentReturning,
            'held_next_returning_rows_next235' => $ticketsComplete ? [] : $nextReturning,
            'visible_change_count_next235' => $ticketsComplete ? (int) ($base['visible_change_count_next232'] ?? 0) : (int) ($base['current_change_count_next232'] ?? 0),
            'after_savepoint_next235' => $ticketsComplete ? ($base['after_savepoint_next232'] ?? []) : ($base['base']['after_savepoint'] ?? []),
            'blocked_reasons_next235' => $blockedReasons,
            'current_yield_ticket_plan_next235' => [
                'base_conflict_released' => $baseReleased,
                'ticket_source_matches' => $sourceMatches,
                'resume_cursor_matches' => $cursorMatches,
                'required_tickets' => $requiredTickets,
                'acknowledged_tickets' => $acknowledgedTickets,
                'missing_tickets' => $missingTickets,
                'unexpected_tickets' => $unexpectedTickets,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'ticket_complete' => $ticketsComplete,
                'decision' => $ticketsComplete
                    ? 'publish-next-source-after-current-recursive-view-upsert-yields'
                    : 'hold-next-source-until-current-recursive-view-upsert-yields',
            ],
            'yield_boundary_next235' => $ticketsComplete
                ? 'recursive-view-upsert-next235-current-yield-tickets-then-next'
                : 'recursive-view-upsert-next235-current-yield-tickets-fence-next',
            'dependency_closure_next235' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-conflict-seals-and-adds-yield-tickets',
            'dependencies_next235' => [
                'sqlite-trigger-recursive-view-upsert-current-source-next235',
                'sqlite-current-recursive-view-upsert-yield-ticket',
                'sqlite-current-upsert-conflict-seal',
                'wordpress-recursive-view-upsert-yield-ticket-next235',
            ],
            'non_overlap_next235' => 'adds current-source yield-ticket fencing after accepted next232 conflict seals; avoids recursive view RETURNING, trigger/FK, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function tickets(array $rows, string $source, string $cursor): array
    {
        $tickets = [];
        foreach ($rows as $index => $row) {
            $returning = is_array($row['returning'] ?? null) ? $row['returning'] : $row;
            $parts = [
                $source,
                $cursor,
                (string) ($row['current_view_source_next232'] ?? ''),
                (string) ($row['current_trigger_program_next232'] ?? ''),
                (string) ($row['current_upsert_conflict_seal_next232'] ?? ''),
                (string) ($row['event'] ?? $returning['event'] ?? ''),
                (string) ($row['depth'] ?? $returning['depth'] ?? ''),
                (string) ($row['ordinal'] ?? $returning['ordinal'] ?? $index),
                (string) ($row['trigger'] ?? $returning['trigger'] ?? ''),
                (string) ($returning['option_name'] ?? ''),
                (string) ($returning['option_value'] ?? ''),
            ];
            $tickets[] = substr(hash('sha256', implode('|', $parts)), 0, 50);
        }

        return $tickets;
    }

    /** @param array<string,mixed> $options @param list<string> $required @return list<string> */
    private static function acknowledgedTickets(array $options, array $required): array
    {
        if (($options['auto_ack_current_yield_tickets_next235'] ?? false) === true) {
            return $required;
        }

        return self::ticketList($options['acknowledged_current_yield_tickets_next235'] ?? [], 'acknowledged current yield tickets');
    }

    /** @param mixed $values @return list<string> */
    private static function ticketList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{50}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} contain a malformed yield ticket");
            }
        }

        return array_values(array_unique($values));
    }

    /** @param mixed $rows @return list<array<string,mixed>> */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $tickets @param list<string> $reasons @return list<array<string,mixed>> */
    private static function tagRows(array $rows, string $phase, bool $visible, array $tickets, string $source, string $cursor, array $reasons): array
    {
        $tagged = [];
        foreach ($rows as $index => $row) {
            $tagged[] = $row + [
                'yield_ticket_phase_next235' => $phase,
                'current_yield_ticket_source_next235' => $source,
                'current_yield_resume_cursor_next235' => $cursor,
                'current_yield_ticket_next235' => $tickets[$index] ?? null,
                'visible_after_current_yield_ticket_next235' => $visible,
                'held_by_current_yield_ticket_reasons_next235' => $visible ? [] : $reasons,
            ];
        }

        return $tagged;
    }

    /** @param list<string> $missing @param list<string> $unexpected @return list<string> */
    private static function blockedReasons(bool $baseReleased, bool $sourceMatches, bool $cursorMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        $reasons = [];
        if (!$baseReleased) {
            $reasons[] = 'base-current-upsert-conflict-not-released';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-yield-ticket-source-mismatch';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-yield-resume-cursor-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-yield-ticket-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-yield-ticket-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-yield-ticket-order-mismatch';
        }

        return $reasons;
    }

    /** @param list<string> $missing @param list<string> $unexpected */
    private static function status(bool $complete, bool $baseReleased, bool $sourceMatches, bool $cursorMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($complete) {
            return 'trigger-recursive-view-upsert-current-source-next235-yield-released';
        }
        if (!$baseReleased) {
            return 'trigger-recursive-view-upsert-current-source-next235-base-conflict-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next235-ticket-source-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-upsert-current-source-next235-resume-cursor-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next235-yield-ticket-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next235-yield-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next235-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} is malformed");
        }

        return $token;
    }
}
