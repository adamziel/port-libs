<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan;

$expr126 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$lower126 = $expr126('lower', 'option_name');
$predicate126 = [
    'operator' => 'IN',
    'left' => $lower126,
    'values' => ['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'],
];
$needed126 = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];

$preparedSource126 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-stat4-covering-expression-in-next126',
        'schemaCookie' => 1260,
        'stat4Generation' => 42,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_in_covering_stat4_next126',
            'rootPage' => 12601,
            'estimatedRows' => 480,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 201]],
                ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 202]],
                ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 203]],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_lower_in_covering_stat4_next126 ON wp_options(lower(option_name), option_id, option_value, blog_id, autoload)',
        ]],
    ];
};

$currentSource126 = static function () use ($preparedSource126): array {
    $source = $preparedSource126([
        'name' => 'current-stat4-covering-expression-in-next126',
        'schemaCookie' => 1264,
        'stat4Generation' => 45,
    ]);
    $source['indexes'][0]['rootPage'] = 12644;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 401]],
        ['neq' => '3 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 402]],
        ['neq' => '1 1', 'nlt' => '5 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 403]],
        ['neq' => '2 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 404]],
        ['neq' => '8 1', 'nlt' => '8 4', 'ndlt' => '4 4', 'sample' => ['theme_mods', 405]],
    ];

    return $source;
};

$rows126 = static fn (): array => [
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 22, 'option_name' => 'Plugin_Cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'option_id' => 22, 'blog_id' => 3],
    ['rowid' => 61, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'option_id' => 61, 'blog_id' => 1],
    ['rowid' => 71, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'option_value' => 'beta', 'option_id' => 71, 'blog_id' => 1],
];

$plan126 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $rows = null,
    ?array $needed = null,
): array => SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan::materializeNext126(
    $prepared ?? $preparedSource126(),
    $current ?? $currentSource126(),
    $predicate ?? $predicate126,
    $rows ?? $rows126(),
    $needed ?? $needed126,
    [$lower126],
);

$fresh126 = static fn (): array => $plan126($preparedSource126(), $preparedSource126(['name' => 'current-fresh-stat4-covering-expression-in-next126']));
$nonCovering126 = static function () use ($currentSource126, $plan126): array {
    $current = $currentSource126();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan126(null, $current);
};
$noStat4126 = static function () use ($currentSource126, $plan126): array {
    $current = $currentSource126();
    foreach ($current['indexes'] as &$index) {
        $index['stat4Samples'] = [];
    }
    unset($index);

    return $plan126(null, $current);
};
$missingSample126 = static function () use ($plan126, $predicate126): array {
    $predicate = $predicate126;
    $predicate['values'] = ['plugin_beta'];

    return $plan126(null, null, $predicate);
};

$tests = [
    'planner stat4 covering expression in current source next126 status ready' => static fn (TestRunner $t) => $t->same('stat4-covering-expression-in-current-source-ready', $plan126()['status']),
    'planner stat4 covering expression in current source next126 selects current' => static fn (TestRunner $t) => $t->same('current', $plan126()['selectedSource']),
    'planner stat4 covering expression in current source next126 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan126()['stalePreparedStatement']),
    'planner stat4 covering expression in current source next126 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan126()['reprepareRequired']),
    'planner stat4 covering expression in current source next126 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan126()['schemaCookieChanged']),
    'planner stat4 covering expression in current source next126 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan126()['stat4GenerationChanged']),
    'planner stat4 covering expression in current source next126 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan126()['indexSignatureChanged']),
    'planner stat4 covering expression in current source next126 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_in_covering_stat4_next126', $plan126()['selectedPlan']['name']),
    'planner stat4 covering expression in current source next126 selected root' => static fn (TestRunner $t) => $t->same(12644, $plan126()['selectedPlan']['rootPage']),
    'planner stat4 covering expression in current source next126 type lower' => static fn (TestRunner $t) => $t->same('lower', $plan126()['selectedPlan']['type']),
    'planner stat4 covering expression in current source next126 column option name' => static fn (TestRunner $t) => $t->same('option_name', $plan126()['selectedPlan']['column']),
    'planner stat4 covering expression in current source next126 operator in' => static fn (TestRunner $t) => $t->same('IN', $plan126()['selectedPlan']['operator']),
    'planner stat4 covering expression in current source next126 values preserved' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan126()['selectedPlan']['values']),
    'planner stat4 covering expression in current source next126 covering true' => static fn (TestRunner $t) => $t->same(true, $plan126()['selectedPlan']['covering']),
    'planner stat4 covering expression in current source next126 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan126()['selectedPlan']['stat4Used']),
    'planner stat4 covering expression in current source next126 stat4 matched' => static fn (TestRunner $t) => $t->same(4, $plan126()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 covering expression in current source next126 covered row count' => static fn (TestRunner $t) => $t->same(5, $plan126()['selectedPlan']['coveredRowCount']),
    'planner stat4 covering expression in current source next126 keys sorted' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan126()['cursorTape']['matchedKeys']),
    'planner stat4 covering expression in current source next126 first rowid' => static fn (TestRunner $t) => $t->same(21, $plan126()['currentNextRows'][0]['current']['rowid']),
    'planner stat4 covering expression in current source next126 duplicate key keeps rowid order' => static fn (TestRunner $t) => $t->same(22, $plan126()['currentNextRows'][1]['current']['rowid']),
    'planner stat4 covering expression in current source next126 last next eof' => static fn (TestRunner $t) => $t->same(null, $plan126()['currentNextRows'][4]['next']),
    'planner stat4 covering expression in current source next126 excludes value without stat4' => static fn (TestRunner $t) => $t->same(false, in_array(71, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan126()['currentNextRows']), true)),
    'planner stat4 covering expression in current source next126 excludes outside value' => static fn (TestRunner $t) => $t->same(false, in_array(61, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan126()['currentNextRows']), true)),
    'planner stat4 covering expression in current source next126 covering value' => static fn (TestRunner $t) => $t->same('forms-enabled', $plan126()['currentNextRows'][2]['current']['covering']['option_value']),
    'planner stat4 covering expression in current source next126 covering blog id' => static fn (TestRunner $t) => $t->same(2, $plan126()['currentNextRows'][3]['current']['covering']['blog_id']),
    'planner stat4 covering expression in current source next126 expression payload' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan126()['currentNextRows'][3]['current']['coveringExpressions']['lower(option_name)']),
    'planner stat4 covering expression in current source next126 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan126()['tableLookupElided']),
    'planner stat4 covering expression in current source next126 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan126()['deferredTableSeekOpcode']),
    'planner stat4 covering expression in current source next126 sorter elided' => static fn (TestRunner $t) => $t->same(true, $plan126()['tempSorterElided']),
    'planner stat4 covering expression in current source next126 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan126()['cursorTape']['source']),
    'planner stat4 covering expression in current source next126 cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_in_covering_stat4_next126', $plan126()['cursorTape']['indexName']),
    'planner stat4 covering expression in current source next126 cursor root' => static fn (TestRunner $t) => $t->same(12644, $plan126()['cursorTape']['rootPage']),
    'planner stat4 covering expression in current source next126 cursor expression type' => static fn (TestRunner $t) => $t->same('lower', $plan126()['cursorTape']['expressionType']),
    'planner stat4 covering expression in current source next126 seek keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan126()['cursorTape']['seekKeys']),
    'planner stat4 covering expression in current source next126 seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $plan126()['cursorTape']['seekOpcode']),
    'planner stat4 covering expression in current source next126 stop opcode' => static fn (TestRunner $t) => $t->same('IdxGT', $plan126()['cursorTape']['stopOpcode']),
    'planner stat4 covering expression in current source next126 output from index' => static fn (TestRunner $t) => $t->same('index', $plan126()['cursorTape']['outputColumns'][2]['source']),
    'planner stat4 covering expression in current source next126 program first seek' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'source' => 'index', 'key' => 'plugin_cache'], $plan126()['cursorTape']['program'][0]),
    'planner stat4 covering expression in current source next126 program first stop' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxGT', 'source' => 'index', 'key' => 'plugin_cache'], $plan126()['cursorTape']['program'][1]),
    'planner stat4 covering expression in current source next126 program result covering' => static fn (TestRunner $t) => $t->same('covering-index', $plan126()['cursorTape']['program'][7]['source']),
    'planner stat4 covering expression in current source next126 no rowid dedupe required' => static fn (TestRunner $t) => $t->same(false, $plan126()['cursorTape']['dedupeByRowid']),
    'planner stat4 covering expression in current source next126 fence cookie' => static fn (TestRunner $t) => $t->same(1264, $plan126()['currentSourceFence']['schemaCookie']),
    'planner stat4 covering expression in current source next126 fence stat4' => static fn (TestRunner $t) => $t->same(45, $plan126()['currentSourceFence']['stat4Generation']),
    'planner stat4 covering expression in current source next126 fence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan126()['currentSourceFence']['indexSignature'])),
    'planner stat4 covering expression in current source next126 predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan126()['currentSourceFence']['predicateSignature'])),
    'planner stat4 covering expression in current source next126 covering signature' => static fn (TestRunner $t) => $t->same('option_name,autoload,option_value,option_id,blog_id', $plan126()['currentSourceFence']['coveringSignature']),
    'planner stat4 covering expression in current source next126 prepared summary root' => static fn (TestRunner $t) => $t->same(12601, $plan126()['preparedSource']['rootPage']),
    'planner stat4 covering expression in current source next126 current summary root' => static fn (TestRunner $t) => $t->same(12644, $plan126()['currentSource']['rootPage']),
    'planner stat4 covering expression in current source next126 detail reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE COVERING EXPRESSION STAT4 IN', $plan126()['detail']),
    'planner stat4 covering expression in current source next126 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-planner-stat4-covering-expression-in-current-source-next126', implode(',', $plan126()['dependencies'])),
    'planner stat4 covering expression in current source next126 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan126()['dependency_closure']),
    'planner stat4 covering expression in current source next126 non overlap' => static fn (TestRunner $t) => $t->contains('multi-seek IN expression probes', $plan126()['non_overlap']),
    'planner stat4 covering expression in current source next126 fresh selects prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh126()['selectedSource']),
    'planner stat4 covering expression in current source next126 fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $fresh126()['reprepareRequired']),
    'planner stat4 covering expression in current source next126 fresh root' => static fn (TestRunner $t) => $t->same(12601, $fresh126()['selectedPlan']['rootPage']),
    'planner stat4 covering expression in current source next126 non covering requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCovering126()['status']),
    'planner stat4 covering expression in current source next126 non covering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering126()['deferredTableSeekOpcode']),
    'planner stat4 covering expression in current source next126 no stat4 requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4126()['status']),
    'planner stat4 covering expression in current source next126 no stat4 matched zero' => static fn (TestRunner $t) => $t->same(0, $noStat4126()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 covering expression in current source next126 missing sample requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingSample126()['status']),
    'planner stat4 covering expression in current source next126 missing sample rows zero' => static fn (TestRunner $t) => $t->same(0, $missingSample126()['selectedPlan']['coveredRowCount']),
    'planner stat4 covering expression in current source next126 validates source indexes' => static function (TestRunner $t) use ($preparedSource126, $currentSource126, $predicate126, $rows126, $needed126, $lower126): void {
        $bad = $preparedSource126();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan::materializeNext126($bad, $currentSource126(), $predicate126, $rows126(), $needed126, [$lower126]));
    },
    'planner stat4 covering expression in current source next126 validates schema cookie' => static function (TestRunner $t) use ($preparedSource126, $currentSource126, $predicate126, $rows126, $needed126, $lower126): void {
        $bad = $preparedSource126(['schemaCookie' => -1]);
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan::materializeNext126($bad, $currentSource126(), $predicate126, $rows126(), $needed126, [$lower126]));
    },
    'planner stat4 covering expression in current source next126 validates output columns' => static function (TestRunner $t) use ($preparedSource126, $currentSource126, $predicate126, $rows126, $lower126): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan::materializeNext126($preparedSource126(), $currentSource126(), $predicate126, $rows126(), [], [$lower126]));
    },
    'planner stat4 covering expression in current source next126 validates in operator' => static function (TestRunner $t) use ($preparedSource126, $currentSource126, $rows126, $needed126, $lower126): void {
        $bad = ['operator' => '=', 'left' => $lower126, 'right' => 'plugin_cache'];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan::materializeNext126($preparedSource126(), $currentSource126(), $bad, $rows126(), $needed126, [$lower126]));
    },
    'planner stat4 covering expression in current source next126 validates in values' => static function (TestRunner $t) use ($preparedSource126, $currentSource126, $predicate126, $rows126, $needed126, $lower126): void {
        $bad = $predicate126;
        $bad['values'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan::materializeNext126($preparedSource126(), $currentSource126(), $bad, $rows126(), $needed126, [$lower126]));
    },
];

return $tests;
