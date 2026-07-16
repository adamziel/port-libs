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

$deletedBaseCommand = $choice->driver->prepareCommand(
    $fixture['deletedBase']['ancestor'],
    $fixture['deletedBase']['current'],
    $fixture['deletedBase']['other'],
    $fixture['relativePath'],
    $fixture['ancestorLabel'],
    $fixture['currentLabel'],
    $fixture['otherLabel'],
    $fixture['markerSize'],
    worktreeDir: $worktree,
);

$tooLargeError = null;
try {
    $choice->driver->prepareCommand(
        $fixture['tooLargeMedia']['ancestor'],
        $fixture['tooLargeMedia']['current'],
        $fixture['tooLargeMedia']['other'],
        'wp-content/uploads/hero.avif',
        worktreeDir: $worktree,
        largeFileThresholdBytes: $fixture['tooLargeMedia']['threshold'],
    );
} catch (RuntimeException $exception) {
    $tooLargeError = $exception->getMessage();
}

$currentBuffer = file_get_contents($command->currentPath);
$result = $command->run(static function ($prepared) use ($fixture): int {
    file_put_contents($prepared->currentPath, $fixture['expectedMerged']);

    return 0;
});

$summary = [
    'driver' => $choice->name,
    'command' => $command->command,
    'tempFilesUnderWorktree' => str_starts_with($command->ancestorPath, $worktree)
        && str_starts_with($command->currentPath, $worktree)
        && str_starts_with($command->otherPath, $worktree),
    'currentBuffer' => $currentBuffer,
    'mergedBuffer' => $result->content,
    'resultResolution' => $result->resolution,
    'deletedBaseBuffer' => file_get_contents($deletedBaseCommand->ancestorPath),
    'tooLargeMediaRejected' => $tooLargeError !== null,
    'tooLargeMediaError' => $tooLargeError,
    'wordpressUse' => $fixture['wordpressUse'],
];

$command->cleanup();
$deletedBaseCommand->cleanup();
@rmdir($worktree);

return $summary;
