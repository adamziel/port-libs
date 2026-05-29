<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';
require_once dirname(__DIR__, 3) . '/tools/TestRunner.php';

$tests = require dirname(__DIR__) . '/tests/SQLitePlannerStat4ExpressionPartialStat4BoundaryPeerFenceTest.php';
$runner = new TestRunner();
$runner->runTests(['wordpress planner stat4 expression partial current source stat4BoundaryPeer smoke' => $tests['planner stat4 expression partial current source stat4BoundaryPeer status ready']], __FILE__);

if ($runner->failures() > 0) {
    exit(1);
}
