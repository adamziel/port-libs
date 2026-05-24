<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@keyframes "wp-cover-reveal" {
  from {
    opacity: 0;
  }

  100% {
    opacity: 1;
  }
}

.wp-block-cover.is-style-reveal {
  animation: "wp-cover-reveal" 600ms ease-in both;
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['safari' => 8]) . PHP_EOL;
