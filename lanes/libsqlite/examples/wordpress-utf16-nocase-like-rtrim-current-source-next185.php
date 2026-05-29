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

$current = [
    $row(1, 'Plugin_Cache  ', 2),
    $row(2, 'plugin_cache', 3),
    $row(3, 'PLUGIN_CACHE  ', 1),
    $row(4, "plugin_cache\t", 2),
    $row(5, 'plugin_cache_alpha', 3),
];
$next = [
    $row(1, 'Plugin_Cache  ', 2),
    $row(3, 'PLUGIN_CACHE  ', 1),
    $row(4, "plugin_cache\t", 2),
    $row(5, 'plugin_cache_alpha', 3),
    $row(9, 'plugin_cache_delta', 3),
];
$token = [
    'key' => 'plugin_cache',
    'rowid' => 2,
    'bytesHex' => bin2hex($enc('plugin_cache', 3)),
    'encoding' => 'UTF-16BE',
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameDeletedTokenResumePlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
    $token,
    'stable',
    'stable',
    185,
    185,
);

$summary = [
    'scenario' => 'wordpress-utf16-nocase-like-rtrim-current-source-next185',
    'status' => $plan['status'],
    'deletedTokenRowid' => $plan['deletedTokenRowid'],
    'resumeSafe' => $plan['deletedTokenResumeSafe'],
    'replayMode' => $plan['replayPlanMode'],
    'replayRowids' => $plan['replayPlanRowids'],
    'dependencyClosure' => $plan['dependency_closure'],
    'wordpressUse' => 'Copied wp_options import scans can resume a UTF-16 RTRIM/NOCASE LIKE cursor after the last yielded option_name row was deleted, as long as the decoded peer boundary before the token is unchanged.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'utf16-nocase-like-rtrim-current-source-next185');
    assert($summary['deletedTokenRowid'] === 2);
    assert($summary['resumeSafe'] === true);
    assert($summary['replayMode'] === 'continue-after-deleted-key-rowid-token');
    assert($summary['replayRowids'] === [3, 4, 5, 9]);
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next185 self-test passed\n";
}
