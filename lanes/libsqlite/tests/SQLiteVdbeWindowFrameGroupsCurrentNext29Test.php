<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;
use PortLibs\LibSqlite\SQLiteSelectSql;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'alpha_cache', 'autoload' => 'yes', 'bytes' => 10, 'bucket' => 'a'],
    ['option_id' => 2, 'option_name' => 'alpha_cache', 'autoload' => 'no', 'bytes' => 10, 'bucket' => 'b'],
    ['option_id' => 3, 'option_name' => 'beta_cache', 'autoload' => 'yes', 'bytes' => 10, 'bucket' => 'c'],
    ['option_id' => 4, 'option_name' => 'cron_lock', 'autoload' => 'no', 'bytes' => 20, 'bucket' => 'd'],
    ['option_id' => 5, 'option_name' => 'cron_lock', 'autoload' => 'yes', 'bytes' => 20, 'bucket' => 'e'],
    ['option_id' => 6, 'option_name' => 'plugin_rules', 'autoload' => 'no', 'bytes' => 30, 'bucket' => 'f'],
    ['option_id' => 7, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bytes' => 30, 'bucket' => 'g'],
    ['option_id' => 8, 'option_name' => 'theme_mods', 'autoload' => 'no', 'bytes' => 30, 'bucket' => 'h'],
];

$tables = ['wp_options' => $rows];

$cases = [
    'count splits composite peers' => ["SELECT count(*) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [3, 3, 3, 3, 3, 3, 2, 2]],
    'sum splits composite peers' => ["SELECT sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [30, 30, 50, 70, 70, 90, 60, 60]],
    'concat preserves composite peer cursor' => ["SELECT group_concat(bucket) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', ['a,b,c', 'a,b,c', 'c,d,e', 'd,e,f', 'd,e,f', 'f,g,h', 'g,h', 'g,h']],
    'current row counts duplicate composite peers' => ["SELECT count(*) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 0 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [2, 2, 1, 2, 2, 1, 2, 2]],
    'current row sums duplicate composite peers' => ["SELECT sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 0 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [20, 20, 10, 40, 40, 30, 60, 60]],
    'two following clamps at tail' => ["SELECT count(*) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [5, 5, 4, 5, 5, 3, 2, 2]],
    'exclude current count retains remaining peers' => ["SELECT count(*) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', [2, 2, 2, 2, 2, 2, 1, 1]],
    'exclude current sum retains remaining peers' => ["SELECT sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', [20, 20, 40, 50, 50, 60, 30, 30]],
    'exclude group count drops composite group only' => ["SELECT count(*) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE GROUP) AS v FROM wp_options ORDER BY option_id", 'v', [1, 1, 2, 1, 1, 2, 0, 0]],
    'exclude group sum drops composite group only' => ["SELECT sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE GROUP) AS v FROM wp_options ORDER BY option_id", 'v', [10, 10, 40, 30, 30, 60, null, null]],
    'exclude ties count keeps current row' => ["SELECT count(*) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', [2, 2, 3, 2, 2, 3, 1, 1]],
    'exclude ties concat keeps current row identity' => ["SELECT group_concat(bucket) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', ['a,c', 'b,c', 'c,d,e', 'd,f', 'e,f', 'f,g,h', 'g', 'h']],
    'desc second term changes next group' => ["SELECT group_concat(bucket) OVER (ORDER BY bytes, option_name DESC GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', ['a,b,d,e', 'a,b,d,e', 'c,a,b', 'd,e,g,h', 'd,e,g,h', 'f', 'g,h,f', 'g,h,f']],
    'partition count isolates autoload' => ["SELECT count(*) OVER (PARTITION BY autoload ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [2, 2, 2, 2, 2, 2, 1, 1]],
    'partition sum isolates autoload' => ["SELECT sum(bytes) OVER (PARTITION BY autoload ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [20, 30, 30, 50, 50, 60, 30, 30]],
    'count text argument follows composite groups' => ["SELECT count(option_name) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [3, 3, 3, 3, 3, 3, 2, 2]],
    'where filters before peer grouping' => ["SELECT sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options WHERE option_id >= 3 ORDER BY option_id", 'v', [50, 70, 70, 90, 60, 60]],
    'limit applies after peer grouping' => ["SELECT sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id LIMIT 4", 'v', [30, 30, 50, 70]],
    'offset applies after peer grouping' => ["SELECT sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id LIMIT 3 OFFSET 4", 'v', [70, 90, 60]],
    'alias survives projection' => ["SELECT option_name AS name, count(*) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS peer_count FROM wp_options ORDER BY option_id LIMIT 1", 'peer_count', [3]],
    'filter count follows composite groups' => ["SELECT count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [2, 2, 2, 1, 1, 1, 1, 1]],
    'filter sum follows composite groups' => ["SELECT sum(bytes) FILTER (WHERE autoload = 'yes') OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', [20, 20, 30, 20, 20, 30, 30, 30]],
    'filter concat follows composite groups' => ["SELECT group_concat(bucket) FILTER (WHERE autoload = 'yes') OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', ['a,c', 'a,c', 'c,e', 'e', 'e', 'g', 'g', 'g']],
    'filter exclude current follows composite groups' => ["SELECT count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', [1, 2, 1, 1, 0, 1, 0, 1]],
    'filter exclude group follows composite groups' => ["SELECT count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE GROUP) AS v FROM wp_options ORDER BY option_id", 'v', [1, 1, 1, 0, 0, 1, 0, 0]],
    'filter exclude ties follows composite groups' => ["SELECT group_concat(bucket) FILTER (WHERE autoload = 'yes') OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', ['a,c', 'c', 'c,e', null, 'e', 'g', 'g', null]],
];

foreach ($cases as $name => [$sql, $field, $expected]) {
    $tests['vdbe window frame groups current next29 ' . $name] = static function (TestRunner $t) use ($sql, $field, $expected, $tables): void {
        $t->same($expected, array_column(SQLiteSelectSql::execute($sql, $tables), $field));
    };
}

$directCases = [
    'direct query plan count' => ['v', [3, 3, 3, 3, 3, 3, 2, 2]],
    'direct helper sum values' => ['s', [30, 30, 50, 70, 70, 90, 60, 60]],
    'direct helper concat values' => ['names', ['alpha_cache,alpha_cache,beta_cache', 'alpha_cache,alpha_cache,beta_cache', 'beta_cache,cron_lock,cron_lock', 'cron_lock,cron_lock,plugin_rules', 'cron_lock,cron_lock,plugin_rules', 'plugin_rules,theme_mods,theme_mods', 'theme_mods,theme_mods', 'theme_mods,theme_mods']],
    'direct helper frame indexes' => ['frame', [[0, 1, 2], [0, 1, 2], [2, 3, 4], [3, 4, 5], [3, 4, 5], [5, 6, 7], [6, 7], [6, 7]]],
    'direct helper exclude group indexes' => ['exclude_group_frame', [[2], [2], [3, 4], [5], [5], [6, 7], [], []]],
    'direct helper exclude ties indexes' => ['exclude_ties_frame', [[0, 2], [1, 2], [2, 3, 4], [3, 5], [4, 5], [5, 6, 7], [6], [7]]],
    'direct helper count values' => ['count_values', [3, 3, 3, 3, 3, 3, 2, 2]],
];

foreach ($directCases as $name => [$field, $expected]) {
    $tests['vdbe window frame groups current next29 ' . $name] = static function (TestRunner $t) use ($field, $expected, $rows): void {
        $peerKeys = array_map(static fn (array $row): array => [$row['bytes'], $row['option_name']], $rows);
        if ($field === 'v') {
            $result = SQLiteSelectQuery::execute([
                'from' => $rows,
                'select' => [[
                    'type' => 'window',
                    'function' => 'count',
                    'arguments' => [['type' => 'wildcard']],
                    'orderBy' => [
                        ['expression' => ['type' => 'column', 'name' => 'bytes'], 'direction' => 'ASC'],
                        ['expression' => ['type' => 'column', 'name' => 'option_name'], 'direction' => 'ASC'],
                    ],
                    'frame' => ['unit' => 'GROUPS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
                    'alias' => 'v',
                ]],
            ]);
            $t->same($expected, array_column($result, 'v'));
            return;
        }
        if ($field === 's') {
            $t->same($expected, SQLiteWindowFunction::aggregateFrameValues('sum', array_column($rows, 'bytes'), $peerKeys, 'GROUPS', 0, 1));
            return;
        }
        if ($field === 'names') {
            $t->same($expected, SQLiteWindowFunction::aggregateFrameValues('group_concat', array_column($rows, 'option_name'), $peerKeys, 'GROUPS', 0, 1));
            return;
        }

        $exclude = $field === 'exclude_group_frame' ? 'GROUP' : ($field === 'exclude_ties_frame' ? 'TIES' : 'NO OTHERS');
        $summary = SQLiteWindowFunction::aggregateFrameRows(array_column($rows, 'bytes'), $peerKeys, 'GROUPS', 0, 1, $exclude);
        $actual = $field === 'count_values' ? array_column($summary, 'count') : array_column($summary, 'frame');
        $t->same($expected, $actual);
    };
}

$tests['vdbe window frame groups current next29 plan records both order terms and frame'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan('SELECT sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options', $tables);
    $t->same(2, count($plan['select'][0]['orderBy']));
    $t->same('bytes', $plan['select'][0]['orderBy'][0]['expression']['name']);
    $t->same('option_name', $plan['select'][0]['orderBy'][1]['expression']['name']);
    $t->same('GROUPS', $plan['select'][0]['frame']['unit']);
    $t->same(1, $plan['select'][0]['frame']['following']);
    $t->same('NO OTHERS', $plan['select'][0]['frame']['exclude']);
};

$tests['vdbe window frame groups current next29 plan records desc order direction'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan('SELECT count(*) OVER (ORDER BY bytes ASC, option_name DESC GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options', $tables);
    $t->same('ASC', $plan['select'][0]['orderBy'][0]['direction']);
    $t->same('DESC', $plan['select'][0]['orderBy'][1]['direction']);
};

$tests['vdbe window frame groups current next29 plan records filter with composite order'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan("SELECT count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options", $tables);
    $t->same('=', $plan['select'][0]['filter']['operator']);
};

$tests['vdbe window frame groups current next29 supports framed value function with composite order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute('SELECT first_value(option_name) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', $tables);

    $t->same(['alpha_cache', 'alpha_cache', 'beta_cache', 'cron_lock', 'cron_lock', 'plugin_rules', 'theme_mods', 'theme_mods'], array_column($rows, 'v'));
};

$tests['vdbe window frame groups current next29 rejects fractional groups offset'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1.5 FOLLOWING) AS v FROM wp_options', $tables));
};

$tests['vdbe window frame groups current next29 rejects malformed composite key member'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameRows([1, 2], [[new stdClass()], [1]], 'GROUPS', 0, 1));
};

$tests['vdbe window frame groups current next29 rejects unsupported frame unit'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameRows([1], [[1, 'a']], 'GROUP', 0, 1));
};

return $tests;
