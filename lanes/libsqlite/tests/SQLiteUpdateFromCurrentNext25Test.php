<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateFromSql;

$currentRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes', 'blog_id' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes', 'blog_id' => 1],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes', 'blog_id' => 1],
    ['option_id' => 4, 'option_name' => 'rewrite_rules', 'option_value' => 'a:0:{}', 'autoload' => 'no', 'blog_id' => 1],
    ['option_id' => 5, 'option_name' => '_transient_timeout_feed', 'option_value' => '100', 'autoload' => 'no', 'blog_id' => 1],
    ['option_id' => 6, 'option_name' => 'widget_text', 'option_value' => 'a:1:{}', 'autoload' => 'no', 'blog_id' => 2],
];

$tables = static fn (): array => [
    'wp_options' => $currentRows(),
    'import_stage' => [
        ['option_name' => 'siteurl', 'new_value' => 'https://new.example', 'new_autoload' => 'yes', 'blog_id' => 1],
        ['option_name' => 'home', 'new_value' => 'https://new.example', 'new_autoload' => 'yes', 'blog_id' => 1],
        ['option_name' => 'blogname', 'new_value' => 'Ported Site', 'new_autoload' => 'yes', 'blog_id' => 1],
        ['option_name' => 'rewrite_rules', 'new_value' => 'a:2:{s:4:"post";s:7:"/%post%";}', 'new_autoload' => 'yes', 'blog_id' => 1],
        ['option_name' => 'widget_text', 'new_value' => 'a:2:{}', 'new_autoload' => 'no', 'blog_id' => 2],
    ],
];

$cteSql = "WITH incoming(option_name,new_value,new_autoload,blog_id) AS (
    VALUES
        ('siteurl','https://new.example','yes',1),
        ('home','https://new.example','yes',1),
        ('blogname','Ported Site','yes',1),
        ('rewrite_rules','a:2:{s:4:\"post\";s:7:\"/%post%\";}','yes',1),
        ('widget_text','a:2:{}','no',2)
)
UPDATE wp_options AS current
SET option_value = incoming.new_value, autoload = incoming.new_autoload
FROM incoming
WHERE incoming.option_name = current.option_name AND incoming.blog_id = current.blog_id";

$execute = static fn (string $sql, array $source = null, array $parameters = [], array $uniqueColumns = []): array => SQLiteUpdateFromSql::execute(
    $sql,
    $source ?? $tables(),
    $parameters,
    $uniqueColumns
);

$plan = static fn (string $sql, array $source = null, array $parameters = []): array => SQLiteUpdateFromSql::plan(
    $sql,
    $source ?? $tables(),
    $parameters
);

$afterByName = static function (array $result): array {
    $rows = [];
    foreach ($result['after'] as $row) {
        $rows[$row['option_name']] = $row;
    }

    return $rows;
};

$cases = [
    'cte update changes five current rows' => static fn (TestRunner $t) => $t->same(5, $execute($cteSql)['changes']),
    'cte update preserves target name' => static fn (TestRunner $t) => $t->same('wp_options', $execute($cteSql)['target']),
    'cte update keeps abort conflict action' => static fn (TestRunner $t) => $t->same('abort', $execute($cteSql)['conflict_action']),
    'cte update records option value assignment' => static fn (TestRunner $t) => $t->same('incoming.new_value', $execute($cteSql)['assignments']['option_value']),
    'cte update records autoload assignment' => static fn (TestRunner $t) => $t->same('incoming.new_autoload', $execute($cteSql)['assignments']['autoload']),
    'cte update exposes matched row identities' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3, 5], array_column($execute($cteSql)['matched_rows'], '__sqlite_update_index')),
    'cte update leaves unmatched transient row unchanged' => static fn (TestRunner $t) => $t->same('100', $afterByName($execute($cteSql))['_transient_timeout_feed']['option_value']),
    'cte update changes siteurl value' => static fn (TestRunner $t) => $t->same('https://new.example', $afterByName($execute($cteSql))['siteurl']['option_value']),
    'cte update changes home value' => static fn (TestRunner $t) => $t->same('https://new.example', $afterByName($execute($cteSql))['home']['option_value']),
    'cte update changes blogname value' => static fn (TestRunner $t) => $t->same('Ported Site', $afterByName($execute($cteSql))['blogname']['option_value']),
    'cte update changes rewrite rules value' => static fn (TestRunner $t) => $t->same('a:2:{s:4:"post";s:7:"/%post%";}', $afterByName($execute($cteSql))['rewrite_rules']['option_value']),
    'cte update changes rewrite rules autoload' => static fn (TestRunner $t) => $t->same('yes', $afterByName($execute($cteSql))['rewrite_rules']['autoload']),
    'cte update keeps network widget scoped by blog id' => static fn (TestRunner $t) => $t->same('a:2:{}', $afterByName($execute($cteSql))['widget_text']['option_value']),
    'cte update preserves target rowids' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4, 5, 6], array_column($execute($cteSql)['after'], 'option_id')),
    'cte update reports no conflict deletes' => static fn (TestRunner $t) => $t->same([], $execute($cteSql)['deleted_rows']),
    'cte plan keeps leading with clause' => static fn (TestRunner $t) => $t->same(true, str_starts_with($plan($cteSql)['select_sql'], 'WITH incoming')),
    'cte plan injects target alias row identity' => static fn (TestRunner $t) => $t->same(true, str_contains($plan($cteSql)['select_sql'], 'current.__sqlite_update_index AS __sqlite_update_index')),
    'cte plan keeps target alias in from source' => static fn (TestRunner $t) => $t->same(true, str_contains($plan($cteSql)['select_sql'], 'FROM wp_options AS current CROSS JOIN incoming')),
    'cte plan keeps where target alias' => static fn (TestRunner $t) => $t->same(true, str_contains($plan($cteSql)['select_sql'], 'incoming.option_name = current.option_name')),
    'cte plan produces five updates' => static fn (TestRunner $t) => $t->same(5, count($plan($cteSql)['updates'])),
    'target alias without AS updates current row' => static fn (TestRunner $t) => $t->same(
        'https://new.example',
        $afterByName($execute("UPDATE wp_options current SET option_value = stage.new_value FROM import_stage AS stage WHERE stage.option_name = current.option_name AND stage.option_name = 'siteurl'"))['siteurl']['option_value']
    ),
    'target alias without AS keeps select identity alias' => static fn (TestRunner $t) => $t->same(
        true,
        str_contains($plan("UPDATE wp_options current SET option_value = stage.new_value FROM import_stage AS stage WHERE stage.option_name = current.option_name")['select_sql'], 'current.__sqlite_update_index')
    ),
    'target alias with AS updates current row' => static fn (TestRunner $t) => $t->same(
        'Ported Site',
        $afterByName($execute("UPDATE wp_options AS current SET option_value = stage.new_value FROM import_stage AS stage WHERE stage.option_name = current.option_name AND stage.option_name = 'blogname'"))['blogname']['option_value']
    ),
    'target alias with AS keeps base table target' => static fn (TestRunner $t) => $t->same(
        'wp_options',
        $execute("UPDATE wp_options AS current SET option_value = stage.new_value FROM import_stage AS stage WHERE stage.option_name = current.option_name AND stage.option_name = 'blogname'")['target']
    ),
    'source table alias updates scoped rows' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home', 'blogname', 'rewrite_rules'],
        array_column($execute("UPDATE wp_options AS current SET option_value = stage.new_value FROM import_stage AS stage WHERE stage.option_name = current.option_name AND stage.blog_id = 1")['updated_rows'], 'option_name')
    ),
    'cte source may filter staged rows before update' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home', 'blogname', 'rewrite_rules'],
        array_column($execute("WITH incoming AS (SELECT option_name, new_value, new_autoload FROM import_stage WHERE blog_id = 1) UPDATE wp_options AS current SET option_value = incoming.new_value, autoload = incoming.new_autoload FROM incoming WHERE incoming.option_name = current.option_name")['updated_rows'], 'option_name')
    ),
    'cte source may order and limit before update' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'rewrite_rules'],
        array_column($execute("WITH incoming AS (SELECT option_name, new_value FROM import_stage WHERE blog_id = 1 ORDER BY option_name DESC LIMIT 2) UPDATE wp_options AS current SET option_value = incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name")['updated_rows'], 'option_name')
    ),
    'cte source can use values column aliases' => static fn (TestRunner $t) => $t->same(
        'autoloaded',
        $afterByName($execute("WITH incoming(name,value) AS (VALUES ('blogname','autoloaded')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name"))['blogname']['option_value']
    ),
    'cte source can use compound rows' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home'],
        array_column($execute("WITH incoming AS (SELECT 'siteurl' AS option_name, 's' AS new_value UNION ALL SELECT 'home' AS option_name, 'h' AS new_value) UPDATE wp_options AS current SET option_value = incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name")['updated_rows'], 'option_name')
    ),
    'cte duplicate source rows keep last matched value' => static fn (TestRunner $t) => $t->same(
        'last',
        $afterByName($execute("WITH incoming(name,value) AS (VALUES ('siteurl','first'), ('siteurl','last')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name"))['siteurl']['option_value']
    ),
    'cte duplicate source rows count one changed target' => static fn (TestRunner $t) => $t->same(
        1,
        $execute("WITH incoming(name,value) AS (VALUES ('siteurl','first'), ('siteurl','last')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name")['changes']
    ),
    'cte parameters update current row' => static fn (TestRunner $t) => $t->same(
        'https://param.example',
        $afterByName($execute("WITH incoming(name,value) AS (VALUES (:name,:value)) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name", null, ['name' => 'siteurl', 'value' => 'https://param.example']))['siteurl']['option_value']
    ),
    'cte positional parameters update current row' => static fn (TestRunner $t) => $t->same(
        'https://positional.example',
        $afterByName($execute("WITH incoming(name,value) AS (VALUES (?1,?2)) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name", null, [1 => 'home', 2 => 'https://positional.example']))['home']['option_value']
    ),
    'cte where can compare target alias expression' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home'],
        array_column($execute("WITH incoming AS (SELECT option_name, new_value FROM import_stage) UPDATE wp_options AS current SET option_value = incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name AND current.option_id BETWEEN 1 AND 2")['updated_rows'], 'option_name')
    ),
    'cte where can use target alias glob predicate' => static fn (TestRunner $t) => $t->same(
        ['_transient_timeout_feed'],
        array_column($execute("WITH incoming(name,value) AS (VALUES ('_transient_timeout_feed','200')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name AND current.option_name GLOB '_transient*'")['updated_rows'], 'option_name')
    ),
    'cte set expression can compose source and target columns' => static fn (TestRunner $t) => $t->same(
        'https://old.example -> https://new.example',
        $afterByName($execute("WITH incoming AS (SELECT option_name, new_value FROM import_stage WHERE option_name = 'siteurl') UPDATE wp_options AS current SET option_value = current.option_value || ' -> ' || incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name"))['siteurl']['option_value']
    ),
    'cte set expression can use scalar function' => static fn (TestRunner $t) => $t->same(
        'PORTED SITE',
        $afterByName($execute("WITH incoming AS (SELECT option_name, new_value FROM import_stage WHERE option_name = 'blogname') UPDATE wp_options AS current SET option_value = upper(incoming.new_value) FROM incoming WHERE incoming.option_name = current.option_name"))['blogname']['option_value']
    ),
    'cte set expression can use case expression' => static fn (TestRunner $t) => $t->same(
        'auto',
        $afterByName($execute("WITH incoming(name,autoload) AS (VALUES ('rewrite_rules','yes')) UPDATE wp_options AS current SET autoload = CASE incoming.autoload WHEN 'yes' THEN 'auto' ELSE 'no' END FROM incoming WHERE incoming.name = current.option_name"))['rewrite_rules']['autoload']
    ),
    'cte update can change option name' => static fn (TestRunner $t) => $t->same(
        'siteurl_imported',
        $execute("WITH incoming(old_name,new_name) AS (VALUES ('siteurl','siteurl_imported')) UPDATE wp_options AS current SET option_name = incoming.new_name FROM incoming WHERE incoming.old_name = current.option_name")['updated_rows'][0]['option_name']
    ),
    'cte update option name changes final lookup' => static fn (TestRunner $t) => $t->same(
        true,
        isset($afterByName($execute("WITH incoming(old_name,new_name) AS (VALUES ('siteurl','siteurl_imported')) UPDATE wp_options AS current SET option_name = incoming.new_name FROM incoming WHERE incoming.old_name = current.option_name"))['siteurl_imported'])
    ),
    'cte update option name preserves rowid' => static fn (TestRunner $t) => $t->same(
        1,
        $afterByName($execute("WITH incoming(old_name,new_name) AS (VALUES ('siteurl','siteurl_imported')) UPDATE wp_options AS current SET option_name = incoming.new_name FROM incoming WHERE incoming.old_name = current.option_name"))['siteurl_imported']['option_id']
    ),
    'cte update or replace deletes conflicting current option' => static fn (TestRunner $t) => $t->same(
        [2],
        array_column($execute("WITH incoming(old_name,new_name) AS (VALUES ('siteurl','home')) UPDATE OR REPLACE wp_options AS current SET option_name = incoming.new_name FROM incoming WHERE incoming.old_name = current.option_name", null, [], [['option_name']])['deleted_rows'], 'option_id')
    ),
    'cte update or replace keeps incoming renamed row' => static fn (TestRunner $t) => $t->same(
        1,
        $afterByName($execute("WITH incoming(old_name,new_name) AS (VALUES ('siteurl','home')) UPDATE OR REPLACE wp_options AS current SET option_name = incoming.new_name FROM incoming WHERE incoming.old_name = current.option_name", null, [], [['option_name']]))['home']['option_id']
    ),
    'cte update abort rejects conflicting option name' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $execute("WITH incoming(old_name,new_name) AS (VALUES ('siteurl','home')) UPDATE wp_options AS current SET option_name = incoming.new_name FROM incoming WHERE incoming.old_name = current.option_name", null, [], [['option_name']])
    ),
    'cte update with no matching rows leaves changes zero' => static fn (TestRunner $t) => $t->same(
        0,
        $execute("WITH incoming(name,value) AS (VALUES ('missing_option','x')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name")['changes']
    ),
    'cte update with no matching rows leaves after unchanged' => static fn (TestRunner $t) => $t->same(
        $currentRows(),
        $execute("WITH incoming(name,value) AS (VALUES ('missing_option','x')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name")['after']
    ),
    'cte update with multiple assignments preserves column order' => static fn (TestRunner $t) => $t->same(
        ['option_value', 'autoload'],
        array_keys($execute($cteSql)['assignments'])
    ),
    'cte update supports quoted literal containing from keyword' => static fn (TestRunner $t) => $t->same(
        'copied from legacy',
        $afterByName($execute("WITH incoming(name,value) AS (VALUES ('blogname','copied from legacy')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name"))['blogname']['option_value']
    ),
    'cte update supports assignment expression containing from keyword literal' => static fn (TestRunner $t) => $t->same(
        'from:Ported Site',
        $afterByName($execute("WITH incoming AS (SELECT option_name, new_value FROM import_stage WHERE option_name = 'blogname') UPDATE wp_options AS current SET option_value = 'from:' || incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name"))['blogname']['option_value']
    ),
    'cte update supports nested cte source' => static fn (TestRunner $t) => $t->same(
        'nested',
        $afterByName($execute("WITH base(name,value) AS (VALUES ('siteurl','nested')), incoming AS (SELECT name, value FROM base) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name"))['siteurl']['option_value']
    ),
    'cte update supports derived source inside from' => static fn (TestRunner $t) => $t->same(
        'derived',
        $afterByName($execute("WITH base(name,value) AS (VALUES ('siteurl','derived')) UPDATE wp_options AS current SET option_value = incoming.value FROM (SELECT name, value FROM base) AS incoming WHERE incoming.name = current.option_name"))['siteurl']['option_value']
    ),
    'cte update with target alias rejects missing alias reference' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $execute("WITH incoming(name,value) AS (VALUES ('siteurl','x')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = wp_options.option_name")
    ),
    'cte update rejects missing update after with' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $plan("WITH incoming(name,value) AS (VALUES ('siteurl','x')) SELECT * FROM incoming")
    ),
    'target alias named set is not consumed as alias' => static fn (TestRunner $t) => $t->same(
        true,
        str_contains($plan("UPDATE wp_options SET option_value = stage.new_value FROM import_stage AS stage WHERE stage.option_name = wp_options.option_name")['select_sql'], 'FROM wp_options CROSS JOIN')
    ),
    'existing non-cte update still works' => static fn (TestRunner $t) => $t->same(
        'https://new.example',
        $afterByName($execute("UPDATE wp_options SET option_value = import_stage.new_value FROM import_stage WHERE import_stage.option_name = wp_options.option_name AND import_stage.option_name = 'siteurl'"))['siteurl']['option_value']
    ),
    'existing non-cte update still keeps last duplicate source' => static fn (TestRunner $t) => $t->same(
        'last',
        $afterByName($execute("UPDATE wp_options SET option_value = import_stage.new_value FROM import_stage WHERE import_stage.option_name = wp_options.option_name", [
            'wp_options' => [['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'old', 'autoload' => 'yes']],
            'import_stage' => [
                ['option_name' => 'siteurl', 'new_value' => 'first'],
                ['option_name' => 'siteurl', 'new_value' => 'last'],
            ],
        ]))['siteurl']['option_value']
    ),
    'multi-cte staging can feed update' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home'],
        array_column($execute("WITH ids(id) AS (VALUES (1)), incoming AS (SELECT option_name, new_value FROM import_stage JOIN ids ON ids.id = import_stage.blog_id WHERE option_name IN ('siteurl','home')) UPDATE wp_options AS current SET option_value = incoming.new_value FROM incoming WHERE incoming.option_name = current.option_name")['updated_rows'], 'option_name')
    ),
    'cte update row array before snapshot is preserved' => static fn (TestRunner $t) => $t->same(
        'https://old.example',
        $execute($cteSql)['before'][0]['option_value']
    ),
    'cte update row array after snapshot is reindexed' => static fn (TestRunner $t) => $t->same(
        [0, 1, 2, 3, 4, 5],
        array_keys($execute($cteSql)['after'])
    ),
    'cte update final row count is stable' => static fn (TestRunner $t) => $t->same(6, count($execute($cteSql)['after'])),
    'cte update source blog scope prevents wrong blog update' => static fn (TestRunner $t) => $t->same(
        'a:1:{}',
        $afterByName($execute("WITH incoming(name,value,blog_id) AS (VALUES ('widget_text','wrong-site',1)) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name AND incoming.blog_id = current.blog_id"))['widget_text']['option_value']
    ),
    'cte update source blog scope updates matching blog' => static fn (TestRunner $t) => $t->same(
        'right-site',
        $afterByName($execute("WITH incoming(name,value,blog_id) AS (VALUES ('widget_text','right-site',2)) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name AND incoming.blog_id = current.blog_id"))['widget_text']['option_value']
    ),
    'cte update select sql can be replayed for matched rows' => static fn (TestRunner $t) => $t->same(
        5,
        count($plan($cteSql)['matched_rows'])
    ),
    'ordered update from keeps order limit tail in select' => static fn (TestRunner $t) => $t->same(
        true,
        str_ends_with(
            $plan("WITH incoming(name,value) AS (VALUES ('siteurl','s'), ('home','h'), ('blogname','b')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id DESC LIMIT 2")['select_sql'],
            'ORDER BY current.option_id DESC LIMIT 2'
        )
    ),
    'ordered update from exposes order limit plan fragment' => static fn (TestRunner $t) => $t->same(
        'ORDER BY current.option_id DESC LIMIT 2',
        $plan("WITH incoming(name,value) AS (VALUES ('siteurl','s'), ('home','h'), ('blogname','b')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id DESC LIMIT 2")['order_limit_sql']
    ),
    'ordered update from applies limited descending target rows' => static fn (TestRunner $t) => $t->same(
        ['blogname', 'home'],
        array_column($execute("WITH incoming(name,value) AS (VALUES ('siteurl','s'), ('home','h'), ('blogname','b')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id DESC LIMIT 2")['updated_rows'], 'option_name')
    ),
    'ordered update from leaves first row outside descending limit unchanged' => static fn (TestRunner $t) => $t->same(
        'https://old.example',
        $afterByName($execute("WITH incoming(name,value) AS (VALUES ('siteurl','s'), ('home','h'), ('blogname','b')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id DESC LIMIT 2"))['siteurl']['option_value']
    ),
    'ordered update from changes highest matching row' => static fn (TestRunner $t) => $t->same(
        'b',
        $afterByName($execute("WITH incoming(name,value) AS (VALUES ('siteurl','s'), ('home','h'), ('blogname','b')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id DESC LIMIT 2"))['blogname']['option_value']
    ),
    'ordered update from changes second highest matching row' => static fn (TestRunner $t) => $t->same(
        'h',
        $afterByName($execute("WITH incoming(name,value) AS (VALUES ('siteurl','s'), ('home','h'), ('blogname','b')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id DESC LIMIT 2"))['home']['option_value']
    ),
    'ordered update from reports limited change count' => static fn (TestRunner $t) => $t->same(
        2,
        $execute("WITH incoming(name,value) AS (VALUES ('siteurl','s'), ('home','h'), ('blogname','b')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id DESC LIMIT 2")['changes']
    ),
    'ordered update from supports offset after limit' => static fn (TestRunner $t) => $t->same(
        ['home'],
        array_column($execute("WITH incoming(name,value) AS (VALUES ('siteurl','s'), ('home','h'), ('blogname','b')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id DESC LIMIT 1 OFFSET 1")['updated_rows'], 'option_name')
    ),
    'ordered update from supports comma limit form' => static fn (TestRunner $t) => $t->same(
        ['home'],
        array_column($execute("WITH incoming(name,value) AS (VALUES ('siteurl','s'), ('home','h'), ('blogname','b')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id DESC LIMIT 1, 1")['updated_rows'], 'option_name')
    ),
    'update from limit works without where clause' => static fn (TestRunner $t) => $t->same(
        1,
        $execute("UPDATE wp_options AS current SET autoload = 'limited' FROM import_stage AS stage ORDER BY current.option_id ASC LIMIT 1")['changes']
    ),
    'update from limit without where changes first target once despite cross join' => static fn (TestRunner $t) => $t->same(
        'limited',
        $execute("UPDATE wp_options AS current SET autoload = 'limited' FROM import_stage AS stage ORDER BY current.option_id ASC LIMIT 1")['after'][0]['autoload']
    ),
    'update from limit without where leaves later target unchanged' => static fn (TestRunner $t) => $t->same(
        'yes',
        $execute("UPDATE wp_options AS current SET autoload = 'limited' FROM import_stage AS stage ORDER BY current.option_id ASC LIMIT 1")['after'][1]['autoload']
    ),
    'update from order by can sort by source expression' => static fn (TestRunner $t) => $t->same(
        ['rewrite_rules'],
        array_column($execute("UPDATE wp_options AS current SET option_value = stage.new_value FROM import_stage AS stage WHERE stage.option_name = current.option_name AND stage.blog_id = 1 ORDER BY length(stage.new_value) DESC LIMIT 1")['updated_rows'], 'option_name')
    ),
    'update from order by source expression applies selected value' => static fn (TestRunner $t) => $t->same(
        'a:2:{s:4:"post";s:7:"/%post%";}',
        $afterByName($execute("UPDATE wp_options AS current SET option_value = stage.new_value FROM import_stage AS stage WHERE stage.option_name = current.option_name AND stage.blog_id = 1 ORDER BY length(stage.new_value) DESC LIMIT 1"))['rewrite_rules']['option_value']
    ),
    'update from order by duplicate source still keeps selected last source value' => static fn (TestRunner $t) => $t->same(
        'last',
        $afterByName($execute("WITH incoming(name,value,rank) AS (VALUES ('siteurl','first',1), ('siteurl','last',2), ('home','home',3)) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY incoming.rank ASC LIMIT 2"))['siteurl']['option_value']
    ),
    'update from order by duplicate source counts selected targets' => static fn (TestRunner $t) => $t->same(
        1,
        $execute("WITH incoming(name,value,rank) AS (VALUES ('siteurl','first',1), ('siteurl','last',2), ('home','home',3)) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY incoming.rank ASC LIMIT 2")['changes']
    ),
    'update from order by unique replace deletes conflict inside limited set' => static fn (TestRunner $t) => $t->same(
        [2],
        array_column($execute("WITH incoming(old_name,new_name,rank) AS (VALUES ('siteurl','home',1), ('blogname','blogname_imported',2)) UPDATE OR REPLACE wp_options AS current SET option_name = incoming.new_name FROM incoming WHERE incoming.old_name = current.option_name ORDER BY incoming.rank ASC LIMIT 1", null, [], [['option_name']])['deleted_rows'], 'option_id')
    ),
    'update from order by unique replace does not apply rows beyond limit' => static fn (TestRunner $t) => $t->same(
        false,
        isset($afterByName($execute("WITH incoming(old_name,new_name,rank) AS (VALUES ('siteurl','home',1), ('blogname','blogname_imported',2)) UPDATE OR REPLACE wp_options AS current SET option_name = incoming.new_name FROM incoming WHERE incoming.old_name = current.option_name ORDER BY incoming.rank ASC LIMIT 1", null, [], [['option_name']]))['blogname_imported'])
    ),
    'update from order by rejects malformed limit through select executor' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $execute("WITH incoming(name,value) AS (VALUES ('siteurl','s')) UPDATE wp_options AS current SET option_value = incoming.value FROM incoming WHERE incoming.name = current.option_name ORDER BY current.option_id LIMIT nope")
    ),
];

foreach ($cases as $name => $case) {
    $tests['sqlite update from current next25 ' . $name] = $case;
}

return $tests;
