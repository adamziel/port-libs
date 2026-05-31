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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test';

$tests = [
    'real upstream trigger1 temp trigger rebind cites temp trigger reinstall section' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-10.2'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TEMP TRIGGER trig1 AFTER INSERT ON main.t4'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TEMP TRIGGER trig3 AFTER INSERT ON aux.t4'));
    },
    'real upstream trigger1 temp trigger rebind cites statement-time body target resolution' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source) && str_contains($source, 'references within trigger programs are resolved at'));
        $t->true(is_string($source) && str_contains($source, 'DROP TABLE insert_log;'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE aux.insert_log(db, d, e, f);'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-10.11'));
    },
];

for ($i = 1; $i <= 125; ++$i) {
    $base = $i * 100;
    $initialRows = [
        ['schema' => 'main', 'a' => $base + 1, 'b' => $base + 2, 'c' => $base + 3],
        ['schema' => 'temp', 'a' => $base + 4, 'b' => $base + 5, 'c' => $base + 6],
        ['schema' => 'aux', 'a' => $base + 7, 'b' => $base + 8, 'c' => $base + 9],
    ];
    $rollbackRows = [
        ['schema' => 'main', 'a' => $base + 11, 'b' => $base + 12, 'c' => $base + 13],
        ['schema' => 'temp', 'a' => $base + 14, 'b' => $base + 15, 'c' => $base + 16],
        ['schema' => 'aux', 'a' => $base + 17, 'b' => $base + 18, 'c' => $base + 19],
    ];
    $reloadRows = [
        ['schema' => 'main', 'a' => $base + 21, 'b' => $base + 22, 'c' => $base + 23],
        ['schema' => 'temp', 'a' => $base + 24, 'b' => $base + 25, 'c' => $base + 26],
        ['schema' => 'aux', 'a' => $base + 27, 'b' => $base + 28, 'c' => $base + 29],
    ];
    $reboundRows = [
        ['schema' => 'main', 'a' => $base + 31, 'b' => $base + 32, 'c' => $base + 33],
        ['schema' => 'temp', 'a' => $base + 34, 'b' => $base + 35, 'c' => $base + 36],
        ['schema' => 'aux', 'a' => $base + 37, 'b' => $base + 38, 'c' => $base + 39],
    ];

    $values = static fn (array $rows): array => array_values(array_map(
        static fn (array $row): array => [
            'db' => $row['schema'],
            'd' => $row['a'],
            'e' => $row['b'],
            'f' => $row['c'],
        ],
        $rows
    ));
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger1TempTriggerReinstallRebindPlan(
        $initialRows,
        $rollbackRows,
        $reloadRows,
        $reboundRows
    );

    foreach ([
        'source' => 'trigger1.test trigger1-10.0..10.11',
        'operation' => 'temp-trigger-reinstall-and-body-rebind',
        'status' => 'commit-ok',
        'attached_schema' => 'aux',
        'trigger_names' => ['trig1', 'trig2', 'trig3'],
        'trigger_target_schemas' => ['main', 'temp', 'aux'],
        'initial_log_values' => $values($initialRows),
        'initial_log_schema' => 'main',
        'rollback_attempted_values' => $values($rollbackRows),
        'rollback_committed_values' => $values($initialRows),
        'transaction_rollback_preserves_log' => true,
        'reload_log_values' => $values($reloadRows),
        'temp_triggers_reinstalled_after_schema_reload' => true,
        'reinstalled_trigger_names' => ['trig1', 'trig2', 'trig3'],
        'rebound_log_schema' => 'aux',
        'rebound_log_values' => $values($reboundRows),
        'body_rebound_to_attached_insert_log' => true,
        'trigger_program_resolves_body_table_at_statement_compile_time' => true,
        'log_table_column_names_can_change' => true,
        'dependencies.0' => 'sqlite-trigger1-temp-triggers-survive-schema-reload',
        'dependencies.1' => 'sqlite-trigger1-trigger-body-name-resolution-is-statement-time',
        'dependencies.2' => 'sqlite-trigger1-temp-trigger-rollback-does-not-leak-body-writes',
        'dependencies.3' => 'sqlite-trigger1-attached-schema-trigger-target-remains-addressable',
    ] as $path => $expected) {
        $tests[sprintf('real upstream trigger1 temp trigger rebind dynamic %04d %s', $i, $path)] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[sprintf('real upstream trigger1 temp trigger rebind dynamic %04d rollback body rows are not committed', $i)] = static function (TestRunner $t) use ($plan): void {
        $actual = $plan();

        $t->same($actual['initial_log_values'], $actual['rollback_committed_values']);
        $t->same(false, $actual['rollback_attempted_values'] === $actual['rollback_committed_values']);
    };

    $tests[sprintf('real upstream trigger1 temp trigger rebind dynamic %04d moved log rows land in attached schema', $i)] = static function (TestRunner $t) use ($plan): void {
        $actual = $plan();

        $t->same(['aux', 'aux', 'aux'], array_column($actual['rebound_log_rows'], 'log_schema'));
        $t->same(['main', 'temp', 'aux'], array_column($actual['rebound_log_rows'], 'db'));
    };
}

$tests['real upstream trigger1 temp trigger rebind rejects main as attached schema'] = static function (TestRunner $t): void {
    $rows = [['schema' => 'main', 'a' => 1, 'b' => 2, 'c' => 3]];

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger1TempTriggerReinstallRebindPlan($rows, $rows, $rows, $rows, 'main'));
};

$tests['real upstream trigger1 temp trigger rebind rejects malformed row schema'] = static function (TestRunner $t): void {
    $good = [['schema' => 'main', 'a' => 1, 'b' => 2, 'c' => 3]];
    $bad = [['schema' => 'other', 'a' => 1, 'b' => 2, 'c' => 3]];

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger1TempTriggerReinstallRebindPlan($good, $bad, $good, $good));
};

$tests['real upstream trigger1 temp trigger rebind rejects malformed row value'] = static function (TestRunner $t): void {
    $good = [['schema' => 'main', 'a' => 1, 'b' => 2, 'c' => 3]];
    $bad = [['schema' => 'main', 'a' => 1, 'b' => 2, 'c' => 'bad']];

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger1TempTriggerReinstallRebindPlan($good, $good, $bad, $good));
};

return $tests;
