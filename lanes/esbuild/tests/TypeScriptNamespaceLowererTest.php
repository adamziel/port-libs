<?php

declare(strict_types=1);

use PortLibs\Esbuild\TypeScriptNamespaceLowerer;

return [
    'lowers upstream namespace import equals emission cases' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same('', $lowerer->lower('namespace ns { import foo = bar }'));
        $t->same('', $lowerer->lower('namespace ns { import foo = bar; type x = foo.x }'));
        $t->same(<<<'JS'
var ns;
((ns) => {
  const foo = bar.x;
  foo;
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { import foo = bar.x; foo }'));
        $t->same(<<<'JS'
var ns;
((ns) => {
  ns.foo = bar;
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { export import foo = bar }'));
        $t->same(<<<'JS'
var ns;
((ns) => {
  ns.foo = bar.x;
  ns.foo;
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { export import foo = bar.x; foo }'));

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower("namespace ns { import {foo} from 'bar' }"));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower("namespace ns { export import foo from 'bar' }"));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('namespace ns { { import foo = bar } }'));
    },
    'lowers upstream namespace exported variable declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same(<<<'JS'
var x;
((x) => {
  x.y = 1;
})(x || (x = {}));
JS . "\n", $lowerer->lower('namespace x { export var y = 1 }'));

        $t->same(<<<'JS'
var x;
((x) => {
  x.await = 1;
  x.y = x.await;
})(x || (x = {}));
JS . "\n", $lowerer->lower('namespace x { export let await = 1, y = await }'));

        $t->same(<<<'JS'
var ns;
((ns) => {
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { export let y }'));
    },
    'rewrites upstream namespace exported value references' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same(<<<'JS'
var ns;
((ns) => {
  ns.foo = 1;
  ns.foo += ns.foo;
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { export var foo = 1; foo += foo }'));

        $t->same(<<<'JS'
var a;
((_a) => {
  _a.a = 123;
  log(_a.a);
})(a || (a = {}));
JS . "\n", $lowerer->lower('namespace a { export var a = 123; log(a) }'));
    },
    'lowers upstream namespace exported function and class declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same(<<<'JS'
var f;
((_f) => {
  function f(){}
  _f.f = f;
  log(f);
})(f || (f = {}));
JS . "\n", $lowerer->lower('namespace f { export function f() {} log(f) }'));

        $t->same(<<<'JS'
var d;
((_d) => {
  class d{}
  _d.d = d;
  log(d);
})(d || (d = {}));
JS . "\n", $lowerer->lower('namespace d { export class d {} log(d) }'));
    },
    'lowers wordpress namespace import equals aliases without node' => static function (TestRunner $t): void {
        $source = <<<'TS'
namespace CardBlockRuntime {
  export import blocks = wp.blocks;
  blocks.registerBlockType(metadata.name, metadata);
}
TS;

        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->same(<<<'JS'
var CardBlockRuntime;
((CardBlockRuntime) => {
  CardBlockRuntime.blocks = wp.blocks;
  CardBlockRuntime.blocks.registerBlockType(metadata.name, metadata);
})(CardBlockRuntime || (CardBlockRuntime = {}));
JS . "\n", $lowered);
    },
    'lowers wordpress namespace exported block settings without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-namespace-export.ts');
        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->contains('CardBlockRuntime.settings = {name:metadata.name, viewScript:"file:./view.js"};', $lowered);
        $t->contains('CardBlockRuntime.viewMode = "card";', $lowered);
        $t->contains('CardBlockRuntime.blocks.registerBlockType(CardBlockRuntime.settings.name, {viewMode:CardBlockRuntime.viewMode});', $lowered);
    },
    'lowers wordpress namespace exported registration function without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-namespace-runtime.ts');
        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->contains('CardBlockRuntime.blocks = wp.blocks;', $lowered);
        $t->contains('class PreviewController{}', $lowered);
        $t->contains('CardBlockRuntime.PreviewController = PreviewController;', $lowered);
        $t->contains('function register(){CardBlockRuntime.blocks.registerBlockType(CardBlockRuntime.settings.name, CardBlockRuntime.settings);}', $lowered);
        $t->contains('CardBlockRuntime.register = register;', $lowered);
        $t->contains('register();', $lowered);
    },
];
