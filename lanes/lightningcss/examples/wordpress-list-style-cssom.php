<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$navigationMarkers = 'list-style: Outside URL("marker.svg") Disc; color: var(--wp--preset--color--contrast)';
$editorOverride = 'list-style: Inside URL("marker.svg") Square';

$actual = [
    'markerType' => $block->getProperty($navigationMarkers, 'list-style-type'),
    'setMarkerType' => $block->setProperty($navigationMarkers, 'list-style-type', 'Upper-Roman'),
    'symbolCounterType' => $block->setProperty($navigationMarkers, 'list-style-type', 'Symbols(Symbolic "A" "B")'),
    'customCounterType' => $block->getProperty('list-style-type: wp\2d marker', 'list-style-type'),
    'dropMarkerImage' => $block->removeProperty($editorOverride, 'list-style-image'),
    'resetListStyle' => $block->removeProperty(
        'list-style: inside square; list-style-image: url(marker.svg); padding-left: var(--wp--preset--spacing--40)',
        'list-style'
    ),
];

$expected = [
    'markerType' => ['value' => 'disc', 'important' => false],
    'setMarkerType' => 'list-style: url(marker.svg) upper-roman; color: var(--wp--preset--color--contrast)',
    'symbolCounterType' => 'list-style: url(marker.svg) symbols("A" "B"); color: var(--wp--preset--color--contrast)',
    'customCounterType' => ['value' => 'wp-marker', 'important' => false],
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
