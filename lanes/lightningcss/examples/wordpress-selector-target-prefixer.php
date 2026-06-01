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

.wp-block-button__link:hover,
.wp-block-button__link:focus-visible {
  outline-color: currentColor;
}

.wp-block-navigation__responsive-container-open:hover,
.wp-block-navigation__responsive-container-open:focus-visible {
  padding-inline: var(--wp--preset--spacing--20);
}
CSS;

$autofillSelectorListCss = <<<'CSS'
.wp-block-search__input:placeholder-shown,
.wp-block-search__input:autofill {
  color: var(--wp--preset--color--contrast);
}
CSS;

$androidSelectorCss = <<<'CSS'
.wp-block-navigation:is(.is-open, .has-modal-open) {
  color: currentColor;
}

.wp-block-comment-author:dir(rtl) {
  color: red;
}
CSS;

$actual = [
    'legacy_safari_firefox' => $prefixer->prefixForTargets($css, ['safari' => 11, 'firefox' => 50]),
    'safari14' => $prefixer->prefixForTargets($css, ['safari' => 14]),
    'safari17' => $prefixer->prefixForTargets($css, ['safari' => 17]),
    'android4_4_selector_boundary' => $prefixer->prefixForTargets($androidSelectorCss, ['android' => '4.4']),
    'android88_dir_boundary' => $prefixer->prefixForTargets($androidSelectorCss, ['android' => 88]),
    'android145_dir_boundary' => $prefixer->prefixForTargets($androidSelectorCss, ['android' => 145]),
    'samsung24_dir_boundary' => $prefixer->prefixForTargets($androidSelectorCss, ['samsung' => 24]),
    'samsung25_dir_boundary' => $prefixer->prefixForTargets($androidSelectorCss, ['samsung' => 25]),
    'chrome109_autofill_list' => $prefixer->prefixForTargets($autofillSelectorListCss, ['chrome' => 109]),
    'chrome110_autofill_list' => $prefixer->prefixForTargets($autofillSelectorListCss, ['chrome' => 110]),
];

$rtlLangs = ':lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)';
$rtlLangList = 'ae,ar,arc,bcc,bqi,ckb,dv,fa,glk,he,ku,mzn,nqo,pnb,ps,sd,ug,ur,yi';

$expected = [
    'legacy_safari_firefox' => '.wp-block-navigation:-webkit-any(.is-open,.has-modal-open){color:currentColor}.wp-block-navigation:-moz-any(.is-open,.has-modal-open){color:currentColor}.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:-webkit-any(' . $rtlLangs . '){color:red}.wp-block-comment-author:-moz-any(' . $rtlLangs . '){color:red}.wp-block-comment-author:is(' . $rtlLangs . '){color:red}.wp-block-cover:-webkit-full-screen{background:#000}.wp-block-cover:-moz-full-screen{background:#000}.wp-block-cover:fullscreen{background:#000}.wp-block-button__link:hover{outline-color:currentColor}.wp-block-button__link:focus-visible{outline-color:currentColor}.wp-block-navigation__responsive-container-open:hover{padding-left:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20)}.wp-block-navigation__responsive-container-open:focus-visible{padding-left:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20)}',
    'safari14' => '.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:lang(' . $rtlLangList . '){color:red}.wp-block-cover:-webkit-full-screen{background:#000}.wp-block-cover:fullscreen{background:#000}:is(.wp-block-button__link:hover,.wp-block-button__link:focus-visible){outline-color:currentColor}:is(.wp-block-navigation__responsive-container-open:hover,.wp-block-navigation__responsive-container-open:focus-visible){padding-inline-start:var(--wp--preset--spacing--20);padding-inline-end:var(--wp--preset--spacing--20)}',
    'safari17' => '.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:dir(rtl){color:red}.wp-block-cover:fullscreen{background:#000}.wp-block-button__link:hover,.wp-block-button__link:focus-visible{outline-color:currentColor}.wp-block-navigation__responsive-container-open:hover,.wp-block-navigation__responsive-container-open:focus-visible{padding-inline:var(--wp--preset--spacing--20)}',
    'android4_4_selector_boundary' => '.wp-block-navigation:-webkit-any(.is-open,.has-modal-open){color:currentColor}.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:-webkit-any(' . $rtlLangs . '){color:red}.wp-block-comment-author:is(' . $rtlLangs . '){color:red}',
    'android88_dir_boundary' => '.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:is(' . $rtlLangs . '){color:red}',
    'android145_dir_boundary' => '.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:dir(rtl){color:red}',
    'samsung24_dir_boundary' => '.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:is(' . $rtlLangs . '){color:red}',
    'samsung25_dir_boundary' => '.wp-block-navigation:is(.is-open,.has-modal-open){color:currentColor}.wp-block-comment-author:dir(rtl){color:red}',
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
