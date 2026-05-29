<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteRtrimGlobNocaseAffinityCurrentSourceNextPlan;

$tests = [];

$encodingCode = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};

$row = static function (int $id, string $name, string $value, string $nameEncoding, string $valueEncoding) use ($encodingCode): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $nameEncoding),
        'option_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $valueEncoding),
        'name_text_encoding' => $encodingCode($nameEncoding),
        'value_text_encoding' => $encodingCode($valueEncoding),
    ];
};

$bad = static fn (int $id, string $nameBytes, int $nameEncoding, string $valueBytes, int $valueEncoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $nameBytes,
    'option_value_bytes' => $valueBytes,
    'name_text_encoding' => $nameEncoding,
    'value_text_encoding' => $valueEncoding,
];

$currentRows = [
    $row(1, 'plugin_cache', '10', 'UTF-8', 'UTF-8'),
    $row(2, 'Plugin_Cache', '11', 'UTF-16LE', 'UTF-16BE'),
    $row(3, 'plugin-cache', '12', 'UTF-16BE', 'UTF-8'),
    $row(4, 'plugin-cache ', '12.5', 'UTF-16LE', 'UTF-16LE'),
    $row(5, 'plugin:cache', '13', 'UTF-8', 'UTF-16BE'),
    $row(6, 'plugin/cache', '14', 'UTF-16BE', 'UTF-16LE'),
    $row(7, 'plugin9cache', '15', 'UTF-16LE', 'UTF-8'),
    $row(8, 'plugin_cache_extra', '8', 'UTF-8', 'UTF-8'),
    $row(9, 'plugin-cache-new', '10e0', 'UTF-16BE', 'UTF-16BE'),
    $row(10, 'plugin-cache-OLD', 'fast', 'UTF-8', 'UTF-16LE'),
    $row(11, 'theme-cache', '12', 'UTF-8', 'UTF-8'),
    $bad(12, "\x3d\xd8", 2, SQLiteEncodingCollationSourceCursor::encodeText('12', 'UTF-8'), 1),
];

$nextRows = [
    $row(1, 'plugin_cache ', '10.0', 'UTF-16BE', 'UTF-16LE'),
    $row(2, 'Plugin_Cache', '11', 'UTF-16BE', 'UTF-16BE'),
    $row(3, 'plugin-cache', '12', 'UTF-16BE', 'UTF-8'),
    $row(4, 'plugin-cache', '13', 'UTF-16LE', 'UTF-16LE'),
    $row(5, 'plugin:cache', '13', 'UTF-8', 'UTF-16BE'),
    $row(6, 'plugin/cache', '9', 'UTF-16BE', 'UTF-16LE'),
    $row(7, 'plugin9cache', '15', 'UTF-16LE', 'UTF-8'),
    $row(8, 'plugin_cache_extra', '10', 'UTF-8', 'UTF-8'),
    $row(9, 'plugin-cache-new', '10e0', 'UTF-16BE', 'UTF-16BE'),
    $row(10, 'plugin-cache-OLD', '12', 'UTF-8', 'UTF-16LE'),
    $row(13, 'pluginXcache', '12', 'UTF-16LE', 'UTF-8'),
    $row(14, 'plugin-cache-fresh', '12', 'UTF-16BE', 'UTF-8'),
    $bad(15, SQLiteEncodingCollationSourceCursor::encodeText('plugin-cache-bad-value', 'UTF-8'), 1, "\x3d\xd8", 2),
];

$plan = static fn (
    string $pattern = 'plugin[-_]cache*',
    ?array $current = null,
    ?array $next = null,
    int|float|string $minimum = 10,
    int|float|string $maximum = 14,
    string $currentSource = 'main.wp_options@148',
    string $nextSource = 'main.wp_options@149',
    int $currentSchemaCookie = 148,
    int $nextSchemaCookie = 149,
    int $currentCollationVersion = 14,
    int $nextCollationVersion = 15,
): array => SQLiteRtrimGlobNocaseAffinityCurrentSourceNextPlan::wordpressOptionNameValuePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $minimum,
    $maximum,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
    $currentCollationVersion,
    $nextCollationVersion,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'rtrim-glob-nocase-affinity-current-source-next149-ready'],
    'operator' => ['operator', 'GLOB'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE GLOB ? AND option_value NUMERIC BETWEEN ? AND ?'],
    'name collation' => ['nameCollation', 'RTRIM+NOCASE index key'],
    'residual collation' => ['residualCollation', 'BINARY bytewise GLOB'],
    'value affinity' => ['valueAffinity', 'NUMERIC'],
    'range lower' => ['range.lowerInclusive', 'plugin'],
    'range upper' => ['range.upperBound', 'plugio'],
    'range lower before class' => ['rangeLowerComesBeforeFirstGlobClass', 'plugin'],
    'index usable' => ['indexUsable', true],
    'class count' => ['globCharacterClassCount', 1],
    'class raw' => ['globCharacterClasses.0.raw', '[-_]'],
    'class negated false' => ['globCharacterClasses.0.negated', false],
    'class literals' => ['globCharacterClasses.0.characters', ['-', '_']],
    'class ranges' => ['globCharacterClasses.0.ranges', []],
    'has negated class false' => ['hasNegatedGlobClass', false],
    'residual keeps case classes' => ['globResidualKeepsCaseSensitiveClasses', true],
    'nocase index only candidates' => ['nocaseRtrimIndexCanOnlyChooseCandidates', true],
    'numeric minimum' => ['numericRange.minimum', 10.0],
    'numeric maximum' => ['numericRange.maximum', 14.0],
    'current candidate rowids' => ['currentCandidateRowids', [3, 4, 9, 10, 6, 7, 5, 1, 2, 8]],
    'next candidate rowids' => ['nextCandidateRowids', [3, 4, 14, 9, 10, 6, 7, 5, 1, 2, 8, 13]],
    'current matched rowids' => ['currentMatchedRowids', [3, 4, 9, 10, 1, 8]],
    'next matched rowids' => ['nextMatchedRowids', [3, 4, 14, 9, 10, 1, 8]],
    'current affinity matched rowids' => ['currentAffinityMatchedRowids', [3, 4, 9, 1]],
    'next affinity matched rowids' => ['nextAffinityMatchedRowids', [3, 4, 14, 9, 10, 1, 8]],
    'current false positives' => ['currentFalsePositiveRowids', [6, 7, 5, 2]],
    'current affinity rejected' => ['currentAffinityRejectedRowids', [10, 8]],
    'next affinity rejected' => ['nextAffinityRejectedRowids', []],
    'retained affinity matched' => ['retainedAffinityMatchedRowids', [3, 4, 9, 1]],
    'entered affinity matched' => ['enteredAffinityMatchedRowids', [14, 10, 8]],
    'exited affinity matched' => ['exitedAffinityMatchedRowids', []],
    'row two comparison key folds' => ['currentComparisonKeys.2', 'plugin_cache'],
    'row four comparison key trims' => ['currentComparisonKeys.4', 'plugin-cache'],
    'row one next text keeps spaces' => ['nextNameTexts.1', 'plugin_cache '],
    'row one next numeric value' => ['nextNumericValues.1', 10.0],
    'row nine exponent value' => ['currentNumericValues.9', 10.0],
    'row ten non numeric current' => ['currentNumericValues.10', null],
    'row ten next numeric' => ['nextNumericValues.10', 12],
    'row one current encoding' => ['currentNameEncodings.1', 'UTF-8'],
    'row one next encoding' => ['nextNameEncodings.1', 'UTF-16BE'],
    'row twelve malformed current' => ['currentMalformedRowids', [12]],
    'row fifteen malformed next' => ['nextMalformedRowids', [15]],
    'retained name encoding changed' => ['retainedNameEncodingChangedRowids', [1, 2]],
    'retained name bytes changed' => ['retainedNameBytesChangedRowids', [1, 2, 4]],
    'retained value changed' => ['retainedValueChangedRowids', [1, 4, 6, 8, 10]],
    'retained value encoding changed' => ['retainedValueEncodingChangedRowids', [1]],
    'retained value bytes changed' => ['retainedValueBytesChangedRowids', [1, 4, 6, 8, 10]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason collation version' => ['invalidationReasons.2', 'collation-version'],
    'reason malformed' => ['invalidationReasons.3', 'malformed-text'],
    'reason class residual' => ['invalidationReasons.12', 'glob-character-class-residual'],
    'dependency bytewise class' => ['dependencies.2', 'sqlite-glob-character-class-bytewise-residual'],
    'dependency current source' => ['dependencies.5', 'sqlite-current-source-next149'],
    'dependency closure' => ['dependency_closure', 'no new support component needed; reuses UTF text source decoding, RTRIM+NOCASE comparison keys, bytewise GLOB character-class residuals, and numeric affinity coercion'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['rtrim glob nocase affinity current source next149 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['rtrim glob nocase affinity current source next149 stable rows still keep class residual invalidation'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin-cache', '10', 'UTF-8', 'UTF-8'), $row(2, 'Plugin_Cache', '11', 'UTF-16LE', 'UTF-16BE')];
    $result = SQLiteRtrimGlobNocaseAffinityCurrentSourceNextPlan::wordpressOptionNameValuePlan($rows, $rows, 'plugin[-_]cache', 9, 12, 'stable', 'stable', 1, 1, 1, 1);
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same(['glob-character-class-residual'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['rtrim glob nocase affinity current source next149 negated class records bytewise exclusion'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin-cache', '10', 'UTF-8', 'UTF-8'), $row(2, 'plugin_cache', '11', 'UTF-8', 'UTF-8'), $row(3, 'pluginXcache', '12', 'UTF-8', 'UTF-8')];
    $result = SQLiteRtrimGlobNocaseAffinityCurrentSourceNextPlan::wordpressOptionNameValuePlan($rows, $rows, 'plugin[^_]cache', 9, 12, 'stable', 'stable', 1, 1, 1, 1);
    $t->same(true, $result['hasNegatedGlobClass']);
    $t->same('[^_]', $result['globCharacterClasses'][0]['raw']);
    $t->same([1, 3], $result['currentMatchedRowids']);
    $t->same([1, 3], $result['currentAffinityMatchedRowids']);
};

$tests['rtrim glob nocase affinity current source next149 range class records unicode ranges'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin-acache', '10', 'UTF-8', 'UTF-8'), $row(2, 'plugin-écache', '11', 'UTF-16LE', 'UTF-16LE'), $row(3, 'plugin-zcache', '12', 'UTF-16BE', 'UTF-16BE')];
    $result = SQLiteRtrimGlobNocaseAffinityCurrentSourceNextPlan::wordpressOptionNameValuePlan($rows, $rows, 'plugin-[a-é]cache', 9, 12, 'stable', 'stable', 1, 1, 1, 1);
    $t->same(['a-é'], $result['globCharacterClasses'][0]['ranges']);
    $t->same([1, 3, 2], $result['currentMatchedRowids']);
    $t->same([1, 3, 2], $result['currentAffinityMatchedRowids']);
};

$tests['rtrim glob nocase affinity current source next149 leading class disables index range'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('[-_]plugin*');
    $t->same(false, $result['indexUsable']);
    $t->same(null, $result['range']);
    $t->same([], $result['currentCandidateRowids']);
    $t->same([], $result['currentAffinityMatchedRowids']);
};

$tests['rtrim glob nocase affinity current source next149 rejects reversed numeric bounds'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin[-_]cache*', null, null, 20, 10));
};

$tests['rtrim glob nocase affinity current source next149 rejects bad option row shape'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRtrimGlobNocaseAffinityCurrentSourceNextPlan::wordpressOptionNameValuePlan([['option_id' => '1', 'option_name_bytes' => 'x', 'option_value_bytes' => '1', 'name_text_encoding' => 1, 'value_text_encoding' => 1]], $nextRows, 'plugin[-_]cache*', 1, 2));
};

return $tests;
