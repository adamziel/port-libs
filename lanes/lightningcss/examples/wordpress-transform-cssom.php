<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$coverMotion = 'transform: translateX(24px) scale3d(100%, 100%, 100%); translate: 0px 12px 0px; rotate: 10deg 0 0 -1; scale: 100% 105% 1; color: var(--wp--preset--color--contrast)';
$legacyMotion = '-ms-transform: translateX(12px); color: var(--wp--preset--color--contrast)';

$actual = [
    'transform' => $block->getProperty($coverMotion, 'transform'),
    'translate' => $block->getProperty($coverMotion, 'translate'),
    'rotate' => $block->getProperty($coverMotion, 'rotate'),
    'scale' => $block->getProperty($coverMotion, 'scale'),
    'updatedTransform' => $block->setProperty(
        $coverMotion,
        'transform',
        'translate3d(2px, 0px, 0px) scale(100%, 200%)'
    ),
    'legacyUpdated' => $block->setProperty(
        $legacyMotion,
        '-ms-transform',
        'translate3d(2px, 0px, 0px) rotateZ(20deg)',
        true
    ),
    'withoutTransform' => $block->removeProperty($coverMotion, 'transform'),
];

$expected = [
    'transform' => ['value' => 'translate(24px)scale(1)', 'important' => false],
    'translate' => ['value' => '0 12px', 'important' => false],
    'rotate' => ['value' => '-10deg', 'important' => false],
    'scale' => ['value' => '1 1.05', 'important' => false],
    'updatedTransform' => 'transform: translate(2px)scaleY(2); translate: 0 12px; rotate: -10deg; scale: 1 1.05; color: var(--wp--preset--color--contrast)',
    'legacyUpdated' => 'color: var(--wp--preset--color--contrast); -ms-transform: translate(2px)rotate(20deg) !important',
    'withoutTransform' => 'translate: 0 12px; rotate: -10deg; scale: 1 1.05; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected transform CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
