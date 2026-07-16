<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

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

$expectedForActions = static function (array $actions, bool $initial): array {
    $enabled = $initial;
    $readValues = [];
    $toggleValues = [];
    $history = [];

    foreach ($actions as $index => $action) {
        if ($action['op'] === 'pragma') {
            $enabled = (bool) $action['value'];
            $toggleValues[] = $enabled ? 1 : 0;
        } elseif ($action['op'] === 'read') {
            $readValues[] = $enabled ? 1 : 0;
        }

        $history[] = [
            'index' => $index,
            'op' => $action['op'],
            'scenario' => $action['scenario'],
            'requested' => $action['value'] ?? null,
            'token' => $action['token'] ?? null,
            'recursive_triggers' => $enabled ? 1 : 0,
            'rows' => $action['op'] === 'read' ? [['recursive_triggers' => $enabled ? 1 : 0]] : [],
        ];
    }

    return [
        'final' => $enabled ? 1 : 0,
        'read_values' => $readValues,
        'toggle_values' => $toggleValues,
        'history' => $history,
    ];
};

$recursiveTriggerActionSequences = static function (int $variant): array {
    $falseTokens = ['off', 'OFF', '0', 'false', 'no'];
    $trueTokens = ['on', 'ON', '1', 'true', 'yes'];
    $off = $falseTokens[$variant % count($falseTokens)];
    $on = $trueTokens[($variant * 3) % count($trueTokens)];

    $read = static fn (string $scenario): array => ['op' => 'read', 'scenario' => $scenario];
    $set = static fn (bool $value, string $token, string $scenario): array => [
        'op' => 'pragma',
        'value' => $value,
        'token' => $token,
        'scenario' => $scenario,
    ];

    return match ($variant % 4) {
        0 => [
            $read('triggerC-6.1'),
            $set(false, $off, 'triggerC-6.2'),
            $read('triggerC-6.2'),
            $set(true, $on, 'triggerC-6.3'),
            $read('triggerC-6.3'),
        ],
        1 => [
            $read('triggerC-6.1'),
            $set(false, $off, 'triggerC-6.2'),
            $set(false, $falseTokens[($variant + 2) % count($falseTokens)], 'triggerC-6.2'),
            $read('triggerC-6.2'),
            $set(true, $on, 'triggerC-6.3'),
            $read('triggerC-6.3'),
        ],
        2 => [
            $read('triggerC-6.1'),
            $set(true, $on, 'triggerC-6.3'),
            $read('triggerC-6.3'),
            $set(false, $off, 'triggerC-6.2'),
            $read('triggerC-6.2'),
            $set(true, $trueTokens[($variant + 1) % count($trueTokens)], 'triggerC-6.3'),
            $read('triggerC-6.3'),
        ],
        default => [
            $read('triggerC-6.1'),
            $set(false, $off, 'triggerC-6.2'),
            $read('triggerC-6.2'),
            $set(true, $on, 'triggerC-6.3'),
            $read('triggerC-6.3'),
            $set(false, $falseTokens[($variant + 4) % count($falseTokens)], 'triggerC-6.2'),
            $read('triggerC-6.2'),
            $set(true, $trueTokens[($variant + 2) % count($trueTokens)], 'triggerC-6.3'),
            $read('triggerC-6.3'),
        ],
    };
};

$tests['real upstream triggerC recursive_triggers pragma cites source state reads'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');

    $t->true(is_string($source));
    $t->contains('do_test triggerC-6.1', $source);
    $t->contains('PRAGMA recursive_triggers', $source);
};

$tests['real upstream triggerC recursive_triggers pragma cites off toggle'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');

    $t->true(is_string($source));
    $t->contains('PRAGMA recursive_triggers = off', $source);
    $t->contains('do_test triggerC-6.2', $source);
};

$tests['real upstream triggerC recursive_triggers pragma cites on toggle'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');

    $t->true(is_string($source));
    $t->contains('PRAGMA recursive_triggers = on', $source);
    $t->contains('do_test triggerC-6.3', $source);
};

for ($variant = 1; $variant <= 100; ++$variant) {
    $actions = $recursiveTriggerActionSequences($variant);
    $expected = $expectedForActions($actions, true);
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveTriggersPragmaStatePlan($actions, true);
    $label = sprintf('real upstream triggerC-6 recursive_triggers pragma dynamic %03d', $variant);

    foreach ([
        'source' => 'triggerC.test triggerC-6.1..6.3',
        'operation' => 'recursive-triggers-pragma-state',
        'status' => 'commit-ok',
        'initial_recursive_triggers' => 1,
        'final_recursive_triggers' => $expected['final'],
        'read_count' => count($expected['read_values']),
        'toggle_count' => count($expected['toggle_values']),
        'history_count' => count($expected['history']),
        'read_values' => $expected['read_values'],
        'toggle_values' => $expected['toggle_values'],
        'scenarios.0' => 'triggerC-6.1',
        'scenarios.1' => 'triggerC-6.2',
        'scenarios.2' => 'triggerC-6.3',
        'dependencies.0' => 'sqlite-triggerC-recursive-triggers-pragma-reports-current-state',
        'dependencies.1' => 'sqlite-triggerC-recursive-triggers-pragma-off-updates-connection-state',
        'dependencies.2' => 'sqlite-triggerC-recursive-triggers-pragma-on-restores-connection-state',
        'history.0.op' => 'read',
        'history.0.rows.0.recursive_triggers' => 1,
    ] as $path => $expectedValue) {
        $tests["{$label} {$path}"] = static function (TestRunner $t) use ($plan, $value, $path, $expectedValue): void {
            $t->same($expectedValue, $value($plan(), (string) $path));
        };
    }

    $tests["{$label} full history mirrors upstream toggle/read order"] = static function (TestRunner $t) use ($plan, $expected): void {
        $t->same($expected['history'], $plan()['history']);
    };

    $tests["{$label} read probes return one-column pragma rows"] = static function (TestRunner $t) use ($plan): void {
        $reads = array_values(array_filter($plan()['history'], static fn (array $row): bool => $row['op'] === 'read'));
        foreach ($reads as $row) {
            $t->same([['recursive_triggers' => $row['recursive_triggers']]], $row['rows']);
        }
    };
}

$tests['real upstream triggerC recursive_triggers pragma rejects empty sequence'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveTriggersPragmaStatePlan([]));
$tests['real upstream triggerC recursive_triggers pragma rejects unsupported action'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveTriggersPragmaStatePlan([['op' => 'begin']]));
$tests['real upstream triggerC recursive_triggers pragma rejects missing value'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveTriggersPragmaStatePlan([['op' => 'pragma']]));

return $tests;
