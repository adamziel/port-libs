<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current236 = [
    ['option_id' => 1, 'option_name' => 'wp_%_timeout'],
    ['option_id' => 2, 'option_name' => 'WP_%_TIMEOUT'],
    ['option_id' => 3, 'option_name' => 'wp_cache_timeout'],
    ['option_id' => 4, 'option_name' => 'wp_%_timeout_extra'],
    ['option_id' => 5, 'option_name' => 'wp_%'],
    ['option_id' => 6, 'option_name' => 'wp_é_timeout'],
    ['option_id' => 7, 'option_name' => 'wp_É_timeout'],
    ['option_id' => 8, 'option_name' => 'wp!_%_timeout'],
    ['option_id' => 9, 'option_name' => 'wp_%_timeout!'],
    ['option_id' => 10, 'option_name' => 404],
    ['option_id' => 11, 'option_name' => new SQLiteBlobValue('wp_%_timeout')],
    ['option_id' => 12, 'option_name' => null],
];

$nextTwoThreeSix = [
    ['option_id' => 1, 'option_name' => 'wp_%_timeout2'],
    ['option_id' => 2, 'option_name' => 'WP_%_TIMEOUT'],
    ['option_id' => 3, 'option_name' => 'wp_cache_timeout'],
    ['option_id' => 4, 'option_name' => 'wp_%_timeout_extra'],
    ['option_id' => 5, 'option_name' => 'wp_%'],
    ['option_id' => 6, 'option_name' => 'wp_é_timeout'],
    ['option_id' => 7, 'option_name' => 'wp_É_timeout'],
    ['option_id' => 8, 'option_name' => 'wp!_%_timeout'],
    ['option_id' => 9, 'option_name' => 'wp_%_timeout!'],
    ['option_id' => 10, 'option_name' => 404],
    ['option_id' => 13, 'option_name' => 'wp_%_timeout'],
    ['option_id' => 14, 'option_name' => 'wp_%_TIMEOUT_archive'],
];

$plan236 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'wp!_!%!_timeout%',
    ?string $escape = '!',
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@235',
    string $nextSource = 'main.wp_options@236',
    int $currentCookie = 235,
    int $nextCookie = 236,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowNameEscapedLikePlan(
    $current ?? $current236,
    $next ?? $nextTwoThreeSix,
    $pattern,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt236 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases236 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoThreeSix'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name COLLATE NOCASE LIKE ? ESCAPE ? /* escaped wildcard current-source fence */'],
    'pattern' => ['pattern', 'wp!_!%!_timeout%'],
    'pattern hex' => ['patternHex', '7770215f2125215f74696d656f757425'],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'prefix' => ['prefix', 'wp_%_timeout'],
    'prefix hex' => ['prefixHex', '77705f255f74696d656f7574'],
    'prefix chars' => ['prefixCharacters', 12],
    'prefix ascii' => ['prefixIsAscii', true],
    'has wildcard' => ['hasWildcard', true],
    'binary lower' => ['binaryRange.lowerInclusive', 'wp_%_timeout'],
    'binary upper' => ['binaryRange.upperBound', 'wp_%_timeouu'],
    'nocase lower' => ['noCaseRange.lowerInclusive', 'wp_%_timeout'],
    'nocase upper' => ['noCaseRange.upperBound', 'wp_%_timeouu'],
    'current source' => ['currentSource', 'main.wp_options@235'],
    'next source' => ['nextSource', 'main.wp_options@236'],
    'current cookie' => ['currentSchemaCookie', 235],
    'next cookie' => ['nextSchemaCookie', 236],
    'current rowids' => ['currentRowids', [2, 1, 9, 4]],
    'next rowids' => ['nextRowids', [2, 14, 13, 9, 1, 4]],
    'retained rowids' => ['retainedRowids', [2, 1, 9, 4]],
    'exited rowids' => ['exitedRowids', []],
    'entered rowids' => ['enteredRowids', [14, 13]],
    'changed names' => ['changedNameBytesRowids', [1]],
    'current uppercase' => ['currentNames.2', 'WP_%_TIMEOUT'],
    'current literal percent' => ['currentNames.1', 'wp_%_timeout'],
    'current bang suffix' => ['currentNames.9', 'wp_%_timeout!'],
    'next archive' => ['nextNames.14', 'wp_%_TIMEOUT_archive'],
    'next new literal' => ['nextNames.13', 'wp_%_timeout'],
    'current uppercase hex' => ['currentNameHex.2', '57505f255f54494d454f5554'],
    'current literal hex' => ['currentNameHex.1', '77705f255f74696d656f7574'],
    'next changed hex' => ['nextNameHex.1', '77705f255f74696d656f757432'],
    'current token uppercase' => ['currentTokenHex.2', ['57', '50', '5f', '25', '5f', '54', '49', '4d', '45', '4f', '55', '54']],
    'current token literal' => ['currentTokenHex.1', ['77', '70', '5f', '25', '5f', '74', '69', '6d', '65', '6f', '75', '74']],
    'current token count' => ['currentTokenCounts.1', 12],
    'next token count' => ['nextTokenCounts.1', 13],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason rowset' => ['invalidationReasons.2', 'matched-rowset'],
    'reason bytes' => ['invalidationReasons.3', 'option-name-bytes'],
    'literal wildcard flag' => ['literalPercentAndUnderscoreRequireEscape', true],
    'trailing escape flag' => ['trailingEscapeDoesNotMatchLiteralEscape', true],
    'multibyte escape flag' => ['multibyteEscapeIsOneSQLiteCharacter', true],
    'nocase ascii flag' => ['likeNocaseFoldsAsciiOnly', true],
    'unicode fold flag' => ['collationDoesNotMakeLikeUnicodeCaseFold', true],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-like-escape-tokenizer'],
    'dependency collation' => ['dependencies.1', 'sqlite-nocase-ascii-collation'],
    'dependency affinity' => ['dependencies.2', 'sqlite-text-affinity'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoThreeSix'],
];

foreach ($cases236 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoThreeSix ' . $name] = static function (TestRunner $t) use ($plan236, $valueAt236, $path, $expected): void {
        $t->same($expected, $valueAt236($plan236(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoThreeSix case sensitive excludes uppercase'] = static function (TestRunner $t) use ($plan236): void {
    $case = $plan236(caseSensitive: true);
    $t->same([1, 9, 4], $case['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSix stable cursor reusable'] = static function (TestRunner $t) use ($current236, $plan236): void {
    $stable = $plan236(current: $current236, next: $current236, currentSource: 'stable', nextSource: 'stable', currentCookie: 236, nextCookie: 236);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoThreeSix escaped prefix can match literal prefix exactly'] = static function (TestRunner $t) use ($plan236): void {
    $plan = $plan236(pattern: 'wp!_!%', escape: '!');
    $t->same([5], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSix unescaped wildcards widen rowset'] = static function (TestRunner $t) use ($plan236): void {
    $plan = $plan236(pattern: 'wp_%_timeout%', escape: null);
    $t->same([2, 8, 1, 9, 4, 3, 7, 6], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSix multibyte escape quotes wildcard'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'wp_%_timeout'],
        ['option_id' => 2, 'option_name' => 'wp_cache_timeout'],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowNameEscapedLikePlan($rows, $rows, 'wpé_é%é_timeout', 'é', false, 'same', 'same', 1, 1);
    $t->same([1], $plan['currentRowids']);
    $t->same('c3a9', $plan['escapeHex']);
};

$tests['encoding collation affinity like current source nextTwoThreeSix trailing escape does not match literal escape'] = static function (TestRunner $t): void {
    $t->same(false, SQLiteDatabase::likeMatches('wp_%_timeout!', 'wp!_!%!_timeout!', '!'));
    $t->same(true, SQLiteDatabase::likeMatches('wp_%_timeout!', 'wp!_!%!_timeout!!', '!'));
};

$tests['encoding collation affinity like current source nextTwoThreeSix ascii nocase does not fold unicode'] = static function (TestRunner $t) use ($plan236): void {
    $case = $plan236(pattern: 'wp!_é%', escape: '!');
    $t->same([6], $case['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSix unicode pattern keeps codepoint prefix range'] = static function (TestRunner $t) use ($plan236): void {
    $case = $plan236(pattern: 'wp!_é%', escape: '!');
    $t->same('wp_é', $case['prefix']);
    $t->same('wp_ê', $case['binaryRange']['upperBound']);
    $t->same(false, $case['prefixIsAscii']);
};

$tests['encoding collation affinity like current source nextTwoThreeSix numeric affinity participates'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 404],
        ['option_id' => 2, 'option_name' => 405],
        ['option_id' => 3, 'option_name' => true],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowNameEscapedLikePlan($rows, $rows, '40_', null, false, 'same', 'same', 1, 1);
    $t->same([1, 2], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSix blob and null stay non text'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name' => new SQLiteBlobValue('wp_%_timeout')],
        ['option_id' => 2, 'option_name' => null],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowNameEscapedLikePlan($rows, $rows, 'wp!_!%!_timeout%', '!', false, 'same', 'same', 1, 1);
    $t->same([], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSix rejects missing option name'] = static function (TestRunner $t) use ($nextTwoThreeSix): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowNameEscapedLikePlan([['option_id' => 1]], $nextTwoThreeSix, 'wp!_!%', '!'));
};

$tests['encoding collation affinity like current source nextTwoThreeSix rejects non scalar option name'] = static function (TestRunner $t) use ($nextTwoThreeSix): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowNameEscapedLikePlan([['option_id' => 1, 'option_name' => ['wp']]], $nextTwoThreeSix, 'wp!_!%', '!'));
};

$tests['encoding collation affinity like current source nextTwoThreeSix rejects multi character escape'] = static function (TestRunner $t) use ($current236, $nextTwoThreeSix): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowNameEscapedLikePlan($current236, $nextTwoThreeSix, 'wp!_!%', '!!'));
};

return $tests;
