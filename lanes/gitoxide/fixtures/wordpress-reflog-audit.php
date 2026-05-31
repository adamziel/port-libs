<?php

declare(strict_types=1);

$previous = '134385f6d781b7e97062102c6a483440bfda2a03';
$published = 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f';
$rolledBack = '28ce6a8b26aa170e1de65536fe8abe1832bd3242';

return [
    'siteRef' => 'refs/heads/sites/main',
    'previousCommit' => $previous,
    'publishedCommit' => $published,
    'rolledBackCommit' => $rolledBack,
    'committer' => [
        'name' => ' WordPress Deploy Bot ',
        'email' => ' deploy@example.com ',
        'time' => '1770000000 +0000',
    ],
    'messages' => [
        'deploy: publish audited block export',
        'deploy: rollback failed import review',
    ],
    'expectedForwardMessages' => [
        'deploy: publish audited block export',
        'deploy: rollback failed import review',
    ],
    'expectedReverseNewOids' => [
        $rolledBack,
        $published,
    ],
    'wordpressUse' => 'A WordPress deployment tool can append and parse native reflog audit entries for a site content branch, then show the newest deployment or rollback first without invoking git reflog.',
];
