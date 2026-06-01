<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Gitoxide\GitUrl;
use PortLibs\Gitoxide\RefSpec;

$fixture = require __DIR__ . '/../fixtures/wordpress-url-refspec-normalize.php';

$remote = GitUrl::parse($fixture['remoteUrl']);
$emptyPortRemote = GitUrl::parse($fixture['emptyPortRemoteUrl']);
$localMirror = GitUrl::parse($fixture['localMirrorUrl']);
$homeMirror = GitUrl::parse($fixture['homeMirrorUrl']);
$homeMirrorHome = GitUrl::parseHomePath($homeMirror->path());
$homeMirrorShellPath = GitUrl::forShellPath($homeMirror->path());
$homeMirrorExpandedPath = GitUrl::expandHomePath(
    $homeMirror->path(),
    static fn (?string $user): ?string => $user === null
        ? $fixture['currentHomeDirectory']
        : ($fixture['homeDirectories'][$user] ?? null)
);
$relativeMirror = GitUrl::parse($fixture['relativeMirrorUrl']);
$relativeMirrorCanonical = $relativeMirror->canonicalized($fixture['relativeMirrorCurrentDirectory']);
$customHelperRemote = GitUrl::parse($fixture['customHelperRemoteUrl']);
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
$malformedBracketedRemoteRejected = false;
try {
    GitUrl::parse($fixture['malformedBracketedRemoteUrl']);
} catch (InvalidArgumentException) {
    $malformedBracketedRemoteRejected = true;
}
$invalidUtf8RemoteRejected = false;
try {
    GitUrl::parse($fixture['invalidUtf8RemoteUrl']);
} catch (InvalidArgumentException) {
    $invalidUtf8RemoteRejected = true;
}
$hostlessFtpRemoteRejected = false;
try {
    GitUrl::parse($fixture['hostlessFtpRemoteUrl']);
} catch (InvalidArgumentException) {
    $hostlessFtpRemoteRejected = true;
}

$summary = [
    'remote' => $remote->toArray(),
    'emptyPortRemote' => $emptyPortRemote->toArray(),
    'localMirror' => $localMirror->toArray(),
    'homeMirror' => $homeMirror->toArray(),
    'homeMirrorHome' => $homeMirrorHome,
    'homeMirrorShellPath' => $homeMirrorShellPath,
    'homeMirrorExpandedPath' => $homeMirrorExpandedPath,
    'relativeMirrorCanonical' => $relativeMirrorCanonical->toArray(),
    'customHelperRemote' => $customHelperRemote->toArray(),
    'customHelperRemotePathArgumentSafe' => $customHelperRemote->pathArgumentSafe(),
    'fetch' => $fetch,
    'push' => $push,
    'oversizedRemoteRejected' => $oversizedRemoteRejected,
    'malformedBracketedRemoteRejected' => $malformedBracketedRemoteRejected,
    'invalidUtf8RemoteRejected' => $invalidUtf8RemoteRejected,
    'hostlessFtpRemoteRejected' => $hostlessFtpRemoteRejected,
    'deploymentRemoteSafe' => $remote->userArgumentSafe() === $fixture['expectedRemoteUser']
        && $remote->hostArgumentSafe() === $fixture['expectedRemoteHost']
        && $remote->pathArgumentSafe() === $fixture['expectedRemotePath'],
];

$argv = $_SERVER['argv'] ?? [];
if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if ($summary['remote']['normalized'] !== $fixture['expectedRemoteUrl']) {
        throw new RuntimeException('Unexpected normalized remote URL');
    }
    if ($summary['emptyPortRemote']['normalized'] !== $fixture['expectedEmptyPortRemoteUrl']) {
        throw new RuntimeException('Unexpected normalized empty-port remote URL');
    }
    if ($summary['emptyPortRemote']['host'] !== $fixture['expectedEmptyPortRemoteHost']) {
        throw new RuntimeException('Unexpected empty-port remote host');
    }
    if ($summary['emptyPortRemote']['path'] !== $fixture['expectedEmptyPortRemotePath']) {
        throw new RuntimeException('Unexpected empty-port remote path');
    }
    if ($summary['localMirror']['normalized'] !== $fixture['expectedLocalMirrorUrl']) {
        throw new RuntimeException('Unexpected normalized local mirror URL');
    }
    if ($summary['homeMirror']['normalized'] !== $fixture['expectedHomeMirrorUrl']) {
        throw new RuntimeException('Unexpected normalized home mirror URL');
    }
    if ($summary['homeMirrorHome']['user'] !== $fixture['expectedHomeMirrorUser']) {
        throw new RuntimeException('Unexpected home mirror user');
    }
    if ($summary['homeMirrorHome']['path'] !== $fixture['expectedHomeMirrorTail']) {
        throw new RuntimeException('Unexpected home mirror tail path');
    }
    if ($summary['homeMirrorShellPath'] !== $fixture['expectedHomeMirrorShellPath']) {
        throw new RuntimeException('Unexpected shell home path');
    }
    if ($summary['homeMirrorExpandedPath'] !== $fixture['expectedHomeMirrorExpandedPath']) {
        throw new RuntimeException('Unexpected expanded home path');
    }
    if ($summary['relativeMirrorCanonical']['path'] !== $fixture['expectedRelativeMirrorCanonicalPath']) {
        throw new RuntimeException('Unexpected relative mirror canonical path');
    }
    if ($summary['relativeMirrorCanonical']['normalized'] !== $fixture['expectedRelativeMirrorCanonicalUrl']) {
        throw new RuntimeException('Unexpected relative mirror canonical URL');
    }
    if ($summary['customHelperRemote']['normalized'] !== $fixture['expectedCustomHelperRemoteUrl']) {
        throw new RuntimeException('Unexpected normalized custom helper remote URL');
    }
    if ($summary['customHelperRemote']['path'] !== $fixture['expectedCustomHelperRemotePath']) {
        throw new RuntimeException('Unexpected custom helper remote path');
    }
    if ($summary['customHelperRemotePathArgumentSafe'] !== $fixture['expectedCustomHelperRemotePathArgumentSafe']) {
        throw new RuntimeException('Unexpected custom helper remote path safety');
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
    if ($summary['malformedBracketedRemoteRejected'] !== $fixture['expectedMalformedBracketedRemoteRejected']) {
        throw new RuntimeException('Unexpected malformed bracketed remote URL preflight result');
    }
    if ($summary['invalidUtf8RemoteRejected'] !== $fixture['expectedInvalidUtf8RemoteRejected']) {
        throw new RuntimeException('Unexpected invalid UTF-8 remote URL preflight result');
    }
    if ($summary['hostlessFtpRemoteRejected'] !== $fixture['expectedHostlessFtpRemoteRejected']) {
        throw new RuntimeException('Unexpected hostless FTP remote URL preflight result');
    }
}

return $summary;
