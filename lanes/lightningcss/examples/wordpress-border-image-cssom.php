<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$frame = 'border-image: url("/wp-content/themes/acme/assets/frame.png") 30 fill / 12px round; color: var(--wp--preset--color--contrast)';
$legacyFrame = '-webkit-border-image: url("/wp-content/themes/acme/assets/frame-legacy.png") 24 / 10px round; color: var(--wp--preset--color--contrast)';

$actual = [
    'frameAsset' => $block->getProperty($frame, 'border-image-source'),
    'frameSlice' => $block->getProperty($frame, 'border-image-slice'),
    'legacyFrameAsset' => $block->getProperty($legacyFrame, 'border-image-source'),
    'editorFrameAsset' => $block->setProperty(
        $frame,
        'border-image-source',
        'url("/wp-content/uploads/frame-new.png")'
    ),
    'legacyEditorFrameAsset' => $block->setProperty(
        $legacyFrame,
        'border-image-source',
        'url("/wp-content/uploads/frame-legacy-new.png")'
    ),
    'editorFrameRepeat' => $block->setProperty($frame, 'border-image-repeat', 'space round'),
    'dropFrameAsset' => $block->removeProperty($frame, 'border-image-source'),
    'legacyDropFrameAsset' => $block->removeProperty($legacyFrame, 'border-image-source'),
];

$expected = [
    'frameAsset' => ['value' => 'url(/wp-content/themes/acme/assets/frame.png)', 'important' => false],
    'frameSlice' => ['value' => '30 fill', 'important' => false],
    'legacyFrameAsset' => ['value' => 'url(/wp-content/themes/acme/assets/frame-legacy.png)', 'important' => false],
    'editorFrameAsset' => 'border-image: url(/wp-content/uploads/frame-new.png) 30 fill / 12px round; color: var(--wp--preset--color--contrast)',
    'legacyEditorFrameAsset' => '-webkit-border-image: url(/wp-content/uploads/frame-legacy-new.png) 24 / 10px round; color: var(--wp--preset--color--contrast)',
    'editorFrameRepeat' => 'border-image: url(/wp-content/themes/acme/assets/frame.png) 30 fill / 12px space round; color: var(--wp--preset--color--contrast)',
    'dropFrameAsset' => 'border-image-slice: 30 fill; border-image-width: 12px; border-image-outset: 0; border-image-repeat: round; color: var(--wp--preset--color--contrast)',
    'legacyDropFrameAsset' => 'border-image-slice: 24; border-image-width: 10px; border-image-outset: 0; border-image-repeat: round; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected border-image CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
