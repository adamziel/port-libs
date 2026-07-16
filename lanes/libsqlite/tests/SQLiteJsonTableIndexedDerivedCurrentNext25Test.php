<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTableDerivedIndex;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'autoload' => 'yes',
        'option_value' => '{"rules":[{"name":"seo","priority":2,"enabled":true},{"name":"cache","priority":7,"enabled":false}]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'autoload' => 'yes',
        'option_value' => '{"rules":[{"name":"forms","priority":4,"enabled":true},{"name":"media","priority":1,"enabled":false}]}',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'autoload' => 'no',
        'option_value' => '{"rules":[]}',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_null_settings',
        'autoload' => 'no',
        'option_value' => null,
    ],
];

$sql = "SELECT o.option_id AS option_id, o.option_name AS option_name, o.autoload AS autoload, jt.key AS attr, jt.atom AS atom, jt.type AS json_type, jt.fullkey AS fullkey, jt.path AS json_path
          FROM wp_options AS o
          JOIN json_tree(o.option_value, '$.rules') AS jt ON jt.type IN ('text', 'integer', 'true', 'false')
         ORDER BY option_id, fullkey";

$tables = ['wp_options' => $options];
$plan = static fn (): array => SQLiteJsonTableDerivedIndex::materialize($sql, $tables, ['option_name', 'attr'], ['fullkey']);
$lookup = static fn (string $name, string $attr): array => SQLiteJsonTableDerivedIndex::lookup($plan(), ['option_name' => $name, 'attr' => $attr]);
$pairs = static fn (string $name, string $attr): array => SQLiteJsonTableDerivedIndex::adjacentFor($plan(), ['option_name' => $name, 'attr' => $attr]);

$tests = [
    'materializes derived json leaf rows' => static function (TestRunner $t) use ($plan): void {
        $t->same(12, count($plan()['rows']));
    },
    'materialized rows keep source option ids' => static function (TestRunner $t) use ($plan): void {
        $t->same([1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 2], array_column($plan()['rows'], 'option_id'));
    },
    'materialized rows keep option names' => static function (TestRunner $t) use ($plan): void {
        $t->same(['plugin_alpha_settings', 'plugin_alpha_settings', 'plugin_alpha_settings'], array_slice(array_column($plan()['rows'], 'option_name'), 0, 3));
    },
    'materialized rows omit empty json source' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, in_array('plugin_empty_settings', array_column($plan()['rows'], 'option_name'), true));
    },
    'materialized rows omit sql null json source' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, in_array('plugin_null_settings', array_column($plan()['rows'], 'option_name'), true));
    },
    'records indexed columns' => static function (TestRunner $t) use ($plan): void {
        $t->same(['option_name', 'attr'], $plan()['indexColumns']);
    },
    'records order columns' => static function (TestRunner $t) use ($plan): void {
        $t->same(['fullkey'], $plan()['orderColumns']);
    },
    'builds one index key per option attribute' => static function (TestRunner $t) use ($plan): void {
        $t->same(6, count($plan()['indexes']));
    },
    'stores one row key per materialized row' => static function (TestRunner $t) use ($plan): void {
        $t->same(12, count($plan()['keys']));
    },
    'lookup alpha names returns two rows' => static function (TestRunner $t) use ($lookup): void {
        $t->same(2, count($lookup('plugin_alpha_settings', 'name')));
    },
    'lookup alpha names preserves current next order' => static function (TestRunner $t) use ($lookup): void {
        $t->same(['seo', 'cache'], array_column($lookup('plugin_alpha_settings', 'name'), 'atom'));
    },
    'lookup alpha priorities returns integers' => static function (TestRunner $t) use ($lookup): void {
        $t->same([2, 7], array_column($lookup('plugin_alpha_settings', 'priority'), 'atom'));
    },
    'lookup alpha enabled returns sqlite boolean atoms' => static function (TestRunner $t) use ($lookup): void {
        $t->same([1, 0], array_column($lookup('plugin_alpha_settings', 'enabled'), 'atom'));
    },
    'lookup beta names returns two rows' => static function (TestRunner $t) use ($lookup): void {
        $t->same(['forms', 'media'], array_column($lookup('plugin_beta_settings', 'name'), 'atom'));
    },
    'lookup beta priorities returns two rows' => static function (TestRunner $t) use ($lookup): void {
        $t->same([4, 1], array_column($lookup('plugin_beta_settings', 'priority'), 'atom'));
    },
    'lookup beta enabled returns two rows' => static function (TestRunner $t) use ($lookup): void {
        $t->same([1, 0], array_column($lookup('plugin_beta_settings', 'enabled'), 'atom'));
    },
    'lookup missing option returns empty rows' => static function (TestRunner $t) use ($lookup): void {
        $t->same([], $lookup('missing_option', 'name'));
    },
    'lookup missing attribute returns empty rows' => static function (TestRunner $t) use ($lookup): void {
        $t->same([], $lookup('plugin_alpha_settings', 'missing'));
    },
    'current next alpha names has two pairs' => static function (TestRunner $t) use ($pairs): void {
        $t->same(2, count($pairs('plugin_alpha_settings', 'name')));
    },
    'current next alpha first row points to second name' => static function (TestRunner $t) use ($pairs): void {
        $t->same('cache', $pairs('plugin_alpha_settings', 'name')[0]['next']['atom']);
    },
    'current next alpha second row has no next' => static function (TestRunner $t) use ($pairs): void {
        $t->same(null, $pairs('plugin_alpha_settings', 'name')[1]['next']);
    },
    'current next alpha first position is stable' => static function (TestRunner $t) use ($pairs): void {
        $t->same(1, $pairs('plugin_alpha_settings', 'name')[0]['currentPosition']);
    },
    'current next alpha second position is stable' => static function (TestRunner $t) use ($pairs): void {
        $t->same(4, $pairs('plugin_alpha_settings', 'name')[0]['nextPosition']);
    },
    'current next beta priority first row points to next priority' => static function (TestRunner $t) use ($pairs): void {
        $t->same(1, $pairs('plugin_beta_settings', 'priority')[0]['next']['atom']);
    },
    'current next beta priority second row has no next' => static function (TestRunner $t) use ($pairs): void {
        $t->same(null, $pairs('plugin_beta_settings', 'priority')[1]['next']);
    },
    'current next keys preserve option name' => static function (TestRunner $t) use ($pairs): void {
        $t->same('plugin_beta_settings', $pairs('plugin_beta_settings', 'enabled')[0]['key']['option_name']);
    },
    'current next keys preserve attribute' => static function (TestRunner $t) use ($pairs): void {
        $t->same('enabled', $pairs('plugin_beta_settings', 'enabled')[0]['key']['attr']);
    },
    'all current next pairs include every materialized row' => static function (TestRunner $t) use ($plan): void {
        $t->same(12, count(SQLiteJsonTableDerivedIndex::adjacentPairs($plan())));
    },
    'all current next pairs keep first option grouping' => static function (TestRunner $t) use ($plan): void {
        $t->same('plugin_alpha_settings', SQLiteJsonTableDerivedIndex::adjacentPairs($plan())[0]['key']['option_name']);
    },
    'all current next pairs keep first attribute grouping' => static function (TestRunner $t) use ($plan): void {
        $t->same('enabled', SQLiteJsonTableDerivedIndex::adjacentPairs($plan())[0]['key']['attr']);
    },
    'derived select can read indexed rows back through select sql' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name, attr, atom FROM ({$sql}) AS derived WHERE attr = 'priority' ORDER BY option_name, atom", $tables);
        $t->same([2, 7, 1, 4], array_column($rows, 'atom'));
    },
    'derived select outer filter keeps names' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT atom FROM ({$sql}) AS derived WHERE attr = 'name' AND option_name = 'plugin_beta_settings' ORDER BY atom", $tables);
        $t->same(['forms', 'media'], array_column($rows, 'atom'));
    },
    'derived select outer range filter keeps high priorities' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name, atom FROM ({$sql}) AS derived WHERE attr = 'priority' AND atom >= 4 ORDER BY atom", $tables);
        $t->same([4, 7], array_column($rows, 'atom'));
    },
    'derived select outer IN filter keeps boolean attributes' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT atom FROM ({$sql}) AS derived WHERE attr IN ('enabled') ORDER BY option_name, fullkey", $tables);
        $t->same([1, 0, 1, 0], array_column($rows, 'atom'));
    },
    'derived select outer group by counts attributes' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT attr, count(atom) AS total FROM ({$sql}) AS derived GROUP BY attr ORDER BY attr", $tables);
        $t->same([['attr' => 'enabled', 'total' => 4], ['attr' => 'name', 'total' => 4], ['attr' => 'priority', 'total' => 4]], $rows);
    },
    'derived select outer having filters attribute groups' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT attr, count(atom) AS total FROM ({$sql}) AS derived GROUP BY attr HAVING count(atom) = 4 ORDER BY attr", $tables);
        $t->same(['enabled', 'name', 'priority'], array_column($rows, 'attr'));
    },
    'typed index distinguishes text 1 from integer 1' => static function (TestRunner $t): void {
        $plan = SQLiteJsonTableDerivedIndex::materialize("SELECT '1' AS k UNION ALL SELECT 1 AS k", [], ['k']);
        $t->same(['1'], array_column(SQLiteJsonTableDerivedIndex::lookup($plan, ['k' => '1']), 'k'));
    },
    'typed index looks up integer one independently' => static function (TestRunner $t): void {
        $plan = SQLiteJsonTableDerivedIndex::materialize("SELECT '1' AS k UNION ALL SELECT 1 AS k", [], ['k']);
        $t->same([1], array_column(SQLiteJsonTableDerivedIndex::lookup($plan, ['k' => 1]), 'k'));
    },
    'null indexed values can be looked up' => static function (TestRunner $t): void {
        $plan = SQLiteJsonTableDerivedIndex::materialize('SELECT NULL AS k UNION ALL SELECT 1 AS k', [], ['k']);
        $t->same([null], array_column(SQLiteJsonTableDerivedIndex::lookup($plan, ['k' => null]), 'k'));
    },
    'rejects empty index columns' => static function (TestRunner $t) use ($sql, $tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTableDerivedIndex::materialize($sql, $tables, []));
    },
    'rejects invalid index column names' => static function (TestRunner $t) use ($sql, $tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTableDerivedIndex::materialize($sql, $tables, ['option-name']));
    },
    'rejects invalid order column names' => static function (TestRunner $t) use ($sql, $tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTableDerivedIndex::materialize($sql, $tables, ['option_name'], ['full.key']));
    },
    'rejects unavailable index column' => static function (TestRunner $t) use ($sql, $tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTableDerivedIndex::materialize($sql, $tables, ['missing']));
    },
    'rejects unavailable order column' => static function (TestRunner $t) use ($sql, $tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTableDerivedIndex::materialize($sql, $tables, ['option_name'], ['missing']));
    },
    'rejects lookup missing first key column' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTableDerivedIndex::lookup($plan(), ['attr' => 'name']));
    },
    'rejects lookup missing second key column' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTableDerivedIndex::lookup($plan(), ['option_name' => 'plugin_alpha_settings']));
    },
    'rejects current next lookup missing key column' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTableDerivedIndex::adjacentFor($plan(), ['option_name' => 'plugin_alpha_settings']));
    },
    'preserves sql text in plan' => static function (TestRunner $t) use ($plan, $sql): void {
        $t->same($sql, $plan()['sql']);
    },
    'lookup returns complete derived row shape' => static function (TestRunner $t) use ($lookup): void {
        $t->same(['option_id', 'option_name', 'autoload', 'attr', 'atom', 'json_type', 'fullkey', 'json_path'], array_keys($lookup('plugin_alpha_settings', 'name')[0]));
    },
    'lookup preserves json path for current row' => static function (TestRunner $t) use ($lookup): void {
        $t->same('$.rules[0]', $lookup('plugin_alpha_settings', 'priority')[0]['json_path']);
    },
    'lookup preserves fullkey for next row' => static function (TestRunner $t) use ($lookup): void {
        $t->same('$.rules[1].priority', $lookup('plugin_alpha_settings', 'priority')[1]['fullkey']);
    },
    'current next exposes next fullkey' => static function (TestRunner $t) use ($pairs): void {
        $t->same('$.rules[1].enabled', $pairs('plugin_alpha_settings', 'enabled')[0]['next']['fullkey']);
    },
    'current next keeps terminal current fullkey' => static function (TestRunner $t) use ($pairs): void {
        $t->same('$.rules[1].enabled', $pairs('plugin_alpha_settings', 'enabled')[1]['current']['fullkey']);
    },
    'materialized json types preserve sqlite labels' => static function (TestRunner $t) use ($plan): void {
        $t->same(['true', 'text', 'integer', 'false'], array_slice(array_column($plan()['rows'], 'json_type'), 0, 4));
    },
    'materialized autoload values survive derived rows' => static function (TestRunner $t) use ($plan): void {
        $t->same(['yes'], array_values(array_unique(array_column($plan()['rows'], 'autoload'))));
    },
    'derived order within index is independent from global row order' => static function (TestRunner $t) use ($pairs): void {
        $t->same(['seo', 'cache'], [$pairs('plugin_alpha_settings', 'name')[0]['current']['atom'], $pairs('plugin_alpha_settings', 'name')[0]['next']['atom']]);
    },
    'current next for absent key is empty' => static function (TestRunner $t) use ($pairs): void {
        $t->same([], $pairs('plugin_empty_settings', 'name'));
    },
    'current next respects composite key boundaries' => static function (TestRunner $t) use ($pairs): void {
        $t->same(null, $pairs('plugin_alpha_settings', 'priority')[1]['next']);
    },
    'index lookup does not mutate plan rows' => static function (TestRunner $t) use ($plan): void {
        $materialized = $plan();
        SQLiteJsonTableDerivedIndex::lookup($materialized, ['option_name' => 'plugin_alpha_settings', 'attr' => 'name']);
        $t->same(12, count($materialized['rows']));
    },
];

foreach ($tests as $name => $case) {
    $tests['json table indexed derived current next25 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
