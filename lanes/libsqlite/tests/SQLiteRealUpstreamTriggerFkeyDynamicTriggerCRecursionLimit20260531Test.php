<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

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

$tests = [
    'real upstream triggerC recursion limit cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');

        $t->true(is_string($source));
        $t->contains('triggerC-13.1', $source);
        $t->contains('triggerC-13.2', $source);
        $t->contains('CREATE TRIGGER tr12 AFTER UPDATE ON t12', $source);
        $t->contains('too many levels of trigger recursion', $source);
    },
];

for ($i = 1; $i <= 430; ++$i) {
    $initialA = $i;
    $initialB = $i * 2;
    $outerUpdates = 1 + ($i % 4);
    $depthLimit = 8 + ($i % 17);
    $firstA = $initialA + $outerUpdates + 1;
    $firstB = $initialB + $outerUpdates + 1;
    $lastA = $initialA + $outerUpdates + $depthLimit;
    $lastB = $initialB + $outerUpdates + $depthLimit;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRecursiveUpdateDepthLimit(
        $initialA,
        $initialB,
        $outerUpdates,
        $depthLimit
    );

    foreach ([
        'source' => 'triggerC.test triggerC-13.1..13.2',
        'operation' => 'after-update-self-recursion-depth-limit',
        'status' => 'constraint-failed',
        'error' => 'too many levels of trigger recursion',
        'initial_row.a' => $initialA,
        'initial_row.b' => $initialB,
        'outer_update_count' => $outerUpdates,
        'depth_limit' => $depthLimit,
        'recursive_frame_count' => $depthLimit,
        'first_recursive_frame.depth' => 1,
        'first_recursive_frame.new_a' => $firstA,
        'first_recursive_frame.new_b' => $firstB,
        'last_recursive_frame.depth' => $depthLimit,
        'last_recursive_frame.new_a' => $lastA,
        'last_recursive_frame.new_b' => $lastB,
        'attempted_final_row.a' => $lastA,
        'attempted_final_row.b' => $lastB,
        'rolled_back_row.a' => $initialA,
        'rolled_back_row.b' => $initialB,
        'statement_rolled_back' => true,
        'recursive_triggers' => true,
        'dependencies.0' => 'sqlite-triggerC-recursive-after-update-fires-self-update',
        'dependencies.1' => 'sqlite-triggerC-recursion-depth-limit-raises-error',
        'dependencies.2' => 'sqlite-triggerC-recursive-update-statement-rolls-back',
    ] as $path => $expected) {
        $tests[sprintf('real upstream triggerC recursion limit dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream triggerC recursion limit rejects zero outer updates'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerRecursiveUpdateDepthLimit(1, 2, 0, 10));
$tests['real upstream triggerC recursion limit rejects zero depth'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerRecursiveUpdateDepthLimit(1, 2, 1, 0));

return $tests;
