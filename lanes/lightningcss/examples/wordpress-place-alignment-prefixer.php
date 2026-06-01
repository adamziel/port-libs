<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-query .wp-block-post-template {
  place-content: center space-between;
}

.wp-block-query .wp-block-post {
  place-items: center flex-end;
}

.wp-block-query .wp-block-post-title {
  place-self: center flex-end;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'android58' => $prefixer->prefixForTargets($css, ['android' => 58]),
    'safari10' => $prefixer->prefixForTargets($css, ['safari' => 10]),
    'modern_frontend' => $prefixer->prefixForTargets($css, ['chrome' => 120]),
];

$expected = [
    'android58' => '.wp-block-query .wp-block-post-template{align-content:center;justify-content:space-between}.wp-block-query .wp-block-post{align-items:center;justify-items:flex-end}.wp-block-query .wp-block-post-title{align-self:center;justify-self:flex-end}',
    'safari10' => '.wp-block-query .wp-block-post-template{place-content:center space-between}.wp-block-query .wp-block-post{align-items:center;justify-items:flex-end}.wp-block-query .wp-block-post-title{align-self:center;justify-self:flex-end}',
    'modern_frontend' => '.wp-block-query .wp-block-post-template{place-content:center space-between}.wp-block-query .wp-block-post{place-items:center flex-end}.wp-block-query .wp-block-post-title{place-self:center flex-end}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected place alignment prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
