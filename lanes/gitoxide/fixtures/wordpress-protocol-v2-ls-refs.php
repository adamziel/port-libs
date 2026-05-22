<?php

declare(strict_types=1);

$main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
$releaseTag = 'dce0ea858eef7ff61ad345cc5cdac62203fb3c10';
$releaseObject = '21c9b7500cb144b3169a6537961ec2b9e865be81';

return [
    'capabilities' => implode("\n", [
        'version 2',
        'ls-refs=unborn',
        'fetch=shallow filter ref-in-want sideband-all',
        'server-option',
        'object-format=sha1',
        'agent=git/2.44.0',
        '',
    ]),
    'refPrefixes' => [
        'HEAD',
        'refs/heads/main',
        'refs/tags/wp-release',
        'refs/heads/main',
    ],
    'response' => implode("\n", [
        "{$main} HEAD symref-target:refs/heads/main",
        "{$main} refs/heads/main",
        "{$releaseTag} refs/tags/wp-release peeled:{$releaseObject}",
        'unborn refs/heads/next-release symref-target:refs/heads/main',
        '',
    ]),
    'objects' => [
        'main' => $main,
        'releaseTag' => $releaseTag,
        'releaseObject' => $releaseObject,
    ],
    'wordpressUse' => 'A PHP deployment tool can parse protocol v2 ls-refs capabilities and response lines to discover the active WordPress branch, release tag target, and unborn staging branch before deciding what to fetch.',
];
