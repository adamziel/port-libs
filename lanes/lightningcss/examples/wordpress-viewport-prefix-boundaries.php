<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@viewport {
  width: device-width;
  zoom: 1;
}

.wp-site-blocks {
  min-width: 320px;
}
CSS;

$prefixer = new TransitionPrefixer();

$actual = [
    'ie10' => $prefixer->prefixForTargets($css, ['ie' => 10]),
    'edge18' => $prefixer->prefixForTargets($css, ['edge' => 18]),
    'edge19' => $prefixer->prefixForTargets($css, ['edge' => 19]),
    'opera12_1' => $prefixer->prefixForTargets($css, ['opera' => '12.1']),
    'opera13' => $prefixer->prefixForTargets($css, ['opera' => 13]),
];

$expected = [
    'ie10' => '@-ms-viewport{width:device-width;zoom:1}@viewport{width:device-width;zoom:1}.wp-site-blocks{min-width:320px}',
    'edge18' => '@-ms-viewport{width:device-width;zoom:1}@viewport{width:device-width;zoom:1}.wp-site-blocks{min-width:320px}',
    'edge19' => '@viewport{width:device-width;zoom:1}.wp-site-blocks{min-width:320px}',
    'opera12_1' => '@-o-viewport{width:device-width;zoom:1}@viewport{width:device-width;zoom:1}.wp-site-blocks{min-width:320px}',
    'opera13' => '@viewport{width:device-width;zoom:1}.wp-site-blocks{min-width:320px}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected viewport target-prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
