<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-title {
  font-family: "Inter", "Helvetica Neue", sans-serif;
  font-stretch: expanded;
}

@font-face {
  font-family: "revert";
  src: url("./fonts/revert.woff2") format("woff2");
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
