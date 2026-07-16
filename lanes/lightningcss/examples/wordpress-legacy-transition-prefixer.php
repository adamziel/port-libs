<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\TransitionPrefixer;

$css = <<<'CSS'
.wp-block-navigation .wp-block-navigation-item {
  transition: margin-inline-start 200ms, transform 200ms;
}
CSS;

echo (new TransitionPrefixer())->prefixLegacySafari($css) . PHP_EOL;
