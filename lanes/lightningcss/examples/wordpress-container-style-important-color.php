<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-button {
  container-name: button-tone;
  container-type: inline-size;
}

@container button-tone style(color: yellow !important) {
  .wp-element-button {
    outline-color: yellow;
  }
}

@container button-tone style(color: yellow) {
  .wp-element-button.is-accent {
    accent-color: yellow;
  }
}
CSS;

$expected = '.wp-block-button{container:button-tone/inline-size}@container button-tone style(color:yellow){.wp-element-button{outline-color:#ff0}}@container button-tone style(color:#ff0){.wp-element-button.is-accent{accent-color:#ff0}}';
$actual = (new CssMinifier())->minify($css);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
