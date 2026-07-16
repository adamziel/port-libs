<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-frame {
  mask-border-source: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364));
  mask-border-slice: 12 24 12 24;
  mask-border-width: 8px;
  mask-border-repeat: round round;
  mask-border-mode: luminance;
}
CSS;

echo (new TransitionPrefixer())->prefixLegacySafari($css) . PHP_EOL;
