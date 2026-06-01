<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-non-srgb-mix {
  color: color-mix(in lab, purple 50%, plum 50%);
  background-color: color-mix(in lch, teal 65%, olive);
  border-color: color-mix(in oklch, white, blue);
  --wp--preset--color--duotone-mix: color-mix(in lch, teal 65%, olive);
  --wp--preset--color--duotone-highlight: color-mix(in xyz, color(xyz 2 3 4 / 5), color(xyz 4 6 8 / 10));
  --wp--preset--color--duotone-shadow: color-mix(in xyz-d65, color(xyz-d65 -2 -3 -4 / -5), color(xyz-d65 -4 -6 -8 / -10));
}
CSS;

$expected = '.wp-block-cover.has-non-srgb-mix{color:lab(51.5117% 43.3777 -29.0443);background-color:lch(49.4431% 40.4806 162.546);border-color:oklch(72.6007% .156607 264.052);--wp--preset--color--duotone-mix:lch(49.4431% 40.4806 162.546);--wp--preset--color--duotone-highlight:color(xyz 3 4.5 6);--wp--preset--color--duotone-shadow:color(xyz 0 0 0/0)}';
$actual = (new CssMinifier())->minify($css);

if (in_array('--self-test', $argv, true) && $actual !== $expected) {
    fwrite(STDERR, "Unexpected non-sRGB color-mix output:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
