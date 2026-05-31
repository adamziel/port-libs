<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media (-webkit-min-device-pixel-ratio: 2) {
    .wp-block-gallery.is-retina-legacy {
      color: yellow;
    }
  }

  @media (-webkit-device-pixel-ratio >= 2) {
    .wp-block-gallery.is-retina-modern {
      color: yellow;
    }
  }

  @media (2 <= -webkit-device-pixel-ratio <= 3) {
    .wp-block-gallery.is-retina-window {
      color: yellow;
    }
  }

  @media (2 <= -moz-device-pixel-ratio <= 3) {
    .wp-block-gallery.is-retina-moz-window {
      color: yellow;
    }
  }
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'legacy' => $prefixer->prefixForTargets($css, ['firefox' => 60]),
    'modern' => $prefixer->prefixForTargets($css, ['firefox' => 90]),
];

$expected = [
    'legacy' => '@layer theme.blocks{@media (-webkit-min-device-pixel-ratio:2){.wp-block-gallery.is-retina-legacy{color:#ff0}.wp-block-gallery.is-retina-modern{color:#ff0}}@media (-webkit-min-device-pixel-ratio:2) and (-webkit-max-device-pixel-ratio:3){.wp-block-gallery.is-retina-window{color:#ff0}}@media (min--moz-device-pixel-ratio:2) and (max--moz-device-pixel-ratio:3){.wp-block-gallery.is-retina-moz-window{color:#ff0}}}',
    'modern' => '@layer theme.blocks{@media (-webkit-device-pixel-ratio>=2){.wp-block-gallery.is-retina-legacy{color:#ff0}.wp-block-gallery.is-retina-modern{color:#ff0}}@media (2<=-webkit-device-pixel-ratio<=3){.wp-block-gallery.is-retina-window{color:#ff0}}@media (2<=-moz-device-pixel-ratio<=3){.wp-block-gallery.is-retina-moz-window{color:#ff0}}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected vendor pixel-ratio media prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
