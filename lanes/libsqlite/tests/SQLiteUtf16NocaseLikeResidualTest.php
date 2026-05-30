<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeCurrentSourceNextPlan;

$tests = [];

$row141 = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encoding === 'UTF-16LE' ? 2 : 3,
    ];
};

$bad141 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current141 = [
    $row141(1, 'Plugin_Cache', 'UTF-16LE'),
    $row141(2, 'plugin_cache', 'UTF-16BE'),
    $row141(3, 'plugin_cache  ', 'UTF-16LE'),
    $row141(4, 'plugin_cache_extra', 'UTF-16BE'),
    $row141(5, 'plugin_caché', 'UTF-16LE'),
    $row141(6, 'pluginXcache', 'UTF-16BE'),
    $row141(7, 'theme_cache', 'UTF-16LE'),
    $bad141(8, "p\x00l\x00u\x00g\x00i\x00n\x00_", 2),
];

$residualRows = [
    $row141(1, 'plugin_cache', 'UTF-16BE'),
    $row141(2, 'plugin_cache', 'UTF-16BE'),
    $row141(3, 'plugin_cache', 'UTF-16LE'),
    $row141(4, 'plugin_cache_extra_v2', 'UTF-16BE'),
    $row141(5, 'plugin_caché', 'UTF-16LE'),
    $row141(9, 'PLUGIN_CACHE_NEW', 'UTF-16LE'),
    $row141(10, 'plugin_cache%literal', 'UTF-16BE'),
    $bad141(11, "\x3d\xd8", 2),
];

$plan141 = static fn (
    string $pattern = 'plugin!_cache%',
    ?string $escape = '!',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@140',
    string $nextSource = 'main.wp_options@141',
    string $currentEncoding = 'UTF-16LE',
    string $nextEncoding = 'UTF-16BE',
): array => SQLiteUtf16NocaseLikeCurrentSourceNextPlan::optionRowNameResidualPlan(
    $current ?? $current141,
    $next ?? $residualRows,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentEncoding,
    $nextEncoding,
);

$value141 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases141 = [
    'operator recorded' => ['operator', 'LIKE'],
    'collation recorded' => ['collation', 'NOCASE'],
    'case insensitive like recorded' => ['caseSensitiveLike', false],
    'pattern recorded' => ['pattern', 'plugin!_cache%'],
    'escape recorded' => ['escape', '!'],
    'prefix unescapes underscore' => ['prefix', 'plugin_cache'],
    'prefix character count' => ['prefixCharacters', 12],
    'prefix ascii' => ['prefixIsAscii', true],
    'index usable' => ['indexUsable', true],
    'no rejected reason' => ['rejectedReason', null],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'current source recorded' => ['currentSource', 'main.wp_options@140'],
    'next source recorded' => ['nextSource', 'main.wp_options@141'],
    'current db encoding recorded' => ['currentDatabaseEncoding', 'UTF-16LE'],
    'next db encoding recorded' => ['nextDatabaseEncoding', 'UTF-16BE'],
    'current range lower bytes' => ['currentRangeBytesHex.lowerInclusive', '70006c007500670069006e005f0063006100630068006500'],
    'current range upper bytes' => ['currentRangeBytesHex.upperBound', '70006c007500670069006e005f0063006100630068006600'],
    'next range lower bytes' => ['nextRangeBytesHex.lowerInclusive', '0070006c007500670069006e005f00630061006300680065'],
    'next range upper bytes' => ['nextRangeBytesHex.upperBound', '0070006c007500670069006e005f00630061006300680066'],
    'range bytes changed' => ['rangeBytesChanged', true],
    'current candidates exclude malformed and outside prefix' => ['currentCandidateRowids', [1, 2, 3, 4]],
    'next candidates include new uppercase and percent literal rows' => ['nextCandidateRowids', [1, 2, 3, 10, 4, 9]],
    'current matches all candidates' => ['currentRowids', [1, 2, 3, 4]],
    'next matches all candidates' => ['nextRowids', [1, 2, 3, 10, 4, 9]],
    'retained rowids' => ['retainedRowids', [1, 2, 3, 4]],
    'entered rowids' => ['enteredRowids', [10, 9]],
    'exited rowids empty' => ['exitedRowids', []],
    'current residual rejected empty' => ['currentResidualRejectedRowids', []],
    'next residual rejected empty' => ['nextResidualRejectedRowids', []],
    'current malformed rowids' => ['currentMalformedRowids', [8]],
    'next malformed rowids' => ['nextMalformedRowids', [11]],
    'current malformed error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'repaired rowids' => ['repairedRowids', [8]],
    'newly malformed rowids' => ['newlyMalformedRowids', [11]],
    'current key row one folded' => ['currentKeys.1', 'plugin_cache'],
    'current key row four extra' => ['currentKeys.4', 'plugin_cache_extra'],
    'next key uppercase new folded' => ['nextKeys.9', 'plugin_cache_new'],
    'current row one le bytes' => ['currentBytesHex.1', '50006c007500670069006e005f0043006100630068006500'],
    'next row one be bytes' => ['nextBytesHex.1', '0070006c007500670069006e005f00630061006300680065'],
    'current row two be encoding' => ['currentEncodings.2', 'UTF-16BE'],
    'next row nine le encoding' => ['nextEncodings.9', 'UTF-16LE'],
    'retained text changed rows' => ['retainedTextChangedRowids', [1, 3, 4]],
    'retained encoding changed rows' => ['retainedEncodingChangedRowids', [1]],
    'retained bytes changed rows' => ['retainedBytesChangedRowids', [1, 3, 4]],
    'first current step rowid' => ['currentPlanSteps.0.rowid', 1],
    'first current step residual true' => ['currentPlanSteps.0.residualMatch', true],
    'first current step next rowid' => ['currentPlanSteps.0.nextRowid', 2],
    'last current step next null' => ['currentPlanSteps.3.nextRowid', null],
    'next percent literal step rowid' => ['nextPlanSteps.3.rowid', 10],
    'next percent literal residual true' => ['nextPlanSteps.3.residualMatch', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason malformed' => ['invalidationReasons.1', 'malformed-text'],
    'reason range bytes' => ['invalidationReasons.2', 'range-bytes'],
    'reason candidate rowset' => ['invalidationReasons.3', 'candidate-rowset'],
    'reason matched rowset' => ['invalidationReasons.4', 'matched-rowset'],
    'reason text value' => ['invalidationReasons.5', 'text-value'],
    'reason text encoding' => ['invalidationReasons.6', 'text-encoding'],
    'reason encoded bytes' => ['invalidationReasons.7', 'encoded-bytes'],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-range'],
    'dependency residual' => ['dependencies.2', 'sqlite-like-residual-byte-preserving'],
    'dependency marker' => ['dependencies.3', 'sqlite-current-source-nextoneFourOne'],
];

foreach ($cases141 as $name => [$path, $expected]) {
    $tests['utf16 nocase like residual ' . $name] = static function (TestRunner $t) use ($plan141, $value141, $path, $expected): void {
        $t->same($expected, $value141($plan141(), $path));
    };
}

$tests['utf16 nocase like residual fixed-width wildcard records residual rejects'] = static function (TestRunner $t) use ($plan141): void {
    $plan = $plan141('plugin!_cache__', '!');
    $t->same([1, 2, 3, 4], $plan['currentCandidateRowids']);
    $t->same([3], $plan['currentRowids']);
    $t->same([1, 2, 4], $plan['currentResidualRejectedRowids']);
    $t->same([], $plan['nextRowids']);
    $t->same([1, 2, 3, 10, 4, 9], $plan['nextResidualRejectedRowids']);
};

$tests['utf16 nocase like residual stable cursor survives unchanged malformed row outside scan'] = static function (TestRunner $t) use ($row141, $bad141, $plan141): void {
    $current = [$row141(1, 'plugin_cache', 'UTF-16LE'), $bad141(8, "x\x00_", 2)];
    $next = [$row141(1, 'plugin_cache', 'UTF-16LE'), $bad141(8, "y\x00_", 2)];
    $plan = $plan141('plugin!_cache%', '!', $current, $next, 'stable', 'stable', 'UTF-16LE', 'UTF-16LE');
    $t->same([1], $plan['currentRowids']);
    $t->same([1], $plan['nextRowids']);
    $t->same([8], $plan['currentMalformedRowids']);
    $t->same([8], $plan['nextMalformedRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->true($plan['cursorReusable']);
};

$tests['utf16 nocase like residual stable malformed error change invalidates only malformed text'] = static function (TestRunner $t) use ($row141, $bad141, $plan141): void {
    $current = [$row141(1, 'plugin_cache', 'UTF-16LE'), $bad141(8, "\xff", 2)];
    $next = [$row141(1, 'plugin_cache', 'UTF-16LE'), $bad141(8, "\x3d\xd8", 2)];
    $plan = $plan141('plugin!_cache%', '!', $current, $next, 'stable', 'stable', 'UTF-16LE', 'UTF-16LE');
    $t->same([1], $plan['currentRowids']);
    $t->same([1], $plan['nextRowids']);
    $t->same([8], $plan['currentMalformedRowids']);
    $t->same([8], $plan['nextMalformedRowids']);
    $t->same(['malformed-text'], $plan['invalidationReasons']);
};

$tests['utf16 nocase like residual non ascii prefix rejects range without scanning matches'] = static function (TestRunner $t) use ($plan141): void {
    $plan = $plan141('plugin!_caché%', '!');
    $t->same(false, $plan['indexUsable']);
    $t->same('nocase_like_prefix_must_be_ascii_for_range', $plan['rejectedReason']);
    $t->same([], $plan['currentCandidateRowids']);
    $t->same([], $plan['currentRowids']);
};

$tests['utf16 nocase like residual rejects bad escape length'] = static function (TestRunner $t) use ($plan141): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan141('plugin%', '!!'));
};

$tests['utf16 nocase like residual rejects non utf16 database encoding'] = static function (TestRunner $t) use ($plan141): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan141('plugin%', null, null, null, 'stable', 'stable', 'UTF-8', 'UTF-16LE'));
};

$tests['utf16 nocase like residual rejects non integer option id'] = static function (TestRunner $t) use ($residualRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeCurrentSourceNextPlan::optionRowNameResidualPlan([['option_id' => '1', 'option_name_bytes' => 'x', 'text_encoding' => 2]], $residualRows, 'plugin%'));
};

$tests['utf16 nocase like residual rejects missing option bytes'] = static function (TestRunner $t) use ($residualRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeCurrentSourceNextPlan::optionRowNameResidualPlan([['option_id' => 1, 'text_encoding' => 2]], $residualRows, 'plugin%'));
};

$tests['utf16 nocase like residual rejects non utf16 row encoding'] = static function (TestRunner $t) use ($residualRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeCurrentSourceNextPlan::optionRowNameResidualPlan([['option_id' => 1, 'option_name_bytes' => 'x', 'text_encoding' => 1]], $residualRows, 'plugin%'));
};

return $tests;
