<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-navigation:is(.is-open, .has-modal-open) {
  color: currentColor;
}

.wp-block-comment-author:dir(rtl) {
  color: red;
}

.wp-block-cover:fullscreen {
  background: black;
}
CSS;

$autofillSelectorListCss = <<<'CSS'
.wp-block-search__input:placeholder-shown,
.wp-block-search__input:autofill {
  color: var(--wp--preset--color--contrast);
}
CSS;

$actual = [
    'legacy_safari_firefox' => $prefixer->prefixForTargets($css, ['safari' => 11, 'firefox' => 50]),
    'safari14' => $prefixer->prefixForTargets($css, ['safari' => 14]),
    'safari17' => $prefixer->prefixForTargets($css, ['safari' => 17]),
    'chrome109_autofill_list' => $prefixer->prefixForTargets($autofillSelectorListCss, ['chrome' => 109]),
    'chrome110_autofill_list' => $prefixer->prefixForTargets($autofillSelectorListCss, ['chrome' => 110]),
];

$rtlLangs = ':lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)';
$rtlLangList = 'ae,ar,arc,bcc,bqi,ckb,dv,fa,glk,he,ku,mzn,nqo,pnb,ps,sd,ug,ur,yi';

$expected = [
    'legacy_safari_firefox' => '.wp-block-navigation:-webkit-any(.is-open,.has-modal-open){color:currentColor}.wp-block-navigation:-moz-any(.is-open,.has-modal-open){color:currentColor}.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:-webkit-any(' . $rtlLangs . '){color:red}.wp-block-comment-author:-moz-any(' . $rtlLangs . '){color:red}.wp-block-comment-author:is(' . $rtlLangs . '){color:red}.wp-block-cover:-webkit-full-screen{background:#000}.wp-block-cover:-moz-full-screen{background:#000}.wp-block-cover:fullscreen{background:#000}',
    'safari14' => '.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:lang(' . $rtlLangList . '){color:red}.wp-block-cover:-webkit-full-screen{background:#000}.wp-block-cover:fullscreen{background:#000}',
    'safari17' => '.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:dir(rtl){color:red}.wp-block-cover:fullscreen{background:#000}',
    'chrome109_autofill_list' => ':-webkit-any(.wp-block-search__input:placeholder-shown,.wp-block-search__input:-webkit-autofill){color:var(--wp--preset--color--contrast)}:is(.wp-block-search__input:placeholder-shown,.wp-block-search__input:autofill){color:var(--wp--preset--color--contrast)}',
    'chrome110_autofill_list' => '.wp-block-search__input:placeholder-shown,.wp-block-search__input:autofill{color:var(--wp--preset--color--contrast)}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected selector target-prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "selector target prefixer example self-test passed\n";
    return;
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
