<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content .has-annotation-emphasis {
  text-emphasis: lch(50.998% 135.363 338) var(--wp--custom--annotation-emphasis);
  text-emphasis-position: over;
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 30, 'safari' => 8]) . PHP_EOL;
