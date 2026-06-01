<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan;

$tests = [];

$encodingCode = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};

$row = static function (
    int $id,
    string $name,
    string $value,
    string $nameEncoding,
    string $valueEncoding,
) use ($encodingCode): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $nameEncoding),
        'key_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $valueEncoding),
        'name_text_encoding' => $encodingCode($nameEncoding),
        'value_text_encoding' => $encodingCode($valueEncoding),
    ];
};

$bad = static fn (int $id, string $nameBytes, int $nameEncoding, string $valueBytes, int $valueEncoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $nameBytes,
    'key_value_bytes' => $valueBytes,
    'name_text_encoding' => $nameEncoding,
    'value_text_encoding' => $valueEncoding,
];

$currentRows = [
    $row(1, 'plugin_cache', '10', 'UTF-8', 'UTF-8'),
    $row(2, 'Plugin_Cache   ', '11', 'UTF-16LE', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', '9.5', 'UTF-16BE', 'UTF-16LE'),
    $row(4, 'plugin_cache_future', '15e0', 'UTF-16LE', 'UTF-8'),
    $row(5, 'plugin_cache_zero', '0x10', 'UTF-16BE', 'UTF-16BE'),
    $row(6, 'plugin_cache_text', 'fast', 'UTF-8', 'UTF-16LE'),
    $row(7, 'PLUGIN_cache', '12', 'UTF-16BE', 'UTF-8'),
    $row(8, 'plugin_éclair', '12.25', 'UTF-16LE', 'UTF-16LE'),
    $row(9, 'PLUGIN_ÉCLAIR ', '13', 'UTF-16BE', 'UTF-16BE'),
    $row(10, 'theme_cache', '12', 'UTF-8', 'UTF-8'),
    $bad(11, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00c", 2, SQLiteEncodingCollationSourceCursor::encodeText('12', 'UTF-8'), 1),
    $bad(15, SQLiteEncodingCollationSourceCursor::encodeText('plugin_cache_broken_value', 'UTF-8'), 1, "\x3d\xd8", 2),
];

$nextRows = [
    $row(1, 'plugin_cache  ', '10.0', 'UTF-16BE', 'UTF-16LE'),
    $row(2, 'Plugin_Cache', '11', 'UTF-16BE', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', '8', 'UTF-16BE', 'UTF-16LE'),
    $row(4, 'plugin_cache_future', '16', 'UTF-16LE', 'UTF-8'),
    $row(5, 'plugin_cache_zero', '10', 'UTF-16BE', 'UTF-16BE'),
    $row(6, 'plugin_cache_text', '12', 'UTF-8', 'UTF-16LE'),
    $row(7, 'PLUGIN_cache', '12', 'UTF-16BE', 'UTF-8'),
    $row(8, 'plugin_éclair', '12.25', 'UTF-16LE', 'UTF-16LE'),
    $row(9, 'PLUGIN_ÉCLAIR ', '13', 'UTF-16BE', 'UTF-16BE'),
    $row(12, 'plugin_cache_new', '14', 'UTF-16LE', 'UTF-8'),
    $row(13, 'Plugin_Cache_New', '14', 'UTF-16BE', 'UTF-8'),
    $row(15, 'plugin_cache_broken_value', '12', 'UTF-8', 'UTF-8'),
    $bad(14, "\x3d\xd8", 2, SQLiteEncodingCollationSourceCursor::encodeText('12', 'UTF-8'), 1),
];

$plan = static fn (
    string $pattern = 'plugin_*',
    ?array $current = null,
    ?array $next = null,
    int|float|string $minimum = '9.5',
    int|float|string $maximum = '14',
    string $currentSource = 'main.app_settings@144',
    string $nextSource = 'main.app_settings@145',
    int $currentSchemaCookie = 31,
    int $nextSchemaCookie = 32,
    int $currentCollationVersion = 8,
    int $nextCollationVersion = 9,
): array => SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyValuePlan(
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
    'operator' => ['operator', 'GLOB'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE GLOB ? AND key_value BETWEEN ? AND ?'],
    'name collation' => ['nameCollation', 'RTRIM+NOCASE'],
    'residual collation' => ['residualCollation', 'BINARY'],
    'value affinity' => ['valueAffinity', 'NUMERIC'],
    'numeric minimum' => ['numericRange.minimum', 9.5],
    'numeric maximum' => ['numericRange.maximum', 14.0],
    'range lower' => ['range.lowerInclusive', 'plugin_'],
    'range upper' => ['range.upperBound', 'plugin`'],
    'index usable' => ['indexUsable', true],
    'current source' => ['currentSource', 'main.app_settings@144'],
    'next source' => ['nextSource', 'main.app_settings@145'],
    'current schema cookie' => ['currentSchemaCookie', 31],
    'next schema cookie' => ['nextSchemaCookie', 32],
    'current collation version' => ['currentCollationVersion', 8],
    'next collation version' => ['nextCollationVersion', 9],
    'current order rowids' => ['currentOrderRowids', [1, 2, 7, 3, 4, 6, 5, 9, 8, 10]],
    'next order rowids' => ['nextOrderRowids', [1, 2, 7, 15, 3, 4, 12, 13, 6, 5, 9, 8]],
    'current candidate rowids' => ['currentCandidateRowids', [1, 2, 7, 3, 4, 6, 5, 9, 8]],
    'next candidate rowids' => ['nextCandidateRowids', [1, 2, 7, 15, 3, 4, 12, 13, 6, 5, 9, 8]],
    'current matched rowids' => ['currentMatchedRowids', [1, 3, 4, 6, 5, 8]],
    'next matched rowids' => ['nextMatchedRowids', [1, 15, 3, 4, 12, 6, 5, 8]],
    'current affinity matched rowids' => ['currentAffinityMatchedRowids', [1, 3, 8]],
    'next affinity matched rowids' => ['nextAffinityMatchedRowids', [1, 15, 12, 6, 5, 8]],
    'current false positive rowids' => ['currentFalsePositiveRowids', [2, 7, 9]],
    'current affinity rejected rowids' => ['currentAffinityRejectedRowids', [4, 6, 5]],
    'next affinity rejected rowids' => ['nextAffinityRejectedRowids', [3, 4]],
    'retained affinity matched rowids' => ['retainedAffinityMatchedRowids', [1, 8]],
    'entered affinity matched rowids' => ['enteredAffinityMatchedRowids', [15, 12, 6, 5]],
    'exited affinity matched rowids' => ['exitedAffinityMatchedRowids', [3]],
    'row two comparison key trims and folds' => ['currentComparisonKeys.2', 'plugin_cache'],
    'row nine uppercase unicode is not folded' => ['currentComparisonKeys.9', 'plugin_Éclair'],
    'row eight unicode lowercase key' => ['currentComparisonKeys.8', 'plugin_éclair'],
    'row one next name text trims only in key' => ['nextNameTexts.1', 'plugin_cache  '],
    'row one next value text' => ['nextValueTexts.1', '10.0'],
    'row five current numeric prefix from hex-like text' => ['currentNumericValues.5', 0],
    'row six current non numeric value' => ['currentNumericValues.6', null],
    'row four exponent numeric' => ['currentNumericValues.4', 15.0],
    'row eight decimal numeric' => ['currentNumericValues.8', 12.25],
    'row one current name encoding' => ['currentNameEncodings.1', 'UTF-8'],
    'row one next name encoding' => ['nextNameEncodings.1', 'UTF-16BE'],
    'row two current value encoding' => ['currentValueEncodings.2', 'UTF-16BE'],
    'row three value bytes changed' => ['retainedValueBytesChangedRowids.1', 3],
    'row one current residual true' => ['currentResidualMatches.1', true],
    'row two current residual false' => ['currentResidualMatches.2', false],
    'row one current affinity true' => ['currentAffinityMatches.1', true],
    'row five current affinity false' => ['currentAffinityMatches.5', false],
    'row fifteen current value error' => ['currentErrors.15', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'row eleven current name error' => ['currentErrors.11', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'row fourteen next name error' => ['nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current malformed rowids' => ['currentMalformedRowids', [11, 15]],
    'next malformed rowids' => ['nextMalformedRowids', [14]],
    'retained comparison changed' => ['retainedComparisonKeyChangedRowids', []],
    'retained name encoding changed' => ['retainedNameEncodingChangedRowids', [1, 2]],
    'retained name bytes changed' => ['retainedNameBytesChangedRowids', [1, 2]],
    'retained value changed' => ['retainedValueChangedRowids', [1, 3, 4, 5, 6]],
    'retained value encoding changed' => ['retainedValueEncodingChangedRowids', [1]],
    'retained value bytes changed' => ['retainedValueBytesChangedRowids', [1, 3, 4, 5, 6]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason collation' => ['invalidationReasons.2', 'collation-version'],
    'reason malformed' => ['invalidationReasons.3', 'malformed-text'],
    'reason candidate' => ['invalidationReasons.4', 'candidate-rowset'],
    'reason matched' => ['invalidationReasons.5', 'matched-rowset'],
    'reason affinity rowset' => ['invalidationReasons.6', 'affinity-rowset'],
    'reason name encoding' => ['invalidationReasons.7', 'name-encoding'],
    'reason name bytes' => ['invalidationReasons.8', 'name-bytes'],
    'reason affinity value' => ['invalidationReasons.9', 'affinity-value'],
    'reason value encoding' => ['invalidationReasons.10', 'value-encoding'],
    'reason value bytes' => ['invalidationReasons.11', 'value-bytes'],
    'dependency numeric affinity' => ['dependencies.3', 'sqlite-numeric-affinity'],
    'dependency current source nextOneFourFive' => ['dependencies.5', 'sqlite-current-source-nextoneFourFive'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 rtrim glob affinity current source nextOneFourFive ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 rtrim glob affinity current source nextOneFourFive stable identical rows are reusable'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'plugin_cache ', '10', 'UTF-16LE', 'UTF-8'), $row(2, 'Plugin_Cache', '11', 'UTF-16BE', 'UTF-16BE')];
    $result = $plan('plugin_*', $rows, $rows, 9, 12, 'stable', 'stable', 4, 4, 5, 5);
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([1], $result['currentAffinityMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 rtrim glob affinity current source nextOneFourFive leading wildcard disables range candidates'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('*cache');
    $t->same(false, $result['indexUsable']);
    $t->same(null, $result['range']);
    $t->same([], $result['currentCandidateRowids']);
    $t->same([], $result['currentAffinityMatchedRowids']);
};

$tests['utf16 rtrim glob affinity current source nextOneFourFive exact rtrim range keeps binary residual and affinity'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'plugin_cache', '10', 'UTF-8', 'UTF-8'), $row(2, 'plugin_cache   ', '10', 'UTF-16LE', 'UTF-16LE'), $row(3, 'Plugin_Cache', '10', 'UTF-16BE', 'UTF-16BE')];
    $result = $plan('plugin_cache', $rows, $rows, 9, 11, 'stable', 'stable', 1, 1, 1, 1);
    $t->same([1, 2, 3], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([1], $result['currentAffinityMatchedRowids']);
    $t->same([2, 3], $result['currentFalsePositiveRowids']);
};

$tests['utf16 rtrim glob affinity current source nextOneFourFive numeric bounds accept sqlite numeric prefixes'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'plugin_cache', '10', 'UTF-8', 'UTF-8'), $row(2, 'plugin_cache_extra', '12.5ms', 'UTF-16LE', 'UTF-16BE')];
    $result = $plan('plugin_*', $rows, $rows, '10x', '12.5ms', 'stable', 'stable', 1, 1, 1, 1);
    $t->same([1, 2], $result['currentAffinityMatchedRowids']);
    $t->same(12.5, $result['currentNumericValues'][2]);
};

$tests['utf16 rtrim glob affinity current source nextOneFourFive rejects reversed numeric range'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin_*', null, null, 20, 10));
};

$tests['utf16 rtrim glob affinity current source nextOneFourFive rejects non numeric bound'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin_*', null, null, 'fast', 10));
};

$tests['utf16 rtrim glob affinity current source nextOneFourFive rejects non integer setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyValuePlan([['setting_id' => '1', 'key_name_bytes' => 'x', 'key_value_bytes' => '1', 'name_text_encoding' => 1, 'value_text_encoding' => 1]], $nextRows, 'plugin_*', 1, 2));
};

$tests['utf16 rtrim glob affinity current source nextOneFourFive rejects missing value bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyValuePlan([['setting_id' => 1, 'key_name_bytes' => 'x', 'name_text_encoding' => 1, 'value_text_encoding' => 1]], $nextRows, 'plugin_*', 1, 2));
};

$tests['utf16 rtrim glob affinity current source nextOneFourFive rejects missing value encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyValuePlan([['setting_id' => 1, 'key_name_bytes' => 'x', 'key_value_bytes' => '1', 'name_text_encoding' => 1]], $nextRows, 'plugin_*', 1, 2));
};

return $tests;
