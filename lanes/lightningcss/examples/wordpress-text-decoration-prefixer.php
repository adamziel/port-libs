<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content a.has-brand-underline {
  text-decoration: lch(50.998% 135.363 338) var(--wp--custom--underline-style);
}

.wp-block-post-content a.has-dotted-underline {
  text-decoration: underline;
  text-decoration-style: dotted;
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90, 'safari' => 16]) . PHP_EOL;
