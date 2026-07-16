<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-cross-fade-hero {
  background-image: cross-fade(url(hero-light.jpg), url(hero-dark.jpg), 50%);
}

.wp-block-media-text .wp-block-media-text__media {
  background: cross-fade(url(card.jpg), url(card-hover.jpg), 35%) center / cover no-repeat;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome16' => $prefixer->prefixForTargets($css, ['chrome' => 16]),
    'chrome17' => $prefixer->prefixForTargets($css, ['chrome' => 17]),
    'safari9_2' => $prefixer->prefixForTargets($css, ['safari' => '9.2']),
];

$modern = '.wp-block-cover.is-style-cross-fade-hero{background-image:cross-fade(url(hero-light.jpg),url(hero-dark.jpg),50%)}'
    . '.wp-block-media-text .wp-block-media-text__media{background:cross-fade(url(card.jpg),url(card-hover.jpg),35%) center/cover no-repeat}';
$expected = [
    'chrome16' => $modern,
    'chrome17' => '.wp-block-cover.is-style-cross-fade-hero{background-image:-webkit-cross-fade(url(hero-light.jpg),url(hero-dark.jpg),50%);background-image:cross-fade(url(hero-light.jpg),url(hero-dark.jpg),50%)}'
        . '.wp-block-media-text .wp-block-media-text__media{background:-webkit-cross-fade(url(card.jpg),url(card-hover.jpg),35%) center/cover no-repeat;background:cross-fade(url(card.jpg),url(card-hover.jpg),35%) center/cover no-repeat}',
    'safari9_2' => $modern,
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected cross-fade prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
