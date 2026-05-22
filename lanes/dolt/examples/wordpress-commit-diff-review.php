<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\CommitDiffTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-commit-diff-review.php';

return [
    'filters' => $fixture['filters'],
    'where' => $fixture['where'],
    'rows' => (new CommitDiffTable())->rows(
        $fixture['snapshots'],
        $fixture['primaryKey'],
        $fixture['filters'],
        $fixture['columns'],
        $fixture['where'],
    ),
];
