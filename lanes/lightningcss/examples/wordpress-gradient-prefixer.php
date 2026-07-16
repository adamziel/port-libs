<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-editor-gradient {
  background-image: linear-gradient(red, blue);
}

.wp-block-cover.has-horizontal-gradient {
  background-image: linear-gradient(to right, red, blue);
}
CSS;

$staleCss = <<<'CSS'
.wp-block-cover.has-editor-gradient {
  background-image: -webkit-linear-gradient(top, red, blue);
  background-image: -moz-linear-gradient(top, red, blue);
  background-image: -o-linear-gradient(top, red, blue);
  background-image: linear-gradient(red, blue);
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome8' => $prefixer->prefixForTargets($css, ['chrome' => 8]),
    'chrome10' => $prefixer->prefixForTargets($css, ['chrome' => 10]),
    'modern' => $prefixer->prefixForTargets($staleCss, ['chrome' => 95]),
];

$expected = [
    'chrome8' => '.wp-block-cover.has-editor-gradient{background-image:-webkit-gradient(linear,0 0,0 100%,from(red),to(#00f));background-image:-webkit-linear-gradient(top,red,#00f);background-image:linear-gradient(red,#00f)}.wp-block-cover.has-horizontal-gradient{background-image:-webkit-gradient(linear,0 0,100% 0,from(red),to(#00f));background-image:-webkit-linear-gradient(left,red,#00f);background-image:linear-gradient(to right,red,#00f)}',
    'chrome10' => '.wp-block-cover.has-editor-gradient{background-image:-webkit-linear-gradient(top,red,#00f);background-image:linear-gradient(red,#00f)}.wp-block-cover.has-horizontal-gradient{background-image:-webkit-linear-gradient(left,red,#00f);background-image:linear-gradient(to right,red,#00f)}',
    'modern' => '.wp-block-cover.has-editor-gradient{background-image:linear-gradient(red,#00f)}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected gradient prefixer output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
