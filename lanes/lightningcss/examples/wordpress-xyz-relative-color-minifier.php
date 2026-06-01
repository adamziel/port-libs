<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-xyz-relative-overlay {
  color: color(from color(xyz 7 -20.5 100) xyz y z x);
  background-color: color(from color(xyz-d50 7 -20.5 100 / 40%) xyz-d50 calc(x) calc(y) calc(z) / calc(alpha));
  outline-color: color(from color(xyz-d65 none none none / none) xyz-d65 x y z / alpha);
  border-color: color(from color(xyz 7 -20.5 100) xyz none none none / none);
}
CSS;

$expected = '.wp-block-cover.has-xyz-relative-overlay{color:color(xyz -20.5 100 7);background-color:color(xyz-d50 7 -20.5 100/.4);outline-color:color(xyz 0 0 0/0);border-color:color(xyz none none none/none)}';
$actual = (new CssMinifier())->minify($css);

if (in_array('--self-test', $argv, true) && $actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n" . $actual . PHP_EOL);
    exit(1);
}

echo $actual . PHP_EOL;
