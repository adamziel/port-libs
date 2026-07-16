<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-cover.is-style-fade-in {
  transition: opacity 200ms ease-in-out;
}

@supports (transition: opacity 200ms) {
  .wp-block-cover.is-style-fade-in {
    transition: opacity 200ms ease-in-out;
  }
}

@supports (transition-property: opacity) {
  .wp-block-navigation__responsive-container {
    transition-property: opacity;
    transition-duration: 100ms;
  }
}
CSS;

$actual = [
    'chrome25' => $prefixer->prefixForTargets($css, ['chrome' => 25]),
    'chrome26' => $prefixer->prefixForTargets($css, ['chrome' => 26]),
    'firefox15' => $prefixer->prefixForTargets($css, ['firefox' => 15]),
    'opera12' => $prefixer->prefixForTargets($css, ['opera' => 12]),
];

$expected = [
    'chrome25' => '.wp-block-cover.is-style-fade-in{-webkit-transition:opacity .2s ease-in-out;transition:opacity .2s ease-in-out}@supports ((-webkit-transition:opacity 200ms) or (transition:opacity 200ms)){.wp-block-cover.is-style-fade-in{-webkit-transition:opacity .2s ease-in-out;transition:opacity .2s ease-in-out}}@supports ((-webkit-transition-property:opacity) or (transition-property:opacity)){.wp-block-navigation__responsive-container{-webkit-transition-property:opacity;transition-property:opacity;-webkit-transition-duration:.1s;transition-duration:.1s}}',
    'chrome26' => '.wp-block-cover.is-style-fade-in{transition:opacity .2s ease-in-out}@supports (transition:opacity 200ms){.wp-block-cover.is-style-fade-in{transition:opacity .2s ease-in-out}}@supports (transition-property:opacity){.wp-block-navigation__responsive-container{transition-property:opacity;transition-duration:.1s}}',
    'firefox15' => '.wp-block-cover.is-style-fade-in{-moz-transition:opacity .2s ease-in-out;transition:opacity .2s ease-in-out}@supports ((-moz-transition:opacity 200ms) or (transition:opacity 200ms)){.wp-block-cover.is-style-fade-in{-moz-transition:opacity .2s ease-in-out;transition:opacity .2s ease-in-out}}@supports ((-moz-transition-property:opacity) or (transition-property:opacity)){.wp-block-navigation__responsive-container{-moz-transition-property:opacity;transition-property:opacity;-moz-transition-duration:.1s;transition-duration:.1s}}',
    'opera12' => '.wp-block-cover.is-style-fade-in{-o-transition:opacity .2s ease-in-out;transition:opacity .2s ease-in-out}@supports ((-o-transition:opacity 200ms) or (transition:opacity 200ms)){.wp-block-cover.is-style-fade-in{-o-transition:opacity .2s ease-in-out;transition:opacity .2s ease-in-out}}@supports ((-o-transition-property:opacity) or (transition-property:opacity)){.wp-block-navigation__responsive-container{-o-transition-property:opacity;transition-property:opacity;-o-transition-duration:.1s;transition-duration:.1s}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected transition supports target-prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
