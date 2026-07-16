<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-query .wp-block-post-featured-image {
  width: min-content;
  max-width: max-content;
}

.wp-block-media-text.is-style-fit {
  inline-size: fit-content;
  block-size: stretch;
}

.wp-block-group.has-fallback-width {
  width: 100%;
  width: max-content;
}
CSS;

$actual = [
    'safari_6_0' => $prefixer->prefixForTargets($css, ['safari' => 6]),
    'safari_6_1' => $prefixer->prefixForTargets($css, ['safari' => '6.1']),
    'legacy_editor' => $prefixer->prefixForTargets($css, ['safari' => 8, 'firefox' => 4]),
    'chrome_45' => $prefixer->prefixForTargets($css, ['chrome' => 45]),
    'modern_safari' => $prefixer->prefixForTargets($css, ['safari' => 16]),
];

$expected = [
    'safari_6_0' => '.wp-block-query .wp-block-post-featured-image{width:min-content;max-width:max-content}.wp-block-media-text.is-style-fit{width:fit-content;height:stretch}.wp-block-group.has-fallback-width{width:100%;width:max-content}',
    'safari_6_1' => '.wp-block-query .wp-block-post-featured-image{width:-webkit-min-content;width:min-content;max-width:-webkit-max-content;max-width:max-content}.wp-block-media-text.is-style-fit{width:-webkit-fit-content;width:fit-content;height:-webkit-fill-available;height:stretch}.wp-block-group.has-fallback-width{width:100%;width:max-content}',
    'legacy_editor' => '.wp-block-query .wp-block-post-featured-image{width:-webkit-min-content;width:-moz-min-content;width:min-content;max-width:-webkit-max-content;max-width:-moz-max-content;max-width:max-content}.wp-block-media-text.is-style-fit{width:-webkit-fit-content;width:-moz-fit-content;width:fit-content;height:-webkit-fill-available;height:-moz-available;height:stretch}.wp-block-group.has-fallback-width{width:100%;width:max-content}',
    'chrome_45' => '.wp-block-query .wp-block-post-featured-image{width:-webkit-min-content;width:min-content;max-width:-webkit-max-content;max-width:max-content}.wp-block-media-text.is-style-fit{width:-webkit-fit-content;width:fit-content;height:-webkit-fill-available;height:stretch}.wp-block-group.has-fallback-width{width:100%;width:max-content}',
    'modern_safari' => '.wp-block-query .wp-block-post-featured-image{width:min-content;max-width:max-content}.wp-block-media-text.is-style-fit{inline-size:fit-content;block-size:-webkit-fill-available;block-size:stretch}.wp-block-group.has-fallback-width{width:100%;width:max-content}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected intrinsic size prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
