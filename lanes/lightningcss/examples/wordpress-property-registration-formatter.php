<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssFormatter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.tokens {
  @property --wp--custom--card-accent { syntax: '<color>'; inherits: true; initial-value: blue; }
  @property --wp--custom--animation-duration {
    syntax: '<time>';
    inherits: false;
    initial-value: 250ms;
  }
}
CSS;

$expected = <<<'CSS'
@layer theme.tokens {
  @property --wp--custom--card-accent {
    syntax: "<color>";
    inherits: true;
    initial-value: blue;
  }

  @property --wp--custom--animation-duration {
    syntax: "<time>";
    inherits: false;
    initial-value: 250ms;
  }
}

CSS;

$actual = (new CssFormatter())->format($css);

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected formatted @property CSS:\n{$actual}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual;
