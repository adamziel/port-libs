<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current238 = [
    ['setting_id' => 1, 'key_name' => 'retry_timeout_real', 'key_value' => 100.0],
    ['setting_id' => 2, 'key_name' => 'retry_timeout_integer', 'key_value' => 100],
    ['setting_id' => 3, 'key_name' => 'retry_timeout_text', 'key_value' => '100.0'],
    ['setting_id' => 4, 'key_name' => 'retry_timeout_real_long', 'key_value' => 100000.0],
    ['setting_id' => 5, 'key_name' => 'retry_timeout_fraction', 'key_value' => 120.5],
    ['setting_id' => 6, 'key_name' => 'retry_timeout_exponent', 'key_value' => 1.0e-5],
    ['setting_id' => 7, 'key_name' => 'retry_timeout_bool', 'key_value' => true],
    ['setting_id' => 8, 'key_name' => 'retry_timeout_blob', 'key_value' => new SQLiteBlobValue('100.0')],
    ['setting_id' => 9, 'key_name' => 'retry_timeout_null', 'key_value' => null],
    ['setting_id' => 10, 'key_name' => 'retry_timeout_case', 'key_value' => 'CACHE100.0'],
];

$nextTwoThreeEight = [
    ['setting_id' => 1, 'key_name' => 'retry_timeout_real', 'key_value' => 100.0],
    ['setting_id' => 2, 'key_name' => 'retry_timeout_integer_promoted', 'key_value' => 100.0],
    ['setting_id' => 3, 'key_name' => 'retry_timeout_text_changed', 'key_value' => '100'],
    ['setting_id' => 4, 'key_name' => 'retry_timeout_real_long', 'key_value' => 100000.0],
    ['setting_id' => 5, 'key_name' => 'retry_timeout_fraction', 'key_value' => 120.5],
    ['setting_id' => 6, 'key_name' => 'retry_timeout_exponent', 'key_value' => 1.0e-5],
    ['setting_id' => 7, 'key_name' => 'retry_timeout_bool', 'key_value' => false],
    ['setting_id' => 8, 'key_name' => 'retry_timeout_blob', 'key_value' => new SQLiteBlobValue('100.0')],
    ['setting_id' => 9, 'key_name' => 'retry_timeout_null', 'key_value' => null],
    ['setting_id' => 10, 'key_name' => 'retry_timeout_case', 'key_value' => 'cache100.0'],
    ['setting_id' => 11, 'key_name' => 'retry_timeout_new_real', 'key_value' => 100.25],
];

$plan238 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = '100.%',
    ?string $escape = null,
    bool $caseSensitive = true,
    string $currentSource = 'main.app_settings@237',
    string $nextSource = 'main.app_settings@238',
    int $currentCookie = 237,
    int $nextCookie = 238,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRealTextAffinityLikePlan(
    $current ?? $current238,
    $next ?? $nextTwoThreeEight,
    $pattern,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt238 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases238 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoThreeEight'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'CAST(key_value AS TEXT) COLLATE BINARY LIKE ? /* REAL text-affinity decimal/exponent preservation */'],
    'pattern' => ['pattern', '100.%'],
    'pattern hex' => ['patternBytesHex', '3130302e25'],
    'pattern characters' => ['patternCharacterCount', 5],
    'escape' => ['escape', null],
    'case flag' => ['caseSensitiveLike', true],
    'collation' => ['collation', 'BINARY'],
    'prefix' => ['prefix', '100.'],
    'prefix hex' => ['prefixBytesHex', '3130302e'],
    'prefix characters' => ['prefixCharacters', 4],
    'range lower' => ['rangeLowerInclusive', '100.'],
    'range upper' => ['rangeUpperBound', '100/'],
    'current source' => ['currentSource', 'main.app_settings@237'],
    'next source' => ['nextSource', 'main.app_settings@238'],
    'current cookie' => ['currentSchemaCookie', 237],
    'next cookie' => ['nextSchemaCookie', 238],
    'current matched rowids' => ['currentMatchedRowids', [1, 3]],
    'next matched rowids' => ['nextMatchedRowids', [1, 2, 11]],
    'retained rowids' => ['retainedMatchedRowids', [1]],
    'exited rowids' => ['exitedMatchedRowids', [3]],
    'entered rowids' => ['enteredMatchedRowids', [2, 11]],
    'current unknown' => ['currentUnknownRowids', [8, 9]],
    'next unknown' => ['nextUnknownRowids', [8, 9]],
    'changed text rowids' => ['changedTextRowids', [2, 3, 7, 10]],
    'changed truth rowids' => ['changedLikeTruthRowids', [2, 3]],
    'changed storage rowids' => ['changedStorageRowids', [2]],
    'current real keeps decimal' => ['currentTexts.1', '100.0'],
    'current integer no decimal' => ['currentTexts.2', '100'],
    'next promoted real keeps decimal' => ['nextTexts.2', '100.0'],
    'current large real keeps decimal' => ['currentTexts.4', '100000.0'],
    'current fraction trims insignificant zero only' => ['currentTexts.5', '120.5'],
    'current exponent keeps marker' => ['currentTexts.6', '1.0e-05'],
    'next bool text' => ['nextTexts.7', '0'],
    'next new real fraction' => ['nextTexts.11', '100.25'],
    'current real hex' => ['currentTextHex.1', '3130302e30'],
    'current exponent hex' => ['currentTextHex.6', '312e30652d3035'],
    'next new real hex' => ['nextTextHex.11', '3130302e3235'],
    'current storage real' => ['currentStorage.1', 'real'],
    'current storage integer' => ['currentStorage.2', 'integer'],
    'next storage promoted' => ['nextStorage.2', 'real'],
    'current like integer false' => ['currentLikeResults.2', false],
    'next like promoted true' => ['nextLikeResults.2', true],
    'current text like true' => ['currentLikeResults.3', true],
    'next text like false' => ['nextLikeResults.3', false],
    'current real tokens' => ['currentPatternTokens.1', ['31', '30', '30', '2e', '30']],
    'current exponent tokens' => ['currentPatternTokens.6', ['31', '2e', '30', '65', '2d', '30', '35']],
    'next new real tokens' => ['nextPatternTokens.11', ['31', '30', '30', '2e', '32', '35']],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason rowset' => ['invalidationReasons.2', 'matched-rowset'],
    'reason real affinity' => ['invalidationReasons.3', 'real-text-affinity'],
    'reason truth' => ['invalidationReasons.4', 'like-truth'],
    'reason storage' => ['invalidationReasons.5', 'storage-class'],
    'real decimal flag' => ['realIntegerValuedTextKeepsDecimal', true],
    'exponent flag' => ['realExponentTextKeepsExponentMarker', true],
    'integer flag' => ['integerTextDoesNotGainDecimal', true],
    'unknown flag' => ['nullAndBlobRemainUnknown', true],
    'residual flag' => ['likeResidualRunsAfterTextAffinity', true],
    'dependency real affinity' => ['dependencies.0', 'sqlite-real-text-affinity'],
    'dependency range' => ['dependencies.1', 'sqlite-like-prefix-range'],
    'dependency residual' => ['dependencies.2', 'sqlite-like-residual'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoThreeEight'],
];

foreach ($cases238 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoThreeEight ' . $name] = static function (TestRunner $t) use ($plan238, $valueAt238, $path, $expected): void {
        $t->same($expected, $valueAt238($plan238(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoThreeEight stable cursor is reusable'] = static function (TestRunner $t) use ($current238, $plan238): void {
    $stable = $plan238(current: $current238, next: $current238, currentSource: 'stable', nextSource: 'stable', currentCookie: 238, nextCookie: 238);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoThreeEight escaped decimal prefix matches real text'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 100.0],
        ['setting_id' => 2, 'key_value' => 100],
        ['setting_id' => 3, 'key_value' => '100x0'],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRealTextAffinityLikePlan($rows, $rows, '100!.%', '!', true, 'same', 'same', 1, 1);
    $t->same([1], $plan['currentMatchedRowids']);
    $t->same('100.', $plan['prefix']);
};

$tests['encoding collation affinity like current source nextTwoThreeEight nocase folds only text after affinity'] = static function (TestRunner $t) use ($plan238): void {
    $nocase = $plan238(pattern: 'cache%', caseSensitive: false);
    $t->same('NOCASE', $nocase['collation']);
    $t->same([10], $nocase['currentMatchedRowids']);
    $t->same([10], $nocase['nextMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeEight exponent pattern matches padded exponent'] = static function (TestRunner $t) use ($current238, $nextTwoThreeEight): void {
    $exp = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRealTextAffinityLikePlan($current238, $nextTwoThreeEight, '1.0e-__', null, true);
    $t->same([6], $exp['currentMatchedRowids']);
    $t->same([6], $exp['nextMatchedRowids']);
    $t->same('1.0e-', $exp['prefix']);
};

$tests['encoding collation affinity like current source nextTwoThreeEight null and blob stay outside like rowset'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => new SQLiteBlobValue('100.0')],
        ['setting_id' => 2, 'key_value' => null],
        ['setting_id' => 3, 'key_value' => 100.0],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRealTextAffinityLikePlan($rows, $rows, '100.%', null, true, 'same', 'same', 1, 1);
    $t->same([3], $plan['currentMatchedRowids']);
    $t->same([1, 2], $plan['currentUnknownRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeEight rejects multi character escape'] = static function (TestRunner $t) use ($current238, $nextTwoThreeEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRealTextAffinityLikePlan($current238, $nextTwoThreeEight, '100!.%', '!!'));
};

$tests['encoding collation affinity like current source nextTwoThreeEight rejects missing key value'] = static function (TestRunner $t) use ($nextTwoThreeEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRealTextAffinityLikePlan([['setting_id' => 1]], $nextTwoThreeEight));
};

$tests['encoding collation affinity like current source nextTwoThreeEight rejects non scalar value'] = static function (TestRunner $t) use ($nextTwoThreeEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRealTextAffinityLikePlan([['setting_id' => 1, 'key_value' => ['100.0']]], $nextTwoThreeEight));
};

return $tests;
