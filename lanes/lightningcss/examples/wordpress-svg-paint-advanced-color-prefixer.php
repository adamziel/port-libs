<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$actual = [
    'duotoneIconPaint' => $prefixer->prefixForTargets(
        '.wp-block-icon svg .painted { fill: url(#brand-duotone) lch(50.998% 135.363 338); stroke: lch(50.998% 135.363 338); }',
        ['chrome' => 90, 'safari' => 14]
    ),
    'themeVariablePaint' => $prefixer->prefixForTargets(
        '.wp-block-icon svg .painted { fill: var(--wp--custom--svg-paint) lch(50.998% 135.363 338); }',
        ['chrome' => 90]
    ),
];

$expected = [
    'duotoneIconPaint' => '.wp-block-icon svg .painted{fill:url("#brand-duotone") #ee00be;fill:url("#brand-duotone") color(display-p3 .972962 -.362078 .804206);fill:url("#brand-duotone") lch(50.998% 135.363 338);stroke:#ee00be;stroke:color(display-p3 .972962 -.362078 .804206);stroke:lch(50.998% 135.363 338)}',
    'themeVariablePaint' => '.wp-block-icon svg .painted{fill:var(--wp--custom--svg-paint) #ee00be}@supports (color:lab(0% 0 0)){.wp-block-icon svg .painted{fill:var(--wp--custom--svg-paint) lab(50.998% 125.506 -50.7078)}}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected SVG paint advanced color prefixer output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . "\n";
