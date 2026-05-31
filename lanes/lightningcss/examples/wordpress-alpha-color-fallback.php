<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-alpha-overlay {
  color: rgba(0, 0, 0, 0);
  background-color: rgba(123, 456, 789, 0.5);
}

.wp-block-button .wp-element-button {
  border-color: #7bffff80;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome61' => $prefixer->prefixForTargets($css, ['chrome' => 61]),
    'chrome95' => $prefixer->prefixForTargets($css, ['chrome' => 95]),
    'ie11' => $prefixer->prefixForTargets($css, ['ie' => 11]),
];

$expected = [
    'chrome61' => '.wp-block-cover.has-alpha-overlay{color:transparent;background-color:rgba(123,255,255,.5)}.wp-block-button .wp-element-button{border-color:rgba(123,255,255,.5)}',
    'chrome95' => '.wp-block-cover.has-alpha-overlay{color:#0000;background-color:#7bffff80}.wp-block-button .wp-element-button{border-color:#7bffff80}',
    'ie11' => '.wp-block-cover.has-alpha-overlay{color:transparent;background-color:rgba(123,255,255,.5)}.wp-block-button .wp-element-button{border-color:rgba(123,255,255,.5)}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected alpha color fallback output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
