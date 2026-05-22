<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-search .wp-block-search__input {
  caret: lch(50.998% 135.363 338) var(--wp--custom--editor-caret-shape);
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90]) . PHP_EOL;
