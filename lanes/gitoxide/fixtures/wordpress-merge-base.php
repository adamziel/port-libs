<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;

$oid = static fn (string $hex): string => str_repeat($hex, 40);
$oid256 = static fn (string $hex): string => str_repeat($hex, 64);
$commit = static fn (array $parents = []): Commit => new Commit(
    $oid('f'),
    $parents,
    'Release Bot <release@example.test> 1700000000 +0000',
    'Deploy Bot <deploy@example.test> 1700000000 +0000',
    "WordPress deployment branch\n",
    [
        'tree' => [$oid('f')],
        'parent' => $parents,
        'author' => ['Release Bot <release@example.test> 1700000000 +0000'],
        'committer' => ['Deploy Bot <deploy@example.test> 1700000000 +0000'],
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
        $sha256Root => $commit256(),
        $sha256Release => $commit256([$sha256Root]),
        $sha256PluginReview => $commit256([$sha256Release]),
        $sha256ThemeReview => $commit256([$sha256Release]),
        $sha256Deploy => $commit256([$sha256PluginReview, $sha256ThemeReview]),
        $sha256ArchiveRoot => $commit256(),
        $sha256ArchivedReview => $commit256([$sha256ArchiveRoot]),
    ],
];
