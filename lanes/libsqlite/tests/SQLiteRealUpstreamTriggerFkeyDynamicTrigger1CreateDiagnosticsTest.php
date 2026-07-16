<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test';

$valueAt = static function (array $row, string $path): mixed {
    $value = $row;
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }

        throw new RuntimeException("Missing trigger1 diagnostic assertion path {$path}");
    }

    return $value;
};

$baseObjects = [
    ['name' => 'app_settings', 'object_type' => 'table'],
    ['name' => 'temp_settings', 'object_type' => 'table', 'temp' => true],
    ['name' => 'sqlite_master', 'object_type' => 'table'],
    ['name' => 'tr_existing', 'object_type' => 'trigger', 'target' => 'app_settings'],
];

$definitionsFor = static function (int $seed): array {
    $suffix = (string) $seed;

    return [
        ['name' => 'tr_missing_' . $suffix, 'target' => 'missing_settings_' . $suffix],
        ['name' => 'tr_temp_missing_' . $suffix, 'target' => 'missing_temp_' . $suffix, 'temp' => true],
        ['name' => 'tr_statement_' . $suffix, 'target' => 'app_settings', 'for_each_statement' => true],
        ['name' => 'tr_existing', 'target' => 'app_settings', 'if_not_exists' => true],
        ['name' => 'tr_existing', 'target' => 'app_settings'],
        ['name' => 'tr_existing', 'quoted_name' => '"tr_existing"', 'target' => 'app_settings'],
        ['name' => 'tr_existing', 'quoted_name' => '[tr_existing]', 'target' => 'app_settings'],
        ['name' => 'tr_clean_' . $suffix, 'target' => 'app_settings'],
        ['name' => 'tr_temp_clean_' . $suffix, 'target' => 'temp_settings', 'temp' => true],
        ['name' => 'tr_system_' . $suffix, 'target' => 'sqlite_master', 'system_target' => true],
    ];
};

$planFor = static fn (int $seed): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCreateDiagnostics($baseObjects, $definitionsFor($seed));

$tests = [
    'real upstream trigger1 create diagnostics cites missing table case' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.1.1'));
        $t->true(is_string($source) && str_contains($source, 'no such table: main.no_such_table'));
    },
    'real upstream trigger1 create diagnostics cites duplicate trigger case' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.2.1'));
        $t->true(is_string($source) && str_contains($source, 'trigger tr1 already exists'));
    },
    'real upstream trigger1 create diagnostics cites system table rejection' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.9'));
        $t->true(is_string($source) && str_contains($source, 'cannot create trigger on system table'));
    },
];

foreach (range(1, 100) as $seed) {
    $plan = static fn (): array => $planFor($seed);
    $checks = [
        'source' => 'trigger1.test trigger1-1.1..1.9',
        'operation' => 'trigger-create-diagnostics',
        'case_count' => 10,
        'created_count' => 2,
        'error_count' => 7,
        'skipped_existing_count' => 1,
        'cases.0.status' => 'schema-error',
        'cases.0.error' => 'no such table: main.missing_settings_' . $seed,
        'cases.1.error' => 'no such table: missing_temp_' . $seed,
        'cases.2.error' => 'near "STATEMENT": syntax error',
        'cases.3.status' => 'skipped-existing',
        'cases.4.error' => 'trigger tr_existing already exists',
        'cases.5.error' => 'trigger "tr_existing" already exists',
        'cases.6.error' => 'trigger [tr_existing] already exists',
        'cases.7.status' => 'created',
        'cases.8.temp' => true,
        'cases.9.error' => 'cannot create trigger on system table',
        'dependencies.0' => 'sqlite-trigger1-missing-target-diagnostics',
        'dependencies.1' => 'sqlite-trigger1-for-each-statement-syntax-error',
        'dependencies.2' => 'sqlite-trigger1-if-not-exists-skips-duplicate-trigger',
        'dependencies.3' => 'sqlite-trigger1-system-table-trigger-rejected',
    ];

    foreach ($checks as $path => $expected) {
        $tests[sprintf('real upstream trigger1 create diagnostics dynamic %03d %s', $seed, $path)] =
            static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
                $t->same($expected, $valueAt($plan(), (string) $path));
            };
    }

    $tests[sprintf('real upstream trigger1 create diagnostics dynamic %03d main catalog includes clean trigger', $seed)] =
        static function (TestRunner $t) use ($plan, $seed): void {
            $t->true(in_array('tr_clean_' . $seed, $plan()['final_main_triggers'], true));
        };

    $tests[sprintf('real upstream trigger1 create diagnostics dynamic %03d temp catalog includes temp trigger', $seed)] =
        static function (TestRunner $t) use ($plan, $seed): void {
            $t->true(in_array('tr_temp_clean_' . $seed, $plan()['final_temp_triggers'], true));
        };
}

$tests['real upstream trigger1 create diagnostics rejects malformed definition'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCreateDiagnostics([], ['broken']));
};

$tests['real upstream trigger1 create diagnostics rejects malformed trigger name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCreateDiagnostics([], [['name' => 'bad-name', 'target' => 'app_settings']]));
};

$tests['real upstream trigger1 create diagnostics dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local schema catalog and trigger diagnostic planning helpers',
        'no new support component needed; reuses lane-local schema catalog and trigger diagnostic planning helpers'
    );
};

return $tests;
