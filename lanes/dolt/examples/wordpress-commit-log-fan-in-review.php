<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\CommitLogTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-commit-log-fan-in-review.php';
$table = new CommitLogTable();

return [
    'log' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'showParents' => true,
        'decorate' => 'short',
    ]),
    'cliGraph' => $table->renderLog($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'graph' => true,
        'decorate' => 'short',
    ]),
    'cliGraphOneline' => $table->renderLog($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'graph' => true,
        'oneline' => true,
        'decorate' => 'short',
    ]),
];
