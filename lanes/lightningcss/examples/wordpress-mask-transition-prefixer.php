<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-framed {
  transition: mask-border 200ms, mask 400ms;
}
CSS;

echo (new TransitionPrefixer())->prefixLegacySafari($css) . PHP_EOL;
