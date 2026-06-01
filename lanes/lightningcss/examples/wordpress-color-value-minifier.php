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

.wp-block-cover.has-rgb-overlay {
  color: rgb(255, 255, 0);
  background-color: rgba(255, 255, 0, 0.8);
  border-color: rgb(128, 128, 128);
  outline-color: rgb(123 255 255);
  text-decoration-color: rgba(123, 255, 255, 0.5);
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

.wp-block-cover.has-relative-hsl-hwb-overlay {
  color: hsl(from rebeccapurple h 20% l / alpha);
  background-color: hwb(from rgb(20%, 40%, 60%, 80%) h w 20% / alpha);
  outline-color: hsl(from hsl(120deg 20% 50% / .5) none s l / alpha);
  border-color: hwb(from hwb(120deg 20% 50% / .5) h w none / alpha);
  text-decoration-color: hsl(from rgb(20%, 40%, 60%, 80%) h s 20% / alpha);
  caret-color: hsl(from rgb(20%, 40%, 60%, 80%) 0deg s l / alpha);
  column-rule-color: hwb(from rgb(20%, 40%, 60%, 80%) h calc(alpha * 100) w / calc(b / 100));
}

.wp-block-cover.has-relative-lab-overlay {
  color: lab(from lab(25% 20 50) l b a);
  background-color: oklab(from oklab(25% 20 50 / 40%) 35% a b / alpha);
  outline-color: lab(from lab(25% 20 50) l a none / alpha);
}

.wp-block-cover.has-relative-srgb-lab-overlay {
  color: lab(from indianred calc(l * .8) a b);
  background-color: lch(from orchid l 30 h);
  outline-color: lch(from peru calc(l * .8) c h);
  text-decoration-color: lch(from currentColor l c sin(h));
}

.wp-block-cover.has-relative-polar-overlay {
  color: lch(from lch(70% 45 30) alpha c h / l);
  background-color: oklch(from oklch(70% 45 30 / 40%) alpha c h / alpha);
  outline-color: lch(from lch(70% 45 30 / 40%) 50% 120 -400deg / -500);
}

.wp-block-cover.has-calculated-overlay {
  --wp-cover-overlay: rgb(50% 50% 50% / calc(100% / 2));
  --wp-cover-halo: hsl(calc(360deg / 2) 50% 50%);
  --wp-cover-gamut: color(display-p3 0.43313 0.50108 calc(0.1 + 0.2));
  color: var(--wp-cover-overlay);
}

.wp-block-cover.has-custom-token-colors {
  --wp-cover-token: rgb(255, 255, 0);
  --wp-cover-outline: hwb(194 0% 0% / 50%);
  --wp-cover-lab: lab(29.2345% 39.3825% 20.0664%);
  --wp-cover-gamut: color(display-p3 100% 50% 0 / 20%);
  color: var(--wp-cover-token);
}

.wp-block-cover.has-wide-gamut-overlay {
  background: linear-gradient(lch(29.2345% 44.2% 27deg), color(display-p3 100% 50% 0 / 20%));
  outline-color: oklab(.40101 0.1147 0.0453);
}

.wp-block-cover.has-relative-color-function-overlay {
  color: color(from color(srgb 0.7 0.5 0.3) srgb r g b / alpha);
  background-color: color(from color(a98-rgb 0.7 0.5 0.3 / 40%) a98-rgb 20% g b / 20%);
  outline-color: color(from color(rec2020 0.7 0.5 0.3 / 40%) rec2020 b alpha r / g);
}

.wp-block-cover.has-mixed-overlay {
  color: color-mix(in srgb, white, blue);
  background-color: color-mix(in srgb, rgb(100% 0% 0% / 0.7) 25%, rgb(0% 100% 0% / 0.2));
}

.wp-block-cover.has-missing-channel-mix {
  color: color-mix(in srgb, rgb(128 128 none), rgb(none none 128));
  background-color: color-mix(in xyz, transparent, green 65%);
  border-color: color-mix(in hsl, color(display-p3 0 1 0) 100%, rgb(0, 0, 0) 0%);
  outline-color: color-mix(in lch, color(display-p3 0 1 none), color(display-p3 0 0 1));
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
  outline-color: color-mix(in hsl decreasing hue, hsl(330deg 50% 50%), hsl(50deg 50% 50%));
  text-decoration-color: color-mix(in hsl, hsl(none 20% 40%), hsl(30deg none 80%));
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
  border-color: color-mix(in lch decreasing hue, lch(100% 0 320deg), lch(100% 0 20deg));
  text-decoration-color: color-mix(in oklch, oklch(10% 20 30deg / none), oklch(50% 60 70deg / 0.5));
}
CSS;

$expected = '.wp-block-cover.has-hwb-overlay{color:#00c4ff80;background:linear-gradient(#80e1ff,#006280);border-color:buttonborder}.wp-block-cover.has-rgb-overlay{color:#ff0;background-color:#ff0c;border-color:gray;outline-color:#7bffff;text-decoration-color:#7bffff80}.wp-block-button .wp-element-button{color:#fff;outline-color:#000}.wp-block-cover.has-relative-overlay{color:#639;background-color:#360c;border-color:#66660a}.wp-block-cover.has-relative-hsl-hwb-overlay{color:#66527a;background-color:#3380cccc;outline-color:#99666680;border-color:#33ff3380;text-decoration-color:#19334dcc;caret-color:#933c;column-rule-color:#ccc6}.wp-block-cover.has-relative-lab-overlay{color:lab(25% 50 20);background-color:oklab(35% 20 50/.4);outline-color:lab(25% 20 none)}.wp-block-cover.has-relative-srgb-lab-overlay{color:lab(43.1402% 45.7516 23.1557);background-color:lch(62.7526% 30 326.969);outline-color:lch(49.8022% 54.0117 63.6804);text-decoration-color:lch(from currentColor l c sin(h))}.wp-block-cover.has-relative-polar-overlay{color:lch(1 45 30/1);background-color:oklch(.4 45 30/.4);outline-color:lch(50% 120 -400/0)}.wp-block-cover.has-calculated-overlay{--wp-cover-overlay:#80808080;--wp-cover-halo:#40bfbf;--wp-cover-gamut:color(display-p3 .43313 .50108 .3);color:var(--wp-cover-overlay)}.wp-block-cover.has-custom-token-colors{--wp-cover-token:#ff0;--wp-cover-outline:#00c4ff80;--wp-cover-lab:lab(29.2345% 49.2281 25.083);--wp-cover-gamut:color(display-p3 1 .5 0/.2);color:var(--wp-cover-token)}.wp-block-cover.has-wide-gamut-overlay{background:linear-gradient(lch(29.2345% 66.3 27),color(display-p3 1 .5 0/.2));outline-color:oklab(40.101% .1147 .0453)}.wp-block-cover.has-relative-color-function-overlay{color:color(srgb .7 .5 .3);background-color:color(a98-rgb .2 .5 .3/.2);outline-color:color(rec2020 .3 .4 .7/.5)}.wp-block-cover.has-mixed-overlay{color:#8080ff;background-color:#89760053}.wp-block-cover.has-missing-channel-mix{color:gray;background-color:color(xyz .0771883 .154377 .0257295/.65);border-color:#00f942;outline-color:lch(58.8143% 141.732 218.684)}.wp-block-cover.has-perceptual-mixed-overlay{color:lab(40% 50 60);background-color:oklab(36.6667% 46.6667 56.6667/.6);outline-color:lab(30% 40 70)}.wp-block-cover.has-hsl-mixed-overlay{color:#545c3d;background-color:#5f694199;border-color:#4055bf;outline-color:#40aabf;text-decoration-color:#ad9985}.wp-block-cover.has-hwb-mixed-overlay{color:#93b334;background-color:#a6994080;border-color:#60bf27}.wp-block-cover.has-xyz-mixed-overlay{color:color(xyz .3 .4 .5);background-color:color(xyz .346154 .446154 .546154/.65);outline-color:color(srgb-linear .4 .5 .6/.5)}.wp-block-cover.has-polar-mixed-overlay{color:lch(30% 40 50);background-color:oklch(100% 0 230);outline-color:lch(50% 20 50);border-color:lch(100% 0 170);text-decoration-color:oklch(30% 40 50/.5)}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
