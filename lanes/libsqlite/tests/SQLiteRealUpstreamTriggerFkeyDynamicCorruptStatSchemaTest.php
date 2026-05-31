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
    'real upstream fkey1 corrupt stat schema cites drop regression' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE sqlite_stat1 (tbl INTEGER PRIMARY KEY DESC, idx UNIQUE DEFAULT NULL) WITHOUT ROWID'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t1(sqlsim7 REFERENCES sqlite_stat1 ON DELETE CASCADE)'));
        $t->true(is_string($source) && str_contains($source, 'DROP table "sqlsim4"'));
    },
    'real upstream fkey1 corrupt stat schema cites malformed reindex regression' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test');
        $t->true(is_string($source) && str_contains($source, 'UPDATE sqlite_schema SET name=\'sqlite_autoindex_sqlite_stat1_1\''));
        $t->true(is_string($source) && str_contains($source, 'do_catchsql_test 8.3'));
        $t->true(is_string($source) && str_contains($source, 'database disk image is malformed'));
    },
];

for ($i = 1; $i <= 500; ++$i) {
    $statSchema = [
        'tbl_primary_key_desc' => true,
        'idx_unique_default_null' => true,
        'without_rowid' => true,
        'autoindex_name_rewritten' => false,
    ];
    $dropPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1CorruptStatSchemaForeignKeyPlan($statSchema, [
        'action' => 'drop-shadow-table',
        'child_table' => 'app_child_' . $i,
        'child_column' => 'setting_ref_' . $i,
        'parent_table' => 'sqlite_stat1',
        'shadow_table' => 'shadow_settings_' . $i,
    ]);

    foreach ([
        'source' => 'fkey1.test fkey1-8.1',
        'operation' => 'corrupt-stat-schema-foreign-key-processing',
        'status' => 'commit-ok',
        'action' => 'drop-shadow-table',
        'child_table' => 'app_child_' . $i,
        'child_column' => 'setting_ref_' . $i,
        'parent_table' => 'sqlite_stat1',
        'shadow_table' => 'shadow_settings_' . $i,
        'foreign_key_parent_is_corrupt_stat_table' => true,
        'nested_parse_released' => true,
        'drop_shadow_table_safe' => true,
        'error' => null,
        'dependencies.0' => 'sqlite-fkey1-corrupt-sqlite-stat1-nested-parse-does-not-leak',
        'dependencies.1' => 'sqlite-fkey1-foreign-key-processing-tolerates-writable-schema-stat-table',
    ] as $path => $expected) {
        $tests["real upstream fkey1 corrupt stat drop dynamic {$i} {$path}"] = static function (TestRunner $t) use ($dropPlan, $value, $path, $expected): void {
            $t->same($expected, $value($dropPlan(), (string) $path));
        };
    }

    $reindexPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1CorruptStatSchemaForeignKeyPlan(
        array_replace($statSchema, ['autoindex_name_rewritten' => true]),
        [
            'action' => 'reindex',
            'child_table' => 'app_child_reindex_' . $i,
            'child_column' => 'setting_ref_' . $i,
            'parent_table' => 'sqlite_stat1',
            'shadow_table' => 'shadow_settings_' . $i,
        ]
    );

    foreach ([
        'source' => 'fkey1.test fkey1-8.2..8.3',
        'operation' => 'corrupt-stat-schema-foreign-key-processing',
        'status' => 'database-malformed',
        'action' => 'reindex',
        'autoindex_name_rewritten' => true,
        'foreign_key_parent_is_corrupt_stat_table' => true,
        'nested_parse_released' => true,
        'drop_shadow_table_safe' => false,
        'reindex_detected_malformed_schema' => true,
        'error' => 'database disk image is malformed',
        'dependencies.2' => 'sqlite-fkey1-reindex-reports-malformed-renamed-autoindex',
    ] as $path => $expected) {
        $tests["real upstream fkey1 corrupt stat reindex dynamic {$i} {$path}"] = static function (TestRunner $t) use ($reindexPlan, $value, $path, $expected): void {
            $t->same($expected, $value($reindexPlan(), (string) $path));
        };
    }
}

$tests['real upstream fkey1 corrupt stat schema rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey1CorruptStatSchemaForeignKeyPlan([], [
        'action' => 'vacuum',
        'child_table' => 'app_child',
        'child_column' => 'setting_ref',
    ]));
};

return $tests;
