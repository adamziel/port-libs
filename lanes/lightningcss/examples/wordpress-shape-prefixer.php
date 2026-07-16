<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-image.alignleft.is-style-text-wrap {
  float: left;
  shape-outside: circle(50%);
  shape-margin: 16px;
  shape-image-threshold: .55;
}

@supports (shape-outside: circle(50%)) {
  .wp-block-image.alignleft.is-style-text-wrap img {
    shape-outside: circle(50%);
  }
}
CSS;

$actual = [
    'pre_shape_safari' => $prefixer->prefixForTargets($css, [
        'safari' => 7,
    ]),
    'shape_safari_7_1' => $prefixer->prefixForTargets($css, [
        'safari' => '7.1',
    ]),
    'legacy_safari' => $prefixer->prefixForTargets($css, [
        'safari' => 10,
    ]),
    'legacy_ios' => $prefixer->prefixForTargets($css, [
        'ios_saf' => 8,
    ]),
    'modern_frontend' => $prefixer->prefixForTargets($css, [
        'chrome' => 120,
        'ios_saf' => 11,
        'safari' => 11,
    ]),
];

$expected = [
    'pre_shape_safari' => '.wp-block-image.alignleft.is-style-text-wrap{float:left;shape-outside:circle(50%);shape-margin:16px;shape-image-threshold:.55}@supports (shape-outside:circle(50%)){.wp-block-image.alignleft.is-style-text-wrap img{shape-outside:circle(50%)}}',
    'shape_safari_7_1' => '.wp-block-image.alignleft.is-style-text-wrap{float:left;-webkit-shape-outside:circle(50%);shape-outside:circle(50%);-webkit-shape-margin:16px;shape-margin:16px;-webkit-shape-image-threshold:.55;shape-image-threshold:.55}@supports ((-webkit-shape-outside:circle(50%)) or (shape-outside:circle(50%))){.wp-block-image.alignleft.is-style-text-wrap img{-webkit-shape-outside:circle(50%);shape-outside:circle(50%)}}',
    'legacy_safari' => '.wp-block-image.alignleft.is-style-text-wrap{float:left;-webkit-shape-outside:circle(50%);shape-outside:circle(50%);-webkit-shape-margin:16px;shape-margin:16px;-webkit-shape-image-threshold:.55;shape-image-threshold:.55}@supports ((-webkit-shape-outside:circle(50%)) or (shape-outside:circle(50%))){.wp-block-image.alignleft.is-style-text-wrap img{-webkit-shape-outside:circle(50%);shape-outside:circle(50%)}}',
    'legacy_ios' => '.wp-block-image.alignleft.is-style-text-wrap{float:left;-webkit-shape-outside:circle(50%);shape-outside:circle(50%);-webkit-shape-margin:16px;shape-margin:16px;-webkit-shape-image-threshold:.55;shape-image-threshold:.55}@supports ((-webkit-shape-outside:circle(50%)) or (shape-outside:circle(50%))){.wp-block-image.alignleft.is-style-text-wrap img{-webkit-shape-outside:circle(50%);shape-outside:circle(50%)}}',
    'modern_frontend' => '.wp-block-image.alignleft.is-style-text-wrap{float:left;shape-outside:circle(50%);shape-margin:16px;shape-image-threshold:.55}@supports (shape-outside:circle(50%)){.wp-block-image.alignleft.is-style-text-wrap img{shape-outside:circle(50%)}}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected shape prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
