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
$timeOnlyFinder = new MergeBaseFinder(static function (string $oid) use ($fixture): Commit {
    if (!isset($fixture['commits'][$oid])) {
        throw new RuntimeException("Missing WordPress commit fixture: {$oid}");
    }

    return $fixture['commits'][$oid];
}, useCommitGraphGenerations: false);

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
$compatibilityCommitGraphBases = $finder->mergeBases(
    $fixture['pluginCompatibilityReview'],
    $fixture['themeCompatibilityReview'],
);
$compatibilityNoCommitGraphBases = $timeOnlyFinder->mergeBases(
    $fixture['pluginCompatibilityReview'],
    $fixture['themeCompatibilityReview'],
);
$compatibilityCommitGraphBase = $compatibilityCommitGraphBases[0] ?? null;
$compatibilityNoCommitGraphBase = $compatibilityNoCommitGraphBases[0] ?? null;
$sequentialOctopusBase = $finder->mergeBaseOctopus($fixture['octopusSpecialHeads']);
$reorderedOctopusBase = $finder->mergeBaseOctopus($fixture['octopusReorderedHeads']);
$stableOctopusIntersectionBases = $finder->mergeBasesMany($fixture['octopusSpecialHeads']);
$shallowGraphWalkBase = $timeOnlyFinder->mergeBaseAgainst(
    $fixture['shallowPluginReview'],
    $fixture['shallowGraphWalkOthers'],
);
$shallowPairwiseBase = $timeOnlyFinder->mergeBase(
    $fixture['shallowPluginReview'],
    $fixture['shallowThemeReview'],
);
$timestampSkewBase = $finder->mergeBase(
    $fixture['timestampSkewLeftReview'],
    $fixture['timestampSkewRightReview'],
);
$timestampSkewReverseBase = $finder->mergeBase(
    $fixture['timestampSkewRightReview'],
    $fixture['timestampSkewLeftReview'],
);
$timestampSkewNoCommitGraphBase = $timeOnlyFinder->mergeBase(
    $fixture['timestampSkewLeftReview'],
    $fixture['timestampSkewRightReview'],
);
$junctionGraphWalkBases = $finder->mergeBasesAgainst(
    $fixture['junctionPluginReview'],
    $fixture['junctionOtherReviews'],
);
$junctionStableIntersectionBases = $finder->mergeBasesMany($fixture['junctionHeads']);

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
    'compatibilityHeads' => $fixture['compatibilityHeads'],
    'compatibilityCommitGraphBases' => $compatibilityCommitGraphBases,
    'compatibilityNoCommitGraphBases' => $compatibilityNoCommitGraphBases,
    'compatibilityCommitGraphBase' => $compatibilityCommitGraphBase,
    'compatibilityNoCommitGraphBase' => $compatibilityNoCommitGraphBase,
    'commitGraphBasePrefersDeeperLegacyBaseline' => $compatibilityCommitGraphBase === $fixture['legacyDeepBaseline'],
    'noCommitGraphBasePrefersNewerSecurityBaseline' => $compatibilityNoCommitGraphBase === $fixture['securityShallowBaseline'],
    'octopusSpecialHeads' => $fixture['octopusSpecialHeads'],
    'octopusReorderedHeads' => $fixture['octopusReorderedHeads'],
    'sequentialOctopusBase' => $sequentialOctopusBase,
    'reorderedOctopusBase' => $reorderedOctopusBase,
    'stableOctopusIntersectionBases' => $stableOctopusIntersectionBases,
    'sequentialOctopusFallsBackToReleaseBaseline' => $sequentialOctopusBase === $fixture['releaseBaseline'],
    'reorderedOctopusKeepsLegacyBaseline' => $reorderedOctopusBase === $fixture['legacyBaseline'],
    'stableIntersectionKeepsLegacyBaseline' => $stableOctopusIntersectionBases === [$fixture['legacyBaseline']],
    'shallowGraphWalkBase' => $shallowGraphWalkBase,
    'shallowPairwiseBase' => $shallowPairwiseBase,
    'shallowReleaseBaseline' => $fixture['shallowReleaseBaseline'],
    'shallowGraphWalkStopsAtReleaseBaseline' => $shallowGraphWalkBase === $fixture['shallowReleaseBaseline'],
    'shallowPairwiseStopsAtReleaseBaseline' => $shallowPairwiseBase === $fixture['shallowReleaseBaseline'],
    'timestampSkewHeads' => $fixture['timestampSkewHeads'],
    'timestampSkewBase' => $timestampSkewBase,
    'timestampSkewReverseBase' => $timestampSkewReverseBase,
    'timestampSkewNoCommitGraphBase' => $timestampSkewNoCommitGraphBase,
    'timestampSkewExpectedBase' => $fixture['timestampSkewExpectedBase'],
    'timestampSkewPrunesNewerRoot' => $timestampSkewBase === $fixture['timestampSkewExpectedBase']
        && $timestampSkewNoCommitGraphBase === $fixture['timestampSkewExpectedBase'],
    'timestampSkewPermutationOrderIsStable' => $timestampSkewReverseBase === $fixture['timestampSkewExpectedBase'],
    'junctionHeads' => $fixture['junctionHeads'],
    'junctionOtherReviews' => $fixture['junctionOtherReviews'],
    'junctionGraphWalkBases' => $junctionGraphWalkBases,
    'junctionStableIntersectionBases' => $junctionStableIntersectionBases,
    'junctionGraphWalkKeepsUnionSideContentBase' => $junctionGraphWalkBases === [
        $fixture['junctionThemeBase'],
        $fixture['junctionContentBase'],
    ],
    'junctionStableIntersectionPrunesContentBase' => $junctionStableIntersectionBases === [$fixture['junctionThemeBase']],
    'sha256ReviewHeads' => $fixture['sha256ReviewHeads'],
    'sha256ReviewBase' => $sha256ReviewBase,
    'sha256GraphWalkBase' => $sha256GraphWalkBase,
    'sha256DeployBase' => $sha256DeployBase,
    'sha256ExpectedReleaseBaseline' => $fixture['sha256ReleaseBaseline'],
    'sha256ReviewBaseIsReleaseBaseline' => $sha256ReviewBase === $fixture['sha256ReleaseBaseline'],
    'sha256GraphWalkKeepsReleaseBaseline' => $sha256GraphWalkBase === $fixture['sha256ReleaseBaseline'],
    'sha256DeployBaseIsReleaseBaseline' => $sha256DeployBase === $fixture['sha256ReleaseBaseline'],
];
