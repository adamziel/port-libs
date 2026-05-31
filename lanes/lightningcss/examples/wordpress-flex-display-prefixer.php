<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-columns.is-layout-flex {
  display: flex;
}

.wp-block-navigation .wp-block-navigation__container {
  display: inline-flex;
}

.wp-block-buttons.is-layout-flex {
  display: -webkit-box;
  display: -moz-box;
  display: -webkit-flex;
  display: -ms-flexbox;
  display: flex;
}

.wp-block-group.is-style-legacy-flex-fallback {
  display: flex;
  display: -webkit-box;
}

.wp-block-navigation .wp-block-navigation__responsive-container {
  display: -webkit-box;
  display: flex;
  display: -moz-box;
  display: -webkit-flex;
  display: -ms-flexbox;
}
CSS;

$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, [
        'safari' => 4,
        'firefox' => 14,
        'ie' => 10,
    ]),
    'chrome_28' => $prefixer->prefixForTargets($css, ['chrome' => 28]),
    'modern_frontend' => $prefixer->prefixForTargets($css, ['chrome' => 29]),
];

$expected = [
    'legacy_editor' => '.wp-block-columns.is-layout-flex{display:-webkit-box;display:-moz-box;display:-webkit-flex;display:-ms-flexbox;display:flex}.wp-block-navigation .wp-block-navigation__container{display:-webkit-inline-box;display:-moz-inline-box;display:-webkit-inline-flex;display:-ms-inline-flexbox;display:inline-flex}.wp-block-buttons.is-layout-flex{display:-webkit-box;display:-moz-box;display:-webkit-flex;display:-ms-flexbox;display:flex}.wp-block-group.is-style-legacy-flex-fallback{display:-webkit-box}.wp-block-navigation .wp-block-navigation__responsive-container{display:-moz-box;display:-webkit-flex;display:-ms-flexbox}',
    'chrome_28' => '.wp-block-columns.is-layout-flex{display:-webkit-flex;display:flex}.wp-block-navigation .wp-block-navigation__container{display:-webkit-inline-flex;display:inline-flex}.wp-block-buttons.is-layout-flex{display:-webkit-flex;display:flex}.wp-block-group.is-style-legacy-flex-fallback{display:-webkit-box}.wp-block-navigation .wp-block-navigation__responsive-container{display:-moz-box;display:-webkit-flex;display:-ms-flexbox}',
    'modern_frontend' => '.wp-block-columns.is-layout-flex{display:flex}.wp-block-navigation .wp-block-navigation__container{display:inline-flex}.wp-block-buttons.is-layout-flex{display:flex}.wp-block-group.is-style-legacy-flex-fallback{display:-webkit-box}.wp-block-navigation .wp-block-navigation__responsive-container{display:-moz-box;display:-webkit-flex;display:-ms-flexbox}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected flex display prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
