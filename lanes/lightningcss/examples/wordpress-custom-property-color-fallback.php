<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-button {
  --wp--preset--color--brand: oklab(59.686% 0.1009 0.1192);
  --wp--preset--color--accent: color(display-p3 0 1 0);
}
CSS;

echo (new TransitionPrefixer())->prefixLegacySafari($css) . PHP_EOL;
