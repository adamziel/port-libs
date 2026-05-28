<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options76 = [
    [
        'option_id' => 10,
        'option_name' => 'plugin_left_alpha',
        'autoload' => 'yes',
        'option_value' => '{"flags":["network","beta","seo"],"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":false}]}',
        'json_root' => '$.flags',
    ],
    [
        'option_id' => 20,
        'option_name' => 'plugin_left_empty',
        'autoload' => 'yes',
        'option_value' => '{"flags":[],"rules":[]}',
        'json_root' => '$.flags',
    ],
    [
        'option_id' => 30,
        'option_name' => 'plugin_left_null',
        'autoload' => 'no',
        'option_value' => null,
        'json_root' => '$.flags',
    ],
    [
        'option_id' => 40,
        'option_name' => 'plugin_left_jsonb',
        'autoload' => 'no',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'flags' => ['media', 'forms'],
            'rules' => [
                ['name' => 'media', 'enabled' => true],
            ],
        ])),
        'json_root' => '$.flags',
    ],
    [
        'option_id' => 50,
        'option_name' => 'plugin_left_missing',
        'autoload' => 'no',
        'option_value' => '{"rules":[{"name":"missing","enabled":true}]}',
        'json_root' => '$.flags',
    ],
];

$run76 = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['wp_options' => $options76]);

$rowsByRowidOn76 = static fn (): array => $run76(
    "SELECT o.option_id AS option_id, o.option_name AS option_name, f.rowid AS f_rowid, f._rowid_ AS f__rowid_, f.oid AS f_oid, f.key AS flag_key, f.atom AS flag
       FROM wp_options AS o
       LEFT JOIN json_each(o.option_value, o.json_root) AS f ON f.rowid = 2
      ORDER BY option_id",
);

$rowsByUnderscoreOn76 = static fn (): array => $run76(
    "SELECT o.option_id AS option_id, f.rowid AS rowid_alias, f.atom AS flag
       FROM wp_options AS o
       LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f._rowid_ = 1
      ORDER BY option_id",
);

$rowsByOidOn76 = static fn (): array => $run76(
    "SELECT o.option_id AS option_id, f.oid AS oid_alias, f.atom AS flag
       FROM wp_options AS o
       LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.oid IN (1, 3)
      ORDER BY option_id, oid_alias",
);

$treeRuleRows76 = static fn (): array => $run76(
    "SELECT o.option_id AS option_id, r.rowid AS rule_rowid, r.parent AS rule_parent, r.key AS rule_key, r.atom AS rule_atom, r.fullkey AS rule_fullkey
       FROM wp_options AS o
       LEFT JOIN json_tree(o.option_value, '$.rules') AS r ON r.rowid = 2
      ORDER BY option_id",
);

$valueAt76 = static function (array $rows, string $path): mixed {
    if ($path === 'count') {
        return count($rows);
    }
    if ($path === 'rows') {
        return $rows;
    }
    if (str_starts_with($path, 'column.')) {
        return array_column($rows, substr($path, 7));
    }
    if (str_starts_with($path, 'row.')) {
        [, $rowIndex, $column] = explode('.', $path, 3);

        return $rows[(int) $rowIndex][$column];
    }

    throw new InvalidArgumentException("Unknown assertion path {$path}");
};

$cases76 = [
    'rowid ON keeps one row per option' => [$rowsByRowidOn76, 'count', 5],
    'rowid ON preserves host order' => [$rowsByRowidOn76, 'column.option_id', [10, 20, 30, 40, 50]],
    'rowid ON matches second text array element' => [$rowsByRowidOn76, 'row.0.flag', 'beta'],
    'rowid ON exposes rowid alias for matched text element' => [$rowsByRowidOn76, 'row.0.f_rowid', 2],
    'rowid ON exposes underscore rowid alias for matched text element' => [$rowsByRowidOn76, 'row.0.f__rowid_', 2],
    'rowid ON exposes oid alias for matched text element' => [$rowsByRowidOn76, 'row.0.f_oid', 2],
    'rowid ON preserves matched key for second text element' => [$rowsByRowidOn76, 'row.0.flag_key', 1],
    'rowid ON null extends empty array value' => [$rowsByRowidOn76, 'row.1.flag', null],
    'rowid ON null extends empty array rowid' => [$rowsByRowidOn76, 'row.1.f_rowid', null],
    'rowid ON null extends SQL NULL source value' => [$rowsByRowidOn76, 'row.2.flag', null],
    'rowid ON null extends SQL NULL source oid' => [$rowsByRowidOn76, 'row.2.f_oid', null],
    'rowid ON matches second JSONB array element' => [$rowsByRowidOn76, 'row.3.flag', 'forms'],
    'rowid ON exposes JSONB matched rowid' => [$rowsByRowidOn76, 'row.3.f_rowid', 2],
    'rowid ON null extends missing dynamic root value' => [$rowsByRowidOn76, 'row.4.flag', null],
    'rowid ON null extends missing dynamic root underscore rowid' => [$rowsByRowidOn76, 'row.4.f__rowid_', null],
    'underscore rowid ON keeps one row per option' => [$rowsByUnderscoreOn76, 'count', 5],
    'underscore rowid ON matches first text element' => [$rowsByUnderscoreOn76, 'row.0.flag', 'network'],
    'underscore rowid ON reports rowid alias one' => [$rowsByUnderscoreOn76, 'row.0.rowid_alias', 1],
    'underscore rowid ON null extends empty array' => [$rowsByUnderscoreOn76, 'row.1.rowid_alias', null],
    'underscore rowid ON null extends SQL NULL source' => [$rowsByUnderscoreOn76, 'row.2.flag', null],
    'underscore rowid ON matches first JSONB element' => [$rowsByUnderscoreOn76, 'row.3.flag', 'media'],
    'underscore rowid ON null extends missing root' => [$rowsByUnderscoreOn76, 'row.4.rowid_alias', null],
    'oid ON keeps two matches for alpha' => [$rowsByOidOn76, 'column.flag', ['network', 'seo', null, null, 'media', null]],
    'oid ON exposes first alpha oid' => [$rowsByOidOn76, 'row.0.oid_alias', 1],
    'oid ON exposes third alpha oid' => [$rowsByOidOn76, 'row.1.oid_alias', 3],
    'oid ON null extends empty array' => [$rowsByOidOn76, 'row.2.oid_alias', null],
    'oid ON null extends SQL NULL source' => [$rowsByOidOn76, 'row.3.flag', null],
    'oid ON matches first JSONB element only' => [$rowsByOidOn76, 'row.4.flag', 'media'],
    'oid ON null extends missing root' => [$rowsByOidOn76, 'row.5.oid_alias', null],
    'json tree rowid ON keeps one row per option' => [$treeRuleRows76, 'count', 5],
    'json tree rowid ON matches first rule name for text JSON' => [$treeRuleRows76, 'row.0.rule_atom', 'seo'],
    'json tree rowid ON exposes parent object id for text JSON' => [$treeRuleRows76, 'row.0.rule_parent', 1],
    'json tree rowid ON exposes rule key for text JSON' => [$treeRuleRows76, 'row.0.rule_key', 'name'],
    'json tree rowid ON exposes rule fullkey for text JSON' => [$treeRuleRows76, 'row.0.rule_fullkey', '$.rules[0].name'],
    'json tree rowid ON null extends empty rules' => [$treeRuleRows76, 'row.1.rule_rowid', null],
    'json tree rowid ON null extends SQL NULL source' => [$treeRuleRows76, 'row.2.rule_atom', null],
    'json tree rowid ON matches first rule name for JSONB' => [$treeRuleRows76, 'row.3.rule_atom', 'media'],
    'json tree rowid ON matches missing-source rules rowid target' => [$treeRuleRows76, 'row.4.rule_rowid', 2],
];

$tests = [];
foreach ($cases76 as $name => [$rows, $path, $expected]) {
    $tests['json table left join rowid current next76 ' . $name] = static function (TestRunner $t) use ($rows, $path, $expected, $valueAt76): void {
        $t->same($expected, $valueAt76($rows(), $path));
    };
}

$tests['json table left join rowid current next76 WHERE rowid IS NULL preserves unmatched options'] = static function (TestRunner $t) use ($run76): void {
    $rows = $run76("SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 WHERE f.rowid IS NULL ORDER BY option_id");
    $t->same(['plugin_left_empty', 'plugin_left_null', 'plugin_left_missing'], array_column($rows, 'option_name'));
};

$tests['json table left join rowid current next76 WHERE oid IS NULL preserves unmatched options'] = static function (TestRunner $t) use ($run76): void {
    $rows = $run76("SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.oid = 2 WHERE f.oid IS NULL ORDER BY option_id");
    $t->same(['plugin_left_empty', 'plugin_left_null', 'plugin_left_missing'], array_column($rows, 'option_name'));
};

$tests['json table left join rowid current next76 WHERE underscore rowid IS NULL preserves unmatched options'] = static function (TestRunner $t) use ($run76): void {
    $rows = $run76("SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f._rowid_ = 2 WHERE f._rowid_ IS NULL ORDER BY option_id");
    $t->same(['plugin_left_empty', 'plugin_left_null', 'plugin_left_missing'], array_column($rows, 'option_name'));
};

$tests['json table left join rowid current next76 WHERE rowid equals filters matched dynamic rows'] = static function (TestRunner $t) use ($run76): void {
    $rows = $run76("SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, o.json_root) AS f ON true WHERE f.rowid = 2 ORDER BY option_id");
    $t->same([['option_name' => 'plugin_left_alpha', 'flag' => 'beta'], ['option_name' => 'plugin_left_jsonb', 'flag' => 'forms']], $rows);
};

$tests['json table left join rowid current next76 ON rowid greater than keeps later JSON rows'] = static function (TestRunner $t) use ($run76): void {
    $rows = $run76("SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid > 1 WHERE o.option_id = 10 ORDER BY f.rowid");
    $t->same(['beta', 'seo'], array_column($rows, 'flag'));
};

$tests['json table left join rowid current next76 ON rowid less than keeps first JSONB row'] = static function (TestRunner $t) use ($run76): void {
    $rows = $run76("SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid < 2 WHERE o.option_id = 40");
    $t->same([['option_name' => 'plugin_left_jsonb', 'flag' => 'media']], $rows);
};

$tests['json table left join rowid current next76 ON rowid not equal keeps complement rows'] = static function (TestRunner $t) use ($run76): void {
    $rows = $run76("SELECT f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid != 2 WHERE o.option_id = 10 ORDER BY f.rowid");
    $t->same(['network', 'seo'], array_column($rows, 'flag'));
};

$tests['json table left join rowid current next76 plan advertises rowid aliases for ON rowid'] = static function (TestRunner $t) use ($options76): void {
    $plan = SQLiteSelectSql::plan("SELECT o.option_name, f.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, o.json_root) AS f ON f.rowid = 2", ['wp_options' => $options76]);
    $t->same(['f.key', 'f.value', 'f.type', 'f.atom', 'f.id', 'f.parent', 'f.fullkey', 'f.path', 'f.rowid', 'f._rowid_', 'f.oid'], $plan['joins'][0]['rightColumns']);
};

$tests['json table left join rowid current next76 plan pushes rowid ON as id constraint'] = static function (TestRunner $t) use ($options76): void {
    $plan = SQLiteSelectSql::plan("SELECT o.option_name, f.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, o.json_root) AS f ON f.rowid = 2", ['wp_options' => $options76]);
    $t->same([['column' => 'id', 'operator' => '=', 'value' => 2, 'usable' => true]], $plan['joins'][0]['jsonTableIndex']['constraints']);
};

$tests['json table left join rowid current next76 indexed callback keeps rowid alias values'] = static function (TestRunner $t) use ($options76): void {
    $plan = SQLiteSelectSql::plan("SELECT o.option_name, f.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, o.json_root) AS f ON f.rowid = 2", ['wp_options' => $options76]);
    $rows = ($plan['joins'][0]['indexedDynamicRows'])($plan['from'][0]);
    $t->same([2, 2, 2], [$rows[0]['f.id'], $rows[0]['f.rowid'], $rows[0]['f.oid']]);
};

$tests['json table left join rowid current next76 dynamic callback returns empty list for SQL NULL source'] = static function (TestRunner $t) use ($options76): void {
    $plan = SQLiteSelectSql::plan("SELECT o.option_name, f.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, o.json_root) AS f ON f.rowid = 2", ['wp_options' => $options76]);
    $rows = ($plan['joins'][0]['indexedDynamicRows'])($plan['from'][2]);
    $t->same([], $rows);
};

$tests['json table left join rowid current next76 dynamic callback returns empty list for missing root'] = static function (TestRunner $t) use ($options76): void {
    $plan = SQLiteSelectSql::plan("SELECT o.option_name, f.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, o.json_root) AS f ON f.rowid = 2", ['wp_options' => $options76]);
    $rows = ($plan['joins'][0]['indexedDynamicRows'])($plan['from'][4]);
    $t->same([], $rows);
};

$tests['json table left join rowid current next76 rejects non-text dynamic root before null extension'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, o.json_root) AS f ON f.rowid = 1",
        ['wp_options' => [['option_name' => 'bad_root', 'option_value' => '{"flags":["x"]}', 'json_root' => 10]]],
    ));
};

return $tests;
