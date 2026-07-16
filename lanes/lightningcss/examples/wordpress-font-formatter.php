<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssFormatter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-title {
  font-family: "Inter", "Noto Serif", serif;
  font-size: 1.5rem;
  font-weight: bold;
  font-style: italic;
  font-stretch: expanded;
  font-variant-caps: small-caps;
  line-height: 1.2;
}

.wp-block-navigation {
  font: 16px "Helvetica Neue", Arial, sans-serif;
  line-height: 1.5;
}

.wp-block-quote {
  font: 16px "Helvetica Neue", Georgia, serif;
  line-height: var(--wp--custom--line-height);
}
CSS;

$expected = <<<'CSS'
.wp-block-post-title {
  font: italic small-caps bold expanded 1.5rem / 1.2 Inter, Noto Serif, serif;
}

.wp-block-navigation {
  font: 16px / 1.5 Helvetica Neue, Arial, sans-serif;
}

.wp-block-quote {
  font: 16px Helvetica Neue, Georgia, serif;
  line-height: var(--wp--custom--line-height);
}

CSS;

$actual = (new CssFormatter())->format($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected formatted CSS:\n" . $actual . "\n");
    exit(1);
}

echo $actual;
