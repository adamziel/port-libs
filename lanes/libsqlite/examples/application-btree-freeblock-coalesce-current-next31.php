<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockCoalescePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = str_repeat("\xff", $pageSize);
$page[0] = "\x0d";
$page = substr_replace($page, pack('n', 400), 1, 2);
$page = substr_replace($page, pack('n', 1), 3, 2);
$page = substr_replace($page, pack('n', 384), 5, 2);
$page[7] = chr(6);
$page = substr_replace($page, pack('n', 500), 8, 2);
$page = substr_replace($page, str_repeat('A', 8), 500, 8);
$page = substr_replace($page, pack('n', 413) . pack('n', 12), 400, 4);
$page = substr_replace($page, pack('n', 428) . pack('n', 12), 413, 4);
$page = substr_replace($page, pack('n', 0) . pack('n', 16), 428, 4);

$plan = SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $page, clearCoalescedFragments: true);

echo json_encode([
    'scenario' => 'application-btree-freeblock-coalesce-current-next31',
    'summary' => 'Copied wp_options overflow-backed delete page coalesces current/next freeblocks and consumes fragmented bytes without ext/sqlite.',
    'plan' => $plan->toArray(),
], JSON_PRETTY_PRINT) . PHP_EOL;
