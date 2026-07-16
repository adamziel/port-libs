<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicPlan;

$tests = [];

$columns = ['setting_id', 'key_name', 'key_value', 'load_policy', 'priority', 'version'];
$defaults = ['load_policy' => 'lazy', 'priority' => 0, 'version' => 0];

for ($i = 0; $i < 125; ++$i) {
    $prefix = 'tenant_' . $i . '_';
    $basePriority = 1000 + ($i * 10);

    $repeatedSource = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        [
            ['setting_id' => 1, 'key_name' => $prefix . 'alpha', 'key_value' => 'seed-alpha', 'load_policy' => 'active', 'priority' => 2, 'version' => 0],
            ['setting_id' => 3, 'key_name' => $prefix . 'gamma', 'key_value' => 'seed-gamma', 'load_policy' => 'active', 'priority' => 4, 'version' => 0],
        ],
        [
            ['setting_id' => 1, 'key_name' => $prefix . 'alpha', 'key_value' => 'raise-alpha', 'load_policy' => 'active', 'priority' => 8],
            ['setting_id' => 2, 'key_name' => $prefix . 'beta', 'key_value' => 'insert-beta', 'load_policy' => 'active', 'priority' => 11],
            ['setting_id' => 3, 'key_name' => $prefix . 'gamma', 'key_value' => 'lower-gamma', 'load_policy' => 'active', 'priority' => 1],
            ['setting_id' => 2, 'key_name' => $prefix . 'beta', 'key_value' => 'raise-beta', 'load_policy' => 'active', 'priority' => 15],
            ['setting_id' => 1, 'key_name' => $prefix . 'alpha', 'key_value' => 'lower-alpha', 'load_policy' => 'active', 'priority' => 4],
            ['setting_id' => 1, 'key_name' => $prefix . 'alpha', 'key_value' => 'final-alpha', 'load_policy' => 'active', 'priority' => 99],
        ],
        $columns,
        ['setting_id'],
        $defaults,
        [
            'key_value' => 'excluded.key_value',
            'priority' => 'excluded.priority',
            'version' => static fn (array $old): int => (int) $old['version'] + 1,
        ],
        ['setting_id', 'key_name', 'key_value', 'priority', 'version'],
        null,
        false,
        'setting_id',
        [['key_name']],
        static fn (array $old, array $candidate): bool => (int) $old['priority'] < (int) $candidate['priority'],
    );

    $partialIndex = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        [
            ['setting_id' => 10, 'key_name' => $prefix . 'draft', 'key_value' => 'draft-old', 'load_policy' => 'draft', 'priority' => $basePriority + 1, 'version' => 0],
            ['setting_id' => 20, 'key_name' => $prefix . 'live', 'key_value' => 'live-old', 'load_policy' => 'active', 'priority' => $basePriority + 2, 'version' => 0],
            ['setting_id' => 30, 'key_name' => $prefix . 'archive', 'key_value' => 'archive-old', 'load_policy' => 'archived', 'priority' => $basePriority + 3, 'version' => 0],
        ],
        [
            ['setting_id' => 11, 'key_name' => $prefix . 'draft', 'key_value' => 'draft-new', 'load_policy' => 'draft', 'priority' => $basePriority + 4],
            ['setting_id' => 21, 'key_name' => $prefix . 'live', 'key_value' => 'live-new', 'load_policy' => 'active', 'priority' => $basePriority + 5],
            ['setting_id' => 31, 'key_name' => $prefix . 'archive', 'key_value' => 'archive-new', 'load_policy' => 'active', 'priority' => $basePriority + 6],
            ['setting_id' => 32, 'key_name' => $prefix . 'archive', 'key_value' => 'archive-cold', 'load_policy' => 'archived', 'priority' => $basePriority + 7],
        ],
        $columns,
        ['key_name'],
        $defaults,
        [
            'key_value' => 'excluded.key_value',
            'load_policy' => 'excluded.load_policy',
            'priority' => 'excluded.priority',
            'version' => static fn (array $old): int => (int) $old['version'] + 1,
        ],
        ['setting_id', 'key_name', 'key_value', 'load_policy', 'priority', 'version'],
        static fn (array $row): bool => ($row['load_policy'] ?? null) === 'active',
    );

    $skipReturning = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        [
            ['setting_id' => 40, 'key_name' => $prefix . 'stable', 'key_value' => 'old-stable', 'load_policy' => 'active', 'priority' => 40, 'version' => 1],
        ],
        [
            ['setting_id' => 40, 'key_name' => $prefix . 'stable-rowid', 'key_value' => 'rowid-conflict', 'load_policy' => 'active', 'priority' => 41],
            ['setting_id' => 41, 'key_name' => $prefix . 'stable', 'key_value' => 'name-conflict', 'load_policy' => 'active', 'priority' => 42],
            ['setting_id' => 42, 'key_name' => $prefix . 'fresh', 'key_value' => 'fresh-row', 'load_policy' => 'active', 'priority' => 43],
        ],
        $columns,
        ['setting_id'],
        $defaults,
        [],
        ['setting_id', 'key_name', 'key_value'],
        null,
        true,
        'setting_id',
        [['key_name']],
    );

    $wildcardReturning = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        [
            ['setting_id' => 50, 'key_name' => $prefix . 'one', 'key_value' => 'one-old', 'load_policy' => 'active', 'priority' => 1, 'version' => 0],
            ['setting_id' => 60, 'key_name' => $prefix . 'two', 'key_value' => 'two-old', 'load_policy' => 'lazy', 'priority' => 2, 'version' => 0],
        ],
        [
            ['setting_id' => 50, 'key_name' => $prefix . 'one', 'key_value' => 'one-updated', 'load_policy' => 'active', 'priority' => 3, 'version' => 7],
            ['setting_id' => 70, 'key_name' => $prefix . 'three', 'key_value' => 'three-inserted', 'priority' => 4, 'version' => 8],
            ['setting_id' => 60, 'key_name' => $prefix . 'two', 'key_value' => 'two-updated', 'load_policy' => 'eager', 'priority' => 5, 'version' => 9],
        ],
        $columns,
        ['key_name'],
        $defaults,
        [
            'key_value' => 'excluded.key_value',
            'load_policy' => 'excluded.load_policy',
            'priority' => 'excluded.priority',
            'version' => 'excluded.version',
        ],
        '*',
    );

    $casePrefix = sprintf('real upstream dynamic upsert returning next %03d ', $i);

    $tests[$casePrefix . 'upsert2-200 chains later conflicts through current row image'] = static function (TestRunner $t) use ($repeatedSource): void {
        $rows = $repeatedSource()['after'];
        usort($rows, static fn (array $left, array $right): int => $left['setting_id'] <=> $right['setting_id']);
        $t->same([99, 15, 4], array_column($rows, 'priority'));
    };
    $tests[$casePrefix . 'upsert2-200 returns only inserted and successful updates'] = static function (TestRunner $t) use ($repeatedSource): void {
        $t->same(['update', 'insert', 'skip', 'update', 'skip', 'update'], array_column($repeatedSource()['decisions'], 'action'));
    };
    $tests[$casePrefix . 'upsert2-200 returning row order follows candidate order'] = static function (TestRunner $t) use ($repeatedSource): void {
        $t->same([1, 2, 2, 1], array_column($repeatedSource()['returning_rows'], 'setting_id'));
    };
    $tests[$casePrefix . 'upsert2-200 version increments from current row image'] = static function (TestRunner $t) use ($repeatedSource): void {
        $t->same([1, 0, 1, 2], array_column($repeatedSource()['returning_rows'], 'version'));
    };

    $tests[$casePrefix . 'upsert1-320 inserts duplicate outside partial unique index'] = static function (TestRunner $t) use ($partialIndex): void {
        $t->same('insert', $partialIndex()['decisions'][0]['action']);
    };
    $tests[$casePrefix . 'upsert1-320 updates row inside partial unique index'] = static function (TestRunner $t) use ($partialIndex): void {
        $t->same('update', $partialIndex()['decisions'][1]['action']);
    };
    $tests[$casePrefix . 'upsert1-320 active incoming can conflict with archived base row'] = static function (TestRunner $t) use ($partialIndex): void {
        $t->same('insert', $partialIndex()['decisions'][2]['action']);
    };
    $tests[$casePrefix . 'upsert1-320 archived incoming remains distinct from partial index'] = static function (TestRunner $t) use ($partialIndex): void {
        $t->same('insert', $partialIndex()['decisions'][3]['action']);
    };
    $tests[$casePrefix . 'upsert1-320 returning distinguishes insert and update actions'] = static function (TestRunner $t) use ($partialIndex): void {
        $t->same(['insert', 'update', 'insert', 'insert'], array_column($partialIndex()['returning_rows'], '_upsert_action'));
    };

    $tests[$casePrefix . 'upsert4-1 do nothing suppresses rowid conflict returning'] = static function (TestRunner $t) use ($skipReturning): void {
        $t->same(['skip', 'skip', 'insert'], array_column($skipReturning()['decisions'], 'action'));
    };
    $tests[$casePrefix . 'upsert4-1 do nothing records secondary unique conflict key'] = static function (TestRunner $t) use ($skipReturning, $prefix): void {
        $t->same(['key_name' => $prefix . 'stable'], $skipReturning()['decisions'][1]['conflict_key']);
    };
    $tests[$casePrefix . 'upsert4-1 do nothing returns only inserted row'] = static function (TestRunner $t) use ($skipReturning, $prefix): void {
        $t->same([$prefix . 'fresh'], array_column($skipReturning()['returning_rows'], 'key_name'));
    };

    $tests[$casePrefix . 'returning1-4.5 wildcard returning includes updated and inserted rows'] = static function (TestRunner $t) use ($wildcardReturning): void {
        $t->same(['update', 'insert', 'update'], array_column($wildcardReturning()['returning_rows'], '_upsert_action'));
    };
    $tests[$casePrefix . 'returning1-4.5 wildcard returning fills default values for omitted insert columns'] = static function (TestRunner $t) use ($wildcardReturning): void {
        $t->same('lazy', $wildcardReturning()['returning_rows'][1]['load_policy']);
    };
    $tests[$casePrefix . 'returning1-4.5 wildcard returning exposes old image for updates'] = static function (TestRunner $t) use ($wildcardReturning): void {
        $t->same(['one-old', 'two-old'], array_column(array_values(array_filter(array_column($wildcardReturning()['returning_rows'], '_old'))), 'key_value'));
    };
    $tests[$casePrefix . 'returning1-4.5 final table preserves source row cardinality'] = static function (TestRunner $t) use ($wildcardReturning): void {
        $t->same(3, count($wildcardReturning()['after']));
    };
}

return $tests;
