<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();
$css = <<<'CSS'
.wp-block-query.is-style-scroll-strip {
  overflow: hidden auto;
}

.wp-block-gallery.has-cropped-preview {
  overflow: clip hidden;
}
CSS;

$actual = [
    'chrome67' => $prefixer->prefixForTargets($css, ['chrome' => 67]),
    'chrome68' => $prefixer->prefixForTargets($css, ['chrome' => 68]),
    'safari13' => $prefixer->prefixForTargets($css, ['safari' => 13]),
    'safari13_1' => $prefixer->prefixForTargets($css, ['safari' => '13.1']),
];

$expected = [
    'chrome67' => '.wp-block-query.is-style-scroll-strip{overflow-x:hidden;overflow-y:auto}.wp-block-gallery.has-cropped-preview{overflow-x:clip;overflow-y:hidden}',
    'chrome68' => '.wp-block-query.is-style-scroll-strip{overflow:hidden auto}.wp-block-gallery.has-cropped-preview{overflow:clip hidden}',
    'safari13' => '.wp-block-query.is-style-scroll-strip{overflow-x:hidden;overflow-y:auto}.wp-block-gallery.has-cropped-preview{overflow-x:clip;overflow-y:hidden}',
    'safari13_1' => '.wp-block-query.is-style-scroll-strip{overflow:hidden auto}.wp-block-gallery.has-cropped-preview{overflow:clip hidden}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected overflow target-boundary output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
