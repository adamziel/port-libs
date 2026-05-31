<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-hwb-overlay {
  color: hwb(194 0% 0% / 50%);
  background: linear-gradient(hwb(194 50% 0%), hwb(194 0% 50%));
  border-color: ButtonBorder;
}

.wp-block-button .wp-element-button {
  color: light-dark(#FFF, #FFF);
  outline-color: hsl(none none none);
}

.wp-block-cover.has-relative-overlay {
  color: rgb(from rebeccapurple r g b / alpha);
  background-color: rgb(from rgb(20%, 40%, 60%, 80%) r g none / alpha);
  border-color: rgb(from rebeccapurple r calc(g * 2) 10);
}

.wp-block-cover.has-wide-gamut-overlay {
  background: linear-gradient(lch(29.2345% 44.2% 27deg), color(display-p3 100% 50% 0 / 20%));
  outline-color: oklab(.40101 0.1147 0.0453);
}

.wp-block-cover.has-mixed-overlay {
  color: color-mix(in srgb, white, blue);
  background-color: color-mix(in srgb, rgb(100% 0% 0% / 0.7) 25%, rgb(0% 100% 0% / 0.2));
}

.wp-block-cover.has-perceptual-mixed-overlay {
  color: color-mix(in lab, lab(10% 20 30) 25%, lab(50% 60 70));
  background-color: color-mix(in oklab, oklab(10% 20 30 / .4), oklab(50% 60 70 / .8));
  outline-color: color-mix(in lab, lab(10% 20 none), lab(50% 60 70));
}

.wp-block-cover.has-polar-mixed-overlay {
  color: color-mix(in lch, lch(10% 20 30deg), lch(50% 60 70deg));
  background-color: color-mix(in oklch longer hue, oklch(100% 0 40deg), oklch(100% 0 60deg));
  outline-color: color-mix(in lch, lch(none 20 30deg), lch(50% none 70deg));
}
CSS;

$expected = '.wp-block-cover.has-hwb-overlay{color:#00c4ff80;background:linear-gradient(#80e1ff,#006280);border-color:buttonborder}.wp-block-button .wp-element-button{color:#fff;outline-color:#000}.wp-block-cover.has-relative-overlay{color:#639;background-color:#360c;border-color:#66660a}.wp-block-cover.has-wide-gamut-overlay{background:linear-gradient(lch(29.2345% 66.3 27),color(display-p3 1 .5 0/.2));outline-color:oklab(40.101% .1147 .0453)}.wp-block-cover.has-mixed-overlay{color:#8080ff;background-color:#89760053}.wp-block-cover.has-perceptual-mixed-overlay{color:lab(40% 50 60);background-color:oklab(36.6667% 46.6667 56.6667/.6);outline-color:lab(30% 40 70)}.wp-block-cover.has-polar-mixed-overlay{color:lch(30% 40 50);background-color:oklch(100% 0 230);outline-color:lch(50% 20 50)}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
