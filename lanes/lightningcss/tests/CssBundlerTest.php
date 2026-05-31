<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssBundleException;
use PortLibs\LightningCSS\CssBundler;

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
            '@media print and (color),print and (orientation:landscape){.c{color:green}}.a{color:red}',
            $bundle([
                '/a.css' => '@import "b.css" print; .a { color: red }',
                '/b.css' => '@import "c.css" (color), (orientation: landscape);',
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
            'bar.css' => '.baz { color: blue; }',
            'root-entry.css' => "@import 'root:bar.css'; .root { color: red; }",
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
        $t->same(['foo.css', 'hello/world.css', 'bar.css'], $reads);

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
                    return '@import "bar.css";';
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
