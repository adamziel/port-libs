<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();
$rtlLangs = ':lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)';

$css = <<<'CSS'
.wp-site-root {
  text-size-adjust: none;
  hyphens: manual;
  tab-size: 4;
}

.wp-block-post-title a {
  text-align-last: left;
  text-overflow: ellipsis;
}

.wp-block-post-navigation {
  text-align: start;
}

.wp-block-media-text {
  box-decoration-break: clone;
  position: sticky;
  top: 0;
}
CSS;

$actual = [
    'legacy_mobile_editor' => $prefixer->prefixForTargets($css, [
        'safari' => 8,
        'ios_saf' => 16,
        'firefox' => 40,
        'edge' => 15,
        'opera' => 12,
    ]),
    'modern_frontend' => $prefixer->prefixForTargets($css, [
        'chrome' => 130,
        'safari' => 17,
        'edge' => 79,
        'opera' => 14,
    ]),
];

$expected = [
    'legacy_mobile_editor' => '.wp-site-root{-webkit-text-size-adjust:none;-moz-text-size-adjust:none;-ms-text-size-adjust:none;text-size-adjust:none;-webkit-hyphens:manual;-moz-hyphens:manual;-ms-hyphens:manual;hyphens:manual;-moz-tab-size:4;-o-tab-size:4;tab-size:4}.wp-block-post-title a{-moz-text-align-last:left;text-align-last:left;-o-text-overflow:ellipsis;text-overflow:ellipsis}.wp-block-post-navigation:not(:is(' . $rtlLangs . ')){text-align:left}.wp-block-post-navigation:is(' . $rtlLangs . '){text-align:right}.wp-block-media-text{-webkit-box-decoration-break:clone;box-decoration-break:clone;position:-webkit-sticky;position:sticky;top:0}',
    'modern_frontend' => '.wp-site-root{text-size-adjust:none;hyphens:manual;tab-size:4}.wp-block-post-title a{text-align-last:left;text-overflow:ellipsis}.wp-block-post-navigation{text-align:start}.wp-block-media-text{-webkit-box-decoration-break:clone;box-decoration-break:clone;position:sticky;top:0}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected text compatibility prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
