<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext250Plan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    ['option_id' => 1, 'option_name' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_cache  '],
    ['option_id' => 3, 'option_name_bytes' => $enc('Plugin_Cache', 'UTF-16LE'), 'text_encoding' => 2],
];

$next = [
    ['option_id' => 1, 'option_name' => 'plugin_cache  '],
    ['option_id' => 2, 'option_name' => 'plugin_cache'],
    ['option_id' => 3, 'option_name_bytes' => $enc('Plugin_Cache', 'UTF-16BE'), 'text_encoding' => 3],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext250Plan::wordpressRtrimLikeResidualPlan($current, $next, 'plugin!_cache', '!');

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'encoding-collation-affinity-like-current-source-next250');
    assert($plan['currentMatchedRowids'] === [3, 1]);
    assert($plan['nextMatchedRowids'] === [3, 2]);
    assert($plan['currentRtrimPeerRejectedRowids'] === [2]);
    assert($plan['nextRtrimPeerRejectedRowids'] === [1]);
    assert(in_array('rtrim-peer-rejections', $plan['invalidationReasons'], true));
    echo "wordpress-rtrim-like-residual-current-source-next250 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-rtrim-like-residual-current-source-next250',
    'wordpressUse' => 'Copied wp_options imports can use an RTRIM collation key to detect trailing-space peers, but LIKE residual matching must still read the raw option_name before publishing a next-source cursor.',
    'pattern' => $plan['pattern'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentRtrimPeerRejectedRowids' => $plan['currentRtrimPeerRejectedRowids'],
    'nextRtrimPeerRejectedRowids' => $plan['nextRtrimPeerRejectedRowids'],
    'changedRawLikeTextRowids' => $plan['changedRawLikeTextRowids'],
    'changedEncodingRowids' => $plan['changedEncodingRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
