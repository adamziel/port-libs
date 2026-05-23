<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content .print-cover {
  print-color-adjust: exact;
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 135]) . PHP_EOL;
