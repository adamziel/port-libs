<?php

declare(strict_types=1);

use PortLibs\LightningCSS\NestingTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-button.is-style-fade-in {
  opacity: 1;
  transform: translateY(0);

  @starting-style {
    opacity: 0;
    transform: translateY(12px);
  }
}
CSS;

echo (new NestingTransformer())->lower($css) . PHP_EOL;
