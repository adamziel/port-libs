<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc177 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row177 = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc177($name, $encoding),
    'text_encoding' => $encoding,
];
$bad177 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current177 = [
    $row177(1, 'Plugin_CacheA', 2),
    $row177(2, 'plugin_cache😀', 2),
    $row177(3, 'plugin_cacheé', 3),
    $row177(4, 'plugin_cacheAB', 2),
    $row177(5, 'plugin_cache💾  ', 3),
    $row177(6, 'plugin_cache', 2),
    $row177(7, 'theme_cache😀', 2),
    $bad177(8, "\x00\xd8", 2),
];
$next177 = [
    $row177(1, 'plugin_cacheA', 3),
    $row177(2, 'plugin_cache😀  ', 3),
    $row177(3, 'plugin_cacheÉ', 3),
    $row177(4, 'plugin_cache', 2),
    $row177(5, 'plugin_cache💾x', 3),
    $row177(6, 'plugin_cacheZ', 2),
    $row177(9, 'PLUGIN_CACHEß', 2),
    $bad177(10, "x\0y", 2),
];

$plan177 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache_',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@176',
    string $nextSource = 'main.wp_options@177',
    int $currentCookie = 176,
    int $nextCookie = 177,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameUnicodeWildcardPlan(
    $current ?? $current177,
    $next ?? $next177,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt177 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases177 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-next177'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ?'],
    'pattern' => ['pattern', 'plugin!_cache_'],
    'escape' => ['escape', '!'],
    'current source' => ['currentSource', 'main.wp_options@176'],
    'next source' => ['nextSource', 'main.wp_options@177'],
    'current cookie' => ['currentSchemaCookie', 176],
    'next cookie' => ['nextSchemaCookie', 177],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'current candidates' => ['currentCandidateRowids', [6, 1, 4, 3, 5, 2]],
    'next candidates' => ['nextCandidateRowids', [4, 1, 6, 3, 9, 5, 2]],
    'current matched' => ['currentMatchedRowids', [1, 3, 5, 2]],
    'next matched' => ['nextMatchedRowids', [1, 6, 3, 9, 2]],
    'current false positives' => ['currentFalsePositiveRowids', [6, 4]],
    'next false positives' => ['nextFalsePositiveRowids', [4, 5]],
    'current unicode wildcard rowids' => ['currentUnicodeWildcardRowids', [2, 5, 7]],
    'next unicode wildcard rowids' => ['nextUnicodeWildcardRowids', [2, 5]],
    'current byte wildcard mismatches' => ['currentByteWildcardMismatchRowids', [2, 3, 5]],
    'next byte wildcard mismatches' => ['nextByteWildcardMismatchRowids', [2, 3, 9]],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'row two current rtrim' => ['currentRtrimTexts.2', 'plugin_cache😀'],
    'row five current rtrim' => ['currentRtrimTexts.5', 'plugin_cache💾'],
    'row five next text' => ['nextTexts.5', 'plugin_cache💾x'],
    'row one nocase' => ['currentNocaseKeys.1', 'plugin_cachea'],
    'row nine nocase ascii only' => ['nextNocaseKeys.9', 'plugin_cacheß'],
    'row two character count' => ['currentCharacterCounts.2', 13],
    'row two utf16 units' => ['currentUtf16CodeUnitCounts.2', 14],
    'row five current character count' => ['currentCharacterCounts.5', 13],
    'row five current utf16 units' => ['currentUtf16CodeUnitCounts.5', 14],
    'row five next character count' => ['nextCharacterCounts.5', 14],
    'row five next utf16 units' => ['nextUtf16CodeUnitCounts.5', 15],
    'row two residual true' => ['currentResidualMatches.2', true],
    'row two byte wildcard false' => ['currentByteWildcardMatches.2', false],
    'row three residual true' => ['currentResidualMatches.3', true],
    'row three byte wildcard false' => ['currentByteWildcardMatches.3', false],
    'row four next residual false' => ['nextResidualMatches.4', false],
    'row nine residual true' => ['nextResidualMatches.9', true],
    'changed text' => ['changedTextRowids', [1, 2, 3, 4, 5, 6]],
    'changed rtrim' => ['changedRtrimRowids', [1, 3, 4, 5, 6]],
    'changed nocase' => ['changedNocaseKeyRowids', [3, 4, 5, 6]],
    'changed character count' => ['changedCharacterCountRowids', [4, 5, 6]],
    'changed utf16 units' => ['changedUtf16CodeUnitCountRowids', [4, 5, 6]],
    'changed bytes' => ['changedBytesRowids', [1, 2, 3, 4, 5, 6]],
    'changed residual' => ['changedResidualRowids', [5, 6]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'underscore character semantic' => ['likeUnderscoreConsumesOneDecodedCharacter', true],
    'surrogate pair one character' => ['utf16SurrogatePairIsOneLikeCharacter', true],
    'byte length not semantic' => ['byteLengthCannotDriveLikeUnderscore', true],
    'rtrim ascii space only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency wildcard' => ['dependencies.1', 'sqlite-like-unicode-character-wildcard'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-next177'],
];

foreach ($cases177 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source next177 ' . $name] = static function (TestRunner $t) use ($plan177, $valueAt177, $path, $expected): void {
        $t->same($expected, $valueAt177($plan177(), $path));
    };
}

$tests['utf16 nocase like rtrim current source next177 invalidation reason order'] = static function (TestRunner $t) use ($plan177): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'character-count',
        'utf16-code-unit-count',
        'encoded-bytes',
        'residual-result',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'unicode-wildcard-recheck',
    ], $plan177()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source next177 stable unicode wildcard is reusable after recheck'] = static function (TestRunner $t) use ($row177): void {
    $rows = [$row177(1, 'plugin_cache😀  ', 2), $row177(2, 'plugin_cacheAB', 3)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameUnicodeWildcardPlan(
        $rows,
        $rows,
        'plugin!_cache_',
        '!',
        'stable',
        'stable',
        7,
        7,
    );
    $t->same([2, 1], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([1], $result['currentUnicodeWildcardRowids']);
    $t->same([1], $result['currentByteWildcardMismatchRowids']);
    $t->same(['unicode-wildcard-recheck'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source next177 ascii wildcard has no byte mismatch'] = static function (TestRunner $t) use ($row177): void {
    $rows = [$row177(1, 'plugin_cacheA', 2), $row177(2, 'plugin_cacheZ  ', 3)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameUnicodeWildcardPlan(
        $rows,
        $rows,
        'plugin!_cache_',
        '!',
        'stable',
        'stable',
        7,
        7,
    );
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['currentUnicodeWildcardRowids']);
    $t->same([], $result['currentByteWildcardMismatchRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source next177 percent wildcard still accepts unicode suffix'] = static function (TestRunner $t) use ($row177): void {
    $rows = [$row177(1, 'plugin_cache😀extra', 2), $row177(2, 'plugin_cache', 3)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameUnicodeWildcardPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        7,
        7,
    );
    $t->same([2, 1], $result['currentMatchedRowids']);
    $t->same([1], $result['currentUnicodeWildcardRowids']);
    $t->same([], $result['currentByteWildcardMismatchRowids']);
};

$tests['utf16 nocase like rtrim current source next177 non ascii prefix remains unplanned'] = static function (TestRunner $t) use ($row177): void {
    $rows = [$row177(1, 'éclair😀', 2), $row177(2, 'ÉclairA', 3)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameUnicodeWildcardPlan(
        $rows,
        $rows,
        'éclair_',
        null,
        'stable',
        'stable',
        7,
        7,
    );
    $t->same(false, $result['indexUsable']);
    $t->same(null, $result['range']);
    $t->same([], $result['currentCandidateRowids']);
};

$tests['utf16 nocase like rtrim current source next177 rejects bad row shape'] = static function (TestRunner $t) use ($enc177): void {
    $rows = [['option_id' => 1, 'option_name_bytes' => $enc177('plugin_cacheA', 2)]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameUnicodeWildcardPlan($rows, $rows, 'plugin%'));
};

return $tests;
