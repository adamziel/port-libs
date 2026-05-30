<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$rows = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row(3, 'plugin-cache', 'UTF-8'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePreparedByteSignaturePlan(
    $rows,
    $rows,
    $enc('plugin!_cache%', 'UTF-16LE'),
    'UTF-16LE',
    $enc('plugin!_cache%', 'UTF-16BE'),
    'UTF-16BE',
    $enc('!', 'UTF-16LE'),
    'UTF-16LE',
    $enc('!', 'UTF-16BE'),
    'UTF-16BE',
    'copied.wp_options',
    'copied.wp_options',
    221,
    221,
);

$payload = [
    'status' => $plan['status'],
    'pattern' => $plan['currentPattern'],
    'prefix' => $plan['prefix'],
    'sameDecodedSql' => $plan['sameDecodedSql'],
    'samePreparedBytes' => $plan['samePreparedBytes'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'applicationUse' => 'Copied wp_options scanners must fence current-source cursor reuse when a prepared UTF-16 LIKE pattern decodes to the same SQL text but carries different endian byte metadata.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['status'] === 'utf16-nocase-like-rtrim-current-source-next221');
    assert($payload['prefix'] === 'plugin_cache');
    assert($payload['sameDecodedSql'] === true);
    assert($payload['samePreparedBytes'] === false);
    assert($payload['currentMatchedRowids'] === [1, 2]);
    assert($payload['nextMatchedRowids'] === [1, 2]);
    assert($payload['cursorInvalidated'] === true);
    assert($payload['invalidationReasons'] === ['prepared-byte-signature', 'decoded-sql-byte-signature', 'prepared-encoding']);
    echo "application-utf16-nocase-like-rtrim-current-source-next221 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
