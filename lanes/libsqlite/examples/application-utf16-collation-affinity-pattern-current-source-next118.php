<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$row = static function (int $id, string $value, string $encoding): array {
    return [
        'option_id' => $id,
        'option_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};
$pattern = static fn (string $value, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding);

$current = [
    $row(1, 'autoload:yes', 'UTF-8'),
    $row(2, 'Autoload:No', 'UTF-16LE'),
    $row(3, 'cache:%literal', 'UTF-16BE'),
    ['option_id' => 4, 'option_value' => 10],
];

$next = [
    $row(1, 'autoload:yes', 'UTF-16LE'),
    $row(2, 'Autoload:No', 'UTF-16BE'),
    $row(3, 'cache:%literal', 'UTF-16LE'),
    ['option_id' => 4, 'option_value' => '10'],
    $row(5, 'autoload:fresh', 'UTF-16BE'),
];

$result = [
    'scenario' => 'application-utf16-collation-affinity-pattern-current-source-next118',
    'nocaseAutoload' => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::optionRowValuePlan($current, $next, $pattern('AUTOLOAD:%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'main.wp_options', 'main.wp_options', 'UTF-16LE', 'UTF-16BE'),
    'literalPercent' => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::optionRowValuePlan($current, $next, $pattern('cache:!%%', 'UTF-16BE'), 'UTF-16BE', 'LIKE', 'NOCASE', $pattern('!', 'UTF-16BE'), 'UTF-16BE'),
    'numericAffinity' => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::optionRowValuePlan($current, $next, $pattern('10', 'UTF-16LE'), 'UTF-16LE', 'LIKE', 'NOCASE'),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($result['nocaseAutoload']['currentRowids'] === [2, 1]);
    assert($result['nocaseAutoload']['nextRowids'] === [2, 5, 1]);
    assert($result['nocaseAutoload']['rangePlan']['range']['lowerInclusive'] === 'autoload:');
    assert($result['nocaseAutoload']['nextRangeBytesHex']['lowerInclusive'] === '006100750074006f006c006f00610064003a');
    assert(in_array('range-bytes', $result['nocaseAutoload']['invalidationReasons'], true));
    assert($result['literalPercent']['currentRowids'] === [3]);
    assert($result['literalPercent']['decodedEscape'] === '!');
    assert($result['numericAffinity']['changedStorageRowids'] === [4]);
    echo "application-utf16-collation-affinity-pattern-current-source-next118 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
