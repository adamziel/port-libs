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
    'lowers upstream nested namespace function and enum exports' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same(<<<'JS'
var A;
((A) => {
  let B;
  ((B) => {
    function fn(){}
    B.fn = fn;
  })(B = A.B || (A.B = {}));
  let C;
  ((C) => {
    let Mode;
    ((Mode) => {
      Mode[Mode["Card"] = 0] = "Card";
      Mode[Mode["Grid"] = 3] = "Grid";
    })(Mode = C.Mode || (C.Mode = {}));
    Mode.Card;
  })(C || (C = {}));
})(A || (A = {}));
JS . "\n", $lowerer->lower('namespace A { export namespace B { export function fn() {} } namespace C { export enum Mode { Card, Grid = 3 } Mode.Card } }'));
    },
    'lowers upstream dot qualified namespace declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();
        $expected = <<<'JS'
var foo;
((foo) => {
  let bar;
  ((bar) => {
    foo(bar);
  })(bar = foo.bar || (foo.bar = {}));
})(foo || (foo = {}));
JS;

        $t->same($expected . "\n", $lowerer->lower('namespace foo.bar { foo(bar) }'));
        $t->same($expected . "\n", $lowerer->lower('module foo.bar { foo(bar) }'));
        $t->same($expected . "\n", $lowerer->lower('module foo { export namespace bar { foo(bar) } }'));
        $t->same($expected . "\n", $lowerer->lower('namespace foo { export module bar { foo(bar) } }'));
    },
    'rewrites simple upstream declared namespace variable exports' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same(<<<'JS'
var ns;
((ns) => {
  console.log(ns.L1);
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { export declare const L1; console.log(L1) }'));
    },
    'rewrites upstream declared namespace binding pattern exports' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same(<<<'JS'
var ns;
((ns) => {
  console.log(ns.L2, ns.L3, x, y);
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { export declare let [[L2 = x, { [y]: L3 }]]; console.log(L2, L3, x, y) }'));
    },
    'lowers upstream namespace destructuring exports' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same(<<<'JS'
var A;
((A) => {
  [A.a, [, A.b = c,...A.d], {[x]:[[A.y]] = z,...A.o}] = ref;
})(A || (A = {}));
JS . "\n", $lowerer->lower('namespace A { export var [a,[,b=c,...d],{[x]:[[y]]=z,...o}] = ref }'));

        $t->same(<<<'JS'
var A;
((A) => {
  [A.a, [, A.b = c,...A.d], {[x]:[[A.y]] = z,...A.o}] = ref;
  console.log(A.a, A.b, A.d, A.y, A.o, x, c, z);
})(A || (A = {}));
JS . "\n", $lowerer->lower('namespace A { export var [a,[,b=c,...d],{[x]:[[y]]=z,...o}] = ref; console.log(a,b,d,y,o,x,c,z) }'));
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
    'lowers wordpress nested namespace enum settings without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-nested-namespace-enum.ts');
        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->contains('let Supports;', $lowered);
        $t->contains('})(Supports = CardBlockRuntime.Supports || (CardBlockRuntime.Supports = {}));', $lowered);
        $t->contains('let DisplayMode;', $lowered);
        $t->contains('DisplayMode[DisplayMode["Card"] = 0] = "Card";', $lowered);
        $t->contains('DisplayMode[DisplayMode["Grid"] = 3] = "Grid";', $lowered);
        $t->contains('DisplayMode[DisplayMode["List"] = 4] = "List";', $lowered);
        $t->contains('Supports.settings = {viewMode:DisplayMode.Card, layout:DisplayMode.Grid, fallback:DisplayMode.List,};', $lowered);
        $t->contains('CardBlockRuntime.blocks.registerBlockType(metadata.name, Supports.settings);', $lowered);
    },
    'lowers wordpress dot namespace block runtime without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-dot-namespace.ts');
        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->contains('var PortLibs;', $lowered);
        $t->contains('let CardBlock;', $lowered);
        $t->contains('})(CardBlock = PortLibs.CardBlock || (PortLibs.CardBlock = {}));', $lowered);
        $t->contains('CardBlock.blocks = wp.blocks;', $lowered);
        $t->contains('CardBlock.settings = {name:metadata.name, viewScript:"file:./view.js"};', $lowered);
        $t->contains('CardBlock.blocks.registerBlockType(CardBlock.settings.name, CardBlock.settings);', $lowered);
    },
    'lowers wordpress destructured namespace settings without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-destructured-settings.ts');
        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->contains('[{name:CardBlockRuntime.blockName}, CardBlockRuntime.settings, [CardBlockRuntime.viewMode],] = blockRecord;', $lowered);
        $t->contains('CardBlockRuntime.blocks.registerBlockType(CardBlockRuntime.blockName, {...CardBlockRuntime.settings, viewMode:CardBlockRuntime.viewMode});', $lowered);
    },
];
