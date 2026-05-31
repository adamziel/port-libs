<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();
$encoded = static fn (int $major, int $minor = 0, int $patch = 0): int => ($major << 16) | ($minor << 8) | $patch;

$css = <<<'CSS'
.wp-block-cover.is-style-hero {
  background: image-set(url(hero.jpg) 2x);
  backdrop-filter: blur(5px);
}

.wp-block-post-content .print-cover {
  print-color-adjust: exact;
}

.wp-block-post-content mark.has-emphasis {
  text-emphasis-style: filled;
}

.wp-block-navigation .wp-block-navigation__responsive-container button {
  user-select: none;
  appearance: none;
}
CSS;

$actual = [
    'safari17_6' => $prefixer->prefixForTargets($css, ['safari' => $encoded(17, 6)]),
    'safari18' => $prefixer->prefixForTargets($css, ['safari' => 18]),
    'edge135' => $prefixer->prefixForTargets($css, ['edge' => 135]),
    'chrome53' => $prefixer->prefixForTargets($css, ['chrome' => 53]),
    'chrome98' => $prefixer->prefixForTargets($css, ['chrome' => 98]),
];

$expected = [
    'safari17_6' => '.wp-block-cover.is-style-hero{background:image-set("hero.jpg" 2x);-webkit-backdrop-filter:blur(5px);backdrop-filter:blur(5px)}.wp-block-post-content .print-cover{print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none;appearance:none}',
    'safari18' => '.wp-block-cover.is-style-hero{background:image-set("hero.jpg" 2x);backdrop-filter:blur(5px)}.wp-block-post-content .print-cover{print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none;appearance:none}',
    'edge135' => '.wp-block-cover.is-style-hero{background:image-set("hero.jpg" 2x);backdrop-filter:blur(5px)}.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{user-select:none;appearance:none}',
    'chrome53' => '.wp-block-cover.is-style-hero{background:-webkit-image-set(url("hero.jpg") 2x);background:image-set("hero.jpg" 2x);backdrop-filter:blur(5px)}.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{-webkit-text-emphasis-style:filled;text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none;-webkit-appearance:none;appearance:none}',
    'chrome98' => '.wp-block-cover.is-style-hero{background:-webkit-image-set(url("hero.jpg") 2x);background:image-set("hero.jpg" 2x);backdrop-filter:blur(5px)}.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{-webkit-text-emphasis-style:filled;text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{user-select:none;appearance:none}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected target-boundary prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
