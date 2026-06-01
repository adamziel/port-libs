<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\MergeBaseCommand;
use PortLibs\Gitoxide\MergeBaseFinder;
use PortLibs\Gitoxide\ObjectDatabase;

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
$maxGenerationFinder = new MergeBaseFinder(
    static function (string $oid) use ($fixture): ?Commit {
        return $fixture['commits'][$oid] ?? null;
    },
    commitGraphGeneration: static fn (string $oid): ?int => $fixture['maxCommitGraphGenerations'][$oid] ?? null,
);
$invalidGenerationFinder = new MergeBaseFinder(
    static function (string $oid) use ($fixture): ?Commit {
        return $fixture['commits'][$oid] ?? null;
    },
    commitGraphGeneration: static fn (string $oid): ?int => $fixture['invalidCommitGraphGenerations'][$oid] ?? null,
);
$commitGraphOnlyObjectReads = [];
$commitGraphOnlyFinder = new MergeBaseFinder(
    static function (string $oid) use ($fixture, &$commitGraphOnlyObjectReads): ?Commit {
        $commitGraphOnlyObjectReads[] = $oid;

        return $fixture['commitGraphObjectCommits'][$oid] ?? null;
    },
    commitGraphGeneration: static fn (string $oid): ?int => $fixture['commitGraphOnlyGenerations'][$oid] ?? null,
    commitGraphCommit: static fn (string $oid): ?Commit => $fixture['commitGraphOnlyCommits'][$oid] ?? null,
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
$hotfixCommandOutput = MergeBaseCommand::humanOutput(
    $finder,
    $fixture['pluginHotfixReview'],
    [$fixture['themeHotfixReview']],
);
$archiveCommandError = null;
try {
    MergeBaseCommand::humanOutput($finder, $fixture['pluginHotfixReview'], [$fixture['archivedPluginReview']]);
} catch (RuntimeException $exception) {
    $archiveCommandError = $exception->getMessage();
}
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
$shallowStableIntersectionBases = $timeOnlyFinder->mergeBasesMany([
    $fixture['shallowPluginReview'],
    $fixture['shallowThemeReview'],
]);
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
$maxGenerationBase = $maxGenerationFinder->mergeBase($fixture['pluginReview'], $fixture['themeReview']);
$invalidGenerationRejected = false;
try {
    $invalidGenerationFinder->mergeBase($fixture['pluginReview'], $fixture['themeReview']);
} catch (InvalidArgumentException) {
    $invalidGenerationRejected = true;
}
$commitGraphOnlyBases = $commitGraphOnlyFinder->mergeBases(
    $fixture['commitGraphOnlyPluginReview'],
    $fixture['commitGraphOnlyThemeReview'],
);
$commitGraphOnlyStableBases = $commitGraphOnlyFinder->mergeBasesMany($fixture['commitGraphOnlyHeads']);
$commitGraphOnlyGraphWalkBase = $commitGraphOnlyFinder->mergeBaseAgainst(
    $fixture['commitGraphOnlyPluginReview'],
    $fixture['commitGraphOnlyGraphWalkOthers'],
);
$equalPriorityBases = $finder->mergeBases(
    $fixture['equalPriorityPluginMerge'],
    $fixture['equalPriorityThemeMerge'],
);
$equalPriorityGraphWalkBases = $finder->mergeBasesAgainst(
    $fixture['equalPriorityPluginMerge'],
    [$fixture['equalPriorityThemeMerge']],
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
$stableHydrationCommits = $fixture['commits'];
$stableHydrationReleaseCommit = $stableHydrationCommits[$fixture['hydratedPromisorReleaseBaseline']];
unset($stableHydrationCommits[$fixture['hydratedPromisorReleaseBaseline']]);
$stableHydrationFinder = new MergeBaseFinder(
    static function (string $oid) use (&$stableHydrationCommits): ?Commit {
        return $stableHydrationCommits[$oid] ?? null;
    },
    useCommitGraphGenerations: false,
);
$stableHydrationBeforeBases = $stableHydrationFinder->mergeBasesMany($fixture['hydratedPromisorHeads']);
$stableHydrationCommits[$fixture['hydratedPromisorReleaseBaseline']] = $stableHydrationReleaseCommit;
$stableHydrationAfterBases = $stableHydrationFinder->mergeBasesMany($fixture['hydratedPromisorHeads']);
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
$objectDatabaseGitDir = sys_get_temp_dir() . '/port-libs-wp-merge-base-shallow-db-' . bin2hex(random_bytes(4)) . '/.git';
$objectDatabaseStore = new LooseObjectStore($objectDatabaseGitDir);
$objectDatabaseCommitBody = static function (array $parents, int $seconds, string $message): string {
    $lines = ["tree " . str_repeat('f', 40)];
    foreach ($parents as $parent) {
        $lines[] = "parent {$parent}";
    }
    $lines[] = "author Release Bot <release@example.test> {$seconds} +0000";
    $lines[] = "committer Deploy Bot <deploy@example.test> {$seconds} +0000";
    $lines[] = '';
    $lines[] = $message;

    return implode("\n", $lines) . "\n";
};
$objectDatabaseReleaseObject = new GitObject(
    'commit',
    $objectDatabaseCommitBody([], 1700005800, 'object database release baseline'),
);
$objectDatabaseReleaseBaseline = $objectDatabaseReleaseObject->oid();
$objectDatabasePluginReview = $objectDatabaseStore->write(new GitObject(
    'commit',
    $objectDatabaseCommitBody([$objectDatabaseReleaseBaseline], 1700005900, 'object database plugin review'),
));
$objectDatabaseThemeReview = $objectDatabaseStore->write(new GitObject(
    'commit',
    $objectDatabaseCommitBody([$objectDatabaseReleaseBaseline], 1700006000, 'object database theme review'),
));
$objectDatabaseFinder = MergeBaseFinder::fromObjectDatabase(new ObjectDatabase($objectDatabaseGitDir));
$objectDatabaseShallowBeforeBases = $objectDatabaseFinder->mergeBases(
    $objectDatabasePluginReview,
    $objectDatabaseThemeReview,
);
$objectDatabaseStore->write($objectDatabaseReleaseObject);
$objectDatabaseShallowAfterBases = $objectDatabaseFinder->mergeBases(
    $objectDatabasePluginReview,
    $objectDatabaseThemeReview,
);
$objectDatabaseAssetBlob = $objectDatabaseStore->write(new GitObject('blob', 'cached plugin asset payload'));
$objectDatabaseAssetPluginReview = $objectDatabaseStore->write(new GitObject(
    'commit',
    $objectDatabaseCommitBody([$objectDatabaseAssetBlob], 1700006050, 'object database asset-backed plugin review'),
));
$objectDatabaseAssetThemeReview = $objectDatabaseStore->write(new GitObject(
    'commit',
    $objectDatabaseCommitBody([$objectDatabaseAssetBlob], 1700006060, 'object database asset-backed theme review'),
));
$objectDatabaseNonCommitParentBases = $objectDatabaseFinder->mergeBases(
    $objectDatabaseAssetPluginReview,
    $objectDatabaseAssetThemeReview,
);
$objectDatabaseNonCommitStartBases = $objectDatabaseFinder->mergeBases(
    $objectDatabaseAssetBlob,
    $objectDatabaseAssetPluginReview,
);
$sha256ObjectDatabaseGitDir = sys_get_temp_dir() . '/port-libs-wp-merge-base-sha256-db-' . bin2hex(random_bytes(4)) . '/.git';
$sha256ObjectDatabaseStore = new LooseObjectStore($sha256ObjectDatabaseGitDir, false, 'sha256');
$sha256ObjectDatabaseCommitBody = static function (array $parents, int $seconds, string $message): string {
    $lines = ["tree " . str_repeat('f', 64)];
    foreach ($parents as $parent) {
        $lines[] = "parent {$parent}";
    }
    $lines[] = "author Release Bot <release@example.test> {$seconds} +0000";
    $lines[] = "committer Deploy Bot <deploy@example.test> {$seconds} +0000";
    $lines[] = '';
    $lines[] = $message;

    return implode("\n", $lines) . "\n";
};
$sha256ObjectDatabaseReleaseBaseline = $sha256ObjectDatabaseStore->write(new GitObject(
    'commit',
    $sha256ObjectDatabaseCommitBody([], 1700006100, 'sha256 object database release baseline'),
));
$sha256ObjectDatabasePluginReview = $sha256ObjectDatabaseStore->write(new GitObject(
    'commit',
    $sha256ObjectDatabaseCommitBody([$sha256ObjectDatabaseReleaseBaseline], 1700006200, 'sha256 object database plugin review'),
));
$sha256ObjectDatabaseThemeReview = $sha256ObjectDatabaseStore->write(new GitObject(
    'commit',
    $sha256ObjectDatabaseCommitBody([$sha256ObjectDatabaseReleaseBaseline], 1700006300, 'sha256 object database theme review'),
));
$sha256ObjectDatabaseArchiveReview = $sha256ObjectDatabaseStore->write(new GitObject(
    'commit',
    $sha256ObjectDatabaseCommitBody([], 1700006400, 'sha256 object database archived review'),
));
$sha256ObjectDatabaseFinder = MergeBaseFinder::fromObjectDatabase(new ObjectDatabase(
    $sha256ObjectDatabaseGitDir,
    objectHash: 'sha256',
));
$sha256ObjectDatabaseBases = $sha256ObjectDatabaseFinder->mergeBases(
    $sha256ObjectDatabasePluginReview,
    $sha256ObjectDatabaseThemeReview,
);
$sha256ObjectDatabaseGraphWalkBase = $sha256ObjectDatabaseFinder->mergeBaseAgainst(
    $sha256ObjectDatabasePluginReview,
    [$sha256ObjectDatabaseThemeReview, $sha256ObjectDatabaseArchiveReview],
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
    'hotfixCommandOutput' => $hotfixCommandOutput,
    'hotfixCommandPrintsAllBases' => $hotfixCommandOutput === $fixture['securityBaseline'] . "\n"
        . $fixture['legacyBaseline'] . "\n",
    'archiveCommandError' => $archiveCommandError,
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
    'shallowStableIntersectionBases' => $shallowStableIntersectionBases,
    'shallowMissingArchiveBase' => $shallowMissingArchiveBase,
    'shallowReleaseBaseline' => $fixture['shallowReleaseBaseline'],
    'shallowGraphWalkStopsAtReleaseBaseline' => $shallowGraphWalkBase === $fixture['shallowReleaseBaseline'],
    'shallowCommitGraphUsesMetadata' => $shallowCommitGraphBase === $fixture['shallowReleaseBaseline'],
    'shallowPairwiseStopsAtReleaseBaseline' => $shallowPairwiseBase === $fixture['shallowReleaseBaseline'],
    'shallowStableIntersectionSkipsMissingParent' => $shallowStableIntersectionBases === [
        $fixture['shallowReleaseBaseline'],
    ],
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
    'maxGenerationBase' => $maxGenerationBase,
    'maxGenerationProviderKeepsReleaseBaseline' => $maxGenerationBase === $fixture['releaseBaseline'],
    'invalidCommitGraphGenerationRejected' => $invalidGenerationRejected,
    'commitGraphOnlyHeads' => $fixture['commitGraphOnlyHeads'],
    'commitGraphOnlyBases' => $commitGraphOnlyBases,
    'commitGraphOnlyStableBases' => $commitGraphOnlyStableBases,
    'commitGraphOnlyGraphWalkBase' => $commitGraphOnlyGraphWalkBase,
    'commitGraphOnlyObjectReads' => $commitGraphOnlyObjectReads,
    'commitGraphOnlyBaseIsReleaseBaseline' => $commitGraphOnlyBases === [
        $fixture['commitGraphOnlyReleaseBaseline'],
    ] && $commitGraphOnlyStableBases === [
        $fixture['commitGraphOnlyReleaseBaseline'],
    ] && $commitGraphOnlyGraphWalkBase === $fixture['commitGraphOnlyReleaseBaseline'],
    'commitGraphOnlyAvoidsObjectReadsForGraphCommits' => $commitGraphOnlyObjectReads === [
        $fixture['commitGraphOnlyArchiveReview'],
    ],
    'equalPriorityHeads' => $fixture['equalPriorityHeads'],
    'equalPriorityBases' => $equalPriorityBases,
    'equalPriorityGraphWalkBases' => $equalPriorityGraphWalkBases,
    'equalPriorityKeepsGitBaselineOrder' => $equalPriorityBases === $fixture['equalPriorityExpectedBases']
        && $equalPriorityGraphWalkBases === $fixture['equalPriorityExpectedBases']
        && strcmp($fixture['equalPriorityThemeBase'], $fixture['equalPriorityContentBase']) < 0,
    'hydratedPromisorHeads' => $fixture['hydratedPromisorHeads'],
    'hydratedPromisorBeforeBases' => $hydratedPromisorBeforeBases,
    'hydratedPromisorAfterBases' => $hydratedPromisorAfterBases,
    'hydratedPromisorReusesFinderAfterMissingAncestor' => $hydratedPromisorBeforeBases === []
        && $hydratedPromisorAfterBases === [$fixture['hydratedPromisorReleaseBaseline']],
    'stableHydrationBeforeBases' => $stableHydrationBeforeBases,
    'stableHydrationAfterBases' => $stableHydrationAfterBases,
    'stableHydrationReusesFinderAfterMissingAncestor' => $stableHydrationBeforeBases === []
        && $stableHydrationAfterBases === [$fixture['hydratedPromisorReleaseBaseline']],
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
    'objectDatabaseShallowHeads' => [$objectDatabasePluginReview, $objectDatabaseThemeReview],
    'objectDatabaseReleaseBaseline' => $objectDatabaseReleaseBaseline,
    'objectDatabaseShallowBeforeBases' => $objectDatabaseShallowBeforeBases,
    'objectDatabaseShallowAfterBases' => $objectDatabaseShallowAfterBases,
    'objectDatabaseFinderReusesHydratedParent' => $objectDatabaseShallowBeforeBases === []
        && $objectDatabaseShallowAfterBases === [$objectDatabaseReleaseBaseline],
    'objectDatabaseNonCommitParentBases' => $objectDatabaseNonCommitParentBases,
    'objectDatabaseNonCommitStartBases' => $objectDatabaseNonCommitStartBases,
    'objectDatabaseSkipsNonCommitAncestors' => $objectDatabaseNonCommitParentBases === []
        && $objectDatabaseNonCommitStartBases === [],
    'sha256ObjectDatabaseHeads' => [$sha256ObjectDatabasePluginReview, $sha256ObjectDatabaseThemeReview],
    'sha256ObjectDatabaseReleaseBaseline' => $sha256ObjectDatabaseReleaseBaseline,
    'sha256ObjectDatabaseBases' => $sha256ObjectDatabaseBases,
    'sha256ObjectDatabaseGraphWalkBase' => $sha256ObjectDatabaseGraphWalkBase,
    'sha256ObjectDatabaseBaseIsReleaseBaseline' => $sha256ObjectDatabaseBases === [$sha256ObjectDatabaseReleaseBaseline]
        && $sha256ObjectDatabaseGraphWalkBase === $sha256ObjectDatabaseReleaseBaseline,
    'sha256ReviewHeads' => $fixture['sha256ReviewHeads'],
    'sha256ReviewBase' => $sha256ReviewBase,
    'sha256GraphWalkBase' => $sha256GraphWalkBase,
    'sha256DeployBase' => $sha256DeployBase,
    'sha256ExpectedReleaseBaseline' => $fixture['sha256ReleaseBaseline'],
    'sha256ReviewBaseIsReleaseBaseline' => $sha256ReviewBase === $fixture['sha256ReleaseBaseline'],
    'sha256GraphWalkKeepsReleaseBaseline' => $sha256GraphWalkBase === $fixture['sha256ReleaseBaseline'],
    'sha256DeployBaseIsReleaseBaseline' => $sha256DeployBase === $fixture['sha256ReleaseBaseline'],
];
