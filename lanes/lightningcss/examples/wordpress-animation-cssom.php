<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'animation: core-block-fade 240ms ease-out both';
$block = new DeclarationBlock();

echo json_encode([
    'animationName' => $block->getProperty($declarations, 'animation-name'),
    'rewrittenDeclarations' => $block->setProperty($declarations, 'animation-name', 'wp-block-fade-in'),
    'multiNameFallback' => $block->setProperty('animation: core-block-fade 240ms', 'animation-name', 'wp-block-fade-in, wp-block-slide-up'),
], JSON_PRETTY_PRINT) . "\n";
