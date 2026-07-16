<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@page chapter:left {
  margin: 0.5in;
  @bottom-left {
    content: "Chapter";
    margin: 10pt;
  }
}

@page toc, index {
  margin: 0.5cm;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
