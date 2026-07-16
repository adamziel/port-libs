<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content .legacy-print-cover {
  color-adjust: exact;
}
CSS;

$staleCss = <<<'CSS'
.wp-block-post-content .legacy-print-cover {
  -webkit-color-adjust: exact;
  -moz-color-adjust: exact;
  color-adjust: exact;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome135' => $prefixer->prefixForTargets($css, ['chrome' => 135]),
    'chrome136' => $prefixer->prefixForTargets($css, ['chrome' => 136]),
    'firefox96' => $prefixer->prefixForTargets($css, ['firefox' => 96]),
    'firefox97' => $prefixer->prefixForTargets($css, ['firefox' => 97]),
    'safari15_2' => $prefixer->prefixForTargets($css, ['safari' => '15.2']),
    'safari15_3' => $prefixer->prefixForTargets($css, ['safari' => '15.3']),
    'edge135' => $prefixer->prefixForTargets($css, ['edge' => 135]),
    'edge136' => $prefixer->prefixForTargets($css, ['edge' => 136]),
    'chrome135_firefox96' => $prefixer->prefixForTargets($css, ['chrome' => 135, 'firefox' => 96]),
    'modern_stale_cleanup' => $prefixer->prefixForTargets($staleCss, ['chrome' => 136, 'firefox' => 97]),
    'chrome135_stale_cleanup' => $prefixer->prefixForTargets($staleCss, ['chrome' => 135]),
    'firefox96_stale_cleanup' => $prefixer->prefixForTargets($staleCss, ['firefox' => 96]),
];

$expected = [
    'chrome135' => '.wp-block-post-content .legacy-print-cover{-webkit-color-adjust:exact;color-adjust:exact}',
    'chrome136' => '.wp-block-post-content .legacy-print-cover{color-adjust:exact}',
    'firefox96' => '.wp-block-post-content .legacy-print-cover{-moz-color-adjust:exact;color-adjust:exact}',
    'firefox97' => '.wp-block-post-content .legacy-print-cover{color-adjust:exact}',
    'safari15_2' => '.wp-block-post-content .legacy-print-cover{-webkit-color-adjust:exact;color-adjust:exact}',
    'safari15_3' => '.wp-block-post-content .legacy-print-cover{color-adjust:exact}',
    'edge135' => '.wp-block-post-content .legacy-print-cover{-webkit-color-adjust:exact;color-adjust:exact}',
    'edge136' => '.wp-block-post-content .legacy-print-cover{color-adjust:exact}',
    'chrome135_firefox96' => '.wp-block-post-content .legacy-print-cover{-webkit-color-adjust:exact;-moz-color-adjust:exact;color-adjust:exact}',
    'modern_stale_cleanup' => '.wp-block-post-content .legacy-print-cover{color-adjust:exact}',
    'chrome135_stale_cleanup' => '.wp-block-post-content .legacy-print-cover{-webkit-color-adjust:exact;color-adjust:exact}',
    'firefox96_stale_cleanup' => '.wp-block-post-content .legacy-print-cover{-moz-color-adjust:exact;color-adjust:exact}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected color-adjust prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
