<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();
$css = <<<'CSS'
.wp-block-template-part.is-style-glass-header {
  backdrop-filter: blur(8px);
  filter: var(--wp--custom--header-filter);
}

@supports (filter: blur(8px)) {
  .wp-block-template-part.is-style-glass-header .wp-block-site-logo {
    filter: blur(8px);
  }
}
CSS;

$actual = [
    'chrome52_safari14' => $prefixer->prefixForTargets($css, ['chrome' => 52, 'safari' => 14]),
    'chrome53_safari14' => $prefixer->prefixForTargets($css, ['chrome' => 53, 'safari' => 14]),
];

$expected = [
    'chrome52_safari14' => '.wp-block-template-part.is-style-glass-header{-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);-webkit-filter:var(--wp--custom--header-filter);filter:var(--wp--custom--header-filter)}@supports ((-webkit-filter:blur(8px)) or (filter:blur(8px))){.wp-block-template-part.is-style-glass-header .wp-block-site-logo{-webkit-filter:blur(8px);filter:blur(8px)}}',
    'chrome53_safari14' => '.wp-block-template-part.is-style-glass-header{-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);filter:var(--wp--custom--header-filter)}@supports (filter:blur(8px)){.wp-block-template-part.is-style-glass-header .wp-block-site-logo{filter:blur(8px)}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected filter target-boundary output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
