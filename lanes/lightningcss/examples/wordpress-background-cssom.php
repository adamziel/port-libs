<?php

declare(strict_types=1);

use PortLibs\LightningCSS\DeclarationBlock;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = new DeclarationBlock();

$declarations = 'background: url(hero.jpg) green; background-repeat: repeat-x; color: var(--wp--preset--color--contrast)';
$gallery = 'background: url(card.jpg), url(texture.png)';

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
    'resetBackgroundOnly' => $block->removeProperty(
        'background: url(hero.jpg) green; background-color: blue; padding: var(--wp--preset--spacing--40)',
        'background'
    ),
];

$expected = [
    'themeBackgroundColor' => 'background: var(--wp--preset--color--accent) url(hero.jpg); background-repeat: repeat-x; color: var(--wp--preset--color--contrast)',
    'galleryRepeat' => 'background: url(card.jpg) no-repeat, url(texture.png) repeat',
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
