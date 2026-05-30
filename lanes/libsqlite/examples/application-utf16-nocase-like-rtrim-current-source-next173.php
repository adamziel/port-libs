<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$summary = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourcePlan(
    [
        $row(1, 'Plugin_Cache  ', 2),
        $row(2, 'plugin_cache', 3),
        $row(3, "plugin_cache\t", 2),
        $row(4, 'plugin_cache' . "\xc2\xa0", 3),
    ],
    [
        $row(1, 'Plugin_Cache', 3),
        $row(2, 'plugin_cache   ', 3),
        $row(3, "plugin_cache\t", 2),
        $row(5, 'PLUGIN_CACHE_NEW  ', 2),
    ],
    'plugin!_cache',
    '!',
    'main.wp_options@172',
    'main.wp_options@173',
    172,
    173,
);

$payload = [
    'scenario' => 'application-utf16-nocase-like-rtrim-current-source-next173',
    'status' => $summary['status'],
    'currentMatchedRowids' => $summary['currentMatchedRowids'],
    'nextMatchedRowids' => $summary['nextMatchedRowids'],
    'changedTrailingSpaceOnlyRowids' => $summary['changedTrailingSpaceOnlyRowids'],
    'byteReprepareReasons' => $summary['byteReprepareReasons'],
    'semanticInvalidationReasons' => $summary['semanticInvalidationReasons'],
    'applicationUse' => 'Copied wp_options cursors over UTF-16 rtrim(option_name) COLLATE NOCASE LIKE probes can distinguish byte-only trailing-space changes from semantic rowset changes before resuming a current-source cursor.',
    'dependency_closure' => $summary['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($payload['status'] !== 'utf16-nocase-like-rtrim-current-source-next173'
        || $payload['currentMatchedRowids'] !== [1, 2]
        || $payload['nextMatchedRowids'] !== [1, 2]
        || $payload['changedTrailingSpaceOnlyRowids'] !== [1, 2]
    ) {
        fwrite(STDERR, "application-utf16-nocase-like-rtrim-current-source-next173 self-test failed\n");
        exit(1);
    }
    echo "application-utf16-nocase-like-rtrim-current-source-next173 self-test passed\n";
}

return $payload;
