<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentHiddenRowidPath = [
    'option_id' => 146,
    'option_name' => 'wp_plugin_hidden_rowid_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]},{"name":"commerce","rules":[{"slug":"shop","priority":6}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$nextHiddenRowidPath = [
    'option_id' => 146,
    'option_name' => 'wp_plugin_hidden_rowid_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"shop","priority":9}]},{"name":"commerce","rules":[{"slug":"shop","priority":6}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$hiddenRowidPathPlan = static fn (?array $current = null, ?array $next = null, ?array $constraints = null): array => SQLiteJsonTablePlan::currentSourceHiddenRowidPathPlan(
    'json_tree',
    $current ?? $currentHiddenRowidPath,
    $next ?? $nextHiddenRowidPath,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? [
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [2, 12]],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].priority'],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    [['column' => 'id']],
);

$stableHiddenRowidPath = static fn (): array => $hiddenRowidPathPlan($currentHiddenRowidPath, $currentHiddenRowidPath);
$pointHiddenRowidPath = static fn (): array => $hiddenRowidPathPlan(
    $currentHiddenRowidPath,
    $currentHiddenRowidPath,
    [
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[1].priority'],
    ],
);
$oidPointHiddenRowidPath = static fn (): array => $hiddenRowidPathPlan(
    $currentHiddenRowidPath,
    $currentHiddenRowidPath,
    [
        ['column' => 'oid', 'operator' => '=', 'value' => 9],
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[2].priority'],
    ],
);
$pathOnlyHiddenRowidPath = static fn (): array => $hiddenRowidPathPlan(
    $currentHiddenRowidPath,
    $nextHiddenRowidPath,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules%'],
    ],
);
$rowidOnlyHiddenRowidPath = static fn (): array => $hiddenRowidPathPlan(
    $currentHiddenRowidPath,
    $currentHiddenRowidPath,
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
);
$emptyHiddenRowidPath = static fn (): array => $hiddenRowidPathPlan(
    $currentHiddenRowidPath,
    $currentHiddenRowidPath,
    [
        ['column' => '_rowid_', 'operator' => '=', 'value' => 999],
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[9].priority'],
    ],
);
$unusableHiddenRowidPath = static fn (): array => $hiddenRowidPathPlan(
    $currentHiddenRowidPath,
    $currentHiddenRowidPath,
    [
        ['column' => 'oid', 'operator' => '=', 'value' => 7, 'usable' => false],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].priority'],
    ],
);
$unrunnableHiddenRowidPath = static fn (): array => $hiddenRowidPathPlan($currentHiddenRowidPath, array_replace($nextHiddenRowidPath, ['option_value' => null]));

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $hiddenRowidPathPlan()['function']),
    'records hidden rowid path dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-rowid-path-current-source', $hiddenRowidPathPlan()['dependencies'], true)),
    'preserves rowid hidden path dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-rowid-hidden-path-current-source', $hiddenRowidPathPlan()['dependencies'], true)),
    'pins current source reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-rowid-path-source-until-cursor-reset', $hiddenRowidPathPlan()['currentReaderPolicy']),
    'changed source prepares next' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-rowid-path-source-plan', $hiddenRowidPathPlan()['nextReaderPolicy']),
    'stable source reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-rowid-path-source-plan', $stableHiddenRowidPath()['nextReaderPolicy']),
    'stable source has no hidden rowid path reasons' => static fn (TestRunner $t) => $t->same([], $stableHiddenRowidPath()['hiddenRowidPathReplanReasons']),
    'current source token carries setting id' => static fn (TestRunner $t) => $t->same(146, $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['sourceToken']['setting_id']),
    'current source token carries key name' => static fn (TestRunner $t) => $t->same('wp_plugin_hidden_rowid_path', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['sourceToken']['key_name']),
    'current source token carries nested root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['sourceToken']['root']),
    'root is nested rules root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['root']),
    'base root retained' => static fn (TestRunner $t) => $t->same('$.plugin.groups', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['baseRoot']),
    'nested path retained' => static fn (TestRunner $t) => $t->same('[0].rules', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['nestedPath']),
    'rowid alias normalized to id' => static fn (TestRunner $t) => $t->same('id', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['rowidAlias']),
    'path alias is fullkey' => static fn (TestRunner $t) => $t->same('fullkey', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['pathAlias']),
    'alias columns preserve hidden rowid then path' => static fn (TestRunner $t) => $t->same(['id', 'fullkey'], $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['aliasColumns']),
    'path signature records LIKE' => static fn (TestRunner $t) => $t->same('fullkey:LIKE:"$.plugin.groups[0].rules[%].priority"', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['pathConstraintSignature']),
    'rowid signature records between' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[2,12]', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['rowidConstraintSignature']),
    'range is not point seekable' => static fn (TestRunner $t) => $t->same(false, $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['pointSeekable']),
    'matched row count is three priorities' => static fn (TestRunner $t) => $t->same(3, $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['matchedRowCount']),
    'current pinned rowids are priority leaves' => static fn (TestRunner $t) => $t->same([3, 6, 9], $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['pinnedRowids']),
    'next pinned rowids include inserted bounded priority' => static fn (TestRunner $t) => $t->same([3, 6, 9, 12], $hiddenRowidPathPlan()['nextHiddenRowidPathSource']['pinnedRowids']),
    'current pinned relative fullkeys' => static fn (TestRunner $t) => $t->same(['$[0].priority', '$[1].priority', '$[2].priority'], $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['pinnedRelativeFullkeys']),
    'first pinned row recorded' => static fn (TestRunner $t) => $t->same(['rowid' => 3, 'relativeFullkey' => '$[0].priority'], $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['firstPinned']),
    'last pinned row recorded' => static fn (TestRunner $t) => $t->same(['rowid' => 9, 'relativeFullkey' => '$[2].priority'], $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['lastPinned']),
    'rowid path tape has current matched row count' => static fn (TestRunner $t) => $t->same(3, count($hiddenRowidPathPlan()['currentHiddenRowidPathSource']['rowidPathTape'])),
    'rowid path tape first rowid' => static fn (TestRunner $t) => $t->same(3, $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['rowidPathTape'][0]['rowid']),
    'rowid path tape priority path match' => static fn (TestRunner $t) => $t->same(true, $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['rowidPathTape'][0]['pathMatched']),
    'rowid path tape priority rowid match' => static fn (TestRunner $t) => $t->same(true, $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['rowidPathTape'][0]['rowidMatched']),
    'rowid path tape priority intersection match' => static fn (TestRunner $t) => $t->same(true, $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['rowidPathTape'][0]['matched']),
    'effective cost narrows to matched row count' => static fn (TestRunner $t) => $t->same(3, $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['effectiveEstimatedCost']),
    'cost class is path current source' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-path-path-current-source', $hiddenRowidPathPlan()['currentHiddenRowidPathSource']['costClass']),
    'transition count records hidden rowid path fields' => static fn (TestRunner $t) => $t->same(9, count($hiddenRowidPathPlan()['hiddenRowidPathSourceTransitions'])),
    'source token transition stable' => static fn (TestRunner $t) => $t->same(false, $hiddenRowidPathPlan()['hiddenRowidPathSourceTransitions'][0]['changed']),
    'alias columns transition stable' => static fn (TestRunner $t) => $t->same(false, $hiddenRowidPathPlan()['hiddenRowidPathSourceTransitions'][1]['changed']),
    'point seek transition stable' => static fn (TestRunner $t) => $t->same(false, $hiddenRowidPathPlan()['hiddenRowidPathSourceTransitions'][2]['changed']),
    'matched count transition changes with inserted priority' => static fn (TestRunner $t) => $t->same(true, $hiddenRowidPathPlan()['hiddenRowidPathSourceTransitions'][3]['changed']),
    'pinned rowids transition changes with inserted priority' => static fn (TestRunner $t) => $t->same(true, $hiddenRowidPathPlan()['hiddenRowidPathSourceTransitions'][4]['changed']),
    'pinned fullkeys transition changes with inserted priority' => static fn (TestRunner $t) => $t->same(true, $hiddenRowidPathPlan()['hiddenRowidPathSourceTransitions'][5]['changed']),
    'tape transition changes after insert' => static fn (TestRunner $t) => $t->same(true, $hiddenRowidPathPlan()['hiddenRowidPathSourceTransitions'][6]['changed']),
    'reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $hiddenRowidPathPlan()['hiddenRowidPathReplanReasons'], true)),
    'reasons include tape change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-rowid-path-tape-changed', $hiddenRowidPathPlan()['hiddenRowidPathReplanReasons'], true)),
    'point seekable with underscore rowid' => static fn (TestRunner $t) => $t->same(true, $pointHiddenRowidPath()['currentHiddenRowidPathSource']['pointSeekable']),
    'point rowid alias is id' => static fn (TestRunner $t) => $t->same('id', $pointHiddenRowidPath()['currentHiddenRowidPathSource']['rowidAlias']),
    'point pinned rowid' => static fn (TestRunner $t) => $t->same([6], $pointHiddenRowidPath()['currentHiddenRowidPathSource']['pinnedRowids']),
    'point class names current source point' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-path-point-current-source', $pointHiddenRowidPath()['currentHiddenRowidPathSource']['costClass']),
    'oid point rowid alias normalizes' => static fn (TestRunner $t) => $t->same('id', $oidPointHiddenRowidPath()['currentHiddenRowidPathSource']['rowidAlias']),
    'oid point path alias retained' => static fn (TestRunner $t) => $t->same('fullkey', $oidPointHiddenRowidPath()['currentHiddenRowidPathSource']['pathAlias']),
    'relative point pins third priority' => static fn (TestRunner $t) => $t->same([9], $oidPointHiddenRowidPath()['currentHiddenRowidPathSource']['pinnedRowids']),
    'path only has only path alias' => static fn (TestRunner $t) => $t->same(['path'], $pathOnlyHiddenRowidPath()['currentHiddenRowidPathSource']['aliasColumns']),
    'rowid only has only rowid alias' => static fn (TestRunner $t) => $t->same(['id'], $rowidOnlyHiddenRowidPath()['currentHiddenRowidPathSource']['aliasColumns']),
    'rowid only class' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-path-rowid-current-source', $rowidOnlyHiddenRowidPath()['currentHiddenRowidPathSource']['costClass']),
    'empty has no first pinned' => static fn (TestRunner $t) => $t->same(null, $emptyHiddenRowidPath()['currentHiddenRowidPathSource']['firstPinned']),
    'empty class' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-path-empty', $emptyHiddenRowidPath()['currentHiddenRowidPathSource']['costClass']),
    'unusable rowid leaves path alias only' => static fn (TestRunner $t) => $t->same(['fullkey'], $unusableHiddenRowidPath()['currentHiddenRowidPathSource']['aliasColumns']),
    'unrunnable next class sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnableHiddenRowidPath()['nextHiddenRowidPathSource']['costClass']),
    'unrunnable next tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnableHiddenRowidPath()['nextHiddenRowidPathSource']['rowidPathTape']),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidPathPlan('json_bad', $currentHiddenRowidPath, $nextHiddenRowidPath, 'option_value', 'base_root', 'nested_path')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden rowid path current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
