<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-gallery.is-style-swipe {
  touch-action: pan-y;
}

.wp-block-post-title.is-vertical-caption {
  text-orientation: upright;
}
CSS;

$actual = [
    'ie10' => $prefixer->prefixForTargets($css, ['ie' => 10]),
    'ie11' => $prefixer->prefixForTargets($css, ['ie' => 11]),
    'safari10_1' => $prefixer->prefixForTargets($css, ['safari' => '10.1']),
    'safari13_2' => $prefixer->prefixForTargets($css, ['safari' => '13.2']),
    'supports_ie10' => $prefixer->prefixForTargets('@supports (touch-action: pan-y) { .wp-block-gallery.is-style-swipe { touch-action: pan-y; } }', ['ie' => 10]),
    'supports_safari10_1' => $prefixer->prefixForTargets('@supports (text-orientation: upright) { .wp-block-post-title.is-vertical-caption { text-orientation: upright; } }', ['safari' => '10.1']),
];

$expected = [
    'ie10' => '.wp-block-gallery.is-style-swipe{-ms-touch-action:pan-y;touch-action:pan-y}.wp-block-post-title.is-vertical-caption{text-orientation:upright}',
    'ie11' => '.wp-block-gallery.is-style-swipe{touch-action:pan-y}.wp-block-post-title.is-vertical-caption{text-orientation:upright}',
    'safari10_1' => '.wp-block-gallery.is-style-swipe{touch-action:pan-y}.wp-block-post-title.is-vertical-caption{-webkit-text-orientation:upright;text-orientation:upright}',
    'safari13_2' => '.wp-block-gallery.is-style-swipe{touch-action:pan-y}.wp-block-post-title.is-vertical-caption{text-orientation:upright}',
    'supports_ie10' => '@supports ((-ms-touch-action:pan-y) or (touch-action:pan-y)){.wp-block-gallery.is-style-swipe{-ms-touch-action:pan-y;touch-action:pan-y}}',
    'supports_safari10_1' => '@supports ((-webkit-text-orientation:upright) or (text-orientation:upright)){.wp-block-post-title.is-vertical-caption{-webkit-text-orientation:upright;text-orientation:upright}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected touch/orientation prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
