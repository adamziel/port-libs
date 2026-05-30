<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current254 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%literal'],
    ['option_id' => 3, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_cache'],
    ['option_id' => 4, 'option_name' => 'plugin_number', 'option_value' => 40],
    ['option_id' => 5, 'option_name' => 'plugin_real', 'option_value' => 40.5],
    ['option_id' => 6, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob')],
    ['option_id' => 7, 'option_name' => 'plugin_null', 'option_value' => null],
    ['option_id' => 8, 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
];

$nextTwoFiveFour = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%literal'],
    ['option_id' => 3, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_cache'],
    ['option_id' => 4, 'option_name' => 'plugin_number', 'option_value' => '40'],
    ['option_id' => 5, 'option_name' => 'plugin_real', 'option_value' => 40.5],
    ['option_id' => 6, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob')],
    ['option_id' => 7, 'option_name' => 'plugin_null', 'option_value' => null],
    ['option_id' => 9, 'option_name' => 'plugin_new', 'option_value' => 'plugin_new'],
];

$plan254 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_%',
    mixed $currentEscape = null,
    bool $currentEscapeIsExplicit = true,
    mixed $nextEscape = '!',
    bool $nextEscapeIsExplicit = true,
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@253',
    string $nextSource = 'main.wp_options@254',
    int $currentCookie = 253,
    int $nextCookie = 254,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNullableEscapeLikePlan(
    $current ?? $current254,
    $next ?? $nextTwoFiveFour,
    $pattern,
    $currentEscape,
    $currentEscapeIsExplicit,
    $nextEscape,
    $nextEscapeIsExplicit,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt254 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases254 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFiveFour'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_value LIKE ? ESCAPE ? /* explicit SQL NULL ESCAPE is UNKNOWN, not omitted ESCAPE */'],
    'pattern' => ['pattern', 'plugin!_%'],
    'pattern hex' => ['patternHex', '706c7567696e215f25'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'current escape text' => ['currentEscapeText', null],
    'next escape text' => ['nextEscapeText', '!'],
    'current escape hex' => ['currentEscapeHex', null],
    'next escape hex' => ['nextEscapeHex', '21'],
    'current escape storage' => ['currentEscapeStorageClass', null],
    'next escape storage' => ['nextEscapeStorageClass', 'text'],
    'current escape explicit' => ['currentEscapeWasExplicit', true],
    'next escape explicit' => ['nextEscapeWasExplicit', true],
    'current escape sql null' => ['currentEscapeIsSqlNull', true],
    'next escape sql null' => ['nextEscapeIsSqlNull', false],
    'omitted flag' => ['omittedEscapeStillUsesLikeDefault', true],
    'null flag' => ['explicitNullEscapeForcesUnknownPredicate', true],
    'not like flag' => ['notLikeWouldAlsoRemainUnknown', true],
    'prefix' => ['prefix', 'plugin_'],
    'prefix hex' => ['prefixHex', '706c7567696e5f'],
    'prefix chars' => ['prefixCharacters', 7],
    'binary lower' => ['binaryRange.lowerInclusive', 'plugin_'],
    'binary upper' => ['binaryRange.upperBound', 'plugin`'],
    'nocase lower' => ['noCaseRange.lowerInclusive', 'plugin_'],
    'nocase upper' => ['noCaseRange.upperBound', 'plugin`'],
    'current source' => ['currentSource', 'main.wp_options@253'],
    'next source' => ['nextSource', 'main.wp_options@254'],
    'current cookie' => ['currentSchemaCookie', 253],
    'next cookie' => ['nextSchemaCookie', 254],
    'current matched' => ['currentMatchedRowids', []],
    'next matched' => ['nextMatchedRowids', [3, 2, 1, 9]],
    'current unknowns' => ['currentUnknownRowids', [1, 2, 3, 4, 5, 6, 7, 8]],
    'next unknowns' => ['nextUnknownRowids', [6, 7]],
    'retained' => ['retainedMatchedRowids', []],
    'exited' => ['exitedMatchedRowids', []],
    'entered' => ['enteredMatchedRowids', [3, 2, 1, 9]],
    'changed truth' => ['changedPredicateTruthRowids', [1, 2, 3, 4, 5]],
    'changed value text' => ['changedValueTextRowids', []],
    'changed storage' => ['changedStorageClassRowids', [4]],
    'current predicate row1 unknown' => ['currentPredicateResults.1', null],
    'next predicate row1 true' => ['nextPredicateResults.1', true],
    'next predicate row4 false' => ['nextPredicateResults.4', false],
    'current text row2' => ['currentValueText.2', 'plugin_%literal'],
    'next text row2' => ['nextValueText.2', 'plugin_%literal'],
    'current hex row2' => ['currentValueHex.2', '706c7567696e5f256c69746572616c'],
    'next hex row9' => ['nextValueHex.9', '706c7567696e5f6e6577'],
    'current storage row4' => ['currentStorage.4', 'integer'],
    'next storage row4' => ['nextStorage.4', 'text'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason nullability' => ['invalidationReasons.2', 'escape-nullability'],
    'reason escape text' => ['invalidationReasons.3', 'escape-text'],
    'reason escape storage' => ['invalidationReasons.4', 'escape-storage-class'],
    'reason rowset' => ['invalidationReasons.5', 'matched-rowset'],
    'reason truth' => ['invalidationReasons.6', 'predicate-truth'],
    'reason storage' => ['invalidationReasons.7', 'value-storage-class'],
    'dependency nullability' => ['dependencies.0', 'sqlite-like-escape-nullability'],
    'dependency tokenizer' => ['dependencies.1', 'sqlite-like-escape-tokenizer'],
    'dependency affinity' => ['dependencies.2', 'sqlite-text-affinity'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFiveFour'],
];

foreach ($cases254 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFiveFour ' . $name] = static function (TestRunner $t) use ($plan254, $valueAt254, $path, $expected): void {
        $t->same($expected, $valueAt254($plan254(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFiveFour omitted escape differs from explicit sql null'] = static function (TestRunner $t) use ($current254, $plan254): void {
    $omitted = $plan254(current: $current254, next: $current254, currentEscape: null, currentEscapeIsExplicit: false, nextEscape: null, nextEscapeIsExplicit: false, currentSource: 'same', nextSource: 'same', currentCookie: 254, nextCookie: 254);
    $explicit = $plan254(current: $current254, next: $current254, currentEscape: null, currentEscapeIsExplicit: true, nextEscape: null, nextEscapeIsExplicit: true, currentSource: 'same', nextSource: 'same', currentCookie: 254, nextCookie: 254);
    $t->same([], $omitted['currentMatchedRowids']);
    $t->same([3, 2, 1], $plan254(current: $current254, next: $current254, pattern: 'plugin_%', currentEscape: null, currentEscapeIsExplicit: false, nextEscape: null, nextEscapeIsExplicit: false, currentSource: 'same', nextSource: 'same', currentCookie: 254, nextCookie: 254)['currentMatchedRowids']);
    $t->same([], $explicit['currentMatchedRowids']);
    $t->same([1, 2, 3, 4, 5, 6, 7, 8], $explicit['currentUnknownRowids']);
    $t->same(false, $omitted['cursorInvalidated']);
    $t->same(false, $explicit['cursorInvalidated']);
};

$tests['encoding collation affinity like current source nextTwoFiveFour stable escaped cursor reusable'] = static function (TestRunner $t) use ($current254, $plan254): void {
    $stable = $plan254(current: $current254, next: $current254, currentEscape: '!', nextEscape: '!', currentSource: 'same', nextSource: 'same', currentCookie: 254, nextCookie: 254);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([3, 2, 1], $stable['currentMatchedRowids']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveFour numeric escape uses text affinity'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin_%'],
        ['option_id' => 2, 'option_value' => 'plugin_123'],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNullableEscapeLikePlan($rows, $rows, 'plugin1_%', 1, true, '1', true, false, 'same', 'same', 1, 1);
    $t->same('1', $plan['currentEscapeText']);
    $t->same('integer', $plan['currentEscapeStorageClass']);
    $t->same([1, 2], $plan['currentMatchedRowids']);
    $t->same(['escape-storage-class'], $plan['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveFour case sensitive rejects uppercase'] = static function (TestRunner $t) use ($plan254): void {
    $case = $plan254(currentEscape: '!', nextEscape: '!', caseSensitive: true);
    $t->same('BINARY', $case['collation']);
    $t->same([2, 1, 9], $case['nextMatchedRowids']);
    $t->same(false, $case['nextPredicateResults'][3]);
};

$tests['encoding collation affinity like current source nextTwoFiveFour rejects blob escape'] = static function (TestRunner $t) use ($current254, $nextTwoFiveFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNullableEscapeLikePlan($current254, $nextTwoFiveFour, 'plugin!_%', new SQLiteBlobValue('!'), true));
};

$tests['encoding collation affinity like current source nextTwoFiveFour rejects multi character escape'] = static function (TestRunner $t) use ($current254, $nextTwoFiveFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNullableEscapeLikePlan($current254, $nextTwoFiveFour, 'plugin!!_%', '!!', true));
};

$tests['encoding collation affinity like current source nextTwoFiveFour rejects missing option value'] = static function (TestRunner $t) use ($nextTwoFiveFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNullableEscapeLikePlan([['option_id' => 1]], $nextTwoFiveFour));
};

$tests['encoding collation affinity like current source nextTwoFiveFour rejects array option value'] = static function (TestRunner $t) use ($nextTwoFiveFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNullableEscapeLikePlan([['option_id' => 1, 'option_value' => ['plugin']]], $nextTwoFiveFour));
};

return $tests;
