<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'border: 2px solid var(--wp--preset--color--contrast); border-color: var(--wp--preset--color--primary)';
$block = new DeclarationBlock();

echo json_encode([
    'border' => $block->getProperty($declarations, 'border'),
    'borderTopColor' => $block->getProperty($declarations, 'border-top-color'),
], JSON_PRETTY_PRINT) . "\n";
