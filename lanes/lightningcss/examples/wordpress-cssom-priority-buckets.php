<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'color: var(--wp--preset--color--accent) !important; background: white; color: var(--wp--preset--color--contrast)';

echo json_encode([
    'activeColor' => $block->getProperty($declarations, 'color'),
    'editorOverride' => $block->setProperty($declarations, 'color', 'var(--wp--preset--color--primary)'),
    'spacingOverride' => $block->setProperty('margin: var(--wp--preset--spacing--40) !important', 'margin-top', '0'),
    'removedColor' => $block->removeProperty($declarations, 'color'),
], JSON_PRETTY_PRINT) . "\n";
