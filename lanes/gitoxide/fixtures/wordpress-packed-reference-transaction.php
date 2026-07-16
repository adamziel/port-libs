<?php

declare(strict_types=1);

$oldProduction = '134385f6d781b7e97062102c6a483440bfda2a03';
$newProduction = 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f';
$review = '9902e3c3e8f0c569b4ab295ddf473e6de763e1e7';

return [
    'productionRef' => 'refs/heads/production',
    'reviewRef' => 'refs/heads/review/plugin-a',
    'releaseRef' => 'refs/tags/wp-release-v2026.05',
    'oldProductionCommit' => $oldProduction,
    'newProductionCommit' => $newProduction,
    'reviewCommit' => $review,
    'packedRefs' => "# pack-refs with: peeled fully-peeled sorted \n"
        . "{$oldProduction} refs/heads/production\n"
        . "{$review} refs/heads/review/plugin-a\n",
    'committer' => [
        'name' => 'WordPress Deploy Bot',
        'email' => 'deploy@example.com',
        'time' => '1770000000 +0000',
    ],
    'message' => 'deploy: publish packed production branch',
    'releaseCommitBody' => 'tree ' . str_repeat('0', 40) . "\n"
        . "author Release Bot <release@example.com> 1770000000 +0000\n"
        . "committer Release Bot <release@example.com> 1770000000 +0000\n\n"
        . "Publish WordPress release package\n",
    'releaseTagName' => 'wp-release-v2026.05',
    'releaseTagger' => 'Release Bot <release@example.com> 1770000000 +0000',
    'releaseTagMessage' => "WordPress release package\n",
    'expectedPackedNames' => [
        'refs/heads/production',
        'refs/tags/wp-release-v2026.05',
    ],
    'expectedPackedLockFailurePrefix' => 'The lock for the packed-ref file could not be obtained',
    'wordpressUse' => 'A WordPress deployment tool can promote a packed production branch, prune a reviewed plugin branch, peel a packed release tag through native object lookup, retain a reflog audit trail, and refuse concurrent packed-ref deployment locks before leaving partial loose refs, reflogs, or packed refs without invoking git update-ref or pack-refs.',
];
