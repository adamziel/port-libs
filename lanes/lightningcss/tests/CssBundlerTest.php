<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssBundleException;
use PortLibs\LightningCSS\CssBundler;

$bundle = static fn (array $files, string $entry, ?callable $resolver = null): string => (new CssBundler())->bundle($entry, $files, $resolver);

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
];
