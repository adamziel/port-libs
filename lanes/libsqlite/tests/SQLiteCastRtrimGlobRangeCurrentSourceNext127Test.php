<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastRtrimGlobRangeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'module_cache'],
    ['setting_id' => 2, 'key_name' => 'base_url', 'key_value' => 'module_cache '],
    ['setting_id' => 3, 'key_name' => 'app_label', 'key_value' => 'module_cache  '],
    ['setting_id' => 4, 'key_name' => 'template', 'key_value' => "module_cache\t"],
    ['setting_id' => 5, 'key_name' => 'view_style', 'key_value' => 'module_cache_extra'],
    ['setting_id' => 6, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('module_blob ')],
    ['setting_id' => 7, 'key_name' => 'retry_count', 'key_value' => '42 widgets'],
    ['setting_id' => 8, 'key_name' => 'decimal_rate', 'key_value' => '4.5ms'],
    ['setting_id' => 9, 'key_name' => 'zero_flag', 'key_value' => false],
    ['setting_id' => 10, 'key_name' => 'null_flag', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'unicode', 'key_value' => 'module_éclair '],
    ['setting_id' => 12, 'key_name' => 'emoji', 'key_value' => 'module_😀 '],
    ['setting_id' => 13, 'key_name' => 'upper', 'key_value' => 'Module_Cache'],
    ['setting_id' => 14, 'key_name' => 'bundle', 'key_value' => 'bundle_cache'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'module_cache'],
    ['setting_id' => 2, 'key_name' => 'base_url', 'key_value' => 'module_cache'],
    ['setting_id' => 3, 'key_name' => 'app_label', 'key_value' => 'module_cache  '],
    ['setting_id' => 4, 'key_name' => 'template', 'key_value' => "module_cache\t"],
    ['setting_id' => 5, 'key_name' => 'view_style', 'key_value' => 'module_cache_extra_v2'],
    ['setting_id' => 6, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('module_blob')],
    ['setting_id' => 7, 'key_name' => 'retry_count', 'key_value' => 42],
    ['setting_id' => 8, 'key_name' => 'decimal_rate', 'key_value' => '5.5ms'],
    ['setting_id' => 9, 'key_name' => 'zero_flag', 'key_value' => true],
    ['setting_id' => 10, 'key_name' => 'null_flag', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'unicode', 'key_value' => 'module_éclair'],
    ['setting_id' => 12, 'key_name' => 'emoji', 'key_value' => 'module_😀'],
    ['setting_id' => 13, 'key_name' => 'upper', 'key_value' => 'Module_Cache'],
    ['setting_id' => 15, 'key_name' => 'fresh', 'key_value' => 'module_cache_new'],
];

$plan = static fn (
    string $castTarget,
    string $pattern,
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@126',
    string $nextSource = 'main.app_settings@127',
    int $currentCookie = 126,
    int $nextCookie = 127,
): array => SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $castTarget,
    $pattern,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
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
    'operator' => ['TEXT', 'module_cache', 'operator', 'GLOB'],
    'collation' => ['TEXT', 'module_cache', 'collation', 'RTRIM'],
    'cast target' => ['TEXT', 'module_cache', 'castTarget', 'TEXT'],
    'pattern' => ['TEXT', 'module_cache', 'pattern', 'module_cache'],
    'range lower exact' => ['TEXT', 'module_cache', 'range.lowerInclusive', 'module_cache'],
    'range upper exact' => ['TEXT', 'module_cache', 'range.upperBound', 'module_cachf'],
    'index usable exact' => ['TEXT', 'module_cache', 'indexUsable', true],
    'residual scan exact' => ['TEXT', 'module_cache', 'residualScan', true],
    'glob does not trim marker' => ['TEXT', 'module_cache', 'globDoesNotTrimTrailingSpaces', true],
    'current source' => ['TEXT', 'module_cache', 'currentSource', 'main.app_settings@126'],
    'next source' => ['TEXT', 'module_cache', 'nextSource', 'main.app_settings@127'],
    'current schema cookie' => ['TEXT', 'module_cache', 'currentSchemaCookie', 126],
    'next schema cookie' => ['TEXT', 'module_cache', 'nextSchemaCookie', 127],
    'current candidates exact include rtrim padded and prefix follower' => ['TEXT', 'module_cache', 'currentCandidateRowids', [1, 2, 3, 4, 5]],
    'next candidates exact include inserted prefix follower' => ['TEXT', 'module_cache', 'nextCandidateRowids', [1, 2, 3, 4, 5, 15]],
    'current residual rejects padded tab and longer text' => ['TEXT', 'module_cache', 'currentResidualRejectedRowids', [2, 3, 4, 5]],
    'next residual rejects padded tab and longer text' => ['TEXT', 'module_cache', 'nextResidualRejectedRowids', [3, 4, 5, 15]],
    'current exact rowids' => ['TEXT', 'module_cache', 'currentRowids', [1]],
    'next exact rowids include trimmed repair' => ['TEXT', 'module_cache', 'nextRowids', [1, 2]],
    'retained exact rowids' => ['TEXT', 'module_cache', 'retainedRowids', [1]],
    'entered exact rowids' => ['TEXT', 'module_cache', 'enteredRowids', [2]],
    'exited exact rowids' => ['TEXT', 'module_cache', 'exitedRowids', []],
    'changed cast rowids text' => ['TEXT', 'module_cache', 'changedCastRowids', [2, 5, 6, 7, 8, 9, 11, 12, 14, 15]],
    'changed rtrim keys text' => ['TEXT', 'module_cache', 'changedRtrimKeyRowids', [5, 7, 8, 9, 14, 15]],
    'changed candidate rowids text' => ['TEXT', 'module_cache', 'changedCandidateRowids', [14, 15]],
    'changed match rowids text' => ['TEXT', 'module_cache', 'changedMatchRowids', [2, 14, 15]],
    'invalidated exact' => ['TEXT', 'module_cache', 'cursorInvalidated', true],
    'not reusable exact' => ['TEXT', 'module_cache', 'cursorReusable', false],
    'reason source' => ['TEXT', 'module_cache', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['TEXT', 'module_cache', 'invalidationReasons.1', 'schema-cookie'],
    'reason cast' => ['TEXT', 'module_cache', 'invalidationReasons.2', 'cast-result'],
    'reason rtrim' => ['TEXT', 'module_cache', 'invalidationReasons.3', 'rtrim-key'],
    'reason candidate' => ['TEXT', 'module_cache', 'invalidationReasons.4', 'candidate-rowset'],
    'reason matched' => ['TEXT', 'module_cache', 'invalidationReasons.5', 'matched-rowset'],
    'trace text row two cast preserves space' => ['TEXT', 'module_cache', 'currentTrace.1.castText', 'module_cache '],
    'trace text row two rtrim trims space' => ['TEXT', 'module_cache', 'currentTrace.1.rtrimKey', 'module_cache'],
    'trace text row four rtrim keeps tab' => ['TEXT', 'module_cache', 'currentTrace.3.rtrimKey', "module_cache\t"],
    'trace text row four candidate due range' => ['TEXT', 'module_cache', 'currentTrace.3.candidate', true],
    'trace text row four residual false' => ['TEXT', 'module_cache', 'currentTrace.3.matched', false],
    'trace text row six blob storage' => ['TEXT', 'module_cache', 'currentTrace.5.originalStorage', 'blob'],
    'trace text row seven casts prefix integer text' => ['TEXT', 'module_cache', 'currentTrace.6.castText', '42 widgets'],
    'trace next row seven integer text' => ['TEXT', 'module_cache', 'nextTrace.6.castText', '42'],
    'wildcard range lower' => ['TEXT', 'module_cache*', 'range.lowerInclusive', 'module_cache'],
    'wildcard current candidates' => ['TEXT', 'module_cache*', 'currentCandidateRowids', [1, 2, 3, 4, 5]],
    'wildcard current rowids include padded and tab values' => ['TEXT', 'module_cache*', 'currentRowids', [1, 2, 3, 4, 5]],
    'wildcard next rowids include fresh value' => ['TEXT', 'module_cache*', 'nextRowids', [1, 2, 3, 4, 5, 15]],
    'wildcard residual rejects empty' => ['TEXT', 'module_cache*', 'currentResidualRejectedRowids', []],
    'blob exact current candidates include rtrim blob' => ['BLOB', 'module_blob', 'currentCandidateRowids', [6]],
    'blob exact current rowids reject padded blob' => ['BLOB', 'module_blob', 'currentRowids', []],
    'blob exact next rowids match shortened blob' => ['BLOB', 'module_blob', 'nextRowids', [6]],
    'blob exact current residual rejects blob padding' => ['BLOB', 'module_blob', 'currentResidualRejectedRowids', [6]],
    'blob wildcard current matches padded blob' => ['BLOB', 'module_blob*', 'currentRowids', [6]],
    'blob wildcard next matches shortened blob' => ['BLOB', 'module_blob*', 'nextRowids', [6]],
    'integer glob current rowids' => ['INTEGER', '4*', 'currentRowids', [7, 8]],
    'integer glob next rowids' => ['INTEGER', '4*', 'nextRowids', [7]],
    'integer cast value prefix' => ['INTEGER', '4*', 'currentTrace.6.castValue', 42],
    'real glob current rowids' => ['REAL', '4*', 'currentRowids', [7, 8]],
    'real glob next rowids' => ['REAL', '4*', 'nextRowids', [7]],
    'numeric false current zero' => ['NUMERIC', '0', 'currentRowids', [1, 2, 3, 4, 5, 6, 9, 11, 12, 13, 14]],
    'numeric false exits next' => ['NUMERIC', '0', 'exitedRowids', [9, 14]],
    'numeric true enters next one' => ['NUMERIC', '1', 'enteredRowids', [9]],
    'unicode exact candidate trims space' => ['TEXT', 'module_éclair', 'currentCandidateRowids', [11]],
    'unicode exact residual rejects padded' => ['TEXT', 'module_éclair', 'currentResidualRejectedRowids', [11]],
    'unicode exact next matches trimmed' => ['TEXT', 'module_éclair', 'nextRowids', [11]],
    'emoji exact current rejects padded' => ['TEXT', 'module_😀', 'currentResidualRejectedRowids', [12]],
    'emoji exact next matches trimmed' => ['TEXT', 'module_😀', 'nextRowids', [12]],
    'uppercase binary exact does not match lowercase pattern' => ['TEXT', 'module_cache', 'currentTrace.12.candidate', false],
    'leading class has no range' => ['TEXT', '[Pp]lugin_*', 'range', null],
    'leading class has no candidates' => ['TEXT', '[Pp]lugin_*', 'currentCandidateRowids', []],
    'leading class reason no prefix' => ['TEXT', '[Pp]lugin_*', 'invalidationReasons.2', 'no-prefix-range'],
    'dependency cast' => ['TEXT', 'module_cache', 'dependencies.0', 'sqlite-select-cast-expression'],
    'dependency rtrim range' => ['TEXT', 'module_cache', 'dependencies.1', 'sqlite-rtrim-glob-prefix-range'],
    'dependency residual' => ['TEXT', 'module_cache', 'dependencies.2', 'sqlite-glob-binary-residual'],
    'dependency current source' => ['TEXT', 'module_cache', 'dependencies.3', 'sqlite-current-source-next127'],
];

foreach ($cases as $name => [$castTarget, $pattern, $path, $expected]) {
    $tests['cast rtrim glob range current source next127 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $castTarget, $pattern, $path, $expected): void {
        $t->same($expected, $valueAt($plan($castTarget, $pattern), $path));
    };
}

$tests['cast rtrim glob range current source next127 stable exact padded peer is reusable'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'module_cache'],
        ['setting_id' => 2, 'key_value' => 'module_cache '],
    ];
    $plan = SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', 'module_cache', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([2], $plan['currentResidualRejectedRowids']);
    $t->same([1], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['cast rtrim glob range current source next127 stable leading class keeps no prefix reason'] = static function (TestRunner $t): void {
    $rows = [['setting_id' => 1, 'key_value' => 'module_cache']];
    $plan = SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', '[Pp]lugin_*', 'stable', 'stable', 7, 7);
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['cast rtrim glob range current source next127 rejects malformed cast target'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT); DROP TABLE app_settings; --', 'module*'));
};

$tests['cast rtrim glob range current source next127 rejects missing setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan([['key_value' => 'module']], $nextRows, 'TEXT', 'module*'));
};

$tests['cast rtrim glob range current source next127 rejects missing setting value'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1]], $nextRows, 'TEXT', 'module*'));
};

$tests['cast rtrim glob range current source next127 rejects non integer setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => '1', 'key_value' => 'module']], $nextRows, 'TEXT', 'module*'));
};

return $tests;
