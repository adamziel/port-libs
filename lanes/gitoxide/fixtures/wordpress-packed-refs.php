<?php

declare(strict_types=1);

return [
    'content' => "# pack-refs with: peeled fully-peeled sorted\n"
        . "134385f6d781b7e97062102c6a483440bfda2a03 refs/heads/main\n"
        . "a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f refs/remotes/origin/main\n"
        . "b3109a7e51fc593f85b145a76c70ddd1d133fafd refs/tags/wp-content-release\n"
        . "^134385f6d781b7e97062102c6a483440bfda2a03\n",
    'branch' => 'main',
    'remoteBranch' => 'origin/main',
    'releaseTag' => 'wp-content-release',
    'releaseCandidateRef' => 'refs/heads/release-candidate',
    'branchCommit' => '134385f6d781b7e97062102c6a483440bfda2a03',
    'remoteCommit' => 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f',
    'tagObject' => 'b3109a7e51fc593f85b145a76c70ddd1d133fafd',
    'tagCommit' => '134385f6d781b7e97062102c6a483440bfda2a03',
    'expectedPeeledHeads' => [
        [
            'name' => 'refs/heads/main',
            'commit' => '134385f6d781b7e97062102c6a483440bfda2a03',
            'source' => 'packed',
        ],
        [
            'name' => 'refs/tags/wp-content-release',
            'commit' => '134385f6d781b7e97062102c6a483440bfda2a03',
            'source' => 'packed',
        ],
    ],
    'expectedHeaderPeeledState' => 'fully',
    'expectedMissingReleaseLookup' => true,
    'wordpressUse' => 'Packed refs let a PHP deployment tool inspect compacted branch and release-tag state for a WordPress repository without invoking git.',
];
