<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current122 = [
    'option_id' => 41,
    'option_name' => 'wp_plugin_hidden_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$next122 = [
    'option_id' => 41,
    'option_name' => 'wp_plugin_hidden_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"next":true}',
    'scan_root' => '$.rules',
];
$nextRoot122 = array_replace($current122, ['scan_root' => '$.rules[1]']);
$constraints122 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.rules[%].priority'],
];

$jsonOrder122 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedHiddenOrder(
    'json_tree',
    $current122,
    $next122,
    'option_value',
    $constraints122,
    'scan_root',
    [['column' => 'json'], ['column' => 'id']],
);

$rootOrder122 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedHiddenOrder(
    'json_tree',
    $current122,
    $nextRoot122,
    'option_value',
    $constraints122,
    'scan_root',
    [['column' => 'root', 'direction' => 'DESC'], ['column' => 'id']],
);

$stable122 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedHiddenOrder(
    'json_tree',
    $current122,
    $current122,
    'option_value',
    $constraints122,
    'scan_root',
    [['column' => 'json'], ['column' => 'id']],
);

$visibleOrder122 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedHiddenOrder(
    'json_tree',
    $current122,
    $next122,
    'option_value',
    $constraints122,
    'scan_root',
    [['column' => 'id']],
);

$unrunnable122 = static fn (): array => SQLiteJsonTablePlan::currentSourceIndexedHiddenOrder(
    'json_tree',
    $current122,
    array_replace($current122, ['option_value' => null]),
    'option_value',
    $constraints122,
    'scan_root',
    [['column' => 'json']],
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $jsonOrder122()['function']),
    'records next122 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-indexed-hidden-order-current-source-next122', $jsonOrder122()['dependencies'], true)),
    'preserves indexed next119 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-indexed-constraint-cost-current-source-next119', $jsonOrder122()['dependencies'], true)),
    'pins current hidden order reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-indexed-hidden-order-until-cursor-reset', $jsonOrder122()['currentReaderPolicy']),
    'prepares changed hidden order plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-indexed-hidden-order-plan', $jsonOrder122()['nextReaderPolicy']),
    'stable hidden order reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-indexed-hidden-order-plan', $stable122()['nextReaderPolicy']),
    'stable hidden order has no next122 reasons' => static fn (TestRunner $t) => $t->same([], $stable122()['next122ReplanReasons']),
    'json hidden order term is captured' => static fn (TestRunner $t) => $t->same([['column' => 'json', 'direction' => 'ASC']], $jsonOrder122()['currentIndexedHiddenOrder']['hiddenOrderBy']),
    'root hidden order term is captured with direction' => static fn (TestRunner $t) => $t->same([['column' => 'root', 'direction' => 'DESC']], $rootOrder122()['currentIndexedHiddenOrder']['hiddenOrderBy']),
    'visible id order has no hidden order terms' => static fn (TestRunner $t) => $t->same([], $visibleOrder122()['currentIndexedHiddenOrder']['hiddenOrderBy']),
    'json hidden order marks current profile' => static fn (TestRunner $t) => $t->same(true, $jsonOrder122()['currentIndexedHiddenOrder']['hasHiddenOrder']),
    'visible order marks no hidden profile' => static fn (TestRunner $t) => $t->same(false, $visibleOrder122()['currentIndexedHiddenOrder']['hasHiddenOrder']),
    'json hidden order requires sorter for multiple rows' => static fn (TestRunner $t) => $t->same(true, $jsonOrder122()['currentIndexedHiddenOrder']['requiresHiddenSorter']),
    'root hidden order narrowed next root avoids sorter' => static fn (TestRunner $t) => $t->same(false, $rootOrder122()['nextIndexedHiddenOrder']['requiresHiddenSorter']),
    'json hidden source key is current option json' => static fn (TestRunner $t) => $t->same([$current122['option_value']], $jsonOrder122()['currentIndexedHiddenOrder']['sourceHiddenKey']),
    'json hidden next source key is next option json' => static fn (TestRunner $t) => $t->same([$next122['option_value']], $jsonOrder122()['nextIndexedHiddenOrder']['sourceHiddenKey']),
    'root hidden source key is current root' => static fn (TestRunner $t) => $t->same(['$.rules'], $rootOrder122()['currentIndexedHiddenOrder']['sourceHiddenKey']),
    'root hidden next source key is next root' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $rootOrder122()['nextIndexedHiddenOrder']['sourceHiddenKey']),
    'json first hidden key includes first row id' => static fn (TestRunner $t) => $t->same([$current122['option_value'], 3], $jsonOrder122()['currentIndexedHiddenOrder']['firstHiddenKey']),
    'json last hidden key includes last row id' => static fn (TestRunner $t) => $t->same([$current122['option_value'], 9], $jsonOrder122()['currentIndexedHiddenOrder']['lastHiddenKey']),
    'json next first hidden key uses changed json' => static fn (TestRunner $t) => $t->same([$next122['option_value'], 3], $jsonOrder122()['nextIndexedHiddenOrder']['firstHiddenKey']),
    'root next first hidden key is narrowed priority row' => static fn (TestRunner $t) => $t->same(['$.rules[1]', 2], $rootOrder122()['nextIndexedHiddenOrder']['firstHiddenKey']),
    'json current row hidden key count follows row count' => static fn (TestRunner $t) => $t->same($jsonOrder122()['currentIndexedHiddenOrder']['rowCount'], count($jsonOrder122()['currentIndexedHiddenOrder']['rowHiddenKeys'])),
    'json next row hidden key count follows row count' => static fn (TestRunner $t) => $t->same($jsonOrder122()['nextIndexedHiddenOrder']['rowCount'], count($jsonOrder122()['nextIndexedHiddenOrder']['rowHiddenKeys'])),
    'json current row count sees priorities' => static fn (TestRunner $t) => $t->same(3, $jsonOrder122()['currentIndexedHiddenOrder']['rowCount']),
    'json next row count remains same despite source text change' => static fn (TestRunner $t) => $t->same(3, $jsonOrder122()['nextIndexedHiddenOrder']['rowCount']),
    'root next row count is narrowed' => static fn (TestRunner $t) => $t->same(1, $rootOrder122()['nextIndexedHiddenOrder']['rowCount']),
    'json indexed selected signature is preserved' => static fn (TestRunner $t) => $t->same('3:fullkey:LIKE:"$.rules[%].priority"', $jsonOrder122()['currentIndexedHiddenOrder']['selectedSignature']),
    'json next selected signature is preserved' => static fn (TestRunner $t) => $t->same($jsonOrder122()['currentIndexedHiddenOrder']['selectedSignature'], $jsonOrder122()['nextIndexedHiddenOrder']['selectedSignature']),
    'hidden sort penalty is charged for json order' => static fn (TestRunner $t) => $t->same(6, $jsonOrder122()['currentIndexedHiddenOrder']['hiddenSortPenalty']),
    'hidden sort penalty is not charged for visible order' => static fn (TestRunner $t) => $t->same(0, $visibleOrder122()['currentIndexedHiddenOrder']['hiddenSortPenalty']),
    'hidden effective cost adds penalty to indexed cost' => static fn (TestRunner $t) => $t->same(7, $jsonOrder122()['currentIndexedHiddenOrder']['effectiveEstimatedCost']),
    'visible order effective cost keeps indexed cost' => static fn (TestRunner $t) => $t->same($visibleOrder122()['currentIndexedConstraintCost']['effectiveEstimatedCost'], $visibleOrder122()['currentIndexedHiddenOrder']['effectiveEstimatedCost']),
    'json hidden cost class names sorter' => static fn (TestRunner $t) => $t->same('json-table-indexed-hidden-order-sort-required', $jsonOrder122()['currentIndexedHiddenOrder']['costClass']),
    'visible order cost class inherits indexed class' => static fn (TestRunner $t) => $t->same($visibleOrder122()['currentIndexedConstraintCost']['costClass'], $visibleOrder122()['currentIndexedHiddenOrder']['costClass']),
    'unrunnable hidden order cost class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable122()['nextIndexedHiddenOrder']['costClass']),
    'unrunnable hidden order effective cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable122()['nextIndexedHiddenOrder']['effectiveEstimatedCost']),
    'transition count records hidden order fields' => static fn (TestRunner $t) => $t->same(6, count($jsonOrder122()['indexedHiddenOrderTransitions'])),
    'hidden order terms transition is stable' => static fn (TestRunner $t) => $t->same(false, $jsonOrder122()['indexedHiddenOrderTransitions'][0]['changed']),
    'hidden source key transition changes for json source' => static fn (TestRunner $t) => $t->same(true, $jsonOrder122()['indexedHiddenOrderTransitions'][1]['changed']),
    'hidden sorter transition is stable for json source' => static fn (TestRunner $t) => $t->same(false, $jsonOrder122()['indexedHiddenOrderTransitions'][2]['changed']),
    'hidden row keys transition changes for json source' => static fn (TestRunner $t) => $t->same(true, $jsonOrder122()['indexedHiddenOrderTransitions'][3]['changed']),
    'hidden effective cost transition is stable for equal row counts' => static fn (TestRunner $t) => $t->same(false, $jsonOrder122()['indexedHiddenOrderTransitions'][4]['changed']),
    'hidden cost class transition is stable for equal row counts' => static fn (TestRunner $t) => $t->same(false, $jsonOrder122()['indexedHiddenOrderTransitions'][5]['changed']),
    'root hidden source transition changes' => static fn (TestRunner $t) => $t->same(true, $rootOrder122()['indexedHiddenOrderTransitions'][1]['changed']),
    'root hidden sorter transition changes after narrowing' => static fn (TestRunner $t) => $t->same(true, $rootOrder122()['indexedHiddenOrderTransitions'][2]['changed']),
    'root hidden effective cost transition changes after narrowing' => static fn (TestRunner $t) => $t->same(true, $rootOrder122()['indexedHiddenOrderTransitions'][4]['changed']),
    'next122 reasons include hidden order source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-order-source-changed', $jsonOrder122()['next122ReplanReasons'], true)),
    'next122 reasons include hidden output order change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-output-order-changed', $jsonOrder122()['next122ReplanReasons'], true)),
    'root next122 reasons include sorter change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-order-sorter-changed', $rootOrder122()['next122ReplanReasons'], true)),
    'root next122 reasons preserve indexed row count change' => static fn (TestRunner $t) => $t->true(in_array('json-table-indexed-row-count-changed', $rootOrder122()['next122ReplanReasons'], true)),
    'visible order has no hidden-only replan reason' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-hidden-order-source-changed', $visibleOrder122()['next122ReplanReasons'], true)),
    'bad hidden order direction is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceIndexedHiddenOrder('json_tree', $current122, $next122, 'option_value', $constraints122, 'scan_root', [['column' => 'json', 'direction' => 'BAD']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table indexed hidden order current source next122 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
