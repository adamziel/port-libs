<?php

declare(strict_types=1);

$base = '134385f6d781b7e97062102c6a483440bfda2a03';
$review = '9902e3c3e8f0c569b4ab295ddf473e6de763e1e7';
$remote = 'b3109a7e51fc593f85b145a76c70ddd1d133fafd';

return [
    'namespace' => 'site-a',
    'otherNamespace' => 'site-b',
    'baseCommit' => $base,
    'reviewCommit' => $review,
    'remoteCommit' => $remote,
    'packedRefs' => "# pack-refs with: peeled fully-peeled sorted\n"
        . "{$base} refs/heads/main\n"
        . "{$base} refs/namespaces/site-a/refs/heads/review/plugin-a\n"
        . "{$remote} refs/namespaces/site-a/refs/remotes/origin/review/plugin-a\n"
        . "{$base} refs/namespaces/site-b/refs/heads/review/plugin-a\n",
    'looseRefs' => [
        'refs/namespaces/site-a/refs/heads/review/plugin-a' => $review . "\n",
        'refs/namespaces/site-a/refs/review-alias' => "ref: refs/namespaces/site-a/refs/heads/review/plugin-a\n",
        'refs/namespaces/site-a/refs/tags/plugin-a-review' => $review . "\n",
        'refs/namespaces/site-b/refs/heads/review/plugin-a' => $base . "\n",
    ],
    'expectedStoreNames' => [
        'refs/heads/review/plugin-a',
        'refs/remotes/origin/review/plugin-a',
        'refs/review-alias',
        'refs/tags/plugin-a-review',
    ],
    'expectedLooseNames' => [
        'refs/heads/review/plugin-a',
        'refs/review-alias',
        'refs/tags/plugin-a-review',
    ],
    'expectedFullNamespacedNames' => [
        'refs/namespaces/site-a/refs/heads/review/plugin-a',
        'refs/namespaces/site-a/refs/remotes/origin/review/plugin-a',
        'refs/namespaces/site-a/refs/review-alias',
        'refs/namespaces/site-a/refs/tags/plugin-a-review',
    ],
];
