<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-list.is-style-gradient-markers {
  list-style: var(--wp--custom--list-marker) linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364));
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90]) . PHP_EOL;
