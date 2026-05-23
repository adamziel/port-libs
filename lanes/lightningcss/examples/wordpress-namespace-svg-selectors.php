<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@namespace svg url(http://www.w3.org/2000/svg);
@namespace xlink url(http://www.w3.org/1999/xlink);

.wp-block-navigation svg|svg {
  fill: currentColor;
}

.wp-block-social-links svg|a[xlink|href=icon] {
  color: yellow;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
