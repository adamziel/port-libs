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
    'rejects upstream namespace exported using declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('namespace ns { export using x: any = y }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('namespace ns { export await using x: any = y }'));
    },
    'lowers upstream namespace scoped using declarations through explicit resource helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptNamespaceLowerer())->lower('namespace ns { export let a = b; using c: Disposable = d; export let e = c }');

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains('var ns;', $lowered);
        $t->contains("((ns) => {\n  var _stack = [];\n  try {\n    ns.a = b;\n    const c = __using(_stack, d);\n    ns.e = c;", $lowered);
        $t->contains("__callDispose(_stack, _error, _hasError);\n  }\n})(ns || (ns = {}));", $lowered);
        $t->true(strpos($lowered, 'var __using') < strpos($lowered, 'var ns;'));
        $t->true(strpos($lowered, 'ns.a = b;') < strpos($lowered, 'const c = __using(_stack, d);'));
        $t->true(strpos($lowered, 'const c = __using(_stack, d);') < strpos($lowered, 'ns.e = c;'));
        $t->throws(InvalidArgumentException::class, static fn (): string => (new TypeScriptNamespaceLowerer())->lower('namespace ns { await using c: Disposable = d }'));
    },
    'renames upstream namespace using helper symbols when source names collide' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptNamespaceLowerer())->lower(<<<'TS'
const __knownSymbol = symbols.known;
const __typeError = errors.type;
const __using = disposables.using;
const __callDispose = disposables.callDispose;
namespace ns {
  using c: Disposable = d;
  export const e = c;
}
TS);

        $t->contains('var __knownSymbol2 = (name, symbol) =>', $lowered);
        $t->contains('var __typeError2 = (msg) => {', $lowered);
        $t->contains('var __using2 = (stack, value, async) => {', $lowered);
        $t->contains('var __callDispose2 = (stack, error, hasError) => {', $lowered);
        $t->contains('const c = __using2(_stack, d);', $lowered);
        $t->contains('__callDispose2(_stack, _error, _hasError);', $lowered);
        $t->true(!str_contains($lowered, 'var __using = (stack, value, async) => {'));
        $t->true(!str_contains($lowered, '__callDispose(_stack, _error, _hasError);'));
    },
    'lowers upstream namespace function scoped using declarations through explicit resource helpers' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();
        $lowered = $lowerer->lower(<<<'TS'
namespace ns {
  export let settings = s;
  function register() {
    using preview: Disposable = acquire(settings.viewScript);
    done(preview, settings);
  }
  export async function registerAsync(queue) {
    await using asset: AsyncDisposable = await queue.open(settings.editorScript);
    done(asset, settings);
  }
}
TS);

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains("function register(){\n    var _stack = [];", $lowered);
        $t->contains('const preview = __using(_stack, acquire(ns.settings.viewScript));', $lowered);
        $t->contains('done(preview, ns.settings);', $lowered);
        $t->contains('__callDispose(_stack, _error, _hasError);', $lowered);
        $t->contains("async function registerAsync(queue){\n    var _stack = [];", $lowered);
        $t->contains('const asset = __using(_stack, await queue.open(ns.settings.editorScript), true);', $lowered);
        $t->contains('var _promise = __callDispose(_stack, _error, _hasError);', $lowered);
        $t->contains('_promise && await _promise;', $lowered);
        $t->contains('ns.registerAsync = registerAsync;', $lowered);
        $t->true(strpos($lowered, 'const preview = __using') < strpos($lowered, 'done(preview, ns.settings);'));
        $t->true(strpos($lowered, 'const asset = __using') < strpos($lowered, 'done(asset, ns.settings);'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'await using asset'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('namespace ns { function f() { await using x: AsyncDisposable = y } }'));
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
    'maps upstream namespace value merge declaration rules' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same(<<<'JS'
function foo() {}
((foo) => {
  0;
})(foo || (foo = {}));
function foo() {}
JS . "\n", $lowerer->lower('function foo() {} namespace foo { 0 } function foo() {}'));

        $t->same(<<<'JS'
class foo {}
((foo) => {
  0;
})(foo || (foo = {}));
JS . "\n", $lowerer->lower('class foo {} namespace foo { 0 }'));

        $t->same(<<<'JS'
var foo = /* @__PURE__ */ ((foo) => {
  foo[foo["a"] = 0] = "a";
  return foo;
})(foo || {});
((foo) => {
  0;
})(foo || (foo = {}));
JS . "\n", $lowerer->lower('enum foo { a } namespace foo { 0 }'));

        $t->same(<<<'JS'
((foo) => {
  0;
})(foo || (foo = {}));
var foo = /* @__PURE__ */ ((foo) => {
  foo[foo["a"] = 0] = "a";
  return foo;
})(foo || {});
JS . "\n", $lowerer->lower('namespace foo { 0 } enum foo { a }'));

        $t->same(<<<'JS'
var foo;
((foo) => {
  0;
})(foo || (foo = {}));
((foo) => {
  1;
})(foo || (foo = {}));
JS . "\n", $lowerer->lower('namespace foo { 0 } namespace foo { 1 }'));

        $t->same("var foo;\n", $lowerer->lower('var foo; namespace foo { export type bar = number }'));

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('var foo; namespace foo { 0 }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('namespace foo { 0 } let foo'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('namespace foo { 0 } function foo() {}'));
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
    'lowers wordpress function namespace merge settings without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-function-namespace.ts');
        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->contains('function registerBlock()', $lowered);
        $t->contains('registerBlock.settings = {name:metadata.name, viewScript:"file:./view.js"};', $lowered);
        $t->contains('wp.blocks.registerBlockType(registerBlock.settings.name, registerBlock.settings);', $lowered);
        $t->contains('registerBlock();', $lowered);
        $t->true(!str_contains($lowered, 'var registerBlock;'));
    },
    'lowers wordpress namespace scoped disposable preview asset without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-namespace-using-preview.ts');
        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains('CardBlockRuntime.settings = {name:metadata.name, viewScript:metadata.viewScript,};', $lowered);
        $t->contains('const previewAsset = __using(_stack, acquirePreviewAsset(CardBlockRuntime.settings.viewScript));', $lowered);
        $t->contains('CardBlockRuntime.previewUrl = previewAsset.url;', $lowered);
        $t->contains('wp.blocks.registerBlockType(CardBlockRuntime.settings.name, {...CardBlockRuntime.settings, viewScript:CardBlockRuntime.previewUrl,});', $lowered);
        $t->true(strpos($lowered, 'CardBlockRuntime.settings =') < strpos($lowered, 'const previewAsset = __using'));
        $t->true(strpos($lowered, 'const previewAsset = __using') < strpos($lowered, 'CardBlockRuntime.previewUrl = previewAsset.url;'));
        $t->true(strpos($lowered, 'CardBlockRuntime.previewUrl = previewAsset.url;') < strpos($lowered, '__callDispose(_stack'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress namespace disposable preview with colliding helper names without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-namespace-using-helper-collision.ts');
        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->contains('var __using2 = (stack, value, async) => {', $lowered);
        $t->contains('var __callDispose2 = (stack, error, hasError) => {', $lowered);
        $t->contains('const __using = wp.disposables.using;', $lowered);
        $t->contains('const __callDispose = wp.disposables.callDispose;', $lowered);
        $t->contains('const previewAsset = __using2(_stack, acquirePreviewAsset(CardBlockRuntime.settings.viewScript));', $lowered);
        $t->contains('__callDispose2(_stack, _error, _hasError);', $lowered);
        $t->contains('wp.blocks.registerBlockType(CardBlockRuntime.settings.name, {...CardBlockRuntime.settings, viewScript:CardBlockRuntime.previewUrl,});', $lowered);
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress namespace async disposable preview without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-namespace-await-using-preview.ts');
        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->contains('CardBlockRuntime.settings = {name:metadata.name, viewScript:metadata.viewScript, editorScript:metadata.editorScript,};', $lowered);
        $t->contains("async function registerPreview(queue){\n    var _stack = [];", $lowered);
        $t->contains('const previewAsset = __using(_stack, await queue.open(CardBlockRuntime.settings.viewScript), true);', $lowered);
        $t->contains('wp.blocks.registerBlockType(CardBlockRuntime.settings.name, {...CardBlockRuntime.settings, viewScript:previewAsset.url,});', $lowered);
        $t->contains('var _promise = __callDispose(_stack, _error, _hasError);', $lowered);
        $t->contains('_promise && await _promise;', $lowered);
        $t->contains('CardBlockRuntime.registerPreview = registerPreview;', $lowered);
        $t->true(strpos($lowered, 'CardBlockRuntime.settings =') < strpos($lowered, 'const previewAsset = __using'));
        $t->true(strpos($lowered, 'const previewAsset = __using') < strpos($lowered, 'wp.blocks.registerBlockType'));
        $t->true(strpos($lowered, 'wp.blocks.registerBlockType') < strpos($lowered, '_promise && await _promise;'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'await using previewAsset'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
];
