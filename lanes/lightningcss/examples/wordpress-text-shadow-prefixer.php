<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-title.has-text-shadow {
  text-shadow: var(--wp--preset--shadow--headline) 12px lab(40% 56.6 39);
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 4]) . PHP_EOL;
