<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-title {
  font-family: "Inter", "Helvetica Neue", sans-serif;
  font-size: 12px;
  font-weight: bold;
  font-style: italic;
  font-stretch: expanded;
  font-variant-caps: small-caps;
  line-height: 1.2em;
}

.wp-block-navigation a {
  font: normal normal 600 9px/normal "Inter", sans-serif;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
