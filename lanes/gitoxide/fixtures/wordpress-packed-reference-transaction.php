<?php

declare(strict_types=1);

$oldProduction = '134385f6d781b7e97062102c6a483440bfda2a03';
$newProduction = 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f';
$review = '9902e3c3e8f0c569b4ab295ddf473e6de763e1e7';

return [
    'productionRef' => 'refs/heads/production',
    'reviewRef' => 'refs/heads/review/plugin-a',
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
    'expectedPackedNames' => [
        'refs/heads/production',
    ],
    'wordpressUse' => 'A WordPress deployment tool can promote a packed production branch, prune a reviewed plugin branch, and retain a reflog audit trail without invoking git update-ref or pack-refs.',
];
