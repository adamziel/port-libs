<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc221 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row221 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc221($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad221 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows221 = [
    $row221(1, 'plugin_cache', 'UTF-16LE'),
    $row221(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row221(3, 'plugin_cache_extra', 'UTF-16LE'),
    $row221(4, 'PLUGIN_CACHE_EXTRA  ', 'UTF-16BE'),
    $row221(5, 'plugin-cache', 'UTF-16LE'),
    $row221(6, 'theme_plugin_cache', 'UTF-16BE'),
    $row221(7, 'plugin_cache' . "\xc2\xa0", 'UTF-16LE'),
    $bad221(8, "\x00\xd8", 2),
];
$nextRows221 = [
    $row221(1, 'plugin_cache', 'UTF-16BE'),
    $row221(2, 'Plugin_Cache', 'UTF-16LE'),
    $row221(3, 'plugin_cache_extra', 'UTF-16BE'),
    $row221(4, 'PLUGIN_CACHE_EXTRA', 'UTF-16LE'),
    $row221(5, 'plugin-cache', 'UTF-16BE'),
    $row221(7, 'plugin_cache' . "\xc2\xa0", 'UTF-16LE'),
    $row221(9, 'plugin_cache_new', 'UTF-16LE'),
    $bad221(10, "\x00\xd8", 2),
];

$plan221 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache%',
    int|string $currentPatternEncoding = 'UTF-16LE',
    int|string $nextPatternEncoding = 'UTF-16BE',
    string $escape = '!',
    int|string $currentEscapeEncoding = 'UTF-16LE',
    int|string $nextEscapeEncoding = 'UTF-16BE',
    string $currentSource = 'main.wp_options@220',
    string $nextSource = 'main.wp_options@221',
    int $currentCookie = 220,
    int $nextCookie = 221,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedByteSignaturePlan(
    $current ?? $currentRows221,
    $next ?? $nextRows221,
    $enc221($pattern, $currentPatternEncoding),
    $currentPatternEncoding,
    $enc221($pattern, $nextPatternEncoding),
    $nextPatternEncoding,
    $enc221($escape, $currentEscapeEncoding),
    $currentEscapeEncoding,
    $enc221($escape, $nextEscapeEncoding),
    $nextEscapeEncoding,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt221 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases221 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoTwoOne'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroZero'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 byte signature */'],
    'current pattern' => ['currentPattern', 'plugin!_cache%'],
    'next pattern' => ['nextPattern', 'plugin!_cache%'],
    'current escape' => ['currentEscape', '!'],
    'next escape' => ['nextEscape', '!'],
    'same decoded sql' => ['sameDecodedSql', true],
    'same prepared bytes' => ['samePreparedBytes', false],
    'current pattern encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next pattern encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current escape encoding' => ['currentEscapeEncoding', 'UTF-16LE'],
    'next escape encoding' => ['nextEscapeEncoding', 'UTF-16BE'],
    'current pattern bytes' => ['currentPatternBytesHex', bin2hex($enc221('plugin!_cache%', 'UTF-16LE'))],
    'next pattern bytes' => ['nextPatternBytesHex', bin2hex($enc221('plugin!_cache%', 'UTF-16BE'))],
    'current escape bytes' => ['currentEscapeBytesHex', bin2hex($enc221('!', 'UTF-16LE'))],
    'next escape bytes' => ['nextEscapeBytesHex', bin2hex($enc221('!', 'UTF-16BE'))],
    'current signature pattern encoding' => ['currentPreparedSignature.patternEncoding', 'UTF-16LE'],
    'next signature pattern encoding' => ['nextPreparedSignature.patternEncoding', 'UTF-16BE'],
    'current signature escape encoding' => ['currentPreparedSignature.escapeEncoding', 'UTF-16LE'],
    'next signature escape encoding' => ['nextPreparedSignature.escapeEncoding', 'UTF-16BE'],
    'current source' => ['currentSource', 'main.wp_options@220'],
    'next source' => ['nextSource', 'main.wp_options@221'],
    'current cookie' => ['currentSchemaCookie', 220],
    'next cookie' => ['nextSchemaCookie', 221],
    'prefix' => ['prefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'next range lower' => ['nextRangeLowerInclusive', 'plugin_cache'],
    'next range upper' => ['nextRangeUpperBound', 'plugin_cachf'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 4, 7]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 4, 9, 7]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 4, 7]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 4, 9, 7]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [9]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [5, 6]],
    'next excluded' => ['nextExcludedDecodedRowids', [5]],
    'current rtrim two' => ['currentRtrimTexts.2', 'Plugin_Cache'],
    'current rtrim four' => ['currentRtrimTexts.4', 'PLUGIN_CACHE_EXTRA'],
    'next rtrim nine' => ['nextRtrimTexts.9', 'plugin_cache_new'],
    'current key four' => ['currentNocaseKeys.4', 'plugin_cache_extra'],
    'next key nine' => ['nextNocaseKeys.9', 'plugin_cache_new'],
    'current matched text seven' => ['currentMatchedTexts.7', 'plugin_cache' . "\xc2\xa0"],
    'next matched text nine' => ['nextMatchedTexts.9', 'plugin_cache_new'],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'decode before prefix' => ['mustDecodePatternBeforePrefixPlanning', true],
    'must reprepare' => ['mustReprepareForPreparedByteSignature', true],
    'share range' => ['decodedSqlCanStillShareRange', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency signature' => ['dependencies.1', 'sqlite-prepared-like-byte-signature'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoTwoOne'],
];

foreach ($cases221 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoTwoOne ' . $name] = static function (TestRunner $t) use ($plan221, $valueAt221, $path, $expected): void {
        $t->same($expected, $valueAt221($plan221(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoTwoOne invalidation reasons include byte signature'] = static function (TestRunner $t) use ($plan221): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'malformed-text',
        'matched-rowset',
        'prepared-byte-signature',
        'decoded-sql-byte-signature',
        'prepared-encoding',
    ], $plan221()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoOne stable source still rejects stale byte signature'] = static function (TestRunner $t) use ($enc221, $row221): void {
    $rows = [
        $row221(1, 'plugin_cache', 'UTF-16LE'),
        $row221(2, 'Plugin_Cache  ', 'UTF-16BE'),
        $row221(3, 'plugin-cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedByteSignaturePlan(
        $rows,
        $rows,
        $enc221('plugin!_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $enc221('plugin!_cache%', 'UTF-16BE'),
        'UTF-16BE',
        $enc221('!', 'UTF-16LE'),
        'UTF-16LE',
        $enc221('!', 'UTF-16BE'),
        'UTF-16BE',
        'stable',
        'stable',
        221,
        221,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([], $result['stableSourceInvalidationReasons']);
    $t->same(['prepared-byte-signature', 'decoded-sql-byte-signature', 'prepared-encoding'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoOne identical prepared bytes can reuse stable cursor'] = static function (TestRunner $t) use ($enc221, $row221): void {
    $rows = [
        $row221(1, 'plugin_cache', 'UTF-16LE'),
        $row221(2, 'Plugin_Cache  ', 'UTF-16BE'),
        $row221(3, 'theme_plugin_cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedByteSignaturePlan(
        $rows,
        $rows,
        $enc221('plugin!_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $enc221('plugin!_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $enc221('!', 'UTF-16LE'),
        'UTF-16LE',
        $enc221('!', 'UTF-16LE'),
        'UTF-16LE',
        'stable',
        'stable',
        221,
        221,
    );

    $t->same(true, $result['sameDecodedSql']);
    $t->same(true, $result['samePreparedBytes']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoOne escape byte signature changes independently'] = static function (TestRunner $t) use ($enc221, $row221): void {
    $rows = [
        $row221(1, 'plugin_cache', 'UTF-16LE'),
        $row221(2, 'plugin-cache', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedByteSignaturePlan(
        $rows,
        $rows,
        $enc221('plugin!_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $enc221('plugin!_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $enc221('!', 'UTF-16LE'),
        'UTF-16LE',
        $enc221('!', 'UTF-16BE'),
        'UTF-16BE',
        'stable',
        'stable',
        221,
        221,
    );

    $t->same('plugin_cache', $result['prefix']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([1], $result['nextMatchedRowids']);
    $t->same(['prepared-byte-signature', 'decoded-sql-byte-signature', 'prepared-encoding'], $result['invalidationReasons']);
    $t->same('UTF-16LE', $result['currentPreparedSignature']['escapeEncoding']);
    $t->same('UTF-16BE', $result['nextPreparedSignature']['escapeEncoding']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoOne rejects malformed prepared bytes'] = static function (TestRunner $t) use ($currentRows221, $nextRows221, $enc221): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedByteSignaturePlan(
        $currentRows221,
        $nextRows221,
        "\x00\xd8",
        'UTF-16LE',
        $enc221('plugin!_cache%', 'UTF-16BE'),
        'UTF-16BE',
        $enc221('!', 'UTF-16LE'),
        'UTF-16LE',
        $enc221('!', 'UTF-16BE'),
        'UTF-16BE',
    ));
};

return $tests;
