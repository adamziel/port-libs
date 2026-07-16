<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\DiffSummaryRenderer;
use PortLibs\Dolt\TableDeltaMatcher;

$fixture = require dirname(__DIR__) . '/fixtures/wp-table-deltas.php';
$rows = (new TableDeltaMatcher())->summaryRows($fixture['fromTables'], $fixture['toTables']);
$renderer = new DiffSummaryRenderer();

return [
    'renamedSummary' => $renderer->render($rows, ['filter' => TableDeltaMatcher::DIFF_RENAMED]),
    'addedSummary' => $renderer->render($rows, ['filter' => TableDeltaMatcher::DIFF_ADDED]),
    'droppedNames' => $renderer->renderNameOnly($rows, ['filter' => 'removed']),
];
