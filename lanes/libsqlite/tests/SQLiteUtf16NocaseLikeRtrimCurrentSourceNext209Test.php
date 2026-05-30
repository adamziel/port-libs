<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc209 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row209 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc209($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad209 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current209 = [
    $row209(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row209(2, "plugin_cache\t", 'UTF-16BE'),
    $row209(3, "plugin_cache\xc2\xa0", 'UTF-16LE'),
    $row209(4, 'plugin_extra', 'UTF-8'),
    $row209(5, "\xc4\xb0nsert_plugin", 'UTF-16BE'),
    $row209(6, 'theme_plugin', 'UTF-16LE'),
    $bad209(7, "\x00\xd8", 2),
];
$nextTwoZeroNine = [
    $row209(1, 'Plugin_Cache', 'UTF-16BE'),
    $row209(2, "plugin_cache\t", 'UTF-16LE'),
    $row209(3, "plugin_cache\xc2\xa0  ", 'UTF-16BE'),
    $row209(4, 'plugin_extra ', 'UTF-8'),
    $row209(5, "\xc4\xb0nsert_plugin", 'UTF-16LE'),
    $row209(8, "PLUGIN_OPTION\xc2\xa0", 'UTF-16BE'),
    $bad209(9, "\x00\xd8", 2),
];

$plan209 = static fn (?array $current = null, ?array $next = null, string $pattern = 'plugin%'): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiSpaceRtrimPlan(
    $current ?? $current209,
    $next ?? $nextTwoZeroNine,
    $pattern,
);

$valueAt209 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases209 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroNine'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* ASCII-space RTRIM only */'],
    'pattern' => ['pattern', 'plugin%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@208'],
    'next source' => ['nextSource', 'main.wp_options@209'],
    'current cookie' => ['currentSchemaCookie', 208],
    'next cookie' => ['nextSchemaCookie', 209],
    'prefix' => ['prefix', 'plugin'],
    'range lower' => ['rangeLowerInclusive', 'plugin'],
    'range upper' => ['rangeUpperBound', 'plugio'],
    'current index' => ['currentIndexUsable', true],
    'next index' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 4, 8]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 4]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 4, 8]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [8]],
    'current row one rtrim' => ['currentRtrimTexts.1', 'Plugin_Cache'],
    'current row two tab kept' => ['currentRtrimTexts.2', "plugin_cache\t"],
    'current row three nbsp kept' => ['currentRtrimTexts.3', "plugin_cache\xc2\xa0"],
    'next row three space trimmed not nbsp' => ['nextRtrimTexts.3', "plugin_cache\xc2\xa0"],
    'next row eight nbsp kept' => ['nextRtrimTexts.8', "PLUGIN_OPTION\xc2\xa0"],
    'current row one nocase' => ['currentNocaseKeys.1', 'plugin_cache'],
    'next row eight nocase' => ['nextNocaseKeys.8', "plugin_option\xc2\xa0"],
    'current ascii trimmed' => ['currentAsciiSpaceTrimmedRowids', [1]],
    'next ascii trimmed' => ['nextAsciiSpaceTrimmedRowids', [3, 4]],
    'current whitespace preserved' => ['currentNonAsciiWhitespacePreservedRowids', [2, 3]],
    'next whitespace preserved' => ['nextNonAsciiWhitespacePreservedRowids', [2, 3, 8]],
    'current tab preserved' => ['currentTabPreservedRowids', [2]],
    'next tab preserved' => ['nextTabPreservedRowids', [2]],
    'current nbsp preserved' => ['currentNbspPreservedRowids', [3]],
    'next nbsp preserved' => ['nextNbspPreservedRowids', [3, 8]],
    'current unicode variant rowids' => ['currentUnicodeCaseVariantRowids', []],
    'next unicode variant rowids' => ['nextUnicodeCaseVariantRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [6, 5]],
    'next excluded' => ['nextExcludedDecodedRowids', [5]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current malformed' => ['currentMalformedRowids', [7]],
    'next malformed' => ['nextMalformedRowids', [9]],
    'current error' => ['currentErrors.7', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'rtrim ascii only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'tab suffix preserved flag' => ['tabSuffixPreservedByRtrim', true],
    'nbsp suffix preserved flag' => ['nbspSuffixPreservedByRtrim', true],
    'nocase ascii flag' => ['nocaseFoldsAsciiOnly', true],
    'unicode residual flag' => ['unicodeCaseVariantsRequireResidualCheck', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-ascii-space-only'],
    'dependency nocase' => ['dependencies.3', 'sqlite-nocase-ascii-only'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoZeroNine'],
];

foreach ($cases209 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroNine ' . $name] = static function (TestRunner $t) use ($plan209, $valueAt209, $path, $expected): void {
        $t->same($expected, $valueAt209($plan209(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroNine invalidation reasons include suffix diagnostics'] = static function (TestRunner $t) use ($plan209): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'malformed-text',
        'matched-rowset',
        'ascii-space-rtrim-rowset',
        'non-ascii-whitespace-rtrim-preserved',
    ], $plan209()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroNine stable ascii space trim can reuse cursor'] = static function (TestRunner $t) use ($row209): void {
    $rows = [
        $row209(1, 'Plugin_Cache  ', 'UTF-16LE'),
        $row209(2, "plugin_cache\t", 'UTF-16BE'),
        $row209(3, "plugin_cache\xc2\xa0", 'UTF-8'),
        $row209(4, 'theme_plugin', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiSpaceRtrimPlan(
        $rows,
        $rows,
        'plugin%',
        '!',
        'stable',
        'stable',
        209,
        209,
    );

    $t->same([1, 2, 3], $result['currentMatchedRowids']);
    $t->same([1, 2, 3], $result['nextMatchedRowids']);
    $t->same([1], $result['currentAsciiSpaceTrimmedRowids']);
    $t->same([2, 3], $result['nextNonAsciiWhitespacePreservedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroNine unicode case variants are not nocase folded'] = static function (TestRunner $t) use ($row209): void {
    $rows = [
        $row209(10, "\xc4\xb0nsert_plugin", 'UTF-16LE'),
        $row209(11, 'INSERT_plugin', 'UTF-16BE'),
        $row209(12, 'insert_plugin', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiSpaceRtrimPlan(
        $rows,
        $rows,
        'insert%',
        '!',
        'stable',
        'stable',
        209,
        209,
    );

    $t->same([11, 12], $result['currentMatchedRowids']);
    $t->same([10], $result['currentUnicodeCaseVariantRowids']);
    $t->same("\xc4\xb0nsert_plugin", $result['currentUnicodeCaseVariantTexts'][10]);
    $t->same(['unicode-case-not-folded'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroNine rejects invalid row shape'] = static function (TestRunner $t) use ($row209): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiSpaceRtrimPlan(
        [['option_id' => '1', 'option_name_bytes' => 'plugin', 'text_encoding' => 1]],
        [$row209(1, 'plugin', 'UTF-8')],
    ));
};

return $tests;
