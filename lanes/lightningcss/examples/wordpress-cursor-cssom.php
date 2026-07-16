<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$declarations = 'cursor: URL("/wp-content/uploads/grab.cur") 4.0 12.00, Grab !important; --Block-Cursor: URL("/wp-content/uploads/grab.cur") 4.0 12.00, Grab; color: var(--wp--preset--color--contrast)';

$actual = [
    'activeCursor' => $block->getProperty($declarations, 'cursor'),
    'themeCursor' => $block->getProperty($declarations, '--Block-Cursor'),
    'writeZoom' => $block->setProperty($declarations, 'cursor', 'url("/wp-content/uploads/zoom.cur") 0.0 0.00, Zoom-In'),
    'dropCursor' => $block->removeProperty($declarations, 'cursor'),
];

$expected = [
    'activeCursor' => ['value' => 'url(/wp-content/uploads/grab.cur) 4 12, grab', 'important' => true],
    'themeCursor' => ['value' => 'URL("/wp-content/uploads/grab.cur") 4.0 12.00, Grab', 'important' => false],
    'writeZoom' => '--Block-Cursor: URL("/wp-content/uploads/grab.cur") 4.0 12.00, Grab; color: var(--wp--preset--color--contrast); cursor: url(/wp-content/uploads/zoom.cur) 0 0, zoom-in',
    'dropCursor' => '--Block-Cursor: URL("/wp-content/uploads/grab.cur") 4.0 12.00, Grab; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected cursor CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
