<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-query.is-style-archive-grid {
  margin-inline: var(--wp--preset--spacing--40) var(--wp--preset--spacing--30);
  padding-block-start: 1rem;
  padding-inline-start: var(--wp--preset--spacing--20);
}
CSS;

$actual = [
    'safari8' => $prefixer->prefixForTargets($css, ['safari' => 8]),
    'safari13' => $prefixer->prefixForTargets($css, ['safari' => 13]),
    'safari15' => $prefixer->prefixForTargets($css, ['safari' => 15]),
];

$rtlLangs = ':lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)';
$variants = static fn (string $selector): array => [
    'ltr-webkit' => $selector . ':not(:-webkit-any(' . $rtlLangs . '))',
    'ltr-modern' => $selector . ':not(:is(' . $rtlLangs . '))',
    'rtl-webkit' => $selector . ':-webkit-any(' . $rtlLangs . ')',
    'rtl-modern' => $selector . ':is(' . $rtlLangs . ')',
];
$query = $variants('.wp-block-query.is-style-archive-grid');

$expected = [
    'safari8' => $query['ltr-webkit'] . '{margin-left:var(--wp--preset--spacing--40);margin-right:var(--wp--preset--spacing--30);padding-top:1rem;padding-left:var(--wp--preset--spacing--20)}'
        . $query['ltr-modern'] . '{margin-left:var(--wp--preset--spacing--40);margin-right:var(--wp--preset--spacing--30);padding-top:1rem;padding-left:var(--wp--preset--spacing--20)}'
        . $query['rtl-webkit'] . '{margin-left:var(--wp--preset--spacing--30);margin-right:var(--wp--preset--spacing--40);padding-top:1rem;padding-right:var(--wp--preset--spacing--20)}'
        . $query['rtl-modern'] . '{margin-left:var(--wp--preset--spacing--30);margin-right:var(--wp--preset--spacing--40);padding-top:1rem;padding-right:var(--wp--preset--spacing--20)}',
    'safari13' => '.wp-block-query.is-style-archive-grid{margin-inline-start:var(--wp--preset--spacing--40);margin-inline-end:var(--wp--preset--spacing--30);padding-block-start:1rem;padding-inline-start:var(--wp--preset--spacing--20)}',
    'safari15' => '.wp-block-query.is-style-archive-grid{margin-inline:var(--wp--preset--spacing--40) var(--wp--preset--spacing--30);padding-block-start:1rem;padding-inline-start:var(--wp--preset--spacing--20)}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected logical spacing prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
