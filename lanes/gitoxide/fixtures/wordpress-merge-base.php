<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;

$oid = static fn (string $hex): string => str_repeat($hex, 40);
$oidPair = static fn (string $hex): string => str_repeat($hex, 20);
$oid256 = static fn (string $hex): string => str_repeat($hex, 64);
$commit = static fn (array $parents = [], int $seconds = 1700000000): Commit => new Commit(
    $oid('f'),
    $parents,
    "Release Bot <release@example.test> {$seconds} +0000",
    "Deploy Bot <deploy@example.test> {$seconds} +0000",
    "WordPress deployment branch\n",
    [
        'tree' => [$oid('f')],
        'parent' => $parents,
        'author' => ["Release Bot <release@example.test> {$seconds} +0000"],
        'committer' => ["Deploy Bot <deploy@example.test> {$seconds} +0000"],
    ],
);
$commit256 = static fn (array $parents = []): Commit => new Commit(
    $oid256('f'),
    $parents,
    'Release Bot <release@example.test> 1700000000 +0000',
    'Deploy Bot <deploy@example.test> 1700000000 +0000',
    "WordPress SHA-256 deployment branch\n",
    [
        'tree' => [$oid256('f')],
        'parent' => $parents,
        'author' => ['Release Bot <release@example.test> 1700000000 +0000'],
        'committer' => ['Deploy Bot <deploy@example.test> 1700000000 +0000'],
    ],
);

$root = $oid('1');
$release = $oid('2');
$pluginReview = $oid('3');
$themeReview = $oid('4');
$contentReview = $oid('5');
$deployment = $oid('6');
$archiveRoot = $oid('7');
$archivedPluginReview = $oid('8');
$legacyBaseline = $oid('9');
$securityBaseline = $oid('a');
$pluginHotfixReview = $oid('b');
$themeHotfixReview = $oid('c');
$legacyOnlyReview = $oid('d');
$compatibilityIntermediate = $oidPair('d1');
$legacyDeepBaseline = $oidPair('d2');
$securityShallowBaseline = $oidPair('d3');
$pluginCompatibilityReview = $oidPair('d4');
$themeCompatibilityReview = $oidPair('d5');
$shallowMissingGrandparent = $oidPair('e0');
$shallowStaleParent = $oidPair('e1');
$shallowReleaseBaseline = $oidPair('e2');
$shallowPluginReview = $oidPair('e3');
$shallowThemeReview = $oidPair('e4');
$shallowArchiveReview = $oidPair('e5');
$sha256Root = $oid256('1');
$sha256Release = $oid256('2');
$sha256PluginReview = $oid256('a');
$sha256ThemeReview = $oid256('b');
$sha256Deploy = $oid256('c');
$sha256ArchiveRoot = $oid256('d');
$sha256ArchivedReview = $oid256('e');

return [
    'root' => $root,
    'releaseBaseline' => $release,
    'pluginReview' => $pluginReview,
    'themeReview' => $themeReview,
    'contentReview' => $contentReview,
    'deployment' => $deployment,
    'archiveRoot' => $archiveRoot,
    'archivedPluginReview' => $archivedPluginReview,
    'legacyBaseline' => $legacyBaseline,
    'securityBaseline' => $securityBaseline,
    'pluginHotfixReview' => $pluginHotfixReview,
    'themeHotfixReview' => $themeHotfixReview,
    'legacyOnlyReview' => $legacyOnlyReview,
    'hotfixHeads' => [$pluginHotfixReview, $themeHotfixReview],
    'octopusSpecialHeads' => [$pluginHotfixReview, $themeHotfixReview, $legacyOnlyReview],
    'octopusReorderedHeads' => [$pluginHotfixReview, $legacyOnlyReview, $themeHotfixReview],
    'legacyDeepBaseline' => $legacyDeepBaseline,
    'securityShallowBaseline' => $securityShallowBaseline,
    'pluginCompatibilityReview' => $pluginCompatibilityReview,
    'themeCompatibilityReview' => $themeCompatibilityReview,
    'compatibilityHeads' => [$pluginCompatibilityReview, $themeCompatibilityReview],
    'shallowMissingGrandparent' => $shallowMissingGrandparent,
    'shallowStaleParent' => $shallowStaleParent,
    'shallowReleaseBaseline' => $shallowReleaseBaseline,
    'shallowPluginReview' => $shallowPluginReview,
    'shallowThemeReview' => $shallowThemeReview,
    'shallowArchiveReview' => $shallowArchiveReview,
    'shallowGraphWalkOthers' => [$shallowThemeReview, $shallowArchiveReview],
    'sha256ReleaseBaseline' => $sha256Release,
    'sha256PluginReview' => $sha256PluginReview,
    'sha256ThemeReview' => $sha256ThemeReview,
    'sha256Deploy' => $sha256Deploy,
    'sha256ArchivedReview' => $sha256ArchivedReview,
    'sha256ReviewHeads' => [$sha256PluginReview, $sha256ThemeReview],
    'sha256GraphWalkOthers' => [$sha256ThemeReview, $sha256ArchivedReview],
    'heads' => [$pluginReview, $themeReview, $contentReview],
    'deploymentHeads' => [$deployment, $pluginReview, $themeReview],
    'graphWalkHeads' => [$pluginReview, $themeReview, $archivedPluginReview],
    'graphWalkOthers' => [$themeReview, $archivedPluginReview],
    'commits' => [
        $root => $commit(),
        $release => $commit([$root]),
        $pluginReview => $commit([$release]),
        $themeReview => $commit([$release]),
        $contentReview => $commit([$release]),
        $deployment => $commit([$pluginReview, $themeReview]),
        $archiveRoot => $commit(),
        $archivedPluginReview => $commit([$archiveRoot]),
        $legacyBaseline => $commit([$release], 1700000100),
        $securityBaseline => $commit([$release], 1700000200),
        $pluginHotfixReview => $commit([$legacyBaseline, $securityBaseline], 1700000300),
        $themeHotfixReview => $commit([$securityBaseline, $legacyBaseline], 1700000400),
        $legacyOnlyReview => $commit([$legacyBaseline], 1700000500),
        $compatibilityIntermediate => $commit([$release], 1700000050),
        $legacyDeepBaseline => $commit([$compatibilityIntermediate], 1700000060),
        $securityShallowBaseline => $commit([$release], 1700000500),
        $pluginCompatibilityReview => $commit([$legacyDeepBaseline, $securityShallowBaseline], 1700000600),
        $themeCompatibilityReview => $commit([$securityShallowBaseline, $legacyDeepBaseline], 1700000700),
        $shallowStaleParent => $commit([$shallowMissingGrandparent], 1699999900),
        $shallowReleaseBaseline => $commit([$shallowStaleParent], 1700000800),
        $shallowPluginReview => $commit([$shallowReleaseBaseline], 1700000900),
        $shallowThemeReview => $commit([$shallowReleaseBaseline], 1700001000),
        $shallowArchiveReview => $commit([], 1700001100),
        $sha256Root => $commit256(),
        $sha256Release => $commit256([$sha256Root]),
        $sha256PluginReview => $commit256([$sha256Release]),
        $sha256ThemeReview => $commit256([$sha256Release]),
        $sha256Deploy => $commit256([$sha256PluginReview, $sha256ThemeReview]),
        $sha256ArchiveRoot => $commit256(),
        $sha256ArchivedReview => $commit256([$sha256ArchiveRoot]),
    ],
];
