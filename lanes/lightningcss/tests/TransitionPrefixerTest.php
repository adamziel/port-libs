<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

$rtlLangs = ':lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)';
$variants = static fn (string $selector): array => [
    'ltr-webkit' => $selector . ':not(:-webkit-any(' . $rtlLangs . '))',
    'ltr-modern' => $selector . ':not(:is(' . $rtlLangs . '))',
    'rtl-webkit' => $selector . ':-webkit-any(' . $rtlLangs . ')',
    'rtl-modern' => $selector . ':is(' . $rtlLangs . ')',
];

return [
    'transition prefixer maps upstream inline transition-property direction selectors' => static function (TestRunner $t) use ($variants): void {
        $selector = $variants('.foo');
        $expected = $selector['ltr-webkit'] . '{transition-property:margin-left,padding-left}'
            . $selector['ltr-modern'] . '{transition-property:margin-left,padding-left}'
            . $selector['rtl-webkit'] . '{transition-property:margin-right,padding-right}'
            . $selector['rtl-modern'] . '{transition-property:margin-right,padding-right}';

        $t->same(
            $expected,
            (new TransitionPrefixer())->prefixLegacySafari('.foo { transition-property: margin-inline-start, padding-inline-start; }')
        );
    },
    'transition prefixer maps upstream inline transition shorthand direction selectors' => static function (TestRunner $t) use ($variants): void {
        $selector = $variants('.foo');
        $expected = $selector['ltr-webkit'] . '{transition:margin-left 2s,padding-left .2s}'
            . $selector['ltr-modern'] . '{transition:margin-left 2s,padding-left .2s}'
            . $selector['rtl-webkit'] . '{transition:margin-right 2s,padding-right .2s}'
            . $selector['rtl-modern'] . '{transition:margin-right 2s,padding-right .2s}';

        $t->same(
            $expected,
            (new TransitionPrefixer())->prefixLegacySafari('.foo { transition: margin-inline-start 2s, padding-inline-start 200ms; }')
        );
    },
    'transition prefixer maps upstream transform transition prefixing' => static function (TestRunner $t): void {
        $t->same(
            '.foo{-webkit-transition:-webkit-transform,transform;transition:-webkit-transform,transform}',
            (new TransitionPrefixer())->prefixLegacySafari('.foo { transition: transform; }')
        );
        $t->same(
            '.foo{-webkit-transition-property:-webkit-transform,transform;transition-property:-webkit-transform,transform}',
            (new TransitionPrefixer())->prefixLegacySafari('.foo { transition-property: transform; }')
        );
    },
    'transition prefixer maps upstream mask transition prefixing' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{transition:-webkit-mask .2s,mask .2s}',
            $prefixer->prefixLegacySafari('.foo { transition: mask 200ms; }')
        );
        $t->same(
            '.foo{transition:-webkit-mask-box-image .2s,mask-border .2s}',
            $prefixer->prefixLegacySafari('.foo { transition: mask-border 200ms; }')
        );
        $t->same(
            '.foo{transition-property:-webkit-mask,mask}',
            $prefixer->prefixLegacySafari('.foo { transition-property: mask; }')
        );
        $t->same(
            '.foo{transition-property:-webkit-mask-box-image,mask-border}',
            $prefixer->prefixLegacySafari('.foo { transition-property: mask-border; }')
        );
        $t->same(
            '.foo{transition-property:-webkit-mask-composite,mask-composite,-webkit-mask-source-type,mask-mode}',
            $prefixer->prefixLegacySafari('.foo { transition-property: mask-composite, mask-mode; }')
        );
    },
    'transition prefixer maps upstream mask-border shorthand and longhand prefixing' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-mask-box-image:url(border-mask.png) 25/35px/12px space;mask-border:url(border-mask.png) 25/35px/12px space luminance}',
            $prefixer->prefixLegacySafari(".foo { mask-border: url('border-mask.png') 25 / 35px / 12px space luminance; }")
        );
        $t->same(
            '.foo{-webkit-mask-box-image:linear-gradient(#ff0f0e,#7773ff) 25/35px/12px space;mask-border:linear-gradient(#ff0f0e,#7773ff) 25/35px/12px space luminance;-webkit-mask-box-image:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 25/35px/12px space;mask-border:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 25/35px/12px space luminance}',
            $prefixer->prefixLegacySafari('.foo { mask-border: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) 25 / 35px / 12px space luminance; }')
        );
        $t->same(
            '.foo{-webkit-mask-box-image:linear-gradient(#ff0f0e,#7773ff) var(--foo);mask-border:linear-gradient(#ff0f0e,#7773ff) var(--foo)}@supports (color:lab(0% 0 0)){.foo{-webkit-mask-box-image:linear-gradient(lab(56.208% 94.4644 98.8928),lab(51% 70.4544 -115.586)) var(--foo);mask-border:linear-gradient(lab(56.208% 94.4644 98.8928),lab(51% 70.4544 -115.586)) var(--foo)}}',
            $prefixer->prefixLegacySafari('.foo { mask-border: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) var(--foo); }')
        );
        $t->same(
            '.foo{-webkit-mask-box-image-source:linear-gradient(#ff0f0e,#7773ff);mask-border-source:linear-gradient(#ff0f0e,#7773ff);-webkit-mask-box-image-source:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364));mask-border-source:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364))}',
            $prefixer->prefixLegacySafari('.foo { mask-border-source: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)); }')
        );
        $t->same(
            '.foo{-webkit-mask-box-image:url(foo.png) 10 40/10px round;mask-border:url(foo.png) 10 40/10px round luminance}',
            $prefixer->prefixLegacySafari('.foo { mask-border-source: url(foo.png); mask-border-slice: 10 40 10 40; mask-border-width: 10px; mask-border-outset: 0; mask-border-repeat: round round; mask-border-mode: luminance; }')
        );
        $t->same(
            '.foo{-webkit-mask-box-image:linear-gradient(#ff0f0e,#7773ff) 10 40/10px round;mask-border:linear-gradient(#ff0f0e,#7773ff) 10 40/10px round luminance;-webkit-mask-box-image:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 10 40/10px round;mask-border:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 10 40/10px round luminance}',
            $prefixer->prefixLegacySafari('.foo { mask-border-source: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)); mask-border-slice: 10 40 10 40; mask-border-width: 10px; mask-border-outset: 0; mask-border-repeat: round round; mask-border-mode: luminance; }')
        );
        $t->same(
            '.foo{-webkit-mask-box-image:url(foo.png) 10 40/10px round}',
            $prefixer->prefixLegacySafari('.foo { -webkit-mask-box-image-source: url(foo.png); -webkit-mask-box-image-slice: 10 40 10 40; -webkit-mask-box-image-width: 10px; -webkit-mask-box-image-outset: 0; -webkit-mask-box-image-repeat: round round; }')
        );
        $t->same(
            '.foo{-webkit-mask-box-image-slice:10 40;mask-border-slice:10 40}',
            $prefixer->prefixLegacySafari('.foo { mask-border-slice: 10 40 10 40; }')
        );
        $t->same(
            '.foo{-webkit-mask-box-image-slice:var(--foo);mask-border-slice:var(--foo)}',
            $prefixer->prefixLegacySafari('.foo { mask-border-slice: var(--foo); }')
        );
        $t->same(
            '.foo{-webkit-mask-composite:source-out;mask-composite:subtract;-webkit-mask-source-type:luminance;mask-mode:luminance}',
            $prefixer->prefixLegacySafari('.foo { mask-composite: subtract; mask-mode: luminance; }')
        );
    },
    'transition prefixer maps upstream mask image and shorthand prefixing' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-mask-image:linear-gradient(red,green);mask-image:linear-gradient(red,green)}',
            $prefixer->prefixLegacySafari('.foo { mask-image: linear-gradient(red, green) }')
        );
        $t->same(
            '.foo{-webkit-mask-image:linear-gradient(#ff0f0e,#7773ff);mask-image:linear-gradient(#ff0f0e,#7773ff);-webkit-mask-image:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364));mask-image:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364))}',
            $prefixer->prefixLegacySafari('.foo { mask-image: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) }')
        );
        $t->same(
            '.foo{-webkit-mask-image:url("masks.svg#star");mask-image:url("masks.svg#star")}',
            $prefixer->prefixLegacySafari('.foo { mask-image: url(masks.svg#star) }')
        );
        $t->same(
            '.foo{-webkit-mask-image:url("x.svg");mask-image:url("x.svg")}',
            $prefixer->prefixLegacySafari('.foo { -webkit-mask-image: url(x.svg); mask-image: url(x.svg); }')
        );
        $t->same(
            '.foo{-webkit-mask:url("masks.svg#star");-webkit-mask-source-type:luminance;mask:url("masks.svg#star") luminance}',
            $prefixer->prefixLegacySafari('.foo { mask: url(masks.svg#star) luminance }')
        );
        $t->same(
            '.foo{-webkit-mask:linear-gradient(#ff0f0e,#7773ff) 40px 20px;mask:linear-gradient(#ff0f0e,#7773ff) 40px 20px;-webkit-mask:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 40px 20px;mask:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 40px 20px}',
            $prefixer->prefixLegacySafari('.foo { mask: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) 40px 20px }')
        );
        $t->same(
            '.foo{-webkit-mask:linear-gradient(#ff0f0e,#7773ff) 40px var(--foo);mask:linear-gradient(#ff0f0e,#7773ff) 40px var(--foo)}@supports (color:lab(0% 0 0)){.foo{-webkit-mask:linear-gradient(lab(56.208% 94.4644 98.8928),lab(51% 70.4544 -115.586)) 40px var(--foo);mask:linear-gradient(lab(56.208% 94.4644 98.8928),lab(51% 70.4544 -115.586)) 40px var(--foo)}}',
            $prefixer->prefixLegacySafari('.foo { mask: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) 40px var(--foo) }')
        );
    },
    'transition prefixer composes upstream mask longhands to shorthand prefixes' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.foo {
  mask-image: url(masks.svg#star);
  mask-position: 25% 75%;
  mask-size: cover;
  mask-repeat: no-repeat;
  mask-clip: padding-box;
  mask-origin: content-box;
  mask-composite: subtract;
  mask-mode: luminance;
}
CSS;

        $t->same(
            '.foo{-webkit-mask:url("masks.svg#star") 25% 75%/cover no-repeat content-box padding-box;-webkit-mask-composite:source-out;-webkit-mask-source-type:luminance;mask:url("masks.svg#star") 25% 75%/cover no-repeat content-box padding-box subtract luminance}',
            (new TransitionPrefixer())->prefixLegacySafari($css)
        );

        $css = <<<'CSS'
.foo {
  mask-image: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364));
  mask-position: 25% 75%;
  mask-size: cover;
  mask-repeat: no-repeat;
  mask-clip: padding-box;
  mask-origin: content-box;
  mask-composite: subtract;
  mask-mode: luminance;
}
CSS;

        $t->same(
            '.foo{-webkit-mask:linear-gradient(#ff0f0e,#7773ff) 25% 75%/cover no-repeat content-box padding-box;-webkit-mask-composite:source-out;-webkit-mask-source-type:luminance;mask:linear-gradient(#ff0f0e,#7773ff) 25% 75%/cover no-repeat content-box padding-box subtract luminance;-webkit-mask:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 25% 75%/cover no-repeat content-box padding-box;-webkit-mask-composite:source-out;-webkit-mask-source-type:luminance;mask:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 25% 75%/cover no-repeat content-box padding-box subtract luminance}',
            (new TransitionPrefixer())->prefixLegacySafari($css)
        );
    },
    'wordpress navigation transitions get logical and transform fallback prefixes without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-navigation .wp-block-navigation-item {
  transition: margin-inline-start 200ms, transform 200ms;
}
CSS;

        $prefixed = (new TransitionPrefixer())->prefixLegacySafari($css);

        $t->contains('transition:margin-left .2s,-webkit-transform .2s,transform .2s', $prefixed);
        $t->contains('transition:margin-right .2s,-webkit-transform .2s,transform .2s', $prefixed);
        $t->contains('-webkit-transition:margin-left .2s,-webkit-transform .2s,transform .2s', $prefixed);
        $t->contains('-webkit-transition:margin-right .2s,-webkit-transform .2s,transform .2s', $prefixed);
    },
    'wordpress decorative mask transitions get legacy WebKit names without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-framed {
  transition: mask-border 200ms, mask 400ms;
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-framed{transition:-webkit-mask-box-image .2s,mask-border .2s,-webkit-mask .4s,mask .4s}',
            (new TransitionPrefixer())->prefixLegacySafari($css)
        );
    },
    'wordpress cover frame mask-border longhands compose and prefix without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-frame {
  mask-border-source: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364));
  mask-border-slice: 12 24 12 24;
  mask-border-width: 8px;
  mask-border-repeat: round round;
  mask-border-mode: luminance;
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-frame{-webkit-mask-box-image:linear-gradient(#ff0f0e,#7773ff) 12 24/8px round;mask-border:linear-gradient(#ff0f0e,#7773ff) 12 24/8px round luminance;-webkit-mask-box-image:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 12 24/8px round;mask-border:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 12 24/8px round luminance}',
            (new TransitionPrefixer())->prefixLegacySafari($css)
        );
    },
    'wordpress media cover mask image longhands compose and prefix without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-soft-fade {
  mask-image: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364));
  mask-position: 50% 50%;
  mask-size: cover;
  mask-repeat: no-repeat;
  mask-origin: content-box;
  mask-clip: padding-box;
  mask-mode: luminance;
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-soft-fade{-webkit-mask:linear-gradient(#ff0f0e,#7773ff) 50% 50%/cover no-repeat content-box padding-box;-webkit-mask-source-type:luminance;mask:linear-gradient(#ff0f0e,#7773ff) 50% 50%/cover no-repeat content-box padding-box luminance;-webkit-mask:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 50% 50%/cover no-repeat content-box padding-box;-webkit-mask-source-type:luminance;mask:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) 50% 50%/cover no-repeat content-box padding-box luminance}',
            (new TransitionPrefixer())->prefixLegacySafari($css)
        );
    },
    'wordpress frame mask-border with custom slice gets lab supports fallback without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-frame {
  mask-border: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) var(--wp--custom--frame-slice);
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-frame{-webkit-mask-box-image:linear-gradient(#ff0f0e,#7773ff) var(--wp--custom--frame-slice);mask-border:linear-gradient(#ff0f0e,#7773ff) var(--wp--custom--frame-slice)}@supports (color:lab(0% 0 0)){.wp-block-cover.is-style-frame{-webkit-mask-box-image:linear-gradient(lab(56.208% 94.4644 98.8928),lab(51% 70.4544 -115.586)) var(--wp--custom--frame-slice);mask-border:linear-gradient(lab(56.208% 94.4644 98.8928),lab(51% 70.4544 -115.586)) var(--wp--custom--frame-slice)}}',
            (new TransitionPrefixer())->prefixLegacySafari($css)
        );
    },
];
