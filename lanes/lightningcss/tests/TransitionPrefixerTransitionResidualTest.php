<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;
use PortLibs\LightningCSS\TransitionPrefixer;

$rtlLangs = ':lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)';
$directionSelectors = static fn (string $selector): array => [
    'ltr-webkit' => $selector . ':not(:-webkit-any(' . $rtlLangs . '))',
    'ltr-modern' => $selector . ':not(:is(' . $rtlLangs . '))',
    'rtl-webkit' => $selector . ':-webkit-any(' . $rtlLangs . ')',
    'rtl-modern' => $selector . ':is(' . $rtlLangs . ')',
];

return [
    'transition residual keeps prefixed duration tail after shorthand' => static function (TestRunner $t): void {
        $t->same(
            '.foo{transition:opacity 2s;-webkit-transition-duration:2s}',
            (new CssMinifier())->minify('.foo { transition: opacity 2s; -webkit-transition-duration: 2s; }'),
            'upstream src/lib.rs::test_transitions line 11743'
        );
    },
    'transition residual maps exact inline shorthand pair prefix row' => static function (TestRunner $t) use ($directionSelectors): void {
        $selector = $directionSelectors('.foo');

        $expected = $selector['ltr-webkit'] . '{transition:margin-left 2s,padding-left 2s}'
            . $selector['ltr-modern'] . '{transition:margin-left 2s,padding-left 2s}'
            . $selector['rtl-webkit'] . '{transition:margin-right 2s,padding-right 2s}'
            . $selector['rtl-modern'] . '{transition:margin-right 2s,padding-right 2s}';

        $t->same(
            $expected,
            (new TransitionPrefixer())->prefixForTargets(
                '.foo { transition: margin-inline-start 2s, padding-inline-start 2s; }',
                ['safari' => 8]
            ),
            'upstream src/lib.rs::test_transitions line 11896'
        );
    },
];
