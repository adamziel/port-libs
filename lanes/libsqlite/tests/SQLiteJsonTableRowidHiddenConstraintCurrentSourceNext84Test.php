<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$settings84 = '{"plugins":[{"slug":"seo","enabled":true,"priority":8},{"slug":"cache","enabled":false,"priority":3},{"slug":"forms","enabled":true,"priority":5}],"meta":{"site":"example"}}';
$settingsSql84 = str_replace("'", "''", $settings84);
$settingsJsonb84 = new SQLiteBlobValue(SQLiteJsonB::encode([
    'plugins' => [
        ['slug' => 'seo', 'enabled' => true, 'priority' => 8],
        ['slug' => 'cache', 'enabled' => false, 'priority' => 3],
        ['slug' => 'forms', 'enabled' => true, 'priority' => 5],
    ],
]));

$rows84 = static fn (string $sql, array $parameters = []): array => SQLiteSelectSql::execute($sql, [], $parameters);
$plan84 = static fn (string $sql, array $parameters = []): array => SQLiteSelectSql::plan($sql, [], $parameters);
$valueAt84 = static function (array $rows, string $path): mixed {
    $value = $rows;
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            return count($value);
        }
        if (str_starts_with($part, 'column:')) {
            return array_column($value, substr($part, 7));
        }
        if (ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases84 = [
    'json_each rowid equality selects object row' => [
        "SELECT rowid AS rid, _rowid_ AS urid, oid AS oid, key, type FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 2",
        '0',
        ['rid' => 2, 'urid' => 2, 'oid' => 2, 'key' => 1, 'type' => 'object'],
    ],
    'json_each commuted rowid equality selects same object row' => [
        "SELECT key, rowid AS rid FROM json_each AS j WHERE '{$settingsSql84}' = j.json AND '$.plugins' = j.root AND 2 = j.rowid",
        '0',
        ['key' => 1, 'rid' => 2],
    ],
    'json_each underscore rowid equality selects third object row' => [
        "SELECT key, _rowid_ AS rid FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j._rowid_ = 3",
        '0',
        ['key' => 2, 'rid' => 3],
    ],
    'json_each oid equality selects first object row' => [
        "SELECT key, oid AS rid FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.oid = 1",
        '0',
        ['key' => 0, 'rid' => 1],
    ],
    'json_tree rowid equality selects nested boolean leaf' => [
        "SELECT key, atom, rowid AS rid, fullkey FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.rowid = 3",
        '0',
        ['key' => 'enabled', 'atom' => 1, 'rid' => 3, 'fullkey' => '$.plugins[0].enabled'],
    ],
    'json_tree underscore rowid equality selects nested priority leaf' => [
        "SELECT key, atom, _rowid_ AS rid FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t._rowid_ = 4",
        '0',
        ['key' => 'priority', 'atom' => 8, 'rid' => 4],
    ],
    'json_tree oid equality selects second plugin object' => [
        "SELECT key, type, oid AS rid FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.oid = 5",
        '0',
        ['key' => 1, 'type' => 'object', 'rid' => 5],
    ],
    'json_each missing rowid returns no rows' => [
        "SELECT key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 99",
        'count',
        0,
    ],
    'json_each rowid equality composes with visible type residual' => [
        "SELECT rowid AS rid, type FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 2 AND j.type = 'object'",
        '0',
        ['rid' => 2, 'type' => 'object'],
    ],
    'json_each rowid equality composes with visible key residual miss' => [
        "SELECT rowid AS rid FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 2 AND j.key = 0",
        'count',
        0,
    ],
    'json_tree rowid equality composes with atom residual' => [
        "SELECT rowid AS rid, atom FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.rowid = 2 AND t.atom = 'seo'",
        '0',
        ['rid' => 2, 'atom' => 'seo'],
    ],
    'json_tree rowid equality composes with parent residual miss' => [
        "SELECT rowid AS rid FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.rowid = 3 AND t.parent = 6",
        'count',
        0,
    ],
    'json_each parameter json rowid equality selects current source' => [
        "SELECT key, rowid AS rid FROM json_each AS j WHERE :settings = j.json AND '$.plugins' = j.root AND j.rowid = 1",
        '0',
        ['key' => 0, 'rid' => 1],
        [':settings' => $settings84],
    ],
    'json_tree parameter root rowid equality selects current source' => [
        "SELECT key, atom, rowid AS rid FROM json_tree AS t WHERE :settings = t.json AND :root = t.root AND t.rowid = 6",
        '0',
        ['key' => 'slug', 'atom' => 'cache', 'rid' => 6],
        [':settings' => $settings84, ':root' => '$.plugins'],
    ],
    'json_each JSONB rowid equality selects current source' => [
        "SELECT key, rowid AS rid FROM json_each AS j WHERE ? = j.json AND '$.plugins' = j.root AND j.rowid = 3",
        '0',
        ['key' => 2, 'rid' => 3],
        [$settingsJsonb84],
    ],
    'json_tree SQL NULL json with rowid remains empty' => [
        "SELECT key FROM json_tree AS t WHERE NULL = t.json AND '$.plugins' = t.root AND t.rowid = 1",
        'count',
        0,
    ],
    'json_each rowid hidden constraint leaves no residual where' => [
        "SELECT key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 2",
        'plan.where',
        false,
    ],
    'json_each underscore hidden constraint leaves no residual where' => [
        "SELECT key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j._rowid_ = 2",
        'plan.where',
        false,
    ],
    'json_each oid hidden constraint leaves no residual where' => [
        "SELECT key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.oid = 2",
        'plan.where',
        false,
    ],
    'json_tree rowid hidden constraint leaves visible residual only' => [
        "SELECT key FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.rowid = 3 AND t.type = 'text'",
        'plan.where.operator',
        '=',
    ],
    'json_tree rowid hidden constraint keeps visible residual column' => [
        "SELECT key FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.rowid = 3 AND t.type = 'text'",
        'plan.where.left.name',
        'type',
    ],
    'json_tree rowid hidden constraint keeps visible residual value' => [
        "SELECT key FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.rowid = 3 AND t.type = 'text'",
        'plan.where.right.value',
        'text',
    ],
];

$tests = [];
foreach ($cases84 as $name => $case) {
    $tests['json table rowid hidden constraint current source next84 ' . $name] = static function (TestRunner $t) use ($case, $rows84, $plan84, $valueAt84): void {
        [$sql, $path, $expected] = $case;
        $parameters = $case[3] ?? [];
        if (str_starts_with($path, 'plan.')) {
            $plan = $plan84($sql, $parameters);
            $subpath = substr($path, 5);
            if ($subpath === 'where') {
                $t->same($expected, isset($plan['where']));

                return;
            }
            $t->same($expected, $valueAt84($plan, $subpath));

            return;
        }

        $t->same($expected, $valueAt84($rows84($sql, $parameters), $path));
    };
}

$tests['json table rowid hidden constraint current source next84 plan uses id constraint for rowid'] = static function (TestRunner $t) use ($plan84, $settingsSql84): void {
    $plan = $plan84("SELECT key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 2");
    $t->same(1, count($plan['from']));
    $t->same(2, $plan['from'][0]['j.id']);
    $t->same(2, $plan['from'][0]['j.rowid']);
};

$tests['json table rowid hidden constraint current source next84 plan uses id constraint for oid'] = static function (TestRunner $t) use ($plan84, $settingsSql84): void {
    $plan = $plan84("SELECT key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.oid = 3");
    $t->same(2, $plan['from'][0]['j.key']);
    $t->same(3, $plan['from'][0]['j.id']);
    $t->same(3, $plan['from'][0]['j.oid']);
};

$tests['json table rowid hidden constraint current source next84 plan keeps next source rowid independent'] = static function (TestRunner $t) use ($plan84, $settingsSql84): void {
    $plan = $plan84("SELECT key FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.rowid = 6");
    $t->same('cache', $plan['from'][0]['t.atom']);
    $t->same(6, $plan['from'][0]['t.rowid']);
    $t->same('$.plugins[1].slug', $plan['from'][0]['t.fullkey']);
};

$tests['json table rowid hidden constraint current source next84 rowid and oid produce same current source'] = static function (TestRunner $t) use ($rows84, $settingsSql84): void {
    $rowidRows = $rows84("SELECT key, atom, rowid AS rid FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.rowid = 8");
    $oidRows = $rows84("SELECT key, atom, oid AS rid FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.oid = 8");
    $t->same($rowidRows, $oidRows);
    $t->same('priority', $rowidRows[0]['key']);
    $t->same(3, $rowidRows[0]['atom']);
};

$tests['json table rowid hidden constraint current source next84 underscore and rowid produce same current source'] = static function (TestRunner $t) use ($rows84, $settingsSql84): void {
    $rowidRows = $rows84("SELECT key, atom, rowid AS rid FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t.rowid = 10");
    $underscoreRows = $rows84("SELECT key, atom, _rowid_ AS rid FROM json_tree AS t WHERE t.json = '{$settingsSql84}' AND t.root = '$.plugins' AND t._rowid_ = 10");
    $t->same($rowidRows, $underscoreRows);
    $t->same('slug', $rowidRows[0]['key']);
    $t->same('forms', $rowidRows[0]['atom']);
};

$tests['json table rowid hidden constraint current source next84 hidden id narrows before order by'] = static function (TestRunner $t) use ($rows84, $settingsSql84): void {
    $rows = $rows84("SELECT rowid AS rid, key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 2 ORDER BY key DESC");
    $t->same(1, count($rows));
    $t->same(2, $rows[0]['rid']);
    $t->same(1, $rows[0]['key']);
};

$tests['json table rowid hidden constraint current source next84 hidden id narrows before limit'] = static function (TestRunner $t) use ($rows84, $settingsSql84): void {
    $rows = $rows84("SELECT rowid AS rid, key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 3 LIMIT 1 OFFSET 0");
    $t->same(1, count($rows));
    $t->same(3, $rows[0]['rid']);
    $t->same(2, $rows[0]['key']);
};

$tests['json table rowid hidden constraint current source next84 no match survives aggregate count'] = static function (TestRunner $t) use ($rows84, $settingsSql84): void {
    $rows = $rows84("SELECT count(*) AS rows FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 30");
    $t->same(1, count($rows));
    $t->same(0, $rows[0]['rows']);
};

$tests['json table rowid hidden constraint current source next84 matched row survives aggregate count'] = static function (TestRunner $t) use ($rows84, $settingsSql84): void {
    $rows = $rows84("SELECT count(*) AS rows FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 3");
    $t->same(1, count($rows));
    $t->same(1, $rows[0]['rows']);
};

$tests['json table rowid hidden constraint current source next84 aliased residual is unqualified for execution'] = static function (TestRunner $t) use ($plan84, $settingsSql84): void {
    $plan = $plan84("SELECT key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 2 AND j.type = 'object'");
    $t->same('type', $plan['where']['left']['name']);
    $t->same('object', $plan['where']['right']['value']);
};

$tests['json table rowid hidden constraint current source next84 aliased rowid predicate is removed from conjunction'] = static function (TestRunner $t) use ($plan84, $settingsSql84): void {
    $plan = $plan84("SELECT key FROM json_each AS j WHERE j.json = '{$settingsSql84}' AND j.root = '$.plugins' AND j.rowid = 2 AND j.type = 'object' AND j.key = 1");
    $t->same('AND', $plan['where']['operator']);
    $t->same(2, count($plan['where']['terms']));
    $t->same(['type', 'key'], [$plan['where']['terms'][0]['left']['name'], $plan['where']['terms'][1]['left']['name']]);
};

return $tests;
