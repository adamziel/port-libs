<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4RangeOrderCurrentSourceNextPlan;

$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$preparedSource = static fn (): array => [
    'name' => 'prepared-wp-options',
    'schemaCookie' => 41,
    'stat4Generation' => 7,
    'indexes' => [[
        'name' => 'idx_wp_options_name_autoload_stat4_old',
        'rootPage' => 210,
        'estimatedRows' => 90,
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
            ['neq' => '6 6', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['autoloaded_widget', 'yes']],
            ['neq' => '2 2', 'nlt' => '7 7', 'ndlt' => '2 2', 'sample' => ['home', 'yes']],
            ['neq' => '1 1', 'nlt' => '9 9', 'ndlt' => '3 3', 'sample' => ['siteurl', 'yes']],
            ['neq' => '13 13', 'nlt' => '10 10', 'ndlt' => '4 4', 'sample' => ['transient_feed', 'no']],
            ['neq' => '4 4', 'nlt' => '23 23', 'ndlt' => '5 5', 'sample' => ['widget_recent', 'yes']],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_name_autoload_stat4_old ON wp_options(option_name, autoload, option_value)',
    ]],
];

$currentSource = static fn (): array => [
    'name' => 'current-wp-options',
    'schemaCookie' => 42,
    'stat4Generation' => 8,
    'indexes' => [[
        'name' => 'idx_wp_options_name_autoload_stat4',
        'rootPage' => 212,
        'estimatedRows' => 64,
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
            ['neq' => '8 8', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['autoloaded_widget', 'yes']],
            ['neq' => '2 2', 'nlt' => '9 9', 'ndlt' => '2 2', 'sample' => ['home', 'yes']],
            ['neq' => '1 1', 'nlt' => '11 11', 'ndlt' => '3 3', 'sample' => ['siteurl', 'yes']],
            ['neq' => '24 24', 'nlt' => '12 12', 'ndlt' => '4 4', 'sample' => ['transient_feed', 'no']],
            ['neq' => '4 4', 'nlt' => '36 36', 'ndlt' => '5 5', 'sample' => ['widget_recent', 'yes']],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_name_autoload_stat4 ON wp_options(option_name, autoload, option_value)',
    ]],
];

$plan = static function (?array $predicate = null, array $orderBy = [['column' => 'option_name']], array $needed = ['option_name', 'autoload', 'option_value'], ?array $prepared = null, ?array $current = null) use ($preparedSource, $currentSource, $and, $range): array {
    return SQLiteStat4RangeOrderCurrentSourceNextPlan::materializeRangeOrderCursorTape(
        $prepared ?? $preparedSource(),
        $current ?? $currentSource(),
        $predicate ?? $and($range('option_name', '>=', 'home'), $range('option_name', '<', 'transient_timeout')),
        $orderBy,
        $needed,
    );
};

$tests = [];

$tests['planner range order stat4 current source next102 selects current source'] = static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']);
$tests['planner range order stat4 current source next102 marks stale prepared'] = static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']);
$tests['planner range order stat4 current source next102 requires reprepare'] = static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']);
$tests['planner range order stat4 current source next102 detects schema cookie'] = static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']);
$tests['planner range order stat4 current source next102 detects stat4 generation'] = static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']);
$tests['planner range order stat4 current source next102 detects index signature'] = static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']);
$tests['planner range order stat4 current source next102 status ready'] = static fn (TestRunner $t) => $t->same('range-order-current-source-ready', $plan()['status']);
$tests['planner range order stat4 current source next102 order signature'] = static fn (TestRunner $t) => $t->same('option_name ASC', $plan()['orderSignature']);
$tests['planner range order stat4 current source next102 selected root page'] = static fn (TestRunner $t) => $t->same(212, $plan()['cursorTape']['rootPage']);
$tests['planner range order stat4 current source next102 selected index'] = static fn (TestRunner $t) => $t->same('idx_wp_options_name_autoload_stat4', $plan()['cursorTape']['indexName']);
$tests['planner range order stat4 current source next102 selected range column'] = static fn (TestRunner $t) => $t->same('option_name', $plan()['cursorTape']['rangeColumn']);
$tests['planner range order stat4 current source next102 uses seek ge for inclusive lower'] = static fn (TestRunner $t) => $t->same('SeekGE', $plan()['cursorTape']['seekOpcode']);
$tests['planner range order stat4 current source next102 uses idxge for exclusive upper'] = static fn (TestRunner $t) => $t->same('IdxGE', $plan()['cursorTape']['stopOpcode']);
$tests['planner range order stat4 current source next102 uses next opcode'] = static fn (TestRunner $t) => $t->same('Next', $plan()['cursorTape']['nextOpcode']);
$tests['planner range order stat4 current source next102 scan direction ascending'] = static fn (TestRunner $t) => $t->same('ascending', $plan()['cursorTape']['scanDirection']);
$tests['planner range order stat4 current source next102 lower value'] = static fn (TestRunner $t) => $t->same('home', $plan()['cursorTape']['lowerValue']);
$tests['planner range order stat4 current source next102 upper value'] = static fn (TestRunner $t) => $t->same('transient_timeout', $plan()['cursorTape']['upperValue']);
$tests['planner range order stat4 current source next102 lower inclusive'] = static fn (TestRunner $t) => $t->same(true, $plan()['cursorTape']['lowerInclusive']);
$tests['planner range order stat4 current source next102 upper exclusive'] = static fn (TestRunner $t) => $t->same(false, $plan()['cursorTape']['upperInclusive']);
$tests['planner range order stat4 current source next102 lower stat4 current'] = static fn (TestRunner $t) => $t->same('home', $plan()['cursorTape']['stat4LowerCurrent']);
$tests['planner range order stat4 current source next102 lower stat4 next'] = static fn (TestRunner $t) => $t->same('siteurl', $plan()['cursorTape']['stat4LowerNext']);
$tests['planner range order stat4 current source next102 upper stat4 current'] = static fn (TestRunner $t) => $t->same('transient_feed', $plan()['cursorTape']['stat4UpperCurrent']);
$tests['planner range order stat4 current source next102 upper stat4 next'] = static fn (TestRunner $t) => $t->same('widget_recent', $plan()['cursorTape']['stat4UpperNext']);
$tests['planner range order stat4 current source next102 lower exact'] = static fn (TestRunner $t) => $t->same(true, $plan()['cursorTape']['stat4LowerExact']);
$tests['planner range order stat4 current source next102 upper gap is not exact'] = static fn (TestRunner $t) => $t->same(false, $plan()['cursorTape']['stat4UpperExact']);
$tests['planner range order stat4 current source next102 not empty gap'] = static fn (TestRunner $t) => $t->same(false, $plan()['cursorTape']['stat4EmptyGap']);
$tests['planner range order stat4 current source next102 matched samples'] = static fn (TestRunner $t) => $t->same(3, $plan()['cursorTape']['stat4MatchedSamples']);
$tests['planner range order stat4 current source next102 covering'] = static fn (TestRunner $t) => $t->same(true, $plan()['cursorTape']['covering']);
$tests['planner range order stat4 current source next102 elides deferred seek'] = static fn (TestRunner $t) => $t->same(null, $plan()['cursorTape']['deferredSeekOpcode']);
$tests['planner range order stat4 current source next102 elides sorter'] = static fn (TestRunner $t) => $t->same(false, $plan()['cursorTape']['sorterOpen']);
$tests['planner range order stat4 current source next102 program opens current root'] = static fn (TestRunner $t) => $t->same(['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => 212], $plan()['cursorTape']['program'][0]);
$tests['planner range order stat4 current source next102 program seeks lower'] = static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'column' => 'option_name', 'value' => 'home'], $plan()['cursorTape']['program'][1]);
$tests['planner range order stat4 current source next102 program stops upper'] = static fn (TestRunner $t) => $t->same(['opcode' => 'IdxGE', 'column' => 'option_name', 'value' => 'transient_timeout'], $plan()['cursorTape']['program'][2]);
$tests['planner range order stat4 current source next102 program emits name column'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'option_name'], $plan()['cursorTape']['program'][3]);
$tests['planner range order stat4 current source next102 program emits autoload column'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'autoload'], $plan()['cursorTape']['program'][4]);
$tests['planner range order stat4 current source next102 program emits option value column'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'option_value'], $plan()['cursorTape']['program'][5]);
$tests['planner range order stat4 current source next102 program advances'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Next', 'target' => 'index'], $plan()['cursorTape']['program'][6]);
$tests['planner range order stat4 current source next102 current fence root signature present'] = static fn (TestRunner $t) => $t->same(42, $plan()['currentSourceFence']['schemaCookie']);
$tests['planner range order stat4 current source next102 current fence stat4 generation'] = static fn (TestRunner $t) => $t->same(8, $plan()['currentSourceFence']['stat4Generation']);
$tests['planner range order stat4 current source next102 detail records reprepare'] = static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 RANGE ORDER CURRENT SOURCE', $plan()['detail']);

$tests['planner range order stat4 current source next102 desc uses current source'] = static fn (TestRunner $t) => $t->same('current', $plan(null, [['column' => 'option_name', 'direction' => 'DESC']])['cursorTape']['source']);
$tests['planner range order stat4 current source next102 desc seek upper inclusive lt'] = static fn (TestRunner $t) => $t->same('SeekLT', $plan(null, [['column' => 'option_name', 'direction' => 'DESC']])['cursorTape']['seekOpcode']);
$tests['planner range order stat4 current source next102 desc stop lower inclusive lt'] = static fn (TestRunner $t) => $t->same('IdxLT', $plan(null, [['column' => 'option_name', 'direction' => 'DESC']])['cursorTape']['stopOpcode']);
$tests['planner range order stat4 current source next102 desc next opcode prev'] = static fn (TestRunner $t) => $t->same('Prev', $plan(null, [['column' => 'option_name', 'direction' => 'DESC']])['cursorTape']['nextOpcode']);
$tests['planner range order stat4 current source next102 desc direction'] = static fn (TestRunner $t) => $t->same('descending', $plan(null, [['column' => 'option_name', 'direction' => 'DESC']])['cursorTape']['scanDirection']);
$tests['planner range order stat4 current source next102 desc program starts upper'] = static fn (TestRunner $t) => $t->same(['opcode' => 'SeekLT', 'column' => 'option_name', 'value' => 'transient_timeout'], $plan(null, [['column' => 'option_name', 'direction' => 'DESC']])['cursorTape']['program'][1]);
$tests['planner range order stat4 current source next102 desc program stops lower'] = static fn (TestRunner $t) => $t->same(['opcode' => 'IdxLT', 'column' => 'option_name', 'value' => 'home'], $plan(null, [['column' => 'option_name', 'direction' => 'DESC']])['cursorTape']['program'][2]);
$tests['planner range order stat4 current source next102 desc program advances prev'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Prev', 'target' => 'index'], $plan(null, [['column' => 'option_name', 'direction' => 'DESC']])['cursorTape']['program'][7]);

$tests['planner range order stat4 current source next102 between seek inclusive'] = static fn (TestRunner $t) => $t->same('SeekGE', $plan($between('option_name', 'home', 'transient_feed'))['cursorTape']['seekOpcode']);
$tests['planner range order stat4 current source next102 between stop inclusive'] = static fn (TestRunner $t) => $t->same('IdxGT', $plan($between('option_name', 'home', 'transient_feed'))['cursorTape']['stopOpcode']);
$tests['planner range order stat4 current source next102 between upper exact'] = static fn (TestRunner $t) => $t->same(true, $plan($between('option_name', 'home', 'transient_feed'))['cursorTape']['stat4UpperExact']);
$tests['planner range order stat4 current source next102 exclusive lower uses seek gt'] = static fn (TestRunner $t) => $t->same('SeekGT', $plan($and($range('option_name', '>', 'home'), $range('option_name', '<=', 'transient_feed')))['cursorTape']['seekOpcode']);
$tests['planner range order stat4 current source next102 exclusive lower desc stop le'] = static fn (TestRunner $t) => $t->same('IdxLE', $plan($and($range('option_name', '>', 'home'), $range('option_name', '<=', 'transient_feed')), [['column' => 'option_name', 'direction' => 'DESC']])['cursorTape']['stopOpcode']);
$tests['planner range order stat4 current source next102 noncovering defers seek'] = static fn (TestRunner $t) => $t->same('DeferredSeek', $plan(null, [['column' => 'option_name']], ['option_id'])['cursorTape']['deferredSeekOpcode']);
$tests['planner range order stat4 current source next102 noncovering column from table'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'table', 'column' => 'option_id'], $plan(null, [['column' => 'option_name']], ['option_id'])['cursorTape']['program'][4]);
$tests['planner range order stat4 current source next102 unrelated order opens sorter'] = static fn (TestRunner $t) => $t->same(true, $plan(null, [['column' => 'autoload']])['cursorTape']['sorterOpen']);
$tests['planner range order stat4 current source next102 unrelated order mode external'] = static fn (TestRunner $t) => $t->same('temp-btree-order', $plan(null, [['column' => 'autoload']])['selectedPlan']['rangeOrderMode']);
$tests['planner range order stat4 current source next102 same source reuses prepared'] = static fn (TestRunner $t) => $t->same('prepared', $plan($and($range('option_name', '>=', 'home'), $range('option_name', '<', 'transient_timeout')), [['column' => 'option_name']], ['option_name'], $preparedSource(), $preparedSource())['selectedSource']);
$tests['planner range order stat4 current source next102 same source no reprepare'] = static fn (TestRunner $t) => $t->same(false, $plan($and($range('option_name', '>=', 'home'), $range('option_name', '<', 'transient_timeout')), [['column' => 'option_name']], ['option_name'], $preparedSource(), $preparedSource())['reprepareRequired']);
$tests['planner range order stat4 current source next102 invalid order direction'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(null, [['column' => 'option_name', 'direction' => 'SIDEWAYS']]));

return $tests;
