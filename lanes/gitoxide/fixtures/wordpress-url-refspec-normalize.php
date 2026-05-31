<?php

declare(strict_types=1);

return [
    'remoteUrl' => 'ssh://Deploy.User@git.example.test/~wp-content/site.git',
    'expectedRemoteUrl' => 'ssh://Deploy.User@git.example.test/~wp-content/site.git',
    'expectedRemoteScheme' => 'ssh',
    'expectedRemoteUser' => 'Deploy.User',
    'expectedRemoteHost' => 'git.example.test',
    'expectedRemotePath' => '~wp-content/site.git',
    'fetchRefspecs' => [
        '+refs/heads/*:refs/remotes/origin/*',
        '^refs/heads/private',
        '',
    ],
    'pushRefspecs' => [
        '+refs/heads/release:refs/heads/wp-release',
        ':refs/heads/old-preview',
        ':',
    ],
    'expectedFetchInstructions' => [
        'fetch-and-update',
        'fetch-exclude',
        'fetch-only',
    ],
    'expectedFetchPrefixes' => [
        'refs/heads/',
        null,
        'HEAD',
    ],
    'expectedPushInstructions' => [
        'push-matching',
        'push-delete',
        'push-all-matching-branches',
    ],
    'expectedPushPrefixes' => [
        'refs/heads/wp-release',
        'refs/heads/old-preview',
        null,
    ],
];
