<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext187Plan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,checkpoint_name?:string,commit_visible_checkpoints?:bool,handoff_token?:string,expected_handoff_token?:string,acknowledged_current_checkpoints?:list<string>,auto_ack_current?:bool,drain_ticket?:string,expected_drain_ticket?:string,drain_ticket_prefix?:string} $options
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
        $baseOptions = $options + [
            'cursor_name' => 'wp_recursive_view_returning_cursor_187',
            'current_generation' => 'wp-current-returning-187',
            'next_generation' => 'wp-next-returning-187',
            'checkpoint_name' => 'wp_recursive_view_checkpoint_187',
            'handoff_token' => 'wp.returning.current.source.handoff.187',
            'savepoint' => 'wp_recursive_view_returning_next187',
        ];

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext184Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $baseOptions,
        );

        $prefix = self::token((string) ($options['drain_ticket_prefix'] ?? 'wp.returning.current.source.drain.187'), 'drain ticket prefix');
        $expectedTicket = self::token((string) ($options['expected_drain_ticket'] ?? self::ticket($prefix, $base['required_current_checkpoints'] ?? [])), 'expected drain ticket');
        $actualTicket = self::token((string) ($options['drain_ticket'] ?? self::ticket($prefix, $base['acknowledged_current_checkpoints'] ?? [])), 'drain ticket');
        $ticketMatches = $actualTicket === $expectedTicket;
        $baseExposed = (bool) ($base['next_source_exposed_after_handoff'] ?? false);
        $ticketAdmitted = !$baseExposed || $ticketMatches;
        $canExposeNext = $baseExposed && $ticketAdmitted;
        $blockReasons = self::blockReasons($base['block_reasons'] ?? [], $ticketAdmitted, $baseExposed);

        $currentRows = self::ticketRows($base['current_source_rows'] ?? [], $actualTicket, true, []);
        $attemptedNextRows = self::ticketRows($base['attempted_next_source_rows'] ?? [], $actualTicket, $canExposeNext, $blockReasons);
        $visibleRows = array_values(array_filter(array_merge($currentRows, $attemptedNextRows), static fn (array $row): bool => $row['visible_after_drain_ticket']));
        $heldRows = array_values(array_filter($attemptedNextRows, static fn (array $row): bool => !$row['visible_after_drain_ticket']));

        return [
            'status' => self::status($canExposeNext, $ticketAdmitted, $baseExposed),
            'base' => $base,
            'drain_ticket_prefix' => $prefix,
            'drain_ticket' => $actualTicket,
            'expected_drain_ticket' => $expectedTicket,
            'drain_ticket_matches' => $ticketMatches,
            'base_next_exposed_before_ticket' => $baseExposed,
            'next_source_exposed_after_drain_ticket' => $canExposeNext,
            'current_source_rows' => $currentRows,
            'attempted_next_source_rows' => $attemptedNextRows,
            'visible_rows' => $visibleRows,
            'held_rows' => $heldRows,
            'visible_returning_rows' => array_column($visibleRows, 'returning'),
            'held_returning_rows' => array_column($heldRows, 'returning'),
            'block_reasons' => $blockReasons,
            'ticket_plan' => [
                'prefix' => $prefix,
                'required_checkpoint_count' => count(self::tokenList($base['required_current_checkpoints'] ?? [], 'required current checkpoints')),
                'acknowledged_checkpoint_count' => count(self::tokenList($base['acknowledged_current_checkpoints'] ?? [], 'acknowledged current checkpoints')),
                'expected_ticket' => $expectedTicket,
                'actual_ticket' => $actualTicket,
                'ticket_matches' => $ticketMatches,
                'next_row_count' => count($attemptedNextRows),
                'held_next_row_count' => count($heldRows),
                'resume_after_token' => $currentRows === [] ? null : $currentRows[array_key_last($currentRows)]['resume_token'],
                'blocked_at_token' => $canExposeNext ? null : ($attemptedNextRows[0]['resume_token'] ?? null),
            ],
            'counts' => [
                'current_rows' => count($currentRows),
                'attempted_next_rows' => count($attemptedNextRows),
                'visible_rows' => count($visibleRows),
                'held_rows' => count($heldRows),
                'block_reasons' => count($blockReasons),
            ],
            'yield_boundary' => $canExposeNext
                ? 'recursive-view-returning-current-source-next187-drain-ticket-next-exposed'
                : 'recursive-view-returning-current-source-next187-drain-ticket-held',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next187',
                'sqlite-returning-current-source-drain-ticket',
                'wordpress-recursive-view-returning-current-source-next187',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses recursive view trigger RETURNING checkpoint handoff and adds current-source drain ticket validation',
            'non_overlap' => 'adds drain-ticket validation after accepted next184 checkpoint acknowledgement handoff; avoids next183 rollback reset and next184 checkpoint exposure behavior',
        ];
    }

    /**
     * @param mixed $rows
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function ticketRows(mixed $rows, string $ticket, bool $visible, array $blockReasons): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next187 rows are malformed');
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning'], $row['resume_token'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next187 row envelope is malformed');
            }
            $out[] = $row + [
                'drain_ticket' => $ticket,
                'visible_after_drain_ticket' => $visible,
                'held_by_drain_ticket_reasons' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockReasons(mixed $baseReasons, bool $ticketMatches, bool $baseExposed): array
    {
        $reasons = self::strings($baseReasons, 'base block reasons');
        if (!$ticketMatches) {
            $reasons[] = 'current-source-drain-ticket-mismatch';
        }
        if (!$baseExposed && $reasons === []) {
            $reasons[] = 'checkpoint-handoff-not-exposed';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $canExposeNext, bool $ticketMatches, bool $baseExposed): string
    {
        if ($canExposeNext) {
            return 'trigger-recursive-view-returning-current-source-next187-next-exposed';
        }
        if (!$ticketMatches) {
            return 'trigger-recursive-view-returning-current-source-next187-drain-ticket-held';
        }
        if (!$baseExposed) {
            return 'trigger-recursive-view-returning-current-source-next187-checkpoint-handoff-held';
        }

        return 'trigger-recursive-view-returning-current-source-next187-next-held';
    }

    /**
     * @param mixed $tokens
     */
    private static function ticket(string $prefix, mixed $tokens): string
    {
        $normalized = self::tokenList($tokens, 'drain ticket checkpoint tokens');
        sort($normalized);

        return $prefix . ':' . substr(hash('sha256', implode('|', $normalized)), 0, 16);
    }

    /**
     * @param mixed $tokens
     * @return list<string>
     */
    private static function tokenList(mixed $tokens, string $label): array
    {
        if (!is_array($tokens) || !array_is_list($tokens)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next187 {$label} must be a list");
        }

        return array_values(array_unique(array_map(static fn (mixed $token): string => self::token((string) $token, $label), $tokens)));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function strings(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next187 {$label} must be a list");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next187 {$label} is malformed");
        }

        return $value;
    }
}
