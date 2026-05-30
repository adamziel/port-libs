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
    'real upstream trigger7 batch cites qualified trigger diagnostics' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test');
        $t->true(is_string($source) && str_contains($source, 'temporary trigger may not have qualified name'));
        $t->true(is_string($source) && str_contains($source, 'unknown database not_a_db'));
    },
    'real upstream trigger7 batch cites update-of explain pruning' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test');
        $t->true(is_string($source) && str_contains($source, 'EXPLAIN UPDATE t1 SET x=5'));
        $t->true(is_string($source) && str_contains($source, '___update_t1.y___'));
    },
    'real upstream trigger7 batch cites selective drop trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER t2r12 BEFORE DELETE ON t2'));
        $t->true(is_string($source) && str_contains($source, 'DROP TRIGGER t2r6'));
    },
];

foreach ([
    ['main.r1', true, 'schema-error', 'main', 'r1', 'temporary trigger may not have qualified name'],
    ['not_a_db.r1', false, 'schema-error', 'not_a_db', 'r1', 'unknown database not_a_db'],
    ['r1', true, 'commit-ok', null, 'r1', null],
] as $case) {
    [$name, $temporary, $status, $database, $local, $error] = $case;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::qualifiedTriggerNameDiagnostic($name, $temporary);
    foreach ([
        'source' => 'trigger7.test trigger7-1.1..1.2',
        'operation' => 'qualified-trigger-name-diagnostic',
        'status' => $status,
        'temporary' => $temporary,
        'database_name' => $database,
        'trigger_name' => $local,
        'error' => $error,
        'dependencies.0' => 'sqlite-trigger7-temporary-trigger-may-not-have-qualified-name',
        'dependencies.1' => 'sqlite-trigger7-qualified-trigger-unknown-database',
    ] as $path => $expected) {
        $tests['real upstream trigger7 qualified trigger ' . $name . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 120; ++$i) {
    $triggers = [
        ['name' => 'x', 'columns' => ['x'], 'timing' => 'after', 'event' => 'update'],
        ['name' => 'y', 'columns' => ['y'], 'timing' => 'after', 'event' => 'update'],
    ];
    $column = match ($i % 3) {
        0 => 'x',
        1 => 'y',
        default => 'rowid',
    };
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::updateOfExplainTriggerPruning($triggers, [$column]);
    $expectedEmitted = $column === 'rowid' ? [] : [$column];
    $expectedPruned = $column === 'x' ? ['y'] : ($column === 'y' ? ['x'] : ['x', 'y']);
    $case = 'real upstream trigger7 update-of explain pruning dynamic ' . $i;

    foreach ([
        'source' => 'trigger7.test trigger7-2.1..2.6',
        'operation' => 'update-of-explain-trigger-pruning',
        'status' => 'commit-ok',
        'updated_columns' => [$column],
        'emitted_trigger_names' => $expectedEmitted,
        'pruned_trigger_names' => $expectedPruned,
        'dependencies.0' => 'sqlite-trigger7-update-of-prunes-unmatched-trigger-programs',
        'dependencies.1' => 'sqlite-trigger7-rowid-update-does-not-match-named-column-trigger',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' explain text contains only emitted trigger sentinel'] = static function (TestRunner $t) use ($plan, $column): void {
        $text = $plan()['explain_text'];
        $t->same($column === 'x', str_contains((string) $text, '___update_t1.x___'));
        $t->same($column === 'y', str_contains((string) $text, '___update_t1.y___'));
    };
}

$baseTriggers = [
    ['name' => 't2r1', 'timing' => 'after', 'event' => 'insert'],
    ['name' => 't2r2', 'timing' => 'before', 'event' => 'insert'],
    ['name' => 't2r3', 'timing' => 'after', 'event' => 'update'],
    ['name' => 't2r4', 'timing' => 'before', 'event' => 'update'],
    ['name' => 't2r5', 'timing' => 'after', 'event' => 'delete'],
    ['name' => 't2r6', 'timing' => 'before', 'event' => 'delete'],
    ['name' => 't2r7', 'timing' => 'after', 'event' => 'insert'],
    ['name' => 't2r8', 'timing' => 'before', 'event' => 'insert'],
    ['name' => 't2r9', 'timing' => 'after', 'event' => 'update'],
    ['name' => 't2r10', 'timing' => 'before', 'event' => 'update'],
    ['name' => 't2r11', 'timing' => 'after', 'event' => 'delete'],
    ['name' => 't2r12', 'timing' => 'before', 'event' => 'delete'],
];

for ($i = 1; $i <= 80; ++$i) {
    $drop = 't2r' . (($i % 12) + 1);
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selectiveDropTriggerCatalog($baseTriggers, [$drop]);
    $remaining = array_values(array_filter(array_column($baseTriggers, 'name'), static fn (string $name): bool => $name !== $drop));
    $case = 'real upstream trigger7 selective drop trigger dynamic ' . $i;

    foreach ([
        'source' => 'trigger7.test trigger7-3.1',
        'operation' => 'selective-drop-trigger-catalog',
        'status' => 'commit-ok',
        'dropped_trigger_names' => [$drop],
        'remaining_trigger_names' => $remaining,
        'dependencies.0' => 'sqlite-trigger7-many-triggers-on-table-remain-addressable',
        'dependencies.1' => 'sqlite-trigger7-drop-trigger-removes-only-named-trigger',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' event and timing counts match single drop'] = static function (TestRunner $t) use ($plan, $drop): void {
        $actual = $plan();
        $t->same(11, count($actual['remaining_trigger_names']));
        $t->same(false, in_array($drop, $actual['remaining_trigger_names'], true));
        $t->same(11, array_sum($actual['remaining_by_event']));
        $t->same(11, array_sum($actual['remaining_by_timing']));
    };
}

$tests['real upstream trigger7 rejects malformed trigger name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::qualifiedTriggerNameDiagnostic('bad-name', false));
};

return $tests;
