<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$declarations = 'background: url(hero.jpg) green; background-repeat: repeat-x; color: var(--wp--preset--color--contrast)';
$gallery = 'background: url(card.jpg), url(texture.png)';
$cover = 'background: url(hero.jpg)';

$actual = [
    'themeBackgroundColor' => $block->setProperty(
        $declarations,
        'background-color',
        'var(--wp--preset--color--accent)'
    ),
    'galleryRepeat' => $block->setProperty(
        $gallery,
        'background-repeat',
        'no-repeat, repeat'
    ),
    'galleryAttachment' => $block->setProperty(
        $gallery,
        'background-attachment',
        'fixed, local'
    ),
    'coverClipText' => $block->setProperty(
        $cover,
        'background-clip',
        'text'
    ),
    'resetBackgroundOnly' => $block->removeProperty(
        'background: url(hero.jpg) fixed content-box text; background-color: blue; background-clip: text; padding: var(--wp--preset--spacing--40)',
        'background'
    ),
];

$expected = [
    'themeBackgroundColor' => 'background: var(--wp--preset--color--accent) url(hero.jpg); background-repeat: repeat-x; color: var(--wp--preset--color--contrast)',
    'galleryRepeat' => 'background: url(card.jpg) no-repeat, url(texture.png) repeat',
    'galleryAttachment' => 'background: url(card.jpg) fixed, url(texture.png) local',
    'coverClipText' => 'background: url(hero.jpg) text',
    'resetBackgroundOnly' => 'padding: var(--wp--preset--spacing--40)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected background CSSOM output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
