<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\TableDiff;

$fixture = require dirname(__DIR__) . '/fixtures/wp-posts-diff.php';

return (new TableDiff())->diffTableRows(
    $fixture['fromRows'],
    $fixture['toRows'],
    'ID',
    $fixture['columns'],
    $fixture['fromCommit'],
    null,
    $fixture['toCommit'],
    null,
);
