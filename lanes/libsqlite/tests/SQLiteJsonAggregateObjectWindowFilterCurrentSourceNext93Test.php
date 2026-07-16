<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'priority' => 40, 'enabled' => 1],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'blogname', 'option_value' => 'Port Fixture', 'priority' => 30, 'enabled' => 1],
        ['option_id' => 3, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('{"kind":"rules"}'), 'priority' => 20, 'enabled' => 1],
        ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'queue'])), 'priority' => 10, 'enabled' => 1],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('{"kind":"rules-refresh"}'), 'priority' => 50, 'enabled' => 1],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'empty_option', 'option_value' => null, 'priority' => 60, 'enabled' => 0],
    ],
];

$tests['json aggregate object window filter current source next93 parser executes filtered rows frames'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_object(option_name, option_value ORDER BY priority DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id',
        $tables,
    );

    $t->same(6, count($rows));
    $t->same('{"siteurl":"https://example.test","blogname":"Port Fixture"}', $rows[0]['frame_json']);
    $t->same('{"blogname":"Port Fixture"}', $rows[1]['frame_json']);
    $t->same('{"plugin_rules":{"kind":"rules-refresh"},"plugin_rules":{"kind":"rules"},"plugin_queue":{"kind":"queue"}}', $rows[2]['frame_json']);
    $t->same('{"plugin_rules":{"kind":"rules-refresh"},"plugin_queue":{"kind":"queue"}}', $rows[3]['frame_json']);
    $t->same('{"plugin_rules":{"kind":"rules-refresh"}}', $rows[4]['frame_json']);
    $t->same('{}', $rows[5]['frame_json']);
};

$tests['json aggregate object window filter current source next93 jsonb dispatch returns object blobs'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, jsonb_group_object(option_name, option_value ORDER BY priority DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_jsonb FROM wp_options ORDER BY option_id',
        $tables,
    );

    $t->true($rows[2]['frame_jsonb'] instanceof SQLiteBlobValue);
    $t->same(['plugin_rules' => ['kind' => 'rules'], 'plugin_queue' => ['kind' => 'queue']], SQLiteJsonB::decode($rows[2]['frame_jsonb']->bytes));
    $t->same(['plugin_rules' => ['kind' => 'rules-refresh'], 'plugin_queue' => ['kind' => 'queue']], SQLiteJsonB::decode($rows[3]['frame_jsonb']->bytes));
    $t->same([], SQLiteJsonB::decode($rows[5]['frame_jsonb']->bytes));
};

$tests['json aggregate object window filter current source next93 filter runs before duplicate distinct'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_object(DISTINCT name, payload ORDER BY rank DESC) FILTER (WHERE keep) OVER (ORDER BY seq ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'rank' => 10, 'name' => 'alpha', 'payload' => 'old', 'keep' => 0],
            ['seq' => 2, 'rank' => 40, 'name' => 'beta', 'payload' => 'one', 'keep' => 1],
            ['seq' => 3, 'rank' => 30, 'name' => 'alpha', 'payload' => 'new', 'keep' => 1],
            ['seq' => 4, 'rank' => 20, 'name' => 'beta', 'payload' => 'one', 'keep' => 1],
        ]],
    );

    $t->same('{"beta":"one","alpha":"new"}', $rows[0]['frame_json']);
    $t->same('{"beta":"one","alpha":"new"}', $rows[1]['frame_json']);
    $t->same('{"alpha":"new","beta":"one"}', $rows[2]['frame_json']);
};

$tests['json aggregate object window filter current source next93 groups frame includes peer group then following'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_object(name, payload ORDER BY rank DESC) FILTER (WHERE keep) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'bucket' => 1, 'rank' => 10, 'name' => 'first', 'payload' => 'a', 'keep' => 1],
            ['seq' => 2, 'bucket' => 2, 'rank' => 40, 'name' => 'second', 'payload' => 'b', 'keep' => 1],
            ['seq' => 3, 'bucket' => 2, 'rank' => 30, 'name' => 'third', 'payload' => 'c', 'keep' => 1],
            ['seq' => 4, 'bucket' => 3, 'rank' => 20, 'name' => 'tail', 'payload' => 'd', 'keep' => 1],
        ]],
    );

    $t->same('{"second":"b","third":"c","first":"a"}', $rows[0]['frame_json']);
    $t->same('{"second":"b","third":"c","tail":"d"}', $rows[1]['frame_json']);
    $t->same('{"second":"b","third":"c","tail":"d"}', $rows[2]['frame_json']);
};

$tests['json aggregate object window filter current source next93 exclude current row preserves filtered peers'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_object(name, payload ORDER BY rank DESC) FILTER (WHERE keep) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'bucket' => 1, 'rank' => 10, 'name' => 'first', 'payload' => 'a', 'keep' => 1],
            ['seq' => 2, 'bucket' => 2, 'rank' => 40, 'name' => 'second', 'payload' => 'b', 'keep' => 1],
            ['seq' => 3, 'bucket' => 2, 'rank' => 30, 'name' => 'third', 'payload' => 'c', 'keep' => 1],
            ['seq' => 4, 'bucket' => 3, 'rank' => 20, 'name' => 'tail', 'payload' => 'd', 'keep' => 1],
        ]],
    );

    $t->same('{"second":"b","third":"c"}', $rows[0]['frame_json']);
    $t->same('{"third":"c","tail":"d"}', $rows[1]['frame_json']);
    $t->same('{"second":"b","tail":"d"}', $rows[2]['frame_json']);
};

$tests['json aggregate object window filter current source next93 exclude group removes current peers'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_object(name, payload ORDER BY rank DESC) FILTER (WHERE keep) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE GROUP) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'bucket' => 1, 'rank' => 10, 'name' => 'first', 'payload' => 'a', 'keep' => 1],
            ['seq' => 2, 'bucket' => 2, 'rank' => 40, 'name' => 'second', 'payload' => 'b', 'keep' => 1],
            ['seq' => 3, 'bucket' => 2, 'rank' => 30, 'name' => 'third', 'payload' => 'c', 'keep' => 1],
            ['seq' => 4, 'bucket' => 3, 'rank' => 20, 'name' => 'tail', 'payload' => 'd', 'keep' => 1],
        ]],
    );

    $t->same('{"second":"b","third":"c"}', $rows[0]['frame_json']);
    $t->same('{"tail":"d"}', $rows[1]['frame_json']);
    $t->same('{"tail":"d"}', $rows[2]['frame_json']);
};

$tests['json aggregate object window filter current source next93 exclude ties keeps current peer'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_object(name, payload ORDER BY rank DESC) FILTER (WHERE keep) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'bucket' => 1, 'rank' => 10, 'name' => 'first', 'payload' => 'a', 'keep' => 1],
            ['seq' => 2, 'bucket' => 2, 'rank' => 40, 'name' => 'second', 'payload' => 'b', 'keep' => 1],
            ['seq' => 3, 'bucket' => 2, 'rank' => 30, 'name' => 'third', 'payload' => 'c', 'keep' => 1],
            ['seq' => 4, 'bucket' => 3, 'rank' => 20, 'name' => 'tail', 'payload' => 'd', 'keep' => 1],
        ]],
    );

    $t->same('{"second":"b","third":"c","first":"a"}', $rows[0]['frame_json']);
    $t->same('{"second":"b","tail":"d"}', $rows[1]['frame_json']);
    $t->same('{"third":"c","tail":"d"}', $rows[2]['frame_json']);
};

$tests['json aggregate object window filter current source next93 range frame uses numeric current source band'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_object(name, payload ORDER BY rank DESC) FILTER (WHERE keep) OVER (ORDER BY score RANGE BETWEEN CURRENT ROW AND 0.5 FOLLOWING) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'score' => 1.0, 'rank' => 20, 'name' => 'a', 'payload' => 'one', 'keep' => 1],
            ['seq' => 2, 'score' => 1.25, 'rank' => 30, 'name' => 'b', 'payload' => 'two', 'keep' => 1],
            ['seq' => 3, 'score' => 1.5, 'rank' => 10, 'name' => 'c', 'payload' => 'three', 'keep' => 0],
            ['seq' => 4, 'score' => 2.0, 'rank' => 40, 'name' => 'd', 'payload' => 'four', 'keep' => 1],
        ]],
    );

    $t->same('{"b":"two","a":"one"}', $rows[0]['frame_json']);
    $t->same('{"b":"two"}', $rows[1]['frame_json']);
    $t->same('{"d":"four"}', $rows[2]['frame_json']);
};

$tests['json aggregate object window filter current source next93 final order can use object window output'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_object(option_name, option_value ORDER BY priority DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options ORDER BY frame_json DESC, option_id',
        $tables,
    );

    $t->same([6, 1, 5, 4, 3, 2], array_column($rows, 'option_id'));
    $t->same('{}', $rows[0]['frame_json']);
};

$tests['json aggregate object window filter current source next93 rejects one argument object aggregate'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_object(option_name) FILTER (WHERE enabled) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate object window filter current source next93 rejects wildcard object label'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_object(*, option_value) FILTER (WHERE enabled) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate object window filter current source next93 default range frame aggregates through current row'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_object(option_name, option_value) FILTER (WHERE enabled) OVER (ORDER BY option_id) AS frame_json FROM wp_options',
        $tables,
    );

    $t->same([
        '{"siteurl":"https://example.test"}',
        '{"siteurl":"https://example.test","blogname":"Port Fixture"}',
        '{"siteurl":"https://example.test","blogname":"Port Fixture","plugin_rules":{"kind":"rules"}}',
        '{"siteurl":"https://example.test","blogname":"Port Fixture","plugin_rules":{"kind":"rules"},"plugin_queue":{"kind":"queue"}}',
        '{"siteurl":"https://example.test","blogname":"Port Fixture","plugin_rules":{"kind":"rules"},"plugin_queue":{"kind":"queue"},"plugin_rules":{"kind":"rules-refresh"}}',
        '{"siteurl":"https://example.test","blogname":"Port Fixture","plugin_rules":{"kind":"rules"},"plugin_queue":{"kind":"queue"},"plugin_rules":{"kind":"rules-refresh"}}',
    ], array_column($rows, 'frame_json'));
};

$tests['json aggregate object window filter current source next93 explicit rows frame can omit window order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_object(option_name, option_value) FILTER (WHERE enabled) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options',
        $tables,
    );

    $t->same([
        '{"siteurl":"https://example.test","blogname":"Port Fixture"}',
        '{"blogname":"Port Fixture","plugin_rules":{"kind":"rules"}}',
        '{"plugin_rules":{"kind":"rules"},"plugin_queue":{"kind":"queue"}}',
        '{"plugin_queue":{"kind":"queue"},"plugin_rules":{"kind":"rules-refresh"}}',
        '{"plugin_rules":{"kind":"rules-refresh"}}',
        '{}',
    ], array_column($rows, 'frame_json'));
};

return $tests;
