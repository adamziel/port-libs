<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'clip-path: padding-box circle(50px at 0 100px) !important; -webkit-clip-path: url("/wp-content/uploads/clip.svg#hero"); --wp--custom--clip-path: padding-box circle(50px at 0 100px)';

$actual = [
    'heroClip' => $block->getProperty($declarations, 'clip-path'),
    'legacyClip' => $block->getProperty($declarations, '-webkit-clip-path'),
    'customClip' => $block->getProperty($declarations, '--wp--custom--clip-path'),
    'editorClip' => $block->setProperty($declarations, 'clip-path', 'circle(closest-side at 50% 50%)'),
    'legacyShapeClip' => $block->setProperty($declarations, '-webkit-clip-path', 'padding-box circle(50px at 0 100px)'),
    'withoutLegacyClip' => $block->removeProperty($declarations, '-webkit-clip-path'),
];

$expected = [
    'heroClip' => ['value' => 'circle(50px at 0 100px) padding-box', 'important' => true],
    'legacyClip' => ['value' => 'url(/wp-content/uploads/clip.svg#hero)', 'important' => false],
    'customClip' => ['value' => 'padding-box circle(50px at 0 100px)', 'important' => false],
    'editorClip' => '-webkit-clip-path: url(/wp-content/uploads/clip.svg#hero); --wp--custom--clip-path: padding-box circle(50px at 0 100px); clip-path: circle()',
    'legacyShapeClip' => '-webkit-clip-path: circle(50px at 0 100px) padding-box; --wp--custom--clip-path: padding-box circle(50px at 0 100px); clip-path: circle(50px at 0 100px) padding-box !important',
    'withoutLegacyClip' => '--wp--custom--clip-path: padding-box circle(50px at 0 100px); clip-path: circle(50px at 0 100px) padding-box !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected clip-path CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
