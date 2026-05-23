<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\SchemaShowRenderer;

$fixture = require dirname(__DIR__) . '/fixtures/wp-schema-show-check-survival.php';
$renderer = new SchemaShowRenderer();

return [
    'schemaShow' => $renderer->render($fixture['tables'], $fixture['requestedTables'], $fixture['commit']),
    'missingTables' => $renderer->missingTables($fixture['tables'], ['wp_missing_import_audit']),
];
