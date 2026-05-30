<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$currentRows = [
    $row(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row(2, "plugin_cache\t", 'UTF-16BE'),
    $row(3, "plugin_cache\xc2\xa0", 'UTF-8'),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, "plugin_cache\t", 'UTF-16LE'),
    $row(3, "plugin_cache\xc2\xa0  ", 'UTF-16BE'),
    $row(4, "PLUGIN_OPTION\xc2\xa0", 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameAsciiSpaceRtrimPlan(
    $currentRows,
    $nextRows,
    'plugin%',
    '!',
    'copied-wp-options@208',
    'copied-wp-options@209',
    208,
    209,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next209');
    assert($plan['currentAsciiSpaceTrimmedRowids'] === [1]);
    assert($plan['nextAsciiSpaceTrimmedRowids'] === [3]);
    assert($plan['nextNbspPreservedRowids'] === [3, 4]);
    assert($plan['nextMatchedRowids'] === [1, 2, 3, 4]);
    echo "application-utf16-nocase-like-rtrim-current-source-next209 self-test passed\n";
}

return $plan;
