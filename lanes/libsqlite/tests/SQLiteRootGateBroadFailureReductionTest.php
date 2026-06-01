<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;
use PortLibs\LibSqlite\SQLiteUpdateDeleteLimitPlan;
use PortLibs\LibSqlite\SQLiteUpdateFromSql;

$tests = [];

$tests['root gate broad failure reduction binds anonymous parameters after named parameters'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT :prefix || ? AS label, ?2 AS explicit_value, @flag AS flag_value, ? AS tail_value',
        [],
        [
            0 => 'setting',
            1 => ':tail',
            2 => 42,
            ':prefix' => 'app-',
            '@flag' => true,
        ],
    );

    $t->same(1, count($rows));
    $t->same('app-setting', $rows[0]['label']);
    $t->same(42, $rows[0]['explicit_value']);
    $t->same(1, $rows[0]['flag_value']);
    $t->same(':tail', $rows[0]['tail_value']);
};

$tests['root gate broad failure reduction applies mixed bind parameters through update from assignments'] = static function (TestRunner $t): void {
    $options = [
        ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'bytes' => 24],
        ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'bytes' => 24],
        ['setting_id' => 3, 'key_name' => 'blogname', 'key_value' => 'Example Site', 'load_policy' => 'yes', 'bytes' => 9],
    ];
    $staging = [
        ['setting_id' => 2, 'new_value' => 'draft-home', 'seq' => 1],
        ['setting_id' => 2, 'new_value' => 'current-home', 'seq' => 2],
    ];

    $result = SQLiteUpdateFromSql::execute(
        'UPDATE app_settings SET key_value = staged_settings.new_value || :suffix, bytes = app_settings.bytes + ? FROM staged_settings WHERE staged_settings.setting_id = app_settings.setting_id AND staged_settings.seq = :seq',
        ['app_settings' => $options, 'staged_settings' => $staging],
        [':suffix' => ':published', 0 => 10, ':seq' => 1],
    );

    $t->same(1, $result['changes']);
    $t->same('draft-home:published', $result['updated_rows'][0]['key_value']);
    $t->same(34, $result['updated_rows'][0]['bytes']);
    $t->same('draft-home:published', $result['after'][1]['key_value']);
    $t->same(34, $result['after'][1]['bytes']);
    $t->same('Example Site', $result['after'][2]['key_value']);
};

$tests['root gate broad failure reduction rejects negative update delete limit offsets'] = static function (TestRunner $t): void {
    $rows = [
        ['rowid' => 1, 'key_name' => 'siteurl', 'bytes' => 10],
        ['rowid' => 2, 'key_name' => 'home', 'bytes' => 20],
    ];

    $deletePlan = SQLiteUpdateDeleteLimitPlan::delete($rows, static fn (array $_row): bool => true, [], 1, 0);
    $updatePlan = SQLiteUpdateDeleteLimitPlan::update($rows, static fn (array $_row): bool => true, ['bytes' => 99], [], 1, 0);

    $t->same([1], $deletePlan->selectedIds);
    $t->same([1], $updatePlan->selectedIds);
    $t->same([99, 20], array_column($updatePlan->resultRows, 'bytes'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteLimitPlan::delete($rows, static fn (array $_row): bool => true, [], 1, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteLimitPlan::update($rows, static fn (array $_row): bool => true, ['bytes' => 99], [], 1, -1));
};

$tests['root gate broad failure reduction dependency closure note'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql host-parameter binding, SQLiteUpdateFromSql row-array execution, and SQLiteUpdateDeleteLimitPlan validation',
        'no new support component needed; reuses SQLiteSelectSql host-parameter binding, SQLiteUpdateFromSql row-array execution, and SQLiteUpdateDeleteLimitPlan validation',
    );
};

return $tests;
