<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding, string $load_policy = 'yes'): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad encoding'),
        },
        'load_policy' => $load_policy,
    ];
};

$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
    'load_policy' => 'yes',
];

$currentRows = [
    $row(1, 'module_cache', 'UTF-16LE'),
    $row(2, 'module_cache ', 'UTF-16BE'),
    $row(3, 'module_cache  ', 'UTF-8'),
    $row(4, "module_cache\t", 'UTF-16LE'),
    $row(5, 'module_cache_extra', 'UTF-16BE'),
    $row(6, 'Module_Cache', 'UTF-8', 'no'),
    $row(7, 'module_éclair ', 'UTF-16LE'),
    $row(8, 'module_Éclair ', 'UTF-16BE'),
    $row(9, 'module_😀 ', 'UTF-16LE'),
    $row(10, 'module_cache' . "\u{00a0}", 'UTF-16BE'),
    $row(11, 'theme_cache ', 'UTF-16LE'),
    $bad(12, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00c", 2),
];

$nextRows = [
    $row(1, 'module_cache', 'UTF-16BE'),
    $row(2, 'module_cache ', 'UTF-16BE'),
    $row(3, 'module_cache', 'UTF-8'),
    $row(4, "module_cache\t", 'UTF-16LE'),
    $row(5, 'module_cache_extra_v2', 'UTF-16BE'),
    $row(6, 'Module_Cache', 'UTF-8', 'no'),
    $row(7, 'module_éclair', 'UTF-16BE'),
    $row(8, 'module_Éclair ', 'UTF-16BE'),
    $row(9, 'module_😀', 'UTF-16BE'),
    $row(10, 'module_cache' . "\u{00a0}", 'UTF-16BE'),
    $row(13, 'module_cache_new', 'UTF-16LE'),
    $bad(14, "\xd8\x00", 3),
];

$plan = static fn (
    string $currentPattern = 'module!_cache%',
    string $nextPattern = 'module_cache*',
    string $currentOperator = 'LIKE',
    string $nextOperator = 'GLOB',
    ?string $currentEscape = '!',
    ?string $nextEscape = null,
    ?array $current = null,
    ?array $next = null,
): array => SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan::keyValueRowKeyOperatorSwitchPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $currentPattern,
    $nextPattern,
    $currentOperator,
    $nextOperator,
    $currentEscape,
    $nextEscape,
    true,
    'main.app_settings@127',
    'main.app_settings@128',
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'collation' => ['collation', 'RTRIM'],
    'current operator' => ['currentOperator', 'LIKE'],
    'next operator' => ['nextOperator', 'GLOB'],
    'current pattern' => ['currentPattern', 'module!_cache%'],
    'next pattern' => ['nextPattern', 'module_cache*'],
    'current escape' => ['currentEscape', '!'],
    'next escape' => ['nextEscape', null],
    'current LIKE range remains null under RTRIM' => ['currentRange', null],
    'next GLOB range lower' => ['nextRange.lowerInclusive', 'module_cache'],
    'next GLOB range upper' => ['nextRange.upperBound', 'module_cachf'],
    'current LIKE rtrim index not usable' => ['currentIndexUsable', false],
    'next GLOB rtrim index usable' => ['nextIndexUsable', true],
    'LIKE forces full scan marker' => ['likeRtrimRequiresFullScan', true],
    'residual does not trim' => ['residualDoesNotTrimTrailingSpaces', true],
    'current LIKE candidates are full decoded scan' => ['currentCandidateRowids', [6, 1, 2, 3, 4, 5, 10, 8, 7, 9, 11]],
    'next GLOB candidates use RTRIM prefix range' => ['nextCandidateRowids', [1, 2, 3, 4, 5, 13, 10]],
    'current LIKE residual rejects non cache prefixes after full scan' => ['currentResidualRejectedRowids', [6, 8, 7, 9, 11]],
    'next GLOB residual rejects tab and NBSP when exact prefix wildcard still matches all prefix' => ['nextResidualRejectedRowids', []],
    'current LIKE rowids include space tab NBSP and unicode prefixes' => ['currentRowids', [1, 2, 3, 4, 5, 10]],
    'next GLOB rowids include repaired and new cache rows' => ['nextRowids', [1, 2, 3, 4, 5, 13, 10]],
    'retained rowids' => ['retainedRowids', [1, 2, 3, 4, 5, 10]],
    'entered rowids' => ['enteredRowids', [13]],
    'exited rowids empty' => ['exitedRowids', []],
    'changed text rowids' => ['changedTextRowids', [3, 5, 7, 9]],
    'changed encoding rowids' => ['changedEncodingRowids', [1, 7, 9]],
    'changed bytes rowids' => ['changedBytesRowids', [1, 3, 5, 7, 9]],
    'decoded uppercase row sorts first' => ['currentDecodedRows.0.text', 'Module_Cache'],
    'decoded row one text' => ['currentDecodedRows.1.text', 'module_cache'],
    'decoded row two rtrim trims only ASCII space' => ['currentDecodedRows.2.rtrimKey', 'module_cache'],
    'decoded tab rtrim key keeps tab' => ['currentDecodedRows.4.rtrimKey', "module_cache\t"],
    'decoded NBSP rtrim key keeps NBSP' => ['currentDecodedRows.6.rtrimKey', 'module_cache' . "\u{00a0}"],
    'current row one encoding' => ['currentDecodedRows.1.encoding', 'UTF-16LE'],
    'next row one encoding' => ['nextDecodedRows.1.encoding', 'UTF-16BE'],
    'current row one bytes' => ['currentDecodedRows.1.bytesHex', '6d006f00640075006c0065005f0063006100630068006500'],
    'next row one bytes' => ['nextDecodedRows.1.bytesHex', '006d006f00640075006c0065005f00630061006300680065'],
    'current malformed rowids' => ['currentMalformedRowids', [12]],
    'next malformed rowids' => ['nextMalformedRowids', [14]],
    'current malformed error' => ['currentErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'source invalidation first' => ['invalidationReasons.0', 'source-name'],
    'operator invalidation second' => ['invalidationReasons.1', 'operator-switch'],
    'pattern invalidation third' => ['invalidationReasons.2', 'pattern-range'],
    'full scan invalidation fourth' => ['invalidationReasons.3', 'full-scan-rtrim-like'],
    'malformed invalidation fifth' => ['invalidationReasons.4', 'malformed-text'],
    'candidate rowset invalidation near end' => ['invalidationReasons.8', 'candidate-rowset'],
    'matched rowset invalidation last' => ['invalidationReasons.9', 'matched-rowset'],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-collation-key'],
    'dependency residual' => ['dependencies.2', 'sqlite-like-glob-residual-match'],
    'dependency marker' => ['dependencies.3', 'sqlite-current-source-nextoneTwoEight'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 rtrim like glob current source nextOneTwoEight ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 rtrim like glob current source nextOneTwoEight exact GLOB rejects space padded residual peers'] = static function (TestRunner $t) use ($plan): void {
    $exact = $plan('module_cache%', 'module_cache', 'LIKE', 'GLOB', null);
    $t->same([2, 4, 5, 13, 10], $exact['nextResidualRejectedRowids']);
};

$tests['utf16 rtrim like glob current source nextOneTwoEight leading GLOB wildcard has no usable next range'] = static function (TestRunner $t) use ($plan): void {
    $wild = $plan('module_cache%', '*cache', 'LIKE', 'GLOB', null);
    $t->same(null, $wild['nextRange']);
    $t->same([], $wild['nextCandidateRowids']);
    $t->same(true, in_array('unusable-range', $wild['invalidationReasons'], true));
};

$tests['utf16 rtrim like glob current source nextOneTwoEight stable same GLOB cursor reusable'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'module_cache', 'UTF-16LE'), $row(2, 'module_cache ', 'UTF-16BE')];
    $plan = SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan::keyValueRowKeyOperatorSwitchPlan(
        $rows,
        $rows,
        'module_cache*',
        'module_cache*',
        'GLOB',
        'GLOB',
        null,
        null,
        true,
        'stable',
        'stable',
    );
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['utf16 rtrim like glob current source nextOneTwoEight stable same LIKE still records full scan'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'module_cache', 'UTF-16LE'), $row(2, 'module_cache ', 'UTF-16BE')];
    $plan = SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan::keyValueRowKeyOperatorSwitchPlan(
        $rows,
        $rows,
        'module_cache%',
        'module_cache%',
        'LIKE',
        'LIKE',
        null,
        null,
        true,
        'stable',
        'stable',
    );
    $t->same(['full-scan-rtrim-like'], $plan['invalidationReasons']);
    $t->same(false, $plan['currentIndexUsable']);
};

$tests['utf16 rtrim like glob current source nextOneTwoEight rejects invalid current operator'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('module%', 'module*', 'REGEXP', 'GLOB'));
};

$tests['utf16 rtrim like glob current source nextOneTwoEight rejects invalid next operator'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('module%', 'module*', 'LIKE', 'MATCH'));
};

$tests['utf16 rtrim like glob current source nextOneTwoEight rejects invalid LIKE escape length'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('module!!%', 'module*', 'LIKE', 'GLOB', '!!'));
};

$tests['utf16 rtrim like glob current source nextOneTwoEight rejects missing setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan::keyValueRowKeyOperatorSwitchPlan([['key_name_bytes' => 'p', 'text_encoding' => 1]], $nextRows, 'p%', 'p*'));
};

$tests['utf16 rtrim like glob current source nextOneTwoEight rejects missing bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan::keyValueRowKeyOperatorSwitchPlan([['setting_id' => 1, 'text_encoding' => 1]], $nextRows, 'p%', 'p*'));
};

$tests['utf16 rtrim like glob current source nextOneTwoEight rejects missing encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan::keyValueRowKeyOperatorSwitchPlan([['setting_id' => 1, 'key_name_bytes' => 'p']], $nextRows, 'p%', 'p*'));
};

return $tests;
