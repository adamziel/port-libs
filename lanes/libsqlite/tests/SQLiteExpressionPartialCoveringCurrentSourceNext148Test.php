<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteExpressionPartialCoveringCurrentSourceNextPlan;

$term148 = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$expr148 = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '=', 'right' => $right];

$rows148 = static fn (): array => [
    ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'SiteURL', 'option_value' => 'https://example.test', 'updated_at' => 10],
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'warm', 'updated_at' => 40],
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh', 'updated_at' => 50],
    ['rowid' => 4, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network', 'updated_at' => 60],
    ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 6, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 80],
];

$source148 = static function (array $overrides = []) use ($rows148): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-expression-partial-covering-next148',
        'schemaCookie' => 1480,
        'stat4Generation' => 11,
        'rows' => array_slice($rows148(), 0, 4),
        'indexes' => [[
            'name' => 'idx_wp_options_lower_name_autoload_cover_next148',
            'rootPage' => 14801,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ],
            'coveringColumns' => ['blog_id', 'option_name', 'option_value', 'updated_at', 'rowid'],
            'estimatedRows' => 24,
            'stat4Samples' => [
                ['sample' => ['plugin_cache', 2]],
                ['sample' => ['siteurl', 1]],
            ],
        ], [
            'name' => 'idx_wp_options_option_name_plain_next148',
            'rootPage' => 14802,
            'expression' => 'option_name',
            'coveringColumns' => ['option_name'],
            'estimatedRows' => 2000,
        ]],
    ], $overrides);
};

$current148 = static function (array $overrides = []) use ($rows148): array {
    return array_replace_recursive([
        'name' => 'current-wp-options-expression-partial-covering-next148',
        'schemaCookie' => 1483,
        'stat4Generation' => 14,
        'rows' => $rows148(),
        'indexes' => [[
            'name' => 'idx_wp_options_lower_name_autoload_cover_next148',
            'rootPage' => 14831,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ],
            'coveringColumns' => ['blog_id', 'option_name', 'option_value', 'updated_at', 'rowid'],
            'estimatedRows' => 12,
            'stat4Samples' => [
                ['sample' => ['plugin_cache', 2]],
                ['sample' => ['plugin_cache', 3]],
                ['sample' => ['plugin_forms', 6]],
            ],
        ], [
            'name' => 'idx_wp_options_option_name_plain_next148',
            'rootPage' => 14802,
            'expression' => 'option_name',
            'coveringColumns' => ['option_name'],
            'estimatedRows' => 2000,
        ]],
    ], $overrides);
};

$query148 = static fn (): array => [
    $expr148('LOWER( option_name )', 'plugin_cache'),
    $term148('blog_id', '=', 1),
    $term148('autoload', '=', 'yes'),
    $term148('option_name', 'IS NOT NULL'),
];
$needed148 = ['option_name', 'option_value', 'updated_at', 'rowid'];
$plan148 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLiteExpressionPartialCoveringCurrentSourceNextPlan::materialize(
    $prepared ?? $source148(),
    $current ?? $current148(),
    $terms ?? $query148(),
    $needed ?? $needed148,
);
$fresh148 = static function () use ($source148, $plan148): array {
    $source = $source148();

    return $plan148($source, $source);
};
$uncovered148 = static function () use ($current148, $plan148): array {
    $current = $current148();
    $current['indexes'] = [[
        'name' => 'idx_wp_options_lower_name_uncovered_next148',
        'rootPage' => 14841,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name'],
        'estimatedRows' => 9,
    ]];

    return $plan148(null, $current);
};
$unproved148 = static function () use ($current148, $plan148, $expr148, $term148): array {
    return $plan148(null, $current148(), [
        $expr148('lower(option_name)', 'plugin_cache'),
        $term148('blog_id', '=', 1),
        $term148('autoload', '=', 'no'),
        $term148('option_name', 'IS NOT NULL'),
    ]);
};
$noExpression148 = static function () use ($current148, $plan148, $term148): array {
    return $plan148(null, $current148(), [
        $term148('autoload', '=', 'yes'),
        $term148('option_name', 'IS NOT NULL'),
    ]);
};

return [
    'expression partial covering next148 status ready' => static fn (TestRunner $t) => $t->same('expression-partial-covering-current-source-next148-ready', $plan148()['status']),
    'expression partial covering next148 selects current source' => static fn (TestRunner $t) => $t->same('current', $plan148()['selectedSource']),
    'expression partial covering next148 marks stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan148()['stalePreparedStatement']),
    'expression partial covering next148 reparses stale plan' => static fn (TestRunner $t) => $t->same(true, $plan148()['reprepareRequired']),
    'expression partial covering next148 schema cookie changed' => static fn (TestRunner $t) => $t->same(true, $plan148()['schemaCookieChanged']),
    'expression partial covering next148 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan148()['stat4GenerationChanged']),
    'expression partial covering next148 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan148()['indexSignatureChanged']),
    'expression partial covering next148 selected index name' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_autoload_cover_next148', $plan148()['selectedPlan']['name']),
    'expression partial covering next148 selected root page' => static fn (TestRunner $t) => $t->same(14831, $plan148()['selectedPlan']['rootPage']),
    'expression partial covering next148 expression matched' => static fn (TestRunner $t) => $t->same(true, $plan148()['selectedPlan']['expressionMatched']),
    'expression partial covering next148 normalized expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan148()['currentSourceFence']['expressionSignature']),
    'expression partial covering next148 expression operator equals' => static fn (TestRunner $t) => $t->same('=', $plan148()['selectedPlan']['expressionOperator']),
    'expression partial covering next148 expression value' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan148()['selectedPlan']['expressionValue']),
    'expression partial covering next148 partial predicate implied' => static fn (TestRunner $t) => $t->same(true, $plan148()['selectedPlan']['partialPredicateImplied']),
    'expression partial covering next148 covering true' => static fn (TestRunner $t) => $t->same(true, $plan148()['selectedPlan']['covering']),
    'expression partial covering next148 no skip scan' => static fn (TestRunner $t) => $t->same(false, $plan148()['selectedPlan']['usesSkipScan']),
    'expression partial covering next148 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan148()['tableLookupElided']),
    'expression partial covering next148 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan148()['deferredSeekOpcode']),
    'expression partial covering next148 row count' => static fn (TestRunner $t) => $t->same(2, $plan148()['selectedPlan']['coveredRowCount']),
    'expression partial covering next148 rowids' => static fn (TestRunner $t) => $t->same([2, 3], $plan148()['selectedPlan']['coveredRowids']),
    'expression partial covering next148 covered rows rowids' => static fn (TestRunner $t) => $t->same([2, 3], array_column($plan148()['coveredRows'], 'rowid')),
    'expression partial covering next148 expression keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache'], array_column($plan148()['coveredRows'], 'expressionKey')),
    'expression partial covering next148 first covering option name' => static fn (TestRunner $t) => $t->same('Plugin_Cache', $plan148()['coveredRows'][0]['covering']['option_name']),
    'expression partial covering next148 second covering value' => static fn (TestRunner $t) => $t->same('fresh', $plan148()['coveredRows'][1]['covering']['option_value']),
    'expression partial covering next148 excludes blog two row' => static fn (TestRunner $t) => $t->same(false, in_array(4, array_column($plan148()['coveredRows'], 'rowid'), true)),
    'expression partial covering next148 excludes partial miss autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(5, array_column($plan148()['coveredRows'], 'rowid'), true)),
    'expression partial covering next148 excludes different expression key' => static fn (TestRunner $t) => $t->same(false, in_array(6, array_column($plan148()['coveredRows'], 'rowid'), true)),
    'expression partial covering next148 current next second row' => static fn (TestRunner $t) => $t->same(3, $plan148()['currentNextRows'][0]['next']['rowid']),
    'expression partial covering next148 current next eof' => static fn (TestRunner $t) => $t->same(null, $plan148()['currentNextRows'][1]['next']),
    'expression partial covering next148 payload columns' => static fn (TestRunner $t) => $t->same($GLOBALS['needed148'], $plan148()['coveringPayloadColumns']),
    'expression partial covering next148 cursor open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan148()['cursorProgram'][0]['opcode']),
    'expression partial covering next148 cursor seek expression' => static fn (TestRunner $t) => $t->same('SeekExpression', $plan148()['cursorProgram'][1]['opcode']),
    'expression partial covering next148 cursor key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan148()['cursorProgram'][1]['key']),
    'expression partial covering next148 cursor reads covering source' => static fn (TestRunner $t) => $t->same(['covering-index', 'covering-index', 'covering-index', 'covering-index'], array_column(array_slice($plan148()['cursorProgram'], 2, 4), 'source')),
    'expression partial covering next148 cursor result row count' => static fn (TestRunner $t) => $t->same(2, $plan148()['cursorProgram'][6]['rowCount']),
    'expression partial covering next148 cursor advances' => static fn (TestRunner $t) => $t->same('Next', $plan148()['cursorProgram'][7]['opcode']),
    'expression partial covering next148 fence cookie' => static fn (TestRunner $t) => $t->same(1483, $plan148()['currentSourceFence']['schemaCookie']),
    'expression partial covering next148 fence stat4' => static fn (TestRunner $t) => $t->same(14, $plan148()['currentSourceFence']['stat4Generation']),
    'expression partial covering next148 fence covering signature' => static fn (TestRunner $t) => $t->same('option_name,option_value,updated_at,rowid', $plan148()['currentSourceFence']['coveringSignature']),
    'expression partial covering next148 fence row stream signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan148()['currentSourceFence']['rowStreamSignature'])),
    'expression partial covering next148 selected current summary usable' => static fn (TestRunner $t) => $t->same(true, $plan148()['currentSource']['usable']),
    'expression partial covering next148 prepared summary root' => static fn (TestRunner $t) => $t->same(14801, $plan148()['preparedSource']['rootPage']),
    'expression partial covering next148 detail without table seek' => static fn (TestRunner $t) => $t->contains('WITHOUT TABLE SEEK', $plan148()['detail']),
    'expression partial covering next148 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-expression-partial-covering-current-source-next148', $plan148()['dependencies'], true)),
    'expression partial covering next148 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan148()['dependency_closure']),
    'expression partial covering next148 non overlap' => static fn (TestRunner $t) => $t->contains('non-skip-scan expression partial covering', $plan148()['non_overlap']),
    'expression partial covering next148 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh148()['selectedSource']),
    'expression partial covering next148 fresh rowids' => static fn (TestRunner $t) => $t->same([2, 3], array_column($fresh148()['coveredRows'], 'rowid')),
    'expression partial covering next148 uncovered falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $uncovered148()['status']),
    'expression partial covering next148 uncovered deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $uncovered148()['deferredSeekOpcode']),
    'expression partial covering next148 unproved falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved148()['status']),
    'expression partial covering next148 no expression term falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noExpression148()['status']),
    'expression partial covering next148 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan148(null, null, [])),
    'expression partial covering next148 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan148(null, null, null, [])),
    'expression partial covering next148 invalid source rows' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan148(null, $current148(['rows' => ['bad' => []]]))),
];

$GLOBALS['needed148'] = $needed148;
