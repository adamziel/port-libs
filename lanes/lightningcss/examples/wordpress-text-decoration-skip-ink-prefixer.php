<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-post-content a.has-skip-ink-underline {
  text-decoration: underline;
  text-decoration-skip-ink: all;
}
CSS;

$actual = [
    'safari12' => $prefixer->prefixForTargets($css, ['safari' => 12]),
    'safari12_1' => $prefixer->prefixForTargets($css, ['safari' => '12.1']),
    'ios17' => $prefixer->prefixForTargets($css, ['ios_saf' => 17]),
];

$expected = [
    'safari12' => '.wp-block-post-content a.has-skip-ink-underline{text-decoration:underline;-webkit-text-decoration-skip-ink:all;text-decoration-skip-ink:all}',
    'safari12_1' => '.wp-block-post-content a.has-skip-ink-underline{text-decoration:underline;text-decoration-skip-ink:all}',
    'ios17' => '.wp-block-post-content a.has-skip-ink-underline{text-decoration:underline;-webkit-text-decoration-skip-ink:all;text-decoration-skip-ink:all}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected text-decoration-skip-ink prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
