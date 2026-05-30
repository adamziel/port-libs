<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicPlan;

$tests = [];

$assignment = [
    'b' => static fn (array $current, array $incoming): int => (int) $incoming['b'],
    'c' => static fn (array $current): int => (int) $current['c'] + 1,
];

for ($i = 0; $i < 120; ++$i) {
    $base = 1000 + ($i * 10);
    $target = match ($i % 3) {
        0 => ['e'],
        1 => ['a'],
        default => ['b'],
    };
    $rows = [[
        'a' => $base + 1,
        'b' => $base + 2,
        'c' => $base + 3,
        'd' => $base + 4,
        'e' => $base + 5,
    ]];
    $incoming = [[
        'a' => $base + 1,
        'b' => $base + 2,
        'c' => $base + 33,
        'd' => $base + 44,
        'e' => $base + 5,
    ]];
    $plan = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        $incoming,
        [[
            'target' => $target,
            'assignments' => ['c' => static fn (array $current, array $excluded): int => (int) $excluded['c']],
        ]],
        [['a'], ['b'], ['e']],
    );

    $prefix = sprintf('upsert1-700-target-priority variant %03d ', $i);
    $tests[$prefix . 'updates the row selected by explicit target'] = static function (TestRunner $t) use ($plan, $base): void {
        $t->same($base + 33, $plan()['after'][0]['c']);
    };
    $tests[$prefix . 'keeps nonassigned d value'] = static function (TestRunner $t) use ($plan, $base): void {
        $t->same($base + 4, $plan()['after'][0]['d']);
    };
}

for ($i = 0; $i < 120; ++$i) {
    $base = 2000 + ($i * 20);
    $rows = [
        ['a' => $base + 1, 'b' => $base + 2, 'c' => 0],
        ['a' => $base + 3, 'b' => $base + 4, 'c' => 0],
    ];
    $incoming = [
        ['a' => $base + 1, 'b' => $base + 8, 'c' => 0],
        ['a' => $base + 2, 'b' => $base + 11, 'c' => 0],
        ['a' => $base + 3, 'b' => $base + 1, 'c' => 0],
        ['a' => $base + 2, 'b' => $base + 15, 'c' => 0],
        ['a' => $base + 1, 'b' => $base + 4, 'c' => 0],
        ['a' => $base + 1, 'b' => $base + 99, 'c' => 0],
    ];
    $plan = static fn (): array => SQLiteUpsertDoUpdateWherePlan::execute(
        $rows,
        $incoming,
        ['a'],
        $assignment,
        static fn (array $current, array $excluded): bool => (int) $current['b'] < (int) $excluded['b'],
    );

    $prefix = sprintf('upsert2-200 repeated source variant %03d ', $i);
    $tests[$prefix . 'chains later conflicts through current row image'] = static function (TestRunner $t) use ($plan, $base): void {
        $rows = $plan()['after'];
        usort($rows, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);
        $t->same([$base + 99, $base + 15, $base + 4], array_column($rows, 'b'));
    };
    $tests[$prefix . 'counts only insert and successful updates'] = static function (TestRunner $t) use ($plan): void {
        $t->same(4, $plan()['changes']);
    };
    $tests[$prefix . 'records skipped lower excluded value'] = static function (TestRunner $t) use ($plan, $base): void {
        $t->same([$base + 3, $base + 1], [$plan()['skipped_rows'][0]['a'], $plan()['skipped_rows'][0]['b']]);
    };
}

for ($i = 0; $i < 100; ++$i) {
    $base = 4000 + ($i * 10);
    $rows = [['a' => $base + 1, 'b' => $base + 2, 'c' => 1]];
    $incoming = [['a' => $base + 1, 'b' => $base + 2, 'c' => 0]];
    $plan = static fn (): array => SQLiteUpsertDoUpdateWherePlan::execute(
        $rows,
        $incoming,
        ['a'],
        $assignment,
        static fn (array $current): bool => (int) $current['c'] < 0,
    );

    $prefix = sprintf('upsert2-320 failed where variant %03d ', $i);
    $tests[$prefix . 'does not update conflicted row'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['after'][0]['c']);
    };
    $tests[$prefix . 'returns no row when update where is false'] = static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan()['returning_rows']);
    };
}

$columns = ['id', 'key_name', 'value_text', 'load_policy', 'score'];
$defaults = ['load_policy' => 'lazy', 'score' => 0];
for ($i = 0; $i < 120; ++$i) {
    $prefixKey = 'setting_' . $i . '_';
    $rows = [
        ['id' => 1, 'key_name' => $prefixKey . 'alpha', 'value_text' => 'old-alpha', 'load_policy' => 'lazy', 'score' => 1],
        ['id' => 2, 'key_name' => $prefixKey . 'beta', 'value_text' => 'old-beta', 'load_policy' => 'eager', 'score' => 2],
        ['id' => 3, 'key_name' => $prefixKey . 'archive', 'value_text' => 'cold', 'load_policy' => 'archived', 'score' => 3],
    ];
    $incoming = [
        ['id' => 8, 'key_name' => $prefixKey . 'alpha', 'value_text' => 'new-alpha', 'load_policy' => 'eager', 'score' => 10],
        ['id' => 4, 'key_name' => $prefixKey . 'delta', 'value_text' => 'new-delta', 'score' => 20],
        ['id' => 9, 'key_name' => $prefixKey . 'beta', 'value_text' => 'new-beta', 'load_policy' => 'lazy', 'score' => 30],
        ['id' => 5, 'key_name' => null, 'value_text' => 'anonymous', 'score' => 40],
        ['id' => 10, 'key_name' => $prefixKey . 'archive', 'value_text' => 'warm', 'load_policy' => 'active', 'score' => 50],
    ];
    $plan = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        $rows,
        $incoming,
        $columns,
        ['key_name'],
        $defaults,
        ['value_text' => 'excluded.value_text', 'load_policy' => 'excluded.load_policy', 'score' => 'excluded.score'],
        '*',
    );

    $prefix = sprintf('returning1-4.5 mixed upsert returning variant %03d ', $i);
    $tests[$prefix . 'preserves statement returning order'] = static function (TestRunner $t) use ($plan, $prefixKey): void {
        $t->same([$prefixKey . 'alpha', $prefixKey . 'delta', $prefixKey . 'beta', null, $prefixKey . 'archive'], array_column($plan()['returning_rows'], 'key_name'));
    };
    $tests[$prefix . 'annotates insert and update actions'] = static function (TestRunner $t) use ($plan): void {
        $t->same(['update', 'insert', 'update', 'insert', 'update'], array_column($plan()['returning_rows'], '_upsert_action'));
    };
    $tests[$prefix . 'keeps old image for updates only'] = static function (TestRunner $t) use ($plan): void {
        $oldRows = array_values(array_filter(
            array_column($plan()['returning_rows'], '_old'),
            static fn (mixed $row): bool => is_array($row),
        ));
        $t->same(['old-alpha', 'old-beta', 'cold'], array_column($oldRows, 'value_text'));
    };
}

for ($i = 0; $i < 100; ++$i) {
    $base = 6000 + ($i * 10);
    $rows = [
        ['id' => 1, 'key_name' => 'draft_' . $i, 'value_text' => 'old-draft', 'load_policy' => 'draft', 'score' => $base + 1],
        ['id' => 2, 'key_name' => 'live_' . $i, 'value_text' => 'old-live', 'load_policy' => 'active', 'score' => $base + 2],
    ];
    $incoming = [
        ['key_name' => 'draft_' . $i, 'value_text' => 'new-draft', 'load_policy' => 'draft', 'score' => $base + 3],
        ['key_name' => 'live_' . $i, 'value_text' => 'new-live', 'load_policy' => 'active', 'score' => $base + 4],
        ['key_name' => 'live_' . $i, 'value_text' => 'inactive-live', 'load_policy' => 'archived', 'score' => $base + 5],
    ];
    $plan = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        $rows,
        $incoming,
        $columns,
        ['key_name'],
        $defaults,
        ['value_text' => 'excluded.value_text', 'score' => 'excluded.score'],
        ['key_name', 'value_text', 'load_policy', 'score'],
        static fn (array $row): bool => ($row['load_policy'] ?? null) === 'active',
    );

    $prefix = sprintf('upsert1-320 partial index dynamic variant %03d ', $i);
    $tests[$prefix . 'inserts candidate outside partial index'] = static function (TestRunner $t) use ($plan): void {
        $t->same('insert', $plan()['decisions'][0]['action']);
    };
    $tests[$prefix . 'updates row inside partial index'] = static function (TestRunner $t) use ($plan): void {
        $t->same('update', $plan()['decisions'][1]['action']);
    };
}

for ($i = 0; $i < 100; ++$i) {
    $rows = [
        ['id' => 1, 'key_name' => 'alpha_' . $i, 'value_text' => 'one', 'load_policy' => 'lazy', 'score' => 1],
    ];
    $incoming = [
        ['id' => 1, 'key_name' => 'alpha_rowid_' . $i, 'value_text' => 'rowid-conflict', 'score' => 2],
        ['id' => 99, 'key_name' => 'alpha_' . $i, 'value_text' => 'unique-conflict', 'score' => 3],
        ['id' => 100 + $i, 'key_name' => 'fresh_' . $i, 'value_text' => 'fresh', 'score' => 4],
    ];
    $plan = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        $rows,
        $incoming,
        $columns,
        ['id'],
        $defaults,
        [],
        ['id', 'key_name', 'score'],
        null,
        true,
        'id',
        [['key_name']],
    );

    $prefix = sprintf('upsert1-100 do nothing dynamic variant %03d ', $i);
    $tests[$prefix . 'skips rowid and alternate unique conflicts'] = static function (TestRunner $t) use ($plan): void {
        $t->same(['skip', 'skip', 'insert'], array_column($plan()['decisions'], 'action'));
    };
    $tests[$prefix . 'returns only inserted fresh row'] = static function (TestRunner $t) use ($plan, $i): void {
        $t->same(['fresh_' . $i], array_column($plan()['returning_rows'], 'key_name'));
    };
}

return $tests;
