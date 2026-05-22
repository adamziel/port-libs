<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\DiffSummaryRenderer;
use PortLibs\Dolt\TableDeltaMatcher;

$fixture = require dirname(__DIR__) . '/fixtures/wp-table-deltas.php';
$rows = (new TableDeltaMatcher())->summaryRows($fixture['fromTables'], $fixture['toTables']);

return (new DiffSummaryRenderer())->render($rows);
