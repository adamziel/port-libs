<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-navigation.is-vertical-labels {
  writing-mode: vertical-rl;
}

.wp-block-post-title.is-side-label {
  writing-mode: horizontal-tb;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome47' => $prefixer->prefixForTargets($css, ['chrome' => 47]),
    'chrome48' => $prefixer->prefixForTargets($css, ['chrome' => 48]),
    'ie11' => $prefixer->prefixForTargets($css, ['ie' => 11]),
];

$expected = [
    'chrome47' => '.wp-block-navigation.is-vertical-labels{-webkit-writing-mode:vertical-rl;writing-mode:vertical-rl}.wp-block-post-title.is-side-label{-webkit-writing-mode:horizontal-tb;writing-mode:horizontal-tb}',
    'chrome48' => '.wp-block-navigation.is-vertical-labels{writing-mode:vertical-rl}.wp-block-post-title.is-side-label{writing-mode:horizontal-tb}',
    'ie11' => '.wp-block-navigation.is-vertical-labels{-ms-writing-mode:tb-rl;writing-mode:vertical-rl}.wp-block-post-title.is-side-label{-ms-writing-mode:lr-tb;writing-mode:horizontal-tb}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected writing-mode prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
