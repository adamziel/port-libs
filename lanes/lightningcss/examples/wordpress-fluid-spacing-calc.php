<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-group.is-style-fluid-stack {
  padding-inline: calc(20px + 30px + 40px);
  margin-block-start: calc(100% - 30px + 20px);
}

.wp-block-cover.is-style-offset {
  inset-inline-start: calc(2 * (100% - 20px));
  border-width: calc(1em + 2px + 2em + 3px);
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
