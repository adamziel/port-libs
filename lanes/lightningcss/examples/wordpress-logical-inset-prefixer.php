<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-cover .wp-block-cover__background {
  inset: 0;
}

.wp-block-cover.alignwide .wp-block-cover__inner-container {
  inset-block: var(--wp--preset--spacing--40);
  inset-inline: 24px 32px;
}
CSS;

$actual = [
    'safari14_0' => $prefixer->prefixForTargets($css, ['safari' => '14.0']),
    'safari14_1' => $prefixer->prefixForTargets($css, ['safari' => '14.1']),
];

$expected = [
    'safari14_0' => '.wp-block-cover .wp-block-cover__background{top:0;bottom:0;left:0;right:0}.wp-block-cover.alignwide .wp-block-cover__inner-container:not(:-webkit-any(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi))){top:var(--wp--preset--spacing--40);bottom:var(--wp--preset--spacing--40);left:24px;right:32px}.wp-block-cover.alignwide .wp-block-cover__inner-container:not(:is(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi))){top:var(--wp--preset--spacing--40);bottom:var(--wp--preset--spacing--40);left:24px;right:32px}.wp-block-cover.alignwide .wp-block-cover__inner-container:-webkit-any(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)){top:var(--wp--preset--spacing--40);bottom:var(--wp--preset--spacing--40);left:32px;right:24px}.wp-block-cover.alignwide .wp-block-cover__inner-container:is(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)){top:var(--wp--preset--spacing--40);bottom:var(--wp--preset--spacing--40);left:32px;right:24px}',
    'safari14_1' => '.wp-block-cover .wp-block-cover__background{inset:0}.wp-block-cover.alignwide .wp-block-cover__inner-container{inset-block:var(--wp--preset--spacing--40);inset-inline:24px 32px}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected logical inset prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
