<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';
require dirname(__DIR__, 3) . '/tools/TestRunner.php';

$tests = require dirname(__DIR__) . '/tests/SQLiteBTreePageMoveFreelistRebalanceTest.php';
$runner = new TestRunner();
$failures = 0;
$error = null;

try {
    $tests['btree page move freelist rebalance page move parent update']($runner);
} catch (Throwable $throwable) {
    $failures = 1;
    $error = $throwable->getMessage();
}

echo json_encode([
    'wordpressUse' => 'Delete an overflow-backed transient option index entry, rebalance sibling index leaves, release obsolete overflow pages, and move the last index leaf into the released freelist slot for auto-vacuum maintenance.',
    'behavior' => 'btree-page-move-freelist-rebalance-current-source-next85',
    'assertions' => $runner->assertions(),
    'failures' => $failures,
    'error' => $error,
    'focusedTest' => 'SQLiteBTreePageMoveFreelistRebalanceTest.php',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($failures > 0) {
    exit(1);
}
