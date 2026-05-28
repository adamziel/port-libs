<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext180Plan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,current_source_token?:string,expected_current_source_token?:string,drain_ack_token?:string,expected_drain_ack_token?:string} $options
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
        $currentToken = self::token((string) ($options['current_source_token'] ?? 'wp.current.source.180'), 'current source token');
        $expectedCurrentToken = self::token((string) ($options['expected_current_source_token'] ?? $currentToken), 'expected current source token');
        $drainAck = self::token((string) ($options['drain_ack_token'] ?? 'wp.returning.drain.180'), 'drain ack token');
        $expectedDrainAck = self::token((string) ($options['expected_drain_ack_token'] ?? $drainAck), 'expected drain ack token');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext177Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentSourceMatches = $currentToken === $expectedCurrentToken;
        $drainAckMatches = $drainAck === $expectedDrainAck;
        $sourceChanged = self::viewSignature($currentView, $returning) !== self::viewSignature($nextView, $returning);
        $canAdmitNext = $currentSourceMatches
            && $drainAckMatches
            && ($base['resume_boundary']['current_drained_before_next'] ?? false) === true
            && ($base['reprepare_token_matches'] ?? false) === true
            && (($base['base']['status'] ?? null) === 'trigger-recursive-view-returning-current-source-next172-next-admitted');

        $currentFrame = self::frame($currentView, $returning, 'current', $currentToken, $expectedCurrentToken, true);
        $nextFrame = self::frame($nextView, $returning, 'next', $drainAck, $expectedDrainAck, $canAdmitNext);
        $currentRows = self::sourceRows($base['current_source_rows'] ?? [], $currentFrame, true, []);
        $blockReasons = self::blockReasons($base, $currentSourceMatches, $drainAckMatches, $sourceChanged, $canAdmitNext);
        $nextRows = self::sourceRows($base['attempted_next_source_rows'] ?? [], $nextFrame, $canAdmitNext, $blockReasons);
        $visibleRows = array_values(array_filter(array_merge($currentRows, $nextRows), static fn (array $row): bool => $row['visible_after_source_snapshot']));
        $heldRows = array_values(array_filter($nextRows, static fn (array $row): bool => !$row['visible_after_source_snapshot']));

        return [
            'status_next180' => self::status($canAdmitNext, $currentSourceMatches, $drainAckMatches, $base),
            'savepoint' => $base['savepoint'],
            'cursor' => $base['cursor'],
            'base' => $base,
            'current_source_token_next180' => $currentToken,
            'expected_current_source_token_next180' => $expectedCurrentToken,
            'current_source_token_matches_next180' => $currentSourceMatches,
            'drain_ack_token_next180' => $drainAck,
            'expected_drain_ack_token_next180' => $expectedDrainAck,
            'drain_ack_token_matches_next180' => $drainAckMatches,
            'source_changed_next180' => $sourceChanged,
            'next_source_admitted_next180' => $canAdmitNext,
            'source_frames_next180' => [$currentFrame, $nextFrame],
            'current_source_frame_next180' => $currentFrame,
            'next_source_frame_next180' => $nextFrame,
            'current_source_rows_next180' => $currentRows,
            'attempted_next_source_rows_next180' => $nextRows,
            'visible_rows_next180' => $visibleRows,
            'held_rows_next180' => $heldRows,
            'visible_returning_rows_next180' => array_column($visibleRows, 'returning'),
            'held_returning_rows_next180' => array_column($heldRows, 'returning'),
            'block_reasons_next180' => $blockReasons,
            'source_snapshot_next180' => [
                'current_signature' => $currentFrame['source_signature'],
                'next_signature' => $nextFrame['source_signature'],
                'current_rows_visible' => count($currentRows),
                'attempted_next_rows' => count($nextRows),
                'held_next_rows' => count($heldRows),
                'next_rows_visible' => count($nextRows) - count($heldRows),
                'current_source_frozen_until_reset' => true,
                'next_source_requires_reprepare' => $sourceChanged,
            ],
            'yield_boundary_next180' => $canAdmitNext
                ? 'recursive-view-returning-next180-source-snapshot-next-admitted'
                : 'recursive-view-returning-next180-current-source-snapshot-held',
            'dependency_closure_next180' => 'no new support component needed; reuses recursive view trigger RETURNING current-source cursor and source snapshot modeling',
            'dependencies_next180' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next180',
                'sqlite-returning-current-source-snapshot-admission',
                'wordpress-recursive-view-returning-current-source-next180',
            ]))),
            'non_overlap_next180' => 'adds current/next source-signature admission over accepted next177 resume tokens; avoids next172 source pinning, next174 watermarking, next175 savepoint release, and next177 cursor-token coverage',
        ];
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function frame(array $view, array $returning, string $phase, string $token, string $expectedToken, bool $admitted): array
    {
        $columns = self::strings($view['columns'] ?? [], "{$phase} view columns");
        $mapping = self::mapping($view['mapping'] ?? [], "{$phase} view mapping");

        return [
            'phase' => $phase,
            'view' => self::identifier((string) ($view['name'] ?? ''), "{$phase} view name"),
            'source' => self::token((string) ($view['source'] ?? ''), "{$phase} source"),
            'trigger' => self::identifier((string) ($view['trigger'] ?? ''), "{$phase} trigger"),
            'trigger_source' => self::token((string) ($view['trigger_source'] ?? ''), "{$phase} trigger source"),
            'columns' => $columns,
            'mapping' => $mapping,
            'returning_aliases' => self::returningAliases($returning),
            'source_signature' => self::viewSignature($view, $returning),
            'token' => $token,
            'expected_token' => $expectedToken,
            'token_matches' => $token === $expectedToken,
            'admitted' => $admitted,
        ];
    }

    /**
     * @param mixed $rows
     * @param array<string,mixed> $frame
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function sourceRows(mixed $rows, array $frame, bool $visible, array $blockReasons): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite trigger recursive view RETURNING next180 rows are malformed');
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning'])) {
                throw new InvalidArgumentException('SQLite trigger recursive view RETURNING next180 row is malformed');
            }
            $out[] = $row + [
                'source_signature_next180' => $frame['source_signature'],
                'source_frame_phase_next180' => $frame['phase'],
                'source_frame_token_next180' => $frame['token'],
                'visible_after_source_snapshot' => $visible,
                'held_by_source_snapshot_reasons' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function blockReasons(array $base, bool $currentSourceMatches, bool $drainAckMatches, bool $sourceChanged, bool $canAdmitNext): array
    {
        if ($canAdmitNext) {
            return [];
        }

        $reasons = [];
        if (!$currentSourceMatches) {
            $reasons[] = 'current-source-token-mismatch';
        }
        if (!$drainAckMatches) {
            $reasons[] = 'current-returning-drain-ack-mismatch';
        }
        if (($base['resume_boundary']['current_drained_before_next'] ?? false) !== true) {
            $reasons[] = 'current-returning-cursor-not-drained';
        }
        if (($base['reprepare_token_matches'] ?? false) !== true) {
            $reasons[] = 'reprepare-token-mismatch';
        }
        if (($base['base']['status'] ?? null) !== 'trigger-recursive-view-returning-current-source-next172-next-admitted') {
            $reasons[] = $sourceChanged ? 'changed-next-source-awaits-reprepare' : 'next-source-admission-not-requested';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $canAdmitNext, bool $currentSourceMatches, bool $drainAckMatches, array $base): string
    {
        if ($canAdmitNext) {
            return 'trigger-recursive-view-returning-current-source-next180-next-admitted';
        }
        if (!$currentSourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next180-current-source-token-held';
        }
        if (!$drainAckMatches) {
            return 'trigger-recursive-view-returning-current-source-next180-drain-ack-held';
        }
        if (($base['reprepare_token_matches'] ?? false) !== true) {
            return 'trigger-recursive-view-returning-current-source-next180-reprepare-held';
        }

        return 'trigger-recursive-view-returning-current-source-next180-current-source-held';
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function viewSignature(array $view, array $returning): string
    {
        $payload = [
            'name' => (string) ($view['name'] ?? ''),
            'source' => (string) ($view['source'] ?? ''),
            'trigger' => (string) ($view['trigger'] ?? ''),
            'trigger_source' => (string) ($view['trigger_source'] ?? ''),
            'columns' => array_values((array) ($view['columns'] ?? [])),
            'mapping' => (array) ($view['mapping'] ?? []),
            'returning' => self::returningAliases($returning),
        ];

        return substr(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<string>
     */
    private static function returningAliases(array $returning): array
    {
        $aliases = [];
        foreach ($returning as $index => $term) {
            if (is_string($term)) {
                $aliases[] = $term;
                continue;
            }
            if (is_array($term)) {
                $aliases[] = (string) ($term['as'] ?? $term['expr'] ?? "expr_{$index}");
                continue;
            }
            if (is_callable($term)) {
                $aliases[] = "callable_{$index}";
                continue;
            }
            throw new InvalidArgumentException('SQLite trigger recursive view RETURNING next180 returning term is malformed');
        }

        if ($aliases === []) {
            throw new InvalidArgumentException('SQLite trigger recursive view RETURNING next180 returning aliases cannot be empty');
        }

        return $aliases;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function strings(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new InvalidArgumentException("SQLite trigger recursive view RETURNING next180 {$label} must be a non-empty list");
        }

        return array_map(static fn (mixed $value): string => self::identifier((string) $value, $label), $values);
    }

    /**
     * @param mixed $mapping
     * @return array<string,string>
     */
    private static function mapping(mixed $mapping, string $label): array
    {
        if (!is_array($mapping) || $mapping === []) {
            throw new InvalidArgumentException("SQLite trigger recursive view RETURNING next180 {$label} must not be empty");
        }

        $out = [];
        foreach ($mapping as $from => $to) {
            $out[self::identifier((string) $from, $label)] = self::identifier((string) $to, $label);
        }

        return $out;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view RETURNING next180 {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view RETURNING next180 {$label} is malformed");
        }

        return $value;
    }
}
