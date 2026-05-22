<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LightningCSS\TransitionPrefixer;

$css = <<<'CSS'
.wp-block-post-template .wp-block-post {
  box-shadow: var(--wp--preset--shadow--card) 12px lab(40% 56.6 39);
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 4]) . PHP_EOL;
