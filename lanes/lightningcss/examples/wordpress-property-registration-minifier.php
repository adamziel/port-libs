<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@property --wp--custom--card-accent {
  syntax: '<color>';
  inherits: false;
  initial-value: yellow;
}

.wp-block-query .wp-block-post {
  color: var(--wp--custom--card-accent);
}

@property --wp--custom--card-accent {
  initial-value: blue;
  inherits: true;
  syntax: '<color>';
}

@property --wp--custom--motion-duration {
  syntax: '<time>';
  inherits: false;
  initial-value: 1000ms;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
