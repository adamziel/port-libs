<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\MergeBaseFinder;

$fixture = require dirname(__DIR__) . '/fixtures/wordpress-merge-base.php';

$finder = new MergeBaseFinder(static function (string $oid) use ($fixture): ?Commit {
    return $fixture['commits'][$oid] ?? null;
});
$timeOnlyFinder = new MergeBaseFinder(static function (string $oid) use ($fixture): ?Commit {
    return $fixture['commits'][$oid] ?? null;
}, useCommitGraphGenerations: false);
$commitGraphFinder = new MergeBaseFinder(
    static function (string $oid) use ($fixture): ?Commit {
        return $fixture['commits'][$oid] ?? null;
    },
    commitGraphGeneration: static fn (string $oid): ?int => $fixture['shallowCommitGraphGenerations'][$oid] ?? null,
);
$redundantPruneFinder = new MergeBaseFinder(
    static function (string $oid) use ($fixture): ?Commit {
        return $fixture['commits'][$oid] ?? null;
    },
    commitGraphGeneration: static fn (string $oid): ?int => $fixture['redundantPruneCommitGraphGenerations'][$oid] ?? null,
);
$missingGenerationFinder = new MergeBaseFinder(
    static function (string $oid) use ($fixture): ?Commit {
        return $fixture['commits'][$oid] ?? null;
    },
    commitGraphGeneration: static fn (string $oid): ?int => null,
);
$shortcutReads = [];
$shortcutFinder = new MergeBaseFinder(static function (string $oid) use (&$shortcutReads): Commit {
    $shortcutReads[] = $oid;
    throw new RuntimeException('merge-base shortcut should not read WordPress review commits');
});

$reviewBase = $finder->mergeBaseMany($fixture['heads']);
$deploymentBase = $finder->mergeBaseMany($fixture['deploymentHeads']);
$graphWalkBase = $finder->mergeBaseAgainst($fixture['pluginReview'], $fixture['graphWalkOthers']);
$disjointShortcutBases = $shortcutFinder->mergeBasesAgainst(
    $fixture['pluginReview'],
    [$fixture['pluginReview'], $fixture['archivedPluginReview']],
);
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
$shallowCommitGraphBase = $commitGraphFinder->mergeBaseAgainst(
    $fixture['shallowPluginReview'],
    $fixture['shallowGraphWalkOthers'],
);
$shallowPairwiseBase = $timeOnlyFinder->mergeBase(
    $fixture['shallowPluginReview'],
    $fixture['shallowThemeReview'],
);
$shallowMissingArchiveBase = $timeOnlyFinder->mergeBaseAgainst(
    $fixture['shallowPluginReview'],
    $fixture['shallowMissingArchiveGraphWalkOthers'],
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
$redundantPruneBases = $redundantPruneFinder->mergeBases(
    $fixture['redundantPluginReview'],
    $fixture['redundantThemeReview'],
);
$missingGenerationGraphWalkBase = $missingGenerationFinder->mergeBaseAgainst(
    $fixture['missingGenerationPluginReview'],
    $fixture['missingGenerationGraphWalkOthers'],
);
$missingGenerationPairwiseBase = $missingGenerationFinder->mergeBase(
    $fixture['missingGenerationPluginReview'],
    $fixture['missingGenerationThemeReview'],
);
$hydratedPromisorCommits = $fixture['commits'];
$hydratedPromisorReleaseCommit = $hydratedPromisorCommits[$fixture['hydratedPromisorReleaseBaseline']];
unset($hydratedPromisorCommits[$fixture['hydratedPromisorReleaseBaseline']]);
$hydratedPromisorFinder = new MergeBaseFinder(
    static function (string $oid) use (&$hydratedPromisorCommits): ?Commit {
        return $hydratedPromisorCommits[$oid] ?? null;
    },
    useCommitGraphGenerations: false,
);
$hydratedPromisorBeforeBases = $hydratedPromisorFinder->mergeBases(
    $fixture['hydratedPromisorPluginReview'],
    $fixture['hydratedPromisorThemeReview'],
);
$hydratedPromisorCommits[$fixture['hydratedPromisorReleaseBaseline']] = $hydratedPromisorReleaseCommit;
$hydratedPromisorAfterBases = $hydratedPromisorFinder->mergeBases(
    $fixture['hydratedPromisorPluginReview'],
    $fixture['hydratedPromisorThemeReview'],
);
$generationHydrationCommits = $fixture['commits'];
$generationHydrationIntermediateCommit = $generationHydrationCommits[$fixture['generationHydrationIntermediate']];
unset($generationHydrationCommits[$fixture['generationHydrationIntermediate']]);
$generationHydrationFinder = new MergeBaseFinder(static function (string $oid) use (&$generationHydrationCommits): ?Commit {
    return $generationHydrationCommits[$oid] ?? null;
});
$generationHydrationBeforeBases = $generationHydrationFinder->mergeBases(
    $fixture['generationHydrationPluginReview'],
    $fixture['generationHydrationThemeReview'],
);
$generationHydrationCommits[$fixture['generationHydrationIntermediate']] = $generationHydrationIntermediateCommit;
$generationHydrationAfterBases = $generationHydrationFinder->mergeBases(
    $fixture['generationHydrationPluginReview'],
    $fixture['generationHydrationThemeReview'],
);

return [
    'reviewHeads' => $fixture['heads'],
    'reviewBase' => $reviewBase,
    'reviewBases' => $finder->mergeBasesMany($fixture['heads']),
    'deploymentHeads' => $fixture['deploymentHeads'],
    'deploymentBase' => $deploymentBase,
    'graphWalkHeads' => $fixture['graphWalkHeads'],
    'graphWalkBase' => $graphWalkBase,
    'disjointShortcutBases' => $disjointShortcutBases,
    'disjointShortcutAvoidsGraphReads' => $disjointShortcutBases === [$fixture['pluginReview']]
        && $shortcutReads === [],
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
    'shallowCommitGraphBase' => $shallowCommitGraphBase,
    'shallowPairwiseBase' => $shallowPairwiseBase,
    'shallowMissingArchiveBase' => $shallowMissingArchiveBase,
    'shallowReleaseBaseline' => $fixture['shallowReleaseBaseline'],
    'shallowGraphWalkStopsAtReleaseBaseline' => $shallowGraphWalkBase === $fixture['shallowReleaseBaseline'],
    'shallowCommitGraphUsesMetadata' => $shallowCommitGraphBase === $fixture['shallowReleaseBaseline'],
    'shallowPairwiseStopsAtReleaseBaseline' => $shallowPairwiseBase === $fixture['shallowReleaseBaseline'],
    'shallowMissingArchiveParentIsSkipped' => $shallowMissingArchiveBase === $fixture['shallowReleaseBaseline'],
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
    'redundantPruneBases' => $redundantPruneBases,
    'redundantPruneKeepsIndependentBases' => $redundantPruneBases === [
        $fixture['redundantSecurityBase'],
        $fixture['redundantLegacyBase'],
    ],
    'missingGenerationGraphWalkBase' => $missingGenerationGraphWalkBase,
    'missingGenerationPairwiseBase' => $missingGenerationPairwiseBase,
    'missingGenerationProviderKeepsReleaseBaseline' => $missingGenerationGraphWalkBase === $fixture['missingGenerationReleaseBaseline']
        && $missingGenerationPairwiseBase === $fixture['missingGenerationReleaseBaseline'],
    'hydratedPromisorHeads' => $fixture['hydratedPromisorHeads'],
    'hydratedPromisorBeforeBases' => $hydratedPromisorBeforeBases,
    'hydratedPromisorAfterBases' => $hydratedPromisorAfterBases,
    'hydratedPromisorReusesFinderAfterMissingAncestor' => $hydratedPromisorBeforeBases === []
        && $hydratedPromisorAfterBases === [$fixture['hydratedPromisorReleaseBaseline']],
    'generationHydrationHeads' => $fixture['generationHydrationHeads'],
    'generationHydrationBeforeBases' => $generationHydrationBeforeBases,
    'generationHydrationAfterBases' => $generationHydrationAfterBases,
    'generationHydrationRecomputesIncompleteGraph' => $generationHydrationBeforeBases === [
        $fixture['generationHydrationSecurityBase'],
        $fixture['generationHydrationLegacyBase'],
    ] && $generationHydrationAfterBases === [
        $fixture['generationHydrationLegacyBase'],
        $fixture['generationHydrationSecurityBase'],
    ],
    'sha256ReviewHeads' => $fixture['sha256ReviewHeads'],
    'sha256ReviewBase' => $sha256ReviewBase,
    'sha256GraphWalkBase' => $sha256GraphWalkBase,
    'sha256DeployBase' => $sha256DeployBase,
    'sha256ExpectedReleaseBaseline' => $fixture['sha256ReleaseBaseline'],
    'sha256ReviewBaseIsReleaseBaseline' => $sha256ReviewBase === $fixture['sha256ReleaseBaseline'],
    'sha256GraphWalkKeepsReleaseBaseline' => $sha256GraphWalkBase === $fixture['sha256ReleaseBaseline'],
    'sha256DeployBaseIsReleaseBaseline' => $sha256DeployBase === $fixture['sha256ReleaseBaseline'],
];
