<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@supports (backdrop-filter: blur(12px)) {
  .wp-block-template-part.is-style-glass-header {
    backdrop-filter: blur(12px);
  }
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['safari' => 14]) . PHP_EOL;
