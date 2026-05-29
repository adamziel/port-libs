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

$rows = [
    $row(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row(4, "plugin_cache\t", 'UTF-16LE'),
    $row(5, 'plugin_cache_alpha', 'UTF-16BE'),
];
$tokenBytes = SQLiteEncodingCollationSourceCursor::encodeText('plugin_cache', 'UTF-16BE');
$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePeerReplayPlan(
    $rows,
    $rows,
    'plugin!_cache%',
    '!',
    [
        'key' => 'plugin_cache',
        'rowid' => 2,
        'bytesHex' => bin2hex($tokenBytes),
        'encoding' => 'UTF-16BE',
        'keyBytes' => $tokenBytes,
        'keyEncoding' => 'UTF-16BE',
    ],
    'copied-wp-options',
    'copied-wp-options',
    181,
    181,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next181');
    assert($plan['peerContinuationSafe'] === true);
    assert($plan['sameKeyReplayRowids'] === [3]);
    assert($plan['replayPlanRowids'] === [3, 4, 5]);
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next181 self-test passed\n";
}

return $plan;
