<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$iconPaint = 'fill: url("#wp-gradient") currentColor; stroke: rgba(255,0,0,.4); stroke-dasharray: 0px, 2px 4px; stroke-linejoin: Miter-Clip; text-rendering: geometricPrecision; color-rendering: optimizeSpeed; image-rendering: CRISP-EDGES';

$actual = [
    'fillPaint' => $block->getProperty($iconPaint, 'fill'),
    'dashPattern' => $block->getProperty($iconPaint, 'stroke-dasharray'),
    'lineJoin' => $block->getProperty($iconPaint, 'stroke-linejoin'),
    'textRendering' => $block->getProperty($iconPaint, 'text-rendering'),
    'colorRendering' => $block->getProperty($iconPaint, 'color-rendering'),
    'imageRendering' => $block->getProperty($iconPaint, 'image-rendering'),
    'editorFill' => $block->setProperty($iconPaint, 'fill', 'url("#editor-gradient") yellow'),
    'editorDash' => $block->setProperty($iconPaint, 'stroke-dasharray', '0.500px 25% 4'),
    'editorLineJoin' => $block->setProperty($iconPaint, 'stroke-linejoin', 'Round'),
    'pixelRasterMode' => $block->setProperty($iconPaint, 'image-rendering', 'Pixelated'),
    'legacyRasterMode' => $block->setProperty($iconPaint, 'image-rendering', '-WEBKIT-OPTIMIZE-CONTRAST'),
    'withoutFill' => $block->removeProperty($iconPaint, 'fill'),
    'withoutRasterMode' => $block->removeProperty($iconPaint, 'image-rendering'),
];

$expected = [
    'fillPaint' => ['value' => 'url(#wp-gradient) currentColor', 'important' => false],
    'dashPattern' => ['value' => '0 2 4', 'important' => false],
    'lineJoin' => ['value' => 'miter-clip', 'important' => false],
    'textRendering' => ['value' => 'geometricprecision', 'important' => false],
    'colorRendering' => ['value' => 'optimizespeed', 'important' => false],
    'imageRendering' => ['value' => 'crisp-edges', 'important' => false],
    'editorFill' => 'fill: url(#editor-gradient) #ff0; stroke: #f006; stroke-dasharray: 0 2 4; stroke-linejoin: miter-clip; text-rendering: geometricprecision; color-rendering: optimizespeed; image-rendering: crisp-edges',
    'editorDash' => 'fill: url(#wp-gradient) currentColor; stroke: #f006; stroke-dasharray: .5 25% 4; stroke-linejoin: miter-clip; text-rendering: geometricprecision; color-rendering: optimizespeed; image-rendering: crisp-edges',
    'editorLineJoin' => 'fill: url(#wp-gradient) currentColor; stroke: #f006; stroke-dasharray: 0 2 4; stroke-linejoin: round; text-rendering: geometricprecision; color-rendering: optimizespeed; image-rendering: crisp-edges',
    'pixelRasterMode' => 'fill: url(#wp-gradient) currentColor; stroke: #f006; stroke-dasharray: 0 2 4; stroke-linejoin: miter-clip; text-rendering: geometricprecision; color-rendering: optimizespeed; image-rendering: pixelated',
    'legacyRasterMode' => 'fill: url(#wp-gradient) currentColor; stroke: #f006; stroke-dasharray: 0 2 4; stroke-linejoin: miter-clip; text-rendering: geometricprecision; color-rendering: optimizespeed; image-rendering: -webkit-optimize-contrast',
    'withoutFill' => 'stroke: #f006; stroke-dasharray: 0 2 4; stroke-linejoin: miter-clip; text-rendering: geometricprecision; color-rendering: optimizespeed; image-rendering: crisp-edges',
    'withoutRasterMode' => 'fill: url(#wp-gradient) currentColor; stroke: #f006; stroke-dasharray: 0 2 4; stroke-linejoin: miter-clip; text-rendering: geometricprecision; color-rendering: optimizespeed',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected SVG paint CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
