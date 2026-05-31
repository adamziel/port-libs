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
    'transition prefixer maps upstream clamp lowering for legacy safari targets' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{border-width:max(1em,min(2px,4vh))}',
            $prefixer->prefixForTargets('.foo { border-width: clamp(1em, 2px, 4vh) }', ['safari' => 12])
        );
        $t->same(
            '.foo{border-width:clamp(1em,2px,4vh)}',
            $prefixer->prefixForTargets('.foo { border-width: clamp(1em, 2px, 4vh) }', ['safari' => 14])
        );
    },
    'transition prefixer maps upstream color-scheme light-dark fallback flags' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{--lightningcss-light:;--lightningcss-dark:initial;color-scheme:dark}',
            $prefixer->prefixForTargets('.foo { color-scheme: dark; }', ['chrome' => 90])
        );
        $t->same(
            '.foo{--lightningcss-light:initial;--lightningcss-dark:;color-scheme:light}',
            $prefixer->prefixForTargets('.foo { color-scheme: light; }', ['chrome' => 90])
        );
        $t->same(
            '.foo{--lightningcss-light:initial;--lightningcss-dark:;color-scheme:light dark}@media (prefers-color-scheme:dark){.foo{--lightningcss-light:;--lightningcss-dark:initial}}',
            $prefixer->prefixForTargets('.foo { color-scheme: light dark; }', ['chrome' => 90])
        );
        $t->same(
            '.foo{color-scheme:light dark}',
            $prefixer->prefixForTargets('.foo { color-scheme: light dark; }', ['firefox' => 120])
        );
    },
    'transition prefixer maps upstream light-dark color fallback values' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{color:var(--lightningcss-light,#7e250f) var(--lightningcss-dark,#c65d07);color:var(--lightningcss-light,lab(29.2661% 38.2437 35.3889)) var(--lightningcss-dark,lab(52.2319% 40.1449 59.9171))}',
            $prefixer->prefixForTargets(
                '.foo { color: light-dark(oklch(40% 0.1268735435 34.568626), oklab(59.686% 0.1009 0.1192)); }',
                ['chrome' => 90]
            )
        );
        $t->same(
            '.foo{color:var(--lightningcss-light,var(--light)) var(--lightningcss-dark,var(--dark))}',
            $prefixer->prefixForTargets('.foo { color: light-dark(var(--light), var(--dark)); }', ['chrome' => 90])
        );
        $t->same(
            '.foo{color:light-dark(#ff0,red)}',
            $prefixer->prefixForTargets('.foo { color: light-dark(yellow, red); }', ['firefox' => 120])
        );
    },
    'transition prefixer maps upstream nested light-dark relative color fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{color:var(--lightningcss-light,#ffff001a) var(--lightningcss-dark,#ff00001a)}',
            $prefixer->prefixForTargets('.foo { color: rgb(from light-dark(yellow, red) r g b / 10%); }', ['chrome' => 90])
        );
        $t->same(
            '.foo{color:var(--lightningcss-light,rgb(255 255 0 / var(--alpha))) var(--lightningcss-dark,rgb(255 0 0 / var(--alpha)))}',
            $prefixer->prefixForTargets('.foo { color: rgb(from light-dark(yellow, red) r g b / var(--alpha)); }', ['chrome' => 90])
        );
        $t->same(
            '.foo{color:var(--lightningcss-light,#ffff001a) var(--lightningcss-dark,#ff00001a);color:var(--lightningcss-light,color(srgb 1 1 0 / .1)) var(--lightningcss-dark,color(srgb 1 0 0 / .1))}',
            $prefixer->prefixForTargets('.foo { color: color(from light-dark(yellow, red) srgb r g b / 10%); }', ['chrome' => 90])
        );
    },
    'transition prefixer maps upstream light-dark color-mix fallback and firefox serialization' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{color:var(--lightningcss-light,#ff8000) var(--lightningcss-dark,#ff6066)}',
            $prefixer->prefixForTargets('.foo { color: color-mix(in srgb, light-dark(yellow, red), light-dark(red, pink)); }', ['chrome' => 90])
        );
        $t->same(
            '.foo{color:light-dark(oklch(40% .126874 34.5686),oklab(59.686% .1009 .1192))}',
            $prefixer->prefixForTargets(
                '.foo { color: light-dark(oklch(40% 0.1268735435 34.568626), oklab(59.686% 0.1009 0.1192)); }',
                ['firefox' => 120]
            )
        );
    },
    'transition prefixer honors upstream light-dark feature exclusion target' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { color-scheme: light; } .bar { color: light-dark(red, green); }';

        $t->same(
            '.foo{color-scheme:light}.bar{color:light-dark(red,green)}',
            $prefixer->prefixForTargets($css, [
                'browsers' => ['safari' => 13],
                'exclude' => ['LightDark'],
            ])
        );
        $t->same(
            '.foo{color-scheme:light}.bar{color:light-dark(red,green)}',
            $prefixer->prefixForTargets($css, [
                'browsers' => ['safari' => 13],
                'exclude' => ['light-dark' => true],
            ])
        );
    },
    'transition prefixer maps upstream print-color-adjust target boundary' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-print-color-adjust:exact;print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['chrome' => 135])
        );
        $t->same(
            '.foo{print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['chrome' => 137])
        );
    },
    'transition prefixer maps upstream ui user-select and appearance target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}',
            $prefixer->prefixForTargets('.foo { user-select: none; }', [
                'safari' => 8,
                'opera' => 5,
                'firefox' => 10,
                'ie' => 10,
            ])
        );
        $t->same(
            '.foo{-webkit-user-select:none;user-select:none}',
            $prefixer->prefixForTargets('.foo { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }', [
                'safari' => 8,
                'opera' => 80,
                'firefox' => 80,
                'edge' => 80,
            ])
        );
        $t->same(
            '.foo{user-select:none}',
            $prefixer->prefixForTargets('.foo { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }', [
                'opera' => 80,
                'firefox' => 80,
                'edge' => 80,
            ])
        );
        $t->same(
            '.foo{-webkit-appearance:none;-moz-appearance:none;-ms-appearance:none;appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', [
                'safari' => 8,
                'chrome' => 80,
                'firefox' => 10,
                'ie' => 11,
            ])
        );
        $t->same(
            '.foo{-webkit-appearance:none;appearance:none}',
            $prefixer->prefixForTargets('.foo { -webkit-appearance: none; -moz-appearance: none; -ms-appearance: none; appearance: none; }', [
                'safari' => 15,
                'chrome' => 85,
                'firefox' => 80,
                'edge' => 85,
            ])
        );
        $t->same(
            '.foo{appearance:none}',
            $prefixer->prefixForTargets('.foo { -webkit-appearance: none; -moz-appearance: none; -ms-appearance: none; appearance: none; }', [
                'chrome' => 85,
                'firefox' => 80,
                'edge' => 85,
            ])
        );
    },
    'transition prefixer maps upstream ui browser boundary targets' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-user-select:none;user-select:none}',
            $prefixer->prefixForTargets('.foo { user-select: none; }', ['chrome' => 53])
        );
        $t->same(
            '.foo{user-select:none}',
            $prefixer->prefixForTargets('.foo { user-select: none; }', ['chrome' => 54])
        );
        $t->same(
            '.foo{-moz-user-select:none;user-select:none}',
            $prefixer->prefixForTargets('.foo { user-select: none; }', ['firefox' => 68])
        );
        $t->same(
            '.foo{user-select:none}',
            $prefixer->prefixForTargets('.foo { user-select: none; }', ['firefox' => 69])
        );
        $t->same(
            '.foo{-ms-user-select:none;user-select:none}',
            $prefixer->prefixForTargets('.foo { user-select: none; }', ['edge' => 18])
        );
        $t->same(
            '.foo{user-select:none}',
            $prefixer->prefixForTargets('.foo { user-select: none; }', ['edge' => 19])
        );
        $t->same(
            '.foo{-webkit-appearance:none;appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['chrome' => 83])
        );
        $t->same(
            '.foo{appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['chrome' => 84])
        );
        $t->same(
            '.foo{-moz-appearance:none;appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['firefox' => 79])
        );
        $t->same(
            '.foo{appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['firefox' => 80])
        );
        $t->same(
            '.foo{-webkit-appearance:none;appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['safari' => 15])
        );
        $t->same(
            '.foo{appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['safari' => 16])
        );
    },
    'transition prefixer maps upstream legacy text and sticky prefix helpers' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-text-size-adjust:none;-moz-text-size-adjust:none;-ms-text-size-adjust:none;text-size-adjust:none}',
            $prefixer->prefixForTargets('.foo { text-size-adjust: none; }', [
                'ios_saf' => 16,
                'edge' => 15,
                'firefox' => 20,
            ])
        );
        $t->same(
            '.foo{text-size-adjust:none}',
            $prefixer->prefixForTargets('.foo { -webkit-text-size-adjust: none; -moz-text-size-adjust: none; -ms-text-size-adjust: none; text-size-adjust: none; }', ['chrome' => 110])
        );
        $t->same(
            '.foo{-webkit-hyphens:manual;-moz-hyphens:manual;-ms-hyphens:manual;hyphens:manual}',
            $prefixer->prefixForTargets('.foo { hyphens: manual; }', [
                'safari' => 14,
                'firefox' => 40,
                'ie' => 10,
            ])
        );
        $t->same(
            '.foo{-webkit-hyphens:manual;hyphens:manual}',
            $prefixer->prefixForTargets('.foo { -webkit-hyphens: manual; -moz-hyphens: manual; -ms-hyphens: manual; hyphens: manual; }', [
                'safari' => 14,
                'chrome' => 88,
                'firefox' => 88,
                'edge' => 79,
            ])
        );
        $t->same(
            '.foo{hyphens:manual}',
            $prefixer->prefixForTargets('.foo { -webkit-hyphens: manual; -moz-hyphens: manual; -ms-hyphens: manual; hyphens: manual; }', [
                'chrome' => 88,
                'firefox' => 88,
                'edge' => 79,
            ])
        );
        $t->same(
            '.foo{-moz-tab-size:4;-o-tab-size:4;tab-size:4}',
            $prefixer->prefixForTargets('.foo { tab-size: 4; }', [
                'firefox' => 50,
                'opera' => 12,
            ])
        );
        $t->same(
            '.foo{tab-size:4}',
            $prefixer->prefixForTargets('.foo { -moz-tab-size: 4; -o-tab-size: 4; tab-size: 4; }', [
                'firefox' => 94,
                'opera' => 30,
            ])
        );
        $t->same(
            '.foo{-moz-text-align-last:left;text-align-last:left}',
            $prefixer->prefixForTargets('.foo { text-align-last: left; }', ['firefox' => 40])
        );
        $t->same(
            '.foo{text-align-last:left}',
            $prefixer->prefixForTargets('.foo { -moz-text-align-last: left; text-align-last: left; }', ['firefox' => 88])
        );
        $t->same(
            '.foo{-o-text-overflow:ellipsis;text-overflow:ellipsis}',
            $prefixer->prefixForTargets('.foo { text-overflow: ellipsis; }', [
                'safari' => 4,
                'opera' => 10,
            ])
        );
        $t->same(
            '.foo{text-overflow:ellipsis}',
            $prefixer->prefixForTargets('.foo { -o-text-overflow: ellipsis; text-overflow: ellipsis; }', [
                'safari' => 4,
                'opera' => 14,
            ])
        );
        $t->same(
            '.foo{-webkit-box-decoration-break:clone;box-decoration-break:clone}',
            $prefixer->prefixForTargets('.foo { box-decoration-break: clone; }', ['safari' => 15])
        );
        $t->same(
            '.foo{box-decoration-break:clone}',
            $prefixer->prefixForTargets('.foo { box-decoration-break: clone; }', ['firefox' => 95])
        );
        $t->same(
            '.foo{position:-webkit-sticky;position:sticky}',
            $prefixer->prefixForTargets('.foo { position: sticky; }', ['safari' => 8])
        );
        $t->same(
            '.foo{position:sticky}',
            $prefixer->prefixForTargets('.foo { position: -webkit-sticky; position: sticky; }', ['safari' => 13])
        );
    },
    'transition prefixer maps upstream legacy text browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{text-size-adjust:none}',
            $prefixer->prefixForTargets('.foo { text-size-adjust: none; }', ['ios_saf' => '4.3'])
        );
        $t->same(
            '.foo{-webkit-text-size-adjust:none;text-size-adjust:none}',
            $prefixer->prefixForTargets('.foo { text-size-adjust: none; }', ['ios_saf' => 5])
        );
        $t->same(
            '.foo{-webkit-hyphens:manual;hyphens:manual}',
            $prefixer->prefixForTargets('.foo { hyphens: manual; }', ['safari' => '16.5'])
        );
        $t->same(
            '.foo{hyphens:manual}',
            $prefixer->prefixForTargets('.foo { hyphens: manual; }', ['safari' => 17])
        );
        $t->same(
            '.foo{-moz-tab-size:4;tab-size:4}',
            $prefixer->prefixForTargets('.foo { tab-size: 4; }', ['firefox' => 90])
        );
        $t->same(
            '.foo{tab-size:4}',
            $prefixer->prefixForTargets('.foo { tab-size: 4; }', ['firefox' => 91])
        );
        $t->same(
            '.foo{-moz-text-align-last:left;text-align-last:left}',
            $prefixer->prefixForTargets('.foo { text-align-last: left; }', ['firefox' => 48])
        );
        $t->same(
            '.foo{text-align-last:left}',
            $prefixer->prefixForTargets('.foo { text-align-last: left; }', ['firefox' => 49])
        );
        $t->same(
            '.foo{-o-text-overflow:ellipsis;text-overflow:ellipsis}',
            $prefixer->prefixForTargets('.foo { text-overflow: ellipsis; }', ['opera' => 12])
        );
        $t->same(
            '.foo{text-overflow:ellipsis}',
            $prefixer->prefixForTargets('.foo { text-overflow: ellipsis; }', ['opera' => 13])
        );
        $t->same(
            '.foo{-webkit-box-decoration-break:clone;box-decoration-break:clone}',
            $prefixer->prefixForTargets('.foo { box-decoration-break: clone; }', ['chrome' => 129])
        );
        $t->same(
            '.foo{box-decoration-break:clone}',
            $prefixer->prefixForTargets('.foo { box-decoration-break: clone; }', ['chrome' => 130])
        );
        $t->same(
            '.foo{position:-webkit-sticky;position:sticky}',
            $prefixer->prefixForTargets('.foo { position: sticky; }', ['safari' => '12.1'])
        );
        $t->same(
            '.foo{position:sticky}',
            $prefixer->prefixForTargets('.foo { position: sticky; }', ['safari' => 13])
        );
    },
    'transition prefixer maps upstream logical inset target fallbacks' => static function (TestRunner $t) use ($variants): void {
        $prefixer = new TransitionPrefixer();
        $selector = $variants('.foo');

        $t->same(
            $selector['ltr-webkit'] . '{left:2px}'
                . $selector['ltr-modern'] . '{left:2px}'
                . $selector['rtl-webkit'] . '{right:2px}'
                . $selector['rtl-modern'] . '{right:2px}',
            $prefixer->prefixForTargets('.foo { inset-inline-start: 2px; }', ['safari' => 8])
        );
        $t->same(
            $selector['ltr-webkit'] . '{left:2px;right:4px}'
                . $selector['ltr-modern'] . '{left:2px;right:4px}'
                . $selector['rtl-webkit'] . '{left:4px;right:2px}'
                . $selector['rtl-modern'] . '{left:4px;right:2px}',
            $prefixer->prefixForTargets('.foo { inset-inline-start: 2px; inset-inline-end: 4px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{left:2px;right:2px}',
            $prefixer->prefixForTargets('.foo { inset-inline: 2px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{top:2px}',
            $prefixer->prefixForTargets('.foo { inset-block-start: 2px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{bottom:2px}',
            $prefixer->prefixForTargets('.foo { inset-block-end: 2px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{top:1px;bottom:3px;left:2px;right:4px}',
            $prefixer->prefixForTargets('.foo { top: 1px; left: 2px; bottom: 3px; right: 4px; }', ['safari' => 8])
        );
    },
    'transition prefixer maps upstream logical inset browser boundaries' => static function (TestRunner $t) use ($variants): void {
        $prefixer = new TransitionPrefixer();
        $selector = $variants('.foo');
        $inlineStartFallback = $selector['ltr-webkit'] . '{left:2px}'
            . $selector['ltr-modern'] . '{left:2px}'
            . $selector['rtl-webkit'] . '{right:2px}'
            . $selector['rtl-modern'] . '{right:2px}';

        $t->same($inlineStartFallback, $prefixer->prefixForTargets('.foo { inset-inline-start: 2px; }', ['safari' => '14.0']));
        $t->same('.foo{inset-inline-start:2px}', $prefixer->prefixForTargets('.foo { inset-inline-start: 2px; }', ['safari' => '14.1']));
        $t->same($inlineStartFallback, $prefixer->prefixForTargets('.foo { inset-inline-start: 2px; }', ['ios_saf' => '14.4']));
        $t->same('.foo{inset-inline-start:2px}', $prefixer->prefixForTargets('.foo { inset-inline-start: 2px; }', ['ios_saf' => '14.5']));
        $t->same('.foo{top:2px}', $prefixer->prefixForTargets('.foo { inset-block-start: 2px; }', ['chrome' => 86]));
        $t->same('.foo{inset-block-start:2px}', $prefixer->prefixForTargets('.foo { inset-block-start: 2px; }', ['chrome' => 87]));
        $t->same('.foo{left:2px;right:2px}', $prefixer->prefixForTargets('.foo { inset-inline: 2px; }', ['firefox' => 62]));
        $t->same('.foo{inset-inline:2px}', $prefixer->prefixForTargets('.foo { inset-inline: 2px; }', ['firefox' => 63]));
        $t->same('.foo{top:1px;bottom:3px;left:2px;right:4px}', $prefixer->prefixForTargets('.foo { inset: 1px 4px 3px 2px; }', ['ie' => 11]));
        $t->same('.foo{top:2px}', $prefixer->prefixForTargets('.foo { inset-block-start: 2px; }', [
            'browsers' => ['chrome' => 120],
            'include' => ['LogicalProperties'],
        ]));
        $t->same('.foo{inset-block-start:2px}', $prefixer->prefixForTargets('.foo { inset-block-start: 2px; }', [
            'browsers' => ['safari' => '14.0'],
            'exclude' => ['logical-properties'],
        ]));
    },
    'transition prefixer maps upstream display flex target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $legacyTargets = [
            'safari' => 4,
            'firefox' => 14,
            'ie' => 10,
        ];

        $t->same(
            '.foo{display:-webkit-box;display:-moz-box;display:-webkit-flex;display:-ms-flexbox;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', $legacyTargets)
        );
        $t->same(
            '.foo{display:-webkit-inline-box;display:-moz-inline-box;display:-webkit-inline-flex;display:-ms-inline-flexbox;display:inline-flex}',
            $prefixer->prefixForTargets('.foo{ display: inline-flex }', $legacyTargets)
        );
        $t->same(
            '.foo{display:-webkit-box;display:-moz-box;display:-webkit-flex;display:-ms-flexbox;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: -webkit-box; display: flex; }', $legacyTargets)
        );
        $t->same(
            '.foo{display:flex}',
            $prefixer->prefixForTargets(
                '.foo { display: -webkit-box; display: -moz-box; display: -webkit-flex; display: -ms-flexbox; display: flex; }',
                ['safari' => 14]
            )
        );
        $t->same(
            '.foo{display:inline-flex}',
            $prefixer->prefixForTargets(
                '.foo { display: -webkit-inline-box; display: -moz-inline-box; display: -webkit-inline-flex; display: -ms-inline-flexbox; display: inline-flex; }',
                ['safari' => 14]
            )
        );
    },
    'transition prefixer maps upstream display flex browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{display:-webkit-box;display:-webkit-flex;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['chrome' => 20])
        );
        $t->same(
            '.foo{display:-webkit-flex;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['chrome' => 21])
        );
        $t->same(
            '.foo{display:-webkit-flex;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['chrome' => 28])
        );
        $t->same(
            '.foo{display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['chrome' => 29])
        );
        $t->same(
            '.foo{display:-webkit-box;display:-webkit-flex;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['safari' => 6])
        );
        $t->same(
            '.foo{display:-webkit-flex;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['safari' => 7])
        );
        $t->same(
            '.foo{display:-webkit-flex;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['safari' => 8])
        );
        $t->same(
            '.foo{display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['safari' => 9])
        );
        $t->same(
            '.foo{display:-moz-box;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['firefox' => 21])
        );
        $t->same(
            '.foo{display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['firefox' => 22])
        );
        $t->same(
            '.foo{display:-ms-flexbox;display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['ie' => 10])
        );
        $t->same(
            '.foo{display:flex}',
            $prefixer->prefixForTargets('.foo{ display: flex }', ['ie' => 11])
        );
    },
    'transition prefixer maps upstream flex longhand target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $targets = [
            'safari' => 4,
            'firefox' => 4,
            'ie' => 10,
        ];

        $t->same(
            '.foo{-webkit-box-orient:horizontal;-moz-box-orient:horizontal;-webkit-box-direction:normal;-moz-box-direction:normal;-webkit-flex-direction:row;-ms-flex-direction:row;flex-direction:row}',
            $prefixer->prefixForTargets('.foo { flex-direction: row; }', $targets)
        );
        $t->same(
            '.foo{-webkit-box-lines:multiple;-moz-box-lines:multiple;-webkit-flex-wrap:wrap;-ms-flex-wrap:wrap;flex-wrap:wrap}',
            $prefixer->prefixForTargets('.foo { flex-wrap: wrap; }', $targets)
        );
        $t->same(
            '.foo{-webkit-box-orient:horizontal;-moz-box-orient:horizontal;-webkit-box-direction:normal;-moz-box-direction:normal;-webkit-flex-flow:wrap;-ms-flex-flow:wrap;flex-flow:wrap}',
            $prefixer->prefixForTargets('.foo { flex-flow: row wrap; }', $targets)
        );
        $t->same(
            '.foo{-webkit-box-flex:1;-moz-box-flex:1;-ms-flex-positive:1;-webkit-flex-grow:1;flex-grow:1}',
            $prefixer->prefixForTargets('.foo { flex-grow: 1; }', $targets)
        );
        $t->same(
            '.foo{-ms-flex-negative:1;-webkit-flex-shrink:1;flex-shrink:1}',
            $prefixer->prefixForTargets('.foo { flex-shrink: 1; }', $targets)
        );
        $t->same(
            '.foo{-ms-flex-preferred-size:1px;-webkit-flex-basis:1px;flex-basis:1px}',
            $prefixer->prefixForTargets('.foo { flex-basis: 1px; }', $targets)
        );
        $t->same(
            '.foo{-webkit-box-flex:1;-moz-box-flex:1;-webkit-flex:1;-ms-flex:1;flex:1}',
            $prefixer->prefixForTargets('.foo { flex: 1; }', $targets)
        );
        $t->same(
            '.foo{-webkit-box-ordinal-group:1;-moz-box-ordinal-group:1;-ms-flex-order:1;-webkit-order:1;order:1}',
            $prefixer->prefixForTargets('.foo { order: 1; }', $targets)
        );
    },
    'transition prefixer maps upstream flex box alignment target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $targets = [
            'safari' => 4,
            'firefox' => 4,
            'ie' => 10,
        ];

        $t->same(
            '.foo{-ms-flex-line-pack:justify;-webkit-align-content:space-between;align-content:space-between}',
            $prefixer->prefixForTargets('.foo { align-content: space-between; }', $targets)
        );
        $t->same(
            '.foo{-webkit-box-pack:justify;-moz-box-pack:justify;-ms-flex-pack:justify;-webkit-justify-content:space-between;justify-content:space-between}',
            $prefixer->prefixForTargets('.foo { justify-content: space-between; }', $targets)
        );
        $t->same(
            '.foo{-ms-flex-item-align:end;-webkit-align-self:flex-end;align-self:flex-end}',
            $prefixer->prefixForTargets('.foo { align-self: flex-end; }', $targets)
        );
        $t->same(
            '.foo{-webkit-box-align:end;-moz-box-align:end;-ms-flex-align:end;-webkit-align-items:flex-end;align-items:flex-end}',
            $prefixer->prefixForTargets('.foo { align-items: flex-end; }', $targets)
        );
        $t->same(
            '.foo{-ms-flex-line-pack:justify;-webkit-box-pack:end;-moz-box-pack:end;-ms-flex-pack:end;-webkit-align-content:space-between;align-content:space-between;-webkit-justify-content:flex-end;justify-content:flex-end}',
            $prefixer->prefixForTargets('.foo { place-content: space-between flex-end; }', $targets)
        );
        $t->same(
            '.foo{-ms-flex-item-align:center;-webkit-align-self:center;align-self:center;justify-self:flex-end}',
            $prefixer->prefixForTargets('.foo { place-self: center flex-end; }', $targets)
        );
        $t->same(
            '.foo{-webkit-box-align:end;-moz-box-align:end;-ms-flex-align:end;-webkit-align-items:flex-end;align-items:flex-end;justify-items:center}',
            $prefixer->prefixForTargets('.foo { place-items: flex-end center; }', $targets)
        );
    },
    'transition prefixer maps upstream flex longhand stale prefix removal' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $modernTargets = ['safari' => 11];

        $t->same(
            '.foo{flex-direction:row}',
            $prefixer->prefixForTargets('.foo { -webkit-box-orient: horizontal; -moz-box-orient: horizontal; -webkit-box-direction: normal; -moz-box-direction: normal; -webkit-flex-direction: row; -ms-flex-direction: row; flex-direction: row; }', $modernTargets)
        );
        $t->same(
            '.foo{flex-wrap:wrap}',
            $prefixer->prefixForTargets('.foo { -webkit-box-lines: multiple; -moz-box-lines: multiple; -webkit-flex-wrap: wrap; -ms-flex-wrap: wrap; flex-wrap: wrap; }', $modernTargets)
        );
        $t->same(
            '.foo{flex-grow:1}',
            $prefixer->prefixForTargets('.foo { -webkit-box-flex: 1; -moz-box-flex: 1; -ms-flex-positive: 1; -webkit-flex-grow: 1; flex-grow: 1; }', $modernTargets)
        );
        $t->same(
            '.foo{flex:1}',
            $prefixer->prefixForTargets('.foo { -webkit-box-flex: 1; -moz-box-flex: 1; -webkit-flex: 1; -ms-flex: 1; flex: 1; }', $modernTargets)
        );
        $t->same(
            '.foo{justify-content:space-between}',
            $prefixer->prefixForTargets('.foo { -webkit-box-pack: justify; -moz-box-pack: justify; -ms-flex-pack: justify; -webkit-justify-content: space-between; justify-content: space-between; }', $modernTargets)
        );
        $t->same(
            '.foo{align-items:flex-end}',
            $prefixer->prefixForTargets('.foo { -webkit-box-align: end; -moz-box-align: end; -ms-flex-align: end; -webkit-align-items: flex-end; align-items: flex-end; }', $modernTargets)
        );
        $t->same(
            '.foo{order:1}',
            $prefixer->prefixForTargets('.foo { -webkit-box-ordinal-group: 1; -moz-box-ordinal-group: 1; -ms-flex-order: 1; -webkit-order: 1; order: 1; }', $modernTargets)
        );
        $t->same(
            '.foo{-ms-flex:0 0 8%;flex:0 0 5%}',
            $prefixer->prefixForTargets('.foo { -ms-flex: 0 0 8%; flex: 0 0 5%; }', $modernTargets)
        );
    },
    'transition prefixer maps upstream flex longhand browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-box-orient:horizontal;-webkit-box-direction:normal;-webkit-flex-direction:row;flex-direction:row}',
            $prefixer->prefixForTargets('.foo { flex-direction: row; }', ['chrome' => 20])
        );
        $t->same(
            '.foo{-webkit-flex-direction:row;flex-direction:row}',
            $prefixer->prefixForTargets('.foo { flex-direction: row; }', ['chrome' => 21])
        );
        $t->same(
            '.foo{-webkit-flex-direction:row;flex-direction:row}',
            $prefixer->prefixForTargets('.foo { flex-direction: row; }', ['chrome' => 28])
        );
        $t->same(
            '.foo{flex-direction:row}',
            $prefixer->prefixForTargets('.foo { flex-direction: row; }', ['chrome' => 29])
        );
        $t->same(
            '.foo{-webkit-box-align:end;-webkit-align-items:flex-end;align-items:flex-end}',
            $prefixer->prefixForTargets('.foo { align-items: flex-end; }', ['safari' => 6])
        );
        $t->same(
            '.foo{-webkit-align-items:flex-end;align-items:flex-end}',
            $prefixer->prefixForTargets('.foo { align-items: flex-end; }', ['safari' => 7])
        );
        $t->same(
            '.foo{-moz-box-pack:justify;justify-content:space-between}',
            $prefixer->prefixForTargets('.foo { justify-content: space-between; }', ['firefox' => 21])
        );
        $t->same(
            '.foo{justify-content:space-between}',
            $prefixer->prefixForTargets('.foo { justify-content: space-between; }', ['firefox' => 22])
        );
        $t->same(
            '.foo{-ms-flex-order:1;order:1}',
            $prefixer->prefixForTargets('.foo { order: 1; }', ['ie' => 10])
        );
        $t->same(
            '.foo{order:1}',
            $prefixer->prefixForTargets('.foo { order: 1; }', ['ie' => 11])
        );
    },
    'transition prefixer maps upstream border-radius target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-border-radius:10px 20px;border-radius:10px 20px}',
            $prefixer->prefixForTargets('.foo { border-radius: 10px 20px 10px 20px; }', ['chrome' => 4])
        );
        $t->same(
            '.foo{-moz-border-radius:20px 10px 10px;border-radius:20px 10px 10px}',
            $prefixer->prefixForTargets('.foo { border-radius: 10px; border-top-left-radius: 20px; }', ['firefox' => 3.6])
        );
        $t->same(
            '.foo{border-radius:5px 20px 10px}',
            $prefixer->prefixForTargets('.foo { -webkit-border-radius: 10px 20px; -moz-border-top-left-radius: 5px; border-radius: 10px 20px; border-top-left-radius: 5px; }', ['chrome' => 95])
        );
    },
    'transition prefixer maps upstream border-radius logical corner fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $rtl = ':is(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi))';

        $t->same(
            ".foo:not({$rtl}){border-top-left-radius:5px}.foo{$rtl}{border-top-right-radius:5px}",
            $prefixer->prefixForTargets('.foo { border-start-start-radius: 5px; }', ['safari' => 12])
        );
        $t->same(
            ".foo:not({$rtl}){border-top-left-radius:5px;border-top-right-radius:10px}.foo{$rtl}{border-top-right-radius:5px;border-top-left-radius:10px}",
            $prefixer->prefixForTargets('.foo { border-start-start-radius: 5px; border-start-end-radius: 10px; }', ['safari' => 12])
        );
        $t->same(
            ".foo:not({$rtl}){border-bottom-right-radius:10px;border-bottom-left-radius:5px}.foo{$rtl}{border-bottom-left-radius:10px;border-bottom-right-radius:5px}",
            $prefixer->prefixForTargets('.foo { border-end-end-radius: 10px; border-end-start-radius: 5px; }', ['safari' => 12])
        );
        $t->same(
            ".foo:not({$rtl}){border-top-left-radius:var(--start);border-top-right-radius:var(--end)}.foo{$rtl}{border-top-right-radius:var(--start);border-top-left-radius:var(--end)}",
            $prefixer->prefixForTargets('.foo { border-start-start-radius: var(--start); border-start-end-radius: var(--end); }', ['safari' => 12])
        );
    },
    'wordpress editor color-scheme fallback flags prefix without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
:root {
  color-scheme: light dark;
}

.editor-styles-wrapper.is-dark-theme {
  color-scheme: dark;
}

.editor-styles-wrapper .has-accent-color {
  color: light-dark(var(--wp--preset--color--accent-light), var(--wp--preset--color--accent-dark));
}

.editor-styles-wrapper .has-warning-background {
  background-color: color-mix(in srgb, light-dark(yellow, red), light-dark(red, pink));
}

.editor-styles-wrapper .has-alpha-accent-color {
  color: rgb(from light-dark(yellow, red) r g b / var(--wp--custom--alpha));
}
CSS;

        $t->same(
            ':root{--lightningcss-light:initial;--lightningcss-dark:;color-scheme:light dark}@media (prefers-color-scheme:dark){:root{--lightningcss-light:;--lightningcss-dark:initial}}.editor-styles-wrapper.is-dark-theme{--lightningcss-light:;--lightningcss-dark:initial;color-scheme:dark}.editor-styles-wrapper .has-accent-color{color:var(--lightningcss-light,var(--wp--preset--color--accent-light)) var(--lightningcss-dark,var(--wp--preset--color--accent-dark))}.editor-styles-wrapper .has-warning-background{background-color:var(--lightningcss-light,#ff8000) var(--lightningcss-dark,#ff6066)}.editor-styles-wrapper .has-alpha-accent-color{color:var(--lightningcss-light,rgb(255 255 0 / var(--wp--custom--alpha))) var(--lightningcss-dark,rgb(255 0 0 / var(--wp--custom--alpha)))}',
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90])
        );
    },
    'wordpress print export keeps exact colors on old chrome print pipeline without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-post-content .print-cover {
  print-color-adjust: exact;
}
CSS;

        $t->same(
            '.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}',
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 135])
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
    'transition prefixer maps upstream background advanced color fallback layers' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{background:linear-gradient(#ff0f0e,#7773ff);background:linear-gradient(color(display-p3 1 .0000153435 -.00000303562),color(display-p3 .440289 .28452 1.23485));background:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364))}',
            $prefixer->prefixLegacySafari('.foo { background: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) }')
        );
        $t->same(
            '.foo{background-image:linear-gradient(#ff0f0e,#7773ff);background-image:linear-gradient(color(display-p3 1 .0000153435 -.00000303562),color(display-p3 .440289 .28452 1.23485));background-image:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364))}',
            $prefixer->prefixLegacySafari('.foo { background-image: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) }')
        );
        $t->same(
            '.foo{background:#af5cae url("foo.png");background:lab(51.5117% 43.3777 -29.0443) url("foo.png")}',
            $prefixer->prefixLegacySafari('.foo { background: lab(51.5117% 43.3777 -29.0443) url(foo.png); }')
        );
        $t->same(
            '@supports (color:lab(0% 0 0)){.foo{background:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364))}}',
            $prefixer->prefixLegacySafari('@supports (color: lab(0% 0 0)) { .foo { background: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) } }')
        );
    },
    'transition prefixer maps upstream oklab and oklch lab target fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{background-image:linear-gradient(#c65d07,#00807c);background-image:linear-gradient(lab(52.2319% 40.1449 59.9171),lab(47.7776% -34.2947 -7.65904))}',
            $prefixer->prefixLegacySafari('.foo { background-image: linear-gradient(oklab(59.686% 0.1009 0.1192), oklab(54.0% -0.10 -0.02)); }')
        );
        $t->same(
            '.foo{background-color:#7e250f;background-color:lab(29.2661% 38.2437 35.3889)}',
            $prefixer->prefixLegacySafari('.foo { background-color: oklch(40% 0.1268735435 34.568626) }')
        );
    },
    'transition prefixer maps upstream custom property advanced color supports' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.foo {
  --foo: oklab(59.686% 0.1009 0.1192);
  --bar: lab(40% 56.6 39);
}
CSS;

        $t->same(
            '.foo{--foo:#c65d07;--bar:#b32323}@supports (color:color(display-p3 0 0 0)){.foo{--foo:color(display-p3 .724144 .386777 .148795);--bar:color(display-p3 .643308 .192455 .167712)}}@supports (color:lab(0% 0 0)){.foo{--foo:lab(52.2319% 40.1449 59.9171);--bar:lab(40% 56.6 39)}}',
            (new TransitionPrefixer())->prefixLegacySafari($css)
        );
        $t->same(
            '.foo{--foo:#00f942}@supports (color:color(display-p3 0 0 0)){.foo{--foo:color(display-p3 0 1 0)}}',
            (new TransitionPrefixer())->prefixLegacySafari('.foo { --foo: color(display-p3 0 1 0); }')
        );
        $t->same(
            '@supports (color:lab(0% 0 0)){.foo{--foo:oklab(59.686% 0.1009 0.1192)}}',
            (new TransitionPrefixer())->prefixLegacySafari('@supports (color: lab(0% 0 0)) { .foo { --foo: oklab(59.686% 0.1009 0.1192); } }')
        );
    },
    'transition prefixer maps upstream font palette values advanced color fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@font-palette-values --Cooler{font-family:Handover Sans;base-palette:3;override-colors:1 #2b0c09,3 #ee00be;override-colors:1 #2b0c09,3 lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets(
                '@font-palette-values --Cooler { font-family: Handover Sans; base-palette: 3; override-colors: 1 rgb(43, 12, 9), 3 lch(50.998% 135.363 338); }',
                ['chrome' => 90]
            )
        );
        $t->same(
            '@font-palette-values --Cooler{font-family:Handover Sans;base-palette:3;override-colors:1 var(--foo),3 #ee00be}@supports (color:lab(0% 0 0)){@font-palette-values --Cooler{font-family:Handover Sans;base-palette:3;override-colors:1 var(--foo),3 lab(50.998% 125.506 -50.7078)}}',
            $prefixer->prefixForTargets(
                '@font-palette-values --Cooler { font-family: Handover Sans; base-palette: 3; override-colors: 1 var(--foo), 3 lch(50.998% 135.363 338); }',
                ['chrome' => 90]
            )
        );
        $t->same(
            '@supports (color:lab(0% 0 0)){@font-palette-values --Cooler{font-family:Handover Sans;base-palette:3;override-colors:1 var(--foo),3 lab(50.998% 125.506 -50.7078)}}',
            $prefixer->prefixForTargets(
                '@supports (color: lab(0% 0 0)) { @font-palette-values --Cooler { font-family: Handover Sans; base-palette: 3; override-colors: 1 var(--foo), 3 lab(50.998% 125.506 -50.7078); } }',
                ['chrome' => 90]
            )
        );
    },
    'transition prefixer maps upstream filter and backdrop-filter prefixing' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-filter:blur(5px);filter:blur(5px)}',
            $prefixer->prefixLegacySafari('.foo { filter: blur(5px) }')
        );
        $t->same(
            '.foo{-webkit-backdrop-filter:blur(5px);backdrop-filter:blur(5px)}',
            $prefixer->prefixLegacySafari('.foo { backdrop-filter: blur(5px) }')
        );
        $t->same(
            '.foo{-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)}',
            $prefixer->prefixLegacySafari('.foo { -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); }')
        );
        $t->same(
            '.foo{-webkit-filter:var(--foo);filter:var(--foo)}',
            $prefixer->prefixLegacySafari('.foo { filter: var(--foo) }')
        );
        $t->same(
            '.foo{backdrop-filter:blur(5px)}',
            $prefixer->prefixForTargets('.foo { backdrop-filter: blur(5px) }', ['chrome' => 80])
        );
    },
    'transition prefixer maps upstream backdrop-filter supports conditions' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@supports ((-webkit-backdrop-filter:blur(10px)) or (backdrop-filter:blur(10px))){div{-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px)}}',
            $prefixer->prefixForTargets('@supports (backdrop-filter: blur(10px)) { div { backdrop-filter: blur(10px); } }', ['safari' => 14])
        );
        $t->same(
            '@supports (-webkit-backdrop-filter:blur(10px)) or (backdrop-filter:blur(10px)){div{-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px)}}',
            $prefixer->prefixForTargets('@supports ((-webkit-backdrop-filter: blur(10px)) or (backdrop-filter: blur(10px))) { div { backdrop-filter: blur(10px); } }', ['safari' => 14])
        );
        $t->same(
            '@supports (-webkit-backdrop-filter:blur(20px)) or ((-webkit-backdrop-filter:blur(10px)) or (backdrop-filter:blur(10px))){div{-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px)}}',
            $prefixer->prefixForTargets('@supports ((-webkit-backdrop-filter: blur(20px)) or (backdrop-filter: blur(10px))) { div { backdrop-filter: blur(10px); } }', ['safari' => 14])
        );
        $t->same(
            '@supports (backdrop-filter:blur(10px)){div{backdrop-filter:blur(10px)}}',
            $prefixer->prefixForTargets('@supports ((-webkit-backdrop-filter: blur(10px)) or (backdrop-filter: blur(10px))) { div { backdrop-filter: blur(10px); } }', ['chrome' => 80])
        );
    },
    'transition prefixer maps upstream filter advanced color fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-filter:drop-shadow(16px 16px 20px #b32323);filter:drop-shadow(16px 16px 20px #b32323);filter:drop-shadow(16px 16px 20px lab(40% 56.6 39))}',
            $prefixer->prefixLegacySafari('.foo { filter: drop-shadow(16px 16px 20px lab(40% 56.6 39)) }')
        );
        $t->same(
            '.foo{-webkit-filter:var(--foo) drop-shadow(16px 16px 20px #b32323);filter:var(--foo) drop-shadow(16px 16px 20px #b32323)}@supports (color:lab(0% 0 0)){.foo{-webkit-filter:var(--foo) drop-shadow(16px 16px 20px lab(40% 56.6 39));filter:var(--foo) drop-shadow(16px 16px 20px lab(40% 56.6 39))}}',
            $prefixer->prefixLegacySafari('.foo { filter: var(--foo) drop-shadow(16px 16px 20px lab(40% 56.6 39)) }')
        );
    },
    'transition prefixer maps upstream target-specific box-shadow prefixes and fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{box-shadow:12px 12px #b32323;box-shadow:12px 12px lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { box-shadow: 12px 12px lab(40% 56.6 39) }', ['chrome' => 90])
        );
        $t->same(
            '.foo{-webkit-box-shadow:12px 12px #b32323;box-shadow:12px 12px #b32323;box-shadow:12px 12px lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { box-shadow: 12px 12px lab(40% 56.6 39) }', ['chrome' => 4])
        );
        $t->same(
            '.foo{-webkit-box-shadow:12px 12px #b32323,12px 12px #ff0;box-shadow:12px 12px #b32323,12px 12px #ff0;box-shadow:12px 12px lab(40% 56.6 39),12px 12px #ff0}',
            $prefixer->prefixForTargets('.foo { box-shadow: 12px 12px lab(40% 56.6 39), 12px 12px yellow }', ['chrome' => 4])
        );
        $t->same(
            '.foo{-webkit-box-shadow:12px 12px rgba(0, 0, 0, .4);-moz-box-shadow:12px 12px rgba(0, 0, 0, .6)}',
            $prefixer->prefixForTargets('.foo { -webkit-box-shadow: 12px 12px #0006; -moz-box-shadow: 12px 12px #0009; }', ['chrome' => 4])
        );
        $t->same(
            '.foo{box-shadow:12px 12px #0006}',
            $prefixer->prefixForTargets('.foo { -webkit-box-shadow: 12px 12px #0006; -moz-box-shadow: 12px 12px #0006; box-shadow: 12px 12px #0006; }', ['chrome' => 95])
        );
        $t->same(
            '.foo{box-shadow:var(--foo) 12px #b32323}@supports (color:lab(0% 0 0)){.foo{box-shadow:var(--foo) 12px lab(40% 56.6 39)}}',
            $prefixer->prefixForTargets('.foo { box-shadow: var(--foo) 12px lab(40% 56.6 39) }', ['chrome' => 90])
        );
        $t->same(
            '.foo{box-shadow:0 0 22px lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { box-shadow: 0px 0px 22px red; box-shadow: 0px 0px 22px lab(40% 56.6 39); }', ['safari' => 16])
        );
        $t->same(
            '.foo{box-shadow:var(--fallback);box-shadow:0 0 22px lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { box-shadow: var(--fallback); box-shadow: 0px 0px 22px lab(40% 56.6 39); }', ['safari' => 16])
        );
        $t->same(
            '.foo{box-shadow:0 0 22px red;box-shadow:0 0 22px lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { box-shadow: 0px 0px 22px red; box-shadow: 0px 0px 22px lab(40% 56.6 39); }', ['safari' => 14])
        );
    },
    'transition prefixer maps upstream box shadow oklch alpha fallback targets' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = <<<'CSS'
.foo {
  box-shadow:
    oklch(100% 0 0deg / 50%) 0 0.63rem 0.94rem -0.19rem,
    currentColor 0 0.44rem 0.8rem -0.58rem;
}
CSS;

        $t->same(
            '.foo{box-shadow:0 .63rem .94rem -.19rem #ffffff80,0 .44rem .8rem -.58rem;box-shadow:0 .63rem .94rem -.19rem lab(100% 0 0 / .5),0 .44rem .8rem -.58rem}',
            $prefixer->prefixForTargets($css, ['chrome' => 95])
        );
        $t->same(
            '.foo{box-shadow:0 .63rem .94rem -.19rem color(display-p3 1 1 1 / .5),0 .44rem .8rem -.58rem;box-shadow:0 .63rem .94rem -.19rem lab(100% 0 0 / .5),0 .44rem .8rem -.58rem}',
            $prefixer->prefixForTargets($css, ['safari' => 14])
        );
    },
    'transition prefixer maps upstream text shadow fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{text-shadow:12px 12px #b32323;text-shadow:12px 12px lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { text-shadow: 12px 12px lab(40% 56.6 39) }', ['chrome' => 4])
        );
        $t->same(
            '.foo{text-shadow:12px 12px #b32323;text-shadow:12px 12px color(display-p3 .643308 .192455 .167712);text-shadow:12px 12px lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { text-shadow: 12px 12px lab(40% 56.6 39) }', ['chrome' => 90, 'safari' => 14])
        );
        $t->same(
            '.foo{text-shadow:12px 12px #b32323,12px 12px #ff0;text-shadow:12px 12px lab(40% 56.6 39),12px 12px #ff0}',
            $prefixer->prefixForTargets('.foo { text-shadow: 12px 12px lab(40% 56.6 39), 12px 12px yellow }', ['chrome' => 4])
        );
        $t->same(
            '.foo{text-shadow:var(--foo) 12px #b32323}@supports (color:lab(0% 0 0)){.foo{text-shadow:var(--foo) 12px lab(40% 56.6 39)}}',
            $prefixer->prefixForTargets('.foo { text-shadow: var(--foo) 12px lab(40% 56.6 39) }', ['chrome' => 4])
        );
        $t->same(
            '@supports (color:lab(0% 0 0)){.foo{text-shadow:var(--foo) 12px lab(40% 56.6 39)}}',
            $prefixer->prefixForTargets('@supports (color: lab(0% 0 0)) { .foo { text-shadow: var(--foo) 12px lab(40% 56.6 39); } }', ['chrome' => 4])
        );
    },
    'transition prefixer maps upstream text decoration prefixes and color fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-text-decoration-line:underline;-moz-text-decoration-line:underline;text-decoration-line:underline}',
            $prefixer->prefixForTargets('.foo { text-decoration-line: underline; }', ['safari' => 8, 'firefox' => 30])
        );
        $t->same(
            '.foo{-webkit-text-decoration-style:dotted;-moz-text-decoration-style:dotted;text-decoration-style:dotted}',
            $prefixer->prefixForTargets('.foo { text-decoration-style: dotted; }', ['safari' => 8, 'firefox' => 30])
        );
        $t->same(
            '.foo{-webkit-text-decoration-color:#ff0;-moz-text-decoration-color:#ff0;text-decoration-color:#ff0}',
            $prefixer->prefixForTargets('.foo { text-decoration-color: yellow; }', ['safari' => 8, 'firefox' => 30])
        );
        $t->same(
            '.foo{text-decoration:underline}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline; }', ['safari' => 8, 'firefox' => 30])
        );
        $t->same(
            '.foo{-webkit-text-decoration:underline double;text-decoration:underline double}',
            $prefixer->prefixForTargets('.foo { text-decoration: double underline; }', ['safari' => 16])
        );
        $t->same(
            '.foo{-webkit-text-decoration:underline double;text-decoration:underline double}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline; text-decoration-style: double; }', ['safari' => 16])
        );
        $t->same(
            '.foo{-webkit-text-decoration:underline red;text-decoration:underline red}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline; text-decoration-color: red; }', ['safari' => 16])
        );
        $t->same(
            '.foo{-webkit-text-decoration:var(--test);text-decoration:var(--test)}',
            $prefixer->prefixForTargets('.foo { text-decoration: var(--test); }', ['safari' => 8, 'firefox' => 30])
        );
        $t->same(
            '.foo{-webkit-text-decoration:underline #ee00be;text-decoration:underline #ee00be;-webkit-text-decoration:underline lch(50.998% 135.363 338);text-decoration:underline lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets('.foo { text-decoration: lch(50.998% 135.363 338) underline; }', ['safari' => 8, 'firefox' => 30])
        );
        $t->same(
            '.foo{-webkit-text-decoration-color:#ee00be;-moz-text-decoration-color:#ee00be;text-decoration-color:#ee00be;-webkit-text-decoration-color:lch(50.998% 135.363 338);-moz-text-decoration-color:lch(50.998% 135.363 338);text-decoration-color:lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets('.foo { text-decoration-color: lch(50.998% 135.363 338); }', ['safari' => 8, 'firefox' => 30])
        );
        $t->same(
            '.foo{text-decoration:#ee00be var(--style)}@supports (color:lab(0% 0 0)){.foo{text-decoration:lab(50.998% 125.506 -50.7078) var(--style)}}',
            $prefixer->prefixForTargets('.foo { text-decoration: lch(50.998% 135.363 338) var(--style); }', ['chrome' => 90])
        );
        $t->same(
            '@supports (color:lab(0% 0 0)){.foo{text-decoration:lab(50.998% 125.506 -50.7078) var(--style)}}',
            $prefixer->prefixForTargets('@supports (color: lab(0% 0 0)) { .foo { text-decoration: lab(50.998% 125.506 -50.7078) var(--style); } }', ['chrome' => 90])
        );
    },
    'transition prefixer maps upstream text emphasis prefixes and color fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-text-emphasis-style:filled;text-emphasis-style:filled}',
            $prefixer->prefixForTargets('.foo { text-emphasis-style: filled; }', ['chrome' => 30, 'safari' => 10, 'firefox' => 45])
        );
        $t->same(
            '.foo{text-emphasis-style:filled}',
            $prefixer->prefixForTargets('.foo { -webkit-text-emphasis-style: filled; text-emphasis-style: filled; }', ['safari' => 10, 'firefox' => 45])
        );
        $t->same(
            '.foo{-webkit-text-emphasis-position:over;text-emphasis-position:over}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: over; }', ['chrome' => 30, 'safari' => 10, 'firefox' => 45])
        );
        $t->same(
            '.foo{text-emphasis-position:over left}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: over left; }', ['chrome' => 30, 'safari' => 10, 'firefox' => 45])
        );
        $t->same(
            '.foo{-webkit-text-emphasis-position:var(--test);text-emphasis-position:var(--test)}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: var(--test); }', ['chrome' => 30, 'safari' => 10, 'firefox' => 45])
        );
        $t->same(
            '.foo{-webkit-text-emphasis:filled #ee00be;text-emphasis:filled #ee00be;-webkit-text-emphasis:filled lch(50.998% 135.363 338);text-emphasis:filled lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets('.foo { text-emphasis: filled lch(50.998% 135.363 338); }', ['chrome' => 25, 'firefox' => 48])
        );
        $t->same(
            '.foo{-webkit-text-emphasis-color:#ee00be;text-emphasis-color:#ee00be;-webkit-text-emphasis-color:lch(50.998% 135.363 338);text-emphasis-color:lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets('.foo { text-emphasis-color: lch(50.998% 135.363 338); }', ['chrome' => 25, 'firefox' => 48])
        );
        $t->same(
            '.foo{text-emphasis:#ee00be var(--style)}@supports (color:lab(0% 0 0)){.foo{text-emphasis:lab(50.998% 125.506 -50.7078) var(--style)}}',
            $prefixer->prefixForTargets('.foo { text-emphasis: lch(50.998% 135.363 338) var(--style); }', ['safari' => 8])
        );
        $t->same(
            '@supports (color:lab(0% 0 0)){.foo{text-emphasis:lab(50.998% 125.506 -50.7078) var(--style)}}',
            $prefixer->prefixForTargets('@supports (color: lab(0% 0 0)) { .foo { text-emphasis: lab(50.998% 125.506 -50.7078) var(--style); } }', ['safari' => 8])
        );
    },
    'transition prefixer maps upstream caret advanced color fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{caret-color:#ee00be;caret-color:color(display-p3 .972962 -.362078 .804206);caret-color:lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets('.foo { caret-color: lch(50.998% 135.363 338) }', ['chrome' => 90, 'safari' => 14])
        );
        $t->same(
            '.foo{caret:#ee00be block;caret:color(display-p3 .972962 -.362078 .804206) block;caret:lch(50.998% 135.363 338) block}',
            $prefixer->prefixForTargets('.foo { caret: lch(50.998% 135.363 338) block }', ['chrome' => 90, 'safari' => 14])
        );
        $t->same(
            '.foo{caret:#ee00be var(--foo)}@supports (color:lab(0% 0 0)){.foo{caret:lab(50.998% 125.506 -50.7078) var(--foo)}}',
            $prefixer->prefixForTargets('.foo { caret: lch(50.998% 135.363 338) var(--foo) }', ['chrome' => 90])
        );
        $t->same(
            '@supports (color:lab(0% 0 0)){.foo{caret:lab(50.998% 125.506 -50.7078) var(--foo)}}',
            $prefixer->prefixForTargets('@supports (color: lab(0% 0 0)) { .foo { caret: lab(50.998% 125.506 -50.7078) var(--foo); } }', ['chrome' => 90])
        );
    },
    'transition prefixer maps upstream list-style advanced color fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $star = "\u{2605}";

        $t->same(
            '.foo{list-style-image:-webkit-gradient(linear,0 0,0 100%,from(#ff0f0e),to(#7773ff));list-style-image:-webkit-linear-gradient(top,#ff0f0e,#7773ff);list-style-image:linear-gradient(#ff0f0e,#7773ff);list-style-image:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364))}',
            $prefixer->prefixForTargets('.foo { list-style-image: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) }', ['chrome' => 8])
        );
        $t->same(
            '.foo{list-style:linear-gradient(#ff0f0e,#7773ff) "' . $star . '";list-style:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364)) "' . $star . '"}',
            $prefixer->prefixForTargets('.foo { list-style: "' . $star . '" linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) }', ['chrome' => 90])
        );
        $t->same(
            '.foo{list-style:var(--foo) linear-gradient(#ff0f0e,#7773ff)}@supports (color:lab(0% 0 0)){.foo{list-style:var(--foo) linear-gradient(lab(56.208% 94.4644 98.8928),lab(51% 70.4544 -115.586))}}',
            $prefixer->prefixForTargets('.foo { list-style: var(--foo) linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364)) }', ['chrome' => 90])
        );
        $t->same(
            '@supports (color:lab(0% 0 0)){.foo{list-style:var(--foo) linear-gradient(lab(56.208% 94.4644 98.8928),lab(51% 70.4544 -115.586))}}',
            $prefixer->prefixForTargets('@supports (color: lab(0% 0 0)) { .foo { list-style: var(--foo) linear-gradient(lab(56.208% 94.4644 98.8928), lab(51% 70.4544 -115.586)); } }', ['chrome' => 90])
        );
    },
    'transition prefixer maps upstream image-set WebKit prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{background:-webkit-image-set(url("foo.png") 2x,url("bar.png") 1x);background:image-set("foo.png" 2x,"bar.png" 1x)}',
            $prefixer->prefixForTargets('.foo { background: image-set(url("foo.png") 2x, url(bar.png) 1x); }', ['chrome' => 85, 'firefox' => 80])
        );
        $t->same(
            '.foo{background:-webkit-image-set(url(foo.png) 2x,url(bar.png) 1x)}',
            $prefixer->prefixForTargets('.foo { background: -webkit-image-set(url("foo.png") 2x, url(bar.png) 1x); }', ['chrome' => 95])
        );
        $t->same(
            '.foo{background:-webkit-image-set(url(foo.png) 2x,url(bar.png) 1x);background:image-set("foo.png" 2x,"bar.png" 1x)}',
            $prefixer->prefixForTargets('.foo { background: -webkit-image-set(url("foo.png") 2x, url(bar.png) 1x); background: image-set(url("foo.png") 2x, url(bar.png) 1x); }', ['firefox' => 80])
        );
        $t->same(
            '.foo{background-image:-webkit-image-set(url("foo.png") 2x,url("bar.png") 1x);background-image:image-set("foo.png" 2x,"bar.png" 1x)}',
            $prefixer->prefixForTargets('.foo { background-image: image-set(url("foo.png") 2x, url(bar.png) 1x); }', ['chrome' => 95])
        );
        $t->same(
            '.foo{list-style-image:-webkit-image-set(url("marker.png") 2x,url("marker-small.png") 1x);list-style-image:image-set("marker.png" 2x,"marker-small.png" 1x)}',
            $prefixer->prefixForTargets('.foo { list-style-image: image-set(url("marker.png") 2x, url(marker-small.png) 1x); }', ['chrome' => 95])
        );
        $t->same(
            '.foo{list-style:-webkit-image-set(url("marker.png") 2x,url("marker-small.png") 1x) square;list-style:image-set("marker.png" 2x,"marker-small.png" 1x) square}',
            $prefixer->prefixForTargets('.foo { list-style: square image-set(url("marker.png") 2x, url(marker-small.png) 1x); }', ['chrome' => 95])
        );
        $t->same(
            '.foo{background:url("foo.png");background:image-set("foo.png" 2x,"bar.png" 1x)}',
            $prefixer->prefixForTargets('.foo { background: url(foo.png); background: image-set(url("foo.png") 2x, url(bar.png) 1x); }', ['ie' => 11, 'chrome' => 95])
        );
    },
    'transition prefixer maps upstream keyframes target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@-webkit-keyframes test{0%{opacity:0}to{opacity:1}}@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['safari' => 8])
        );
        $t->same(
            '@-moz-keyframes test{0%{opacity:0}to{opacity:1}}@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['firefox' => 15])
        );
        $t->same(
            '@-webkit-keyframes test{0%{opacity:0}to{opacity:1}}@-moz-keyframes test{0%{opacity:0}to{opacity:1}}@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['chrome' => 42, 'firefox' => 15])
        );
        $t->same(
            '@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@-webkit-keyframes test { from { opacity: 0 } to { opacity: 1 } } @keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['chrome' => 95])
        );
        $t->same(
            '@-webkit-keyframes test{0%{opacity:0}to{opacity:1}}@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@-webkit-keyframes test { from { opacity: 0 } to { opacity: 1 } } @keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['safari' => 8])
        );
    },
    'transition prefixer maps upstream encoded browser target prefix boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $encoded = static fn (int $major, int $minor = 0, int $patch = 0): int => ($major << 16) | ($minor << 8) | $patch;

        $t->same(
            '.foo{-webkit-box-shadow:1px 1px #000;box-shadow:1px 1px #000}',
            $prefixer->prefixForTargets('.foo { box-shadow: 1px 1px #000; }', ['safari' => $encoded(5, 0)])
        );
        $t->same(
            '.foo{box-shadow:1px 1px #000}',
            $prefixer->prefixForTargets('.foo { box-shadow: 1px 1px #000; }', ['safari' => $encoded(5, 1)])
        );
        $t->same(
            '.foo{background:-webkit-image-set(url("foo.png") 2x);background:image-set("foo.png" 2x)}',
            $prefixer->prefixForTargets('.foo { background: image-set(url(foo.png) 2x); }', ['chrome' => 112])
        );
        $t->same(
            '.foo{background:image-set("foo.png" 2x)}',
            $prefixer->prefixForTargets('.foo { background: image-set(url(foo.png) 2x); }', ['chrome' => 113])
        );
        $t->same(
            '.foo{-webkit-backdrop-filter:blur(5px);backdrop-filter:blur(5px)}',
            $prefixer->prefixForTargets('.foo { backdrop-filter: blur(5px); }', ['safari' => $encoded(17, 6)])
        );
        $t->same(
            '.foo{backdrop-filter:blur(5px)}',
            $prefixer->prefixForTargets('.foo { backdrop-filter: blur(5px); }', ['safari' => 18])
        );
        $t->same(
            '.foo{-webkit-print-color-adjust:exact;print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['edge' => 135])
        );
        $t->same(
            '.foo{print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['chrome' => 16])
        );
        $t->same(
            '.foo{-webkit-text-emphasis-style:filled;text-emphasis-style:filled}',
            $prefixer->prefixForTargets('.foo { text-emphasis-style: filled; }', ['chrome' => 98])
        );
        $t->same(
            '.foo{text-emphasis-style:filled}',
            $prefixer->prefixForTargets('.foo { text-emphasis-style: filled; }', ['chrome' => 99])
        );
        $t->same(
            '.foo{-webkit-text-decoration:underline double;text-decoration:underline double}',
            $prefixer->prefixForTargets('.foo { text-decoration: double underline; }', ['safari' => 26])
        );
        $t->same(
            '.foo{text-decoration:double underline}',
            $prefixer->prefixForTargets('.foo { text-decoration: double underline; }', ['safari' => 27])
        );
        $t->same(
            '@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['safari' => $encoded(8, 1)])
        );
        $t->same(
            '.foo{color-scheme:light;color:light-dark(red,green)}',
            $prefixer->prefixForTargets('.foo { color-scheme: light; color: light-dark(red, green); }', ['safari' => $encoded(17, 5)])
        );
        $t->same(
            '.foo{--lightningcss-light:initial;--lightningcss-dark:;color-scheme:light;color:var(--lightningcss-light,red) var(--lightningcss-dark,green)}',
            $prefixer->prefixForTargets('.foo { color-scheme: light; color: light-dark(red, green); }', ['safari' => $encoded(17, 4)])
        );
    },
    'transition prefixer maps upstream media range target fallbacks inside layers' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@layer blocks{@media (min-width:240px){.wp-block-query{color:#7fff00}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 240px) { .wp-block-query { color: chartreuse; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (width>=240px){.wp-block-query{color:#7fff00}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 240px) { .wp-block-query { color: chartreuse; } } }', ['firefox' => 64])
        );
        $t->same(
            '@layer blocks{@media (not (min-width:240px)) and (hover){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width < 240px) and (hover) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (hover) or (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media not (max-width:0){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width > 0) { .wp-block-query { color: yellow; } } }', ['chrome' => 85])
        );
    },
    'transition prefixer maps upstream media range include and exclude flags inside layers' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@layer blocks{@media (not (min-width:256px)) or (hover:none){.wp-block-query{color:#fff}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width < 256px) or (hover: none) { .wp-block-query { color: #fff; } } }', [
                'include' => ['MediaRangeSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (width<256px) or (hover:none){.wp-block-query{color:#fff}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width < 256px) or (hover: none) { .wp-block-query { color: #fff; } } }', [
                'firefox' => 60,
                'exclude' => ['MediaRangeSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (hover) or (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', [
                'include' => ['MediaIntervalSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (hover) or (100px<=width<=200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (hover) or (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', [
                'firefox' => 85,
                'exclude' => ['MediaIntervalSyntax'],
            ])
        );
    },
    'transition prefixer maps upstream resolution media prefixes inside layers' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15])
        );
        $t->same(
            '@layer blocks{@media (min--moz-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media not (-webkit-max-device-pixel-ratio:2),not (max-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution > 2dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15])
        );
        $t->same(
            '@media (-webkit-min-device-pixel-ratio:3.125),(min-resolution:300dpi){.foo{color:#ff0}}',
            $prefixer->prefixForTargets('@media (resolution >= 300dpi) { .foo { color: yellow; } }', ['safari' => 15])
        );
        $t->same(
            '@media (-webkit-min-device-pixel-ratio:2.99985),(min--moz-device-pixel-ratio:2.99985),(min-resolution:113.38dpcm){.foo{color:#ff0}}',
            $prefixer->prefixForTargets('@media (min-resolution: 113.38dpcm) { .foo { color: yellow; } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media (color) and (-webkit-min-device-pixel-ratio:2),(color) and (min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (color) and (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15])
        );
        $t->same(
            '@media (-webkit-min-device-pixel-ratio:2),(min--moz-device-pixel-ratio:2),(min-resolution:2dppx),(min-resolution:192dpi){.foo{color:#ff0}}',
            $prefixer->prefixForTargets('@media (min-resolution: 2dppx), (min-resolution: 192dpi) { .foo { color: yellow; } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@media only screen and (-webkit-min-device-pixel-ratio:1.3),only screen and (min--moz-device-pixel-ratio:1.3),only screen and (min-resolution:124.8dpi){.foo{color:#ff0}}',
            $prefixer->prefixForTargets('@media only screen and (min-resolution: 124.8dpi) { .foo { color: yellow; } }', ['safari' => 15, 'firefox' => 10])
        );
    },
    'transition prefixer maps upstream resolution x unit serialization inside layers' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@media (resolution:1dppx){body{background:red}}',
            $prefixer->prefixForTargets('@media (resolution: 1dppx) { body { background: red; } }', ['chrome' => 50])
        );
        $t->same(
            '@media (resolution:1x){body{background:red}}',
            $prefixer->prefixForTargets('@media (resolution: 1dppx) { body { background: red; } }', ['chrome' => 95])
        );
        $t->same(
            '@layer blocks{@media (resolution:1x){.wp-block-query{background:red}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution: 1dppx) { .wp-block-query { background: red; } } }', ['chrome' => 95])
        );
        $t->same(
            '@layer blocks{@media (min-resolution:2x){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['chrome' => 95])
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
    'wordpress cover transform math and clamp fallback minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-tilt:hover {
  transform: rotateX(mod(140deg, -90deg)) rotateY(rem(140deg, -90deg));
  border-width: clamp(1em, 2px, 4vh);
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-tilt:hover{transform:rotateX(-40deg)rotateY(50deg);border-width:max(1em,min(2px,4vh))}',
            (new TransitionPrefixer())->prefixForTargets($css, ['safari' => 12])
        );
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
    'wordpress sticky header filters get legacy WebKit prefixes without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-template-part.is-style-glass-header {
  backdrop-filter: blur(8px);
  filter: var(--wp--custom--header-filter);
}
CSS;

        $t->same(
            '.wp-block-template-part.is-style-glass-header{-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);-webkit-filter:var(--wp--custom--header-filter);filter:var(--wp--custom--header-filter)}',
            (new TransitionPrefixer())->prefixLegacySafari($css)
        );
    },
    'wordpress query card shadows get target-specific WebKit and color fallbacks without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-post-template .wp-block-post {
  box-shadow: var(--wp--preset--shadow--card) 12px lab(40% 56.6 39);
}
CSS;

        $t->same(
            '.wp-block-post-template .wp-block-post{-webkit-box-shadow:var(--wp--preset--shadow--card) 12px #b32323;box-shadow:var(--wp--preset--shadow--card) 12px #b32323}@supports (color:lab(0% 0 0)){.wp-block-post-template .wp-block-post{box-shadow:var(--wp--preset--shadow--card) 12px lab(40% 56.6 39)}}',
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 4])
        );
    },
    'wordpress heading text shadows get advanced color fallbacks without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-post-title.has-text-shadow {
  text-shadow: var(--wp--preset--shadow--headline) 12px lab(40% 56.6 39);
}
CSS;

        $t->same(
            '.wp-block-post-title.has-text-shadow{text-shadow:var(--wp--preset--shadow--headline) 12px #b32323}@supports (color:lab(0% 0 0)){.wp-block-post-title.has-text-shadow{text-shadow:var(--wp--preset--shadow--headline) 12px lab(40% 56.6 39)}}',
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 4])
        );
    },
    'wordpress link underline decoration gets legacy prefixes and lab fallbacks without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-post-content a.has-brand-underline {
  text-decoration: lch(50.998% 135.363 338) var(--wp--custom--underline-style);
}
CSS;

        $t->same(
            '.wp-block-post-content a.has-brand-underline{text-decoration:#ee00be var(--wp--custom--underline-style)}@supports (color:lab(0% 0 0)){.wp-block-post-content a.has-brand-underline{text-decoration:lab(50.998% 125.506 -50.7078) var(--wp--custom--underline-style)}}',
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90])
        );

        $css = <<<'CSS'
.wp-block-post-content a.has-brand-underline {
  text-decoration: underline;
  text-decoration-style: dotted;
}
CSS;

        $t->same(
            '.wp-block-post-content a.has-brand-underline{-webkit-text-decoration:underline dotted;text-decoration:underline dotted}',
            (new TransitionPrefixer())->prefixForTargets($css, ['safari' => 16])
        );
    },
    'wordpress editorial emphasis marks get advanced color fallbacks without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-post-content .has-annotation-emphasis {
  text-emphasis: lch(50.998% 135.363 338) var(--wp--custom--annotation-emphasis);
}
CSS;

        $t->same(
            '.wp-block-post-content .has-annotation-emphasis{text-emphasis:#ee00be var(--wp--custom--annotation-emphasis)}@supports (color:lab(0% 0 0)){.wp-block-post-content .has-annotation-emphasis{text-emphasis:lab(50.998% 125.506 -50.7078) var(--wp--custom--annotation-emphasis)}}',
            (new TransitionPrefixer())->prefixForTargets($css, ['safari' => 8])
        );
    },
    'wordpress editor inputs get caret color fallbacks without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-search .wp-block-search__input {
  caret: lch(50.998% 135.363 338) var(--wp--custom--editor-caret-shape);
}
CSS;

        $t->same(
            '.wp-block-search .wp-block-search__input{caret:#ee00be var(--wp--custom--editor-caret-shape)}@supports (color:lab(0% 0 0)){.wp-block-search .wp-block-search__input{caret:lab(50.998% 125.506 -50.7078) var(--wp--custom--editor-caret-shape)}}',
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90])
        );
    },
    'wordpress list marker gradients get advanced color fallbacks without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-list.is-style-gradient-markers {
  list-style: var(--wp--custom--list-marker) linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364));
}
CSS;

        $t->same(
            '.wp-block-list.is-style-gradient-markers{list-style:var(--wp--custom--list-marker) linear-gradient(#ff0f0e,#7773ff)}@supports (color:lab(0% 0 0)){.wp-block-list.is-style-gradient-markers{list-style:var(--wp--custom--list-marker) linear-gradient(lab(56.208% 94.4644 98.8928),lab(51% 70.4544 -115.586))}}',
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 90])
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
    'wordpress cover background gradients get advanced color fallback layers without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.has-brand-gradient {
  background: linear-gradient(lch(56.208% 136.76 46.312), lch(51% 135.366 301.364));
}
CSS;

        $t->same(
            '.wp-block-cover.has-brand-gradient{background:linear-gradient(#ff0f0e,#7773ff);background:linear-gradient(color(display-p3 1 .0000153435 -.00000303562),color(display-p3 .440289 .28452 1.23485));background:linear-gradient(lch(56.208% 136.76 46.312),lch(51% 135.366 301.364))}',
            (new TransitionPrefixer())->prefixLegacySafari($css)
        );
    },
    'wordpress theme color tokens get guarded p3 and lab fallbacks without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-button {
  --wp--preset--color--brand: oklab(59.686% 0.1009 0.1192);
  --wp--preset--color--accent: color(display-p3 0 1 0);
}
CSS;

        $t->same(
            '.wp-block-button{--wp--preset--color--brand:#c65d07;--wp--preset--color--accent:#00f942}@supports (color:color(display-p3 0 0 0)){.wp-block-button{--wp--preset--color--brand:color(display-p3 .724144 .386777 .148795);--wp--preset--color--accent:color(display-p3 0 1 0)}}@supports (color:lab(0% 0 0)){.wp-block-button{--wp--preset--color--brand:lab(52.2319% 40.1449 59.9171)}}',
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
