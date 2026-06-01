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
    'mappings' => 'ACAA',
    'sources' => ['blocks/_tokens.scss', 'blocks/generated-card.scss'],
    'sourcesContent' => ['$theme-green: green;', '.wp-block-card { color: $theme-green }'],
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
        'blocks/_tokens.scss',
        'blocks/generated-card.scss',
    ]
) {
    fwrite(STDERR, "Expected inline input source map to replace generated block CSS source\n");
    exit(1);
}

echo 'source-map-input: remapped' . PHP_EOL;
echo 'source-map-input-unused: preserved' . PHP_EOL;

$literalSourceMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/generated-content.scss'],
    'sourcesContent' => ['.wp-block-button__link::before { content: $label; }'],
    'names' => [],
], JSON_THROW_ON_ERROR));
$literalSourceCss = '.wp-block-button__link::before { content: "/*# sourceMappingURL=' . $literalSourceMap . ' */"; color: green }';
$literalSourceBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/generated-content.css"; .wp-site-blocks { color: red }',
    '/blocks/generated-content.css' => $literalSourceCss,
], null, '/');

if (
    !str_contains($literalSourceBundle['code'], 'sourceMappingURL=' . $literalSourceMap)
    || $literalSourceBundle['sourceMap']->toArray(null, false)['sources'] !== [
        'theme.css',
        'blocks/generated-content.css',
    ]
) {
    fwrite(STDERR, "Expected source-map-looking generated content to remain an imported CSS source\n");
    exit(1);
}

echo 'source-map-string-literal: ignored' . PHP_EOL;

$resolverTrace = [];
$resolverRejected = false;
try {
    (new CssBundler())->bundle('/theme.css', [
        '/theme.css' => '@import "blocks/card.css"; .wp-site-blocks { color: red }',
        '/blocks/card.css' => "\n  @import \"pkg:missing-tokens.css\";\n  .wp-block-card { color: green }",
    ], static function (string $specifier, string $originatingFile) use (&$resolverTrace): string {
        $resolverTrace[] = [$specifier, $originatingFile];
        if ($specifier === 'pkg:missing-tokens.css') {
            throw new RuntimeException("Failed to resolve WP package `{$specifier}` from `{$originatingFile}`.");
        }

        return '/' . ltrim($specifier, '/');
    });
} catch (CssBundleException $exception) {
    $resolverRejected = $exception->kind === 'resolver-error'
        && $exception->sourceFile === '/blocks/card.css'
        && $exception->sourceLine === 2
        && $exception->sourceColumn === 3
        && $resolverTrace === [
            ['blocks/card.css', '/theme.css'],
            ['pkg:missing-tokens.css', '/blocks/card.css'],
        ];
}

if (!$resolverRejected) {
    fwrite(STDERR, "Expected package resolver failure to report the imported block stylesheet location\n");
    exit(1);
}

echo 'resolver-error-location: mapped' . PHP_EOL;

$readerObjectRejected = false;
try {
    (new CssBundler())->bundleWithReader(
        '/theme.css',
        static function (string $file): mixed {
            return $file === '/theme.css'
                ? '@import "blocks/card.css"; .wp-site-blocks { color: red }'
                : (object) ['source' => '.wp-block-card { color: green }'];
        }
    );
} catch (CssBundleException $exception) {
    $readerObjectRejected = $exception->kind === 'resolver-error'
        && $exception->getMessage() === 'expect String, got: Object'
        && $exception->sourceFile === '/theme.css'
        && $exception->sourceLine === 1
        && $exception->sourceColumn === 1;
}

if (!$readerObjectRejected) {
    fwrite(STDERR, "Expected object reader return to use upstream SourceProvider diagnostics\n");
    exit(1);
}

echo 'reader-object-diagnostic: rejected' . PHP_EOL;

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

$hexEscapedCrlfBundle = (new CssBundler())->bundle('/hex-crlf.css', [
    '/hex-crlf.css' => "@import \"blocks/card\\2e\r\ncss\";\n@import url(blocks/navigation\\2e\r\ncss);\n.wp-site-blocks { color: red; }",
    '/blocks/card.css' => '.wp-block-card { color: green; }',
    '/blocks/navigation.css' => '.wp-block-navigation { color: blue; }',
]);

if ($hexEscapedCrlfBundle !== '.wp-block-card{color:green}.wp-block-navigation{color:#00f}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected CRLF-terminated escaped import paths to resolve\n");
    exit(1);
}

echo 'escaped-crlf-imports: resolved' . PHP_EOL;

$escapedImportKeywordBundle = (new CssBundler())->bundle('/escaped-import-keywords.css', [
    '/escaped-import-keywords.css' => <<<'CSS'
@import u\72l(pkg:card.css) l\61yer(theme.blocks) s\75pports(display: grid) screen;
@import \75 rl("tokens.css") \6c ayer;
.wp-site-blocks {
  color: red;
}
CSS,
    '/vendor/card.css' => '.wp-block-card { color: green; }',
    '/tokens.css' => ':root { --wp--preset--spacing--block-gap: 1rem; }',
], static function (string $specifier, string $originatingFile): string {
    if ($specifier === 'pkg:card.css') {
        return '/vendor/card.css';
    }

    return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
});

if ($escapedImportKeywordBundle !== '@supports (display:grid){@media screen{@layer theme.blocks{.wp-block-card{color:green}}}}@layer{:root{--wp--preset--spacing--block-gap:1rem}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected escaped @import source and modifier identifiers to resolve before bundling\n");
    exit(1);
}

echo 'escaped-import-identifiers: resolved' . PHP_EOL;

$escapedAtKeywordBundle = (new CssBundler())->bundle('/escaped-at-keywords.css', [
    '/escaped-at-keywords.css' => <<<'CSS'
@\63harset "UTF-8";
@\6c ayer reset, theme.blocks;
@\69mport "tokens.css" \6c ayer(theme.tokens) s\75pports(display: grid) screen;
.wp-site-blocks {
  color: red;
}
CSS,
    '/tokens.css' => ':root { --wp--preset--spacing--block-gap: 1rem; }',
]);

if ($escapedAtKeywordBundle !== '@layer reset,theme.blocks;@supports (display:grid){@media screen{@layer theme.tokens{:root{--wp--preset--spacing--block-gap:1rem}}}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected escaped @charset, @layer, and @import at-keywords to resolve before bundling\n");
    exit(1);
}

echo 'escaped-at-keyword-import: resolved' . PHP_EOL;

$escapedSpaceUrlBundle = (new CssBundler())->bundle('/escaped-space-url.css', [
    '/escaped-space-url.css' => '@import url(blocks/card\ hero.css); .wp-site-blocks { color: red; }',
    '/blocks/card hero.css' => '.wp-block-card { color: green; }',
]);

if ($escapedSpaceUrlBundle !== '.wp-block-card{color:green}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected escaped url() whitespace to stay inside block import paths\n");
    exit(1);
}

echo 'escaped-space-url-import: resolved' . PHP_EOL;

$quotedUrlImportBundle = (new CssBundler())->bundle('/quoted-url-import.css', [
    '/quoted-url-import.css' => <<<'CSS'
@import url( /* generated by theme build */ "blocks/quote-card.css" /* trailing build note */ ) screen;
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/quote-card.css' => '.wp-block-quote { color: green; }',
]);

if ($quotedUrlImportBundle !== '@media screen{.wp-block-quote{color:green}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected quoted url() imports with trailing comments to resolve\n");
    exit(1);
}

echo 'quoted-url-import-trivia: resolved' . PHP_EOL;

$unquotedUrlImportTriviaBundle = (new CssBundler())->bundle('/unquoted-url-import-trivia.css', [
    '/unquoted-url-import-trivia.css' => <<<'CSS'
@import url( /* generated by theme build */ blocks/commented-card.css /* trailing build note */ ) layer(theme.blocks) screen;
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/commented-card.css' => '.wp-block-card { color: green; }',
]);

if ($unquotedUrlImportTriviaBundle !== '@media screen{@layer theme.blocks{.wp-block-card{color:green}}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected unquoted url() import comments to be trimmed before resolution\n");
    exit(1);
}

echo 'unquoted-url-import-trivia: resolved' . PHP_EOL;

try {
    (new CssBundler())->bundleWithReader(
        '/bad-quoted-url-import.css',
        static function (string $file): string {
            return $file === '/bad-quoted-url-import.css'
                ? '@import url("blocks/card.css" theme); .wp-site-blocks { color: red; }'
                : '.wp-block-card { color: green; }';
        }
    );

    fwrite(STDERR, "Expected trailing tokens in quoted url() import to be rejected\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Invalid @import source'
        || $exception->sourceFile !== '/bad-quoted-url-import.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 1
    ) {
        fwrite(STDERR, 'Unexpected bad quoted url() import diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'bad-quoted-url-import: rejected-before-resolution' . PHP_EOL;
}

try {
    (new CssBundler())->bundle('/bad-url-import.css', [
        '/bad-url-import.css' => '@import url(blocks/card hero.css); .wp-site-blocks { color: red; }',
        '/blocks/card hero.css' => '.wp-block-card { color: green; }',
    ]);

    fwrite(STDERR, "Expected unescaped whitespace in url() import to be rejected\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Invalid @import source'
        || $exception->sourceFile !== '/bad-url-import.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 1
    ) {
        fwrite(STDERR, 'Unexpected bad url() import diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'bad-url-import: rejected-before-resolution' . PHP_EOL;
}

$badImportSourceReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/bad-import-source.css',
        static function (string $file) use (&$badImportSourceReads): string {
            $badImportSourceReads[] = $file;

            return $file === '/bad-import-source.css'
                ? "@import \"blocks/\ncard.css\"; .wp-site-blocks { color: red; }"
                : '.wp-block-card { color: green; }';
        }
    );

    fwrite(STDERR, "Expected malformed quoted import source to be rejected before reading block CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Invalid @import source'
        || $exception->sourceFile !== '/bad-import-source.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 1
        || $badImportSourceReads !== ['/bad-import-source.css']
    ) {
        fwrite(STDERR, 'Unexpected malformed import source diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'malformed-import-source: rejected-before-read' . PHP_EOL;
}

$badImportMediaReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/bad-import-media.css',
        static function (string $file) use (&$badImportMediaReads): string {
            $badImportMediaReads[] = $file;

            return $file === '/bad-import-media.css'
                ? '@import "blocks/card.css" screen and; .wp-site-blocks { color: red; }'
                : '.wp-block-card { color: green; }';
        }
    );

    fwrite(STDERR, "Expected malformed import media tail to be rejected before reading block CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Media query boolean operator must be followed by a condition'
        || $exception->sourceFile !== '/bad-import-media.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 1
        || $badImportMediaReads !== ['/bad-import-media.css']
    ) {
        fwrite(STDERR, 'Unexpected malformed import media diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'malformed-import-media: rejected-before-read' . PHP_EOL;
}

$badImportSupportsReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/bad-import-supports.css',
        static function (string $file) use (&$badImportSupportsReads): string {
            $badImportSupportsReads[] = $file;

            return $file === '/bad-import-supports.css'
                ? '@import "blocks/card.css" supports((display: grid) and); .wp-site-blocks { color: red; }'
                : '.wp-block-card { color: green; }';
        }
    );

    fwrite(STDERR, "Expected malformed import supports condition to be rejected before reading block CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Invalid @import supports condition'
        || $exception->sourceFile !== '/bad-import-supports.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 1
        || $badImportSupportsReads !== ['/bad-import-supports.css']
    ) {
        fwrite(STDERR, 'Unexpected malformed import supports diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'malformed-import-supports: rejected-before-read' . PHP_EOL;
}

$escapedSupportsBundle = (new CssBundler())->bundle('/escaped-supports-import.css', [
    '/escaped-supports-import.css' => <<<'CSS'
@import "remote-fonts.css" supports(\6e ot (font-tech(color-COLRv1)));
@import "blocks/card.css" supports((display: grid) \61nd s\65lector(.wp-block-card > .wp-block-heading));
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/card.css' => '.wp-block-card { color: green; }',
], static function (string $specifier, string $originatingFile): array|string {
    if ($specifier === 'remote-fonts.css') {
        return ['external' => 'https://fonts.example/blocks.css'];
    }

    return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
});

if ($escapedSupportsBundle !== '@import "https://fonts.example/blocks.css" supports(not font-tech(color-COLRv1));@supports (display:grid) and selector(.wp-block-card > .wp-block-heading){.wp-block-card{color:green}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected escaped import supports identifiers to normalize before bundling\n");
    exit(1);
}

echo 'escaped-import-supports: normalized' . PHP_EOL;

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

$supportsGraphBundle = (new CssBundler())->bundle('/supports-entry.css', [
    '/supports-entry.css' => '@import "layout.css" supports((display: grid) or (display: flex)); .wp-site-blocks { color: red }',
    '/layout.css' => '@import "query.css" supports(container-type: inline-size); .wp-block-columns { color: green }',
    '/query.css' => '.wp-block-query { color: blue }',
]);

if ($supportsGraphBundle !== '@supports ((display:grid) or (display:flex)) and (container-type:inline-size){.wp-block-query{color:#00f}}@supports (display:grid) or (display:flex){.wp-block-columns{color:green}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected nested supports import graph to preserve condition grouping\n");
    exit(1);
}

echo 'supports-import-graph: grouped' . PHP_EOL;

$namespaceAfterImportReads = [];
$namespaceAfterImportBundle = (new CssBundler())->bundleWithReader(
    '/namespace-entry.css',
    static function (string $file) use (&$namespaceAfterImportReads): string {
        $namespaceAfterImportReads[] = $file;

        return match ($file) {
            '/namespace-entry.css' => '@import "blocks/svg-icon.css"; @namespace svg url(http://www.w3.org/2000/svg); svg|path { fill: currentColor }',
            '/blocks/svg-icon.css' => '.wp-block-icon { color: green; }',
            default => throw new RuntimeException("Missing namespace import fixture {$file}"),
        };
    }
);

if (
    $namespaceAfterImportBundle !== '.wp-block-icon{color:green}@namespace svg "http://www.w3.org/2000/svg";svg|path{fill:currentColor}'
    || $namespaceAfterImportReads !== ['/namespace-entry.css', '/blocks/svg-icon.css']
) {
    fwrite(STDERR, "Expected namespace statements after imports to survive resolved block graph bundling\n");
    exit(1);
}

echo 'namespace-after-import: preserved' . PHP_EOL;

$lateNamespaceReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/late-namespace.css',
        static function (string $file) use (&$lateNamespaceReads): string {
            $lateNamespaceReads[] = $file;

            return $file === '/late-namespace.css'
                ? '@import "blocks/svg-icon.css"; .wp-site-blocks { color: red } @namespace svg "http://www.w3.org/2000/svg";'
                : '.wp-block-icon { color: green; }';
        }
    );

    fwrite(STDERR, "Expected late namespace diagnostic before reading imported block CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== '@namespaces rules must precede all rules aside from @charset, @import, and @layer statements'
        || $exception->sourceFile !== '/late-namespace.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 73
        || $lateNamespaceReads !== ['/late-namespace.css']
    ) {
        fwrite(STDERR, 'Unexpected late namespace diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'late-namespace: rejected-before-read' . PHP_EOL;
}

$charsetGraphBundle = (new CssBundler())->bundle('/charset-entry.css', [
    '/charset-entry.css' => '@import "blocks/card.css" screen; .wp-site-blocks { color: red }',
    '/blocks/card.css' => '@charset "UTF-8"; @import "../tokens.css"; .wp-block-card { color: green }',
    '/tokens.css' => ':root { --wp--preset--color--brand: blue }',
]);

if ($charsetGraphBundle !== '@media screen{:root{--wp--preset--color--brand:blue}.wp-block-card{color:green}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected imported @charset statements to be ignored before block styles are wrapped\n");
    exit(1);
}

echo 'charset-import-graph: ignored' . PHP_EOL;

$lateLayerBundle = (new CssBundler())->bundle('/late-layer-entry.css', [
    '/late-layer-entry.css' => '@import "blocks/card.css" layer(theme.blocks); .wp-site-blocks { color: red }',
    '/blocks/card.css' => '.wp-block-card { color: green } @layer editor-overrides;',
]);

if ($lateLayerBundle !== '@layer theme.blocks{.wp-block-card{color:green}@layer theme.blocks.editor-overrides}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected post-style layer statement to remain after block styles\n");
    exit(1);
}

echo 'late-layer-import-order: preserved' . PHP_EOL;

$duplicateLayerBundle = (new CssBundler())->bundle('/duplicate-layer-entry.css', [
    '/duplicate-layer-entry.css' => '@import "blocks/shared.css"; @import "blocks/shared.css" layer(theme.blocks); @import "blocks/card.css" layer(theme.blocks); .wp-site-blocks { color: red }',
    '/blocks/shared.css' => '.wp-block-buttons { color: blue }',
    '/blocks/card.css' => '.wp-block-card { color: green }',
]);

if ($duplicateLayerBundle !== '@layer theme.blocks{.wp-block-buttons{color:#00f}.wp-block-card{color:green}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected duplicate block imports to preserve named layer state\n");
    exit(1);
}

echo 'duplicate-layer-imports: merged' . PHP_EOL;

$escapedLayerBundle = (new CssBundler())->bundle('/escaped-layer-entry.css', [
    '/escaped-layer-entry.css' => '@import "blocks/card.css" layer(plugin\2e cards); .wp-site-blocks { color: red }',
    '/blocks/card.css' => '@import "../tokens.css" layer(palette\2c dark); .wp-block-card { color: green }',
    '/tokens.css' => ':root { --wp--preset--color--brand: blue }',
]);

if ($escapedLayerBundle !== '@layer plugin\\.cards.palette\\,dark{:root{--wp--preset--color--brand:blue}}@layer plugin\\.cards{.wp-block-card{color:green}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected escaped import layer names to survive block-theme graph composition\n");
    exit(1);
}

echo 'escaped-layer-imports: preserved' . PHP_EOL;

$anonymousChildLayerReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/anonymous-child-layer.css',
        static function (string $file) use (&$anonymousChildLayerReads): string {
            $anonymousChildLayerReads[] = $file;

            return match ($file) {
                '/anonymous-child-layer.css' => '@import "blocks/card.css" layer; .wp-site-blocks { color: red }',
                '/blocks/card.css' => '@import "../tokens.css" layer(theme.tokens); .wp-block-card { color: green }',
                default => throw new RuntimeException("Unexpected anonymous layer fixture read {$file}"),
            };
        }
    );

    fwrite(STDERR, "Expected nested named layer under anonymous block import to be rejected\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'unsupported-layer-combination'
        || $exception->getMessage() !== 'Unsupported layer combination in @import'
        || $exception->sourceFile !== '/blocks/card.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 1
        || $anonymousChildLayerReads !== ['/anonymous-child-layer.css', '/blocks/card.css']
    ) {
        fwrite(STDERR, 'Unexpected anonymous child layer diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'anonymous-child-layer: rejected-before-child-read' . PHP_EOL;

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

$nestedImportReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/nested-import.css',
        static function (string $file) use (&$nestedImportReads): string {
            $nestedImportReads[] = $file;

            return $file === '/nested-import.css'
                ? <<<'CSS'
@media screen {
  @import "blocks/card.css";
  .wp-site-blocks { color: red }
}
CSS
                : '.wp-block-card { color: green; }';
        }
    );

    fwrite(STDERR, "Expected nested @import in block-theme CSS to be rejected before reading block CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Unknown at rule: @import'
        || $exception->sourceFile !== '/nested-import.css'
        || $exception->sourceLine !== 2
        || $exception->sourceColumn !== 3
        || $nestedImportReads !== ['/nested-import.css']
    ) {
        fwrite(STDERR, 'Unexpected nested @import diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'nested-import: rejected-before-read' . PHP_EOL;
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

$invalidLayerReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/invalid-layer.css',
        static function (string $file) use (&$invalidLayerReads): string {
            $invalidLayerReads[] = $file;

            return $file === '/invalid-layer.css'
                ? '@import "tokens.css" layer(theme.tokens, theme.blocks); .wp-site-blocks { color: red }'
                : ':root { --wp--preset--color--brand: blue; }';
        }
    );

    fwrite(STDERR, "Expected invalid import layer diagnostic before block-theme graph resolution\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Invalid @import layer name: theme.tokens, theme.blocks'
        || $exception->sourceFile !== '/invalid-layer.css'
        || $invalidLayerReads !== ['/invalid-layer.css']
    ) {
        fwrite(STDERR, 'Unexpected invalid layer diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'invalid-import-layer: rejected-before-read' . PHP_EOL;
}

$invalidLayerBlockReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/invalid-layer-block.css',
        static function (string $file) use (&$invalidLayerBlockReads): string {
            $invalidLayerBlockReads[] = $file;

            return $file === '/invalid-layer-block.css'
                ? '@import "tokens.css" layer(theme.tokens, theme.blocks) {}; .wp-site-blocks { color: red }'
                : ':root { --wp--preset--color--brand: blue; }';
        }
    );

    fwrite(STDERR, "Expected block-form invalid import layer diagnostic before block-theme graph resolution\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Invalid @import layer name: theme.tokens, theme.blocks'
        || $exception->sourceFile !== '/invalid-layer-block.css'
        || $invalidLayerBlockReads !== ['/invalid-layer-block.css']
    ) {
        fwrite(STDERR, 'Unexpected block-form invalid layer diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'invalid-import-layer-block: rejected-before-read' . PHP_EOL;
}

$escapedLayerNameBundle = (new CssBundler())->bundle('/escaped-layer-name.css', [
    '/escaped-layer-name.css' => '@import "tokens.css" layer(theme\20 tokens.block); .wp-site-blocks { color: red }',
    '/tokens.css' => ':root { --wp--preset--color--brand: blue; }',
]);

if ($escapedLayerNameBundle !== '@layer theme\ tokens.block{:root{--wp--preset--color--brand:blue}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected escaped import layer names to stay valid before block-theme bundling\n");
    exit(1);
}

echo 'escaped-import-layer-name: bundled' . PHP_EOL;

$invalidDottedLayerReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/invalid-dotted-layer.css',
        static function (string $file) use (&$invalidDottedLayerReads): string {
            $invalidDottedLayerReads[] = $file;

            return $file === '/invalid-dotted-layer.css'
                ? '@import "tokens.css" layer(theme .blocks); .wp-site-blocks { color: red }'
                : ':root { --wp--preset--color--brand: blue; }';
        }
    );

    fwrite(STDERR, "Expected invalid dotted import layer diagnostic before block-theme graph resolution\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Invalid @import layer name: theme .blocks'
        || $exception->sourceFile !== '/invalid-dotted-layer.css'
        || $invalidDottedLayerReads !== ['/invalid-dotted-layer.css']
    ) {
        fwrite(STDERR, 'Unexpected invalid dotted layer diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'invalid-import-layer-dots: rejected-before-read' . PHP_EOL;
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

$externalResolverBundle = (new CssBundler())->bundle('/external-resolver.css', [
    '/external-resolver.css' => <<<'CSS'
@import "cdn:editor.css" screen;
@import "tokens.css";
.wp-site-blocks {
  color: red;
}
CSS,
    '/tokens.css' => ':root { --wp--preset--color--brand: blue; }',
], static function (string $specifier): array|string {
    if ($specifier === 'cdn:editor.css') {
        return ['external' => 'https://cdn.example/wp"blocks\\editor.css'];
    }

    return '/' . ltrim($specifier, './');
});

if ($externalResolverBundle !== '@import "https://cdn.example/wp\"blocks\\\\editor.css" screen;:root{--wp--preset--color--brand:blue}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Unexpected resolver external string output\n");
    exit(1);
}

echo 'resolver-external-string: serialized' . PHP_EOL;

$nestedExternalBundle = (new CssBundler())->bundle('/nested-external-entry.css', [
    '/nested-external-entry.css' => <<<'CSS'
@import "blocks/card.css" layer(theme.blocks) screen;
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/card.css' => <<<'CSS'
@import "cdn:card-reset.css" print;
@import "button.css";
.wp-block-card {
  color: green;
}
CSS,
    '/blocks/button.css' => '.wp-block-button__link { color: blue; }',
], static function (string $specifier, string $originatingFile): array|string {
    if ($specifier === 'cdn:card-reset.css') {
        return ['external' => 'https://cdn.example/wp-card-reset.css'];
    }

    return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
});

if ($nestedExternalBundle !== '@import "https://cdn.example/wp-card-reset.css" print;@media screen{@layer theme.blocks{.wp-block-button__link{color:#00f}}@layer theme.blocks{.wp-block-card{color:green}}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected nested external import to stay outside parent wrappers\n");
    exit(1);
}

echo 'nested-external-import: preserved' . PHP_EOL;

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

$rawReaderFiles = [
    '/reader-raw-theme.css' => <<<'CSS'
@import "pkg:presets.css";
.wp-site-blocks {
  color: red;
}
CSS,
    './vendor/../vendor/presets.css' => <<<'CSS'
:root {
  --wp--preset--color--brand: blue;
}
CSS,
];
$rawReaderReads = [];
$rawReaderBundle = (new CssBundler())->bundleWithReader(
    '/reader-raw-theme.css',
    static function (string $file) use (&$rawReaderReads, $rawReaderFiles): string {
        $rawReaderReads[] = $file;
        if (!array_key_exists($file, $rawReaderFiles)) {
            throw new RuntimeException("Missing reader-backed theme file {$file}");
        }

        return $rawReaderFiles[$file];
    },
    static function (string $specifier): string {
        if ($specifier === 'pkg:presets.css') {
            return './vendor/../vendor/presets.css';
        }

        throw new RuntimeException("Unexpected reader-backed specifier {$specifier}");
    }
);

if (
    $rawReaderBundle !== ':root{--wp--preset--color--brand:blue}.wp-site-blocks{color:red}'
    || $rawReaderReads !== ['/reader-raw-theme.css', './vendor/../vendor/presets.css']
) {
    fwrite(STDERR, "Expected reader-backed resolver return path to be preserved\n");
    exit(1);
}

echo 'reader-resolver-raw-path: preserved' . PHP_EOL;

$mountedReaderEntry = './themes/current/../current/theme.css';
$mountedReaderTokens = './vendor/../vendor/tokens.css';
$mountedReaderFiles = [
    $mountedReaderEntry => <<<'CSS'
@import "pkg:tokens.css";
.wp-site-blocks {
  color: red;
}
CSS,
    $mountedReaderTokens => ':root { --wp--preset--color--brand: blue; }',
];
$mountedReaderReads = [];
$mountedReaderResolved = [];
$mountedReaderBundle = (new CssBundler())->bundleWithReader(
    $mountedReaderEntry,
    static function (string $file) use (&$mountedReaderReads, $mountedReaderFiles): string {
        $mountedReaderReads[] = $file;
        if (!array_key_exists($file, $mountedReaderFiles)) {
            throw new RuntimeException("Missing mounted reader-backed theme file {$file}");
        }

        return $mountedReaderFiles[$file];
    },
    static function (string $specifier, string $originatingFile) use (&$mountedReaderResolved, $mountedReaderEntry, $mountedReaderTokens): string {
        $mountedReaderResolved[] = [$specifier, $originatingFile];
        if ($specifier === 'pkg:tokens.css') {
            return $mountedReaderTokens;
        }

        throw new RuntimeException("Unexpected mounted reader-backed specifier {$specifier}");
    }
);

if (
    $mountedReaderBundle !== ':root{--wp--preset--color--brand:blue}.wp-site-blocks{color:red}'
    || $mountedReaderReads !== [$mountedReaderEntry, $mountedReaderTokens]
    || $mountedReaderResolved !== [['pkg:tokens.css', $mountedReaderEntry]]
) {
    fwrite(STDERR, "Expected mounted reader entry path to remain the source-provider identity\n");
    exit(1);
}

echo 'reader-entry-raw-path: preserved' . PHP_EOL;

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

$readerLexicalFiles = [
    '/reader-theme/entry.css' => <<<'CSS'
@import "base.css";
@import "blocks/card.css";
.wp-site-blocks {
  color: red;
}
CSS,
    '/reader-theme/base.css' => '.wp-block-base { color: blue; }',
    '/reader-theme/blocks/card.css' => <<<'CSS'
@import "../base.css";
.wp-block-card {
  color: green;
}
CSS,
    '/reader-theme/blocks/../base.css' => '.wp-block-base-override { color: purple; }',
];
$readerLexicalReads = [];
$readerLexicalBundle = (new CssBundler())->bundleWithReader(
    '/reader-theme/entry.css',
    static function (string $file) use (&$readerLexicalReads, $readerLexicalFiles): string {
        $readerLexicalReads[] = $file;
        if (!array_key_exists($file, $readerLexicalFiles)) {
            throw new RuntimeException("Missing reader-backed lexical theme file {$file}");
        }

        return $readerLexicalFiles[$file];
    }
);

if (
    $readerLexicalBundle !== '.wp-block-base{color:#00f}.wp-block-base-override{color:purple}.wp-block-card{color:green}.wp-site-blocks{color:red}'
    || $readerLexicalReads !== [
        '/reader-theme/entry.css',
        '/reader-theme/base.css',
        '/reader-theme/blocks/card.css',
        '/reader-theme/blocks/../base.css',
    ]
) {
    fwrite(STDERR, "Expected reader default resolver to preserve lexical block-theme paths\n");
    exit(1);
}

echo 'reader-lexical-import-identities: preserved' . PHP_EOL;

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

$withTempFiles([
    'theme/entry.css' => <<<'CSS'
@import "blocks/card.css";
@import "base.css";
.wp-site-blocks {
  color: red;
}
CSS,
    'theme/blocks/card.css' => <<<'CSS'
@import "../base.css";
.wp-block-card {
  color: green;
}
CSS,
    'theme/base.css' => '.wp-block-base { color: blue; }',
], static function (string $root): void {
    $filesystemBundle = (new CssBundler())->bundleFile($root . '/theme/entry.css');

    if ($filesystemBundle !== '.wp-block-base{color:#00f}.wp-block-card{color:green}.wp-block-base{color:#00f}.wp-site-blocks{color:red}') {
        fwrite(STDERR, "Expected filesystem lexical import identities to stay distinct\n");
        exit(1);
    }
});

echo 'filesystem-lexical-import-identities: preserved' . PHP_EOL;

$withTempFiles([
    'theme.css' => <<<'CSS'
@import "pkg:presets.css";
@import "pkg:blocks.css";
.wp-site-blocks {
  color: red;
}
CSS,
    'vendor/presets.css' => ':root { --wp--preset--color--brand: blue; }',
    'blocks/query.css' => <<<'CSS'
@import "pkg:button.css";
.wp-block-query {
  color: green;
}
CSS,
    'shared/button.css' => '.wp-block-button__link { color: blue; }',
], static function (string $root): void {
    $resolved = [];
    $filesystemBundle = (new CssBundler())->bundleFile(
        $root . '/theme.css',
        static function (string $specifier, string $originatingFile) use ($root, &$resolved): string {
            $resolved[] = [$specifier, $originatingFile];

            return match ($specifier) {
                'pkg:presets.css' => $root . '/vendor/../vendor/presets.css',
                'pkg:blocks.css' => $root . '/blocks/../blocks/query.css',
                'pkg:button.css' => $root . '/shared/../shared/button.css',
                default => throw new RuntimeException("Unexpected filesystem-backed specifier {$specifier}"),
            };
        }
    );

    if (
        $filesystemBundle !== ':root{--wp--preset--color--brand:blue}.wp-block-button__link{color:#00f}.wp-block-query{color:green}.wp-site-blocks{color:red}'
        || $resolved !== [
            ['pkg:presets.css', $root . '/theme.css'],
            ['pkg:blocks.css', $root . '/theme.css'],
            ['pkg:button.css', $root . '/blocks/../blocks/query.css'],
        ]
    ) {
        fwrite(STDERR, "Expected filesystem resolver-returned paths to remain import graph identities\n");
        exit(1);
    }
});

echo 'filesystem-resolver-raw-path: preserved' . PHP_EOL;

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

$moduleMappedBundle = (new CssBundler())->bundleCssModulesWithSourceMap('/modules/card.css', [
    '/modules/card.css' => <<<'CSS'
@import "../theme.css";

.wp-block-card {
  composes: token from "../tokens.module.css";
  color: red;
}
CSS,
    '/tokens.module.css' => <<<'CSS'
.token {
  color: blue;
}
CSS,
    '/theme.css' => '.wp-block-theme { color: yellow; }',
], null, [
    'hashes' => [
        '/modules/card.css' => 'card',
        '/tokens.module.css' => 'tok',
        '/theme.css' => 'theme',
    ],
], '/');
$moduleMappedSources = $moduleMappedBundle['sourceMap']->toArray(null, false)['sources'];
if (
    $moduleMappedBundle['code'] !== '.tok_token{color:#00f}.theme_wp-block-theme{color:#ff0}.card_wp-block-card{color:red}'
    || ($moduleMappedBundle['exports']['wp-block-card']['composes'][0]['name'] ?? null) !== 'tok_token'
    || $moduleMappedSources !== ['modules/card.css', 'theme.css', 'tokens.module.css']
) {
    fwrite(STDERR, "Expected CSS Modules source-map bundle graph output\n");
    exit(1);
}

echo 'css-modules-source-map: collected' . PHP_EOL;

$moduleOrderFiles = [
    '/modules/order-card.css' => <<<'CSS'
@import "pkg:theme.css";

.wp-block-card {
  composes: token from "pkg:tokens.css";
  color: red;
}
CSS,
    '/theme.css' => '.wp-block-theme { color: blue }',
    '/tokens.css' => '.token { color: green }',
];
$moduleOrderReads = [];
$moduleOrderResolved = [];
$moduleOrderBundle = (new CssBundler())->bundleCssModulesWithReader(
    '/modules/order-card.css',
    static function (string $file) use (&$moduleOrderReads, $moduleOrderFiles): string {
        $moduleOrderReads[] = $file;
        if (!array_key_exists($file, $moduleOrderFiles)) {
            throw new RuntimeException("Missing CSS Modules order file {$file}");
        }

        return $moduleOrderFiles[$file];
    },
    static function (string $specifier, string $originatingFile) use (&$moduleOrderResolved): string {
        $moduleOrderResolved[] = [$specifier, $originatingFile];

        return match ($specifier) {
            'pkg:theme.css' => '/theme.css',
            'pkg:tokens.css' => '/tokens.css',
            default => throw new RuntimeException("Unexpected CSS Modules order specifier {$specifier}"),
        };
    },
    [
        'hashes' => [
            '/modules/order-card.css' => 'card',
            '/theme.css' => 'theme',
            '/tokens.css' => 'tok',
        ],
    ]
);

if (
    $moduleOrderBundle['code'] !== '.tok_token{color:green}.theme_wp-block-theme{color:#00f}.card_wp-block-card{color:red}'
    || $moduleOrderReads !== ['/modules/order-card.css', '/theme.css', '/tokens.css']
    || $moduleOrderResolved !== [
        ['pkg:theme.css', '/modules/order-card.css'],
        ['pkg:tokens.css', '/modules/order-card.css'],
    ]
) {
    fwrite(STDERR, "Expected CSS Modules resolver/read order to match upstream import-first loading\n");
    exit(1);
}

echo 'css-modules-import-first-resolution: ordered' . PHP_EOL;

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

$escapedFromResolverTrace = [];
try {
    (new CssBundler())->bundleCssModules('/modules/escaped-from-card.css', [
        '/modules/escaped-from-card.css' => <<<'CSS'
.wp-block-intro { color: red; }

.wp-block-card {
  c\6fmposes: remote fr\6fm "pkg:remote-card.css";
  color: blue;
}
CSS,
    ], static function (string $specifier, string $originatingFile) use (&$escapedFromResolverTrace): string {
        $escapedFromResolverTrace[] = [$specifier, $originatingFile];
        throw new RuntimeException("Failed to resolve WP module `{$specifier}` from `{$originatingFile}`.");
    });

    fwrite(STDERR, "Expected escaped CSS Modules from diagnostic\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'resolver-error'
        || $exception->getMessage() !== 'Failed to resolve WP module `pkg:remote-card.css` from `/modules/escaped-from-card.css`.'
        || $exception->sourceFile !== '/modules/escaped-from-card.css'
        || $exception->sourceLine !== 3
        || $exception->sourceColumn !== 1
        || $escapedFromResolverTrace !== [['pkg:remote-card.css', '/modules/escaped-from-card.css']]
    ) {
        fwrite(STDERR, 'Unexpected escaped CSS Modules from diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'css-modules-escaped-from-location: rejected' . PHP_EOL;

try {
    (new CssBundler())->bundleCssModules('/modules/remote-card.css', [
        '/modules/remote-card.css' => <<<'CSS'
.wp-block-intro { color: red; }

.wp-block-card {
  composes: remote from "https://cdn.example/remote-card.css";
  color: blue;
}
CSS,
    ]);

    fwrite(STDERR, "Expected external CSS Modules dependency diagnostic\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'referenced-external-module-with-css-module-from'
        || $exception->getMessage() !== 'Referenced external module with CSS module "from" clause'
        || $exception->sourceFile !== '/modules/remote-card.css'
        || $exception->sourceLine !== 3
        || $exception->sourceColumn !== 1
    ) {
        fwrite(STDERR, 'Unexpected external CSS Modules dependency diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'css-modules-external-style-location: rejected' . PHP_EOL;

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

$cssModuleFirstInstance = (new CssBundler())->bundleCssModules('/blocks/card.css', [
    '/blocks/card.css' => <<<'CSS'
@import "../theme.css";
.wp-block-card {
  composes: token from "../tokens.css";
  color: red;
}
CSS,
    '/theme.css' => <<<'CSS'
@import "tokens.css";
.wp-block-card { color: blue; }
CSS,
    '/tokens.css' => '.token { color: green; }',
], null, [
    'hashes' => [
        '/blocks/card.css' => 'card',
        '/theme.css' => 'theme',
        '/tokens.css' => 'tok',
    ],
]);

if (
    $cssModuleFirstInstance['code'] !== '.tok_token{color:green}.theme_wp-block-card{color:#00f}.card_wp-block-card{color:red}'
    || ($cssModuleFirstInstance['exports']['wp-block-card']['composes'][0]['name'] ?? null) !== 'tok_token'
) {
    fwrite(STDERR, "Unexpected CSS Modules first-instance dependency graph output\n");
    exit(1);
}

echo 'css-modules-first-instance: stable' . PHP_EOL;

$cssModuleEnvDependency = (new CssBundler())->bundleCssModules('/blocks/card.css', [
    '/blocks/card.css' => <<<'CSS'
@import "pkg:theme.css";

.wp-block-card {
  margin: env(--wp-card-gap from "pkg:tokens.css", var(--fallback-gap from "pkg:fallback.css"));
  color: red;
}
CSS,
    '/vendor/tokens.css' => <<<'CSS'
.tokens {
  --wp-card-gap: 24px;
}
CSS,
    '/vendor/fallback.css' => <<<'CSS'
.fallback {
  --fallback-gap: 12px;
}
CSS,
    '/vendor/theme.css' => '.theme { color: blue }',
], static function (string $specifier): string {
    return '/vendor/' . substr($specifier, strlen('pkg:'));
}, [
    'hashes' => [
        '/blocks/card.css' => 'card',
        '/vendor/tokens.css' => 'tok',
        '/vendor/fallback.css' => 'fallback',
        '/vendor/theme.css' => 'theme',
    ],
    'dashedIdents' => true,
]);

if (
    $cssModuleEnvDependency['code'] !== '.tok_tokens{--tok_wp-card-gap:24px}.fallback_fallback{--fallback_fallback-gap:12px}.theme_theme{color:#00f}.card_wp-block-card{margin:env(--tok_wp-card-gap,var(--fallback_fallback-gap));color:red}'
    || ($cssModuleEnvDependency['exports']['wp-block-card']['name'] ?? null) !== 'card_wp-block-card'
) {
    fwrite(STDERR, "Unexpected CSS Modules env() dependency graph output\n");
    exit(1);
}

echo 'css-modules-env-dependency: resolved' . PHP_EOL;
