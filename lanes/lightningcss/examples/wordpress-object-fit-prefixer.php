<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();
$css = <<<'CSS'
.wp-block-post-featured-image img,
.wp-block-cover video {
  object-fit: cover;
  object-position: center top;
}
CSS;

$actual = [
    'opera10_5' => $prefixer->prefixForTargets($css, ['opera' => '10.5']),
    'opera10_6' => $prefixer->prefixForTargets($css, ['opera' => '10.6']),
    'opera12_1' => $prefixer->prefixForTargets($css, ['opera' => '12.1']),
    'opera13' => $prefixer->prefixForTargets($css, ['opera' => 13]),
];

$expected = [
    'opera10_5' => '.wp-block-post-featured-image img,.wp-block-cover video{object-fit:cover;object-position:center top}',
    'opera10_6' => '.wp-block-post-featured-image img,.wp-block-cover video{-o-object-fit:cover;object-fit:cover;-o-object-position:center top;object-position:center top}',
    'opera12_1' => '.wp-block-post-featured-image img,.wp-block-cover video{-o-object-fit:cover;object-fit:cover;-o-object-position:center top;object-position:center top}',
    'opera13' => '.wp-block-post-featured-image img,.wp-block-cover video{object-fit:cover;object-position:center top}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected object-fit target-boundary output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
