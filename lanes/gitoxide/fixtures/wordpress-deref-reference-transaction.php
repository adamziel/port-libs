<?php

declare(strict_types=1);

$oldProduction = '134385f6d781b7e97062102c6a483440bfda2a03';
$newProduction = 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f';

return [
    'headRef' => 'HEAD',
    'productionRef' => 'refs/heads/production',
    'oldProductionCommit' => $oldProduction,
    'newProductionCommit' => $newProduction,
    'committer' => [
        'name' => 'WordPress Deploy Bot',
        'email' => 'deploy@example.com',
        'time' => '1770000000 +0000',
    ],
    'message' => 'deploy: publish through symbolic HEAD',
    'expectedHeadContents' => "ref: refs/heads/production\n",
    'expectedEditModes' => [
        'only',
        'and-reference',
    ],
    'expectedDeleteEditModes' => [
        'only',
        'only',
    ],
    'expectedDeleteUpdatesReference' => [
        false,
        false,
    ],
    'wordpressUse' => 'A WordPress deployment tool can update the production branch through symbolic HEAD, keep HEAD symbolic, write audit reflogs for both HEAD and the branch, and later prune those reflogs through the same dereferenced symbolic split without invoking git update-ref.',
];
