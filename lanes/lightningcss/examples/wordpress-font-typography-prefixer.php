<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-title.has-display-font {
  font-feature-settings: "kern";
  font-variant-ligatures: no-common-ligatures;
  font-language-override: "SRB";
  font-kerning: normal;
}

@supports (font-feature-settings: "kern") {
  .wp-block-post-title.has-display-font {
    font-feature-settings: "kern";
  }
}

@supports (font-kerning: normal) {
  .wp-block-post-title.has-display-font {
    font-kerning: normal;
  }
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, [
        'chrome' => 47,
        'firefox' => 33,
        'opera' => 34,
        'safari' => 9,
    ]),
    'modern_frontend' => $prefixer->prefixForTargets($css, [
        'chrome' => 48,
        'firefox' => 34,
        'opera' => 35,
        'safari' => 10,
    ]),
];

$expected = [
    'legacy_editor' => '.wp-block-post-title.has-display-font{-webkit-font-feature-settings:"kern";-moz-font-feature-settings:"kern";font-feature-settings:"kern";-webkit-font-variant-ligatures:no-common-ligatures;-moz-font-variant-ligatures:no-common-ligatures;font-variant-ligatures:no-common-ligatures;-webkit-font-language-override:"SRB";-moz-font-language-override:"SRB";font-language-override:"SRB";-webkit-font-kerning:normal;font-kerning:normal}@supports ((-webkit-font-feature-settings:"kern") or (-moz-font-feature-settings:"kern") or (font-feature-settings:"kern")){.wp-block-post-title.has-display-font{-webkit-font-feature-settings:"kern";-moz-font-feature-settings:"kern";font-feature-settings:"kern"}}@supports ((-webkit-font-kerning:normal) or (font-kerning:normal)){.wp-block-post-title.has-display-font{-webkit-font-kerning:normal;font-kerning:normal}}',
    'modern_frontend' => '.wp-block-post-title.has-display-font{font-feature-settings:"kern";font-variant-ligatures:no-common-ligatures;font-language-override:"SRB";font-kerning:normal}@supports (font-feature-settings:"kern"){.wp-block-post-title.has-display-font{font-feature-settings:"kern"}}@supports (font-kerning:normal){.wp-block-post-title.has-display-font{font-kerning:normal}}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected font typography prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
