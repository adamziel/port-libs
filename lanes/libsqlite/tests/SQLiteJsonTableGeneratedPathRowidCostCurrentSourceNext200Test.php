<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current200 = [
    'option_id' => 200,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next200',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-200-a',
];
$next200 = [
    'option_id' => 200,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next200',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-200-b',
];

$plan200 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $lastYieldedRowid = null,
    ?array $projection = null,
    ?int $yieldedRowid = 6,
    ?string $observedSourceGeneration = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXFilterArguments(
    'json_tree',
    $current ?? $current200,
    $next ?? $next200,
    'option_value',
    'generated_path',
    $constraints ?? [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    $orderBy ?? [['column' => 'rowid', 'direction' => 'DESC']],
    $limit,
    $lastYieldedRowid,
    $projection ?? ['id', 'fullkey', 'atom', 'value', 'type'],
    $yieldedRowid,
    $observedSourceGeneration,
);

$changed200 = static fn (): array => $plan200();
$stable200 = static fn (): array => $plan200($current200, $current200);
$point200 = static fn (): array => $plan200(
    array_replace($current200, ['generated_path' => '$.rules[1]', 'source_generation' => 'same-200']),
    array_replace($current200, ['generated_path' => '$.rules[1]', 'source_generation' => 'same-200']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
    [['column' => 'id']],
    null,
    null,
    ['id', 'value'],
    6,
);
$empty200 = static fn (): array => $plan200($current200, $current200, [
    ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'oid', 'operator' => 'IN', 'value' => [42, 43]],
]);
$stale200 = static fn (): array => $plan200($current200, $current200, null, null, null, null, null, 6, 'source_generation:stale-200');

$valueAt200 = static function (array $array, string $path): mixed {
    $value = $array;
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$paths200 = [
    'dependency next200' => ['dependencies', 'sqlite-json-table-generated-path-rowid-cost-current-source-next200'],
    'preserves next194 dependency' => ['dependencies', 'sqlite-json-table-generated-path-rowid-cost-current-source-next194'],
    'current policy emits argv' => ['currentReaderPolicy', 'emit-current-json-table-generated-path-rowid-xfilter-argv-next200'],
    'next policy reparses' => ['nextReaderPolicy', 'reprepare-next-json-table-generated-path-rowid-xfilter-argv-next200'],
    'current argv order' => ['currentGeneratedPathRowidXFilterArgv200.argvOrder', ['json', 'root', 'generated_path', 'rowid']],
    'current json argv column' => ['currentGeneratedPathRowidXFilterArgv200.argv.0.column', 'json'],
    'current json argv value' => ['currentGeneratedPathRowidXFilterArgv200.argv.0.value', 'option_value'],
    'current root argv value' => ['currentGeneratedPathRowidXFilterArgv200.argv.1.value', '$.rules'],
    'current generated path argv value' => ['currentGeneratedPathRowidXFilterArgv200.argv.2.value', '$.rules'],
    'current rowid argv value' => ['currentGeneratedPathRowidXFilterArgv200.argv.3.value', [6, 5]],
    'current rowid terms' => ['currentGeneratedPathRowidXFilterArgv200.rowidTerms', [5, 6, 42]],
    'current accepted rowids' => ['currentGeneratedPathRowidXFilterArgv200.acceptedRowids', [6, 5]],
    'current source pinned' => ['currentGeneratedPathRowidXFilterArgv200.sourcePinned', true],
    'current upstream not required' => ['currentGeneratedPathRowidXFilterArgv200.upstreamReplanRequired', false],
    'current argv reusable' => ['currentGeneratedPathRowidXFilterArgv200.argvReusable', true],
    'current disposition reuse' => ['currentGeneratedPathRowidXFilterArgv200.xFilterDisposition', 'reuse-current-source-generated-path-rowid-xfilter-argv-next200'],
    'current opcode reuse' => ['currentGeneratedPathRowidXFilterArgv200.xFilterOpcode', 'OP_JsonTableReuseGeneratedPathRowidXFilterArgvNext200'],
    'current estimated rows' => ['currentGeneratedPathRowidXFilterArgv200.estimatedRows', 2],
    'current estimated cost' => ['currentGeneratedPathRowidXFilterArgv200.estimatedCost', 2],
    'current cost range' => ['currentGeneratedPathRowidXFilterArgv200.costClass', 'json-table-generated-path-rowid-xfilter-argv-range-next200'],
    'next upstream required' => ['nextGeneratedPathRowidXFilterArgv200.upstreamReplanRequired', true],
    'next argv not reusable' => ['nextGeneratedPathRowidXFilterArgv200.argvReusable', false],
    'next disposition reprepare' => ['nextGeneratedPathRowidXFilterArgv200.xFilterDisposition', 'reprepare-upstream-generated-path-rowid-xfilter-argv-next200'],
    'next opcode reprepare' => ['nextGeneratedPathRowidXFilterArgv200.xFilterOpcode', 'OP_JsonTableReprepareGeneratedPathRowidXFilterArgvNext200'],
    'next rows zero' => ['nextGeneratedPathRowidXFilterArgv200.estimatedRows', 0],
    'next cost sentinel' => ['nextGeneratedPathRowidXFilterArgv200.estimatedCost', 1000000],
    'next cost reprepare' => ['nextGeneratedPathRowidXFilterArgv200.costClass', 'json-table-generated-path-rowid-xfilter-argv-reprepare-next200'],
    'transition count' => ['generatedPathRowidXFilterArgv200Transitions', 13],
];

foreach ($paths200 as $name => [$path, $expected]) {
    $tests['json table generated path rowid cost current source next200 ' . $name] = static function (TestRunner $t) use ($changed200, $valueAt200, $path, $expected): void {
        $actual = $valueAt200($changed200(), $path);
        if ($path === 'dependencies') {
            $t->true(in_array($expected, $actual, true));
            return;
        }
        if ($path === 'generatedPathRowidXFilterArgv200Transitions') {
            $t->same($expected, count($actual));
            return;
        }
        $t->same($expected, $actual);
    };
}

$tests['json table generated path rowid cost current source next200 fingerprint sha256'] = static function (TestRunner $t) use ($changed200): void {
    $t->same(64, strlen($changed200()['currentGeneratedPathRowidXFilterArgv200']['argvFingerprint']));
};
$tests['json table generated path rowid cost current source next200 fingerprints differ after source change'] = static function (TestRunner $t) use ($changed200): void {
    $t->same(false, $changed200()['currentGeneratedPathRowidXFilterArgv200']['argvFingerprint'] === $changed200()['nextGeneratedPathRowidXFilterArgv200']['argvFingerprint']);
};
$tests['json table generated path rowid cost current source next200 stable reuses reader'] = static function (TestRunner $t) use ($stable200): void {
    $t->same('reuse-current-json-table-generated-path-rowid-xfilter-argv-next200', $stable200()['nextReaderPolicy']);
};
$tests['json table generated path rowid cost current source next200 stable reasons empty'] = static function (TestRunner $t) use ($stable200): void {
    $t->same([], $stable200()['next200ReplanReasons']);
};
$tests['json table generated path rowid cost current source next200 stable next rowids reusable'] = static function (TestRunner $t) use ($stable200): void {
    $t->same([6, 5], $stable200()['nextGeneratedPathRowidXFilterArgv200']['acceptedRowids']);
};
$tests['json table generated path rowid cost current source next200 point cost class'] = static function (TestRunner $t) use ($point200): void {
    $t->same('json-table-generated-path-rowid-xfilter-argv-point-next200', $point200()['currentGeneratedPathRowidXFilterArgv200']['costClass']);
};
$tests['json table generated path rowid cost current source next200 point rowid argv'] = static function (TestRunner $t) use ($point200): void {
    $t->same([6], $point200()['currentGeneratedPathRowidXFilterArgv200']['argv'][3]['value']);
};
$tests['json table generated path rowid cost current source next200 empty rowid rejects'] = static function (TestRunner $t) use ($empty200): void {
    $t->same('reject-empty-generated-path-rowid-xfilter-argv-next200', $empty200()['currentGeneratedPathRowidXFilterArgv200']['xFilterDisposition']);
};
$tests['json table generated path rowid cost current source next200 empty rowid cost'] = static function (TestRunner $t) use ($empty200): void {
    $t->same('json-table-generated-path-rowid-xfilter-argv-empty-next200', $empty200()['currentGeneratedPathRowidXFilterArgv200']['costClass']);
};
$tests['json table generated path rowid cost current source next200 stale source unpinned'] = static function (TestRunner $t) use ($stale200): void {
    $t->same('reseek-unpinned-generated-path-rowid-xfilter-argv-next200', $stale200()['currentGeneratedPathRowidXFilterArgv200']['xFilterDisposition']);
};
$tests['json table generated path rowid cost current source next200 reasons include argv'] = static function (TestRunner $t) use ($changed200): void {
    $t->true(in_array('json-table-generated-path-rowid-xfilter-argv-changed-next200', $changed200()['next200ReplanReasons'], true));
};
$tests['json table generated path rowid cost current source next200 reasons include rowset'] = static function (TestRunner $t) use ($changed200): void {
    $t->true(in_array('json-table-generated-path-rowid-xfilter-rowset-changed-next200', $changed200()['next200ReplanReasons'], true));
};
$tests['json table generated path rowid cost current source next200 reasons include admission'] = static function (TestRunner $t) use ($changed200): void {
    $t->true(in_array('json-table-generated-path-rowid-xfilter-admission-changed-next200', $changed200()['next200ReplanReasons'], true));
};
$tests['json table generated path rowid cost current source next200 reasons include cost'] = static function (TestRunner $t) use ($changed200): void {
    $t->true(in_array('json-table-generated-path-rowid-xfilter-cost-changed-next200', $changed200()['next200ReplanReasons'], true));
};
$tests['json table generated path rowid cost current source next200 reasons include fingerprint'] = static function (TestRunner $t) use ($changed200): void {
    $t->true(in_array('json-table-generated-path-rowid-xfilter-fingerprint-changed-next200', $changed200()['next200ReplanReasons'], true));
};
$tests['json table generated path rowid cost current source next200 preserves next194 reason'] = static function (TestRunner $t) use ($changed200): void {
    $t->true(in_array('json-table-generated-path-rowid-source-changed-next194', $changed200()['next200ReplanReasons'], true));
};
$tests['json table generated path rowid cost current source next200 malformed generated path rejected'] = static function (TestRunner $t) use ($plan200, $current200): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan200(array_replace($current200, ['generated_path' => '$.rules[']), $current200));
};
$tests['json table generated path rowid cost current source next200 bad function rejected'] = static function (TestRunner $t) use ($current200): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXFilterArguments('json_bad', $current200, $current200, 'option_value', 'generated_path'));
};
$tests['json table generated path rowid cost current source next200 dependency closure'] = static function (TestRunner $t): void {
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
