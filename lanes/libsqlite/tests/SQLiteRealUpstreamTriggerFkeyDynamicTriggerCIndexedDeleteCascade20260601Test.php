<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

/**
 * @param array<string,mixed> $array
 */
$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test';

$tests = [
    'real upstream triggerC indexed after delete cascade cites source block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('do_test triggerC-9.1', $source);
        $t->contains('CREATE INDEX t9b ON t9(b)', $source);
        $t->contains('DELETE FROM t9 WHERE b=old.a', $source);
        $t->contains('do_test triggerC-9.2', $source);
    },
];

for ($seed = 1; $seed <= 80; ++$seed) {
    $base = $seed * 100;
    $length = 9 + ($seed % 8);
    $survivorCount = 2 + ($seed % 5);
    $rows = [];
    for ($offset = 0; $offset < $length; ++$offset) {
        $rows[] = [
            'a' => $base + $offset + 1,
            'b' => $base + $offset,
        ];
    }

    $deleteB = $base + $survivorCount;
    $expectedInitialA = range($base + 1, $base + $length);
    $expectedDeletedA = range($base + $survivorCount + 1, $base + $length);
    $expectedDeletedB = range($base + $survivorCount, $base + $length - 1);
    $expectedFinalA = range($base + 1, $base + $survivorCount);
    $expectedIndexProbes = range($base + $survivorCount, $base + $length);
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCIndexedDeleteCascadePlan($rows, $deleteB);
    $case = sprintf('real upstream triggerC-9 indexed delete cascade dynamic %03d', $seed);

    foreach ([
        'source' => 'triggerC.test triggerC-9.1..9.2',
        'operation' => 'indexed-after-delete-recursive-cascade',
        'status' => 'commit-ok',
        'trigger_name' => 't9r1',
        'index_name' => 't9b',
        'recursive_trigger_enabled' => true,
        'delete_b' => $deleteB,
        'initial_a_values' => $expectedInitialA,
        'initial_row_count' => $length,
        'deleted_a_values' => $expectedDeletedA,
        'deleted_b_values' => $expectedDeletedB,
        'trigger_fire_count' => count($expectedDeletedA),
        'index_probe_values' => $expectedIndexProbes,
        'final_a_values' => $expectedFinalA,
        'final_row_count' => $survivorCount,
        'dependencies.0' => 'sqlite-triggerC-indexed-after-delete-recursive-cascade',
        'dependencies.1' => 'sqlite-triggerC-after-delete-old-row-feeds-recursive-delete',
        'dependencies.2' => 'sqlite-triggerC-delete-trigger-uses-indexed-b-lookup',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' first deleted row starts at indexed b match'] = static function (TestRunner $t) use ($plan, $base, $survivorCount): void {
        $first = $plan()['deleted_rows'][0];

        $t->same($base + $survivorCount + 1, $first['a']);
        $t->same($base + $survivorCount, $first['b']);
        $t->same(0, $first['depth']);
    };

    $tests[$case . ' leaf trigger still probes next indexed b value'] = static function (TestRunner $t) use ($plan, $base, $length): void {
        $actual = $plan();

        $t->same($base + $length, $actual['index_probe_values'][array_key_last($actual['index_probe_values'])]);
    };
}

$tests['real upstream triggerC indexed delete cascade no match preserves rows'] = static function (TestRunner $t): void {
    $plan = SQLiteDynamicTriggerForeignKeyPlan::triggerCIndexedDeleteCascadePlan([
        ['a' => 1, 'b' => 0],
        ['a' => 2, 'b' => 1],
    ], 99);

    $t->same([], $plan['deleted_rows']);
    $t->same([99], $plan['index_probe_values']);
    $t->same([1, 2], $plan['final_a_values']);
};

$tests['real upstream triggerC indexed delete cascade rejects empty rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCIndexedDeleteCascadePlan([], 1));
};

$tests['real upstream triggerC indexed delete cascade rejects malformed row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCIndexedDeleteCascadePlan([['a' => 1]], 1));
};

$tests['real upstream triggerC indexed delete cascade rejects non integer row values'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCIndexedDeleteCascadePlan([['a' => '1', 'b' => 0]], 1));
};

return $tests;
