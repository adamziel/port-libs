<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'autoload' => 'yes',
        'option_value' => '{"rules":[{"name":"seo","enabled":true,"priority":2},{"name":"cache","enabled":false,"priority":7}],"version":3}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'autoload' => 'yes',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'rules' => [
                ['name' => 'forms', 'enabled' => true, 'priority' => 4],
                ['name' => 'media', 'enabled' => false, 'priority' => 1],
            ],
            'version' => 9,
        ])),
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'autoload' => 'no',
        'option_value' => '{"rules":[],"version":1}',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_null_settings',
        'autoload' => 'no',
        'option_value' => null,
    ],
];

$tables = ['wp_options' => $options];
$ruleRows = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name, j.key AS attr, j.atom AS atom, j.type AS json_type, j.fullkey AS fullkey, j.rowid AS rowid_alias
       FROM wp_options AS o
       JOIN json_tree(o.option_value, '$.rules') AS j
         ON j.key IN ('name', 'priority') AND j.atom IS NOT NULL
      ORDER BY option_name, fullkey",
    $tables,
);
$priorityRows = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name, j.atom AS priority, j.fullkey AS fullkey
       FROM wp_options AS o
       JOIN json_tree(o.option_value, '$.rules') AS j
         ON j.key = 'priority' AND j.atom >= 4
      ORDER BY priority",
    $tables,
);
$leftRows = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name, j.atom AS matched_name, j.rowid AS matched_rowid
       FROM wp_options AS o
       LEFT JOIN json_tree(o.option_value, '$.rules') AS j
         ON j.key = 'missing' AND j.atom IS NOT NULL
      ORDER BY option_name",
    $tables,
);
$rowidRows = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name, j.key AS key_name, j.rowid AS rowid_alias, j.fullkey AS fullkey
       FROM wp_options AS o
       JOIN json_tree(o.option_value, '$.rules') AS j
         ON j.rowid IN (2, 6)
      ORDER BY option_name",
    $tables,
);
$plan = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_name AS option_name, j.atom AS atom
       FROM wp_options AS o
       JOIN json_tree(o.option_value, '$.rules') AS j
         ON j.key IN ('name', 'priority') AND j.atom IS NOT NULL",
    $tables,
);

$tests = [];

foreach ([
    'rule row count after ON pushdown' => [static fn (): mixed => count($ruleRows()), 8],
    'rule rows keep option order' => [static fn (): mixed => array_column($ruleRows(), 'option_name'), [
        'plugin_alpha_settings',
        'plugin_alpha_settings',
        'plugin_alpha_settings',
        'plugin_alpha_settings',
        'plugin_beta_settings',
        'plugin_beta_settings',
        'plugin_beta_settings',
        'plugin_beta_settings',
    ]],
    'rule rows keep current alpha key order' => [static fn (): mixed => array_slice(array_column($ruleRows(), 'attr'), 0, 4), ['name', 'priority', 'name', 'priority']],
    'rule rows keep next beta key order' => [static fn (): mixed => array_slice(array_column($ruleRows(), 'attr'), 4, 4), ['name', 'priority', 'name', 'priority']],
    'rule rows expose alpha text atoms' => [static fn (): mixed => [$ruleRows()[0]['atom'], $ruleRows()[2]['atom']], ['seo', 'cache']],
    'rule rows expose alpha integer atoms' => [static fn (): mixed => [$ruleRows()[1]['atom'], $ruleRows()[3]['atom']], [2, 7]],
    'rule rows expose jsonb beta text atoms' => [static fn (): mixed => [$ruleRows()[4]['atom'], $ruleRows()[6]['atom']], ['forms', 'media']],
    'rule rows expose jsonb beta integer atoms' => [static fn (): mixed => [$ruleRows()[5]['atom'], $ruleRows()[7]['atom']], [4, 1]],
    'rule rows omit boolean enabled keys' => [static fn (): mixed => in_array('enabled', array_column($ruleRows(), 'attr'), true), false],
    'rule rows omit root version keys' => [static fn (): mixed => in_array('version', array_column($ruleRows(), 'attr'), true), false],
    'rule rows omit empty source option' => [static fn (): mixed => in_array('plugin_empty_settings', array_column($ruleRows(), 'option_name'), true), false],
    'rule rows omit null source option' => [static fn (): mixed => in_array('plugin_null_settings', array_column($ruleRows(), 'option_name'), true), false],
    'rule rows preserve fullkeys for alpha current' => [static fn (): mixed => array_slice(array_column($ruleRows(), 'fullkey'), 0, 4), ['$.rules[0].name', '$.rules[0].priority', '$.rules[1].name', '$.rules[1].priority']],
    'rule rows preserve fullkeys for beta next' => [static fn (): mixed => array_slice(array_column($ruleRows(), 'fullkey'), 4, 4), ['$.rules[0].name', '$.rules[0].priority', '$.rules[1].name', '$.rules[1].priority']],
    'rule rows expose rowid aliases for alpha leaves' => [static fn (): mixed => array_slice(array_column($ruleRows(), 'rowid_alias'), 0, 4), [2, 4, 6, 8]],
    'rule rows expose rowid aliases for beta leaves' => [static fn (): mixed => array_slice(array_column($ruleRows(), 'rowid_alias'), 4, 4), [2, 4, 6, 8]],
    'priority pushdown row count' => [static fn (): mixed => count($priorityRows()), 2],
    'priority pushdown keeps greater equal values' => [static fn (): mixed => array_column($priorityRows(), 'priority'), [4, 7]],
    'priority pushdown keeps matched names by order' => [static fn (): mixed => array_column($priorityRows(), 'option_name'), ['plugin_beta_settings', 'plugin_alpha_settings']],
    'priority pushdown keeps matched fullkeys' => [static fn (): mixed => array_column($priorityRows(), 'fullkey'), ['$.rules[0].priority', '$.rules[1].priority']],
    'left join null extends all host rows when ON index is empty' => [static fn (): mixed => count($leftRows()), 4],
    'left join null row for alpha' => [static fn (): mixed => $leftRows()[0]['matched_name'], null],
    'left join null rowid for alpha' => [static fn (): mixed => $leftRows()[0]['matched_rowid'], null],
    'left join null row for jsonb beta' => [static fn (): mixed => $leftRows()[1]['matched_name'], null],
    'left join null rowid for jsonb beta' => [static fn (): mixed => $leftRows()[1]['matched_rowid'], null],
    'left join preserves empty option host' => [static fn (): mixed => $leftRows()[2]['option_name'], 'plugin_empty_settings'],
    'left join preserves null option host' => [static fn (): mixed => $leftRows()[3]['option_name'], 'plugin_null_settings'],
    'rowid pushdown row count' => [static fn (): mixed => count($rowidRows()), 4],
    'rowid pushdown maps aliases to id constraint' => [static fn (): mixed => array_column($rowidRows(), 'rowid_alias'), [2, 6, 2, 6]],
    'rowid pushdown keeps only name leaves' => [static fn (): mixed => array_column($rowidRows(), 'key_name'), ['name', 'name', 'name', 'name']],
    'rowid pushdown preserves alpha and beta current next' => [static fn (): mixed => array_column($rowidRows(), 'option_name'), ['plugin_alpha_settings', 'plugin_alpha_settings', 'plugin_beta_settings', 'plugin_beta_settings']],
    'plan records one dynamic join' => [static fn (): mixed => count($plan()['joins']), 1],
    'plan records json virtual index alias' => [static fn (): mixed => $plan()['joins'][0]['jsonTableIndex']['alias'] ?? null, 'j'],
    'plan records two ON index constraints' => [static fn (): mixed => $plan()['joins'][0]['jsonTableIndex']['constraintCount'] ?? null, 2],
    'plan records key IN constraint' => [static fn (): mixed => $plan()['joins'][0]['jsonTableIndex']['constraints'][0]['column'] ?? null, 'key'],
    'plan records atom IS NOT NULL constraint' => [static fn (): mixed => $plan()['joins'][0]['jsonTableIndex']['constraints'][1]['operator'] ?? null, 'IS NOT'],
    'plan keeps right columns for left join null extension' => [static fn (): mixed => in_array('j.rowid', SQLiteSelectSql::plan("SELECT o.option_name, j.rowid FROM wp_options AS o LEFT JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'missing'", $tables)['joins'][0]['rightColumns'], true), true],
    'literal json table without lateral source still works' => [static fn (): mixed => SQLiteSelectSql::execute("SELECT key AS key_name FROM json_each('{\"a\":1,\"b\":2}') AS j WHERE key = 'b'", []), [['key_name' => 'b']]],
    'literal json table plan has no lateral index metadata' => [static fn (): mixed => SQLiteSelectSql::plan("SELECT j.key AS key_name FROM json_each('{\"a\":1,\"b\":2}') AS j WHERE j.key = 'b'", [])['joins'] ?? [], []],
    'plan records IN literal values' => [static fn (): mixed => $plan()['joins'][0]['jsonTableIndex']['constraints'][0]['value'] ?? null, ['name', 'priority']],
    'plan records atom null test value' => [static fn (): mixed => array_key_exists('value', $plan()['joins'][0]['jsonTableIndex']['constraints'][1]) ? $plan()['joins'][0]['jsonTableIndex']['constraints'][1]['value'] : 'missing', null],
] as $name => [$callback, $expected]) {
    $tests['json table lateral index current next33 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['json table lateral index current next33 rejects nonliteral ON pushdown without changing semantics'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT o.option_name AS option_name, j.atom AS atom
           FROM wp_options AS o
           JOIN json_tree(o.option_value, '$.rules') AS j
             ON j.key = o.autoload
          ORDER BY option_name",
        $tables,
    );
    $t->same([], $rows);
};

return $tests;
