<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

echo json_encode([
    'logicalSpacingAfterPhysicalFallback' => $block->setProperty(
        'margin-inline-start: var(--wp--preset--spacing--40); margin-left: 2rem',
        'margin-inline-start',
        'var(--wp--preset--spacing--50)'
    ),
    'physicalSpacingAfterLogicalFallback' => $block->setProperty(
        'padding-left: 1rem; padding-inline-start: var(--wp--preset--spacing--30)',
        'padding-left',
        '2rem'
    ),
], JSON_PRETTY_PRINT) . "\n";
