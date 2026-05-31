<?php

declare(strict_types=1);

$main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
$releaseTag = 'dce0ea858eef7ff61ad345cc5cdac62203fb3c10';
$releaseObject = '21c9b7500cb144b3169a6537961ec2b9e865be81';
$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';
$delimiter = '0001';

$capabilityLines = [
    'version 2',
    'ls-refs=unborn',
    'fetch=shallow filter ref-in-want sideband-all',
    'server-option',
    'object-format=sha1',
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
    'capabilityAdvertisement' => implode('', array_map(static fn (string $line): string => $packet($line . "\n"), $capabilityLines)) . $flush,
    'refPrefixes' => [
        'HEAD',
        'refs/heads/main',
        'refs/tags/wp-release',
        'refs/heads/main',
    ],
    'requestBytes' => $packet("command=ls-refs\n")
        . $packet("agent=port-libs/0.1\n")
        . $delimiter
        . $packet("symrefs\n")
        . $packet("peel\n")
        . $packet("unborn\n")
        . $packet("ref-prefix HEAD\n")
        . $packet("ref-prefix refs/heads/main\n")
        . $packet("ref-prefix refs/tags/wp-release\n")
        . $flush,
    'response' => implode("\n", [...$responseLines, '']),
    'responseAdvertisement' => implode('', array_map(static fn (string $line): string => $packet($line . "\n"), $responseLines)) . $flush,
    'objects' => [
        'main' => $main,
        'releaseTag' => $releaseTag,
        'releaseObject' => $releaseObject,
    ],
    'wordpressUse' => 'A PHP deployment tool can parse protocol v2 ls-refs capabilities and response lines to discover the active WordPress branch, release tag target, and unborn staging branch before deciding what to fetch.',
];
