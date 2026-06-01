<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$iconPaint = 'fill: url("#wp-gradient") currentColor; stroke: rgba(255,0,0,.4); stroke-dasharray: 0px, 2px 4px; text-rendering: geometricPrecision';

$actual = [
    'fillPaint' => $block->getProperty($iconPaint, 'fill'),
    'dashPattern' => $block->getProperty($iconPaint, 'stroke-dasharray'),
    'textRendering' => $block->getProperty($iconPaint, 'text-rendering'),
    'editorFill' => $block->setProperty($iconPaint, 'fill', 'url("#editor-gradient") yellow'),
    'editorDash' => $block->setProperty($iconPaint, 'stroke-dasharray', '0.500px 25% 4'),
    'withoutFill' => $block->removeProperty($iconPaint, 'fill'),
];

$expected = [
    'fillPaint' => ['value' => 'url(#wp-gradient) currentColor', 'important' => false],
    'dashPattern' => ['value' => '0 2 4', 'important' => false],
    'textRendering' => ['value' => 'geometricprecision', 'important' => false],
    'editorFill' => 'fill: url(#editor-gradient) #ff0; stroke: #f006; stroke-dasharray: 0 2 4; text-rendering: geometricprecision',
    'editorDash' => 'fill: url(#wp-gradient) currentColor; stroke: #f006; stroke-dasharray: .5 25% 4; text-rendering: geometricprecision',
    'withoutFill' => 'stroke: #f006; stroke-dasharray: 0 2 4; text-rendering: geometricprecision',
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
