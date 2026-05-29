<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4ExpressionPartialCurrentSourceNextPlan;

$term = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['left' => ['column' => $column], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
$exprEq = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '=', 'right' => $right];

$preparedSource = static fn (array $overrides = []): array => array_replace_recursive([
    'name' => 'prepared-wp-options-stat4-expression-partial',
    'schemaCookie' => 1630,
    'stat4Generation' => 7,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_updated_old',
        'rootPage' => 16301,
        'expression' => 'lower(option_name)',
        'expressionColumn' => 'option_name',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'baseCost' => 4,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'sample' => ['siteurl', 100, 1]],
            ['neq' => '2 1 1', 'nlt' => '1 1 1', 'sample' => ['siteurl', 200, 2]],
            ['neq' => '1 1 1', 'nlt' => '3 3 3', 'sample' => ['home', 300, 3]],
        ],
    ]],
], $overrides);

$currentSource = static fn (array $overrides = []): array => array_replace_recursive([
    'name' => 'current-wp-options-stat4-expression-partial',
    'schemaCookie' => 1634,
    'stat4Generation' => 11,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_updated',
        'rootPage' => 16341,
        'expression' => 'lower(option_name)',
        'expressionColumn' => 'option_name',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'baseCost' => 2,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'sample' => ['home', 130, 20]],
            ['neq' => '1 1 1', 'nlt' => '1 1 1', 'sample' => ['siteurl', 90, 21]],
            ['neq' => '2 1 1', 'nlt' => '2 2 2', 'sample' => ['siteurl', 150, 22]],
            ['neq' => '3 1 1', 'nlt' => '4 4 4', 'sample' => ['siteurl', 260, 23]],
            ['neq' => '1 1 1', 'nlt' => '7 7 7', 'sample' => ['siteurl', 420, 24]],
            ['neq' => '1 1 1', 'nlt' => '8 8 8', 'sample' => ['transient_timeout', 200, 25]],
        ],
    ], [
        'name' => 'idx_wp_options_lower_autoload_updated_sparse',
        'rootPage' => 16342,
        'expression' => 'lower(option_name)',
        'expressionColumn' => 'option_name',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
        ],
        'baseCost' => 1,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'sample' => ['siteurl', 150, 31]],
        ],
    ]],
], $overrides);

$query = static fn (): array => [
    $exprEq('LOWER( option_name )', 'siteurl'),
    $term('autoload', '=', 'yes'),
    $term('option_name', 'IS NOT NULL'),
    $between('updated_at', 100, 300),
];
$planForSources = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null): array => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materialize(
    $prepared ?? $preparedSource(),
    $current ?? $currentSource(),
    $terms ?? $query(),
);
$freshSource = static function () use ($preparedSource, $planForSources): array {
    $source = $preparedSource();

    return $planForSources($source, $source);
};
$openRange = static fn (): array => $planForSources(null, null, [
    $exprEq('lower(option_name)', 'siteurl'),
    $term('autoload', '=', 'yes'),
    $term('option_name', 'IS NOT NULL'),
    $term('updated_at', '>', 100),
    $term('updated_at', '<', 300),
]);
$missingPartial = static fn (): array => $planForSources(null, null, [
    $exprEq('lower(option_name)', 'siteurl'),
    $between('updated_at', 100, 300),
]);
$sparsePartial = static fn (): array => $planForSources(null, null, [
    $exprEq('lower(option_name)', 'siteurl'),
    $term('autoload', '=', 'yes'),
    $term('blog_id', '=', 1),
    $between('updated_at', 100, 300),
]);

return [
    'stat4 expression partial status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-ready', $planForSources()['status']),
    'stat4 expression partial selects current' => static fn (TestRunner $t) => $t->same('current', $planForSources()['selectedSource']),
    'stat4 expression partial marks stale prepared' => static fn (TestRunner $t) => $t->same(true, $planForSources()['stalePreparedStatement']),
    'stat4 expression partial reparses stale prepared' => static fn (TestRunner $t) => $t->same(true, $planForSources()['reprepareRequired']),
    'stat4 expression partial schema cookie changed' => static fn (TestRunner $t) => $t->same(true, $planForSources()['schemaCookieChanged']),
    'stat4 expression partial stat4 generation changed' => static fn (TestRunner $t) => $t->same(true, $planForSources()['stat4GenerationChanged']),
    'stat4 expression partial index signature changed' => static fn (TestRunner $t) => $t->same(true, $planForSources()['indexSignatureChanged']),
    'stat4 expression partial selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_autoload_updated', $planForSources()['selectedPlan']['name']),
    'stat4 expression partial root page' => static fn (TestRunner $t) => $t->same(16341, $planForSources()['selectedPlan']['rootPage']),
    'stat4 expression partial expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $planForSources()['selectedPlan']['expression']),
    'stat4 expression partial expression column' => static fn (TestRunner $t) => $t->same('option_name', $planForSources()['selectedPlan']['expressionColumn']),
    'stat4 expression partial partial implied' => static fn (TestRunner $t) => $t->same(true, $planForSources()['selectedPlan']['partialPredicateImplied']),
    'stat4 expression partial equality value' => static fn (TestRunner $t) => $t->same('siteurl', $planForSources()['selectedPlan']['equalityValue']),
    'stat4 expression partial range column' => static fn (TestRunner $t) => $t->same('updated_at', $planForSources()['selectedPlan']['rangeColumn']),
    'stat4 expression partial lower bound' => static fn (TestRunner $t) => $t->same(100, $planForSources()['selectedPlan']['rangeLower']),
    'stat4 expression partial upper bound' => static fn (TestRunner $t) => $t->same(300, $planForSources()['selectedPlan']['rangeUpper']),
    'stat4 expression partial lower inclusive' => static fn (TestRunner $t) => $t->same(true, $planForSources()['selectedPlan']['lowerInclusive']),
    'stat4 expression partial upper inclusive' => static fn (TestRunner $t) => $t->same(true, $planForSources()['selectedPlan']['upperInclusive']),
    'stat4 expression partial stat4 used' => static fn (TestRunner $t) => $t->same(true, $planForSources()['selectedPlan']['stat4Used']),
    'stat4 expression partial matched count' => static fn (TestRunner $t) => $t->same(2, $planForSources()['selectedPlan']['matchedSampleCount']),
    'stat4 expression partial matched rowids' => static fn (TestRunner $t) => $t->same([22, 23], $planForSources()['selectedPlan']['matchedRowids']),
    'stat4 expression partial matched keys' => static fn (TestRunner $t) => $t->same([['siteurl', 150], ['siteurl', 260]], $planForSources()['selectedPlan']['matchedKeys']),
    'stat4 expression partial estimates rows from neq' => static fn (TestRunner $t) => $t->same(5, $planForSources()['selectedPlan']['estimatedRows']),
    'stat4 expression partial adds base cost' => static fn (TestRunner $t) => $t->same(7, $planForSources()['selectedPlan']['estimatedCost']),
    'stat4 expression partial prepared summary row count' => static fn (TestRunner $t) => $t->same(2, $planForSources()['preparedSource']['matchedSampleCount']),
    'stat4 expression partial current summary row count' => static fn (TestRunner $t) => $t->same(2, $planForSources()['currentSource']['matchedSampleCount']),
    'stat4 expression partial cursor source' => static fn (TestRunner $t) => $t->same('current', $planForSources()['cursorTape']['source']),
    'stat4 expression partial cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_autoload_updated', $planForSources()['cursorTape']['indexName']),
    'stat4 expression partial seek opcode inclusive' => static fn (TestRunner $t) => $t->same('SeekGE', $planForSources()['cursorTape']['seekOpcode']),
    'stat4 expression partial stop opcode inclusive' => static fn (TestRunner $t) => $t->same('IdxGT', $planForSources()['cursorTape']['stopOpcode']),
    'stat4 expression partial cursor equality' => static fn (TestRunner $t) => $t->same('siteurl', $planForSources()['cursorTape']['equalityValue']),
    'stat4 expression partial cursor lower' => static fn (TestRunner $t) => $t->same(100, $planForSources()['cursorTape']['rangeLower']),
    'stat4 expression partial cursor upper' => static fn (TestRunner $t) => $t->same(300, $planForSources()['cursorTape']['rangeUpper']),
    'stat4 expression partial current next count' => static fn (TestRunner $t) => $t->same(2, count($planForSources()['cursorTape']['matchedCurrentNext'])),
    'stat4 expression partial current next first rowid' => static fn (TestRunner $t) => $t->same(22, $planForSources()['cursorTape']['matchedCurrentNext'][0]['current']['rowid']),
    'stat4 expression partial current next first next rowid' => static fn (TestRunner $t) => $t->same(23, $planForSources()['cursorTape']['matchedCurrentNext'][0]['next']['rowid']),
    'stat4 expression partial current next eof' => static fn (TestRunner $t) => $t->same(null, $planForSources()['cursorTape']['matchedCurrentNext'][1]['next']),
    'stat4 expression partial program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $planForSources()['cursorTape']['program'][0]['opcode']),
    'stat4 expression partial program seek' => static fn (TestRunner $t) => $t->same('SeekGE', $planForSources()['cursorTape']['program'][1]['opcode']),
    'stat4 expression partial program stop' => static fn (TestRunner $t) => $t->same('IdxGT', $planForSources()['cursorTape']['program'][2]['opcode']),
    'stat4 expression partial program column' => static fn (TestRunner $t) => $t->same('option_name', $planForSources()['cursorTape']['program'][3]['column']),
    'stat4 expression partial program next' => static fn (TestRunner $t) => $t->same('Next', $planForSources()['cursorTape']['program'][4]['opcode']),
    'stat4 expression partial table lookup deferred' => static fn (TestRunner $t) => $t->same(true, $planForSources()['tableLookupDeferred']),
    'stat4 expression partial residual predicate required' => static fn (TestRunner $t) => $t->same(true, $planForSources()['residualPredicateRequired']),
    'stat4 expression partial fence cookie' => static fn (TestRunner $t) => $t->same(1634, $planForSources()['currentSourceFence']['schemaCookie']),
    'stat4 expression partial fence stat4' => static fn (TestRunner $t) => $t->same(11, $planForSources()['currentSourceFence']['stat4Generation']),
    'stat4 expression partial fence query signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planForSources()['currentSourceFence']['querySignature'])),
    'stat4 expression partial fence sample signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planForSources()['currentSourceFence']['sampleSignature'])),
    'stat4 expression partial detail names reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 EXPRESSION PARTIAL CURRENT SOURCE', $planForSources()['detail']),
    'stat4 expression partial dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-stat4-expression-partial-current-source', $planForSources()['dependencies'], true)),
    'stat4 expression partial dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $planForSources()['dependency_closure']),
    'stat4 expression partial non overlap' => static fn (TestRunner $t) => $t->contains('equality+range STAT4 samples', $planForSources()['non_overlap']),
    'stat4 expression partial fresh source reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $freshSource()['selectedSource']),
    'stat4 expression partial fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $freshSource()['reprepareRequired']),
    'stat4 expression partial fresh rowids' => static fn (TestRunner $t) => $t->same([1, 2], $freshSource()['selectedPlan']['matchedRowids']),
    'stat4 expression partial open seek gt' => static fn (TestRunner $t) => $t->same('SeekGT', $openRange()['cursorTape']['seekOpcode']),
    'stat4 expression partial open stop ge' => static fn (TestRunner $t) => $t->same('IdxGE', $openRange()['cursorTape']['stopOpcode']),
    'stat4 expression partial open excludes boundary samples' => static fn (TestRunner $t) => $t->same([22, 23], $openRange()['selectedPlan']['matchedRowids']),
    'stat4 expression partial missing partial falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingPartial()['status']),
    'stat4 expression partial missing partial no table deferral' => static fn (TestRunner $t) => $t->same(false, $missingPartial()['tableLookupDeferred']),
    'stat4 expression partial sparse partial can win' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_autoload_updated_sparse', $sparsePartial()['selectedPlan']['name']),
    'stat4 expression partial sparse rowid' => static fn (TestRunner $t) => $t->same([31], $sparsePartial()['selectedPlan']['matchedRowids']),
    'stat4 expression partial validates source indexes' => static function (TestRunner $t) use ($preparedSource, $currentSource, $query): void {
        $bad = $currentSource();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materialize($preparedSource(), $bad, $query()));
    },
    'stat4 expression partial validates stat4 sample shape' => static function (TestRunner $t) use ($preparedSource, $currentSource, $query): void {
        $bad = $currentSource();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['siteurl'];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materialize($preparedSource(), $bad, $query()));
    },
    'stat4 expression partial validates stat4 neq' => static function (TestRunner $t) use ($preparedSource, $currentSource, $query): void {
        $bad = $currentSource();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = '0 0 0';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materialize($preparedSource(), $bad, $query()));
    },
    'stat4 expression partial validates schema cookie' => static function (TestRunner $t) use ($preparedSource, $currentSource, $query): void {
        $bad = $preparedSource(['schemaCookie' => -1]);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materialize($bad, $currentSource(), $query()));
    },
];
