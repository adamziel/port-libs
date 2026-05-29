<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteExpressionIndexPartialCurrentSourceNextPlan;

$eq = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$exprEq = static fn (string $expression, mixed $value): array => ['operator' => '=', 'left' => ['expression' => $expression], 'right' => $value];
$exprIn = static fn (string $expression, array $values): array => ['operator' => 'IN', 'left' => ['expression' => $expression], 'values' => $values];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$preparedSource = static fn (array $overrides = []): array => $overrides + [
    'name' => 'prepared-wp-options-next121',
    'schemaCookie' => 1210,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_active_or_network_next121_old',
        'rootPage' => 12101,
        'sql' => "CREATE INDEX idx_wp_options_lower_active_or_network_next121_old ON wp_options(lower(option_name) COLLATE NOCASE, option_id) WHERE autoload = 'yes' OR blog_id = 0",
    ], [
        'name' => 'idx_wp_options_lower_not_partial_next121',
        'rootPage' => 12102,
        'sql' => "CREATE INDEX idx_wp_options_lower_not_partial_next121 ON wp_options(lower(option_name) COLLATE NOCASE)",
    ]],
    'rows' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => 'https://old.example.test'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'Home', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => 'https://old.example.test'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'Network_Plugins', 'autoload' => 'no', 'blog_id' => 0, 'option_value' => 'a:0:{}'],
    ],
];

$currentSource = static fn (): array => [
    'name' => 'current-wp-options-next121',
    'schemaCookie' => 1211,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_active_or_network_next121_current',
        'rootPage' => 12111,
        'sql' => "CREATE INDEX idx_wp_options_lower_active_or_network_next121_current ON wp_options(lower(option_name) COLLATE NOCASE, option_id) WHERE autoload = 'yes' OR blog_id = 0",
    ], [
        'name' => 'idx_wp_options_lower_inactive_next121',
        'rootPage' => 12112,
        'sql' => "CREATE INDEX idx_wp_options_lower_inactive_next121 ON wp_options(lower(option_name) COLLATE NOCASE) WHERE autoload = 'no'",
    ]],
    'rows' => [
        ['rowid' => 10, 'option_id' => 10, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => 'https://example.test'],
        ['rowid' => 11, 'option_id' => 11, 'option_name' => 'Home', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => 'https://example.test'],
        ['rowid' => 12, 'option_id' => 12, 'option_name' => 'BlogName', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => 'Port Libs'],
        ['rowid' => 13, 'option_id' => 13, 'option_name' => 'Network_Plugins', 'autoload' => 'no', 'blog_id' => 0, 'option_value' => 'a:1:{s:12:"hello.php";b:1;}'],
        ['rowid' => 14, 'option_id' => 14, 'option_name' => 'Transient_Feed', 'autoload' => 'no', 'blog_id' => 1, 'option_value' => 'cached'],
        ['rowid' => 15, 'option_id' => 15, 'option_name' => 'siteurl', 'autoload' => 'yes', 'blog_id' => 2, 'option_value' => 'https://two.example.test'],
    ],
];

$predicate = $and($eq('autoload', 'yes'), $exprIn('lower(option_name)', ['siteurl', 'home']));
$orderBy = [['expression' => 'lower(option_name)', 'direction' => 'ASC', 'collation' => 'NOCASE']];
$plan = static fn (?array $predicateOverride = null, ?array $prepared = null, ?array $current = null, ?array $order = null): array => SQLiteExpressionIndexPartialCurrentSourceNextPlan::materialize(
    $prepared ?? $preparedSource(),
    $current ?? $currentSource(),
    $predicateOverride ?? $GLOBALS['predicate_next121'],
    $order ?? $GLOBALS['order_next121'],
);
$GLOBALS['predicate_next121'] = $predicate;
$GLOBALS['order_next121'] = $orderBy;

$tests = [];

$tests['planner expression index partial current source next121 status ready'] = static fn (TestRunner $t) => $t->same('expression-index-partial-current-source-ready', $plan()['status']);
$tests['planner expression index partial current source next121 selects current'] = static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']);
$tests['planner expression index partial current source next121 stale prepared'] = static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']);
$tests['planner expression index partial current source next121 reprepare required'] = static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']);
$tests['planner expression index partial current source next121 schema changed'] = static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']);
$tests['planner expression index partial current source next121 signature changed'] = static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']);
$tests['planner expression index partial current source next121 prepared ready'] = static fn (TestRunner $t) => $t->same(true, $plan()['preparedSource']['ready']);
$tests['planner expression index partial current source next121 current ready'] = static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['ready']);
$tests['planner expression index partial current source next121 prepared row count'] = static fn (TestRunner $t) => $t->same(2, $plan()['preparedSource']['matchedRowCount']);
$tests['planner expression index partial current source next121 current row count'] = static fn (TestRunner $t) => $t->same(3, $plan()['currentSource']['matchedRowCount']);
$tests['planner expression index partial current source next121 selected index'] = static fn (TestRunner $t) => $t->same('idx_wp_options_lower_active_or_network_next121_current', $plan()['selectedPlan']['name']);
$tests['planner expression index partial current source next121 selected root'] = static fn (TestRunner $t) => $t->same(12111, $plan()['selectedPlan']['rootPage']);
$tests['planner expression index partial current source next121 expression'] = static fn (TestRunner $t) => $t->same('lower(option_name)', $plan()['selectedPlan']['expression']);
$tests['planner expression index partial current source next121 expression column'] = static fn (TestRunner $t) => $t->same('option_name', $plan()['selectedPlan']['expressionColumn']);
$tests['planner expression index partial current source next121 collation'] = static fn (TestRunner $t) => $t->same('NOCASE', $plan()['selectedPlan']['collation']);
$tests['planner expression index partial current source next121 partial true'] = static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partial']);
$tests['planner expression index partial current source next121 partial implied'] = static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partialPredicateImplied']);
$tests['planner expression index partial current source next121 partial operator or'] = static fn (TestRunner $t) => $t->same('OR', $plan()['selectedPlan']['partialPredicate']['operator']);
$tests['planner expression index partial current source next121 constraint operator'] = static fn (TestRunner $t) => $t->same('IN', $plan()['selectedPlan']['constraintOperator']);
$tests['planner expression index partial current source next121 constraint values'] = static fn (TestRunner $t) => $t->same(['siteurl', 'home'], $plan()['selectedPlan']['constraintValues']);
$tests['planner expression index partial current source next121 materialized rows'] = static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['currentRowsMaterialized']);
$tests['planner expression index partial current source next121 matched count'] = static fn (TestRunner $t) => $t->same(3, $plan()['selectedPlan']['matchedRowCount']);
$tests['planner expression index partial current source next121 matched rowids'] = static fn (TestRunner $t) => $t->same([11, 10, 15], $plan()['selectedPlan']['matchedRowids']);
$tests['planner expression index partial current source next121 expression keys'] = static fn (TestRunner $t) => $t->same(['home', 'siteurl', 'siteurl'], $plan()['selectedPlan']['expressionKeys']);
$tests['planner expression index partial current source next121 estimated rows'] = static fn (TestRunner $t) => $t->same(3, $plan()['selectedPlan']['estimatedRows']);
$tests['planner expression index partial current source next121 estimated cost'] = static fn (TestRunner $t) => $t->same(3, $plan()['selectedPlan']['estimatedCost']);
$tests['planner expression index partial current source next121 order satisfied'] = static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['orderBySatisfied']);
$tests['planner expression index partial current source next121 table lookup deferred'] = static fn (TestRunner $t) => $t->same(true, $plan()['tableLookupDeferred']);
$tests['planner expression index partial current source next121 sorter elided'] = static fn (TestRunner $t) => $t->same(true, $plan()['tempSortElided']);
$tests['planner expression index partial current source next121 cursor source'] = static fn (TestRunner $t) => $t->same('current', $plan()['cursorTape']['source']);
$tests['planner expression index partial current source next121 cursor index'] = static fn (TestRunner $t) => $t->same('idx_wp_options_lower_active_or_network_next121_current', $plan()['cursorTape']['indexName']);
$tests['planner expression index partial current source next121 cursor rowids'] = static fn (TestRunner $t) => $t->same([11, 10, 15], $plan()['cursorTape']['rowids']);
$tests['planner expression index partial current source next121 cursor keys'] = static fn (TestRunner $t) => $t->same(['home', 'siteurl', 'siteurl'], $plan()['cursorTape']['expressionKeys']);
$tests['planner expression index partial current source next121 cursor scan'] = static fn (TestRunner $t) => $t->same('ascending', $plan()['cursorTape']['scanDirection']);
$tests['planner expression index partial current source next121 cursor order signature'] = static fn (TestRunner $t) => $t->same('lower(option_name) ASC COLLATE NOCASE', $plan()['cursorTape']['orderSignature']);
$tests['planner expression index partial current source next121 program open current'] = static fn (TestRunner $t) => $t->same(['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => 12111, 'source' => 'current'], $plan()['cursorTape']['program'][0]);
$tests['planner expression index partial current source next121 program rechecks partial'] = static fn (TestRunner $t) => $t->same(['opcode' => 'RecheckPartialPredicate', 'implied' => true], $plan()['cursorTape']['program'][1]);
$tests['planner expression index partial current source next121 program expression column'] = static fn (TestRunner $t) => $t->same('ExpressionColumn', $plan()['cursorTape']['program'][2]['opcode']);
$tests['planner expression index partial current source next121 program advances'] = static fn (TestRunner $t) => $t->same('Next', $plan()['cursorTape']['program'][3]['opcode']);
$tests['planner expression index partial current source next121 first current row home'] = static fn (TestRunner $t) => $t->same('Home', $plan()['selectedPlan']['currentNextRows'][0]['current']['covering']['option_name']);
$tests['planner expression index partial current source next121 first next row siteurl'] = static fn (TestRunner $t) => $t->same('SiteURL', $plan()['selectedPlan']['currentNextRows'][0]['next']['covering']['option_name']);
$tests['planner expression index partial current source next121 last next null'] = static fn (TestRunner $t) => $t->same(null, $plan()['selectedPlan']['currentNextRows'][2]['next']);
$tests['planner expression index partial current source next121 fence cookie'] = static fn (TestRunner $t) => $t->same(1211, $plan()['currentSourceFence']['schemaCookie']);
$tests['planner expression index partial current source next121 fence order'] = static fn (TestRunner $t) => $t->same('lower(option_name) ASC COLLATE NOCASE', $plan()['currentSourceFence']['orderSignature']);
$tests['planner expression index partial current source next121 detail'] = static fn (TestRunner $t) => $t->contains('REPREPARE PARTIAL EXPRESSION INDEX CURRENT SOURCE', $plan()['detail']);
$tests['planner expression index partial current source next121 dependency closure'] = static fn (TestRunner $t) => $t->contains('no new support component needed', $plan()['dependency_closure']);
$tests['planner expression index partial current source next121 non overlap'] = static fn (TestRunner $t) => $t->contains('OR/AND partial predicate proof', $plan()['non_overlap']);

$freshPlan = static fn (): array => $plan(null, $preparedSource(), $preparedSource());
$tests['planner expression index partial current source next121 reuses prepared source'] = static fn (TestRunner $t) => $t->same('prepared', $freshPlan()['selectedSource']);
$tests['planner expression index partial current source next121 no reprepare when fresh'] = static fn (TestRunner $t) => $t->same(false, $freshPlan()['reprepareRequired']);
$tests['planner expression index partial current source next121 fresh rowids'] = static fn (TestRunner $t) => $t->same([2, 1], $freshPlan()['selectedPlan']['matchedRowids']);

$pointPlan = static fn (): array => $plan($and($eq('autoload', 'yes'), $exprEq('lower(option_name)', 'siteurl')));
$tests['planner expression index partial current source next121 point narrows rows'] = static fn (TestRunner $t) => $t->same([10, 15], $pointPlan()['selectedPlan']['matchedRowids']);
$tests['planner expression index partial current source next121 point operator'] = static fn (TestRunner $t) => $t->same('=', $pointPlan()['selectedPlan']['constraintOperator']);

$networkPlan = static fn (): array => $plan($and($eq('blog_id', 0), $exprEq('lower(option_name)', 'network_plugins')));
$tests['planner expression index partial current source next121 or arm network implied'] = static fn (TestRunner $t) => $t->same('expression-index-partial-current-source-ready', $networkPlan()['status']);
$tests['planner expression index partial current source next121 network rowid'] = static fn (TestRunner $t) => $t->same([13], $networkPlan()['selectedPlan']['matchedRowids']);

$missingPartialPlan = static fn (): array => $plan($exprEq('lower(option_name)', 'siteurl'));
$tests['planner expression index partial current source next121 missing partial falls back'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $missingPartialPlan()['status']);
$tests['planner expression index partial current source next121 missing partial no cursor'] = static fn (TestRunner $t) => $t->same(null, $missingPartialPlan()['cursorTape']['indexName']);

$wrongOrder = static fn (): array => $plan(null, null, null, [['expression' => 'lower(option_name)', 'direction' => 'DESC', 'collation' => 'NOCASE']]);
$tests['planner expression index partial current source next121 wrong order still materializes'] = static fn (TestRunner $t) => $t->same('expression-index-partial-current-source-ready', $wrongOrder()['status']);
$tests['planner expression index partial current source next121 wrong order opens sorter'] = static fn (TestRunner $t) => $t->same('SorterOpen', $wrongOrder()['cursorTape']['program'][3]['opcode']);
$tests['planner expression index partial current source next121 wrong order cost includes sort'] = static fn (TestRunner $t) => $t->same(23, $wrongOrder()['selectedPlan']['estimatedCost']);

$tests['planner expression index partial current source next121 validates schema cookie'] = static function (TestRunner $t) use ($preparedSource, $currentSource): void {
    $bad = $preparedSource(['schemaCookie' => -1]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionIndexPartialCurrentSourceNextPlan::materialize($bad, $currentSource(), $GLOBALS['predicate_next121'], $GLOBALS['order_next121']));
};
$tests['planner expression index partial current source next121 validates row list'] = static function (TestRunner $t) use ($preparedSource, $currentSource): void {
    $bad = $currentSource();
    $bad['rows'][] = 'bad-row';
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionIndexPartialCurrentSourceNextPlan::materialize($preparedSource(), $bad, $GLOBALS['predicate_next121'], $GLOBALS['order_next121']));
};
$tests['planner expression index partial current source next121 validates index list'] = static function (TestRunner $t) use ($preparedSource, $currentSource): void {
    $bad = $currentSource();
    $bad['indexes'][] = 'bad-index';
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionIndexPartialCurrentSourceNextPlan::materialize($preparedSource(), $bad, $GLOBALS['predicate_next121'], $GLOBALS['order_next121']));
};
$tests['planner expression index partial current source next121 validates order direction'] = static function (TestRunner $t) use ($preparedSource, $currentSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionIndexPartialCurrentSourceNextPlan::materialize($preparedSource(), $currentSource(), $GLOBALS['predicate_next121'], [['expression' => 'lower(option_name)', 'direction' => 'SIDEWAYS']]));
};

return $tests;
