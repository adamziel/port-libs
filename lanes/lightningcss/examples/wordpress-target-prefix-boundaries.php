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
  transition-property: backdrop-filter;
}

.wp-block-cover.is-style-cropped {
  clip-path: circle(50px);
}

.wp-block-cover.is-style-flip-card {
  transform: rotateY(180deg);
  transform-origin: 0 0;
  perspective: 800px;
  backface-visibility: hidden;
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

$iosUserSelectCss = <<<'CSS'
.wp-block-navigation .wp-block-navigation__responsive-container button {
  user-select: none;
}
CSS;

$actual = [
    'safari15_2' => $prefixer->prefixForTargets($css, ['safari' => $encoded(15, 2)]),
    'safari15_3' => $prefixer->prefixForTargets($css, ['safari' => $encoded(15, 3)]),
    'safari17_6' => $prefixer->prefixForTargets($css, ['safari' => $encoded(17, 6)]),
    'safari18' => $prefixer->prefixForTargets($css, ['safari' => 18]),
    'edge135' => $prefixer->prefixForTargets($css, ['edge' => 135]),
    'chrome35' => $prefixer->prefixForTargets($css, ['chrome' => 35]),
    'chrome53' => $prefixer->prefixForTargets($css, ['chrome' => 53]),
    'chrome98' => $prefixer->prefixForTargets($css, ['chrome' => 98]),
    'ios3_1_user_select' => $prefixer->prefixForTargets($iosUserSelectCss, ['ios_saf' => '3.1']),
    'ios3_2_user_select' => $prefixer->prefixForTargets($iosUserSelectCss, ['ios_saf' => '3.2']),
];

$expected = [
    'safari15_2' => '.wp-block-cover.is-style-hero{background:image-set("hero.jpg" 2x);-webkit-backdrop-filter:blur(5px);backdrop-filter:blur(5px);transition-property:-webkit-backdrop-filter,backdrop-filter}.wp-block-cover.is-style-cropped{clip-path:circle(50px)}.wp-block-cover.is-style-flip-card{transform:rotateY(180deg);transform-origin:0 0;perspective:800px;-webkit-backface-visibility:hidden;backface-visibility:hidden}.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none;-webkit-appearance:none;appearance:none}',
    'safari15_3' => '.wp-block-cover.is-style-hero{background:image-set("hero.jpg" 2x);-webkit-backdrop-filter:blur(5px);backdrop-filter:blur(5px);transition-property:-webkit-backdrop-filter,backdrop-filter}.wp-block-cover.is-style-cropped{clip-path:circle(50px)}.wp-block-cover.is-style-flip-card{transform:rotateY(180deg);transform-origin:0 0;perspective:800px;backface-visibility:hidden}.wp-block-post-content .print-cover{print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none;appearance:none}',
    'safari17_6' => '.wp-block-cover.is-style-hero{background:image-set("hero.jpg" 2x);-webkit-backdrop-filter:blur(5px);backdrop-filter:blur(5px);transition-property:-webkit-backdrop-filter,backdrop-filter}.wp-block-cover.is-style-cropped{clip-path:circle(50px)}.wp-block-cover.is-style-flip-card{transform:rotateY(180deg);transform-origin:0 0;perspective:800px;backface-visibility:hidden}.wp-block-post-content .print-cover{print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none;appearance:none}',
    'safari18' => '.wp-block-cover.is-style-hero{background:image-set("hero.jpg" 2x);backdrop-filter:blur(5px);transition-property:backdrop-filter}.wp-block-cover.is-style-cropped{clip-path:circle(50px)}.wp-block-cover.is-style-flip-card{transform:rotateY(180deg);transform-origin:0 0;perspective:800px;backface-visibility:hidden}.wp-block-post-content .print-cover{print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none;appearance:none}',
    'edge135' => '.wp-block-cover.is-style-hero{background:image-set("hero.jpg" 2x);backdrop-filter:blur(5px);transition-property:backdrop-filter}.wp-block-cover.is-style-cropped{clip-path:circle(50px)}.wp-block-cover.is-style-flip-card{transform:rotateY(180deg);transform-origin:0 0;perspective:800px;backface-visibility:hidden}.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{user-select:none;appearance:none}',
    'chrome35' => '.wp-block-cover.is-style-hero{background:-webkit-image-set(url("hero.jpg") 2x);background:image-set("hero.jpg" 2x);backdrop-filter:blur(5px);transition-property:backdrop-filter}.wp-block-cover.is-style-cropped{-webkit-clip-path:circle(50px);clip-path:circle(50px)}.wp-block-cover.is-style-flip-card{-webkit-transform:rotateY(180deg);transform:rotateY(180deg);-webkit-transform-origin:0 0;transform-origin:0 0;-webkit-perspective:800px;perspective:800px;-webkit-backface-visibility:hidden;backface-visibility:hidden}.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{-webkit-text-emphasis-style:filled;text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none;-webkit-appearance:none;appearance:none}',
    'chrome53' => '.wp-block-cover.is-style-hero{background:-webkit-image-set(url("hero.jpg") 2x);background:image-set("hero.jpg" 2x);backdrop-filter:blur(5px);transition-property:backdrop-filter}.wp-block-cover.is-style-cropped{-webkit-clip-path:circle(50px);clip-path:circle(50px)}.wp-block-cover.is-style-flip-card{transform:rotateY(180deg);transform-origin:0 0;perspective:800px;backface-visibility:hidden}.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{-webkit-text-emphasis-style:filled;text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none;-webkit-appearance:none;appearance:none}',
    'chrome98' => '.wp-block-cover.is-style-hero{background:-webkit-image-set(url("hero.jpg") 2x);background:image-set("hero.jpg" 2x);backdrop-filter:blur(5px);transition-property:backdrop-filter}.wp-block-cover.is-style-cropped{clip-path:circle(50px)}.wp-block-cover.is-style-flip-card{transform:rotateY(180deg);transform-origin:0 0;perspective:800px;backface-visibility:hidden}.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}.wp-block-post-content mark.has-emphasis{-webkit-text-emphasis-style:filled;text-emphasis-style:filled}.wp-block-navigation .wp-block-navigation__responsive-container button{user-select:none;appearance:none}',
    'ios3_1_user_select' => '.wp-block-navigation .wp-block-navigation__responsive-container button{user-select:none}',
    'ios3_2_user_select' => '.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected target-boundary prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
