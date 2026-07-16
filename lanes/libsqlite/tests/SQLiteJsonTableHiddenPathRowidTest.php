<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current140 = [
    'option_id' => 140,
    'option_name' => 'wp_plugin_hidden_path_rowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next140 = [
    'option_id' => 140,
    'option_name' => 'wp_plugin_hidden_path_rowid',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"shop","priority":8}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$point140 = static fn (?array $current = null, ?array $next = null): array => SQLiteJsonTablePlan::currentSourceHiddenPathRowid(
    'json_tree',
    $current ?? $current140,
    $next ?? $next140,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

$stable140 = static fn (): array => $point140($current140, $current140);
$miss140 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenPathRowid(
    'json_tree',
    $current140,
    $next140,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[9]'],
        ['column' => 'oid', 'operator' => '=', 'value' => 99],
    ],
    'scan_root',
);
$range140 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenPathRowid(
    'json_tree',
    $current140,
    $next140,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [3, 9]],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    'scan_root',
);
$jsonb140 = static fn (): array => $point140(
    $current140,
    array_replace($next140, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next140['option_value'])))]),
);
$unrunnable140 = static fn (): array => $point140($current140, array_replace($next140, ['option_value' => null]));

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $point140()['function']),
    'records hidden path rowid dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-path-rowid', $point140()['dependencies'], true)),
    'preserves next126 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-hidden-rowid-cost-current-source-next126', $point140()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-path-rowid-source-until-cursor-reset', $point140()['currentReaderPolicy']),
    'prepares changed next source' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-path-rowid-source', $point140()['nextReaderPolicy']),
    'stable reuses current source' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-path-rowid-source', $stable140()['nextReaderPolicy']),
    'stable has no hidden path rowid reasons' => static fn (TestRunner $t) => $t->same([], $stable140()['hiddenPathRowidReplanReasons']),
    'point seek signature combines path rowid' => static fn (TestRunner $t) => $t->same('2:path:=:"$.rules[1]"&&3:id:=:6', $point140()['currentHiddenPathRowidSource']['seekSignature']),
    'point path value is recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point140()['currentHiddenPathRowidSource']['pathValue']),
    'point rowid value normalizes alias' => static fn (TestRunner $t) => $t->same(6, $point140()['currentHiddenPathRowidSource']['rowidValue']),
    'point is seekable' => static fn (TestRunner $t) => $t->same(true, $point140()['currentHiddenPathRowidSource']['pointSeekable']),
    'point matches current row' => static fn (TestRunner $t) => $t->same(true, $point140()['currentHiddenPathRowidSource']['matched']),
    'point has no missing rowid' => static fn (TestRunner $t) => $t->same(false, $point140()['currentHiddenPathRowidSource']['missingRowid']),
    'point source kind is text' => static fn (TestRunner $t) => $t->same('text', $point140()['currentHiddenPathRowidSource']['sourceKind']),
    'point current row count is one' => static fn (TestRunner $t) => $t->same(1, $point140()['currentHiddenPathRowidSource']['rowCount']),
    'point current matched rowid' => static fn (TestRunner $t) => $t->same(6, $point140()['currentHiddenPathRowidSource']['matchedRowid']),
    'point current matched path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point140()['currentHiddenPathRowidSource']['matchedPath']),
    'point current matched fullkey' => static fn (TestRunner $t) => $t->same('$.rules[1].priority', $point140()['currentHiddenPathRowidSource']['matchedFullkey']),
    'point current matched key' => static fn (TestRunner $t) => $t->same('priority', $point140()['currentHiddenPathRowidSource']['matchedKey']),
    'point current matched type' => static fn (TestRunner $t) => $t->same('integer', $point140()['currentHiddenPathRowidSource']['matchedType']),
    'point current atom is cache priority' => static fn (TestRunner $t) => $t->same(7, $point140()['currentHiddenPathRowidSource']['matchedAtom']),
    'point next atom changes with current source' => static fn (TestRunner $t) => $t->same(8, $point140()['nextHiddenPathRowidSource']['matchedAtom']),
    'point current value fingerprint' => static fn (TestRunner $t) => $t->same('int:7', $point140()['currentHiddenPathRowidSource']['matchedValueFingerprint']),
    'point next value fingerprint' => static fn (TestRunner $t) => $t->same('int:8', $point140()['nextHiddenPathRowidSource']['matchedValueFingerprint']),
    'point current seek tape marks matched row' => static fn (TestRunner $t) => $t->same([['path' => '$.rules[1]', 'rowid' => 6, 'fullkey' => '$.rules[1].priority', 'type' => 'integer', 'key' => 'priority', 'matched' => true]], $point140()['currentHiddenPathRowidSource']['seekTape']),
    'point effective cost is one' => static fn (TestRunner $t) => $t->same(1, $point140()['currentHiddenPathRowidSource']['effectiveEstimatedCost']),
    'point cost class is current source point' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-rowid-current-source-point', $point140()['currentHiddenPathRowidSource']['costClass']),
    'transition count records source state' => static fn (TestRunner $t) => $t->same(7, count($point140()['hiddenPathRowidSourceTransitions'])),
    'seek signature transition stable' => static fn (TestRunner $t) => $t->same(false, $point140()['hiddenPathRowidSourceTransitions'][0]['changed']),
    'source kind transition stable for text' => static fn (TestRunner $t) => $t->same(false, $point140()['hiddenPathRowidSourceTransitions'][1]['changed']),
    'matched transition stable' => static fn (TestRunner $t) => $t->same(false, $point140()['hiddenPathRowidSourceTransitions'][2]['changed']),
    'fullkey transition stable' => static fn (TestRunner $t) => $t->same(false, $point140()['hiddenPathRowidSourceTransitions'][3]['changed']),
    'value transition changes' => static fn (TestRunner $t) => $t->same(true, $point140()['hiddenPathRowidSourceTransitions'][4]['changed']),
    'seek tape transition stable' => static fn (TestRunner $t) => $t->same(false, $point140()['hiddenPathRowidSourceTransitions'][5]['changed']),
    'cost class transition stable' => static fn (TestRunner $t) => $t->same(false, $point140()['hiddenPathRowidSourceTransitions'][6]['changed']),
    'reasons include value change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-path-rowid-current-source-value-changed', $point140()['hiddenPathRowidReplanReasons'], true)),
    'reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $point140()['hiddenPathRowidReplanReasons'], true)),
    'miss remains seekable' => static fn (TestRunner $t) => $t->same(true, $miss140()['currentHiddenPathRowidSource']['pointSeekable']),
    'miss has no matched row' => static fn (TestRunner $t) => $t->same(false, $miss140()['currentHiddenPathRowidSource']['matched']),
    'miss records missing rowid' => static fn (TestRunner $t) => $t->same(true, $miss140()['currentHiddenPathRowidSource']['missingRowid']),
    'miss cost class' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-rowid-current-source-miss', $miss140()['currentHiddenPathRowidSource']['costClass']),
    'miss matched fullkey is null' => static fn (TestRunner $t) => $t->same(null, $miss140()['currentHiddenPathRowidSource']['matchedFullkey']),
    'range is not point seekable' => static fn (TestRunner $t) => $t->same(false, $range140()['currentHiddenPathRowidSource']['pointSeekable']),
    'range has no seek signature' => static fn (TestRunner $t) => $t->same(null, $range140()['currentHiddenPathRowidSource']['seekSignature']),
    'range cost class is scan' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-rowid-current-source-intersection', $range140()['currentHiddenPathRowidSource']['costClass']),
    'range tape keeps current integer rowids' => static fn (TestRunner $t) => $t->same([3, 6, 9], array_column($range140()['currentHiddenPathRowidSource']['seekTape'], 'rowid')),
    'range tape keeps next bounded rowids' => static fn (TestRunner $t) => $t->same([3, 6, 9], array_column($range140()['nextHiddenPathRowidSource']['seekTape'], 'rowid')),
    'jsonb next remains matched' => static fn (TestRunner $t) => $t->same(true, $jsonb140()['nextHiddenPathRowidSource']['matched']),
    'jsonb next source kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb140()['nextHiddenPathRowidSource']['sourceKind']),
    'jsonb next reasons include source kind' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-path-rowid-source-kind-changed', $jsonb140()['hiddenPathRowidReplanReasons'], true)),
    'unrunnable next source kind sql null' => static fn (TestRunner $t) => $t->same('sql-null', $unrunnable140()['nextHiddenPathRowidSource']['sourceKind']),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable140()['nextHiddenPathRowidSource']['costClass']),
    'unrunnable next seek tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnable140()['nextHiddenPathRowidSource']['seekTape']),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenPathRowid('json_bad', $current140, $next140, 'option_value', [])),
    'bad json column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenPathRowid('json_tree', $current140, $next140, '', [])),
    'bad root column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenPathRowid('json_tree', $current140, $next140, 'option_value', [], '')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden path rowid current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
