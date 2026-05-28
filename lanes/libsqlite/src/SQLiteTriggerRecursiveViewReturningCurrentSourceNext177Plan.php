<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext177Plan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int} $options
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
        $cursor = self::token((string) ($options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_177'), 'cursor name');
        $currentGeneration = self::token((string) ($options['current_generation'] ?? 'wp-current-returning-177'), 'current generation');
        $nextGeneration = self::token((string) ($options['next_generation'] ?? 'wp-next-returning-177'), 'next generation');
        $token = self::token((string) ($options['reprepare_token'] ?? 'wp.reprepare.177'), 'reprepare token');
        $expectedToken = self::token((string) ($options['expected_reprepare_token'] ?? $token), 'expected reprepare token');
        $pageSize = self::positiveInt($options['page_size'] ?? 3, 'page size');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext172Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            [
                'key' => $options['key'] ?? 'option_name',
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_returning_next177',
                'admit_next_source' => $options['admit_next_source'] ?? false,
                'recursive_triggers' => $options['recursive_triggers'] ?? true,
                'max_depth' => $options['max_depth'] ?? 2,
                'child_suffix' => $options['child_suffix'] ?? ':child',
            ],
        );

        $tokenMatches = $token === $expectedToken;
        $currentRows = self::annotateRows($base['current_yield_stream'], $cursor, $currentGeneration, 'current', true, 0, $pageSize);
        $attemptedNextRows = self::annotateRows(
            $base['attempted_next_yield_stream'],
            $cursor,
            $nextGeneration,
            'next',
            (bool) ($base['next_yield_stream'] !== []) && $tokenMatches,
            count($currentRows),
            $pageSize,
        );
        $attemptedRows = array_merge($currentRows, $attemptedNextRows);
        $visibleRows = array_values(array_filter($attemptedRows, static fn (array $row): bool => $row['visible_after_current_source']));
        $heldRows = array_values(array_filter($attemptedRows, static fn (array $row): bool => !$row['visible_after_current_source']));

        $currentLastToken = $currentRows === [] ? null : $currentRows[array_key_last($currentRows)]['resume_token'];
        $nextFirstToken = $attemptedNextRows === [] ? null : $attemptedNextRows[0]['resume_token'];
        $admittedNextRows = array_values(array_filter($attemptedNextRows, static fn (array $row): bool => $row['visible_after_current_source']));
        $heldNextRows = array_values(array_filter($attemptedNextRows, static fn (array $row): bool => !$row['visible_after_current_source']));

        return [
            'status' => self::status($base, $tokenMatches, $admittedNextRows, $heldNextRows),
            'savepoint' => $base['savepoint'],
            'cursor' => $cursor,
            'current_generation' => $currentGeneration,
            'next_generation' => $nextGeneration,
            'reprepare_token' => $token,
            'expected_reprepare_token' => $expectedToken,
            'reprepare_token_matches' => $tokenMatches,
            'base' => $base,
            'page_size' => $pageSize,
            'current_source_rows' => $currentRows,
            'attempted_next_source_rows' => $attemptedNextRows,
            'visible_rows' => $visibleRows,
            'held_rows' => $heldRows,
            'visible_returning_rows' => array_column($visibleRows, 'returning'),
            'held_returning_rows' => array_column($heldRows, 'returning'),
            'current_resume_tokens' => array_column($currentRows, 'resume_token'),
            'attempted_next_resume_tokens' => array_column($attemptedNextRows, 'resume_token'),
            'visible_resume_tokens' => array_column($visibleRows, 'resume_token'),
            'held_resume_tokens' => array_column($heldRows, 'resume_token'),
            'current_last_resume_token' => $currentLastToken,
            'next_first_resume_token' => $nextFirstToken,
            'resume_boundary' => [
                'current_drained_before_next' => self::currentDrainedBeforeNext($attemptedRows),
                'current_last_resume_token' => $currentLastToken,
                'next_first_resume_token' => $nextFirstToken,
                'next_admitted' => $admittedNextRows !== [],
                'next_held' => $heldNextRows !== [],
                'held_reason' => $heldNextRows === []
                    ? null
                    : ($tokenMatches ? 'next source waits for current RETURNING cursor drain' : 'next source waits for matching reprepare token'),
            ],
            'counts' => [
                'current' => count($currentRows),
                'attempted_next' => count($attemptedNextRows),
                'visible' => count($visibleRows),
                'held' => count($heldRows),
                'pages' => self::pageCount(count($attemptedRows), $pageSize),
            ],
            'yield_boundary' => $heldNextRows === []
                ? 'recursive-view-returning-current-source-resume-next177-next-visible'
                : 'recursive-view-returning-current-source-resume-next177-next-held',
            'dependency_closure' => 'no new support component needed; reuses recursive view trigger RETURNING current-source cursor modeling',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next177',
                'sqlite-returning-current-source-resume-token-boundary',
                'wordpress-recursive-view-returning-current-source-next177',
            ]))),
            'non_overlap' => 'adds resume-token current-source RETURNING admission over accepted recursive view trigger rows; avoids accepted next172 source pinning and next174 duplicate-key watermark behavior',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function annotateRows(mixed $rows, string $cursor, string $generation, string $phase, bool $visible, int $offset, int $pageSize): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next177 rows are malformed');
        }

        $out = [];
        foreach ($rows as $ordinal => $row) {
            if (!is_array($row) || !isset($row['returning'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next177 row is malformed');
            }
            $absolute = $offset + (int) $ordinal;
            $returning = $row['returning'];
            if (!is_array($returning)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next177 returning row is malformed');
            }
            $out[] = [
                'cursor' => $cursor,
                'phase' => $phase,
                'source' => $row['source'] ?? null,
                'trigger_source' => $row['trigger_source'] ?? null,
                'trigger' => $row['trigger'] ?? null,
                'ordinal' => (int) ($row['ordinal'] ?? $ordinal),
                'depth' => (int) ($row['depth'] ?? 0),
                'event' => $row['event'] ?? null,
                'generation' => $generation,
                'resume_ordinal' => $absolute,
                'resume_page' => intdiv($absolute, $pageSize),
                'resume_token' => $cursor . ':' . $generation . ':' . $absolute,
                'visible_after_current_source' => $visible,
                'returning' => $returning,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function currentDrainedBeforeNext(array $rows): bool
    {
        $seenNext = false;
        foreach ($rows as $row) {
            if (($row['phase'] ?? null) === 'next') {
                $seenNext = true;
                continue;
            }
            if ($seenNext && ($row['phase'] ?? null) === 'current') {
                return false;
            }
        }

        return true;
    }

    private static function pageCount(int $rows, int $pageSize): int
    {
        if ($rows === 0) {
            return 0;
        }

        return (int) ceil($rows / $pageSize);
    }

    private static function status(array $base, bool $tokenMatches, array $admittedNextRows, array $heldNextRows): string
    {
        if (!$tokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next177-reprepare-held';
        }
        if ($admittedNextRows !== []) {
            return 'trigger-recursive-view-returning-current-source-next177-next-admitted';
        }
        if ($heldNextRows !== [] || ($base['attempted_next_yield_stream'] ?? []) !== []) {
            return 'trigger-recursive-view-returning-current-source-next177-current-drained-next-held';
        }

        return 'trigger-recursive-view-returning-current-source-next177-current-only';
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next177 {$label} is malformed");
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        $int = (int) $value;
        if ($int < 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next177 {$label} must be positive");
        }

        return $int;
    }
}
