<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current251 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache_limit', 'option_value' => 40],
    ['option_id' => 2, 'option_name' => 'plugin_cache_ratio', 'option_value' => 40.5],
    ['option_id' => 3, 'option_name' => 'plugin_cache_text', 'option_value' => '40'],
    ['option_id' => 4, 'option_name' => 'plugin_enabled', 'option_value' => true],
    ['option_id' => 5, 'option_name' => 'plugin_disabled', 'option_value' => false],
    ['option_id' => 6, 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
    ['option_id' => 7, 'option_name' => 'negative_cache', 'option_value' => -40.5],
];

$nextTwoFiveOne = [
    ['option_id' => 1, 'option_name' => 'plugin_cache_limit', 'option_value' => '40'],
    ['option_id' => 2, 'option_name' => 'plugin_cache_ratio', 'option_value' => 40.50],
    ['option_id' => 3, 'option_name' => 'plugin_cache_text', 'option_value' => 400],
    ['option_id' => 4, 'option_name' => 'plugin_enabled', 'option_value' => true],
    ['option_id' => 5, 'option_name' => 'plugin_disabled', 'option_value' => false],
    ['option_id' => 6, 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
    ['option_id' => 8, 'option_name' => 'plugin_cache_new', 'option_value' => 409],
];

$plan251 = static fn (
    mixed $currentPattern = 40,
    mixed $nextPattern = '40',
    mixed $currentEscape = null,
    mixed $nextEscape = null,
    ?array $current = null,
    ?array $next = null,
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@250',
    string $nextSource = 'main.wp_options@251',
    int $currentCookie = 250,
    int $nextCookie = 251,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressPreparedPatternAffinityPlan(
    $current ?? $current251,
    $next ?? $nextTwoFiveOne,
    $currentPattern,
    $nextPattern,
    $currentEscape,
    $nextEscape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt251 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases251 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFiveOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_value LIKE ? ESCAPE ? /* prepared pattern affinity current-source fence */'],
    'case sensitive flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'current pattern text' => ['currentPatternText', '40'],
    'next pattern text' => ['nextPatternText', '40'],
    'current pattern hex' => ['currentPatternHex', '3430'],
    'next pattern hex' => ['nextPatternHex', '3430'],
    'current pattern storage' => ['currentPatternStorageClass', 'integer'],
    'next pattern storage' => ['nextPatternStorageClass', 'text'],
    'current escape null' => ['currentEscapeText', null],
    'next escape null' => ['nextEscapeText', null],
    'prefix' => ['prefix', '40'],
    'prefix hex' => ['prefixHex', '3430'],
    'prefix chars' => ['prefixCharacters', 2],
    'binary lower' => ['binaryRange.lowerInclusive', '40'],
    'binary upper' => ['binaryRange.upperBound', '41'],
    'nocase lower' => ['noCaseRange.lowerInclusive', '40'],
    'nocase upper' => ['noCaseRange.upperBound', '41'],
    'current source' => ['currentSource', 'main.wp_options@250'],
    'next source' => ['nextSource', 'main.wp_options@251'],
    'current cookie' => ['currentSchemaCookie', 250],
    'next cookie' => ['nextSchemaCookie', 251],
    'current rowids' => ['currentRowids', [1, 3]],
    'next rowids' => ['nextRowids', [1]],
    'retained rowids' => ['retainedRowids', [1]],
    'exited rowids' => ['exitedRowids', [3]],
    'entered rowids' => ['enteredRowids', []],
    'changed value text' => ['changedValueTextRowids', []],
    'changed value storage' => ['changedValueStorageClassRowids', [1]],
    'current value text row1' => ['currentValueText.1', '40'],
    'next value text row1' => ['nextValueText.1', '40'],
    'current value storage row1' => ['currentValueStorageClasses.1', 'integer'],
    'next value storage row1' => ['nextValueStorageClasses.1', 'text'],
    'current option name' => ['currentOptionNames.1', 'plugin_cache_limit'],
    'next option name' => ['nextOptionNames.1', 'plugin_cache_limit'],
    'pattern storage flag' => ['patternStorageClassChangeInvalidatesEvenWhenTextMatches', true],
    'escape storage flag' => ['escapeStorageClassChangeInvalidatesEvenWhenTextMatches', true],
    'blob guard flag' => ['blobPatternAndBlobEscapeDoNotEnterLikeMatcher', true],
    'numeric pattern flag' => ['numericAndBooleanPatternsUseTextAffinity', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason pattern storage' => ['invalidationReasons.2', 'pattern-storage-class'],
    'reason rowset' => ['invalidationReasons.3', 'matched-rowset'],
    'reason value storage' => ['invalidationReasons.4', 'value-storage-class'],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-like-escape-tokenizer'],
    'dependency affinity' => ['dependencies.1', 'sqlite-pattern-text-affinity'],
    'dependency source' => ['dependencies.2', 'sqlite-current-source-nexttwoFiveOne'],
];

foreach ($cases251 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFiveOne ' . $name] = static function (TestRunner $t) use ($plan251, $valueAt251, $path, $expected): void {
        $t->same($expected, $valueAt251($plan251(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFiveOne stable cursor reusable'] = static function (TestRunner $t) use ($current251, $plan251): void {
    $stable = $plan251(currentPattern: '40', nextPattern: '40', current: $current251, next: $current251, currentSource: 'same', nextSource: 'same', currentCookie: 251, nextCookie: 251);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveOne pattern text change invalidates'] = static function (TestRunner $t) use ($plan251): void {
    $plan = $plan251(currentPattern: '40%', nextPattern: '4_%');
    $t->same('pattern-text', $plan['invalidationReasons'][2]);
    $t->same('matched-rowset', $plan['invalidationReasons'][4]);
    $t->same([1, 2, 3, 8], $plan['nextRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveOne escaped numeric pattern storage change'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'plain_40', 'option_value' => '40'],
        ['option_id' => 2, 'option_name' => 'plain_402', 'option_value' => '402'],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressPreparedPatternAffinityPlan($rows, $rows, '40', '40', true, '1', false, 'same', 'same', 1, 1);
    $t->same([1], $plan['currentRowids']);
    $t->same('text', $plan['nextEscapeStorageClass']);
    $t->same(['escape-storage-class'], $plan['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveOne boolean pattern text affinity'] = static function (TestRunner $t) use ($plan251): void {
    $plan = $plan251(currentPattern: true, nextPattern: '1');
    $t->same('1', $plan['currentPatternText']);
    $t->same('integer', $plan['currentPatternStorageClass']);
    $t->same([4], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveOne real pattern formatter trims zeros'] = static function (TestRunner $t) use ($plan251): void {
    $plan = $plan251(currentPattern: 40.50, nextPattern: '40.5');
    $t->same('40.5', $plan['currentPatternText']);
    $t->same([2], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveOne case sensitive exposes binary collation'] = static function (TestRunner $t) use ($plan251): void {
    $plan = $plan251(currentPattern: 'HTTPS:%', nextPattern: 'HTTPS:%', caseSensitive: true);
    $t->same('BINARY', $plan['collation']);
    $t->same([], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveOne rejects blob pattern'] = static function (TestRunner $t) use ($current251, $nextTwoFiveOne): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressPreparedPatternAffinityPlan($current251, $nextTwoFiveOne, new SQLiteBlobValue('40'), '40'));
};

$tests['encoding collation affinity like current source nextTwoFiveOne rejects blob escape'] = static function (TestRunner $t) use ($current251, $nextTwoFiveOne): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressPreparedPatternAffinityPlan($current251, $nextTwoFiveOne, '40!%', '40!%', new SQLiteBlobValue('!')));
};

$tests['encoding collation affinity like current source nextTwoFiveOne rejects invalid escape length'] = static function (TestRunner $t) use ($current251, $nextTwoFiveOne): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressPreparedPatternAffinityPlan($current251, $nextTwoFiveOne, '40!!', '40!!', '!!'));
};

$tests['encoding collation affinity like current source nextTwoFiveOne rejects missing option value'] = static function (TestRunner $t) use ($nextTwoFiveOne): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressPreparedPatternAffinityPlan([['option_id' => 1]], $nextTwoFiveOne, '40', '40'));
};

$tests['encoding collation affinity like current source nextTwoFiveOne rejects blob option value'] = static function (TestRunner $t) use ($nextTwoFiveOne): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressPreparedPatternAffinityPlan([['option_id' => 1, 'option_value' => new SQLiteBlobValue('40')]], $nextTwoFiveOne, '40', '40'));
};

return $tests;
