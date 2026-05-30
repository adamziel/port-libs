<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

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

$current = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(6, 'plugin_cache', 'UTF-16LE'),
    $row(8, "plugin_cache\t", 'UTF-16LE'),
    $row(12, 'plugin_cache_alpha', 'UTF-16BE'),
];
$next = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'Plugin_Cache   ', 'UTF-16LE'),
    $row(6, 'PLUGIN_CACHE  ', 'UTF-16BE'),
    $row(11, 'plugin_cache', 'UTF-16LE'),
    $row(8, "plugin_cache\t", 'UTF-16BE'),
    $row(12, 'plugin_cache_alpha', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameDuplicatePeerResumePlan(
    $current,
    $next,
    'plugin!_cache',
    '!',
    ['key' => 'plugin_cache', 'rowid' => 6],
    'main.wp_options@195',
    'main.wp_options@196',
    195,
    196,
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next196');
    assert($plan['currentDuplicatePeersBeforeOrAtTokenRowids'] === [1, 2, 6]);
    assert($plan['nextDuplicatePeersBeforeOrAtTokenRowids'] === [1, 2, 6]);
    assert($plan['nextDuplicatePeersAfterTokenRowids'] === [11]);
    assert($plan['candidateTokenResumeSafe'] === false);
    assert(in_array('source-or-schema-changed', $plan['candidateTokenUnsafeReasons'], true));
    assert(in_array('duplicate-key-matched-peers-changed', $plan['candidateTokenUnsafeReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next196 self-test passed\n";

    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
