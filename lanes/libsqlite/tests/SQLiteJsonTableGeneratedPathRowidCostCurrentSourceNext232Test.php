<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current232 = [
    'option_id' => 232,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next232',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-232-a',
];
$next232 = [
    'option_id' => 232,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next232',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"security","priority":10},{"slug":"search","priority":6}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-232-b',
];

$plan232 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?string $observedBatchToken = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext232(
    'json_tree',
    $current ?? $current232,
    $next ?? $next232,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'ASC']],
    5,
    null,
    3,
    ['rowid', 'value', 'type', 'fullkey'],
    null,
    null,
    null,
    null,
    $observedBatchToken,
);

$stable232 = static fn (): array => $plan232($current232, $current232);
$staleToken232 = static fn (): array => $plan232($current232, $current232, str_repeat('0', 64));
$currentProfile232 = static fn (): array => $plan232()['currentGeneratedPathRowidCurrentSourceBatch232'];
$nextProfile232 = static fn (): array => $plan232()['nextGeneratedPathRowidCurrentSourceBatch232'];

$tests = [
    'records next228 through next232 dependencies' => static function (TestRunner $t) use ($plan232): void {
        foreach ([228, 229, 230, 231, 232] as $next) {
            $t->true(in_array("sqlite-json-table-generated-path-rowid-cost-current-source-next{$next}", $plan232()['dependencies'], true));
        }
    },
    'preserves next227 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next227', $plan232()['dependencies'], true)),
    'current reader policy guards batch token' => static fn (TestRunner $t) => $t->same('batch-token-json-table-generated-path-rowid-current-source-next232', $plan232()['currentReaderPolicy']),
    'changed next reader restarts' => static fn (TestRunner $t) => $t->same('restart-batch-token-json-table-generated-path-rowid-current-source-next232', $plan232()['nextReaderPolicy']),
    'stable next reader reuses' => static fn (TestRunner $t) => $t->same('reuse-batch-token-json-table-generated-path-rowid-current-source-next232', $stable232()['nextReaderPolicy']),
    'stable next232 reasons empty' => static fn (TestRunner $t) => $t->same([], $stable232()['next232ReplanReasons']),
    'current batch token sha256' => static fn (TestRunner $t) => $t->same(64, strlen($currentProfile232()['actualBatchToken'])),
    'current batch token matches' => static fn (TestRunner $t) => $t->same(true, $currentProfile232()['batchTokenMatches']),
    'current batch reusable' => static fn (TestRunner $t) => $t->same(true, $currentProfile232()['batchReusable']),
    'current batch rowid delivered' => static fn (TestRunner $t) => $t->same([7], $currentProfile232()['batchRowids']),
    'current batch projection retained' => static fn (TestRunner $t) => $t->same('$.rules[2]', $currentProfile232()['batchProjectedColumns']['fullkey']),
    'current batch opcode delivers' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceBatchDeliverNext232', $currentProfile232()['batchOpcode']),
    'next batch reprepare' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceBatchReprepareNext232', $nextProfile232()['batchOpcode']),
    'next batch not reusable' => static fn (TestRunner $t) => $t->same(false, $nextProfile232()['batchReusable']),
    'next batch cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $nextProfile232()['estimatedCost']),
    'reasons include batch admission changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-batch-admission-changed-next232', $plan232()['next232ReplanReasons'], true)),
    'reasons preserve source generation changed' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-current-source-generation-changed-next227', $plan232()['next232ReplanReasons'], true)),
    'stale token mismatch' => static fn (TestRunner $t) => $t->same(false, $staleToken232()['currentGeneratedPathRowidCurrentSourceBatch232']['batchTokenMatches']),
    'stale token restarts' => static fn (TestRunner $t) => $t->same('OP_JsonTableGeneratedPathRowidCurrentSourceBatchRestartTokenNext232', $staleToken232()['currentGeneratedPathRowidCurrentSourceBatch232']['batchOpcode']),
    'bad token rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan232($current232, $current232, 'bad-token')),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next232 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
