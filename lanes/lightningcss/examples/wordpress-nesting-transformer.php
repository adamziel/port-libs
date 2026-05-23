<?php

declare(strict_types=1);

use PortLibs\LightningCSS\NestingTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-query {
  color: blue;

  .wp-block-post-title {
    color: red;

    .is-featured & {
      opacity: .9;
    }
  }

  &:hover .wp-block-post-title {
    text-decoration-color: yellow;
  }

  @media (min-width: 600px) {
    .wp-block-post-title {
      color: blue;
    }
  }

  &article > .wp-block-post-title {
    margin-block-start: 0;
  }

  @supports (display: grid) {
    & > .wp-block-post-template {
      display: grid;
    }
  }

  @container (min-width: 320px) {
    &article > .wp-block-post-title {
      font-size: clamp(1.25rem, 2cqw, 2rem);
    }
  }
}
CSS;

echo (new NestingTransformer())->lower($css) . PHP_EOL;
