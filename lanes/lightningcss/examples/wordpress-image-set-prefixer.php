<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.is-style-responsive-art {
  background-image: image-set(url(hero-small.jpg) 1x, url("hero-large.jpg") 2x);
}

.wp-block-list.is-style-retina-markers {
  list-style-image: image-set(url(marker.png) 1x, url("marker@2x.png") 2x);
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 95]) . PHP_EOL;
