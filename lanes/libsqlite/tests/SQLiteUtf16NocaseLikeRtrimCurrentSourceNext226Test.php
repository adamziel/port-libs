<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc226 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row226 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc226($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad226 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$decomposed226 = "e\xcc\x81";
$composed226 = "\xc3\xa9";
$currentRows226 = [
    $row226(1, 'module_caf' . $composed226, 'UTF-16LE'),
    $row226(2, 'Module_Caf' . $composed226 . '  ', 'UTF-16BE'),
    $row226(3, 'module_cafe' . "\xcc\x81", 'UTF-16LE'),
    $row226(4, 'module_cafe' . "\xcc\x81" . '  ', 'UTF-16BE'),
    $row226(5, 'module_cafe', 'UTF-8'),
    $row226(6, 'module_cafE', 'UTF-16LE'),
    $row226(7, 'module_caf' . $composed226 . 'x', 'UTF-16BE'),
    $row226(8, 'layout_caf' . $composed226, 'UTF-16LE'),
    $bad226(9, "\x00\xd8", 2),
    $row226(10, 'MODULE_CAF' . $composed226, 'UTF-16BE'),
];
$nextRows226 = [
    $row226(1, 'module_caf' . $decomposed226, 'UTF-16BE'),
    $row226(2, 'Module_Caf' . $composed226, 'UTF-16LE'),
    $row226(3, 'module_caf' . $composed226, 'UTF-16BE'),
    $row226(4, 'module_cafe' . "\xcc\x81" . "\t", 'UTF-16LE'),
    $row226(5, 'module_cafe' . "\xcc\x81", 'UTF-8'),
    $row226(6, 'module_cafE', 'UTF-16BE'),
    $row226(10, 'MODULE_CAF' . $composed226, 'UTF-16LE'),
    $row226(11, 'module_caf' . $composed226 . '  ', 'UTF-16LE'),
    $bad226(12, "\x00\xd8", 2),
];

$plan226 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'module_caf_',
    ?string $escape = null,
    string $currentSource = 'main.app_settings@225',
    string $nextSource = 'main.app_settings@226',
    int $currentCookie = 225,
    int $nextCookie = 226,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCombiningMarkPlan(
    $current ?? $currentRows226,
    $next ?? $nextRows226,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt226 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases226 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoTwoSix'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ? /* combining mark normalization boundary */'],
    'pattern' => ['pattern', 'module_caf_'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.app_settings@225'],
    'next source' => ['nextSource', 'main.app_settings@226'],
    'current cookie' => ['currentSchemaCookie', 225],
    'next cookie' => ['nextSchemaCookie', 226],
    'prefix' => ['prefix', 'module'],
    'range lower' => ['rangeLowerInclusive', 'module'],
    'range upper' => ['rangeUpperBound', 'modulf'],
    'index usable' => ['indexUsable', true],
    'current candidates' => ['currentCandidateRowids', [5, 6, 3, 4, 1, 2, 10, 7]],
    'next candidates' => ['nextCandidateRowids', [6, 1, 5, 4, 2, 3, 10, 11]],
    'current matched' => ['currentMatchedRowids', [5, 6, 1, 2, 10]],
    'next matched' => ['nextMatchedRowids', [6, 2, 3, 10, 11]],
    'matched retained' => ['matchedRetainedRowids', [2, 6, 10]],
    'matched exited' => ['matchedExitedRowids', [1, 5]],
    'matched entered' => ['matchedEnteredRowids', [3, 11]],
    'current false positives' => ['currentFalsePositiveRowids', [3, 4, 7]],
    'next false positives' => ['nextFalsePositiveRowids', [1, 5, 4]],
    'current combining rowids' => ['currentCombiningMarkRowids', [3, 4]],
    'next combining rowids' => ['nextCombiningMarkRowids', [1, 4, 5]],
    'current normalization traps' => ['currentNormalizationTrapRowids', [3, 4]],
    'next normalization traps' => ['nextNormalizationTrapRowids', [1, 4, 5]],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current row one text' => ['currentRtrimTexts.1', 'module_caf' . $composed226],
    'next row one text' => ['nextRtrimTexts.1', 'module_caf' . $decomposed226],
    'current row two rtrim' => ['currentRtrimTexts.2', 'Module_Caf' . $composed226],
    'next row four tab preserved' => ['nextRtrimTexts.4', 'module_cafe' . "\xcc\x81" . "\t"],
    'current row ten nocase' => ['currentNocaseKeys.10', 'module_caf' . $composed226],
    'next row ten nocase' => ['nextNocaseKeys.10', 'module_caf' . $composed226],
    'current row one chars' => ['currentCharacterCounts.1', 11],
    'next row one chars' => ['nextCharacterCounts.1', 12],
    'current row three combining count' => ['currentCombiningMarkCounts.3', 1],
    'next row one combining count' => ['nextCombiningMarkCounts.1', 1],
    'current row one form' => ['currentNormalizationForms.1', 'composed-latin-small-e-acute'],
    'next row one form' => ['nextNormalizationForms.1', 'decomposed-combining-acute'],
    'current row five form' => ['currentNormalizationForms.5', 'plain'],
    'current row three residual' => ['currentResidualMatches.3', false],
    'next row one residual' => ['nextResidualMatches.1', false],
    'next row eleven residual' => ['nextResidualMatches.11', true],
    'changed text' => ['changedTextRowids', [1, 2, 3, 4, 5, 7, 8, 11]],
    'changed rtrim' => ['changedRtrimRowids', [1, 3, 4, 5, 7, 8, 11]],
    'changed nocase' => ['changedNocaseKeyRowids', [1, 3, 4, 5, 7, 8, 11]],
    'changed normalization' => ['changedNormalizationRowids', [1, 3, 5, 7, 8, 11]],
    'changed combining' => ['changedCombiningMarkRowids', [1, 3, 5, 7, 8, 11]],
    'changed characters' => ['changedCharacterCountRowids', [1, 3, 4, 5, 7, 8, 11]],
    'changed residual' => ['changedResidualRowids', []],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'underscore codepoint' => ['likeUnderscoreConsumesUnicodeCodepoint', true],
    'combining separate' => ['combiningMarkRemainsSeparateLikeCharacter', true],
    'no normalization' => ['unicodeNormalizationIsNotApplied', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency combining' => ['dependencies.3', 'sqlite-combining-mark-like-character'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoTwoSix'],
];

foreach ($cases226 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoTwoSix ' . $name] = static function (TestRunner $t) use ($plan226, $valueAt226, $path, $expected): void {
        $t->same($expected, $valueAt226($plan226(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoTwoSix invalidation reason order'] = static function (TestRunner $t) use ($plan226): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'unicode-normalization-form',
        'combining-mark-count',
        'like-character-count',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'unicode-normalization-not-applied',
    ], $plan226()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoSix stable composed rows are reusable'] = static function (TestRunner $t) use ($row226, $composed226): void {
    $rows = [
        $row226(1, 'module_caf' . $composed226, 'UTF-16LE'),
        $row226(2, 'Module_Caf' . $composed226 . '  ', 'UTF-16BE'),
        $row226(3, 'module_cafe', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCombiningMarkPlan(
        $rows,
        $rows,
        'module_caf_',
        null,
        'stable',
        'stable',
        226,
        226,
    );

    $t->same([3, 1, 2], $result['currentMatchedRowids']);
    $t->same([3, 1, 2], $result['nextMatchedRowids']);
    $t->same([], $result['currentNormalizationTrapRowids']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same([], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoSix decomposed text needs two underscores'] = static function (TestRunner $t) use ($row226, $decomposed226, $composed226): void {
    $rows = [
        $row226(1, 'module_caf' . $decomposed226, 'UTF-16LE'),
        $row226(2, 'module_caf' . $composed226, 'UTF-16BE'),
        $row226(3, 'module_cafex', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCombiningMarkPlan(
        $rows,
        $rows,
        'module_caf__',
        null,
        'stable',
        'stable',
        226,
        226,
    );

    $t->same([3, 1, 2], $result['currentCandidateRowids']);
    $t->same([3, 1], $result['currentMatchedRowids']);
    $t->same([2], $result['currentFalsePositiveRowids']);
    $t->same(12, $result['currentCharacterCounts'][1]);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoSix ascii nocase does not fold composed accents'] = static function (TestRunner $t) use ($row226, $composed226): void {
    $rows = [
        $row226(1, 'MODULE_CAF' . $composed226, 'UTF-16LE'),
        $row226(2, 'module_caf' . $composed226, 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCombiningMarkPlan(
        $rows,
        $rows,
        'module_caf_',
        null,
        'stable',
        'stable',
        226,
        226,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same('module_caf' . $composed226, $result['currentNocaseKeys'][1]);
    $t->same('module_caf' . $composed226, $result['currentNocaseKeys'][2]);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoSix rejects malformed row shape'] = static function (TestRunner $t) use ($enc226): void {
    $rows = [['setting_id' => 1, 'key_name_bytes' => $enc226('module_cafe', 'UTF-16LE')]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCombiningMarkPlan($rows, $rows));
};

return $tests;
