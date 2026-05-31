<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssBundleException;
use PortLibs\LightningCSS\CssBundler;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$files = [
    '/theme.css' => <<<'CSS'
@charset "UTF-8";
/*! WP theme bundle license */
@import url("https://fonts.example/css2?family=Inter");
@layer reset, theme.blocks;
@import "tokens.css";
@import "blocks/card.css" layer(theme.blocks) screen and (--wp-wide);
@import "blocks/print.css" supports(print-color-adjust: exact) print;

.wp-site-blocks {
  color: red;
}
CSS,
    '/tokens.css' => <<<'CSS'
/*! Design token package license */
@custom-media --wp-wide (min-width: 782px);
:root {
  --wp--style--block-gap: 1.5rem;
}
CSS,
    '/blocks/card.css' => <<<'CSS'
@import "../shared/buttons.css";
@import "../shared/buttons-contrast.css" (prefers-contrast);
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
    '/shared/buttons-contrast.css' => <<<'CSS'
.wp-block-button__link {
  border-color: currentColor;
}
CSS,
];

echo (new CssBundler())->bundle('/theme.css', $files) . PHP_EOL;

try {
    (new CssBundler())->bundle('/broken-theme.css', [
        '/broken-theme.css' => '.wp-site-blocks { color: red } @import "tokens.css";',
        '/tokens.css' => ':root { --wp--style--block-gap: 1.5rem }',
    ]);

    fwrite(STDERR, "Expected late @import diagnostic for block-theme CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== '@import rules must precede all rules aside from @charset and @layer statements'
    ) {
        fwrite(STDERR, 'Unexpected late @import diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'late-import: rejected' . PHP_EOL;
}

$moduleBundle = (new CssBundler())->bundleCssModules('/modules/card.css', [
    '/modules/card.css' => <<<'CSS'
@import "../theme.css";

.card {
  composes: token missing-token from "../tokens.module.css";
  color: red;
  background: var(--card-bg from "../tokens.module.css");
}
CSS,
    '/tokens.module.css' => <<<'CSS'
.token {
  border-color: blue;
  --card-bg: blue;
}
CSS,
    '/theme.css' => '.theme { color: yellow }',
], null, [
    'hashes' => [
        '/modules/card.css' => 'card',
        '/tokens.module.css' => 'tok',
        '/theme.css' => 'theme',
    ],
    'dashedIdents' => true,
]);

if (
    $moduleBundle['code'] !== '.tok_token{border-color:#00f;--tok_card-bg:blue}.theme_theme{color:#ff0}.card_card{color:red;background:var(--tok_card-bg)}'
    || ($moduleBundle['exports']['card']['composes'][0]['name'] ?? null) !== 'tok_token'
) {
    fwrite(STDERR, "Unexpected CSS Modules bundle graph output\n");
    exit(1);
}

echo 'css-modules: dependency graph resolved' . PHP_EOL;
