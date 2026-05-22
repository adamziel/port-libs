<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\PatchRenderer;

$fixture = require dirname(__DIR__) . '/fixtures/wp-patch-review.php';
$renderer = new PatchRenderer();

return [
    'all' => $renderer->rows($fixture['tables'], [
        'fromCommit' => $fixture['fromCommit'],
        'toCommit' => $fixture['toCommit'],
    ]),
    'schema' => $renderer->rows($fixture['tables'], [
        'fromCommit' => $fixture['fromCommit'],
        'toCommit' => $fixture['toCommit'],
        'filter' => 'schema',
    ]),
    'data' => $renderer->rows($fixture['tables'], [
        'fromCommit' => $fixture['fromCommit'],
        'toCommit' => $fixture['toCommit'],
        'filter' => 'data',
    ]),
];
