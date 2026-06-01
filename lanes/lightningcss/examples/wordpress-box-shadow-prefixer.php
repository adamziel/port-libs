<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-template .wp-block-post {
  box-shadow: 0 2px 12px #000;
}
CSS;

$prefixer = new TransitionPrefixer();
$encoded = static fn (int $major, int $minor = 0): int => ($major << 16) | ($minor << 8);

$actual = [
    'firefox36' => $prefixer->prefixForTargets($css, ['firefox' => $encoded(3, 6)]),
    'mixedLegacy' => $prefixer->prefixForTargets($css, ['chrome' => 4, 'firefox' => $encoded(3, 6)]),
    'ios31' => $prefixer->prefixForTargets($css, ['ios_saf' => $encoded(3, 1)]),
    'ios32' => $prefixer->prefixForTargets($css, ['ios_saf' => $encoded(3, 2)]),
    'modern' => $prefixer->prefixForTargets($css, ['chrome' => 95, 'firefox' => 4]),
];

$expected = [
    'firefox36' => '.wp-block-post-template .wp-block-post{-moz-box-shadow:0 2px 12px #000;box-shadow:0 2px 12px #000}',
    'mixedLegacy' => '.wp-block-post-template .wp-block-post{-webkit-box-shadow:0 2px 12px #000;-moz-box-shadow:0 2px 12px #000;box-shadow:0 2px 12px #000}',
    'ios31' => '.wp-block-post-template .wp-block-post{box-shadow:0 2px 12px #000}',
    'ios32' => '.wp-block-post-template .wp-block-post{-webkit-box-shadow:0 2px 12px #000;box-shadow:0 2px 12px #000}',
    'modern' => '.wp-block-post-template .wp-block-post{box-shadow:0 2px 12px #000}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected box-shadow prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
