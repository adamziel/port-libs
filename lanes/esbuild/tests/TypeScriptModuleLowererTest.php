<?php

declare(strict_types=1);

use PortLibs\Esbuild\TypeScriptModuleLowerer;

return [
    'lowers upstream typescript export equals assignments' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same("module.exports = [];\n", $lowerer->lower('export = []'));
        $t->same("with ({}) ;\nmodule.exports = [];\n", $lowerer->lower('export = []; with ({}) ;'));
    },
    'lowers upstream top level import equals declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same("const x = require(\"y\");\n", $lowerer->lower("import x = require('y')"));
        $t->same("const x = require(\"foo\");\nx();\n", $lowerer->lower("import x = require('foo'); x()"));
        $t->same("const x = require;\nx();\n", $lowerer->lower("import x = require\nx()"));
        $t->same("const x = foo.bar;\nx();\n", $lowerer->lower("import x = foo.bar\nx()"));
    },
    'lowers upstream exported import equals declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same("export const x = require(\"foo\");\nx();\n", $lowerer->lower("export import x = require('foo'); x()"));
        $t->same("export const x = foo.bar;\nx();\n", $lowerer->lower("export import x = foo.bar\nx()"));
    },
    'rejects malformed upstream top level import equals declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('import x = foo()'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('import x = foo<T>.bar'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower("export import {foo} from 'bar'"));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower("export import foo from 'bar'"));
    },
    'lowers upstream typescript runtime enum declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var Foo = /* @__PURE__ */ ((Foo) => {
  Foo[Foo["A"] = 0] = "A";
  Foo[Foo["B"] = 1] = "B";
  return Foo;
})(Foo || {});
JS . "\n", $lowerer->lower('enum Foo { A, B }'));

        $t->same(<<<'JS'
export var Foo = /* @__PURE__ */ ((Foo) => {
  Foo[Foo["A"] = 0] = "A";
  Foo[Foo["B"] = 1] = "B";
  return Foo;
})(Foo || {});
JS . "\n", $lowerer->lower('export enum Foo { A; B }'));

        $t->same(<<<'JS'
var Foo = /* @__PURE__ */ ((Foo) => {
  Foo[Foo["A"] = 0] = "A";
  Foo[Foo["B"] = 1] = "B";
  Foo[Foo["C"] = 3.3] = "C";
  Foo[Foo["D"] = 4.3] = "D";
  Foo[Foo["E"] = 5.3] = "E";
  return Foo;
})(Foo || {});
JS . "\n", $lowerer->lower('enum Foo { A, B, C = 3.3, D, E }'));

        $t->same(<<<'JS'
var Foo = /* @__PURE__ */ ((Foo) => {
  Foo["A"] = "x";
  Foo[Foo["B"] = void 0] = "B";
  return Foo;
})(Foo || {});
JS . "\n", $lowerer->lower("enum Foo { A = 'x', B }"));
    },
    'folds upstream enum member constants and split enum blocks' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var Foo = /* @__PURE__ */ ((Foo) => {
  Foo[Foo["A"] = 1] = "A";
  return Foo;
})(Foo || {});
var Foo = /* @__PURE__ */ ((Foo) => {
  Foo[Foo["B"] = 2] = "B";
  return Foo;
})(Foo || {});
JS . "\n", $lowerer->lower('enum Foo { A = 1 } enum Foo { B = 2 }'));

        $t->same(<<<'JS'
var Foo = /* @__PURE__ */ ((_Foo) => {
  _Foo[_Foo["Foo"] = 1] = "Foo";
  _Foo[_Foo["Bar"] = 1 /* Foo */] = "Bar";
  return _Foo;
})(Foo || {});
JS . "\n", $lowerer->lower('enum Foo { Foo = 1, Bar = Foo }'));

        $lowered = $lowerer->lower(<<<'TS'
enum Foo {
  'a' = 10.01,
  'a b' = 100,
  c = a + Foo.a + Foo['a b'],
  d,
  e = a + Foo.a + Foo['a b'] + Math.random(),
  f,
}
enum Bar {
  a = Foo.a
}
TS);

        $t->contains('Foo[Foo["c"] = 120.02] = "c";', $lowered);
        $t->contains('Foo[Foo["d"] = 121.02] = "d";', $lowered);
        $t->contains('Foo[Foo["f"] = void 0] = "f";', $lowered);
        $t->contains('Bar[Bar["a"] = 10.01 /* a */] = "a";', $lowered);
    },
    'rejects malformed upstream typescript enum members' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('enum x { y z }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower("enum x { 'y' 'z' }"));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('enum x { y = 0 z }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower("enum x { 'y' = 0 'z' }"));
    },
    'inlines upstream same file enum member references' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
foo = 0 /* FOO */;
var Foo = /* @__PURE__ */ ((Foo) => {
  Foo[Foo["FOO"] = 0] = "FOO";
  return Foo;
})(Foo || {});
bar = 0 /* FOO */;
JS . "\n", $lowerer->lower('foo = Foo.FOO; enum Foo { FOO } bar = Foo.FOO'));

        $lowered = $lowerer->lower(<<<'TS'
enum a_num { x = 123 }
enum b_num { x = 123 }
enum a_str { x = 'abc' }
inlined = [a_num.x, b_num['x'], a_str.x]
not_inlined = [a_num?.x, b_num?.['x']]
TS);

        $t->contains('inlined = [123 /* x */, 123 /* x */, "abc" /* x */];', $lowered);
        $t->contains("not_inlined = [a_num?.x, b_num?.['x']];", $lowered);
    },
    'erases non exported const enums while inlining same file accesses' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $lowered = $lowerer->lower(<<<'TS'
const enum Mode {
  Card,
  Grid = 3,
  List,
}
const config = { viewMode: Mode.Card, layout: Mode['Grid'] };
TS);

        $t->true(!str_contains($lowered, 'var Mode'));
        $t->contains('const config = {viewMode:0 /* Card */, layout:3 /* Grid */}', $lowered);
    },
    'lowers upstream typescript type annotation erasure subset' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same("let x = Foo;\n", $lowerer->lower('let x: () => void = Foo'));
        $t->same("const x = y;\n", $lowerer->lower('const x: unique<T> = y'));
        $t->same("let fs = require(\"fs\");\n", $lowerer->lower("let fs: typeof import('fs') = require('fs')"));
        $t->same("let x = \"x\";\n", $lowerer->lower("let x = 'x' as keyof typeof Foo"));
        $t->same("function edit(props) {return props.title;}\n", $lowerer->lower('function edit(props: Props): WPElement { return props.title; }'));
        $t->same("const edit = (props) => wp.element.createElement(\"div\", {}, props.attributes.title);\n", $lowerer->lower(
            "const edit = (props: BlockEditProps<{ title: string }>): WPElement => wp.element.createElement('div', {}, props.attributes.title);"
        ));
    },
    'lowers upstream typescript using declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same("using x = y;\n", $lowerer->lower('using x = y'));
        $t->same("using x = y;\n", $lowerer->lower('using x: any = y'));
        $t->same("using x = y, z = _;\n", $lowerer->lower('using x: any = y, z: any = _'));
        $t->same("using x = y, z = _;\n", $lowerer->lower("using x: any = y,\n z: any = _"));
        $t->same("await using x = y, z = _;\n", $lowerer->lower("await using x: any = y,\n z: any = _"));
        $t->same("using;\nx = y;\n", $lowerer->lower("using \n x = y"));
    },
    'rejects upstream exported typescript using declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('export using x: any = y'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('export await using x: any = y'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('using x: Disposable'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('using x = y, z'));
    },
    'maps upstream using nullish initializer optimization boundaries' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same("using x = void 0;\n", $lowerer->lower('using x = undefined'));
        $t->same("using x = (foo, void 0);\n", $lowerer->lower('using x = (foo, undefined)'));
        $t->same("const x = null;\n", $lowerer->lower('using x = null', minifySyntax: true));
        $t->same("const x = void 0;\n", $lowerer->lower('using x = undefined', minifySyntax: true));
        $t->same("const x = (foo, null);\n", $lowerer->lower('using x = (foo, null)', minifySyntax: true));
        $t->same("const x = null, y = void 0;\n", $lowerer->lower('using x = null, y = undefined', minifySyntax: true));
        $t->same("using x = null, y = z;\n", $lowerer->lower('using x = null, y = z', minifySyntax: true));
        $t->same("using x = z, y = void 0;\n", $lowerer->lower('using x = z, y = undefined', minifySyntax: true));
        $t->same("await using x = null;\n", $lowerer->lower('await using x = null', minifySyntax: true));
    },
    'lowers upstream using declarations through explicit resource helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            'foo; using x: Disposable = y, z = undefined; bar',
            lowerUsingDeclarations: true
        );

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains('var __callDispose = (stack, error, hasError) => {', $lowered);
        $t->contains('var _stack = [];', $lowered);
        $t->contains("try {\n  foo;\n  var x = __using(_stack, y), z = __using(_stack, void 0);\n  bar;\n}", $lowered);
        $t->contains("} catch (_) {\n  var _error = _, _hasError = true;\n} finally {\n  __callDispose(_stack, _error, _hasError);\n}", $lowered);
        $t->true(strpos($lowered, 'var __using') < strpos($lowered, 'try {'));

        $optimized = (new TypeScriptModuleLowerer())->lower(
            'using x = null',
            minifySyntax: true,
            lowerUsingDeclarations: true
        );
        $t->same("var x = null;\n", $optimized);
        $t->true(!str_contains($optimized, '__using'));
    },
    'keeps upstream module statements outside top level using helper scopes' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
"use strict";
import metadata from "./block.json" with { type: "json" };
export * from "./shared";
using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);
queue(previewAsset.url);
export { previewAsset as previewAssetHandle };
TS,
            lowerUsingDeclarations: true
        );

        $t->true(strpos($lowered, '"use strict";') < strpos($lowered, 'var __knownSymbol'));
        $t->true(strpos($lowered, 'import metadata from "./block.json" with { type: "json" };') < strpos($lowered, 'var __knownSymbol'));
        $t->true(strpos($lowered, 'export * from "./shared";') < strpos($lowered, 'var __knownSymbol'));
        $t->contains("try {\n  var previewAsset = __using(_stack, acquirePreviewAsset(metadata.viewScript));\n  queue(previewAsset.url);\n}", $lowered);
        $t->true(strpos($lowered, '} finally {') < strpos($lowered, 'export { previewAsset as previewAssetHandle };'));
        $t->true(!str_contains($lowered, "try {\n  import "));
        $t->true(!str_contains($lowered, "try {\n  export "));

        $ordinaryString = (new TypeScriptModuleLowerer())->lower(
            'using asset = acquire(); "after";',
            lowerUsingDeclarations: true
        );
        $t->contains("try {\n  var asset = __using(_stack, acquire());\n  \"after\";\n}", $ordinaryString);
        $t->true(strpos($ordinaryString, 'var asset = __using') < strpos($ordinaryString, '"after";'));
    },
    'keeps upstream local exports outside top level using helper scopes' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
using previewAsset: Disposable = acquire();
export const settings: BlockSettings = { viewScript: previewAsset.url };
export class PreviewRegistration {
  register() { return settings; }
}
queue(settings);
TS,
            lowerUsingDeclarations: true
        );

        $t->contains("try {\n  var previewAsset = __using(_stack, acquire());\n  var settings = {viewScript:previewAsset.url};", $lowered);
        $t->contains('var PreviewRegistration = class {', $lowered);
        $t->contains('register() { return settings; }', $lowered);
        $t->contains("queue(settings);\n} catch (_)", $lowered);
        $t->contains("export {\n  settings\n};", $lowered);
        $t->contains("export {\n  PreviewRegistration\n};", $lowered);
        $t->true(strpos($lowered, 'var settings =') < strpos($lowered, '__callDispose(_stack'));
        $t->true(strpos($lowered, '__callDispose(_stack') < strpos($lowered, "export {\n  settings"));
        $t->true(!str_contains($lowered, "try {\n  export "));
        $t->true(!str_contains($lowered, 'export const settings'));
        $t->true(!str_contains($lowered, 'export class PreviewRegistration'));
    },
    'keeps upstream destructured local exports outside top level using helper scopes' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
using a = b;
export var ac1 = [a, c], { x: [x1] } = foo;
export let a1 = a, { y: [y1 = fallback] } = foo;
export const c1 = c, { [key]: [[z1]], ...rest } = foo;
using c = d;
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var ac1 = [a, c], { x: [x1] } = foo;', $lowered);
        $t->contains('var a1 = a, { y: [y1 = fallback] } = foo;', $lowered);
        $t->contains('var c1 = c, { [key]: [[z1]], ...rest } = foo;', $lowered);
        $t->contains("export {\n  ac1,\n  x1\n};", $lowered);
        $t->contains("export {\n  a1,\n  y1\n};", $lowered);
        $t->contains("export {\n  c1,\n  z1,\n  rest\n};", $lowered);
        $t->true(strpos($lowered, 'var ac1 =') < strpos($lowered, '__callDispose(_stack'));
        $t->true(strpos($lowered, '__callDispose(_stack') < strpos($lowered, "export {\n  ac1"));
        $t->true(!str_contains($lowered, "try {\n  export "));
        $t->true(!str_contains($lowered, 'export var ac1'));
        $t->true(!str_contains($lowered, 'export let a1'));
        $t->true(!str_contains($lowered, 'export const c1'));
    },
    'keeps upstream function declarations outside top level using helper scopes' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
using a: Disposable = b;
export function foo1() { return [a, c] }
export default function fooDefault() { return [a, c, fooDefault] }
function helper() { return [a, c, helper] }
using c: Disposable = d;
TS,
            lowerUsingDeclarations: true
        );

        $t->true(strpos($lowered, 'export function foo1()') < strpos($lowered, 'var __knownSymbol'));
        $t->true(strpos($lowered, 'export default function fooDefault()') < strpos($lowered, 'var __knownSymbol'));
        $t->true(strpos($lowered, 'function helper()') < strpos($lowered, 'var __knownSymbol'));
        $t->contains("try {\n  var a = __using(_stack, b);\n  var c = __using(_stack, d);\n}", $lowered);
        $t->true(!str_contains($lowered, "try {\n  export function"));
        $t->true(!str_contains($lowered, "try {\n  function helper"));
    },
    'lowers upstream block scoped using declarations through explicit resource helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            'if (nested) { using x: Disposable = y; bar(x); }',
            lowerUsingDeclarations: true
        );

        $t->contains("if (nested) {\n  var _stack2 = [];\n  try {\n    const x = __using(_stack2, y);\n    bar(x);\n  } catch (_2) {\n    var _error2 = _2, _hasError2 = true;\n  } finally {\n    __callDispose(_stack2, _error2, _hasError2);\n  }\n}", $lowered);
        $t->true(strpos($lowered, 'var __using') < strpos($lowered, 'if (nested)'));

        $awaitLowered = (new TypeScriptModuleLowerer())->lower(
            'if (nested) { await using y: AsyncDisposable = acquire(); done(y); }',
            lowerUsingDeclarations: true
        );
        $t->contains('const y = __using(_stack2, acquire(), true);', $awaitLowered);
        $t->contains('var _promise2 = __callDispose(_stack2, _error2, _hasError2);', $awaitLowered);
        $t->contains('_promise2 && await _promise2;', $awaitLowered);
    },
    'lowers upstream function scoped using declarations through explicit resource helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            'function foo() { using a: Disposable = b; if (nested) { using x: Disposable = y; bar(x); } done(a); }',
            lowerUsingDeclarations: true
        );

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains("function foo() {\n  var _stack2 = [];\n  try {\n    const a = __using(_stack2, b);", $lowered);
        $t->contains("if (nested) {\n      var _stack3 = [];\n      try {\n        const x = __using(_stack3, y);\n        bar(x);", $lowered);
        $t->contains('__callDispose(_stack3, _error3, _hasError3);', $lowered);
        $t->contains('__callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->true(strpos($lowered, 'const a = __using(_stack2, b);') < strpos($lowered, 'done(a);'));
        $t->true(strpos($lowered, 'bar(x);') < strpos($lowered, '__callDispose(_stack3'));

        $awaitLowered = (new TypeScriptModuleLowerer())->lower(
            'async function bar() { using a: Disposable = b; await using c: AsyncDisposable = d; if (nested) { await using y: AsyncDisposable = acquire(); done(y); } done(a, c); }',
            lowerUsingDeclarations: true
        );
        $t->contains('const c = __using(_stack2, d, true);', $awaitLowered);
        $t->contains('const y = __using(_stack3, acquire(), true);', $awaitLowered);
        $t->contains('var _promise3 = __callDispose(_stack3, _error3, _hasError3);', $awaitLowered);
        $t->contains('_promise3 && await _promise3;', $awaitLowered);
        $t->contains('var _promise2 = __callDispose(_stack2, _error2, _hasError2);', $awaitLowered);
        $t->contains('_promise2 && await _promise2;', $awaitLowered);
    },
    'erases upstream function scoped typescript using declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
function foo() {
  using x = y;
}
JS . "\n", $lowerer->lower('function foo() { using x: Disposable = y }'));

        $t->same(<<<'JS'
foo = function() {
  using x = y;
};
JS . "\n", $lowerer->lower('foo = function() { using x: Disposable = y }'));

        $t->same(<<<'JS'
foo = async () => {
  await using x = y;
};
JS . "\n", $lowerer->lower('foo = async () => { await using x: Disposable = y }'));

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('function foo() { await using x: Disposable = y }'));
    },
    'lowers upstream for using declarations with erased types' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same("for(using x of y)body(x);\n", $lowerer->lower('for (using x: Disposable of y) body(x)'));
        $t->same("for await(using asset of assets)register(asset);\n", $lowerer->lower('for await (using asset: Disposable of assets) register(asset)'));
        $t->same("for(await using x of y)body(x);\n", $lowerer->lower('for (await using x: AsyncDisposable of y) body(x)'));
        $t->same("for await(await using x of y)body(x);\n", $lowerer->lower('for await (await using x: AsyncDisposable of y) body(x)'));
        $t->same("for(using x = y;;)body(x);\n", $lowerer->lower('for (using x: Disposable = y;;) body(x)'));

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('for (using x: Disposable in y) body(x)'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('for (await using x: AsyncDisposable = y;;) body(x)'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('for (using x: Disposable = y of z) body(x)'));
    },
    'lowers upstream for using loops through explicit resource helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
for (using a: Disposable of b) c(() => a)
for (await using d: AsyncDisposable of e) f(() => d)
for await (using g: Disposable of h) i(() => g)
for await (await using j: AsyncDisposable of k) l(() => j)
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains("for (var _a of b) {\n  var _stack2 = [];\n  try {\n    const a = __using(_stack2, _a);\n    c(() => a);", $lowered);
        $t->contains('__callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains("for (var _d of e) {\n  var _stack3 = [];\n  try {\n    const d = __using(_stack3, _d, true);\n    f(() => d);", $lowered);
        $t->contains('var _promise3 = __callDispose(_stack3, _error3, _hasError3);', $lowered);
        $t->contains('_promise3 && await _promise3;', $lowered);
        $t->contains('for await (var _g of h) {', $lowered);
        $t->contains('const g = __using(_stack4, _g);', $lowered);
        $t->contains('for await (var _j of k) {', $lowered);
        $t->contains('const j = __using(_stack5, _j, true);', $lowered);
        $t->true(strpos($lowered, 'const a = __using') < strpos($lowered, 'c(() => a);'));
        $t->true(strpos($lowered, 'c(() => a);') < strpos($lowered, '__callDispose(_stack2'));

        $functionLowered = (new TypeScriptModuleLowerer())->lower(
            'function foo() { for (using asset: Disposable of assets) { register(asset); } }',
            lowerUsingDeclarations: true
        );
        $t->contains("function foo() {\n  for (var _asset of assets) {\n    var _stack2 = [];\n    try {\n      const asset = __using(_stack2, _asset);\n      register(asset);", $functionLowered);
        $t->contains('__callDispose(_stack2, _error2, _hasError2);', $functionLowered);
    },
    'erases upstream ambient typescript declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same('', $lowerer->lower('declare var x: number'));
        $t->same('', $lowerer->lower('declare let x: number'));
        $t->same('', $lowerer->lower('declare const x: number'));
        $t->same("function scope(){}\n", $lowerer->lower('declare function fn() {} function scope(){}'));
        $t->same("function scope(){}\n", $lowerer->lower('declare class X { x = function() {} } function scope(){}'));
        $t->same("function scope(){}\n", $lowerer->lower('declare namespace X { export var x = function() {} } function scope(){}'));
        $t->same("let foo;\n", $lowerer->lower("declare module 'X'; let foo"));
        $t->same("let foo;\n", $lowerer->lower("declare module 'X'\nlet foo"));
        $t->same('', $lowerer->lower("declare module 'X'\n{ let foo }"));
        $t->same("let bar;\n", $lowerer->lower('declare global { interface Foo {} let foo: any } let bar'));
        $t->same('', $lowerer->lower('declare module M { export as namespace ns; }'));
        $t->same('', $lowerer->lower('export as namespace ns'));
    },
    'erases upstream decorated abstract ambient class declarations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same('', $lowerer->lower('declare abstract class Foo { accessor #x }'));
        $t->same('', $lowerer->lower('export declare abstract class Foo { accessor #x }'));
        $t->same("{let foo}\n", $lowerer->lower('@dec(() => 0) declare class Foo {} {let foo}'));
        $t->same("{let foo}\n", $lowerer->lower('@dec(() => 0) export declare abstract class Foo {} {let foo}'));
        $t->same("{let foo}\n", $lowerer->lower('declare class Foo { @dec(() => 0) foo(@arg(() => 0) x) } {let foo}'));
    },
    'erases upstream class member declare fields' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same("class Foo {\n}\n", $lowerer->lower('class Foo { declare foo: number }'));
        $t->same("class Foo {\n}\n", $lowerer->lower('class Foo { declare public foo: number }'));
        $t->same("class Foo {\n}\n", $lowerer->lower('class Foo { public declare foo: number }'));
        $t->same("class Foo {\n}\n", $lowerer->lower('class Foo { declare override public static foo: number }'));
        $t->same("class Foo {\n}\n", $lowerer->lower('class Foo { public static declare foo: number }'));
        $t->same("class Foo {\n}\n", $lowerer->lower('class Foo { declare static foo = 123 }'));
        $t->same("class Foo {\n}\n", $lowerer->lower('class Foo { static declare foo = 123 }'));
        $t->same("class Foo {\n}\n", $lowerer->lower('class Foo { declare accessor x }'));
        $t->same("class Foo {\n  bar = 1;\n}\n", $lowerer->lower('class Foo { declare foo = 123; bar = 1 }'));
    },
    'rejects upstream invalid declare class member boundaries' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { declare #foo }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { declare [foo: string]: number }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { declare foo() }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { declare get foo() }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { declare set foo(x) }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { static declare #foo }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { static declare foo() }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { @(() => {}) declare foo: any; bar = 1 }'));
    },
    'lowers upstream abstract class members and headers' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A {
  bar() {}
}
JS . "\n", $lowerer->lower('abstract class A { abstract foo(): void; bar(): void {} }'));

        $t->same(<<<'JS'
export class A {
  bar() {}
}
JS . "\n", $lowerer->lower('export abstract class A { abstract foo(): void; bar(): void {} }'));

        $t->same(<<<'JS'
export default class A {
  bar() {}
}
- after;
JS . "\n", $lowerer->lower('export default abstract class A { abstract foo(): void; bar(): void {} } - after'));

        $t->same(<<<'JS'
class A {
  abstract;
  foo() {}
}
JS . "\n", $lowerer->lower("abstract class A { abstract \n foo(): void {} }"));
    },
    'erases upstream class method type parameters and optional markers' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  foo() {}
}
JS . "\n", $lowerer->lower('class Foo { foo<T>() {} }'));

        $t->same(<<<'JS'
class Foo {
  foo() {}
}
JS . "\n", $lowerer->lower('class Foo { foo?<T>() {} }'));

        $t->same(<<<'JS'
class Foo {
  [foo]() {}
}
JS . "\n", $lowerer->lower('class Foo { [foo]<T>() {} }'));

        $t->same(<<<'JS'
class Foo {
  [foo]() {}
}
JS . "\n", $lowerer->lower('class Foo { [foo]?<T>() {} }'));
    },
    'lowers upstream typescript auto accessor markers and types' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  accessor x;
}
JS . "\n", $lowerer->lower('class Foo { accessor x? }'));

        $t->same(<<<'JS'
class Foo {
  accessor x = y;
}
JS . "\n", $lowerer->lower('class Foo { accessor x!: any = y }'));

        $t->same(<<<'JS'
class Foo {
  accessor [x] = y;
}
JS . "\n", $lowerer->lower('class Foo { accessor [x]?: any = y }'));

        $t->same(<<<'JS'
class Foo {
  accessor #x;
}
JS . "\n", $lowerer->lower('class Foo { accessor #x!: any }'));

        $t->same(<<<'JS'
class Foo {
  accessor x;
}
JS . "\n", $lowerer->lower('class Foo { readonly accessor x }'));

        $t->same("let x;\n", $lowerer->lower('let x: { accessor x }'));
        $t->same("let x;\n", $lowerer->lower('let x: { static accessor x }'));
    },
    'rejects malformed upstream typescript auto accessors' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { accessor x<T> }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { accessor x<T>() {} }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { accessor declare x }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { accessor readonly x }'));
    },
    'rejects upstream definite assignment markers on class methods' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { foo!() {} }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { *foo!() {} }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { get foo!() {} }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { set foo!(x) {} }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { async foo!() {} }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { foo!<T>() {} }'));
    },
    'lowers upstream class fields in assign semantics mode' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
}
JS . "\n", $lowerer->lower('class Foo { foo: number }', false));

        $t->same(<<<'JS'
class Foo {
  constructor() {
    this.foo = 0;
  }
}
JS . "\n", $lowerer->lower('class Foo { foo?: number = 0 }', false));

        $t->same(<<<'JS'
class Foo {
  constructor() {
    this.foo = 0;
  }
}
JS . "\n", $lowerer->lower('class Foo { foo!: number = 0 }', false));

        $t->same(<<<'JS'
class Foo {
  constructor() {
    this["foo"] = 0;
  }
}
JS . "\n", $lowerer->lower("class Foo { ['foo']: number = 0 }", false));

        $t->same(<<<'JS'
class Foo {
  constructor() {
    this.foo = 0;
  }
}
JS . "\n", $lowerer->lower('class Foo { [key: string]: any; foo = 0 }', false));
    },
    'lowers upstream static class fields in assign semantics mode' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  static {
    this.foo = 0;
  }
}
JS . "\n", $lowerer->lower('class Foo { static foo: number = 0 }', false));
    },
    'caches upstream computed class field keys in assign semantics mode' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _a;
_a = foo;
class Foo {
  constructor() {
    this[_a] = 0;
  }
}
JS . "\n", $lowerer->lower('class Foo { [foo] = 0 }', false));

        $t->same(<<<'JS'
var _a;
_a = sideEffect();
class Foo {
}
JS . "\n", $lowerer->lower('class Foo { [sideEffect()] }', false));

        $t->same(<<<'JS'
var _a;
_a = x();
class Foo {
  static {
    this[_a] = 1;
  }
}
JS . "\n", $lowerer->lower('class Foo { static [x()] = 1 }', false));
    },
    'preserves upstream computed class field key order in derived assign semantics classes' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _a, _b;
class A extends (_b = B, _a = x, _b) {
  constructor() {
    foo();
    super(1);
    this[_a] = 1;
  }
}
JS . "\n", $lowerer->lower('class A extends B { [x] = 1; constructor() { foo(); super(1); } }', false));

        $lowered = $lowerer->lower('class A extends resolveBase() { [assetKey("settings")] = metadata; constructor() { super(metadata); } }', false);
        $t->contains('class A extends (_b = resolveBase(), _a = assetKey("settings"), _b) {', $lowered);
        $t->true(strpos($lowered, '_b = resolveBase()') < strpos($lowered, '_a = assetKey("settings")'));
        $t->true(strpos($lowered, 'super(metadata);') < strpos($lowered, 'this[_a] = metadata;'));
    },
    'preserves upstream computed class key side effect order in assign semantics mode' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _a, _b, _c;
class Foo {
  constructor() {
    this[_a] = 1;
  }
  [a()]() {}
  [(b(), _a = c(), d())]() {}
  static {
    this[_b] = 1;
  }
  static [(e(), _b = f(), _c = g(), h(), _c)]() {}
}
JS . "\n", $lowerer->lower(<<<'TS'
class Foo {
  [a()]() {}
  [b()];
  [c()] = 1;
  [d()]() {}
  static [e()];
  static [f()] = 1;
  static [g()]() {}
  [h()];
}
TS, false));
    },
    'lowers upstream constructor parameter properties' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  constructor(x) {
    this.x = x;
  }
  x;
}
JS . "\n", $lowerer->lower('class Foo { constructor(public x) {} }'));

        $t->same(<<<'JS'
class Foo {
  constructor(x = "card", normal) {
    this.x = x;
    this.ready = true;
  }
  x;
}
JS . "\n", $lowerer->lower("class Foo { constructor(protected readonly x: string = 'card', normal: number) { this.ready = true; } }"));

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { constructor(public {x}) {} }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { constructor(private [x]) {} }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { constructor(public) {} }'));
    },
    'lowers upstream constructor parameter properties in assign semantics mode' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  constructor(x) {
    this.x = x;
  }
}
JS . "\n", $lowerer->lower('class Foo { constructor(public x) {} }', false));

        $t->same(<<<'JS'
class Foo {
  constructor(x) {
    this.x = x;
  }
}
JS . "\n", $lowerer->lower('class Foo { constructor(private readonly x) {} }', false));
    },
    'inserts upstream derived constructor parameter properties after super' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    foo();
    super(1);
    this.x = x;
  }
}
JS . "\n", $lowerer->lower(<<<'TS'
class A extends B {
  constructor(public x = 1) {
    foo();
    super(1);
  }
}
TS, false));
    },
    'wraps upstream multiple derived super calls for parameter properties' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    var __super = (...args) => {
      super(...args);
      this.x = x;
      return this;
    };
    foo();
    __super(1);
    __super(2);
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { foo(); super(1); super(2); } }', false));
    },
    'wraps upstream conditional derived super calls for parameter properties' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    var __super = (...args) => {
      super(...args);
      this.x = x;
      return this;
    };
    if (foo) __super(1);
    else __super(2);
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { if (foo) super(1); else super(2); } }', false));
    },
    'wraps upstream logical assignment derived super calls for assign semantics fields' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor() {
    var __super = (...args) => {
      super(...args);
      this.x = 1;
      return this;
    };
    foo();
    y ||= __super(1);
  }
}
JS . "\n", $lowerer->lower('class A extends B { x = 1; constructor() { foo(); y ||= super(1); } }', false));

        $uninitialized = $lowerer->lower('class A extends B { x; constructor() { foo(); y ||= super(1); } }', false);
        $t->contains('y ||= super(1);', $uninitialized);
        $t->true(!str_contains($uninitialized, '__super'));
    },
    'keeps upstream dead false super branches outside the helper path' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    if (false) __super(1);
    super(2);
    this.x = x;
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { if (false) super(1); super(2); } }', false));
    },
    'injects upstream assign semantics fields into one line derived constructors' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor() {
    foo();
    super(1);
    this.x = 1;
  }
}
JS . "\n", $lowerer->lower('class A extends B { x = 1; constructor() { foo(); super(1); } }', false));
    },
    'splits upstream comma expression derived super calls before assignment injection' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    foo();
    super(1);
    this.x = x;
    bar();
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { foo(), super(1), bar(); } }', false));

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    foo(), bar();
    super(1);
    this.x = x;
    baz(), qux();
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { foo(), bar(), super(1), baz(), qux(); } }', false));

        $lowered = $lowerer->lower('class A extends B { constructor(public x = 1) { foo(), super(1), bar(); } }', false);
        $t->true(!str_contains($lowered, '__super'));
    },
    'splits upstream return and throw comma expression derived super calls before assignment injection' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    foo();
    super(1);
    this.x = x;
    return bar();
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { return foo(), super(1), bar(); } }', false));

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    foo();
    super(1);
    this.x = x;
    throw bar();
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { throw foo(), super(1), bar(); } }', false));

        $helper = $lowerer->lower('class A extends B { constructor(public x = 1) { return super(1); } }', false);
        $t->contains('return __super(1);', $helper);
    },
    'splits upstream switch tests and for initializers around derived super calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    foo();
    super(1);
    this.x = x;
    switch(bar()) {case 1:baz();}
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { switch (foo(), super(1), bar()) { case 1: baz(); } } }', false));

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    foo();
    super(1);
    this.x = x;
    for(bar();test;update())body();
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { for (foo(), super(1), bar(); test; update()) body(); } }', false));

        $t->same(<<<'JS'
class A extends B {
  constructor(x = 1) {
    super(1);
    this.x = x;
    for(;test;update())body();
  }
}
JS . "\n", $lowerer->lower('class A extends B { constructor(public x = 1) { for (super(1); test; update()) body(); } }', false));

        $lowered = $lowerer->lower('class A extends B { constructor(public x = 1) { if (foo(), super(1), bar()) baz(); } }', false);
        $t->contains("foo();\n    super(1);\n    this.x = x;\n    if (bar()) baz();", $lowered);
        $t->true(!str_contains($lowered, '__super'));
    },
    'lowers upstream private class fields in assign semantics super insertion' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class A extends B {
  constructor() {
    super();
    this.y = 1;
  }
  #x;
}
JS . "\n", $lowerer->lower('class A extends B { #x; y = 1; constructor() { super() } }', false));

        $t->same(<<<'JS'
class A extends B {
  constructor() {
    super();
    this.#x = 1;
    this.y = 2;
  }
  #x;
}
JS . "\n", $lowerer->lower('class A extends B { #x = 1; y = 2; constructor() { super() } }', false));
    },
    'keeps non ambient declare line breaks and rejects malformed export as namespace' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same("declare;\nvar foo;\n", $lowerer->lower("declare\nvar foo"));
        $t->same("declare;\nvar Foo = /* @__PURE__ */ ((Foo) => {\n  return Foo;\n})(Foo || {});\n", $lowerer->lower("declare\nenum Foo {}"));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('export as namespace ns.foo'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('export as namespace ns function foo() {}'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('declare module M { export as namespace ns.foo }'));
    },
    'lowers wordpress commonjs block export without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-commonjs-export.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('function registerBlock()', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, metadata);', $lowered);
        $t->same("module.exports = registerBlock;\n", substr($lowered, -32));
    },
    'lowers wordpress typed block callbacks without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-typed-callback.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('const edit = (props) => {return wp.element.createElement("div", {}, props.attributes.title);}', $lowered);
        $t->contains('wp.blocks.registerBlockType("port-libs/card", {edit});', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockEditProps'));
        $t->true(!str_contains($lowered, 'satisfies'));
    },
    'lowers wordpress runtime enum config without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-enum-config.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('var DisplayMode = /* @__PURE__ */ ((DisplayMode) => {', $lowered);
        $t->contains('DisplayMode[DisplayMode["Card"] = 0] = "Card";', $lowered);
        $t->contains('DisplayMode[DisplayMode["Grid"] = 3] = "Grid";', $lowered);
        $t->contains('DisplayMode[DisplayMode["List"] = 4] = "List";', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, config);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress const enum config without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-const-enum-config.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->true(!str_contains($lowered, 'var DisplayMode'));
        $t->contains('viewMode:0 /* Card */', $lowered);
        $t->contains('layout:3 /* Grid */', $lowered);
        $t->contains('fallback:4 /* List */', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, config);', $lowered);
    },
    'lowers wordpress enum alias config without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-enum-alias-config.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('DisplayMode[DisplayMode["Card"] = 0] = "Card";', $lowered);
        $t->contains('DisplayMode[DisplayMode["Grid"] = 3] = "Grid";', $lowered);
        $t->contains('DisplayMode[DisplayMode["Default"] = 0 /* Card */] = "Default";', $lowered);
        $t->contains('DisplayMode[DisplayMode["Wide"] = 3 /* Grid */] = "Wide";', $lowered);
        $t->contains('viewMode:0 /* Default */', $lowered);
        $t->contains('supports:{layout:3 /* Wide */, fallback:0 /* Card */', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, config);', $lowered);
    },
    'erases wordpress ambient type declarations without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-ambient-types.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains("const metadata = { name: 'port-libs/card' };", $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, {', $lowered);
        $t->contains('supports: { html: false },', $lowered);
        $t->true(!str_contains($lowered, 'declare module'));
        $t->true(!str_contains($lowered, 'declare global'));
        $t->true(!str_contains($lowered, 'export as namespace'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
    },
    'erases wordpress ambient exported class declarations without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-ambient-exports.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains("const metadata = { name: 'port-libs/card' };", $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, {', $lowered);
        $t->contains('supports: { html: false },', $lowered);
        $t->true(!str_contains($lowered, 'declare abstract class'));
        $t->true(!str_contains($lowered, 'export declare'));
        $t->true(!str_contains($lowered, '#view'));
        $t->true(!str_contains($lowered, '@blockTypes'));
    },
    'erases wordpress declared class fields without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-class-declare-settings.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('class CardBlockController {', $lowered);
        $t->contains('register() {wp.blocks.registerBlockType(metadata.name, {edit:Edit});}', $lowered);
        $t->contains('controller.register();', $lowered);
        $t->true(!str_contains($lowered, 'declare'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, 'blockName'));
        $t->true(!str_contains($lowered, 'supports ='));
    },
    'lowers wordpress constructor property controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-constructor-properties.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('class BaseController {', $lowered);
        $t->true(!str_contains($lowered, 'abstract register'));
        $t->contains('class CardBlockController extends BaseController {', $lowered);
        $t->contains('constructor(blockName = metadata.name, blocks = wp.blocks) {', $lowered);
        $t->contains('this.blockName = blockName;', $lowered);
        $t->contains('this.blocks = blocks;', $lowered);
        $t->true(strpos($lowered, 'super();') < strpos($lowered, 'this.blockName = blockName;'));
        $t->contains('blockName;', $lowered);
        $t->contains('blocks;', $lowered);
        $t->contains('register() {this.blocks.registerBlockType(this.blockName, {supports:{html:false}});}', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress constructor properties in assign semantics without field declarations' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-constructor-properties.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('class CardBlockController extends BaseController {', $lowered);
        $t->contains('constructor(blockName = metadata.name, blocks = wp.blocks) {', $lowered);
        $t->true(strpos($lowered, 'super();') < strpos($lowered, 'this.blockName = blockName;'));
        $t->contains('this.blocks = blocks;', $lowered);
        $t->true(!str_contains($lowered, "\n  blockName;\n"));
        $t->true(!str_contains($lowered, "\n  blocks;\n"));
        $t->contains('this.blocks.registerBlockType(this.blockName, {supports:{html:false}});', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
    },
    'lowers wordpress class field assign semantics without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-class-fields-assign.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('const metadata = { name: \'port-libs/card\' };', $lowered);
        $t->contains('constructor() {', $lowered);
        $t->contains('this.blockName = metadata.name;', $lowered);
        $t->contains('this.settings = {supports:{html:false}};', $lowered);
        $t->contains('static {', $lowered);
        $t->contains('this.metadata = metadata;', $lowered);
        $t->contains('register(config = this.settings) {wp.blocks.registerBlockType(this.blockName, config);}', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress computed class field asset keys without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-computed-class-fields.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('var _a, _b;', $lowered);
        $t->contains('_a = assetKey("viewScript");', $lowered);
        $t->contains('_b = assetKey("worker");', $lowered);
        $t->contains('this[_a] = "file:./view.js";', $lowered);
        $t->contains('this[_b] = "file:./card-worker.js";', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, config);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress computed super controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-computed-super-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('var _a, _b;', $lowered);
        $t->contains('class CardBlockComputedController extends (_b = resolveBaseController(metadata), _a = assetKey("settings"), _b) {', $lowered);
        $t->contains('super(metadata);', $lowered);
        $t->contains('this[_a] = {supports:{html:false}, viewScript:"file:./view.js"};', $lowered);
        $t->contains('this.blocks.registerBlockType(this.blockName, this[assetKey("settings")]);', $lowered);
        $t->true(strpos($lowered, '_b = resolveBaseController(metadata)') < strpos($lowered, '_a = assetKey("settings")'));
        $t->true(strpos($lowered, 'super(metadata);') < strpos($lowered, 'this[_a] ='));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress conditional super constructor controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-conditional-super-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('var __super = (...args) => {', $lowered);
        $t->contains('super(...args);', $lowered);
        $t->contains('this.blockName = blockName;', $lowered);
        $t->contains('if (previewMode) __super(metadata);', $lowered);
        $t->contains('else __super({name:blockName});', $lowered);
        $t->contains('this.blocks.registerBlockType(this.blockName, this.settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress lazy super controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-lazy-super-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('var __super = (...args) => {', $lowered);
        $t->contains('super(...args);', $lowered);
        $t->contains('this.blockName = metadata.name;', $lowered);
        $t->contains('this.settings = {supports:{html:false}};', $lowered);
        $t->contains('ready ||= __super(metadata);', $lowered);
        $t->contains('this.blocks.registerBlockType(this.blockName, this.settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress comma super controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-comma-super-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('preparePreview(), preloadAssets();', $lowered);
        $t->contains('super(metadata);', $lowered);
        $t->contains('this.blockName = blockName;', $lowered);
        $t->contains('hydrateAssets(), markReady();', $lowered);
        $t->contains('this.blocks.registerBlockType(this.blockName, this.settings);', $lowered);
        $t->true(!str_contains($lowered, '__super'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress return super controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-return-super-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('preparePreview();', $lowered);
        $t->contains('super(metadata);', $lowered);
        $t->contains('this.settings = {supports:{html:false}};', $lowered);
        $t->contains('this.blockName = blockName;', $lowered);
        $t->contains('return this;', $lowered);
        $t->true(strpos($lowered, 'super(metadata);') < strpos($lowered, 'this.settings ='));
        $t->true(strpos($lowered, 'this.blocks = blocks;') < strpos($lowered, 'return this;'));
        $t->true(!str_contains($lowered, '__super'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress control statement super controllers without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-control-super-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('preparePreview();', $lowered);
        $t->contains('switch(resolveMode()) {case "preview":preloadPreview();break;default:hydrateDefaults();}', $lowered);
        $t->contains('queueAssets();', $lowered);
        $t->contains('for(asset = nextAsset();asset;asset = nextAsset())hydrateAsset(asset);', $lowered);
        $t->true(strpos($lowered, 'preparePreview();') < strpos($lowered, 'switch(resolveMode())'));
        $t->true(strpos($lowered, 'queueAssets();') < strpos($lowered, 'for(asset = nextAsset()'));
        $t->true(substr_count($lowered, 'super(metadata);') === 2);
        $t->true(!str_contains($lowered, '__super'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress private settings controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-private-settings-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('constructor(blockName = metadata.name, blocks = wp.blocks) {', $lowered);
        $t->contains('super(metadata);', $lowered);
        $t->contains('this.#settings = {supports:{html:false}, viewScript:"file:./view.js"};', $lowered);
        $t->contains('this.blockName = blockName;', $lowered);
        $t->contains('this.blocks = blocks;', $lowered);
        $t->contains('#settings;', $lowered);
        $t->contains('this.blocks.registerBlockType(this.blockName, this.#settings);', $lowered);
        $t->true(strpos($lowered, 'super(metadata);') < strpos($lowered, 'this.#settings ='));
        $t->true(strpos($lowered, 'this.#settings =') < strpos($lowered, 'this.blockName = blockName;'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress auto accessor controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-auto-accessor-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('class CardBlockAccessorController {', $lowered);
        $t->contains('accessor settings = {supports:{html:false}, viewScript:"file:./view.js",};', $lowered);
        $t->contains('accessor [assetKey("worker")] = "file:./card-worker.js";', $lowered);
        $t->contains('accessor #blockName = metadata.name;', $lowered);
        $t->contains('wp.blocks.registerBlockType(this.#blockName, this.settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, '?:'));
        $t->true(!str_contains($lowered, '!:'));
    },
    'lowers wordpress using disposable asset handles without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-using-disposable.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);
        $legacyLowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->contains('using previewAsset = acquirePreviewAsset(metadata.viewScript), workerAsset = acquireWorkerAsset("file:./card-worker.js");', $lowered);
        $t->contains('const settings = {name:metadata.name, viewScript:previewAsset.url, worker:workerAsset.url,};', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'satisfies'));
        $t->contains('var previewAsset = __using(_stack, acquirePreviewAsset(metadata.viewScript)), workerAsset = __using(_stack, acquireWorkerAsset("file:./card-worker.js"));', $legacyLowered);
        $t->contains('__callDispose(_stack, _error, _hasError);', $legacyLowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $legacyLowered);
        $t->true(strpos($legacyLowered, 'var __using') < strpos($legacyLowered, 'try {'));
        $t->true(!str_contains($legacyLowered, '@wordpress/blocks'));
    },
    'lowers wordpress imported using asset cleanup without trapping module statements' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-using-import-hoist.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->true(strpos($lowered, 'import metadata from "./block.json" with { type: "json" };') < strpos($lowered, 'var __knownSymbol'));
        $t->contains('var previewAsset = __using(_stack, acquirePreviewAsset(metadata.viewScript));', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(strpos($lowered, '__callDispose(_stack, _error, _hasError);') < strpos($lowered, 'export { settings };'));
        $t->true(!str_contains($lowered, "try {\n  import "));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': Disposable'));
    },
    'lowers wordpress exported using asset settings without trapping exports' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-using-export-local.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->true(strpos($lowered, 'import metadata from "./block.json" with { type: "json" };') < strpos($lowered, 'var __knownSymbol'));
        $t->contains('var previewAsset = __using(_stack, acquirePreviewAsset(metadata.viewScript));', $lowered);
        $t->contains('var settings = {name:metadata.name, viewScript:previewAsset.url,};', $lowered);
        $t->contains('var PreviewRegistration = class {', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->contains("export {\n  settings\n};", $lowered);
        $t->contains("export {\n  PreviewRegistration\n};", $lowered);
        $t->true(strpos($lowered, '__callDispose(_stack, _error, _hasError);') < strpos($lowered, "export {\n  settings"));
        $t->true(!str_contains($lowered, "try {\n  export "));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress destructured exported using asset settings without trapping exports' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-using-destructured-export.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->true(strpos($lowered, 'import metadata from "./block.json" with { type: "json" };') < strpos($lowered, 'var __knownSymbol'));
        $t->contains('var {name:blockName} = metadata, settings = {name:blockName, viewScript:previewAsset.url,};', $lowered);
        $t->contains('var previewAsset = __using(_stack, acquirePreviewAsset(metadata.viewScript));', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->contains("export {\n  blockName,\n  settings\n};", $lowered);
        $t->true(strpos($lowered, '__callDispose(_stack, _error, _hasError);') < strpos($lowered, "export {\n  blockName"));
        $t->true(!str_contains($lowered, "try {\n  export "));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, 'satisfies'));
    },
    'lowers wordpress exported using asset functions without trapping exports' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-using-export-function.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->true(strpos($lowered, 'import metadata from "./block.json" with { type: "json" };') < strpos($lowered, 'var __knownSymbol'));
        $t->true(strpos($lowered, 'export function registerPreviewBlock()') < strpos($lowered, 'var __knownSymbol'));
        $t->contains('viewScript: previewAsset.url,', $lowered);
        $t->contains('var previewAsset = __using(_stack, acquirePreviewAsset(metadata.viewScript));', $lowered);
        $t->contains('__callDispose(_stack, _error, _hasError);', $lowered);
        $t->true(strpos($lowered, 'export function registerPreviewBlock()') < strpos($lowered, 'var previewAsset = __using'));
        $t->true(!str_contains($lowered, "try {\n  export function"));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress function scoped disposable asset handles without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-function-using-disposable.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('export function registerPreviewAsset(metadata) {', $lowered);
        $t->contains('using previewAsset = acquirePreviewAsset(metadata.viewScript);', $lowered);
        $t->contains('const settings = { name: metadata.name, viewScript: previewAsset.url };', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, ': Disposable'));
    },
    'lowers wordpress function scoped disposable asset cleanup without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-function-using-disposable.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->contains('export function registerPreviewAsset(metadata) {', $lowered);
        $t->contains('var _stack2 = [];', $lowered);
        $t->contains('const previewAsset = __using(_stack2, acquirePreviewAsset(metadata.viewScript));', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->contains('__callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->true(strpos($lowered, 'const previewAsset = __using') < strpos($lowered, 'wp.blocks.registerBlockType'));
        $t->true(strpos($lowered, 'wp.blocks.registerBlockType') < strpos($lowered, '__callDispose(_stack2'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, ': Disposable'));
    },
    'lowers wordpress block scoped disposable asset cleanup without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-block-using-disposable.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->contains('if (metadata.viewScript) {', $lowered);
        $t->contains('const previewAsset = __using(_stack2, acquirePreviewAsset(metadata.viewScript));', $lowered);
        $t->contains('queueAsset(previewAsset.url);', $lowered);
        $t->contains('__callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(strpos($lowered, 'const previewAsset = __using') < strpos($lowered, 'queueAsset(previewAsset.url);'));
        $t->true(strpos($lowered, 'queueAsset(previewAsset.url);') < strpos($lowered, '__callDispose(_stack2'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'satisfies'));
    },
    'lowers wordpress for using asset loops without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-for-using-assets.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('for(using asset of collectBlockAssets(metadata))', $lowered);
        $t->contains('registerAsset(asset.handle, asset.url);', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'satisfies'));
    },
    'lowers wordpress for using asset cleanup without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-for-using-assets.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->contains('for (var _asset of collectBlockAssets(metadata)) {', $lowered);
        $t->contains('const asset = __using(_stack2, _asset);', $lowered);
        $t->contains('registerAsset(asset.handle, asset.url);', $lowered);
        $t->contains('__callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(strpos($lowered, 'const asset = __using') < strpos($lowered, 'registerAsset(asset.handle, asset.url);'));
        $t->true(strpos($lowered, 'registerAsset(asset.handle, asset.url);') < strpos($lowered, '__callDispose(_stack2'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'satisfies'));
    },
];
