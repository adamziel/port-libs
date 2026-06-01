<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['setting_id' => 1, 'key_name' => 'module_cache', 'text_encoding' => 'UTF-16LE'],
    ['setting_id' => 2, 'key_name' => 'module_cache  ', 'text_encoding' => 'UTF-16BE'],
    ['setting_id' => 3, 'key_name' => 'module_cache_more', 'text_encoding' => 'UTF-8'],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'module_cache ', 'text_encoding' => 'UTF-16BE'],
    ['setting_id' => 2, 'key_name' => 'module_cache', 'text_encoding' => 'UTF-16BE'],
    ['setting_id' => 4, 'key_name' => 'module_cache', 'text_encoding' => 'UTF-8'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeSourcePlan($current, $next);

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
