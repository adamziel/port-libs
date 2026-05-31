<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-cover.is-style-magazine {
  background-image: url(hero.jpg);
  background-size: cover;
  background-origin: content-box;
}
CSS;

$actual = [
    'android2_3' => $prefixer->prefixForTargets($css, ['android' => '2.3']),
    'firefox3_6' => $prefixer->prefixForTargets($css, ['firefox' => '3.6']),
    'opera10' => $prefixer->prefixForTargets($css, ['opera' => 10]),
    'modern' => $prefixer->prefixForTargets($css, ['android' => 3, 'firefox' => 4, 'opera' => '10.1']),
];

$expected = [
    'android2_3' => '.wp-block-cover.is-style-magazine{background-image:url(hero.jpg);-webkit-background-size:cover;background-size:cover;-webkit-background-origin:content-box;background-origin:content-box}',
    'firefox3_6' => '.wp-block-cover.is-style-magazine{background-image:url(hero.jpg);-moz-background-size:cover;background-size:cover;-moz-background-origin:content-box;background-origin:content-box}',
    'opera10' => '.wp-block-cover.is-style-magazine{background-image:url(hero.jpg);-o-background-size:cover;background-size:cover;-o-background-origin:content-box;background-origin:content-box}',
    'modern' => '.wp-block-cover.is-style-magazine{background-image:url(hero.jpg);background-size:cover;background-origin:content-box}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected background size/origin prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
