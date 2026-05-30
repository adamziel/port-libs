<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastRtrimGlobRangeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'plugin_cache '],
    ['setting_id' => 3, 'key_name' => 'site_label', 'key_value' => 'plugin_cache  '],
    ['setting_id' => 4, 'key_name' => 'template', 'key_value' => "plugin_cache\t"],
    ['setting_id' => 5, 'key_name' => 'stylesheet', 'key_value' => 'plugin_cache_extra'],
    ['setting_id' => 6, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('plugin_blob ')],
    ['setting_id' => 7, 'key_name' => 'retry_count', 'key_value' => '42 widgets'],
    ['setting_id' => 8, 'key_name' => 'decimal_rate', 'key_value' => '4.5ms'],
    ['setting_id' => 9, 'key_name' => 'zero_flag', 'key_value' => false],
    ['setting_id' => 10, 'key_name' => 'null_flag', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'unicode', 'key_value' => 'plugin_éclair '],
    ['setting_id' => 12, 'key_name' => 'emoji', 'key_value' => 'plugin_😀 '],
    ['setting_id' => 13, 'key_name' => 'upper', 'key_value' => 'Plugin_Cache'],
    ['setting_id' => 14, 'key_name' => 'theme', 'key_value' => 'theme_cache'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'plugin_cache'],
    ['setting_id' => 3, 'key_name' => 'site_label', 'key_value' => 'plugin_cache  '],
    ['setting_id' => 4, 'key_name' => 'template', 'key_value' => "plugin_cache\t"],
    ['setting_id' => 5, 'key_name' => 'stylesheet', 'key_value' => 'plugin_cache_extra_v2'],
    ['setting_id' => 6, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('plugin_blob')],
    ['setting_id' => 7, 'key_name' => 'retry_count', 'key_value' => 42],
    ['setting_id' => 8, 'key_name' => 'decimal_rate', 'key_value' => '5.5ms'],
    ['setting_id' => 9, 'key_name' => 'zero_flag', 'key_value' => true],
    ['setting_id' => 10, 'key_name' => 'null_flag', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'unicode', 'key_value' => 'plugin_éclair'],
    ['setting_id' => 12, 'key_name' => 'emoji', 'key_value' => 'plugin_😀'],
    ['setting_id' => 13, 'key_name' => 'upper', 'key_value' => 'Plugin_Cache'],
    ['setting_id' => 15, 'key_name' => 'fresh', 'key_value' => 'plugin_cache_new'],
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
    'operator' => ['TEXT', 'plugin_cache', 'operator', 'GLOB'],
    'collation' => ['TEXT', 'plugin_cache', 'collation', 'RTRIM'],
    'cast target' => ['TEXT', 'plugin_cache', 'castTarget', 'TEXT'],
    'pattern' => ['TEXT', 'plugin_cache', 'pattern', 'plugin_cache'],
    'range lower exact' => ['TEXT', 'plugin_cache', 'range.lowerInclusive', 'plugin_cache'],
    'range upper exact' => ['TEXT', 'plugin_cache', 'range.upperBound', 'plugin_cachf'],
    'index usable exact' => ['TEXT', 'plugin_cache', 'indexUsable', true],
    'residual scan exact' => ['TEXT', 'plugin_cache', 'residualScan', true],
    'glob does not trim marker' => ['TEXT', 'plugin_cache', 'globDoesNotTrimTrailingSpaces', true],
    'current source' => ['TEXT', 'plugin_cache', 'currentSource', 'main.app_settings@126'],
    'next source' => ['TEXT', 'plugin_cache', 'nextSource', 'main.app_settings@127'],
    'current schema cookie' => ['TEXT', 'plugin_cache', 'currentSchemaCookie', 126],
    'next schema cookie' => ['TEXT', 'plugin_cache', 'nextSchemaCookie', 127],
    'current candidates exact include rtrim padded and prefix follower' => ['TEXT', 'plugin_cache', 'currentCandidateRowids', [1, 2, 3, 4, 5]],
    'next candidates exact include inserted prefix follower' => ['TEXT', 'plugin_cache', 'nextCandidateRowids', [1, 2, 3, 4, 5, 15]],
    'current residual rejects padded tab and longer text' => ['TEXT', 'plugin_cache', 'currentResidualRejectedRowids', [2, 3, 4, 5]],
    'next residual rejects padded tab and longer text' => ['TEXT', 'plugin_cache', 'nextResidualRejectedRowids', [3, 4, 5, 15]],
    'current exact rowids' => ['TEXT', 'plugin_cache', 'currentRowids', [1]],
    'next exact rowids include trimmed repair' => ['TEXT', 'plugin_cache', 'nextRowids', [1, 2]],
    'retained exact rowids' => ['TEXT', 'plugin_cache', 'retainedRowids', [1]],
    'entered exact rowids' => ['TEXT', 'plugin_cache', 'enteredRowids', [2]],
    'exited exact rowids' => ['TEXT', 'plugin_cache', 'exitedRowids', []],
    'changed cast rowids text' => ['TEXT', 'plugin_cache', 'changedCastRowids', [2, 5, 6, 7, 8, 9, 11, 12, 14, 15]],
    'changed rtrim keys text' => ['TEXT', 'plugin_cache', 'changedRtrimKeyRowids', [5, 7, 8, 9, 14, 15]],
    'changed candidate rowids text' => ['TEXT', 'plugin_cache', 'changedCandidateRowids', [14, 15]],
    'changed match rowids text' => ['TEXT', 'plugin_cache', 'changedMatchRowids', [2, 14, 15]],
    'invalidated exact' => ['TEXT', 'plugin_cache', 'cursorInvalidated', true],
    'not reusable exact' => ['TEXT', 'plugin_cache', 'cursorReusable', false],
    'reason source' => ['TEXT', 'plugin_cache', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['TEXT', 'plugin_cache', 'invalidationReasons.1', 'schema-cookie'],
    'reason cast' => ['TEXT', 'plugin_cache', 'invalidationReasons.2', 'cast-result'],
    'reason rtrim' => ['TEXT', 'plugin_cache', 'invalidationReasons.3', 'rtrim-key'],
    'reason candidate' => ['TEXT', 'plugin_cache', 'invalidationReasons.4', 'candidate-rowset'],
    'reason matched' => ['TEXT', 'plugin_cache', 'invalidationReasons.5', 'matched-rowset'],
    'trace text row two cast preserves space' => ['TEXT', 'plugin_cache', 'currentTrace.1.castText', 'plugin_cache '],
    'trace text row two rtrim trims space' => ['TEXT', 'plugin_cache', 'currentTrace.1.rtrimKey', 'plugin_cache'],
    'trace text row four rtrim keeps tab' => ['TEXT', 'plugin_cache', 'currentTrace.3.rtrimKey', "plugin_cache\t"],
    'trace text row four candidate due range' => ['TEXT', 'plugin_cache', 'currentTrace.3.candidate', true],
    'trace text row four residual false' => ['TEXT', 'plugin_cache', 'currentTrace.3.matched', false],
    'trace text row six blob storage' => ['TEXT', 'plugin_cache', 'currentTrace.5.originalStorage', 'blob'],
    'trace text row seven casts prefix integer text' => ['TEXT', 'plugin_cache', 'currentTrace.6.castText', '42 widgets'],
    'trace next row seven integer text' => ['TEXT', 'plugin_cache', 'nextTrace.6.castText', '42'],
    'wildcard range lower' => ['TEXT', 'plugin_cache*', 'range.lowerInclusive', 'plugin_cache'],
    'wildcard current candidates' => ['TEXT', 'plugin_cache*', 'currentCandidateRowids', [1, 2, 3, 4, 5]],
    'wildcard current rowids include padded and tab values' => ['TEXT', 'plugin_cache*', 'currentRowids', [1, 2, 3, 4, 5]],
    'wildcard next rowids include fresh value' => ['TEXT', 'plugin_cache*', 'nextRowids', [1, 2, 3, 4, 5, 15]],
    'wildcard residual rejects empty' => ['TEXT', 'plugin_cache*', 'currentResidualRejectedRowids', []],
    'blob exact current candidates include rtrim blob' => ['BLOB', 'plugin_blob', 'currentCandidateRowids', [6]],
    'blob exact current rowids reject padded blob' => ['BLOB', 'plugin_blob', 'currentRowids', []],
    'blob exact next rowids match shortened blob' => ['BLOB', 'plugin_blob', 'nextRowids', [6]],
    'blob exact current residual rejects blob padding' => ['BLOB', 'plugin_blob', 'currentResidualRejectedRowids', [6]],
    'blob wildcard current matches padded blob' => ['BLOB', 'plugin_blob*', 'currentRowids', [6]],
    'blob wildcard next matches shortened blob' => ['BLOB', 'plugin_blob*', 'nextRowids', [6]],
    'integer glob current rowids' => ['INTEGER', '4*', 'currentRowids', [7, 8]],
    'integer glob next rowids' => ['INTEGER', '4*', 'nextRowids', [7]],
    'integer cast value prefix' => ['INTEGER', '4*', 'currentTrace.6.castValue', 42],
    'real glob current rowids' => ['REAL', '4*', 'currentRowids', [7, 8]],
    'real glob next rowids' => ['REAL', '4*', 'nextRowids', [7]],
    'numeric false current zero' => ['NUMERIC', '0', 'currentRowids', [1, 2, 3, 4, 5, 6, 9, 11, 12, 13, 14]],
    'numeric false exits next' => ['NUMERIC', '0', 'exitedRowids', [9, 14]],
    'numeric true enters next one' => ['NUMERIC', '1', 'enteredRowids', [9]],
    'unicode exact candidate trims space' => ['TEXT', 'plugin_éclair', 'currentCandidateRowids', [11]],
    'unicode exact residual rejects padded' => ['TEXT', 'plugin_éclair', 'currentResidualRejectedRowids', [11]],
    'unicode exact next matches trimmed' => ['TEXT', 'plugin_éclair', 'nextRowids', [11]],
    'emoji exact current rejects padded' => ['TEXT', 'plugin_😀', 'currentResidualRejectedRowids', [12]],
    'emoji exact next matches trimmed' => ['TEXT', 'plugin_😀', 'nextRowids', [12]],
    'uppercase binary exact does not match lowercase pattern' => ['TEXT', 'plugin_cache', 'currentTrace.12.candidate', false],
    'leading class has no range' => ['TEXT', '[Pp]lugin_*', 'range', null],
    'leading class has no candidates' => ['TEXT', '[Pp]lugin_*', 'currentCandidateRowids', []],
    'leading class reason no prefix' => ['TEXT', '[Pp]lugin_*', 'invalidationReasons.2', 'no-prefix-range'],
    'dependency cast' => ['TEXT', 'plugin_cache', 'dependencies.0', 'sqlite-select-cast-expression'],
    'dependency rtrim range' => ['TEXT', 'plugin_cache', 'dependencies.1', 'sqlite-rtrim-glob-prefix-range'],
    'dependency residual' => ['TEXT', 'plugin_cache', 'dependencies.2', 'sqlite-glob-binary-residual'],
    'dependency current source' => ['TEXT', 'plugin_cache', 'dependencies.3', 'sqlite-current-source-next127'],
];

foreach ($cases as $name => [$castTarget, $pattern, $path, $expected]) {
    $tests['cast rtrim glob range current source next127 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $castTarget, $pattern, $path, $expected): void {
        $t->same($expected, $valueAt($plan($castTarget, $pattern), $path));
    };
}

$tests['cast rtrim glob range current source next127 stable exact padded peer is reusable'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'plugin_cache'],
        ['setting_id' => 2, 'key_value' => 'plugin_cache '],
    ];
    $plan = SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', 'plugin_cache', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([2], $plan['currentResidualRejectedRowids']);
    $t->same([1], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['cast rtrim glob range current source next127 stable leading class keeps no prefix reason'] = static function (TestRunner $t): void {
    $rows = [['setting_id' => 1, 'key_value' => 'plugin_cache']];
    $plan = SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', '[Pp]lugin_*', 'stable', 'stable', 7, 7);
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['cast rtrim glob range current source next127 rejects malformed cast target'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT); DROP TABLE app_settings; --', 'plugin*'));
};

$tests['cast rtrim glob range current source next127 rejects missing setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan([['key_value' => 'plugin']], $nextRows, 'TEXT', 'plugin*'));
};

$tests['cast rtrim glob range current source next127 rejects missing setting value'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1]], $nextRows, 'TEXT', 'plugin*'));
};

$tests['cast rtrim glob range current source next127 rejects non integer setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => '1', 'key_value' => 'plugin']], $nextRows, 'TEXT', 'plugin*'));
};

return $tests;
