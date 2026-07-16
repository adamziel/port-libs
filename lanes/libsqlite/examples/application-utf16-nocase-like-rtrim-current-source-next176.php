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
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$summary = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerYieldPlan(
    [
        $row(1, 'Plugin_Cache', 2),
        $row(2, 'plugin_cache  ', 3),
        $row(4, 'plugin_cache_extra', 2),
    ],
    [
        $row(1, 'Plugin_Cache  ', 3),
        $row(2, 'plugin_cache', 2),
        $row(3, 'PLUGIN_CACHE   ', 2),
        $row(4, 'plugin_cache_extra', 2),
        $row(5, 'plugin_cache_extra  ', 3),
    ],
    'plugin!_cache%',
    '!',
    ['key' => 'plugin_cache', 'rowid' => 2],
    3,
);

$payload = [
    'scenario' => 'application-utf16-nocase-like-rtrim-current-source-nextoneSevenSix',
    'status' => $summary['status'],
    'nextMatchedRowids' => $summary['nextMatchedRowids'],
    'nextDuplicatePeerGroups' => $summary['nextDuplicatePeerGroups'],
    'peerGroupsStraddlingToken' => $summary['peerGroupsStraddlingToken'],
    'yieldedRowids' => $summary['yieldedRowids'],
    'highWaterToken' => $summary['highWaterToken'],
    'safeToResumeInsidePeerGroup' => $summary['safeToResumeInsidePeerGroup'],
    'applicationUse' => 'Application settings cursors over UTF-16 rtrim(key_name) COLLATE NOCASE LIKE probes preserve SQLite rowid tie ordering when case and trailing-space variants collapse to the same index key.',
    'dependency_closure' => $summary['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($payload['status'] !== 'utf16-nocase-like-rtrim-current-source-nextoneSevenSix'
        || $payload['nextMatchedRowids'] !== [1, 2, 3, 4, 5]
        || $payload['peerGroupsStraddlingToken']['plugin_cache']['after'] !== [3]
        || $payload['yieldedRowids'] !== [3, 4, 5]
    ) {
        fwrite(STDERR, "application-utf16-nocase-like-rtrim-current-source-nextoneSevenSix self-test failed\n");
        exit(1);
    }
    echo "application-utf16-nocase-like-rtrim-current-source-nextoneSevenSix self-test passed\n";
}

return $payload;
