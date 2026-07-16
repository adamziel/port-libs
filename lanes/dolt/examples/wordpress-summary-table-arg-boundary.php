<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\DiffSummaryRenderer;
use PortLibs\Dolt\TableDeltaMatcher;

$fixture = require dirname(__DIR__) . '/fixtures/wp-table-deltas.php';
$rows = (new TableDeltaMatcher())->summaryRows($fixture['fromTables'], $fixture['toTables']);
$renderer = new DiffSummaryRenderer();

return [
    'firstChangedTable' => $renderer->renderForTableArgs($rows, ['wp_import_audit']),
    'laterDroppedTable' => $renderer->renderForTableArgs($rows, ['wp_legacy_links']),
    'genericFilteredTable' => $renderer->render($rows, ['tableNames' => ['wp_legacy_links']]),
];
