<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tests = require dirname(__DIR__) . '/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext153Test.php';
$probe = $tests['planner stat4 expression partial current source next153 status ready'];
$reflection = new ReflectionFunction($probe);
$planFactory = $reflection->getStaticVariables()['plan153'];
$plan = $planFactory();

if (($argv[1] ?? '') === '--self-test') {
    if (($plan['status'] ?? null) !== 'stat4-expression-partial-current-source-sample-fence-ready') {
        fwrite(STDERR, "STAT4 current-source sample fence did not select the current source\n");
        exit(1);
    }
    if (($plan['sampleFence']['matchedRowids'] ?? []) !== [21, 11, 12, 22]) {
        fwrite(STDERR, "STAT4 current-source sample fence rowids changed\n");
        exit(1);
    }

    echo "wordpress-planner-stat4-expression-partial-current-source-sample-fence self-test passed\n";
    exit(0);
}

printf(
    "wordpress planner stat4 expression partial current-source sample fence: %s rowids=%s stale-blocked=%s status=%s\n",
    (string) ($plan['selectedPlan']['name'] ?? 'NO INDEX'),
    implode(',', $plan['sampleFence']['matchedRowids'] ?? []),
    implode(',', $plan['sampleFence']['stalePreparedRowidsBlocked'] ?? []),
    (string) ($plan['status'] ?? 'unknown'),
);
