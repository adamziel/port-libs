<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-button.is-style-animated {
  --wp--custom--motion-preset: ;
  transition: var(--wp--custom--duration, 200ms),
var(--wp--custom--easing, ease-in-out);
  color: var(--wp--preset--color--accent, rgb(var(--wp--custom--red), var(--wp--custom--green), 0));
  margin-top: calc(var(--wp--preset--spacing--40) / 2);
}
CSS;

$actual = (new CssMinifier())->minify($css);

$expected = '.wp-block-button.is-style-animated{--wp--custom--motion-preset: ;transition:var(--wp--custom--duration,200ms), var(--wp--custom--easing,ease-in-out);color:var(--wp--preset--color--accent,rgb(var(--wp--custom--red), var(--wp--custom--green), 0));margin-top:calc(var(--wp--preset--spacing--40) / 2)}';

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected custom property var fallback minifier output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual . PHP_EOL;
