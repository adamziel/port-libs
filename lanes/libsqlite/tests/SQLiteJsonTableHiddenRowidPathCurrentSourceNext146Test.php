<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current146 = [
    'option_id' => 146,
    'option_name' => 'wp_plugin_hidden_rowid_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]},{"name":"commerce","rules":[{"slug":"shop","priority":6}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next146 = [
    'option_id' => 146,
    'option_name' => 'wp_plugin_hidden_rowid_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"shop","priority":9}]},{"name":"commerce","rules":[{"slug":"shop","priority":6}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$plan146 = static fn (?array $current = null, ?array $next = null, ?array $constraints = null): array => SQLiteJsonTablePlan::currentSourceHiddenRowidPathNext146(
    'json_tree',
    $current ?? $current146,
    $next ?? $next146,
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

$stable146 = static fn (): array => $plan146($current146, $current146);
$point146 = static fn (): array => $plan146(
    $current146,
    $current146,
    [
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[1].priority'],
    ],
);
$oidPoint146 = static fn (): array => $plan146(
    $current146,
    $current146,
    [
        ['column' => 'oid', 'operator' => '=', 'value' => 9],
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[2].priority'],
    ],
);
$pathOnly146 = static fn (): array => $plan146(
    $current146,
    $next146,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules%'],
    ],
);
$rowidOnly146 = static fn (): array => $plan146(
    $current146,
    $current146,
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
);
$empty146 = static fn (): array => $plan146(
    $current146,
    $current146,
    [
        ['column' => '_rowid_', 'operator' => '=', 'value' => 999],
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[9].priority'],
    ],
);
$unusable146 = static fn (): array => $plan146(
    $current146,
    $current146,
    [
        ['column' => 'oid', 'operator' => '=', 'value' => 7, 'usable' => false],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].priority'],
    ],
);
$unrunnable146 = static fn (): array => $plan146($current146, array_replace($next146, ['option_value' => null]));

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $plan146()['function']),
    'records next146 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-rowid-path-current-source-next146', $plan146()['dependencies'], true)),
    'preserves next138 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-rowid-hidden-path-current-source-next138', $plan146()['dependencies'], true)),
    'pins current source reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-rowid-path-source-until-cursor-reset', $plan146()['currentReaderPolicy']),
    'changed source prepares next' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-rowid-path-source-plan', $plan146()['nextReaderPolicy']),
    'stable source reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-rowid-path-source-plan', $stable146()['nextReaderPolicy']),
    'stable source has no next146 reasons' => static fn (TestRunner $t) => $t->same([], $stable146()['next146ReplanReasons']),
    'current source token carries option id' => static fn (TestRunner $t) => $t->same(146, $plan146()['currentHiddenRowidPathSource']['sourceToken']['option_id']),
    'current source token carries option name' => static fn (TestRunner $t) => $t->same('wp_plugin_hidden_rowid_path', $plan146()['currentHiddenRowidPathSource']['sourceToken']['option_name']),
    'current source token carries nested root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan146()['currentHiddenRowidPathSource']['sourceToken']['root']),
    'root is nested rules root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan146()['currentHiddenRowidPathSource']['root']),
    'base root retained' => static fn (TestRunner $t) => $t->same('$.plugin.groups', $plan146()['currentHiddenRowidPathSource']['baseRoot']),
    'nested path retained' => static fn (TestRunner $t) => $t->same('[0].rules', $plan146()['currentHiddenRowidPathSource']['nestedPath']),
    'rowid alias normalized to id' => static fn (TestRunner $t) => $t->same('id', $plan146()['currentHiddenRowidPathSource']['rowidAlias']),
    'path alias is fullkey' => static fn (TestRunner $t) => $t->same('fullkey', $plan146()['currentHiddenRowidPathSource']['pathAlias']),
    'alias columns preserve hidden rowid then path' => static fn (TestRunner $t) => $t->same(['id', 'fullkey'], $plan146()['currentHiddenRowidPathSource']['aliasColumns']),
    'path signature records LIKE' => static fn (TestRunner $t) => $t->same('fullkey:LIKE:"$.plugin.groups[0].rules[%].priority"', $plan146()['currentHiddenRowidPathSource']['pathConstraintSignature']),
    'rowid signature records between' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[2,12]', $plan146()['currentHiddenRowidPathSource']['rowidConstraintSignature']),
    'range is not point seekable' => static fn (TestRunner $t) => $t->same(false, $plan146()['currentHiddenRowidPathSource']['pointSeekable']),
    'matched row count is three priorities' => static fn (TestRunner $t) => $t->same(3, $plan146()['currentHiddenRowidPathSource']['matchedRowCount']),
    'current pinned rowids are priority leaves' => static fn (TestRunner $t) => $t->same([3, 6, 9], $plan146()['currentHiddenRowidPathSource']['pinnedRowids']),
    'next pinned rowids include inserted bounded priority' => static fn (TestRunner $t) => $t->same([3, 6, 9, 12], $plan146()['nextHiddenRowidPathSource']['pinnedRowids']),
    'current pinned relative fullkeys' => static fn (TestRunner $t) => $t->same(['$[0].priority', '$[1].priority', '$[2].priority'], $plan146()['currentHiddenRowidPathSource']['pinnedRelativeFullkeys']),
    'first pinned row recorded' => static fn (TestRunner $t) => $t->same(['rowid' => 3, 'relativeFullkey' => '$[0].priority'], $plan146()['currentHiddenRowidPathSource']['firstPinned']),
    'last pinned row recorded' => static fn (TestRunner $t) => $t->same(['rowid' => 9, 'relativeFullkey' => '$[2].priority'], $plan146()['currentHiddenRowidPathSource']['lastPinned']),
    'rowid path tape has current matched row count' => static fn (TestRunner $t) => $t->same(3, count($plan146()['currentHiddenRowidPathSource']['rowidPathTape'])),
    'rowid path tape first rowid' => static fn (TestRunner $t) => $t->same(3, $plan146()['currentHiddenRowidPathSource']['rowidPathTape'][0]['rowid']),
    'rowid path tape priority path match' => static fn (TestRunner $t) => $t->same(true, $plan146()['currentHiddenRowidPathSource']['rowidPathTape'][0]['pathMatched']),
    'rowid path tape priority rowid match' => static fn (TestRunner $t) => $t->same(true, $plan146()['currentHiddenRowidPathSource']['rowidPathTape'][0]['rowidMatched']),
    'rowid path tape priority intersection match' => static fn (TestRunner $t) => $t->same(true, $plan146()['currentHiddenRowidPathSource']['rowidPathTape'][0]['matched']),
    'effective cost narrows to matched row count' => static fn (TestRunner $t) => $t->same(3, $plan146()['currentHiddenRowidPathSource']['effectiveEstimatedCost']),
    'cost class is path current source' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-path-path-current-source', $plan146()['currentHiddenRowidPathSource']['costClass']),
    'transition count records next146 fields' => static fn (TestRunner $t) => $t->same(9, count($plan146()['hiddenRowidPathSourceTransitions'])),
    'source token transition stable' => static fn (TestRunner $t) => $t->same(false, $plan146()['hiddenRowidPathSourceTransitions'][0]['changed']),
    'alias columns transition stable' => static fn (TestRunner $t) => $t->same(false, $plan146()['hiddenRowidPathSourceTransitions'][1]['changed']),
    'point seek transition stable' => static fn (TestRunner $t) => $t->same(false, $plan146()['hiddenRowidPathSourceTransitions'][2]['changed']),
    'matched count transition changes with inserted priority' => static fn (TestRunner $t) => $t->same(true, $plan146()['hiddenRowidPathSourceTransitions'][3]['changed']),
    'pinned rowids transition changes with inserted priority' => static fn (TestRunner $t) => $t->same(true, $plan146()['hiddenRowidPathSourceTransitions'][4]['changed']),
    'pinned fullkeys transition changes with inserted priority' => static fn (TestRunner $t) => $t->same(true, $plan146()['hiddenRowidPathSourceTransitions'][5]['changed']),
    'tape transition changes after insert' => static fn (TestRunner $t) => $t->same(true, $plan146()['hiddenRowidPathSourceTransitions'][6]['changed']),
    'reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan146()['next146ReplanReasons'], true)),
    'reasons include tape change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-rowid-path-tape-changed', $plan146()['next146ReplanReasons'], true)),
    'point seekable with underscore rowid' => static fn (TestRunner $t) => $t->same(true, $point146()['currentHiddenRowidPathSource']['pointSeekable']),
    'point rowid alias is id' => static fn (TestRunner $t) => $t->same('id', $point146()['currentHiddenRowidPathSource']['rowidAlias']),
    'point pinned rowid' => static fn (TestRunner $t) => $t->same([6], $point146()['currentHiddenRowidPathSource']['pinnedRowids']),
    'point class names current source point' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-path-point-current-source', $point146()['currentHiddenRowidPathSource']['costClass']),
    'oid point rowid alias normalizes' => static fn (TestRunner $t) => $t->same('id', $oidPoint146()['currentHiddenRowidPathSource']['rowidAlias']),
    'oid point path alias retained' => static fn (TestRunner $t) => $t->same('fullkey', $oidPoint146()['currentHiddenRowidPathSource']['pathAlias']),
    'relative point pins third priority' => static fn (TestRunner $t) => $t->same([9], $oidPoint146()['currentHiddenRowidPathSource']['pinnedRowids']),
    'path only has only path alias' => static fn (TestRunner $t) => $t->same(['path'], $pathOnly146()['currentHiddenRowidPathSource']['aliasColumns']),
    'rowid only has only rowid alias' => static fn (TestRunner $t) => $t->same(['id'], $rowidOnly146()['currentHiddenRowidPathSource']['aliasColumns']),
    'rowid only class' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-path-rowid-current-source', $rowidOnly146()['currentHiddenRowidPathSource']['costClass']),
    'empty has no first pinned' => static fn (TestRunner $t) => $t->same(null, $empty146()['currentHiddenRowidPathSource']['firstPinned']),
    'empty class' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-path-empty', $empty146()['currentHiddenRowidPathSource']['costClass']),
    'unusable rowid leaves path alias only' => static fn (TestRunner $t) => $t->same(['fullkey'], $unusable146()['currentHiddenRowidPathSource']['aliasColumns']),
    'unrunnable next class sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable146()['nextHiddenRowidPathSource']['costClass']),
    'unrunnable next tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnable146()['nextHiddenRowidPathSource']['rowidPathTape']),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenRowidPathNext146('json_bad', $current146, $next146, 'option_value', 'base_root', 'nested_path')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden rowid path current source next146 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
