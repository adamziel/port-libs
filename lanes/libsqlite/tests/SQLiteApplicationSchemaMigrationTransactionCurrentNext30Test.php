<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaMigrationTransactionPlan;

$columns = static fn (): array => [
    ['name' => 'option_id', 'type' => 'INTEGER', 'primary_key' => true],
    ['name' => 'option_name', 'type' => 'VARCHAR(191)', 'not_null' => true],
    ['name' => 'option_value', 'type' => 'LONGTEXT', 'not_null' => true, 'default' => ''],
    ['name' => 'autoload', 'type' => 'VARCHAR(20)', 'not_null' => true, 'default' => 'yes'],
];

$rows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
];

$plan = static fn (array $options = []): array => SQLiteSchemaMigrationTransactionPlan::plan(
    'wp_options',
    $columns(),
    $rows(),
    array_replace([
        'database_path' => '/tmp/wp-schema-migration.sqlite',
        'schema_version' => 42,
        'page_size' => 1024,
        'indexes' => [
            'CREATE UNIQUE INDEX option_name ON wp_options(option_name)',
            'CREATE INDEX autoload ON wp_options(autoload)',
        ],
        'triggers' => [
            'CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN SELECT 1; END',
        ],
        'copy_expressions' => [
            'autoload' => "CASE WHEN autoload IN ('yes','auto','on') THEN 'yes' ELSE 'no' END",
        ],
    ], $options)
);

$cases = [
    'status is planned' => static fn (): mixed => $plan()['status'],
    'database path is preserved' => static fn (): mixed => $plan()['database_path'],
    'source table is preserved' => static fn (): mixed => $plan()['table'],
    'default temporary table is derived' => static fn (): mixed => $plan()['temporary_table'],
    'default target table matches source' => static fn (): mixed => $plan()['target_table'],
    'begin mode is immediate' => static fn (): mixed => $plan()['begin']['mode'],
    'begin write lock is acquired' => static fn (): mixed => $plan()['begin']['write_lock_acquired'],
    'foreign keys default enabled' => static fn (): mixed => $plan()['foreign_keys'],
    'strict default is false' => static fn (): mixed => $plan()['strict'],
    'without rowid default is false' => static fn (): mixed => $plan()['without_rowid'],
    'schema version before is preserved' => static fn (): mixed => $plan()['schema_version_before'],
    'schema version after increments' => static fn (): mixed => $plan()['schema_version_after'],
    'data version after is bumped' => static fn (): mixed => $plan()['data_version_after'],
    'row count is copied' => static fn (): mixed => $plan()['row_count'],
    'four columns are normalized' => static fn (): mixed => count($plan()['columns']),
    'primary key column is tracked' => static fn (): mixed => $plan()['columns'][0]['primary_key'],
    'not null column is tracked' => static fn (): mixed => $plan()['columns'][1]['not_null'],
    'default string is tracked' => static fn (): mixed => $plan()['columns'][2]['default'],
    'copy columns preserve option id identifier' => static fn (): mixed => $plan()['copy_columns']['option_id'],
    'copy expression overrides autoload' => static fn (): mixed => $plan()['copy_columns']['autoload'],
    'two indexes are preserved' => static fn (): mixed => count($plan()['indexes']),
    'trigger is preserved' => static fn (): mixed => $plan()['triggers'][0],
    'dirty pages include table page' => static fn (): mixed => $plan()['dirty_pages'][0],
    'dirty pages include first index page' => static fn (): mixed => $plan()['dirty_pages'][1],
    'dirty pages include second index page' => static fn (): mixed => $plan()['dirty_pages'][2],
    'journal bytes reflect dirty pages' => static fn (): mixed => $plan()['journal_bytes'],
    'sync targets are journal database directory' => static fn (): mixed => array_column($plan()['sync_sequence'], 'target'),
    'first statement disables foreign keys' => static fn (): mixed => $plan()['statements'][0]['sql'],
    'second statement begins transaction' => static fn (): mixed => $plan()['statements'][1]['op'],
    'create statement names temporary table' => static fn (): mixed => $plan()['statements'][2]['table'],
    'create SQL includes primary key' => static fn (): mixed => str_contains($plan()['statements'][2]['sql'], '"option_id" INTEGER PRIMARY KEY'),
    'create SQL includes default literal' => static fn (): mixed => str_contains($plan()['statements'][2]['sql'], "DEFAULT ''"),
    'copy statement copies three rows' => static fn (): mixed => $plan()['statements'][3]['rows'],
    'copy statement lists migrated columns' => static fn (): mixed => $plan()['statements'][3]['columns'],
    'copy SQL uses source table' => static fn (): mixed => str_contains($plan()['statements'][3]['sql'], 'FROM "wp_options"'),
    'copy SQL uses autoload expression' => static fn (): mixed => str_contains($plan()['statements'][3]['sql'], "CASE WHEN autoload"),
    'drop statement drops source table' => static fn (): mixed => $plan()['statements'][4]['sql'],
    'rename statement targets wp options' => static fn (): mixed => $plan()['statements'][5]['to'],
    'index recreation follows rename' => static fn (): mixed => $plan()['statements'][6]['op'],
    'second index recreation follows first' => static fn (): mixed => $plan()['statements'][7]['op'],
    'trigger recreation follows indexes' => static fn (): mixed => $plan()['statements'][8]['op'],
    'schema pragma follows trigger' => static fn (): mixed => $plan()['statements'][9]['value'],
    'foreign key check follows schema pragma' => static fn (): mixed => $plan()['statements'][10]['sql'],
    'foreign key restore follows check' => static fn (): mixed => $plan()['statements'][11]['sql'],
    'sync statements are appended' => static fn (): mixed => array_slice(array_column($plan()['statements'], 'op'), -3),
    'rollback drops temporary table' => static fn (): mixed => $plan()['rollback']['drop_temporary_table'],
    'rollback restores schema version' => static fn (): mixed => $plan()['rollback']['restore_schema_version'],
    'rollback restores foreign key state' => static fn (): mixed => $plan()['rollback']['restore_foreign_keys'],
    'rollback records discarded statements' => static fn (): mixed => $plan()['rollback']['discarded_statements'],
    'dependency names schema migration' => static fn (): mixed => in_array('sqlite-application-schema-migration-transaction', $plan()['dependencies'], true),
    'exclusive begin is accepted' => static fn (): mixed => $plan(['begin' => 'BEGIN EXCLUSIVE TRANSACTION'])['begin']['mode'],
    'foreign keys off skips disable statement' => static fn (): mixed => $plan(['foreign_keys' => false])['statements'][0]['op'],
    'foreign keys off skips check statement' => static fn (): mixed => in_array('PRAGMA foreign_key_check', array_column($plan(['foreign_keys' => false])['statements'], 'sql'), true),
    'strict create suffix is emitted' => static fn (): mixed => str_ends_with($plan(['strict' => true])['statements'][2]['sql'], ' STRICT'),
    'without rowid create suffix is emitted' => static fn (): mixed => str_contains($plan(['without_rowid' => true])['statements'][2]['sql'], 'WITHOUT ROWID'),
    'custom temporary name is preserved' => static fn (): mixed => $plan(['temporary_name' => '__wp_tmp_options'])['temporary_table'],
    'custom target name is preserved' => static fn (): mixed => $plan(['target_name' => 'wp_options_new'])['target_table'],
    'larger row count expands dirty table pages' => static function () use ($columns): mixed {
        $many = [];
        for ($i = 1; $i <= 97; $i++) {
            $many[] = ['option_id' => $i, 'option_name' => 'option_' . $i, 'option_value' => 'v', 'autoload' => 'no'];
        }
        return SQLiteSchemaMigrationTransactionPlan::plan('wp_options', $columns(), $many, ['database_path' => '/tmp/wp-schema-migration.sqlite'])['dirty_pages'];
    },
    'deferred begin is rejected' => static function () use ($columns, $rows): mixed {
        try {
            SQLiteSchemaMigrationTransactionPlan::plan('wp_options', $columns(), $rows(), ['begin' => 'BEGIN DEFERRED']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'unsafe database path is rejected' => static function () use ($columns, $rows): mixed {
        try {
            SQLiteSchemaMigrationTransactionPlan::plan('wp_options', $columns(), $rows(), ['database_path' => '../wp.sqlite']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'invalid table name is rejected' => static function () use ($columns, $rows): mixed {
        try {
            SQLiteSchemaMigrationTransactionPlan::plan('wp-options', $columns(), $rows());
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'invalid copy expression target is rejected' => static function () use ($columns, $rows): mixed {
        try {
            SQLiteSchemaMigrationTransactionPlan::plan('wp_options', $columns(), $rows(), ['copy_expressions' => ['missing' => '1']]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
];

$expected = [
    'status is planned' => 'planned',
    'database path is preserved' => '/tmp/wp-schema-migration.sqlite',
    'source table is preserved' => 'wp_options',
    'default temporary table is derived' => '__wp_migrate_wp_options',
    'default target table matches source' => 'wp_options',
    'begin mode is immediate' => 'immediate',
    'begin write lock is acquired' => true,
    'foreign keys default enabled' => true,
    'strict default is false' => false,
    'without rowid default is false' => false,
    'schema version before is preserved' => 42,
    'schema version after increments' => 43,
    'data version after is bumped' => 2,
    'row count is copied' => 3,
    'four columns are normalized' => 4,
    'primary key column is tracked' => true,
    'not null column is tracked' => true,
    'default string is tracked' => '',
    'copy columns preserve option id identifier' => '"option_id"',
    'copy expression overrides autoload' => "CASE WHEN autoload IN ('yes','auto','on') THEN 'yes' ELSE 'no' END",
    'two indexes are preserved' => 2,
    'trigger is preserved' => 'CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN SELECT 1; END',
    'dirty pages include table page' => 2,
    'dirty pages include first index page' => 3,
    'dirty pages include second index page' => 4,
    'journal bytes reflect dirty pages' => 3124,
    'sync targets are journal database directory' => ['rollback_journal', 'database', 'directory'],
    'first statement disables foreign keys' => 'PRAGMA foreign_keys=OFF',
    'second statement begins transaction' => 'begin',
    'create statement names temporary table' => '__wp_migrate_wp_options',
    'create SQL includes primary key' => true,
    'create SQL includes default literal' => true,
    'copy statement copies three rows' => 3,
    'copy statement lists migrated columns' => ['option_id', 'option_name', 'option_value', 'autoload'],
    'copy SQL uses source table' => true,
    'copy SQL uses autoload expression' => true,
    'drop statement drops source table' => 'DROP TABLE "wp_options"',
    'rename statement targets wp options' => 'wp_options',
    'index recreation follows rename' => 'recreate_index',
    'second index recreation follows first' => 'recreate_index',
    'trigger recreation follows indexes' => 'recreate_trigger',
    'schema pragma follows trigger' => 43,
    'foreign key check follows schema pragma' => 'PRAGMA foreign_key_check',
    'foreign key restore follows check' => 'PRAGMA foreign_keys=ON',
    'sync statements are appended' => ['sync', 'sync', 'sync'],
    'rollback drops temporary table' => '__wp_migrate_wp_options',
    'rollback restores schema version' => 42,
    'rollback restores foreign key state' => true,
    'rollback records discarded statements' => 15,
    'dependency names schema migration' => true,
    'exclusive begin is accepted' => 'exclusive',
    'foreign keys off skips disable statement' => 'begin',
    'foreign keys off skips check statement' => false,
    'strict create suffix is emitted' => true,
    'without rowid create suffix is emitted' => true,
    'custom temporary name is preserved' => '__wp_tmp_options',
    'custom target name is preserved' => 'wp_options_new',
    'larger row count expands dirty table pages' => [2, 3, 4],
    'deferred begin is rejected' => 'rejected',
    'unsafe database path is rejected' => 'rejected',
    'invalid table name is rejected' => 'rejected',
    'invalid copy expression target is rejected' => 'rejected',
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application schema migration transaction current next30 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
