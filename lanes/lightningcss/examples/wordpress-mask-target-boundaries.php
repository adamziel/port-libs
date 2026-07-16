<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();
$css = <<<'CSS'
.wp-block-cover.is-style-soft-mask {
  mask-image: linear-gradient(red, green);
  transition: mask 200ms;
}
CSS;

$actual = [
    'chrome119' => $prefixer->prefixForTargets($css, ['chrome' => 119]),
    'chrome120' => $prefixer->prefixForTargets($css, ['chrome' => 120]),
    'safari15_2' => $prefixer->prefixForTargets($css, ['safari' => '15.2']),
    'safari15_3' => $prefixer->prefixForTargets($css, ['safari' => '15.3']),
    'ios15_2' => $prefixer->prefixForTargets($css, ['ios_saf' => '15.2']),
    'ios15_3' => $prefixer->prefixForTargets($css, ['ios_saf' => '15.3']),
];

$expected = [
    'chrome119' => '.wp-block-cover.is-style-soft-mask{-webkit-mask-image:linear-gradient(red,green);mask-image:linear-gradient(red,green);transition:-webkit-mask .2s,mask .2s}',
    'chrome120' => '.wp-block-cover.is-style-soft-mask{mask-image:linear-gradient(red,green);transition:mask .2s}',
    'safari15_2' => '.wp-block-cover.is-style-soft-mask{-webkit-mask-image:linear-gradient(red,green);mask-image:linear-gradient(red,green);transition:-webkit-mask .2s,mask .2s}',
    'safari15_3' => '.wp-block-cover.is-style-soft-mask{mask-image:linear-gradient(red,green);transition:mask .2s}',
    'ios15_2' => '.wp-block-cover.is-style-soft-mask{-webkit-mask-image:linear-gradient(red,green);mask-image:linear-gradient(red,green);transition:-webkit-mask .2s,mask .2s}',
    'ios15_3' => '.wp-block-cover.is-style-soft-mask{mask-image:linear-gradient(red,green);transition:mask .2s}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected mask target-boundary output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
