<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
:root {
  color-scheme: light dark;
}

.editor-styles-wrapper.is-dark-theme {
  color-scheme: dark;
}

.editor-styles-wrapper .has-accent-color {
  color: light-dark(var(--wp--preset--color--accent-light), var(--wp--preset--color--accent-dark));
}

.editor-styles-wrapper .has-warning-background {
  background-color: color-mix(in srgb, light-dark(yellow, red), light-dark(red, pink));
}

.editor-styles-wrapper .has-alpha-accent-color {
  color: rgb(from light-dark(yellow, red) r g b / var(--wp--custom--alpha));
}
CSS;

echo (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90]) . PHP_EOL;
