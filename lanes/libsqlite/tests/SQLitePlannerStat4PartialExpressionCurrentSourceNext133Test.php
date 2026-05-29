<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan;

$expr133 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column133 = static fn (string $name): array => ['column' => $name];
$point133 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range133 = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and133 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower133 = $expr133('lower', 'option_name');
$predicate133 = $and133(
    $range133($lower133, '>=', 'plugin_'),
    $range133($lower133, '<', 'plugin_z'),
    $point133($column133('autoload'), 'yes'),
);
$order133 = [$lower133, ['column' => 'option_id']];
$needed133 = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];

$preparedSource133 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-partial-expression-stat4-next133',
        'schemaCookie' => 1330,
        'stat4Generation' => 40,
        'rowGeneration' => 10,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_partial_expr_next133',
            'rootPage' => 13301,
            'estimatedRows' => 360,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 21]],
                ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 31]],
                ['neq' => '4 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 51]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_partial_expr_next133 ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
        ]],
    ];
};

$currentSource133 = static function () use ($preparedSource133): array {
    $source = $preparedSource133([
        'name' => 'current-partial-expression-stat4-next133',
        'schemaCookie' => 1334,
        'stat4Generation' => 47,
        'rowGeneration' => 16,
    ]);
    $source['indexes'][0]['rootPage'] = 13344;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 11]],
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 21]],
        ['neq' => '3 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 31]],
        ['neq' => '2 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 41]],
        ['neq' => '4 1', 'nlt' => '8 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 51]],
        ['neq' => '1 1', 'nlt' => '12 5', 'ndlt' => '5 5', 'sample' => ['theme_mods', 71]],
    ];

    return $source;
};

$preparedRows133 = static fn (): array => [
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-old', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 81, 'option_name' => 'plugin_deleted', 'autoload' => 'yes', 'option_value' => 'deleted', 'option_id' => 81, 'blog_id' => 1],
];

$currentRows133 = static fn (): array => [
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 11, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-new', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 61, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'option_id' => 61, 'blog_id' => 3],
    ['rowid' => 71, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'option_id' => 71, 'blog_id' => 1],
];

$plan133 = static fn (
    ?array $preparedSource = null,
    ?array $currentSource = null,
    ?array $preparedRows = null,
    ?array $currentRows = null,
    ?array $predicate = null,
): array => SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan::materializeNext133(
    $preparedSource ?? $preparedSource133(),
    $currentSource ?? $currentSource133(),
    $predicate ?? $GLOBALS['predicate_next133'],
    $preparedRows ?? $GLOBALS['prepared_rows_next133'](),
    $currentRows ?? $GLOBALS['current_rows_next133'](),
    $GLOBALS['order_next133'],
    $GLOBALS['needed_next133'],
    [$GLOBALS['lower_next133']],
);

$GLOBALS['predicate_next133'] = $predicate133;
$GLOBALS['prepared_rows_next133'] = $preparedRows133;
$GLOBALS['current_rows_next133'] = $currentRows133;
$GLOBALS['order_next133'] = $order133;
$GLOBALS['needed_next133'] = $needed133;
$GLOBALS['lower_next133'] = $lower133;

$fresh133 = static fn (): array => $plan133($preparedSource133(), $preparedSource133(['name' => 'current-fresh-next133']), $preparedRows133(), $preparedRows133());
$noStat4133 = static function () use ($currentSource133, $plan133): array {
    $current = $currentSource133();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan133(null, $current);
};
$nonCovering133 = static function () use ($currentSource133, $plan133): array {
    $current = $currentSource133();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan133(null, $current);
};

return [
    'planner stat4 partial expression current source next133 status ready' => static fn (TestRunner $t) => $t->same('partial-expression-stat4-current-source-next-ready', $plan133()['status']),
    'planner stat4 partial expression current source next133 selects current' => static fn (TestRunner $t) => $t->same('current', $plan133()['selectedSource']),
    'planner stat4 partial expression current source next133 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan133()['stalePreparedStatement']),
    'planner stat4 partial expression current source next133 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan133()['reprepareRequired']),
    'planner stat4 partial expression current source next133 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan133()['schemaCookieChanged']),
    'planner stat4 partial expression current source next133 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan133()['stat4GenerationChanged']),
    'planner stat4 partial expression current source next133 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan133()['indexSignatureChanged']),
    'planner stat4 partial expression current source next133 row signature changed' => static fn (TestRunner $t) => $t->same(true, $plan133()['rowSignatureChanged']),
    'planner stat4 partial expression current source next133 prepared row signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan133()['preparedRowSignature'])),
    'planner stat4 partial expression current source next133 current row signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan133()['currentRowSignature'])),
    'planner stat4 partial expression current source next133 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_expr_next133', $plan133()['selectedPlan']['name']),
    'planner stat4 partial expression current source next133 selected root' => static fn (TestRunner $t) => $t->same(13344, $plan133()['selectedPlan']['rootPage']),
    'planner stat4 partial expression current source next133 partial true' => static fn (TestRunner $t) => $t->same(true, $plan133()['selectedPlan']['partial']),
    'planner stat4 partial expression current source next133 covering true' => static fn (TestRunner $t) => $t->same(true, $plan133()['selectedPlan']['covering']),
    'planner stat4 partial expression current source next133 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan133()['selectedPlan']['stat4Used']),
    'planner stat4 partial expression current source next133 operator bounded' => static fn (TestRunner $t) => $t->same('range-bounded', $plan133()['selectedPlan']['operator']),
    'planner stat4 partial expression current source next133 type lower' => static fn (TestRunner $t) => $t->same('lower', $plan133()['selectedPlan']['type']),
    'planner stat4 partial expression current source next133 column option name' => static fn (TestRunner $t) => $t->same('option_name', $plan133()['selectedPlan']['column']),
    'planner stat4 partial expression current source next133 estimated rows' => static fn (TestRunner $t) => $t->same(12, $plan133()['selectedPlan']['estimatedRows']),
    'planner stat4 partial expression current source next133 covered rows' => static fn (TestRunner $t) => $t->same(5, $plan133()['selectedPlan']['coveredRowCount']),
    'planner stat4 partial expression current source next133 partial expression flag' => static fn (TestRunner $t) => $t->same(true, $plan133()['selectedPlan']['partialExpressionCurrentSource']),
    'planner stat4 partial expression current source next133 row generation changed flag' => static fn (TestRunner $t) => $t->same(true, $plan133()['selectedPlan']['rowGenerationChanged']),
    'planner stat4 partial expression current source next133 deleted blocked' => static fn (TestRunner $t) => $t->same([81], $plan133()['selectedPlan']['deletedPreparedRowidsBlocked']),
    'planner stat4 partial expression current source next133 inserted admitted' => static fn (TestRunner $t) => $t->same([11, 41, 61, 71], $plan133()['selectedPlan']['insertedCurrentRowidsAdmitted']),
    'planner stat4 partial expression current source next133 updated refreshed' => static fn (TestRunner $t) => $t->same([21], $plan133()['selectedPlan']['updatedCurrentRowidsRefreshed']),
    'planner stat4 partial expression current source next133 row fence prepared count' => static fn (TestRunner $t) => $t->same(4, $plan133()['rowGenerationFence']['preparedRows']),
    'planner stat4 partial expression current source next133 row fence current count' => static fn (TestRunner $t) => $t->same(7, $plan133()['rowGenerationFence']['currentRows']),
    'planner stat4 partial expression current source next133 row fence unchanged' => static fn (TestRunner $t) => $t->same([31, 51], $plan133()['rowGenerationFence']['unchangedRowids']),
    'planner stat4 partial expression current source next133 matched rowids sorted' => static fn (TestRunner $t) => $t->same([11, 21, 31, 41, 51], $plan133()['cursorTape']['matchedRowids']),
    'planner stat4 partial expression current source next133 matched keys sorted' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan133()['cursorTape']['matchedKeys']),
    'planner stat4 partial expression current source next133 blocks deleted rowid' => static fn (TestRunner $t) => $t->same([81], $plan133()['cursorTape']['deletedRowidsBlocked']),
    'planner stat4 partial expression current source next133 cursor inserts' => static fn (TestRunner $t) => $t->same([11, 41, 61, 71], $plan133()['cursorTape']['insertedRowids']),
    'planner stat4 partial expression current source next133 cursor updates' => static fn (TestRunner $t) => $t->same([21], $plan133()['cursorTape']['updatedRowids']),
    'planner stat4 partial expression current source next133 current next first row' => static fn (TestRunner $t) => $t->same(11, $plan133()['currentNextRows'][0]['current']['rowid']),
    'planner stat4 partial expression current source next133 current next second next' => static fn (TestRunner $t) => $t->same(31, $plan133()['currentNextRows'][1]['next']['rowid']),
    'planner stat4 partial expression current source next133 current next last eof' => static fn (TestRunner $t) => $t->same(null, $plan133()['currentNextRows'][4]['next']),
    'planner stat4 partial expression current source next133 uses refreshed payload' => static fn (TestRunner $t) => $t->same('cache-new', $plan133()['currentNextRows'][1]['current']['covering']['option_value']),
    'planner stat4 partial expression current source next133 excludes disabled partial miss' => static fn (TestRunner $t) => $t->same(false, in_array(61, $plan133()['cursorTape']['matchedRowids'], true)),
    'planner stat4 partial expression current source next133 excludes upper range miss' => static fn (TestRunner $t) => $t->same(false, in_array(71, $plan133()['cursorTape']['matchedRowids'], true)),
    'planner stat4 partial expression current source next133 no table lookup' => static fn (TestRunner $t) => $t->same(true, $plan133()['tableLookupElided']),
    'planner stat4 partial expression current source next133 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan133()['deferredTableSeekOpcode']),
    'planner stat4 partial expression current source next133 row fence keeps table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan133()['cursorTape']['tableLookupElidedAfterRowFence']),
    'planner stat4 partial expression current source next133 program opens partial expression index' => static fn (TestRunner $t) => $t->same('partial-expression-index', $plan133()['cursorTape']['program'][0]['source']),
    'planner stat4 partial expression current source next133 program fences source' => static fn (TestRunner $t) => $t->same('FenceCurrentSource', $plan133()['cursorTape']['program'][1]['opcode']),
    'planner stat4 partial expression current source next133 program filters deleted' => static fn (TestRunner $t) => $t->same(['opcode' => 'FilterDeletedRowids', 'rowids' => [81]], $plan133()['cursorTape']['program'][4]),
    'planner stat4 partial expression current source next133 program reads current covering' => static fn (TestRunner $t) => $t->same('current-covering-index', $plan133()['cursorTape']['program'][5]['source']),
    'planner stat4 partial expression current source next133 current fence row generation' => static fn (TestRunner $t) => $t->same(16, $plan133()['currentSourceFence']['rowGeneration']),
    'planner stat4 partial expression current source next133 current fence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan133()['currentSourceFence']['rowSignature'])),
    'planner stat4 partial expression current source next133 detail' => static fn (TestRunner $t) => $t->contains('REPREPARE PARTIAL EXPRESSION STAT4 CURRENT SOURCE NEXT133', $plan133()['detail']),
    'planner stat4 partial expression current source next133 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-partial-expression-current-source-next133', $plan133()['dependencies'], true)),
    'planner stat4 partial expression current source next133 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan133()['dependency_closure']),
    'planner stat4 partial expression current source next133 non overlap' => static fn (TestRunner $t) => $t->contains('avoids accepted range-cost', $plan133()['non_overlap']),
    'planner stat4 partial expression current source next133 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh133()['selectedSource']),
    'planner stat4 partial expression current source next133 fresh no row signature change' => static fn (TestRunner $t) => $t->same(false, $fresh133()['rowSignatureChanged']),
    'planner stat4 partial expression current source next133 fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $fresh133()['reprepareRequired']),
    'planner stat4 partial expression current source next133 no stat4 requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4133()['status']),
    'planner stat4 partial expression current source next133 non covering requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCovering133()['status']),
    'planner stat4 partial expression current source next133 validates rowid' => static function (TestRunner $t) use ($preparedRows133, $currentRows133, $plan133): void {
        $bad = $preparedRows133();
        $bad[0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan133(null, null, $bad, $currentRows133()));
    },
    'planner stat4 partial expression current source next133 validates row generation' => static function (TestRunner $t) use ($currentSource133, $plan133): void {
        $bad = $currentSource133();
        $bad['rowGeneration'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan133(null, $bad));
    },
];
