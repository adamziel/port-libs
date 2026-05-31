<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Gitoxide\GitUrl;
use PortLibs\Gitoxide\RefSpec;

$fixture = require __DIR__ . '/../fixtures/wordpress-url-refspec-normalize.php';

$remote = GitUrl::parse($fixture['remoteUrl']);
$localMirror = GitUrl::parse($fixture['localMirrorUrl']);
$fetch = array_map(
    static fn (string $spec): array => RefSpec::parseFetch($spec)->toArray(),
    $fixture['fetchRefspecs']
);
$push = array_map(
    static fn (string $spec): array => RefSpec::parsePush($spec)->toArray(),
    $fixture['pushRefspecs']
);
$oversizedRemoteRejected = false;
try {
    GitUrl::parse($fixture['oversizedRemoteUrl']);
} catch (InvalidArgumentException) {
    $oversizedRemoteRejected = true;
}

$summary = [
    'remote' => $remote->toArray(),
    'localMirror' => $localMirror->toArray(),
    'fetch' => $fetch,
    'push' => $push,
    'oversizedRemoteRejected' => $oversizedRemoteRejected,
    'deploymentRemoteSafe' => $remote->userArgumentSafe() === $fixture['expectedRemoteUser']
        && $remote->hostArgumentSafe() === $fixture['expectedRemoteHost']
        && $remote->pathArgumentSafe() === $fixture['expectedRemotePath'],
];

$argv = $_SERVER['argv'] ?? [];
if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if ($summary['remote']['normalized'] !== $fixture['expectedRemoteUrl']) {
        throw new RuntimeException('Unexpected normalized remote URL');
    }
    if ($summary['localMirror']['normalized'] !== $fixture['expectedLocalMirrorUrl']) {
        throw new RuntimeException('Unexpected normalized local mirror URL');
    }
    if (array_column($summary['fetch'], 'instruction') !== $fixture['expectedFetchInstructions']) {
        throw new RuntimeException('Unexpected fetch refspec instructions');
    }
    if (array_column($summary['fetch'], 'normalized') !== $fixture['expectedFetchNormalized']) {
        throw new RuntimeException('Unexpected normalized fetch refspecs');
    }
    if (array_column($summary['fetch'], 'expandedPrefixes') !== $fixture['expectedFetchExpandedPrefixes']) {
        throw new RuntimeException('Unexpected expanded fetch refspec prefixes');
    }
    if (array_column($summary['push'], 'instruction') !== $fixture['expectedPushInstructions']) {
        throw new RuntimeException('Unexpected push refspec instructions');
    }
    if (array_column($summary['push'], 'normalized') !== $fixture['expectedPushNormalized']) {
        throw new RuntimeException('Unexpected normalized push refspecs');
    }
    if ($summary['oversizedRemoteRejected'] !== $fixture['expectedOversizedRemoteRejected']) {
        throw new RuntimeException('Unexpected oversized remote URL preflight result');
    }
}

return $summary;
