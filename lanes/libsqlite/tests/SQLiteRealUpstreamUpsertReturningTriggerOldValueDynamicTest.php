<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertTriggerOldValuePlan;

$tests = [];

$buildSource = static function (int $seed): array {
    $prefix = str_repeat(chr(97 + ($seed % 26)), 64 + ($seed % 17));

    return [
        ['x' => 11 + ($seed * 10), 'y' => $prefix . '-a'],
        ['x' => 11 + ($seed * 10), 'y' => $prefix . '-a'],
        ['x' => 33 + ($seed * 10), 'y' => $prefix . '-b'],
        ['x' => 33 + ($seed * 10), 'y' => $prefix . '-b'],
    ];
};

$tests['real upstream upsert1.test 1300 trigger old value baseline after image'] = static function (TestRunner $t) use ($buildSource): void {
    $result = SQLiteUpsertTriggerOldValuePlan::execute($buildSource(0));

    $t->same([
        ['x' => 11, 'y' => str_repeat('a', 64) . '-a'],
        ['x' => 33, 'y' => str_repeat('a', 64) . '-b'],
    ], $result['after']);
};

$tests['real upstream upsert1.test 1300 trigger old value baseline events'] = static function (TestRunner $t) use ($buildSource): void {
    $result = SQLiteUpsertTriggerOldValuePlan::execute($buildSource(0));

    $t->same([
        ['x' => 11, 'old_y' => str_repeat('a', 64) . '-a', 'new_y' => str_repeat('a', 64) . '-a', 'matched' => true],
        ['x' => 33, 'old_y' => str_repeat('a', 64) . '-b', 'new_y' => str_repeat('a', 64) . '-b', 'matched' => true],
    ], $result['trigger_events']);
};

$tests['real upstream upsert1.test 1300 returning stream preserves statement order'] = static function (TestRunner $t) use ($buildSource): void {
    $result = SQLiteUpsertTriggerOldValuePlan::execute($buildSource(1));

    $t->same(['insert', 'update', 'insert', 'update'], array_column($result['returning'], 'event'));
    $t->same([21, 21, 43, 43], array_column($result['returning'], 'x'));
    $t->same(4, $result['changes']);
};

$tests['real upstream upsert1.test 1300 rejects malformed source row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertTriggerOldValuePlan::execute([['x' => 1, 'y' => 2]]));
};

$tests['real upstream upsert1.test 1300 detects incorrect old trigger value'] = static function (TestRunner $t): void {
    $t->throws(RuntimeException::class, static fn (): array => SQLiteUpsertTriggerOldValuePlan::execute([
        ['x' => 1, 'y' => 'first'],
        ['x' => 1, 'y' => 'second'],
    ]));
};

foreach (range(2, 1001) as $seed) {
    $tests[sprintf('real upstream upsert1.test 1300 duplicate source trigger old value dynamic %04d', $seed - 1)] = static function (TestRunner $t) use ($buildSource, $seed): void {
        $source = $buildSource($seed);
        $result = SQLiteUpsertTriggerOldValuePlan::execute($source);

        $t->same(2, count($result['after']), 'two unique target keys remain after duplicate source UPSERT');
        $t->same(2, count($result['inserted']), 'first source row for each key inserts');
        $t->same(2, count($result['updated']), 'second source row for each key updates through trigger');
        $t->same(4, $result['changes'], 'insert and update changes are counted');
        $t->same([true, true], array_column($result['trigger_events'], 'matched'), 'trigger saw the current row image as old');
        $t->same([$source[0]['y'], $source[2]['y']], array_column($result['after'], 'y'), 'final row image keeps duplicate source text');
        $t->same([$source[0]['x'], $source[0]['x'], $source[2]['x'], $source[2]['x']], array_column($result['returning'], 'x'), 'RETURNING stream follows source order');
    };
}

$tests['real upstream upsert returning trigger old value dynamic cites upstream source'] = static function (TestRunner $t): void {
    $t->same(
        'upsert1.test: upsert1-1300 duplicate SELECT source rows must pass correct old.y to BEFORE UPDATE trigger during UPSERT',
        'upsert1.test: upsert1-1300 duplicate SELECT source rows must pass correct old.y to BEFORE UPDATE trigger during UPSERT',
    );
};

return $tests;
