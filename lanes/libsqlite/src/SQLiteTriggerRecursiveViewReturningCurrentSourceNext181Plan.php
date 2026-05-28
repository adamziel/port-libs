<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext181Plan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string,cursor_name?:string,current_generation?:string,next_generation?:string,reprepare_token?:string,expected_reprepare_token?:string,page_size?:int,checkpoint_name?:string,commit_visible_checkpoints?:bool} $options
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
        $checkpoint = self::token((string) ($options['checkpoint_name'] ?? 'wp_recursive_view_returning_checkpoint_181'), 'checkpoint name');
        $commitVisible = (bool) ($options['commit_visible_checkpoints'] ?? true);
        $baseOptions = $options + [
            'cursor_name' => 'wp_recursive_view_returning_cursor_181',
            'current_generation' => 'wp-current-returning-181',
            'next_generation' => 'wp-next-returning-181',
            'savepoint' => 'wp_recursive_view_returning_next181',
        ];

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext177Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $baseOptions,
        );

        $groups = self::checkpointGroups($base['current_source_rows'], $base['attempted_next_source_rows']);
        $checkpoints = [];
        foreach ($groups as $key => $rows) {
            $checkpoints[] = self::checkpointRow($checkpoint, $key, $rows, $commitVisible);
        }

        $visible = array_values(array_filter($checkpoints, static fn (array $row): bool => $row['visible']));
        $pending = array_values(array_filter($checkpoints, static fn (array $row): bool => !$row['visible']));
        $durable = array_values(array_filter($visible, static fn (array $row): bool => $row['durable']));

        return [
            'status' => self::status($base, $pending),
            'checkpoint_name' => $checkpoint,
            'base' => $base,
            'checkpoints' => $checkpoints,
            'visible_checkpoints' => $visible,
            'pending_checkpoints' => $pending,
            'durable_checkpoints' => $durable,
            'checkpoint_tokens' => array_column($checkpoints, 'checkpoint_token'),
            'visible_checkpoint_tokens' => array_column($visible, 'checkpoint_token'),
            'pending_checkpoint_tokens' => array_column($pending, 'checkpoint_token'),
            'durable_checkpoint_tokens' => array_column($durable, 'checkpoint_token'),
            'last_visible_checkpoint' => $visible === [] ? null : $visible[array_key_last($visible)],
            'first_pending_checkpoint' => $pending[0] ?? null,
            'replay_plan' => [
                'current_generation' => $base['current_generation'],
                'next_generation' => $base['next_generation'],
                'next_admitted' => $base['resume_boundary']['next_admitted'],
                'pending_requires_reprepare' => $pending !== [],
                'resume_after_token' => $visible === [] ? null : $visible[array_key_last($visible)]['last_resume_token'],
                'blocked_at_token' => $pending[0]['first_resume_token'] ?? null,
            ],
            'counts' => [
                'checkpoints' => count($checkpoints),
                'visible' => count($visible),
                'pending' => count($pending),
                'durable' => count($durable),
                'rows_visible' => count($base['visible_rows']),
                'rows_pending' => count($base['held_rows']),
            ],
            'yield_boundary' => $pending === []
                ? 'recursive-view-returning-current-source-checkpoint-next181-all-visible'
                : 'recursive-view-returning-current-source-checkpoint-next181-next-pending',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next181',
                'sqlite-returning-cursor-checkpoint-source-boundary',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses recursive view trigger RETURNING cursor rows and checkpoint metadata',
            'non_overlap' => 'adds page checkpoint visibility/durability over accepted next177 resume tokens; avoids changing accepted next172 source pinning or next177 row admission',
        ];
    }

    /**
     * @param mixed $currentRows
     * @param mixed $nextRows
     * @return array<string,list<array<string,mixed>>>
     */
    private static function checkpointGroups(mixed $currentRows, mixed $nextRows): array
    {
        if (!is_array($currentRows) || !array_is_list($currentRows) || !is_array($nextRows) || !array_is_list($nextRows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next181 checkpoint rows are malformed');
        }

        $groups = [];
        foreach (array_merge($currentRows, $nextRows) as $row) {
            if (!is_array($row) || !isset($row['phase'], $row['generation'], $row['resume_page'], $row['resume_token'])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next181 row envelope is malformed');
            }
            $key = (string) $row['phase'] . ':' . (string) $row['generation'] . ':' . (string) $row['resume_page'];
            $groups[$key][] = $row;
        }

        return $groups;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function checkpointRow(string $checkpoint, string $key, array $rows, bool $commitVisible): array
    {
        [$phase, $generation, $page] = explode(':', $key, 3);
        $visible = array_values(array_unique(array_column($rows, 'visible_after_current_source'))) === [true];
        $names = [];
        foreach ($rows as $row) {
            $returning = $row['returning'] ?? [];
            $names[] = is_array($returning) ? ($returning['name'] ?? null) : null;
        }

        return [
            'checkpoint' => $checkpoint,
            'phase' => $phase,
            'generation' => $generation,
            'page' => (int) $page,
            'checkpoint_token' => $checkpoint . ':' . $generation . ':' . $page,
            'first_resume_token' => $rows[0]['resume_token'],
            'last_resume_token' => $rows[array_key_last($rows)]['resume_token'],
            'row_count' => count($rows),
            'names' => $names,
            'visible' => $visible,
            'durable' => $visible && $commitVisible,
            'source' => $rows[0]['source'] ?? null,
            'trigger_source' => $rows[0]['trigger_source'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $pending
     */
    private static function status(array $base, array $pending): string
    {
        if ($pending !== []) {
            return ($base['reprepare_token_matches'] ?? false)
                ? 'trigger-recursive-view-returning-current-source-next181-current-checkpointed-next-pending'
                : 'trigger-recursive-view-returning-current-source-next181-reprepare-checkpoint-pending';
        }

        return 'trigger-recursive-view-returning-current-source-next181-checkpoints-admitted';
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next181 {$label} is malformed");
        }

        return $value;
    }
}
