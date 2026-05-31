<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-button .wp-element-button:focus-visible {
  outline-color: lab(40% 56.6 39);
}

.wp-block-navigation a:focus-visible {
  outline: var(--wp--custom--focus-ring-width) solid lab(40% 56.6 39);
}
CSS;

$actual = [
    'legacy_chrome' => $prefixer->prefixForTargets($css, ['chrome' => 90]),
    'modern_chrome' => $prefixer->prefixForTargets($css, ['chrome' => 111]),
];

$expected = [
    'legacy_chrome' => '.wp-block-button .wp-element-button:focus-visible{outline-color:#b32323;outline-color:lab(40% 56.6 39)}.wp-block-navigation a:focus-visible{outline:var(--wp--custom--focus-ring-width) solid #b32323}@supports (color:lab(0% 0 0)){.wp-block-navigation a:focus-visible{outline:var(--wp--custom--focus-ring-width) solid lab(40% 56.6 39)}}',
    'modern_chrome' => '.wp-block-button .wp-element-button:focus-visible{outline-color:lab(40% 56.6 39)}.wp-block-navigation a:focus-visible{outline:var(--wp--custom--focus-ring-width) solid lab(40% 56.6 39)}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected outline color fallback output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
