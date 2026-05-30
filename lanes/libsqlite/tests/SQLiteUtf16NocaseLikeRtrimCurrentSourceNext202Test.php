<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc202 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId202 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row202 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc202($name, $encoding),
    'text_encoding' => $encodingId202($encoding),
];
$pattern202 = static fn (int $id, string $pattern, int|string $encoding): array => [
    'option_id' => $id,
    'option_value_bytes' => $enc202($pattern, $encoding),
    'text_encoding' => $encodingId202($encoding),
];
$bad202 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current202 = [
    $row202(1, 'Plugin_Cache', 'UTF-16LE'),
    $row202(2, 'plugin_cache  ', 'UTF-16BE'),
    $row202(3, 'plugin_cache_alpha', 'UTF-8'),
    $row202(4, 'plugin_cache_beta', 'UTF-16LE'),
    $row202(5, "plugin_cache\t", 'UTF-16BE'),
    $row202(6, 'plugin_setting', 'UTF-16LE'),
    $row202(7, 'theme_cache', 'UTF-16LE'),
    $bad202(8, "\x00\xd8", 2),
];
$nextTwoZeroTwo = [
    $row202(1, 'Plugin_Cache', 'UTF-16BE'),
    $row202(2, 'plugin_cache  ', 'UTF-16LE'),
    $row202(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row202(4, 'plugin_cache_beta', 'UTF-8'),
    $row202(5, "plugin_cache\t", 'UTF-16LE'),
    $row202(6, 'plugin_setting', 'UTF-16BE'),
    $row202(9, 'plugin_cache_zeta', 'UTF-16LE'),
    $row202(10, 'plugin_config', 'UTF-8'),
    $bad202(11, "x\0y", 2),
];
$currentPattern202 = $pattern202(91, 'plugin!_cache%', 'UTF-16LE');
$nextPattern202 = $pattern202(92, 'plugin!_cache!_%', 'UTF-16BE');

$plan202 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $currentPattern = null,
    ?array $nextPattern = null,
    string $currentSource = 'main.app_settings@201',
    string $nextSource = 'main.app_settings@202',
    int $currentCookie = 201,
    int $nextCookie = 202,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePatternPlan(
    $current ?? $current202,
    $next ?? $nextTwoZeroTwo,
    $currentPattern ?? $currentPattern202,
    $nextPattern ?? $nextPattern202,
    '!',
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt202 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases202 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroTwo'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE (SELECT option_value FROM wp_options WHERE option_name = ?)'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.app_settings@201'],
    'next source' => ['nextSource', 'main.app_settings@202'],
    'current cookie' => ['currentSchemaCookie', 201],
    'next cookie' => ['nextSchemaCookie', 202],
    'current pattern rowid' => ['currentPatternSourceRowid', 91],
    'next pattern rowid' => ['nextPatternSourceRowid', 92],
    'current encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current pattern' => ['currentPattern', 'plugin!_cache%'],
    'next pattern' => ['nextPattern', 'plugin!_cache!_%'],
    'same decoded' => ['sameDecodedPattern', false],
    'same bytes' => ['sameSourcePatternBytes', false],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache_'],
    'current lower' => ['currentRangeLowerInclusive', 'plugin_cache'],
    'next lower' => ['nextRangeLowerInclusive', 'plugin_cache_'],
    'current upper' => ['currentRangeUpperBound', 'plugin_cachf'],
    'next upper' => ['nextRangeUpperBound', 'plugin_cache`'],
    'current range cursor' => ['currentUsesPrefixRangeCursor', true],
    'next range cursor' => ['nextUsesPrefixRangeCursor', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 5, 3, 4]],
    'next candidates' => ['nextCandidateRowids', [3, 4, 9]],
    'candidate retained' => ['candidateRetainedRowids', [3, 4]],
    'candidate exited' => ['candidateExitedRowids', [1, 2, 5]],
    'candidate entered' => ['candidateEnteredRowids', [9]],
    'current matched' => ['currentMatchedRowids', [1, 2, 5, 3, 4]],
    'next matched' => ['nextMatchedRowids', [3, 4, 9]],
    'matched retained' => ['matchedRetainedRowids', [3, 4]],
    'matched exited' => ['matchedExitedRowids', [1, 2, 5]],
    'matched entered' => ['matchedEnteredRowids', [9]],
    'current false positives' => ['currentRangeFalsePositiveRowids', []],
    'next false positives' => ['nextRangeFalsePositiveRowids', []],
    'current rtrim row two' => ['currentRtrimTexts.2', 'plugin_cache'],
    'next rtrim row two' => ['nextRtrimTexts.2', 'plugin_cache'],
    'current key row one' => ['currentNocaseKeys.1', 'plugin_cache'],
    'next key row nine' => ['nextNocaseKeys.9', 'plugin_cache_zeta'],
    'current matched text row five' => ['currentMatchedTexts.5', "plugin_cache\t"],
    'next matched text row nine' => ['nextMatchedTexts.9', 'plugin_cache_zeta'],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'reason source' => ['rhsPatternInvalidationReasons.0', 'source-or-schema-changed'],
    'reason rowid' => ['rhsPatternInvalidationReasons.1', 'rhs-pattern-source-rowid-changed'],
    'reason bytes' => ['rhsPatternInvalidationReasons.2', 'rhs-pattern-source-bytes-changed'],
    'reason decoded' => ['rhsPatternInvalidationReasons.3', 'decoded-rhs-pattern-changed'],
    'reason range' => ['rhsPatternInvalidationReasons.4', 'range-bound'],
    'reason candidates' => ['rhsPatternInvalidationReasons.5', 'candidate-rowset'],
    'reason matches' => ['rhsPatternInvalidationReasons.6', 'matched-rowset'],
    'reason malformed' => ['rhsPatternInvalidationReasons.7', 'malformed-text'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'must reprepare' => ['mustReprepareForSourcePatternChange', true],
    'cannot reuse residual' => ['canReuseResidualForStableSourcePattern', false],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'rhs source row' => ['rhsPatternComesFromCurrentSourceRow', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency source pattern' => ['dependencies.1', 'sqlite-source-row-like-pattern'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoZeroTwo'],
];

foreach ($cases202 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroTwo ' . $name] = static function (TestRunner $t) use ($plan202, $valueAt202, $path, $expected): void {
        $t->same($expected, $valueAt202($plan202(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroTwo stable utf16 byte order pattern remains reusable'] = static function (TestRunner $t) use ($row202, $pattern202): void {
    $rows = [
        $row202(1, 'Plugin_Cache', 'UTF-16LE'),
        $row202(2, 'plugin_cache  ', 'UTF-16BE'),
        $row202(3, 'plugin_cache_alpha', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePatternPlan(
        $rows,
        $rows,
        $pattern202(91, 'plugin!_cache%', 'UTF-16LE'),
        $pattern202(91, 'plugin!_cache%', 'UTF-16LE'),
        '!',
        'stable',
        'stable',
        202,
        202,
    );

    $t->same(true, $result['sameDecodedPattern']);
    $t->same(true, $result['sameSourcePatternBytes']);
    $t->same([], $result['rhsPatternInvalidationReasons']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same(false, $result['mustReprepareForSourcePatternChange']);
    $t->same(true, $result['canReuseResidualForStableSourcePattern']);
    $t->same([1, 2, 3], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroTwo same decoded pattern but new source bytes invalidates cursor'] = static function (TestRunner $t) use ($row202, $pattern202): void {
    $rows = [
        $row202(1, 'plugin_cache', 'UTF-16LE'),
        $row202(2, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePatternPlan(
        $rows,
        $rows,
        $pattern202(91, 'plugin!_cache%', 'UTF-16LE'),
        $pattern202(91, 'plugin!_cache%', 'UTF-16BE'),
        '!',
        'stable',
        'stable',
        202,
        202,
    );

    $t->same(true, $result['sameDecodedPattern']);
    $t->same(false, $result['sameSourcePatternBytes']);
    $t->same(['rhs-pattern-source-bytes-changed'], $result['rhsPatternInvalidationReasons']);
    $t->same(true, $result['cursorInvalidated']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroTwo source rowid replacement invalidates even with same bytes'] = static function (TestRunner $t) use ($row202, $pattern202): void {
    $rows = [
        $row202(1, 'plugin_cache', 'UTF-16LE'),
        $row202(2, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $currentPattern = $pattern202(91, 'plugin!_cache%', 'UTF-16LE');
    $nextPattern = $currentPattern;
    $nextPattern['option_id'] = 99;
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePatternPlan(
        $rows,
        $rows,
        $currentPattern,
        $nextPattern,
        '!',
        'stable',
        'stable',
        202,
        202,
    );

    $t->same(true, $result['sameDecodedPattern']);
    $t->same(true, $result['sameSourcePatternBytes']);
    $t->same(['rhs-pattern-source-rowid-changed'], $result['rhsPatternInvalidationReasons']);
    $t->same(true, $result['cursorInvalidated']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroTwo malformed source pattern is rejected'] = static function (TestRunner $t) use ($row202, $pattern202): void {
    $rows = [$row202(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePatternPlan(
        $rows,
        $rows,
        $pattern202(91, 'plugin!_cache%', 'UTF-16LE'),
        ['option_id' => 92, 'option_value_bytes' => "\x00\xd8", 'text_encoding' => 2],
    ));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroTwo rejects malformed pattern row shape'] = static function (TestRunner $t) use ($row202, $pattern202): void {
    $rows = [$row202(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePatternPlan(
        $rows,
        $rows,
        $pattern202(91, 'plugin!_cache%', 'UTF-16LE'),
        ['option_id' => '92', 'option_value_bytes' => 'plugin%', 'text_encoding' => 1],
    ));
};

return $tests;
