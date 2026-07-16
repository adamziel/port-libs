<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-channel-carry-mix {
  color: color-mix(in srgb, rgb(128 128 none), rgb(none none 128));
  background-color: color-mix(in srgb, rgb(50% 50% none), rgb(none none 50%));
  border-color: color-mix(in srgb, rgb(none 50% none), rgb(50% none 50%));
}
CSS;

$expected = '.wp-block-cover.has-channel-carry-mix{color:gray;background-color:gray;border-color:gray}';
$actual = (new CssMinifier())->minify($css);

if (in_array('--self-test', $argv, true) && $actual !== $expected) {
    fwrite(STDERR, "Unexpected missing-channel color-mix output:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
