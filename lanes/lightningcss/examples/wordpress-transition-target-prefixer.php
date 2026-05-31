<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-button.is-style-legacy-reveal .wp-block-button__link {
  transition: opacity 200ms ease-in-out 100ms;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'legacy_webkit_moz' => $prefixer->prefixForTargets($css, ['safari' => 5, 'firefox' => 14]),
    'legacy_opera' => $prefixer->prefixForTargets($css, ['opera' => 12]),
    'modern_chrome' => $prefixer->prefixForTargets($css, ['chrome' => 26]),
];

$expected = [
    'legacy_webkit_moz' => '.wp-block-button.is-style-legacy-reveal .wp-block-button__link{-webkit-transition:opacity .2s ease-in-out .1s;-moz-transition:opacity .2s ease-in-out .1s;transition:opacity .2s ease-in-out .1s}',
    'legacy_opera' => '.wp-block-button.is-style-legacy-reveal .wp-block-button__link{-o-transition:opacity .2s ease-in-out .1s;transition:opacity .2s ease-in-out .1s}',
    'modern_chrome' => '.wp-block-button.is-style-legacy-reveal .wp-block-button__link{transition:opacity .2s ease-in-out .1s}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected transition target prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
