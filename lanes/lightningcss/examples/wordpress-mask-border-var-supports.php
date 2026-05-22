<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-frame {
  mask-border: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) var(--wp--custom--frame-slice);
}
CSS;

echo (new TransitionPrefixer())->prefixLegacySafari($css) . PHP_EOL;
