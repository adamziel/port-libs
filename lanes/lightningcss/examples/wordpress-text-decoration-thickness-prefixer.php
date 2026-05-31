<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content a.has-offset-underline {
  text-decoration: underline 10%;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'legacy_safari' => $prefixer->prefixForTargets($css, ['safari' => 12]),
    'modern_firefox' => $prefixer->prefixForTargets($css, ['firefox' => 89]),
];
$expected = [
    'legacy_safari' => '.wp-block-post-content a.has-offset-underline{text-decoration:underline;text-decoration-thickness:calc(1em / 10)}',
    'modern_firefox' => '.wp-block-post-content a.has-offset-underline{text-decoration:underline 10%}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected text-decoration thickness target output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
