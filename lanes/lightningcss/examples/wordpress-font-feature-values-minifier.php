<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@font-feature-values "Inter", "Inter Variable" {
  @styleset {
    open-digits: 1;
  }
  @styleset {
    disambiguation: 2;
  }
}

@font-feature-values "Inter", "Inter Variable" {
  @character-variant {
    single-storey-a: 11;
  }
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
