<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeRtrimCurrentSourceNextPlan;

$tests = [];

$row137 = static function (int $id, string $name, string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad encoding'),
        },
        'autoload' => $autoload,
    ];
};

$bad137 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
    'autoload' => 'yes',
];

$current137 = [
    $row137(1, 'plugin_cache', 'UTF-16LE'),
    $row137(2, 'plugin_cache ', 'UTF-16BE'),
    $row137(3, 'plugin_cache  ', 'UTF-8'),
    $row137(4, "plugin_cache\t", 'UTF-16LE'),
    $row137(5, "plugin_cache\xc2\xa0", 'UTF-16BE'),
    $row137(6, 'plugin_cache_extra', 'UTF-16LE'),
    $row137(7, 'Plugin_Cache', 'UTF-16BE'),
    $row137(8, 'plugin_éclair ', 'UTF-16LE'),
    $row137(9, 'plugin_Éclair ', 'UTF-16BE'),
    $row137(10, 'plugin_😀 ', 'UTF-16LE'),
    $row137(11, 'theme_cache', 'UTF-16LE'),
    $bad137(12, "p\x00l\x00u\x00g\x00i\x00n\x00_", 2),
];

$nextOneThreeSeven = [
    $row137(1, 'plugin_cache', 'UTF-16BE'),
    $row137(2, 'plugin_cache ', 'UTF-16LE'),
    $row137(3, 'plugin_cache', 'UTF-8'),
    $row137(4, "plugin_cache\t", 'UTF-16LE'),
    $row137(5, "plugin_cache\xc2\xa0", 'UTF-16BE'),
    $row137(6, 'plugin_cache_extra_v2', 'UTF-16LE'),
    $row137(7, 'Plugin_Cache', 'UTF-16BE'),
    $row137(8, 'plugin_éclair', 'UTF-16BE'),
    $row137(9, 'plugin_Éclair ', 'UTF-16BE'),
    $row137(10, 'plugin_😀', 'UTF-16BE'),
    $row137(13, 'plugin_cache_new', 'UTF-16LE'),
    $bad137(14, "\x3d\xd8", 2),
];

$plan137 = static fn (
    string $pattern = 'plugin_cache',
    ?string $escape = null,
    bool $caseSensitiveLike = true,
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@136',
    string $nextSource = 'main.wp_options@137',
): array => SQLiteUtf16LikeRtrimCurrentSourceNextPlan::optionRowNamePlan(
    $current ?? $current137,
    $next ?? $nextOneThreeSeven,
    $pattern,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
);

$value137 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases137 = [
    'operator recorded' => ['plugin_cache', null, true, 'operator', 'LIKE'],
    'rtrim collation recorded' => ['plugin_cache', null, true, 'collation', 'RTRIM'],
    'pattern recorded' => ['plugin_cache', null, true, 'pattern', 'plugin_cache'],
    'case sensitive recorded' => ['plugin_cache', null, true, 'caseSensitiveLike', true],
    'range unavailable' => ['plugin_cache', null, true, 'range', null],
    'index unusable' => ['plugin_cache', null, true, 'indexUsable', false],
    'rejection records binary index need' => ['plugin_cache', null, true, 'rejectedReason', 'case_sensitive_like_requires_binary_index'],
    'candidate source full scan' => ['plugin_cache', null, true, 'candidateSource', 'full-scan'],
    'residual marker' => ['plugin_cache', null, true, 'likeResidualDoesNotApplyRtrim', true],
    'current candidates include all valid rows sorted by rtrim key' => ['plugin_cache', null, true, 'currentCandidateRowids', [7, 1, 2, 3, 4, 6, 5, 9, 8, 10, 11]],
    'next candidates include all valid rows sorted by rtrim key' => ['plugin_cache', null, true, 'nextCandidateRowids', [7, 1, 2, 3, 4, 6, 13, 5, 9, 8, 10]],
    'exact current like only unpadded row' => ['plugin_cache', null, true, 'currentRowids', [1]],
    'exact next like includes repaired row' => ['plugin_cache', null, true, 'nextRowids', [1, 3]],
    'space padded rows are residual rejected current' => ['plugin_cache', null, true, 'currentResidualRejectedRowids', [7, 2, 3, 4, 6, 5, 9, 8, 10, 11]],
    'space padded rows are residual rejected next' => ['plugin_cache', null, true, 'nextResidualRejectedRowids', [7, 2, 4, 6, 13, 5, 9, 8, 10]],
    'retained exact match' => ['plugin_cache', null, true, 'retainedRowids', [1]],
    'entered exact match' => ['plugin_cache', null, true, 'enteredRowids', [3]],
    'exited exact match empty' => ['plugin_cache', null, true, 'exitedRowids', []],
    'wildcard current matches cache family including padding' => ['plugin_cache%', null, true, 'currentRowids', [1, 2, 3, 4, 6, 5]],
    'wildcard next matches cache family including new row' => ['plugin_cache%', null, true, 'nextRowids', [1, 2, 3, 4, 6, 13, 5]],
    'wildcard residual rejects uppercase under case sensitive like' => ['plugin_cache%', null, true, 'currentResidualRejectedRowids', [7, 9, 8, 10, 11]],
    'case insensitive exact matches uppercase and repaired row' => ['plugin_cache', null, false, 'nextRowids', [7, 1, 3]],
    'case insensitive rejection records nocase need' => ['plugin_cache', null, false, 'rejectedReason', 'default_like_requires_nocase_index'],
    'space pattern current row two' => ['plugin_cache ', null, true, 'currentRowids', [2]],
    'two space pattern current row three' => ['plugin_cache  ', null, true, 'currentRowids', [3]],
    'tab pattern current row four' => ["plugin_cache\t", null, true, 'currentRowids', [4]],
    'nbsp pattern current row five' => ["plugin_cache\xc2\xa0", null, true, 'currentRowids', [5]],
    'escaped underscore current family' => ['plugin!_cache%', '!', true, 'currentRowids', [1, 2, 3, 4, 6, 5]],
    'escaped percent has no matches' => ['plugin!%cache%', '!', true, 'currentRowids', []],
    'lower e acute current only lower' => ['plugin_éclair%', null, true, 'currentRowids', [8]],
    'upper e acute current only upper' => ['plugin_Éclair%', null, true, 'currentRowids', [9]],
    'emoji exact space current' => ['plugin_😀 ', null, true, 'currentRowids', [10]],
    'emoji exact no space next' => ['plugin_😀', null, true, 'nextRowids', [10]],
    'theme exact exits next' => ['theme_cache', null, true, 'exitedRowids', [11]],
    'current comparison key trims ascii spaces' => ['plugin_cache', null, true, 'currentComparisonKeys.2', 'plugin_cache'],
    'current comparison key leaves tab' => ['plugin_cache', null, true, 'currentComparisonKeys.4', "plugin_cache\t"],
    'current comparison key leaves nbsp' => ['plugin_cache', null, true, 'currentComparisonKeys.5', "plugin_cache\xc2\xa0"],
    'current uppercase comparison key not nocase folded' => ['plugin_cache', null, true, 'currentComparisonKeys.7', 'Plugin_Cache'],
    'next row one endian switch' => ['plugin_cache', null, true, 'nextEncodings.1', 'UTF-16BE'],
    'next row two endian switch' => ['plugin_cache', null, true, 'nextEncodings.2', 'UTF-16LE'],
    'current row one le bytes' => ['plugin_cache', null, true, 'currentBytesHex.1', '70006c007500670069006e005f0063006100630068006500'],
    'next row one be bytes' => ['plugin_cache', null, true, 'nextBytesHex.1', '0070006c007500670069006e005f00630061006300680065'],
    'current malformed rowids' => ['plugin_cache', null, true, 'currentMalformedRowids', [12]],
    'next malformed rowids' => ['plugin_cache', null, true, 'nextMalformedRowids', [14]],
    'current malformed error' => ['plugin_cache', null, true, 'currentErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['plugin_cache', null, true, 'nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'retained text changed' => ['plugin_cache%', null, true, 'retainedTextChangedRowids', [3, 6, 8, 10]],
    'retained encoding changed' => ['plugin_cache%', null, true, 'retainedEncodingChangedRowids', [1, 2, 8, 10]],
    'retained bytes changed' => ['plugin_cache%', null, true, 'retainedBytesChangedRowids', [1, 2, 3, 6, 8, 10]],
    'retained rtrim key changed excludes pure space trim row three' => ['plugin_cache%', null, true, 'retainedRtrimKeyChangedRowids', [6]],
    'first current step rowid' => ['plugin_cache', null, true, 'currentPlanSteps.0.rowid', 7],
    'first current step next rowid' => ['plugin_cache', null, true, 'currentPlanSteps.0.nextRowid', 1],
    'first current step residual false' => ['plugin_cache', null, true, 'currentPlanSteps.0.residualMatch', false],
    'second current step residual true' => ['plugin_cache', null, true, 'currentPlanSteps.1.residualMatch', true],
    'second current step next residual false' => ['plugin_cache', null, true, 'currentPlanSteps.1.nextResidualMatch', false],
    'last current step has no next' => ['plugin_cache', null, true, 'currentPlanSteps.10.nextRowid', null],
    'invalidated true' => ['plugin_cache', null, true, 'cursorInvalidated', true],
    'reusable false' => ['plugin_cache', null, true, 'cursorReusable', false],
    'reason source' => ['plugin_cache', null, true, 'invalidationReasons.0', 'source-name'],
    'reason malformed' => ['plugin_cache', null, true, 'invalidationReasons.1', 'malformed-text'],
    'reason candidate rowset' => ['plugin_cache', null, true, 'invalidationReasons.2', 'candidate-rowset'],
    'reason matched rowset' => ['plugin_cache', null, true, 'invalidationReasons.3', 'matched-rowset'],
    'reason text value' => ['plugin_cache', null, true, 'invalidationReasons.4', 'text-value'],
    'reason text encoding' => ['plugin_cache', null, true, 'invalidationReasons.5', 'text-encoding'],
    'reason encoded bytes' => ['plugin_cache', null, true, 'invalidationReasons.6', 'encoded-bytes'],
    'reason rtrim key' => ['plugin_cache', null, true, 'invalidationReasons.7', 'rtrim-key'],
    'dependency utf16 decode' => ['plugin_cache', null, true, 'dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim full scan' => ['plugin_cache', null, true, 'dependencies.1', 'sqlite-like-rtrim-full-scan-current-next'],
    'dependency residual' => ['plugin_cache', null, true, 'dependencies.2', 'sqlite-like-residual-byte-preserving'],
    'dependency marker' => ['plugin_cache', null, true, 'dependencies.3', 'sqlite-current-source-nextoneThreeSeven'],
];

foreach ($cases137 as $name => [$pattern, $escape, $caseSensitiveLike, $path, $expected]) {
    $tests['utf16 like rtrim current source nextOneThreeSeven ' . $name] = static function (TestRunner $t) use ($plan137, $value137, $pattern, $escape, $caseSensitiveLike, $path, $expected): void {
        $t->same($expected, $value137($plan137($pattern, $escape, $caseSensitiveLike), $path));
    };
}

$tests['utf16 like rtrim current source nextOneThreeSeven stable unchanged still records reusable full scan'] = static function (TestRunner $t) use ($row137, $plan137): void {
    $rows = [$row137(1, 'plugin_cache', 'UTF-16LE'), $row137(2, 'plugin_cache ', 'UTF-16LE')];
    $plan = $plan137('plugin_cache', null, true, $rows, $rows, 'stable', 'stable');
    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([1], $plan['currentRowids']);
    $t->same([2], $plan['currentResidualRejectedRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->true($plan['cursorReusable']);
};

$tests['utf16 like rtrim current source nextOneThreeSeven stable byte only change invalidates bytes'] = static function (TestRunner $t) use ($row137, $plan137): void {
    $current = [$row137(1, 'plugin_cache', 'UTF-16LE')];
    $next = [$row137(1, 'plugin_cache', 'UTF-16BE')];
    $plan = $plan137('plugin_cache', null, true, $current, $next, 'stable', 'stable');
    $t->same([1], $plan['currentRowids']);
    $t->same([1], $plan['nextRowids']);
    $t->same([], $plan['retainedTextChangedRowids']);
    $t->same([1], $plan['retainedEncodingChangedRowids']);
    $t->same([1], $plan['retainedBytesChangedRowids']);
    $t->same(['text-encoding', 'encoded-bytes'], $plan['invalidationReasons']);
};

$tests['utf16 like rtrim current source nextOneThreeSeven stable trailing space repair changes exact match rowset'] = static function (TestRunner $t) use ($row137, $plan137): void {
    $current = [$row137(1, 'plugin_cache ', 'UTF-16LE')];
    $next = [$row137(1, 'plugin_cache', 'UTF-16LE')];
    $plan = $plan137('plugin_cache', null, true, $current, $next, 'stable', 'stable');
    $t->same([], $plan['currentRowids']);
    $t->same([1], $plan['nextRowids']);
    $t->same([], $plan['retainedRtrimKeyChangedRowids']);
    $t->same(['matched-rowset', 'text-value', 'encoded-bytes'], $plan['invalidationReasons']);
};

$tests['utf16 like rtrim current source nextOneThreeSeven rejects invalid escape length'] = static function (TestRunner $t) use ($plan137): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan137('plugin%', '!!'));
};

$tests['utf16 like rtrim current source nextOneThreeSeven rejects non integer option id'] = static function (TestRunner $t) use ($nextOneThreeSeven): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeRtrimCurrentSourceNextPlan::optionRowNamePlan([['option_id' => '1', 'option_name_bytes' => 'x', 'text_encoding' => 1]], $nextOneThreeSeven, 'plugin%'));
};

$tests['utf16 like rtrim current source nextOneThreeSeven rejects missing bytes'] = static function (TestRunner $t) use ($nextOneThreeSeven): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeRtrimCurrentSourceNextPlan::optionRowNamePlan([['option_id' => 1, 'text_encoding' => 1]], $nextOneThreeSeven, 'plugin%'));
};

$tests['utf16 like rtrim current source nextOneThreeSeven rejects missing encoding'] = static function (TestRunner $t) use ($nextOneThreeSeven): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeRtrimCurrentSourceNextPlan::optionRowNamePlan([['option_id' => 1, 'option_name_bytes' => 'x']], $nextOneThreeSeven, 'plugin%'));
};

return $tests;
