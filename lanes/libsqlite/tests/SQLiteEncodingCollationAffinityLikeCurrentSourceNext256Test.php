<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current256 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_CACHE'],
    ['option_id' => 3, 'option_name' => 'plugin_percent_literal', 'option_value' => 'plugin_%'],
    ['option_id' => 4, 'option_name' => 'plugin_number_text', 'option_value' => '123'],
    ['option_id' => 5, 'option_name' => 'plugin_number_prefix', 'option_value' => '1234'],
    ['option_id' => 6, 'option_name' => 'plugin_float', 'option_value' => '12.5'],
    ['option_id' => 7, 'option_name' => 'plugin_int', 'option_value' => 123],
    ['option_id' => 8, 'option_name' => 'plugin_real', 'option_value' => 12.5],
    ['option_id' => 9, 'option_name' => 'plugin_blob_value', 'option_value' => new SQLiteBlobValue('plugin_cache')],
    ['option_id' => 10, 'option_name' => 'plugin_bad_text', 'option_value' => "plugin_\xff"],
];

$nextTwoFiveSix = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_CACHE'],
    ['option_id' => 3, 'option_name' => 'plugin_percent_literal', 'option_value' => 'plugin_%'],
    ['option_id' => 4, 'option_name' => 'plugin_number_text', 'option_value' => '123'],
    ['option_id' => 5, 'option_name' => 'plugin_number_prefix', 'option_value' => '1234'],
    ['option_id' => 6, 'option_name' => 'plugin_float', 'option_value' => '12.5'],
    ['option_id' => 7, 'option_name' => 'plugin_int', 'option_value' => '123'],
    ['option_id' => 8, 'option_name' => 'plugin_real', 'option_value' => 12.5],
    ['option_id' => 9, 'option_name' => 'plugin_blob_value', 'option_value' => new SQLiteBlobValue('plugin_cache')],
    ['option_id' => 10, 'option_name' => 'plugin_bad_text', 'option_value' => "plugin_\xff"],
    ['option_id' => 11, 'option_name' => 'plugin_new', 'option_value' => 'plugin_new'],
];

$plan256 = static fn (
    ?array $current = null,
    ?array $next = null,
    mixed $currentPattern = 'plugin%',
    mixed $nextPattern = '123%',
    ?string $escape = null,
    string $collation = 'NOCASE',
    string $currentSource = 'main.app_settings@255',
    string $nextSource = 'main.app_settings@256',
    int $currentCookie = 255,
    int $nextCookie = 256,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationPatternAffinityPlan(
    $current ?? $current256,
    $next ?? $nextTwoFiveSix,
    $currentPattern,
    $nextPattern,
    $escape,
    $collation,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt256 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases256 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFiveSix'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_value COLLATE NOCASE LIKE dynamic_pattern /* pattern TEXT affinity current-source fence */'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.app_settings@255'],
    'next source' => ['nextSource', 'main.app_settings@256'],
    'current cookie' => ['currentSchemaCookie', 255],
    'next cookie' => ['nextSchemaCookie', 256],
    'current pattern storage' => ['currentPattern.storage', 'text'],
    'current pattern text' => ['currentPattern.patternText', 'plugin%'],
    'current pattern hex' => ['currentPattern.patternHex', '706C7567696E25'],
    'current pattern key' => ['currentPattern.patternKey', 'plugin%'],
    'current prefix' => ['currentPattern.prefix', 'plugin'],
    'current range lower' => ['currentPattern.range.lowerInclusive', 'plugin'],
    'current range upper' => ['currentPattern.range.upperBound', 'plugio'],
    'current index usable' => ['currentPattern.indexUsable', true],
    'next pattern storage' => ['nextPattern.storage', 'text'],
    'next pattern text' => ['nextPattern.patternText', '123%'],
    'next pattern key' => ['nextPattern.patternKey', '123%'],
    'next prefix' => ['nextPattern.prefix', '123'],
    'next range lower' => ['nextPattern.range.lowerInclusive', '123'],
    'next range upper' => ['nextPattern.range.upperBound', '124'],
    'current candidates' => ['currentCandidateRowids', [3, 1, 2]],
    'next candidates' => ['nextCandidateRowids', [4, 7, 5]],
    'current matched' => ['currentMatchedRowids', [3, 1, 2]],
    'next matched' => ['nextMatchedRowids', [4, 7, 5]],
    'retained' => ['retainedRowids', []],
    'entered' => ['enteredRowids', [4, 5, 7]],
    'exited' => ['exitedRowids', [1, 2, 3]],
    'current false positive' => ['currentFalsePositiveRowids', []],
    'next false positive' => ['nextFalsePositiveRowids', []],
    'current malformed' => ['currentMalformedRowids', [9, 10]],
    'next malformed' => ['nextMalformedRowids', [9, 10]],
    'current blob error' => ['currentErrors.9', 'SQLite pattern-affinity LIKE nextTwoFiveSix option_value is BLOB, not text'],
    'changed storage' => ['changedStorageRowids', [7, 11]],
    'changed text' => ['changedLikeTextRowids', [11]],
    'changed key' => ['changedCollationKeyRowids', [11]],
    'changed residual' => ['changedResidualRowids', [1, 2, 3, 4, 5, 7, 11]],
    'pattern affinity flag' => ['patternUsesTextAffinity', true],
    'null flag' => ['nullPatternMakesLikeUnknown', true],
    'blob pattern flag' => ['blobPatternIsRejected', true],
    'blob value flag' => ['blobValuesDoNotMatchTextLike', true],
    'nocase flag' => ['nocaseFoldsAsciiOnly', true],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'dependency pattern' => ['dependencies.0', 'sqlite-like-pattern-text-affinity'],
    'dependency range' => ['dependencies.1', 'sqlite-like-prefix-range'],
    'dependency collation' => ['dependencies.2', 'sqlite-nocase-rtrim-collation'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFiveSix'],
];

foreach ($cases256 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFiveSix ' . $name] = static function (TestRunner $t) use ($plan256, $valueAt256, $path, $expected): void {
        $t->same($expected, $valueAt256($plan256(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFiveSix invalidation reason order'] = static function (TestRunner $t) use ($plan256): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'pattern-text',
        'pattern-collation-key',
        'storage-class',
        'like-text',
        'collation-key',
        'candidate-rowset',
        'residual-result',
        'matched-rowset',
        'malformed-text',
    ], $plan256()['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveSix stable cursor is reusable'] = static function (TestRunner $t) use ($current256, $plan256): void {
    $rows = array_values(array_filter($current256, static fn (array $row): bool => !in_array($row['option_id'] ?? null, [9, 10], true)));
    $result = $plan256(current: $rows, next: $rows, currentPattern: 'plugin%', nextPattern: 'plugin%', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same([], $result['invalidationReasons']);
    $t->same([3, 1, 2], $result['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveSix integer pattern is exact text pattern'] = static function (TestRunner $t) use ($plan256): void {
    $rows = [
        ['option_id' => 1, 'option_value' => '123'],
        ['option_id' => 2, 'option_value' => 123],
        ['option_id' => 3, 'option_value' => '1234'],
    ];
    $result = $plan256(current: $rows, next: $rows, currentPattern: 123, nextPattern: 123, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same('integer', $result['currentPattern']['storage']);
    $t->same('123', $result['currentPattern']['patternText']);
    $t->same([1, 2], $result['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveSix real pattern keeps sqlite text form'] = static function (TestRunner $t) use ($plan256): void {
    $rows = [
        ['option_id' => 1, 'option_value' => '12.5'],
        ['option_id' => 2, 'option_value' => 12.5],
        ['option_id' => 3, 'option_value' => '12'],
    ];
    $result = $plan256(current: $rows, next: $rows, currentPattern: 12.5, nextPattern: 12.5, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same('real', $result['currentPattern']['storage']);
    $t->same('12.5', $result['currentPattern']['patternText']);
    $t->same([1, 2], $result['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveSix boolean pattern uses integer text'] = static function (TestRunner $t) use ($plan256): void {
    $rows = [
        ['option_id' => 1, 'option_value' => '1'],
        ['option_id' => 2, 'option_value' => 1],
        ['option_id' => 3, 'option_value' => 'true'],
    ];
    $result = $plan256(current: $rows, next: $rows, currentPattern: true, nextPattern: true, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same('integer', $result['currentPattern']['storage']);
    $t->same('1', $result['currentPattern']['patternText']);
    $t->same([1, 2], $result['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveSix null pattern makes rows unknown'] = static function (TestRunner $t) use ($plan256): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin_cache'],
        ['option_id' => 2, 'option_value' => '123'],
    ];
    $result = $plan256(current: $rows, next: $rows, currentPattern: null, nextPattern: null, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same(true, $result['currentPattern']['unknown']);
    $t->same('pattern_is_null', $result['currentPattern']['rejectedReason']);
    $t->same([1, 2], $result['currentUnknownRowids']);
    $t->same([], $result['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveSix blob pattern is rejected before scan match'] = static function (TestRunner $t) use ($plan256): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin_cache'],
    ];
    $result = $plan256(current: $rows, next: $rows, currentPattern: new SQLiteBlobValue('plugin%'), nextPattern: 'plugin%', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same('blob', $result['currentPattern']['storage']);
    $t->same('SQLite pattern-affinity LIKE nextTwoFiveSix current pattern is BLOB, not text', $result['currentPattern']['error']);
    $t->same([1], $result['currentUnknownRowids']);
    $t->same(true, in_array('pattern-malformed', $result['invalidationReasons'], true));
};

$tests['encoding collation affinity like current source nextTwoFiveSix escaped numeric-looking pattern keeps literal percent'] = static function (TestRunner $t) use ($plan256): void {
    $rows = [
        ['option_id' => 1, 'option_value' => '123%'],
        ['option_id' => 2, 'option_value' => '1234'],
    ];
    $result = $plan256(current: $rows, next: $rows, currentPattern: '123!%%', nextPattern: '123!%%', escape: '!', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same('123%', $result['currentPattern']['prefix']);
    $t->same([1], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveSix rtrim collation disables default like range'] = static function (TestRunner $t) use ($plan256): void {
    $result = $plan256(currentPattern: 'plugin%', nextPattern: 'plugin%', collation: 'RTRIM');

    $t->same('RTRIM', $result['collation']);
    $t->same(false, $result['currentPattern']['indexUsable']);
    $t->same('default_like_requires_nocase_index', $result['currentPattern']['rejectedReason']);
    $t->same([], $result['currentCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveSix rejects invalid collation'] = static function (TestRunner $t) use ($plan256): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan256(collation: 'UNICODE'));
};

$tests['encoding collation affinity like current source nextTwoFiveSix rejects invalid escape'] = static function (TestRunner $t) use ($plan256): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan256(escape: '!!'));
};

$tests['encoding collation affinity like current source nextTwoFiveSix rejects missing option value'] = static function (TestRunner $t) use ($plan256): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan256(current: [['option_id' => 1]], next: []));
};

return $tests;
