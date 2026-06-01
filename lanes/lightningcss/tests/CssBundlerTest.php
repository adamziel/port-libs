<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssBundleException;
use PortLibs\LightningCSS\CssBundler;
use PortLibs\LightningCSS\SourceMap;

$bundle = static fn (array $files, string $entry, ?callable $resolver = null): string => (new CssBundler())->bundle($entry, $files, $resolver);
$bundleModules = static fn (array $files, string $entry, ?callable $resolver = null, array $options = []): array => (new CssBundler())->bundleCssModules($entry, $files, $resolver, $options);
$withTempFiles = static function (array $files, callable $callback): mixed {
    $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'lightningcss-bundle-' . bin2hex(random_bytes(6));
    foreach ($files as $path => $css) {
        $target = $root . '/' . ltrim((string) $path, '/');
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create temporary bundle directory {$directory}");
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
$moduleExport = static fn (string $name, array $composes = []): array => [
    'name' => $name,
    'composes' => $composes,
    'isReferenced' => false,
];
$moduleLocal = static fn (string $name): array => ['type' => 'local', 'name' => $name];
$moduleGlobal = static fn (string $name): array => ['type' => 'global', 'name' => $name];
$moduleDashed = static fn (string $name, bool $isReferenced = false): array => [
    'name' => $name,
    'composes' => [],
    'isReferenced' => $isReferenced,
];

return [
    'css bundler maps upstream resolver import graph order' => static function (TestRunner $t) use ($bundle): void {
        $resolved = [];
        $code = $bundle([
            'foo.css' => <<<'CSS'
@import "root:bar.css";
.foo { color: red; }
CSS,
            'bar.css' => <<<'CSS'
@import "root:hello/world.css";
.bar { color: green; }
CSS,
            'hello/world.css' => '.baz { color: blue; }',
        ], 'foo.css', static function (string $specifier, string $originatingFile) use (&$resolved): string {
            $resolved[] = [$specifier, $originatingFile];

            return substr($specifier, strlen('root:'));
        });

        $t->same('.baz{color:#00f}.bar{color:green}.foo{color:red}', $code);
        $t->same([
            ['root:bar.css', 'foo.css'],
            ['root:hello/world.css', 'bar.css'],
        ], $resolved);
    },
    'css bundler maps upstream default relative resolution' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '.baz{color:#00f}.bar{color:green}.foo{color:red}',
            $bundle([
                'foo.css' => '@import "hello/world.css"; .foo { color: red; }',
                'hello/world.css' => '@import "../bar.css"; .bar { color: green; }',
                'bar.css' => '.baz { color: blue; }',
            ], 'foo.css')
        );

        $t->same(
            '.b{color:green}.a{color:red}',
            $bundle([
                '/a.css' => '@import "./b/c.css"; .a { color: red }',
                '/b/c.css' => '.b { color: green }',
            ], '/a.css')
        );
    },
    'css bundler preserves leading parent segments in relative import resolution' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            ':root{--theme-gap:1rem}.entry{color:red}',
            $bundle([
                'style.css' => '@import "../shared/tokens.css"; .entry { color: red }',
                '../shared/tokens.css' => ':root { --theme-gap: 1rem }',
            ], 'style.css')
        );

        $reads = [];
        $readerFiles = [
            'style.css' => '@import "../shared/tokens.css"; .entry { color: red }',
            '../shared/tokens.css' => ':root { --theme-gap: 1rem }',
        ];
        $readerBundle = (new CssBundler())->bundleWithReader('style.css', static function (string $file) use (&$reads, $readerFiles): string {
            $reads[] = $file;
            if (!array_key_exists($file, $readerFiles)) {
                throw new RuntimeException("Missing reader fixture {$file}");
            }

            return $readerFiles[$file];
        });

        $t->same(':root{--theme-gap:1rem}.entry{color:red}', $readerBundle);
        $t->same(['style.css', '../shared/tokens.css'], $reads);
    },
    'css bundler collects upstream source map sources across resolved import graph' => static function (TestRunner $t): void {
        $result = (new CssBundler())->bundleWithSourceMap('/theme/entry.css', [
            '/theme/entry.css' => <<<'CSS'
@import "https://cdn.example/base.css";
@import "pkg:tokens.css";
@import "card.css";
@import "escaped\000020hero.css";
.entry { color: red }
CSS,
            '/theme/tokens.css' => ':root { --gap: 1rem }',
            '/theme/card.css' => '@import "../shared/button.css"; .card { color: green }',
            '/shared/button.css' => '.button { color: blue }',
            '/theme/escaped hero.css' => '.hero { color: purple }',
        ], static function (string $specifier, string $originatingFile): array|string {
            if (str_starts_with($specifier, 'https:')) {
                return ['external' => $specifier];
            }

            if (str_starts_with($specifier, 'pkg:')) {
                return '/theme/' . substr($specifier, strlen('pkg:'));
            }

            return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
        }, '/theme');

        $t->same(
            '@import "https://cdn.example/base.css";:root{--gap:1rem}.button{color:#00f}.card{color:green}.hero{color:purple}.entry{color:red}',
            $result['code']
        );

        $sourceMap = $result['sourceMap']->toArray(null, false);
        $t->same(['entry.css', 'tokens.css', 'card.css', '../shared/button.css', 'escaped hero.css'], $sourceMap['sources']);
        $t->same([
            "@import \"https://cdn.example/base.css\";\n@import \"pkg:tokens.css\";\n@import \"card.css\";\n@import \"escaped\\000020hero.css\";\n.entry { color: red }",
            ':root { --gap: 1rem }',
            '@import "../shared/button.css"; .card { color: green }',
            '.button { color: blue }',
            '.hero { color: purple }',
        ], $sourceMap['sourcesContent']);
        $t->same('', $sourceMap['mappings']);
    },
    'css bundler source map follows reader-backed source provider resolution' => static function (TestRunner $t): void {
        $files = [
            '/theme/entry.css' => '@import "tokens.css"; @import "../shared/button.css"; .entry { color: red }',
            '/theme/tokens.css' => ':root { --gap: 1rem }',
            '/theme/../shared/button.css' => '.button { color: blue }',
        ];
        $reads = [];
        $result = (new CssBundler())->bundleWithReaderSourceMap(
            '/theme/entry.css',
            static function (string $file) use (&$reads, $files): string {
                $reads[] = $file;
                if (!array_key_exists($file, $files)) {
                    throw new RuntimeException("Missing reader source {$file}");
                }

                return $files[$file];
            },
            null,
            '/theme'
        );

        $t->same(':root{--gap:1rem}.button{color:#00f}.entry{color:red}', $result['code']);
        $t->same(['/theme/entry.css', '/theme/tokens.css', '/theme/../shared/button.css'], $reads);
        $t->same(['entry.css', 'tokens.css', '../shared/button.css'], $result['sourceMap']->toArray(null, false)['sources']);
    },
    'css bundler remaps upstream inline input source maps across imports' => static function (TestRunner $t): void {
        $inputMap = 'data:application/json;base64,' . base64_encode(json_encode([
            'version' => 3,
            'mappings' => 'AAAA',
            'sources' => ['blocks/card.scss'],
            'sourcesContent' => ['.card { color: $theme-green }'],
            'names' => [],
        ], JSON_THROW_ON_ERROR));

        $result = (new CssBundler())->bundleWithSourceMap('/theme/entry.css', [
            '/theme/entry.css' => '@import "blocks/card.css"; .entry { color: red }',
            '/theme/blocks/card.css' => ".card { color: green }\n/*# sourceMappingURL={$inputMap} */",
        ], null, '/theme');

        $data = $result['sourceMap']->toArray(null, false);
        $decoded = SourceMap::decodeVlq($data['mappings']);

        $t->same('.card{color:green}.entry{color:red}', $result['code']);
        $t->same(['entry.css', 'blocks/card.scss'], $data['sources']);
        $t->same([
            '@import "blocks/card.css"; .entry { color: red }',
            '.card { color: $theme-green }',
        ], $data['sourcesContent']);
        $t->same('ACAA', $data['mappings']);
        $t->same(1, $decoded[0]['sourceIndex']);
        $t->same(0, $decoded[0]['originalLine']);
        $t->same(0, $decoded[0]['originalColumn']);
    },
    'css bundler preserves unused upstream inline input source map sources across imports' => static function (TestRunner $t): void {
        $inputMap = 'data:application/json;base64,' . base64_encode(json_encode([
            'version' => 3,
            'mappings' => 'ACAA',
            'sources' => ['blocks/_tokens.scss', 'blocks/generated-card.scss'],
            'sourcesContent' => [
                '$brand: green;',
                '.card { color: $brand }',
            ],
            'names' => [],
        ], JSON_THROW_ON_ERROR));

        $result = (new CssBundler())->bundleWithSourceMap('/theme/entry.css', [
            '/theme/entry.css' => '@import "blocks/generated-card.css"; .entry { color: red }',
            '/theme/blocks/generated-card.css' => ".card { color: green }\n/*# sourceMappingURL={$inputMap} */",
        ], null, '/theme');

        $data = $result['sourceMap']->toArray(null, false);
        $decoded = SourceMap::decodeVlq($data['mappings']);

        $t->same('.card{color:green}.entry{color:red}', $result['code']);
        $t->same(['entry.css', 'blocks/_tokens.scss', 'blocks/generated-card.scss'], $data['sources']);
        $t->same([
            '@import "blocks/generated-card.css"; .entry { color: red }',
            '$brand: green;',
            '.card { color: $brand }',
        ], $data['sourcesContent']);
        $t->same('AEAA', $data['mappings']);
        $t->same(2, $decoded[0]['sourceIndex']);
    },
    'css bundler maps upstream EOF import without semicolon' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '.b{color:green}',
            $bundle([
                '/a.css' => '@import "b.css"',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '.c{color:#00f}.b{color:green}',
            $bundle([
                '/a.css' => '@import "b.css"',
                '/b.css' => '@import "c.css"; .b { color: green }',
                '/c.css' => '.c { color: blue }',
            ], '/a.css')
        );
    },
    'css bundler maps upstream filesystem source provider with custom resolver' => static function (TestRunner $t) use ($withTempFiles): void {
        $withTempFiles([
            'foo.css' => <<<'CSS'
@import "root:bar.css";
.foo { color: red; }
CSS,
            'bar.css' => <<<'CSS'
@import "root:hello/world.css";
.bar { color: green; }
CSS,
            'hello/world.css' => '.baz { color: blue; }',
        ], static function (string $root) use ($t): void {
            $resolved = [];
            $code = (new CssBundler())->bundleFile(
                $root . '/foo.css',
                static function (string $specifier, string $originatingFile) use (&$resolved, $root): string {
                    $resolved[] = [$specifier, $originatingFile];

                    return $root . '/' . substr($specifier, strlen('root:'));
                }
            );

            $t->same('.baz{color:#00f}.bar{color:green}.foo{color:red}', $code);
            $t->same([
                ['root:bar.css', $root . '/foo.css'],
                ['root:hello/world.css', $root . '/bar.css'],
            ], $resolved);
        });
    },
    'css bundler preserves upstream filesystem lexical import identities' => static function (TestRunner $t) use ($withTempFiles): void {
        $withTempFiles([
            'theme/entry.css' => '@import "blocks/card.css"; @import "base.css"; .entry { color: red }',
            'theme/blocks/card.css' => '@import "../base.css"; .card { color: green }',
            'theme/base.css' => '.base { color: blue }',
        ], static function (string $root) use ($t): void {
            $t->same(
                '.base{color:#00f}.card{color:green}.base{color:#00f}.entry{color:red}',
                (new CssBundler())->bundleFile($root . '/theme/entry.css')
            );
        });
    },
    'css bundler maps upstream import prelude ordering diagnostics' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            "/*! bundle */\n@layer reset;.b{color:green}.a{color:red}",
            $bundle([
                '/a.css' => '@charset "utf-8"; @layer reset; /*! bundle */ @import "b.css"; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $rejectedAfterStyle = false;
        try {
            $bundle([
                '/a.css' => '.a { color: red } @import "b.css"; .tail { color: blue }',
                '/b.css' => '.b { color: green }',
            ], '/a.css');
        } catch (CssBundleException $exception) {
            $t->same('parser-error', $exception->kind);
            $t->same('@import rules must precede all rules aside from @charset and @layer statements', $exception->getMessage());
            $t->same('/a.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(26, $exception->sourceColumn);
            $rejectedAfterStyle = true;
        }

        if (!$rejectedAfterStyle) {
            throw new RuntimeException('Expected late @import after style rule exception');
        }

        try {
            $bundle([
                '/a.css' => '@namespace svg "http://www.w3.org/2000/svg"; @import "icons.css"; svg|path { fill: red }',
                '/icons.css' => '.icon { fill: green }',
            ], '/a.css');
        } catch (CssBundleException $exception) {
            $t->same('parser-error', $exception->kind);
            $t->same('/a.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(53, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected late @import after @namespace exception');
    },
    'css bundler treats post-import layer statements as import prelude barriers' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@layer base;.base{color:green}@layer components;.entry{color:red}',
            $bundle([
                '/entry.css' => '@layer base; @import "base.css"; @layer components; .entry { color: red }',
                '/base.css' => '.base { color: green }',
            ], '/entry.css')
        );

        try {
            $bundle([
                '/entry.css' => '@layer base; @import "base.css"; @layer components; @import "card.css"; .entry { color: red }',
                '/base.css' => '.base { color: green }',
                '/card.css' => '.card { color: blue }',
            ], '/entry.css');
        } catch (CssBundleException $exception) {
            $t->same('parser-error', $exception->kind);
            $t->same('@import rules must precede all rules aside from @charset and @layer statements', $exception->getMessage());
            $t->same('/entry.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(60, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected late @import after post-import @layer statement exception');
    },
    'css bundler wraps imported files in supports media and layer conditions' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@supports (color:green){@media print{.b{color:green}}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" supports(color: green) print; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@media (width>=1000px){@layer bar{#box{background:green}}}@layer baz{#box{background:purple}}@layer bar{#box{background:#ff0}}',
            $bundle([
                '/a.css' => <<<'CSS'
@import "b.css" layer(bar) (min-width: 1000px);
@layer baz { #box { background: purple } }
@layer bar { #box { background: yellow } }
CSS,
                '/b.css' => '#box { background: green }',
            ], '/a.css')
        );
    },
    'css bundler ignores upstream charset statements across import graph' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@media screen{.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" screen; .a { color: red }',
                '/b.css' => '@charset "UTF-8"; .b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '.c{color:#00f}.d{color:purple}.b{color:green}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css"; .a { color: red }',
                '/b.css' => '@import "c.css"; @charset "UTF-8"; @import "d.css"; .b { color: green }',
                '/c.css' => '.c { color: blue }',
                '/d.css' => '.d { color: purple }',
            ], '/a.css')
        );

        try {
            $bundle([
                '/a.css' => '@import "b.css"; .a { color: red }',
                '/b.css' => '.b { color: green } @charset "UTF-8"; @import "c.css";',
                '/c.css' => '.c { color: blue }',
            ], '/a.css');
        } catch (CssBundleException $exception) {
            $t->same('parser-error', $exception->kind);
            $t->same('@import rules must precede all rules aside from @charset and @layer statements', $exception->getMessage());
            $t->same('/b.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(46, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected late @import after style and ignored @charset exception');
    },
    'css bundler preserves upstream supports condition grouping across import graph' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@supports ((display:flex) or (display:grid)) and (color:red){.c{color:#00f}}@supports (display:flex) or (display:grid){.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" supports((display: flex) or (display: grid)); .a { color: red }',
                '/b.css' => '@import "c.css" supports(color: red); .b { color: green }',
                '/c.css' => '.c { color: blue }',
            ], '/a.css')
        );

        $t->same(
            '@supports ((display:flex) and (color:red)) or (display:grid){.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" supports((display: flex) and (color: red)); @import "b.css" supports(display: grid); .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@supports (not (display:flex)) and (color:red){.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" supports(not (display: flex)); .a { color: red }',
                '/b.css' => '@import "c.css" supports(color: red);',
                '/c.css' => '.b { color: green }',
            ], '/a.css')
        );
    },
    'css bundler maps upstream url import modifiers with trailing media' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@media print{.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import url(b.css) print; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@supports (display:flex){@media print{.b{color:green}}}.a{color:red}',
            $bundle([
                '/a.css' => '@import url(b.css) supports(display: flex) print; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@media screen{@layer theme.tokens{:root{--gap:1rem}}}.theme{color:red}',
            $bundle([
                '/theme.css' => '@import url(tokens.css) layer(theme.tokens) screen; .theme { color: red }',
                '/tokens.css' => ':root { --gap: 1rem }',
            ], '/theme.css')
        );
    },
    'css bundler treats layer after supports as media like upstream import grammar' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@supports (display:flex){@media layer{@layer theme.blocks{.b{color:green}}}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" layer(theme.blocks) supports(display: flex) layer; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@import "https://cdn.example/theme.css" supports(display:flex) layer;.b{color:green}',
            $bundle([
                '/a.css' => '@import "https://cdn.example/theme.css" supports(display: flex) layer; @import "b.css";',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );
    },
    'css bundler decodes upstream escaped import specifiers before resolution' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '.theme-tokens{color:green}@media screen{.icon{color:#00f}}.entry{color:red}',
            $bundle([
                '/entry.css' => <<<'CSS'
@import "./theme\000020components.css";
@import url(./icons\2f arrow.css) screen;
.entry { color: red }
CSS,
                '/theme components.css' => '.theme-tokens { color: green }',
                '/icons/arrow.css' => '.icon { color: blue }',
            ], '/entry.css')
        );

        $resolved = [];
        $t->same(
            '.token{color:green}.entry{color:red}',
            $bundle([
                '/entry.css' => '@import "pkg:theme\2d tokens.css"; .entry { color: red }',
                '/vendor/tokens.css' => '.token { color: green }',
            ], '/entry.css', static function (string $specifier, string $originatingFile) use (&$resolved): string {
                $resolved[] = [$specifier, $originatingFile];

                return '/vendor/tokens.css';
            })
        );
        $t->same([['pkg:theme-tokens.css', '/entry.css']], $resolved);

        $t->same(
            '@import "https://fonts.example/css";.entry{color:red}',
            $bundle([
                '/entry.css' => '@import "https\3a //fonts.example/css"; .entry { color: red }',
            ], '/entry.css')
        );
    },
    'css bundler parses escaped import source and modifier identifiers before graph resolution' => static function (TestRunner $t) use ($bundle): void {
        $resolved = [];
        $code = $bundle([
            '/entry.css' => <<<'CSS'
@import u\72l(pkg:card.css) l\61yer(theme.blocks) s\75pports(display: grid) screen;
@import \75 rl("tokens.css") \6c ayer;
.entry { color: red }
CSS,
            '/vendor/card.css' => '.card { color: green }',
            '/tokens.css' => ':root { --gap: 1rem }',
        ], '/entry.css', static function (string $specifier, string $originatingFile) use (&$resolved): string {
            $resolved[] = [$specifier, $originatingFile];

            if ($specifier === 'pkg:card.css') {
                return '/vendor/card.css';
            }

            return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
        });

        $t->same(
            '@supports (display:grid){@media screen{@layer theme.blocks{.card{color:green}}}}@layer{:root{--gap:1rem}}.entry{color:red}',
            $code
        );
        $t->same([
            ['pkg:card.css', '/entry.css'],
            ['tokens.css', '/entry.css'],
        ], $resolved);
    },
    'css bundler parses escaped top-level at-keywords before import graph resolution' => static function (TestRunner $t) use ($bundle): void {
        $resolved = [];
        $code = $bundle([
            '/entry.css' => <<<'CSS'
@\63harset "UTF-8";
@\6c ayer reset, theme.blocks;
@\69mport u\72l(pkg:tokens.css) \6c ayer(theme.tokens) s\75pports(display: grid) screen;
.entry { color: red }
CSS,
            '/vendor/tokens.css' => ':root { --gap: 1rem }',
        ], '/entry.css', static function (string $specifier, string $originatingFile) use (&$resolved): string {
            $resolved[] = [$specifier, $originatingFile];

            if ($specifier !== 'pkg:tokens.css') {
                throw new RuntimeException("Unexpected escaped at-keyword import specifier {$specifier}");
            }

            return '/vendor/tokens.css';
        });

        $t->same(
            '@layer reset,theme.blocks;@supports (display:grid){@media screen{@layer theme.tokens{:root{--gap:1rem}}}}.entry{color:red}',
            $code
        );
        $t->same([['pkg:tokens.css', '/entry.css']], $resolved);

        try {
            $bundle([
                '/entry.css' => '.entry { color: red } @\69mport "tokens.css";',
                '/tokens.css' => ':root { --gap: 1rem }',
            ], '/entry.css');
        } catch (CssBundleException $exception) {
            $t->same('parser-error', $exception->kind);
            $t->same('@import rules must precede all rules aside from @charset and @layer statements', $exception->getMessage());
            $t->same('/entry.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(32, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected escaped late @import diagnostic');
    },
    'css bundler resolves escaped url delimiters in import graph like upstream' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '.close{color:#00f}.open{color:green}.entry{color:red}',
            $bundle([
                '/entry.css' => <<<'CSS'
@import url(./icons\).css);
@import url(./icons\(.css);
.entry { color: red }
CSS,
                '/icons).css' => '.close { color: blue }',
                '/icons(.css' => '.open { color: green }',
            ], '/entry.css')
        );

        $resolved = [];
        $t->same(
            '.remote{color:green}.entry{color:red}',
            $bundle([
                '/entry.css' => '@import url(pkg:icons\).css); .entry { color: red }',
                '/vendor/icons).css' => '.remote { color: green }',
            ], '/entry.css', static function (string $specifier, string $originatingFile) use (&$resolved): string {
                $resolved[] = [$specifier, $originatingFile];

                return '/vendor/' . substr($specifier, strlen('pkg:'));
            })
        );
        $t->same([['pkg:icons).css', '/entry.css']], $resolved);
    },
    'css bundler consumes crlf after hex escaped import specifiers like upstream' => static function (TestRunner $t) use ($bundle): void {
        $resolved = [];
        $t->same(
            '.card{color:green}.icon{color:#00f}.entry{color:red}',
            $bundle([
                '/entry.css' => "@import \"blocks/card\\2e\r\ncss\";\n@import url(pkg:icon\\2e\r\ncss);\n.entry { color: red }",
                '/blocks/card.css' => '.card { color: green }',
                '/vendor/icon.css' => '.icon { color: blue }',
            ], '/entry.css', static function (string $specifier, string $originatingFile) use (&$resolved): string {
                $resolved[] = [$specifier, $originatingFile];
                if (!str_starts_with($specifier, 'pkg:')) {
                    return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
                }

                return '/vendor/' . substr($specifier, strlen('pkg:'));
            })
        );
        $t->same([
            ['blocks/card.css', '/entry.css'],
            ['pkg:icon.css', '/entry.css'],
        ], $resolved);
    },
    'css bundler rejects upstream bad unquoted url import sources before resolution' => static function (TestRunner $t): void {
        $assertBadUrlImport = static function (string $css) use ($t): void {
            $reads = [];
            try {
                (new CssBundler())->bundleWithReader('/entry.css', static function (string $file) use (&$reads, $css): string {
                    $reads[] = $file;

                    return $file === '/entry.css' ? $css : '.bad { color: red }';
                });
            } catch (CssBundleException $exception) {
                $t->same('parser-error', $exception->kind);
                $t->same('Invalid @import source', $exception->getMessage());
                $t->same('/entry.css', $exception->sourceFile);
                $t->same(1, $exception->sourceLine);
                $t->same(1, $exception->sourceColumn);
                $t->same(['/entry.css'], $reads);

                return;
            }

            throw new RuntimeException('Expected bad unquoted @import url() source exception');
        };

        $assertBadUrlImport('@import url(blocks/card hero.css); .entry { color: red }');
        $assertBadUrlImport('@import url(blocks/card(hero).css); .entry { color: red }');
        $assertBadUrlImport("@import url(blocks/card\\\nhero.css); .entry { color: red }");

        $t->same(
            '.card{color:green}.entry{color:red}',
            (new CssBundler())->bundle('/entry.css', [
                '/entry.css' => '@import url(blocks/card\ hero.css); .entry { color: red }',
                '/blocks/card hero.css' => '.card { color: green }',
            ])
        );
    },
    'css bundler validates quoted url import source token boundaries like upstream' => static function (TestRunner $t) use ($bundle): void {
        $resolved = [];
        $t->same(
            '@media screen{.card{color:green}}@layer theme.blocks{.hero{color:#00f}}.entry{color:red}',
            $bundle([
                '/entry.css' => <<<'CSS'
@import url( /* generated by a loader */ "pkg:card.css" /* trailing build note */ ) screen;
@import url('blocks/hero.css') layer(theme.blocks);
.entry { color: red }
CSS,
                '/vendor/card.css' => '.card { color: green }',
                '/blocks/hero.css' => '.hero { color: blue }',
            ], '/entry.css', static function (string $specifier, string $originatingFile) use (&$resolved): string {
                $resolved[] = [$specifier, $originatingFile];

                if (str_starts_with($specifier, 'pkg:')) {
                    return '/vendor/' . substr($specifier, strlen('pkg:'));
                }

                return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
            })
        );
        $t->same([
            ['pkg:card.css', '/entry.css'],
            ['blocks/hero.css', '/entry.css'],
        ], $resolved);

        $assertInvalidQuotedUrlImport = static function (string $css) use ($t): void {
            $reads = [];
            try {
                (new CssBundler())->bundleWithReader('/entry.css', static function (string $file) use (&$reads, $css): string {
                    $reads[] = $file;

                    return $file === '/entry.css' ? $css : '.bad { color: red }';
                });
            } catch (CssBundleException $exception) {
                $t->same('parser-error', $exception->kind);
                $t->same('Invalid @import source', $exception->getMessage());
                $t->same('/entry.css', $exception->sourceFile);
                $t->same(1, $exception->sourceLine);
                $t->same(1, $exception->sourceColumn);
                $t->same(['/entry.css'], $reads);

                return;
            }

            throw new RuntimeException('Expected bad quoted @import url() source exception');
        };

        $assertInvalidQuotedUrlImport('@import url("blocks/card.css" extra); .entry { color: red }');
        $assertInvalidQuotedUrlImport('@import url("blocks/card.css" "theme.css"); .entry { color: red }');
        $assertInvalidQuotedUrlImport('@import url("blocks/card.css", screen); .entry { color: red }');
    },
    'css bundler trims upstream unquoted url import trivia before graph resolution' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@media screen{.card{color:green}}.entry{color:red}',
            $bundle([
                '/entry.css' => '@import url( /* generated by a loader */ blocks/card.css /* trailing build note */ ) screen; .entry { color: red }',
                '/blocks/card.css' => '.card { color: green }',
            ], '/entry.css')
        );

        $resolved = [];
        $t->same(
            '@supports (display:grid){@layer theme.tokens{:root{--gap:1rem}}}.entry{color:red}',
            $bundle([
                '/entry.css' => '@import url( /* package alias */ pkg:tokens.css /* versioned by resolver */ ) layer(theme.tokens) supports(display: grid); .entry { color: red }',
                '/vendor/tokens.css' => ':root { --gap: 1rem }',
            ], '/entry.css', static function (string $specifier, string $originatingFile) use (&$resolved): string {
                $resolved[] = [$specifier, $originatingFile];

                return '/vendor/' . substr($specifier, strlen('pkg:'));
            })
        );
        $t->same([['pkg:tokens.css', '/entry.css']], $resolved);

        $t->same(
            '.card{color:green}.entry{color:red}',
            $bundle([
                '/entry.css' => '@import url(blocks/card\/*hero.css); .entry { color: red }',
                '/blocks/card/*hero.css' => '.card { color: green }',
            ], '/entry.css')
        );

        $reads = [];
        try {
            (new CssBundler())->bundleWithReader('/entry.css', static function (string $file) use (&$reads): string {
                $reads[] = $file;

                return $file === '/entry.css'
                    ? '@import url(blocks/card/* stale loader split */hero.css); .entry { color: red }'
                    : '.bad { color: red }';
            });
        } catch (CssBundleException $exception) {
            $t->same('parser-error', $exception->kind);
            $t->same('Invalid @import source', $exception->getMessage());
            $t->same('/entry.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(1, $exception->sourceColumn);
            $t->same(['/entry.css'], $reads);

            return;
        }

        throw new RuntimeException('Expected internal comment inside unquoted @import url() source exception');
    },
    'css bundler rejects malformed import source tokens before resolver reads' => static function (TestRunner $t): void {
        $assertMalformedImportSource = static function (string $css) use ($t): void {
            $reads = [];
            try {
                (new CssBundler())->bundleWithReader('/entry.css', static function (string $file) use (&$reads, $css): string {
                    $reads[] = $file;

                    return $file === '/entry.css' ? $css : '.bad { color: red }';
                });
            } catch (CssBundleException $exception) {
                $t->same('parser-error', $exception->kind);
                $t->same('Invalid @import source', $exception->getMessage());
                $t->same('/entry.css', $exception->sourceFile);
                $t->same(1, $exception->sourceLine);
                $t->same(1, $exception->sourceColumn);
                $t->same(['/entry.css'], $reads);

                return;
            }

            throw new RuntimeException('Expected malformed @import source exception');
        };

        $assertMalformedImportSource('@import url(; .entry { color: red }');
        $assertMalformedImportSource('@import "blocks/card.css; .entry { color: red }');
        $assertMalformedImportSource("@import \"blocks/\ncard.css\"; .entry { color: red }");
        $assertMalformedImportSource("@import url(\"blocks/\ncard.css\"); .entry { color: red }");

        $t->same(
            '.card{color:green}.entry{color:red}',
            (new CssBundler())->bundle('/entry.css', [
                '/entry.css' => "@import \"blocks/card\\\n.css\"; .entry { color: red }",
                '/blocks/card.css' => '.card { color: green }',
            ])
        );
    },
    'css bundler rejects malformed import condition tails before resolver reads' => static function (TestRunner $t): void {
        $assertMalformedImportCondition = static function (string $css, string $message) use ($t): void {
            $reads = [];
            try {
                (new CssBundler())->bundleWithReader('/entry.css', static function (string $file) use (&$reads, $css): string {
                    $reads[] = $file;

                    return $file === '/entry.css' ? $css : '.bad { color: red }';
                });
            } catch (CssBundleException $exception) {
                $t->same('parser-error', $exception->kind);
                $t->same($message, $exception->getMessage());
                $t->same('/entry.css', $exception->sourceFile);
                $t->same(1, $exception->sourceLine);
                $t->same(1, $exception->sourceColumn);
                $t->same(['/entry.css'], $reads);

                return;
            }

            throw new RuntimeException('Expected malformed @import condition exception');
        };

        $assertMalformedImportCondition(
            '@import "tokens.css" screen and;',
            'Media query boolean operator must be followed by a condition'
        );
        $assertMalformedImportCondition(
            '@import "tokens.css" (width > 1px;',
            'Media query contains unbalanced parentheses'
        );
        $assertMalformedImportCondition(
            '@import "tokens.css" unknown(foo);',
            'Unknown media query condition function: unknown(foo)'
        );
        $assertMalformedImportCondition(
            '@import "tokens.css" layer(theme.tokens',
            'CSS contains an unbalanced () pair'
        );
        $assertMalformedImportCondition(
            '@import "tokens.css" supports(display: grid',
            'CSS contains an unbalanced () pair'
        );
    },
    'css bundler combines nested media conditions across import graph' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@media print and (color){.c{color:green}}@media print{.b{color:#ff0}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" print; .a { color: red }',
                '/b.css' => '@import "c.css" (color); .b { color: yellow }',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@media print and (color) and (orientation:landscape){.c{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" print; .a { color: red }',
                '/b.css' => '@import "c.css" (color), (orientation: landscape);',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );
    },
    'css bundler maps upstream media type boolean semantics through layered range imports' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@media print and (width>=240px){@layer theme.blocks{.wide{color:green}}}@media print{@layer theme.blocks{.print{color:#00f}}}.entry{color:red}',
            $bundle([
                '/entry.css' => '@import "print.css" layer(theme.blocks) print; .entry { color: red }',
                '/print.css' => '@import "wide.css" not screen and (width >= 240px); .print { color: blue }',
                '/wide.css' => '.wide { color: green }',
            ], '/entry.css')
        );

        $t->same(
            '@media screen{@layer theme.blocks{.screen{color:#00f}}}.entry{color:red}',
            $bundle([
                '/entry.css' => '@import "screen.css" layer(theme.blocks) screen; .entry { color: red }',
                '/screen.css' => '@import "print.css" print; .screen { color: blue }',
                '/print.css' => '.print { color: green }',
            ], '/entry.css')
        );

        $t->same(
            '@media not screen and (width>=240px){.c{color:green}}@media not screen{.b{color:#00f}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" not screen; .a { color: red }',
                '/b.css' => '@import "c.css" not screen and (width >= 240px); .b { color: blue }',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );

        $t->same(
            '.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" not all; .a { color: red }',
                '/b.css' => '@import "c.css" screen; .b { color: blue }',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );
    },
    'css bundler maps upstream media range conjunctions through layered import graph' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@media ((width>=250px) or (color)) and (orientation:landscape){@layer theme.blocks{.c{color:green}}}@media ((width>=250px) or (color)){@layer theme.blocks{.b{color:#00f}}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" layer(theme.blocks) ((min-width: 250px) or (color)); .a { color: red }',
                '/b.css' => '@import "c.css" (orientation: landscape); .b { color: blue }',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@media print and (width>=250px) and (color){@layer theme.blocks{.c{color:green}}}@media print and (width>=250px){@layer theme.blocks{.b{color:#00f}}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" layer(theme.blocks) print and (min-width: 250px); .a { color: red }',
                '/b.css' => '@import "c.css" (color); .b { color: blue }',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@media only screen{@layer theme.blocks{.c{color:green}}}.b{color:#00f}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" all; .a { color: red }',
                '/b.css' => '@import "c.css" layer(theme.blocks) only screen; .b { color: blue }',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@media only screen{@layer theme.blocks{.c{color:green}}@layer theme.blocks{.b{color:#00f}}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" layer(theme.blocks) only screen; .a { color: red }',
                '/b.css' => '@import "c.css" all; .b { color: blue }',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@media not all and (width>=250px){@layer theme.blocks{.c{color:green}}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" layer(theme.blocks) not all; .a { color: red }',
                '/b.css' => '@import "c.css" (min-width: 250px); .b { color: blue }',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@media print and (width>=250px) and (hover),screen and (width>=250px) and (hover){@layer theme.blocks{.c{color:green}}}@media print,screen{@layer theme.blocks{.b{color:#00f}}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" layer(theme.blocks) print, screen; .a { color: red }',
                '/b.css' => '@import "c.css" (min-width: 250px), (hover); .b { color: blue }',
                '/c.css' => '.c { color: green }',
            ], '/a.css')
        );
    },
    'css bundler merges repeated import conditions like upstream' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@media print,screen{.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" print; @import "b.css" screen; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '@supports (color:red) or (foo:bar){.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" supports(color: red); @import "b.css" supports(foo: bar); .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );
    },
    'css bundler keeps duplicate supports imports unconditional like upstream' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '.b{color:green}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css"; @import "b.css" supports(color: red); .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '.b{color:green}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" supports(color: red); @import "b.css"; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $t->same(
            '.b{color:green}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" supports(color: red) screen; @import "b.css"; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );
    },
    'css bundler rejects incompatible repeated media and supports imports' => static function (TestRunner $t) use ($bundle): void {
        try {
            $bundle([
                '/a.css' => '@import "b.css" print; @import "b.css" supports(color: red);',
                '/b.css' => '.b { color: green }',
            ], '/a.css');
        } catch (CssBundleException $exception) {
            $t->same('unsupported-import-condition', $exception->kind);
            $t->same('Unsupported import condition', $exception->getMessage());
            $t->same('/a.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(24, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected unsupported import condition exception');
    },
    'css bundler rejects unsupported nested negated media boolean logic' => static function (TestRunner $t) use ($bundle): void {
        try {
            $bundle([
                '/a.css' => '@import "b.css" not print; .a { color: red }',
                '/b.css' => '@import "c.css" not screen; .b { color: green }',
                '/c.css' => '.c { color: yellow }',
            ], '/a.css');
        } catch (CssBundleException $exception) {
            $t->same('unsupported-media-boolean-logic', $exception->kind);
            $t->same('Unsupported boolean logic in @import media query', $exception->getMessage());
            $t->same('/b.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(1, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected unsupported media boolean logic exception');
    },
    'css bundler preserves upstream last import graph position and cycles' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '.a{background:red}.c{background:#fff;color:#000}.b{color:red}',
            $bundle([
                '/index.css' => '@import "a.css"; @import "b.css";',
                '/a.css' => '@import "./c.css"; .a { background: red; }',
                '/b.css' => '@import "./c.css"; .b { color: red; }',
                '/c.css' => '.c { background: white; color: black; }',
            ], '/index.css')
        );

        $t->same(
            '.b{background:red}.a{background:green}',
            $bundle([
                '/index.css' => '@import "a.css"; @import "b.css"; @import "a.css";',
                '/a.css' => '.a { background: green; }',
                '/b.css' => '.b { background: red; }',
            ], '/index.css')
        );

        $t->same(
            '.c{color:green}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css"; .a { color: red }',
                '/b.css' => '@import "c.css";',
                '/c.css' => '@import "a.css"; .c { color: green }',
            ], '/a.css')
        );
    },
    'css bundler prefixes nested layer statements inside parent imports' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@layer bar,foo;@layer foo.qux,foo.baz;@layer foo.baz{div{background:#ff0}}@layer foo{@layer qux{div{background:green}}}@layer bar{div{background:red}}',
            $bundle([
                '/a.css' => <<<'CSS'
@layer bar, foo;
@import "b.css" layer(foo);
@layer bar { div { background: red; } }
CSS,
                '/b.css' => <<<'CSS'
@layer qux, baz;
@import "c.css" layer(baz);
@layer qux { div { background: green; } }
CSS,
                '/c.css' => 'div { background: yellow; }',
            ], '/a.css')
        );
    },
    'css bundler preserves non-prelude layer statement order in imported graph' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '.component{color:green}@layer late;.entry{color:red}',
            $bundle([
                '/entry.css' => '@import "component.css"; .entry { color: red }',
                '/component.css' => '.component { color: green } @layer late;',
            ], '/entry.css')
        );

        $t->same(
            '@layer theme{.component{color:green}@layer theme.late}.entry{color:red}',
            $bundle([
                '/entry.css' => '@import "component.css" layer(theme); .entry { color: red }',
                '/component.css' => '.component { color: green } @layer late;',
            ], '/entry.css')
        );
    },
    'css bundler maps anonymous layer imports and unsupported layer combinations' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@layer{.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" layer; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        $rejectedRepeatedAnonymousLayer = false;
        try {
            $bundle([
                '/a.css' => '@import "b.css" layer; @import "b.css" layer;',
                '/b.css' => '.b { color: red }',
            ], '/a.css');
        } catch (CssBundleException $exception) {
            $t->same('unsupported-layer-combination', $exception->kind);
            $t->same('/a.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(24, $exception->sourceColumn);
            $rejectedRepeatedAnonymousLayer = true;
        }

        if (!$rejectedRepeatedAnonymousLayer) {
            throw new RuntimeException('Expected repeated anonymous layer combination exception');
        }

        try {
            $bundle([
                '/a.css' => '@import "b.css" layer; .a { color: red }',
                '/b.css' => '@import "c.css" layer; .b { color: green }',
                '/c.css' => '.c { color: green }',
            ], '/a.css');
        } catch (CssBundleException $exception) {
            $t->same('unsupported-layer-combination', $exception->kind);
            $t->same('/b.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(1, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected unsupported anonymous layer combination exception');
    },
    'css bundler preserves escaped import layer names through graph composition' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@layer foo\\.bar.baz\\ qux{.c{color:#00f}}@layer foo\\.bar{.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" layer(foo\2e bar); .a { color: red }',
                '/b.css' => '@import "c.css" layer(baz\20 qux); .b { color: green }',
                '/c.css' => '.c { color: blue }',
            ], '/a.css')
        );

        $t->same(
            '@layer theme\\,tokens{.b{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" layer(theme\2c tokens); .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );
    },
    'css bundler rejects invalid import layer names before graph resolution' => static function (TestRunner $t): void {
        $assertInvalidLayerImport = static function (string $css, string $message) use ($t): void {
            $reads = [];
            try {
                (new CssBundler())->bundleWithReader('/entry.css', static function (string $file) use (&$reads, $css): string {
                    $reads[] = $file;

                    return $file === '/entry.css' ? $css : ':root { --gap: 1rem }';
                });
            } catch (CssBundleException $exception) {
                $t->same('parser-error', $exception->kind);
                $t->same($message, $exception->getMessage());
                $t->same('/entry.css', $exception->sourceFile);
                $t->same(1, $exception->sourceLine);
                $t->same(1, $exception->sourceColumn);
                $t->same(['/entry.css'], $reads);

                return;
            }

            throw new RuntimeException('Expected invalid @import layer name exception');
        };

        $validEscaped = (new CssBundler())->bundle('/entry.css', [
            '/entry.css' => '@import "tokens.css" layer(theme\20 tokens.block); .entry { color: red }',
            '/tokens.css' => ':root { --gap: 1rem }',
        ]);
        $t->same('@layer theme\ tokens.block{:root{--gap:1rem}}.entry{color:red}', $validEscaped);

        $assertInvalidLayerImport('@import "tokens.css" layer(foo, bar); .entry { color: red }', 'Invalid @import layer name: foo, bar');
        $assertInvalidLayerImport('@import "tokens.css" layer(); .entry { color: red }', 'Invalid @import layer name: ');
        $assertInvalidLayerImport('@import "tokens.css" layer(foo bar); .entry { color: red }', 'Invalid @import layer name: foo bar');
        $assertInvalidLayerImport('@import "tokens.css" layer(foo.); .entry { color: red }', 'Invalid @import layer name: foo.');
        $assertInvalidLayerImport('@import "tokens.css" layer(.foo); .entry { color: red }', 'Invalid @import layer name: .foo');
        $assertInvalidLayerImport('@import "tokens.css" layer(foo..bar); .entry { color: red }', 'Invalid @import layer name: foo..bar');
        $assertInvalidLayerImport('@import "tokens.css" layer(foo/* old */.bar); .entry { color: red }', 'Invalid @import layer name: foo/* old */.bar');
    },
    'css bundler rejects block-form invalid import layer names before graph resolution' => static function (TestRunner $t): void {
        $reads = [];
        try {
            (new CssBundler())->bundleWithReader('/entry.css', static function (string $file) use (&$reads): string {
                $reads[] = $file;

                return $file === '/entry.css'
                    ? '@import "tokens.css" layer(foo, bar) {}; .entry { color: red }'
                    : ':root { --gap: 1rem }';
            });
        } catch (CssBundleException $exception) {
            $t->same('parser-error', $exception->kind);
            $t->same('Invalid @import layer name: foo, bar', $exception->getMessage());
            $t->same('/entry.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(1, $exception->sourceColumn);
            $t->same(['/entry.css'], $reads);

            return;
        }

        throw new RuntimeException('Expected invalid block-form @import layer name exception');
    },
    'css bundler maps external import ordering diagnostics' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@import "https://fonts.example/css";.b{color:green}',
            $bundle([
                '/a.css' => '@import url("https://fonts.example/css"); @import "./b.css";',
                '/b.css' => '.b { color: green }',
            ], '/a.css')
        );

        try {
            $bundle([
                '/a.css' => <<<'CSS'
@import "./b.css";
@import url("https://fonts.example/css");
CSS,
                '/b.css' => '.b { color: green }',
            ], '/a.css');
        } catch (CssBundleException $exception) {
            $t->same('external-import-after-bundled-import', $exception->kind);
            $t->same('/a.css', $exception->sourceFile);
            $t->same(2, $exception->sourceLine);
            $t->same(1, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected external import order exception');
    },
    'css bundler preserves resolver marked external imports before bundled imports' => static function (TestRunner $t) use ($bundle): void {
        $resolver = static function (string $specifier, string $originatingFile): array {
            if ($specifier === './does_not_exist.css' || str_starts_with($specifier, 'https:')) {
                return ['external' => $specifier];
            }

            return ['file' => rtrim(dirname($originatingFile), '/') . '/' . ltrim($specifier, './')];
        };

        $t->same(
            '@import "https://fonts.googleapis.com/css2?family=Roboto&display=swap";@import "./does_not_exist.css";.b{height:calc(100vh - 64px)}',
            $bundle([
                '/a.css' => <<<'CSS'
@import url("https://fonts.googleapis.com/css2?family=Roboto&display=swap");
@import "./does_not_exist.css";
@import "./b.css";
CSS,
                '/b.css' => '.b { height: calc(100vh - 64px); }',
            ], '/a.css', $resolver)
        );
    },
    'css bundler serializes resolver marked external url quotes and backslashes like upstream strings' => static function (TestRunner $t) use ($bundle): void {
        $resolved = [];
        $resolver = static function (string $specifier, string $originatingFile) use (&$resolved): array|string {
            $resolved[] = [$specifier, $originatingFile];
            if ($specifier === 'theme-remote.css') {
                return ['external' => 'https://cdn.example/theme\\dark-editor.css'];
            }

            return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
        };

        $t->same(
            '@import "https://cdn.example/theme\\\\dark-editor.css" screen;.b{color:green}.a{color:red}',
            $bundle([
                '/a.css' => '@import "theme-remote.css" screen; @import "b.css"; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css', $resolver)
        );
        $t->same([
            ['theme-remote.css', '/a.css'],
            ['b.css', '/a.css'],
        ], $resolved);

        $resolved = [];
        $quoteResolver = static function (string $specifier, string $originatingFile) use (&$resolved): array|string {
            $resolved[] = [$specifier, $originatingFile];
            if ($specifier === 'quote-remote.css') {
                return ['external' => 'https://cdn.example/theme"dark\\editor.css'];
            }

            return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
        };

        $t->same(
            '@import "https://cdn.example/theme\"dark\\\\editor.css" screen;.b{color:green}.a{color:red}',
            $bundle([
                '/a.css' => '@import "quote-remote.css" screen; @import "b.css"; .a { color: red }',
                '/b.css' => '.b { color: green }',
            ], '/a.css', $quoteResolver)
        );
        $t->same([
            ['quote-remote.css', '/a.css'],
            ['b.css', '/a.css'],
        ], $resolved);
    },
    'css bundler preserves nested external imports outside parent wrappers like upstream' => static function (TestRunner $t) use ($bundle): void {
        $resolved = [];
        $code = $bundle([
            '/entry.css' => '@import "card.css" layer(theme.blocks) screen; .entry { color: red }',
            '/card.css' => '@import "cdn:card-reset.css" print; @import "button.css"; .card { color: green }',
            '/button.css' => '.button { color: blue }',
        ], '/entry.css', static function (string $specifier, string $originatingFile) use (&$resolved): array|string {
            $resolved[] = [$specifier, $originatingFile];

            if ($specifier === 'cdn:card-reset.css') {
                return ['external' => 'https://cdn.example/card-reset.css'];
            }

            return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
        });

        $t->same(
            '@import "https://cdn.example/card-reset.css" print;@media screen{@layer theme.blocks{.button{color:#00f}}@layer theme.blocks{.card{color:green}}}.entry{color:red}',
            $code
        );
        $t->same([
            ['card.css', '/entry.css'],
            ['cdn:card-reset.css', '/card.css'],
            ['button.css', '/card.css'],
        ], $resolved);

        try {
            $bundle([
                '/entry.css' => '@import "card.css"; .entry { color: red }',
                '/card.css' => "@import \"button.css\";\n@import \"cdn:late-reset.css\";\n.card { color: green }",
                '/button.css' => '.button { color: blue }',
            ], '/entry.css', static function (string $specifier, string $originatingFile): array|string {
                if ($specifier === 'cdn:late-reset.css') {
                    return ['external' => 'https://cdn.example/late-reset.css'];
                }

                return rtrim(dirname($originatingFile), '/') . '/' . $specifier;
            });
        } catch (CssBundleException $exception) {
            $t->same('external-import-after-bundled-import', $exception->kind);
            $t->same('An external `@import` was found after a bundled `@import`. This may result in unintended selector order.', $exception->getMessage());
            $t->same('/card.css', $exception->sourceFile);
            $t->same(2, $exception->sourceLine);
            $t->same(1, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected nested external import after bundled dependency exception');
    },
    'css bundler reports upstream resolver and layer errors with import locations' => static function (TestRunner $t) use ($bundle): void {
        try {
            $bundle([
                '/a.css' => "\n  @import \"/b.css\";\n  .a { color: red; }",
            ], '/a.css', static function (string $specifier, string $originatingFile): string {
                throw new RuntimeException("Failed to resolve `{$specifier}` from `{$originatingFile}`.");
            });
        } catch (CssBundleException $exception) {
            $t->same('resolver-error', $exception->kind);
            $t->same('Failed to resolve `/b.css` from `/a.css`.', $exception->getMessage());
            $t->same('/a.css', $exception->sourceFile);
            $t->same(2, $exception->sourceLine);
            $t->same(3, $exception->sourceColumn);
        }

        try {
            $bundle([
                '/a.css' => '@import "b.css" layer(foo); @import "b.css" layer(bar);',
                '/b.css' => '.b { color: red }',
            ], '/a.css');
        } catch (CssBundleException $exception) {
            $t->same('unsupported-layer-combination', $exception->kind);
            $t->same('/a.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(29, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected layer combination exception');
    },
    'css bundler rejects malformed resolver results with upstream location diagnostics' => static function (TestRunner $t) use ($bundle): void {
        $assertResolverShapeError = static function (callable $resolver, int $line, int $column) use ($bundle, $t): void {
            try {
                $bundle([
                    '/a.css' => "\n  @import \"b.css\";\n  .a { color: red; }",
                    '/b.css' => '.b { color: green }',
                ], '/a.css', $resolver);
            } catch (CssBundleException $exception) {
                $t->same('resolver-error', $exception->kind);
                $t->same('data did not match any variant of untagged enum ResolveResult', $exception->getMessage());
                $t->same('/a.css', $exception->sourceFile);
                $t->same($line, $exception->sourceLine);
                $t->same($column, $exception->sourceColumn);

                return;
            }

            throw new RuntimeException('Expected malformed resolver result exception');
        };

        $assertResolverShapeError(static fn (): int => 1234, 2, 3);
        $assertResolverShapeError(static fn (): array => ['file' => 1234], 2, 3);
        $assertResolverShapeError(static fn (): array => ['external' => 1234], 2, 3);
    },
    'css bundler maps upstream source provider read callbacks' => static function (TestRunner $t): void {
        $files = [
            'foo.css' => "@import 'hello/world.css'; .foo { color: red; }",
            'hello/world.css' => "@import '../bar.css'; .bar { color: green; }",
            'hello/../bar.css' => '.baz { color: blue; }',
            'root-entry.css' => "@import 'root:bar.css'; .root { color: red; }",
            'bar.css' => '.baz { color: blue; }',
        ];
        $reads = [];
        $reader = static function (string $file) use (&$reads, $files): string {
            $reads[] = $file;
            if (!array_key_exists($file, $files)) {
                throw new RuntimeException("Could not find {$file}.");
            }

            return $files[$file];
        };

        $t->same(
            '.baz{color:#00f}.bar{color:green}.foo{color:red}',
            (new CssBundler())->bundleWithReader('foo.css', $reader)
        );
        $t->same(['foo.css', 'hello/world.css', 'hello/../bar.css'], $reads);

        $resolved = [];
        $t->same(
            '.baz{color:#00f}.root{color:red}',
            (new CssBundler())->bundleWithReader(
                'root-entry.css',
                $reader,
                static function (string $specifier, string $originatingFile) use (&$resolved): string {
                    $resolved[] = [$specifier, $originatingFile];

                    return substr($specifier, strlen('root:'));
                }
            )
        );
        $t->same([['root:bar.css', 'root-entry.css']], $resolved);
    },
    'css bundler preserves upstream reader default lexical import identities' => static function (TestRunner $t): void {
        $files = [
            '/theme/entry.css' => '@import "base.css"; @import "blocks/card.css"; .entry { color: red }',
            '/theme/base.css' => '.base { color: blue }',
            '/theme/blocks/card.css' => '@import "../base.css"; .card { color: green }',
            '/theme/blocks/../base.css' => '.base-override { color: purple }',
        ];
        $reads = [];

        $code = (new CssBundler())->bundleWithReader('/theme/entry.css', static function (string $file) use (&$reads, $files): string {
            $reads[] = $file;
            if (!array_key_exists($file, $files)) {
                throw new RuntimeException("Missing reader source {$file}");
            }

            return $files[$file];
        });

        $t->same('.base{color:#00f}.base-override{color:purple}.card{color:green}.entry{color:red}', $code);
        $t->same([
            '/theme/entry.css',
            '/theme/base.css',
            '/theme/blocks/card.css',
            '/theme/blocks/../base.css',
        ], $reads);
    },
    'css bundler preserves resolver-returned reader paths like upstream' => static function (TestRunner $t): void {
        $files = [
            '/entry.css' => '@import "pkg:tokens.css"; @import "pkg:card.css"; .entry { color: red }',
            './vendor/../vendor/tokens.css' => ':root { --brand: blue }',
            './blocks/../blocks/card.css' => '@import "pkg:button.css"; .card { color: green }',
            './shared/../shared/button.css' => '.button { color: blue }',
        ];
        $reads = [];
        $resolved = [];

        $code = (new CssBundler())->bundleWithReader(
            '/entry.css',
            static function (string $file) use (&$reads, $files): string {
                $reads[] = $file;
                if (!array_key_exists($file, $files)) {
                    throw new RuntimeException("Missing reader source {$file}");
                }

                return $files[$file];
            },
            static function (string $specifier, string $originatingFile) use (&$resolved): string {
                $resolved[] = [$specifier, $originatingFile];

                return match ($specifier) {
                    'pkg:tokens.css' => './vendor/../vendor/tokens.css',
                    'pkg:card.css' => './blocks/../blocks/card.css',
                    'pkg:button.css' => './shared/../shared/button.css',
                    default => throw new RuntimeException("Unexpected specifier {$specifier}"),
                };
            }
        );

        $t->same(':root{--brand:blue}.button{color:#00f}.card{color:green}.entry{color:red}', $code);
        $t->same([
            '/entry.css',
            './vendor/../vendor/tokens.css',
            './blocks/../blocks/card.css',
            './shared/../shared/button.css',
        ], $reads);
        $t->same([
            ['pkg:tokens.css', '/entry.css'],
            ['pkg:card.css', '/entry.css'],
            ['pkg:button.css', './blocks/../blocks/card.css'],
        ], $resolved);
    },
    'css bundler preserves resolver-returned filesystem paths like upstream' => static function (TestRunner $t) use ($withTempFiles): void {
        $withTempFiles([
            'entry.css' => '@import "pkg:tokens.css"; @import "pkg:card.css"; .entry { color: red }',
            'vendor/tokens.css' => ':root { --brand: blue }',
            'blocks/card.css' => '@import "pkg:button.css"; .card { color: green }',
            'shared/button.css' => '.button { color: blue }',
        ], static function (string $root) use ($t): void {
            $resolved = [];
            $code = (new CssBundler())->bundleFile(
                $root . '/entry.css',
                static function (string $specifier, string $originatingFile) use (&$resolved, $root): string {
                    $resolved[] = [$specifier, $originatingFile];

                    return match ($specifier) {
                        'pkg:tokens.css' => $root . '/vendor/../vendor/tokens.css',
                        'pkg:card.css' => $root . '/blocks/../blocks/card.css',
                        'pkg:button.css' => $root . '/shared/../shared/button.css',
                        default => throw new RuntimeException("Unexpected specifier {$specifier}"),
                    };
                }
            );

            $t->same(':root{--brand:blue}.button{color:#00f}.card{color:green}.entry{color:red}', $code);
            $t->same([
                ['pkg:tokens.css', $root . '/entry.css'],
                ['pkg:card.css', $root . '/entry.css'],
                ['pkg:button.css', $root . '/blocks/../blocks/card.css'],
            ], $resolved);
        });
    },
    'css bundler preserves upstream source provider entry path identity' => static function (TestRunner $t) use ($withTempFiles): void {
        $readerEntry = './themes/current/../current/theme.css';
        $readerTokens = './vendor/../vendor/tokens.css';
        $readerFiles = [
            $readerEntry => '@import "pkg:tokens.css"; .entry { color: red }',
            $readerTokens => ':root { --brand: blue }',
        ];
        $reads = [];
        $resolved = [];

        $code = (new CssBundler())->bundleWithReader(
            $readerEntry,
            static function (string $file) use (&$reads, $readerFiles): string {
                $reads[] = $file;
                if (!array_key_exists($file, $readerFiles)) {
                    throw new RuntimeException("Missing reader source {$file}");
                }

                return $readerFiles[$file];
            },
            static function (string $specifier, string $originatingFile) use (&$resolved, $readerEntry, $readerTokens): string {
                $resolved[] = [$specifier, $originatingFile];
                if ($originatingFile !== $readerEntry) {
                    throw new RuntimeException("Unexpected reader origin {$originatingFile}");
                }

                return $readerTokens;
            }
        );

        $t->same(':root{--brand:blue}.entry{color:red}', $code);
        $t->same([$readerEntry, $readerTokens], $reads);
        $t->same([['pkg:tokens.css', $readerEntry]], $resolved);

        $withTempFiles([
            'theme/entry.css' => '@import "pkg:tokens.css"; .entry { color: red }',
            'vendor/tokens.css' => ':root { --brand: blue }',
        ], static function (string $root) use ($t): void {
            $entry = $root . '/theme/../theme/entry.css';
            $tokens = $root . '/vendor/../vendor/tokens.css';
            $resolved = [];

            $code = (new CssBundler())->bundleFile(
                $entry,
                static function (string $specifier, string $originatingFile) use (&$resolved, $entry, $tokens): string {
                    $resolved[] = [$specifier, $originatingFile];
                    if ($originatingFile !== $entry) {
                        throw new RuntimeException("Unexpected filesystem origin {$originatingFile}");
                    }

                    return $tokens;
                }
            );

            $t->same(':root{--brand:blue}.entry{color:red}', $code);
            $t->same([['pkg:tokens.css', $entry]], $resolved);
        });
    },
    'css bundler maps upstream source provider read diagnostics' => static function (TestRunner $t): void {
        $initialReadRejected = false;
        try {
            (new CssBundler())->bundleWithReader('foo.css', static function (string $file): string {
                throw new RuntimeException("Oh noes! Failed to read `{$file}`.");
            });
        } catch (CssBundleException $exception) {
            $t->same('resolver-error', $exception->kind);
            $t->same('Oh noes! Failed to read `foo.css`.', $exception->getMessage());
            $t->same(null, $exception->sourceFile);
            $t->same(null, $exception->sourceLine);
            $t->same(null, $exception->sourceColumn);
            $initialReadRejected = true;
        }

        if (!$initialReadRejected) {
            throw new RuntimeException('Expected entry read callback exception');
        }

        $importReadRejected = false;
        try {
            (new CssBundler())->bundleWithReader('foo.css', static function (string $file): string {
                if ($file === 'foo.css') {
                    return '@import "bar.css"';
                }

                throw new RuntimeException("Oh noes! Failed to read `{$file}`.");
            });
        } catch (CssBundleException $exception) {
            $t->same('resolver-error', $exception->kind);
            $t->same('Oh noes! Failed to read `bar.css`.', $exception->getMessage());
            $t->same('foo.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(1, $exception->sourceColumn);
            $importReadRejected = true;
        }

        if (!$importReadRejected) {
            throw new RuntimeException('Expected imported read callback exception');
        }

        $syntaxRejected = false;
        try {
            (new CssBundler())->bundleWithReader('foo.css', static fn (): string => '.foo');
        } catch (CssBundleException $exception) {
            $t->same('parser-error', $exception->kind);
            $t->same('Unexpected end of input', $exception->getMessage());
            $t->same('foo.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(5, $exception->sourceColumn);
            $syntaxRejected = true;
        }

        if (!$syntaxRejected) {
            throw new RuntimeException('Expected parser diagnostic for unterminated resolver source');
        }

        try {
            (new CssBundler())->bundleWithReader('foo.css', static fn (): int => 1234);
        } catch (CssBundleException $exception) {
            $t->same('resolver-error', $exception->kind);
            $t->same('expect String, got: Number', $exception->getMessage());
            $t->same(null, $exception->sourceFile);

            return;
        }

        throw new RuntimeException('Expected non-string read callback exception');
    },
    'css bundler shares custom media definitions across imported graph' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            '@media print{.a{color:green}}.entry{color:red}',
            $bundle([
                '/a.css' => '@import "media.css"; @import "b.css"; .entry { color: red }',
                '/media.css' => '@custom-media --foo print;',
                '/b.css' => '@media (--foo) { .a { color: green } }',
            ], '/a.css')
        );
    },
    'css bundler preserves upstream license comments across imported graph' => static function (TestRunner $t) use ($bundle): void {
        $t->same(
            "/*! Copyright 2023 Someone awesome */\n/*! Copyright 2023 Someone else */\n.b{color:green}.a{color:red}",
            $bundle([
                '/a.css' => <<<'CSS'
/*! Copyright 2023 Someone awesome */
/* Some other comment */
@import "b.css";
.a { color: red }
CSS,
                '/b.css' => <<<'CSS'
/*! Copyright 2023 Someone else */
.b { color: green }
CSS,
            ], '/a.css')
        );

        $t->same(
            "/*! Theme bundle */\n/*! Card component */\n@media screen{.card{color:green}}.theme{color:red}",
            $bundle([
                '/theme.css' => <<<'CSS'
/*! Theme bundle */
@import "card.css" screen;
.theme { color: red }
CSS,
                '/card.css' => <<<'CSS'
/*! Card component */
.card { color: green }
CSS,
            ], '/theme.css')
        );
    },
    'css bundler hoists upstream css module dependency graph before imports' => static function (TestRunner $t) use ($bundleModules, $moduleExport, $moduleLocal): void {
        $resolved = [];
        $result = $bundleModules([
            'entry.css' => <<<'CSS'
@import "root:theme.css";

.entry {
  composes: card from "root:card.css";
  color: red;
}
CSS,
            'card.css' => <<<'CSS'
.card {
  composes: token;
  background: green;
}

.token {
  color: blue;
}
CSS,
            'theme.css' => '.theme { color: yellow; }',
        ], 'entry.css', static function (string $specifier, string $originatingFile) use (&$resolved): string {
            $resolved[] = [$specifier, $originatingFile];

            return substr($specifier, strlen('root:'));
        }, [
            'hashes' => [
                'entry.css' => 'entry',
                'card.css' => 'dep',
                'theme.css' => 'theme',
            ],
        ]);

        $t->same('.dep_card{background:green}.dep_token{color:#00f}.theme_theme{color:#ff0}.entry_entry{color:red}', $result['code']);
        $t->same([
            ['root:card.css', 'entry.css'],
            ['root:theme.css', 'entry.css'],
        ], $resolved);
        $t->same([
            'entry' => $moduleExport('entry_entry', [
                $moduleLocal('dep_card'),
                $moduleLocal('dep_token'),
            ]),
        ], $result['exports']);
    },
    'css bundler maps upstream css module imported local collisions and recursive composes' => static function (TestRunner $t) use ($bundleModules, $moduleExport, $moduleLocal): void {
        $collision = $bundleModules([
            '/a.css' => <<<'CSS'
@import "b.css";
.a { color: red }
CSS,
            '/b.css' => '.a { color: green }',
        ], '/a.css', null, [
            'hashes' => [
                '/a.css' => 'entry',
                '/b.css' => 'dep',
            ],
        ]);

        $t->same('.dep_a{color:green}.entry_a{color:red}', $collision['code']);
        $t->same([
            'a' => $moduleExport('entry_a'),
        ], $collision['exports']);

        $recursive = $bundleModules([
            '/a.css' => <<<'CSS'
.a {
  composes: x from "./b.css";
  color: red;
}

.b { color: yellow }
CSS,
            '/b.css' => <<<'CSS'
.x {
  composes: y;
  background: green;
}

.y { font: Helvetica }
CSS,
        ], '/a.css', null, [
            'hashes' => [
                '/a.css' => 'entry',
                '/b.css' => 'dep',
            ],
        ]);

        $t->same('.dep_x{background:green}.dep_y{font:Helvetica}.entry_a{color:red}.entry_b{color:#ff0}', $recursive['code']);
        $t->same([
            'a' => $moduleExport('entry_a', [
                $moduleLocal('dep_x'),
                $moduleLocal('dep_y'),
            ]),
            'b' => $moduleExport('entry_b'),
        ], $recursive['exports']);
        $t->same(['dep_x', 'dep_y'], array_column($recursive['exports']['a']['composes'], 'name'));
        $t->same(['local', 'local'], array_column($recursive['exports']['a']['composes'], 'type'));

        $firstInstance = $bundleModules([
            '/entry.css' => <<<'CSS'
@import "./theme.css";
.entry {
  composes: token from "./tokens.css";
  color: red;
}
CSS,
            '/theme.css' => <<<'CSS'
@import "./tokens.css";
.theme { color: blue }
CSS,
            '/tokens.css' => '.token { color: green }',
        ], '/entry.css', null, [
            'hashes' => [
                '/entry.css' => 'entry',
                '/theme.css' => 'theme',
                '/tokens.css' => 'tok',
            ],
        ]);

        $t->same('.tok_token{color:green}.theme_theme{color:#00f}.entry_entry{color:red}', $firstInstance['code']);
        $t->same([
            'entry' => $moduleExport('entry_entry', [
                $moduleLocal('tok_token'),
            ]),
        ], $firstInstance['exports']);
        $t->same(['tok_token'], array_column($firstInstance['exports']['entry']['composes'], 'name'));
    },
    'css bundler preserves upstream repeated source-index css module composes' => static function (TestRunner $t) use ($bundleModules, $moduleExport, $moduleLocal, $moduleGlobal): void {
        $result = $bundleModules([
            '/entry.css' => <<<'CSS'
.entry {
  composes: card card from "./card.css";
  color: red;
}
CSS,
            '/card.css' => <<<'CSS'
.card {
  composes: wp-utility from global;
  composes: token from "./tokens.css";
  background: blue;
}
CSS,
            '/tokens.css' => <<<'CSS'
.token {
  color: green;
}
CSS,
        ], '/entry.css', null, [
            'hashes' => [
                '/entry.css' => 'entry',
                '/card.css' => 'dep',
                '/tokens.css' => 'tok',
            ],
        ]);

        $t->same('.tok_token{color:green}.dep_card{background:#00f}.entry_entry{color:red}', $result['code']);
        $t->same([
            'entry' => $moduleExport('entry_entry', [
                $moduleLocal('dep_card'),
                $moduleGlobal('wp-utility'),
                $moduleLocal('tok_token'),
                $moduleLocal('dep_card'),
                $moduleGlobal('wp-utility'),
                $moduleLocal('tok_token'),
            ]),
        ], $result['exports']);
    },
    'css bundler keeps css module dependency imports unconditional like upstream' => static function (TestRunner $t) use ($bundleModules, $moduleExport, $moduleLocal): void {
        $result = $bundleModules([
            '/entry.css' => <<<'CSS'
@import "./tokens.css" supports(color: red);

.entry {
  composes: token from "./tokens.css";
  color: red;
}
CSS,
            '/tokens.css' => <<<'CSS'
.token {
  color: green;
}
CSS,
        ], '/entry.css', null, [
            'hashes' => [
                '/entry.css' => 'entry',
                '/tokens.css' => 'tok',
            ],
        ]);

        $t->same('.tok_token{color:green}.entry_entry{color:red}', $result['code']);
        $t->same([
            'entry' => $moduleExport('entry_entry', [
                $moduleLocal('tok_token'),
            ]),
        ], $result['exports']);
    },
    'css bundler resolves upstream css module dashed ident dependency graph' => static function (TestRunner $t) use ($bundleModules, $moduleExport, $moduleDashed): void {
        $result = $bundleModules([
            '/entry.css' => <<<'CSS'
@import "./theme.css";

.card {
  --inline-size: 10px;
  background: var(--bg from "./tokens.css", var(--fallback from "./tokens.css"));
  color: rgb(255 255 255 / var(--opacity from "./tokens.css"));
  margin: env(--inline-size, var(--gap from "./env.css"));
}
CSS,
            '/tokens.css' => <<<'CSS'
.tokens {
  --bg: red;
  --fallback: yellow;
  --opacity: .5;
}
CSS,
            '/env.css' => <<<'CSS'
.env {
  --gap: 20px;
}
CSS,
            '/theme.css' => '.theme { color: blue }',
        ], '/entry.css', null, [
            'hashes' => [
                '/entry.css' => 'entry',
                '/tokens.css' => 'tok',
                '/env.css' => 'env',
                '/theme.css' => 'theme',
            ],
            'dashedIdents' => true,
        ]);

        $t->same(
            '.tok_tokens{--tok_bg:red;--tok_fallback:yellow;--tok_opacity:.5}.env_env{--env_gap:20px}.theme_theme{color:#00f}.entry_card{--entry_inline-size:10px;background:var(--tok_bg,var(--tok_fallback));color:rgb(255 255 255/var(--tok_opacity));margin:env(--entry_inline-size,var(--env_gap))}',
            $result['code']
        );
        $t->same([
            'card' => $moduleExport('entry_card'),
            '--inline-size' => $moduleDashed('--entry_inline-size', true),
        ], $result['exports']);
    },
    'css bundler maps upstream css module content-hash imports' => static function (TestRunner $t) use ($bundleModules, $moduleExport): void {
        $result = $bundleModules([
            '/a.css' => "\n          @import \"b.css\";\n          .a { color: red }\n        ",
            '/b.css' => "\n          .a { color: green }\n        ",
        ], '/a.css', null, [
            'pattern' => '[content-hash]-[local]',
        ]);

        $t->same('.do5n2W-a{color:green}.pP97eq-a{color:red}', $result['code']);
        $t->same([
            'a' => $moduleExport('pP97eq-a'),
        ], $result['exports']);
    },
    'css bundler maps upstream css module project-root hashes across import graph' => static function (TestRunner $t) use ($bundleModules, $moduleExport): void {
        $expectedCode = '.dyGcAa_b{background:#ff0}.CK9avG_a{background:#fff}';
        $expectedExports = [
            'a' => $moduleExport('CK9avG_a'),
        ];

        $rootA = $bundleModules([
            '/foo/bar/a.css' => "\n        @import \"b.css\";\n        .a { background: white; }\n      ",
            '/foo/bar/b.css' => "\n        .b { background: yellow; }\n      ",
        ], '/foo/bar/a.css', null, [
            'projectRoot' => '/foo/bar',
        ]);

        $rootB = $bundleModules([
            '/x/y/z/a.css' => "\n      @import \"b.css\";\n      .a { background: white; }\n    ",
            '/x/y/z/b.css' => "\n      .b { background: yellow; }\n    ",
        ], '/x/y/z/a.css', null, [
            'projectRoot' => '/x/y/z',
        ]);

        $t->same($expectedCode, $rootA['code']);
        $t->same($expectedExports, $rootA['exports']);
        $t->same($expectedCode, $rootB['code']);
        $t->same($expectedExports, $rootB['exports']);
    },
    'css bundler passes upstream css module container option through import graph' => static function (TestRunner $t) use ($bundleModules, $moduleExport): void {
        $default = $bundleModules([
            '/entry.css' => <<<'CSS'
@container layout (width >= 0) {
  .entry {
    color: red;
  }
}
CSS,
        ], '/entry.css', null, [
            'hashes' => [
                '/entry.css' => 'entry',
            ],
        ]);

        $t->same('@container entry_layout (width>=0){.entry_entry{color:red}}', $default['code']);
        $t->same([
            'layout' => $moduleExport('entry_layout'),
            'entry' => $moduleExport('entry_entry'),
        ], $default['exports']);

        $disabled = $bundleModules([
            '/entry.css' => '@import "./component.css"; .entry { color: red }',
            '/component.css' => <<<'CSS'
@container layout (width >= 0) {
  .card {
    color: green;
  }
}
CSS,
        ], '/entry.css', null, [
            'hashes' => [
                '/entry.css' => 'entry',
                '/component.css' => 'component',
            ],
            'container' => false,
        ]);

        $t->same('@container layout (width>=0){.component_card{color:green}}.entry_entry{color:red}', $disabled['code']);
        $t->same([
            'entry' => $moduleExport('entry_entry'),
        ], $disabled['exports']);
    },
    'css bundler passes upstream css module grid option through import graph' => static function (TestRunner $t) use ($bundleModules, $moduleExport): void {
        $default = $bundleModules([
            '/entry.css' => <<<'CSS'
.entry {
  grid-template-areas: "media content";
}

.media {
  grid-area: media;
}
CSS,
        ], '/entry.css', null, [
            'hashes' => [
                '/entry.css' => 'entry',
            ],
        ]);

        $t->same('.entry_entry{grid-template-areas:"entry_media entry_content"}.entry_media{grid-area:entry_media}', $default['code']);
        $t->same([
            'entry' => $moduleExport('entry_entry'),
            'media' => $moduleExport('entry_media'),
            'content' => $moduleExport('entry_content'),
        ], $default['exports']);

        $disabled = $bundleModules([
            '/entry.css' => '@import "./component.css"; .entry { color: red }',
            '/component.css' => <<<'CSS'
.grid {
  grid-template-areas: "media content";
}

.media {
  grid-area: media;
}
CSS,
        ], '/entry.css', null, [
            'hashes' => [
                '/entry.css' => 'entry',
                '/component.css' => 'component',
            ],
            'grid' => false,
        ]);

        $t->same('.component_grid{grid-template-areas:"media content"}.component_media{grid-area:media}.entry_entry{color:red}', $disabled['code']);
        $t->same([
            'entry' => $moduleExport('entry_entry'),
        ], $disabled['exports']);
    },
    'css bundler maps upstream file-backed css modules import graph' => static function (TestRunner $t) use ($withTempFiles, $moduleExport, $moduleLocal): void {
        $withTempFiles([
            'theme/card.module.css' => <<<'CSS'
@import "../base.css" layer(theme.base);

.card {
  composes: token from "pkg:tokens.module.css";
  color: red;
}
CSS,
            'vendor/tokens.module.css' => <<<'CSS'
.token {
  color: blue;
}
CSS,
            'base.css' => <<<'CSS'
.base {
  color: yellow;
}
CSS,
        ], static function (string $root) use ($t, $moduleExport, $moduleLocal): void {
            $entry = $root . '/theme/card.module.css';
            $tokens = $root . '/vendor/tokens.module.css';
            $base = $root . '/base.css';
            $resolved = [];

            $result = (new CssBundler())->bundleCssModulesFile(
                $entry,
                static function (string $specifier, string $originatingFile) use ($root, &$resolved): string {
                    $resolved[] = [$specifier, $originatingFile];
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

            $t->same('.tok_token{color:#00f}@layer theme.base{.base_base{color:#ff0}}.card_card{color:red}', $result['code']);
            $t->same([
                'card' => $moduleExport('card_card', [
                    $moduleLocal('tok_token'),
                ]),
            ], $result['exports']);
            $t->same([
                ['pkg:tokens.module.css', $entry],
                ['../base.css', $entry],
            ], $resolved);
        });
    },
    'css bundler omits unresolved upstream css module dependency exports' => static function (TestRunner $t) use ($bundleModules, $moduleExport): void {
        $result = $bundleModules([
            '/entry.css' => <<<'CSS'
.card {
  composes: missing from "./tokens.css";
  background: red;
}
CSS,
            '/tokens.css' => <<<'CSS'
.token {
  color: green;
}
CSS,
        ], '/entry.css', null, [
            'hashes' => [
                '/entry.css' => 'entry',
                '/tokens.css' => 'tokens',
            ],
        ]);

        $t->same('.tokens_token{color:green}.entry_card{background:red}', $result['code']);
        $t->same([
            'card' => $moduleExport('entry_card'),
        ], $result['exports']);
    },
    'css bundler maps upstream css module dependency read diagnostics to composes locations' => static function (TestRunner $t) use ($bundleModules): void {
        try {
            $bundleModules([
                '/entry.css' => <<<'CSS'
.card {
  color: red;
}

.cardVariant {
  composes: token from "./missing.css";
  background: blue;
}
CSS,
            ], '/entry.css');
        } catch (CssBundleException $exception) {
            $t->same('resolver-error', $exception->kind);
            $t->same('Could not read `/missing.css`.', $exception->getMessage());
            $t->same('/entry.css', $exception->sourceFile);
            $t->same(6, $exception->sourceLine);
            $t->same(13, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected missing CSS Modules dependency read diagnostic');
    },
    'css bundler maps upstream css module dependency resolver diagnostics to style locations' => static function (TestRunner $t) use ($bundleModules): void {
        $resolverRejected = false;
        try {
            $bundleModules([
                '/entry.css' => <<<'CSS'
.intro { color: red; }
@media screen {
  .card {
    composes: token from "pkg:tokens.css";
    color: blue;
  }
}
CSS,
            ], '/entry.css', static function (string $specifier, string $originatingFile): string {
                throw new RuntimeException("Cannot resolve {$specifier} from {$originatingFile}");
            });
        } catch (CssBundleException $exception) {
            $t->same('resolver-error', $exception->kind);
            $t->same('Cannot resolve pkg:tokens.css from /entry.css', $exception->getMessage());
            $t->same('/entry.css', $exception->sourceFile);
            $t->same(3, $exception->sourceLine);
            $t->same(3, $exception->sourceColumn);
            $resolverRejected = true;
        }

        if (!$resolverRejected) {
            throw new RuntimeException('Expected CSS Modules resolver diagnostic');
        }

        try {
            $bundleModules([
                '/entry.css' => <<<'CSS'
.intro { color: red; }

.card {
  composes: remote from "https://cdn.example/remote.css";
  color: blue;
}
CSS,
            ], '/entry.css');
        } catch (CssBundleException $exception) {
            $t->same('referenced-external-module-with-css-module-from', $exception->kind);
            $t->same('Referenced external module with CSS module "from" clause', $exception->getMessage());
            $t->same('/entry.css', $exception->sourceFile);
            $t->same(3, $exception->sourceLine);
            $t->same(1, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected external CSS Modules dependency diagnostic');
    },
    'css bundler rejects external css module from references like upstream' => static function (TestRunner $t) use ($bundleModules): void {
        try {
            $bundleModules([
                '/entry.css' => '.entry { composes: remote from "https://cdn.example/remote.css"; color: red }',
            ], '/entry.css');
        } catch (CssBundleException $exception) {
            $t->same('referenced-external-module-with-css-module-from', $exception->kind);
            $t->same('Referenced external module with CSS module "from" clause', $exception->getMessage());
            $t->same('/entry.css', $exception->sourceFile);
            $t->same(1, $exception->sourceLine);
            $t->same(1, $exception->sourceColumn);

            return;
        }

        throw new RuntimeException('Expected external CSS module from exception');
    },
];
