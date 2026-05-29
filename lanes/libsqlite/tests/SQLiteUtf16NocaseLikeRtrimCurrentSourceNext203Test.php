<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc203 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row203 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc203($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad203 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current203 = [
    $row203(1, 'Plugin Cache  ', 'UTF-16LE'),
    $row203(2, 'plugin-cache', 'UTF-16BE'),
    $row203(3, 'theme cache', 'UTF-8'),
    $row203(4, 'plugin settings', 'UTF-16LE'),
    $row203(5, 'CACHE', 'UTF-16BE'),
    $row203(6, "plugin cache\t", 'UTF-16LE'),
    $row203(7, 'café cache', 'UTF-16BE'),
    $bad203(8, "\x00\xd8", 2),
];
$nextTwoZeroThree = [
    $row203(1, 'PLUGIN CACHE ', 'UTF-16BE'),
    $row203(2, 'plugin-cache', 'UTF-16LE'),
    $row203(3, 'theme cache', 'UTF-8'),
    $row203(4, 'plugin settings', 'UTF-16LE'),
    $row203(5, 'CACHE ', 'UTF-16BE'),
    $row203(6, "plugin cache\t", 'UTF-16LE'),
    $row203(7, 'café-cache', 'UTF-16BE'),
    $row203(9, 'new cache', 'UTF-16LE'),
    $bad203(10, "x\0y", 2),
];

$plan203 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = '%cache',
    ?string $escape = null,
    string $currentSource = 'main.wp_options@202',
    string $nextSource = 'main.wp_options@203',
    int $currentCookie = 202,
    int $nextCookie = 203,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameFullScanPlan(
    $current ?? $current203,
    $next ?? $nextTwoZeroThree,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt203 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases203 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroThree'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? /* no fixed prefix */'],
    'pattern' => ['pattern', '%cache'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'prefix empty' => ['prefix', ''],
    'prefix ascii' => ['prefixIsAscii', true],
    'range lower null' => ['rangeLowerInclusive', null],
    'range upper null' => ['rangeUpperBound', null],
    'index unusable' => ['indexUsable', false],
    'prefix cursor false' => ['usesPrefixRangeCursor', false],
    'full scan true' => ['usesFullScanFallback', true],
    'rejected reason' => ['rejectedReason', 'no_fixed_prefix'],
    'current source' => ['currentSource', 'main.wp_options@202'],
    'next source' => ['nextSource', 'main.wp_options@203'],
    'current cookie' => ['currentSchemaCookie', 202],
    'next cookie' => ['nextSchemaCookie', 203],
    'like plan rejected reason' => ['likePlan.rejectedReason', 'no_fixed_prefix'],
    'like plan has wildcard' => ['likePlan.hasWildcard', true],
    'like plan index unusable' => ['likePlan.indexUsable', false],
    'current decoded' => ['currentDecodedRowids', [5, 7, 1, 6, 4, 2, 3]],
    'next decoded' => ['nextDecodedRowids', [5, 7, 9, 1, 6, 4, 2, 3]],
    'current candidates equal decoded' => ['currentCandidateRowids', [5, 7, 1, 6, 4, 2, 3]],
    'next candidates equal decoded' => ['nextCandidateRowids', [5, 7, 9, 1, 6, 4, 2, 3]],
    'current matched' => ['currentMatchedRowids', [5, 7, 1, 2, 3]],
    'next matched' => ['nextMatchedRowids', [5, 7, 9, 1, 2, 3]],
    'current rejected' => ['currentFullScanRejectedRowids', [6, 4]],
    'next rejected' => ['nextFullScanRejectedRowids', [6, 4]],
    'matched retained' => ['matchedRetainedRowids', [5, 7, 1, 2, 3]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [9]],
    'current text one' => ['currentTexts.1', 'Plugin Cache  '],
    'next rtrim one' => ['nextRtrimTexts.1', 'PLUGIN CACHE'],
    'current tab not trimmed' => ['currentRtrimTexts.6', "plugin cache\t"],
    'current nocase key one' => ['currentNocaseKeys.1', 'plugin cache'],
    'next nocase key five' => ['nextNocaseKeys.5', 'cache'],
    'current matched text seven' => ['currentMatchedTexts.7', 'café cache'],
    'next matched text nine' => ['nextMatchedTexts.9', 'new cache'],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current malformed error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'changed text' => ['changedTextRowids', [1, 5, 7]],
    'changed rtrim' => ['changedRtrimRowids', [1, 7]],
    'changed nocase' => ['changedNocaseKeyRowids', [7]],
    'changed bytes' => ['changedBytesRowids', [1, 2, 5, 7]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'residual after rtrim' => ['likeResidualAppliesAfterRtrim', true],
    'full scan marker' => ['noFixedPrefixRequiresFullScan', true],
    'malformed isolation' => ['malformedRowsDoNotAbortFullScan', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency full scan' => ['dependencies.1', 'sqlite-like-no-fixed-prefix-full-scan'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-residual-match'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoZeroThree'],
];

foreach ($cases203 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroThree ' . $name] = static function (TestRunner $t) use ($plan203, $valueAt203, $path, $expected): void {
        $t->same($expected, $valueAt203($plan203(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroThree invalidation reasons include no fixed prefix'] = static function (TestRunner $t) use ($plan203): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'no-fixed-prefix-full-scan',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'encoded-bytes',
        'malformed-text',
        'matched-rowset',
    ], $plan203()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroThree stable source still cannot reuse prefix cursor'] = static function (TestRunner $t) use ($row203): void {
    $rows = [
        $row203(1, 'plugin cache', 'UTF-16LE'),
        $row203(2, 'theme cache  ', 'UTF-16BE'),
        $row203(3, 'plugin-cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameFullScanPlan(
        $rows,
        $rows,
        '%cache',
        null,
        'stable',
        'stable',
        203,
        203,
    );

    $t->same([1, 3, 2], $result['currentCandidateRowids']);
    $t->same([1, 3, 2], $result['currentMatchedRowids']);
    $t->same([], $result['currentFullScanRejectedRowids']);
    $t->same(['no-fixed-prefix-full-scan'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroThree escaped leading percent is rejected as ranged slice'] = static function (TestRunner $t) use ($row203): void {
    $rows = [
        $row203(1, '%cache', 'UTF-16LE'),
        $row203(2, 'plugin%cache', 'UTF-16BE'),
    ];

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameFullScanPlan(
        $rows,
        $rows,
        '!%cache',
        '!',
    ));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroThree accepts underscore wildcard with empty prefix'] = static function (TestRunner $t) use ($row203): void {
    $rows = [
        $row203(1, 'xcache', 'UTF-16LE'),
        $row203(2, 'cache', 'UTF-16BE'),
        $row203(3, 'xxcache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameFullScanPlan(
        $rows,
        $rows,
        '_cache',
        null,
        'stable',
        'stable',
        203,
        203,
    );

    $t->same('', $result['prefix']);
    $t->same([2, 1, 3], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([2, 3], $result['currentFullScanRejectedRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroThree rejects missing option id'] = static function (TestRunner $t) use ($nextTwoZeroThree): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameFullScanPlan([
        ['option_name_bytes' => 'cache', 'text_encoding' => 1],
    ], $nextTwoZeroThree));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroThree rejects missing bytes'] = static function (TestRunner $t) use ($nextTwoZeroThree): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameFullScanPlan([
        ['option_id' => 1, 'text_encoding' => 1],
    ], $nextTwoZeroThree));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroThree rejects missing encoding'] = static function (TestRunner $t) use ($nextTwoZeroThree): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameFullScanPlan([
        ['option_id' => 1, 'option_name_bytes' => 'cache'],
    ], $nextTwoZeroThree));
};

return $tests;
