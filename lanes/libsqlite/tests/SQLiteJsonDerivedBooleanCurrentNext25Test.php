<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache',
        'option_value' => '{"enabled":true,"network":false,"count":3,"flag_text":"1true","zero_text":"0false","label":"cache"}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'enabled' => false,
            'network' => true,
            'count' => 0,
            'flag_text' => '2beta',
            'zero_text' => 'false',
            'label' => 'forms',
        ])),
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty',
        'option_value' => '{"enabled":false,"network":false,"count":null,"flag_text":"enabled","zero_text":"0","label":"empty"}',
        'autoload' => 'no',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_mu',
        'option_value' => '{"enabled":true,"network":true,"count":-1,"flag_text":"  -4","zero_text":"","label":"mu"}',
        'autoload' => 'yes',
    ],
];

$select = static fn (string $sql, array $parameters = []): array => SQLiteSelectSql::execute($sql, ['wp_options' => $rows], $parameters);
$column = static fn (string $sql, string $column = 'option_name'): array => array_column($select($sql), $column);
$scalar = static function (string $sql, array $parameters = []) use ($select): mixed {
    $result = $select($sql, $parameters);
    if (count($result) !== 1) {
        throw new RuntimeException('Expected one SQLite SELECT SQL result row');
    }

    return reset($result[0]);
};

return [
    'json derived boolean filters true json_extract rows' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') ORDER BY option_id")),
    'json derived boolean filters false json_extract rows through not' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_empty'], $column("SELECT option_name FROM wp_options WHERE NOT json_extract(option_value, '$.enabled') ORDER BY option_id")),
    'json derived boolean filters jsonb_extract true rows' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE jsonb_extract(option_value, '$.network') ORDER BY option_id")),
    'json derived boolean filters json text operator true rows' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.enabled' ORDER BY option_id")),
    'json derived boolean treats json value operator true text as numeric false' => static fn (TestRunner $t) => $t->same([], $column("SELECT option_name FROM wp_options WHERE option_value -> '$.enabled' ORDER BY option_id")),
    'json derived boolean true literal filters all rows' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_empty', 'plugin_mu'], $column('SELECT option_name FROM wp_options WHERE TRUE ORDER BY option_id')),
    'json derived boolean false literal filters no rows' => static fn (TestRunner $t) => $t->same([], $column('SELECT option_name FROM wp_options WHERE FALSE ORDER BY option_id')),
    'json derived boolean not true literal filters no rows' => static fn (TestRunner $t) => $t->same([], $column('SELECT option_name FROM wp_options WHERE NOT TRUE ORDER BY option_id')),
    'json derived boolean not false literal filters all rows' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_empty', 'plugin_mu'], $column('SELECT option_name FROM wp_options WHERE NOT FALSE ORDER BY option_id')),
    'json derived boolean supports is true over json_extract' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') IS TRUE ORDER BY option_id")),
    'json derived boolean supports is false over json_extract' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_empty'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') IS FALSE ORDER BY option_id")),
    'json derived boolean supports is not true over json_extract' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_empty'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') IS NOT TRUE ORDER BY option_id")),
    'json derived boolean supports is not false over json_extract' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') IS NOT FALSE ORDER BY option_id")),
    'json derived boolean treats null path as not true' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_empty', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.missing') IS NOT TRUE ORDER BY option_id")),
    'json derived boolean treats null path as not false' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_empty', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.missing') IS NOT FALSE ORDER BY option_id")),
    'json derived boolean null path is not bare true' => static fn (TestRunner $t) => $t->same([], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.missing') ORDER BY option_id")),
    'json derived boolean null path not remains null filtered' => static fn (TestRunner $t) => $t->same([], $column("SELECT option_name FROM wp_options WHERE NOT json_extract(option_value, '$.missing') ORDER BY option_id")),
    'json derived boolean and short-circuits false rows' => static fn (TestRunner $t) => $t->same(['plugin_cache'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') AND autoload = 'yes' AND option_id < 4 ORDER BY option_id")),
    'json derived boolean or accepts network true rows' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') OR json_extract(option_value, '$.network') ORDER BY option_id")),
    'json derived boolean not composes with and' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $column("SELECT option_name FROM wp_options WHERE NOT json_extract(option_value, '$.enabled') AND json_extract(option_value, '$.network') ORDER BY option_id")),
    'json derived boolean text numeric prefix one is true' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.flag_text') ORDER BY option_id")),
    'json derived boolean text no numeric prefix is false' => static fn (TestRunner $t) => $t->same(['plugin_empty'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.flag_text') IS FALSE ORDER BY option_id")),
    'json derived boolean zero-prefixed text is false' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_empty', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.zero_text') IS FALSE ORDER BY option_id")),
    'json derived boolean integer count truth filters nonzero rows' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.count') ORDER BY option_id")),
    'json derived boolean integer count false filters zero row' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.count') IS FALSE ORDER BY option_id")),
    'json derived boolean integer null count is not false' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_empty', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.count') IS NOT FALSE ORDER BY option_id")),
    'json derived boolean case when json true' => static fn (TestRunner $t) => $t->same(['on', 'off', 'off', 'on'], $column("SELECT CASE WHEN json_extract(option_value, '$.enabled') THEN 'on' ELSE 'off' END AS state FROM wp_options ORDER BY option_id", 'state')),
    'json derived boolean case when network json true' => static fn (TestRunner $t) => $t->same(['network-no', 'network-yes', 'network-no', 'network-yes'], $column("SELECT CASE WHEN json_extract(option_value, '$.network') THEN 'network-yes' ELSE 'network-no' END AS state FROM wp_options ORDER BY option_id", 'state')),
    'json derived boolean case when numeric text true' => static fn (TestRunner $t) => $t->same(['flag-on', 'flag-on', 'flag-off', 'flag-on'], $column("SELECT CASE WHEN json_extract(option_value, '$.flag_text') THEN 'flag-on' ELSE 'flag-off' END AS state FROM wp_options ORDER BY option_id", 'state')),
    'json derived boolean where with bound true literal' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu'], array_column(SQLiteSelectSql::execute("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') IS ? ORDER BY option_id", ['wp_options' => $rows], [true]), 'option_name')),
    'json derived boolean where with bound false literal' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_empty'], array_column(SQLiteSelectSql::execute("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') IS ? ORDER BY option_id", ['wp_options' => $rows], [false]), 'option_name')),
    'json derived boolean constant select true' => static fn (TestRunner $t) => $t->same(1, $scalar('SELECT TRUE WHERE TRUE')),
    'json derived boolean constant select false filtered' => static fn (TestRunner $t) => $t->same([], SQLiteSelectSql::execute('SELECT TRUE AS value WHERE FALSE', [])),
    'json derived boolean constant select not false' => static fn (TestRunner $t) => $t->same(1, $scalar('SELECT TRUE WHERE NOT FALSE')),
    'json derived boolean constant select is true' => static fn (TestRunner $t) => $t->same(1, $scalar('SELECT TRUE WHERE 5 IS TRUE')),
    'json derived boolean constant select is false' => static fn (TestRunner $t) => $t->same(1, $scalar('SELECT TRUE WHERE 0 IS FALSE')),
    'json derived boolean constant select null is not true' => static fn (TestRunner $t) => $t->same(1, $scalar('SELECT TRUE WHERE NULL IS NOT TRUE')),
    'json derived boolean constant select null is not false' => static fn (TestRunner $t) => $t->same(1, $scalar('SELECT TRUE WHERE NULL IS NOT FALSE')),
    'json derived boolean constant select true is not false' => static fn (TestRunner $t) => $t->same(1, $scalar('SELECT TRUE WHERE TRUE IS NOT FALSE')),
    'json derived boolean constant select false is not true' => static fn (TestRunner $t) => $t->same(1, $scalar('SELECT TRUE WHERE FALSE IS NOT TRUE')),
    'json derived boolean order by boolean expression' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu', 'plugin_forms', 'plugin_empty'], $column("SELECT option_name FROM wp_options ORDER BY json_extract(option_value, '$.enabled') DESC, option_id")),
    'json derived boolean join on bare json true' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu'], $column("SELECT option_name FROM wp_options JOIN json_each('[1]') AS tag ON json_extract(option_value, '$.enabled') ORDER BY option_id")),
    'json derived boolean join on not json true' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_empty'], $column("SELECT option_name FROM wp_options JOIN json_each('[1]') AS tag ON NOT json_extract(option_value, '$.enabled') ORDER BY option_id")),
    'json derived boolean cte filters true json' => static fn (TestRunner $t) => $t->same(['cache', 'mu'], array_column(SQLiteSelectSql::execute("WITH seed(name, doc) AS (VALUES ('cache','{\"enabled\":true}'), ('forms','{\"enabled\":false}'), ('mu','{\"enabled\":true}')) SELECT name FROM seed WHERE json_extract(doc, '$.enabled') ORDER BY name", []), 'name')),
    'json derived boolean cte filters false json' => static fn (TestRunner $t) => $t->same(['forms'], array_column(SQLiteSelectSql::execute("WITH seed(name, doc) AS (VALUES ('cache','{\"enabled\":true}'), ('forms','{\"enabled\":false}'), ('mu','{\"enabled\":true}')) SELECT name FROM seed WHERE json_extract(doc, '$.enabled') IS FALSE ORDER BY name", []), 'name')),
    'json derived boolean jsonb blob true is true' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE jsonb_extract(option_value, '$.network') IS TRUE ORDER BY option_id")),
    'json derived boolean jsonb blob false is false' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_empty'], $column("SELECT option_name FROM wp_options WHERE jsonb_extract(option_value, '$.network') IS FALSE ORDER BY option_id")),
    'json derived boolean extracted value compares equal to true literal' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') = TRUE ORDER BY option_id")),
    'json derived boolean extracted value compares equal to false literal' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_empty'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') = FALSE ORDER BY option_id")),
    'json derived boolean text operator compares equal to true literal' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.enabled' = TRUE ORDER BY option_id")),
    'json derived boolean text operator compares equal to false literal' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_empty'], $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.enabled' = FALSE ORDER BY option_id")),
    'json derived boolean not over numeric text prefix' => static fn (TestRunner $t) => $t->same(['plugin_empty'], $column("SELECT option_name FROM wp_options WHERE NOT json_extract(option_value, '$.flag_text') ORDER BY option_id")),
    'json derived boolean not over zero text prefix' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_empty', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE NOT json_extract(option_value, '$.zero_text') ORDER BY option_id")),
    'json derived boolean derived arithmetic truth accepts nonzero' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mu'], $column("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$.enabled') + json_extract(option_value, '$.network') ORDER BY option_id")),
    'json derived boolean derived arithmetic truth rejects zero' => static fn (TestRunner $t) => $t->same(['plugin_empty'], $column("SELECT option_name FROM wp_options WHERE (json_extract(option_value, '$.enabled') + json_extract(option_value, '$.network')) IS FALSE ORDER BY option_id")),
    'json derived boolean whole object truth is numeric false' => static fn (TestRunner $t) => $t->same([], SQLiteSelectSql::execute("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$')", ['wp_options' => $rows])),
    'json derived boolean rejects malformed json path in truth predicate' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT option_name FROM wp_options WHERE json_extract(option_value, '$[#-]')", ['wp_options' => $rows])),
];
