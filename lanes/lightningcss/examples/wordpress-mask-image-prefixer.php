<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-soft-fade {
  mask-image: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364));
  mask-position: 50% 50%;
  mask-size: cover;
  mask-repeat: no-repeat;
  mask-origin: content-box;
  mask-clip: padding-box;
  mask-mode: luminance;
}
CSS;

echo (new TransitionPrefixer())->prefixLegacySafari($css) . PHP_EOL;
