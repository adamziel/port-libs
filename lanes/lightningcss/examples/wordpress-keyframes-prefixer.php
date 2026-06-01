<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@keyframes "wp-cover-reveal" {
  from {
    opacity: 0;
  }

  100% {
    opacity: 1;
  }
}

.wp-block-cover.is-style-reveal {
  animation: "wp-cover-reveal" 600ms ease-in both;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'legacy_safari_8' => $prefixer->prefixForTargets($css, ['safari' => 8]),
    'legacy_opera_12' => $prefixer->prefixForTargets($css, ['opera' => 12]),
    'modern_opera_13' => $prefixer->prefixForTargets($css, ['opera' => 13]),
];

$expected = [
    'legacy_safari_8' => '@-webkit-keyframes wp-cover-reveal{0%{opacity:0}to{opacity:1}}@keyframes wp-cover-reveal{0%{opacity:0}to{opacity:1}}.wp-block-cover.is-style-reveal{-webkit-animation:.6s ease-in both wp-cover-reveal;animation:.6s ease-in both wp-cover-reveal}',
    'legacy_opera_12' => '@-o-keyframes wp-cover-reveal{0%{opacity:0}to{opacity:1}}@keyframes wp-cover-reveal{0%{opacity:0}to{opacity:1}}.wp-block-cover.is-style-reveal{-o-animation:.6s ease-in both wp-cover-reveal;animation:.6s ease-in both wp-cover-reveal}',
    'modern_opera_13' => '@keyframes wp-cover-reveal{0%{opacity:0}to{opacity:1}}.wp-block-cover.is-style-reveal{animation:.6s ease-in both wp-cover-reveal}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected keyframes target prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
