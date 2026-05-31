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
$sha256ReviewBase = $finder->mergeBase($fixture['sha256PluginReview'], $fixture['sha256ThemeReview']);
$sha256GraphWalkBase = $finder->mergeBaseAgainst($fixture['sha256PluginReview'], $fixture['sha256GraphWalkOthers']);
$sha256DeployBase = $finder->mergeBaseMany([
    $fixture['sha256Deploy'],
    $fixture['sha256PluginReview'],
    $fixture['sha256ThemeReview'],
]);
$hotfixBases = $finder->mergeBases($fixture['pluginHotfixReview'], $fixture['themeHotfixReview']);
$hotfixBase = $hotfixBases[0] ?? null;

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
    'hotfixHeads' => $fixture['hotfixHeads'],
    'hotfixBases' => $hotfixBases,
    'hotfixBase' => $hotfixBase,
    'hotfixBasePrefersNewerSecurityBaseline' => $hotfixBase === $fixture['securityBaseline'],
    'sha256ReviewHeads' => $fixture['sha256ReviewHeads'],
    'sha256ReviewBase' => $sha256ReviewBase,
    'sha256GraphWalkBase' => $sha256GraphWalkBase,
    'sha256DeployBase' => $sha256DeployBase,
    'sha256ExpectedReleaseBaseline' => $fixture['sha256ReleaseBaseline'],
    'sha256ReviewBaseIsReleaseBaseline' => $sha256ReviewBase === $fixture['sha256ReleaseBaseline'],
    'sha256GraphWalkKeepsReleaseBaseline' => $sha256GraphWalkBase === $fixture['sha256ReleaseBaseline'],
    'sha256DeployBaseIsReleaseBaseline' => $sha256DeployBase === $fixture['sha256ReleaseBaseline'],
];
