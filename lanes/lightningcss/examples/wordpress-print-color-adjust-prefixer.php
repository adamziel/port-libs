<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content .print-cover {
  print-color-adjust: exact;
}
CSS;

$staleCss = <<<'CSS'
.wp-block-post-content .print-cover {
  -webkit-print-color-adjust: exact;
  -moz-print-color-adjust: exact;
  print-color-adjust: exact;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome135' => $prefixer->prefixForTargets($css, ['chrome' => 135]),
    'chrome136' => $prefixer->prefixForTargets($css, ['chrome' => 136]),
    'firefox96' => $prefixer->prefixForTargets($css, ['firefox' => 96]),
    'safari15_2' => $prefixer->prefixForTargets($css, ['safari' => '15.2']),
    'safari15_3' => $prefixer->prefixForTargets($css, ['safari' => '15.3']),
    'samsung28' => $prefixer->prefixForTargets($css, ['samsung' => 28]),
    'samsung29' => $prefixer->prefixForTargets($css, ['samsung' => 29]),
    'chrome135_firefox96' => $prefixer->prefixForTargets($css, ['chrome' => 135, 'firefox' => 96]),
    'modern_stale_cleanup' => $prefixer->prefixForTargets($staleCss, ['chrome' => 136, 'firefox' => 97]),
    'chrome135_stale_cleanup' => $prefixer->prefixForTargets($staleCss, ['chrome' => 135]),
    'firefox96_stale_cleanup' => $prefixer->prefixForTargets($staleCss, ['firefox' => 96]),
];

$expected = [
    'chrome135' => '.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}',
    'chrome136' => '.wp-block-post-content .print-cover{print-color-adjust:exact}',
    'firefox96' => '.wp-block-post-content .print-cover{-moz-print-color-adjust:exact;print-color-adjust:exact}',
    'safari15_2' => '.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}',
    'safari15_3' => '.wp-block-post-content .print-cover{print-color-adjust:exact}',
    'samsung28' => '.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}',
    'samsung29' => '.wp-block-post-content .print-cover{print-color-adjust:exact}',
    'chrome135_firefox96' => '.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;-moz-print-color-adjust:exact;print-color-adjust:exact}',
    'modern_stale_cleanup' => '.wp-block-post-content .print-cover{print-color-adjust:exact}',
    'chrome135_stale_cleanup' => '.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}',
    'firefox96_stale_cleanup' => '.wp-block-post-content .print-cover{-moz-print-color-adjust:exact;print-color-adjust:exact}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected print-color-adjust prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
