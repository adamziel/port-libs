<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current164 = [
    'option_id' => 164,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next164',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules',
];
$next164 = [
    'option_id' => 164,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next164',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$plan164 = static fn (?array $current = null, ?array $next = null, ?array $constraints = null, ?array $orderBy = null, ?int $limit = null): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceOrderPlan(
    'json_tree',
    $current ?? $current164,
    $next ?? $next164,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
);
$stable164 = static fn (): array => $plan164($current164, $current164);
$forward164 = static fn (): array => $plan164(null, null, null, [['column' => 'rowid', 'direction' => 'ASC']]);
$limited164 = static fn (): array => $plan164(null, null, null, [['column' => 'id', 'direction' => 'ASC']], 1);
$single164 = static fn (): array => $plan164(
    array_replace($current164, ['generated_path' => '$.rules[1]']),
    array_replace($current164, ['generated_path' => '$.rules[1]']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'oid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'path', 'direction' => 'DESC']],
);
$sorter164 = static fn (): array => $plan164(null, null, null, [['column' => 'path', 'direction' => 'ASC']]);
$unusable164 = static fn (): array => $plan164(
    $current164,
    $next164,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%', 'usable' => false],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
    [['column' => 'id', 'direction' => 'DESC']],
);
$nullNext164 = static fn (): array => $plan164($current164, array_replace($next164, ['option_value' => null]));

$tests = [
    'records next164 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next164', $plan164()['dependencies'], true)),
    'preserves current-source admission dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source', $plan164()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-current-source-next164-until-cursor-reset', $plan164()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-current-source-next164-plan', $plan164()['nextReaderPolicy']),
    'stable reuses next reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-current-source-next164-plan', $stable164()['nextReaderPolicy']),
    'stable has no next164 reasons' => static fn (TestRunner $t) => $t->same([], $stable164()['next164ReplanReasons']),
    'current order consumes rowid desc' => static fn (TestRunner $t) => $t->same(true, $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['orderByConsumed']),
    'current order does not require sorter' => static fn (TestRunner $t) => $t->same(false, $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['requiresSorter']),
    'current order scan direction reverse' => static fn (TestRunner $t) => $t->same('reverse', $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['scanDirection']),
    'current order normalized order by id desc' => static fn (TestRunner $t) => $t->same([['column' => 'id', 'direction' => 'DESC']], $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['orderBy']),
    'current order rowids reverse' => static fn (TestRunner $t) => $t->same([6, 5], $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['orderedSeekRowids']),
    'current order first rowid reverse' => static fn (TestRunner $t) => $t->same(6, $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['firstOutputRowid']),
    'current order last rowid reverse' => static fn (TestRunner $t) => $t->same(5, $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['lastOutputRowid']),
    'current order estimated rows' => static fn (TestRunner $t) => $t->same(2, $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['estimatedRows']),
    'current order estimated cost' => static fn (TestRunner $t) => $t->same(2, $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['estimatedCost']),
    'current order cost class reverse' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-order-reverse', $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['costClass']),
    'current order idx string consumed' => static fn (TestRunner $t) => $t->same('omit:path:LIKE|omit:id:IN|orderby:consumed', $plan164()['currentGeneratedPathRowidCurrentSourceOrder']['idxStr']),
    'next order reprepare requires sorter' => static fn (TestRunner $t) => $t->same(true, $plan164()['nextGeneratedPathRowidCurrentSourceOrder']['requiresSorter']),
    'next order not consumed' => static fn (TestRunner $t) => $t->same(false, $plan164()['nextGeneratedPathRowidCurrentSourceOrder']['orderByConsumed']),
    'next order sorter reason reprepare' => static fn (TestRunner $t) => $t->same('current-source-reprepare-required', $plan164()['nextGeneratedPathRowidCurrentSourceOrder']['sorterReason']),
    'next order cost class sorter after reprepare' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-order-sorter', $plan164()['nextGeneratedPathRowidCurrentSourceOrder']['costClass']),
    'next order sentinel cost' => static fn (TestRunner $t) => $t->same(1000000, $plan164()['nextGeneratedPathRowidCurrentSourceOrder']['estimatedCost']),
    'forward order rowids ascending' => static fn (TestRunner $t) => $t->same([5, 6], $forward164()['currentGeneratedPathRowidCurrentSourceOrder']['orderedSeekRowids']),
    'forward order scan direction' => static fn (TestRunner $t) => $t->same('forward', $forward164()['currentGeneratedPathRowidCurrentSourceOrder']['scanDirection']),
    'forward order cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-order-forward', $forward164()['currentGeneratedPathRowidCurrentSourceOrder']['costClass']),
    'limited order rowids capped' => static fn (TestRunner $t) => $t->same([5], $limited164()['currentGeneratedPathRowidCurrentSourceOrder']['orderedSeekRowids']),
    'limited order limit recorded' => static fn (TestRunner $t) => $t->same(1, $limited164()['currentGeneratedPathRowidCurrentSourceOrder']['limit']),
    'limited order rows capped' => static fn (TestRunner $t) => $t->same(1, $limited164()['currentGeneratedPathRowidCurrentSourceOrder']['estimatedRows']),
    'limited order cost capped' => static fn (TestRunner $t) => $t->same(1, $limited164()['currentGeneratedPathRowidCurrentSourceOrder']['estimatedCost']),
    'limited order cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-order-limit', $limited164()['currentGeneratedPathRowidCurrentSourceOrder']['costClass']),
    'single row path order consumed' => static fn (TestRunner $t) => $t->same(true, $single164()['currentGeneratedPathRowidCurrentSourceOrder']['orderByConsumed']),
    'single row path order has no sorter reason' => static fn (TestRunner $t) => $t->same(null, $single164()['currentGeneratedPathRowidCurrentSourceOrder']['sorterReason']),
    'single row path order rowids' => static fn (TestRunner $t) => $t->same([6], $single164()['currentGeneratedPathRowidCurrentSourceOrder']['orderedSeekRowids']),
    'single row path order cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-order-forward', $single164()['currentGeneratedPathRowidCurrentSourceOrder']['costClass']),
    'sorter path order requires sorter' => static fn (TestRunner $t) => $t->same(true, $sorter164()['currentGeneratedPathRowidCurrentSourceOrder']['requiresSorter']),
    'sorter path order not consumed' => static fn (TestRunner $t) => $t->same(false, $sorter164()['currentGeneratedPathRowidCurrentSourceOrder']['orderByConsumed']),
    'sorter path order reason' => static fn (TestRunner $t) => $t->same('order-by-not-covered-by-rowid-current-source', $sorter164()['currentGeneratedPathRowidCurrentSourceOrder']['sorterReason']),
    'sorter path order cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-current-source-order-sorter', $sorter164()['currentGeneratedPathRowidCurrentSourceOrder']['costClass']),
    'sorter path order adds cost penalty' => static fn (TestRunner $t) => $t->same(4, $sorter164()['currentGeneratedPathRowidCurrentSourceOrder']['estimatedCost']),
    'sorter path order idx string' => static fn (TestRunner $t) => $t->same('omit:path:LIKE|omit:id:IN|orderby:sorter', $sorter164()['currentGeneratedPathRowidCurrentSourceOrder']['idxStr']),
    'unusable path prevents order consumption' => static fn (TestRunner $t) => $t->same(false, $unusable164()['currentGeneratedPathRowidCurrentSourceOrder']['orderByConsumed']),
    'unusable path order rowids empty' => static fn (TestRunner $t) => $t->same([], $unusable164()['currentGeneratedPathRowidCurrentSourceOrder']['orderedSeekRowids']),
    'unusable path order idx string keeps residual' => static fn (TestRunner $t) => $t->same('residual:id:IN|orderby:sorter', $unusable164()['currentGeneratedPathRowidCurrentSourceOrder']['idxStr']),
    'transition count records order state' => static fn (TestRunner $t) => $t->same(8, count($plan164()['generatedPathRowidCurrentSourceOrderTransitions'])),
    'transition order consumed changes' => static fn (TestRunner $t) => $t->same(true, $plan164()['generatedPathRowidCurrentSourceOrderTransitions'][0]['changed']),
    'transition direction changes' => static fn (TestRunner $t) => $t->same(true, $plan164()['generatedPathRowidCurrentSourceOrderTransitions'][1]['changed']),
    'transition sorter changes' => static fn (TestRunner $t) => $t->same(true, $plan164()['generatedPathRowidCurrentSourceOrderTransitions'][2]['changed']),
    'transition rowids change' => static fn (TestRunner $t) => $t->same(true, $plan164()['generatedPathRowidCurrentSourceOrderTransitions'][3]['changed']),
    'transition rows change' => static fn (TestRunner $t) => $t->same(true, $plan164()['generatedPathRowidCurrentSourceOrderTransitions'][4]['changed']),
    'transition cost changes' => static fn (TestRunner $t) => $t->same(true, $plan164()['generatedPathRowidCurrentSourceOrderTransitions'][5]['changed']),
    'transition cost class changes' => static fn (TestRunner $t) => $t->same(true, $plan164()['generatedPathRowidCurrentSourceOrderTransitions'][6]['changed']),
    'transition idx string changes' => static fn (TestRunner $t) => $t->same(true, $plan164()['generatedPathRowidCurrentSourceOrderTransitions'][7]['changed']),
    'reasons include order usage' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-order-usage-changed', $plan164()['next164ReplanReasons'], true)),
    'reasons include order rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-order-rowset-changed', $plan164()['next164ReplanReasons'], true)),
    'reasons include order cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-order-cost-changed', $plan164()['next164ReplanReasons'], true)),
    'reasons preserve admission usage' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-admission-usage-changed', $plan164()['next164ReplanReasons'], true)),
    'null next remains unrunnable' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $nullNext164()['nextGeneratedPathRowidCurrentSourceOrder']['costClass']),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan164(null, null, null, null, -1)),
    'bad order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan164(null, null, null, [['column' => 'id', 'direction' => 'SIDEWAYS']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next164 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
