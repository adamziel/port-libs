<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc229 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId229 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row229 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc229($name, $encoding),
    'text_encoding' => $encodingId229($encoding),
];
$bad229 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current229 = [
    $row229(1, 'Plugin_Cache', 'UTF-16LE'),
    $row229(2, 'plugin_cache  ', 'UTF-16BE'),
    $row229(3, "plugin_cache\u{00a0}", 'UTF-16LE'),
    $row229(4, "plugin_cache\u{3000}", 'UTF-16BE'),
    $row229(5, 'plugin_cache_alpha', 'UTF-8'),
    $row229(6, "PLUGIN_CACHE\u{202f}", 'UTF-16LE'),
    $row229(7, 'pluginXcache', 'UTF-16BE'),
    $bad229(8, "\x00\xd8", 2),
];
$nextTwoTwoNine = [
    $row229(1, 'Plugin_Cache', 'UTF-16BE'),
    $row229(2, 'plugin_cache', 'UTF-16LE'),
    $row229(3, "plugin_cache\u{00a0}", 'UTF-16BE'),
    $row229(4, 'plugin_cache  ', 'UTF-16LE'),
    $row229(5, 'plugin_cache_alpha', 'UTF-8'),
    $row229(6, "PLUGIN_CACHE\u{202f}", 'UTF-16BE'),
    $row229(9, "plugin_cache\u{2009}", 'UTF-16LE'),
    $bad229(10, "x\0y", 2),
];

$plan229 = static fn (?array $current = null, ?array $next = null): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUnicodeSpaceRtrimPlan(
    $current ?? $current229,
    $next ?? $nextTwoTwoNine,
    'plugin!_cache%',
    $enc229('!', 'UTF-16LE'),
    'UTF-16LE',
    4,
    1,
    'plugin_cache',
    'main.wp_options@228',
    'main.wp_options@229',
    228,
    229,
);

$valueAt229 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases229 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoTwoNine'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nexttwoTwoFour'],
    'operator' => ['operator', 'LIKE'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['currentEscape', '!'],
    'source current' => ['currentSource', 'main.wp_options@228'],
    'source next' => ['nextSource', 'main.wp_options@229'],
    'schema current' => ['currentSchemaCookie', 228],
    'schema next' => ['nextSchemaCookie', 229],
    'prefix' => ['currentPrefix', 'plugin_cache'],
    'range lower' => ['currentRangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['currentRangeUpperBound', 'plugin_cachf'],
    'current candidates' => ['currentCandidateRowids', [1, 2, 5, 3, 6, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 4, 5, 3, 9, 6]],
    'current matched' => ['currentMatchedRowids', [1, 2, 5, 3, 6, 4]],
    'next matched' => ['nextMatchedRowids', [1, 2, 4, 5, 3, 9, 6]],
    'current unicode rows' => ['currentUnicodeSpaceRowids', [3, 4, 6]],
    'next unicode rows' => ['nextUnicodeSpaceRowids', [3, 6, 9]],
    'current nbsp name' => ['currentUnicodeSpaceNames.3.0', 'NO-BREAK SPACE'],
    'current ideographic name' => ['currentUnicodeSpaceNames.4.0', 'IDEOGRAPHIC SPACE'],
    'current narrow nbsp name' => ['currentUnicodeSpaceNames.6.0', 'NARROW NO-BREAK SPACE'],
    'next thin space name' => ['nextUnicodeSpaceNames.9.0', 'THIN SPACE'],
    'current unicode matched' => ['currentUnicodeSpaceMatchedRowids', [3, 6, 4]],
    'next unicode matched' => ['nextUnicodeSpaceMatchedRowids', [3, 9, 6]],
    'current ascii trimmed' => ['currentAsciiSpaceTrimmedRowids', [2]],
    'next ascii trimmed' => ['nextAsciiSpaceTrimmedRowids', [4]],
    'current rtrim ascii row' => ['currentRtrimTexts.2', 'plugin_cache'],
    'current rtrim nbsp row' => ['currentRtrimTexts.3', "plugin_cache\u{00a0}"],
    'current rtrim ideographic row' => ['currentRtrimTexts.4', "plugin_cache\u{3000}"],
    'next rtrim ascii row' => ['nextRtrimTexts.4', 'plugin_cache'],
    'next rtrim thin row' => ['nextRtrimTexts.9', "plugin_cache\u{2009}"],
    'current visual nbsp' => ['currentVisualSpaceKeys.3', 'plugin_cache'],
    'current visual ideographic' => ['currentVisualSpaceKeys.4', 'plugin_cache'],
    'current visual narrow nbsp' => ['currentVisualSpaceKeys.6', 'plugin_cache'],
    'next visual thin' => ['nextVisualSpaceKeys.9', 'plugin_cache'],
    'unicode retained' => ['unicodeSpacesRetainedByRtrim', true],
    'ascii only rtrim' => ['asciiSpaceOnlyRtrim', true],
    'residual order' => ['likeResidualRunsAfterUnicodeSpaceRetention', true],
    'nocase unicode spaces' => ['nocaseFoldsUnicodeSpacesNever', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'keyset risk' => ['staleKeysetResumeRisk', true],
    'malformed current' => ['currentMalformedRowids', [8]],
    'malformed next' => ['nextMalformedRowids', [10]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-ascii-space-only'],
    'dependency current source' => ['dependencies.4', 'sqlite-current-source-nexttwoTwoNine'],
];

foreach ($cases229 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoTwoNine ' . $name] = static function (TestRunner $t) use ($plan229, $valueAt229, $path, $expected): void {
        $t->same($expected, $valueAt229($plan229(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoTwoNine invalidation reasons include unicode whitespace fences'] = static function (TestRunner $t) use ($plan229): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'malformed-text',
        'matched-rowset',
        'resume-page-rowset',
        'resume-tail-rowset',
        'unicode-space-rowset',
        'ascii-space-rtrim-rowset',
        'visual-space-peer-rowset',
    ], $plan229()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoNine groups visual peers without merging SQLite keys'] = static function (TestRunner $t) use ($plan229): void {
    $result = $plan229();
    $t->same([1, 2, 3, 4, 6], $result['currentVisualSpacePeerRowids']['plugin_cache']);
    $t->same([1, 2, 3, 4, 6, 9], $result['nextVisualSpacePeerRowids']['plugin_cache']);
    $t->same("plugin_cache\u{00a0}", $result['currentRtrimTexts'][3]);
    $t->same("plugin_cache\u{3000}", $result['currentRtrimTexts'][4]);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoNine reusable when unicode whitespace rows are byte-stable'] = static function (TestRunner $t) use ($row229): void {
    $rows = [
        $row229(1, 'plugin_cache', 'UTF-16LE'),
        $row229(2, "plugin_cache\u{00a0}", 'UTF-16BE'),
        $row229(3, "plugin_cache\u{3000}", 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUnicodeSpaceRtrimPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        SQLiteEncodingCollationSourceCursor::encodeText('!', 'UTF-16LE'),
        'UTF-16LE',
        2,
        1,
        'plugin_cache',
        'stable',
        'stable',
        229,
        229,
    );
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
    $t->same([2, 3], $result['currentUnicodeSpaceMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoNine detects ascii space changing into nbsp'] = static function (TestRunner $t) use ($row229): void {
    $current = [
        $row229(1, 'plugin_cache  ', 'UTF-16LE'),
        $row229(2, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $next = [
        $row229(1, "plugin_cache\u{00a0}", 'UTF-16LE'),
        $row229(2, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUnicodeSpaceRtrimPlan(
        $current,
        $next,
        'plugin!_cache%',
        SQLiteEncodingCollationSourceCursor::encodeText('!', 'UTF-16LE'),
        'UTF-16LE',
        2,
        0,
        null,
        'stable',
        'stable',
        229,
        229,
    );
    $t->same([1], $result['currentAsciiSpaceTrimmedRowids']);
    $t->same([1], $result['nextUnicodeSpaceMatchedRowids']);
    $t->same(true, in_array('unicode-space-rowset', $result['invalidationReasons'], true));
    $t->same(true, in_array('ascii-space-rtrim-rowset', $result['invalidationReasons'], true));
};

$tests['utf16 nocase like rtrim current source nextTwoTwoNine rejects invalid page size through base keyset plan'] = static function (TestRunner $t) use ($current229, $nextTwoTwoNine, $enc229): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUnicodeSpaceRtrimPlan(
        $current229,
        $nextTwoTwoNine,
        'plugin!_cache%',
        $enc229('!', 'UTF-16LE'),
        'UTF-16LE',
        0,
    ));
};

return $tests;
