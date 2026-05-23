<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
:root {
  color-scheme: dark light only;
}

.editor-styles-wrapper.is-light-theme {
  color-scheme: only light;
}

.editor-styles-wrapper.is-dark-theme {
  color-scheme: only dark;
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
