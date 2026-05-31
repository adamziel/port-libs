<?php

declare(strict_types=1);

return [
    'remoteUrl' => 'ssh://Deploy.User@git.example.test/~wp-content/site.git',
    'expectedRemoteUrl' => 'ssh://Deploy.User@git.example.test/~wp-content/site.git',
    'expectedRemoteScheme' => 'ssh',
    'expectedRemoteUser' => 'Deploy.User',
    'expectedRemoteHost' => 'git.example.test',
    'expectedRemotePath' => '~wp-content/site.git',
    'localMirrorUrl' => 'file://Deploy@[::1]/var/cache/wp-content/site.git',
    'expectedLocalMirrorUrl' => 'file://Deploy@[::1]/var/cache/wp-content/site.git',
    'expectedLocalMirrorScheme' => 'file',
    'expectedLocalMirrorUser' => 'Deploy',
    'expectedLocalMirrorHost' => '[::1]',
    'expectedLocalMirrorPath' => '/var/cache/wp-content/site.git',
    'fetchRefspecs' => [
        '+refs/heads/*:refs/remotes/origin/*',
        '^refs/heads/private',
        '+',
        '',
    ],
    'pushRefspecs' => [
        '+refs/heads/release:refs/heads/wp-release',
        '+:refs/heads/stale-preview',
        ':refs/heads/old-preview',
        'refs/heads/wp-content',
        ':',
    ],
    'expectedFetchInstructions' => [
        'fetch-and-update',
        'fetch-exclude',
        'fetch-only',
        'fetch-only',
    ],
    'expectedFetchNormalized' => [
        '+refs/heads/*:refs/remotes/origin/*',
        '^refs/heads/private',
        'HEAD',
        'HEAD',
    ],
    'expectedFetchPrefixes' => [
        'refs/heads/',
        null,
        'HEAD',
        'HEAD',
    ],
    'expectedPushInstructions' => [
        'push-matching',
        'push-delete',
        'push-delete',
        'push-matching',
        'push-all-matching-branches',
    ],
    'expectedPushNormalized' => [
        '+refs/heads/release:refs/heads/wp-release',
        ':refs/heads/stale-preview',
        ':refs/heads/old-preview',
        'refs/heads/wp-content:refs/heads/wp-content',
        ':',
    ],
    'expectedPushPrefixes' => [
        'refs/heads/wp-release',
        'refs/heads/stale-preview',
        'refs/heads/old-preview',
        null,
        null,
    ],
];
