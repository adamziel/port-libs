<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-gallery .wp-block-image.is-zoomable img {
  cursor: zoom-in;
}

.wp-block-navigation .menu-item.is-draggable {
  cursor: grab;
}

.wp-block-navigation .menu-item.is-dragging {
  cursor: grabbing;
}

.wp-block-cover .media-handle {
  cursor: url("resize.cur"), grab;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'legacy_boundaries' => $prefixer->prefixForTargets($css, [
        'chrome' => 36,
        'firefox' => 23,
        'safari' => 10,
        'opera' => 23,
    ]),
    'modern_boundaries' => $prefixer->prefixForTargets($css, [
        'chrome' => 68,
        'firefox' => 26,
        'safari' => 11,
        'opera' => 55,
    ]),
];

$expected = [
    'legacy_boundaries' => '.wp-block-gallery .wp-block-image.is-zoomable img{cursor:-webkit-zoom-in;cursor:-moz-zoom-in;cursor:zoom-in}.wp-block-navigation .menu-item.is-draggable{cursor:-webkit-grab;cursor:-moz-grab;cursor:grab}.wp-block-navigation .menu-item.is-dragging{cursor:-webkit-grabbing;cursor:-moz-grabbing;cursor:grabbing}.wp-block-cover .media-handle{cursor:url("resize.cur"),-webkit-grab;cursor:url("resize.cur"),-moz-grab;cursor:url("resize.cur"),grab}',
    'modern_boundaries' => '.wp-block-gallery .wp-block-image.is-zoomable img{cursor:zoom-in}.wp-block-navigation .menu-item.is-draggable{cursor:grab}.wp-block-navigation .menu-item.is-dragging{cursor:grabbing}.wp-block-cover .media-handle{cursor:url("resize.cur"),grab}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected cursor target prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
