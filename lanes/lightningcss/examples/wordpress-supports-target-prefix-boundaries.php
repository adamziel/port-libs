<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();
$encoded = static fn (int $major, int $minor = 0): int => ($major << 16) | ($minor << 8);

$css = <<<'CSS'
@supports (appearance: none) {
  .wp-block-navigation .wp-block-navigation__responsive-container button {
    appearance: none;
    user-select: none;
  }
}

@supports (clip-path: circle(50%)) {
  .wp-block-cover.is-style-cropped {
    clip-path: circle(50%);
    backface-visibility: hidden;
  }
}

@supports (print-color-adjust: exact) {
  .wp-block-post-content .print-cover {
    print-color-adjust: exact;
  }
}
CSS;

$actual = [
    'safari15_2' => $prefixer->prefixForTargets($css, ['safari' => $encoded(15, 2)]),
    'safari15_3' => $prefixer->prefixForTargets($css, ['safari' => $encoded(15, 3)]),
    'chrome54' => $prefixer->prefixForTargets($css, ['chrome' => 54]),
    'chrome136' => $prefixer->prefixForTargets($css, ['chrome' => 136]),
];

$expected = [
    'safari15_2' => '@supports ((-webkit-appearance:none) or (appearance:none)){.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-appearance:none;appearance:none;-webkit-user-select:none;user-select:none}}@supports (clip-path:circle(50%)){.wp-block-cover.is-style-cropped{clip-path:circle(50%);-webkit-backface-visibility:hidden;backface-visibility:hidden}}@supports ((-webkit-print-color-adjust:exact) or (print-color-adjust:exact)){.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}}',
    'safari15_3' => '@supports (appearance:none){.wp-block-navigation .wp-block-navigation__responsive-container button{appearance:none;-webkit-user-select:none;user-select:none}}@supports (clip-path:circle(50%)){.wp-block-cover.is-style-cropped{clip-path:circle(50%);backface-visibility:hidden}}@supports (print-color-adjust:exact){.wp-block-post-content .print-cover{print-color-adjust:exact}}',
    'chrome54' => '@supports ((-webkit-appearance:none) or (appearance:none)){.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-appearance:none;appearance:none;user-select:none}}@supports ((-webkit-clip-path:circle(50%)) or (clip-path:circle(50%))){.wp-block-cover.is-style-cropped{-webkit-clip-path:circle(50%);clip-path:circle(50%);backface-visibility:hidden}}@supports ((-webkit-print-color-adjust:exact) or (print-color-adjust:exact)){.wp-block-post-content .print-cover{-webkit-print-color-adjust:exact;print-color-adjust:exact}}',
    'chrome136' => '@supports (appearance:none){.wp-block-navigation .wp-block-navigation__responsive-container button{appearance:none;user-select:none}}@supports (clip-path:circle(50%)){.wp-block-cover.is-style-cropped{clip-path:circle(50%);backface-visibility:hidden}}@supports (print-color-adjust:exact){.wp-block-post-content .print-cover{print-color-adjust:exact}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected supports target-prefix boundary output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
