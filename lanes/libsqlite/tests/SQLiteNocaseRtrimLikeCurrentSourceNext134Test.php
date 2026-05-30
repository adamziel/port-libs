<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteNocaseRtrimLikeCurrentSourceNextPlan;

$tests = [];

$row134 = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad encoding'),
        },
    ];
};

$bad134 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current134 = [
    $row134(1, 'Plugin_Cache', 'UTF-16LE'),
    $row134(2, 'plugin_cache ', 'UTF-16BE'),
    $row134(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row134(4, "plugin_cache\t", 'UTF-16LE'),
    $row134(5, 'plugin_cache_extra', 'UTF-16BE'),
    $row134(6, 'plugin_case', 'UTF-16LE'),
    $row134(7, 'Plugin_Case ', 'UTF-16BE'),
    $row134(8, 'plugin_éclair', 'UTF-16LE'),
    $row134(9, 'PLUGIN_ÉCLAIR', 'UTF-16BE'),
    $row134(10, 'theme_cache', 'UTF-8'),
    $row134(11, 'plugin', 'UTF-16LE'),
    $row134(12, 'plugio_cache', 'UTF-16BE'),
    $bad134(13, "p\x00l\x00u\x00g\x00i\x00n", 2),
];

$next134 = [
    $row134(1, 'Plugin_Cache ', 'UTF-16BE'),
    $row134(2, 'plugin_cache ', 'UTF-16LE'),
    $row134(3, 'PLUGIN_CACHE', 'UTF-8'),
    $row134(4, "plugin_cache\t", 'UTF-16LE'),
    $row134(5, 'plugin_cache_extra', 'UTF-16BE'),
    $row134(6, 'plugin_case ', 'UTF-16LE'),
    $row134(7, 'Plugin_Case ', 'UTF-16BE'),
    $row134(8, 'plugin_éclair ', 'UTF-16BE'),
    $row134(9, 'PLUGIN_ÉCLAIR', 'UTF-16BE'),
    $row134(14, 'plugin_new', 'UTF-16LE'),
    $row134(15, 'Plugin_New ', 'UTF-8'),
    $row134(16, 'plugin_cache' . "\xc2\xa0", 'UTF-16LE'),
    $bad134(17, "\x3d\xd8", 2),
];

$plan134 = static fn (
    string $currentPattern = 'plugin_%',
    string $nextPattern = 'plugin_%',
    string $currentCollation = 'NOCASE',
    string $nextCollation = 'RTRIM',
    ?array $current = null,
    ?array $next = null,
    ?string $currentEscape = null,
    ?string $nextEscape = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.app_settings@133',
    string $nextSource = 'main.app_settings@134',
): array => SQLiteNocaseRtrimLikeCurrentSourceNextPlan::keyValueRowKeyPlan(
    $current ?? $current134,
    $next ?? $next134,
    $currentPattern,
    $nextPattern,
    $currentCollation,
    $nextCollation,
    $currentEscape,
    $nextEscape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
);

$value134 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases134 = [
    'operator recorded' => ['operator', 'LIKE'],
    'case insensitive like recorded' => ['caseSensitiveLike', false],
    'current source recorded' => ['currentSource', 'main.app_settings@133'],
    'next source recorded' => ['nextSource', 'main.app_settings@134'],
    'current pattern recorded' => ['currentPattern', 'plugin_%'],
    'next pattern recorded' => ['nextPattern', 'plugin_%'],
    'current collation recorded' => ['currentCollation', 'NOCASE'],
    'next collation recorded' => ['nextCollation', 'RTRIM'],
    'current nocase index is usable' => ['currentIndexUsable', true],
    'next rtrim index is unusable for default like' => ['nextIndexUsable', false],
    'next rejected reason records default like needs nocase' => ['nextRejectedReason', 'default_like_requires_nocase_index'],
    'current range lower folds prefix' => ['currentRange.lowerInclusive', 'plugin'],
    'current range upper is next prefix' => ['currentRange.upperBound', 'plugio'],
    'next range is null' => ['nextRange', null],
    'residual flag records no trim' => ['likeResidualIgnoresCollationTrim', true],
    'current candidates use nocase prefix range' => ['currentCandidateRowids', [11, 1, 4, 2, 3, 5, 6, 7, 9, 8]],
    'next rtrim full scan candidates include all valid rows' => ['nextCandidateRowids', [3, 9, 1, 7, 15, 2, 4, 5, 16, 6, 14, 8]],
    'current residual rejects no underscore suffix row' => ['currentResidualRejectedRowids', [11]],
    'next residual rejects none after full scan' => ['nextResidualRejectedRowids', []],
    'current matched rowids keep nocase range order' => ['currentRowids', [1, 4, 2, 3, 5, 6, 7, 9, 8]],
    'next matched rowids keep rtrim order' => ['nextRowids', [3, 9, 1, 7, 15, 2, 4, 5, 16, 6, 14, 8]],
    'retained rowids preserve current order' => ['retainedRowids', [1, 4, 2, 3, 5, 6, 7, 9, 8]],
    'entered rowids preserve next order' => ['enteredRowids', [15, 16, 14]],
    'exited rowids empty' => ['exitedRowids', []],
    'current malformed rowids' => ['currentMalformedRowids', [13]],
    'next malformed rowids' => ['nextMalformedRowids', [17]],
    'current malformed error' => ['currentErrors.13', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['nextErrors.17', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'retained text changes' => ['retainedTextChangedRowids', [1, 3, 6, 8]],
    'retained encoding changes' => ['retainedEncodingChangedRowids', [1, 2, 8]],
    'retained byte changes' => ['retainedBytesChangedRowids', [1, 2, 3, 6, 8]],
    'retained nocase key changes track value changes' => ['retainedCurrentKeyChangedRowids', [1, 3, 6, 8]],
    'retained rtrim key changes ignore trailing spaces' => ['retainedNextKeyChangedRowids', []],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason collation' => ['invalidationReasons.1', 'collation-switch'],
    'reason pattern range' => ['invalidationReasons.2', 'pattern-range'],
    'reason full scan' => ['invalidationReasons.3', 'full-scan-rtrim-like'],
    'reason malformed' => ['invalidationReasons.4', 'malformed-text'],
    'reason candidate rowset' => ['invalidationReasons.5', 'candidate-rowset'],
    'reason matched rowset' => ['invalidationReasons.6', 'matched-rowset'],
    'reason text value' => ['invalidationReasons.7', 'text-value'],
    'reason text encoding' => ['invalidationReasons.8', 'text-encoding'],
    'reason encoded bytes' => ['invalidationReasons.9', 'encoded-bytes'],
    'reason collation key' => ['invalidationReasons.10', 'collation-key'],
    'current row one nocase key' => ['currentComparisonKeys.1', 'plugin_cache'],
    'next row one rtrim key' => ['nextComparisonKeys.1', 'Plugin_Cache'],
    'next row sixteen nbsp is not trimmed' => ['nextComparisonKeys.16', "plugin_cache\xc2\xa0"],
    'current row one encoding' => ['currentEncodings.1', 'UTF-16LE'],
    'next row one encoding' => ['nextEncodings.1', 'UTF-16BE'],
    'current row one bytes' => ['currentBytesHex.1', '50006c007500670069006e005f0043006100630068006500'],
    'next row one bytes' => ['nextBytesHex.1', '0050006c007500670069006e005f004300610063006800650020'],
    'dependency utf16 decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency nocase range' => ['dependencies.1', 'sqlite-like-nocase-range'],
    'dependency rtrim full scan' => ['dependencies.2', 'sqlite-rtrim-like-full-scan'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-next134'],
];

foreach ($cases134 as $name => [$path, $expected]) {
    $tests['nocase rtrim like current source next134 ' . $name] = static function (TestRunner $t) use ($plan134, $value134, $path, $expected): void {
        $t->same($expected, $value134($plan134(), $path));
    };
}

$tests['nocase rtrim like current source next134 stable nocase cursor remains reusable'] = static function (TestRunner $t) use ($row134, $plan134): void {
    $rows = [$row134(1, 'Plugin_Cache', 'UTF-16LE'), $row134(2, 'plugin_new', 'UTF-16BE')];
    $plan = $plan134('plugin_%', 'plugin_%', 'NOCASE', 'NOCASE', $rows, $rows, null, null, false, 'stable', 'stable');
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([1, 2], $plan['nextRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->true($plan['cursorReusable']);
};

$tests['nocase rtrim like current source next134 rtrim retains padded residual but not exact unpadded like'] = static function (TestRunner $t) use ($row134, $plan134): void {
    $rows = [$row134(1, 'plugin_cache', 'UTF-16LE'), $row134(2, 'plugin_cache ', 'UTF-16LE')];
    $plan = $plan134('plugin_cache', 'plugin_cache', 'RTRIM', 'RTRIM', $rows, $rows, null, null, false, 'stable', 'stable');
    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([1], $plan['currentRowids']);
    $t->same([2], $plan['currentResidualRejectedRowids']);
    $t->same('default_like_requires_nocase_index', $plan['currentRejectedReason']);
};

$tests['nocase rtrim like current source next134 escaped underscore changes range'] = static function (TestRunner $t) use ($row134, $plan134): void {
    $rows = [$row134(1, 'plugin_cache', 'UTF-16LE'), $row134(2, 'pluginXcache', 'UTF-16LE')];
    $plan = $plan134('plugin\_%', 'plugin\_%', 'NOCASE', 'NOCASE', $rows, $rows, '\\', '\\', false, 'stable', 'stable');
    $t->same('plugin_', $plan['currentRange']['lowerInclusive']);
    $t->same('plugin`', $plan['currentRange']['upperBound']);
    $t->same([1], $plan['currentCandidateRowids']);
    $t->same([1], $plan['currentRowids']);
};

$tests['nocase rtrim like current source next134 non ascii nocase prefix disables range'] = static function (TestRunner $t) use ($row134, $plan134): void {
    $rows = [$row134(1, 'éclair_option', 'UTF-16LE'), $row134(2, 'Éclair_option', 'UTF-16LE')];
    $plan = $plan134('éclair_%', 'éclair_%', 'NOCASE', 'NOCASE', $rows, $rows, null, null, false, 'stable', 'stable');
    $t->same(false, $plan['currentIndexUsable']);
    $t->same('nocase_like_prefix_must_be_ascii_for_range', $plan['currentRejectedReason']);
    $t->same([2, 1], $plan['currentCandidateRowids']);
    $t->same([1], $plan['currentRowids']);
};

$tests['nocase rtrim like current source next134 case sensitive like rejects nocase index'] = static function (TestRunner $t) use ($row134, $plan134): void {
    $rows = [$row134(1, 'Plugin_Cache', 'UTF-16LE'), $row134(2, 'plugin_cache', 'UTF-16LE')];
    $plan = $plan134('plugin_%', 'plugin_%', 'NOCASE', 'NOCASE', $rows, $rows, null, null, true, 'stable', 'stable');
    $t->same(false, $plan['currentIndexUsable']);
    $t->same('case_sensitive_like_requires_binary_index', $plan['currentRejectedReason']);
    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([2], $plan['currentRowids']);
};

$tests['nocase rtrim like current source next134 rejects unsupported current collation'] = static function (TestRunner $t) use ($plan134): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan134('plugin_%', 'plugin_%', 'BINARY', 'NOCASE'));
};

$tests['nocase rtrim like current source next134 rejects non integer option id'] = static function (TestRunner $t) use ($next134): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimLikeCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => '1', 'option_name_bytes' => 'x', 'text_encoding' => 1]], $next134, 'plugin_%', 'plugin_%'));
};

$tests['nocase rtrim like current source next134 rejects missing bytes'] = static function (TestRunner $t) use ($next134): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimLikeCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'text_encoding' => 1]], $next134, 'plugin_%', 'plugin_%'));
};

return $tests;
