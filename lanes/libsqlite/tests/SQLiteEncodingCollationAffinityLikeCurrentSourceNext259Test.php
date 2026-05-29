<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc259 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row259 = static fn (int $id, string $name, int $encoding, mixed $value = null): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc259($name, $encoding),
    'text_encoding' => $encoding,
    'option_value' => $value,
];

$current259 = [
    $row259(1, 'Plugin_Cache', 1, 'mixed-case'),
    $row259(2, 'plugin_cache', 2, 'lowercase'),
    $row259(3, 'PLUGIN_CACHE', 3, 'uppercase'),
    $row259(4, 'Plugout_Cache', 1, 'binary-neighbor'),
    $row259(5, 'Plugiñ_cache', 2, 'unicode-tail'),
    ['option_id' => 6, 'option_name' => 'plugin_extra', 'option_value' => 'scalar-text'],
    ['option_id' => 7, 'option_name' => 123, 'option_value' => 'integer-name'],
    ['option_id' => 8, 'option_name' => null, 'option_value' => 'null-name'],
    ['option_id' => 9, 'option_name' => new SQLiteBlobValue('Plugin_Blob'), 'option_value' => 'blob-name'],
    ['option_id' => 10, 'option_name' => "Plugin_\xff", 'option_value' => 'malformed-name'],
];

$nextTwoFiveNine = [
    $row259(1, 'Plugin_Cache', 1, 'mixed-case'),
    $row259(2, 'plugin_cache_v2', 2, 'lowercase-changed'),
    $row259(3, 'PLUGIN_CACHE', 3, 'uppercase'),
    $row259(4, 'Plugout_Cache', 1, 'binary-neighbor'),
    $row259(5, 'Plugiñ_cache', 3, 'unicode-tail-reencoded'),
    ['option_id' => 6, 'option_name' => 'Plugin_extra', 'option_value' => 'scalar-text-case-change'],
    ['option_id' => 7, 'option_name' => 123, 'option_value' => 'integer-name'],
    $row259(11, 'plugin_new', 1, 'new-lowercase'),
    ['option_id' => 12, 'option_name' => 'Plugin_literal%', 'option_value' => 'literal-percent'],
];

$plan259 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'Plugin%',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.wp_options@258',
    string $nextSource = 'main.wp_options@259',
    int $currentCookie = 258,
    int $nextCookie = 259,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressBinaryCollationDefaultLikePlan(
    $current ?? $current259,
    $next ?? $nextTwoFiveNine,
    $pattern,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt259 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases259 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFiveNine'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name COLLATE BINARY LIKE ? /* default LIKE ignores BINARY collation for ASCII folding */'],
    'pattern' => ['pattern', 'Plugin%'],
    'pattern hex' => ['patternHex', '506C7567696E25'],
    'escape' => ['escape', null],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'BINARY'],
    'prefix' => ['prefix', 'Plugin'],
    'prefix hex' => ['prefixHex', '506C7567696E'],
    'range lower' => ['binaryRange.lowerInclusive', 'Plugin'],
    'range upper' => ['binaryRange.upperBound', 'Plugio'],
    'binary range unusable' => ['binaryRangeUsable', false],
    'full scan required' => ['fullScanResidualRequired', true],
    'default like fold flag' => ['defaultLikeIgnoresBinaryCollationForAsciiFold', true],
    'case-sensitive range flag' => ['caseSensitiveLikeRestoresBinaryRangeSafety', false],
    'current source' => ['currentSource', 'main.wp_options@258'],
    'next source' => ['nextSource', 'main.wp_options@259'],
    'current cookie' => ['currentSchemaCookie', 258],
    'next cookie' => ['nextSchemaCookie', 259],
    'current candidates' => ['currentCandidateRowids', [7, 3, 1, 5, 4, 2, 6]],
    'next candidates' => ['nextCandidateRowids', [7, 3, 1, 6, 12, 5, 4, 2, 11]],
    'current matched' => ['currentMatchedRowids', [3, 1, 2, 6]],
    'next matched' => ['nextMatchedRowids', [3, 1, 6, 12, 2, 11]],
    'retained' => ['retainedRowids', [1, 2, 3, 6]],
    'entered' => ['enteredRowids', [11, 12]],
    'exited' => ['exitedRowids', []],
    'current false positives' => ['currentFalsePositiveRowids', [7, 5, 4]],
    'next false positives' => ['nextFalsePositiveRowids', [7, 5, 4]],
    'changed text' => ['changedTextRowids', [2, 6]],
    'changed bytes' => ['changedBytesRowids', [2, 6]],
    'changed encoding' => ['changedEncodingRowids', [5]],
    'changed storage' => ['changedStorageClassRowids', []],
    'changed key' => ['changedBinaryKeyRowids', [2, 6]],
    'changed residual' => ['changedResidualRowids', []],
    'unknown current' => ['currentUnknownRowids', [8, 9]],
    'malformed current' => ['currentMalformedRowids', [10]],
    'malformed error' => ['currentErrors.10', 'SQLite BINARY LIKE nextTwoFiveNine option_name text is malformed UTF-8'],
    'current row2 text' => ['currentText.2', 'plugin_cache'],
    'next row2 text' => ['nextText.2', 'plugin_cache_v2'],
    'next row12 text' => ['nextText.12', 'Plugin_literal%'],
    'current row2 encoding' => ['currentEncodings.2', 'UTF-16LE'],
    'current row3 encoding' => ['currentEncodings.3', 'UTF-16BE'],
    'next row5 encoding' => ['nextEncodings.5', 'UTF-16BE'],
    'current row7 storage' => ['currentStorage.7', 'integer'],
    'invalidated' => ['cursorInvalidated', true],
    'reusable false' => ['cursorReusable', false],
    'dependency like' => ['dependencies.0', 'sqlite-like-default-ascii-fold'],
    'dependency binary' => ['dependencies.1', 'sqlite-binary-collation-key'],
    'dependency decoder' => ['dependencies.2', 'sqlite-mixed-utf-source-decoder'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFiveNine'],
];

foreach ($cases259 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFiveNine ' . $name] = static function (TestRunner $t) use ($plan259, $valueAt259, $path, $expected): void {
        $t->same($expected, $valueAt259($plan259(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFiveNine invalidation reason order'] = static function (TestRunner $t) use ($plan259): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'binary-prefix-range-unsafe',
        'text-value',
        'text-bytes',
        'text-encoding',
        'binary-key',
        'candidate-rowset',
        'matched-rowset',
    ], $plan259()['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveNine stable default like still invalidates binary cursor'] = static function (TestRunner $t) use ($current259, $plan259): void {
    $rows = array_values(array_filter($current259, static fn (array $row): bool => !in_array($row['option_id'] ?? null, [8, 9, 10], true)));
    $plan = $plan259(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 259, nextCookie: 259);

    $t->same(['binary-prefix-range-unsafe'], $plan['invalidationReasons']);
    $t->same(false, $plan['binaryRangeUsable']);
    $t->same([7, 3, 1, 5, 4, 2, 6], $plan['currentCandidateRowids']);
    $t->same([3, 1, 2, 6], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveNine case sensitive like can use binary range'] = static function (TestRunner $t) use ($current259, $plan259): void {
    $rows = array_values(array_filter($current259, static fn (array $row): bool => !in_array($row['option_id'] ?? null, [8, 9, 10], true)));
    $plan = $plan259(current: $rows, next: $rows, caseSensitiveLike: true, currentSource: 'same', nextSource: 'same', currentCookie: 259, nextCookie: 259);

    $t->same(true, $plan['binaryRangeUsable']);
    $t->same(false, $plan['fullScanResidualRequired']);
    $t->same([], $plan['invalidationReasons']);
    $t->same([1], $plan['currentCandidateRowids']);
    $t->same([1], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveNine escaped literal percent uses binary range when case sensitive'] = static function (TestRunner $t) use ($plan259): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'Plugin_literal%'],
        ['option_id' => 2, 'option_name' => 'Plugin_literalx'],
        ['option_id' => 3, 'option_name' => 'plugin_literal%'],
    ];
    $plan = $plan259(current: $rows, next: $rows, pattern: 'Plugin!_%', escape: '!', caseSensitiveLike: true, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same('Plugin_', $plan['prefix']);
    $t->same(['lowerInclusive' => 'Plugin_', 'upperBound' => 'Plugin`'], $plan['binaryRange']);
    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([1, 2], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveNine null and blob option names stay unknown'] = static function (TestRunner $t) use ($plan259): void {
    $rows = [
        ['option_id' => 1, 'option_name' => null],
        ['option_id' => 2, 'option_name' => new SQLiteBlobValue('Plugin')],
        ['option_id' => 3, 'option_name' => 'Plugin'],
    ];
    $plan = $plan259(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);

    $t->same([1, 2], $plan['currentUnknownRowids']);
    $t->same([3], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveNine rejects invalid escape length'] = static function (TestRunner $t) use ($plan259): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan259(escape: '!!'));
};

$tests['encoding collation affinity like current source nextTwoFiveNine rejects missing option name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressBinaryCollationDefaultLikePlan([['option_id' => 1]], []));
};

$tests['encoding collation affinity like current source nextTwoFiveNine reports bad byte row encoding'] = static function (TestRunner $t) use ($enc259): void {
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressBinaryCollationDefaultLikePlan([
        ['option_id' => 1, 'option_name_bytes' => $enc259('Plugin', 1), 'text_encoding' => 9],
    ], [], currentSource: 'same', nextSource: 'same', currentSchemaCookie: 1, nextSchemaCookie: 1);

    $t->same([1], $plan['currentMalformedRowids']);
    $t->same('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE', $plan['currentErrors'][1]);
};

return $tests;
