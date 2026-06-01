<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityCursor;
use PortLibs\LibSqlite\SQLiteUtf16CollationAffinitySourceSwitchPlan;

$tests = [];

$enc = static fn (string $text, int|string $encoding): string => SQLiteUtf16CollationAffinityCursor::encodeText($text, $encoding);
$row = static fn (int $id, mixed $value, int|string $encoding = 'UTF-16LE', string $name = 'app_setting'): array => is_string($value)
    ? [
        'setting_id' => $id,
        'key_name' => $name,
        'key_value_bytes' => $enc($value, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8', 1 => 1,
            'UTF-16LE', 2 => 2,
            'UTF-16BE', 3 => 3,
        },
    ]
    : [
        'setting_id' => $id,
        'key_name' => $name,
        'key_value' => $value,
    ];

$currentRows = [
    $row(1, '02', 'UTF-16LE', 'module_priority'),
    $row(2, '2.0', 'UTF-16BE', 'module_priority_real'),
    $row(3, '10', 'UTF-16LE', 'module_priority_later'),
    $row(4, 'Module_Alpha ', 'UTF-16LE', 'module_slug'),
    $row(5, 'module_beta', 'UTF-16BE', 'module_slug_beta'),
    $row(6, 2, 'UTF-8', 'module_priority_native'),
];

$nextRows = [
    $row(1, '02', 'UTF-16BE', 'module_priority'),
    $row(2, '2x', 'UTF-16BE', 'module_priority_real'),
    $row(3, '10', 'UTF-16LE', 'module_priority_later'),
    $row(4, 'Module_Alpha', 'UTF-16LE', 'module_slug'),
    $row(5, 'module_beta', 'UTF-16BE', 'module_slug_beta'),
    $row(6, 2, 'UTF-8', 'module_priority_native'),
    $row(7, '2', 'UTF-16LE', 'module_priority_new'),
];

$numericPlan = static fn (): array => SQLiteUtf16CollationAffinitySourceSwitchPlan::settingRowValueSourceSwitch(
    $currentRows,
    $nextRows,
    ['valueBytes' => $enc('2', 'UTF-16LE'), 'textEncoding' => 2],
    'NUMERIC',
    'NONE',
    'BINARY',
    'current-source',
    'next-source',
);

$numericCases = [
    'probe is preserved as encoded byte array' => ['probe.textEncoding', 2],
    'left affinity is numeric' => ['leftAffinity', 'NUMERIC'],
    'right affinity is none' => ['rightAffinity', 'NONE'],
    'collation is binary' => ['collation', 'BINARY'],
    'source changed is true' => ['sourceChanged', true],
    'cursor invalidates for changed source rows' => ['cursorInvalidated', true],
    'first invalidation reason is source name' => ['invalidationReasons.0', 'source-name'],
    'second invalidation reason is text encoding' => ['invalidationReasons.1', 'text-encoding'],
    'third invalidation reason is value bytes' => ['invalidationReasons.2', 'value-bytes'],
    'decoded value reason is present' => ['invalidationReasons.3', 'decoded-value'],
    'coerced storage reason is present' => ['invalidationReasons.4', 'coerced-storage'],
    'comparison reason is present' => ['invalidationReasons.5', 'comparison-to-probe'],
    'matched rowset reason is present' => ['invalidationReasons.6', 'matched-rowset'],
    'current numeric seek rowids include current peers' => ['currentRowids', [1, 2, 6, 3, 4, 5]],
    'next numeric seek rowids include new peer before text fallback' => ['nextRowids', [1, 6, 7, 3, 2, 4, 5]],
    'retained rowids keep seek order from current source' => ['retainedRowids', [1, 2, 6, 3, 4, 5]],
    'entered rowids report new numeric peer' => ['enteredRowids', [7]],
    'exited rowids are empty because changed text remains after probe' => ['exitedRowids', []],
    'encoding changed rowid is first setting' => ['changedEncodingRowids', [1]],
    'bytes changed rowids include reencoded, decoded, and numeric text changes' => ['changedBytesRowids', [1, 2, 4]],
    'decoded changed rowids include changed numeric and trimmed text' => ['changedDecodedValueRowids', [2, 4]],
    'coerced storage changed rowid detects numeric text fallback' => ['changedCoercedStorageRowids', [2]],
    'comparison changed rowids detect numeric text fallback' => ['changedComparisonRowids', [2]],
    'current encoding map records utf16le row one' => ['currentEncodings.1', 'UTF-16LE'],
    'next encoding map records utf16be row one' => ['nextEncodings.1', 'UTF-16BE'],
    'current comparison for numeric real peer is equal' => ['currentComparisons.2', 0],
    'next comparison for numeric fallback text is after probe' => ['nextComparisons.2', 1],
    'current bytes expose utf16le row one' => ['currentBytesHex.1', '30003200'],
    'next bytes expose utf16be row one' => ['nextBytesHex.1', '00300032'],
    'dependency decode is recorded' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency comparison is recorded' => ['dependencies.1', 'sqlite-affinity-comparison'],
    'dependency current next invalidation is recorded' => ['dependencies.2', 'sqlite-current-next-source-invalidation'],
];

foreach ($numericCases as $name => [$path, $expected]) {
    $tests['utf16 collation affinity source switch nextOneZeroZero numeric ' . $name] = static function (TestRunner $t) use ($numericPlan, $path, $expected): void {
        $value = $numericPlan();
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$textCurrentRows = [
    $row(10, 'Module_Alpha ', 'UTF-16LE', 'module_slug'),
    $row(11, 'module_alpha', 'UTF-16BE', 'module_slug_alias'),
    $row(12, 'module_beta', 'UTF-16LE', 'module_slug_beta'),
    $row(13, 'layout_alpha', 'UTF-8', 'layout_slug'),
];

$textNextRows = [
    $row(10, 'Module_Alpha ', 'UTF-16BE', 'module_slug'),
    $row(11, 'module_alpha', 'UTF-16BE', 'module_slug_alias'),
    $row(12, 'module_beta ', 'UTF-16LE', 'module_slug_beta'),
    $row(13, 'layout_alpha', 'UTF-8', 'layout_slug'),
];

$textPlan = static fn (): array => SQLiteUtf16CollationAffinitySourceSwitchPlan::settingRowValueSourceSwitch(
    $textCurrentRows,
    $textNextRows,
    'module_alpha ',
    'TEXT',
    'TEXT',
    'RTRIM',
    'app_settings-current',
    'app_settings-next',
);

$textCases = [
    'text rtrim current rowids start at alpha peers' => ['currentRowids', [11, 12]],
    'text rtrim next rowids start at alpha peers' => ['nextRowids', [11, 12]],
    'text rtrim changed source name invalidates cursor' => ['invalidationReasons.0', 'source-name'],
    'text rtrim changed beta bytes invalidates cursor' => ['invalidationReasons.1', 'value-bytes'],
    'text rtrim changed decoded beta value is detected' => ['invalidationReasons.2', 'decoded-value'],
    'text rtrim no rowset change reason' => ['enteredRowids', []],
    'text rtrim no exited rows' => ['exitedRowids', []],
    'text rtrim row ten encoding not compared because before seek' => ['changedEncodingRowids', []],
    'text rtrim row twelve bytes changed' => ['changedBytesRowids', [12]],
    'text rtrim row twelve decoded changed' => ['changedDecodedValueRowids', [12]],
    'text rtrim storage remains text' => ['changedCoercedStorageRowids', []],
    'text rtrim row twelve remains after alpha probe in both sources' => ['changedComparisonRowids', []],
    'text rtrim current beta comparison is after alpha' => ['currentComparisons.12', 1],
    'text rtrim next beta-space comparison is after alpha' => ['nextComparisons.12', 1],
    'text rtrim dependency current next remains present' => ['dependencies.2', 'sqlite-current-next-source-invalidation'],
];

foreach ($textCases as $name => [$path, $expected]) {
    $tests['utf16 collation affinity source switch nextOneZeroZero text ' . $name] = static function (TestRunner $t) use ($textPlan, $path, $expected): void {
        $value = $textPlan();
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$stableRows = [
    $row(21, '2', 'UTF-16LE'),
    $row(22, '10', 'UTF-16BE'),
];

$tests['utf16 collation affinity source switch nextOneZeroZero stable source does not invalidate'] = static function (TestRunner $t) use ($stableRows, $enc): void {
    $plan = SQLiteUtf16CollationAffinitySourceSwitchPlan::settingRowValueSourceSwitch($stableRows, $stableRows, ['valueBytes' => $enc('2', 'UTF-16BE'), 'textEncoding' => 3], 'NUMERIC', 'NONE', 'BINARY', 'same', 'same');
    $t->same(false, $plan['cursorInvalidated']);
};

$tests['utf16 collation affinity source switch nextOneZeroZero stable source has no reasons'] = static function (TestRunner $t) use ($stableRows, $enc): void {
    $plan = SQLiteUtf16CollationAffinitySourceSwitchPlan::settingRowValueSourceSwitch($stableRows, $stableRows, ['valueBytes' => $enc('2', 'UTF-16BE'), 'textEncoding' => 3], 'NUMERIC', 'NONE', 'BINARY', 'same', 'same');
    $t->same([], $plan['invalidationReasons']);
};

$tests['utf16 collation affinity source switch nextOneZeroZero rejects malformed next utf16 bytes'] = static function (TestRunner $t) use ($stableRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinitySourceSwitchPlan::settingRowValueSourceSwitch($stableRows, [['setting_id' => 1, 'key_value_bytes' => "\x70", 'text_encoding' => 2]], 'p'));
};

$tests['utf16 collation affinity source switch nextOneZeroZero rejects unsupported collation'] = static function (TestRunner $t) use ($stableRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinitySourceSwitchPlan::settingRowValueSourceSwitch($stableRows, $stableRows, 'p', 'TEXT', 'TEXT', 'APP_LOCALE'));
};

return $tests;
