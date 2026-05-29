<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan;

$expr146 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column146 = static fn (string $name): array => ['column' => $name];
$point146 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range146 = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and146 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower146 = $expr146('lower', 'option_name');
$preparedPredicate146 = $and146($range146($lower146, '>=', 'plugin_'), $range146($lower146, '<=', 'plugin_z'), $point146($column146('autoload'), 'yes'));
$currentPredicate146 = $and146($range146($lower146, '>', 'plugin_beta'), $range146($lower146, '<=', 'plugin_seo'), $point146($column146('autoload'), 'yes'));
$needed146 = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];
$order146 = [['function' => 'lower', 'column' => 'option_name', 'direction' => 'DESC'], ['column' => 'option_id', 'direction' => 'DESC']];

$preparedSource146 = static fn (array $overrides = []): array => array_replace_recursive([
    'name' => 'prepared-wordpress-expression-covering-range-next146',
    'schemaCookie' => 1460,
    'stat4Generation' => 70,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_desc_covering_next146',
        'rootPage' => 14601,
        'estimatedRows' => 720,
        'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
        'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 101]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 102]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_cache', 103]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_forms', 104]],
            ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_mail', 105]],
            ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['plugin_seo', 106]],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_desc_covering_next146 ON wp_options(lower(option_name) DESC, option_id DESC, option_value, blog_id) WHERE autoload = 'yes'",
    ]],
], $overrides);

$currentSource146 = static function (array $overrides = []) use ($preparedSource146): array {
    $source = $preparedSource146();
    $source['name'] = 'current-wordpress-expression-covering-range-next146';
    $source['schemaCookie'] = 1464;
    $source['stat4Generation'] = 76;
    $source['indexes'][0]['rootPage'] = 14644;

    return array_replace_recursive($source, $overrides);
};

$rows146 = static fn (): array => [
    ['rowid' => 10, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 10, 'blog_id' => 1],
    ['rowid' => 20, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'option_value' => 'beta-enabled', 'option_id' => 20, 'blog_id' => 1],
    ['rowid' => 30, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 30, 'blog_id' => 1],
    ['rowid' => 35, 'option_name' => 'Plugin_Cache_Extra', 'autoload' => 'yes', 'option_value' => 'cache-extra', 'option_id' => 35, 'blog_id' => 2],
    ['rowid' => 40, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 40, 'blog_id' => 1],
    ['rowid' => 50, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 50, 'blog_id' => 3],
    ['rowid' => 60, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 60, 'blog_id' => 1],
    ['rowid' => 70, 'option_name' => 'plugin_theme', 'autoload' => 'no', 'option_value' => 'theme-disabled', 'option_id' => 70, 'blog_id' => 1],
];

$nextSource146 = static function (array $overrides = []) use ($currentSource146, $rows146): array {
    $source = $currentSource146();
    $source['rows'] = $rows146();

    return array_replace_recursive($source, $overrides);
};

$plan146 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $next = null,
    ?array $preparedPredicate = null,
    ?array $currentPredicate = null,
    ?array $rows = null,
    ?array $order = null,
    ?array $needed = null,
): array => SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan::materialize(
    $prepared ?? $preparedSource146(),
    $current ?? $currentSource146(),
    $next,
    $preparedPredicate ?? $preparedPredicate146,
    $currentPredicate ?? $currentPredicate146,
    $rows ?? $rows146(),
    $order ?? $order146,
    $needed ?? $needed146,
    [$lower146],
);

$ready146 = static fn (): array => $plan146(null, null, $nextSource146());
$noNext146 = static fn (): array => $plan146();
$staleNext146 = static function () use ($plan146, $nextSource146, $rows146): array {
    $rows = $rows146();
    $rows[] = ['rowid' => 80, 'option_name' => 'plugin_security', 'autoload' => 'yes', 'option_value' => 'shield', 'option_id' => 80, 'blog_id' => 1];

    return $plan146(null, null, $nextSource146([
        'schemaCookie' => 1465,
        'stat4Generation' => 77,
        'rows' => $rows,
        'indexes' => [[
            'rootPage' => 14655,
        ]],
    ]));
};
$uncovered146 = static function () use ($currentSource146, $plan146): array {
    $current = $currentSource146();
    $current['indexes'][0]['coveringColumns'] = ['option_name', 'autoload'];

    return $plan146(null, $current, null);
};

$tests = [
    'planner expression covering range current source next146 status ready' => static fn (TestRunner $t) => $t->same('expression-covering-range-current-source-next146-ready', $ready146()['status']),
    'planner expression covering range current source next146 no next source ready' => static fn (TestRunner $t) => $t->same('expression-covering-range-current-source-next146-ready', $noNext146()['status']),
    'planner expression covering range current source next146 selects current' => static fn (TestRunner $t) => $t->same('current', $ready146()['selectedSource']),
    'planner expression covering range current source next146 stale prepared' => static fn (TestRunner $t) => $t->same(true, $ready146()['stalePreparedStatement']),
    'planner expression covering range current source next146 reprepare' => static fn (TestRunner $t) => $t->same(true, $ready146()['reprepareRequired']),
    'planner expression covering range current source next146 schema changed' => static fn (TestRunner $t) => $t->same(true, $ready146()['schemaCookieChanged']),
    'planner expression covering range current source next146 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $ready146()['stat4GenerationChanged']),
    'planner expression covering range current source next146 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_desc_covering_next146', $ready146()['selectedPlan']['name']),
    'planner expression covering range current source next146 root page' => static fn (TestRunner $t) => $t->same(14644, $ready146()['selectedPlan']['rootPage']),
    'planner expression covering range current source next146 descending' => static fn (TestRunner $t) => $t->same('DESC', $ready146()['rangeDirection']),
    'planner expression covering range current source next146 seek opcode' => static fn (TestRunner $t) => $t->same('SeekLE', $ready146()['rangeSeekOpcode']),
    'planner expression covering range current source next146 stop opcode' => static fn (TestRunner $t) => $t->same('IdxLE', $ready146()['rangeStopOpcode']),
    'planner expression covering range current source next146 rowids descending' => static fn (TestRunner $t) => $t->same([60, 50, 40, 35, 30], $ready146()['coveringRangeRowids']),
    'planner expression covering range current source next146 current source rowids' => static fn (TestRunner $t) => $t->same([60, 50, 40, 35, 30], $ready146()['currentSourceNextRowids']),
    'planner expression covering range current source next146 current keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_cache_extra', 'plugin_cache'], $ready146()['currentSourceNextKeys']),
    'planner expression covering range current source next146 stale rejected rowids' => static fn (TestRunner $t) => $t->same([10, 20], $ready146()['staleRangeRejectedRowids']),
    'planner expression covering range current source next146 admitted rowids' => static fn (TestRunner $t) => $t->same([], $ready146()['currentRangeAdmittedRowids']),
    'planner expression covering range current source next146 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $ready146()['tableLookupElided']),
    'planner expression covering range current source next146 deferred seek null' => static fn (TestRunner $t) => $t->same(null, $ready146()['cursorTape']['deferredSeekOpcode']),
    'planner expression covering range current source next146 cursor fence opcode' => static fn (TestRunner $t) => $t->same('FenceCurrentSource', $ready146()['cursorTape']['program'][0]['opcode']),
    'planner expression covering range current source next146 cursor range recheck after fence' => static fn (TestRunner $t) => $t->same('RecheckRangeBounds', $ready146()['cursorTape']['program'][1]['opcode']),
    'planner expression covering range current source next146 cursor seeks index' => static fn (TestRunner $t) => $t->same('SeekLE', $ready146()['cursorTape']['program'][2]['opcode']),
    'planner expression covering range current source next146 cursor stops index' => static fn (TestRunner $t) => $t->same('IdxLE', $ready146()['cursorTape']['program'][3]['opcode']),
    'planner expression covering range current source next146 cursor prev' => static fn (TestRunner $t) => $t->same('Prev', $ready146()['cursorTape']['nextOpcode']),
    'planner expression covering range current source next146 next admitted' => static fn (TestRunner $t) => $t->same(true, $ready146()['nextSourceAdmitted']),
    'planner expression covering range current source next146 next summary matches' => static fn (TestRunner $t) => $t->same(true, $ready146()['nextSource']['matchesCurrentSource']),
    'planner expression covering range current source next146 next reasons empty' => static fn (TestRunner $t) => $t->same([], $ready146()['nextSource']['replanReasons']),
    'planner expression covering range current source next146 no next summary null' => static fn (TestRunner $t) => $t->same(null, $noNext146()['nextSource']),
    'planner expression covering range current source next146 payload signature length' => static fn (TestRunner $t) => $t->same(64, strlen($ready146()['coveringRangePayloadSignature'])),
    'planner expression covering range current source next146 source signature length' => static fn (TestRunner $t) => $t->same(64, strlen($ready146()['currentSourceFence']['next146SourceSignature'])),
    'planner expression covering range current source next146 selected signature length' => static fn (TestRunner $t) => $t->same(64, strlen($ready146()['selectedPlan']['next146SourceSignature'])),
    'planner expression covering range current source next146 selected payload signature length' => static fn (TestRunner $t) => $t->same(64, strlen($ready146()['selectedPlan']['next146PayloadSignature'])),
    'planner expression covering range current source next146 payload signature shared with tape' => static fn (TestRunner $t) => $t->same($ready146()['coveringRangePayloadSignature'], $ready146()['cursorTape']['next146PayloadSignature']),
    'planner expression covering range current source next146 covering count' => static fn (TestRunner $t) => $t->same(5, $ready146()['selectedPlan']['next146CoveringRowCount']),
    'planner expression covering range current source next146 ready flag' => static fn (TestRunner $t) => $t->same(true, $ready146()['selectedPlan']['next146Ready']),
    'planner expression covering range current source next146 current next admitted selected' => static fn (TestRunner $t) => $t->same(true, $ready146()['selectedPlan']['next146NextSourceAdmitted']),
    'planner expression covering range current source next146 order signature' => static fn (TestRunner $t) => $t->same('lower DESC, option_id DESC', $ready146()['currentSourceFence']['next146OrderSignature']),
    'planner expression covering range current source next146 covering columns' => static fn (TestRunner $t) => $t->same($GLOBALS['needed146'], $ready146()['currentSourceFence']['next146CoveringColumns']),
    'planner expression covering range current source next146 expression count' => static fn (TestRunner $t) => $t->same(1, $ready146()['currentSourceFence']['next146CoveringExpressionCount']),
    'planner expression covering range current source next146 first payload' => static fn (TestRunner $t) => $t->same('seo-enabled', $ready146()['coveringRangeRows'][0]['covering']['option_value']),
    'planner expression covering range current source next146 next rowid chain' => static fn (TestRunner $t) => $t->same([50, 40, 35, 30, null], array_column($ready146()['coveringRangeRows'], 'nextRowid')),
    'planner expression covering range current source next146 excludes prepared alpha' => static fn (TestRunner $t) => $t->same(false, in_array(10, $ready146()['coveringRangeRowids'], true)),
    'planner expression covering range current source next146 excludes prepared beta' => static fn (TestRunner $t) => $t->same(false, in_array(20, $ready146()['coveringRangeRowids'], true)),
    'planner expression covering range current source next146 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(70, $ready146()['coveringRangeRowids'], true)),
    'planner expression covering range current source next146 detail fenced' => static fn (TestRunner $t) => $t->contains('NEXT-SOURCE FENCED', $ready146()['detail']),
    'planner expression covering range current source next146 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-expression-covering-range-current-source-next146', $ready146()['dependencies'], true)),
    'planner expression covering range current source next146 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $ready146()['dependency_closure']),
    'planner expression covering range current source next146 non overlap' => static fn (TestRunner $t) => $t->contains('fences the current covering expression range payload', $ready146()['non_overlap']),
    'planner expression covering range current source next146 stale next falls back' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $staleNext146()['status']),
    'planner expression covering range current source next146 stale next not admitted' => static fn (TestRunner $t) => $t->same(false, $staleNext146()['nextSourceAdmitted']),
    'planner expression covering range current source next146 stale next no table elision after fence' => static fn (TestRunner $t) => $t->same(false, $staleNext146()['cursorTape']['tableLookupElidedAfterNextFence']),
    'planner expression covering range current source next146 stale next deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $staleNext146()['cursorTape']['deferredSeekOpcode']),
    'planner expression covering range current source next146 stale next name' => static fn (TestRunner $t) => $t->same('current-wordpress-expression-covering-range-next146', $staleNext146()['nextSource']['name']),
    'planner expression covering range current source next146 stale next cookie' => static fn (TestRunner $t) => $t->same(1465, $staleNext146()['nextSource']['schemaCookie']),
    'planner expression covering range current source next146 stale next stat4' => static fn (TestRunner $t) => $t->same(77, $staleNext146()['nextSource']['stat4Generation']),
    'planner expression covering range current source next146 stale next reasons' => static fn (TestRunner $t) => $t->same(['schema-cookie', 'stat4-generation', 'index-signature', 'row-stream'], $staleNext146()['nextSource']['replanReasons']),
    'planner expression covering range current source next146 stale next signature length' => static fn (TestRunner $t) => $t->same(64, strlen($staleNext146()['nextSource']['sourceSignature'])),
    'planner expression covering range current source next146 stale next detail' => static fn (TestRunner $t) => $t->contains('NEXT-SOURCE STALE', $staleNext146()['detail']),
    'planner expression covering range current source next146 uncovered falls back' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $uncovered146()['status']),
    'planner expression covering range current source next146 uncovered ready false' => static fn (TestRunner $t) => $t->same(false, $uncovered146()['selectedPlan']['next146Ready']),
    'planner expression covering range current source next146 validates next rows list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan146(null, null, $nextSource146(['rows' => ['bad' => []]]))),
    'planner expression covering range current source next146 validates next row arrays' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan146(null, null, $nextSource146(['rows' => ['bad']]))),
];

$GLOBALS['needed146'] = $needed146;

return $tests;
