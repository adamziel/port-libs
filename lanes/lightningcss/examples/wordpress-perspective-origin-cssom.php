<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$cardMotion = 'perspective-origin: LEFT top; -webkit-perspective-origin: right bottom !important; -moz-perspective-origin: center 0px; color: var(--wp--preset--color--contrast)';

$actual = [
    'origin' => $block->getProperty($cardMotion, 'perspective-origin'),
    'legacyWebkitOrigin' => $block->getProperty($cardMotion, '-webkit-perspective-origin'),
    'legacyMozOrigin' => $block->getProperty($cardMotion, '-moz-perspective-origin'),
    'customToken' => $block->getProperty('--Perspective-Origin: LEFT top', '--Perspective-Origin'),
    'updatedOrigin' => $block->setProperty($cardMotion, 'perspective-origin', 'bottom'),
    'legacyOriginUpdated' => $block->setProperty($cardMotion, '-webkit-perspective-origin', 'left', true),
    'withoutLegacyMozOrigin' => $block->removeProperty($cardMotion, '-moz-perspective-origin'),
];

$expected = [
    'origin' => ['value' => '0 0', 'important' => false],
    'legacyWebkitOrigin' => ['value' => '100% 100%', 'important' => true],
    'legacyMozOrigin' => ['value' => '50% 0', 'important' => false],
    'customToken' => ['value' => 'LEFT top', 'important' => false],
    'updatedOrigin' => 'perspective-origin: 50% 100%; -moz-perspective-origin: 50% 0; color: var(--wp--preset--color--contrast); -webkit-perspective-origin: 100% 100% !important',
    'legacyOriginUpdated' => 'perspective-origin: 0 0; -moz-perspective-origin: 50% 0; color: var(--wp--preset--color--contrast); -webkit-perspective-origin: 0 50% !important',
    'withoutLegacyMozOrigin' => 'perspective-origin: 0 0; color: var(--wp--preset--color--contrast); -webkit-perspective-origin: 100% 100% !important',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected perspective-origin CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
