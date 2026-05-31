<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$navigationMarkers = 'list-style: outside url(marker.svg) disc; color: var(--wp--preset--color--contrast)';
$editorOverride = 'list-style: inside url(marker.svg) square';

$actual = [
    'markerType' => $block->getProperty($navigationMarkers, 'list-style-type'),
    'setMarkerType' => $block->setProperty($navigationMarkers, 'list-style-type', 'square'),
    'dropMarkerImage' => $block->removeProperty($editorOverride, 'list-style-image'),
    'resetListStyle' => $block->removeProperty(
        'list-style: inside square; list-style-image: url(marker.svg); padding-left: var(--wp--preset--spacing--40)',
        'list-style'
    ),
];

$expected = [
    'markerType' => ['value' => 'disc', 'important' => false],
    'setMarkerType' => 'list-style: url(marker.svg) square; color: var(--wp--preset--color--contrast)',
    'dropMarkerImage' => 'list-style-position: inside; list-style-type: square',
    'resetListStyle' => 'padding-left: var(--wp--preset--spacing--40)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected list-style CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
