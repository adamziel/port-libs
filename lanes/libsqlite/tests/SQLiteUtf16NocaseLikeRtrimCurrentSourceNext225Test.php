<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc225 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId225 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row225 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc225($name, $encoding),
    'text_encoding' => $encodingId225($encoding),
];
$bad225 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current225 = [
    $row225(1, 'Plugin_Cache', 'UTF-16LE'),
    $row225(2, 'plugin_cache  ', 'UTF-16LE'),
    $row225(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row225(4, "plugin_cache\t", 'UTF-16LE'),
    $row225(5, 'plugin_cache_beta', 'UTF-8'),
    $row225(6, 'plugin_other', 'UTF-16BE'),
    $bad225(9, "\x00\xd8", 2),
];
$nextTwoTwoFive = [
    $row225(1, 'Plugin_Cache', 'UTF-16BE'),
    $row225(2, 'plugin_cache  ', 'UTF-16BE'),
    $row225(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row225(4, "plugin_cache\t", 'UTF-16LE'),
    $row225(5, 'plugin_cache_beta', 'UTF-16LE'),
    $row225(6, 'plugin_other', 'UTF-16BE'),
    $row225(7, 'PLUGIN_CACHE_ZETA', 'UTF-16BE'),
    $bad225(10, "\xd8\x00", 3),
];

$plan225 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@224',
    string $nextSource = 'main.wp_options@225',
    int $currentCookie = 224,
    int $nextCookie = 225,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceBytePlan(
    $current ?? $current225,
    $next ?? $nextTwoTwoFive,
    'plugin!_cache%',
    '!',
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt225 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases225 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoTwoFive'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nexttwoOneNine'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* source-byte fence */'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@224'],
    'next source' => ['nextSource', 'main.wp_options@225'],
    'current cookie' => ['currentSchemaCookie', 224],
    'next cookie' => ['nextSchemaCookie', 225],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 4, 3, 5]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 4, 3, 5, 7]],
    'current matched' => ['currentMatchedRowids', [1, 2, 4, 3, 5]],
    'next matched' => ['nextMatchedRowids', [1, 2, 4, 3, 5, 7]],
    'matched retained' => ['matchedRetainedRowids', [1, 2, 3, 4, 5]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [7]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'row one current encoding' => ['currentTextEncodings.1', 'UTF-16LE'],
    'row one next encoding' => ['nextTextEncodings.1', 'UTF-16BE'],
    'row five current encoding' => ['currentTextEncodings.5', 'UTF-8'],
    'row five next encoding' => ['nextTextEncodings.5', 'UTF-16LE'],
    'row one current byte order' => ['currentByteOrders.1', 'little-endian'],
    'row one next byte order' => ['nextByteOrders.1', 'big-endian'],
    'row five current byte order' => ['currentByteOrders.5', 'UTF-8'],
    'row five next byte order' => ['nextByteOrders.5', 'little-endian'],
    'row one current bytes' => ['currentSourceBytesHex.1', '50006c007500670069006e005f0043006100630068006500'],
    'row one next bytes' => ['nextSourceBytesHex.1', '0050006c007500670069006e005f00430061006300680065'],
    'row two rtrim stable' => ['nextRtrimTexts.2', 'plugin_cache'],
    'row two nocase stable' => ['nextNocaseKeys.2', 'plugin_cache'],
    'changed encodings' => ['changedEncodingRowids', [1, 2, 3, 5]],
    'changed source bytes' => ['changedSourceByteRowids', [1, 2, 3, 5]],
    'changed byte order' => ['changedByteOrderRowids', [1, 2, 3, 5]],
    'stable decoded changed sources' => ['stableDecodedChangedSourceRowids', [1, 2, 3, 5]],
    'changed text' => ['changedTextRowids', []],
    'changed rtrim' => ['changedRtrimRowids', []],
    'changed nocase' => ['changedNocaseKeyRowids', []],
    'changed residual' => ['changedResidualRowids', []],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'base reusable false' => ['baseCursorReusable', false],
    'source byte fence flag' => ['sourceByteFenceAppliedAfterDecode', true],
    'stable decoded flag' => ['decodedComparisonCanRemainStableAcrossEndianRewrite', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency source bytes' => ['dependencies.3', 'sqlite-current-source-byte-fence'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoTwoFive'],
];

foreach ($cases225 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoTwoFive ' . $name] = static function (TestRunner $t) use ($plan225, $valueAt225, $path, $expected): void {
        $t->same($expected, $valueAt225($plan225(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoTwoFive invalidation reason order'] = static function (TestRunner $t) use ($plan225): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'text-encoding',
        'source-bytes',
        'utf16-byte-order',
    ], $plan225()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoFive same decoded rows still invalidate on endian rewrite'] = static function (TestRunner $t) use ($row225): void {
    $current = [
        $row225(1, 'plugin_cache', 'UTF-16LE'),
        $row225(2, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $next = [
        $row225(1, 'plugin_cache', 'UTF-16BE'),
        $row225(2, 'plugin_cache_alpha', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceBytePlan(
        $current,
        $next,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        225,
        225,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([], $result['baseInvalidationReasons']);
    $t->same(['text-encoding', 'source-bytes', 'utf16-byte-order'], $result['invalidationReasons']);
    $t->same([1, 2], $result['stableDecodedChangedSourceRowids']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoFive byte identical source can reuse cursor'] = static function (TestRunner $t) use ($row225): void {
    $rows = [
        $row225(1, 'plugin_cache', 'UTF-16LE'),
        $row225(2, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceBytePlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        225,
        225,
    );

    $t->same([], $result['changedEncodingRowids']);
    $t->same([], $result['changedSourceByteRowids']);
    $t->same([], $result['changedByteOrderRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoFive rejects unsupported raw encoding'] = static function (TestRunner $t) use ($bad225): void {
    $rows = [$bad225(1, "p\0", 4)];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceBytePlan($rows, $rows));
};

return $tests;
