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
    $row(1, 'Plugin_%Cache  ', 'UTF-16LE'),
    $row(2, 'plugin_%cache', 'UTF-16BE'),
    $row(3, 'PLUGIN_%CACHE  ', 'UTF-8'),
    $row(4, 'plugin_acache', 'UTF-16LE'),
    $row(5, 'plugin_%cache_alpha', 'UTF-16BE'),
];
$tokenBytes = SQLiteEncodingCollationSourceCursor::encodeText('plugin_%cache', 'UTF-16BE');
$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedPeerReplayPlan(
    $rows,
    $rows,
    'plugin!_!%cache%',
    '!',
    [
        'key' => 'plugin_%cache',
        'rowid' => 2,
        'bytesHex' => bin2hex($tokenBytes),
        'encoding' => 'UTF-16BE',
        'keyBytes' => $tokenBytes,
        'keyEncoding' => 'UTF-16BE',
    ],
    'copied-wp-options',
    'copied-wp-options',
    184,
    184,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next184');
    assert($plan['tokenMatchesEscapedLikeResidual'] === true);
    assert($plan['sameKeyReplayRowids'] === [3]);
    assert($plan['replayPlanRowids'] === [3, 5]);
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next184 self-test passed\n";
}

return $plan;
