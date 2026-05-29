<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'text_encoding' => 'UTF-16LE'],
    ['option_id' => 2, 'option_name' => 'plugin_cache  ', 'text_encoding' => 'UTF-16BE'],
    ['option_id' => 3, 'option_name' => 'plugin_cache_more', 'text_encoding' => 'UTF-8'],
];

$next = [
    ['option_id' => 1, 'option_name' => 'plugin_cache ', 'text_encoding' => 'UTF-16BE'],
    ['option_id' => 2, 'option_name' => 'plugin_cache', 'text_encoding' => 'UTF-16BE'],
    ['option_id' => 4, 'option_name' => 'plugin_cache', 'text_encoding' => 'UTF-8'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressRtrimLikeSourcePlan($current, $next);

echo json_encode([
    'status' => $plan['status'],
    'expression' => $plan['expression'],
    'currentCandidateRowids' => $plan['currentCandidateRowids'],
    'nextCandidateRowids' => $plan['nextCandidateRowids'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentRtrimResidualRejectedRowids' => $plan['currentRtrimResidualRejectedRowids'],
    'nextRtrimResidualRejectedRowids' => $plan['nextRtrimResidualRejectedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
