<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-quote.is-style-plain {
  --wp--custom--quote-accent: currentcolor;
  margin-block-start: var(--wp--preset--spacing--40);
  background: var(--wp--preset--color--base);
  direction: rtl;
  all: unset;
  display: block;
  background: var(--wp--preset--color--contrast);
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
