<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-group.is-style-reveal {
  transition-property: margin-block;
  transition-duration: 200ms;
  transition-timing-function: ease;
  transition-delay: 0s;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
