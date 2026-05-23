<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BuiltinDriver;
use PortLibs\Gitoxide\ExternalMergeDriver;

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-external-merge-driver.php';

$choice = ExternalMergeDriver::select(
    BuiltinDriver::ATTRIBUTE_VALUE,
    $fixture['attributeValue'],
    $fixture['drivers'],
);

$worktree = sys_get_temp_dir() . '/gitoxide-wordpress-external-driver-' . bin2hex(random_bytes(4));
if (!mkdir($worktree, 0700) && !is_dir($worktree)) {
    throw new RuntimeException("Could not create temp directory: {$worktree}");
}

$command = $choice->driver?->prepareCommand(
    $fixture['ancestor'],
    $fixture['current'],
    $fixture['other'],
    $fixture['relativePath'],
    $fixture['ancestorLabel'],
    $fixture['currentLabel'],
    $fixture['otherLabel'],
    $fixture['markerSize'],
    worktreeDir: $worktree,
);

if ($command === null) {
    throw new RuntimeException('Expected an external WordPress merge driver');
}

$summary = [
    'driver' => $choice->name,
    'command' => $command->command,
    'tempFilesUnderWorktree' => str_starts_with($command->ancestorPath, $worktree)
        && str_starts_with($command->currentPath, $worktree)
        && str_starts_with($command->otherPath, $worktree),
    'currentBuffer' => file_get_contents($command->currentPath),
    'wordpressUse' => $fixture['wordpressUse'],
];

$command->cleanup();
@rmdir($worktree);

return $summary;
