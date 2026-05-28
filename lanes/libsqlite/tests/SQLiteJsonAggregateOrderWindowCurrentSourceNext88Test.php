<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'sort_key' => 40, 'enabled' => 1, 'option_value' => 'https://example.test'],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'blogname', 'sort_key' => 30, 'enabled' => 1, 'option_value' => 'Port Fixture'],
        ['option_id' => 3, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'sort_key' => 20, 'enabled' => 1, 'option_value' => new SQLiteJsonSubtypeValue('{"kind":"rules"}')],
        ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'sort_key' => 10, 'enabled' => 1, 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'queue']))],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'sort_key' => 50, 'enabled' => 1, 'option_value' => new SQLiteJsonSubtypeValue('{"kind":"rules"}')],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'empty_option', 'sort_key' => 60, 'enabled' => 0, 'option_value' => null],
    ],
];

$tests['json aggregate order window current source next88 distinct rows frame orders desc before dedupe'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY sort_key DESC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id',
        $tables,
    );

    $t->same('["siteurl","blogname"]', $rows[0]['frame_json']);
    $t->same('["blogname"]', $rows[1]['frame_json']);
    $t->same('["plugin_rules","plugin_queue"]', $rows[2]['frame_json']);
    $t->same('["empty_option","plugin_rules","plugin_queue"]', $rows[3]['frame_json']);
    $t->same('["empty_option","plugin_rules"]', $rows[4]['frame_json']);
    $t->same('["empty_option"]', $rows[5]['frame_json']);
};

$tests['json aggregate order window current source next88 distinct rows frame can order asc'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY sort_key ASC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id',
        $tables,
    );

    $t->same('["blogname","siteurl"]', $rows[0]['frame_json']);
    $t->same('["blogname"]', $rows[1]['frame_json']);
    $t->same('["plugin_queue","plugin_rules"]', $rows[2]['frame_json']);
    $t->same('["plugin_queue","plugin_rules","empty_option"]', $rows[3]['frame_json']);
    $t->same('["plugin_rules","empty_option"]', $rows[4]['frame_json']);
};

$tests['json aggregate order window current source next88 jsonb dispatch keeps distinct ordered array'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, jsonb_group_array(DISTINCT option_name ORDER BY sort_key DESC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS frame_jsonb FROM wp_options ORDER BY option_id',
        $tables,
    );

    $t->true($rows[2]['frame_jsonb'] instanceof SQLiteBlobValue);
    $t->same(['plugin_rules', 'plugin_queue'], SQLiteJsonB::decode($rows[2]['frame_jsonb']->bytes));
    $t->same(['empty_option', 'plugin_rules', 'plugin_queue'], SQLiteJsonB::decode($rows[3]['frame_jsonb']->bytes));
};

$tests['json aggregate order window current source next88 filter runs before distinct'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY sort_key DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id',
        $tables,
    );

    $t->same('["plugin_rules","plugin_queue"]', $rows[2]['frame_json']);
    $t->same('["plugin_rules","plugin_queue"]', $rows[3]['frame_json']);
    $t->same('["plugin_rules"]', $rows[4]['frame_json']);
    $t->same('[]', $rows[5]['frame_json']);
    $t->same(6, count($rows));
};

$tests['json aggregate order window current source next88 duplicate winner follows sorted order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY sort_key DESC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS frame_json FROM wp_options ORDER BY option_id',
        $tables,
    );

    $t->same('["plugin_rules","plugin_queue"]', $rows[4]['frame_json']);
    $t->same('["empty_option","plugin_rules","plugin_queue"]', $rows[5]['frame_json']);
};

$tests['json aggregate order window current source next88 aggregate order differs from frame order'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_array(DISTINCT name ORDER BY rank DESC) OVER (ORDER BY seq ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'rank' => 20, 'name' => 'second'],
            ['seq' => 2, 'rank' => 40, 'name' => 'first'],
            ['seq' => 3, 'rank' => 30, 'name' => 'third'],
            ['seq' => 4, 'rank' => 10, 'name' => 'second'],
        ]],
    );

    $t->same('["first","third","second"]', $rows[0]['frame_json']);
    $t->same('["first","third","second"]', $rows[1]['frame_json']);
};

$tests['json aggregate order window current source next88 groups frame handles peer duplicate dedupe'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_array(DISTINCT name ORDER BY rank DESC) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'bucket' => 1, 'rank' => 10, 'name' => 'alpha'],
            ['seq' => 2, 'bucket' => 2, 'rank' => 30, 'name' => 'beta'],
            ['seq' => 3, 'bucket' => 2, 'rank' => 20, 'name' => 'alpha'],
            ['seq' => 4, 'bucket' => 3, 'rank' => 40, 'name' => 'gamma'],
        ]],
    );

    $t->same('["beta","alpha"]', $rows[0]['frame_json']);
    $t->same('["gamma","beta","alpha"]', $rows[1]['frame_json']);
    $t->same('["gamma","beta","alpha"]', $rows[2]['frame_json']);
};

$tests['json aggregate order window current source next88 exclude current row dedupes remaining peers'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_array(DISTINCT name ORDER BY rank DESC) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'bucket' => 1, 'rank' => 10, 'name' => 'alpha'],
            ['seq' => 2, 'bucket' => 2, 'rank' => 30, 'name' => 'beta'],
            ['seq' => 3, 'bucket' => 2, 'rank' => 20, 'name' => 'alpha'],
            ['seq' => 4, 'bucket' => 3, 'rank' => 40, 'name' => 'gamma'],
        ]],
    );

    $t->same('["beta","alpha"]', $rows[0]['frame_json']);
    $t->same('["gamma","alpha"]', $rows[1]['frame_json']);
    $t->same('["gamma","beta"]', $rows[2]['frame_json']);
};

$tests['json aggregate order window current source next88 range frame keeps numeric band'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_array(DISTINCT name ORDER BY rank DESC) OVER (ORDER BY score RANGE BETWEEN CURRENT ROW AND 0.5 FOLLOWING) AS frame_json FROM events ORDER BY seq',
        ['events' => [
            ['seq' => 1, 'score' => 1.0, 'rank' => 20, 'name' => 'alpha'],
            ['seq' => 2, 'score' => 1.25, 'rank' => 30, 'name' => 'beta'],
            ['seq' => 3, 'score' => 1.5, 'rank' => 10, 'name' => 'alpha'],
            ['seq' => 4, 'score' => 2.0, 'rank' => 40, 'name' => 'tail'],
        ]],
    );

    $t->same('["beta","alpha"]', $rows[0]['frame_json']);
    $t->same('["beta","alpha"]', $rows[1]['frame_json']);
    $t->same('["tail","alpha"]', $rows[2]['frame_json']);
};

$tests['json aggregate order window current source next88 json subtype and jsonb values stay distinct classes'] = static function (TestRunner $t): void {
    $json = new SQLiteJsonSubtypeValue('{"enabled":true}');
    $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode(['enabled' => true]));
    $rows = SQLiteSelectSql::execute(
        'SELECT seq, json_group_array(DISTINCT payload ORDER BY rank DESC) OVER (ORDER BY seq ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS frame_json FROM payloads ORDER BY seq',
        ['payloads' => [
            ['seq' => 1, 'rank' => 10, 'payload' => $json],
            ['seq' => 2, 'rank' => 30, 'payload' => $jsonb],
            ['seq' => 3, 'rank' => 20, 'payload' => $json],
        ]],
    );

    $t->same('[{"enabled":true},{"enabled":true}]', $rows[0]['frame_json']);
    $t->same('[{"enabled":true},{"enabled":true}]', $rows[1]['frame_json']);
};

$tests['json aggregate order window current source next88 final order can use distinct window output'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY sort_key DESC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS frame_json FROM wp_options ORDER BY frame_json DESC, option_id',
        $tables,
    );

    $t->same([1, 3, 6, 5, 4, 2], array_column($rows, 'option_id'));
    $t->same('["siteurl","blogname"]', $rows[0]['frame_json']);
    $t->same('["blogname"]', $rows[5]['frame_json']);
};

$tests['json aggregate order window current source next88 rejects distinct wildcard window aggregate'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT *) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate order window current source next88 rejects distinct without argument'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate order window current source next88 rejects bad aggregate order direction'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY sort_key SIDEWAYS) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate order window current source next88 rejects distinct for count window'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT count(DISTINCT option_name) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_count FROM wp_options',
        $tables,
    ));
};

return $tests;
