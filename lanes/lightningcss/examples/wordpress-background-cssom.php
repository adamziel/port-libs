<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$declarations = 'background: url(hero.jpg) green; background-repeat: repeat-x; color: var(--wp--preset--color--contrast)';
$gallery = 'background: url(card.jpg), url(texture.png)';
$cover = 'background: url(hero.jpg)';
$heroPosition = 'background-position: 20px 10px; background-size: cover';
$legacyClip = '-webkit-background-clip: Text; background-clip: Text; color: var(--wp--preset--color--contrast)';
$directLonghands = 'background-image: URL("hero.jpg"); background-size: 0PX AUTO; background-repeat: repeat no-repeat; background-attachment: Fixed; background-origin: Content-Box; background-position: Left 0PX Top 50%; color: var(--wp--preset--color--contrast)';

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
    'legacyClipText' => $block->getProperty(
        $legacyClip,
        '-webkit-background-clip'
    ),
    'legacyClipContentBox' => $block->setProperty(
        $legacyClip,
        '-webkit-background-clip',
        'Content-Box'
    ),
    'withoutLegacyClip' => $block->removeProperty(
        $legacyClip,
        '-webkit-background-clip'
    ),
    'resetBackgroundOnly' => $block->removeProperty(
        'background: url(hero.jpg) fixed content-box text; background-color: blue; background-clip: text; padding: var(--wp--preset--spacing--40)',
        'background'
    ),
    'heroFocalPointX' => $block->setProperty(
        $heroPosition,
        'background-position-x',
        'left'
    ),
    'heroFocalPointY' => $block->setProperty(
        $heroPosition,
        'background-position-y',
        'bottom'
    ),
    'resetHeroFocalX' => $block->removeProperty(
        $heroPosition,
        'background-position-x'
    ),
    'resetHeroFocalPoint' => $block->removeProperty(
        'background-position: 20px 10px; background-position-x: 30px; color: var(--wp--preset--color--contrast)',
        'background-position'
    ),
    'directBackgroundImage' => $block->getProperty(
        $directLonghands,
        'background-image'
    ),
    'directBackgroundSize' => $block->getProperty(
        $directLonghands,
        'background-size'
    ),
    'directBackgroundRepeat' => $block->getProperty(
        $directLonghands,
        'background-repeat'
    ),
    'directBackgroundAttachment' => $block->getProperty(
        $directLonghands,
        'background-attachment'
    ),
    'directBackgroundOrigin' => $block->getProperty(
        $directLonghands,
        'background-origin'
    ),
    'directBackgroundPosition' => $block->getProperty(
        $directLonghands,
        'background-position'
    ),
    'updateDirectBackgroundSize' => $block->setProperty(
        $directLonghands,
        'background-size',
        'AUTO 0PX'
    ),
];

$expected = [
    'themeBackgroundColor' => 'background: var(--wp--preset--color--accent) url(hero.jpg); background-repeat: repeat-x; color: var(--wp--preset--color--contrast)',
    'galleryRepeat' => 'background: url(card.jpg) no-repeat, url(texture.png)',
    'galleryAttachment' => 'background: url(card.jpg) fixed, url(texture.png) local',
    'coverClipText' => 'background: url(hero.jpg) text',
    'legacyClipText' => ['value' => 'text', 'important' => false],
    'legacyClipContentBox' => '-webkit-background-clip: content-box; background-clip: text; color: var(--wp--preset--color--contrast)',
    'withoutLegacyClip' => 'background-clip: text; color: var(--wp--preset--color--contrast)',
    'resetBackgroundOnly' => 'padding: var(--wp--preset--spacing--40)',
    'heroFocalPointX' => 'background-position: left 10px; background-size: cover',
    'heroFocalPointY' => 'background-position: 20px bottom; background-size: cover',
    'resetHeroFocalX' => 'background-position-y: 10px; background-size: cover',
    'resetHeroFocalPoint' => 'color: var(--wp--preset--color--contrast)',
    'directBackgroundImage' => ['value' => 'url(hero.jpg)', 'important' => false],
    'directBackgroundSize' => ['value' => '0', 'important' => false],
    'directBackgroundRepeat' => ['value' => 'repeat-x', 'important' => false],
    'directBackgroundAttachment' => ['value' => 'fixed', 'important' => false],
    'directBackgroundOrigin' => ['value' => 'content-box', 'important' => false],
    'directBackgroundPosition' => ['value' => '0 50%', 'important' => false],
    'updateDirectBackgroundSize' => 'background-image: url(hero.jpg); background-size: auto 0; background-repeat: repeat-x; background-attachment: fixed; background-origin: content-box; background-position: 0 50%; color: var(--wp--preset--color--contrast)',
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
