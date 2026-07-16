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
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current = [
    $row(1, 'Module_Cache', 'UTF-16LE'),
    $row(2, 'module_cache  ', 'UTF-16BE'),
    $row(6, 'module_cache', 'UTF-16LE'),
    $row(8, "module_cache\t", 'UTF-16LE'),
    $row(12, 'module_cache_alpha', 'UTF-16BE'),
];
$next = [
    $row(1, 'module_cache', 'UTF-16BE'),
    $row(2, 'Module_Cache   ', 'UTF-16LE'),
    $row(6, 'MODULE_CACHE  ', 'UTF-16BE'),
    $row(11, 'module_cache', 'UTF-16LE'),
    $row(8, "module_cache\t", 'UTF-16BE'),
    $row(12, 'module_cache_alpha', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicatePeerResumePlan(
    $current,
    $next,
    'module!_cache',
    '!',
    ['key' => 'module_cache', 'rowid' => 6],
    'main.app_settings@195',
    'main.app_settings@196',
    195,
    196,
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-nextoneNineSix');
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
