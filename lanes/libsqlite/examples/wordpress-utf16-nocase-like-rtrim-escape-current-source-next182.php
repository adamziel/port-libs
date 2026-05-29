<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$current = [
    $row(1, 'plugin_%_cache', 2),
    $row(2, 'plugin_a_cache', 3),
    $row(3, 'plugin_%_cache_alpha', 2),
];
$next = [
    $row(1, 'plugin_%_cache', 3),
    $row(3, 'plugin_%_cache_alpha  ', 2),
    $row(5, 'PLUGIN_%_CACHE_NEW', 2),
];
$token = [
    'key' => 'plugin_%_cache',
    'rowid' => 1,
    'bytesHex' => bin2hex($enc('plugin_%_cache', 3)),
    'encoding' => 'UTF-16BE',
];

$plan = SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapeReplayPlan(
    $current,
    $next,
    $enc('plugin!_!%!_cache%', 2),
    2,
    $enc('plugin!_!%!_cache%', 3),
    3,
    $enc('!', 2),
    2,
    $enc('!', 3),
    3,
    $token,
    'stable',
    'stable',
    182,
    182,
);

$summary = [
    'scenario' => 'wordpress-utf16-nocase-like-rtrim-escape-current-source-next182',
    'status' => $plan['status'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'escapeReasons' => $plan['operandInvalidationReasons'],
    'replayMode' => $plan['replayPlanMode'],
    'dependencyClosure' => $plan['dependency_closure'],
    'wordpressUse' => 'Copied wp_options LIKE ESCAPE scans over UTF-16 option_name rows validate decoded ESCAPE operands before replaying a saved RTRIM/NOCASE cursor, preventing stale escaped-wildcard imports after source changes.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'utf16-nocase-like-rtrim-escape-current-source-next182');
    assert($summary['nextMatchedRowids'] === [1, 3, 5]);
    assert(in_array('escape-encoding-changed', $summary['escapeReasons'], true));
    assert($summary['replayMode'] === 'reprepare-from-decoded-escape-start');
    echo "wordpress-utf16-nocase-like-rtrim-escape-current-source-next182 self-test passed\n";
}
