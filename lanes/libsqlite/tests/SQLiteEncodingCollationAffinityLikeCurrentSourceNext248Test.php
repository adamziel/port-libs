<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc248 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$code248 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row248 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc248($name, $encoding),
    'text_encoding' => $code248($encoding),
];
$bad248 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current248 = [
    $row248(1, 'plugin_cache%enabled', 'UTF-16LE'),
    $row248(2, 'Plugin_Cache%Enabled', 'UTF-16BE'),
    $row248(3, 'plugin_cache_enabled', 'UTF-8'),
    $row248(4, 'plugin_cache%beta', 'UTF-16LE'),
    $row248(5, 'pluginXcache%enabled', 'UTF-16BE'),
    $row248(6, 'theme_cache%enabled', 'UTF-8'),
    $row248(7, 'plugin_cache%', 'UTF-16LE'),
    $row248(8, 'plugin_cache%Éclair', 'UTF-16BE'),
    $bad248(9, "\x00\xd8", 2),
    ['option_id' => 10, 'option_name_bytes' => null, 'text_encoding' => 1],
    ['option_id' => 11, 'option_name_bytes' => new SQLiteBlobValue('plugin_cache%blob'), 'text_encoding' => 1],
];
$nextTwoFourEight = [
    $row248(1, 'plugin_cache%enabled', 'UTF-16BE'),
    $row248(2, 'Plugin_Cache%Enabled2', 'UTF-16LE'),
    $row248(3, 'plugin_cache_enabled', 'UTF-8'),
    $row248(4, 'plugin_cache_beta', 'UTF-16BE'),
    $row248(7, 'plugin_cache%', 'UTF-16BE'),
    $row248(8, 'plugin_cache%éclair', 'UTF-16LE'),
    $row248(12, 'PLUGIN_CACHE%NEW', 'UTF-8'),
    $row248(13, 'plugin_cache%later', 'UTF-16BE'),
    $bad248(14, "\xd8\x00", 3),
];

$plan248 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'pluginé_cacheé%%',
    ?string $escape = 'é',
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@247',
    string $nextSource = 'main.wp_options@248',
    int $currentCookie = 247,
    int $nextCookie = 248,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressNonAsciiEscapeLikePlan(
    $current ?? $current248,
    $next ?? $nextTwoFourEight,
    $pattern,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt248 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases248 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFourEight'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name COLLATE NOCASE LIKE ? ESCAPE ? /* non-ASCII ESCAPE */'],
    'pattern' => ['pattern', 'pluginé_cacheé%%'],
    'pattern hex' => ['patternHex', '706c7567696ec3a95f6361636865c3a92525'],
    'pattern tokens' => ['patternTokenHex', ['70', '6c', '75', '67', '69', '6e', 'c3a9', '5f', '63', '61', '63', '68', '65', 'c3a9', '25', '25']],
    'pattern chars' => ['patternCharacters', 16],
    'escape' => ['escape', 'é'],
    'escape hex' => ['escapeHex', 'c3a9'],
    'escape token' => ['escapeTokenHex', ['c3a9']],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'prefix' => ['prefix', 'plugin_cache%'],
    'prefix hex' => ['prefixHex', '706c7567696e5f636163686525'],
    'prefix tokens' => ['prefixTokenHex', ['70', '6c', '75', '67', '69', '6e', '5f', '63', '61', '63', '68', '65', '25']],
    'prefix chars' => ['prefixCharacters', 13],
    'prefix ascii' => ['prefixIsAscii', true],
    'has wildcard' => ['hasWildcard', true],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache%'],
    'range upper' => ['rangeUpperBound', 'plugin_cache&'],
    'range lower hex' => ['rangeLowerInclusiveHex', '706c7567696e5f636163686525'],
    'range upper hex' => ['rangeUpperBoundHex', '706c7567696e5f636163686526'],
    'current source' => ['currentSource', 'main.wp_options@247'],
    'next source' => ['nextSource', 'main.wp_options@248'],
    'current cookie' => ['currentSchemaCookie', 247],
    'next cookie' => ['nextSchemaCookie', 248],
    'current candidates' => ['currentCandidateRowids', [7, 4, 1, 2, 8]],
    'next candidates' => ['nextCandidateRowids', [7, 1, 2, 13, 12, 8]],
    'current matched' => ['currentMatchedRowids', [7, 4, 1, 2, 8]],
    'next matched' => ['nextMatchedRowids', [7, 1, 2, 13, 12, 8]],
    'retained' => ['retainedMatchedRowids', [7, 1, 2, 8]],
    'exited' => ['exitedMatchedRowids', [4]],
    'entered' => ['enteredMatchedRowids', [13, 12]],
    'current rejected' => ['currentResidualRejectedRowids', []],
    'next rejected' => ['nextResidualRejectedRowids', []],
    'current unknown' => ['currentUnknownRowids', [10, 11]],
    'next unknown' => ['nextUnknownRowids', []],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [14]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current one hex' => ['currentDecodedHex.1', '706c7567696e5f636163686525656e61626c6564'],
    'current two encoding' => ['currentTextEncoding.2', 'UTF-16BE'],
    'next two encoding' => ['nextTextEncoding.2', 'UTF-16LE'],
    'current seven tokens' => ['currentTokenHex.7', ['70', '6c', '75', '67', '69', '6e', '5f', '63', '61', '63', '68', '65', '25']],
    'next twelve tokens' => ['nextTokenHex.12', ['50', '4c', '55', '47', '49', '4e', '5f', '43', '41', '43', '48', '45', '25', '4e', '45', '57']],
    'current three residual false' => ['currentResidualMatches.3', false],
    'next four residual false' => ['nextResidualMatches.4', false],
    'changed decoded' => ['changedDecodedRowids', [2, 4, 8]],
    'changed encoding' => ['changedEncodingRowids', [1, 2, 4, 7, 8]],
    'changed residual' => ['changedResidualRowids', [4]],
    'single char flag' => ['nonAsciiEscapeIsSinglePatternCharacter', true],
    'literal flag' => ['escapedUnderscoreAndPercentAreLiterals', true],
    'decoded range flag' => ['prefixRangeUsesDecodedTextNotEncodedBytes', true],
    'nocase flag' => ['nocaseFoldsAsciiOnlyAfterUtf16Decode', true],
    'malformed flag' => ['malformedUtf16RowsDoNotEnterRange', true],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['invalidationReasons.2', 'malformed-text'],
    'reason candidates' => ['invalidationReasons.3', 'candidate-rowset'],
    'reason matched' => ['invalidationReasons.4', 'matched-rowset'],
    'reason decoded' => ['invalidationReasons.5', 'decoded-text'],
    'reason encoding' => ['invalidationReasons.6', 'text-encoding'],
    'reason residual' => ['invalidationReasons.7', 'like-residual-result'],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-like-non-ascii-escape-tokenizer'],
    'dependency decode' => ['dependencies.1', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.2', 'sqlite-like-escape-prefix-range'],
    'dependency collation' => ['dependencies.3', 'sqlite-nocase-ascii-collation'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoFourEight'],
];

foreach ($cases248 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFourEight ' . $name] = static function (TestRunner $t) use ($plan248, $valueAt248, $path, $expected): void {
        $t->same($expected, $valueAt248($plan248(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFourEight stable cursor reusable'] = static function (TestRunner $t) use ($plan248, $row248): void {
    $rows = [
        $row248(1, 'plugin_cache%enabled', 'UTF-16LE'),
        $row248(2, 'Plugin_Cache%Enabled', 'UTF-16BE'),
    ];
    $stable = $plan248(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 248, nextCookie: 248);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
    $t->same([1, 2], $stable['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourEight case sensitive keeps uppercase outside binary range'] = static function (TestRunner $t) use ($plan248): void {
    $case = $plan248(caseSensitive: true);
    $t->same('BINARY', $case['collation']);
    $t->same([7, 4, 1, 8], $case['currentCandidateRowids']);
    $t->same([7, 1, 13, 8], $case['nextCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourEight non escaped percent changes prefix'] = static function (TestRunner $t) use ($plan248): void {
    $wild = $plan248(pattern: 'pluginé_cache%', escape: 'é', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same('plugin_cache', $wild['prefix']);
    $t->same([7, 4, 1, 2, 8, 3], $wild['currentMatchedRowids']);
    $t->same([7, 4, 1, 2, 8, 3], $wild['currentCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourEight escaped underscore and percent are literal'] = static function (TestRunner $t) use ($plan248, $row248): void {
    $rows = [
        $row248(1, 'plugin_cache%ok', 'UTF-8'),
        $row248(2, 'pluginXcache%ok', 'UTF-8'),
        $row248(3, 'plugin_cacheXok', 'UTF-8'),
    ];
    $plan = $plan248(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1], $plan['currentMatchedRowids']);
    $t->same([1], $plan['currentCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourEight direct like accepts non ascii escape'] = static function (TestRunner $t): void {
    $t->same(true, SQLiteDatabase::likeMatches('plugin_cache%ok', 'pluginé_cacheé%%', 'é'));
    $t->same(false, SQLiteDatabase::likeMatches('pluginXcache%ok', 'pluginé_cacheé%%', 'é'));
    $t->same(false, SQLiteDatabase::likeMatches('plugin_cacheXok', 'pluginé_cacheé%%', 'é'));
};

$tests['encoding collation affinity like current source nextTwoFourEight rejects multi character escape'] = static function (TestRunner $t) use ($current248, $nextTwoFourEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressNonAsciiEscapeLikePlan($current248, $nextTwoFourEight, 'pluginé_cacheé%%', 'éé'));
};

$tests['encoding collation affinity like current source nextTwoFourEight rejects missing option bytes'] = static function (TestRunner $t) use ($nextTwoFourEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressNonAsciiEscapeLikePlan([['option_id' => 1, 'text_encoding' => 1]], $nextTwoFourEight));
};

$tests['encoding collation affinity like current source nextTwoFourEight rejects non string bytes'] = static function (TestRunner $t) use ($nextTwoFourEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressNonAsciiEscapeLikePlan([['option_id' => 1, 'option_name_bytes' => ['plugin'], 'text_encoding' => 1]], $nextTwoFourEight));
};

$tests['encoding collation affinity like current source nextTwoFourEight rejects invalid encoding id'] = static function (TestRunner $t) use ($nextTwoFourEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressNonAsciiEscapeLikePlan([['option_id' => 1, 'option_name_bytes' => 'plugin_cache%ok', 'text_encoding' => 9]], $nextTwoFourEight));
};

$tests['encoding collation affinity like current source nextTwoFourEight note fields stay explicit'] = static function (TestRunner $t) use ($plan248): void {
    $plan = $plan248();
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
    $t->true(str_contains($plan['non_overlap'], 'non-ASCII single-character ESCAPE'));
    $t->true(str_contains($plan['non_overlap'], 'nextTwoFourFive dangling ASCII ESCAPE'));
};

return $tests;
