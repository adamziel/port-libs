<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-query-pagination a {
  transition-property: opacity, transform;
  transition-duration: 90ms, 200ms;
  transition-timing-function: ease-in-out, cubic-bezier(0.25, 0.1, 0.25, 1);
  transition-delay: 500ms, 0s;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
