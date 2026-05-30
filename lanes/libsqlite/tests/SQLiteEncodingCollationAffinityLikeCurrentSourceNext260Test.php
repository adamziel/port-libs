<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc260 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row260 = static fn (int $id, string $name, int $encoding, mixed $value = null): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc260($name, $encoding),
    'text_encoding' => $encoding,
    'option_value' => $value,
];

$current260 = [
    $row260(1, 'plugin_cache', 1, 'exact'),
    $row260(2, 'plugin_cache   ', 2, 'trailing-spaces'),
    $row260(3, 'plugin_cache  ', 3, 'trailing-two'),
    $row260(4, 'plugin_cached', 1, 'range-neighbor'),
    $row260(5, 'plugin_caché', 2, 'accent-neighbor'),
    $row260(6, 'Plugin_cache', 1, 'case-neighbor'),
    ['option_id' => 7, 'option_name' => null],
    ['option_id' => 8, 'option_name' => new SQLiteBlobValue('plugin_cache')],
    ['option_id' => 9, 'option_name' => 404],
    ['option_id' => 10, 'option_name' => "plugin_cache\xc3"],
];

$nextTwoSixZero = [
    $row260(1, 'plugin_cache ', 1, 'now-rejected'),
    $row260(2, 'plugin_cache', 2, 'now-exact'),
    $row260(3, 'plugin_cache  ', 3, 'still-trailing-two'),
    $row260(4, 'plugin_cached', 1, 'range-neighbor'),
    $row260(5, 'plugin_caché', 2, 'accent-neighbor'),
    $row260(6, 'Plugin_cache', 1, 'case-neighbor'),
    $row260(11, 'plugin_cache', 3, 'new-exact'),
    ['option_id' => 7, 'option_name' => null],
    ['option_id' => 8, 'option_name' => new SQLiteBlobValue('plugin_cache')],
    ['option_id' => 9, 'option_name' => 404],
    ['option_id' => 10, 'option_name' => "plugin_cache\xc3"],
];

$plan260 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin_cache',
    ?string $escape = null,
    string $currentSource = 'main.wp_options@259',
    string $nextSource = 'main.wp_options@260',
    int $currentCookie = 259,
    int $nextCookie = 260,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimCollationLikeResidualPlan(
    $current ?? $current260,
    $next ?? $nextTwoSixZero,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt260 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases260 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoSixZero'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name COLLATE RTRIM LIKE ? /* RTRIM index candidates still require raw LIKE residual */'],
    'pattern' => ['pattern', 'plugin_cache'],
    'pattern hex' => ['patternHex', '706C7567696E5F6361636865'],
    'escape' => ['escape', null],
    'prefix' => ['prefix', 'plugin'],
    'prefix hex' => ['prefixHex', '706C7567696E'],
    'prefix characters' => ['prefixCharacters', 6],
    'range lower' => ['rangeLowerInclusive', 'plugin'],
    'range upper' => ['rangeUpperBound', 'plugio'],
    'collation' => ['collation', 'RTRIM'],
    'rtrim equality flag' => ['rtrimCollationCanShareEqualityKey', true],
    'like residual flag' => ['likeResidualDoesNotTrimTrailingSpaces', true],
    'false positive flag' => ['rangeMayAdmitTrailingSpaceFalsePositives', true],
    'current source' => ['currentSource', 'main.wp_options@259'],
    'next source' => ['nextSource', 'main.wp_options@260'],
    'current cookie' => ['currentSchemaCookie', 259],
    'next cookie' => ['nextSchemaCookie', 260],
    'current candidates' => ['currentCandidateRowids', [1, 3, 2, 4, 5]],
    'next candidates' => ['nextCandidateRowids', [2, 11, 1, 3, 4, 5]],
    'retained candidates' => ['retainedCandidateRowids', [1, 2, 3, 4, 5]],
    'entered candidate' => ['enteredCandidateRowids', [11]],
    'exited candidates' => ['exitedCandidateRowids', []],
    'current matched' => ['currentMatchedRowids', [1]],
    'next matched' => ['nextMatchedRowids', [2, 11]],
    'current rejected' => ['currentResidualRejectedRowids', [3, 2, 4, 5]],
    'next rejected' => ['nextResidualRejectedRowids', [1, 3, 4, 5]],
    'unknown current' => ['currentUnknownRowids', [7, 8]],
    'unknown next' => ['nextUnknownRowids', [7, 8]],
    'malformed current' => ['currentMalformedRowids', [10]],
    'malformed next' => ['nextMalformedRowids', [10]],
    'malformed error' => ['currentErrors.10', 'SQLite RTRIM LIKE nextTwoSixZero string option_name must be well-formed UTF-8'],
    'current row2 text' => ['currentText.2', 'plugin_cache   '],
    'next row2 text' => ['nextText.2', 'plugin_cache'],
    'current row2 rtrim' => ['currentRtrimKeys.2', 'plugin_cache'],
    'next row1 rtrim' => ['nextRtrimKeys.1', 'plugin_cache'],
    'current row2 encoding' => ['currentEncodings.2', 'UTF-16LE'],
    'next row11 encoding' => ['nextEncodings.11', 'UTF-16BE'],
    'current row2 bytes' => ['currentBytesHex.2', '70006C007500670069006E005F0063006100630068006500200020002000'],
    'next row11 bytes' => ['nextBytesHex.11', '0070006C007500670069006E005F00630061006300680065'],
    'changed text' => ['changedTextRowids', [1, 2]],
    'changed bytes' => ['changedBytesRowids', [1, 2]],
    'changed encoding' => ['changedEncodingRowids', []],
    'changed rtrim key' => ['changedRtrimKeyRowids', []],
    'changed residual' => ['changedResidualRowids', [1, 2]],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'dependency rtrim' => ['dependencies.0', 'sqlite-rtrim-collation-key'],
    'dependency like' => ['dependencies.1', 'sqlite-like-raw-residual'],
    'dependency decoder' => ['dependencies.2', 'sqlite-mixed-utf-source-decoder'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoSixZero'],
    'dependency closure' => ['dependency_closure', 'no new support component needed; reuses native UTF-8/UTF-16 decode, LIKE residual matching, scalar text-affinity coercion, and RTRIM collation-key diagnostics'],
];

foreach ($cases260 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoSixZero ' . $name] = static function (TestRunner $t) use ($plan260, $valueAt260, $path, $expected): void {
        $t->same($expected, $valueAt260($plan260(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoSixZero invalidation reason order'] = static function (TestRunner $t) use ($plan260): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'candidate-rowset',
        'matched-rowset',
        'residual-result',
        'text-value',
        'text-bytes',
        'unknown-like',
        'malformed-text',
    ], $plan260()['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoSixZero stable exact source is reusable'] = static function (TestRunner $t) use ($plan260): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'plugin_cache'],
        ['option_id' => 2, 'option_name' => 'plugin_cache   '],
        ['option_id' => 3, 'option_name' => 'plugin_cached'],
    ];
    $plan = $plan260(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same(false, $plan['cursorInvalidated']);
    $t->same(true, $plan['cursorReusable']);
    $t->same([], $plan['invalidationReasons']);
    $t->same([1], $plan['currentMatchedRowids']);
    $t->same([2, 3], $plan['currentResidualRejectedRowids']);
};

$tests['encoding collation affinity like current source nextTwoSixZero escaped underscore literal narrows residual'] = static function (TestRunner $t) use ($plan260): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'plugin_cache'],
        ['option_id' => 2, 'option_name' => 'pluginXcache'],
    ];
    $plan = $plan260(current: $rows, next: $rows, pattern: 'plugin!_cache', escape: '!', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same('plugin_cache', $plan['prefix']);
    $t->same([1], $plan['currentCandidateRowids']);
    $t->same([1], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoSixZero wildcard underscore admits both raw residuals'] = static function (TestRunner $t) use ($plan260): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'plugin_cache'],
        ['option_id' => 2, 'option_name' => 'pluginXcache'],
        ['option_id' => 3, 'option_name' => 'plugin_cache '],
    ];
    $plan = $plan260(current: $rows, next: $rows, pattern: 'plugin_cache', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same([2, 1, 3], $plan['currentCandidateRowids']);
    $t->same([2, 1], $plan['currentMatchedRowids']);
    $t->same([3], $plan['currentResidualRejectedRowids']);
};

$tests['encoding collation affinity like current source nextTwoSixZero integer affinity can match exact numeric text'] = static function (TestRunner $t) use ($plan260): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 404],
        ['option_id' => 2, 'option_name' => '404 '],
        ['option_id' => 3, 'option_name' => '405'],
    ];
    $plan = $plan260(current: $rows, next: $rows, pattern: '404', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([1], $plan['currentMatchedRowids']);
    $t->same('integer', $plan['currentTrace'][0]['storage']);
};

$tests['encoding collation affinity like current source nextTwoSixZero real affinity keeps sqlite text form'] = static function (TestRunner $t) use ($plan260): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 12.5],
        ['option_id' => 2, 'option_name' => '12.5 '],
        ['option_id' => 3, 'option_name' => '12.50'],
    ];
    $plan = $plan260(current: $rows, next: $rows, pattern: '12.5', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same([1, 2, 3], $plan['currentCandidateRowids']);
    $t->same([1], $plan['currentMatchedRowids']);
    $t->same('real', $plan['currentTrace'][0]['storage']);
};

$tests['encoding collation affinity like current source nextTwoSixZero invalid escape is rejected'] = static function (TestRunner $t) use ($plan260): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan260(escape: '!!'));
};

$tests['encoding collation affinity like current source nextTwoSixZero rejects missing option name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimCollationLikeResidualPlan([['option_id' => 1]], []));
};

$tests['encoding collation affinity like current source nextTwoSixZero records bad byte row as malformed'] = static function (TestRunner $t): void {
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimCollationLikeResidualPlan([
        ['option_id' => 1, 'option_name_bytes' => 'plugin_cache', 'text_encoding' => 'UTF-8'],
    ], []);

    $t->same([1], $plan['currentMalformedRowids']);
    $t->same('SQLite RTRIM LIKE nextTwoSixZero byte rows require option_name_bytes and integer text_encoding', $plan['currentErrors'][1]);
};

$tests['encoding collation affinity like current source nextTwoSixZero non overlap states accepted clusters'] = static function (TestRunner $t) use ($plan260): void {
    $plan = $plan260();

    $t->true(str_contains($plan['non_overlap'], 'nextTwoFiveFive GLOB bracket'));
    $t->true(str_contains($plan['non_overlap'], 'nextTwoFiveSix dynamic pattern affinity'));
    $t->true(str_contains($plan['non_overlap'], 'Unicode GLOB ranges'));
};

return $tests;
