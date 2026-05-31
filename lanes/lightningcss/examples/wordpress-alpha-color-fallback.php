<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-alpha-overlay {
  color: rgba(0, 0, 0, 0);
  background-color: rgba(123, 456, 789, 0.5);
  --wp-overlay-rgb: rgb(50% 50% 50% / var(--alpha));
  --wp-overlay-relative: rgb(from yellow r g b / var(--alpha));
  --wp-overlay-hsl: hsl(270 100% 50% / var(--alpha));
  --wp-overlay-hsl-relative: hsl(from yellow h s l / var(--alpha));
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
    'safari11' => $prefixer->prefixForTargets($css, ['safari' => 11]),
    'safari13' => $prefixer->prefixForTargets($css, ['safari' => 13]),
];

$expected = [
    'chrome61' => '.wp-block-cover.has-alpha-overlay{color:transparent;background-color:rgba(123,255,255,.5);--wp-overlay-rgb:rgb(50% 50% 50%/var(--alpha));--wp-overlay-relative:rgb(from yellow r g b/var(--alpha));--wp-overlay-hsl:hsl(270 100% 50%/var(--alpha));--wp-overlay-hsl-relative:hsl(from yellow h s l/var(--alpha))}.wp-block-button .wp-element-button{border-color:rgba(123,255,255,.5)}',
    'chrome95' => '.wp-block-cover.has-alpha-overlay{color:#0000;background-color:#7bffff80;--wp-overlay-rgb:rgb(50% 50% 50%/var(--alpha));--wp-overlay-relative:rgb(from yellow r g b/var(--alpha));--wp-overlay-hsl:hsl(270 100% 50%/var(--alpha));--wp-overlay-hsl-relative:hsl(from yellow h s l/var(--alpha))}.wp-block-button .wp-element-button{border-color:#7bffff80}',
    'ie11' => '.wp-block-cover.has-alpha-overlay{color:transparent;background-color:rgba(123,255,255,.5);--wp-overlay-rgb:rgb(50% 50% 50%/var(--alpha));--wp-overlay-relative:rgb(from yellow r g b/var(--alpha));--wp-overlay-hsl:hsl(270 100% 50%/var(--alpha));--wp-overlay-hsl-relative:hsl(from yellow h s l/var(--alpha))}.wp-block-button .wp-element-button{border-color:rgba(123,255,255,.5)}',
    'safari11' => '.wp-block-cover.has-alpha-overlay{color:#0000;background-color:#7bffff80;--wp-overlay-rgb:rgba(128,128,128,var(--alpha));--wp-overlay-relative:rgba(255,255,0,var(--alpha));--wp-overlay-hsl:hsla(270,100%,50%,var(--alpha));--wp-overlay-hsl-relative:hsla(60,100%,50%,var(--alpha))}.wp-block-button .wp-element-button{border-color:#7bffff80}',
    'safari13' => '.wp-block-cover.has-alpha-overlay{color:#0000;background-color:#7bffff80;--wp-overlay-rgb:rgb(128 128 128/var(--alpha));--wp-overlay-relative:rgb(255 255 0/var(--alpha));--wp-overlay-hsl:hsl(270 100% 50%/var(--alpha));--wp-overlay-hsl-relative:hsl(from yellow h s l/var(--alpha))}.wp-block-button .wp-element-button{border-color:#7bffff80}',
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
