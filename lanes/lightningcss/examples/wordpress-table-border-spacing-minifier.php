<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-table.is-style-loose-spacing table {
  border-collapse: separate;
  border-spacing: 0px 12px;
}

.wp-block-table.is-style-compact-spacing table {
  border-spacing: 0px 0px;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
