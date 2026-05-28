<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_size' => 20, 'option_value' => 'https://example.test'],
        ['option_id' => 2, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_size' => 12, 'option_value' => 'Port Fixture'],
        ['option_id' => 3, 'option_name' => 'plugin_rules', 'autoload' => 'no', 'option_size' => 30, 'option_value' => new SQLiteJsonSubtypeValue('{"kind":"rules"}')],
        ['option_id' => 4, 'option_name' => 'plugin_queue', 'autoload' => 'no', 'option_size' => 25, 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'queue']))],
        ['option_id' => 5, 'option_name' => 'empty_option', 'autoload' => 'no', 'option_size' => 0, 'option_value' => null],
        ['option_id' => 6, 'option_name' => 'plugin_tail', 'autoload' => 'no', 'option_size' => 5, 'option_value' => 'tail'],
    ],
];

$windowSql = "SELECT option_id, option_name, json_group_array(option_name ORDER BY option_name) FILTER (WHERE option_size > 0) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id";

$tests['json aggregate filter order window current source next84 row frames are parser executable'] = static function (TestRunner $t) use ($tables, $windowSql): void {
    $rows = SQLiteSelectSql::execute($windowSql, $tables);

    $t->same(6, count($rows));
    $t->same('["blogname","siteurl"]', $rows[0]['frame_json']);
    $t->same('["blogname"]', $rows[1]['frame_json']);
    $t->same('["plugin_queue","plugin_rules"]', $rows[2]['frame_json']);
    $t->same('["plugin_queue"]', $rows[3]['frame_json']);
    $t->same('["plugin_tail"]', $rows[4]['frame_json']);
    $t->same('["plugin_tail"]', $rows[5]['frame_json']);
};

$tests['json aggregate filter order window current source next84 final order remains independent'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT option_id, json_group_array(option_name ORDER BY option_name) FILTER (WHERE option_size > 0) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options ORDER BY frame_json DESC, option_id",
        $tables,
    );

    $t->same([5, 6, 4, 3, 2, 1], array_column($rows, 'option_id'));
    $t->same('["plugin_tail"]', $rows[0]['frame_json']);
};

$tests['json aggregate filter order window current source next84 jsonb dispatch returns frame blobs'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT option_id, jsonb_group_array(option_name ORDER BY option_name) FILTER (WHERE option_size > 0) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_jsonb FROM wp_options ORDER BY option_id",
        $tables,
    );

    $t->true($rows[2]['frame_jsonb'] instanceof SQLiteBlobValue);
    $t->same(['plugin_queue', 'plugin_rules'], SQLiteJsonB::decode($rows[2]['frame_jsonb']->bytes));
};

$tests['json aggregate filter order window current source next84 json subtype and jsonb values survive frames'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT option_id, json_group_array(option_value ORDER BY option_name) FILTER (WHERE autoload = 'no') OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS payloads FROM wp_options ORDER BY option_id",
        $tables,
    );

    $t->same('[{"kind":"queue"},{"kind":"rules"}]', $rows[2]['payloads']);
    $t->same('[null,{"kind":"queue"}]', $rows[3]['payloads']);
    $t->same('[null,"tail"]', $rows[4]['payloads']);
};

$tests['json aggregate filter order window current source next84 groups frame includes current and following peers'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT seq, json_group_array(name ORDER BY name) FILTER (WHERE keep) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM events ORDER BY seq",
        ['events' => [
            ['seq' => 1, 'bucket' => 10, 'name' => 'delta', 'keep' => 1],
            ['seq' => 2, 'bucket' => 20, 'name' => 'beta', 'keep' => 1],
            ['seq' => 3, 'bucket' => 20, 'name' => 'alpha', 'keep' => 1],
            ['seq' => 4, 'bucket' => 30, 'name' => 'gamma', 'keep' => 1],
        ]],
    );

    $t->same('["alpha","beta","delta"]', $rows[0]['frame_json']);
    $t->same('["alpha","beta","gamma"]', $rows[1]['frame_json']);
    $t->same('["alpha","beta","gamma"]', $rows[2]['frame_json']);
};

$tests['json aggregate filter order window current source next84 exclude current row removes only current tuple'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT seq, json_group_array(name ORDER BY name) FILTER (WHERE keep) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS frame_json FROM events ORDER BY seq",
        ['events' => [
            ['seq' => 1, 'bucket' => 10, 'name' => 'delta', 'keep' => 1],
            ['seq' => 2, 'bucket' => 20, 'name' => 'beta', 'keep' => 1],
            ['seq' => 3, 'bucket' => 20, 'name' => 'alpha', 'keep' => 1],
            ['seq' => 4, 'bucket' => 30, 'name' => 'gamma', 'keep' => 1],
        ]],
    );

    $t->same('["alpha","beta"]', $rows[0]['frame_json']);
    $t->same('["alpha","gamma"]', $rows[1]['frame_json']);
    $t->same('["beta","gamma"]', $rows[2]['frame_json']);
};

$tests['json aggregate filter order window current source next84 exclude group removes all peers'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT seq, json_group_array(name ORDER BY name) FILTER (WHERE keep) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE GROUP) AS frame_json FROM events ORDER BY seq",
        ['events' => [
            ['seq' => 1, 'bucket' => 10, 'name' => 'delta', 'keep' => 1],
            ['seq' => 2, 'bucket' => 20, 'name' => 'beta', 'keep' => 1],
            ['seq' => 3, 'bucket' => 20, 'name' => 'alpha', 'keep' => 1],
            ['seq' => 4, 'bucket' => 30, 'name' => 'gamma', 'keep' => 1],
        ]],
    );

    $t->same('["alpha","beta"]', $rows[0]['frame_json']);
    $t->same('["gamma"]', $rows[1]['frame_json']);
    $t->same('["gamma"]', $rows[2]['frame_json']);
};

$tests['json aggregate filter order window current source next84 exclude ties keeps current peer only'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT seq, json_group_array(name ORDER BY name) FILTER (WHERE keep) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS frame_json FROM events ORDER BY seq",
        ['events' => [
            ['seq' => 1, 'bucket' => 10, 'name' => 'delta', 'keep' => 1],
            ['seq' => 2, 'bucket' => 20, 'name' => 'beta', 'keep' => 1],
            ['seq' => 3, 'bucket' => 20, 'name' => 'alpha', 'keep' => 1],
            ['seq' => 4, 'bucket' => 30, 'name' => 'gamma', 'keep' => 1],
        ]],
    );

    $t->same('["alpha","beta","delta"]', $rows[0]['frame_json']);
    $t->same('["beta","gamma"]', $rows[1]['frame_json']);
    $t->same('["alpha","gamma"]', $rows[2]['frame_json']);
};

$tests['json aggregate filter order window current source next84 range frame uses numeric band'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT seq, json_group_array(name ORDER BY name) FILTER (WHERE keep) OVER (ORDER BY score RANGE BETWEEN CURRENT ROW AND 0.5 FOLLOWING) AS frame_json FROM events ORDER BY seq",
        ['events' => [
            ['seq' => 1, 'score' => 1.0, 'name' => 'b', 'keep' => 1],
            ['seq' => 2, 'score' => 1.25, 'name' => 'a', 'keep' => 1],
            ['seq' => 3, 'score' => 1.75, 'name' => 'c', 'keep' => 1],
        ]],
    );

    $t->same('["a","b"]', $rows[0]['frame_json']);
    $t->same('["a","c"]', $rows[1]['frame_json']);
    $t->same('["c"]', $rows[2]['frame_json']);
};

$tests['json aggregate filter order window current source next84 filter is evaluated per current source row'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT seq, json_group_array(name ORDER BY seq) FILTER (WHERE keep AND name LIKE 'plugin_%') OVER (ORDER BY seq ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS frame_json FROM events ORDER BY seq",
        ['events' => [
            ['seq' => 1, 'name' => 'plugin_a', 'keep' => 1],
            ['seq' => 2, 'name' => 'theme_a', 'keep' => 1],
            ['seq' => 3, 'name' => 'plugin_b', 'keep' => 0],
            ['seq' => 4, 'name' => 'plugin_c', 'keep' => 1],
        ]],
    );

    $t->same('["plugin_a"]', $rows[0]['frame_json']);
    $t->same('["plugin_c"]', $rows[1]['frame_json']);
    $t->same('["plugin_c"]', $rows[2]['frame_json']);
};

$tests['json aggregate filter order window current source next84 empty filtered frames return empty arrays'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT option_id, json_group_array(option_name ORDER BY option_name) FILTER (WHERE option_size < 0) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id",
        $tables,
    );

    $t->same(['[]', '[]', '[]', '[]', '[]', '[]'], array_column($rows, 'frame_json'));
};

$tests['json aggregate filter order window current source next84 default range frame aggregates through current row'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(option_name ORDER BY option_name) OVER (ORDER BY option_id) AS frame_json FROM wp_options",
        $tables,
    );

    $t->same([
        '["siteurl"]',
        '["blogname","siteurl"]',
        '["blogname","plugin_rules","siteurl"]',
        '["blogname","plugin_queue","plugin_rules","siteurl"]',
        '["blogname","empty_option","plugin_queue","plugin_rules","siteurl"]',
        '["blogname","empty_option","plugin_queue","plugin_rules","plugin_tail","siteurl"]',
    ], array_column($rows, 'frame_json'));
};

$tests['json aggregate filter order window current source next84 explicit rows frame can omit window order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(option_name ORDER BY option_name) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options",
        $tables,
    );

    $t->same([
        '["blogname","siteurl"]',
        '["blogname","plugin_rules"]',
        '["plugin_queue","plugin_rules"]',
        '["empty_option","plugin_queue"]',
        '["empty_option","plugin_tail"]',
        '["plugin_tail"]',
    ], array_column($rows, 'frame_json'));
};

$tests['json aggregate filter order window current source next84 rejects wildcard json aggregate'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT json_group_array(*) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options", $tables));
};

$tests['json aggregate filter order window current source next84 rejects malformed aggregate order by'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT json_group_array(option_name ORDER BY) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options", $tables));
};

$tests['json aggregate filter order window current source next84 rejects malformed filter'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT json_group_array(option_name ORDER BY option_name) FILTER (option_size > 0) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options", $tables));
};

$tests['json aggregate filter order window current source next84 aliases frame output'] = static function (TestRunner $t) use ($tables, $windowSql): void {
    $rows = SQLiteSelectSql::execute($windowSql, $tables);

    $t->true(array_key_exists('frame_json', $rows[0]));
    $t->same(false, array_key_exists('__window2', $rows[0]));
};

$tests['json aggregate filter order window current source next84 partitioning isolates autoload groups'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT option_id, json_group_array(option_name ORDER BY option_id) FILTER (WHERE option_size >= 0) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS frame_json FROM wp_options ORDER BY option_id",
        $tables,
    );

    $t->same('["siteurl"]', $rows[0]['frame_json']);
    $t->same('["siteurl","blogname"]', $rows[1]['frame_json']);
    $t->same('["plugin_rules"]', $rows[2]['frame_json']);
};

$tests['json aggregate filter order window current source next84 aggregate order can differ from frame order'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT seq, json_group_array(name ORDER BY sort_name) FILTER (WHERE keep) OVER (ORDER BY seq ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS frame_json FROM events ORDER BY seq",
        ['events' => [
            ['seq' => 1, 'sort_name' => 'c', 'name' => 'third', 'keep' => 1],
            ['seq' => 2, 'sort_name' => 'a', 'name' => 'first', 'keep' => 1],
            ['seq' => 3, 'sort_name' => 'b', 'name' => 'second', 'keep' => 1],
        ]],
    );

    $t->same('["first","second","third"]', $rows[0]['frame_json']);
};

$tests['json aggregate filter order window current source next84 jsonb aggregate order can differ from frame order'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT seq, jsonb_group_array(name ORDER BY sort_name) FILTER (WHERE keep) OVER (ORDER BY seq ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS frame_jsonb FROM events ORDER BY seq",
        ['events' => [
            ['seq' => 1, 'sort_name' => 'c', 'name' => 'third', 'keep' => 1],
            ['seq' => 2, 'sort_name' => 'a', 'name' => 'first', 'keep' => 1],
            ['seq' => 3, 'sort_name' => 'b', 'name' => 'second', 'keep' => 1],
        ]],
    );

    $t->same(['first', 'second', 'third'], SQLiteJsonB::decode($rows[0]['frame_jsonb']->bytes));
};

$truthinessCases = [
    'blank string filter is false' => ['  ', '["next"]'],
    'zero string filter is false' => ['0', '["next"]'],
    'fraction string filter is true' => ['0.5', '["first","next"]'],
    'negative string filter is true' => ['-2', '["first","next"]'],
    'null filter is false' => [null, '["next"]'],
    'integer zero filter is false' => [0, '["next"]'],
    'integer one filter is true' => [1, '["first","next"]'],
    'boolean false filter is false' => [false, '["next"]'],
    'boolean true filter is true' => [true, '["first","next"]'],
];

foreach ($truthinessCases as $name => [$filterValue, $expected]) {
    $tests['json aggregate filter order window current source next84 filter truthiness ' . $name] = static function (TestRunner $t) use ($filterValue, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT seq, json_group_array(name ORDER BY sort_name) FILTER (WHERE keep) OVER (ORDER BY seq ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM events ORDER BY seq",
            ['events' => [
                ['seq' => 1, 'sort_name' => 'a', 'name' => 'first', 'keep' => $filterValue],
                ['seq' => 2, 'sort_name' => 'b', 'name' => 'next', 'keep' => 1],
            ]],
        );

        $t->same($expected, $rows[0]['frame_json']);
        $t->same('["next"]', $rows[1]['frame_json']);
    };
}

$frameCases = [
    'rows one preceding current' => ['ROWS BETWEEN 1 PRECEDING AND CURRENT ROW', ['["a"]', '["a","c"]', '["b","c"]']],
    'rows current two following' => ['ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING', ['["a","b","c"]', '["b","c"]', '["b"]']],
    'groups one preceding current' => ['GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW', ['["a"]', '["a","b","c"]', '["a","b","c"]']],
    'groups current following exclude current' => ['GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW', ['["b","c"]', '["b"]', '["c"]']],
    'groups current following exclude ties' => ['GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES', ['["a","b","c"]', '["c"]', '["b"]']],
    'range half following' => ['RANGE BETWEEN CURRENT ROW AND 0.5 FOLLOWING', ['["a","b","c"]', '["b","c"]', '["b","c"]']],
    'range half preceding current' => ['RANGE BETWEEN 0.5 PRECEDING AND CURRENT ROW', ['["a"]', '["a","b","c"]', '["a","b","c"]']],
    'range half following exclude group' => ['RANGE BETWEEN CURRENT ROW AND 0.5 FOLLOWING EXCLUDE GROUP', ['["b","c"]', '[]', '[]']],
];

foreach ($frameCases as $name => [$frameSql, $expected]) {
    $tests['json aggregate filter order window current source next84 frame variant ' . $name] = static function (TestRunner $t) use ($frameSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT seq, json_group_array(name ORDER BY name) FILTER (WHERE keep) OVER (ORDER BY score {$frameSql}) AS frame_json FROM events ORDER BY seq",
            ['events' => [
                ['seq' => 1, 'score' => 1.0, 'name' => 'a', 'keep' => 1],
                ['seq' => 2, 'score' => 1.5, 'name' => 'c', 'keep' => 1],
                ['seq' => 3, 'score' => 1.5, 'name' => 'b', 'keep' => 1],
            ]],
        );

        $t->same($expected, array_column($rows, 'frame_json'));
    };
}

return $tests;
