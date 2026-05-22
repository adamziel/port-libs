<?php

declare(strict_types=1);

$review = '9902e3c3e8f0c569b4ab295ddf473e6de763e1e7';
$production = 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f';
$namespace = 'site-a';
$headTarget = 'refs/heads/production';

return [
    'namespace' => $namespace,
    'reviewRef' => 'refs/heads/review/plugin-a',
    'productionRef' => 'refs/heads/production',
    'reviewCommit' => $review,
    'productionCommit' => $production,
    'headTarget' => $headTarget,
    'expectedVisibleRefs' => [
        'refs/heads/production',
    ],
    'expectedPhysicalHead' => "ref: refs/namespaces/{$namespace}/{$headTarget}\n",
    'wordpressUse' => 'A multisite WordPress deployment tool can promote a reviewed plugin snapshot and prune the review ref inside a tenant namespace without invoking git update-ref.',
];
