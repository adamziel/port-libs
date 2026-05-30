<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc231 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row231 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc231($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad231 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current231 = [
    $row231(1, 'PLUGIN_CAFÉ_MAIN ', 'UTF-16LE'),
    $row231(2, 'plugin_café_main', 'UTF-16BE'),
    $row231(3, 'plugin_cafÉ_aux', 'UTF-8'),
    $row231(4, 'plugin_cafÉ_Μeta', 'UTF-16LE'),
    $row231(5, 'plugin_cafÉ_μeta', 'UTF-16BE'),
    $row231(6, 'plugin_cafÉ', 'UTF-16LE'),
    $row231(7, 'plugin_cafe_plain', 'UTF-16BE'),
    $row231(8, 'theme_cafÉ_main', 'UTF-16LE'),
    $bad231(9, "\x00\xd8", 2),
];
$nextTwoThreeOne = [
    $row231(1, 'plugin_café_main ', 'UTF-16BE'),
    $row231(2, 'plugin_cafÉ_main', 'UTF-16LE'),
    $row231(3, 'PLUGIN_CAFÉ_AUX ', 'UTF-8'),
    $row231(4, 'plugin_cafÉ_μeta', 'UTF-16BE'),
    $row231(5, 'plugin_cafÉ_Μeta', 'UTF-16LE'),
    $row231(6, 'plugin_cafÉ  ', 'UTF-16BE'),
    $row231(10, 'plugin_cafÉ_extra', 'UTF-16LE'),
    $bad231(11, "\x00\xd8", 2),
];

$plan231 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin_cafÉ%',
    ?string $escape = null,
    string $currentSource = 'main.wp_options@230',
    string $nextSource = 'main.wp_options@231',
    int $currentCookie = 230,
    int $nextCookie = 231,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiOnlyNocasePlan(
    $current ?? $current231,
    $next ?? $nextTwoThreeOne,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt231 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases231 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoThreeOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? /* ASCII-only NOCASE boundary */'],
    'pattern' => ['pattern', 'plugin_cafÉ%'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@230'],
    'next source' => ['nextSource', 'main.wp_options@231'],
    'current cookie' => ['currentSchemaCookie', 230],
    'next cookie' => ['nextSchemaCookie', 231],
    'prefix' => ['prefix', 'plugin'],
    'range lower' => ['rangeLowerInclusive', 'plugin'],
    'range upper' => ['rangeUpperBound', 'plugio'],
    'index usable' => ['indexUsable', true],
    'current candidates' => ['currentCandidateRowids', [7, 6, 3, 1, 4, 5, 2]],
    'next candidates' => ['nextCandidateRowids', [6, 3, 10, 2, 5, 4, 1]],
    'current matched' => ['currentMatchedRowids', [6, 3, 1, 4, 5]],
    'next matched' => ['nextMatchedRowids', [6, 3, 10, 2, 5, 4]],
    'matched retained' => ['matchedRetainedRowids', [3, 4, 5, 6]],
    'matched exited' => ['matchedExitedRowids', [1]],
    'matched entered' => ['matchedEnteredRowids', [2, 10]],
    'current false positives' => ['currentFalsePositiveRowids', [7, 2]],
    'next false positives' => ['nextFalsePositiveRowids', [1]],
    'current non ascii variants' => ['currentNonAsciiCaseVariantRowids', [1, 2, 3, 4, 5, 6, 8]],
    'next non ascii variants' => ['nextNonAsciiCaseVariantRowids', [1, 2, 3, 4, 5, 6, 10]],
    'current ascii folded' => ['currentAsciiFoldedRowids', [1]],
    'next ascii folded' => ['nextAsciiFoldedRowids', [3]],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current row one rtrim' => ['currentRtrimTexts.1', 'PLUGIN_CAFÉ_MAIN'],
    'next row six rtrim' => ['nextRtrimTexts.6', 'plugin_cafÉ'],
    'current row one key' => ['currentNocaseKeys.1', 'plugin_cafÉ_main'],
    'current row two key preserves lower e acute' => ['currentNocaseKeys.2', 'plugin_café_main'],
    'next row two key preserves upper e acute' => ['nextNocaseKeys.2', 'plugin_cafÉ_main'],
    'current row four upper greek class' => ['currentNonAsciiCaseClasses.4', 'upper-non-ascii-case'],
    'current row five lower greek class' => ['currentNonAsciiCaseClasses.5', 'mixed-non-ascii-case'],
    'next row one lower e class' => ['nextNonAsciiCaseClasses.1', 'lower-non-ascii-case'],
    'current row two residual false' => ['currentResidualMatches.2', false],
    'next row two residual true' => ['nextResidualMatches.2', true],
    'changed text' => ['changedTextRowids', [1, 2, 3, 4, 5, 6, 7, 8, 10]],
    'changed rtrim' => ['changedRtrimRowids', [1, 2, 3, 4, 5, 7, 8, 10]],
    'changed key' => ['changedNocaseKeyRowids', [1, 2, 4, 5, 7, 8, 10]],
    'changed non ascii class' => ['changedNonAsciiCaseClassRowids', [1, 2, 4, 5, 7, 8, 10]],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'ascii fold flag' => ['asciiLettersFoldUnderNocase', true],
    'non ascii no fold flag' => ['nonAsciiLettersDoNotFoldUnderNocase', true],
    'rtrim flag' => ['rtrimTrimsOnlyAsciiSpace', true],
    'residual flag' => ['likeResidualRunsAfterRtrim', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency ascii nocase' => ['dependencies.3', 'sqlite-ascii-only-nocase'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoThreeOne'],
];

foreach ($cases231 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoThreeOne ' . $name] = static function (TestRunner $t) use ($plan231, $valueAt231, $path, $expected): void {
        $t->same($expected, $valueAt231($plan231(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoThreeOne invalidation reason order'] = static function (TestRunner $t) use ($plan231): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'non-ascii-case-class',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'ascii-only-nocase-boundary',
    ], $plan231()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeOne stable ascii folded cursor is reusable'] = static function (TestRunner $t) use ($row231): void {
    $rows = [
        $row231(1, 'PLUGIN_CAFÉ_MAIN ', 'UTF-16LE'),
        $row231(2, 'plugin_cafÉ_aux', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiOnlyNocasePlan(
        $rows,
        $rows,
        'plugin_cafÉ%',
        null,
        'stable',
        'stable',
        231,
        231,
    );

    $t->same([2, 1], $result['currentMatchedRowids']);
    $t->same([1], $result['currentAsciiFoldedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeOne lower e acute does not match upper e acute pattern'] = static function (TestRunner $t) use ($row231): void {
    $rows = [
        $row231(1, 'plugin_café_main', 'UTF-16LE'),
        $row231(2, 'plugin_cafÉ_main', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiOnlyNocasePlan(
        $rows,
        $rows,
        'plugin_cafÉ%',
        null,
        'stable',
        'stable',
        231,
        231,
    );

    $t->same([2], $result['currentMatchedRowids']);
    $t->same([1], $result['currentFalsePositiveRowids']);
    $t->same('lower-non-ascii-case', $result['currentNonAsciiCaseClasses'][1]);
    $t->same('upper-non-ascii-case', $result['currentNonAsciiCaseClasses'][2]);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeOne ascii prefix still folds before non ascii suffix'] = static function (TestRunner $t) use ($row231): void {
    $rows = [
        $row231(1, 'PLUGIN_CAFÉ_MAIN', 'UTF-16LE'),
        $row231(2, 'plugin_cafÉ_main', 'UTF-16BE'),
        $row231(3, 'Plugin_CafÉ_extra', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiOnlyNocasePlan(
        $rows,
        $rows,
        'plugin_cafÉ%',
        null,
        'stable',
        'stable',
        231,
        231,
    );

    $t->same([3, 1, 2], $result['currentMatchedRowids']);
    $t->same([1, 3], $result['currentAsciiFoldedRowids']);
    $t->same('plugin_cafÉ_main', $result['currentNocaseKeys'][1]);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeOne rejects malformed row shape'] = static function (TestRunner $t) use ($enc231): void {
    $rows = [['option_id' => 1, 'option_name_bytes' => $enc231('plugin_cafÉ_main', 'UTF-16LE')]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiOnlyNocasePlan($rows, $rows));
};

return $tests;
