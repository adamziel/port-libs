<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq176 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull176 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprRange176 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared176 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-exact-boundary-next176',
        'schemaCookie' => 1760,
        'stat4Generation' => 41,
        'rows' => [
            ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'stale-cache'],
            ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
            ['rowid' => 30, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail'],
            ['rowid' => 40, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo'],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_exact_boundary_next176',
            'rootPage' => 17601,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'autoload'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 30]],
                ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
                ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_t', 90]],
            ],
        ]],
    ], $overrides);
};

$current176 = static function (array $overrides = []) use ($prepared176): array {
    $source = $prepared176([
        'name' => 'current-wp-options-stat4-exact-boundary-next176',
        'schemaCookie' => 1768,
        'stat4Generation' => 57,
    ]);
    $source['indexes'][0]['rootPage'] = 17688;
    $source['rows'] = [
        ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'excluded-lower-edge'],
        ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
        ['rowid' => 30, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail'],
        ['rowid' => 40, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo'],
        ['rowid' => 90, 'autoload' => 'yes', 'option_name' => 'plugin_t', 'option_value' => 'included-upper-edge'],
        ['rowid' => 100, 'autoload' => 'no', 'option_name' => 'plugin_tail', 'option_value' => 'lazy'],
        ['rowid' => 110, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme'],
        ['rowid' => 120, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name'],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms176 = static fn (): array => [
    $exprRange176('LOWER( option_name )', '>', 'plugin_cache'),
    $exprRange176('lower(option_name)', '<=', 'plugin_t'),
    $eq176('autoload', 'yes'),
    $notNull176('option_name'),
];
$needed176 = ['option_name', 'option_value'];
$plan176 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext176(
    $prepared ?? $prepared176(),
    $current ?? $current176(),
    $terms ?? $terms176(),
    $needed ?? $needed176,
);
$inclusiveLower176 = static function () use ($terms176, $exprRange176, $plan176): array {
    $terms = $terms176();
    $terms[0] = $exprRange176('lower(option_name)', '>=', 'plugin_cache');

    return $plan176(null, null, $terms);
};
$exclusiveUpper176 = static function () use ($terms176, $exprRange176, $plan176): array {
    $terms = $terms176();
    $terms[1] = $exprRange176('lower(option_name)', '<', 'plugin_t');

    return $plan176(null, null, $terms);
};
$nonCovering176 = static function () use ($current176, $plan176): array {
    $current = $current176();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan176(null, $current, null, ['option_name', 'option_value']);
};
$wideRange176 = static function () use ($terms176, $exprRange176, $plan176): array {
    $terms = $terms176();
    $terms[0] = $exprRange176('lower(option_name)', '>', 'option_');

    return $plan176(null, null, $terms);
};

return [
    'planner stat4 expression partial current source next176 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next176-ready', $plan176()['status']),
    'planner stat4 expression partial current source next176 selected current' => static fn (TestRunner $t) => $t->same('current', $plan176()['selectedSource']),
    'planner stat4 expression partial current source next176 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan176()['stalePreparedStatement']),
    'planner stat4 expression partial current source next176 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan176()['reprepareRequired']),
    'planner stat4 expression partial current source next176 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan176()['schemaCookieChanged']),
    'planner stat4 expression partial current source next176 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan176()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next176 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_exact_boundary_next176', $plan176()['selectedPlan']['name']),
    'planner stat4 expression partial current source next176 root page' => static fn (TestRunner $t) => $t->same(17688, $plan176()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next176 covering' => static fn (TestRunner $t) => $t->same(true, $plan176()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next176 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan176()['tableLookupRequired']),
    'planner stat4 expression partial current source next176 partial proved' => static fn (TestRunner $t) => $t->same(true, $plan176()['selectedPlan']['partialPredicateImpliedByRange']),
    'planner stat4 expression partial current source next176 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan176()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next176 lower key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan176()['rangeBoundary']['lowerKey']),
    'planner stat4 expression partial current source next176 upper key' => static fn (TestRunner $t) => $t->same('plugin_t', $plan176()['rangeBoundary']['upperKey']),
    'planner stat4 expression partial current source next176 lower exclusive' => static fn (TestRunner $t) => $t->same(false, $plan176()['rangeBoundary']['lowerInclusive']),
    'planner stat4 expression partial current source next176 upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan176()['rangeBoundary']['upperInclusive']),
    'planner stat4 expression partial current source next176 seek opcode' => static fn (TestRunner $t) => $t->same('SeekGT', $plan176()['rangeBoundary']['lowerSeekOpcode']),
    'planner stat4 expression partial current source next176 fence opcode' => static fn (TestRunner $t) => $t->same('IdxLE', $plan176()['rangeBoundary']['upperFenceOpcode']),
    'planner stat4 expression partial current source next176 exact opcodes' => static fn (TestRunner $t) => $t->same(true, $plan176()['rangeBoundary']['usesExactBoundaryOpcodes']),
    'planner stat4 expression partial current source next176 matched rowids' => static fn (TestRunner $t) => $t->same([20, 30, 40, 90], $plan176()['matchedRowids']),
    'planner stat4 expression partial current source next176 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_t'], $plan176()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next176 lower edge excluded' => static fn (TestRunner $t) => $t->same(false, in_array(10, $plan176()['matchedRowids'], true)),
    'planner stat4 expression partial current source next176 upper edge included' => static fn (TestRunner $t) => $t->same(true, in_array(90, $plan176()['matchedRowids'], true)),
    'planner stat4 expression partial current source next176 autoload no excluded' => static fn (TestRunner $t) => $t->same(false, in_array(100, $plan176()['matchedRowids'], true)),
    'planner stat4 expression partial current source next176 theme excluded' => static fn (TestRunner $t) => $t->same(false, in_array(110, $plan176()['matchedRowids'], true)),
    'planner stat4 expression partial current source next176 null excluded' => static fn (TestRunner $t) => $t->same(false, in_array(120, $plan176()['matchedRowids'], true)),
    'planner stat4 expression partial current source next176 audit accepted rowids' => static fn (TestRunner $t) => $t->same([20, 30, 40, 90], $plan176()['boundaryRowAudit']['acceptedRowids']),
    'planner stat4 expression partial current source next176 audit accepted keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_t'], $plan176()['boundaryRowAudit']['acceptedKeys']),
    'planner stat4 expression partial current source next176 audit no leaks' => static fn (TestRunner $t) => $t->same([], $plan176()['boundaryRowAudit']['leakedRowids']),
    'planner stat4 expression partial current source next176 audit upper edge' => static fn (TestRunner $t) => $t->same([90], $plan176()['boundaryRowAudit']['upperEdgeRowids']),
    'planner stat4 expression partial current source next176 audit lower edge absent' => static fn (TestRunner $t) => $t->same([], $plan176()['boundaryRowAudit']['lowerEdgeRowids']),
    'planner stat4 expression partial current source next176 audit hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan176()['boundaryRowAudit']['signature'])),
    'planner stat4 expression partial current source next176 selected ready' => static fn (TestRunner $t) => $t->same(true, $plan176()['selectedPlan']['next176Ready']),
    'planner stat4 expression partial current source next176 selected seek' => static fn (TestRunner $t) => $t->same('SeekGT', $plan176()['selectedPlan']['next176SeekOpcode']),
    'planner stat4 expression partial current source next176 selected fence' => static fn (TestRunner $t) => $t->same('IdxLE', $plan176()['selectedPlan']['next176UpperFenceOpcode']),
    'planner stat4 expression partial current source next176 selected boundary hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan176()['selectedPlan']['next176BoundarySignature'])),
    'planner stat4 expression partial current source next176 fence boundary hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan176()['stat4Fence']['next176BoundarySignature'])),
    'planner stat4 expression partial current source next176 fence rowids' => static fn (TestRunner $t) => $t->same([20, 30, 40, 90], $plan176()['stat4Fence']['next176AcceptedRowids']),
    'planner stat4 expression partial current source next176 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan176()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next176 cursor boundary fence' => static fn (TestRunner $t) => $t->same('FenceExactRangeBoundary', $plan176()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next176 cursor seek gt' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGT', 'key' => 'plugin_cache'], $plan176()['cursorProgram'][2]),
    'planner stat4 expression partial current source next176 cursor idx le' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxLE', 'key' => 'plugin_t'], $plan176()['cursorProgram'][3]),
    'planner stat4 expression partial current source next176 cursor covering' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan176()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next176 cursor residual' => static fn (TestRunner $t) => $t->same('ResidualPartialCheck', $plan176()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source next176 cursor result rowids' => static fn (TestRunner $t) => $t->same([20, 30, 40, 90], $plan176()['cursorProgram'][6]['rowids']),
    'planner stat4 expression partial current source next176 cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan176()['cursorProgram'][7]['opcode']),
    'planner stat4 expression partial current source next176 inclusive lower seek' => static fn (TestRunner $t) => $t->same('SeekGE', $inclusiveLower176()['rangeBoundary']['lowerSeekOpcode']),
    'planner stat4 expression partial current source next176 inclusive lower admits edge' => static fn (TestRunner $t) => $t->same([10], $inclusiveLower176()['boundaryRowAudit']['lowerEdgeRowids']),
    'planner stat4 expression partial current source next176 exclusive upper fence' => static fn (TestRunner $t) => $t->same('IdxLT', $exclusiveUpper176()['rangeBoundary']['upperFenceOpcode']),
    'planner stat4 expression partial current source next176 exclusive upper drops edge' => static fn (TestRunner $t) => $t->same(false, in_array(90, $exclusiveUpper176()['matchedRowids'], true)),
    'planner stat4 expression partial current source next176 noncovering ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next176-ready', $nonCovering176()['status']),
    'planner stat4 expression partial current source next176 noncovering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering176()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next176 wide range fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $wideRange176()['status']),
    'planner stat4 expression partial current source next176 wide cursor fallback' => static fn (TestRunner $t) => $t->same('FallbackFullScan', $wideRange176()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next176 detail' => static fn (TestRunner $t) => $t->contains('NEXT176 EXACT RANGE BOUNDARIES', $plan176()['detail']),
    'planner stat4 expression partial current source next176 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-stat4-expression-partial-current-source-next176', implode(',', $plan176()['dependencies'])),
    'planner stat4 expression partial current source next176 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan176()['dependency_closure']),
    'planner stat4 expression partial current source next176 non overlap' => static fn (TestRunner $t) => $t->contains('exclusive/inclusive expression range cursor boundaries', $plan176()['non_overlap']),
    'planner stat4 expression partial current source next176 invalid matched rowid' => static function (TestRunner $t) use ($current176, $plan176): void {
        $bad = $current176();
        $bad['rows'][1]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan176(null, $bad));
    },
];
