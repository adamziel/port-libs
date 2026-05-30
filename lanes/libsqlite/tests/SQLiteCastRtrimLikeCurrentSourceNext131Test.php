<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastRtrimLikeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'plugin_cache '],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'plugin_cache  '],
    ['option_id' => 4, 'option_name' => 'template', 'option_value' => "plugin_cache\t"],
    ['option_id' => 5, 'option_name' => 'stylesheet', 'option_value' => 'plugin_cache_extra'],
    ['option_id' => 6, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('plugin_blob ')],
    ['option_id' => 7, 'option_name' => 'retry_count', 'option_value' => '42 widgets'],
    ['option_id' => 8, 'option_name' => 'decimal_rate', 'option_value' => '4.5ms'],
    ['option_id' => 9, 'option_name' => 'zero_flag', 'option_value' => false],
    ['option_id' => 10, 'option_name' => 'null_flag', 'option_value' => null],
    ['option_id' => 11, 'option_name' => 'unicode', 'option_value' => 'plugin_eclair '],
    ['option_id' => 12, 'option_name' => 'upper', 'option_value' => 'Plugin_Cache'],
    ['option_id' => 13, 'option_name' => 'literal_under', 'option_value' => 'plugin_100%_enabled '],
    ['option_id' => 14, 'option_name' => 'theme', 'option_value' => 'theme_cache'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'plugin_cache'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'plugin_cache  '],
    ['option_id' => 4, 'option_name' => 'template', 'option_value' => "plugin_cache\t"],
    ['option_id' => 5, 'option_name' => 'stylesheet', 'option_value' => 'plugin_cache_extra_v2'],
    ['option_id' => 6, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('plugin_blob')],
    ['option_id' => 7, 'option_name' => 'retry_count', 'option_value' => 42],
    ['option_id' => 8, 'option_name' => 'decimal_rate', 'option_value' => '5.5ms'],
    ['option_id' => 9, 'option_name' => 'zero_flag', 'option_value' => true],
    ['option_id' => 10, 'option_name' => 'null_flag', 'option_value' => null],
    ['option_id' => 11, 'option_name' => 'unicode', 'option_value' => 'plugin_eclair'],
    ['option_id' => 12, 'option_name' => 'upper', 'option_value' => 'Plugin_Cache'],
    ['option_id' => 13, 'option_name' => 'literal_under', 'option_value' => 'plugin_100%_enabled'],
    ['option_id' => 15, 'option_name' => 'fresh', 'option_value' => 'plugin_cache_new'],
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
    'operator' => ['TEXT', 'plugin\\_cache', '\\', 'operator', 'LIKE'],
    'collation' => ['TEXT', 'plugin\\_cache', '\\', 'collation', 'RTRIM'],
    'case sensitive like' => ['TEXT', 'plugin\\_cache', '\\', 'caseSensitiveLike', true],
    'cast target' => ['TEXT', 'plugin\\_cache', '\\', 'castTarget', 'TEXT'],
    'pattern prefix' => ['TEXT', 'plugin\\_cache', '\\', 'patternPrefix', 'plugin_cache'],
    'prefix ascii' => ['TEXT', 'plugin\\_cache', '\\', 'prefixIsAscii', true],
    'range lower exact' => ['TEXT', 'plugin\\_cache', '\\', 'range.lowerInclusive', 'plugin_cache'],
    'range upper exact' => ['TEXT', 'plugin\\_cache', '\\', 'range.upperBound', 'plugin_cachf'],
    'index usable exact' => ['TEXT', 'plugin\\_cache', '\\', 'indexUsable', true],
    'residual scan exact' => ['TEXT', 'plugin\\_cache', '\\', 'residualScan', true],
    'like does not trim marker' => ['TEXT', 'plugin\\_cache', '\\', 'likeDoesNotTrimTrailingSpaces', true],
    'rtrim trims only space marker' => ['TEXT', 'plugin\\_cache', '\\', 'rtrimTrimsOnlySpace', true],
    'current source' => ['TEXT', 'plugin\\_cache', '\\', 'currentSource', 'main.app_settings@130'],
    'next source' => ['TEXT', 'plugin\\_cache', '\\', 'nextSource', 'main.app_settings@131'],
    'current schema cookie' => ['TEXT', 'plugin\\_cache', '\\', 'currentSchemaCookie', 130],
    'next schema cookie' => ['TEXT', 'plugin\\_cache', '\\', 'nextSchemaCookie', 131],
    'current exact candidates include rtrim padded and tab peer' => ['TEXT', 'plugin\\_cache', '\\', 'currentCandidateRowids', [1, 2, 3, 4, 5]],
    'next exact candidates include fresh prefix follower' => ['TEXT', 'plugin\\_cache', '\\', 'nextCandidateRowids', [1, 2, 3, 4, 5, 15]],
    'current residual rejects padded peers' => ['TEXT', 'plugin\\_cache', '\\', 'currentResidualRejectedRowids', [2, 3, 4, 5]],
    'next residual rejects padded tab and longer text' => ['TEXT', 'plugin\\_cache', '\\', 'nextResidualRejectedRowids', [3, 4, 5, 15]],
    'current exact rowids' => ['TEXT', 'plugin\\_cache', '\\', 'currentRowids', [1]],
    'next exact rowids include trimmed repair' => ['TEXT', 'plugin\\_cache', '\\', 'nextRowids', [1, 2]],
    'retained exact rowids' => ['TEXT', 'plugin\\_cache', '\\', 'retainedRowids', [1]],
    'entered exact rowids' => ['TEXT', 'plugin\\_cache', '\\', 'enteredRowids', [2]],
    'exited exact rowids' => ['TEXT', 'plugin\\_cache', '\\', 'exitedRowids', []],
    'changed cast rowids text' => ['TEXT', 'plugin\\_cache', '\\', 'changedCastRowids', [2, 5, 6, 7, 8, 9, 11, 13, 14, 15]],
    'changed rtrim keys text' => ['TEXT', 'plugin\\_cache', '\\', 'changedRtrimKeyRowids', [5, 7, 8, 9, 14, 15]],
    'changed candidate rowids text' => ['TEXT', 'plugin\\_cache', '\\', 'changedCandidateRowids', [14, 15]],
    'changed match rowids text' => ['TEXT', 'plugin\\_cache', '\\', 'changedMatchRowids', [2, 14, 15]],
    'invalidated exact' => ['TEXT', 'plugin\\_cache', '\\', 'cursorInvalidated', true],
    'not reusable exact' => ['TEXT', 'plugin\\_cache', '\\', 'cursorReusable', false],
    'reason source' => ['TEXT', 'plugin\\_cache', '\\', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['TEXT', 'plugin\\_cache', '\\', 'invalidationReasons.1', 'schema-cookie'],
    'reason cast' => ['TEXT', 'plugin\\_cache', '\\', 'invalidationReasons.2', 'cast-result'],
    'reason rtrim' => ['TEXT', 'plugin\\_cache', '\\', 'invalidationReasons.3', 'rtrim-key'],
    'reason candidate' => ['TEXT', 'plugin\\_cache', '\\', 'invalidationReasons.4', 'candidate-rowset'],
    'reason matched' => ['TEXT', 'plugin\\_cache', '\\', 'invalidationReasons.5', 'matched-rowset'],
    'trace row two preserves space' => ['TEXT', 'plugin\\_cache', '\\', 'currentTrace.1.castText', 'plugin_cache '],
    'trace row two rtrim trims space' => ['TEXT', 'plugin\\_cache', '\\', 'currentTrace.1.rtrimKey', 'plugin_cache'],
    'trace row four rtrim keeps tab' => ['TEXT', 'plugin\\_cache', '\\', 'currentTrace.3.rtrimKey', "plugin_cache\t"],
    'trace row four candidate due rtrim range' => ['TEXT', 'plugin\\_cache', '\\', 'currentTrace.3.candidate', true],
    'trace row four residual false' => ['TEXT', 'plugin\\_cache', '\\', 'currentTrace.3.matched', false],
    'trace blob storage' => ['TEXT', 'plugin\\_cache', '\\', 'currentTrace.5.originalStorage', 'blob'],
    'trace blob text casts as text' => ['TEXT', 'plugin\\_cache', '\\', 'currentTrace.5.castStorage', 'text'],
    'wildcard range lower' => ['TEXT', 'plugin\\_cache%', '\\', 'range.lowerInclusive', 'plugin_cache'],
    'wildcard current candidates' => ['TEXT', 'plugin\\_cache%', '\\', 'currentCandidateRowids', [1, 2, 3, 4, 5]],
    'wildcard current rowids include spaces and tab' => ['TEXT', 'plugin\\_cache%', '\\', 'currentRowids', [1, 2, 3, 4, 5]],
    'wildcard next rowids include fresh' => ['TEXT', 'plugin\\_cache%', '\\', 'nextRowids', [1, 2, 3, 4, 5, 15]],
    'wildcard residual rejects empty' => ['TEXT', 'plugin\\_cache%', '\\', 'currentResidualRejectedRowids', []],
    'blob exact current candidate uses rtrim blob' => ['BLOB', 'plugin\\_blob', '\\', 'currentCandidateRowids', [6]],
    'blob exact current rowids reject padded blob' => ['BLOB', 'plugin\\_blob', '\\', 'currentRowids', []],
    'blob exact next rowids match shortened blob' => ['BLOB', 'plugin\\_blob', '\\', 'nextRowids', [6]],
    'blob wildcard current matches padded blob' => ['BLOB', 'plugin\\_blob%', '\\', 'currentRowids', [6]],
    'blob wildcard next matches shortened blob' => ['BLOB', 'plugin\\_blob%', '\\', 'nextRowids', [6]],
    'integer like current rowids' => ['INTEGER', '4%', null, 'currentRowids', [7, 8]],
    'integer like next rowids' => ['INTEGER', '4%', null, 'nextRowids', [7]],
    'integer cast value prefix' => ['INTEGER', '4%', null, 'currentTrace.6.castValue', 42],
    'real like current rowids' => ['REAL', '4%', null, 'currentRowids', [7, 8]],
    'real like next rowids' => ['REAL', '4%', null, 'nextRowids', [7]],
    'numeric false current zero' => ['NUMERIC', '0', null, 'currentRowids', [1, 2, 3, 4, 5, 6, 9, 11, 12, 13, 14]],
    'numeric false exits next' => ['NUMERIC', '0', null, 'exitedRowids', [9, 14]],
    'numeric true enters next one' => ['NUMERIC', '1', null, 'enteredRowids', [9]],
    'literal percent escaped prefix' => ['TEXT', 'plugin\\_100\\%\\_enabled', '\\', 'patternPrefix', 'plugin_100%_enabled'],
    'literal percent current candidate trims space' => ['TEXT', 'plugin\\_100\\%\\_enabled', '\\', 'currentCandidateRowids', [13]],
    'literal percent current residual rejects padded' => ['TEXT', 'plugin\\_100\\%\\_enabled', '\\', 'currentResidualRejectedRowids', [13]],
    'literal percent next matches trimmed' => ['TEXT', 'plugin\\_100\\%\\_enabled', '\\', 'nextRowids', [13]],
    'uppercase binary exact does not match lowercase pattern' => ['TEXT', 'plugin\\_cache', '\\', 'currentTrace.11.candidate', false],
    'leading wildcard has no range' => ['TEXT', '%cache', null, 'range', null],
    'leading wildcard has no candidates' => ['TEXT', '%cache', null, 'currentCandidateRowids', []],
    'leading wildcard reason no prefix' => ['TEXT', '%cache', null, 'invalidationReasons.2', 'no-prefix-range'],
    'dependency cast' => ['TEXT', 'plugin\\_cache', '\\', 'dependencies.0', 'sqlite-select-cast-expression'],
    'dependency rtrim range' => ['TEXT', 'plugin\\_cache', '\\', 'dependencies.1', 'sqlite-rtrim-like-prefix-range'],
    'dependency residual' => ['TEXT', 'plugin\\_cache', '\\', 'dependencies.2', 'sqlite-like-binary-residual'],
    'dependency current source' => ['TEXT', 'plugin\\_cache', '\\', 'dependencies.3', 'sqlite-current-source-next131'],
];

foreach ($cases as $name => [$castTarget, $pattern, $escape, $path, $expected]) {
    $tests['cast rtrim like current source next131 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $castTarget, $pattern, $escape, $path, $expected): void {
        $t->same($expected, $valueAt($plan($castTarget, $pattern, $escape), $path));
    };
}

$tests['cast rtrim like current source next131 stable exact padded peer is reusable'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin_cache'],
        ['option_id' => 2, 'option_value' => 'plugin_cache '],
    ];
    $plan = SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', 'plugin\\_cache', '\\', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([2], $plan['currentResidualRejectedRowids']);
    $t->same([1], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['cast rtrim like current source next131 stable leading wildcard keeps no prefix reason'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1, 'option_value' => 'plugin_cache']];
    $plan = SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', '%cache', null, 'stable', 'stable', 7, 7);
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['cast rtrim like current source next131 rejects malformed cast target'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT); DROP TABLE wp_options; --', 'plugin%'));
};

$tests['cast rtrim like current source next131 rejects missing option id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan([['option_value' => 'plugin']], $nextRows, 'TEXT', 'plugin%'));
};

$tests['cast rtrim like current source next131 rejects missing option value'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan([['option_id' => 1]], $nextRows, 'TEXT', 'plugin%'));
};

$tests['cast rtrim like current source next131 rejects non integer option id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan([['option_id' => '1', 'option_value' => 'plugin']], $nextRows, 'TEXT', 'plugin%'));
};

$tests['cast rtrim like current source next131 rejects multi byte escape'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT', 'plugin!!_%', '!!'));
};

return $tests;
