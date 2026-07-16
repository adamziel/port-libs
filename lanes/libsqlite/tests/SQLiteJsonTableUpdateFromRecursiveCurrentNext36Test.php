<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;
use PortLibs\LibSqlite\SQLiteUpdateFromSql;

$currentRows = static fn (): array => [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"cache","enabled":true},{"name":"seo","enabled":false}],"meta":{"site":1}}',
        'autoload' => 'no',
        'blog_id' => 1,
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":[{"name":"forms","enabled":true}],"meta":{"site":1}}',
        'autoload' => 'no',
        'blog_id' => 1,
    ],
    [
        'option_id' => 3,
        'option_name' => 'theme_gamma_settings',
        'option_value' => '{"rules":[{"name":"theme","enabled":true}],"meta":{"site":1}}',
        'autoload' => 'yes',
        'blog_id' => 1,
    ],
    [
        'option_id' => 4,
        'option_name' => '_transient_plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"temp","enabled":true}],"meta":{"site":1}}',
        'autoload' => 'no',
        'blog_id' => 1,
    ],
];

$tables = static fn (): array => ['wp_options' => $currentRows()];

$sql = <<<'SQL'
WITH RECURSIVE incoming(option_name,new_value,new_autoload,depth,fullkey) AS (
    SELECT o.option_name,
           j.atom || ':' || j.fullkey AS new_value,
           CASE j.atom WHEN 1 THEN 'yes' ELSE 'no' END AS new_autoload,
           0 AS depth,
           j.fullkey AS fullkey
      FROM wp_options AS o
      JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'enabled'
     WHERE o.option_name GLOB 'plugin_*'
    UNION ALL
    SELECT option_name,
           new_value || ':final' AS new_value,
           new_autoload,
           depth + 1 AS depth,
           fullkey
      FROM incoming
     WHERE depth < 1
)
UPDATE wp_options AS current
   SET option_value = incoming.new_value,
       autoload = incoming.new_autoload
  FROM incoming
 WHERE incoming.option_name = current.option_name
SQL;

$replaceSql = <<<'SQL'
WITH RECURSIVE incoming(option_name,new_name,new_value,depth) AS (
    SELECT o.option_name,
           CASE o.option_name WHEN 'plugin_alpha_settings' THEN 'plugin_beta_settings' ELSE o.option_name END AS new_name,
           j.atom || ':' || j.fullkey AS new_value,
           0 AS depth
      FROM wp_options AS o
      JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'enabled'
     WHERE o.option_name = 'plugin_alpha_settings'
    UNION ALL
    SELECT option_name, new_name, new_value || ':final', depth + 1
      FROM incoming
     WHERE depth < 1
)
UPDATE OR REPLACE wp_options AS current
   SET option_name = incoming.new_name,
       option_value = incoming.new_value
  FROM incoming
 WHERE incoming.option_name = current.option_name
SQL;

$guardedSql = <<<'SQL'
WITH RECURSIVE incoming(option_name,new_value,depth) AS (
    SELECT o.option_name,
           j.atom || ':' || j.fullkey AS new_value,
           0 AS depth
      FROM wp_options AS o
      JOIN json_tree(o.option_value, '$.missing') AS j ON j.key = 'enabled'
     WHERE o.option_name GLOB 'plugin_*'
    UNION ALL
    SELECT option_name, new_value || ':final', depth + 1
      FROM incoming
     WHERE depth < 1
)
UPDATE wp_options AS current
   SET option_value = incoming.new_value
  FROM incoming
 WHERE incoming.option_name = current.option_name
SQL;

$execute = static fn (string $statement = null, array $source = null, array $uniqueColumns = []): array => SQLiteUpdateFromSql::execute(
    $statement ?? $sql,
    $source ?? $tables(),
    [],
    $uniqueColumns
);

$plan = static fn (string $statement = null, array $source = null): array => SQLiteUpdateFromSql::plan(
    $statement ?? $sql,
    $source ?? $tables(),
);

$afterByName = static function (array $result): array {
    $rows = [];
    foreach ($result['after'] as $row) {
        $rows[$row['option_name']] = $row;
    }

    return $rows;
};

$sourceRows = static fn (): array => SQLiteSelectSql::execute(
    <<<'SQL'
WITH RECURSIVE incoming(option_name,new_value,new_autoload,depth,fullkey) AS (
    SELECT o.option_name,
           j.atom || ':' || j.fullkey AS new_value,
           CASE j.atom WHEN 1 THEN 'yes' ELSE 'no' END AS new_autoload,
           0 AS depth,
           j.fullkey AS fullkey
      FROM wp_options AS o
      JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'enabled'
     WHERE o.option_name GLOB 'plugin_*'
    UNION ALL
    SELECT option_name, new_value || ':final', new_autoload, depth + 1, fullkey
      FROM incoming
     WHERE depth < 1
)
SELECT option_name, new_value, new_autoload, depth, fullkey
  FROM incoming
 ORDER BY option_name, depth, fullkey
SQL,
    $tables(),
);

$cases = [
    'recursive json update changes two plugin rows' => static fn (TestRunner $t) => $t->same(2, $execute()['changes']),
    'recursive json update preserves target table name' => static fn (TestRunner $t) => $t->same('wp_options', $execute()['target']),
    'recursive json update uses abort conflict action' => static fn (TestRunner $t) => $t->same('abort', $execute()['conflict_action']),
    'recursive json update records option value assignment' => static fn (TestRunner $t) => $t->same('incoming.new_value', $execute()['assignments']['option_value']),
    'recursive json update records autoload assignment' => static fn (TestRunner $t) => $t->same('incoming.new_autoload', $execute()['assignments']['autoload']),
    'recursive json update assignment order is stable' => static fn (TestRunner $t) => $t->same(['option_value', 'autoload'], array_keys($execute()['assignments'])),
    'recursive json update emits six matched source rows' => static fn (TestRunner $t) => $t->same(6, count($execute()['matched_rows'])),
    'recursive json update matched target identities include duplicates' => static fn (TestRunner $t) => $t->same([0, 0, 0, 0, 1, 1], array_column($execute()['matched_rows'], '__sqlite_update_index')),
    'recursive json update collapses duplicate alpha matches to one update' => static fn (TestRunner $t) => $t->same(['plugin_alpha_settings', 'plugin_beta_settings'], array_column($execute()['updated_rows'], 'option_name')),
    'recursive json update keeps last recursive alpha value' => static fn (TestRunner $t) => $t->same('0:$.rules[1].enabled:final', $afterByName($execute())['plugin_alpha_settings']['option_value']),
    'recursive json update keeps beta recursive value' => static fn (TestRunner $t) => $t->same('1:$.rules[0].enabled:final', $afterByName($execute())['plugin_beta_settings']['option_value']),
    'recursive json update derives alpha autoload from last false atom' => static fn (TestRunner $t) => $t->same('no', $afterByName($execute())['plugin_alpha_settings']['autoload']),
    'recursive json update derives beta autoload from true atom' => static fn (TestRunner $t) => $t->same('yes', $afterByName($execute())['plugin_beta_settings']['autoload']),
    'recursive json update leaves theme row unchanged' => static fn (TestRunner $t) => $t->same($currentRows()[2], $afterByName($execute())['theme_gamma_settings']),
    'recursive json update leaves transient row unchanged' => static fn (TestRunner $t) => $t->same($currentRows()[3], $afterByName($execute())['_transient_plugin_alpha_settings']),
    'recursive json update preserves target rowids' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4], array_column($execute()['after'], 'option_id')),
    'recursive json update reports no conflict deletes' => static fn (TestRunner $t) => $t->same([], $execute()['deleted_rows']),
    'recursive json update preserves before snapshot' => static fn (TestRunner $t) => $t->same($currentRows(), $execute()['before']),
    'recursive json update after rows remain reindexed' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3], array_keys($execute()['after'])),
    'recursive json update final row count is stable' => static fn (TestRunner $t) => $t->same(4, count($execute()['after'])),
    'recursive json plan keeps leading recursive CTE' => static fn (TestRunner $t) => $t->same(true, str_starts_with($plan()['select_sql'], 'WITH RECURSIVE incoming')),
    'recursive json plan injects target identity' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['select_sql'], 'current.__sqlite_update_index AS __sqlite_update_index')),
    'recursive json plan keeps target cross join source' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['select_sql'], 'FROM wp_options AS current CROSS JOIN incoming')),
    'recursive json plan keeps update predicate' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['select_sql'], 'incoming.option_name = current.option_name')),
    'recursive json plan produces two updates' => static fn (TestRunner $t) => $t->same(2, count($plan()['updates'])),
    'recursive json source produces six rows' => static fn (TestRunner $t) => $t->same(6, count($sourceRows())),
    'recursive json source first row is alpha anchor true' => static fn (TestRunner $t) => $t->same(['plugin_alpha_settings', '1:$.rules[0].enabled', 'yes', 0, '$.rules[0].enabled'], array_values($sourceRows()[0])),
    'recursive json source second row is alpha anchor false' => static fn (TestRunner $t) => $t->same(['plugin_alpha_settings', '0:$.rules[1].enabled', 'no', 0, '$.rules[1].enabled'], array_values($sourceRows()[1])),
    'recursive json source alpha current next rows are adjacent by depth' => static fn (TestRunner $t) => $t->same(['1:$.rules[0].enabled', '0:$.rules[1].enabled', '1:$.rules[0].enabled:final', '0:$.rules[1].enabled:final'], array_column(array_slice($sourceRows(), 0, 4), 'new_value')),
    'recursive json source beta has anchor and recursive final' => static fn (TestRunner $t) => $t->same(['1:$.rules[0].enabled', '1:$.rules[0].enabled:final'], array_column(array_values(array_filter($sourceRows(), static fn (array $row): bool => $row['option_name'] === 'plugin_beta_settings')), 'new_value')),
    'recursive json source depths include anchor and recursive rows' => static fn (TestRunner $t) => $t->same([0, 0, 1, 1, 0, 1], array_column($sourceRows(), 'depth')),
    'recursive json source autoload values preserve atom truthiness' => static fn (TestRunner $t) => $t->same(['yes', 'no', 'yes', 'no', 'yes', 'yes'], array_column($sourceRows(), 'new_autoload')),
    'recursive json matched rows keep last alpha row value' => static fn (TestRunner $t) => $t->same('0:$.rules[1].enabled:final', $execute()['matched_rows'][3]['option_value']),
    'recursive json matched rows keep beta final row value' => static fn (TestRunner $t) => $t->same('1:$.rules[0].enabled:final', $execute()['matched_rows'][5]['option_value']),
    'recursive json update or replace deletes conflicting beta row' => static fn (TestRunner $t) => $t->same([2], array_column($execute($replaceSql, null, [['option_name']])['deleted_rows'], 'option_id')),
    'recursive json update or replace keeps renamed alpha row' => static fn (TestRunner $t) => $t->same(1, $afterByName($execute($replaceSql, null, [['option_name']]))['plugin_beta_settings']['option_id']),
    'recursive json update or replace keeps final recursive value' => static fn (TestRunner $t) => $t->same('0:$.rules[1].enabled:final', $afterByName($execute($replaceSql, null, [['option_name']]))['plugin_beta_settings']['option_value']),
    'recursive json update abort rejects conflicting rename' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $execute(str_replace('UPDATE OR REPLACE', 'UPDATE', $replaceSql), null, [['option_name']])),
    'recursive json missing root leaves changes zero' => static fn (TestRunner $t) => $t->same(0, $execute($guardedSql)['changes']),
    'recursive json missing root leaves rows unchanged' => static fn (TestRunner $t) => $t->same($currentRows(), $execute($guardedSql)['after']),
    'recursive json missing root plan has no updates' => static fn (TestRunner $t) => $t->same([], $plan($guardedSql)['updates']),
    'recursive json sql with alias-free target still updates beta' => static fn (TestRunner $t) => $t->same(
        '1:$.rules[0].enabled:final',
        $afterByName($execute(str_replace('UPDATE wp_options AS current', 'UPDATE wp_options current', $sql)))['plugin_beta_settings']['option_value']
    ),
    'recursive json sql with base target name still updates alpha' => static fn (TestRunner $t) => $t->same(
        '0:$.rules[1].enabled:final',
        $afterByName($execute(str_replace([' AS current', 'current.'], ['', 'wp_options.'], $sql)))['plugin_alpha_settings']['option_value']
    ),
    'recursive json update rejects reserved target identity column' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $execute($sql, ['wp_options' => [['option_id' => 1, 'option_name' => 'plugin_bad', 'option_value' => '{}', 'autoload' => 'no', '__sqlite_update_index' => 9]]])
    ),
    'recursive json update rejects missing target table' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $execute($sql, ['not_options' => []])),
    'recursive json update rejects missing cte update statement' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('WITH RECURSIVE incoming(x) AS (VALUES (1)) SELECT x FROM incoming')),
    'recursive json update rejects malformed recursive queue' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $execute("WITH RECURSIVE incoming(option_name,new_value) AS (SELECT 'plugin_alpha_settings','x' UNION ALL SELECT option_name FROM incoming) UPDATE wp_options AS current SET option_value = incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name")
    ),
    'recursive json update supports recursive source limit' => static fn (TestRunner $t) => $t->same(
        1,
        $execute("WITH RECURSIVE incoming(option_name,new_value,depth) AS (SELECT 'plugin_alpha_settings','anchor',0 UNION ALL SELECT option_name, new_value || ':r', depth + 1 FROM incoming WHERE depth < 3 LIMIT 1) UPDATE wp_options AS current SET option_value = incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name")['changes']
    ),
    'recursive json update limit keeps anchor value' => static fn (TestRunner $t) => $t->same(
        'anchor',
        $afterByName($execute("WITH RECURSIVE incoming(option_name,new_value,depth) AS (SELECT 'plugin_alpha_settings','anchor',0 UNION ALL SELECT option_name, new_value || ':r', depth + 1 FROM incoming WHERE depth < 3 LIMIT 1) UPDATE wp_options AS current SET option_value = incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name"))['plugin_alpha_settings']['option_value']
    ),
    'recursive json update supports source order by depth' => static fn (TestRunner $t) => $t->same(
        'anchor:r:r',
        $afterByName($execute("WITH RECURSIVE incoming(option_name,new_value,depth) AS (SELECT 'plugin_alpha_settings','anchor',0 UNION ALL SELECT option_name, new_value || ':r', depth + 1 FROM incoming WHERE depth < 2 ORDER BY depth DESC) UPDATE wp_options AS current SET option_value = incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name"))['plugin_alpha_settings']['option_value']
    ),
    'recursive json update supports parameterized json root' => static fn (TestRunner $t) => $t->same(
        2,
        SQLiteUpdateFromSql::execute(
            "WITH RECURSIVE incoming(option_name,new_value,depth) AS (SELECT o.option_name, j.atom || ':' || j.fullkey, 0 FROM wp_options AS o JOIN json_tree(o.option_value, :root) AS j ON j.key = 'enabled' WHERE o.option_name GLOB 'plugin_*' UNION ALL SELECT option_name, new_value || ':final', depth + 1 FROM incoming WHERE depth < 1) UPDATE wp_options AS current SET option_value = incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name",
            $tables(),
            ['root' => '$.rules']
        )['changes']
    ),
];

foreach ($cases as $name => $case) {
    $tests['sqlite json table update from recursive current next36 ' . $name] = $case;
}

return $tests;
