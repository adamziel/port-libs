<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-lift:hover {
  transform: translate3d(0px, 12px, 0px) scale(100%, 105%);
}

.wp-block-cover.is-style-lift:active {
  transform: translateX(calc(4px + 8px)) scale3d(100%, 100%, 100%);
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
