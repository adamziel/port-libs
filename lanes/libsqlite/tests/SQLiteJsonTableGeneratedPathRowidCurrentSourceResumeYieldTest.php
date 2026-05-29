<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current178 = [
    'option_id' => 178,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next178',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 17,
];
$next178 = [
    'option_id' => 178,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next178',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 18,
];

$plan178 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $lastYieldedRowid = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceResumeYieldPlan(
    'json_tree',
    $current ?? $current178,
    $next ?? $next178,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
);

$resume178 = static fn (): array => $plan178(null, null, null, null, null, 6);
$stable178 = static fn (): array => $plan178($current178, $current178, null, null, null, 6);
$first178 = static fn (): array => $plan178($current178, $current178);
$eof178 = static fn (): array => $plan178($current178, $current178, null, null, null, 5);
$missing178 = static fn (): array => $plan178($current178, $current178, null, null, null, 99);
$unusable178 = static fn (): array => $plan178(
    $current178,
    $current178,
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%', 'usable' => false],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6]],
    ],
    [['column' => 'id', 'direction' => 'DESC']],
    null,
    6,
);
$point178 = static fn (): array => $plan178(
    array_replace($current178, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    array_replace($current178, ['generated_path' => '$.rules[1]', 'source_generation' => 'same']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
    null,
    6,
);

$tests = [
    'records next178 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next178', $resume178()['dependencies'], true)),
    'preserves next175 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next175', $resume178()['dependencies'], true)),
    'current reader policy resumes yield' => static fn (TestRunner $t) => $t->same('resume-current-json-table-generated-path-rowid-cost-current-source-next178-yield', $resume178()['currentReaderPolicy']),
    'changed next reader policy restarts filter' => static fn (TestRunner $t) => $t->same('restart-next-json-table-generated-path-rowid-cost-current-source-next178-filter', $resume178()['nextReaderPolicy']),
    'stable next reader policy resumes yield' => static fn (TestRunner $t) => $t->same('resume-current-json-table-generated-path-rowid-cost-current-source-next178-yield', $stable178()['nextReaderPolicy']),
    'stable has no next178 reasons' => static fn (TestRunner $t) => $t->same([], $stable178()['next178ReplanReasons']),
    'current yield records last rowid' => static fn (TestRunner $t) => $t->same(6, $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['lastYieldedRowid']),
    'current yield records ordinal' => static fn (TestRunner $t) => $t->same(0, $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['lastYieldedOrdinal']),
    'current yield resumes after ordinal' => static fn (TestRunner $t) => $t->same(1, $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['resumeOrdinal']),
    'current yield remaining rowids' => static fn (TestRunner $t) => $t->same([5], $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['remainingRowids']),
    'current yield yielded rowids' => static fn (TestRunner $t) => $t->same([6], $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['yieldedRowids']),
    'current yield no skipped rowids' => static fn (TestRunner $t) => $t->same([], $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['skippedRowids']),
    'current yield not eof' => static fn (TestRunner $t) => $t->same(false, $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['eofAfterYield']),
    'current yield xfilter reusable' => static fn (TestRunner $t) => $t->same(true, $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['xFilterReusable']),
    'current yield not stale' => static fn (TestRunner $t) => $t->same(false, $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['staleAfterNextSource']),
    'current yield resume mode' => static fn (TestRunner $t) => $t->same('resume-xnext-from-pinned-current-source', $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['resumeMode']),
    'current yield fence' => static fn (TestRunner $t) => $t->same('current-source-generated-path-rowid-yield-fence', $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['replanFence']),
    'current yield generation hash' => static fn (TestRunner $t) => $t->same(64, strlen($resume178()['currentGeneratedPathRowidCurrentSourceYield178']['cursorGeneration'])),
    'current yield cost one' => static fn (TestRunner $t) => $t->same(1, $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['yieldCost']),
    'current yield cost class single' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-single-current-source', $resume178()['currentGeneratedPathRowidCurrentSourceYield178']['costClass']),
    'next yield stale after next source' => static fn (TestRunner $t) => $t->same(true, $resume178()['nextGeneratedPathRowidCurrentSourceYield178']['staleAfterNextSource']),
    'next yield not reusable' => static fn (TestRunner $t) => $t->same(false, $resume178()['nextGeneratedPathRowidCurrentSourceYield178']['xFilterReusable']),
    'next yield restart mode' => static fn (TestRunner $t) => $t->same('restart-xfilter-for-next-source', $resume178()['nextGeneratedPathRowidCurrentSourceYield178']['resumeMode']),
    'next yield restart fence' => static fn (TestRunner $t) => $t->same('next-source-generated-path-rowid-cache-fence', $resume178()['nextGeneratedPathRowidCurrentSourceYield178']['replanFence']),
    'next yield cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $resume178()['nextGeneratedPathRowidCurrentSourceYield178']['yieldCost']),
    'next yield cost class restart' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-restart-next-source', $resume178()['nextGeneratedPathRowidCurrentSourceYield178']['costClass']),
    'first yield starts at ordinal zero' => static fn (TestRunner $t) => $t->same(0, $first178()['currentGeneratedPathRowidCurrentSourceYield178']['resumeOrdinal']),
    'first yield keeps all rowids' => static fn (TestRunner $t) => $t->same([6, 5], $first178()['currentGeneratedPathRowidCurrentSourceYield178']['remainingRowids']),
    'first yield mode' => static fn (TestRunner $t) => $t->same('yield-first-row-from-pinned-current-source', $first178()['currentGeneratedPathRowidCurrentSourceYield178']['resumeMode']),
    'first yield cost class resume' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-resume-current-source', $first178()['currentGeneratedPathRowidCurrentSourceYield178']['costClass']),
    'eof yield has ordinal one' => static fn (TestRunner $t) => $t->same(1, $eof178()['currentGeneratedPathRowidCurrentSourceYield178']['lastYieldedOrdinal']),
    'eof yield has no remaining rowids' => static fn (TestRunner $t) => $t->same([], $eof178()['currentGeneratedPathRowidCurrentSourceYield178']['remainingRowids']),
    'eof yield true' => static fn (TestRunner $t) => $t->same(true, $eof178()['currentGeneratedPathRowidCurrentSourceYield178']['eofAfterYield']),
    'eof yield mode' => static fn (TestRunner $t) => $t->same('yield-eof-from-pinned-current-source', $eof178()['currentGeneratedPathRowidCurrentSourceYield178']['resumeMode']),
    'eof yield cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-eof-current-source', $eof178()['currentGeneratedPathRowidCurrentSourceYield178']['costClass']),
    'missing rowid has null ordinal' => static fn (TestRunner $t) => $t->same(null, $missing178()['currentGeneratedPathRowidCurrentSourceYield178']['lastYieldedOrdinal']),
    'missing rowid skips current tape' => static fn (TestRunner $t) => $t->same([6, 5], $missing178()['currentGeneratedPathRowidCurrentSourceYield178']['skippedRowids']),
    'missing rowid reseeks' => static fn (TestRunner $t) => $t->same('reseek-xfilter-after-missing-rowid', $missing178()['currentGeneratedPathRowidCurrentSourceYield178']['resumeMode']),
    'missing rowid not reusable' => static fn (TestRunner $t) => $t->same(false, $missing178()['currentGeneratedPathRowidCurrentSourceYield178']['xFilterReusable']),
    'missing rowid cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $missing178()['currentGeneratedPathRowidCurrentSourceYield178']['yieldCost']),
    'unusable current reseeks after missing rowid' => static fn (TestRunner $t) => $t->same('reseek-xfilter-after-missing-rowid', $unusable178()['currentGeneratedPathRowidCurrentSourceYield178']['resumeMode']),
    'unusable current not reusable' => static fn (TestRunner $t) => $t->same(false, $unusable178()['currentGeneratedPathRowidCurrentSourceYield178']['xFilterReusable']),
    'unusable current cost class reseek' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-yield-reseek-current-source', $unusable178()['currentGeneratedPathRowidCurrentSourceYield178']['costClass']),
    'point eof after yielded point' => static fn (TestRunner $t) => $t->same(true, $point178()['currentGeneratedPathRowidCurrentSourceYield178']['eofAfterYield']),
    'point yielded rowids' => static fn (TestRunner $t) => $t->same([6], $point178()['currentGeneratedPathRowidCurrentSourceYield178']['yieldedRowids']),
    'transition count records yield state' => static fn (TestRunner $t) => $t->same(11, count($resume178()['generatedPathRowidCurrentSourceYield178Transitions'])),
    'transition generation changes' => static fn (TestRunner $t) => $t->same(true, $resume178()['generatedPathRowidCurrentSourceYield178Transitions'][0]['changed']),
    'transition mode changes' => static fn (TestRunner $t) => $t->same(true, $resume178()['generatedPathRowidCurrentSourceYield178Transitions'][1]['changed']),
    'transition remaining rowids changes' => static fn (TestRunner $t) => $t->same(true, $resume178()['generatedPathRowidCurrentSourceYield178Transitions'][2]['changed']),
    'transition yielded rowids changes' => static fn (TestRunner $t) => $t->same(true, $resume178()['generatedPathRowidCurrentSourceYield178Transitions'][3]['changed']),
    'transition skipped rowids stable' => static fn (TestRunner $t) => $t->same(false, $resume178()['generatedPathRowidCurrentSourceYield178Transitions'][4]['changed']),
    'transition stale flag changes' => static fn (TestRunner $t) => $t->same(true, $resume178()['generatedPathRowidCurrentSourceYield178Transitions'][7]['changed']),
    'transition fence changes' => static fn (TestRunner $t) => $t->same(true, $resume178()['generatedPathRowidCurrentSourceYield178Transitions'][8]['changed']),
    'transition yield cost changes' => static fn (TestRunner $t) => $t->same(true, $resume178()['generatedPathRowidCurrentSourceYield178Transitions'][9]['changed']),
    'reasons include source fence' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-source-fence-changed', $resume178()['next178ReplanReasons'], true)),
    'reasons include resume mode' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-resume-mode-changed', $resume178()['next178ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-rowset-changed', $resume178()['next178ReplanReasons'], true)),
    'reasons include yield cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-yield-cost-changed', $resume178()['next178ReplanReasons'], true)),
    'reasons preserve cache fence' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cache-key-changed', $resume178()['next178ReplanReasons'], true)),
    'negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan178(null, null, null, null, -1)),
    'bad order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan178(null, null, null, [['column' => 'id', 'direction' => 'SIDEWAYS']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next178 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
