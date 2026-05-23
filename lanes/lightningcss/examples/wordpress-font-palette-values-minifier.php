<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@font-palette-values --wp-duotone-accent {
  font-family: Bixa;
  base-palette: 1;
  override-colors: 1 #7EB7E4, 3 var(--wp--preset--color--accent);
}

.wp-block-heading.is-style-color-font {
  font-palette: --wp-duotone-accent;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
