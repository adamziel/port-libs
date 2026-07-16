<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current188 = [
    'option_id' => 188,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next188',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-188-a',
];
$next188 = [
    'option_id' => 188,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next188',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-188-b',
];

$plan188 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 1,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidDeletedResume(
    'json_tree',
    $current ?? $current188,
    $next ?? $next188,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ],
    'scan_root',
    $orderBy ?? [['column' => '_rowid_', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $yieldBatchSize,
    $projection ?? ['key', 'value', 'type', 'id', 'fullkey', 'path'],
);

$stable188 = static fn (): array => $plan188($current188, $current188);
$sameRowsNewGeneration188 = static fn (): array => $plan188(
    $current188,
    array_replace($current188, ['source_generation' => 'next-188-generation-only']),
);
$projection188 = static fn (): array => $plan188($current188, $next188, null, null, 5, 9, 1, ['value', 'atom', 'id']);

$valueAt188 = static function (array $array, string $path): mixed {
    $value = $array;
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$paths188 = [
    'current policy' => ['currentReaderPolicy', 'preserve-current-json-table-generated-path-rowid-deleted-resume-next188'],
    'next policy restart' => ['nextReaderPolicy', 'restart-next-json-table-generated-path-rowid-deleted-resume-next188'],
    'function' => ['generatedPathRowidDeletedResume188.function', 'json_tree'],
    'current candidate rowids' => ['generatedPathRowidDeletedResume188.currentCandidateRowids', [5, 6, 7, 8]],
    'next source rowids' => ['generatedPathRowidDeletedResume188.nextSourceRowids', [0, 1, 2, 3]],
    'deleted rowids' => ['generatedPathRowidDeletedResume188.deletedRowids', [5, 6, 7, 8]],
    'retained rowids' => ['generatedPathRowidDeletedResume188.retainedRowids', []],
    'inserted rowids' => ['generatedPathRowidDeletedResume188.insertedRowids', [0, 1, 2, 3]],
    'restart required' => ['generatedPathRowidDeletedResume188.restartRequired', true],
    'checkpoint not reusable' => ['generatedPathRowidDeletedResume188.checkpointReusable', false],
    'deleted count' => ['generatedPathRowidDeletedResume188.deletedRowidCount', 4],
    'retained count' => ['generatedPathRowidDeletedResume188.retainedRowidCount', 0],
    'inserted count' => ['generatedPathRowidDeletedResume188.insertedRowidCount', 4],
    'estimated rows zero' => ['generatedPathRowidDeletedResume188.estimatedRows', 0],
    'estimated cost sentinel' => ['generatedPathRowidDeletedResume188.estimatedCost', 1000000],
    'cost class deleted restart' => ['generatedPathRowidDeletedResume188.costClass', 'json-table-generated-path-rowid-deleted-resume-restart-next188'],
    'last delivered rowid' => ['generatedPathRowidDeletedResume188.lastDeliveredRowid', 8],
    'next resume ordinal' => ['generatedPathRowidDeletedResume188.nextResumeOrdinal', 2],
];

$tests = [];

foreach ($paths188 as $name => [$path, $expected]) {
    $tests['json table generated path rowid deleted resume ' . $name] = static function (TestRunner $t) use ($plan188, $valueAt188, $path, $expected): void {
        $t->same($expected, $valueAt188($plan188(), $path));
    };
}

$tests['json table generated path rowid deleted resume records dependency'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next188', $plan188()['dependencies'], true));
};
$tests['json table generated path rowid deleted resume preserves path constraint dependency'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('sqlite-json-table-path-constraint-pushdown-current-source-next123', $plan188()['dependencies'], true));
};
$tests['json table generated path rowid deleted resume preserves next185 dependency'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next185', $plan188()['dependencies'], true));
};
$tests['json table generated path rowid deleted resume current fingerprint is sha256'] = static function (TestRunner $t) use ($plan188): void {
    $t->same(64, strlen($plan188()['generatedPathRowidDeletedResume188']['currentSourceFingerprint']));
};
$tests['json table generated path rowid deleted resume next fingerprint is sha256'] = static function (TestRunner $t) use ($plan188): void {
    $t->same(64, strlen($plan188()['generatedPathRowidDeletedResume188']['nextSourceFingerprint']));
};
$tests['json table generated path rowid deleted resume fingerprints differ'] = static function (TestRunner $t) use ($plan188): void {
    $t->same(false, $plan188()['generatedPathRowidDeletedResume188']['currentSourceFingerprint'] === $plan188()['generatedPathRowidDeletedResume188']['nextSourceFingerprint']);
};
$tests['json table generated path rowid deleted resume resume token preserved'] = static function (TestRunner $t) use ($plan188): void {
    $t->same($plan188()['currentGeneratedPathRowidCurrentSourceResume185']['resumeToken'], $plan188()['generatedPathRowidDeletedResume188']['resumeToken']);
};
$tests['json table generated path rowid deleted resume projected row still comes from current checkpoint'] = static function (TestRunner $t) use ($plan188): void {
    $t->same('forms', $plan188()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['value']);
};
$tests['json table generated path rowid deleted resume projection survives narrowed checkpoint'] = static function (TestRunner $t) use ($projection188): void {
    $t->same(['value', 'atom', 'id'], $projection188()['currentGeneratedPathRowidCurrentSourceResume185']['projection']);
};
$tests['json table generated path rowid deleted resume narrowed projection atom'] = static function (TestRunner $t) use ($projection188): void {
    $t->same('forms', $projection188()['currentGeneratedPathRowidCurrentSourceResume185']['projectedRows'][0]['atom']);
};
$tests['json table generated path rowid deleted resume reasons include deleted rowid'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('json-table-generated-path-rowid-deleted-rowid-next188', $plan188()['generatedPathRowidDeletedResume188']['replanReasons'], true));
};
$tests['json table generated path rowid deleted resume reasons include inserted rowid'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('json-table-generated-path-rowid-inserted-rowid-next188', $plan188()['generatedPathRowidDeletedResume188']['replanReasons'], true));
};
$tests['json table generated path rowid deleted resume reasons include fingerprint'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('json-table-generated-path-rowid-source-fingerprint-next188', $plan188()['generatedPathRowidDeletedResume188']['replanReasons'], true));
};
$tests['json table generated path rowid deleted resume reasons include stale next source'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('json-table-generated-path-rowid-next-source-stale-next188', $plan188()['generatedPathRowidDeletedResume188']['replanReasons'], true));
};
$tests['json table generated path rowid deleted resume reasons include checkpoint restart'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('json-table-generated-path-rowid-checkpoint-restart-next188', $plan188()['generatedPathRowidDeletedResume188']['replanReasons'], true));
};
$tests['json table generated path rowid deleted resume top-level reasons include deleted rowid'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('json-table-generated-path-rowid-deleted-rowid-next188', $plan188()['next188ReplanReasons'], true));
};
$tests['json table generated path rowid deleted resume top-level reasons include checkpoint restart'] = static function (TestRunner $t) use ($plan188): void {
    $t->true(in_array('json-table-generated-path-rowid-checkpoint-restart-next188', $plan188()['next188ReplanReasons'], true));
};
$tests['json table generated path rowid deleted resume stable resumes'] = static function (TestRunner $t) use ($stable188): void {
    $t->same('resume-current-json-table-generated-path-rowid-deleted-resume-next188', $stable188()['nextReaderPolicy']);
};
$tests['json table generated path rowid deleted resume stable checkpoint reusable'] = static function (TestRunner $t) use ($stable188): void {
    $t->same(true, $stable188()['generatedPathRowidDeletedResume188']['checkpointReusable']);
};
$tests['json table generated path rowid deleted resume stable deleted rowids empty'] = static function (TestRunner $t) use ($stable188): void {
    $t->same([], $stable188()['generatedPathRowidDeletedResume188']['deletedRowids']);
};
$tests['json table generated path rowid deleted resume stable cost reusable'] = static function (TestRunner $t) use ($stable188): void {
    $t->same('json-table-generated-path-rowid-deleted-resume-reusable-next188', $stable188()['generatedPathRowidDeletedResume188']['costClass']);
};
$tests['json table generated path rowid deleted resume stable estimated rows retained'] = static function (TestRunner $t) use ($stable188): void {
    $t->same(4, $stable188()['generatedPathRowidDeletedResume188']['estimatedRows']);
};
$tests['json table generated path rowid deleted resume generation only restarts by source'] = static function (TestRunner $t) use ($sameRowsNewGeneration188): void {
    $t->same('json-table-generated-path-rowid-inserted-resume-restart-next188', $sameRowsNewGeneration188()['generatedPathRowidDeletedResume188']['costClass']);
};
$tests['json table generated path rowid deleted resume generation only keeps rowids'] = static function (TestRunner $t) use ($sameRowsNewGeneration188): void {
    $t->same([], $sameRowsNewGeneration188()['generatedPathRowidDeletedResume188']['deletedRowids']);
};
$tests['json table generated path rowid deleted resume generation only has fingerprint reason'] = static function (TestRunner $t) use ($sameRowsNewGeneration188): void {
    $t->true(in_array('json-table-generated-path-rowid-source-fingerprint-next188', $sameRowsNewGeneration188()['generatedPathRowidDeletedResume188']['replanReasons'], true));
};
$tests['json table generated path rowid deleted resume bad projection rejected'] = static function (TestRunner $t) use ($plan188): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan188(null, null, null, null, 5, 9, 1, ['missing_column']));
};
$tests['json table generated path rowid deleted resume bad root rejected'] = static function (TestRunner $t) use ($plan188, $current188): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan188(array_replace($current188, ['scan_root' => 12]), $current188));
};
$tests['json table generated path rowid deleted resume bad function rejected'] = static function (TestRunner $t) use ($current188): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidDeletedResume('json_bad', $current188, $current188, 'option_value', 'generated_path'));
};
$tests['json table generated path rowid deleted resume dependency closure'] = static function (TestRunner $t): void {
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
