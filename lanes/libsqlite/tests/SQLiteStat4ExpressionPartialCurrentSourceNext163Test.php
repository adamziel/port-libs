<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4ExpressionPartialCurrentSourceNextPlan;

$term163 = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$between163 = static fn (string $column, mixed $lower, mixed $upper): array => ['left' => ['column' => $column], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
$exprEq163 = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '=', 'right' => $right];

$prepared163 = static fn (array $overrides = []): array => array_replace_recursive([
    'name' => 'prepared-wp-options-stat4-expression-partial-next163',
    'schemaCookie' => 1630,
    'stat4Generation' => 7,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_updated_next163_old',
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

$current163 = static fn (array $overrides = []): array => array_replace_recursive([
    'name' => 'current-wp-options-stat4-expression-partial-next163',
    'schemaCookie' => 1634,
    'stat4Generation' => 11,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_autoload_updated_next163',
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
        'name' => 'idx_wp_options_lower_autoload_updated_next163_sparse',
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

$query163 = static fn (): array => [
    $exprEq163('LOWER( option_name )', 'siteurl'),
    $term163('autoload', '=', 'yes'),
    $term163('option_name', 'IS NOT NULL'),
    $between163('updated_at', 100, 300),
];
$plan163 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null): array => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materializeNext163(
    $prepared ?? $prepared163(),
    $current ?? $current163(),
    $terms ?? $query163(),
);
$fresh163 = static function () use ($prepared163, $plan163): array {
    $source = $prepared163();

    return $plan163($source, $source);
};
$openRange163 = static fn (): array => $plan163(null, null, [
    $exprEq163('lower(option_name)', 'siteurl'),
    $term163('autoload', '=', 'yes'),
    $term163('option_name', 'IS NOT NULL'),
    $term163('updated_at', '>', 100),
    $term163('updated_at', '<', 300),
]);
$missingPartial163 = static fn (): array => $plan163(null, null, [
    $exprEq163('lower(option_name)', 'siteurl'),
    $between163('updated_at', 100, 300),
]);
$sparse163 = static fn (): array => $plan163(null, null, [
    $exprEq163('lower(option_name)', 'siteurl'),
    $term163('autoload', '=', 'yes'),
    $term163('blog_id', '=', 1),
    $between163('updated_at', 100, 300),
]);

return [
    'stat4 expression partial next163 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next163-ready', $plan163()['status']),
    'stat4 expression partial next163 selects current' => static fn (TestRunner $t) => $t->same('current', $plan163()['selectedSource']),
    'stat4 expression partial next163 marks stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan163()['stalePreparedStatement']),
    'stat4 expression partial next163 reparses stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan163()['reprepareRequired']),
    'stat4 expression partial next163 schema cookie changed' => static fn (TestRunner $t) => $t->same(true, $plan163()['schemaCookieChanged']),
    'stat4 expression partial next163 stat4 generation changed' => static fn (TestRunner $t) => $t->same(true, $plan163()['stat4GenerationChanged']),
    'stat4 expression partial next163 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan163()['indexSignatureChanged']),
    'stat4 expression partial next163 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_autoload_updated_next163', $plan163()['selectedPlan']['name']),
    'stat4 expression partial next163 root page' => static fn (TestRunner $t) => $t->same(16341, $plan163()['selectedPlan']['rootPage']),
    'stat4 expression partial next163 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan163()['selectedPlan']['expression']),
    'stat4 expression partial next163 expression column' => static fn (TestRunner $t) => $t->same('option_name', $plan163()['selectedPlan']['expressionColumn']),
    'stat4 expression partial next163 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan163()['selectedPlan']['partialPredicateImplied']),
    'stat4 expression partial next163 equality value' => static fn (TestRunner $t) => $t->same('siteurl', $plan163()['selectedPlan']['equalityValue']),
    'stat4 expression partial next163 range column' => static fn (TestRunner $t) => $t->same('updated_at', $plan163()['selectedPlan']['rangeColumn']),
    'stat4 expression partial next163 lower bound' => static fn (TestRunner $t) => $t->same(100, $plan163()['selectedPlan']['rangeLower']),
    'stat4 expression partial next163 upper bound' => static fn (TestRunner $t) => $t->same(300, $plan163()['selectedPlan']['rangeUpper']),
    'stat4 expression partial next163 lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan163()['selectedPlan']['lowerInclusive']),
    'stat4 expression partial next163 upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan163()['selectedPlan']['upperInclusive']),
    'stat4 expression partial next163 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan163()['selectedPlan']['stat4Used']),
    'stat4 expression partial next163 matched count' => static fn (TestRunner $t) => $t->same(2, $plan163()['selectedPlan']['matchedSampleCount']),
    'stat4 expression partial next163 matched rowids' => static fn (TestRunner $t) => $t->same([22, 23], $plan163()['selectedPlan']['matchedRowids']),
    'stat4 expression partial next163 matched keys' => static fn (TestRunner $t) => $t->same([['siteurl', 150], ['siteurl', 260]], $plan163()['selectedPlan']['matchedKeys']),
    'stat4 expression partial next163 estimates rows from neq' => static fn (TestRunner $t) => $t->same(5, $plan163()['selectedPlan']['estimatedRows']),
    'stat4 expression partial next163 adds base cost' => static fn (TestRunner $t) => $t->same(7, $plan163()['selectedPlan']['estimatedCost']),
    'stat4 expression partial next163 prepared summary row count' => static fn (TestRunner $t) => $t->same(2, $plan163()['preparedSource']['matchedSampleCount']),
    'stat4 expression partial next163 current summary row count' => static fn (TestRunner $t) => $t->same(2, $plan163()['currentSource']['matchedSampleCount']),
    'stat4 expression partial next163 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan163()['cursorTape']['source']),
    'stat4 expression partial next163 cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_autoload_updated_next163', $plan163()['cursorTape']['indexName']),
    'stat4 expression partial next163 seek opcode inclusive' => static fn (TestRunner $t) => $t->same('SeekGE', $plan163()['cursorTape']['seekOpcode']),
    'stat4 expression partial next163 stop opcode inclusive' => static fn (TestRunner $t) => $t->same('IdxGT', $plan163()['cursorTape']['stopOpcode']),
    'stat4 expression partial next163 cursor equality' => static fn (TestRunner $t) => $t->same('siteurl', $plan163()['cursorTape']['equalityValue']),
    'stat4 expression partial next163 cursor lower' => static fn (TestRunner $t) => $t->same(100, $plan163()['cursorTape']['rangeLower']),
    'stat4 expression partial next163 cursor upper' => static fn (TestRunner $t) => $t->same(300, $plan163()['cursorTape']['rangeUpper']),
    'stat4 expression partial next163 current next count' => static fn (TestRunner $t) => $t->same(2, count($plan163()['cursorTape']['matchedCurrentNext'])),
    'stat4 expression partial next163 current next first rowid' => static fn (TestRunner $t) => $t->same(22, $plan163()['cursorTape']['matchedCurrentNext'][0]['current']['rowid']),
    'stat4 expression partial next163 current next first next rowid' => static fn (TestRunner $t) => $t->same(23, $plan163()['cursorTape']['matchedCurrentNext'][0]['next']['rowid']),
    'stat4 expression partial next163 current next eof' => static fn (TestRunner $t) => $t->same(null, $plan163()['cursorTape']['matchedCurrentNext'][1]['next']),
    'stat4 expression partial next163 program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan163()['cursorTape']['program'][0]['opcode']),
    'stat4 expression partial next163 program seek' => static fn (TestRunner $t) => $t->same('SeekGE', $plan163()['cursorTape']['program'][1]['opcode']),
    'stat4 expression partial next163 program stop' => static fn (TestRunner $t) => $t->same('IdxGT', $plan163()['cursorTape']['program'][2]['opcode']),
    'stat4 expression partial next163 program column' => static fn (TestRunner $t) => $t->same('option_name', $plan163()['cursorTape']['program'][3]['column']),
    'stat4 expression partial next163 program next' => static fn (TestRunner $t) => $t->same('Next', $plan163()['cursorTape']['program'][4]['opcode']),
    'stat4 expression partial next163 table lookup deferred' => static fn (TestRunner $t) => $t->same(true, $plan163()['tableLookupDeferred']),
    'stat4 expression partial next163 residual predicate required' => static fn (TestRunner $t) => $t->same(true, $plan163()['residualPredicateRequired']),
    'stat4 expression partial next163 fence cookie' => static fn (TestRunner $t) => $t->same(1634, $plan163()['currentSourceFence']['schemaCookie']),
    'stat4 expression partial next163 fence stat4' => static fn (TestRunner $t) => $t->same(11, $plan163()['currentSourceFence']['stat4Generation']),
    'stat4 expression partial next163 fence query signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan163()['currentSourceFence']['querySignature'])),
    'stat4 expression partial next163 fence sample signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan163()['currentSourceFence']['sampleSignature'])),
    'stat4 expression partial next163 detail names reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT163', $plan163()['detail']),
    'stat4 expression partial next163 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next163', $plan163()['dependencies'], true)),
    'stat4 expression partial next163 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan163()['dependency_closure']),
    'stat4 expression partial next163 non overlap' => static fn (TestRunner $t) => $t->contains('equality+range STAT4 samples', $plan163()['non_overlap']),
    'stat4 expression partial next163 fresh source reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh163()['selectedSource']),
    'stat4 expression partial next163 fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $fresh163()['reprepareRequired']),
    'stat4 expression partial next163 fresh rowids' => static fn (TestRunner $t) => $t->same([1, 2], $fresh163()['selectedPlan']['matchedRowids']),
    'stat4 expression partial next163 open seek gt' => static fn (TestRunner $t) => $t->same('SeekGT', $openRange163()['cursorTape']['seekOpcode']),
    'stat4 expression partial next163 open stop ge' => static fn (TestRunner $t) => $t->same('IdxGE', $openRange163()['cursorTape']['stopOpcode']),
    'stat4 expression partial next163 open excludes boundary samples' => static fn (TestRunner $t) => $t->same([22, 23], $openRange163()['selectedPlan']['matchedRowids']),
    'stat4 expression partial next163 missing partial falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingPartial163()['status']),
    'stat4 expression partial next163 missing partial no table deferral' => static fn (TestRunner $t) => $t->same(false, $missingPartial163()['tableLookupDeferred']),
    'stat4 expression partial next163 sparse partial can win' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_autoload_updated_next163_sparse', $sparse163()['selectedPlan']['name']),
    'stat4 expression partial next163 sparse rowid' => static fn (TestRunner $t) => $t->same([31], $sparse163()['selectedPlan']['matchedRowids']),
    'stat4 expression partial next163 validates source indexes' => static function (TestRunner $t) use ($prepared163, $current163, $query163): void {
        $bad = $current163();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materializeNext163($prepared163(), $bad, $query163()));
    },
    'stat4 expression partial next163 validates stat4 sample shape' => static function (TestRunner $t) use ($prepared163, $current163, $query163): void {
        $bad = $current163();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['siteurl'];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materializeNext163($prepared163(), $bad, $query163()));
    },
    'stat4 expression partial next163 validates stat4 neq' => static function (TestRunner $t) use ($prepared163, $current163, $query163): void {
        $bad = $current163();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = '0 0 0';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materializeNext163($prepared163(), $bad, $query163()));
    },
    'stat4 expression partial next163 validates schema cookie' => static function (TestRunner $t) use ($prepared163, $current163, $query163): void {
        $bad = $prepared163(['schemaCookie' => -1]);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionPartialCurrentSourceNextPlan::materializeNext163($bad, $current163(), $query163()));
    },
];
