<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-site-blocks,
.wp-block-group,
.wp-block-cover__inner-container {
  box-sizing: border-box;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome9' => $prefixer->prefixForTargets($css, ['chrome' => 9]),
    'firefox28' => $prefixer->prefixForTargets($css, ['firefox' => 28]),
    'modern' => $prefixer->prefixForTargets(
        '.wp-block-group{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}',
        ['chrome' => 10, 'firefox' => 29]
    ),
];

$expected = [
    'chrome9' => '.wp-site-blocks,.wp-block-group,.wp-block-cover__inner-container{-webkit-box-sizing:border-box;box-sizing:border-box}',
    'firefox28' => '.wp-site-blocks,.wp-block-group,.wp-block-cover__inner-container{-moz-box-sizing:border-box;box-sizing:border-box}',
    'modern' => '.wp-block-group{box-sizing:border-box}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected box-sizing prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
