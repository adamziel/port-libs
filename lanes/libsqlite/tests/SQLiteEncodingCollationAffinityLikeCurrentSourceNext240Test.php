<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current240 = [
    ['option_id' => 1, 'option_name' => 'rewrite_rules_version', 'option_value' => 404],
    ['option_id' => 2, 'option_name' => 'rewrite_rules_preview', 'option_value' => 405.5],
    ['option_id' => 3, 'option_name' => 'rewrite_rules_text', 'option_value' => '0404'],
    ['option_id' => 4, 'option_name' => 'cache_enabled', 'option_value' => true],
    ['option_id' => 5, 'option_name' => 'cache_disabled', 'option_value' => false],
    ['option_id' => 6, 'option_name' => 'ratio', 'option_value' => 4.2500],
    ['option_id' => 7, 'option_name' => 'blob', 'option_value' => new SQLiteBlobValue('404')],
    ['option_id' => 8, 'option_name' => 'null_value', 'option_value' => null],
    ['option_id' => 9, 'option_name' => 'negative', 'option_value' => -40.5],
    ['option_id' => 10, 'option_name' => 'scientific', 'option_value' => 4000000000000000.0],
];

$nextTwoFourZero = [
    ['option_id' => 1, 'option_name' => 'rewrite_rules_version', 'option_value' => '404'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules_preview', 'option_value' => 405.50],
    ['option_id' => 3, 'option_name' => 'rewrite_rules_text', 'option_value' => 404],
    ['option_id' => 4, 'option_name' => 'cache_enabled', 'option_value' => true],
    ['option_id' => 5, 'option_name' => 'cache_disabled', 'option_value' => false],
    ['option_id' => 6, 'option_name' => 'ratio', 'option_value' => 4.25],
    ['option_id' => 9, 'option_name' => 'negative', 'option_value' => -40.5],
    ['option_id' => 10, 'option_name' => 'scientific', 'option_value' => 4000000000000000.0],
    ['option_id' => 11, 'option_name' => 'rewrite_rules_new', 'option_value' => 409],
];

$plan240 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = '40%',
    ?string $escape = null,
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@239',
    string $nextSource = 'main.wp_options@240',
    int $currentCookie = 239,
    int $nextCookie = 240,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueNumericLikePlan(
    $current ?? $current240,
    $next ?? $nextTwoFourZero,
    $pattern,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt240 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases240 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFourZero'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'CAST(option_value AS NUMERIC) LIKE ? ESCAPE ? /* numeric affinity current-source fence */'],
    'pattern' => ['pattern', '40%'],
    'pattern hex' => ['patternHex', '343025'],
    'escape null' => ['escape', null],
    'escape hex null' => ['escapeHex', null],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'prefix' => ['prefix', '40'],
    'prefix hex' => ['prefixHex', '3430'],
    'prefix chars' => ['prefixCharacters', 2],
    'prefix ascii' => ['prefixIsAscii', true],
    'has wildcard' => ['hasWildcard', true],
    'binary lower' => ['binaryRange.lowerInclusive', '40'],
    'binary upper' => ['binaryRange.upperBound', '41'],
    'nocase lower' => ['noCaseRange.lowerInclusive', '40'],
    'nocase upper' => ['noCaseRange.upperBound', '41'],
    'current source' => ['currentSource', 'main.wp_options@239'],
    'next source' => ['nextSource', 'main.wp_options@240'],
    'current cookie' => ['currentSchemaCookie', 239],
    'next cookie' => ['nextSchemaCookie', 240],
    'current rowids' => ['currentRowids', [1, 2]],
    'next rowids' => ['nextRowids', [1, 3, 2, 11]],
    'retained rowids' => ['retainedRowids', [1, 2]],
    'exited rowids' => ['exitedRowids', []],
    'entered rowids' => ['enteredRowids', [3, 11]],
    'changed formatted' => ['changedFormattedRowids', []],
    'changed storage' => ['changedStorageClassRowids', [1]],
    'changed bytes' => ['changedFormattedBytesRowids', []],
    'current formatted text absent' => ['currentFormatted.3', null],
    'current formatted int' => ['currentFormatted.1', '404'],
    'current formatted real' => ['currentFormatted.2', '405.5'],
    'next formatted int' => ['nextFormatted.3', '404'],
    'next formatted new' => ['nextFormatted.11', '409'],
    'current hex text absent' => ['currentFormattedHex.3', null],
    'next hex int' => ['nextFormattedHex.3', '343034'],
    'current storage text absent' => ['currentStorageClasses.3', null],
    'current storage integer' => ['currentStorageClasses.1', 'integer'],
    'current storage real' => ['currentStorageClasses.2', 'real'],
    'next storage text' => ['nextStorageClasses.1', 'text'],
    'next storage integer' => ['nextStorageClasses.3', 'integer'],
    'current option name' => ['currentOptionNames.1', 'rewrite_rules_version'],
    'next option name' => ['nextOptionNames.11', 'rewrite_rules_new'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason rowset' => ['invalidationReasons.2', 'matched-rowset'],
    'reason storage' => ['invalidationReasons.3', 'storage-class'],
    'text affinity flag' => ['integerRealAndBooleanUseTextAffinityForLike', true],
    'blob null flag' => ['blobAndNullStayNonTextForNumericLike', true],
    'storage invalidates flag' => ['storageClassChangeInvalidatesEvenWhenLikeTextMatches', true],
    'real formatting flag' => ['sqliteRealFormattingUsesSignificantDigits', true],
    'dependency numeric' => ['dependencies.0', 'sqlite-numeric-affinity-format'],
    'dependency tokenizer' => ['dependencies.1', 'sqlite-like-escape-tokenizer'],
    'dependency source' => ['dependencies.2', 'sqlite-current-source-nexttwoFourZero'],
];

foreach ($cases240 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFourZero ' . $name] = static function (TestRunner $t) use ($plan240, $valueAt240, $path, $expected): void {
        $t->same($expected, $valueAt240($plan240(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFourZero stable cursor reusable'] = static function (TestRunner $t) use ($current240, $plan240): void {
    $stable = $plan240(current: $current240, next: $current240, currentSource: 'same', nextSource: 'same', currentCookie: 240, nextCookie: 240);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFourZero boolean numeric text matches'] = static function (TestRunner $t) use ($plan240): void {
    $plan = $plan240(pattern: '_');
    $t->same([5, 4], $plan['currentRowids']);
    $t->same('1', $plan['currentFormatted'][4]);
    $t->same('0', $plan['currentFormatted'][5]);
};

$tests['encoding collation affinity like current source nextTwoFourZero real formatter trims trailing zeroes'] = static function (TestRunner $t) use ($plan240): void {
    $plan = $plan240(pattern: '4.25');
    $t->same([6], $plan['currentRowids']);
    $t->same('4.25', $plan['currentFormatted'][6]);
};

$tests['encoding collation affinity like current source nextTwoFourZero negative real participates in like'] = static function (TestRunner $t) use ($plan240): void {
    $plan = $plan240(pattern: '-40%');
    $t->same([9], $plan['currentRowids']);
    $t->same('-40.5', $plan['currentFormatted'][9]);
};

$tests['encoding collation affinity like current source nextTwoFourZero blob and null are skipped'] = static function (TestRunner $t) use ($plan240): void {
    $plan = $plan240(pattern: '%04%');
    $t->same([3, 1], $plan['currentRowids']);
    $t->same(false, array_key_exists(7, $plan['currentFormatted']));
    $t->same(false, array_key_exists(8, $plan['currentFormatted']));
};

$tests['encoding collation affinity like current source nextTwoFourZero escaped literal percent in numeric text'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'literal', 'option_value' => '40%'],
        ['option_id' => 2, 'option_name' => 'numeric', 'option_value' => 404],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueNumericLikePlan($rows, $rows, '40!%', '!', false, 'same', 'same', 1, 1);
    $t->same([1], $plan['currentRowids']);
    $t->same('21', $plan['escapeHex']);
};

$tests['encoding collation affinity like current source nextTwoFourZero case sensitive changes collation only'] = static function (TestRunner $t) use ($plan240): void {
    $plan = $plan240(pattern: '40%', caseSensitive: true);
    $t->same('BINARY', $plan['collation']);
    $t->same([1, 2], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourZero storage-only change invalidates'] = static function (TestRunner $t): void {
    $current = [['option_id' => 1, 'option_name' => 'same_text', 'option_value' => 404]];
    $next = [['option_id' => 1, 'option_name' => 'same_text', 'option_value' => '404']];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueNumericLikePlan($current, $next, '404', null, false, 'same', 'same', 1, 1);
    $t->same([], $plan['changedFormattedRowids']);
    $t->same([1], $plan['changedStorageClassRowids']);
    $t->same(['storage-class'], $plan['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFourZero rejects missing option value'] = static function (TestRunner $t) use ($nextTwoFourZero): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueNumericLikePlan([['option_id' => 1]], $nextTwoFourZero, '40%'));
};

$tests['encoding collation affinity like current source nextTwoFourZero rejects array option value'] = static function (TestRunner $t) use ($nextTwoFourZero): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueNumericLikePlan([['option_id' => 1, 'option_value' => ['404']]], $nextTwoFourZero, '40%'));
};

$tests['encoding collation affinity like current source nextTwoFourZero rejects invalid escape'] = static function (TestRunner $t) use ($current240, $nextTwoFourZero): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueNumericLikePlan($current240, $nextTwoFourZero, '40!!', '!!'));
};

return $tests;
