<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-tilt:hover {
  transform: rotateX(mod(140deg, -90deg)) rotateY(rem(140deg, -90deg));
  border-width: clamp(1em, 2px, 4vh);
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['safari' => 12]) . PHP_EOL;
