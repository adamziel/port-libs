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
            (new TransitionPrefixer())->prefixForTargets('.foo { transition-property: margin-inline-start, padding-inline-start; }', ['safari' => 8])
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
            (new TransitionPrefixer())->prefixForTargets('.foo { transition: margin-inline-start 2s, padding-inline-start 200ms; }', ['safari' => 8])
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
    'transition prefixer maps upstream transition declaration target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $transition = '.foo { transition: opacity 200ms; }';
        $modern = '.foo{transition:opacity .2s}';
        $webkit = '.foo{-webkit-transition:opacity .2s;transition:opacity .2s}';

        $t->same(
            '.foo{-webkit-transition:opacity .2s;-moz-transition:opacity .2s;transition:opacity .2s}',
            $prefixer->prefixForTargets($transition, ['safari' => 5, 'firefox' => 14])
        );
        $t->same(
            $modern,
            $prefixer->prefixForTargets('.foo { -webkit-transition: opacity 200ms; -moz-transition: opacity 200ms; transition: opacity 200ms; }', ['chrome' => 95])
        );

        $t->same($webkit, $prefixer->prefixForTargets($transition, ['chrome' => 25]));
        $t->same($modern, $prefixer->prefixForTargets($transition, ['chrome' => 26]));
        $t->same('.foo{-moz-transition:opacity .2s;transition:opacity .2s}', $prefixer->prefixForTargets($transition, ['firefox' => 15]));
        $t->same($modern, $prefixer->prefixForTargets($transition, ['firefox' => 16]));
        $t->same('.foo{-o-transition:opacity .2s;transition:opacity .2s}', $prefixer->prefixForTargets($transition, ['opera' => 12]));
        $t->same($modern, $prefixer->prefixForTargets($transition, ['opera' => 13]));
        $t->same($webkit, $prefixer->prefixForTargets($transition, ['ios_saf' => 6]));
        $t->same($modern, $prefixer->prefixForTargets($transition, ['ios_saf' => 7]));
        $t->same($webkit, $prefixer->prefixForTargets($transition, ['android' => '4.2']));
        $t->same($modern, $prefixer->prefixForTargets($transition, ['android' => '4.3']));
        $t->same(
            '.foo{-webkit-transition-property:opacity;transition-property:opacity}',
            $prefixer->prefixForTargets('.foo { transition-property: opacity; }', ['android' => '4.2'])
        );
        $t->same(
            '.foo{transition-property:opacity}',
            $prefixer->prefixForTargets('.foo { transition-property: opacity; }', ['android' => '4.3'])
        );
        $t->same(
            '.foo{-webkit-transition:opacity .2s ease-in-out .1s;transition:opacity .2s ease-in-out .1s}',
            $prefixer->prefixForTargets('.foo { transition-duration: 200ms; transition-delay: 100ms; transition-timing-function: ease-in-out; transition-property: opacity; }', ['safari' => 6])
        );
        $t->same(
            '.foo{transition:opacity .2s ease-in-out .1s}',
            $prefixer->prefixForTargets('.foo { transition-duration: 200ms; transition-delay: 100ms; transition-timing-function: ease-in-out; transition-property: opacity; }', ['safari' => 7])
        );
    },
    'transition prefixer maps upstream transform family target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-transform:scale(.5);-moz-transform:scale(.5);transform:scale(.5)}',
            $prefixer->prefixForTargets('.foo { transform: scale(0.5); }', ['firefox' => 6, 'safari' => 6])
        );
        $t->same(
            '.foo{-webkit-transform:var(--transform);-moz-transform:var(--transform);transform:var(--transform)}',
            $prefixer->prefixForTargets('.foo { transform: var(--transform); }', ['firefox' => 6, 'safari' => 6])
        );
        $t->same(
            '.foo{-webkit-transform-origin:0 0;-ms-transform-origin:0 0;-o-transform-origin:0 0;transform-origin:0 0}',
            $prefixer->prefixForTargets('.foo { transform-origin: 0 0; }', ['chrome' => 35, 'ie' => 9, 'opera' => 12])
        );
        $t->same(
            '.foo{transform-origin:0 0}',
            $prefixer->prefixForTargets('.foo { -webkit-transform-origin: 0 0; -ms-transform-origin: 0 0; -o-transform-origin: 0 0; transform-origin: 0 0; }', ['chrome' => 36, 'ie' => 10, 'opera' => 13])
        );
        $t->same(
            '.foo{-webkit-perspective:400px;-moz-perspective:400px;perspective:400px;-webkit-perspective-origin:0 0;-moz-perspective-origin:0 0;perspective-origin:0 0;-webkit-transform-style:preserve-3d;-moz-transform-style:preserve-3d;transform-style:preserve-3d}',
            $prefixer->prefixForTargets('.foo { perspective: 400px; perspective-origin: 0 0; transform-style: preserve-3d; }', ['chrome' => 35, 'firefox' => 15])
        );
        $t->same(
            '.foo{perspective:400px;perspective-origin:0 0;transform-style:preserve-3d}',
            $prefixer->prefixForTargets('.foo { -webkit-perspective: 400px; -moz-perspective: 400px; perspective: 400px; -webkit-perspective-origin: 0 0; -moz-perspective-origin: 0 0; perspective-origin: 0 0; -webkit-transform-style: preserve-3d; -moz-transform-style: preserve-3d; transform-style: preserve-3d; }', ['chrome' => 36, 'firefox' => 16])
        );
    },
    'transition prefixer maps upstream transform browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $webkitTransform = '.foo{-webkit-transform:scale(.5);transform:scale(.5)}';
        $modernTransform = '.foo{transform:scale(.5)}';

        $t->same($webkitTransform, $prefixer->prefixForTargets('.foo { transform: scale(0.5); }', ['chrome' => 35]));
        $t->same($modernTransform, $prefixer->prefixForTargets('.foo { transform: scale(0.5); }', ['chrome' => 36]));
        $t->same('.foo{-moz-transform:scale(.5);transform:scale(.5)}', $prefixer->prefixForTargets('.foo { transform: scale(0.5); }', ['firefox' => 15]));
        $t->same($modernTransform, $prefixer->prefixForTargets('.foo { transform: scale(0.5); }', ['firefox' => 16]));
        $t->same('.foo{-ms-transform:scale(.5);transform:scale(.5)}', $prefixer->prefixForTargets('.foo { transform: scale(0.5); }', ['ie' => 9]));
        $t->same($modernTransform, $prefixer->prefixForTargets('.foo { transform: scale(0.5); }', ['ie' => 10]));
        $t->same('.foo{-o-transform:scale(.5);transform:scale(.5)}', $prefixer->prefixForTargets('.foo { transform: scale(0.5); }', ['opera' => 12]));
        $t->same($modernTransform, $prefixer->prefixForTargets('.foo { transform: scale(0.5); }', ['opera' => 13]));

        $t->same('.foo{-webkit-perspective:400px;perspective:400px}', $prefixer->prefixForTargets('.foo { perspective: 400px; }', ['chrome' => 35]));
        $t->same('.foo{perspective:400px}', $prefixer->prefixForTargets('.foo { perspective: 400px; }', ['chrome' => 36]));
        $t->same('.foo{-moz-transform-style:preserve-3d;transform-style:preserve-3d}', $prefixer->prefixForTargets('.foo { transform-style: preserve-3d; }', ['firefox' => 15]));
        $t->same('.foo{transform-style:preserve-3d}', $prefixer->prefixForTargets('.foo { transform-style: preserve-3d; }', ['firefox' => 16]));
        $t->same('.foo{-webkit-backface-visibility:hidden;backface-visibility:hidden}', $prefixer->prefixForTargets('.foo { backface-visibility: hidden; }', ['safari' => '15.2']));
        $t->same('.foo{backface-visibility:hidden}', $prefixer->prefixForTargets('.foo { backface-visibility: hidden; }', ['safari' => '15.3']));
        $t->same('.foo{-moz-backface-visibility:hidden;backface-visibility:hidden}', $prefixer->prefixForTargets('.foo { backface-visibility: hidden; }', ['firefox' => 15]));
        $t->same('.foo{backface-visibility:hidden}', $prefixer->prefixForTargets('.foo { backface-visibility: hidden; }', ['firefox' => 16]));
    },
    'transition prefixer maps upstream selector target prefix browser boundaries' => static function (TestRunner $t) use ($rtlLangs): void {
        $prefixer = new TransitionPrefixer();
        $rtlLangList = 'ae,ar,arc,bcc,bqi,ckb,dv,fa,glk,he,ku,mzn,nqo,pnb,ps,sd,ug,ur,yi';

        $t->same(
            '.test:not(:-webkit-any(.foo,.bar)){color:red}.test:not(:is(.foo,.bar)){color:red}',
            $prefixer->prefixForTargets('.test:not(.foo, .bar) { color: red; }', ['safari' => 8])
        );
        $t->same(
            '.test:not(.foo,.bar){color:red}',
            $prefixer->prefixForTargets('.test:not(.foo, .bar) { color: red; }', ['safari' => 11])
        );
        $t->same(
            'a:-webkit-any(.foo,.bar){color:red}a:-moz-any(.foo,.bar){color:red}a:is(.foo,.bar){color:red}',
            $prefixer->prefixForTargets('a:is(.foo, .bar) { color: red; }', ['safari' => 11, 'firefox' => 50])
        );
        $t->same(
            'a:is(.foo>.bar){color:red}',
            $prefixer->prefixForTargets('a:is(.foo > .bar) { color: red; }', ['safari' => 11, 'firefox' => 50])
        );
        $t->same(
            'a:-webkit-any(:lang(en),:lang(fr)){color:red}a:-moz-any(:lang(en),:lang(fr)){color:red}a:is(:lang(en),:lang(fr)){color:red}',
            $prefixer->prefixForTargets('a:lang(en, fr) { color: red; }', ['safari' => 11, 'firefox' => 50])
        );
        $t->same(
            'a:is(:lang(en),:lang(fr)){color:red}',
            $prefixer->prefixForTargets('a:lang(en, fr) { color: red; }', ['safari' => 14, 'firefox' => 88])
        );
        $t->same(
            'a:lang(en,fr){color:red}',
            $prefixer->prefixForTargets('a:lang(en, fr) { color: red; }', ['safari' => 14])
        );
        $t->same(
            'a:-webkit-any(' . $rtlLangs . '){color:red}a:-moz-any(' . $rtlLangs . '){color:red}a:is(' . $rtlLangs . '){color:red}',
            $prefixer->prefixForTargets('a:dir(rtl) { color: red; }', ['safari' => 11, 'firefox' => 50])
        );
        $t->same(
            'a:not(:-webkit-any(' . $rtlLangs . ')){color:red}a:not(:-moz-any(' . $rtlLangs . ')){color:red}a:not(:is(' . $rtlLangs . ')){color:red}',
            $prefixer->prefixForTargets('a:dir(ltr) { color: red; }', ['safari' => 11, 'firefox' => 50])
        );
        $t->same(
            'a:is(' . $rtlLangs . '){color:red}',
            $prefixer->prefixForTargets('a:dir(rtl) { color: red; }', ['safari' => 14, 'firefox' => 88])
        );
        $t->same(
            'a:not(' . $rtlLangs . '){color:red}',
            $prefixer->prefixForTargets('a:dir(ltr) { color: red; }', ['safari' => 14, 'firefox' => 88])
        );
        $t->same(
            'a:lang(' . $rtlLangList . '){color:red}',
            $prefixer->prefixForTargets('a:dir(rtl) { color: red; }', ['safari' => 14])
        );
        $t->same(
            'a:not(:lang(' . $rtlLangList . ')){color:red}',
            $prefixer->prefixForTargets('a:dir(ltr) { color: red; }', ['safari' => 14])
        );
        $t->same(
            'a:lang(' . $rtlLangList . '){color:red}',
            $prefixer->prefixForTargets('a:is(:dir(rtl)) { color: red; }', ['safari' => 14])
        );
        $t->same(
            'a:where(:lang(' . $rtlLangList . ')){color:red}',
            $prefixer->prefixForTargets('a:where(:dir(rtl)) { color: red; }', ['safari' => 14])
        );
        $t->same(
            'a:has(:lang(' . $rtlLangList . ')){color:red}',
            $prefixer->prefixForTargets('a:has(:dir(rtl)) { color: red; }', ['safari' => 14])
        );
        $t->same(
            'a:not(:lang(' . $rtlLangList . ')){color:red}',
            $prefixer->prefixForTargets('a:not(:dir(rtl)) { color: red; }', ['safari' => 14])
        );
        $t->same(
            'a:lang(' . $rtlLangList . '):after{color:red}',
            $prefixer->prefixForTargets('a:dir(rtl)::after { color: red; }', ['safari' => 14])
        );
        $t->same(
            'a:lang(' . $rtlLangList . ') div{color:red}',
            $prefixer->prefixForTargets('a:dir(rtl) div { color: red; }', ['safari' => 14])
        );
    },
    'transition prefixer isolates upstream unsupported focus selector lists by target boundary' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            ':hover{color:red}:focus-visible{color:red}',
            $prefixer->prefixForTargets(':hover, :focus-visible { color: red; }', ['safari' => 13])
        );
        $t->same(
            ':is(:hover,:focus-visible){color:red}',
            $prefixer->prefixForTargets(':hover, :focus-visible { color: red; }', ['safari' => 14])
        );
        $t->same(
            ':is(:hover,:focus-visible){color:red}',
            $prefixer->prefixForTargets(':hover, :focus-visible { color: red; }', ['safari' => '15.3'])
        );
        $t->same(
            ':hover,:focus-visible{color:red}',
            $prefixer->prefixForTargets(':hover, :focus-visible { color: red; }', ['safari' => '15.4'])
        );
        $t->same(
            ':focus-within{color:red}:focus-visible{color:red}',
            $prefixer->prefixForTargets(':focus-within, :focus-visible { color: red; }', ['safari' => 9])
        );
        $t->same(
            ':is(a:not(:hover),a:not(:focus-visible)){color:red}',
            $prefixer->prefixForTargets('a:not(:hover), a:not(:focus-visible) { color: red; }', ['safari' => 14])
        );
        $t->same(
            ':is(a:has(:hover),a:has(:focus-visible)){color:red}',
            $prefixer->prefixForTargets('a:has(:hover), a:has(:focus-visible) { color: red; }', ['safari' => 14])
        );
        $t->same(
            '.foo.foo:hover{color:red}.bar:focus-visible{color:red}',
            $prefixer->prefixForTargets('.foo.foo:hover, .bar:focus-visible { color: red; }', ['safari' => 14])
        );
        $t->same(
            'a:after:hover{color:red}a:after:focus-visible{color:red}',
            $prefixer->prefixForTargets('a::after:hover, a::after:focus-visible { color: red; }', ['safari' => 14])
        );
    },
    'transition prefixer composes upstream unsupported selector-list isolation with logical fallbacks' => static function (TestRunner $t) use ($variants): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            ':hover{padding-inline-start:2px;padding-inline-end:2px}:focus-visible{padding-inline-start:2px;padding-inline-end:2px}',
            $prefixer->prefixForTargets(':hover, :focus-visible { padding-inline: 2px; }', ['safari' => 13])
        );
        $t->same(
            ':is(:hover,:focus-visible){padding-inline-start:2px;padding-inline-end:2px}',
            $prefixer->prefixForTargets(':hover, :focus-visible { padding-inline: 2px; }', ['safari' => 14])
        );
        $t->same(
            ':is(:hover,:focus-visible){padding-inline:2px}',
            $prefixer->prefixForTargets(':hover, :focus-visible { padding-inline: 2px; }', ['safari' => 15])
        );

        $hover = $variants(':hover');
        $focus = $variants(':focus-visible');
        $directionalExpected = $hover['ltr-webkit'] . '{margin-left:2px}'
            . $hover['ltr-modern'] . '{margin-left:2px}'
            . $hover['rtl-webkit'] . '{margin-right:2px}'
            . $hover['rtl-modern'] . '{margin-right:2px}'
            . $focus['ltr-webkit'] . '{margin-left:2px}'
            . $focus['ltr-modern'] . '{margin-left:2px}'
            . $focus['rtl-webkit'] . '{margin-right:2px}'
            . $focus['rtl-modern'] . '{margin-right:2px}';

        $t->same(
            $directionalExpected,
            $prefixer->prefixForTargets(':hover, :focus-visible { margin-inline-start: 2px; }', ['safari' => 8])
        );
    },
    'transition prefixer maps upstream selector pseudo browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same('.foo::-moz-selection{color:red}.foo::selection{color:red}', $prefixer->prefixForTargets('.foo::selection { color: red; }', ['firefox' => 61]));
        $t->same('.foo::selection{color:red}', $prefixer->prefixForTargets('.foo::selection { color: red; }', ['firefox' => 62]));
        $t->same('input:-moz-placeholder-shown{color:red}input:placeholder-shown{color:red}', $prefixer->prefixForTargets('input:placeholder-shown { color: red; }', ['firefox' => 50]));
        $t->same('input:placeholder-shown{color:red}', $prefixer->prefixForTargets('input:placeholder-shown { color: red; }', ['firefox' => 51]));
        $t->same('input:placeholder-shown{color:red}', $prefixer->prefixForTargets('input:placeholder-shown { color: red; }', ['ie' => 9]));
        $t->same('input:-ms-placeholder-shown{color:red}input:placeholder-shown{color:red}', $prefixer->prefixForTargets('input:placeholder-shown { color: red; }', ['ie' => 10]));
        $t->same('input:-ms-placeholder-shown{color:red}input:placeholder-shown{color:red}', $prefixer->prefixForTargets('input:placeholder-shown { color: red; }', ['ie' => 11]));
        $t->same('.wp-block-search__input:-ms-placeholder-shown{opacity:.75}.wp-block-search__input:placeholder-shown{opacity:.75}', $prefixer->prefixForTargets('.wp-block-search__input:placeholder-shown { opacity: .75; }', ['ie' => 10]));
        $t->same('section:-webkit-full-screen{color:red}section:fullscreen{color:red}', $prefixer->prefixForTargets('section:fullscreen { color: red; }', ['chrome' => 70]));
        $t->same('section:fullscreen{color:red}', $prefixer->prefixForTargets('section:fullscreen { color: red; }', ['chrome' => 71]));
        $t->same('section:-moz-full-screen{color:red}section:fullscreen{color:red}', $prefixer->prefixForTargets('section:fullscreen { color: red; }', ['firefox' => 63]));
        $t->same('section:fullscreen{color:red}', $prefixer->prefixForTargets('section:fullscreen { color: red; }', ['firefox' => 64]));
        $t->same('section:-ms-fullscreen{color:red}section:fullscreen{color:red}', $prefixer->prefixForTargets('section:fullscreen { color: red; }', ['ie' => 11]));
        $t->same('section:-webkit-full-screen{color:red}section:fullscreen{color:red}', $prefixer->prefixForTargets('section:fullscreen { color: red; }', ['safari' => '16.3']));
        $t->same('section:fullscreen{color:red}', $prefixer->prefixForTargets('section:fullscreen { color: red; }', ['safari' => '16.4']));
        $t->same('section:-webkit-full-screen{color:red}section:fullscreen{color:red}', $prefixer->prefixForTargets('section:fullscreen { color: red; }', ['samsung' => '9.2']));
        $t->same('section:fullscreen{color:red}', $prefixer->prefixForTargets('section:fullscreen { color: red; }', ['samsung' => '9.3']));
        $t->same('dialog::-webkit-backdrop{background:#000}dialog::backdrop{background:#000}', $prefixer->prefixForTargets('dialog::backdrop { background: black; }', ['chrome' => 36]));
        $t->same('dialog::backdrop{background:#000}', $prefixer->prefixForTargets('dialog::backdrop { background: black; }', ['chrome' => 37]));
        $t->same('dialog::-ms-backdrop{background:#000}dialog::backdrop{background:#000}', $prefixer->prefixForTargets('dialog::backdrop { background: black; }', ['edge' => 18]));
        $t->same('input::-webkit-file-upload-button{color:red}input::file-selector-button{color:red}', $prefixer->prefixForTargets('input::file-selector-button { color: red; }', ['chrome' => 88]));
        $t->same('input::file-selector-button{color:red}', $prefixer->prefixForTargets('input::file-selector-button { color: red; }', ['chrome' => 89]));
        $t->same('input::-ms-browse{color:red}input::file-selector-button{color:red}', $prefixer->prefixForTargets('input::file-selector-button { color: red; }', ['edge' => 18]));
        $t->same('input:-webkit-autofill{color:red}input:autofill{color:red}', $prefixer->prefixForTargets('input:autofill { color: red; }', ['chrome' => 109]));
        $t->same('input:autofill{color:red}', $prefixer->prefixForTargets('input:autofill { color: red; }', ['chrome' => 110]));
        $t->same(
            ':-webkit-any(.foo:placeholder-shown .bar,.foo:-webkit-autofill .baz){color:red}:is(.foo:placeholder-shown .bar,.foo:autofill .baz){color:red}',
            $prefixer->prefixForTargets('.foo:placeholder-shown .bar, .foo:autofill .baz { color: red; }', ['chrome' => 109])
        );
        $t->same(
            '.foo:placeholder-shown .bar,.foo:autofill .baz{color:red}',
            $prefixer->prefixForTargets('.foo:placeholder-shown .bar, .foo:autofill .baz { color: red; }', ['chrome' => 110])
        );
        $t->same(
            '.foo:placeholder-shown .bar{color:red}.foo:-webkit-autofill .baz{color:red}.foo:autofill .baz{color:red}',
            $prefixer->prefixForTargets('.foo:placeholder-shown .bar, .foo:autofill .baz { color: red; }', ['chrome' => 64])
        );
        $t->same('input:-webkit-autofill{color:red}input:autofill{color:red}', $prefixer->prefixForTargets('input:autofill { color: red; }', ['safari' => '14.1']));
        $t->same('input:autofill{color:red}', $prefixer->prefixForTargets('input:autofill { color: red; }', ['safari' => '14.2']));
        $t->same('input:-moz-read-only{color:red}input:read-only{color:red}', $prefixer->prefixForTargets('input:read-only { color: red; }', ['firefox' => 77]));
        $t->same('input:read-only{color:red}', $prefixer->prefixForTargets('input:read-only { color: red; }', ['firefox' => 78]));
        $t->same('textarea:-moz-read-write{color:red}textarea:read-write{color:red}', $prefixer->prefixForTargets('textarea:read-write { color: red; }', ['firefox' => 77]));
        $t->same('a:-webkit-any-link{color:red}a:any-link{color:red}', $prefixer->prefixForTargets('a:any-link { color: red; }', ['chrome' => 64]));
        $t->same('a:any-link{color:red}', $prefixer->prefixForTargets('a:any-link { color: red; }', ['chrome' => 65]));
        $t->same('a:-moz-any-link{color:red}a:any-link{color:red}', $prefixer->prefixForTargets('a:any-link { color: red; }', ['firefox' => 49]));
        $t->same('a:any-link{color:red}', $prefixer->prefixForTargets('a:any-link { color: red; }', ['firefox' => 50]));
    },
    'transition prefixer prunes adjacent stale selector prefixes by browser boundary' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo:read-only{color:red}',
            $prefixer->prefixForTargets('.foo:-moz-read-only { color: red; } .foo:read-only { color: red; }', ['firefox' => 85])
        );
        $t->same(
            '.foo:-moz-read-only{color:red}.bar{color:#ff0}.foo:read-only{color:red}',
            $prefixer->prefixForTargets('.foo:-moz-read-only { color: red; } .bar { color: yellow; } .foo:read-only { color: red; }', ['firefox' => 85])
        );
        $t->same(
            '.foo:-moz-read-only{color:red}.foo:read-only{color:red}',
            $prefixer->prefixForTargets('.foo:-moz-read-only { color: red; } .foo:read-only { color: red; }', ['firefox' => 36])
        );
        $t->same(
            '.foo:fullscreen{color:red}',
            $prefixer->prefixForTargets('.foo:-webkit-full-screen { color: red; } .foo:-moz-full-screen { color: red; } .foo:-ms-fullscreen { color: red; } .foo:fullscreen { color: red; }', ['chrome' => 96])
        );
        $t->same(
            '.foo:-webkit-full-screen{color:red}.foo:-moz-full-screen{color:red}.foo:-ms-fullscreen{color:red}.foo:fullscreen{color:red}',
            $prefixer->prefixForTargets('.foo:-webkit-full-screen { color: red; } .foo:-moz-full-screen { color: red; } .foo:-ms-fullscreen { color: red; } .foo:fullscreen { color: red; }', ['chrome' => 45, 'firefox' => 45, 'ie' => 11])
        );
        $t->same(
            '.foo::file-selector-button{color:red}',
            $prefixer->prefixForTargets('.foo::-webkit-file-upload-button { color: red; } .foo::-ms-browse { color: red; } .foo::file-selector-button { color: red; }', ['chrome' => 89, 'edge' => 19])
        );
    },
    'transition prefixer maps upstream placeholder pseudo-element target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo::-webkit-input-placeholder{color:red}.foo::-moz-placeholder{color:red}.foo::-ms-input-placeholder{color:red}.foo::placeholder{color:red}',
            $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['chrome' => 45, 'firefox' => 45, 'ie' => 11])
        );
        $t->same(
            '.wp-block-search__input::-webkit-input-placeholder{color:var(--wp--preset--color--contrast)}.wp-block-search__input::placeholder{color:var(--wp--preset--color--contrast)}',
            $prefixer->prefixForTargets('.wp-block-search__input::placeholder { color: var(--wp--preset--color--contrast); }', ['chrome' => 56])
        );
        $t->same(
            '.wp-block-search__input::placeholder{color:var(--wp--preset--color--contrast)}',
            $prefixer->prefixForTargets('.wp-block-search__input::placeholder { color: var(--wp--preset--color--contrast); }', ['chrome' => 57])
        );
        $t->same('.foo::-moz-placeholder{color:red}.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['firefox' => 50]));
        $t->same('.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['firefox' => 51]));
        $t->same('.foo::-ms-input-placeholder{color:red}.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['edge' => 18]));
        $t->same('.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['edge' => 19]));
        $t->same('.foo::-webkit-input-placeholder{color:red}.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['safari' => 10]));
        $t->same('.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['safari' => 11]));
        $t->same('.foo::-webkit-input-placeholder{color:red}.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['android' => '4.4.3']));
        $t->same('.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['android' => '4.4.4']));
        $t->same('.foo::-webkit-input-placeholder{color:red}.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['ios_saf' => 10]));
        $t->same('.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['ios_saf' => 11]));
        $t->same('.foo::-webkit-input-placeholder{color:red}.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['opera' => 43]));
        $t->same('.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['opera' => 44]));
        $t->same('.foo::-webkit-input-placeholder{color:red}.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['samsung' => '6.2']));
        $t->same('.foo::placeholder{color:red}', $prefixer->prefixForTargets('.foo::placeholder { color: red; }', ['samsung' => '6.3']));
    },
    'transition prefixer maps upstream intrinsic sizing keyword target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{width:-webkit-min-content;width:-moz-min-content;width:min-content}',
            $prefixer->prefixForTargets('.foo { width: min-content; }', ['safari' => 8, 'firefox' => 4])
        );
        $t->same(
            '.foo{height:-webkit-max-content;height:-moz-max-content;height:max-content}',
            $prefixer->prefixForTargets('.foo { height: max-content; }', ['safari' => 8, 'firefox' => 4])
        );
        $t->same(
            '.foo{width:-webkit-fit-content;width:-moz-fit-content;width:fit-content}',
            $prefixer->prefixForTargets('.foo { inline-size: fit-content; }', ['safari' => 8, 'firefox' => 4])
        );
        $t->same(
            '.foo{height:-webkit-fill-available;height:-moz-available;height:stretch}',
            $prefixer->prefixForTargets('.foo { block-size: stretch; }', ['safari' => 8, 'firefox' => 4])
        );
        $t->same(
            '.foo{width:fit-content(50%)}',
            $prefixer->prefixForTargets('.foo { inline-size: fit-content(50%); }', ['safari' => 8, 'firefox' => 4])
        );
        $t->same(
            '.foo{width:fill-available;height:fill}',
            $prefixer->prefixForTargets('.foo { width: fill-available; height: fill; }', ['safari' => 8, 'firefox' => 4])
        );
        $t->same(
            '.foo{width:100%;width:max-content;height:var(--fallback);height:fit-content}',
            $prefixer->prefixForTargets('.foo { width: 100%; width: max-content; height: var(--fallback); height: fit-content; }', ['safari' => 8, 'firefox' => 4])
        );
        $t->same(
            '.foo{width:-moz-min-content;width:min-content}',
            $prefixer->prefixForTargets('.foo { width: -webkit-min-content; width: -moz-min-content; width: min-content; }', ['firefox' => 65])
        );
        $t->same(
            '.foo{width:min-content}',
            $prefixer->prefixForTargets('.foo { width: -webkit-min-content; width: -moz-min-content; width: min-content; }', ['chrome' => 46])
        );

        $t->same('.foo{width:-webkit-min-content;width:min-content}', $prefixer->prefixForTargets('.foo { width: min-content; }', ['chrome' => 45]));
        $t->same('.foo{width:min-content}', $prefixer->prefixForTargets('.foo { width: min-content; }', ['chrome' => 46]));
        $t->same('.foo{width:-moz-min-content;width:min-content}', $prefixer->prefixForTargets('.foo { width: min-content; }', ['firefox' => 65]));
        $t->same('.foo{width:min-content}', $prefixer->prefixForTargets('.foo { width: min-content; }', ['firefox' => 66]));
        $t->same('.foo{width:-moz-fit-content;width:fit-content}', $prefixer->prefixForTargets('.foo { width: fit-content; }', ['firefox' => 93]));
        $t->same('.foo{width:fit-content}', $prefixer->prefixForTargets('.foo { width: fit-content; }', ['firefox' => 94]));
        $t->same('.foo{width:-webkit-min-content;width:min-content}', $prefixer->prefixForTargets('.foo { width: min-content; }', ['safari' => '10.1']));
        $t->same('.foo{width:min-content}', $prefixer->prefixForTargets('.foo { width: min-content; }', ['safari' => 11]));
        $t->same('.foo{height:-webkit-fill-available;height:stretch}', $prefixer->prefixForTargets('.foo { height: stretch; }', ['chrome' => 137]));
        $t->same('.foo{height:stretch}', $prefixer->prefixForTargets('.foo { height: stretch; }', ['chrome' => 138]));
        $t->same('.foo{height:-webkit-fill-available;height:stretch}', $prefixer->prefixForTargets('.foo { height: stretch; }', ['edge' => 137]));
        $t->same('.foo{height:stretch}', $prefixer->prefixForTargets('.foo { height: stretch; }', ['edge' => 138]));
        $t->same('.foo{block-size:-moz-available;block-size:stretch}', $prefixer->prefixForTargets('.foo { block-size: stretch; }', ['firefox' => 120]));
        $t->same('.foo{block-size:-webkit-fill-available;block-size:stretch}', $prefixer->prefixForTargets('.foo { block-size: stretch; }', ['safari' => 16]));
    },
    'transition prefixer maps upstream logical size browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $legacyNumeric = '.foo{height:25px;min-height:25px;width:25px;min-width:25px}';
        $legacyVariable = '.foo{height:var(--size);min-height:var(--size);width:var(--size);min-width:var(--size)}';
        $modernVariable = '.foo{block-size:var(--size);inline-size:var(--size);min-block-size:var(--size);min-inline-size:var(--size)}';
        $variableSource = '.foo { block-size: var(--size); inline-size: var(--size); min-block-size: var(--size); min-inline-size: var(--size); }';

        $t->same(
            $legacyNumeric,
            $prefixer->prefixForTargets('.foo { block-size: 25px; inline-size: 25px; min-block-size: 25px; min-inline-size: 25px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{block-size:25px;min-block-size:25px;inline-size:25px;min-inline-size:25px}',
            $prefixer->prefixForTargets('.foo { block-size: 25px; min-block-size: 25px; inline-size: 25px; min-inline-size: 25px; }', ['safari' => 14])
        );
        $t->same($legacyVariable, $prefixer->prefixForTargets($variableSource, ['safari' => 8]));
        $t->same($legacyVariable, $prefixer->prefixForTargets($variableSource, ['safari' => 12]));
        $t->same($modernVariable, $prefixer->prefixForTargets($variableSource, ['safari' => '12.1']));
        $t->same($legacyVariable, $prefixer->prefixForTargets($variableSource, ['ios_saf' => '12.1']));
        $t->same($modernVariable, $prefixer->prefixForTargets($variableSource, ['ios_saf' => '12.2']));
        $t->same($legacyVariable, $prefixer->prefixForTargets($variableSource, ['opera' => 42]));
        $t->same($modernVariable, $prefixer->prefixForTargets($variableSource, ['opera' => 43]));
        $t->same($legacyVariable, $prefixer->prefixForTargets($variableSource, ['chrome' => 56]));
        $t->same($modernVariable, $prefixer->prefixForTargets($variableSource, ['chrome' => 57]));
        $t->same($legacyVariable, $prefixer->prefixForTargets($variableSource, ['firefox' => 40]));
        $t->same($modernVariable, $prefixer->prefixForTargets($variableSource, ['firefox' => 41]));
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
    'transition prefixer maps upstream length max and cqw target fallback boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $properties = [
            'margin-right',
            'margin',
            'padding-right',
            'padding',
            'width',
            'height',
            'min-height',
            'max-height',
            'line-height',
            'border-radius',
        ];

        foreach ($properties as $property) {
            $t->same(
                '.foo{' . $property . ':22px;' . $property . ':max(4%,22px)}',
                $prefixer->prefixForTargets('.foo { ' . $property . ': 22px; ' . $property . ': max(4%, 22px); }', ['safari' => 10])
            );
            $t->same(
                '.foo{' . $property . ':max(4%,22px)}',
                $prefixer->prefixForTargets('.foo { ' . $property . ': 22px; ' . $property . ': max(4%, 22px); }', ['safari' => 14])
            );
            $t->same(
                '.foo{' . $property . ':22px;' . $property . ':max(2cqw,22px)}',
                $prefixer->prefixForTargets('.foo { ' . $property . ': 22px; ' . $property . ': max(2cqw, 22px); }', ['safari' => 14])
            );
            $t->same(
                '.foo{' . $property . ':max(2cqw,22px)}',
                $prefixer->prefixForTargets('.foo { ' . $property . ': 22px; ' . $property . ': max(2cqw, 22px); }', ['safari' => 16])
            );
        }
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
    'transition prefixer maps upstream alpha color target fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{color:transparent}',
            $prefixer->prefixForTargets('.foo { color: rgba(0, 0, 0, 0); }', ['chrome' => 61])
        );
        $t->same(
            '.foo{color:transparent}',
            $prefixer->prefixForTargets('.foo { color: #0000; }', ['chrome' => 61])
        );
        $t->same(
            '.foo{color:transparent}',
            $prefixer->prefixForTargets('.foo { color: transparent; }', ['chrome' => 61])
        );
        $t->same(
            '.foo{color:rgba(255,0,0,0)}',
            $prefixer->prefixForTargets('.foo { color: rgba(255, 0, 0, 0); }', ['chrome' => 61])
        );
        $t->same(
            '.foo{color:#f000}',
            $prefixer->prefixForTargets('.foo { color: rgba(255, 0, 0, 0); }', ['chrome' => 62])
        );
        $t->same(
            '.foo{color:#7bffff80}',
            $prefixer->prefixForTargets('.foo { color: rgba(123, 456, 789, 0.5); }', ['chrome' => 95])
        );
        $t->same(
            '.foo{color:rgba(123,255,255,.5)}',
            $prefixer->prefixForTargets('.foo { color: rgba(123, 255, 255, 0.5); }', ['ie' => 11])
        );
        $t->same(
            '.foo{color:rgba(123,255,255,.5)}',
            $prefixer->prefixForTargets('.foo { color: #7bffff80; }', ['ie' => 11])
        );
        $t->same(
            '.foo{color:rgba(123,255,255,.5)}',
            $prefixer->prefixForTargets('.foo { color: rgba(123, 456, 789, 0.5); }', [
                'firefox' => 48,
                'safari' => 10,
                'ios_saf' => 9,
            ])
        );
        $t->same(
            '.foo{color:#7bffff80}',
            $prefixer->prefixForTargets('.foo { color: rgba(123, 456, 789, 0.5); }', [
                'firefox' => 49,
                'safari' => 10,
                'ios_saf' => 10,
            ])
        );
    },
    'transition prefixer maps upstream custom property rgb variable alpha fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = <<<'CSS'
.foo {
  --a: rgb(0 0 0 / var(--alpha));
  --b: rgb(50% 50% 50% / var(--alpha));
  --c: rgb(var(--x) 0 0);
  --d: rgb(0 var(--x) 0);
  --e: rgb(0 0 var(--x));
  --f: rgb(var(--x) 0 0 / var(--alpha));
  --g: rgb(0 var(--x) 0 / var(--alpha));
  --h: rgb(0 0 var(--x) / var(--alpha));
  --i: rgb(none 0 0 / var(--alpha));
  --j: rgb(from yellow r g b / var(--alpha));
}
CSS;

        $t->same(
            '.foo{--a:rgba(0,0,0,var(--alpha));--b:rgba(128,128,128,var(--alpha));--c:rgb(var(--x) 0 0);--d:rgb(0 var(--x) 0);--e:rgb(0 0 var(--x));--f:rgb(var(--x) 0 0/var(--alpha));--g:rgb(0 var(--x) 0/var(--alpha));--h:rgb(0 0 var(--x)/var(--alpha));--i:rgb(none 0 0/var(--alpha));--j:rgba(255,255,0,var(--alpha))}',
            $prefixer->prefixForTargets($css, ['safari' => 11])
        );
        $t->same(
            '.foo{--a:rgb(0 0 0/var(--alpha));--b:rgb(128 128 128/var(--alpha));--c:rgb(var(--x) 0 0);--d:rgb(0 var(--x) 0);--e:rgb(0 0 var(--x));--f:rgb(var(--x) 0 0/var(--alpha));--g:rgb(0 var(--x) 0/var(--alpha));--h:rgb(0 0 var(--x)/var(--alpha));--i:rgb(none 0 0/var(--alpha));--j:rgb(255 255 0/var(--alpha))}',
            $prefixer->prefixForTargets($css, ['safari' => 13])
        );
    },
    'transition prefixer maps upstream custom property hsl variable alpha fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $legacyCss = <<<'CSS'
.foo {
  --a: hsl(270 100% 50% / var(--alpha));
  --b: hsl(var(--x) 0 0);
  --c: hsl(0 var(--x) 0);
  --d: hsl(0 0 var(--x));
  --e: hsl(var(--x) 0 0 / var(--alpha));
  --f: hsl(0 var(--x) 0 / var(--alpha));
  --g: hsl(0 0 var(--x) / var(--alpha));
  --h: hsl(270 100% 50% / calc(var(--alpha) / 2));
  --i: hsl(none 100% 50% / var(--alpha));
  --j: hsl(from yellow h s l / var(--alpha));
}
CSS;

        $modernCss = <<<'CSS'
.foo {
  --a: hsl(270 100% 50% / var(--alpha));
  --b: hsl(var(--x) 0 0);
  --c: hsl(0 var(--x) 0);
  --d: hsl(0 0 var(--x));
  --e: hsl(var(--x) 0 0 / var(--alpha));
  --f: hsl(0 var(--x) 0 / var(--alpha));
  --g: hsl(0 0 var(--x) / var(--alpha));
  --h: hsl(270 100% 50% / calc(var(--alpha) / 2));
  --i: hsl(none 100% 50% / var(--alpha));
}
CSS;

        $t->same(
            '.foo{--a:hsla(270,100%,50%,var(--alpha));--b:hsl(var(--x) 0 0);--c:hsl(0 var(--x) 0);--d:hsl(0 0 var(--x));--e:hsl(var(--x) 0 0/var(--alpha));--f:hsl(0 var(--x) 0/var(--alpha));--g:hsl(0 0 var(--x)/var(--alpha));--h:hsla(270,100%,50%,calc(var(--alpha)/2));--i:hsl(none 100% 50%/var(--alpha));--j:hsla(60,100%,50%,var(--alpha))}',
            $prefixer->prefixForTargets($legacyCss, ['safari' => 11])
        );
        $t->same(
            '.foo{--a:hsl(270 100% 50%/var(--alpha));--b:hsl(var(--x) 0 0);--c:hsl(0 var(--x) 0);--d:hsl(0 0 var(--x));--e:hsl(var(--x) 0 0/var(--alpha));--f:hsl(0 var(--x) 0/var(--alpha));--g:hsl(0 0 var(--x)/var(--alpha));--h:hsl(270 100% 50%/calc(var(--alpha)/2));--i:hsl(none 100% 50%/var(--alpha))}',
            $prefixer->prefixForTargets($modernCss, ['safari' => 13])
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
    'transition prefixer maps upstream font target fallback boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $systemFallback = 'system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Noto Sans,Ubuntu,Cantarell,Helvetica Neue';

        $t->same(
            '.foo{font-family:Helvetica,' . $systemFallback . ',sans-serif}',
            $prefixer->prefixForTargets('.foo { font-family: Helvetica, system-ui, sans-serif; }', ['safari' => 8])
        );
        $t->same(
            '.foo{font:100%/1.5 Helvetica,' . $systemFallback . ',sans-serif}',
            $prefixer->prefixForTargets('.foo { font: 100%/1.5 Helvetica, system-ui, sans-serif; }', ['safari' => 8])
        );
        $t->same(
            '.foo{font-family:ui-sans-serif,' . $systemFallback . ',Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji}',
            $prefixer->prefixForTargets(
                '.foo { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"; }',
                ['firefox' => 91]
            )
        );

        $t->same(
            '.foo{font-size:22px;font-size:max(2cqw,22px)}',
            $prefixer->prefixForTargets('.foo { font-size: 22px; font-size: max(2cqw, 22px); }', ['safari' => 14])
        );
        $t->same(
            '.foo{font-size:max(2cqw,22px)}',
            $prefixer->prefixForTargets('.foo { font-size: 22px; font-size: max(2cqw, 22px); }', ['safari' => 16])
        );
        $t->same(
            '.foo{font-size:22px;font-size:xxx-large}',
            $prefixer->prefixForTargets('.foo { font-size: 22px; font-size: xxx-large; }', ['chrome' => 70])
        );
        $t->same(
            '.foo{font-size:xxx-large}',
            $prefixer->prefixForTargets('.foo { font-size: 22px; font-size: xxx-large; }', ['chrome' => 80])
        );
        $t->same(
            '.foo{font-weight:700;font-weight:789}',
            $prefixer->prefixForTargets('.foo { font-weight: 700; font-weight: 789; }', ['chrome' => 60])
        );
        $t->same(
            '.foo{font-weight:789}',
            $prefixer->prefixForTargets('.foo { font-weight: 700; font-weight: 789; }', ['chrome' => 80])
        );
        $t->same(
            '.foo{font-family:Helvetica;font-family:system-ui}',
            $prefixer->prefixForTargets('.foo { font-family: Helvetica; font-family: system-ui; }', ['chrome' => 50])
        );
        $t->same(
            '.foo{font-family:system-ui}',
            $prefixer->prefixForTargets('.foo { font-family: Helvetica; font-family: system-ui; }', ['chrome' => 80])
        );
        $t->same(
            '.foo{font-style:oblique;font-style:oblique 40deg}',
            $prefixer->prefixForTargets('.foo { font-style: oblique; font-style: oblique 40deg; }', ['firefox' => 50])
        );
        $t->same(
            '.foo{font-style:oblique 40deg}',
            $prefixer->prefixForTargets('.foo { font-style: oblique; font-style: oblique 40deg; }', ['firefox' => 80])
        );
        $t->same(
            '.foo{font:22px Helvetica;font:oblique 40deg 22px Helvetica}',
            $prefixer->prefixForTargets('.foo { font: 22px Helvetica; font: oblique 40deg 22px Helvetica; }', ['firefox' => 50])
        );
        $t->same(
            '.foo{font:oblique 40deg 22px Helvetica}',
            $prefixer->prefixForTargets('.foo { font: 22px Helvetica; font: oblique 40deg 22px Helvetica; }', ['firefox' => 80])
        );
        $t->same(
            '.foo{font:22px Helvetica;font:oblique 40deg xxx-large Helvetica}',
            $prefixer->prefixForTargets('.foo { font: 22px Helvetica; font: oblique 40deg xxx-large Helvetica; }', ['firefox' => 50, 'chrome' => 80])
        );
        $t->same(
            '.foo{font:oblique 40deg xxx-large Helvetica}',
            $prefixer->prefixForTargets('.foo { font: 22px Helvetica; font: oblique 40deg xxx-large Helvetica; }', ['firefox' => 80, 'chrome' => 80])
        );
        $t->same(
            '.foo{font:oblique 40deg 22px ' . $systemFallback . ',sans-serif}',
            $prefixer->prefixForTargets('.foo { font: oblique 40deg 22px system-ui, sans-serif; }', ['safari' => 8])
        );
        $t->same(
            '.foo{font:22px Helvetica;font:xxx-large system-ui}',
            $prefixer->prefixForTargets('.foo { font: 22px Helvetica; font: xxx-large system-ui; }', ['chrome' => 70])
        );
        $t->same(
            '.foo{font:xxx-large system-ui}',
            $prefixer->prefixForTargets('.foo { font: 22px Helvetica; font: xxx-large system-ui; }', ['chrome' => 80])
        );
        $t->same(
            '.foo{font:var(--fallback);font:xxx-large system-ui}',
            $prefixer->prefixForTargets('.foo { font: var(--fallback); font: xxx-large system-ui; }', ['chrome' => 50])
        );
    },
    'transition prefixer maps upstream font typography browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-font-feature-settings:"kern";-moz-font-feature-settings:"kern";font-feature-settings:"kern"}',
            $prefixer->prefixForTargets('.foo { font-feature-settings: "kern"; }', ['chrome' => 47, 'firefox' => 33])
        );
        $t->same(
            '.foo{font-feature-settings:"kern"}',
            $prefixer->prefixForTargets('.foo { font-feature-settings: "kern"; }', ['chrome' => 48, 'firefox' => 34])
        );
        $t->same(
            '.foo{-webkit-font-feature-settings:"kern";font-feature-settings:"kern"}',
            $prefixer->prefixForTargets('.foo { -webkit-font-feature-settings: "kern"; -moz-font-feature-settings: "kern"; font-feature-settings: "kern"; }', ['chrome' => 47, 'firefox' => 34])
        );
        $t->same(
            '.foo{-moz-font-feature-settings:"kern";font-feature-settings:"kern"}',
            $prefixer->prefixForTargets('.foo { -webkit-font-feature-settings: "kern"; -moz-font-feature-settings: "kern"; font-feature-settings: "kern"; }', ['chrome' => 48, 'firefox' => 33])
        );

        $t->same(
            '.foo{-webkit-font-variant-ligatures:no-common-ligatures;font-variant-ligatures:no-common-ligatures}',
            $prefixer->prefixForTargets('.foo { font-variant-ligatures: no-common-ligatures; }', ['android' => '4.4.3', 'samsung' => 4])
        );
        $t->same(
            '.foo{font-variant-ligatures:no-common-ligatures}',
            $prefixer->prefixForTargets('.foo { -webkit-font-variant-ligatures: no-common-ligatures; font-variant-ligatures: no-common-ligatures; }', ['android' => '4.4.4', 'samsung' => 5])
        );

        $t->same(
            '.foo{-webkit-font-language-override:"SRB";font-language-override:"SRB"}',
            $prefixer->prefixForTargets('.foo { font-language-override: "SRB"; }', ['opera' => 34])
        );
        $t->same(
            '.foo{font-language-override:"SRB"}',
            $prefixer->prefixForTargets('.foo { -webkit-font-language-override: "SRB"; font-language-override: "SRB"; }', ['opera' => 35])
        );
        $t->same(
            '.foo{-moz-font-language-override:"SRB";font-language-override:"SRB"}',
            $prefixer->prefixForTargets('.foo { font-language-override: "SRB"; }', ['firefox' => 33])
        );
        $t->same(
            '.foo{font-language-override:"SRB"}',
            $prefixer->prefixForTargets('.foo { -moz-font-language-override: "SRB"; font-language-override: "SRB"; }', ['firefox' => 34])
        );

        $t->same(
            '.foo{-webkit-font-kerning:normal;font-kerning:normal}',
            $prefixer->prefixForTargets('.foo { font-kerning: normal; }', ['safari' => 9, 'chrome' => 32])
        );
        $t->same(
            '.foo{font-kerning:normal}',
            $prefixer->prefixForTargets('.foo { -webkit-font-kerning: normal; font-kerning: normal; }', ['safari' => 10, 'chrome' => 33])
        );
        $t->same(
            '.foo{-webkit-font-kerning:normal;font-kerning:normal}',
            $prefixer->prefixForTargets('.foo { font-kerning: normal; }', ['ios_saf' => '11.3'])
        );
        $t->same(
            '.foo{font-kerning:normal}',
            $prefixer->prefixForTargets('.foo { -webkit-font-kerning: normal; font-kerning: normal; }', ['ios_saf' => '11.4'])
        );
        $t->same(
            '.foo{-webkit-font-kerning:normal;font-kerning:normal}',
            $prefixer->prefixForTargets('.foo { font-kerning: normal; }', ['opera' => 19])
        );
        $t->same(
            '.foo{font-kerning:normal}',
            $prefixer->prefixForTargets('.foo { -webkit-font-kerning: normal; font-kerning: normal; }', ['opera' => 20])
        );
        $t->same(
            '.foo{-webkit-font-kerning:normal;font-kerning:normal}',
            $prefixer->prefixForTargets('.foo { font-kerning: normal; }', ['android' => '4.4'])
        );
        $t->same(
            '.foo{font-kerning:normal}',
            $prefixer->prefixForTargets('.foo { -webkit-font-kerning: normal; font-kerning: normal; }', ['android' => '4.4.3'])
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
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['chrome' => 136])
        );
        $t->same(
            '.foo{-moz-print-color-adjust:exact;print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['firefox' => 96])
        );
        $t->same(
            '.foo{print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['firefox' => 97])
        );
        $t->same(
            '.foo{-webkit-print-color-adjust:exact;print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['safari' => '15.2'])
        );
        $t->same(
            '.foo{print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['safari' => '15.3'])
        );
        $t->same(
            '.foo{-webkit-print-color-adjust:exact;print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['samsung' => 28])
        );
        $t->same(
            '.foo{print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['samsung' => 29])
        );
        $t->same(
            '.foo{-webkit-print-color-adjust:exact;-moz-print-color-adjust:exact;print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['chrome' => 135, 'firefox' => 96])
        );
        $t->same(
            '.foo{print-color-adjust:exact}',
            $prefixer->prefixForTargets('.foo { print-color-adjust: exact; }', ['chrome' => 137])
        );
    },
    'transition prefixer maps upstream multi-column target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { columns: 12em; column-gap: 20px; column-rule: 1px solid black; column-count: 3; column-span: all; column-fill: balance; }';

        $t->same(
            '.foo{-webkit-columns:12em;-moz-columns:12em;columns:12em;-webkit-column-gap:20px;-moz-column-gap:20px;column-gap:20px;-webkit-column-rule:1px solid #000;-moz-column-rule:1px solid #000;column-rule:1px solid #000;-webkit-column-count:3;-moz-column-count:3;column-count:3;-webkit-column-span:all;-moz-column-span:all;column-span:all;-webkit-column-fill:balance;-moz-column-fill:balance;column-fill:balance}',
            $prefixer->prefixForTargets($css, ['chrome' => 49, 'firefox' => 51])
        );
        $t->same(
            '.foo{columns:12em;column-gap:20px}',
            $prefixer->prefixForTargets('.foo { -webkit-columns: 12em; -moz-columns: 12em; columns: 12em; -webkit-column-gap: 20px; -moz-column-gap: 20px; column-gap: 20px; }', ['chrome' => 50, 'firefox' => 52])
        );
        $t->same(
            '.foo{-webkit-columns:12em;columns:12em}',
            $prefixer->prefixForTargets('.foo { -webkit-columns: 12em; -moz-columns: 12em; columns: 12em; }', ['chrome' => 49])
        );
        $t->same(
            '.foo{-moz-columns:12em;columns:12em}',
            $prefixer->prefixForTargets('.foo { -webkit-columns: 12em; -moz-columns: 12em; columns: 12em; }', ['firefox' => 51])
        );
    },
    'transition prefixer maps upstream multi-column browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { columns: 12em; column-gap: 20px; }';
        $webkit = '.foo{-webkit-columns:12em;columns:12em;-webkit-column-gap:20px;column-gap:20px}';
        $moz = '.foo{-moz-columns:12em;columns:12em;-moz-column-gap:20px;column-gap:20px}';
        $modern = '.foo{columns:12em;column-gap:20px}';

        $t->same($webkit, $prefixer->prefixForTargets($css, ['chrome' => 49]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['chrome' => 50]));
        $t->same($moz, $prefixer->prefixForTargets($css, ['firefox' => 51]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['firefox' => 52]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['safari' => 8]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['safari' => 9]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['opera' => 36]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['opera' => 37]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['android' => '4.4.3']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['android' => '4.4.4']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['ios_saf' => '8.1']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ios_saf' => '8.2']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['samsung' => 4]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['samsung' => 5]));
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
    'transition prefixer maps upstream unicode-bidi value browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{unicode-bidi:-webkit-isolate;unicode-bidi:-moz-isolate;unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate; }', ['chrome' => 47, 'firefox' => 49])
        );
        $t->same(
            '.foo{unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate; }', ['chrome' => 48, 'firefox' => 50])
        );
        $t->same(
            '.foo{unicode-bidi:-webkit-isolate;unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate; }', ['safari' => '10.1'])
        );
        $t->same(
            '.foo{unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate; }', ['safari' => '10.2'])
        );
        $t->same(
            '.foo{unicode-bidi:-webkit-isolate;unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate; }', ['ios_saf' => '10.3'])
        );
        $t->same(
            '.foo{unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate; }', ['ios_saf' => '10.4'])
        );
        $t->same(
            '.foo{unicode-bidi:-webkit-isolate;unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate; }', ['opera' => 34])
        );
        $t->same(
            '.foo{unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate; }', ['opera' => 35])
        );
        $t->same(
            '.foo{unicode-bidi:-webkit-plaintext;unicode-bidi:-moz-plaintext;unicode-bidi:plaintext}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: plaintext; }', ['safari' => '10.1', 'firefox' => 49])
        );
        $t->same(
            '.foo{unicode-bidi:plaintext}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: plaintext; }', ['chrome' => 47])
        );
        $t->same(
            '.foo{unicode-bidi:-webkit-isolate-override;unicode-bidi:-moz-isolate-override;unicode-bidi:isolate-override}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate-override; }', ['safari' => 7, 'firefox' => 17])
        );
        $t->same(
            '.foo{unicode-bidi:isolate-override}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: isolate-override; }', ['safari' => 6, 'firefox' => 16])
        );
        $t->same(
            '.foo{unicode-bidi:-webkit-isolate;unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: -webkit-isolate; unicode-bidi: -moz-isolate; unicode-bidi: isolate; }', ['safari' => '10.1'])
        );
        $t->same(
            '.foo{unicode-bidi:isolate}',
            $prefixer->prefixForTargets('.foo { unicode-bidi: -webkit-isolate; unicode-bidi: -moz-isolate; unicode-bidi: isolate; }', ['chrome' => 48, 'firefox' => 50, 'safari' => 11])
        );
    },
    'transition prefixer maps upstream cursor value target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{cursor:-webkit-zoom-in;cursor:-moz-zoom-in;cursor:zoom-in;cursor:-webkit-zoom-out;cursor:-moz-zoom-out;cursor:zoom-out}',
            $prefixer->prefixForTargets('.foo { cursor: zoom-in; cursor: zoom-out; }', [
                'chrome' => 36,
                'firefox' => 23,
                'safari' => 8,
                'opera' => 23,
            ])
        );
        $t->same(
            '.foo{cursor:-webkit-zoom-in;cursor:zoom-in}',
            $prefixer->prefixForTargets('.foo { cursor: zoom-in; }', ['chrome' => 36])
        );
        $t->same(
            '.foo{cursor:zoom-in}',
            $prefixer->prefixForTargets('.foo { cursor: zoom-in; }', ['chrome' => 37])
        );
        $t->same(
            '.foo{cursor:-moz-zoom-out;cursor:zoom-out}',
            $prefixer->prefixForTargets('.foo { cursor: zoom-out; }', ['firefox' => 23])
        );
        $t->same(
            '.foo{cursor:zoom-out}',
            $prefixer->prefixForTargets('.foo { cursor: zoom-out; }', ['firefox' => 24])
        );
        $t->same(
            '.foo{cursor:-webkit-zoom-in;cursor:zoom-in}',
            $prefixer->prefixForTargets('.foo { cursor: zoom-in; }', ['safari' => 8])
        );
        $t->same(
            '.foo{cursor:zoom-in}',
            $prefixer->prefixForTargets('.foo { cursor: zoom-in; }', ['safari' => 9])
        );
        $t->same(
            '.foo{cursor:-webkit-zoom-out;cursor:zoom-out}',
            $prefixer->prefixForTargets('.foo { cursor: zoom-out; }', ['opera' => 23])
        );
        $t->same(
            '.foo{cursor:zoom-out}',
            $prefixer->prefixForTargets('.foo { cursor: zoom-out; }', ['opera' => 24])
        );
        $t->same(
            '.foo{cursor:-webkit-grab;cursor:-moz-grab;cursor:grab;cursor:-webkit-grabbing;cursor:-moz-grabbing;cursor:grabbing}',
            $prefixer->prefixForTargets('.foo { cursor: grab; cursor: grabbing; }', [
                'chrome' => 67,
                'firefox' => 25,
                'safari' => 10,
                'opera' => 54,
            ])
        );
        $t->same(
            '.foo{cursor:-webkit-grab;cursor:grab}',
            $prefixer->prefixForTargets('.foo { cursor: grab; }', ['chrome' => 67])
        );
        $t->same(
            '.foo{cursor:grab}',
            $prefixer->prefixForTargets('.foo { cursor: grab; }', ['chrome' => 68])
        );
        $t->same(
            '.foo{cursor:-moz-grabbing;cursor:grabbing}',
            $prefixer->prefixForTargets('.foo { cursor: grabbing; }', ['firefox' => 25])
        );
        $t->same(
            '.foo{cursor:grabbing}',
            $prefixer->prefixForTargets('.foo { cursor: grabbing; }', ['firefox' => 26])
        );
        $t->same(
            '.foo{cursor:-webkit-grab;cursor:grab}',
            $prefixer->prefixForTargets('.foo { cursor: grab; }', ['safari' => 10])
        );
        $t->same(
            '.foo{cursor:grab}',
            $prefixer->prefixForTargets('.foo { cursor: grab; }', ['safari' => 11])
        );
        $t->same(
            '.foo{cursor:-webkit-grabbing;cursor:grabbing}',
            $prefixer->prefixForTargets('.foo { cursor: grabbing; }', ['opera' => 54])
        );
        $t->same(
            '.foo{cursor:grabbing}',
            $prefixer->prefixForTargets('.foo { cursor: grabbing; }', ['opera' => 55])
        );
        $t->same(
            '.foo{cursor:url("hand.cur"),-webkit-grab;cursor:url("hand.cur"),grab}',
            $prefixer->prefixForTargets('.foo { cursor: url("hand.cur"), grab; }', ['safari' => 10])
        );
        $t->same(
            '.foo{cursor:-webkit-grab;cursor:grab}',
            $prefixer->prefixForTargets('.foo { cursor: -webkit-grab; cursor: grab; }', ['safari' => 10])
        );
        $t->same(
            '.foo{cursor:grab}',
            $prefixer->prefixForTargets('.foo { cursor: -webkit-grab; cursor: -moz-grab; cursor: grab; }', [
                'chrome' => 68,
                'firefox' => 26,
                'safari' => 11,
                'opera' => 55,
            ])
        );
        $t->same(
            '.foo{cursor:-webkit-grab}',
            $prefixer->prefixForTargets('.foo { cursor: -webkit-grab; }', ['chrome' => 68])
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
            '.foo{appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['ios_saf' => '3.1'])
        );
        $t->same(
            '.foo{-webkit-appearance:none;appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['ios_saf' => '3.2'])
        );
        $t->same(
            '.foo{-webkit-appearance:none;appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['safari' => 15])
        );
        $t->same(
            '.foo{-webkit-appearance:none;appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['safari' => '15.2'])
        );
        $t->same(
            '.foo{appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['safari' => '15.3'])
        );
        $t->same(
            '.foo{-webkit-appearance:none;appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['ios_saf' => '15.2'])
        );
        $t->same(
            '.foo{appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['ios_saf' => '15.3'])
        );
        $t->same(
            '.foo{appearance:none}',
            $prefixer->prefixForTargets('.foo { appearance: none; }', ['safari' => 16])
        );
    },
    'transition prefixer maps upstream box-sizing browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['chrome' => 9, 'firefox' => 28])
        );
        $t->same(
            '.foo{box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box; }', ['chrome' => 10, 'firefox' => 29])
        );
        $t->same(
            '.foo{-webkit-box-sizing:border-box;box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['chrome' => 9])
        );
        $t->same(
            '.foo{box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['chrome' => 10])
        );
        $t->same(
            '.foo{-moz-box-sizing:border-box;box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['firefox' => 28])
        );
        $t->same(
            '.foo{box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['firefox' => 29])
        );
        $t->same(
            '.foo{-webkit-box-sizing:border-box;box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['safari' => 5])
        );
        $t->same(
            '.foo{box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['safari' => 6])
        );
        $t->same(
            '.foo{-webkit-box-sizing:border-box;box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['ios_saf' => '4.2'])
        );
        $t->same(
            '.foo{box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['ios_saf' => '4.3'])
        );
        $t->same(
            '.foo{-webkit-box-sizing:border-box;box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['android' => 3])
        );
        $t->same(
            '.foo{box-sizing:border-box}',
            $prefixer->prefixForTargets('.foo { box-sizing: border-box; }', ['android' => 4])
        );
    },
    'transition prefixer maps upstream object-fit Opera browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{object-fit:cover}',
            $prefixer->prefixForTargets('.foo { object-fit: cover; }', ['opera' => '10.5'])
        );
        $t->same(
            '.foo{-o-object-fit:cover;object-fit:cover}',
            $prefixer->prefixForTargets('.foo { object-fit: cover; }', ['opera' => '10.6'])
        );
        $t->same(
            '.foo{-o-object-fit:cover;object-fit:cover}',
            $prefixer->prefixForTargets('.foo { object-fit: cover; }', ['opera' => '12.1'])
        );
        $t->same(
            '.foo{object-fit:cover}',
            $prefixer->prefixForTargets('.foo { object-fit: cover; }', ['opera' => 13])
        );
        $t->same(
            '.foo{-o-object-position:center top;object-position:center top}',
            $prefixer->prefixForTargets('.foo { object-position: center top; }', ['opera' => 12])
        );
        $t->same(
            '.foo{object-position:center top}',
            $prefixer->prefixForTargets('.foo { object-position: center top; }', ['opera' => 13])
        );
        $t->same(
            '.foo{object-fit:cover;object-position:center}',
            $prefixer->prefixForTargets('.foo { -o-object-fit: cover; object-fit: cover; -o-object-position: center; object-position: center; }', ['opera' => 13])
        );
        $t->same(
            '.foo{-o-object-fit:cover;object-fit:cover;-o-object-position:center;object-position:center}',
            $prefixer->prefixForTargets('.foo { -o-object-fit: cover; object-fit: cover; -o-object-position: center; object-position: center; }', ['opera' => 12])
        );
    },
    'transition prefixer maps upstream CSS Regions browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { flow-into: article; flow-from: article; region-fragment: break; }';
        $modern = '.foo{flow-into:article;flow-from:article;region-fragment:break}';
        $webkit = '.foo{-webkit-flow-into:article;flow-into:article;-webkit-flow-from:article;flow-from:article;-webkit-region-fragment:break;region-fragment:break}';
        $ms = '.foo{-ms-flow-into:article;flow-into:article;-ms-flow-from:article;flow-from:article;-ms-region-fragment:break;region-fragment:break}';
        $webkitMs = '.foo{-webkit-flow-into:article;-ms-flow-into:article;flow-into:article;-webkit-flow-from:article;-ms-flow-from:article;flow-from:article;-webkit-region-fragment:break;-ms-region-fragment:break;region-fragment:break}';
        $stalePrefixed = '.foo { -webkit-flow-into: article; -ms-flow-into: article; flow-into: article; -webkit-flow-from: article; -ms-flow-from: article; flow-from: article; -webkit-region-fragment: break; -ms-region-fragment: break; region-fragment: break; }';

        $t->same($webkitMs, $prefixer->prefixForTargets($css, ['chrome' => 18, 'ie' => 10]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['chrome' => 14]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['chrome' => 15]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['chrome' => 18]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['chrome' => 19]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['edge' => 11]));
        $t->same($ms, $prefixer->prefixForTargets($css, ['edge' => 12]));
        $t->same($ms, $prefixer->prefixForTargets($css, ['edge' => 18]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['edge' => 19]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ie' => 9]));
        $t->same($ms, $prefixer->prefixForTargets($css, ['ie' => 10]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['safari' => 6]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['safari' => '6.1']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['safari' => 11]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['safari' => 12]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ios_saf' => 6]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['ios_saf' => 7]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['ios_saf' => 11]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ios_saf' => 12]));
        $t->same($modern, $prefixer->prefixForTargets($stalePrefixed, ['chrome' => 19, 'edge' => 19, 'safari' => 12]));
        $t->same($webkit, $prefixer->prefixForTargets($stalePrefixed, ['safari' => 11]));
        $t->same($ms, $prefixer->prefixForTargets($stalePrefixed, ['ie' => 10]));
        $t->same($webkitMs, $prefixer->prefixForTargets($stalePrefixed, ['chrome' => 18, 'ie' => 10]));
    },
    'transition prefixer maps upstream border-image target boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { border-image: url(border.png) 30 fill / 10px / 4px round; }';
        $modern = '.foo{border-image:url(border.png) 30 fill/10px/4px round}';
        $webkit = '.foo{-webkit-border-image:url(border.png) 30 fill/10px/4px round;border-image:url(border.png) 30 fill/10px/4px round}';
        $moz = '.foo{-moz-border-image:url(border.png) 30 fill/10px/4px round;border-image:url(border.png) 30 fill/10px/4px round}';
        $opera = '.foo{-o-border-image:url(border.png) 30 fill/10px/4px round;border-image:url(border.png) 30 fill/10px/4px round}';
        $allLegacy = '.foo{-webkit-border-image:url(border.png) 30 fill/10px/4px round;-moz-border-image:url(border.png) 30 fill/10px/4px round;-o-border-image:url(border.png) 30 fill/10px/4px round;border-image:url(border.png) 30 fill/10px/4px round}';
        $stalePrefixed = '.foo { -webkit-border-image: url(border.png) 30 fill / 10px / 4px round; -moz-border-image: url(border.png) 30 fill / 10px / 4px round; -o-border-image: url(border.png) 30 fill / 10px / 4px round; border-image: url(border.png) 30 fill / 10px / 4px round; }';

        $t->same($allLegacy, $prefixer->prefixForTargets($css, ['chrome' => 14, 'firefox' => 14, 'opera' => '12.1']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['chrome' => 14]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['chrome' => 15]));
        $t->same($moz, $prefixer->prefixForTargets($css, ['firefox' => '3.5']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['firefox' => 3]));
        $t->same($moz, $prefixer->prefixForTargets($css, ['firefox' => 14]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['firefox' => 15]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['safari' => '5.1']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['safari' => 6]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['ios_saf' => 5]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ios_saf' => 6]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['android' => '4.2']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['android' => '4.3']));
        $t->same($opera, $prefixer->prefixForTargets($css, ['opera' => '12.1']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['opera' => '12.2']));
        $t->same($webkit, $prefixer->prefixForTargets($stalePrefixed, ['safari' => '5.1']));
        $t->same($modern, $prefixer->prefixForTargets($stalePrefixed, ['chrome' => 15, 'firefox' => 15, 'opera' => '12.2', 'safari' => 6]));
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
            '.foo{hyphens:manual}',
            $prefixer->prefixForTargets('.foo { hyphens: manual; }', ['ios_saf' => '4.1'])
        );
        $t->same(
            '.foo{-webkit-hyphens:manual;hyphens:manual}',
            $prefixer->prefixForTargets('.foo { hyphens: manual; }', ['ios_saf' => '4.2'])
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
            '.foo{tab-size:4}',
            $prefixer->prefixForTargets('.foo { tab-size: 4; }', ['opera' => '10.5'])
        );
        $t->same(
            '.foo{-o-tab-size:4;tab-size:4}',
            $prefixer->prefixForTargets('.foo { tab-size: 4; }', ['opera' => '10.6'])
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
            '.foo{-ms-touch-action:manipulation;touch-action:manipulation}',
            $prefixer->prefixForTargets('.foo { touch-action: manipulation; }', ['ie' => 10])
        );
        $t->same(
            '.foo{touch-action:manipulation}',
            $prefixer->prefixForTargets('.foo { touch-action: manipulation; }', ['ie' => 11])
        );
        $t->same(
            '.foo{touch-action:manipulation}',
            $prefixer->prefixForTargets('.foo { touch-action: manipulation; }', ['edge' => 18])
        );
        $t->same(
            '.foo{touch-action:manipulation}',
            $prefixer->prefixForTargets('.foo { -ms-touch-action: manipulation; touch-action: manipulation; }', ['ie' => 11])
        );
        $t->same(
            '.foo{text-orientation:upright}',
            $prefixer->prefixForTargets('.foo { text-orientation: upright; }', ['safari' => 10])
        );
        $t->same(
            '.foo{-webkit-text-orientation:upright;text-orientation:upright}',
            $prefixer->prefixForTargets('.foo { text-orientation: upright; }', ['safari' => '10.1'])
        );
        $t->same(
            '.foo{-webkit-text-orientation:upright;text-orientation:upright}',
            $prefixer->prefixForTargets('.foo { text-orientation: upright; }', ['safari' => '13.1'])
        );
        $t->same(
            '.foo{text-orientation:upright}',
            $prefixer->prefixForTargets('.foo { text-orientation: upright; }', ['safari' => '13.2'])
        );
        $t->same(
            '.foo{text-orientation:upright}',
            $prefixer->prefixForTargets('.foo { text-orientation: upright; }', ['ios_saf' => '13.1'])
        );
        $t->same(
            '.foo{text-orientation:upright}',
            $prefixer->prefixForTargets('.foo { -webkit-text-orientation: upright; text-orientation: upright; }', ['safari' => '13.2'])
        );
        $t->same(
            '.foo{text-decoration-skip-ink:all}',
            $prefixer->prefixForTargets('.foo { text-decoration-skip-ink: all; }', ['safari' => 7])
        );
        $t->same(
            '.foo{-webkit-text-decoration-skip-ink:none;text-decoration-skip-ink:none}',
            $prefixer->prefixForTargets('.foo { text-decoration-skip-ink: none; }', ['safari' => '7.1'])
        );
        $t->same(
            '.foo{-webkit-text-decoration-skip-ink:all;text-decoration-skip-ink:all}',
            $prefixer->prefixForTargets('.foo { text-decoration-skip-ink: all; }', ['safari' => 12])
        );
        $t->same(
            '.foo{text-decoration-skip-ink:all}',
            $prefixer->prefixForTargets('.foo { text-decoration-skip-ink: all; }', ['safari' => '12.1'])
        );
        $t->same(
            '.foo{-webkit-text-decoration-skip-ink:auto;text-decoration-skip-ink:auto}',
            $prefixer->prefixForTargets('.foo { text-decoration-skip-ink: auto; }', ['ios_saf' => 8])
        );
        $t->same(
            '.foo{-webkit-text-decoration-skip-ink:all;text-decoration-skip-ink:all}',
            $prefixer->prefixForTargets('.foo { text-decoration-skip-ink: all; }', ['ios_saf' => 17])
        );
        $t->same(
            '.foo{text-decoration-skip-ink:all}',
            $prefixer->prefixForTargets('.foo { -webkit-text-decoration-skip-ink: all; text-decoration-skip-ink: all; }', ['safari' => 13])
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
    'transition prefixer maps upstream writing-mode browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { writing-mode: vertical-rl; }';
        $modern = '.foo{writing-mode:vertical-rl}';
        $webkit = '.foo{-webkit-writing-mode:vertical-rl;writing-mode:vertical-rl}';
        $ms = '.foo{-ms-writing-mode:tb-rl;writing-mode:vertical-rl}';
        $webkitMs = '.foo{-webkit-writing-mode:vertical-rl;-ms-writing-mode:tb-rl;writing-mode:vertical-rl}';
        $stalePrefixed = '.foo { -webkit-writing-mode: vertical-rl; -ms-writing-mode: tb-rl; writing-mode: vertical-rl; }';

        $t->same($modern, $prefixer->prefixForTargets($css, ['android' => '2.3']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['android' => 3]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['android' => '4.4.3']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['android' => '4.4.4']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['chrome' => 7]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['chrome' => 8]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['chrome' => 47]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['chrome' => 48]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ios_saf' => '4.3']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['ios_saf' => 5]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['ios_saf' => '10.3']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ios_saf' => '10.4']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['opera' => 14]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['opera' => 15]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['opera' => 34]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['opera' => 35]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['safari' => 5]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['safari' => '5.1']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['safari' => '10.1']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['safari' => '10.2']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['samsung' => 4]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['samsung' => 5]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ie' => 5]));
        $t->same($ms, $prefixer->prefixForTargets($css, ['ie' => '5.5']));
        $t->same($ms, $prefixer->prefixForTargets($css, ['ie' => 11]));
        $t->same($webkitMs, $prefixer->prefixForTargets($css, ['chrome' => 47, 'ie' => 11]));
        $t->same(
            '.foo{-ms-writing-mode:lr-tb;writing-mode:horizontal-tb}',
            $prefixer->prefixForTargets('.foo { writing-mode: horizontal-tb; }', ['ie' => 11])
        );
        $t->same(
            '.foo{-ms-writing-mode:tb-lr;writing-mode:vertical-lr}',
            $prefixer->prefixForTargets('.foo { writing-mode: vertical-lr; }', ['ie' => 11])
        );
        $t->same($modern, $prefixer->prefixForTargets($stalePrefixed, ['chrome' => 48, 'safari' => '10.2', 'samsung' => 5]));
        $t->same($ms, $prefixer->prefixForTargets($stalePrefixed, ['chrome' => 48, 'ie' => 11]));
    },
    'transition prefixer maps upstream break property WebKit browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { break-before: page; break-after: column; break-inside: avoid; }';
        $modern = '.foo{break-before:page;break-after:column;break-inside:avoid}';
        $webkit = '.foo{-webkit-break-before:page;break-before:page;-webkit-break-after:column;break-after:column;-webkit-break-inside:avoid;break-inside:avoid}';
        $stalePrefixed = '.foo { -webkit-break-before: page; break-before: page; -webkit-break-after: column; break-after: column; -webkit-break-inside: avoid; break-inside: avoid; }';

        $t->same($modern, $prefixer->prefixForTargets($css, ['android' => 2]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['android' => '2.1']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['android' => '4.4.3']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['android' => '4.4.4']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['chrome' => 49]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['chrome' => 50]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ios_saf' => '3.1']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['ios_saf' => '3.2']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['ios_saf' => '8.1']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ios_saf' => '8.2']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['opera' => 36]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['opera' => 37]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['safari' => 8]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['safari' => 9]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['samsung' => 4]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['samsung' => 5]));
        $t->same($modern, $prefixer->prefixForTargets($stalePrefixed, ['chrome' => 50, 'safari' => 9, 'opera' => 37]));
    },
    'transition prefixer maps upstream scroll snap browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { scroll-snap-type: x mandatory; scroll-snap-coordinate: 0 0; scroll-snap-destination: 50% 50%; scroll-snap-points-x: repeat(100%); scroll-snap-points-y: repeat(50%); }';
        $modern = '.foo{scroll-snap-type:x mandatory;scroll-snap-coordinate:0 0;scroll-snap-destination:50% 50%;scroll-snap-points-x:repeat(100%);scroll-snap-points-y:repeat(50%)}';
        $webkit = '.foo{-webkit-scroll-snap-type:x mandatory;scroll-snap-type:x mandatory;-webkit-scroll-snap-coordinate:0 0;scroll-snap-coordinate:0 0;-webkit-scroll-snap-destination:50% 50%;scroll-snap-destination:50% 50%;-webkit-scroll-snap-points-x:repeat(100%);scroll-snap-points-x:repeat(100%);-webkit-scroll-snap-points-y:repeat(50%);scroll-snap-points-y:repeat(50%)}';
        $ms = '.foo{-ms-scroll-snap-type:x mandatory;scroll-snap-type:x mandatory;-ms-scroll-snap-coordinate:0 0;scroll-snap-coordinate:0 0;-ms-scroll-snap-destination:50% 50%;scroll-snap-destination:50% 50%;-ms-scroll-snap-points-x:repeat(100%);scroll-snap-points-x:repeat(100%);-ms-scroll-snap-points-y:repeat(50%);scroll-snap-points-y:repeat(50%)}';
        $webkitMs = '.foo{-webkit-scroll-snap-type:x mandatory;-ms-scroll-snap-type:x mandatory;scroll-snap-type:x mandatory;-webkit-scroll-snap-coordinate:0 0;-ms-scroll-snap-coordinate:0 0;scroll-snap-coordinate:0 0;-webkit-scroll-snap-destination:50% 50%;-ms-scroll-snap-destination:50% 50%;scroll-snap-destination:50% 50%;-webkit-scroll-snap-points-x:repeat(100%);-ms-scroll-snap-points-x:repeat(100%);scroll-snap-points-x:repeat(100%);-webkit-scroll-snap-points-y:repeat(50%);-ms-scroll-snap-points-y:repeat(50%);scroll-snap-points-y:repeat(50%)}';
        $stalePrefixed = '.foo { -webkit-scroll-snap-type: x mandatory; -ms-scroll-snap-type: x mandatory; scroll-snap-type: x mandatory; -webkit-scroll-snap-points-x: repeat(100%); -ms-scroll-snap-points-x: repeat(100%); scroll-snap-points-x: repeat(100%); }';
        $modernStaleCleanup = '.foo{scroll-snap-type:x mandatory;scroll-snap-points-x:repeat(100%)}';

        $t->same($modern, $prefixer->prefixForTargets($css, ['safari' => 8]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['safari' => 9]));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['safari' => '10.1']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['safari' => '10.2']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['ios_saf' => '10.3']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['ios_saf' => '10.4']));
        $t->same($ms, $prefixer->prefixForTargets($css, ['ie' => 10]));
        $t->same($ms, $prefixer->prefixForTargets($css, ['edge' => 18]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['edge' => 19]));
        $t->same($webkitMs, $prefixer->prefixForTargets($css, ['safari' => '10.1', 'ie' => 10]));
        $t->same($modernStaleCleanup, $prefixer->prefixForTargets($stalePrefixed, ['safari' => '10.2', 'edge' => 19]));
    },
    'transition prefixer maps upstream overflow shorthand browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{overflow-x:hidden;overflow-y:auto}',
            $prefixer->prefixForTargets('.foo { overflow: hidden auto; }', ['chrome' => 67])
        );
        $t->same(
            '.foo{overflow:hidden auto}',
            $prefixer->prefixForTargets('.foo { overflow: hidden auto; }', ['chrome' => 68])
        );
        $t->same(
            '.foo{overflow:hidden}',
            $prefixer->prefixForTargets('.foo { overflow: hidden hidden; }', ['chrome' => 67])
        );
        $t->same(
            '.foo{overflow-x:hidden;overflow-y:auto}',
            $prefixer->prefixForTargets('.foo { overflow-x: hidden; overflow-y: auto; }', ['chrome' => 67])
        );
        $t->same(
            '.foo{overflow-x:hidden;overflow-y:auto}',
            $prefixer->prefixForTargets('.foo { overflow: hidden; overflow-y: auto; }', ['chrome' => 67])
        );
        $t->same(
            '.foo{overflow-x:hidden!important;overflow-y:auto!important}',
            $prefixer->prefixForTargets('.foo { overflow: hidden auto !important; }', ['chrome' => 67])
        );
        $t->same(
            '.foo{overflow-x:hidden;overflow-y:auto}',
            $prefixer->prefixForTargets('.foo { overflow: hidden auto; }', ['firefox' => 60])
        );
        $t->same(
            '.foo{overflow:hidden auto}',
            $prefixer->prefixForTargets('.foo { overflow: hidden auto; }', ['firefox' => 61])
        );
        $t->same(
            '.foo{overflow-x:hidden;overflow-y:auto}',
            $prefixer->prefixForTargets('.foo { overflow: hidden auto; }', ['safari' => 13])
        );
        $t->same(
            '.foo{overflow:hidden auto}',
            $prefixer->prefixForTargets('.foo { overflow: hidden auto; }', ['safari' => '13.1'])
        );
        $t->same(
            '.foo{overflow-x:hidden;overflow-y:auto}',
            $prefixer->prefixForTargets('.foo { overflow: hidden auto; }', ['edge' => 78])
        );
        $t->same(
            '.foo{overflow:hidden auto}',
            $prefixer->prefixForTargets('.foo { overflow: hidden auto; }', ['edge' => 79])
        );
    },
    'transition prefixer maps upstream background clip text browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{background:url(img.png);-webkit-background-clip:text;background-clip:text}',
            $prefixer->prefixForTargets('.foo { background: url(img.png); background-clip: text; }', ['safari' => 8])
        );
        $t->same(
            '.foo{background:url(img.png);background-clip:text}',
            $prefixer->prefixForTargets('.foo { background: url(img.png); background-clip: text; }', ['safari' => 14])
        );
        $t->same(
            '.foo{background:url(img.png);-webkit-background-clip:text;background-clip:text}',
            $prefixer->prefixForTargets('.foo { background: url(img.png) text; }', ['chrome' => 45])
        );
        $t->same(
            '.foo{background-image:url(img.png);-webkit-background-clip:text;background-clip:text}',
            $prefixer->prefixForTargets('.foo { background-image: url(img.png); background-clip: text; }', ['safari' => 8])
        );
        $t->same(
            '.foo{background:url(img.png);-webkit-background-clip:text}',
            $prefixer->prefixForTargets('.foo { background: url(img.png); -webkit-background-clip: text; }', ['chrome' => 45])
        );
        $t->same(
            '.foo{-webkit-background-clip:text;background-clip:text}',
            $prefixer->prefixForTargets('.foo { -webkit-background-clip: text; background-clip: text; }', ['chrome' => 45])
        );
        $t->same(
            '.foo{background-clip:text}',
            $prefixer->prefixForTargets('.foo { -webkit-background-clip: text; background-clip: text; }', ['chrome' => 120])
        );
        $t->same(
            '.foo{background:url(img.png);-webkit-background-clip:text;background-clip:text}',
            $prefixer->prefixForTargets('.foo { background: url(img.png); background-clip: text; }', ['chrome' => 119])
        );
        $t->same(
            '.foo{background:url(img.png);background-clip:text}',
            $prefixer->prefixForTargets('.foo { background: url(img.png); background-clip: text; }', ['chrome' => 120])
        );
        $t->same(
            '.foo{background:url(img.png);-ms-background-clip:text;background-clip:text}',
            $prefixer->prefixForTargets('.foo { background: url(img.png); background-clip: text; }', ['edge' => 13])
        );
    },
    'transition prefixer maps upstream background size and origin browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { background-size: cover; background-origin: content-box; }';
        $modern = '.foo{background-size:cover;background-origin:content-box}';
        $webkit = '.foo{-webkit-background-size:cover;background-size:cover;-webkit-background-origin:content-box;background-origin:content-box}';
        $moz = '.foo{-moz-background-size:cover;background-size:cover;-moz-background-origin:content-box;background-origin:content-box}';
        $opera = '.foo{-o-background-size:cover;background-size:cover;-o-background-origin:content-box;background-origin:content-box}';

        $t->same(
            '.foo{-webkit-background-size:cover;-moz-background-size:cover;-o-background-size:cover;background-size:cover;-webkit-background-origin:content-box;-moz-background-origin:content-box;-o-background-origin:content-box;background-origin:content-box}',
            $prefixer->prefixForTargets($css, ['android' => '2.3', 'firefox' => '3.6', 'opera' => 10])
        );
        $t->same($webkit, $prefixer->prefixForTargets($css, ['android' => '2.1']));
        $t->same($webkit, $prefixer->prefixForTargets($css, ['android' => '2.3']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['android' => 3]));
        $t->same($moz, $prefixer->prefixForTargets($css, ['firefox' => '3.6']));
        $t->same($modern, $prefixer->prefixForTargets($css, ['firefox' => 4]));
        $t->same($opera, $prefixer->prefixForTargets($css, ['opera' => 10]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['opera' => '10.1']));
        $t->same(
            $modern,
            $prefixer->prefixForTargets('.foo { -webkit-background-size: cover; -moz-background-size: cover; -o-background-size: cover; background-size: cover; -webkit-background-origin: content-box; -moz-background-origin: content-box; -o-background-origin: content-box; background-origin: content-box; }', ['firefox' => 4, 'opera' => '10.1', 'android' => 3])
        );
        $t->same(
            $moz,
            $prefixer->prefixForTargets('.foo { -webkit-background-size: cover; -moz-background-size: cover; -o-background-size: cover; background-size: cover; -webkit-background-origin: content-box; -moz-background-origin: content-box; -o-background-origin: content-box; background-origin: content-box; }', ['firefox' => '3.6'])
        );
    },
    'transition prefixer maps upstream clip-path WebKit target boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $prefixed = '.foo{-webkit-clip-path:circle(50px);clip-path:circle(50px)}';
        $modern = '.foo{clip-path:circle(50px)}';

        $t->same($prefixed, $prefixer->prefixForTargets('.foo { clip-path: circle(50px); }', ['chrome' => 30]));
        $t->same($modern, $prefixer->prefixForTargets('.foo { clip-path: circle(50px); }', ['chrome' => 80]));
        $t->same($prefixed, $prefixer->prefixForTargets('.foo { clip-path: circle(50px); }', ['safari' => 8]));
        $t->same($modern, $prefixer->prefixForTargets('.foo { clip-path: circle(50px); }', ['safari' => 14]));
        $t->same($prefixed, $prefixer->prefixForTargets('.foo { clip-path: circle(50px); }', ['chrome' => 54]));
        $t->same($modern, $prefixer->prefixForTargets('.foo { clip-path: circle(50px); }', ['chrome' => 55]));
        $t->same($prefixed, $prefixer->prefixForTargets('.foo { clip-path: circle(50px); }', ['safari' => 9]));
        $t->same($modern, $prefixer->prefixForTargets('.foo { clip-path: circle(50px); }', ['safari' => 10]));
    },
    'transition prefixer maps upstream logical text-align browser boundaries' => static function (TestRunner $t) use ($rtlLangs): void {
        $prefixer = new TransitionPrefixer();
        $ltr = ':not(:is(' . $rtlLangs . '))';
        $rtl = ':is(' . $rtlLangs . ')';

        $t->same(
            '.foo' . $ltr . '{text-align:left}.foo' . $rtl . '{text-align:right}',
            $prefixer->prefixForTargets('.foo { text-align: start; }', ['safari' => 2])
        );
        $t->same(
            '.foo' . $ltr . '{text-align:right}.foo' . $rtl . '{text-align:left}',
            $prefixer->prefixForTargets('.foo { text-align: end; }', ['safari' => 2])
        );
        $t->same(
            '.foo{text-align:start}',
            $prefixer->prefixForTargets('.foo { text-align: start; }', ['safari' => 14])
        );
        $t->same(
            '.foo>.bar' . $ltr . '{text-align:left}.foo>.bar' . $rtl . '{text-align:right}',
            $prefixer->prefixForTargets('.foo > .bar { text-align: start; }', ['safari' => 2])
        );
        $t->same(
            '.foo' . $ltr . ':after{text-align:left}.foo' . $rtl . ':after{text-align:right}',
            $prefixer->prefixForTargets('.foo:after { text-align: start; }', ['safari' => 2])
        );
        $t->same(
            '.foo:hover' . $ltr . '{text-align:left}.foo:hover' . $rtl . '{text-align:right}',
            $prefixer->prefixForTargets('.foo:hover { text-align: start; }', ['safari' => 2])
        );
        $t->same(
            '.foo' . $ltr . '{text-align:left}.foo' . $rtl . '{text-align:right}',
            $prefixer->prefixForTargets('.foo { text-align: start; }', ['chrome' => 17])
        );
        $t->same(
            '.foo{text-align:start}',
            $prefixer->prefixForTargets('.foo { text-align: start; }', ['chrome' => 18])
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
    'transition prefixer maps upstream logical spacing target fallbacks' => static function (TestRunner $t) use ($variants): void {
        $prefixer = new TransitionPrefixer();
        $selector = $variants('.foo');

        $t->same(
            $selector['ltr-webkit'] . '{margin-left:2px}'
                . $selector['ltr-modern'] . '{margin-left:2px}'
                . $selector['rtl-webkit'] . '{margin-right:2px}'
                . $selector['rtl-modern'] . '{margin-right:2px}',
            $prefixer->prefixForTargets('.foo { margin-inline-start: 2px; }', ['safari' => 8])
        );
        $t->same(
            $selector['ltr-webkit'] . '{margin-left:2px;margin-right:4px}'
                . $selector['ltr-modern'] . '{margin-left:2px;margin-right:4px}'
                . $selector['rtl-webkit'] . '{margin-left:4px;margin-right:2px}'
                . $selector['rtl-modern'] . '{margin-left:4px;margin-right:2px}',
            $prefixer->prefixForTargets('.foo { margin-inline-start: 2px; margin-inline-end: 4px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{margin-left:2px;margin-right:2px}',
            $prefixer->prefixForTargets('.foo { margin-inline: 2px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{margin-top:2px}',
            $prefixer->prefixForTargets('.foo { margin-block-start: 2px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{margin-bottom:2px}',
            $prefixer->prefixForTargets('.foo { margin-block-end: 2px; }', ['safari' => 8])
        );
        $t->same(
            $selector['ltr-webkit'] . '{padding-left:var(--padding)}'
                . $selector['ltr-modern'] . '{padding-left:var(--padding)}'
                . $selector['rtl-webkit'] . '{padding-right:var(--padding)}'
                . $selector['rtl-modern'] . '{padding-right:var(--padding)}',
            $prefixer->prefixForTargets('.foo { padding-inline-start: var(--padding); }', ['safari' => 8])
        );
        $t->same(
            $selector['ltr-webkit'] . '{padding-left:2px;padding-right:4px}'
                . $selector['ltr-modern'] . '{padding-left:2px;padding-right:4px}'
                . $selector['rtl-webkit'] . '{padding-left:4px;padding-right:2px}'
                . $selector['rtl-modern'] . '{padding-left:4px;padding-right:2px}',
            $prefixer->prefixForTargets('.foo { padding-inline: 2px 4px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{padding-top:2px}',
            $prefixer->prefixForTargets('.foo { padding-block-start: 2px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{padding-bottom:2px}',
            $prefixer->prefixForTargets('.foo { padding-block-end: 2px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{margin-inline-start:2px;margin-inline-end:4px;padding-block-start:1rem;padding-block-end:2rem}',
            $prefixer->prefixForTargets('.foo { margin-inline: 2px 4px; padding-block: 1rem 2rem; }', ['safari' => 13])
        );
    },
    'transition prefixer maps upstream logical spacing browser boundaries' => static function (TestRunner $t) use ($variants): void {
        $prefixer = new TransitionPrefixer();
        $selector = $variants('.foo');
        $inlineStartFallback = $selector['ltr-webkit'] . '{margin-left:2px}'
            . $selector['ltr-modern'] . '{margin-left:2px}'
            . $selector['rtl-webkit'] . '{margin-right:2px}'
            . $selector['rtl-modern'] . '{margin-right:2px}';
        $blockStartFallback = '.foo{margin-top:2px}';

        $t->same($inlineStartFallback, $prefixer->prefixForTargets('.foo { margin-inline-start: 2px; }', ['safari' => '12.0']));
        $t->same('.foo{margin-inline-start:2px}', $prefixer->prefixForTargets('.foo { margin-inline-start: 2px; }', ['safari' => '12.1']));
        $t->same($blockStartFallback, $prefixer->prefixForTargets('.foo { margin-block-start: 2px; }', ['safari' => '12.0']));
        $t->same('.foo{margin-block-start:2px}', $prefixer->prefixForTargets('.foo { margin-block-start: 2px; }', ['safari' => '12.1']));
        $t->same($inlineStartFallback, $prefixer->prefixForTargets('.foo { margin-inline-start: 2px; }', ['chrome' => 68]));
        $t->same('.foo{margin-inline-start:2px}', $prefixer->prefixForTargets('.foo { margin-inline-start: 2px; }', ['chrome' => 69]));
        $t->same($inlineStartFallback, $prefixer->prefixForTargets('.foo { margin-inline-start: 2px; }', ['firefox' => 40]));
        $t->same('.foo{margin-inline-start:2px}', $prefixer->prefixForTargets('.foo { margin-inline-start: 2px; }', ['firefox' => 41]));
        $t->same('.foo{margin-block-start:2px}', $prefixer->prefixForTargets('.foo { margin-block-start: 2px; }', ['firefox' => 40]));
        $t->same('.foo{padding-inline-start:2px;padding-inline-end:2px}', $prefixer->prefixForTargets('.foo { padding-inline: 2px; }', ['safari' => 13]));
        $t->same('.foo{padding-inline:2px}', $prefixer->prefixForTargets('.foo { padding-inline: 2px; }', ['safari' => 15]));
        $t->same($blockStartFallback, $prefixer->prefixForTargets('.foo { margin-block-start: 2px; }', [
            'browsers' => ['chrome' => 120],
            'include' => ['LogicalProperties'],
        ]));
        $t->same('.foo{margin-block-start:2px}', $prefixer->prefixForTargets('.foo { margin-block-start: 2px; }', [
            'browsers' => ['safari' => 8],
            'exclude' => ['logical-properties'],
        ]));
    },
    'transition prefixer maps upstream logical border target fallbacks' => static function (TestRunner $t) use ($variants): void {
        $prefixer = new TransitionPrefixer();
        $selector = $variants('.foo');

        $t->same(
            '.foo{border-top:2px solid red;border-bottom:2px solid red}',
            $prefixer->prefixForTargets('.foo { border-block: 2px solid red; }', ['safari' => 8])
        );
        $t->same(
            '.foo{border-top:2px solid red}',
            $prefixer->prefixForTargets('.foo { border-block-start: 2px solid red; }', ['safari' => 8])
        );
        $t->same(
            '.foo{border-bottom:2px solid red}',
            $prefixer->prefixForTargets('.foo { border-block-end: 2px solid red; }', ['safari' => 8])
        );
        $t->same(
            '.foo{border-left:2px solid red;border-right:2px solid red}',
            $prefixer->prefixForTargets('.foo { border-inline: 2px solid red; }', ['safari' => 8])
        );
        $t->same(
            $selector['ltr-webkit'] . '{border-left:2px solid red}'
                . $selector['ltr-modern'] . '{border-left:2px solid red}'
                . $selector['rtl-webkit'] . '{border-right:2px solid red}'
                . $selector['rtl-modern'] . '{border-right:2px solid red}',
            $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; }', ['safari' => 8])
        );
        $t->same(
            $selector['ltr-webkit'] . '{border-left-width:2px}'
                . $selector['ltr-modern'] . '{border-left-width:2px}'
                . $selector['rtl-webkit'] . '{border-right-width:2px}'
                . $selector['rtl-modern'] . '{border-right-width:2px}',
            $prefixer->prefixForTargets('.foo { border-inline-start-width: 2px; }', ['safari' => 8])
        );
        $t->same(
            $selector['ltr-webkit'] . '{border-right:2px solid red}'
                . $selector['ltr-modern'] . '{border-right:2px solid red}'
                . $selector['rtl-webkit'] . '{border-left:2px solid red}'
                . $selector['rtl-modern'] . '{border-left:2px solid red}',
            $prefixer->prefixForTargets('.foo { border-inline-end: 2px solid red; }', ['safari' => 8])
        );
        $t->same(
            $selector['ltr-webkit'] . '{border-left:2px solid red;border-right:5px solid green}'
                . $selector['ltr-modern'] . '{border-left:2px solid red;border-right:5px solid green}'
                . $selector['rtl-webkit'] . '{border-right:2px solid red;border-left:5px solid green}'
                . $selector['rtl-modern'] . '{border-right:2px solid red;border-left:5px solid green}',
            $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; border-inline-end: 5px solid green; }', ['safari' => 8])
        );
        $t->same(
            '.foo{border-left-width:2px;border-right-width:2px}',
            $prefixer->prefixForTargets('.foo { border-inline-width: 2px; }', ['safari' => 8])
        );
        $t->same(
            '.foo{border-left-style:solid;border-right-style:solid}',
            $prefixer->prefixForTargets('.foo { border-inline-style: solid; }', ['safari' => 8])
        );
        $t->same(
            '.foo{border-left-color:red;border-right-color:red}',
            $prefixer->prefixForTargets('.foo { border-inline-color: red; }', ['safari' => 8])
        );
        $t->same(
            $selector['ltr-webkit'] . '{border-right:var(--test)}'
                . $selector['ltr-modern'] . '{border-right:var(--test)}'
                . $selector['rtl-webkit'] . '{border-left:var(--test)}'
                . $selector['rtl-modern'] . '{border-left:var(--test)}',
            $prefixer->prefixForTargets('.foo { border-inline-end: var(--test); }', ['safari' => 8])
        );
    },
    'transition prefixer maps upstream logical border browser boundaries' => static function (TestRunner $t) use ($variants): void {
        $prefixer = new TransitionPrefixer();
        $selector = $variants('.foo');
        $inlineStartFallback = $selector['ltr-webkit'] . '{border-left:2px solid red}'
            . $selector['ltr-modern'] . '{border-left:2px solid red}'
            . $selector['rtl-webkit'] . '{border-right:2px solid red}'
            . $selector['rtl-modern'] . '{border-right:2px solid red}';

        $t->same('.foo{border-block-start-width:2px;border-block-end-width:2px}', $prefixer->prefixForTargets('.foo { border-block-width: 2px; }', ['safari' => 13]));
        $t->same('.foo{border-block-width:2px}', $prefixer->prefixForTargets('.foo { border-block-width: 2px; }', ['safari' => 15]));
        $t->same('.foo{border-inline-start:2px solid red;border-inline-end:2px solid red}', $prefixer->prefixForTargets('.foo { border-inline: 2px solid red; }', ['safari' => 13]));
        $t->same('.foo{border-inline:2px solid red}', $prefixer->prefixForTargets('.foo { border-inline: 2px solid red; }', ['safari' => '14.1']));
        $t->same($inlineStartFallback, $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; }', ['safari' => '12.0']));
        $t->same('.foo{border-inline-start:2px solid red}', $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; }', ['safari' => '12.1']));
        $t->same($inlineStartFallback, $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; }', ['chrome' => 68]));
        $t->same('.foo{border-inline-start:2px solid red}', $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; }', ['chrome' => 69]));
        $t->same($inlineStartFallback, $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; }', ['firefox' => 40]));
        $t->same('.foo{border-inline-start:2px solid red}', $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; }', ['firefox' => 41]));
        $t->same($inlineStartFallback, $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; }', [
            'browsers' => ['chrome' => 120],
            'include' => ['LogicalProperties'],
        ]));
        $t->same('.foo{border-inline-start:2px solid red}', $prefixer->prefixForTargets('.foo { border-inline-start: 2px solid red; }', [
            'browsers' => ['safari' => 8],
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
            '.foo{display:-webkit-box}',
            $prefixer->prefixForTargets('.foo{ display: flex; display: -webkit-box; }', $legacyTargets)
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
        $t->same(
            '.foo{display:-moz-box;display:-webkit-flex;display:-ms-flexbox}',
            $prefixer->prefixForTargets(
                '.foo { display: -webkit-box; display: flex; display: -moz-box; display: -webkit-flex; display: -ms-flexbox; }',
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
            $prefixer->prefixForTargets('.foo { transition: mask 200ms; }', ['chrome' => 119])
        );
        $t->same(
            '.foo{transition:-webkit-mask-box-image .2s,mask-border .2s}',
            $prefixer->prefixForTargets('.foo { transition: mask-border 200ms; }', ['chrome' => 119])
        );
        $t->same(
            '.foo{transition-property:-webkit-mask,mask}',
            $prefixer->prefixForTargets('.foo { transition-property: mask; }', ['chrome' => 119])
        );
        $t->same(
            '.foo{transition-property:-webkit-mask-box-image,mask-border}',
            $prefixer->prefixForTargets('.foo { transition-property: mask-border; }', ['chrome' => 119])
        );
        $t->same(
            '.foo{transition-property:-webkit-mask-composite,mask-composite,-webkit-mask-source-type,mask-mode}',
            $prefixer->prefixForTargets('.foo { transition-property: mask-composite, mask-mode; }', ['chrome' => 119])
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
    'transition prefixer maps upstream mask WebKit browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-mask-image:linear-gradient(red,green);mask-image:linear-gradient(red,green)}',
            $prefixer->prefixForTargets('.foo { mask-image: linear-gradient(red, green); }', ['chrome' => 119])
        );
        $t->same(
            '.foo{mask-image:linear-gradient(red,green)}',
            $prefixer->prefixForTargets('.foo { mask-image: linear-gradient(red, green); }', ['chrome' => 120])
        );
        $t->same(
            '.foo{-webkit-mask-box-image:url(border-mask.png) 25/35px/12px space;mask-border:url(border-mask.png) 25/35px/12px space luminance}',
            $prefixer->prefixForTargets('.foo { mask-border: url(border-mask.png) 25 / 35px / 12px space luminance; }', ['edge' => 119])
        );
        $t->same(
            '.foo{mask-border:url(border-mask.png) 25/35px/12px space luminance}',
            $prefixer->prefixForTargets('.foo { mask-border: url(border-mask.png) 25 / 35px / 12px space luminance; }', ['edge' => 120])
        );
        $t->same(
            '.foo{-webkit-mask-source-type:luminance;mask-mode:luminance}',
            $prefixer->prefixForTargets('.foo { mask-mode: luminance; }', ['safari' => 15])
        );
        $t->same(
            '.foo{-webkit-mask-image:linear-gradient(red,green);mask-image:linear-gradient(red,green)}',
            $prefixer->prefixForTargets('.foo { mask-image: linear-gradient(red, green); }', ['safari' => '15.2'])
        );
        $t->same(
            '.foo{mask-image:linear-gradient(red,green)}',
            $prefixer->prefixForTargets('.foo { -webkit-mask-image: linear-gradient(red, green); mask-image: linear-gradient(red, green); }', ['safari' => '15.3'])
        );
        $t->same(
            '.foo{-webkit-mask-image:linear-gradient(red,green);mask-image:linear-gradient(red,green)}',
            $prefixer->prefixForTargets('.foo { mask-image: linear-gradient(red, green); }', ['ios_saf' => '15.2'])
        );
        $t->same(
            '.foo{mask-image:linear-gradient(red,green)}',
            $prefixer->prefixForTargets('.foo { -webkit-mask-image: linear-gradient(red, green); mask-image: linear-gradient(red, green); }', ['ios_saf' => '15.3'])
        );
        $t->same(
            '.foo{mask-mode:luminance}',
            $prefixer->prefixForTargets('.foo { mask-mode: luminance; }', ['safari' => 16])
        );
        $t->same(
            '.foo{transition:-webkit-mask .2s,mask .2s}',
            $prefixer->prefixForTargets('.foo { transition: mask 200ms; }', ['opera' => 105])
        );
        $t->same(
            '.foo{transition:mask .2s}',
            $prefixer->prefixForTargets('.foo { transition: mask 200ms; }', ['opera' => 106])
        );
        $t->same(
            '.foo{transition-property:-webkit-mask-composite,mask-composite,-webkit-mask-source-type,mask-mode}',
            $prefixer->prefixForTargets('.foo { transition-property: mask-composite, mask-mode; }', ['samsung' => 24])
        );
        $t->same(
            '.foo{transition-property:mask-composite,mask-mode}',
            $prefixer->prefixForTargets('.foo { transition-property: mask-composite, mask-mode; }', ['samsung' => 25])
        );
        $t->same(
            '.foo{mask-image:linear-gradient(red,green)}',
            $prefixer->prefixForTargets('.foo { -webkit-mask-image: linear-gradient(red, green); mask-image: linear-gradient(red, green); }', ['chrome' => 120])
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
    'transition prefixer maps upstream SVG paint advanced color fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{fill:#ee00be;fill:color(display-p3 .972962 -.362078 .804206);fill:lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets('.foo { fill: lch(50.998% 135.363 338) }', ['chrome' => 90, 'safari' => 14])
        );
        $t->same(
            '.foo{stroke:#ee00be;stroke:color(display-p3 .972962 -.362078 .804206);stroke:lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets('.foo { stroke: lch(50.998% 135.363 338) }', ['chrome' => 90, 'safari' => 14])
        );
        $t->same(
            '.foo{fill:url("#foo") #ee00be;fill:url("#foo") color(display-p3 .972962 -.362078 .804206);fill:url("#foo") lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets('.foo { fill: url(#foo) lch(50.998% 135.363 338) }', ['chrome' => 90, 'safari' => 14])
        );
        $t->same(
            '.foo{fill:var(--url) #ee00be}@supports (color:lab(0% 0 0)){.foo{fill:var(--url) lab(50.998% 125.506 -50.7078)}}',
            $prefixer->prefixForTargets('.foo { fill: var(--url) lch(50.998% 135.363 338) }', ['chrome' => 90])
        );
        $t->same(
            '.foo{fill:lch(50.998% 135.363 338)}',
            $prefixer->prefixForTargets('.foo { fill: lch(50.998% 135.363 338) }', ['safari' => 15])
        );
    },
    'transition prefixer maps upstream outline advanced color target fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{outline-color:#b32323;outline-color:lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { outline-color: lab(40% 56.6 39) }', ['chrome' => 90])
        );
        $t->same(
            '.foo{outline:2px solid #b32323;outline:2px solid lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { outline: 2px solid lab(40% 56.6 39) }', ['chrome' => 90])
        );
        $t->same(
            '.foo{outline:var(--width) solid #b32323}@supports (color:lab(0% 0 0)){.foo{outline:var(--width) solid lab(40% 56.6 39)}}',
            $prefixer->prefixForTargets('.foo { outline: var(--width) solid lab(40% 56.6 39) }', ['chrome' => 90])
        );
        $t->same(
            '.foo{outline-color:lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { outline-color: lab(40% 56.6 39) }', ['chrome' => 111])
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
        $t->same(
            '.foo{background-color:#c65d07;background-color:lab(52.2319% 40.1449 59.9171)}',
            $prefixer->prefixForTargets(
                '.foo { background-color: oklab(59.686% 0.1009 0.1192); }',
                ['chrome' => 90, 'safari' => 15]
            )
        );
    },
    'transition prefixer maps upstream color function gamut fallbacks by target' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $chromeCases = [
            '.foo { background-color: color(sRGB 0.41587 0.503670 0.36664); }' => '.foo{background-color:#6a805d;background-color:color(srgb .41587 .50367 .36664)}',
            '.foo { background-color: color(display-p3 0.43313 0.50108 0.37950); }' => '.foo{background-color:#6a805d;background-color:color(display-p3 .43313 .50108 .3795)}',
            '.foo { background-color: color(a98-rgb 0.44091 0.49971 0.37408); }' => '.foo{background-color:#6a805d;background-color:color(a98-rgb .44091 .49971 .37408)}',
            '.foo { background-color: color(prophoto-rgb 0.36589 0.41717 0.31333); }' => '.foo{background-color:#6a805d;background-color:color(prophoto-rgb .36589 .41717 .31333)}',
            '.foo { background-color: color(rec2020 0.42210 0.47580 0.35605); }' => '.foo{background-color:#728765;background-color:color(rec2020 .4221 .4758 .35605)}',
            '.foo { background-color: color(xyz-d50 0.2005 0.14089 0.4472); }' => '.foo{background-color:#7654cd;background-color:color(xyz-d50 .2005 .14089 .4472)}',
            '.foo { background-color: color(xyz-d65 0.21661 0.14602 0.59452); }' => '.foo{background-color:#7654cd;background-color:color(xyz .21661 .14602 .59452)}',
        ];

        foreach ($chromeCases as $input => $expected) {
            $t->same($expected, $prefixer->prefixForTargets($input, ['chrome' => 90]));
        }

        $t->same(
            '.foo{background-color:#6a805d;background-color:color(display-p3 .43313 .50108 .3795)}',
            $prefixer->prefixForTargets(
                '.foo { background-color: color(display-p3 0.43313 0.50108 0.37950); }',
                ['chrome' => 90, 'safari' => 14]
            )
        );
        $t->same(
            '.foo{background-color:color(a98-rgb .44091 .49971 .37408)}',
            $prefixer->prefixForTargets(
                '.foo { background-color: color(a98-rgb 0.44091 0.49971 0.37408); }',
                ['safari' => 15]
            )
        );
        $t->same(
            '.foo{background-color:lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { background-color: lab(40% 56.6 39) }', ['safari' => 15])
        );
    },
    'transition prefixer maps upstream advanced color fallback cleanup targets' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{color:red;color:lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { color: red; color: lab(40% 56.6 39); }', ['safari' => 14])
        );
        $t->same(
            '.foo{color:lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { color: red; color: lab(40% 56.6 39); }', ['safari' => 16])
        );
        $t->same(
            '.foo{color:lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { color: var(--fallback); color: lab(40% 56.6 39); }', ['safari' => 16])
        );
        $t->same(
            '.foo{color:var(--foo,color(display-p3 .643308 .192455 .167712))}@supports (color:lab(0% 0 0)){.foo{color:var(--foo,lab(40% 56.6 39))}}',
            $prefixer->prefixForTargets('.foo { color: red; color: var(--foo, lab(40% 56.6 39)); }', ['safari' => 14])
        );
        $t->same(
            '@supports (color:lab(0% 0 0)){.foo{color:var(--foo,lab(40% 56.6 39))}}',
            $prefixer->prefixForTargets('@supports (color: lab(0% 0 0)) { .foo { color: var(--foo, lab(40% 56.6 39)); } }', ['safari' => 14])
        );
    },
    'transition prefixer maps upstream supports guarded color fallback boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@supports (color:lab(0% 0 0)) and (color:color(display-p3 0 0 0)){.foo{color:lab(40% 56.6 39)}.bar{color:color(display-p3 .643308 .192455 .167712)}}',
            $prefixer->prefixForTargets('@supports (color: lab(0% 0 0)) and (color: color(display-p3 0 0 0)) { .foo { color: lab(40% 56.6 39); } .bar { color: color(display-p3 .643308 .192455 .167712); } }', ['chrome' => 4])
        );
        $t->same(
            '@supports (color:lab(40% 56.6 39)){.foo{color:lab(40% 56.6 39)}}',
            $prefixer->prefixForTargets('@supports (color: lab(40% 56.6 39)) { .foo { color: lab(40% 56.6 39); } }', ['chrome' => 4])
        );
        $t->same(
            '@supports (background-color:lab(40% 56.6 39)){.foo{background-color:lab(40% 56.6 39)}}',
            $prefixer->prefixForTargets('@supports (background-color: lab(40% 56.6 39)) { .foo { background-color: lab(40% 56.6 39); } }', ['chrome' => 4])
        );
        $t->same(
            '@supports (color:light-dark(#f00,#00f)){.foo{color:light-dark(#ff0,#0ff)}}',
            $prefixer->prefixForTargets('@supports (color: light-dark(#f00, #00f)) { .foo { color: light-dark(#ff0, #0ff); } }', ['chrome' => 4])
        );
        $t->same(
            '@supports (color:lab(0% 0 0)) and (not (color:color(display-p3 0 0 0))){.foo{color:#b32323;color:lab(40% 56.6 39)}.bar{color:#b32323;color:color(display-p3 .643308 .192455 .167712)}}',
            $prefixer->prefixForTargets('@supports (color: lab(0% 0 0)) and (not (color: color(display-p3 0 0 0))) { .foo { color: lab(40% 56.6 39); } .bar { color: color(display-p3 .643308 .192455 .167712); } }', ['chrome' => 4])
        );
        $t->same(
            '@supports (color:lab(0% 0 0)) or (color:color(display-p3 0 0 0)){.foo{color:#b32323;color:lab(40% 56.6 39)}.bar{color:#b32323;color:color(display-p3 .643308 .192455 .167712)}}',
            $prefixer->prefixForTargets('@supports (color: lab(0% 0 0)) or (color: color(display-p3 0 0 0)) { .foo { color: lab(40% 56.6 39); } .bar { color: color(display-p3 .643308 .192455 .167712); } }', ['chrome' => 4])
        );
    },
    'transition prefixer maps upstream xyz color mix target fallback' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{color:#008000a6;color:color(xyz .0771883 .154377 .0257295/.65)}',
            $prefixer->prefixForTargets(
                '.foo { color: color-mix(in xyz, transparent, green 65%); }',
                ['chrome' => 95]
            )
        );
        $t->same(
            '.foo{color:color(xyz .0771883 .154377 .0257295/.65)}',
            $prefixer->prefixForTargets(
                '.foo { color: color-mix(in xyz, transparent, green 65%); }',
                ['chrome' => 111]
            )
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
            $prefixer->prefixForTargets('.foo { filter: blur(5px) }', ['chrome' => 20])
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
            $prefixer->prefixForTargets('.foo { filter: var(--foo) }', ['chrome' => 20])
        );
        $t->same(
            '.foo{backdrop-filter:blur(5px)}',
            $prefixer->prefixForTargets('.foo { backdrop-filter: blur(5px) }', ['chrome' => 80])
        );
    },
    'transition prefixer maps upstream filter WebKit browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $prefixed = '.foo{-webkit-filter:blur(5px);filter:blur(5px)}';
        $modern = '.foo{filter:blur(5px)}';

        $t->same($modern, $prefixer->prefixForTargets('.foo { filter: blur(5px); }', ['chrome' => 17]));
        $t->same($prefixed, $prefixer->prefixForTargets('.foo { filter: blur(5px); }', ['chrome' => 18]));
        $t->same($prefixed, $prefixer->prefixForTargets('.foo { filter: blur(5px); }', ['chrome' => 52]));
        $t->same($modern, $prefixer->prefixForTargets('.foo { -webkit-filter: blur(5px); filter: blur(5px); }', ['chrome' => 53]));
        $t->same($prefixed, $prefixer->prefixForTargets('.foo { filter: blur(5px); }', ['safari' => 9]));
        $t->same($modern, $prefixer->prefixForTargets('.foo { -webkit-filter: blur(5px); filter: blur(5px); }', ['safari' => 10]));
        $t->same($prefixed, $prefixer->prefixForTargets('.foo { filter: blur(5px); }', ['ios_saf' => 9]));
        $t->same($modern, $prefixer->prefixForTargets('.foo { -webkit-filter: blur(5px); filter: blur(5px); }', ['ios_saf' => 10]));
        $t->same($prefixed, $prefixer->prefixForTargets('.foo { filter: blur(5px); }', ['samsung' => '6.2']));
        $t->same($modern, $prefixer->prefixForTargets('.foo { -webkit-filter: blur(5px); filter: blur(5px); }', ['samsung' => '6.3']));
    },
    'transition prefixer maps upstream backdrop-filter transition browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $encoded = static fn (int $major, int $minor = 0, int $patch = 0): int => ($major << 16) | ($minor << 8) | $patch;

        $t->same(
            '.foo{transition-property:-webkit-backdrop-filter,backdrop-filter}',
            $prefixer->prefixForTargets('.foo { transition-property: backdrop-filter; }', ['safari' => $encoded(17, 6)])
        );
        $t->same(
            '.foo{transition-property:backdrop-filter}',
            $prefixer->prefixForTargets('.foo { transition-property: backdrop-filter; }', ['safari' => 18])
        );
        $t->same(
            '.foo{transition-property:backdrop-filter}',
            $prefixer->prefixForTargets('.foo { transition-property: -webkit-backdrop-filter, backdrop-filter; }', ['safari' => 18])
        );
        $t->same(
            '.foo{transition-property:-webkit-backdrop-filter}',
            $prefixer->prefixForTargets('.foo { transition-property: -webkit-backdrop-filter; }', ['safari' => $encoded(17, 6)])
        );
        $t->same(
            '.foo{transition:-webkit-backdrop-filter,backdrop-filter}',
            $prefixer->prefixForTargets('.foo { transition: backdrop-filter; }', ['safari' => $encoded(17, 6)])
        );
        $t->same(
            '.foo{transition:backdrop-filter}',
            $prefixer->prefixForTargets('.foo { transition: backdrop-filter; }', ['safari' => 18])
        );
        $t->same(
            '.foo{transition:backdrop-filter}',
            $prefixer->prefixForTargets('.foo { transition: -webkit-backdrop-filter, backdrop-filter; }', ['safari' => 18])
        );
        $t->same(
            '.foo{transition:-webkit-backdrop-filter}',
            $prefixer->prefixForTargets('.foo { transition: -webkit-backdrop-filter; }', ['safari' => $encoded(17, 6)])
        );
    },
    'transition prefixer merges adjacent rules after target prefixing' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo,.bar{transition-property:-webkit-backdrop-filter,backdrop-filter}.baz{transition-property:-webkit-backdrop-filter}',
            $prefixer->prefixForTargets(
                '.foo { transition-property: -webkit-backdrop-filter, backdrop-filter; } .bar { transition-property: backdrop-filter; } .baz { transition-property: -webkit-backdrop-filter; }',
                ['safari' => 15]
            )
        );
        $t->same(
            '.foo,.bar{transition:-webkit-backdrop-filter,backdrop-filter}.baz{transition:-webkit-backdrop-filter}',
            $prefixer->prefixForTargets(
                '.foo { transition: -webkit-backdrop-filter, backdrop-filter; } .bar { transition: backdrop-filter; } .baz { transition: -webkit-backdrop-filter; }',
                ['safari' => 15]
            )
        );
        $t->same(
            '.foo,.bar{filter:blur(5px)}',
            $prefixer->prefixForTargets(
                '.foo { -webkit-filter: blur(5px); filter: blur(5px); } .bar { filter: blur(5px); }',
                ['chrome' => 53]
            )
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
        $t->same(
            '@supports ((-webkit-filter:blur(5px)) or (filter:blur(5px))){.foo{-webkit-filter:blur(5px);filter:blur(5px)}}',
            $prefixer->prefixForTargets('@supports (filter: blur(5px)) { .foo { filter: blur(5px); } }', ['chrome' => 52])
        );
        $t->same(
            '@supports (filter:blur(5px)){.foo{filter:blur(5px)}}',
            $prefixer->prefixForTargets('@supports ((-webkit-filter: blur(5px)) or (filter: blur(5px))) { .foo { -webkit-filter: blur(5px); filter: blur(5px); } }', ['chrome' => 53])
        );
        $t->same(
            '@supports ((-webkit-filter:blur(5px)) or (filter:blur(5px))) and (color:red){.foo{-webkit-filter:blur(5px);filter:blur(5px);color:red}}',
            $prefixer->prefixForTargets('@supports (filter: blur(5px)) and (color: red) { .foo { filter: blur(5px); color: red; } }', ['chrome' => 52])
        );
        $t->same(
            '@supports ((-webkit-filter:blur(5px)) or (filter:blur(5px))){.foo{-webkit-filter:blur(5px);filter:blur(5px)}}',
            $prefixer->prefixForTargets('@supports (filter: blur(5px)) { .foo { filter: blur(5px); } }', ['safari' => 9])
        );
        $t->same(
            '@supports (filter:blur(5px)){.foo{filter:blur(5px)}}',
            $prefixer->prefixForTargets('@supports ((-webkit-filter: blur(5px)) or (filter: blur(5px))) { .foo { -webkit-filter: blur(5px); filter: blur(5px); } }', ['safari' => 10])
        );
    },
    'transition prefixer maps upstream supports declaration target-prefix boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $encoded = static fn (int $major, int $minor = 0): int => ($major << 16) | ($minor << 8);

        $t->same(
            '@supports ((-webkit-appearance:none) or (appearance:none)){.foo{-webkit-appearance:none;appearance:none}}',
            $prefixer->prefixForTargets('@supports (appearance: none) { .foo { appearance: none; } }', ['safari' => $encoded(15, 2)])
        );
        $t->same(
            '@supports (appearance:none){.foo{appearance:none}}',
            $prefixer->prefixForTargets('@supports (appearance: none) { .foo { appearance: none; } }', ['safari' => $encoded(15, 3)])
        );
        $t->same(
            '@supports (appearance:none){.foo{appearance:none}}',
            $prefixer->prefixForTargets('@supports ((-webkit-appearance: none) or (appearance: none)) { .foo { -webkit-appearance: none; appearance: none; } }', ['safari' => $encoded(15, 3)])
        );
        $t->same(
            '@supports ((-webkit-appearance:none) or (appearance:none)) and (color:red){.foo{-webkit-appearance:none;appearance:none;color:red}}',
            $prefixer->prefixForTargets('@supports (appearance: none) and (color: red) { .foo { appearance: none; color: red; } }', ['safari' => $encoded(15, 2)])
        );
        $t->same(
            '@supports ((-moz-user-select:none) or (user-select:none)){.foo{-moz-user-select:none;user-select:none}}',
            $prefixer->prefixForTargets('@supports (user-select: none) { .foo { user-select: none; } }', ['firefox' => 68])
        );
        $t->same(
            '@supports (user-select:none){.foo{user-select:none}}',
            $prefixer->prefixForTargets('@supports (user-select: none) { .foo { user-select: none; } }', ['firefox' => 69])
        );
        $t->same(
            '@supports ((-webkit-print-color-adjust:exact) or (-moz-print-color-adjust:exact) or (print-color-adjust:exact)){.foo{-webkit-print-color-adjust:exact;-moz-print-color-adjust:exact;print-color-adjust:exact}}',
            $prefixer->prefixForTargets('@supports (print-color-adjust: exact) { .foo { print-color-adjust: exact; } }', ['chrome' => 135, 'firefox' => 96])
        );
        $t->same(
            '@supports ((-webkit-font-feature-settings:"kern") or (-moz-font-feature-settings:"kern") or (font-feature-settings:"kern")){.foo{-webkit-font-feature-settings:"kern";-moz-font-feature-settings:"kern";font-feature-settings:"kern"}}',
            $prefixer->prefixForTargets('@supports (font-feature-settings: "kern") { .foo { font-feature-settings: "kern"; } }', ['chrome' => 47, 'firefox' => 33])
        );
        $t->same(
            '@supports (font-feature-settings:"kern"){.foo{font-feature-settings:"kern"}}',
            $prefixer->prefixForTargets('@supports ((-webkit-font-feature-settings: "kern") or (-moz-font-feature-settings: "kern") or (font-feature-settings: "kern")) { .foo { -webkit-font-feature-settings: "kern"; -moz-font-feature-settings: "kern"; font-feature-settings: "kern"; } }', ['chrome' => 48, 'firefox' => 34])
        );
        $t->same(
            '@supports ((-webkit-font-variant-ligatures:no-common-ligatures) or (font-variant-ligatures:no-common-ligatures)){.foo{-webkit-font-variant-ligatures:no-common-ligatures;font-variant-ligatures:no-common-ligatures}}',
            $prefixer->prefixForTargets('@supports (font-variant-ligatures: no-common-ligatures) { .foo { font-variant-ligatures: no-common-ligatures; } }', ['android' => '4.4.3', 'samsung' => 4])
        );
        $t->same(
            '@supports (font-variant-ligatures:no-common-ligatures){.foo{font-variant-ligatures:no-common-ligatures}}',
            $prefixer->prefixForTargets('@supports ((-webkit-font-variant-ligatures: no-common-ligatures) or (font-variant-ligatures: no-common-ligatures)) { .foo { -webkit-font-variant-ligatures: no-common-ligatures; font-variant-ligatures: no-common-ligatures; } }', ['android' => '4.4.4', 'samsung' => 5])
        );
        $t->same(
            '@supports ((-moz-font-language-override:"SRB") or (font-language-override:"SRB")){.foo{-moz-font-language-override:"SRB";font-language-override:"SRB"}}',
            $prefixer->prefixForTargets('@supports (font-language-override: "SRB") { .foo { font-language-override: "SRB"; } }', ['firefox' => 33])
        );
        $t->same(
            '@supports (font-language-override:"SRB"){.foo{font-language-override:"SRB"}}',
            $prefixer->prefixForTargets('@supports ((-moz-font-language-override: "SRB") or (font-language-override: "SRB")) { .foo { -moz-font-language-override: "SRB"; font-language-override: "SRB"; } }', ['firefox' => 34])
        );
        $t->same(
            '@supports ((-webkit-font-kerning:normal) or (font-kerning:normal)){.foo{-webkit-font-kerning:normal;font-kerning:normal}}',
            $prefixer->prefixForTargets('@supports (font-kerning: normal) { .foo { font-kerning: normal; } }', ['safari' => 9])
        );
        $t->same(
            '@supports (font-kerning:normal){.foo{font-kerning:normal}}',
            $prefixer->prefixForTargets('@supports ((-webkit-font-kerning: normal) or (font-kerning: normal)) { .foo { -webkit-font-kerning: normal; font-kerning: normal; } }', ['safari' => 10])
        );
        $t->same(
            '@supports ((-webkit-clip-path:circle(50%)) or (clip-path:circle(50%))){.foo{-webkit-clip-path:circle(50%);clip-path:circle(50%)}}',
            $prefixer->prefixForTargets('@supports (clip-path: circle(50%)) { .foo { clip-path: circle(50%); } }', ['chrome' => 54])
        );
        $t->same(
            '@supports (clip-path:circle(50%)){.foo{clip-path:circle(50%)}}',
            $prefixer->prefixForTargets('@supports (clip-path: circle(50%)) { .foo { clip-path: circle(50%); } }', ['chrome' => 55])
        );
        $t->same(
            '@supports ((-ms-text-size-adjust:none) or (text-size-adjust:none)){.foo{-ms-text-size-adjust:none;text-size-adjust:none}}',
            $prefixer->prefixForTargets('@supports (text-size-adjust: none) { .foo { text-size-adjust: none; } }', ['ie' => 11])
        );
        $t->same(
            '@supports ((-webkit-hyphens:manual) or (hyphens:manual)){.foo{-webkit-hyphens:manual;hyphens:manual}}',
            $prefixer->prefixForTargets('@supports (hyphens: manual) { .foo { hyphens: manual; } }', ['safari' => $encoded(16, 5)])
        );
        $t->same(
            '@supports (hyphens:manual){.foo{hyphens:manual}}',
            $prefixer->prefixForTargets('@supports (hyphens: manual) { .foo { hyphens: manual; } }', ['safari' => 17])
        );
        $t->same(
            '@supports ((-webkit-backface-visibility:hidden) or (backface-visibility:hidden)){.foo{-webkit-backface-visibility:hidden;backface-visibility:hidden}}',
            $prefixer->prefixForTargets('@supports (backface-visibility: hidden) { .foo { backface-visibility: hidden; } }', ['safari' => $encoded(15, 2)])
        );
        $t->same(
            '@supports (backface-visibility:hidden){.foo{backface-visibility:hidden}}',
            $prefixer->prefixForTargets('@supports (backface-visibility: hidden) { .foo { backface-visibility: hidden; } }', ['safari' => $encoded(15, 3)])
        );
        $t->same(
            '@supports ((-webkit-text-decoration-style:dotted) or (text-decoration-style:dotted)){.foo{-webkit-text-decoration-style:dotted;text-decoration-style:dotted}}',
            $prefixer->prefixForTargets('@supports (text-decoration-style: dotted) { .foo { text-decoration-style: dotted; } }', ['safari' => 12])
        );
        $t->same(
            '@supports ((-webkit-text-decoration:underline) or (text-decoration:underline)){.foo{text-decoration:underline}}',
            $prefixer->prefixForTargets('@supports (text-decoration: underline) { .foo { text-decoration: underline; } }', ['safari' => 26])
        );
    },
    'transition prefixer maps upstream filter advanced color fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{-webkit-filter:drop-shadow(16px 16px 20px #b32323);filter:drop-shadow(16px 16px 20px #b32323);filter:drop-shadow(16px 16px 20px lab(40% 56.6 39))}',
            $prefixer->prefixForTargets('.foo { filter: drop-shadow(16px 16px 20px lab(40% 56.6 39)) }', ['chrome' => 20])
        );
        $t->same(
            '.foo{filter:var(--foo) drop-shadow(16px 16px 20px #b32323)}@supports (color:lab(0% 0 0)){.foo{filter:var(--foo) drop-shadow(16px 16px 20px lab(40% 56.6 39))}}',
            $prefixer->prefixForTargets('.foo { filter: var(--foo) drop-shadow(16px 16px 20px lab(40% 56.6 39)) }', ['chrome' => 4])
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
            '.foo{-moz-box-shadow:12px 12px #b32323;box-shadow:12px 12px #b32323;box-shadow:12px 12px lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { box-shadow: 12px 12px lab(40% 56.6 39) }', ['firefox' => (3 << 16) | (6 << 8)])
        );
        $t->same(
            '.foo{-webkit-box-shadow:12px 12px #b32323;-moz-box-shadow:12px 12px #b32323;box-shadow:12px 12px #b32323;box-shadow:12px 12px lab(40% 56.6 39)}',
            $prefixer->prefixForTargets('.foo { box-shadow: 12px 12px lab(40% 56.6 39) }', ['chrome' => 4, 'firefox' => (3 << 16) | (6 << 8)])
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
            '.foo{-moz-box-shadow:12px 12px #000;box-shadow:12px 12px #000}',
            $prefixer->prefixForTargets('.foo { -webkit-box-shadow: 12px 12px #000; -moz-box-shadow: 12px 12px #000; box-shadow: 12px 12px #000; }', ['firefox' => (3 << 16) | (6 << 8)])
        );
        $t->same(
            '.foo{-webkit-box-shadow:12px 12px #000;box-shadow:12px 12px #000}',
            $prefixer->prefixForTargets('.foo { -webkit-box-shadow: 12px 12px #000; -moz-box-shadow: 12px 12px #000; box-shadow: 12px 12px #000; }', ['chrome' => 4])
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
    'transition prefixer maps upstream text decoration longhand browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { text-decoration-line: underline; text-decoration-style: dotted; text-decoration-color: red; }';
        $webkitLonghands = '.foo{-webkit-text-decoration-line:underline;text-decoration-line:underline;-webkit-text-decoration-style:dotted;text-decoration-style:dotted;-webkit-text-decoration-color:red;text-decoration-color:red}';
        $mozLonghands = '.foo{-moz-text-decoration-line:underline;text-decoration-line:underline;-moz-text-decoration-style:dotted;text-decoration-style:dotted;-moz-text-decoration-color:red;text-decoration-color:red}';
        $modernLonghands = '.foo{text-decoration-line:underline;text-decoration-style:dotted;text-decoration-color:red}';

        $t->same($webkitLonghands, $prefixer->prefixForTargets($css, ['safari' => 12]));
        $t->same($modernLonghands, $prefixer->prefixForTargets($css, ['safari' => '12.1']));
        $t->same($webkitLonghands, $prefixer->prefixForTargets($css, ['ios_saf' => 12]));
        $t->same($modernLonghands, $prefixer->prefixForTargets($css, ['ios_saf' => '12.1']));
        $t->same($mozLonghands, $prefixer->prefixForTargets($css, ['firefox' => 35]));
        $t->same($modernLonghands, $prefixer->prefixForTargets($css, ['firefox' => 36]));
        $t->same($modernLonghands, $prefixer->prefixForTargets($css, ['safari' => 16]));
        $t->same($modernLonghands, $prefixer->prefixForTargets('.foo { -webkit-text-decoration-line: underline; text-decoration-line: underline; -webkit-text-decoration-style: dotted; text-decoration-style: dotted; -webkit-text-decoration-color: red; text-decoration-color: red; }', ['safari' => '12.1']));
        $t->same($modernLonghands, $prefixer->prefixForTargets('.foo { -moz-text-decoration-line: underline; text-decoration-line: underline; -moz-text-decoration-style: dotted; text-decoration-style: dotted; -moz-text-decoration-color: red; text-decoration-color: red; }', ['firefox' => 36]));
        $t->same(
            '.foo{-webkit-text-decoration:underline dotted;text-decoration:underline dotted}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline dotted; }', ['safari' => 16])
        );
    },
    'transition prefixer maps upstream text decoration thickness target fallbacks' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '.foo{text-decoration:underline;text-decoration-thickness:10px}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline 10px; }', ['safari' => 15])
        );
        $t->same(
            '.foo{text-decoration:underline 10px}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline 10px; }', ['chrome' => 90])
        );
        $t->same(
            '.foo{text-decoration:underline;text-decoration-thickness:calc(1em / 10)}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline 10%; }', ['safari' => 12])
        );
        $t->same(
            '.foo{text-decoration:underline 10%}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline 10%; }', ['firefox' => 89])
        );
        $t->same(
            '.foo{text-decoration-thickness:calc(1em / 10)}',
            $prefixer->prefixForTargets('.foo { text-decoration-thickness: 10%; }', ['safari' => 12])
        );
        $t->same(
            '.foo{text-decoration-thickness:10%}',
            $prefixer->prefixForTargets('.foo { text-decoration-thickness: 10%; }', ['firefox' => 89])
        );
        $t->same(
            '.foo{text-decoration:underline;text-decoration-thickness:10px}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline 10px; }', ['safari' => '26.1'])
        );
        $t->same(
            '.foo{text-decoration:underline 10px}',
            $prefixer->prefixForTargets('.foo { text-decoration: underline 10px; }', ['safari' => '26.2'])
        );
        $t->same(
            '.foo{text-decoration-thickness:calc(1em / 10)}',
            $prefixer->prefixForTargets('.foo { text-decoration-thickness: 10%; }', ['safari' => '17.3'])
        );
        $t->same(
            '.foo{text-decoration-thickness:10%}',
            $prefixer->prefixForTargets('.foo { text-decoration-thickness: 10%; }', ['safari' => '17.4'])
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
            '.foo{-webkit-text-emphasis-position:over;text-emphasis-position:over}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: right over; }', ['chrome' => 30, 'safari' => 10, 'firefox' => 45])
        );
        $t->same(
            '.foo{text-emphasis-position:over left}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: over left; }', ['chrome' => 30, 'safari' => 10, 'firefox' => 45])
        );
        $t->same(
            '.foo{text-emphasis-position:over left}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: left over; }', ['chrome' => 30, 'safari' => 10, 'firefox' => 45])
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
    'transition prefixer maps upstream linear-gradient browser boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $default = '.foo { background-image: linear-gradient(red, blue); }';
        $right = '.foo { background-image: linear-gradient(to right, red, blue); }';

        $t->same(
            '.foo{background-image:-webkit-gradient(linear,0 0,0 100%,from(red),to(#00f));background-image:-webkit-linear-gradient(top,red,#00f);background-image:linear-gradient(red,#00f)}',
            $prefixer->prefixForTargets($default, ['chrome' => 8])
        );
        $t->same(
            '.foo{background-image:-webkit-gradient(linear,0 0,100% 0,from(red),to(#00f));background-image:-webkit-linear-gradient(left,red,#00f);background-image:linear-gradient(to right,red,#00f)}',
            $prefixer->prefixForTargets($right, ['chrome' => 8])
        );
        $t->same(
            '.foo{background-image:-webkit-linear-gradient(top,red,#00f);background-image:linear-gradient(red,#00f)}',
            $prefixer->prefixForTargets($default, ['chrome' => 10])
        );
        $t->same(
            '.foo{background-image:linear-gradient(red,#00f)}',
            $prefixer->prefixForTargets($default, ['chrome' => 26])
        );
        $t->same(
            '.foo{background-image:-moz-linear-gradient(top,red,#00f);background-image:linear-gradient(red,#00f)}',
            $prefixer->prefixForTargets($default, ['firefox' => 15])
        );
        $t->same(
            '.foo{background-image:linear-gradient(red,#00f)}',
            $prefixer->prefixForTargets($default, ['firefox' => 16])
        );
        $t->same(
            '.foo{background-image:-o-linear-gradient(top,red,#00f);background-image:linear-gradient(red,#00f)}',
            $prefixer->prefixForTargets($default, ['opera' => 12])
        );
        $t->same(
            '.foo{background-image:linear-gradient(red,#00f)}',
            $prefixer->prefixForTargets($default, ['opera' => 13])
        );
        $t->same(
            '.foo{background-image:linear-gradient(red,#00f)}',
            $prefixer->prefixForTargets('.foo { background-image: -webkit-linear-gradient(top, red, blue); background-image: -moz-linear-gradient(top, red, blue); background-image: -o-linear-gradient(top, red, blue); background-image: linear-gradient(red, blue); }', ['chrome' => 95])
        );
        $t->same(
            '.foo{background-image:-webkit-linear-gradient(top,red,#00f);background-image:-moz-linear-gradient(top,red,#00f)}',
            $prefixer->prefixForTargets('.foo { background-image: -webkit-linear-gradient(top, red, blue); background-image: -moz-linear-gradient(top, red, blue); }', ['chrome' => 95])
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
        $t->same(
            '@-o-keyframes test{0%{opacity:0}to{opacity:1}}@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['opera' => 12])
        );
        $t->same(
            '@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['opera' => 13])
        );
        $t->same(
            '@-webkit-keyframes test{0%{opacity:0}to{opacity:1}}@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['opera' => 15])
        );
        $t->same(
            '@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@-o-keyframes test { from { opacity: 0 } to { opacity: 1 } } @keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['opera' => 13])
        );
        $t->same(
            '@-o-keyframes test{0%{opacity:0}to{opacity:1}}@keyframes test{0%{opacity:0}to{opacity:1}}',
            $prefixer->prefixForTargets('@-o-keyframes test { from { opacity: 0 } to { opacity: 1 } } @keyframes test { from { opacity: 0 } to { opacity: 1 } }', ['opera' => 12])
        );
    },
    'transition prefixer maps upstream animation declaration target prefixes' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $encoded = static fn (int $major, int $minor = 0, int $patch = 0): int => ($major << 16) | ($minor << 8) | $patch;

        $t->same(
            '.foo{-webkit-animation:.2s ease-in-out bar;-moz-animation:.2s ease-in-out bar;animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['firefox' => 6, 'safari' => 6])
        );
        $t->same(
            '.foo{animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { -webkit-animation: .2s ease-in-out bar; -moz-animation: .2s ease-in-out bar; animation: .2s ease-in-out bar; }', ['firefox' => 20, 'safari' => 14])
        );
        $t->same(
            '.foo{-webkit-animation:.2s var(--ease) bar;-moz-animation:.2s var(--ease) bar;animation:.2s var(--ease) bar}',
            $prefixer->prefixForTargets('.foo { animation: 200ms var(--ease) bar; }', ['firefox' => 6, 'safari' => 6])
        );
        $t->same(
            '.foo{-webkit-animation-name:bar;-moz-animation-name:bar;animation-name:bar;-webkit-animation-duration:.2s;-moz-animation-duration:.2s;animation-duration:.2s}',
            $prefixer->prefixForTargets('.foo { animation-name: bar; animation-duration: 200ms; }', ['firefox' => 6, 'safari' => 6])
        );
        $t->same(
            '.foo{-o-animation:.2s ease-in-out bar;animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['opera' => 12])
        );
        $t->same(
            '.foo{animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['opera' => 13])
        );
        $t->same(
            '.foo{-webkit-animation:.2s ease-in-out bar;animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['opera' => 29])
        );
        $t->same(
            '.foo{animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['opera' => 30])
        );
        $t->same(
            '.foo{-webkit-animation:.2s ease-in-out bar;animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['chrome' => 42])
        );
        $t->same(
            '.foo{animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['chrome' => 43])
        );
        $t->same(
            '.foo{-moz-animation:.2s ease-in-out bar;animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['firefox' => 15])
        );
        $t->same(
            '.foo{animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['firefox' => 16])
        );
        $t->same(
            '.foo{-webkit-animation:.2s ease-in-out bar;animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['safari' => $encoded(8)])
        );
        $t->same(
            '.foo{animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['safari' => $encoded(8, 1)])
        );
        $t->same(
            '.foo{-webkit-animation:.2s ease-in-out bar;animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['ios_saf' => $encoded(8, 1)])
        );
        $t->same(
            '.foo{animation:.2s ease-in-out bar}',
            $prefixer->prefixForTargets('.foo { animation: .2s ease-in-out bar; }', ['ios_saf' => $encoded(8, 2)])
        );
    },
    'transition prefixer maps upstream animation timeline shorthand target boundaries' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();
        $css = '.foo { animation: .2s ease-in-out bar scroll(); }';
        $fallback = '.foo{animation:.2s ease-in-out bar;animation-timeline:scroll()}';
        $modern = '.foo{animation:.2s ease-in-out bar scroll()}';

        $t->same($fallback, $prefixer->prefixForTargets($css, ['safari' => 16]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['chrome' => 120]));
        $t->same(
            '.foo{-webkit-animation:.2s ease-in-out bar;animation:.2s ease-in-out bar;animation-timeline:scroll()}',
            $prefixer->prefixForTargets($css, ['safari' => 6])
        );
        $t->same($fallback, $prefixer->prefixForTargets($css, ['chrome' => 114]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['chrome' => 115]));
        $t->same($fallback, $prefixer->prefixForTargets($css, ['opera' => 76]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['opera' => 77]));
        $t->same($fallback, $prefixer->prefixForTargets($css, ['samsung' => 22]));
        $t->same($modern, $prefixer->prefixForTargets($css, ['samsung' => 23]));
        $t->same($fallback, $prefixer->prefixForTargets($css, ['firefox' => 120]));
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
            '.foo{box-shadow:1px 1px #000}',
            $prefixer->prefixForTargets('.foo { box-shadow: 1px 1px #000; }', ['firefox' => $encoded(3, 4)])
        );
        $t->same(
            '.foo{-moz-box-shadow:1px 1px #000;box-shadow:1px 1px #000}',
            $prefixer->prefixForTargets('.foo { box-shadow: 1px 1px #000; }', ['firefox' => $encoded(3, 5)])
        );
        $t->same(
            '.foo{-moz-box-shadow:1px 1px #000;box-shadow:1px 1px #000}',
            $prefixer->prefixForTargets('.foo { box-shadow: 1px 1px #000; }', ['firefox' => $encoded(3, 6)])
        );
        $t->same(
            '.foo{box-shadow:1px 1px #000}',
            $prefixer->prefixForTargets('.foo { box-shadow: 1px 1px #000; }', ['firefox' => $encoded(3, 7)])
        );
        $t->same(
            '.foo{box-shadow:1px 1px #000}',
            $prefixer->prefixForTargets('.foo { box-shadow: 1px 1px #000; }', ['ios_saf' => $encoded(3, 1)])
        );
        $t->same(
            '.foo{-webkit-box-shadow:1px 1px #000;box-shadow:1px 1px #000}',
            $prefixer->prefixForTargets('.foo { box-shadow: 1px 1px #000; }', ['ios_saf' => $encoded(3, 2)])
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
            '.foo{-webkit-text-emphasis-position:over;text-emphasis-position:over}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: right over; }', ['chrome' => 98])
        );
        $t->same(
            '.foo{text-emphasis-position:over}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: right over; }', ['chrome' => 99])
        );
        $t->same(
            '.foo{-webkit-text-emphasis-position:under;text-emphasis-position:under}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: right under; }', ['safari' => 7])
        );
        $t->same(
            '.foo{text-emphasis-position:under}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: right under; }', ['safari' => 8])
        );
        $t->same(
            '.foo{text-emphasis-position:over left}',
            $prefixer->prefixForTargets('.foo { text-emphasis-position: left over; }', ['chrome' => 98, 'safari' => 7])
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
            '@layer blocks{@media (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 62])
        );
        $t->same(
            '@layer blocks{@media (width>=240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 63])
        );
        $t->same(
            '@layer blocks{@media (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 240px) { .wp-block-query { color: yellow; } } }', ['opera' => 70])
        );
        $t->same(
            '@layer blocks{@media (width>=240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 240px) { .wp-block-query { color: yellow; } } }', ['opera' => 71])
        );
        $t->same(
            '@layer blocks{@media (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 240px) { .wp-block-query { color: yellow; } } }', ['safari' => '16.3'])
        );
        $t->same(
            '@layer blocks{@media (width>=240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 240px) { .wp-block-query { color: yellow; } } }', ['safari' => '16.4'])
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
            '@layer blocks{@media not ((min-width:100px) and (max-width:200px)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media not (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media (hover) and (min-width:100px) and (max-width:200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (hover) and (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media (min-width:100px) and (max-width:200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 101])
        );
        $t->same(
            '@layer blocks{@media (100px<=width<=200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 102])
        );
        $t->same(
            '@layer blocks{@media (min-width:100px) and (max-width:200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['opera' => 70])
        );
        $t->same(
            '@layer blocks{@media (100px<=width<=200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['opera' => 71])
        );
        $t->same(
            '@layer blocks{@media (min-width:100px) and (max-width:200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['ios_saf' => '16.3'])
        );
        $t->same(
            '@layer blocks{@media (100px<=width<=200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['ios_saf' => '16.4'])
        );
        $t->same(
            '@layer blocks{@media (not (max-width:100px)) and (not (min-width:200px)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (100px < width < 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media not ((not (max-width:100px)) and (not (min-width:200px))){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media not (100px < width < 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media (max-width:200px) and (min-width:100px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (200px >= width >= 100px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media not (max-color:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (color > 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media not (min-color:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (color < 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media not (max-width:0){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width > 0) { .wp-block-query { color: yellow; } } }', ['chrome' => 85])
        );
        $t->same(
            '@layer blocks{@media (width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width = 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (240px = width) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (width=240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width = 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 64])
        );
        $t->same(
            '@layer blocks{@media not screen and not (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media not screen and (width < 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media only screen and (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media only screen and (width >= 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media only screen and (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media only screen and (not (width < 240px)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (hover) and (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (hover) and (not (width < 240px)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (hover) and (not ((min-width:200px) and (not (min-width:500px)))){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (hover) and (not (200px <= width < 500px)) { .wp-block-query { color: yellow; } } }', ['chrome' => 95])
        );
        $t->same(
            '@layer blocks{@media screen and not (max-width:max(10px,1rem)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media screen and (width > max(10px, 1rem)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media not (max-width:20px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width > max(10px, 20px)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min-width:15px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= clamp(10px, 15px, 20px)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media not (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media not (not (width < 240px)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media screen and not (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media screen and (not (not (width < 240px))) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (hover) and (not (min-width:240px)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (hover) and (not (not (width < 240px))) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media not (max-width:calc(1px + 1rem)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width > calc(1px + 1rem)) { .wp-block-query { color: yellow; } } }', ['chrome' => 85])
        );
        $t->same(
            '@layer blocks{@media (width>calc(1px + 1rem)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width > calc(1px + 1rem)) { .wp-block-query { color: yellow; } } }', ['firefox' => 64])
        );
        $t->same(
            '@layer blocks{@media (not (max-width:100px)) and (not (min-width:calc(100vw - 50px))){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (100px < width < calc(100vw - 50px)) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media all and (width >= 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (width>=240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media all and (width >= 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 64])
        );
        $t->same(
            '@layer blocks{@media (-webkit-device-pixel-ratio>=2),(resolution>=2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution >= 2dppx) { .wp-block-query { color: yellow; } } }', [
                'safari' => 15,
                'exclude' => ['MediaRangeSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (-webkit-device-pixel-ratio>2),(resolution>2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution > 2dppx) { .wp-block-query { color: yellow; } } }', [
                'safari' => 15,
                'exclude' => ['MediaRangeSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (-webkit-device-pixel-ratio=2),(-moz-device-pixel-ratio=2),(resolution=2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution = 2e0dppx) { .wp-block-query { color: yellow; } } }', [
                'safari' => 15,
                'firefox' => 10,
                'exclude' => ['MediaRangeSyntax'],
            ])
        );
        $t->same(
            '@media only screen and (-webkit-device-pixel-ratio>=1.3),only screen and (-moz-device-pixel-ratio>=1.3),only screen and (resolution>=124.8dpi){.foo{color:#ff0}}',
            $prefixer->prefixForTargets('@media only screen and (resolution >= 124.8dpi) { .foo { color: yellow; } }', [
                'safari' => 15,
                'firefox' => 10,
                'exclude' => ['MediaRangeSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (-webkit-device-pixel-ratio >= 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (-webkit-device-pixel-ratio >= calc(1 + 1)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (-webkit-device-pixel-ratio >= max(1, 2)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (-webkit-device-pixel-ratio >= 2e0) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media not (-webkit-max-device-pixel-ratio:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (-webkit-device-pixel-ratio > 2) { .wp-block-query { color: yellow; } } }', ['chrome' => 85])
        );
        $t->same(
            '@layer blocks{@media (-webkit-device-pixel-ratio>=2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (-webkit-min-device-pixel-ratio: 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 64])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2) and (-webkit-max-device-pixel-ratio:3){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (2 <= -webkit-device-pixel-ratio <= 3) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min--moz-device-pixel-ratio:2) and (max--moz-device-pixel-ratio:3){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (2 <= -moz-device-pixel-ratio <= 3) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min--moz-device-pixel-ratio:1) and (max--moz-device-pixel-ratio:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (1 <= -moz-device-pixel-ratio <= calc(1 + 1)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min-width:.5px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 0.5px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min-width:2px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (width>=2px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 64])
        );
        $t->same(
            '@layer blocks{@media (min-width:2px) and (max-width:4px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (2 <= width <= 4) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media (2px<=width<=4px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (2 <= width <= 4) { .wp-block-query { color: yellow; } } }', ['firefox' => 102])
        );
        $t->same(
            '@layer blocks{@media (min-width:.5px) and (max-width:1.5px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (0.5px <= width <= 1.50px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media (min-width:1000px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 1e3px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min-width:100px) and (max-width:200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (1e2px <= width <= 2e2px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
        $t->same(
            '@layer blocks{@media (width>=.5px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (width >= 0.5px) { .wp-block-query { color: yellow; } } }', ['firefox' => 64])
        );
        $t->same(
            '@layer blocks{@media (max-width:env(--branding-small 1,20px)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (max-width: env(--branding-small 1, 20px)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (width<=env(--branding-small 1,20px)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (max-width: env(--branding-small 1, 20px)) { .wp-block-query { color: yellow; } } }', ['firefox' => 64])
        );
        $t->same(
            '@layer blocks{@media (max-width:env(safe-area-inset-top)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (max-width: env(safe-area-inset-top)) { .wp-block-query { color: yellow; } } }', ['chrome' => 95])
        );
        $t->same(
            '@layer blocks{@media screen and (min-width:240px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media scr\\65 en and (w\\69 dth >= 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min-width:100px) and (max-width:200px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (100px <= w\\69 dth <= 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
        );
    },
    'transition prefixer maps upstream typed media range fallbacks inside layers' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@layer blocks{@media (min-aspect-ratio:16/9) and (not (max-color-index:2)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (aspect-ratio >= 16 / 9) and (color-index > 2) { .wp-block-query { color: yellow; } } }', ['chrome' => 85])
        );
        $t->same(
            '@layer blocks{@media (min-aspect-ratio:16/9){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (aspect-ratio >= 16e0 / 9e0) { .wp-block-query { color: yellow; } } }', ['chrome' => 85])
        );
        $t->same(
            '@layer blocks{@media ((min-monochrome:1) and (max-monochrome:4)) or (max-device-width:480px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (1 <= monochrome <= 4) or (device-width <= 480px) { .wp-block-query { color: yellow; } } }', [
                'include' => ['MediaRangeSyntax', 'MediaIntervalSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (horizontal-viewport-segments>=2) and (vertical-viewport-segments<3){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (horizontal-viewport-segments >= 2) and (vertical-viewport-segments < 3) { .wp-block-query { color: yellow; } } }', [
                'exclude' => ['MediaRangeSyntax'],
            ])
        );
    },
    'transition prefixer maps upstream unknown media range fallbacks inside layers' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@layer blocks{@media (min-theme-breakpoint:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (theme-breakpoint >= 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min-theme-breakpoint:100px){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (theme-breakpoint >= 1e2px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media ((min-theme-ratio:2) and (max-theme-ratio:3)) or (theme-state:expanded){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (2 / 1 <= theme-ratio <= 3 / 1) or (theme-state: expanded) { .wp-block-query { color: yellow; } } }', [
                'include' => ['MediaIntervalSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (theme-breakpoint>=2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (theme-breakpoint >= 2) { .wp-block-query { color: yellow; } } }', [
                'firefox' => 60,
                'exclude' => ['MediaRangeSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (theme-state:expanded){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (theme-state = expanded) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min-Theme-Breakpoint:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (Theme-Breakpoint >= 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media not (min-Theme-Breakpoint:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media not (Theme-Breakpoint >= 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min---WP-Breakpoint:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (--WP-Breakpoint >= 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media Speech and not (min---WP-Breakpoint:3){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media Speech and (not (--WP-Breakpoint >= 3)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media Speech and (min---WP-Breakpoint:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media Speech and (--WP-Breakpoint >= 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (Theme-Breakpoint>=2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (Theme-Breakpoint >= 2) { .wp-block-query { color: yellow; } } }', [
                'firefox' => 60,
                'exclude' => ['MediaRangeSyntax'],
            ])
        );
        $t->same(
            '@layer blocks{@media (--wp-breakpoint:env(--wp-breakpoint)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (--wp-breakpoint = env(--wp-breakpoint)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (min-theme-breakpoint:2){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (theme\\2d breakpoint >= 2) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media (theme-state:expanded){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (theme\\2d state = exp\\61 nded) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $prefixer->prefixForTargets('@layer blocks { @media (100px < width > 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $prefixer->prefixForTargets('@layer blocks { @media var(--theme-breakpoint) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $prefixer->prefixForTargets('@layer blocks { @media (width >= var(--theme-breakpoint)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $prefixer->prefixForTargets('@layer blocks { @media (color >= calc(1 + 1)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $prefixer->prefixForTargets('@layer blocks { @media (resolution >= calc(1 + 1dppx)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $prefixer->prefixForTargets('@layer blocks { @media (color >= 1e0) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $prefixer->prefixForTargets('@layer blocks { @media screen not (width >= 240px) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $prefixer->prefixForTargets('@layer blocks { @media print (100px <= width <= 200px) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
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
    'transition prefixer maps upstream negated grouped media ranges inside layers' => static function (TestRunner $t): void {
        $prefixer = new TransitionPrefixer();

        $t->same(
            '@layer blocks{@media not ((not (min-width:240px)) or (hover)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media not ((width < 240px) or (hover)) { .wp-block-query { color: yellow; } } }', ['firefox' => 60])
        );
        $t->same(
            '@layer blocks{@media not (((min-width:100px) and (max-width:200px)) or (hover)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media not ((100px <= width <= 200px) or (hover)) { .wp-block-query { color: yellow; } } }', ['firefox' => 85])
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
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['chrome' => 28])
        );
        $t->same(
            '@layer blocks{@media (min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['chrome' => 29])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['android' => '4.2'])
        );
        $t->same(
            '@layer blocks{@media (min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['android' => '4.3'])
        );
        $t->same(
            '@layer blocks{@media (min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['firefox' => '3.0'])
        );
        $t->same(
            '@layer blocks{@media (min--moz-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['firefox' => '3.5'])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['safari' => '15.6'])
        );
        $t->same(
            '@layer blocks{@media (min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['safari' => '15.7'])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2),(min--moz-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2e0dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
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
            '@layer blocks{@media (-webkit-device-pixel-ratio:2),(-moz-device-pixel-ratio:2),(resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution = 2dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media (resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution: 2dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@media only screen and (-webkit-device-pixel-ratio:1.3),only screen and (-moz-device-pixel-ratio:1.3),only screen and (resolution:124.8dpi){.foo{color:#ff0}}',
            $prefixer->prefixForTargets('@media only screen and (resolution = 124.8dpi) { .foo { color: yellow; } }', ['safari' => 15, 'firefox' => 10])
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
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2) and (-webkit-max-device-pixel-ratio:3),(min--moz-device-pixel-ratio:2) and (max--moz-device-pixel-ratio:3),(min-resolution:2dppx) and (max-resolution:3dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) and (max-resolution: 3dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media (not (-webkit-max-device-pixel-ratio:2)) and (not (-webkit-min-device-pixel-ratio:4)),(not (max-resolution:2dppx)) and (not (min-resolution:4dppx)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution > 2dppx) and (resolution < 4dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:.5),(min--moz-device-pixel-ratio:.5),(min-resolution:.5dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution >= 0.5dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media not (-webkit-max-device-pixel-ratio:.5),not (max-resolution:.5dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution > 0.5dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:.5) and (-webkit-max-device-pixel-ratio:1.5),(min--moz-device-pixel-ratio:.5) and (max--moz-device-pixel-ratio:1.5),(min-resolution:.5dppx) and (max-resolution:1.5dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (0.5dppx <= resolution <= 1.5dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media (.5x<=resolution<=1.5x){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (0.5dppx <= resolution <= 1.5dppx) { .wp-block-query { color: yellow; } } }', ['firefox' => 102])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:.5) and (-webkit-max-device-pixel-ratio:1.5),(min--moz-device-pixel-ratio:.5) and (max--moz-device-pixel-ratio:1.5),(min-resolution:.5dppx) and (max-resolution:1.5dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (5e-1dppx <= resolution <= 1.5e0dppx) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2) and (min-resolution:env(--wp-density-floor)),(min--moz-device-pixel-ratio:2) and (min-resolution:env(--wp-density-floor)),(min-resolution:2dppx) and (min-resolution:env(--wp-density-floor)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2dppx) and (min-resolution: env(--wp-density-floor)) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media (not (-webkit-max-device-pixel-ratio:2)) and (not (min-resolution:env(--wp-density-ceiling))),(not (max--moz-device-pixel-ratio:2)) and (not (min-resolution:env(--wp-density-ceiling))),(not (max-resolution:2dppx)) and (not (min-resolution:env(--wp-density-ceiling))){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution > 2dppx) and (resolution < env(--wp-density-ceiling)) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media (min-resolution:env(--wp-density-floor)){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: env(--wp-density-floor)) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@media only screen and (-webkit-min-device-pixel-ratio:.5),only screen and (min--moz-device-pixel-ratio:.5),only screen and (min-resolution:48dpi){.foo{color:#ff0}}',
            $prefixer->prefixForTargets('@media only screen and (min-resolution: 48dpi) { .foo { color: yellow; } }', ['safari' => 15, 'firefox' => 10])
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
        $t->same(
            '@layer blocks{@media (resolution:1dppx){.wp-block-query{background:red}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (resolution: 1x) { .wp-block-query { background: red; } } }', ['chrome' => 50])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:2),(min--moz-device-pixel-ratio:2),(min-resolution:2dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (min-resolution: 2x) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
        );
        $t->same(
            '@layer blocks{@media (-webkit-min-device-pixel-ratio:.5) and (-webkit-max-device-pixel-ratio:1.5),(min--moz-device-pixel-ratio:.5) and (max--moz-device-pixel-ratio:1.5),(min-resolution:.5dppx) and (max-resolution:1.5dppx){.wp-block-query{color:#ff0}}}',
            $prefixer->prefixForTargets('@layer blocks { @media (0.5x <= resolution <= 1.5x) { .wp-block-query { color: yellow; } } }', ['safari' => 15, 'firefox' => 10])
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
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 119])
        );
    },
    'wordpress sticky header filters get target-boundary WebKit prefixes without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-template-part.is-style-glass-header {
  backdrop-filter: blur(8px);
  filter: var(--wp--custom--header-filter);
}
CSS;

        $t->same(
            '.wp-block-template-part.is-style-glass-header{-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);-webkit-filter:var(--wp--custom--header-filter);filter:var(--wp--custom--header-filter)}',
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 52, 'safari' => 14])
        );
        $t->same(
            '.wp-block-template-part.is-style-glass-header{-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);filter:var(--wp--custom--header-filter)}',
            (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 53, 'safari' => 14])
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
