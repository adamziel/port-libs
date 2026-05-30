<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteNocaseGlobAffinityCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => 'a'],
    ['option_id' => 2, 'option_name' => 'Plugin_Beta', 'option_value' => 'b'],
    ['option_id' => 3, 'option_name' => 'plugin_beta', 'option_value' => 'c'],
    ['option_id' => 4, 'option_name' => 'plugin-cache', 'option_value' => 'd'],
    ['option_id' => 5, 'option_name' => 'theme_alpha', 'option_value' => 'e'],
    ['option_id' => 6, 'option_name' => 'plugin_éclair', 'option_value' => 'f'],
    ['option_id' => 7, 'option_name' => 'plugin_😀', 'option_value' => 'g'],
    ['option_id' => 8, 'option_name' => 'plugin_42', 'option_value' => 'h'],
    ['option_id' => 9, 'option_name' => 42, 'option_value' => 'i'],
    ['option_id' => 10, 'option_name' => true, 'option_value' => 'j'],
    ['option_id' => 11, 'option_name' => 'Plugin_Zed', 'option_value' => 'k'],
    ['option_id' => 12, 'option_name' => 'plugin_zed', 'option_value' => 'l'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => 'a'],
    ['option_id' => 2, 'option_name' => 'plugin_Beta', 'option_value' => 'b'],
    ['option_id' => 3, 'option_name' => 'plugin_beta2', 'option_value' => 'c'],
    ['option_id' => 4, 'option_name' => 'plugin-cache', 'option_value' => 'd'],
    ['option_id' => 5, 'option_name' => 'theme_alpha', 'option_value' => 'e'],
    ['option_id' => 6, 'option_name' => 'plugin_éclair', 'option_value' => 'f'],
    ['option_id' => 7, 'option_name' => 'plugin_😀', 'option_value' => 'g'],
    ['option_id' => 8, 'option_name' => 'plugin_43', 'option_value' => 'h'],
    ['option_id' => 9, 'option_name' => '42', 'option_value' => 'i'],
    ['option_id' => 10, 'option_name' => false, 'option_value' => 'j'],
    ['option_id' => 11, 'option_name' => 'Plugin_Zed', 'option_value' => 'k'],
    ['option_id' => 13, 'option_name' => 'plugin_fresh', 'option_value' => 'm'],
];

$plan = static fn (
    string $pattern = 'plugin_*',
    string $affinity = 'TEXT',
    string $collation = 'NOCASE',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@138',
    string $nextSource = 'main.app_settings@139',
    int $currentSchemaCookie = 138,
    int $nextSchemaCookie = 139,
): array => SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $affinity,
    $collation,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'operator is glob' => ['plugin_*', 'TEXT', 'NOCASE', 'operator', 'GLOB'],
    'pattern recorded' => ['plugin_*', 'TEXT', 'NOCASE', 'pattern', 'plugin_*'],
    'affinity recorded' => ['plugin_*', 'TEXT', 'NOCASE', 'affinity', 'TEXT'],
    'collation recorded' => ['plugin_*', 'TEXT', 'NOCASE', 'collation', 'NOCASE'],
    'range lower records literal prefix' => ['plugin_*', 'TEXT', 'NOCASE', 'range.lowerInclusive', 'plugin_'],
    'range upper increments underscore' => ['plugin_*', 'TEXT', 'NOCASE', 'range.upperBound', 'plugin`'],
    'nocase range not usable for glob' => ['plugin_*', 'TEXT', 'NOCASE', 'rangeUsable', false],
    'residual scan enabled' => ['plugin_*', 'TEXT', 'NOCASE', 'residualScan', true],
    'fallback reason names binary requirement' => ['plugin_*', 'TEXT', 'NOCASE', 'fallbackReason', 'glob-range-requires-binary-collation'],
    'current source recorded' => ['plugin_*', 'TEXT', 'NOCASE', 'currentSource', 'main.app_settings@138'],
    'next source recorded' => ['plugin_*', 'TEXT', 'NOCASE', 'nextSource', 'main.app_settings@139'],
    'current schema cookie recorded' => ['plugin_*', 'TEXT', 'NOCASE', 'currentSchemaCookie', 138],
    'next schema cookie recorded' => ['plugin_*', 'TEXT', 'NOCASE', 'nextSchemaCookie', 139],
    'current residual candidates include all rows' => ['plugin_*', 'TEXT', 'NOCASE', 'currentCandidateRowids', [10, 9, 4, 8, 1, 2, 3, 11, 12, 6, 7, 5]],
    'next residual candidates include all rows' => ['plugin_*', 'TEXT', 'NOCASE', 'nextCandidateRowids', [10, 9, 4, 8, 1, 2, 3, 13, 11, 6, 7, 5]],
    'current glob rowids are case sensitive' => ['plugin_*', 'TEXT', 'NOCASE', 'currentRowids', [8, 1, 3, 12, 6, 7]],
    'next glob rowids include case changed and fresh rows' => ['plugin_*', 'TEXT', 'NOCASE', 'nextRowids', [8, 1, 2, 3, 13, 6, 7]],
    'retained rowids preserve residual matches' => ['plugin_*', 'TEXT', 'NOCASE', 'retainedRowids', [8, 1, 3, 6, 7]],
    'entered rowids include case change and fresh' => ['plugin_*', 'TEXT', 'NOCASE', 'enteredRowids', [2, 13]],
    'exited rowids include deleted lowercase zed' => ['plugin_*', 'TEXT', 'NOCASE', 'exitedRowids', [12]],
    'current residual rejects uppercase and non prefix rows' => ['plugin_*', 'TEXT', 'NOCASE', 'currentResidualRejectedRowids', [10, 9, 4, 2, 11, 5]],
    'next residual rejects uppercase and non prefix rows' => ['plugin_*', 'TEXT', 'NOCASE', 'nextResidualRejectedRowids', [10, 9, 4, 11, 5]],
    'changed text rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'changedTextRowids', [2, 3, 8, 10, 12, 13]],
    'changed storage rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'changedStorageRowids', [9, 12, 13]],
    'changed bytes rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'changedBytesRowids', [2, 3, 8, 10, 12, 13]],
    'changed candidate rowids track deletion and insert only' => ['plugin_*', 'TEXT', 'NOCASE', 'changedCandidateRowids', [12, 13]],
    'changed match rowids include case sensitive glob deltas' => ['plugin_*', 'TEXT', 'NOCASE', 'changedMatchRowids', [2, 12, 13]],
    'cursor invalidated' => ['plugin_*', 'TEXT', 'NOCASE', 'cursorInvalidated', true],
    'cursor not reusable' => ['plugin_*', 'TEXT', 'NOCASE', 'cursorReusable', false],
    'reason source name' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.0', 'source-name'],
    'reason schema cookie' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.1', 'schema-cookie'],
    'reason nocase range fallback' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.2', 'glob-range-requires-binary-collation'],
    'reason storage class' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.3', 'storage-class'],
    'reason text affinity' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.4', 'text-affinity'],
    'reason encoded bytes' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.5', 'encoded-bytes'],
    'reason candidate rowset' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.6', 'candidate-rowset'],
    'reason matched rowset' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.7', 'matched-rowset'],
    'uppercase current sorted by nocase but not matched' => ['plugin_*', 'TEXT', 'NOCASE', 'currentTrace.1.matched', false],
    'uppercase next becomes matched after lower prefix' => ['plugin_*', 'TEXT', 'NOCASE', 'nextTrace.5.matched', true],
    'hyphen row range class before underscore' => ['plugin_*', 'TEXT', 'NOCASE', 'currentTrace.2.rangeClass', 'before-range'],
    'accent row has utf8 bytes' => ['plugin_*', 'TEXT', 'NOCASE', 'currentTrace.9.bytesHex', '706C7567696E5FC3A9636C616972'],
    'emoji row matches literal prefix' => ['plugin_*', 'TEXT', 'NOCASE', 'currentTrace.10.matched', true],
    'numeric text current matched by digit pattern' => ['4*', 'TEXT', 'NOCASE', 'currentRowids', [9]],
    'numeric text next still matched by digit pattern' => ['4*', 'TEXT', 'NOCASE', 'nextRowids', [9]],
    'boolean true current matched' => ['1', 'TEXT', 'NOCASE', 'currentRowids', [10]],
    'boolean false exits true pattern' => ['1', 'TEXT', 'NOCASE', 'exitedRowids', [10]],
    'binary collation can use range' => ['plugin_*', 'TEXT', 'BINARY', 'rangeUsable', true],
    'binary collation disables residual fallback' => ['plugin_*', 'TEXT', 'BINARY', 'residualScan', false],
    'binary collation no fallback reason' => ['plugin_*', 'TEXT', 'BINARY', 'fallbackReason', null],
    'binary current candidates use prefix range' => ['plugin_*', 'TEXT', 'BINARY', 'currentCandidateRowids', [8, 1, 3, 12, 6, 7]],
    'binary current residual rejects none inside range' => ['plugin_*', 'TEXT', 'BINARY', 'currentResidualRejectedRowids', []],
    'leading class has no fixed prefix' => ['[Pp]lugin_*', 'TEXT', 'NOCASE', 'range', null],
    'leading class fallback names no prefix' => ['[Pp]lugin_*', 'TEXT', 'NOCASE', 'fallbackReason', 'no-fixed-prefix'],
    'leading class rowids include upper and lower' => ['[Pp]lugin_*', 'TEXT', 'NOCASE', 'currentRowids', [8, 1, 2, 3, 11, 12, 6, 7]],
    'dependency bytewise glob' => ['plugin_*', 'TEXT', 'NOCASE', 'dependencies.0', 'sqlite-glob-bytewise-residual'],
    'dependency nocase rejection' => ['plugin_*', 'TEXT', 'NOCASE', 'dependencies.1', 'sqlite-nocase-index-range-rejection'],
    'dependency affinity' => ['plugin_*', 'TEXT', 'NOCASE', 'dependencies.2', 'sqlite-affinity-text-coercion'],
    'dependency current source' => ['plugin_*', 'TEXT', 'NOCASE', 'dependencies.3', 'sqlite-current-source-next139'],
];

foreach ($cases as $name => [$pattern, $affinity, $collation, $path, $expected]) {
    $tests['nocase glob affinity current source next139 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $affinity, $collation, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern, $affinity, $collation), $path));
    };
}

$tests['nocase glob affinity current source next139 stable binary source reusable'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'plugin_alpha'],
        ['option_id' => 2, 'option_name' => 'plugin_beta'],
    ];
    $plan = SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan($rows, $rows, 'plugin_*', 'TEXT', 'BINARY', 'stable', 'stable', 9, 9);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['nocase glob affinity current source next139 stable nocase still records range rejection'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'plugin_alpha'],
        ['option_id' => 2, 'option_name' => 'Plugin_Beta'],
    ];
    $plan = SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan($rows, $rows, 'plugin_*', 'TEXT', 'NOCASE', 'stable', 'stable', 9, 9);
    $t->same([1], $plan['currentRowids']);
    $t->same(['glob-range-requires-binary-collation'], $plan['invalidationReasons']);
    $t->same(false, $plan['cursorReusable']);
};

$tests['nocase glob affinity current source next139 rejects unsupported collation'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $currentRows, 'plugin_*', 'TEXT', 'UNICODE'));
};

$tests['nocase glob affinity current source next139 rejects missing option name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1]], [], 'plugin_*'));
};

$tests['nocase glob affinity current source next139 rejects non integer option id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => '1', 'option_name' => 'plugin_alpha']], [], 'plugin_*'));
};

$tests['nocase glob affinity current source next139 rejects blob option name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'option_name' => new SQLiteBlobValue('plugin_alpha')]], [], 'plugin_*'));
};

$tests['nocase glob affinity current source next139 rejects null option name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'option_name' => null]], [], 'plugin_*'));
};

$tests['nocase glob affinity current source next139 rejects malformed utf8'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'option_name' => "plugin_\xc3"]], [], 'plugin_*'));
};

return $tests;
