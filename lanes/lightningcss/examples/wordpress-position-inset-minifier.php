<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover .wp-block-cover__background {
  top: 0;
  left: 0;
  bottom: 0;
  right: 0;
}

.wp-block-cover.alignwide .wp-block-cover__inner-container {
  inset-block-start: var(--wp--preset--spacing--40);
  inset-block-end: var(--wp--preset--spacing--40);
  inset-inline-start: 24px;
  inset-inline-end: 32px;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
