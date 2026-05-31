<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\MergeBaseFinder;

$fixture = require dirname(__DIR__) . '/fixtures/wordpress-merge-base.php';

$finder = new MergeBaseFinder(static function (string $oid) use ($fixture): Commit {
    if (!isset($fixture['commits'][$oid])) {
        throw new RuntimeException("Missing WordPress commit fixture: {$oid}");
    }

    return $fixture['commits'][$oid];
});

$reviewBase = $finder->mergeBaseMany($fixture['heads']);
$deploymentBase = $finder->mergeBaseMany($fixture['deploymentHeads']);
$graphWalkBase = $finder->mergeBaseAgainst($fixture['pluginReview'], $fixture['graphWalkOthers']);
$archiveOctopusBases = $finder->mergeBasesMany($fixture['graphWalkHeads']);

return [
    'reviewHeads' => $fixture['heads'],
    'reviewBase' => $reviewBase,
    'reviewBases' => $finder->mergeBasesMany($fixture['heads']),
    'deploymentHeads' => $fixture['deploymentHeads'],
    'deploymentBase' => $deploymentBase,
    'graphWalkHeads' => $fixture['graphWalkHeads'],
    'graphWalkBase' => $graphWalkBase,
    'archiveOctopusBases' => $archiveOctopusBases,
    'expectedReleaseBaseline' => $fixture['releaseBaseline'],
    'reviewBaseIsReleaseBaseline' => $reviewBase === $fixture['releaseBaseline'],
    'deploymentBaseIsReleaseBaseline' => $deploymentBase === $fixture['releaseBaseline'],
    'graphWalkKeepsReleaseBaseline' => $graphWalkBase === $fixture['releaseBaseline'],
    'octopusRejectsArchiveBranch' => $archiveOctopusBases === [],
];
