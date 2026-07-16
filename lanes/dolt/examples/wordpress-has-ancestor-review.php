<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\CommitGraph;

$fixture = require dirname(__DIR__) . '/fixtures/wp-has-ancestor-review.php';
$graph = new CommitGraph();

$checks = [];
foreach ($fixture['checks'] as $check) {
    $checks[] = [
        'reference' => $check['reference'],
        'ancestor' => $check['ancestor'],
        'has_ancestor' => $graph->hasAncestor(
            $fixture['commits'],
            $check['reference'],
            $check['ancestor'],
            $fixture['headHash'],
        ),
    ];
}

$resolved = [];
foreach (array_keys($fixture['expectedResolvedSpecs']) as $spec) {
    $resolved[$spec] = $graph->resolve($fixture['commits'], $spec, $fixture['headHash']);
}

return [
    'checks' => $checks,
    'resolved' => $resolved,
];
