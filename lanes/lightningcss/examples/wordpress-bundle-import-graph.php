<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssBundler;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$files = [
    '/theme.css' => <<<'CSS'
@layer reset, theme.blocks;
@import "tokens.css";
@import "blocks/card.css" layer(theme.blocks) screen and (--wp-wide);
@import "blocks/print.css" supports(print-color-adjust: exact) print;

.wp-site-blocks {
  color: red;
}
CSS,
    '/tokens.css' => <<<'CSS'
@custom-media --wp-wide (min-width: 782px);
:root {
  --wp--style--block-gap: 1.5rem;
}
CSS,
    '/blocks/card.css' => <<<'CSS'
@import "../shared/buttons.css";
.wp-block-query {
  color: green;
}
CSS,
    '/blocks/print.css' => <<<'CSS'
.wp-block-post-content {
  color: black;
}
CSS,
    '/shared/buttons.css' => <<<'CSS'
.wp-block-button__link {
  color: blue;
}
CSS,
];

echo (new CssBundler())->bundle('/theme.css', $files) . PHP_EOL;
