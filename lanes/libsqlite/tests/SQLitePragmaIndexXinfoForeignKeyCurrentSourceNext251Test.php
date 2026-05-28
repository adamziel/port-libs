<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext156.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext157.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext159.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext163.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext164.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext165.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext169.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext171.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext173.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext177.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext181.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext182.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext183.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext184.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext185.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext186.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext187.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext188.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext189.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext190.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext191.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext192.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext193.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext194.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext195.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext196.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext200.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext202.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext203.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext205.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext206.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext207.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext208.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext209.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext211.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext212.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext217.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext219.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext220.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext223.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext224.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext227.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext228.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext229.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext230.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext231.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext233.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext236.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext239.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext242.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record251 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords251 = [
    $record251('table', 'wp_option_defaults', 'wp_option_defaults', 2, 'CREATE TABLE wp_option_defaults(option_name TEXT PRIMARY KEY, locale TEXT NOT NULL, option_value TEXT)', 1),
    $record251('index', 'sqlite_autoindex_wp_option_defaults_1', 'wp_option_defaults', 3, null, 2),
    $record251('table', 'wp_options_stage', 'wp_options_stage', 4, "CREATE TABLE wp_options_stage(
        option_id INTEGER PRIMARY KEY,
        option_name TEXT NOT NULL,
        locale TEXT NOT NULL,
        shadow_name TEXT NOT NULL,
        FOREIGN KEY(option_name) REFERENCES wp_option_defaults(option_name) ON DELETE CASCADE,
        FOREIGN KEY(option_name, locale) REFERENCES wp_option_defaults(option_name, locale) ON UPDATE SET NULL
    )", 3),
    $record251('index', 'wp_options_stage_expr_lookup', 'wp_options_stage', 5, 'CREATE INDEX wp_options_stage_expr_lookup ON wp_options_stage(option_name, lower(locale))', 4),
    $record251('index', 'wp_options_stage_shadow_expr_lookup', 'wp_options_stage', 6, 'CREATE INDEX wp_options_stage_shadow_expr_lookup ON wp_options_stage(shadow_name, lower(locale))', 5),
];

$nextRecords251 = [
    $currentRecords251[0],
    $currentRecords251[1],
    $currentRecords251[2],
    $record251('index', 'wp_options_stage_fk_lookup', 'wp_options_stage', 5, 'CREATE INDEX wp_options_stage_fk_lookup ON wp_options_stage(option_name, locale)', 4),
    $currentRecords251[4],
];

$unrelatedRecords251 = [
    $currentRecords251[0],
    $currentRecords251[1],
    $currentRecords251[2],
    $record251('index', 'wp_options_stage_unrelated_expr', 'wp_options_stage', 5, 'CREATE INDEX wp_options_stage_unrelated_expr ON wp_options_stage(lower(locale), option_name)', 4),
];

$page251 = static fn (
    int $offset = 0,
    int $limit = 360,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251::page(
    $currentRecords251,
    $nextRecords ?? $nextRecords251,
    'PRAGMA main.index_xinfo(wp_options_stage_expr_lookup)',
    'PRAGMA main.foreign_key_list(wp_options_stage)',
    $offset,
    $limit,
    $resume,
);

$valueAt251 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default251 = static fn (): array => $page251();
$currentRows251 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251::expressionChildActionRows($currentRecords251);
$nextRows251 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251::expressionChildActionRows($nextRecords251, 'next');
$unrelatedRows251 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251::expressionChildActionRows($unrelatedRecords251);
$currentPageRows251 = static fn (): array => array_values(array_filter(
    $page251()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_child_action_expression_index'
        && ($row['phase'] ?? null) === 'current',
));

$cases251 = [
    'status ok' => [$default251, 'status', 'ok'],
    'operation marker' => [$default251, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next251'],
    'source id length' => [static fn (): array => ['len' => strlen($page251()['source_id'])], 'len', 64],
    'offset default' => [$default251, 'offset', 0],
    'limit default' => [$default251, 'limit', 360],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-child-action-expression-index', $page251()['dependencies'], true)], 'has', true],
    'base external source retained' => [$default251, 'current_source.foreign_key_parent_external_unique_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_list_origin_and_pragma_index_xinfo'],
    'expression source current' => [$default251, 'current_source.foreign_key_child_action_expression_index_source', 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_expression_child_prefix'],
    'expression source next' => [$default251, 'next_source.foreign_key_child_action_expression_index_source', 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_expression_child_prefix'],
    'current rows' => [$default251, 'current.foreign_key_child_action_expression_index.rows', 2],
    'current blocked' => [$default251, 'current.foreign_key_child_action_expression_index.blocked', 2],
    'current expression blockers' => [$default251, 'current.foreign_key_child_action_expression_index.expression_child_action_index', 2],
    'current cascade count' => [$default251, 'current.foreign_key_child_action_expression_index.cascade', 0],
    'current set null count' => [$default251, 'current.foreign_key_child_action_expression_index.set_null', 2],
    'current set default zero' => [$default251, 'current.foreign_key_child_action_expression_index.set_default', 0],
    'current restrict zero' => [$default251, 'current.foreign_key_child_action_expression_index.restrict', 0],
    'current composite columns' => [$default251, 'current.foreign_key_child_action_expression_index.composite_columns', 2],
    'current expression key terms' => [$default251, 'current.foreign_key_child_action_expression_index.expression_key_terms', 2],
    'current matched prefix columns' => [$default251, 'current.foreign_key_child_action_expression_index.matched_prefix_columns', 2],
    'next rows cleared' => [$default251, 'next_counts.foreign_key_child_action_expression_index.rows', 0],
    'next blocked cleared' => [$default251, 'next_counts.foreign_key_child_action_expression_index.blocked', 0],
    'delta rows decreased' => [$default251, 'delta.foreign_key_child_action_expression_index_rows', -2],
    'delta blockers decreased' => [$default251, 'delta.foreign_key_child_action_expression_index_blockers', -2],
    'delta repaired true' => [$default251, 'delta.foreign_key_child_action_expression_index_repaired', true],
    'delta changed true' => [$default251, 'delta.foreign_key_child_action_expression_index_changed', true],
    'current summary composite first' => [$default251, 'current_source.foreign_key_child_action_expression_index.0', 'current:wp_options_stage#1.0:option_name->wp_option_defaults.option_name:SET NULL/NO ACTION:index=wp_options_stage_expr_lookup:expr=1:expression_child_action_index'],
    'current summary composite second' => [$default251, 'current_source.foreign_key_child_action_expression_index.1', 'current:wp_options_stage#1.1:locale->wp_option_defaults.locale:SET NULL/NO ACTION:index=wp_options_stage_expr_lookup:expr=1:expression_child_action_index'],
    'complete no next' => [$default251, 'next', null],
    'first row kind' => [$currentPageRows251, '0.kind', 'foreign_key_child_action_expression_index'],
    'first row status' => [$currentPageRows251, '0.status', 'expression_child_action_index'],
    'first row blocked' => [$currentPageRows251, '0.blocked', true],
    'first row index' => [$currentPageRows251, '0.child_index', 'wp_options_stage_expr_lookup'],
    'first row unique flag' => [$currentPageRows251, '0.child_index_unique', 0],
    'first row partial flag' => [$currentPageRows251, '0.child_index_partial', 0],
    'first row columns' => [$currentPageRows251, '0.child_index_columns', ['option_name', null]],
    'first row expression positions' => [$currentPageRows251, '0.expression_key_positions', [1]],
    'first row expression count' => [$currentPageRows251, '0.expression_key_count', 1],
    'first row matched prefix' => [$currentPageRows251, '0.matched_prefix_columns', 1],
    'first row message' => [$currentPageRows251, '0.message', 'foreign key wp_options_stage action lookup cannot use child index wp_options_stage_expr_lookup because PRAGMA index_xinfo reports expression key terms'],
    'composite second from' => [$currentPageRows251, '1.from', 'locale'],
    'composite child columns' => [$currentPageRows251, '0.child_columns', ['option_name', 'locale']],
    'helper current count' => [static fn (): array => ['count' => count($currentRows251())], 'count', 2],
    'helper next empty' => [static fn (): array => ['count' => count($nextRows251())], 'count', 0],
    'helper unrelated empty' => [static fn (): array => ['count' => count($unrelatedRows251())], 'count', 0],
];

$tests = [];
foreach ($cases251 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey expression child action current source next251 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt251): void {
        $t->same($expected, $valueAt251($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey expression child action current source next251 paginates appended rows'] = static function (TestRunner $t) use ($page251): void {
    $full = $page251();
    $baseCount = $full['total'] - 2;
    $first = $page251(0, $baseCount);
    $second = $page251($baseCount, 2, $first['next']);
    $third = $page251($baseCount + 2, 2, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_child_action_expression_index', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('option_name', $second['rows'][0]['from']);
    $t->same('expression_child_action_index', $second['rows'][1]['status']);
    $t->same(0, $third['count']);
    $t->same(null, $second['next']);
};

$tests['pragma index xinfo foreignkey expression child action current source next251 ignores no action foreign keys'] = static function (TestRunner $t) use ($record251): void {
    $records = [
        $record251('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record251('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent(id))', 2),
        $record251('index', 'child_expr', 'child', 4, 'CREATE INDEX child_expr ON child(parent_id, abs(parent_id))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251::expressionChildActionRows($records));
};

$tests['pragma index xinfo foreignkey expression child action current source next251 ignores non expression child indexes'] = static function (TestRunner $t) use ($record251): void {
    $records = [
        $record251('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a INTEGER, b INTEGER, UNIQUE(a, b))', 1),
        $record251('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record251('table', 'child', 'child', 4, 'CREATE TABLE child(a INTEGER, b INTEGER, FOREIGN KEY(a, b) REFERENCES parent(a, b) ON DELETE CASCADE)', 3),
        $record251('index', 'child_plain', 'child', 5, 'CREATE INDEX child_plain ON child(a, b)', 4),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251::expressionChildActionRows($records));
};

$tests['pragma index xinfo foreignkey expression child action current source next251 rejects stale cursor'] = static function (TestRunner $t) use ($page251, $currentRecords251): void {
    $full = $page251();
    $first = $page251(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page251($full['total'] - 3, 2, $first['next'], $currentRecords251));
};

$tests['pragma index xinfo foreignkey expression child action current source next251 rejects stale offset'] = static function (TestRunner $t) use ($page251): void {
    $full = $page251();
    $first = $page251(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page251($full['total'] - 2, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey expression child action current source next251 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251::expressionChildActionRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey expression child action current source next251 rejects invalid bounds'] = static function (TestRunner $t) use ($page251): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page251(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page251(0, 0));
};

return $tests;
