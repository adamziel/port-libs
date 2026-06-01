<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current172 = [
    'setting_id' => 172,
    'key_name' => 'app_plugin_generated_path_rowid_cost_current_source_next172',
    'key_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next172 = [
    'setting_id' => 172,
    'key_name' => 'app_plugin_generated_path_rowid_cost_current_source_next172',
    'key_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"load_policy":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$constraints172 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6],
];
$order172 = [['column' => 'path'], ['column' => '_rowid_']];

$plan172 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSourceFence(
    'json_tree',
    $current ?? $current172,
    $next ?? $next172,
    'key_value',
    'generated_path',
    $constraints ?? $constraints172,
    'scan_root',
    $orderBy ?? $order172,
);

$stable172 = static fn (): array => $plan172($current172, $current172);
$residual172 = static fn (): array => $plan172(
    array_replace($current172, ['generated_path' => '$.rules']),
    array_replace($next172, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [4, 9]],
    ],
);
$movedRoot172 = static fn (): array => $plan172(
    $current172,
    array_replace($current172, ['generated_path' => '$.meta', 'scan_root' => '$']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
);

$tests = [
    'records next172 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next172', $plan172()['dependencies'], true)),
    'preserves next166 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next166', $plan172()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('yield-current-json-table-generated-path-rowid-source-fence-next172-until-xfilter-reset', $plan172()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-source-fence-next172-plan', $plan172()['nextReaderPolicy']),
    'stable reader policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-source-fence-next172-plan', $stable172()['nextReaderPolicy']),
    'stable next172 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable172()['next172ReplanReasons']),
    'current fence source id recorded' => static fn (TestRunner $t) => $t->same(172, $plan172()['currentGeneratedPathRowidSourceFence172']['sourceSettingId']),
    'current fence source name recorded' => static fn (TestRunner $t) => $t->same('app_plugin_generated_path_rowid_cost_current_source_next172', $plan172()['currentGeneratedPathRowidSourceFence172']['sourceKeyName']),
    'current fence root recorded' => static fn (TestRunner $t) => $t->same('$.rules', $plan172()['currentGeneratedPathRowidSourceFence172']['sourceRoot']),
    'current fence generated path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan172()['currentGeneratedPathRowidSourceFence172']['generatedPath']),
    'current fence yield decision recorded' => static fn (TestRunner $t) => $t->same('yield-current-source-generated-path-rowid-covering', $plan172()['currentGeneratedPathRowidSourceFence172']['yieldDecision']),
    'current fence cost class recorded' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-covering-point', $plan172()['currentGeneratedPathRowidSourceFence172']['costClass']),
    'current fence rowids retained' => static fn (TestRunner $t) => $t->same([6], $plan172()['currentGeneratedPathRowidSourceFence172']['yieldRowids']),
    'current fence paths retained' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $plan172()['currentGeneratedPathRowidSourceFence172']['yieldPaths']),
    'current source is pinned' => static fn (TestRunner $t) => $t->same(true, $plan172()['currentGeneratedPathRowidSourceFence172']['currentSourcePinned']),
    'current source reset not required' => static fn (TestRunner $t) => $t->same(false, $plan172()['currentGeneratedPathRowidSourceFence172']['sourceResetRequired']),
    'current retains rows' => static fn (TestRunner $t) => $t->same(true, $plan172()['currentGeneratedPathRowidSourceFence172']['retainsCurrentRows']),
    'current stale yield not blocked' => static fn (TestRunner $t) => $t->same(false, $plan172()['currentGeneratedPathRowidSourceFence172']['staleYieldBlocked']),
    'current reset generation zero' => static fn (TestRunner $t) => $t->same(0, $plan172()['currentGeneratedPathRowidSourceFence172']['resetGeneration']),
    'current fence token is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan172()['currentGeneratedPathRowidSourceFence172']['sourceFenceToken'])),
    'current stable yield key is sha256' => static fn (TestRunner $t) => $t->same(64, strlen($plan172()['currentGeneratedPathRowidSourceFence172']['stableYieldKey'])),
    'current source fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan172()['currentGeneratedPathRowidSourceFence172']['sourceFingerprint'])),
    'current cost fingerprint is sha256' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan172()['currentGeneratedPathRowidSourceFence172']['costFingerprint'])),
    'next fence generated path shifted' => static fn (TestRunner $t) => $t->same('$.rules[2]', $plan172()['nextGeneratedPathRowidSourceFence172']['generatedPath']),
    'next fence rowids empty' => static fn (TestRunner $t) => $t->same([], $plan172()['nextGeneratedPathRowidSourceFence172']['yieldRowids']),
    'next reset required' => static fn (TestRunner $t) => $t->same(true, $plan172()['nextGeneratedPathRowidSourceFence172']['sourceResetRequired']),
    'next retains no current rows' => static fn (TestRunner $t) => $t->same(false, $plan172()['nextGeneratedPathRowidSourceFence172']['retainsCurrentRows']),
    'next stale yield blocked' => static fn (TestRunner $t) => $t->same(true, $plan172()['nextGeneratedPathRowidSourceFence172']['staleYieldBlocked']),
    'next reset generation one' => static fn (TestRunner $t) => $t->same(1, $plan172()['nextGeneratedPathRowidSourceFence172']['resetGeneration']),
    'next fence token differs' => static fn (TestRunner $t) => $t->true($plan172()['currentGeneratedPathRowidSourceFence172']['sourceFenceToken'] !== $plan172()['nextGeneratedPathRowidSourceFence172']['sourceFenceToken']),
    'transition count records fence fields' => static fn (TestRunner $t) => $t->same(16, count($plan172()['generatedPathRowidSourceFence172Transitions'])),
    'generated path transition changes' => static fn (TestRunner $t) => $t->same(true, $plan172()['generatedPathRowidSourceFence172Transitions'][2]['changed']),
    'rowid transition changes' => static fn (TestRunner $t) => $t->same(true, $plan172()['generatedPathRowidSourceFence172Transitions'][5]['changed']),
    'source fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan172()['generatedPathRowidSourceFence172Transitions'][8]['changed']),
    'fence token transition changes' => static fn (TestRunner $t) => $t->same(true, $plan172()['generatedPathRowidSourceFence172Transitions'][10]['changed']),
    'reset generation transition changes' => static fn (TestRunner $t) => $t->same(true, $plan172()['generatedPathRowidSourceFence172Transitions'][11]['changed']),
    'retains transition changes' => static fn (TestRunner $t) => $t->same(true, $plan172()['generatedPathRowidSourceFence172Transitions'][13]['changed']),
    'stable key transition changes' => static fn (TestRunner $t) => $t->same(true, $plan172()['generatedPathRowidSourceFence172Transitions'][15]['changed']),
    'next172 reasons include source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-fence-source-changed', $plan172()['next172ReplanReasons'], true)),
    'next172 reasons include rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-fence-rowset-changed', $plan172()['next172ReplanReasons'], true)),
    'next172 reasons include admission change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-fence-admission-changed', $plan172()['next172ReplanReasons'], true)),
    'next172 reasons include token change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-fence-token-changed', $plan172()['next172ReplanReasons'], true)),
    'next172 preserves next166 rowset reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-rowset-changed', $plan172()['next172ReplanReasons'], true)),
    'residual current reset required because cursor is not pinned' => static fn (TestRunner $t) => $t->same(true, $residual172()['currentGeneratedPathRowidSourceFence172']['sourceResetRequired']),
    'residual current blocks stale yield' => static fn (TestRunner $t) => $t->same(true, $residual172()['currentGeneratedPathRowidSourceFence172']['staleYieldBlocked']),
    'residual current keeps rowset evidence' => static fn (TestRunner $t) => $t->same([4, 7, 5, 6, 8, 9], $residual172()['currentGeneratedPathRowidSourceFence172']['yieldRowids']),
    'moved root next source root recorded' => static fn (TestRunner $t) => $t->same('$', $movedRoot172()['nextGeneratedPathRowidSourceFence172']['sourceRoot']),
    'moved root next token changes' => static fn (TestRunner $t) => $t->true($movedRoot172()['currentGeneratedPathRowidSourceFence172']['sourceFenceToken'] !== $movedRoot172()['nextGeneratedPathRowidSourceFence172']['sourceFenceToken']),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSourceFence('json_tree', $current172, $next172, '', 'generated_path', $constraints172)),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSourceFence('json_tree', $current172, $next172, 'key_value', '', $constraints172)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next172 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
