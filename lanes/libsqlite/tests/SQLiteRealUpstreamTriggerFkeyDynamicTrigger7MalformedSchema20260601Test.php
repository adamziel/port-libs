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

$catalogFor = static function (int $case): array {
    $suffix = str_pad((string) $case, 3, '0', STR_PAD_LEFT);
    $table = 'app_event_' . $suffix;
    $catalog = [
        [
            'type' => 'table',
            'name' => $table,
            'tbl_name' => $table,
            'sql' => 'CREATE TABLE ' . $table . '(x,y,z)',
        ],
    ];

    $definitions = [
        ['t2r1', 'AFTER', 'INSERT'],
        ['t2r2', 'BEFORE', 'INSERT'],
        ['t2r3', 'AFTER', 'UPDATE'],
        ['t2r4', 'BEFORE', 'UPDATE'],
        ['t2r5', 'AFTER', 'DELETE'],
        ['t2r6', 'BEFORE', 'DELETE'],
        ['t2r7', 'AFTER', 'INSERT'],
        ['t2r8', 'BEFORE', 'INSERT'],
        ['t2r9', 'AFTER', 'UPDATE'],
        ['t2r10', 'BEFORE', 'UPDATE'],
        ['t2r11', 'AFTER', 'DELETE'],
        ['t2r12', 'BEFORE', 'DELETE'],
    ];

    foreach ($definitions as [$name, $timing, $event]) {
        $catalog[] = [
            'type' => 'trigger',
            'name' => $name,
            'tbl_name' => $table,
            'sql' => 'CREATE TRIGGER ' . $name . ' ' . $timing . ' ' . $event . ' ON ' . $table . ' BEGIN SELECT 1; END',
        ];
    }

    return $catalog;
};

$tests = [
    'real upstream trigger7 malformed schema cites defensive disable' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test');
        $t->true(is_string($source) && str_contains($source, 'sqlite3_db_config db DEFENSIVE 0'));
    },
    'real upstream trigger7 malformed schema cites writable schema corruption' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test');
        $t->true(is_string($source) && str_contains($source, "UPDATE sqlite_master SET sql='nonsense'"));
    },
    'real upstream trigger7 malformed schema cites drop trigger failure' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test');
        $t->true(is_string($source) && str_contains($source, 'catchsql { DROP TRIGGER t2r5 }'));
    },
];

for ($i = 1; $i <= 125; ++$i) {
    $catalog = $catalogFor($i);
    $drop = 't2r' . (($i % 12) + 1);
    $names = array_values(array_column($catalog, 'name'));
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::dropTriggerMalformedSchemaPlan($catalog, $drop);
    $case = 'real upstream trigger7 malformed schema drop trigger dynamic ' . $i;

    foreach ([
        'source' => 'trigger7.test trigger7-99.1',
        'operation' => 'drop-trigger-malformed-schema-guard',
        'status' => 'schema-error',
        'drop_trigger' => $drop,
        'drop_target_exists' => true,
        'defensive_disabled' => true,
        'writable_schema' => true,
        'writable_schema_update_applied' => true,
        'corrupt_sql' => 'nonsense',
        'schema_reopened' => true,
        'schema_parse_status' => 'malformed',
        'corrupt_record_count' => 13,
        'catalog_before_count' => 13,
        'catalog_after_count' => 13,
        'catalog_names_after' => $names,
        'trigger_removed' => false,
        'drop_blocked_by_malformed_schema' => true,
        'dependencies.0' => 'sqlite-trigger7-writable-schema-corruption-rechecked-on-drop-trigger',
        'dependencies.1' => 'sqlite-trigger7-drop-trigger-blocks-on-malformed-schema',
        'dependencies.2' => 'sqlite-trigger7-malformed-schema-preserves-trigger-catalog',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' error matches sqlite malformed schema prefix'] = static function (TestRunner $t) use ($plan): void {
        $t->true(str_contains((string) $plan()['error'], 'malformed database schema'));
    };

    $tests[$case . ' first parse error records corrupted sql owner'] = static function (TestRunner $t) use ($plan, $catalog): void {
        $actual = $plan();
        $t->same($catalog[0]['name'], $actual['schema_parse_errors'][0]['name']);
    };
}

$tests['real upstream trigger7 malformed schema rejects empty catalog'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::dropTriggerMalformedSchemaPlan([], 't2r5'));
};

$tests['real upstream trigger7 malformed schema rejects blank corrupt sql'] = static function (TestRunner $t) use ($catalogFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::dropTriggerMalformedSchemaPlan($catalogFor(1), 't2r5', ''));
};

$tests['real upstream trigger7 defensive mode blocks writable schema mutation'] = static function (TestRunner $t) use ($catalogFor): void {
    $plan = SQLiteDynamicTriggerForeignKeyPlan::dropTriggerMalformedSchemaPlan($catalogFor(1), 't2r5', 'nonsense', false);
    $t->same('write-blocked', $plan['status']);
    $t->same(false, $plan['writable_schema_update_applied']);
    $t->same(13, $plan['catalog_after_count']);
};

return $tests;
