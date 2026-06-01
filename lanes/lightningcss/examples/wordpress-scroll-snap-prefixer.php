<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-gallery.is-style-snap-carousel {
  scroll-snap-type: x mandatory;
  scroll-snap-points-x: repeat(100%);
  scroll-snap-destination: 50% 50%;
}

.wp-block-post-template.is-style-card-strip > li {
  scroll-snap-coordinate: 0 0;
  scroll-snap-points-y: repeat(50%);
}
CSS;

$stalePrefixed = <<<'CSS'
.wp-block-gallery.is-style-snap-carousel {
  -webkit-scroll-snap-type: x mandatory;
  -ms-scroll-snap-type: x mandatory;
  scroll-snap-type: x mandatory;
  -webkit-scroll-snap-points-x: repeat(100%);
  -ms-scroll-snap-points-x: repeat(100%);
  scroll-snap-points-x: repeat(100%);
}
CSS;

$actual = [
    'safari10_1' => $prefixer->prefixForTargets($css, ['safari' => '10.1']),
    'safari10_2' => $prefixer->prefixForTargets($css, ['safari' => '10.2']),
    'edge18' => $prefixer->prefixForTargets($css, ['edge' => 18]),
    'ieAndSafari' => $prefixer->prefixForTargets($css, ['ie' => 10, 'safari' => '10.1']),
    'modernCleanup' => $prefixer->prefixForTargets($stalePrefixed, ['safari' => '10.2', 'edge' => 19]),
];

$expected = [
    'safari10_1' => '.wp-block-gallery.is-style-snap-carousel{-webkit-scroll-snap-type:x mandatory;scroll-snap-type:x mandatory;-webkit-scroll-snap-points-x:repeat(100%);scroll-snap-points-x:repeat(100%);-webkit-scroll-snap-destination:50% 50%;scroll-snap-destination:50% 50%}.wp-block-post-template.is-style-card-strip>li{-webkit-scroll-snap-coordinate:0 0;scroll-snap-coordinate:0 0;-webkit-scroll-snap-points-y:repeat(50%);scroll-snap-points-y:repeat(50%)}',
    'safari10_2' => '.wp-block-gallery.is-style-snap-carousel{scroll-snap-type:x mandatory;scroll-snap-points-x:repeat(100%);scroll-snap-destination:50% 50%}.wp-block-post-template.is-style-card-strip>li{scroll-snap-coordinate:0 0;scroll-snap-points-y:repeat(50%)}',
    'edge18' => '.wp-block-gallery.is-style-snap-carousel{-ms-scroll-snap-type:x mandatory;scroll-snap-type:x mandatory;-ms-scroll-snap-points-x:repeat(100%);scroll-snap-points-x:repeat(100%);-ms-scroll-snap-destination:50% 50%;scroll-snap-destination:50% 50%}.wp-block-post-template.is-style-card-strip>li{-ms-scroll-snap-coordinate:0 0;scroll-snap-coordinate:0 0;-ms-scroll-snap-points-y:repeat(50%);scroll-snap-points-y:repeat(50%)}',
    'ieAndSafari' => '.wp-block-gallery.is-style-snap-carousel{-webkit-scroll-snap-type:x mandatory;-ms-scroll-snap-type:x mandatory;scroll-snap-type:x mandatory;-webkit-scroll-snap-points-x:repeat(100%);-ms-scroll-snap-points-x:repeat(100%);scroll-snap-points-x:repeat(100%);-webkit-scroll-snap-destination:50% 50%;-ms-scroll-snap-destination:50% 50%;scroll-snap-destination:50% 50%}.wp-block-post-template.is-style-card-strip>li{-webkit-scroll-snap-coordinate:0 0;-ms-scroll-snap-coordinate:0 0;scroll-snap-coordinate:0 0;-webkit-scroll-snap-points-y:repeat(50%);-ms-scroll-snap-points-y:repeat(50%);scroll-snap-points-y:repeat(50%)}',
    'modernCleanup' => '.wp-block-gallery.is-style-snap-carousel{scroll-snap-type:x mandatory;scroll-snap-points-x:repeat(100%)}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected scroll-snap prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
