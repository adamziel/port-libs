<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@keyframes wp-duotone-pulse {
  from { --wp--preset--color--duotone: lab(40% 56.6 39); opacity: 0; }
  to { --wp--preset--color--duotone: lch(50.998% 135.363 338); opacity: 1; }
}

.wp-block-cover.is-style-duotone-pulse {
  animation: wp-duotone-pulse 600ms ease-out;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome90' => $prefixer->prefixForTargets($css, ['chrome' => 90]),
    'legacyWideGamut' => $prefixer->prefixForTargets($css, ['chrome' => 90, 'safari' => 14]),
];

$expected = [
    'chrome90' => '@keyframes wp-duotone-pulse{0%{--wp--preset--color--duotone:#b32323;opacity:0}to{--wp--preset--color--duotone:#ee00be;opacity:1}}@supports (color:lab(0% 0 0)){@keyframes wp-duotone-pulse{0%{--wp--preset--color--duotone:lab(40% 56.6 39);opacity:0}to{--wp--preset--color--duotone:lab(50.998% 125.506 -50.7078);opacity:1}}}.wp-block-cover.is-style-duotone-pulse{animation:.6s ease-out wp-duotone-pulse}',
    'legacyWideGamut' => '@keyframes wp-duotone-pulse{0%{--wp--preset--color--duotone:#b32323;opacity:0}to{--wp--preset--color--duotone:#ee00be;opacity:1}}@supports (color:color(display-p3 0 0 0)){@keyframes wp-duotone-pulse{0%{--wp--preset--color--duotone:color(display-p3 .643308 .192455 .167712);opacity:0}to{--wp--preset--color--duotone:color(display-p3 .972962 -.362078 .804206);opacity:1}}}@supports (color:lab(0% 0 0)){@keyframes wp-duotone-pulse{0%{--wp--preset--color--duotone:lab(40% 56.6 39);opacity:0}to{--wp--preset--color--duotone:lab(50.998% 125.506 -50.7078);opacity:1}}}.wp-block-cover.is-style-duotone-pulse{animation:.6s ease-out wp-duotone-pulse}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected keyframes advanced color fallback output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
