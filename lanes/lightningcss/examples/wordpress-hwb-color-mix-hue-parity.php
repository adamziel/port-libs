<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-hwb-hue-mix {
  color: color-mix(in hwb, hwb(50deg 30% 40%), hwb(330deg 30% 40%));
  background-color: color-mix(in hwb shorter hue, hwb(20deg 30% 40%), hwb(320deg 30% 40%));
  border-color: color-mix(in hwb longer hue, hwb(40deg 30% 40%), hwb(60deg 30% 40%));
  outline-color: color-mix(in hwb increasing hue, hwb(60deg 30% 40%), hwb(40deg 30% 40%));
  text-decoration-color: color-mix(in hwb decreasing hue, hwb(50deg 30% 40%), hwb(330deg 30% 40%));
}
CSS;

$expected = '.wp-block-cover.has-hwb-hue-mix{color:#99594d;background-color:#994d59;border-color:#4d5999;outline-color:#4d5999;text-decoration-color:#99594d}';
$actual = (new CssMinifier())->minify($css);

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected HWB color-mix hue output:\n{$actual}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual . PHP_EOL;
