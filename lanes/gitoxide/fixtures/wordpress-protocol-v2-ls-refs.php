<?php

declare(strict_types=1);

$main = '9b0fc92260312ce44e74ef369f5f4b4d6fd6672f54064da57e9450051e7f5a3c';
$releaseTag = '3b7a0f55a20c3fe6c451bb9f551e2f7f0d4091b6bb0d0ad0a39fc4fcd4b6d1a9';
$releaseObject = '6d0f02a4db7bc9a514f45a0bb3ee4a25e0f6be41832fe3482077a9f6cf2c04d8';
$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';
$delimiter = '0001';

$capabilityLines = [
    'version 2',
    'ls-refs=unborn',
    'fetch=shallow filter ref-in-want sideband-all',
    'server-option',
    'object-format=sha256',
    'agent=git/2.44.0',
];
$responseLines = [
    "{$main} HEAD symref-target:refs/heads/main",
    "{$main} refs/heads/main",
    "{$releaseTag} refs/tags/wp-release peeled:{$releaseObject}",
    'unborn refs/heads/next-release symref-target:refs/heads/main',
];

return [
    'capabilities' => implode("\n", [...$capabilityLines, '']),
    'capabilityAdvertisement' => $packet("# service=git-upload-pack\n")
        . $flush
        . implode('', array_map(static fn (string $line): string => $packet($line . "\n"), $capabilityLines))
        . $flush,
    'fetchRefspecs' => [
        'HEAD',
        'main',
        'wp-release',
        'refs/heads/main',
    ],
    'refPrefixes' => [
        'HEAD',
        'main',
        'refs/main',
        'refs/tags/main',
        'refs/heads/main',
        'refs/remotes/main',
        'refs/remotes/main/HEAD',
        'wp-release',
        'refs/wp-release',
        'refs/tags/wp-release',
        'refs/heads/wp-release',
        'refs/remotes/wp-release',
        'refs/remotes/wp-release/HEAD',
    ],
    'requestBytes' => $packet("command=ls-refs\n")
        . $packet("agent=port-libs/0.1\n")
        . $delimiter
        . $packet("symrefs\n")
        . $packet("peel\n")
        . $packet("unborn\n")
        . $packet("ref-prefix HEAD\n")
        . $packet("ref-prefix main\n")
        . $packet("ref-prefix refs/main\n")
        . $packet("ref-prefix refs/tags/main\n")
        . $packet("ref-prefix refs/heads/main\n")
        . $packet("ref-prefix refs/remotes/main\n")
        . $packet("ref-prefix refs/remotes/main/HEAD\n")
        . $packet("ref-prefix wp-release\n")
        . $packet("ref-prefix refs/wp-release\n")
        . $packet("ref-prefix refs/tags/wp-release\n")
        . $packet("ref-prefix refs/heads/wp-release\n")
        . $packet("ref-prefix refs/remotes/wp-release\n")
        . $packet("ref-prefix refs/remotes/wp-release/HEAD\n")
        . $flush,
    'response' => implode("\n", [...$responseLines, '']),
    'responseAdvertisement' => implode('', array_map(static fn (string $line): string => $packet($line . "\n"), $responseLines)) . $flush,
    'objects' => [
        'main' => $main,
        'releaseTag' => $releaseTag,
        'releaseObject' => $releaseObject,
    ],
    'wordpressUse' => 'A PHP deployment tool can parse service-announced protocol v2 ls-refs capabilities, expand shorthand fetch refspecs into Gitoxide-style DWIM ref-prefix request lines, and parse SHA-256 response lines to discover the active WordPress branch, release tag target, and unborn staging branch before deciding what to fetch.',
];
