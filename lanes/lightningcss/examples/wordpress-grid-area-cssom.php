<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'grid-area: content-start / sidebar-start / content-end / sidebar-end';
$block = new DeclarationBlock();

echo json_encode([
    'gridRowStart' => $block->getProperty($declarations, 'grid-row-start'),
    'gridRow' => $block->getProperty($declarations, 'grid-row'),
    'gridColumn' => $block->getProperty($declarations, 'grid-column'),
], JSON_PRETTY_PRINT) . "\n";
