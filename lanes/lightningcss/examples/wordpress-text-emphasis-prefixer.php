<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content .has-annotation-left {
  text-emphasis-style: filled;
  text-emphasis-position: left over;
}

.wp-block-post-content .has-annotation-right {
  text-emphasis-style: filled;
  text-emphasis-position: right over;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome98' => $prefixer->prefixForTargets($css, ['chrome' => 98]),
    'chrome99' => $prefixer->prefixForTargets($css, ['chrome' => 99]),
];

$expected = [
    'chrome98' => '.wp-block-post-content .has-annotation-left{-webkit-text-emphasis-style:filled;text-emphasis-style:filled;text-emphasis-position:over left}.wp-block-post-content .has-annotation-right{-webkit-text-emphasis-style:filled;text-emphasis-style:filled;-webkit-text-emphasis-position:over;text-emphasis-position:over}',
    'chrome99' => '.wp-block-post-content .has-annotation-left{text-emphasis-style:filled;text-emphasis-position:over left}.wp-block-post-content .has-annotation-right{text-emphasis-style:filled;text-emphasis-position:over}',
];

if (($argv[1] ?? null) === '--self-test' && $actual !== $expected) {
    fwrite(STDERR, "Unexpected text-emphasis prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
