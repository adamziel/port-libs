<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\PatchRenderer;

$fixture = require dirname(__DIR__) . '/fixtures/wp-binary-patch-review.php';
$rows = (new PatchRenderer())->rows($fixture['tables'], [
    'fromCommit' => $fixture['fromCommit'],
    'toCommit' => $fixture['toCommit'],
    'filter' => 'data',
]);

return [
    'rows' => $rows,
    'statements' => array_column($rows, 'statement'),
];
