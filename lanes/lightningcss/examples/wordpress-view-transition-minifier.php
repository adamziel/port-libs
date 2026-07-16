<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@view-transition {
  navigation: auto;
  types: page nav-menu;
}

:root:active-view-transition-type(page, nav-menu) {
  color: yellow;
}

.wp-block-navigation__responsive-container {
  view-transition-name: wp-nav-menu;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
