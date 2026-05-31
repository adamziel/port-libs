<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$carousel = 'scroll-margin: var(--wp--preset--spacing--40) 1rem; scroll-padding: 0 2rem; color: var(--wp--preset--color--contrast)';

$actual = [
    'snapMarginLeft' => $block->getProperty($carousel, 'scroll-margin-left'),
    'editorSnapMarginTop' => $block->setProperty(
        $carousel,
        'scroll-margin-top',
        'var(--wp--preset--spacing--60)'
    ),
    'editorSnapPaddingLeft' => $block->setProperty($carousel, 'scroll-padding-left', '3rem'),
    'dropSnapPaddingRight' => $block->removeProperty($carousel, 'scroll-padding-right'),
    'dropSnapMargin' => $block->removeProperty($carousel, 'scroll-margin'),
];

$expected = [
    'snapMarginLeft' => [
        'value' => '1rem',
        'important' => false,
    ],
    'editorSnapMarginTop' => 'scroll-margin: var(--wp--preset--spacing--60) 1rem var(--wp--preset--spacing--40); scroll-padding: 0 2rem; color: var(--wp--preset--color--contrast)',
    'editorSnapPaddingLeft' => 'scroll-margin: var(--wp--preset--spacing--40) 1rem; scroll-padding: 0 2rem 0 3rem; color: var(--wp--preset--color--contrast)',
    'dropSnapPaddingRight' => 'scroll-margin: var(--wp--preset--spacing--40) 1rem; scroll-padding-top: 0; scroll-padding-bottom: 0; scroll-padding-left: 2rem; color: var(--wp--preset--color--contrast)',
    'dropSnapMargin' => 'scroll-padding: 0 2rem; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected scroll snap CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
