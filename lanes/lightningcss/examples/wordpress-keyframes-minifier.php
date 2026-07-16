<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@keyframes "wp-cover-reveal" {
  from {
    opacity: 0;
    transform: translateY(calc(10px + 20px));
  }

  100% {
    opacity: 1;
    background: blue;
  }
}

.wp-block-cover.is-style-reveal {
  animation: "wp-cover-reveal" 600ms cubic-bezier(0.42, 0, 1, 1) both;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
