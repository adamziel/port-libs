<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\TableDeltaMatcher;

$fixture = require dirname(__DIR__) . '/fixtures/wp-table-deltas.php';

return (new TableDeltaMatcher())->summaries($fixture['fromTables'], $fixture['toTables']);
