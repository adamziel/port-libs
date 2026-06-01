<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-cover.has-accent-link {
  color: red;
  color: var(--wp--preset--color--accent, lab(40% 56.6 39));
}

.wp-block-button .wp-element-button {
  color: var(--wp--preset--color--contrast);
  color: lab(40% 56.6 39);
}

.wp-block-group.has-color-token-fallback {
  --wp--preset--color--accent: lab(40% 56.6 39);
  --wp--preset--color--brand: oklab(59.686% 0.1009 0.1192);
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'safari14' => $prefixer->prefixForTargets($css, ['safari' => 14]),
    'safari15' => $prefixer->prefixForTargets($css, ['safari' => 15]),
    'safari16' => $prefixer->prefixForTargets($css, ['safari' => 16]),
];

$expected = [
    'safari14' => '.wp-block-cover.has-accent-link{color:var(--wp--preset--color--accent,color(display-p3 .643308 .192455 .167712))}@supports (color:lab(0% 0 0)){.wp-block-cover.has-accent-link{color:var(--wp--preset--color--accent,lab(40% 56.6 39))}}.wp-block-button .wp-element-button{color:var(--wp--preset--color--contrast);color:lab(40% 56.6 39)}.wp-block-group.has-color-token-fallback{--wp--preset--color--accent:color(display-p3 .643308 .192455 .167712);--wp--preset--color--brand:color(display-p3 .724144 .386777 .148795)}@supports (color:lab(0% 0 0)){.wp-block-group.has-color-token-fallback{--wp--preset--color--accent:lab(40% 56.6 39);--wp--preset--color--brand:lab(52.2319% 40.1449 59.9171)}}',
    'safari15' => '.wp-block-cover.has-accent-link{color:red;color:var(--wp--preset--color--accent,lab(40% 56.6 39))}.wp-block-button .wp-element-button{color:var(--wp--preset--color--contrast);color:lab(40% 56.6 39)}.wp-block-group.has-color-token-fallback{--wp--preset--color--accent:lab(40% 56.6 39);--wp--preset--color--brand:lab(52.2319% 40.1449 59.9171)}',
    'safari16' => '.wp-block-cover.has-accent-link{color:var(--wp--preset--color--accent,lab(40% 56.6 39))}.wp-block-button .wp-element-button{color:lab(40% 56.6 39)}.wp-block-group.has-color-token-fallback{--wp--preset--color--accent:lab(40% 56.6 39);--wp--preset--color--brand:oklab(59.686% .1009 .1192)}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected advanced color var fallback output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
