<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastRtrimLikeCurrentSourceNextPlan;

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
    ['setting_id' => 11, 'key_name' => 'unicode', 'key_value' => 'module_eclair '],
    ['setting_id' => 12, 'key_name' => 'upper', 'key_value' => 'Module_Cache'],
    ['setting_id' => 13, 'key_name' => 'literal_under', 'key_value' => 'module_100%_enabled '],
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
    ['setting_id' => 11, 'key_name' => 'unicode', 'key_value' => 'module_eclair'],
    ['setting_id' => 12, 'key_name' => 'upper', 'key_value' => 'Module_Cache'],
    ['setting_id' => 13, 'key_name' => 'literal_under', 'key_value' => 'module_100%_enabled'],
    ['setting_id' => 15, 'key_name' => 'fresh', 'key_value' => 'module_cache_new'],
];

$plan = static fn (
    string $castTarget,
    string $pattern,
    ?string $escape = null,
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@130',
    string $nextSource = 'main.app_settings@131',
    int $currentCookie = 130,
    int $nextCookie = 131,
): array => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $castTarget,
    $pattern,
    $escape,
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
    'operator' => ['TEXT', 'module\\_cache', '\\', 'operator', 'LIKE'],
    'collation' => ['TEXT', 'module\\_cache', '\\', 'collation', 'RTRIM'],
    'case sensitive like' => ['TEXT', 'module\\_cache', '\\', 'caseSensitiveLike', true],
    'cast target' => ['TEXT', 'module\\_cache', '\\', 'castTarget', 'TEXT'],
    'pattern prefix' => ['TEXT', 'module\\_cache', '\\', 'patternPrefix', 'module_cache'],
    'prefix ascii' => ['TEXT', 'module\\_cache', '\\', 'prefixIsAscii', true],
    'range lower exact' => ['TEXT', 'module\\_cache', '\\', 'range.lowerInclusive', 'module_cache'],
    'range upper exact' => ['TEXT', 'module\\_cache', '\\', 'range.upperBound', 'module_cachf'],
    'index usable exact' => ['TEXT', 'module\\_cache', '\\', 'indexUsable', true],
    'residual scan exact' => ['TEXT', 'module\\_cache', '\\', 'residualScan', true],
    'like does not trim marker' => ['TEXT', 'module\\_cache', '\\', 'likeDoesNotTrimTrailingSpaces', true],
    'rtrim trims only space marker' => ['TEXT', 'module\\_cache', '\\', 'rtrimTrimsOnlySpace', true],
    'current source' => ['TEXT', 'module\\_cache', '\\', 'currentSource', 'main.app_settings@130'],
    'next source' => ['TEXT', 'module\\_cache', '\\', 'nextSource', 'main.app_settings@131'],
    'current schema cookie' => ['TEXT', 'module\\_cache', '\\', 'currentSchemaCookie', 130],
    'next schema cookie' => ['TEXT', 'module\\_cache', '\\', 'nextSchemaCookie', 131],
    'current exact candidates include rtrim padded and tab peer' => ['TEXT', 'module\\_cache', '\\', 'currentCandidateRowids', [1, 2, 3, 4, 5]],
    'next exact candidates include fresh prefix follower' => ['TEXT', 'module\\_cache', '\\', 'nextCandidateRowids', [1, 2, 3, 4, 5, 15]],
    'current residual rejects padded peers' => ['TEXT', 'module\\_cache', '\\', 'currentResidualRejectedRowids', [2, 3, 4, 5]],
    'next residual rejects padded tab and longer text' => ['TEXT', 'module\\_cache', '\\', 'nextResidualRejectedRowids', [3, 4, 5, 15]],
    'current exact rowids' => ['TEXT', 'module\\_cache', '\\', 'currentRowids', [1]],
    'next exact rowids include trimmed repair' => ['TEXT', 'module\\_cache', '\\', 'nextRowids', [1, 2]],
    'retained exact rowids' => ['TEXT', 'module\\_cache', '\\', 'retainedRowids', [1]],
    'entered exact rowids' => ['TEXT', 'module\\_cache', '\\', 'enteredRowids', [2]],
    'exited exact rowids' => ['TEXT', 'module\\_cache', '\\', 'exitedRowids', []],
    'changed cast rowids text' => ['TEXT', 'module\\_cache', '\\', 'changedCastRowids', [2, 5, 6, 7, 8, 9, 11, 13, 14, 15]],
    'changed rtrim keys text' => ['TEXT', 'module\\_cache', '\\', 'changedRtrimKeyRowids', [5, 7, 8, 9, 14, 15]],
    'changed candidate rowids text' => ['TEXT', 'module\\_cache', '\\', 'changedCandidateRowids', [14, 15]],
    'changed match rowids text' => ['TEXT', 'module\\_cache', '\\', 'changedMatchRowids', [2, 14, 15]],
    'invalidated exact' => ['TEXT', 'module\\_cache', '\\', 'cursorInvalidated', true],
    'not reusable exact' => ['TEXT', 'module\\_cache', '\\', 'cursorReusable', false],
    'reason source' => ['TEXT', 'module\\_cache', '\\', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['TEXT', 'module\\_cache', '\\', 'invalidationReasons.1', 'schema-cookie'],
    'reason cast' => ['TEXT', 'module\\_cache', '\\', 'invalidationReasons.2', 'cast-result'],
    'reason rtrim' => ['TEXT', 'module\\_cache', '\\', 'invalidationReasons.3', 'rtrim-key'],
    'reason candidate' => ['TEXT', 'module\\_cache', '\\', 'invalidationReasons.4', 'candidate-rowset'],
    'reason matched' => ['TEXT', 'module\\_cache', '\\', 'invalidationReasons.5', 'matched-rowset'],
    'trace row two preserves space' => ['TEXT', 'module\\_cache', '\\', 'currentTrace.1.castText', 'module_cache '],
    'trace row two rtrim trims space' => ['TEXT', 'module\\_cache', '\\', 'currentTrace.1.rtrimKey', 'module_cache'],
    'trace row four rtrim keeps tab' => ['TEXT', 'module\\_cache', '\\', 'currentTrace.3.rtrimKey', "module_cache\t"],
    'trace row four candidate due rtrim range' => ['TEXT', 'module\\_cache', '\\', 'currentTrace.3.candidate', true],
    'trace row four residual false' => ['TEXT', 'module\\_cache', '\\', 'currentTrace.3.matched', false],
    'trace blob storage' => ['TEXT', 'module\\_cache', '\\', 'currentTrace.5.originalStorage', 'blob'],
    'trace blob text casts as text' => ['TEXT', 'module\\_cache', '\\', 'currentTrace.5.castStorage', 'text'],
    'wildcard range lower' => ['TEXT', 'module\\_cache%', '\\', 'range.lowerInclusive', 'module_cache'],
    'wildcard current candidates' => ['TEXT', 'module\\_cache%', '\\', 'currentCandidateRowids', [1, 2, 3, 4, 5]],
    'wildcard current rowids include spaces and tab' => ['TEXT', 'module\\_cache%', '\\', 'currentRowids', [1, 2, 3, 4, 5]],
    'wildcard next rowids include fresh' => ['TEXT', 'module\\_cache%', '\\', 'nextRowids', [1, 2, 3, 4, 5, 15]],
    'wildcard residual rejects empty' => ['TEXT', 'module\\_cache%', '\\', 'currentResidualRejectedRowids', []],
    'blob exact current candidate uses rtrim blob' => ['BLOB', 'module\\_blob', '\\', 'currentCandidateRowids', [6]],
    'blob exact current rowids reject padded blob' => ['BLOB', 'module\\_blob', '\\', 'currentRowids', []],
    'blob exact next rowids match shortened blob' => ['BLOB', 'module\\_blob', '\\', 'nextRowids', [6]],
    'blob wildcard current matches padded blob' => ['BLOB', 'module\\_blob%', '\\', 'currentRowids', [6]],
    'blob wildcard next matches shortened blob' => ['BLOB', 'module\\_blob%', '\\', 'nextRowids', [6]],
    'integer like current rowids' => ['INTEGER', '4%', null, 'currentRowids', [7, 8]],
    'integer like next rowids' => ['INTEGER', '4%', null, 'nextRowids', [7]],
    'integer cast value prefix' => ['INTEGER', '4%', null, 'currentTrace.6.castValue', 42],
    'real like current rowids' => ['REAL', '4%', null, 'currentRowids', [7, 8]],
    'real like next rowids' => ['REAL', '4%', null, 'nextRowids', [7]],
    'numeric false current zero' => ['NUMERIC', '0', null, 'currentRowids', [1, 2, 3, 4, 5, 6, 9, 11, 12, 13, 14]],
    'numeric false exits next' => ['NUMERIC', '0', null, 'exitedRowids', [9, 14]],
    'numeric true enters next one' => ['NUMERIC', '1', null, 'enteredRowids', [9]],
    'literal percent escaped prefix' => ['TEXT', 'module\\_100\\%\\_enabled', '\\', 'patternPrefix', 'module_100%_enabled'],
    'literal percent current candidate trims space' => ['TEXT', 'module\\_100\\%\\_enabled', '\\', 'currentCandidateRowids', [13]],
    'literal percent current residual rejects padded' => ['TEXT', 'module\\_100\\%\\_enabled', '\\', 'currentResidualRejectedRowids', [13]],
    'literal percent next matches trimmed' => ['TEXT', 'module\\_100\\%\\_enabled', '\\', 'nextRowids', [13]],
    'uppercase binary exact does not match lowercase pattern' => ['TEXT', 'module\\_cache', '\\', 'currentTrace.11.candidate', false],
    'leading wildcard has no range' => ['TEXT', '%cache', null, 'range', null],
    'leading wildcard has no candidates' => ['TEXT', '%cache', null, 'currentCandidateRowids', []],
    'leading wildcard reason no prefix' => ['TEXT', '%cache', null, 'invalidationReasons.2', 'no-prefix-range'],
    'dependency cast' => ['TEXT', 'module\\_cache', '\\', 'dependencies.0', 'sqlite-select-cast-expression'],
    'dependency rtrim range' => ['TEXT', 'module\\_cache', '\\', 'dependencies.1', 'sqlite-rtrim-like-prefix-range'],
    'dependency residual' => ['TEXT', 'module\\_cache', '\\', 'dependencies.2', 'sqlite-like-binary-residual'],
    'dependency current source' => ['TEXT', 'module\\_cache', '\\', 'dependencies.3', 'sqlite-current-source-next131'],
];

foreach ($cases as $name => [$castTarget, $pattern, $escape, $path, $expected]) {
    $tests['cast rtrim like current source next131 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $castTarget, $pattern, $escape, $path, $expected): void {
        $t->same($expected, $valueAt($plan($castTarget, $pattern, $escape), $path));
    };
}

$tests['cast rtrim like current source next131 stable exact padded peer is reusable'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'module_cache'],
        ['setting_id' => 2, 'key_value' => 'module_cache '],
    ];
    $plan = SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', 'module\\_cache', '\\', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([2], $plan['currentResidualRejectedRowids']);
    $t->same([1], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['cast rtrim like current source next131 stable leading wildcard keeps no prefix reason'] = static function (TestRunner $t): void {
    $rows = [['setting_id' => 1, 'key_value' => 'module_cache']];
    $plan = SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', '%cache', null, 'stable', 'stable', 7, 7);
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['cast rtrim like current source next131 rejects malformed cast target'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT); DROP TABLE app_settings; --', 'module%'));
};

$tests['cast rtrim like current source next131 rejects missing setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan([['key_value' => 'module']], $nextRows, 'TEXT', 'module%'));
};

$tests['cast rtrim like current source next131 rejects missing setting value'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1]], $nextRows, 'TEXT', 'module%'));
};

$tests['cast rtrim like current source next131 rejects non integer setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => '1', 'key_value' => 'module']], $nextRows, 'TEXT', 'module%'));
};

$tests['cast rtrim like current source next131 rejects multi byte escape'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT', 'module!!_%', '!!'));
};

return $tests;
