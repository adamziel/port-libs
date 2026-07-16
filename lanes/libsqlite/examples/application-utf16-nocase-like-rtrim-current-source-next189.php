<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current = [
    $row(1, 'Module_Cache', 'UTF-16LE'),
    $row(2, 'module_cache  ', 'UTF-16BE'),
    $row(3, 'MODULE_CACHE', 'UTF-8'),
    $row(4, 'module_cache_alpha', 'UTF-16LE'),
];
$next = [
    $row(1, 'Module_Cache ', 'UTF-16BE'),
    $row(2, 'module_cache   ', 'UTF-16LE'),
    $row(3, 'MODULE_CACHE', 'UTF-8'),
    $row(4, 'module_cache_alpha', 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerWindowPlan(
    $current,
    $next,
    'module!_cache%',
    '!',
    ['key' => 'module_cache', 'rowid' => 2],
    'copied-wp-options',
    'copied-wp-options',
    189,
    189,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next189');
    assert($plan['peerWindowResumeSafe'] === true);
    assert($plan['paddingOnlyStableRowids'] === [1, 2]);
    assert($plan['replayPlanRowids'] === [3, 4]);
    echo "application-utf16-nocase-like-rtrim-current-source-next189 self-test passed\n";
}

return $plan;
