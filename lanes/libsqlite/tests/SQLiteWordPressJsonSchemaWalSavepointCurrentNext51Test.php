<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonSchemaWalSavepointPlan;

$schema = static fn (): array => [
    [
        'name' => 'wp_options',
        'type' => 'table',
        'rootpage' => 2,
        'sql' => 'CREATE TABLE wp_options (option_id integer primary key, option_name text unique, option_value text, autoload text)',
    ],
    [
        'name' => 'option_name',
        'type' => 'index',
        'rootpage' => 3,
        'sql' => 'CREATE UNIQUE INDEX option_name ON wp_options(option_name)',
    ],
];

$steps = static fn (): array => [
    [
        'name' => 'create_json_schema',
        'type' => 'table',
        'rootpage' => 8,
        'page_number' => 1,
        'wal_frame_index' => 3,
        'sql' => 'CREATE TABLE wp_json_schema (option_name text primary key, schema_json text not null)',
    ],
    [
        'name' => 'create_json_schema_option_index',
        'type' => 'index',
        'rootpage' => 9,
        'page_number' => 3,
        'wal_frame_index' => 4,
        'sql' => 'CREATE INDEX wp_json_schema_option_index ON wp_json_schema(option_name)',
    ],
    [
        'name' => 'create_json_schema_trigger',
        'type' => 'trigger',
        'page_number' => 1,
        'wal_frame_index' => 5,
        'sql' => 'CREATE TRIGGER wp_json_schema_validate BEFORE INSERT ON wp_options BEGIN SELECT json_valid(NEW.option_value); END',
    ],
    [
        'name' => 'broken_json_schema_view',
        'type' => 'view',
        'page_number' => 1,
        'wal_frame_index' => 6,
        'sql' => 'CREATE VIEW broken_json_schema_view AS SELECT * FROM',
        'fail' => true,
    ],
];

$plan = static fn (array $customSteps = null, array $options = []): array => SQLiteJsonSchemaWalSavepointPlan::plan(
    $schema(),
    $customSteps ?? $steps(),
    $options + ['schema_cookie' => 41, 'data_version' => 9, 'page_size' => 512],
);

$cases = [
    'status is partial rollback after failed schema view' => static fn (): mixed => $plan()['status'],
    'initial schema cookie is retained' => static fn (): mixed => $plan()['initial_schema_cookie'],
    'final schema cookie increments for applied schema edits only' => static fn (): mixed => $plan()['final_schema_cookie'],
    'initial data version is retained' => static fn (): mixed => $plan()['initial_data_version'],
    'final data version increments for applied schema edits only' => static fn (): mixed => $plan()['final_data_version'],
    'initial schema names include wp_options' => static fn (): mixed => $plan()['initial_schema_names'],
    'final schema names include applied table index trigger' => static fn (): mixed => $plan()['final_schema_names'],
    'failed view is absent from final schema names' => static fn (): mixed => in_array('broken_json_schema_view', $plan()['final_schema_names'], true),
    'three schema edits apply before failed view' => static fn (): mixed => count($plan()['applied']),
    'one failed schema edit is recorded' => static fn (): mixed => count($plan()['failed']),
    'first applied schema step name' => static fn (): mixed => $plan()['applied'][0]['name'],
    'first applied schema cookie' => static fn (): mixed => $plan()['applied'][0]['schema_cookie'],
    'first applied data version' => static fn (): mixed => $plan()['applied'][0]['data_version'],
    'second applied schema step name' => static fn (): mixed => $plan()['applied'][1]['name'],
    'second applied page number' => static fn (): mixed => $plan()['applied'][1]['page_number'],
    'second applied wal frame' => static fn (): mixed => $plan()['applied'][1]['wal_frame_index'],
    'third applied schema step name' => static fn (): mixed => $plan()['applied'][2]['name'],
    'third applied page number' => static fn (): mixed => $plan()['applied'][2]['page_number'],
    'third applied wal frame' => static fn (): mixed => $plan()['applied'][2]['wal_frame_index'],
    'failed step name is retained' => static fn (): mixed => $plan()['failed'][0]['name'],
    'failed step schema cookie does not advance' => static fn (): mixed => $plan()['failed'][0]['schema_cookie'],
    'failed step data version does not advance' => static fn (): mixed => $plan()['failed'][0]['data_version'],
    'failed step rollback restores schema page' => static fn (): mixed => $plan()['failed'][0]['rollback']['restored_page_numbers'],
    'failed step rollback discards only failed wal frame' => static fn (): mixed => array_column($plan()['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'),
    'failed step rollback leaves savepoint active' => static fn (): mixed => $plan()['failed'][0]['rollback']['savepoint_active_after'],
    'failed step rollback clears statement journal' => static fn (): mixed => $plan()['failed'][0]['rollback']['statement_journal_cleared'],
    'rollback to savepoint restores schema and index pages' => static fn (): mixed => $plan()['rollback_to_savepoint']['restored_page_numbers'],
    'rollback to savepoint reports no missing images' => static fn (): mixed => $plan()['rollback_to_savepoint']['missing_page_numbers'],
    'wal rollback discards applied schema frames' => static fn (): mixed => array_column($plan()['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'),
    'wal rollback discards touched schema pages' => static fn (): mixed => $plan()['wal_rollback_to_savepoint']['discarded_page_numbers'],
    'commit plan contains applied schema pages' => static fn (): mixed => $plan()['commit']['committed_page_numbers'],
    'savepoint state tracks applied page images' => static fn (): mixed => $plan()['savepoint_state'][1]['page_numbers'],
    'database changed after applied schema edits' => static fn (): mixed => $plan()['database_changed'],
    'dependency marker names schema WAL savepoint behavior' => static fn (): mixed => in_array('sqlite-application-json-schema-wal-savepoint', $plan()['dependencies'], true),
    'ready status when all schema edits apply' => static fn (): mixed => $plan(array_slice($steps(), 0, 3))['status'],
    'ready final schema cookie advances by three' => static fn (): mixed => $plan(array_slice($steps(), 0, 3))['final_schema_cookie'],
    'ready final data version advances by three' => static fn (): mixed => $plan(array_slice($steps(), 0, 3))['final_data_version'],
    'ready failure list is empty' => static fn (): mixed => $plan(array_slice($steps(), 0, 3))['failed'],
    'released inner savepoint records merged pages' => static fn (): mixed => $plan([
        $steps()[0] + ['release' => true],
        $steps()[1],
    ])['released_savepoints'][0]['merged_page_numbers'],
    'released inner savepoint starts a new savepoint for later edits' => static fn (): mixed => $plan([
        $steps()[0] + ['release' => true],
        $steps()[1],
    ])['savepoint_state'][1]['name'],
    'release keeps transaction commit pages merged' => static fn (): mixed => $plan([
        $steps()[0] + ['release' => true],
        $steps()[1],
    ])['commit']['committed_page_numbers'],
    'custom transaction name is reported' => static fn (): mixed => $plan(null, ['transaction' => 'wp_schema_txn'])['transaction'],
    'custom savepoint name is reported' => static fn (): mixed => $plan(null, ['savepoint' => 'wp_schema_sp'])['savepoint'],
    'custom page size is reported' => static fn (): mixed => $plan(null, ['page_size' => 1024])['page_size'],
    'database expands when schema page is beyond current image' => static fn (): mixed => strlen($plan([
        array_replace($steps()[0], ['page_number' => 6]),
    ])['database_bytes']),
    'view schema object can apply when not failed' => static fn (): mixed => in_array('json_schema_view', $plan([
        [
            'name' => 'json_schema_view',
            'type' => 'view',
            'sql' => 'CREATE VIEW json_schema_view AS SELECT option_name FROM wp_options',
        ],
    ])['final_schema_names'], true),
    'trigger rootpage defaults to zero' => static fn (): mixed => $plan([$steps()[2]])['applied'][0]['schema_names'],
    'duplicate initial schema object is rejected' => static function () use ($schema, $steps): mixed {
        try {
            SQLiteJsonSchemaWalSavepointPlan::plan(array_merge($schema(), [$schema()[0]]), $steps());
        } catch (InvalidArgumentException $exception) {
            return str_contains($exception->getMessage(), 'Duplicate SQLite schema object');
        }

        return false;
    },
    'non increasing wal frame is rejected' => static function () use ($plan, $steps): mixed {
        try {
            $plan([$steps()[0], array_replace($steps()[1], ['wal_frame_index' => 3])]);
        } catch (InvalidArgumentException $exception) {
            return str_contains($exception->getMessage(), 'frame indexes must increase');
        }

        return false;
    },
    'bad schema type rolls back statement' => static fn (): mixed => $plan([
        ['name' => 'bad', 'type' => 'sequence', 'sql' => 'CREATE SEQUENCE bad'],
    ])['failed'][0]['error'],
    'bad page size is rejected' => static function () use ($schema, $steps): mixed {
        try {
            SQLiteJsonSchemaWalSavepointPlan::plan($schema(), $steps(), ['page_size' => 500]);
        } catch (InvalidArgumentException $exception) {
            return str_contains($exception->getMessage(), 'page size');
        }

        return false;
    },
];

$expected = [
    'status is partial rollback after failed schema view' => 'partial_rollback',
    'initial schema cookie is retained' => 41,
    'final schema cookie increments for applied schema edits only' => 44,
    'initial data version is retained' => 9,
    'final data version increments for applied schema edits only' => 12,
    'initial schema names include wp_options' => ['wp_options', 'option_name'],
    'final schema names include applied table index trigger' => ['wp_options', 'option_name', 'create_json_schema', 'create_json_schema_option_index', 'create_json_schema_trigger'],
    'failed view is absent from final schema names' => false,
    'three schema edits apply before failed view' => 3,
    'one failed schema edit is recorded' => 1,
    'first applied schema step name' => 'create_json_schema',
    'first applied schema cookie' => 42,
    'first applied data version' => 10,
    'second applied schema step name' => 'create_json_schema_option_index',
    'second applied page number' => 3,
    'second applied wal frame' => 4,
    'third applied schema step name' => 'create_json_schema_trigger',
    'third applied page number' => 1,
    'third applied wal frame' => 5,
    'failed step name is retained' => 'broken_json_schema_view',
    'failed step schema cookie does not advance' => 44,
    'failed step data version does not advance' => 12,
    'failed step rollback restores schema page' => [1],
    'failed step rollback discards only failed wal frame' => [6],
    'failed step rollback leaves savepoint active' => true,
    'failed step rollback clears statement journal' => true,
    'rollback to savepoint restores schema and index pages' => [1, 3],
    'rollback to savepoint reports no missing images' => [],
    'wal rollback discards applied schema frames' => [3, 4, 5],
    'wal rollback discards touched schema pages' => [1, 3],
    'commit plan contains applied schema pages' => [1, 3],
    'savepoint state tracks applied page images' => [1, 3],
    'database changed after applied schema edits' => true,
    'dependency marker names schema WAL savepoint behavior' => true,
    'ready status when all schema edits apply' => 'ready',
    'ready final schema cookie advances by three' => 44,
    'ready final data version advances by three' => 12,
    'ready failure list is empty' => [],
    'released inner savepoint records merged pages' => [1],
    'released inner savepoint starts a new savepoint for later edits' => 'json_schema_batch',
    'release keeps transaction commit pages merged' => [1, 3],
    'custom transaction name is reported' => 'wp_schema_txn',
    'custom savepoint name is reported' => 'wp_schema_sp',
    'custom page size is reported' => 1024,
    'database expands when schema page is beyond current image' => 3072,
    'view schema object can apply when not failed' => true,
    'trigger rootpage defaults to zero' => ['wp_options', 'option_name', 'create_json_schema_trigger'],
    'duplicate initial schema object is rejected' => true,
    'non increasing wal frame is rejected' => true,
    'bad schema type rolls back statement' => 'Unsupported SQLite schema object type: sequence',
    'bad page size is rejected' => true,
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application json schema wal savepoint current next51 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
