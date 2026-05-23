<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-query {
  gap: round(23px, 5px);
  margin-block-start: rem(38px, 12px);
  padding-inline: mod(42px, 16px);
  width: calc(10px * round(22, 5));
  border-width: clamp(1rem + 1rem, 1rem + 3rem, 6rem);
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
