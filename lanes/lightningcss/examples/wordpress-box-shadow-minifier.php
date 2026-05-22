<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-template .wp-block-post {
  box-shadow: 12px 12px 0px 0px rgba(0,0,0,0.4), 0px 0px 12px 4px rgba(0,0,0,0.4) inset;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
