<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Gitoxide\GitUrl;
use PortLibs\Gitoxide\RefSpec;

$fixture = require __DIR__ . '/../fixtures/wordpress-url-refspec-normalize.php';

$remote = GitUrl::parse($fixture['remoteUrl']);
$fetch = array_map(
    static fn (string $spec): array => RefSpec::parseFetch($spec)->toArray(),
    $fixture['fetchRefspecs']
);
$push = array_map(
    static fn (string $spec): array => RefSpec::parsePush($spec)->toArray(),
    $fixture['pushRefspecs']
);

$summary = [
    'remote' => $remote->toArray(),
    'fetch' => $fetch,
    'push' => $push,
    'deploymentRemoteSafe' => $remote->userArgumentSafe() === $fixture['expectedRemoteUser']
        && $remote->hostArgumentSafe() === $fixture['expectedRemoteHost']
        && $remote->pathArgumentSafe() === $fixture['expectedRemotePath'],
];

$argv = $_SERVER['argv'] ?? [];
if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if ($summary['remote']['normalized'] !== $fixture['expectedRemoteUrl']) {
        throw new RuntimeException('Unexpected normalized remote URL');
    }
    if (array_column($summary['fetch'], 'instruction') !== $fixture['expectedFetchInstructions']) {
        throw new RuntimeException('Unexpected fetch refspec instructions');
    }
    if (array_column($summary['push'], 'instruction') !== $fixture['expectedPushInstructions']) {
        throw new RuntimeException('Unexpected push refspec instructions');
    }
}

return $summary;
