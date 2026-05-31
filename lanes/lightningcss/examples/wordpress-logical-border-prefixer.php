<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-group.is-style-callout {
  border-block: 2px solid var(--wp--preset--color--contrast);
}

.wp-block-navigation .wp-block-navigation-item {
  border-inline-start: 4px solid var(--wp--preset--color--accent);
  border-inline-end: 1px solid var(--wp--preset--color--contrast);
}

.wp-block-separator.is-style-inline {
  border-inline-color: var(--wp--preset--color--accent);
}
CSS;

$actual = [
    'safari8' => $prefixer->prefixForTargets($css, ['safari' => 8]),
    'safari13' => $prefixer->prefixForTargets($css, ['safari' => 13]),
    'safari15' => $prefixer->prefixForTargets($css, ['safari' => 15]),
];

$expected = [
    'safari8' => '.wp-block-group.is-style-callout{border-top:2px solid var(--wp--preset--color--contrast);border-bottom:2px solid var(--wp--preset--color--contrast)}.wp-block-navigation .wp-block-navigation-item:not(:-webkit-any(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi))){border-left:4px solid var(--wp--preset--color--accent);border-right:1px solid var(--wp--preset--color--contrast)}.wp-block-navigation .wp-block-navigation-item:not(:is(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi))){border-left:4px solid var(--wp--preset--color--accent);border-right:1px solid var(--wp--preset--color--contrast)}.wp-block-navigation .wp-block-navigation-item:-webkit-any(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)){border-right:4px solid var(--wp--preset--color--accent);border-left:1px solid var(--wp--preset--color--contrast)}.wp-block-navigation .wp-block-navigation-item:is(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)){border-right:4px solid var(--wp--preset--color--accent);border-left:1px solid var(--wp--preset--color--contrast)}.wp-block-separator.is-style-inline{border-left-color:var(--wp--preset--color--accent);border-right-color:var(--wp--preset--color--accent)}',
    'safari13' => '.wp-block-group.is-style-callout{border-block-start:2px solid var(--wp--preset--color--contrast);border-block-end:2px solid var(--wp--preset--color--contrast)}.wp-block-navigation .wp-block-navigation-item{border-inline-start:4px solid var(--wp--preset--color--accent);border-inline-end:1px solid var(--wp--preset--color--contrast)}.wp-block-separator.is-style-inline{border-inline-start-color:var(--wp--preset--color--accent);border-inline-end-color:var(--wp--preset--color--accent)}',
    'safari15' => '.wp-block-group.is-style-callout{border-block:2px solid var(--wp--preset--color--contrast)}.wp-block-navigation .wp-block-navigation-item{border-inline-start:4px solid var(--wp--preset--color--accent);border-inline-end:1px solid var(--wp--preset--color--contrast)}.wp-block-separator.is-style-inline{border-inline-color:var(--wp--preset--color--accent)}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected logical border prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
