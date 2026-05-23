<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@font-face {
  font-family: "Inter Variable";
  font-style: oblique 0deg 10deg;
  font-weight: 100 900;
  src: local("Inter Variable"), url("./assets/fonts/inter-var.woff2") format(woff2) tech(variations);
  unicode-range: U+0025-00FF, U+400-4FF;
  font-display: swap;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
