<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssBundleException;
use PortLibs\LightningCSS\CssBundler;
use PortLibs\LightningCSS\SourceMap;

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
        'blocks/generated-card.scss',
    ]
) {
    fwrite(STDERR, "Expected inline input source map to replace generated block CSS source and prune unused originals\n");
    exit(1);
}

echo 'source-map-input: remapped' . PHP_EOL;
echo 'source-map-input-unused: pruned' . PHP_EOL;

$offsetBlockMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/generated-offset-card.scss'],
    'sourcesContent' => ['.wp-block-card { color: $theme-green }'],
    'names' => [],
], JSON_THROW_ON_ERROR));

$offsetSourceBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/base.css"; @import "blocks/generated-offset-card.css"; .wp-site-blocks { color: red }',
    '/blocks/base.css' => '.wp-block-base { color: blue }',
    '/blocks/generated-offset-card.css' => ".wp-block-card { color: green }\n/*# sourceMappingURL={$offsetBlockMap} */",
], null, '/');
$offsetSourceDecoded = SourceMap::decodeVlq($offsetSourceBundle['sourceMap']->toArray(null, false)['mappings']);

if (
    $offsetSourceBundle['code'] !== '.wp-block-base{color:#00f}.wp-block-card{color:green}.wp-site-blocks{color:red}'
    || $offsetSourceBundle['sourceMap']->toArray(null, false)['sources'] !== [
        'theme.css',
        'blocks/base.css',
        'blocks/generated-offset-card.scss',
    ]
    || ($offsetSourceDecoded[0]['generatedColumn'] ?? null) !== strlen('.wp-block-base{color:#00f}')
    || ($offsetSourceDecoded[0]['sourceIndex'] ?? null) !== 2
) {
    fwrite(STDERR, "Expected inline input source map to offset after earlier bundled block CSS\n");
    exit(1);
}

echo 'source-map-input-offset: remapped' . PHP_EOL;

$licenseOffsetBlockMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/generated-license-card.scss'],
    'sourcesContent' => ['.wp-block-card { color: $theme-green }'],
    'names' => [],
], JSON_THROW_ON_ERROR));

$licenseOffsetSourceBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/license.css"; @import "blocks/generated-license-card.css"; .wp-site-blocks { color: red }',
    '/blocks/license.css' => "/*! WP block package */\n.wp-block-base { color: blue }",
    '/blocks/generated-license-card.css' => ".wp-block-card { color: green }\n/*# sourceMappingURL={$licenseOffsetBlockMap} */",
], null, '/');
$licenseOffsetSourceDecoded = SourceMap::decodeVlq($licenseOffsetSourceBundle['sourceMap']->toArray(null, false)['mappings']);

if (
    $licenseOffsetSourceBundle['code'] !== "/*! WP block package */\n.wp-block-base{color:#00f}.wp-block-card{color:green}.wp-site-blocks{color:red}"
    || $licenseOffsetSourceBundle['sourceMap']->toArray(null, false)['sources'] !== [
        'theme.css',
        'blocks/license.css',
        'blocks/generated-license-card.scss',
    ]
    || ($licenseOffsetSourceDecoded[0]['generatedLine'] ?? null) !== 1
    || ($licenseOffsetSourceDecoded[0]['generatedColumn'] ?? null) !== strlen('.wp-block-base{color:#00f}')
    || ($licenseOffsetSourceDecoded[0]['sourceIndex'] ?? null) !== 2
) {
    fwrite(STDERR, "Expected inline input source map to offset after hoisted block CSS license comments\n");
    exit(1);
}

echo 'source-map-input-license-offset: remapped' . PHP_EOL;

$stringFragmentBlockMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/string-fragment-card.scss'],
    'sourcesContent' => ['.wp-block-card { color: $theme-green }'],
    'names' => [],
], JSON_THROW_ON_ERROR));

$stringFragmentBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/label.css"; @import "blocks/string-fragment-card.css"; .wp-site-blocks { color: red }',
    '/blocks/label.css' => '.wp-block-label:before { content: ".wp-block-card{color:green}"; color: blue }',
    '/blocks/string-fragment-card.css' => ".wp-block-card { color: green }\n/*# sourceMappingURL={$stringFragmentBlockMap} */",
], null, '/');
$stringFragmentDecoded = SourceMap::decodeVlq($stringFragmentBundle['sourceMap']->toArray(null, false)['mappings']);

if (
    $stringFragmentBundle['code'] !== '.wp-block-label:before{content:".wp-block-card{color:green}";color:#00f}.wp-block-card{color:green}.wp-site-blocks{color:red}'
    || $stringFragmentBundle['sourceMap']->toArray(null, false)['sources'] !== [
        'theme.css',
        'blocks/label.css',
        'blocks/string-fragment-card.scss',
    ]
    || ($stringFragmentDecoded[0]['generatedColumn'] ?? null) !== strlen('.wp-block-label:before{content:".wp-block-card{color:green}";color:#00f}')
    || ($stringFragmentDecoded[0]['sourceIndex'] ?? null) !== 2
) {
    fwrite(STDERR, "Expected inline input source map to skip earlier quoted block CSS fragments\n");
    exit(1);
}

echo 'source-map-input-string-fragment: remapped' . PHP_EOL;

$duplicateScreenMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/screen-card.scss'],
    'sourcesContent' => ['.wp-block-card { color: $screen-green }'],
    'names' => [],
], JSON_THROW_ON_ERROR));
$duplicatePrintMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/print-card.scss'],
    'sourcesContent' => ['.wp-block-card { color: $print-green }'],
    'names' => [],
], JSON_THROW_ON_ERROR));
$duplicateFragmentBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/screen-card.css" screen; @import "blocks/print-card.css" print; .wp-site-blocks { color: red }',
    '/blocks/screen-card.css' => ".wp-block-card { color: green }\n/*# sourceMappingURL={$duplicateScreenMap} */",
    '/blocks/print-card.css' => ".wp-block-card { color: green }\n/*# sourceMappingURL={$duplicatePrintMap} */",
], null, '/');
$duplicateFragmentDecoded = SourceMap::decodeVlq($duplicateFragmentBundle['sourceMap']->toArray(null, false)['mappings']);

if (
    $duplicateFragmentBundle['code'] !== '@media screen{.wp-block-card{color:green}}@media print{.wp-block-card{color:green}}.wp-site-blocks{color:red}'
    || array_column($duplicateFragmentDecoded, 'generatedColumn') !== [
        strlen('@media screen{'),
        strlen('@media screen{.wp-block-card{color:green}}@media print{'),
    ]
    || array_column($duplicateFragmentDecoded, 'sourceIndex') !== [1, 2]
) {
    fwrite(STDERR, "Expected duplicate generated block CSS fragments to remap in emitted import order\n");
    exit(1);
}

echo 'source-map-duplicate-fragment-offset: remapped' . PHP_EOL;

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

$malformedInlineMap = 'data:application/json;base64,not-json';
$malformedInlineBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/bad-generated.css"; @import "blocks/plain.css"; .wp-site-blocks { color: red }',
    '/blocks/bad-generated.css' => ".wp-block-bad { color: green }\n/*# sourceMappingURL={$malformedInlineMap} */",
    '/blocks/plain.css' => '.wp-block-plain { color: blue }',
], null, '/');

if (
    $malformedInlineBundle['code'] !== '.wp-block-bad{color:green}.wp-block-plain{color:#00f}.wp-site-blocks{color:red}'
    || $malformedInlineBundle['sourceMap']->toArray(null, false)['sources'] !== [
        'theme.css',
        'blocks/plain.css',
    ]
) {
    fwrite(STDERR, "Expected malformed inline input source map to suppress generated block CSS source collection\n");
    exit(1);
}

echo 'source-map-input-malformed: suppressed' . PHP_EOL;

$directiveBlockMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/directive-old.scss'],
    'sourcesContent' => ['.wp-block-old { color: $wp-purple }'],
    'names' => [],
], JSON_THROW_ON_ERROR));
$directiveBadMap = 'data:application/json;base64,not-json';
$directiveBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/directive-old.css"; @import "blocks/directive-leading.css"; @import "blocks/directive-equals.css"; @import "blocks/directive-empty.css"; .wp-site-blocks { color: red }',
    '/blocks/directive-old.css' => ".wp-block-old { color: purple }\n/*@ sourceMappingURL={$directiveBlockMap} */",
    '/blocks/directive-leading.css' => ".wp-block-leading { color: green }\n/*   # sourceMappingURL={$directiveBadMap} */",
    '/blocks/directive-equals.css' => ".wp-block-equals { color: blue }\n/*# sourceMappingURL = {$directiveBadMap} */",
    '/blocks/directive-empty.css' => ".wp-block-empty { color: red }\n/*# sourceMappingURL=  {$directiveBadMap} */",
], null, '/');
$directiveMap = $directiveBundle['sourceMap']->toArray(null, false);
$directiveDecoded = SourceMap::decodeVlq($directiveMap['mappings']);

if (
    $directiveBundle['code'] !== '.wp-block-old{color:purple}.wp-block-leading{color:green}.wp-block-equals{color:#00f}.wp-block-empty{color:red}.wp-site-blocks{color:red}'
    || $directiveMap['sources'] !== [
        'theme.css',
        'blocks/directive-leading.css',
        'blocks/directive-equals.css',
        'blocks/directive-empty.css',
        'blocks/directive-old.scss',
    ]
    || ($directiveDecoded[0]['sourceIndex'] ?? null) !== array_search('blocks/directive-old.scss', $directiveMap['sources'], true)
) {
    fwrite(STDERR, "Expected source-map URL directives to follow upstream tokenization before inline-map replacement\n");
    exit(1);
}

echo 'source-map-directive-tokenization: matched' . PHP_EOL;

$mixedCaseDataMap = 'Data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/mixed-case-card.scss'],
    'sourcesContent' => ['.wp-block-card { color: $wp-green }'],
    'names' => [],
], JSON_THROW_ON_ERROR));
$lowercaseDataMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/lowercase-card.scss'],
    'sourcesContent' => ['.wp-block-lowercase-card { color: $wp-blue }'],
    'names' => [],
], JSON_THROW_ON_ERROR));
$mixedCaseDataBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/mixed-case-card.css"; @import "blocks/lowercase-card.css"; .wp-site-blocks { color: red }',
    '/blocks/mixed-case-card.css' => ".wp-block-card { color: green }\n/*# sourceMappingURL={$mixedCaseDataMap} */",
    '/blocks/lowercase-card.css' => ".wp-block-lowercase-card { color: blue }\n/*# sourceMappingURL={$lowercaseDataMap} */",
], null, '/');
$mixedCaseDataSourceMap = $mixedCaseDataBundle['sourceMap']->toArray(null, false);
$mixedCaseDataDecoded = SourceMap::decodeVlq($mixedCaseDataSourceMap['mappings']);

if (
    $mixedCaseDataBundle['code'] !== '.wp-block-card{color:green}.wp-block-lowercase-card{color:#00f}.wp-site-blocks{color:red}'
    || $mixedCaseDataSourceMap['sources'] !== [
        'theme.css',
        'blocks/mixed-case-card.css',
        'blocks/lowercase-card.scss',
    ]
    || ($mixedCaseDataDecoded[0]['sourceIndex'] ?? null) !== 2
    || ($mixedCaseDataDecoded[0]['generatedColumn'] ?? null) !== strlen('.wp-block-card{color:green}')
) {
    fwrite(STDERR, "Expected mixed-case Data: source-map URL to keep generated block CSS source while lowercase data: remaps\n");
    exit(1);
}

echo 'source-map-mixed-case-data-url: generated-source' . PHP_EOL;

$sourceMapUrlDataMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => '',
    'sources' => ['blocks/source-map-url-inline.scss'],
    'sourcesContent' => ['.wp-block-inline-card { color: $wp-green }'],
    'names' => [],
], JSON_THROW_ON_ERROR));
$sourceMapUrlBundle = (new CssBundler())->bundleWithSourceMap('/theme.css', [
    '/theme.css' => '@import "blocks/source-map-url-editor.css"; @import "blocks/source-map-url-inline.css"; @import "blocks/source-map-url-plain.css"; .wp-site-blocks { color: red }',
    '/blocks/source-map-url-editor.css' => ".wp-block-editor-card { color: blue }\n/*# sourceMappingURL=source-map-url-editor.css.map */",
    '/blocks/source-map-url-inline.css' => ".wp-block-inline-card { color: green }\n/*# sourceMappingURL={$sourceMapUrlDataMap} */",
    '/blocks/source-map-url-plain.css' => '.wp-block-plain-card { color: purple }',
], null, '/');
$sourceMapUrlSources = $sourceMapUrlBundle['sourceMap']->toArray(null, false)['sources'];

if (
    $sourceMapUrlBundle['code'] !== '.wp-block-editor-card{color:#00f}.wp-block-inline-card{color:green}.wp-block-plain-card{color:purple}.wp-site-blocks{color:red}'
    || $sourceMapUrlBundle['sourceMapUrls'] !== [
        null,
        'source-map-url-editor.css.map',
        $sourceMapUrlDataMap,
        null,
    ]
    || $sourceMapUrlSources !== [
        'theme.css',
        'blocks/source-map-url-editor.css',
        'blocks/source-map-url-plain.css',
    ]
) {
    fwrite(STDERR, "Expected bundled block stylesheets to preserve upstream source_map_urls metadata across imports\n");
    exit(1);
}

echo 'source-map-url-import-graph: preserved' . PHP_EOL;

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

$prefixResolved = [];
$prefixResolver = static function (string $specifier, string $originatingFile) use (&$prefixResolved): string {
    $prefixResolved[] = [$specifier, $originatingFile];
    if (!str_starts_with($specifier, 'wp:')) {
        throw new RuntimeException("Failed to resolve WP import `{$specifier}` from `{$originatingFile}` without wp: prefix.");
    }

    return '/' . substr($specifier, strlen('wp:'));
};
$prefixBundle = (new CssBundler())->bundle('/theme.css', [
    '/theme.css' => '@import "wp:blocks/card.css"; .wp-site-blocks { color: red }',
    '/blocks/card.css' => '.wp-block-card { color: green }',
], $prefixResolver);

if (
    $prefixBundle !== '.wp-block-card{color:green}.wp-site-blocks{color:red}'
    || $prefixResolved !== [['wp:blocks/card.css', '/theme.css']]
) {
    fwrite(STDERR, "Expected wp:-prefixed package imports to resolve through the custom provider\n");
    exit(1);
}

echo 'custom-prefix-resolver: resolved' . PHP_EOL;

$prefixRejected = false;
try {
    (new CssBundler())->bundle('/theme.css', [
        '/theme.css' => "\n  @import \"blocks/card.css\";\n  .wp-site-blocks { color: red }",
        '/blocks/card.css' => '.wp-block-card { color: green }',
    ], $prefixResolver);
} catch (CssBundleException $exception) {
    $prefixRejected = $exception->kind === 'resolver-error'
        && $exception->getMessage() === 'Failed to resolve WP import `blocks/card.css` from `/theme.css` without wp: prefix.'
        && $exception->sourceFile === '/theme.css'
        && $exception->sourceLine === 2
        && $exception->sourceColumn === 3
        && $prefixResolved === [
            ['wp:blocks/card.css', '/theme.css'],
            ['blocks/card.css', '/theme.css'],
        ];
}

if (!$prefixRejected) {
    fwrite(STDERR, "Expected non-prefixed block package imports to report the import location\n");
    exit(1);
}

echo 'custom-prefix-resolver: rejected' . PHP_EOL;

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

$emptyImportResolved = [];
$emptyImportBundle = (new CssBundler())->bundle('/empty-import.css', [
    '/empty-import.css' => '@import ""; .wp-site-blocks { color: red }',
    '/vendor/reset.css' => ':root { --wp--style--block-gap: 1rem }',
], static function (string $specifier, string $originatingFile) use (&$emptyImportResolved): string {
    $emptyImportResolved[] = [$specifier, $originatingFile];

    return '/vendor/reset.css';
});

if (
    $emptyImportBundle !== ':root{--wp--style--block-gap:1rem}.wp-site-blocks{color:red}'
    || $emptyImportResolved !== [['', '/empty-import.css']]
) {
    fwrite(STDERR, "Expected empty @import source to reach resolver and inline shared CSS\n");
    exit(1);
}

echo 'empty-import-source: resolved' . PHP_EOL;

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

$repeatedLayerBundle = (new CssBundler())->bundle('/layered-theme.css', [
    '/layered-theme.css' => <<<'CSS'
@layer reset, theme.blocks;
@import "blocks/card.css" layer(theme.blocks);
@import "blocks/gallery.css";
@import "blocks/card.css" layer(theme.blocks);
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/card.css' => <<<'CSS'
@import "../shared/block-tokens.css";
.wp-block-card {
  color: green;
}
CSS,
    '/blocks/gallery.css' => <<<'CSS'
@import "../shared/block-tokens.css";
.wp-block-gallery {
  color: blue;
}
CSS,
    '/shared/block-tokens.css' => ':root { --wp--preset--color--brand: purple; }',
]);

if ($repeatedLayerBundle !== '@layer reset;@layer theme.blocks{:root{--wp--preset--color--brand:purple}.wp-block-card{color:green}}.wp-block-gallery{color:#00f}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected repeated layered block imports to merge descendants at the first layer occurrence\n");
    exit(1);
}

echo 'repeated-layer-import-descendants: merged' . PHP_EOL;

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

$replacementCharacter = "\xEF\xBF\xBD";
$surrogateResolved = [];
$surrogateEscapeBundle = (new CssBundler())->bundle('/surrogate-import.css', [
    '/surrogate-import.css' => <<<'CSS'
@import "pkg:theme\d800-tokens.css";
.wp-site-blocks {
  color: red;
}
CSS,
    '/vendor/theme-tokens.css' => ':root { --wp--preset--spacing--block-gap: 1rem; }',
], static function (string $specifier, string $originatingFile) use (&$surrogateResolved, $replacementCharacter): string {
    $surrogateResolved[] = [$specifier, $originatingFile];
    if ($specifier !== 'pkg:theme' . $replacementCharacter . '-tokens.css') {
        throw new RuntimeException("Unexpected surrogate import specifier {$specifier}");
    }

    return '/vendor/theme-tokens.css';
});

if (
    $surrogateEscapeBundle !== ':root{--wp--preset--spacing--block-gap:1rem}.wp-site-blocks{color:red}'
    || $surrogateResolved !== [['pkg:theme' . $replacementCharacter . '-tokens.css', '/surrogate-import.css']]
) {
    fwrite(STDERR, "Expected surrogate import escapes to resolve with replacement characters\n");
    exit(1);
}

echo 'surrogate-import-escape: resolved' . PHP_EOL;

$nullByte = "\0";
$nullByteResolved = [];
$nullByteImportBundle = (new CssBundler())->bundle('/null-byte-import.css', [
    '/null-byte-import.css' => '@import "pkg:theme' . $nullByte . '-tokens.css"; @import url(pkg:icon' . $nullByte . '.css); .wp-site-blocks { color: red; }',
    '/vendor/theme-tokens.css' => ':root { --wp--preset--spacing--block-gap: 1rem; }',
    '/vendor/icon.css' => '.wp-block-icon { color: blue; }',
], static function (string $specifier, string $originatingFile) use (&$nullByteResolved, $replacementCharacter): string {
    $nullByteResolved[] = [$specifier, $originatingFile];

    return match ($specifier) {
        'pkg:theme' . $replacementCharacter . '-tokens.css' => '/vendor/theme-tokens.css',
        'pkg:icon' . $replacementCharacter . '.css' => '/vendor/icon.css',
        default => throw new RuntimeException("Unexpected null-byte import specifier {$specifier}"),
    };
});

if (
    $nullByteImportBundle !== ':root{--wp--preset--spacing--block-gap:1rem}.wp-block-icon{color:#00f}.wp-site-blocks{color:red}'
    || $nullByteResolved !== [
        ['pkg:theme' . $replacementCharacter . '-tokens.css', '/null-byte-import.css'],
        ['pkg:icon' . $replacementCharacter . '.css', '/null-byte-import.css'],
    ]
) {
    fwrite(STDERR, "Expected literal null bytes in import sources to resolve with replacement characters\n");
    exit(1);
}

echo 'null-byte-import-source: resolved' . PHP_EOL;

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

$unknownStatementReads = [];
$unknownStatementResolved = [];
$unknownStatementImportBundle = (new CssBundler())->bundleWithReader(
    '/unknown-statement-import.css',
    static function (string $file) use (&$unknownStatementReads): string {
        $unknownStatementReads[] = $file;

        return $file === '/unknown-statement-import.css'
            ? '@wp-bundle meta; @import "pkg:card\2e css" layer(theme.blocks) supports(display: grid) screen; .wp-site-blocks { color: red }'
            : '.wp-block-card { color: green; }';
    },
    static function (string $specifier, string $originatingFile) use (&$unknownStatementResolved): string {
        $unknownStatementResolved[] = [$specifier, $originatingFile];

        return '/blocks/card.css';
    }
);

if (
    $unknownStatementImportBundle !== '@wp-bundle meta;@import "pkg:card.css" layer(theme.blocks) supports(display:grid) screen;.wp-site-blocks{color:red}'
    || $unknownStatementReads !== ['/unknown-statement-import.css', '/blocks/card.css']
    || $unknownStatementResolved !== [['pkg:card.css', '/unknown-statement-import.css']]
) {
    fwrite(STDERR, "Expected statement-form unknown at-rule imports to resolve but remain preserved\n");
    exit(1);
}

echo 'unknown-statement-import: preserved-after-resolution' . PHP_EOL;

$unknownStatementMappedReads = [];
$unknownStatementMappedResolved = [];
$unknownStatementMappedBundle = (new CssBundler())->bundleWithReaderSourceMap(
    '/unknown-statement-map.css',
    static function (string $file) use (&$unknownStatementMappedReads): string {
        $unknownStatementMappedReads[] = $file;

        return $file === '/unknown-statement-map.css'
            ? '@wp-bundle meta; @import "pkg:card\2e css" layer(theme.blocks) supports(display: grid) screen; .wp-site-blocks { color: red }'
            : '.wp-block-card { color: green; }';
    },
    static function (string $specifier, string $originatingFile) use (&$unknownStatementMappedResolved): string {
        $unknownStatementMappedResolved[] = [$specifier, $originatingFile];

        return '/blocks/card.css';
    },
    '/'
);
$unknownStatementMappedMap = $unknownStatementMappedBundle['sourceMap']->toArray(null, false);

if (
    $unknownStatementMappedBundle['code'] !== '@wp-bundle meta;@import "pkg:card.css" layer(theme.blocks) supports(display:grid) screen;.wp-site-blocks{color:red}'
    || $unknownStatementMappedReads !== ['/unknown-statement-map.css', '/blocks/card.css']
    || $unknownStatementMappedResolved !== [['pkg:card.css', '/unknown-statement-map.css']]
    || $unknownStatementMappedMap['sources'] !== ['unknown-statement-map.css', 'blocks/card.css']
    || $unknownStatementMappedMap['sourcesContent'] !== [
        '@wp-bundle meta; @import "pkg:card\2e css" layer(theme.blocks) supports(display: grid) screen; .wp-site-blocks { color: red }',
        '.wp-block-card { color: green; }',
    ]
) {
    fwrite(STDERR, "Expected preserved unknown at-rule imports to retain resolved source-map sources\n");
    exit(1);
}

echo 'unknown-statement-source-map: collected' . PHP_EOL;

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
@import url( "blocks/quote-card.css" ) screen;
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/quote-card.css' => '.wp-block-quote { color: green; }',
]);

if ($quotedUrlImportBundle !== '@media screen{.wp-block-quote{color:green}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected quoted url() imports with whitespace to resolve\n");
    exit(1);
}

echo 'quoted-url-import-whitespace: resolved' . PHP_EOL;

$unquotedUrlImportWhitespaceBundle = (new CssBundler())->bundle('/unquoted-url-import-whitespace.css', [
    '/unquoted-url-import-whitespace.css' => <<<'CSS'
@import url( blocks/commented-card.css ) layer(theme.blocks) screen;
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/commented-card.css' => '.wp-block-card { color: green; }',
]);

if ($unquotedUrlImportWhitespaceBundle !== '@media screen{@layer theme.blocks{.wp-block-card{color:green}}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected unquoted url() import whitespace to be trimmed before resolution\n");
    exit(1);
}

echo 'unquoted-url-import-whitespace: resolved' . PHP_EOL;

try {
    (new CssBundler())->bundleWithReader(
        '/commented-url-import.css',
        static function (string $file): string {
            return $file === '/commented-url-import.css'
                ? '@import url(/* generated by theme build */ blocks/card.css); .wp-site-blocks { color: red; }'
                : '.wp-block-card { color: green; }';
        }
    );

    fwrite(STDERR, "Expected comments inside url() import sources to be rejected\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Unexpected token BadUrl("/* generated by theme build */ blocks/card.css")'
        || $exception->sourceFile !== '/commented-url-import.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 8
    ) {
        fwrite(STDERR, 'Unexpected commented url() import diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'commented-url-import: rejected-before-resolution' . PHP_EOL;
}

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
        || $exception->getMessage() !== 'Unexpected token Ident("theme")'
        || $exception->sourceFile !== '/bad-quoted-url-import.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 30
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
        || $exception->getMessage() !== 'Unexpected token BadUrl("blocks/card hero.css")'
        || $exception->sourceFile !== '/bad-url-import.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 8
    ) {
        fwrite(STDERR, 'Unexpected bad url() import diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'bad-url-import: rejected-before-resolution' . PHP_EOL;
}

$rawUrlDelimiterBundle = (new CssBundler())->bundle('/raw-url-delimiters.css', [
    '/raw-url-delimiters.css' => '@import url(blocks/card[hero.css); @import url(blocks/card{hero.css); .wp-site-blocks { color: red; }',
    '/blocks/card[hero.css' => '.wp-block-card-bracket { color: green; }',
    '/blocks/card{hero.css' => '.wp-block-card-brace { color: blue; }',
]);

if ($rawUrlDelimiterBundle !== '.wp-block-card-bracket{color:green}.wp-block-card-brace{color:#00f}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected raw url() import delimiters to stay in block stylesheet specifiers\n");
    exit(1);
}

echo 'raw-url-import-delimiters: resolved' . PHP_EOL;

$badRawUrlDelimiterReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/bad-raw-url-delimiter.css',
        static function (string $file) use (&$badRawUrlDelimiterReads): string {
            $badRawUrlDelimiterReads[] = $file;

            return $file === '/bad-raw-url-delimiter.css'
                ? '@import url(blocks/card(hero.css); .wp-site-blocks { color: red; }'
                : '.wp-block-card { color: green; }';
        }
    );

    fwrite(STDERR, "Expected unmatched raw url() import parenthesis to be rejected before reading block CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Unexpected token BadUrl("blocks/card(hero.css")'
        || $exception->sourceFile !== '/bad-raw-url-delimiter.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 8
        || $badRawUrlDelimiterReads !== ['/bad-raw-url-delimiter.css']
    ) {
        fwrite(STDERR, 'Unexpected unmatched raw url() delimiter diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'bad-raw-url-import-delimiter: rejected-before-read' . PHP_EOL;
}

try {
    (new CssBundler())->bundle('/bad-raw-url-tail.css', [
        '/bad-raw-url-tail.css' => '@import url(blocks/card)hero.css); .wp-site-blocks { color: red; }',
        '/blocks/card' => '.wp-block-card { color: green; }',
    ]);

    fwrite(STDERR, "Expected trailing raw url() import tokens to be rejected\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Unexpected token Delim(".")'
        || $exception->sourceFile !== '/bad-raw-url-tail.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 29
    ) {
        fwrite(STDERR, 'Unexpected trailing raw url() diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'bad-raw-url-import-tail: rejected-before-resolution' . PHP_EOL;
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

$badImportMediaFunctionReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/bad-import-media-function.css',
        static function (string $file) use (&$badImportMediaFunctionReads): string {
            $badImportMediaFunctionReads[] = $file;

            return $file === '/bad-import-media-function.css'
                ? '@import "blocks/card.css" screen and foo(bar); .wp-site-blocks { color: red; }'
                : '.wp-block-card { color: green; }';
        }
    );

    fwrite(STDERR, "Expected top-level import media function to be rejected before reading block CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Unexpected token Function("foo")'
        || $exception->sourceFile !== '/bad-import-media-function.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 37
        || $badImportMediaFunctionReads !== ['/bad-import-media-function.css']
    ) {
        fwrite(STDERR, 'Unexpected import media function diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'malformed-import-media-function: rejected-before-read' . PHP_EOL;
}

$badImportSupportsReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/bad-import-supports.css',
        static function (string $file) use (&$badImportSupportsReads): string {
            $badImportSupportsReads[] = $file;

            return $file === '/bad-import-supports.css'
                ? '@import "blocks/card.css" supports(not/**/display: grid); .wp-site-blocks { color: red; }'
                : '.wp-block-card { color: green; }';
        }
    );

    fwrite(STDERR, "Expected malformed import supports condition to be rejected before reading block CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Unexpected token Ident("display")'
        || $exception->sourceFile !== '/bad-import-supports.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 39
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

$escapedEquivalentLayerBundle = (new CssBundler())->bundle('/escaped-equivalent-layer-entry.css', [
    '/escaped-equivalent-layer-entry.css' => '@import "blocks/card.css" layer(plugin\2e cards); @import "blocks/card.css" layer(plugin\.cards); .wp-site-blocks { color: red }',
    '/blocks/card.css' => '.wp-block-card { color: green }',
]);

if ($escapedEquivalentLayerBundle !== '@layer plugin\\.cards{.wp-block-card{color:green}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected escaped-equivalent import layer names to merge one block stylesheet\n");
    exit(1);
}

echo 'escaped-equivalent-layer-imports: merged' . PHP_EOL;

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

$badLayerStatementReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/bad-layer-statement.css',
        static function (string $file) use (&$badLayerStatementReads): string {
            $badLayerStatementReads[] = $file;

            return $file === '/bad-layer-statement.css'
                ? '@layer theme.blocks,; @import "blocks/card.css"; .wp-site-blocks { color: red }'
                : '.wp-block-card { color: green }';
        }
    );

    fwrite(STDERR, "Expected malformed @layer statement to fail before reading block imports\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Unexpected token Semicolon'
        || $exception->sourceFile !== '/bad-layer-statement.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 22
        || $badLayerStatementReads !== ['/bad-layer-statement.css']
    ) {
        fwrite(STDERR, 'Unexpected malformed @layer diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'bad-layer-statement-import: rejected-before-read' . PHP_EOL;
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

try {
    (new CssBundler())->bundle('/theme.css', [
        '/theme.css' => '@import "tokens.css"; .wp-site-blocks { color: red }',
        '/tokens.css' => ':root { --wp--style--block-gap: 1.5rem }',
    ], static fn (): array => [
        'external' => 'https://cdn.example/theme-tokens.css',
        'file' => '/tokens.css',
    ]);

    fwrite(STDERR, "Expected ambiguous resolver object diagnostic for block-theme CSS\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'resolver-error'
        || $exception->getMessage() !== 'data did not match any variant of untagged enum ResolveResult'
        || $exception->sourceFile !== '/theme.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 1
    ) {
        fwrite(STDERR, 'Unexpected ambiguous resolver diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'resolver-ambiguous-shape: rejected' . PHP_EOL;
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

$validImportBlockReads = [];
try {
    (new CssBundler())->bundleWithReader(
        '/valid-import-block.css',
        static function (string $file) use (&$validImportBlockReads): string {
            $validImportBlockReads[] = $file;

            return $file === '/valid-import-block.css'
                ? '@import "tokens.css" layer(theme.tokens) {}; .wp-site-blocks { color: red }'
                : ':root { --wp--preset--color--brand: blue; }';
        }
    );

    fwrite(STDERR, "Expected valid block-form import to reject at curly block before block-theme graph resolution\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Unexpected token CurlyBracketBlock'
        || $exception->sourceFile !== '/valid-import-block.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 43
        || $validImportBlockReads !== ['/valid-import-block.css']
    ) {
        fwrite(STDERR, 'Unexpected valid block-form import diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    echo 'valid-import-block: rejected-at-curly-block' . PHP_EOL;
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

$externalSupportsUnknownBundle = (new CssBundler())->bundle('/external-supports-unknown.css', [
    '/external-supports-unknown.css' => <<<'CSS'
@import "cdn:theme-variant.css" supports((--wp-theme-variant));
@import "tokens.css";
.wp-site-blocks {
  color: red;
}
CSS,
    '/tokens.css' => ':root { --wp--preset--color--brand: blue; }',
], static function (string $specifier): array|string {
    if ($specifier === 'cdn:theme-variant.css') {
        return ['external' => 'https://cdn.example/wp-theme-variant.css'];
    }

    return '/' . ltrim($specifier, './');
});

if ($externalSupportsUnknownBundle !== '@import "https://cdn.example/wp-theme-variant.css" supports((--wp-theme-variant));:root{--wp--preset--color--brand:blue}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected external import supports general-enclosed condition to stay wrapped\n");
    exit(1);
}

echo 'external-supports-unknown: preserved' . PHP_EOL;

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

$repeatedImportBundle = (new CssBundler())->bundle('/repeated-import-entry.css', [
    '/repeated-import-entry.css' => <<<'CSS'
@import "blocks/card.css";
@import "cdn:editor-reset.css";
@import "blocks/gallery.css";
@import "blocks/card.css";
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/card.css' => <<<'CSS'
@import "../tokens.css";
.wp-block-card {
  color: green;
}
CSS,
    '/blocks/gallery.css' => '.wp-block-gallery { color: blue; }',
    '/tokens.css' => ':root { --wp--preset--color--brand: purple; }',
], static function (string $specifier, string $originatingFile): array|string {
    if ($specifier === 'cdn:editor-reset.css') {
        return ['external' => 'https://cdn.example/editor-reset.css'];
    }

    return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
});

if ($repeatedImportBundle !== '@import "https://cdn.example/editor-reset.css";.wp-block-gallery{color:#00f}:root{--wp--preset--color--brand:purple}.wp-block-card{color:green}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected repeated block import to move after external and sibling imports\n");
    exit(1);
}

echo 'repeated-import-position: preserved' . PHP_EOL;

$cycleLayerBundle = (new CssBundler())->bundle('/cycle-layer-entry.css', [
    '/cycle-layer-entry.css' => <<<'CSS'
@import "blocks/card.css" layer(theme.blocks) screen;
.wp-site-blocks {
  color: red;
}
CSS,
    '/blocks/card.css' => <<<'CSS'
@import "../cycle-layer-entry.css" layer(cycle) print;
.wp-block-card {
  color: green;
}
CSS,
]);

if ($cycleLayerBundle !== '@layer theme.blocks.cycle;@media screen{@layer theme.blocks{.wp-block-card{color:green}}}.wp-site-blocks{color:red}') {
    fwrite(STDERR, "Expected recursive block import layer to emit an empty layer statement without wrapping the entry CSS\n");
    exit(1);
}

echo 'cycle-layer-import: preserved' . PHP_EOL;

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

$readerAbsoluteUrlFiles = [
    '/reader-absolute-url.css' => <<<'CSS'
@import "https://cdn.example/wp-block-reset.css" screen;
@import "//cdn.example/wp-print.css";
@import "blocks/card.css";
.wp-site-blocks {
  color: red;
}
CSS,
    '/https://cdn.example/wp-block-reset.css' => '.wp-block-reset { color: green; }',
    '//cdn.example/wp-print.css' => '.wp-block-print { color: purple; }',
    '/blocks/card.css' => '.wp-block-card { color: blue; }',
];
$readerAbsoluteUrlReads = [];
$readerAbsoluteUrlBundle = (new CssBundler())->bundleWithReader(
    '/reader-absolute-url.css',
    static function (string $file) use (&$readerAbsoluteUrlReads, $readerAbsoluteUrlFiles): string {
        $readerAbsoluteUrlReads[] = $file;
        if (!array_key_exists($file, $readerAbsoluteUrlFiles)) {
            throw new RuntimeException("Missing reader-backed absolute URL import file {$file}");
        }

        return $readerAbsoluteUrlFiles[$file];
    }
);

if (
    $readerAbsoluteUrlBundle !== '@media screen{.wp-block-reset{color:green}}.wp-block-print{color:purple}.wp-block-card{color:#00f}.wp-site-blocks{color:red}'
    || $readerAbsoluteUrlReads !== [
        '/reader-absolute-url.css',
        '/https://cdn.example/wp-block-reset.css',
        '//cdn.example/wp-print.css',
        '/blocks/card.css',
    ]
) {
    fwrite(STDERR, "Expected reader-backed URL imports to resolve through source-provider paths\n");
    exit(1);
}

echo 'reader-absolute-url-imports: resolved' . PHP_EOL;

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

$cssModuleDependencyMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['modules/_tokens.scss'],
    'sourcesContent' => ['.token { color: $brand-blue; }'],
    'names' => [],
], JSON_THROW_ON_ERROR));
$cssModuleDependencyMappedBundle = (new CssBundler())->bundleCssModulesWithSourceMap('/modules/card.css', [
    '/modules/card.css' => '.wp-block-card { composes: token from "./tokens.css"; color: red }',
    '/modules/tokens.css' => ".token { color: blue }\n/*# sourceMappingURL={$cssModuleDependencyMap} */",
], null, [
    'hashes' => [
        '/modules/card.css' => 'card',
        '/modules/tokens.css' => 'tok',
    ],
], '/');
$cssModuleDependencySources = $cssModuleDependencyMappedBundle['sourceMap']->toArray(null, false)['sources'];
if (
    $cssModuleDependencyMappedBundle['code'] !== '.tok_token{color:#00f}.card_wp-block-card{color:red}'
    || ($cssModuleDependencyMappedBundle['exports']['wp-block-card']['composes'][0]['name'] ?? null) !== 'tok_token'
    || $cssModuleDependencySources !== ['modules/card.css', 'modules/_tokens.scss']
) {
    fwrite(STDERR, "Expected CSS Modules dependency source-map remapping output\n");
    exit(1);
}

echo 'css-modules-dependency-source-map: remapped' . PHP_EOL;

$emptyCssModuleResolved = [];
$emptyCssModuleBundle = (new CssBundler())->bundleCssModules('/modules/empty-card.css', [
    '/modules/empty-card.css' => <<<'CSS'
.wp-block-card {
  composes: token from "";
  color: red;
}
CSS,
    '/modules/empty-tokens.css' => '.token { color: blue }',
], static function (string $specifier, string $originatingFile) use (&$emptyCssModuleResolved): string {
    $emptyCssModuleResolved[] = [$specifier, $originatingFile];

    return '/modules/empty-tokens.css';
}, [
    'hashes' => [
        '/modules/empty-card.css' => 'card',
        '/modules/empty-tokens.css' => 'tok',
    ],
]);

if (
    $emptyCssModuleBundle['code'] !== '.tok_token{color:#00f}.card_wp-block-card{color:red}'
    || ($emptyCssModuleBundle['exports']['wp-block-card']['composes'][0]['name'] ?? null) !== 'tok_token'
    || $emptyCssModuleResolved !== [['', '/modules/empty-card.css']]
) {
    fwrite(STDERR, "Expected empty CSS Modules from specifier to resolve through the bundle graph\n");
    exit(1);
}

echo 'css-modules-empty-from: resolved' . PHP_EOL;

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

$envFromResolverCalled = false;
try {
    (new CssBundler())->bundleCssModules('/modules/env-card.css', [
        '/modules/env-card.css' => <<<'CSS'
.wp-block-card {
  margin: env(--wp-card-gap from "pkg:tokens.css", 1rem);
  color: red;
}
CSS,
    ], static function () use (&$envFromResolverCalled): string {
        $envFromResolverCalled = true;
        throw new RuntimeException('Unexpected env() CSS Modules dependency resolution');
    }, [
        'dashedIdents' => true,
    ]);

    fwrite(STDERR, "Expected env() from syntax to stop before block dependency resolution\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'Unexpected token Ident("from")'
        || $exception->sourceFile !== '/modules/env-card.css'
        || $exception->sourceLine !== 2
        || $exception->sourceColumn !== 28
        || $envFromResolverCalled
    ) {
        fwrite(STDERR, 'Unexpected env() from diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'css-modules-env-from: rejected-before-resolve' . PHP_EOL;

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

$nestedComposesResolverCalled = false;
try {
    (new CssBundler())->bundleCssModules('/modules/nested-card.css', [
        '/modules/nested-card.css' => <<<'CSS'
@supports (composes: token from "../missing-tokens.css") {
  .wp-block-card {
    composes: token from "../missing-tokens.css";
    color: red;
  }
}
CSS,
    ], static function (string $specifier, string $originatingFile) use (&$nestedComposesResolverCalled): string {
        $nestedComposesResolverCalled = true;
        throw new RuntimeException("Unexpected nested CSS Modules resolution for {$specifier} from {$originatingFile}");
    });

    fwrite(STDERR, "Expected nested CSS Modules composes to stop before dependency resolution\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'The `composes` property cannot be used within nested rules'
        || $exception->sourceFile !== '/modules/nested-card.css'
        || $exception->sourceLine !== 3
        || $exception->sourceColumn !== 14
        || $nestedComposesResolverCalled
    ) {
        fwrite(STDERR, 'Unexpected nested CSS Modules composes diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'css-modules-nested-composes-before-resolve: rejected' . PHP_EOL;

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

$cssModuleExternalAfterHoist = (new CssBundler())->bundleCssModules('/blocks/externals.css', [
    '/blocks/externals.css' => <<<'CSS'
@import "cdn:editor-reset.css";
@import "../theme.css";

.wp-block-card {
  composes: token from "../tokens.css";
  color: red;
}
CSS,
    '/theme.css' => '.wp-block-theme { color: blue; }',
    '/tokens.css' => '.token { color: green; }',
], static function (string $specifier, string $originatingFile): array|string {
    if ($specifier === 'cdn:editor-reset.css') {
        return ['external' => 'https://cdn.example/editor-reset.css'];
    }

    return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
}, [
    'hashes' => [
        '/blocks/externals.css' => 'card',
        '/theme.css' => 'theme',
        '/tokens.css' => 'tok',
    ],
]);

if (
    $cssModuleExternalAfterHoist['code'] !== '.tok_token{color:green}@import "https://cdn.example/editor-reset.css";.theme_wp-block-theme{color:#00f}.card_wp-block-card{color:red}'
    || ($cssModuleExternalAfterHoist['exports']['wp-block-card']['composes'][0]['name'] ?? null) !== 'tok_token'
) {
    fwrite(STDERR, "Expected CSS Modules dependencies to hoist before external editor imports without triggering external-order diagnostics\n");
    exit(1);
}

echo 'css-modules-external-after-hoist: preserved' . PHP_EOL;

$cssModuleVarDependency = (new CssBundler())->bundleCssModules('/blocks/card.css', [
    '/blocks/card.css' => <<<'CSS'
@import "pkg:theme.css";

.wp-block-card {
  margin: var(--wp-card-gap from "pkg:tokens.css", var(--fallback-gap from "pkg:fallback.css"));
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
    $cssModuleVarDependency['code'] !== '.tok_tokens{--tok_wp-card-gap:24px}.fallback_fallback{--fallback_fallback-gap:12px}.theme_theme{color:#00f}.card_wp-block-card{margin:var(--tok_wp-card-gap,var(--fallback_fallback-gap));color:red}'
    || ($cssModuleVarDependency['exports']['wp-block-card']['name'] ?? null) !== 'card_wp-block-card'
) {
    fwrite(STDERR, "Unexpected CSS Modules var() dependency graph output\n");
    exit(1);
}

echo 'css-modules-var-dependency: resolved' . PHP_EOL;

$conditionalVarReads = [];
$conditionalVarResolverCalled = false;
$conditionalVarBundle = (new CssBundler())->bundleCssModulesWithReader(
    '/blocks/conditional-card.css',
    static function (string $file) use (&$conditionalVarReads): string {
        $conditionalVarReads[] = $file;
        if ($file === '/blocks/conditional-card.css') {
            return <<<'CSS'
@media screen {
  .wp-block-card {
    margin: var(--wp-card-gap from "pkg:missing.css", 1rem);
    color: blue;
  }
}
CSS;
        }

        throw new RuntimeException("Unexpected conditional var() dependency read for {$file}");
    },
    static function () use (&$conditionalVarResolverCalled): string {
        $conditionalVarResolverCalled = true;
        throw new RuntimeException('Unexpected conditional var() dependency resolution');
    },
    [
        'hashes' => [
            '/blocks/conditional-card.css' => 'card',
        ],
        'dashedIdents' => true,
    ]
);

if (
    $conditionalVarBundle['code'] !== '@media screen{.card_wp-block-card{margin:var(--card_wp-card-gap,1rem);color:#00f}}'
    || $conditionalVarReads !== ['/blocks/conditional-card.css']
    || $conditionalVarResolverCalled
    || ($conditionalVarBundle['exports']['--wp-card-gap']['name'] ?? null) !== '--card_wp-card-gap'
) {
    fwrite(STDERR, "Expected conditional CSS Modules var() from syntax to scope locally without dependency resolution\n");
    exit(1);
}

echo 'css-modules-conditional-var-dependency: scoped-without-resolve' . PHP_EOL;

$directVarLocationResolverCalls = [];
try {
    (new CssBundler())->bundleCssModules('/blocks/direct-var-location.css', [
        '/blocks/direct-var-location.css' => <<<'CSS'
@media screen {
  .wp-block-card--preview {
    color: var(--wp-card-color from "pkg:tokens.css", red);
  }
}

.wp-block-card {
  color: var(--wp-card-color from "pkg:tokens.css", red);
}
CSS,
    ], static function (string $specifier, string $originatingFile) use (&$directVarLocationResolverCalls): string {
        $directVarLocationResolverCalls[] = [$specifier, $originatingFile];
        throw new RuntimeException("Cannot resolve {$specifier} from {$originatingFile}");
    }, [
        'dashedIdents' => true,
    ]);

    fwrite(STDERR, "Expected direct CSS Modules var() dependency resolver diagnostic\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'resolver-error'
        || $exception->getMessage() !== 'Cannot resolve pkg:tokens.css from /blocks/direct-var-location.css'
        || $exception->sourceFile !== '/blocks/direct-var-location.css'
        || $exception->sourceLine !== 7
        || $exception->sourceColumn !== 1
        || $directVarLocationResolverCalls !== [
            ['pkg:tokens.css', '/blocks/direct-var-location.css'],
        ]
    ) {
        fwrite(STDERR, 'Unexpected direct CSS Modules var() dependency diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'css-modules-direct-var-location: direct-style' . PHP_EOL;

$conditionalComposesFiles = [
    '/modules/conditional-card.css' => '@import "blocks/card.css" layer(theme.blocks) screen; .wp-site-blocks { color: red }',
    '/modules/blocks/card.css' => '.wp-block-card { composes: token from "tokens.css"; color: green }',
    '/modules/blocks/tokens.css' => '.token { color: blue }',
];
$conditionalComposesReads = [];
try {
    (new CssBundler())->bundleCssModulesWithReader(
        '/modules/conditional-card.css',
        static function (string $file) use ($conditionalComposesFiles, &$conditionalComposesReads): string {
            $conditionalComposesReads[] = $file;
            if (!array_key_exists($file, $conditionalComposesFiles)) {
                throw new RuntimeException("Missing conditional CSS Modules fixture {$file}");
            }

            return $conditionalComposesFiles[$file];
        },
        null,
        [
            'hashes' => [
                '/modules/conditional-card.css' => 'entry',
                '/modules/blocks/card.css' => 'card',
                '/modules/blocks/tokens.css' => 'tok',
            ],
        ]
    );

    fwrite(STDERR, "Expected conditional CSS Modules composes import to be rejected\n");
    exit(1);
} catch (CssBundleException $exception) {
    if (
        $exception->kind !== 'parser-error'
        || $exception->getMessage() !== 'The `composes` property cannot be used within nested rules'
        || $exception->sourceFile !== '/modules/blocks/card.css'
        || $exception->sourceLine !== 1
        || $exception->sourceColumn !== 27
        || $conditionalComposesReads !== [
            '/modules/conditional-card.css',
            '/modules/blocks/card.css',
            '/modules/blocks/tokens.css',
        ]
    ) {
        fwrite(STDERR, 'Unexpected conditional CSS Modules composes diagnostic: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'css-modules-conditional-composes-location: rejected' . PHP_EOL;
