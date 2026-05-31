<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssBundleException;
use PortLibs\LightningCSS\CssBundler;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$withTempFiles = static function (array $files, callable $callback): mixed {
    $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'lightningcss-wp-bundle-' . bin2hex(random_bytes(6));
    foreach ($files as $path => $css) {
        $target = $root . '/' . ltrim((string) $path, '/');
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create temporary theme directory {$directory}");
        }
        file_put_contents($target, (string) $css);
    }

    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $remove($path . DIRECTORY_SEPARATOR . $entry);
            }
            rmdir($path);
            return;
        }

        if (file_exists($path)) {
            unlink($path);
        }
    };

    try {
        return $callback($root);
    } finally {
        $remove($root);
    }
};

$files = [
    '/theme.css' => <<<'CSS'
@charset "UTF-8";
/*! WP theme bundle license */
@layer reset, theme.blocks;
@import url("https://fonts.example/css2?family=Inter");
@import "tokens.css";
@import "blocks/card.css" layer(theme.blocks) screen and (--wp-wide);
@import url(blocks/print.css) supports(print-color-adjust: exact) print;
@import "blocks/escaped\000020hero.css" layer(theme.blocks);

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
    '/blocks/escaped hero.css' => <<<'CSS'
.wp-block-cover {
  color: purple;
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

$themeBundle = (new CssBundler())->bundle('/theme.css', $files);
echo $themeBundle . PHP_EOL;
if (!str_contains($themeBundle, '.wp-block-cover{color:purple}')) {
    fwrite(STDERR, "Expected escaped import path to resolve in block-theme CSS\n");
    exit(1);
}

$mappedThemeBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', $files, null, '/');
$mappedThemeSources = $mappedThemeBundle['sourceMap']->toArray(null, false)['sources'];
if (
    $mappedThemeBundle['code'] !== $themeBundle
    || $mappedThemeSources !== [
        'theme.css',
        'tokens.css',
        'blocks/card.css',
        'shared/buttons.css',
        'shared/buttons-contrast.css',
        'blocks/print.css',
        'blocks/escaped hero.css',
    ]
) {
    fwrite(STDERR, "Expected source-map source collection to follow block-theme import graph\n");
    exit(1);
}

echo 'source-map-sources: collected' . PHP_EOL;

$generatedBlockMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/generated-card.scss'],
    'sourcesContent' => ['.wp-block-card { color: $theme-green }'],
    'names' => [],
], JSON_THROW_ON_ERROR));

$generatedSourceBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/generated-card.css"; .wp-site-blocks { color: red }',
    '/blocks/generated-card.css' => ".wp-block-card { color: green }\n/*# sourceMappingURL={$generatedBlockMap} */",
], null, '/');

if (
    $generatedSourceBundle['code'] !== '.wp-block-card{color:green}.wp-site-blocks{color:red}'
    || $generatedSourceBundle['sourceMap']->toArray(null, false)['sources'] !== [
        'theme.css',
        'blocks/generated-card.scss',
    ]
) {
    fwrite(STDERR, "Expected inline input source map to replace generated block CSS source\n");
    exit(1);
}

echo 'source-map-input: remapped' . PHP_EOL;

$sharedPresetBundle = (new CssBundler())->bundle('style.css', [
    'style.css' => <<<'CSS'
@import "../shared/presets.css";
.wp-site-blocks {
  color: red;
}
CSS,
    '../shared/presets.css' => <<<'CSS'
:root {
  --wp--preset--spacing--block-gap: 1rem;
}
CSS,
]);

if ($sharedPresetBundle !== ':root{--wp--preset--spacing--block-gap:1rem}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected parent-relative shared preset import to resolve\n");
    exit(1);
}

echo 'parent-relative-import: resolved' . PHP_EOL;

$escapedDelimiterBundle = (new CssBundler())->bundle('/escaped-delimiters.css', [
    '/escaped-delimiters.css' => <<<'CSS'
@import url(blocks/icon\).css);
@import url(blocks/icon\(.css);
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/icon).css' => '.wp-block-icon-close { color: blue; }',
    '/blocks/icon(.css' => '.wp-block-icon-open { color: green; }',
]);

if ($escapedDelimiterBundle !== '.wp-block-icon-close{color:#00f}.wp-block-icon-open{color:green}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected escaped url() delimiters to stay inside import paths\n");
    exit(1);
}

echo 'escaped-url-delimiters: resolved' . PHP_EOL;

$mediaBooleanBundle = (new CssBundler())->bundle('/entry.css', [
    '/entry.css' => '@import "print.css" layer(theme.blocks) print; .entry { color: red }',
    '/print.css' => '@import "wide.css" not screen and (width >= 240px); .wp-block-query { color: blue }',
    '/wide.css' => '.wp-block-query.is-wide { color: green }',
]);

if ($mediaBooleanBundle !== '@media print and (width>=240px){@layer theme.blocks{.wp-block-query.is-wide{color:green}}}@media print{@layer theme.blocks{.wp-block-query{color:#00f}}}.entry{color:red}') {
    fwrite(STDERR, "Unexpected layered media import boolean/range output\n");
    exit(1);
}

echo 'media-boolean-layer-range: simplified' . PHP_EOL;

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

try {
    (new CssBundler())->bundle('/layered-theme.css', [
        '/layered-theme.css' => '@layer theme.reset; @import "reset.css"; @layer theme.blocks; @import "blocks.css";',
        '/reset.css' => '.wp-site-blocks { margin: 0 }',
        '/blocks.css' => '.wp-block-query { color: green }',
    ]);

    fwrite(STDERR, "Expected post-import @layer diagnostic for block-theme CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== '@import rules must precede all rules aside from @charset and @layer statements'
    ) {
        fwrite(STDERR, 'Unexpected post-import @layer diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'post-import-layer: rejected' . PHP_EOL;
}

try {
    (new CssBundler())->bundle('/theme.css', [
        '/theme.css' => '@import "tokens.css"; .wp-site-blocks { color: red }',
        '/tokens.css' => ':root { --wp--style--block-gap: 1.5rem }',
    ], static fn (): array => ['file' => 1234]);

    fwrite(STDERR, "Expected malformed resolver diagnostic for block-theme CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'resolver-error'
        || $exception->getMessage() !== 'data did not match any variant of untagged enum ResolveResult'
        || $exception->sourceFile !== '/theme.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 1
    ) {
        fwrite(STDERR, 'Unexpected resolver diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'resolver-shape: rejected' . PHP_EOL;
}

$externalLayerMediaBundle = (new CssBundler())->bundle('/external-layer-media.css', [
    '/external-layer-media.css' => <<<'CSS'
@import "https://cdn.example/theme.css" supports(display: flex) layer;
@import "tokens.css";
.wp-site-blocks {
  color: red;
}
CSS,
    '/tokens.css' => ':root { --wp--preset--color--brand: blue; }',
]);

if ($externalLayerMediaBundle !== '@import "https://cdn.example/theme.css" supports(display:flex) layer;:root{--wp--preset--color--brand:blue}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Unexpected supports-layer media import output\n");
    exit(1);
}

echo 'supports-layer-media: preserved' . PHP_EOL;

$readerFiles = [
    '/reader-theme.css' => <<<'CSS'
@import "pkg:tokens.css";
@import "blocks/query.css";
.wp-site-blocks {
  color: red;
}
CSS,
    '/vendor/tokens.css' => ':root { --wp--preset--color--brand: blue; }',
    '/blocks/query.css' => '.wp-block-query { color: green; }',
];
$readerReads = [];
$readerBundle = (new CssBundler())->bundleWithReader(
    '/reader-theme.css',
    static function (string $file) use (&$readerReads, $readerFiles): string {
        $readerReads[] = $file;
        if (!array_key_exists($file, $readerFiles)) {
            throw new RuntimeException("Missing reader-backed theme file {$file}");
        }

        return $readerFiles[$file];
    },
    static function (string $specifier, string $originatingFile): string {
        if (str_starts_with($specifier, 'pkg:')) {
            return '/vendor/' . substr($specifier, strlen('pkg:'));
        }

        return rtrim(dirname($originatingFile), '/') . '/' . ltrim($specifier, './');
    }
);

if (
    $readerBundle !== ':root{--wp--preset--color--brand:blue}.wp-block-query{color:green}.wp-site-blocks{color:red}'
    || $readerReads !== ['/reader-theme.css', '/vendor/tokens.css', '/blocks/query.css']
) {
    fwrite(STDERR, "Unexpected reader-backed bundle graph output\n");
    exit(1);
}

echo 'reader-provider: resolved' . PHP_EOL;

$eofImportBundle = (new CssBundler())->bundleWithReader(
    '/reader-eof.css',
    static function (string $file): string {
        return match ($file) {
            '/reader-eof.css' => '@import "blocks/query.css"',
            '/blocks/query.css' => '.wp-block-query { color: green; }',
            default => throw new RuntimeException("Missing reader-backed theme file {$file}"),
        };
    }
);

if ($eofImportBundle !== '.wp-block-query{color:green}') {
    fwrite(STDERR, "Unexpected EOF import bundle graph output\n");
    exit(1);
}

echo 'reader-eof-import: resolved' . PHP_EOL;

$withTempFiles([
    'theme.css' => <<<'CSS'
@import "pkg:tokens.css";
@import "blocks/navigation.css";
.wp-site-blocks {
  color: red;
}
CSS,
    'vendor/tokens.css' => ':root { --wp--preset--color--brand: blue; }',
    'blocks/navigation.css' => '.wp-block-navigation { color: green; }',
], static function (string $root): void {
    $filesystemBundle = (new CssBundler())->bundleFile(
        $root . '/theme.css',
        static function (string $specifier, string $originatingFile) use ($root): string {
            if (str_starts_with($specifier, 'pkg:')) {
                return $root . '/vendor/' . substr($specifier, strlen('pkg:'));
            }

            return rtrim(dirname($originatingFile), '/') . '/' . ltrim($specifier, './');
        }
    );

    if ($filesystemBundle !== ':root{--wp--preset--color--brand:blue}.wp-block-navigation{color:green}.wp-site-blocks{color:red}') {
        fwrite(STDERR, "Unexpected filesystem-backed bundle graph output\n");
        exit(1);
    }
});

echo 'filesystem-provider: resolved' . PHP_EOL;

$moduleBundle = (new CssBundler())->bundleCssModules('/modules/card.css', [
    '/modules/card.css' => <<<'CSS'
@import "../tokens.module.css" supports(color: red);
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

try {
    (new CssBundler())->bundleCssModules('/modules/missing-card.css', [
        '/modules/missing-card.css' => <<<'CSS'
.wp-block-card {
  composes: token from "../missing-tokens.css";
  color: red;
}
CSS,
    ]);

    fwrite(STDERR, "Expected missing CSS Modules dependency diagnostic\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'resolver-error'
        || $exception->getMessage() !== 'Could not read `/missing-tokens.css`.'
        || $exception->sourceFile !== '/modules/missing-card.css'
        || $exception->sourceLine !== 2
        || $exception->sourceColumn !== 13
    ) {
        fwrite(STDERR, 'Unexpected CSS Modules dependency diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'css-modules-missing-dependency-location: rejected' . PHP_EOL;

$withTempFiles([
    'modules/card.css' => <<<'CSS'
@import "../base.css" layer(theme.blocks);

.wp-block-card {
  composes: token from "pkg:tokens.css";
  color: red;
}
CSS,
    'vendor/tokens.css' => <<<'CSS'
.token {
  color: blue;
}
CSS,
    'base.css' => '.wp-block-base { color: yellow; }',
], static function (string $root): void {
    $entry = $root . '/modules/card.css';
    $tokens = $root . '/vendor/tokens.css';
    $base = $root . '/base.css';
    $bundle = (new CssBundler())->bundleCssModulesFile(
        $entry,
        static function (string $specifier, string $originatingFile) use ($root): string {
            if (str_starts_with($specifier, 'pkg:')) {
                return $root . '/vendor/' . substr($specifier, strlen('pkg:'));
            }

            return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
        },
        [
            'hashes' => [
                $entry => 'card',
                $tokens => 'tok',
                $base => 'base',
            ],
        ]
    );

    if (
        $bundle['code'] !== '.tok_token{color:#00f}@layer theme.blocks{.base_wp-block-base{color:#ff0}}.card_wp-block-card{color:red}'
        || ($bundle['exports']['wp-block-card']['composes'][0]['name'] ?? null) !== 'tok_token'
    ) {
        fwrite(STDERR, "Unexpected file-backed CSS Modules bundle graph output\n");
        exit(1);
    }
});

echo 'css-modules-file: resolved' . PHP_EOL;

$rootedA = (new CssBundler())->bundleCssModules('/themes/one/blocks/card.css', [
    '/themes/one/blocks/card.css' => <<<'CSS'
@import "../tokens.css";
.card { color: white; }
CSS,
    '/themes/one/tokens.css' => '.tokens { color: yellow; }',
], null, [
    'projectRoot' => '/themes/one',
]);
$rootedB = (new CssBundler())->bundleCssModules('/sites/current/blocks/card.css', [
    '/sites/current/blocks/card.css' => <<<'CSS'
@import "../tokens.css";
.card { color: white; }
CSS,
    '/sites/current/tokens.css' => '.tokens { color: yellow; }',
], null, [
    'projectRoot' => '/sites/current',
]);

if (
    $rootedA['code'] !== $rootedB['code']
    || ($rootedA['exports']['card']['name'] ?? null) !== ($rootedB['exports']['card']['name'] ?? null)
) {
    fwrite(STDERR, "Unexpected project-root-stable CSS Modules bundle output\n");
    exit(1);
}

echo 'css-modules-project-root: stable' . PHP_EOL;
