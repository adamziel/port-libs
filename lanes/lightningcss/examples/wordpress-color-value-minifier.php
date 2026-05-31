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

.wp-block-cover.has-calculated-overlay {
  --wp-cover-overlay: rgb(50% 50% 50% / calc(100% / 2));
  --wp-cover-halo: hsl(calc(360deg / 2) 50% 50%);
  --wp-cover-gamut: color(display-p3 0.43313 0.50108 calc(0.1 + 0.2));
  color: var(--wp-cover-overlay);
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

.wp-block-cover.has-hsl-mixed-overlay {
  color: color-mix(in hsl, hsl(120deg 10% 20%), hsl(30deg 30% 40%));
  background-color: color-mix(in hsl, hsl(120deg 10% 20% / .4), hsl(30deg 30% 40% / .8));
  border-color: color-mix(in hsl longer hue, hsl(40deg 50% 50%), hsl(60deg 50% 50%));
}

.wp-block-cover.has-hwb-mixed-overlay {
  color: color-mix(in hwb, hwb(120deg 10% 20%), hwb(30deg 30% 40%));
  background-color: color-mix(in hwb, hwb(120deg 10% 20%) 12.5%, hwb(30deg 30% 40%) 37.5%);
  border-color: color-mix(in hwb, hwb(120deg 10% 20%), 25% hwb(30deg 30% 40%));
}

.wp-block-cover.has-xyz-mixed-overlay {
  color: color-mix(in xyz, color(xyz .1 .2 .3), color(xyz .5 .6 .7));
  background-color: color-mix(in xyz-d65, color(xyz-d65 .1 .2 .3 / .5), color(xyz-d65 .5 .6 .7 / .8));
  outline-color: color-mix(in srgb-linear, color(srgb-linear .1 .2 .3) 12.5%, color(srgb-linear .5 .6 .7) 37.5%);
}

.wp-block-cover.has-polar-mixed-overlay {
  color: color-mix(in lch, lch(10% 20 30deg), lch(50% 60 70deg));
  background-color: color-mix(in oklch longer hue, oklch(100% 0 40deg), oklch(100% 0 60deg));
  outline-color: color-mix(in lch, lch(none 20 30deg), lch(50% none 70deg));
}
CSS;

$expected = '.wp-block-cover.has-hwb-overlay{color:#00c4ff80;background:linear-gradient(#80e1ff,#006280);border-color:buttonborder}.wp-block-button .wp-element-button{color:#fff;outline-color:#000}.wp-block-cover.has-relative-overlay{color:#639;background-color:#360c;border-color:#66660a}.wp-block-cover.has-calculated-overlay{--wp-cover-overlay:#80808080;--wp-cover-halo:#40bfbf;--wp-cover-gamut:color(display-p3 .43313 .50108 .3);color:var(--wp-cover-overlay)}.wp-block-cover.has-wide-gamut-overlay{background:linear-gradient(lch(29.2345% 66.3 27),color(display-p3 1 .5 0/.2));outline-color:oklab(40.101% .1147 .0453)}.wp-block-cover.has-mixed-overlay{color:#8080ff;background-color:#89760053}.wp-block-cover.has-perceptual-mixed-overlay{color:lab(40% 50 60);background-color:oklab(36.6667% 46.6667 56.6667/.6);outline-color:lab(30% 40 70)}.wp-block-cover.has-hsl-mixed-overlay{color:#545c3d;background-color:#5f694199;border-color:#4055bf}.wp-block-cover.has-hwb-mixed-overlay{color:#93b334;background-color:#a6994080;border-color:#60bf27}.wp-block-cover.has-xyz-mixed-overlay{color:color(xyz .3 .4 .5);background-color:color(xyz .346154 .446154 .546154/.65);outline-color:color(srgb-linear .4 .5 .6/.5)}.wp-block-cover.has-polar-mixed-overlay{color:lch(30% 40 50);background-color:oklch(100% 0 230);outline-color:lch(50% 20 50)}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
