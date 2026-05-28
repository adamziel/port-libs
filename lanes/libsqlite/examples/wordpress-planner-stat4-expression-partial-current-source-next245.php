<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tests = require dirname(__DIR__) . '/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext245Test.php';
unset($tests);

$plan = $plan245();

printf(
    "wordpress planner stat4 expression partial current-source next245: %s anchored=%d rowids=%s status=%s\n",
    (string) $plan['selectedPlan']['name'],
    (int) $plan['stat4SampleAnchorFence']['anchoredSampleCount'],
    implode(',', $plan['stat4SampleAnchorFence']['anchoredSampleRowids']),
    (string) $plan['status'],
);
