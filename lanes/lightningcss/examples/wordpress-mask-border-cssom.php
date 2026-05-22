<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$declarations = 'mask-border: linear-gradient(var(--wp--preset--color--contrast), transparent) 28 round';
$block = new DeclarationBlock();

echo json_encode([
    'maskBorderSource' => $block->getProperty($declarations, 'mask-border-source'),
    'overriddenSource' => $block->getProperty(
        $declarations . '; mask-border-source: url("/wp-content/themes/acme/assets/frame.svg")',
        'mask-border-source'
    ),
], JSON_PRETTY_PRINT) . "\n";
