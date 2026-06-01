<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-green-overlay {
  color: color-mix(in xyz, transparent, green 65%);
}
CSS;

$expected = '.wp-block-cover.has-green-overlay{color:#008000a6;color:color(xyz .0771883 .154377 .0257295/.65)}';
$actual = (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 95]);

if (in_array('--self-test', $argv, true) && $actual !== $expected) {
    fwrite(STDERR, "Unexpected XYZ color-mix fallback output:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
