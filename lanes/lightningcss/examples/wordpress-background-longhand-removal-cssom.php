<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();
$heroBackground = 'background: red url(hero.jpg) 20px 10px / cover no-repeat fixed border-box content-box; color: var(--wp--preset--color--contrast)';

$actual = [
    'defaultImage' => $block->getProperty('background: red', 'background-image'),
    'defaultRepeat' => $block->getProperty('background: red', 'background-repeat'),
    'dropThemeColor' => $block->removeProperty($heroBackground, 'background-color'),
    'dropThemeImage' => $block->removeProperty($heroBackground, 'background-image'),
    'dropThemeRepeat' => $block->removeProperty($heroBackground, 'background-repeat'),
    'dropCoverSize' => $block->removeProperty(
        'background: url(hero.jpg) 20px 10px / cover; color: var(--wp--preset--color--contrast)',
        'background-size'
    ),
];

$expected = [
    'defaultImage' => [
        'value' => 'none',
        'important' => false,
    ],
    'defaultRepeat' => [
        'value' => 'repeat',
        'important' => false,
    ],
    'dropThemeColor' => 'background-image: url(hero.jpg); background-position-x: 20px; background-position-y: 10px; background-repeat: no-repeat; background-size: cover; background-attachment: fixed; background-origin: border-box; background-clip: content-box; color: var(--wp--preset--color--contrast)',
    'dropThemeImage' => 'background-color: red; background-position-x: 20px; background-position-y: 10px; background-repeat: no-repeat; background-size: cover; background-attachment: fixed; background-origin: border-box; background-clip: content-box; color: var(--wp--preset--color--contrast)',
    'dropThemeRepeat' => 'background-color: red; background-image: url(hero.jpg); background-position-x: 20px; background-position-y: 10px; background-size: cover; background-attachment: fixed; background-origin: border-box; background-clip: content-box; color: var(--wp--preset--color--contrast)',
    'dropCoverSize' => 'background-color: #0000; background-image: url(hero.jpg); background-position-x: 20px; background-position-y: 10px; background-repeat: repeat; background-attachment: scroll; background-origin: padding-box; background-clip: border-box; color: var(--wp--preset--color--contrast)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected background longhand removal CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
