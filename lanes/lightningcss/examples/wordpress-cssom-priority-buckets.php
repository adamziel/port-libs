<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = '/* theme override ; */ color: var(--wp--preset--color--accent) /* keep */ ! /* core */ important; background: white; color: var(--wp--preset--color--contrast)';

echo json_encode([
    'activeColor' => $block->getProperty($declarations, 'color'),
    'editorOverride' => $block->setProperty($declarations, 'color', 'var(--wp--preset--color--primary)'),
    'spacingOverride' => $block->setProperty('margin: var(--wp--preset--spacing--40) /* user reset */ !important', 'margin-top', '0'),
    'removedColor' => $block->removeProperty($declarations, 'color'),
], JSON_PRETTY_PRINT) . "\n";
