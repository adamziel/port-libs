<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme;
@layer blocks {}
@layer utilities;

@layer blocks {
  .wp-block-query {
    color: red;
  }
}

@layer blocks {
  .wp-block-query {
    background: #fff;
  }

  @media all {
    .wp-block-query__empty {
      color: chartreuse;
    }
  }

  @media not all {
    .wp-block-query__debug {
      color: red;
    }
  }

  @media (min-width: 600px) and ((hover) and (color)) {
    .wp-block-query {
      color: yellow;
    }
  }
}
CSS;

echo (new CssMinifier())->minify($css) . PHP_EOL;
