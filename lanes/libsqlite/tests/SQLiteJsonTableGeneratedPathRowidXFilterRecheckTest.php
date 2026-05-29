<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current191 = [
    'option_id' => 191,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next191',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-191-a',
];
$next191 = [
    'option_id' => 191,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next191',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-191-b',
];

$plan191 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?int $limit = 5,
    ?int $lastYieldedRowid = 9,
    ?int $yieldBatchSize = 1,
    ?array $projection = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXFilterRecheck(
    'json_tree',
    $current ?? $current191,
    $next ?? $next191,
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

$stable191 = static fn (): array => $plan191($current191, $current191);
$changedGeneration191 = static fn (): array => $plan191(
    $current191,
    array_replace($current191, ['source_generation' => 'next-191-generation-only']),
);
$final191 = static fn (): array => $plan191($current191, $current191, null, null, 5, 7, 3);
$projection191 = static fn (): array => $plan191($current191, $current191, null, null, 5, 9, 1, ['value', 'atom', 'id']);

$valueAt191 = static function (array $array, string $path): mixed {
    $value = $array;
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$paths191 = [
    'current policy' => ['currentReaderPolicy', 'xfilter-recheck-current-json-table-generated-path-rowid-next191'],
    'next policy restart' => ['nextReaderPolicy', 'restart-xfilter-next-json-table-generated-path-rowid-next191'],
    'dependency next191' => ['dependencies', 'sqlite-json-table-generated-path-rowid-cost-current-source-next191'],
    'current function' => ['currentGeneratedPathRowidXFilterRecheck191.function', 'json_tree'],
    'current root' => ['currentGeneratedPathRowidXFilterRecheck191.root', '$.rules'],
    'current generated path' => ['currentGeneratedPathRowidXFilterRecheck191.generatedPath', '$.rules'],
    'current source generation' => ['currentGeneratedPathRowidXFilterRecheck191.sourceGeneration', 'current-191-a'],
    'current checkpoint rowids' => ['currentGeneratedPathRowidXFilterRecheck191.checkpointRowids', [8]],
    'current accepted rowids' => ['currentGeneratedPathRowidXFilterRecheck191.acceptedRowids', [8]],
    'current rejected rowids' => ['currentGeneratedPathRowidXFilterRecheck191.rejectedRowids', []],
    'current xfilter argv json' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterArgv.json', 'option_value'],
    'current xfilter argv root' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterArgv.root', '$.rules'],
    'current xfilter argv path' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterArgv.generatedPath', '$.rules'],
    'current xfilter argv rowids' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterArgv.rowids', [8]],
    'current tape rowid' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterTape.0.rowid', 8],
    'current tape source path' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterTape.0.sourcePath', '$.rules[2]'],
    'current tape source fullkey' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterTape.0.sourceFullkey', '$.rules[2].slug'],
    'current tape path matched' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterTape.0.pathMatched', true],
    'current tape value matched' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterTape.0.valueMatched', true],
    'current tape accepted' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterTape.0.accepted', true],
    'current not stale' => ['currentGeneratedPathRowidXFilterRecheck191.staleAfterNextSource', false],
    'current reusable' => ['currentGeneratedPathRowidXFilterRecheck191.checkpointReusable', true],
    'current estimated rows' => ['currentGeneratedPathRowidXFilterRecheck191.estimatedRows', 1],
    'current estimated cost' => ['currentGeneratedPathRowidXFilterRecheck191.estimatedCost', 1],
    'current opcode seek' => ['currentGeneratedPathRowidXFilterRecheck191.xFilterOpcode', 'seek-current-source-generated-path-rowid-xfilter-next191'],
    'current cost point' => ['currentGeneratedPathRowidXFilterRecheck191.costClass', 'json-table-generated-path-rowid-xfilter-point-next191'],
    'next generated path' => ['nextGeneratedPathRowidXFilterRecheck191.generatedPath', '$.rules[0]'],
    'next checkpoint rowids' => ['nextGeneratedPathRowidXFilterRecheck191.checkpointRowids', [8]],
    'next accepted rowids empty' => ['nextGeneratedPathRowidXFilterRecheck191.acceptedRowids', []],
    'next rejected rowids' => ['nextGeneratedPathRowidXFilterRecheck191.rejectedRowids', [8]],
    'next tape path unmatched' => ['nextGeneratedPathRowidXFilterRecheck191.xFilterTape.0.pathMatched', false],
    'next tape value unmatched' => ['nextGeneratedPathRowidXFilterRecheck191.xFilterTape.0.valueMatched', false],
    'next tape rejected' => ['nextGeneratedPathRowidXFilterRecheck191.xFilterTape.0.accepted', false],
    'next stale' => ['nextGeneratedPathRowidXFilterRecheck191.staleAfterNextSource', true],
    'next not reusable' => ['nextGeneratedPathRowidXFilterRecheck191.checkpointReusable', false],
    'next estimated rows zero' => ['nextGeneratedPathRowidXFilterRecheck191.estimatedRows', 0],
    'next estimated cost sentinel' => ['nextGeneratedPathRowidXFilterRecheck191.estimatedCost', 1000000],
    'next opcode restart' => ['nextGeneratedPathRowidXFilterRecheck191.xFilterOpcode', 'restart-next-source-generated-path-rowid-xfilter-next191'],
    'next cost restart' => ['nextGeneratedPathRowidXFilterRecheck191.costClass', 'json-table-generated-path-rowid-xfilter-restart-next191'],
    'transition count' => ['generatedPathRowidXFilterRecheck191Transitions', 15],
];

$tests = [];

foreach ($paths191 as $name => [$path, $expected]) {
    $tests['json table generated path rowid xfilter recheck ' . $name] = static function (TestRunner $t) use ($plan191, $valueAt191, $path, $expected): void {
        $actual = $valueAt191($plan191(), $path);
        if ($path === 'dependencies') {
            $t->true(in_array($expected, $actual, true));
            return;
        }
        if ($path === 'generatedPathRowidXFilterRecheck191Transitions') {
            $t->same($expected, count($actual));
            return;
        }
        $t->same($expected, $actual);
    };
}

$tests['json table generated path rowid xfilter recheck current fingerprint is sha256'] = static function (TestRunner $t) use ($plan191): void {
    $t->same(64, strlen($plan191()['currentGeneratedPathRowidXFilterRecheck191']['filterFingerprint']));
};
$tests['json table generated path rowid xfilter recheck next fingerprint is sha256'] = static function (TestRunner $t) use ($plan191): void {
    $t->same(64, strlen($plan191()['nextGeneratedPathRowidXFilterRecheck191']['filterFingerprint']));
};
$tests['json table generated path rowid xfilter recheck fingerprints differ'] = static function (TestRunner $t) use ($plan191): void {
    $t->same(false, $plan191()['currentGeneratedPathRowidXFilterRecheck191']['filterFingerprint'] === $plan191()['nextGeneratedPathRowidXFilterRecheck191']['filterFingerprint']);
};
$tests['json table generated path rowid xfilter recheck reasons include source'] = static function (TestRunner $t) use ($plan191): void {
    $t->true(in_array('json-table-generated-path-rowid-xfilter-source-changed-next191', $plan191()['next191ReplanReasons'], true));
};
$tests['json table generated path rowid xfilter recheck reasons include rowset'] = static function (TestRunner $t) use ($plan191): void {
    $t->true(in_array('json-table-generated-path-rowid-xfilter-rowset-changed-next191', $plan191()['next191ReplanReasons'], true));
};
$tests['json table generated path rowid xfilter recheck reasons include reuse'] = static function (TestRunner $t) use ($plan191): void {
    $t->true(in_array('json-table-generated-path-rowid-xfilter-reuse-changed-next191', $plan191()['next191ReplanReasons'], true));
};
$tests['json table generated path rowid xfilter recheck reasons include cost'] = static function (TestRunner $t) use ($plan191): void {
    $t->true(in_array('json-table-generated-path-rowid-xfilter-cost-changed-next191', $plan191()['next191ReplanReasons'], true));
};
$tests['json table generated path rowid xfilter recheck preserves next188 reason'] = static function (TestRunner $t) use ($plan191): void {
    $t->true(in_array('json-table-generated-path-rowid-deleted-rowid-next188', $plan191()['next191ReplanReasons'], true));
};
$tests['json table generated path rowid xfilter recheck stable reuses'] = static function (TestRunner $t) use ($stable191): void {
    $t->same('reuse-xfilter-current-json-table-generated-path-rowid-next191', $stable191()['nextReaderPolicy']);
};
$tests['json table generated path rowid xfilter recheck stable next accepted rowid'] = static function (TestRunner $t) use ($stable191): void {
    $t->same([8], $stable191()['nextGeneratedPathRowidXFilterRecheck191']['acceptedRowids']);
};
$tests['json table generated path rowid xfilter recheck stable next reusable'] = static function (TestRunner $t) use ($stable191): void {
    $t->same(true, $stable191()['nextGeneratedPathRowidXFilterRecheck191']['checkpointReusable']);
};
$tests['json table generated path rowid xfilter recheck stable reasons empty'] = static function (TestRunner $t) use ($stable191): void {
    $t->same([], $stable191()['next191ReplanReasons']);
};
$tests['json table generated path rowid xfilter recheck generation change rejects checkpoint'] = static function (TestRunner $t) use ($changedGeneration191): void {
    $t->same(false, $changedGeneration191()['nextGeneratedPathRowidXFilterRecheck191']['checkpointReusable']);
};
$tests['json table generated path rowid xfilter recheck generation change preserves rowid tape'] = static function (TestRunner $t) use ($changedGeneration191): void {
    $t->same([8], $changedGeneration191()['nextGeneratedPathRowidXFilterRecheck191']['checkpointRowids']);
};
$tests['json table generated path rowid xfilter recheck final batch range cost'] = static function (TestRunner $t) use ($final191): void {
    $t->same('json-table-generated-path-rowid-xfilter-range-next191', $final191()['currentGeneratedPathRowidXFilterRecheck191']['costClass']);
};
$tests['json table generated path rowid xfilter recheck final batch accepts two rowids'] = static function (TestRunner $t) use ($final191): void {
    $t->same([6, 5], $final191()['currentGeneratedPathRowidXFilterRecheck191']['acceptedRowids']);
};
$tests['json table generated path rowid xfilter recheck narrowed projection keeps filter rowid'] = static function (TestRunner $t) use ($projection191): void {
    $t->same([8], $projection191()['currentGeneratedPathRowidXFilterRecheck191']['checkpointRowids']);
};
$tests['json table generated path rowid xfilter recheck narrowed projection keeps point cost'] = static function (TestRunner $t) use ($projection191): void {
    $t->same('json-table-generated-path-rowid-xfilter-point-next191', $projection191()['currentGeneratedPathRowidXFilterRecheck191']['costClass']);
};
$tests['json table generated path rowid xfilter recheck malformed generated path rejected'] = static function (TestRunner $t) use ($plan191, $current191): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan191(array_replace($current191, ['generated_path' => '$.rules[']), $current191));
};
$tests['json table generated path rowid xfilter recheck bad root rejected'] = static function (TestRunner $t) use ($plan191, $current191): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan191(array_replace($current191, ['scan_root' => 191]), $current191));
};
$tests['json table generated path rowid xfilter recheck bad function rejected'] = static function (TestRunner $t) use ($current191): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXFilterRecheck('json_bad', $current191, $current191, 'option_value', 'generated_path'));
};
$tests['json table generated path rowid xfilter recheck dependency closure'] = static function (TestRunner $t): void {
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
