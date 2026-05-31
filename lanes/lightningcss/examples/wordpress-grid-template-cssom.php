<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'grid-template-areas: "header header" "content sidebar"; grid-template-rows: [header-start] auto [header-end content-start] 1fr [content-end]; grid-template-columns: minmax(0, 1fr) 18rem; grid-auto-flow: row; grid-auto-rows: auto; grid-auto-columns: auto';
$block = new DeclarationBlock();

echo json_encode([
    'gridTemplate' => $block->getProperty($declarations, 'grid-template'),
    'grid' => $block->getProperty($declarations, 'grid'),
], JSON_PRETTY_PRINT) . "\n";
