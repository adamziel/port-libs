<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;

$oid = static fn (string $hex): string => str_repeat($hex, 40);
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

$root = $oid('1');
$release = $oid('2');
$pluginReview = $oid('3');
$themeReview = $oid('4');
$contentReview = $oid('5');
$deployment = $oid('6');
$archiveRoot = $oid('7');
$archivedPluginReview = $oid('8');

return [
    'root' => $root,
    'releaseBaseline' => $release,
    'pluginReview' => $pluginReview,
    'themeReview' => $themeReview,
    'contentReview' => $contentReview,
    'deployment' => $deployment,
    'archiveRoot' => $archiveRoot,
    'archivedPluginReview' => $archivedPluginReview,
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
    ],
];
