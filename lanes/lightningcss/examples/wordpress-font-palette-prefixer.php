<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@font-palette-values --wp-heading-palette {
  font-family: Handover Sans;
  base-palette: 3;
  override-colors: 1 var(--wp--preset--color--contrast), 3 lch(50.998% 135.363 338);
}

.wp-block-heading.is-style-color-font {
  font-palette: --wp-heading-palette;
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90]) . PHP_EOL;
