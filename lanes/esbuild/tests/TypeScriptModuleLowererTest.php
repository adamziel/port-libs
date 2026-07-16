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
    'rejects upstream switch case using declarations unless block wrapped' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('switch (x) { case 0: using y = z }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('switch (x) { default: await using y = z }'));
        $t->same(
            "switch (x) { case 0: { using y = z } }\n",
            $lowerer->lower('switch (x) { case 0: { using y = z } }')
        );
    },
    'lowers upstream switch case block using declarations through explicit resource helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            'async function f() { switch (x) { case 0: { using y: Disposable = z; work(y); } default: { await using q: AsyncDisposable = r; done(q); } } }',
            lowerUsingDeclarations: true
        );

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains('switch (x) { case 0: {', $lowered);
        $t->contains('const y = __using(_stack2, z);', $lowered);
        $t->contains('__callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('default: {', $lowered);
        $t->contains('const q = __using(_stack3, r, true);', $lowered);
        $t->contains('var _promise3 = __callDispose(_stack3, _error3, _hasError3);', $lowered);
        $t->contains('_promise3 && await _promise3;', $lowered);
        $t->true(strpos($lowered, 'const y = __using(_stack2, z);') < strpos($lowered, 'work(y);'));
        $t->true(strpos($lowered, 'work(y);') < strpos($lowered, '__callDispose(_stack2'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
    },
    'lowers upstream switch case for using headers through explicit resource helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            'switch (x) { case 0: for (using y: Disposable of z) body(y) default: for (await using q: AsyncDisposable of r) done(q) }',
            lowerUsingDeclarations: true
        );

        $t->contains('switch (x) { case 0: for (var _y of z) {', $lowered);
        $t->contains('const y = __using(_stack2, _y);', $lowered);
        $t->contains('body(y);', $lowered);
        $t->contains('__callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('default: for (var _q of r) {', $lowered);
        $t->contains('const q = __using(_stack3, _q, true);', $lowered);
        $t->contains('done(q);', $lowered);
        $t->contains('var _promise3 = __callDispose(_stack3, _error3, _hasError3);', $lowered);
        $t->contains('_promise3 && await _promise3;', $lowered);
        $t->true(strpos($lowered, 'const y = __using(_stack2, _y);') < strpos($lowered, 'body(y);'));
        $t->true(strpos($lowered, 'body(y);') < strpos($lowered, '__callDispose(_stack2'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
    },
    'rejects wordpress switch case disposable asset declarations without node' => static function (TestRunner $t): void {
        $source = <<<'TS'
switch (metadata.viewScript) {
  case "view":
    using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);
    queueAsset(previewAsset.url);
}
TS;

        $t->throws(InvalidArgumentException::class, static fn (): string => (new TypeScriptModuleLowerer())->lower($source));
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
    'keeps upstream exported class self references inside hoisted class expressions' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
using a: Disposable = b;
export class Foo {
  ac = [a, c]
}
export class Bar {
  ac = [a, c, Bar]
}
using c: Disposable = d;
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var Foo = class {', $lowered);
        $t->contains('ac = [a, c]', $lowered);
        $t->contains('var Bar = class _Bar {', $lowered);
        $t->contains('ac = [a, c, _Bar]', $lowered);
        $t->contains("export {\n  Foo\n};", $lowered);
        $t->contains("export {\n  Bar\n};", $lowered);
        $t->true(strpos($lowered, 'var Foo = class') < strpos($lowered, '__callDispose(_stack'));
        $t->true(strpos($lowered, 'var Bar = class _Bar') < strpos($lowered, '__callDispose(_stack'));
        $t->true(!str_contains($lowered, 'class Bar {'));
        $t->true(!str_contains($lowered, '[a, c, Bar]'));
    },
    'keeps upstream local classes inside top level using helper scopes as class expressions' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
using a: Disposable = b;
class Foo {
  ac = [a, c]
}
class Bar {
  ac = [a, c, Bar]
}
using c: Disposable = d;
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var Foo = class {', $lowered);
        $t->contains('ac = [a, c]', $lowered);
        $t->contains('var Bar = class _Bar {', $lowered);
        $t->contains('ac = [a, c, _Bar]', $lowered);
        $t->true(strpos($lowered, 'var Foo = class') < strpos($lowered, '__callDispose(_stack'));
        $t->true(strpos($lowered, 'var Bar = class _Bar') < strpos($lowered, '__callDispose(_stack'));
        $t->true(!str_contains($lowered, "try {\n  class Foo"));
        $t->true(!str_contains($lowered, "try {\n  class Bar"));
        $t->true(!str_contains($lowered, '[a, c, Bar]'));
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
    'keeps upstream default class and expression exports outside top level using helper scopes' => static function (TestRunner $t): void {
        $unusedClassLowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
using a: Disposable = b;
export default class Foo {
  ac = [a, c]
}
using c: Disposable = d;
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var Foo = class {', $unusedClassLowered);
        $t->contains('ac = [a, c]', $unusedClassLowered);
        $t->contains("export {\n  Foo as default\n};", $unusedClassLowered);
        $t->true(!str_contains($unusedClassLowered, 'class Foo {'));

        $usedClassLowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
using a: Disposable = b;
export default class Foo {
  ac = [a, c, Foo]
}
using c: Disposable = d;
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var Foo = class _Foo {', $usedClassLowered);
        $t->contains('ac = [a, c, _Foo]', $usedClassLowered);
        $t->contains("export {\n  Foo as default\n};", $usedClassLowered);
        $t->true(strpos($usedClassLowered, 'var Foo = class _Foo') < strpos($usedClassLowered, '__callDispose(_stack'));
        $t->true(strpos($usedClassLowered, '__callDispose(_stack') < strpos($usedClassLowered, "export {\n  Foo as default"));
        $t->true(!str_contains($usedClassLowered, "try {\n  export default class"));
        $t->true(!str_contains($usedClassLowered, '[a, c, Foo]'));

        $expressionLowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
const defaultExport = "occupied";
using a: Disposable = b;
export default [a, c]
using c: Disposable = d;
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var defaultExport2 = [a, c];', $expressionLowered);
        $t->contains("export {\n  defaultExport2 as default\n};", $expressionLowered);
        $t->true(strpos($expressionLowered, 'var defaultExport2 = [a, c];') < strpos($expressionLowered, '__callDispose(_stack'));
        $t->true(strpos($expressionLowered, '__callDispose(_stack') < strpos($expressionLowered, "export {\n  defaultExport2 as default"));
        $t->true(!str_contains($expressionLowered, "try {\n  export default"));
    },
    'renames upstream using helper symbols when source names collide' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
const __knownSymbol = "source-known";
const __typeError = "source-error";
const __using = "source-using";
const __callDispose = "source-dispose";
using asset: Disposable = acquire();
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var __knownSymbol2 = (name, symbol) =>', $lowered);
        $t->contains('var __typeError2 = (msg) => {', $lowered);
        $t->contains('var __using2 = (stack, value, async) => {', $lowered);
        $t->contains('var __callDispose2 = (stack, error, hasError) => {', $lowered);
        $t->contains('var asset = __using2(_stack, acquire());', $lowered);
        $t->contains('__callDispose2(_stack, _error, _hasError);', $lowered);
        $t->contains('value[__knownSymbol2("dispose")]', $lowered);
        $t->true(!str_contains($lowered, 'var __using = (stack, value, async) => {'));
        $t->true(!str_contains($lowered, '__using(_stack, acquire())'));
    },
    'renames upstream for using helper symbols when source names collide' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
const __knownSymbol = "source-known";
const __typeError = "source-error";
const __using = "source-using";
const __callDispose = "source-dispose";
for (using asset: Disposable of assets) {
  register(asset);
}
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var __using2 = (stack, value, async) => {', $lowered);
        $t->contains('var __callDispose2 = (stack, error, hasError) => {', $lowered);
        $t->contains('const asset = __using2(_stack2, _asset);', $lowered);
        $t->contains('__callDispose2(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('const __using = "source-using";', $lowered);
        $t->true(!str_contains($lowered, 'const asset = __using(_stack2, _asset);'));
        $t->true(!str_contains($lowered, 'var __using = (stack, value, async) => {'));
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
    'lowers upstream control flow block scoped using declarations through explicit resource helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
async function hydrateAssets(queue) {
  while (queue.hasMore()) {
    await using asset: AsyncDisposable = queue.openNext();
    register(asset);
  }
  for (const item of queue.items) {
    using preview: Disposable = item.preview();
    queuePreview(preview);
  }
  for await (const item of queue.remoteItems) {
    await using remote: AsyncDisposable = item.openRemote();
    queueRemote(remote);
  }
}
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains("while (queue.hasMore()) {\n    var _stack2 = [];", $lowered);
        $t->contains('const asset = __using(_stack2, queue.openNext(), true);', $lowered);
        $t->contains('var _promise2 = __callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('_promise2 && await _promise2;', $lowered);
        $t->contains("for (const item of queue.items) {\n    var _stack3 = [];", $lowered);
        $t->contains('const preview = __using(_stack3, item.preview());', $lowered);
        $t->contains('__callDispose(_stack3, _error3, _hasError3);', $lowered);
        $t->contains("for await (const item of queue.remoteItems) {\n    var _stack4 = [];", $lowered);
        $t->contains('const remote = __using(_stack4, item.openRemote(), true);', $lowered);
        $t->contains('var _promise4 = __callDispose(_stack4, _error4, _hasError4);', $lowered);
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, ': Disposable'));
    },
    'erases upstream class async generator method await using types' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  async *bar() {
    await using x = y;
    yield x;
  }
}
JS . "\n", $lowerer->lower('class Foo { async *bar(): AsyncGenerator<string> { await using x: AsyncDisposable = y; yield x; } }'));

        $t->throws(
            InvalidArgumentException::class,
            static fn (): string => $lowerer->lower('class Foo { bar() { await using x: AsyncDisposable = y; } }')
        );
    },
    'lowers upstream class async generator method await using cleanup' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
class Foo {
  async *bar(): AsyncGenerator<string> {
    await using x: AsyncDisposable = y;
    yield x;
    for await (await using asset: AsyncDisposable of assets) {
      yield asset;
    }
  }
}
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains('class Foo {', $lowered);
        $t->contains('async *bar() {', $lowered);
        $t->contains('const x = __using(_stack2, y, true);', $lowered);
        $t->contains('for await (var _asset of assets) {', $lowered);
        $t->contains('const asset = __using(_stack3, _asset, true);', $lowered);
        $t->contains('yield asset;', $lowered);
        $t->contains('var _promise3 = __callDispose(_stack3, _error3, _hasError3);', $lowered);
        $t->contains('var _promise2 = __callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('_promise2 && await _promise2;', $lowered);
        $t->true(strpos($lowered, 'const x = __using(_stack2') < strpos($lowered, 'yield x;'));
        $t->true(strpos($lowered, 'yield asset;') < strpos($lowered, 'var _promise3 = __callDispose'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'erases upstream object async generator method await using types' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();
        $lowered = $lowerer->lower(<<<'TS'
foo = { async *bar(queue): AsyncGenerator<string> {
  await using x: AsyncDisposable = await y;
  yield x;
} }
TS);

        $t->contains('foo = {async *bar(queue) {', $lowered);
        $t->contains('await using x = await y;', $lowered);
        $t->contains('yield x;', $lowered);
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): string => $lowerer->lower('foo = { *bar() { await using x: AsyncDisposable = y; } }')
        );
    },
    'lowers upstream object async generator method await using cleanup' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
foo = { async *bar(queue): AsyncGenerator<string> {
  await using x: AsyncDisposable = await y;
  yield x;
  for await (await using asset: AsyncDisposable of assets) {
    yield asset;
  }
} }
TS,
            lowerUsingDeclarations: true
        );

        $t->contains('var __using = (stack, value, async) => {', $lowered);
        $t->contains('foo = {async *bar(queue) {', $lowered);
        $t->contains('const x = __using(_stack2, await y, true);', $lowered);
        $t->contains('for await (var _asset of assets) {', $lowered);
        $t->contains('const asset = __using(_stack3, _asset, true);', $lowered);
        $t->contains('yield asset;', $lowered);
        $t->contains('var _promise3 = __callDispose(_stack3, _error3, _hasError3);', $lowered);
        $t->contains('var _promise2 = __callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('_promise2 && await _promise2;', $lowered);
        $t->true(strpos($lowered, 'const x = __using(_stack2') < strpos($lowered, 'yield x;'));
        $t->true(strpos($lowered, 'yield asset;') < strpos($lowered, 'var _promise3 = __callDispose'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'lowers upstream async generator functions through runtime helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
async function* foo(): AsyncGenerator<string> {
  yield;
  yield x;
  yield *x;
  await using x: AsyncDisposable = await y;
}
foo = async function* (): AsyncGenerator<string> {
  yield *assets;
}
TS,
            lowerUsingDeclarations: true,
            lowerAsyncGenerators: true
        );

        $t->contains('var __await = function(promise, isYieldStar) {', $lowered);
        $t->contains('var __asyncGenerator = (__this, __arguments, generator) => {', $lowered);
        $t->contains('var __yieldStar = (value) => {', $lowered);
        $t->contains('function foo() {', $lowered);
        $t->contains('return __asyncGenerator(this, null, function* () {', $lowered);
        $t->contains('yield;', $lowered);
        $t->contains('yield x;', $lowered);
        $t->contains('yield* __yieldStar(x);', $lowered);
        $t->contains('const x = __using(_stack2, yield new __await(y), true);', $lowered);
        $t->contains('_promise2 && (yield new __await(_promise2));', $lowered);
        $t->contains('foo = function() {', $lowered);
        $t->contains('yield* __yieldStar(assets);', $lowered);
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, 'await using'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'lowers upstream async generator await operands with nested commas' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
async function* foo(): AsyncGenerator<string> {
  const asset = await openAsset(queue, metadata, { kind: "view" });
  const fallback = await (prepare(metadata), openAsset(queue, "fallback"));
  yield await renderAsset(asset, fallback);
}
TS,
            lowerAsyncGenerators: true
        );

        $t->contains('const asset = yield new __await(openAsset(queue, metadata, {kind:"view"}));', $lowered);
        $t->contains('const fallback = yield new __await((prepare(metadata), openAsset(queue, "fallback")));', $lowered);
        $t->contains('yield yield new __await(renderAsset(asset, fallback));', $lowered);
        $t->true(!str_contains($lowered, 'yield new __await(openAsset(queue), metadata'));
        $t->true(!str_contains($lowered, 'const fallback = await('));
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'lowers upstream async generator await operands with unary and binary precedence' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
async function* foo(): AsyncGenerator<string> {
  const sum = await loadAsset() + metadata.version;
  const ready = await queue.ready && queue.hasMore();
  const choice = await getChoice() ? "primary" : "fallback";
  const grouped = await (asset.score + fallback.score);
  const negated = await !queue.paused;
}
TS,
            lowerAsyncGenerators: true
        );

        $t->contains('const sum = yield new __await(loadAsset())+metadata.version;', $lowered);
        $t->contains('const ready = yield new __await(queue.ready)&&queue.hasMore();', $lowered);
        $t->contains('const choice = yield new __await(getChoice())?"primary":"fallback";', $lowered);
        $t->contains('const grouped = yield new __await((asset.score+fallback.score));', $lowered);
        $t->contains('const negated = yield new __await(!queue.paused);', $lowered);
        $t->true(!str_contains($lowered, 'yield new __await(loadAsset()+metadata.version)'));
        $t->true(!str_contains($lowered, 'yield new __await(queue.ready&&queue.hasMore())'));
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'lowers upstream async generator await operands with optional chain and nullish precedence' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
async function* foo(): AsyncGenerator<string> {
  const resolved = await queue.resolveHandle?.(metadata.viewScript) ?? metadata.viewScript;
  const assetUrl = await queue.currentAsset?.url;
  const byName = await queue.assets?.[metadata.name];
  const fallback = await queue.resolveHandle?.(metadata.editorScript) || metadata.editorScript;
}
TS,
            lowerAsyncGenerators: true
        );

        $t->contains('const resolved = yield new __await(queue.resolveHandle?.(metadata.viewScript))??metadata.viewScript;', $lowered);
        $t->contains('const assetUrl = yield new __await(queue.currentAsset?.url);', $lowered);
        $t->contains('const byName = yield new __await(queue.assets?.[metadata.name]);', $lowered);
        $t->contains('const fallback = yield new __await(queue.resolveHandle?.(metadata.editorScript))||metadata.editorScript;', $lowered);
        $t->true(!str_contains($lowered, 'yield new __await(queue.resolveHandle?.(metadata.viewScript)??metadata.viewScript)'));
        $t->true(!str_contains($lowered, 'yield new __await(queue.resolveHandle?.(metadata.editorScript)||metadata.editorScript)'));
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'lowers upstream async generator for await bodies through runtime helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
async function* foo() {
  for await (let item of items) {
    yield item;
  }
  for await (await using asset: AsyncDisposable of assets) {
    yield asset;
  }
}
TS,
            lowerUsingDeclarations: true,
            lowerAsyncGenerators: true
        );

        $t->contains('var __forAwait = (obj, it, method) =>', $lowered);
        $t->contains('for (var iter = __forAwait(items), more, temp, error; more = !(temp = yield new __await(iter.next())).done; more = false) {', $lowered);
        $t->contains('let item = temp.value;', $lowered);
        $t->contains('yield item;', $lowered);
        $t->contains('more && (temp = iter.return) && (yield new __await(temp.call(iter)));', $lowered);
        $t->contains('for (var iter2 = __forAwait(assets), more2, temp2, error2; more2 = !(temp2 = yield new __await(iter2.next())).done; more2 = false) {', $lowered);
        $t->contains('var _asset = temp2.value;', $lowered);
        $t->contains('const asset = __using(_stack2, _asset, true);', $lowered);
        $t->contains('_promise2 && (yield new __await(_promise2));', $lowered);
        $t->true(!str_contains($lowered, 'for await'));
    },
    'renames upstream async generator helper symbols after prior statements' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
const __knownSymbol = symbols.known;
const __typeError = errors.type;
const __await = runtime.await;
const __asyncGenerator = runtime.asyncGenerator;
const __yieldStar = runtime.yieldStar;
const __forAwait = runtime.forAwait;
async function* stream(): AsyncGenerator<string> {
  yield await load();
  yield *extras;
  for await (let item of items) yield item;
}
TS,
            lowerAsyncGenerators: true
        );

        $t->contains('const __await = runtime.await;', $lowered);
        $t->contains('var __knownSymbol2 = (name, symbol) =>', $lowered);
        $t->contains('var __typeError2 = (msg) => {', $lowered);
        $t->contains('var __await2 = function(promise, isYieldStar) {', $lowered);
        $t->contains('var __asyncGenerator2 = (__this, __arguments, generator) => {', $lowered);
        $t->contains('var __yieldStar2 = (value) => {', $lowered);
        $t->contains('var __forAwait2 = (obj, it, method) =>', $lowered);
        $t->contains('function stream() {', $lowered);
        $t->contains('return __asyncGenerator2(this, null, function* () {', $lowered);
        $t->contains('yield yield new __await2(load());', $lowered);
        $t->contains('yield* __yieldStar2(extras);', $lowered);
        $t->contains('for (var iter = __forAwait2(items), more, temp, error; more = !(temp = yield new __await2(iter.next())).done; more = false) {', $lowered);
        $t->true(!str_contains($lowered, 'async function* stream'));
        $t->true(!str_contains($lowered, 'new __await(load())'));
        $t->true(!str_contains($lowered, 'return __asyncGenerator(this'));
    },
    'lowers upstream class and object async generator methods through runtime helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
class Foo {
  async *bar(): AsyncGenerator<string> {
    await using x: AsyncDisposable = await y;
    yield x;
  }
}
foo = { async *bar(queue): AsyncGenerator<string> {
  await using x: AsyncDisposable = await y;
  yield x;
} }
TS,
            lowerUsingDeclarations: true,
            lowerAsyncGenerators: true
        );

        $t->contains("class Foo {\n  bar() {\n    return __asyncGenerator(this, null, function* () {", $lowered);
        $t->contains('foo = {bar(queue) {', $lowered);
        $t->contains('return __asyncGenerator(this, null, function* () {', $lowered);
        $t->contains('const x = __using(_stack2, yield new __await(y), true);', $lowered);
        $t->contains('const x = __using(_stack3, yield new __await(y), true);', $lowered);
        $t->true(!str_contains($lowered, 'async *bar'));
        $t->true(!str_contains($lowered, 'await using'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'lowers upstream default export async generator expressions through runtime helpers' => static function (TestRunner $t): void {
        $direct = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
export default async function* stream(): AsyncGenerator<string> {
  yield *assets;
}
TS,
            lowerAsyncGenerators: true
        );

        $t->contains('export default function stream() {', $direct);
        $t->contains('return __asyncGenerator(this, null, function* () {', $direct);
        $t->contains('yield* __yieldStar(assets);', $direct);
        $t->true(!str_contains($direct, 'async function*'));
        $t->true(!str_contains($direct, ': AsyncGenerator'));

        $parenthesized = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
export default (async function* (): AsyncGenerator<string> {
  await using asset: AsyncDisposable = await open();
  yield asset;
  yield *extraAssets;
});
afterDefault();
TS,
            lowerUsingDeclarations: true,
            lowerAsyncGenerators: true
        );

        $t->contains('export default(function() {', $parenthesized);
        $t->contains('const asset = __using(_stack2, yield new __await(open()), true);', $parenthesized);
        $t->contains('yield asset;', $parenthesized);
        $t->contains('yield* __yieldStar(extraAssets);', $parenthesized);
        $t->contains('_promise2 && (yield new __await(_promise2));', $parenthesized);
        $t->contains("});\nafterDefault();", $parenthesized);
        $t->true(!str_contains($parenthesized, 'async function*'));
        $t->true(!str_contains($parenthesized, 'await using'));
        $t->true(!str_contains($parenthesized, ': AsyncGenerator'));
    },
    'lowers upstream parenthesized async generator expressions through runtime helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
const stream = (async function* (): AsyncGenerator<string> {
  await using asset: AsyncDisposable = await open();
  yield asset;
  yield *extraAssets;
});
consume(async function* (): AsyncGenerator<string> {
  yield *stream();
});
TS,
            lowerUsingDeclarations: true,
            lowerAsyncGenerators: true
        );

        $t->contains('const stream = (function() {', $lowered);
        $t->contains('const asset = __using(_stack2, yield new __await(open()), true);', $lowered);
        $t->contains('yield asset;', $lowered);
        $t->contains('yield* __yieldStar(extraAssets);', $lowered);
        $t->contains('_promise2 && (yield new __await(_promise2));', $lowered);
        $t->contains('consume(function() {', $lowered);
        $t->contains('yield* __yieldStar(stream());', $lowered);
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, 'await using'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'lowers upstream nested async generator expressions through runtime helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
const streams = [async function* (): AsyncGenerator<string> {
  yield *arrayAssets;
}];
consume(first, async function* (): AsyncGenerator<string> {
  yield *laterAssets;
});
TS,
            lowerAsyncGenerators: true
        );

        $t->contains('const streams = [function() {', $lowered);
        $t->contains('yield* __yieldStar(arrayAssets);', $lowered);
        $t->contains('consume(first, function() {', $lowered);
        $t->contains('yield* __yieldStar(laterAssets);', $lowered);
        $t->true(!str_contains($lowered, '[ function'));
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'lowers upstream object property async generator expressions through runtime helpers' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
const registry = {
  stream: async function* (): AsyncGenerator<string> {
    yield *objectAssets;
  },
};
const computedRegistry = {
  [streamName]: async function* (): AsyncGenerator<string> {
    yield *computedAssets;
  },
};
consume({ stream: async function* (): AsyncGenerator<string> {
  yield *argumentAssets;
} });
TS,
            lowerAsyncGenerators: true
        );

        $t->contains('const registry = {stream:function() {', $lowered);
        $t->contains('yield* __yieldStar(objectAssets);', $lowered);
        $t->contains('const computedRegistry = {[streamName]:function() {', $lowered);
        $t->contains('yield* __yieldStar(computedAssets);', $lowered);
        $t->contains('consume({stream:function() {', $lowered);
        $t->contains('yield* __yieldStar(argumentAssets);', $lowered);
        $t->true(!str_contains($lowered, ': async function'));
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
    },
    'lowers multiple upstream async generator expressions in one statement' => static function (TestRunner $t): void {
        $lowered = (new TypeScriptModuleLowerer())->lower(
            <<<'TS'
const streams = [
  async function* (): AsyncGenerator<string> {
    yield *firstAssets;
  },
  async function* (): AsyncGenerator<string> {
    await using asset: AsyncDisposable = await openSecond();
    yield asset;
    yield *secondAssets;
  },
];
consume(first, async function* (): AsyncGenerator<string> {
  yield *thirdAssets;
}, async function* (): AsyncGenerator<string> {
  yield *fourthAssets;
});
const registry = {
  first: async function* (): AsyncGenerator<string> {
    yield *objectOne;
  },
  second: async function* (): AsyncGenerator<string> {
    yield *objectTwo;
  },
};
TS,
            lowerUsingDeclarations: true,
            lowerAsyncGenerators: true
        );

        $t->same(6, substr_count($lowered, 'return __asyncGenerator(this, null, function* () {'));
        $t->contains('const streams = [function() {', $lowered);
        $t->contains('yield* __yieldStar(firstAssets);', $lowered);
        $t->contains('}, function() {', $lowered);
        $t->contains('const asset = __using(_stack2, yield new __await(openSecond()), true);', $lowered);
        $t->contains('yield* __yieldStar(secondAssets);', $lowered);
        $t->contains('consume(first, function() {', $lowered);
        $t->contains('yield* __yieldStar(thirdAssets);', $lowered);
        $t->contains('yield* __yieldStar(fourthAssets);', $lowered);
        $t->contains('const registry = {first:function() {', $lowered);
        $t->contains('yield* __yieldStar(objectOne);', $lowered);
        $t->contains('second:function() {', $lowered);
        $t->contains('yield* __yieldStar(objectTwo);', $lowered);
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, 'await using'));
        $t->true(!str_contains($lowered, ': AsyncGenerator'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
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
    'normalizes upstream plain typescript auto accessor fields' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  accessor;
}
JS . "\n", $lowerer->lower('class Foo { accessor }'));

        $t->same(<<<'JS'
class Foo {
  accessor x;
}
JS . "\n", $lowerer->lower('class Foo { accessor x }'));

        $t->same(<<<'JS'
class Foo {
  static accessor x;
}
JS . "\n", $lowerer->lower('class Foo { static accessor x }'));

        $t->same(<<<'JS'
class Foo {
  accessor [x];
}
JS . "\n", $lowerer->lower('class Foo { accessor [x] }'));

        $t->same(<<<'JS'
class Foo {
  accessor #x;
}
JS . "\n", $lowerer->lower('class Foo { accessor #x }'));

        $t->same(<<<'JS'
class Foo {
  accessor x = y;
}
JS . "\n", $lowerer->lower('class Foo { accessor x = y }'));
    },
    'erases upstream decorated typescript auto accessor types' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  @x accessor y;
}
JS . "\n", $lowerer->lower('class Foo { @x accessor y: any }'));

        $t->same(<<<'JS'
class Foo {
  @x static accessor y;
}
JS . "\n", $lowerer->lower('class Foo { @x static accessor y?: any }'));

        $t->same(<<<'JS'
class Foo {
  @x accessor #y;
}
JS . "\n", $lowerer->lower('class Foo { @x accessor #y: any }'));
    },
    'erases upstream typescript decorator type arguments on class members' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  @x y;
}
JS . "\n", $lowerer->lower('class Foo { @x<{}> y: any }'));

        $t->same(<<<'JS'
class Foo {
  @x() y;
}
JS . "\n", $lowerer->lower('class Foo { @x<{}>() y: any }'));

        $t->same(<<<'JS'
class Foo {
  @x @y z;
}
JS . "\n", $lowerer->lower('class Foo { @x<{}> @y<[], () => {}> z: any }'));

        $t->same(<<<'JS'
class Foo {
  @x() @y() z;
}
JS . "\n", $lowerer->lower('class Foo { @x<{}>() @y<[], () => {}>() z: any }'));

        $t->same(<<<'JS'
class Foo {
  @x.y z;
}
JS . "\n", $lowerer->lower('class Foo { @x<{}>.y<[], () => {}> z: any }'));

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { @x<{}>().y<[], () => {}>() z: any }'));
    },
    'rejects upstream invalid decorator boundaries' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('class Foo { x(@y z) {} }'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('@dec enum foo {}'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('@dec namespace foo {}'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('@dec function foo() {}'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('@x[y] class Foo {}'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('@x?.y() class Foo {}'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('@new Function() class Foo {}'));
    },
    'keeps upstream class decorators on lowered class statements' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
@x class Foo {
  y;
}
JS . "\n", $lowerer->lower('@x class Foo { y: any }'));

        $t->same(<<<'JS'
@x export class Foo {
  y;
}
JS . "\n", $lowerer->lower('export @x class Foo { y: any }'));

        $t->same(<<<'JS'
@blockController(metadata) class Foo {
}
Foo.settings = metadata;
JS . "\n", $lowerer->lower('@blockController<BlockConfiguration>(metadata) class Foo { static settings: BlockConfiguration = metadata }', false, targetYear: 2021));

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('@x export @y class Foo { y: any }'));
    },
    'lowers upstream class decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _Foo_decorators, _init;
_Foo_decorators = [dec];
class Foo {
}
_init = __decoratorStart(null);
Foo = __decorateElement(_init, 0, "Foo", _Foo_decorators, Foo);
__runInitializers(_init, 1, Foo);
JS . "\n", $lowerer->lower('@dec class Foo {}', lowerDecorators: true));

        $t->same(<<<'JS'
var _Foo_decorators, _init;
_Foo_decorators = [dec];
export class Foo {
}
_init = __decoratorStart(null);
Foo = __decorateElement(_init, 0, "Foo", _Foo_decorators, Foo);
__runInitializers(_init, 1, Foo);
JS . "\n", $lowerer->lower('export @dec class Foo {}', lowerDecorators: true));

        $t->same(<<<'JS'
var _class_decorators, _init;
_class_decorators = [dec];
export default class defaultExport {
}
_init = __decoratorStart(null);
defaultExport = __decorateElement(_init, 0, "", _class_decorators, defaultExport);
__runInitializers(_init, 1, defaultExport);
JS . "\n", $lowerer->lower('export default @dec class {}', lowerDecorators: true));

        $collision = $lowerer->lower(
            'const _init = runtime.init; @track<BlockConfiguration>(metadata) class Foo { y: any }',
            lowerDecorators: true
        );
        $t->contains('const _init = runtime.init;', $collision);
        $t->contains('var _Foo_decorators, _init2;', $collision);
        $t->contains('_Foo_decorators = [track(metadata)];', $collision);
        $t->contains('class Foo {', $collision);
        $t->contains('  y;', $collision);
        $t->contains('Foo = __decorateElement(_init2, 0, "Foo", _Foo_decorators, Foo);', $collision);
        $t->true(!str_contains($collision, '@track'));
        $t->true(!str_contains($collision, 'BlockConfiguration'));

        $mixed = $lowerer->lower('@dec class Foo { @field x }', lowerDecorators: true);
        $t->contains('Foo = __decorateElement(_init, 0, "Foo", _Foo_decorators, Foo);', $mixed);
        $t->contains('__decorateElement(_init, 5, "x", _x_dec, Foo);', $mixed);
    },
    'lowers upstream mixed class and member decorators through shared helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _Foo_decorators, _init;
_Foo_decorators = [classDec];
_x_dec = [methodDec];
class Foo {
  constructor() {
    __runInitializers(_init, 5, this);
  }
  x() {return y}
}
_init = __decoratorStart(null);
__decorateElement(_init, 1, "x", _x_dec, Foo);
Foo = __decorateElement(_init, 0, "Foo", _Foo_decorators, Foo);
__runInitializers(_init, 1, Foo);
JS . "\n", $lowerer->lower('@classDec class Foo { @methodDec x(): any { return y } }', lowerDecorators: true));

        $field = $lowerer->lower('@classDec class Foo { @fieldDec x: any = y }', lowerDecorators: true);
        $t->contains('_Foo_decorators = [classDec];', $field);
        $t->contains('_x_dec = [fieldDec];', $field);
        $t->contains('__publicField(this, "x", __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);', $field);
        $t->contains('__decorateElement(_init, 5, "x", _x_dec, Foo);', $field);
        $t->contains('Foo = __decorateElement(_init, 0, "Foo", _Foo_decorators, Foo);', $field);
        $t->true(strpos($field, '__decorateElement(_init, 5, "x"') < strpos($field, 'Foo = __decorateElement(_init, 0'));
        $t->true(!str_contains($field, '__decoratorMetadata(_init, Foo);'));

        $derived = $lowerer->lower('@classDec class Foo extends Bar { @methodDec x(): any { return y } }', lowerDecorators: true);
        $t->contains('class Foo extends (_a = Bar, _x_dec = [methodDec], _a) {', $derived);
        $t->contains('_init = __decoratorStart(_a);', $derived);
        $t->contains('__decorateElement(_init, 1, "x", _x_dec, Foo);', $derived);
        $t->contains('Foo = __decorateElement(_init, 0, "Foo", _Foo_decorators, Foo);', $derived);
    },
    'lowers upstream exported mixed class and member decorators through shared helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $exported = $lowerer->lower('export @classDec class Foo { @methodDec x(): any { return y } }', lowerDecorators: true);
        $t->contains('var _x_dec, _Foo_decorators, _init;', $exported);
        $t->contains('_Foo_decorators = [classDec];', $exported);
        $t->contains('_x_dec = [methodDec];', $exported);
        $t->contains('export class Foo {', $exported);
        $t->contains('__decorateElement(_init, 1, "x", _x_dec, Foo);', $exported);
        $t->contains('Foo = __decorateElement(_init, 0, "Foo", _Foo_decorators, Foo);', $exported);
        $t->contains('__runInitializers(_init, 1, Foo);', $exported);
        $t->true(strpos($exported, '__decorateElement(_init, 1, "x"') < strpos($exported, 'Foo = __decorateElement(_init, 0'));
        $t->true(!str_contains($exported, '__decoratorMetadata(_init, Foo);'));

        $default = $lowerer->lower('export default @classDec class { @methodDec x(): any { return y } }', lowerDecorators: true);
        $t->contains('var _x_dec, _class_decorators, _init;', $default);
        $t->contains('_class_decorators = [classDec];', $default);
        $t->contains('_x_dec = [methodDec];', $default);
        $t->contains('export default class defaultExport {', $default);
        $t->contains('__decorateElement(_init, 1, "x", _x_dec, defaultExport);', $default);
        $t->contains('defaultExport = __decorateElement(_init, 0, "", _class_decorators, defaultExport);', $default);
        $t->contains('__runInitializers(_init, 1, defaultExport);', $default);
        $t->true(strpos($default, '__decorateElement(_init, 1, "x"') < strpos($default, 'defaultExport = __decorateElement(_init, 0'));
        $t->true(!str_contains($default, '__decoratorMetadata(_init, defaultExport);'));
    },
    'lowers upstream instance method decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _init;
_x_dec = [dec];
class Foo {
  constructor() {
    __runInitializers(_init, 5, this);
  }
  x() {}
}
_init = __decoratorStart(null);
__decorateElement(_init, 1, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec x(): any {} }', lowerDecorators: true));

        $collision = $lowerer->lower(
            'const _init = runtime.init; class Foo { @track<BlockConfiguration>(metadata) register(): void {} }',
            lowerDecorators: true
        );
        $t->contains('const _init = runtime.init;', $collision);
        $t->contains('var _register_dec, _init2;', $collision);
        $t->contains('_register_dec = [track(metadata)];', $collision);
        $t->contains('__runInitializers(_init2, 5, this);', $collision);
        $t->contains('__decorateElement(_init2, 1, "register", _register_dec, Foo);', $collision);
        $t->true(!str_contains($collision, '@track'));
        $t->true(!str_contains($collision, 'BlockConfiguration'));

        $t->same(<<<'JS'
var _x_dec, _init;
_x_dec = [dec];
class Foo {
  constructor(value) {
    __runInitializers(_init, 5, this);
    this.value = value;
  }
  x() {}
}
_init = __decoratorStart(null);
__decorateElement(_init, 1, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { constructor(value: any) { this.value = value } @dec x(): any {} }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _init;
_x_dec = [dec];
class Foo {
  constructor(value) {
    this.value = value;
    __runInitializers(_init, 5, this);
    this.ready = true;
  }
  value;
  x() {}
}
_init = __decoratorStart(null);
__decorateElement(_init, 1, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { constructor(public value: any) { this.ready = true } @dec x(): any {} }', lowerDecorators: true));
    },
    'lowers upstream static method decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _init;
_x_dec = [dec];
class Foo {
  static x() {}
}
_init = __decoratorStart(null);
__decorateElement(_init, 9, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
__runInitializers(_init, 3, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec static x(): any {} }', lowerDecorators: true));

        $constructor = $lowerer->lower(
            'class Foo { constructor() { this.ready = true } @dec static register(): void {} }',
            lowerDecorators: true
        );
        $t->contains('constructor() {this.ready = true}', $constructor);
        $t->contains('__decorateElement(_init, 9, "register", _register_dec, Foo);', $constructor);
        $t->contains('__runInitializers(_init, 3, Foo);', $constructor);
        $t->true(!str_contains($constructor, '__runInitializers(_init, 5, this);'));
    },
    'lowers upstream public field decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _init;
_x_dec = [dec];
class Foo {
  constructor() {
    __publicField(this, "x", __runInitializers(_init, 8, this)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
__decorateElement(_init, 5, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec x }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _init;
_x_dec = [dec];
class Foo {
  constructor() {
    __publicField(this, "x", __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
__decorateElement(_init, 5, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec x: any = y }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _init;
_x_dec = [dec];
class Foo {
}
_init = __decoratorStart(null);
__decorateElement(_init, 13, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
__publicField(Foo, "x", __runInitializers(_init, 8, Foo, y)), __runInitializers(_init, 11, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec static x: any = y }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _init;
_x_dec = [dec];
class Foo {
  constructor() {
    __publicField(this, "x", __runInitializers(_init, 8, this)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
__decorateElement(_init, 5, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { constructor() {} @dec x }', lowerDecorators: true));
    },
    'lowers upstream private field decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _init, _x;
_x_dec = [dec];
class Foo {
  constructor() {
    __privateAdd(this, _x, __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
_x = new WeakMap();
__decorateElement(_init, 21, "#x", _x_dec, _x);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec #x: any = y }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _init, _x;
_x_dec = [dec];
class Foo {
}
_init = __decoratorStart(null);
_x = new WeakMap();
__decorateElement(_init, 29, "#x", _x_dec, _x);
__decoratorMetadata(_init, Foo);
__privateAdd(Foo, _x, __runInitializers(_init, 8, Foo, y)), __runInitializers(_init, 11, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec static #x: any = y }', lowerDecorators: true));

        $derived = $lowerer->lower('class Foo extends Bar { @dec #x: any = y }', lowerDecorators: true);
        $t->contains('class Foo extends (_a = Bar, _x_dec = [dec], _a) {', $derived);
        $t->contains('__privateAdd(this, _x, __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);', $derived);
        $t->contains('__decorateElement(_init, 21, "#x", _x_dec, _x);', $derived);
        $t->contains('_init = __decoratorStart(_a);', $derived);
    },
    'lowers upstream private auto accessor decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _init, _x, _a, x_get, x_set, _x_instances;
_x_dec = [dec];
class Foo {
  constructor() {
    __privateAdd(this, _x_instances);
    __privateAdd(this, _x, __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
_x = new WeakMap();
_x_instances = new WeakSet();
_a = __decorateElement(_init, 20, "#x", _x_dec, _x_instances, _x), x_get = _a.get, x_set = _a.set;
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec accessor #x: any = y }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _init, _x, _a, x_get, x_set, _x_static;
_x_dec = [dec];
class Foo {
}
_init = __decoratorStart(null);
_x = new WeakMap();
_x_static = new WeakSet();
_a = __decorateElement(_init, 28, "#x", _x_dec, _x_static, _x), x_get = _a.get, x_set = _a.set;
__privateAdd(Foo, _x_static);
__decoratorMetadata(_init, Foo);
__privateAdd(Foo, _x, __runInitializers(_init, 8, Foo, y)), __runInitializers(_init, 11, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec static accessor #x: any = y }', lowerDecorators: true));

        $derived = $lowerer->lower('class Foo extends Bar { @dec accessor #x: any = y }', lowerDecorators: true);
        $t->contains('class Foo extends (_a2 = Bar, _x_dec = [dec], _a2) {', $derived);
        $t->contains('__privateAdd(this, _x_instances);', $derived);
        $t->contains('__privateAdd(this, _x, __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);', $derived);
        $t->contains('_a = __decorateElement(_init, 20, "#x", _x_dec, _x_instances, _x), x_get = _a.get, x_set = _a.set;', $derived);
        $t->contains('_init = __decoratorStart(_a2);', $derived);
    },
    'lowers upstream auto accessor decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _init, _x;
_x_dec = [dec];
class Foo {
  constructor() {
    __privateAdd(this, _x, __runInitializers(_init, 8, this)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
_x = new WeakMap();
__decorateElement(_init, 4, "x", _x_dec, Foo, _x);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec accessor x }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _init, _x;
_x_dec = [dec];
class Foo {
  constructor() {
    __privateAdd(this, _x, __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
_x = new WeakMap();
__decorateElement(_init, 4, "x", _x_dec, Foo, _x);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec accessor x: any = y }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _init, _x;
_x_dec = [dec];
class Foo {
}
_init = __decoratorStart(null);
_x = new WeakMap();
__decorateElement(_init, 12, "x", _x_dec, Foo, _x);
__decoratorMetadata(_init, Foo);
__privateAdd(Foo, _x, __runInitializers(_init, 8, Foo, y)), __runInitializers(_init, 11, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec static accessor x: any = y }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _init, _x;
_x_dec = [dec];
class Foo {
  constructor() {
    __privateAdd(this, _x, __runInitializers(_init, 8, this)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
_x = new WeakMap();
__decorateElement(_init, 4, "x", _x_dec, Foo, _x);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { constructor() {} @dec accessor x }', lowerDecorators: true));
    },
    'lowers upstream computed field and accessor decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _a, _init;
_a = (_x_dec = [dec], x);
class Foo {
  constructor() {
    __publicField(this, _a, __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
__decorateElement(_init, 5, _a, _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec [x]: any = y }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _a, _init;
_a = (_x_dec = [dec], x);
class Foo {
}
_init = __decoratorStart(null);
__decorateElement(_init, 13, _a, _x_dec, Foo);
__decoratorMetadata(_init, Foo);
__publicField(Foo, _a, __runInitializers(_init, 8, Foo, y)), __runInitializers(_init, 11, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec static [x]: any = y }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _a, _init, __a;
_a = (_x_dec = [dec], x);
class Foo {
  constructor() {
    __privateAdd(this, __a, __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);
  }
}
_init = __decoratorStart(null);
__a = new WeakMap();
__decorateElement(_init, 4, _a, _x_dec, Foo, __a);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec accessor [x]: any = y }', lowerDecorators: true));
    },
    'lowers upstream computed method decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _a, _init;
class Foo {
  constructor() {
    __runInitializers(_init, 5, this);
  }
  [_a = (_x_dec = [dec], x)]() {}
}
_init = __decoratorStart(null);
__decorateElement(_init, 1, _a, _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec [x](): any {} }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _a, _init;
class Foo {
  static [_a = (_x_dec = [dec], x)]() {}
}
_init = __decoratorStart(null);
__decorateElement(_init, 9, _a, _x_dec, Foo);
__decoratorMetadata(_init, Foo);
__runInitializers(_init, 3, Foo);
JS . "\n", $lowerer->lower('class Foo { @dec static [x](): any {} }', lowerDecorators: true));

        $derived = $lowerer->lower('class Foo extends Bar { @dec [x](): any {} }', lowerDecorators: true);
        $t->contains('class Foo extends (_a2 = Bar, _a2) {', $derived);
        $t->contains('[_a = (_x_dec = [dec], x)]() {}', $derived);
        $t->contains('__runInitializers(_init, 5, this);', $derived);
        $t->contains('_init = __decoratorStart(_a2);', $derived);
        $t->contains('__decorateElement(_init, 1, _a, _x_dec, Foo);', $derived);
    },
    'lowers upstream getter and setter decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $getter = $lowerer->lower('class Foo { @dec get x(): any { return y } }', lowerDecorators: true);
        $t->contains('_x_dec = [dec];', $getter);
        $t->contains('constructor() {', $getter);
        $t->contains('__runInitializers(_init, 5, this);', $getter);
        $t->contains('get x() {return y}', $getter);
        $t->contains('__decorateElement(_init, 2, "x", _x_dec, Foo);', $getter);
        $t->contains('__decoratorMetadata(_init, Foo);', $getter);

        $setter = $lowerer->lower('class Foo { @dec set x(value: any) { this.value = value } }', lowerDecorators: true);
        $t->contains('set x(value) {this.value = value}', $setter);
        $t->contains('__decorateElement(_init, 3, "x", _x_dec, Foo);', $setter);

        $staticGetter = $lowerer->lower('class Foo { @dec static get x(): any { return y } }', lowerDecorators: true);
        $t->contains('static get x() {return y}', $staticGetter);
        $t->contains('__decorateElement(_init, 10, "x", _x_dec, Foo);', $staticGetter);
        $t->contains('__runInitializers(_init, 3, Foo);', $staticGetter);
        $t->true(!str_contains($staticGetter, '__runInitializers(_init, 5, this);'));
    },
    'lowers upstream computed getter and setter decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $getter = $lowerer->lower('class Foo { @dec get [x](): any { return y } }', lowerDecorators: true);
        $t->contains('var _x_dec, _a, _init;', $getter);
        $t->contains('get [_a = (_x_dec = [dec], x)]() {return y}', $getter);
        $t->contains('__decorateElement(_init, 2, _a, _x_dec, Foo);', $getter);

        $setter = $lowerer->lower('class Foo { @dec static set [x](value: any) { this.value = value } }', lowerDecorators: true);
        $t->contains('static set [_a = (_x_dec = [dec], x)](value) {this.value = value}', $setter);
        $t->contains('__decorateElement(_init, 11, _a, _x_dec, Foo);', $setter);
        $t->contains('__runInitializers(_init, 3, Foo);', $setter);

        $derived = $lowerer->lower('class Foo extends Bar { @dec get [x](): any { return y } }', lowerDecorators: true);
        $t->contains('class Foo extends (_a2 = Bar, _a2) {', $derived);
        $t->contains('get [_a = (_x_dec = [dec], x)]() {return y}', $derived);
        $t->contains('_init = __decoratorStart(_a2);', $derived);
        $t->contains('__decorateElement(_init, 2, _a, _x_dec, Foo);', $derived);
    },
    'lowers upstream private getter and setter decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $getter = $lowerer->lower('class Foo { @dec get #x(): any { return y } }', lowerDecorators: true);
        $t->contains('var _x_dec, _init, _x_instances, x_get;', $getter);
        $t->contains('__runInitializers(_init, 5, this);', $getter);
        $t->contains('__privateAdd(this, _x_instances);', $getter);
        $t->contains('_x_instances = new WeakSet();', $getter);
        $t->contains('x_get = function() {return y};', $getter);
        $t->contains('x_get = __decorateElement(_init, 18, "#x", _x_dec, _x_instances, x_get);', $getter);

        $setter = $lowerer->lower('class Foo { @dec static set #x(value: any) { this.value = value } }', lowerDecorators: true);
        $t->contains('var _x_dec, _init, _x_static, x_set;', $setter);
        $t->contains('x_set = function(value) {this.value = value};', $setter);
        $t->contains('x_set = __decorateElement(_init, 27, "#x", _x_dec, _x_static, x_set);', $setter);
        $t->contains('__privateAdd(Foo, _x_static);', $setter);
        $t->contains('__runInitializers(_init, 3, Foo);', $setter);

        $derived = $lowerer->lower('class Foo extends Bar { @dec get #x(): any { return y } }', lowerDecorators: true);
        $t->contains('class Foo extends (_a = Bar, _x_dec = [dec], _a) {', $derived);
        $t->contains('__privateAdd(this, _x_instances);', $derived);
        $t->contains('_init = __decoratorStart(_a);', $derived);
        $t->contains('x_get = __decorateElement(_init, 18, "#x", _x_dec, _x_instances, x_get);', $derived);
    },
    'lowers upstream derived member decorators through helper calls' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _x_dec, _a, _init;
class Foo extends (_a = Bar, _x_dec = [dec], _a) {
  constructor() {
    super(...arguments);
    __runInitializers(_init, 5, this);
  }
  x() {}
}
_init = __decoratorStart(_a);
__decorateElement(_init, 1, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo extends Bar { @dec x(): any {} }', lowerDecorators: true));

        $t->same(<<<'JS'
var _x_dec, _a, _init;
class Foo extends (_a = Bar, _x_dec = [dec], _a) {
  constructor(value) {
    foo();
    super(value);
    __runInitializers(_init, 5, this);
    bar();
  }
  x() {}
}
_init = __decoratorStart(_a);
__decorateElement(_init, 1, "x", _x_dec, Foo);
__decoratorMetadata(_init, Foo);
JS . "\n", $lowerer->lower('class Foo extends Bar { constructor(value) { foo(); super(value); bar(); } @dec x(): any {} }', lowerDecorators: true));

        $conditional = $lowerer->lower(
            'class Foo extends Bar { constructor(value) { if (value) super(1); else super(2); } @dec x(): any {} }',
            lowerDecorators: true
        );
        $t->contains('var __super = (...args) => {', $conditional);
        $t->contains('super(...args);', $conditional);
        $t->contains('__runInitializers(_init, 5, this);', $conditional);
        $t->contains('if (value) __super(1);', $conditional);
        $t->contains('else __super(2);', $conditional);

        $field = $lowerer->lower('class Foo extends Bar { @dec x: any = y }', lowerDecorators: true);
        $t->contains('class Foo extends (_a = Bar, _x_dec = [dec], _a) {', $field);
        $t->contains('__publicField(this, "x", __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);', $field);
        $t->contains('_init = __decoratorStart(_a);', $field);

        $accessor = $lowerer->lower('class Foo extends Bar { @dec accessor x: any = y }', lowerDecorators: true);
        $t->contains('var _x_dec, _a, _init, _x;', $accessor);
        $t->contains('_x = new WeakMap();', $accessor);
        $t->contains('__privateAdd(this, _x, __runInitializers(_init, 8, this, y)), __runInitializers(_init, 11, this);', $accessor);
        $t->contains('__decorateElement(_init, 4, "x", _x_dec, Foo, _x);', $accessor);
    },
    'rejects wordpress block decorators on non-class declarations without node' => static function (TestRunner $t): void {
        $source = <<<'TS'
@blockController<BlockConfiguration>(metadata)
function registerBlock() {
  wp.blocks.registerBlockType(metadata.name, metadata);
}
TS;

        $t->throws(InvalidArgumentException::class, static fn (): string => (new TypeScriptModuleLowerer())->lower($source));
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
    'lowers upstream static class fields after class for es2021 targets' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
}
Foo.foo = 0;
JS . "\n", $lowerer->lower('class Foo { static foo: number = 0 }', false, targetYear: 2021));

        $t->same(<<<'JS'
var _a;
_a = x();
class Foo {
}
Foo[_a] = 1;
JS . "\n", $lowerer->lower('class Foo { static [x()] = 1 }', false, targetYear: 2021));

        $t->same(<<<'JS'
var _a, _b, _c;
class Foo {
  constructor() {
    this[_a] = 1;
  }
  [a()]() {}
  [(b(), _a = c(), d())]() {}
  static [(e(), _b = f(), _c = g(), h(), _c)]() {}
}
Foo[_b] = 1;
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
TS, false, targetYear: 2021));
    },
    'lowers upstream default static class fields after class for es2021 targets' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
}
Foo.foo = 1;
export {
  Foo as default
};
JS . "\n", $lowerer->lower('export default class Foo { static foo: number = 1 }', false, targetYear: 2021));

        $t->same(<<<'JS'
var _a;
_a = x();
class defaultExport {
}
defaultExport[_a] = 1;
export {
  defaultExport as default
};
JS . "\n", $lowerer->lower('export default class { static [x()] = 1 }', false, targetYear: 2021));

        $t->same(<<<'JS'
const defaultExport = 0;
class defaultExport2 {
}
defaultExport2.foo = 1;
export {
  defaultExport2 as default
};
JS . "\n", $lowerer->lower('const defaultExport = 0; export default class { static foo = 1 }', false, targetYear: 2021));
    },
    'lowers upstream class expression static fields after class for es2021 targets' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _a;
const Foo = (_a = class {
}, _a.foo = 1, _a);
JS . "\n", $lowerer->lower('const Foo = class { static foo: number = 1 }', false, targetYear: 2021));

        $t->same(<<<'JS'
var _a, _b;
const Foo = (_a = x(), _b = class Bar {
}, _b.foo = 1, _b[_a] = 2, _b);
JS . "\n", $lowerer->lower('const Foo = class Bar { static foo: number = 1; static [x()] = 2 }', false, targetYear: 2021));

        $t->same(<<<'JS'
var _a;
foo((_a = class {
}, _a.x = 1, _a));
JS . "\n", $lowerer->lower('foo(class { static x: number = 1 })', false, targetYear: 2021));
    },
    'keeps upstream decorators on lowered class expressions' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
_ = @x @y class {
  foo;
};
JS . "\n", $lowerer->lower('_ = @x @y class { foo: any }'));

        $t->same(<<<'JS'
export default(@x class Foo {
  foo;
});
JS . "\n", $lowerer->lower('export default (@x class Foo { foo: any })'));
    },
    'lowers upstream decorated class expression static fields after class for es2021 targets' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _a;
const Foo = (_a = @x class {
}, _a.foo = 1, _a);
JS . "\n", $lowerer->lower('const Foo = @x class { static foo: number = 1 }', false, targetYear: 2021));

        $t->same(<<<'JS'
var _a;
export default((_a = @x class Foo {
}, _a.foo = 1, _a));
JS . "\n", $lowerer->lower('export default (@x class Foo { static foo: number = 1 })', false, targetYear: 2021));
    },
    'lowers upstream class expression field key ordering in assign semantics mode' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
var _a;
const Foo = (_a = x(), class {
  constructor() {
    this[_a] = 1;
  }
});
JS . "\n", $lowerer->lower('const Foo = class { [x()] = 1 }', false, targetYear: 2021));

        $t->same(<<<'JS'
var _a, _b;
const Foo = class extends (_b = Base, _a = x(), _b) {
  constructor() {
    super(...arguments);
    this[_a] = 1;
  }
};
JS . "\n", $lowerer->lower('const Foo = class extends Base { [x()] = 1 }', false, targetYear: 2021));
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
    'keeps upstream private static fields inside assign semantics classes' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  static #foo = 1;
  static #bar;
}
JS . "\n", $lowerer->lower('class Foo { static #foo: number = 1; static #bar: number }', false));

        $t->same(<<<'JS'
class Foo {
  static #foo = 1;
}
Foo.bar = 2;
JS . "\n", $lowerer->lower('class Foo { static #foo: number = 1; static bar: number = 2 }', false, targetYear: 2021));

        $legacy = $lowerer->lower('class Foo { static #foo: number = 1; static bar: number = 2 }', false, targetYear: 2021);
        $t->true(!str_contains($legacy, 'Foo.#foo'));
        $t->true(strpos($legacy, 'static #foo = 1;') < strpos($legacy, 'Foo.bar = 2;'));
    },
    'erases upstream private method and accessor type annotations' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptModuleLowerer();

        $t->same(<<<'JS'
class Foo {
  get #foo() {}
  set #foo(x) {x}
  static get #bar() {}
  static set #bar(x) {x}
  #baz(x) {return x}
}
JS . "\n", $lowerer->lower('class Foo { get #foo(): any {} set #foo(x: any) {x} static get #bar(): any {} static set #bar(x: any) {x} #baz(x: any): any { return x } }'));

        $t->same(<<<'JS'
class Foo {
  get [foo]() {}
  set [foo](value) {this.value = value}
}
JS . "\n", $lowerer->lower('class Foo { get [foo](): any {} set [foo](value: any) { this.value = value } }'));
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

        $legacyLowered = (new TypeScriptModuleLowerer())->lower($source, false, targetYear: 2021);
        $t->contains('CardBlockController.metadata = metadata;', $legacyLowered);
        $t->true(!str_contains($legacyLowered, 'static {'));
    },
    'keeps wordpress private static block settings inside legacy target classes without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-private-static-cache.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false, targetYear: 2021);

        $t->contains('static #settings = {supports:{html:false}};', $lowered);
        $t->contains('CachedBlockController.metadata = metadata;', $lowered);
        $t->contains('CachedBlockController.#settings', $lowered);
        $t->true(!str_contains($lowered, 'CachedBlockController.#settings ='));
        $t->true(strpos($lowered, 'static #settings =') < strpos($lowered, 'CachedBlockController.metadata ='));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress anonymous default static controller fields without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-default-static-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false, targetYear: 2021);

        $t->contains('var _a;', $lowered);
        $t->contains('_a = assetKey("worker");', $lowered);
        $t->contains('class defaultExport {', $lowered);
        $t->contains('defaultExport.settings = {supports:{html:false}, viewScript:"file:./view.js",};', $lowered);
        $t->contains('defaultExport[_a] = "file:./card-worker.js";', $lowered);
        $t->contains("export {\n  defaultExport as default\n};", $lowered);
        $t->true(strpos($lowered, 'class defaultExport') < strpos($lowered, 'defaultExport.settings ='));
        $t->true(strpos($lowered, 'defaultExport[_a]') < strpos($lowered, "export {\n  defaultExport as default"));
        $t->true(!str_contains($lowered, 'export default class'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress class expression controller static fields without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-class-expression-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false, targetYear: 2021);

        $t->contains('var _a, _b, _c;', $lowered);
        $t->contains('const Controller = (_c = class extends (_b = BaseController, _a = assetKey("worker"), _b) {', $lowered);
        $t->contains('super(metadata);', $lowered);
        $t->contains('this.blockName = metadata.name;', $lowered);
        $t->contains('Controller.settings', $lowered);
        $t->contains('_c.settings = {supports:{html:false}, viewScript:"file:./view.js",}', $lowered);
        $t->contains('_c[_a] = "file:./card-worker.js"', $lowered);
        $t->contains('export { Controller as CardBlockController };', $lowered);
        $t->true(strpos($lowered, '_b = BaseController') < strpos($lowered, '_a = assetKey("worker")'));
        $t->true(strpos($lowered, 'super(metadata);') < strpos($lowered, 'this.blockName = metadata.name;'));
        $t->true(strpos($lowered, '_c[_a]') < strpos($lowered, 'export { Controller as CardBlockController };'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, 'static {'));
    },
    'lowers wordpress decorated class expression controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-decorated-class-expression-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false, targetYear: 2021);

        $t->contains('export const DecoratedController = (_a = assetKey("worker"), _b = @blockController(metadata) class {', $lowered);
        $t->contains('_b.settings = {supports:{html:false}, viewScript:"file:./view.js",}', $lowered);
        $t->contains('_b[_a] = "file:./card-worker.js"', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, DecoratedController.settings);', $lowered);
        $t->true(strpos($lowered, '@blockController(metadata) class') < strpos($lowered, '_b.settings ='));
        $t->true(!str_contains($lowered, '@blockController<BlockConfiguration>'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, 'static {'));
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
    'lowers wordpress private accessor block settings without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-private-accessor-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false);

        $t->contains('class PrivateAccessorBlockController {', $lowered);
        $t->contains('#settings;', $lowered);
        $t->contains('get #blockSettings() {return this.#settings;}', $lowered);
        $t->contains('set #blockSettings(value) {this.#settings = value;}', $lowered);
        $t->contains('this.#blockSettings = {supports:{html:false}, viewScript:"file:./view.js",};', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, this.#blockSettings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress auto accessor controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-auto-accessor-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source);

        $t->contains('class CardBlockAccessorController {', $lowered);
        $t->contains('const trackAccessor = () => (value) => value;', $lowered);
        $t->contains('static accessor controllerVersion = "1";', $lowered);
        $t->contains('accessor assetHandle = metadata.name;', $lowered);
        $t->contains('@trackAccessor() accessor settings = {supports:{html:false}, viewScript:"file:./view.js",};', $lowered);
        $t->contains('accessor [assetKey("worker")] = "file:./card-worker.js";', $lowered);
        $t->contains('accessor #blockName = metadata.name;', $lowered);
        $t->contains('wp.blocks.registerBlockType(this.#blockName, this.settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, '?:'));
        $t->true(!str_contains($lowered, '!:'));
    },
    'lowers wordpress decorated block controller without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-decorated-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, false, targetYear: 2021);

        $t->contains('@blockController(metadata) class DecoratedBlockController {', $lowered);
        $t->contains('DecoratedBlockController.settings = {supports:{html:false}, viewScript:"file:./view.js",};', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, DecoratedBlockController.settings);', $lowered);
        $t->true(strpos($lowered, '@blockController(metadata) class DecoratedBlockController') < strpos($lowered, 'DecoratedBlockController.settings ='));
        $t->true(!str_contains($lowered, '@blockController<BlockConfiguration>'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, 'static {'));
    },
    'lowers wordpress block controller decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_DecoratedRegistration_decorators = [blockController(metadata)];', $lowered);
        $t->contains('class DecoratedRegistration {', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, metadata);', $lowered);
        $t->contains('DecoratedRegistration = __decorateElement(_init, 0, "DecoratedRegistration", _DecoratedRegistration_decorators, DecoratedRegistration);', $lowered);
        $t->contains('__runInitializers(_init, 1, DecoratedRegistration);', $lowered);
        $t->contains('new DecoratedRegistration().register();', $lowered);
        $t->true(!str_contains($lowered, '@blockController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress default block controller decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-default-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_class_decorators = [blockController(metadata)];', $lowered);
        $t->contains('export default class defaultExport {', $lowered);
        $t->contains('static settings = {supports:{html:false}, viewScript:"file:./view.js",};', $lowered);
        $t->contains('blocks.registerBlockType(metadata.name, metadata);', $lowered);
        $t->contains('defaultExport = __decorateElement(_init, 0, "", _class_decorators, defaultExport);', $lowered);
        $t->contains('__runInitializers(_init, 1, defaultExport);', $lowered);
        $t->true(strpos($lowered, 'export default class defaultExport') < strpos($lowered, 'defaultExport = __decorateElement'));
        $t->true(!str_contains($lowered, '@blockController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress method decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-method-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_register_dec = [methodController(metadata)];', $lowered);
        $t->contains('class MethodDecoratedRegistration {', $lowered);
        $t->contains('__runInitializers(_init, 5, this);', $lowered);
        $t->contains('register(blocks = wp.blocks) {', $lowered);
        $t->contains('blocks.registerBlockType(metadata.name, metadata);', $lowered);
        $t->contains('__decorateElement(_init, 1, "register", _register_dec, MethodDecoratedRegistration);', $lowered);
        $t->contains('__decoratorMetadata(_init, MethodDecoratedRegistration);', $lowered);
        $t->true(strpos($lowered, '_register_dec = [methodController(metadata)];') < strpos($lowered, 'class MethodDecoratedRegistration'));
        $t->true(!str_contains($lowered, '@methodController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress method decorators into existing constructors without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-method-decorator-constructor-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_register_dec = [methodController(metadata)];', $lowered);
        $t->contains('class ConstructorDecoratedRegistration {', $lowered);
        $t->contains('constructor(blocks = wp.blocks) {', $lowered);
        $t->contains('__runInitializers(_init, 5, this);', $lowered);
        $t->contains('this.blocks = blocks;', $lowered);
        $t->contains('register(settings = metadata) {this.blocks.registerBlockType(metadata.name, settings);}', $lowered);
        $t->contains('__decorateElement(_init, 1, "register", _register_dec, ConstructorDecoratedRegistration);', $lowered);
        $t->true(strpos($lowered, '__runInitializers(_init, 5, this);') < strpos($lowered, 'this.blocks = blocks;'));
        $t->true(!str_contains($lowered, '@methodController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress derived method decorators after super without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-derived-method-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('class BaseBlockController {', $lowered);
        $t->contains('class DerivedDecoratedRegistration extends (_a = BaseBlockController, _register_dec = [methodController(metadata)], _a) {', $lowered);
        $t->contains('constructor(blocks = wp.blocks) {', $lowered);
        $t->contains('super(metadata);', $lowered);
        $t->contains('__runInitializers(_init, 5, this);', $lowered);
        $t->contains('this.blocks = blocks;', $lowered);
        $t->contains('register(settings = metadata) {this.blocks.registerBlockType(metadata.name, settings);}', $lowered);
        $t->contains('_init = __decoratorStart(_a);', $lowered);
        $t->contains('__decorateElement(_init, 1, "register", _register_dec, DerivedDecoratedRegistration);', $lowered);
        $t->true(strpos($lowered, 'super(metadata);') < strpos($lowered, '__runInitializers(_init, 5, this);'));
        $t->true(strpos($lowered, '__runInitializers(_init, 5, this);') < strpos($lowered, 'this.blocks = blocks;'));
        $t->true(!str_contains($lowered, '@methodController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress mixed class and member decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-mixed-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_MixedDecoratedRegistration_decorators = [blockController(metadata)];', $lowered);
        $t->contains('_register_dec = [methodController(metadata)];', $lowered);
        $t->contains('class MixedDecoratedRegistration {', $lowered);
        $t->contains('__runInitializers(_init, 5, this);', $lowered);
        $t->contains('register(blocks = wp.blocks, settings = metadata) {blocks.registerBlockType(metadata.name, settings);}', $lowered);
        $t->contains('__decorateElement(_init, 1, "register", _register_dec, MixedDecoratedRegistration);', $lowered);
        $t->contains('MixedDecoratedRegistration = __decorateElement(_init, 0, "MixedDecoratedRegistration", _MixedDecoratedRegistration_decorators, MixedDecoratedRegistration);', $lowered);
        $t->contains('__runInitializers(_init, 1, MixedDecoratedRegistration);', $lowered);
        $t->true(strpos($lowered, '__decorateElement(_init, 1, "register"') < strpos($lowered, 'MixedDecoratedRegistration = __decorateElement(_init, 0'));
        $t->true(!str_contains($lowered, '__decoratorMetadata(_init, MixedDecoratedRegistration);'));
        $t->true(!str_contains($lowered, '@blockController'));
        $t->true(!str_contains($lowered, '@methodController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress default mixed class and member decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-default-mixed-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_class_decorators = [blockController(metadata)];', $lowered);
        $t->contains('_register_dec = [methodController(metadata)];', $lowered);
        $t->contains('export default class defaultExport {', $lowered);
        $t->contains('__runInitializers(_init, 5, this);', $lowered);
        $t->contains('register(blocks = wp.blocks, settings = metadata) {blocks.registerBlockType(metadata.name, settings);}', $lowered);
        $t->contains('__decorateElement(_init, 1, "register", _register_dec, defaultExport);', $lowered);
        $t->contains('defaultExport = __decorateElement(_init, 0, "", _class_decorators, defaultExport);', $lowered);
        $t->contains('__runInitializers(_init, 1, defaultExport);', $lowered);
        $t->true(strpos($lowered, '__decorateElement(_init, 1, "register"') < strpos($lowered, 'defaultExport = __decorateElement(_init, 0'));
        $t->true(!str_contains($lowered, '__decoratorMetadata(_init, defaultExport);'));
        $t->true(!str_contains($lowered, '@blockController'));
        $t->true(!str_contains($lowered, '@methodController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress static method decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-static-method-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_register_dec = [methodController(metadata)];', $lowered);
        $t->contains('class StaticMethodDecoratedRegistration {', $lowered);
        $t->contains('static register(blocks = wp.blocks) {', $lowered);
        $t->contains('blocks.registerBlockType(metadata.name, metadata);', $lowered);
        $t->contains('__decorateElement(_init, 9, "register", _register_dec, StaticMethodDecoratedRegistration);', $lowered);
        $t->contains('__runInitializers(_init, 3, StaticMethodDecoratedRegistration);', $lowered);
        $t->contains('StaticMethodDecoratedRegistration.register();', $lowered);
        $t->true(!str_contains($lowered, '__runInitializers(_init, 5, this);'));
        $t->true(!str_contains($lowered, '@methodController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress static field decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-static-field-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_settings_dec = [fieldController(metadata)];', $lowered);
        $t->contains('class StaticFieldDecoratedRegistration {', $lowered);
        $t->contains('static register(blocks = wp.blocks) {', $lowered);
        $t->contains('blocks.registerBlockType(metadata.name, StaticFieldDecoratedRegistration.settings);', $lowered);
        $t->contains('__decorateElement(_init, 13, "settings", _settings_dec, StaticFieldDecoratedRegistration);', $lowered);
        $t->contains('__publicField(StaticFieldDecoratedRegistration, "settings", __runInitializers(_init, 8, StaticFieldDecoratedRegistration, metadata)), __runInitializers(_init, 11, StaticFieldDecoratedRegistration);', $lowered);
        $t->contains('StaticFieldDecoratedRegistration.register();', $lowered);
        $t->true(strpos($lowered, '__decoratorMetadata(_init, StaticFieldDecoratedRegistration);') < strpos($lowered, '__publicField(StaticFieldDecoratedRegistration'));
        $t->true(!str_contains($lowered, '__runInitializers(_init, 3, StaticFieldDecoratedRegistration);'));
        $t->true(!str_contains($lowered, '@fieldController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress private field decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-private-field-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_settings_dec = [privateFieldController(metadata)];', $lowered);
        $t->contains('class PrivateFieldDecoratedRegistration {', $lowered);
        $t->contains('__privateAdd(this, _settings, __runInitializers(_init, 8, this, metadata)), __runInitializers(_init, 11, this);', $lowered);
        $t->contains('blocks.registerBlockType(metadata.name, metadata);', $lowered);
        $t->contains('_settings = new WeakMap();', $lowered);
        $t->contains('__decorateElement(_init, 21, "#settings", _settings_dec, _settings);', $lowered);
        $t->contains('__decoratorMetadata(_init, PrivateFieldDecoratedRegistration);', $lowered);
        $t->contains('new PrivateFieldDecoratedRegistration().register();', $lowered);
        $t->true(!str_contains($lowered, '@privateFieldController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress private accessor decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-private-accessor-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_settings_dec = [privateAccessorController(metadata)];', $lowered);
        $t->contains('class PrivateAccessorDecoratedRegistration {', $lowered);
        $t->contains('__privateAdd(this, _settings_instances);', $lowered);
        $t->contains('__privateAdd(this, _settings, __runInitializers(_init, 8, this, metadata)), __runInitializers(_init, 11, this);', $lowered);
        $t->contains('_settings_instances = new WeakSet();', $lowered);
        $t->contains('_a = __decorateElement(_init, 20, "#settings", _settings_dec, _settings_instances, _settings), settings_get = _a.get, settings_set = _a.set;', $lowered);
        $t->contains('__decoratorMetadata(_init, PrivateAccessorDecoratedRegistration);', $lowered);
        $t->contains('new PrivateAccessorDecoratedRegistration().register();', $lowered);
        $t->true(!str_contains($lowered, '@privateAccessorController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress private getter decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-private-getter-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_settings_dec = [privateGetterController(metadata)];', $lowered);
        $t->contains('class PrivateGetterDecoratedRegistration {', $lowered);
        $t->contains('__runInitializers(_init, 5, this);', $lowered);
        $t->contains('__privateAdd(this, _settings_instances);', $lowered);
        $t->contains('_settings_instances = new WeakSet();', $lowered);
        $t->contains('settings_get = function() {return this.settings;};', $lowered);
        $t->contains('settings_get = __decorateElement(_init, 18, "#settings", _settings_dec, _settings_instances, settings_get);', $lowered);
        $t->contains('blocks.registerBlockType(metadata.name, this.settings);', $lowered);
        $t->contains('new PrivateGetterDecoratedRegistration().register();', $lowered);
        $t->true(!str_contains($lowered, '@privateGetterController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress accessor decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-accessor-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_settings_dec = [accessorController(metadata)];', $lowered);
        $t->contains('class AccessorDecoratedRegistration {', $lowered);
        $t->contains('__privateAdd(this, _settings, __runInitializers(_init, 8, this, metadata)), __runInitializers(_init, 11, this);', $lowered);
        $t->contains('register(blocks = wp.blocks) {', $lowered);
        $t->contains('blocks.registerBlockType(metadata.name, this.settings);', $lowered);
        $t->contains('_settings = new WeakMap();', $lowered);
        $t->contains('__decorateElement(_init, 4, "settings", _settings_dec, AccessorDecoratedRegistration, _settings);', $lowered);
        $t->contains('__decoratorMetadata(_init, AccessorDecoratedRegistration);', $lowered);
        $t->true(strpos($lowered, '_settings = new WeakMap();') < strpos($lowered, '__decorateElement(_init, 4'));
        $t->true(!str_contains($lowered, '@accessorController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress computed field decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-computed-field-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('_a = (_assetKey_dec = [fieldController(metadata)], assetKey("settings"));', $lowered);
        $t->contains('__publicField(this, _a, __runInitializers(_init, 8, this, metadata)), __runInitializers(_init, 11, this);', $lowered);
        $t->contains('__decorateElement(_init, 5, _a, _assetKey_dec, ComputedFieldDecoratedRegistration);', $lowered);
        $t->contains('blocks.registerBlockType(metadata.name, this[assetKey("settings")]);', $lowered);
        $t->true(strpos($lowered, '_a = (_assetKey_dec = [fieldController(metadata)], assetKey("settings"));') < strpos($lowered, 'class ComputedFieldDecoratedRegistration'));
        $t->true(!str_contains($lowered, '@fieldController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress computed method decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-computed-method-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('[_a = (_methodKey_dec = [methodController(metadata)], methodKey("register"))](blocks = wp.blocks) {blocks.registerBlockType(metadata.name, metadata);}', $lowered);
        $t->contains('__runInitializers(_init, 5, this);', $lowered);
        $t->contains('__decorateElement(_init, 1, _a, _methodKey_dec, ComputedMethodDecoratedRegistration);', $lowered);
        $t->contains("new ComputedMethodDecoratedRegistration()[methodKey('register')]();", $lowered);
        $t->true(!str_contains($lowered, '@methodController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress computed getter and setter decorators for legacy targets without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-computed-accessor-decorator-legacy-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerDecorators: true);

        $t->contains('var _accessorKey_dec, _accessorKey_dec2, _a, _a2, _init;', $lowered);
        $t->contains('get [_a = (_accessorKey_dec = [accessorController(metadata)], accessorKey("settings"))]() {return this.#settings;}', $lowered);
        $t->contains('set [_a2 = (_accessorKey_dec2 = [accessorController(metadata)], accessorKey("settings"))](value) {this.#settings = value;}', $lowered);
        $t->contains('__runInitializers(_init, 5, this);', $lowered);
        $t->same(1, substr_count($lowered, '__runInitializers(_init, 5, this);'));
        $t->contains('__decorateElement(_init, 2, _a, _accessorKey_dec, ComputedAccessorDecoratedRegistration);', $lowered);
        $t->contains('__decorateElement(_init, 3, _a2, _accessorKey_dec2, ComputedAccessorDecoratedRegistration);', $lowered);
        $t->contains('blocks.registerBlockType(metadata.name, this[accessorKey("settings")]);', $lowered);
        $t->contains("controller[accessorKey('settings')] = metadata;", $lowered);
        $t->true(!str_contains($lowered, '@accessorController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
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
    'lowers wordpress default exported using asset controller without trapping exports' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-using-default-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->true(strpos($lowered, 'import metadata from "./block.json" with { type: "json" };') < strpos($lowered, 'var __knownSymbol'));
        $t->contains('var previewAsset = __using(_stack, acquirePreviewAsset(metadata.viewScript));', $lowered);
        $t->contains('var PreviewBlockController = class _PreviewBlockController', $lowered);
        $t->contains('static controller = _PreviewBlockController;', $lowered);
        $t->contains('register() {', $lowered);
        $t->contains('wp.blocks.registerBlockType(metadata.name, {', $lowered);
        $t->contains('viewScript: previewAsset.url,', $lowered);
        $t->contains("export {\n  PreviewBlockController as default\n};", $lowered);
        $t->true(strpos($lowered, 'var PreviewBlockController = class _PreviewBlockController') < strpos($lowered, '__callDispose(_stack'));
        $t->true(strpos($lowered, '__callDispose(_stack') < strpos($lowered, "export {\n  PreviewBlockController as default"));
        $t->true(!str_contains($lowered, "try {\n  export default class"));
        $t->true(!str_contains($lowered, 'static controller = PreviewBlockController'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress local using asset controller without trapping exports' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-using-local-controller.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->true(strpos($lowered, 'import metadata from "./block.json" with { type: "json" };') < strpos($lowered, 'var __knownSymbol'));
        $t->contains('var previewAsset = __using(_stack, acquirePreviewAsset(metadata.viewScript));', $lowered);
        $t->contains('var PreviewBlockController = class _PreviewBlockController', $lowered);
        $t->contains('static controller = _PreviewBlockController;', $lowered);
        $t->contains('new PreviewBlockController().register();', $lowered);
        $t->contains('export { PreviewBlockController };', $lowered);
        $t->true(strpos($lowered, 'var PreviewBlockController = class _PreviewBlockController') < strpos($lowered, '__callDispose(_stack'));
        $t->true(strpos($lowered, '__callDispose(_stack') < strpos($lowered, 'export { PreviewBlockController };'));
        $t->true(!str_contains($lowered, "try {\n  class PreviewBlockController"));
        $t->true(!str_contains($lowered, 'static controller = PreviewBlockController'));
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
    'lowers wordpress switch case disposable asset cleanup without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-switch-using-assets.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->contains('import metadata from "./block.json" with { type: "json" };', $lowered);
        $t->contains('export async function registerPreviewAssetByMode(mode, queue) {', $lowered);
        $t->contains('switch (mode) {', $lowered);
        $t->contains('case "view": {', $lowered);
        $t->contains('const previewAsset = __using(_stack2, acquirePreviewAsset(metadata.viewScript));', $lowered);
        $t->contains('registerAsset(previewAsset.handle, previewAsset.url);', $lowered);
        $t->contains('__callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('case "bulk":', $lowered);
        $t->contains('for (var _modeAsset of collectModeAssets(metadata, mode)) {', $lowered);
        $t->contains('const modeAsset = __using(_stack3, _modeAsset);', $lowered);
        $t->contains('registerAsset(modeAsset.handle, modeAsset.url);', $lowered);
        $t->contains('__callDispose(_stack3, _error3, _hasError3);', $lowered);
        $t->contains('const editorAsset = __using(_stack4, await queue.openNext(metadata.editorScript), true);', $lowered);
        $t->contains('var _promise4 = __callDispose(_stack4, _error4, _hasError4);', $lowered);
        $t->contains('_promise4 && await _promise4;', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(strpos($lowered, 'const previewAsset = __using') < strpos($lowered, 'registerAsset(previewAsset.handle'));
        $t->true(strpos($lowered, 'registerAsset(previewAsset.handle') < strpos($lowered, '__callDispose(_stack2'));
        $t->true(strpos($lowered, 'const modeAsset = __using') < strpos($lowered, 'registerAsset(modeAsset.handle'));
        $t->true(strpos($lowered, 'registerAsset(modeAsset.handle') < strpos($lowered, '__callDispose(_stack3'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
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
    'lowers wordpress for using asset cleanup with colliding helper names without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-for-using-helper-collision.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->contains('var __using2 = (stack, value, async) => {', $lowered);
        $t->contains('var __callDispose2 = (stack, error, hasError) => {', $lowered);
        $t->contains('const __using = wp.disposables.using;', $lowered);
        $t->contains('const __callDispose = wp.disposables.callDispose;', $lowered);
        $t->contains('const asset = __using2(_stack2, _asset);', $lowered);
        $t->contains('__callDispose2(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(!str_contains($lowered, 'const asset = __using(_stack2, _asset);'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, ': Disposable'));
        $t->true(!str_contains($lowered, 'satisfies'));
    },
    'lowers wordpress async asset queue using cleanup without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-while-using-assets.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->contains('import metadata from "./block.json" with { type: "json" };', $lowered);
        $t->contains('export async function registerQueuedAssets(queue) {', $lowered);
        $t->contains('while (queue.hasMore()) {', $lowered);
        $t->contains('const asset = __using(_stack2, await queue.openNext(metadata.viewScript), true);', $lowered);
        $t->contains('registerAsset(asset.handle, asset.url);', $lowered);
        $t->contains('var _promise2 = __callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('_promise2 && await _promise2;', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress async generator function asset queue runtime without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-async-generator-function-assets.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true, lowerAsyncGenerators: true);

        $t->contains('import metadata from "./block.json" with { type: "json" };', $lowered);
        $t->contains('export function streamPreviewAssets(queue) {', $lowered);
        $t->contains('return __asyncGenerator(this, null, function* () {', $lowered);
        $t->contains('const asset = __using(_stack2, yield new __await(queue.openNext(metadata.viewScript)), true);', $lowered);
        $t->contains('yield {handle:asset.handle, url:asset.url};', $lowered);
        $t->contains('yield* __yieldStar(queue.extraAssets());', $lowered);
        $t->contains('_promise2 && (yield new __await(_promise2));', $lowered);
        $t->contains('export async function registerStreamedAssets(queue) {', $lowered);
        $t->contains('for await (const asset of streamPreviewAssets(queue)) {', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'async function* streamPreviewAssets'));
        $t->true(!str_contains($lowered, 'await using'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress default async generator asset stream runtime without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-default-async-generator-assets.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true, lowerAsyncGenerators: true);

        $t->contains('import metadata from "./block.json" with { type: "json" };', $lowered);
        $t->contains('const settings = {name:metadata.name, viewScript:metadata.viewScript,};', $lowered);
        $t->contains('export default(function(queue) {', $lowered);
        $t->contains('return __asyncGenerator(this, null, function* () {', $lowered);
        $t->contains('const asset = __using(_stack2, yield new __await(queue.openNext(settings.viewScript)), true);', $lowered);
        $t->contains('yield {handle:asset.handle, url:asset.url};', $lowered);
        $t->contains('yield* __yieldStar(queue.extraAssets(settings.name));', $lowered);
        $t->contains('_promise2 && (yield new __await(_promise2));', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, 'await using'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress exported async generator constant runtime without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-exported-async-generator-constant.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true, lowerAsyncGenerators: true);

        $t->contains('import metadata from "./block.json" with { type: "json" };', $lowered);
        $t->contains('export const previewAssetStream = (function(queue) {', $lowered);
        $t->contains('return __asyncGenerator(this, null, function* () {', $lowered);
        $t->contains('const asset = __using(_stack2, yield new __await(queue.openNext(metadata.viewScript)), true);', $lowered);
        $t->contains('yield {handle:asset.handle, url:asset.url};', $lowered);
        $t->contains('yield* __yieldStar(queue.extraAssets(metadata.name));', $lowered);
        $t->contains('for await (const asset of previewAssetStream(queue)) {', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, 'await using'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress async generator registry array runtime without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-async-generator-registry-assets.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true, lowerAsyncGenerators: true);

        $t->contains('import metadata from "./block.json" with { type: "json" };', $lowered);
        $t->contains('const previewStreams = [function(queue) {', $lowered);
        $t->contains('return __asyncGenerator(this, null, function* () {', $lowered);
        $t->contains('const resolvedHandle = yield new __await(queue.resolveHandle?.(metadata.viewScript))??metadata.viewScript;', $lowered);
        $t->contains('const preferredHandle = yield new __await(queue.resolveHandle(metadata.viewScript))||metadata.viewScript;', $lowered);
        $t->contains('const asset = __using(_stack2, yield new __await(queue.openNext(resolvedHandle, metadata.name)), true);', $lowered);
        $t->contains('yield {handle:asset.handle, url:asset.url};', $lowered);
        $t->contains('yield* __yieldStar(queue.extraAssets(metadata.name));', $lowered);
        $t->contains('const fallback = __using(_stack3, yield new __await((queue.prepareFallback(metadata.name), queue.openNext(metadata.editorScript, metadata.name))), true);', $lowered);
        $t->contains('yield {handle:fallback.handle, url:fallback.url};', $lowered);
        $t->contains('yield* __yieldStar(queue.fallbackAssets(metadata.name));', $lowered);
        $t->contains('const previewStreamMap = {[metadata.name]:function(queue) {', $lowered);
        $t->contains('const asset = __using(_stack4, yield new __await(queue.openNext(metadata.editorScript, metadata.name)), true);', $lowered);
        $t->contains('consumePreviewStream(metadata.name, function() {', $lowered);
        $t->contains('yield* __yieldStar(stream(queue));', $lowered);
        $t->contains('consumePreviewStream(metadata.name, previewStreamMap[metadata.name]);', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'async function*'));
        $t->true(!str_contains($lowered, 'await using'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress async generator helper collisions without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-async-generator-helper-collision.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true, lowerAsyncGenerators: true);

        $t->contains('const __await = wp.interactivity.await;', $lowered);
        $t->contains('var __knownSymbol2 = (name, symbol) =>', $lowered);
        $t->contains('var __typeError2 = (msg) => {', $lowered);
        $t->contains('var __await2 = function(promise, isYieldStar) {', $lowered);
        $t->contains('var __asyncGenerator2 = (__this, __arguments, generator) => {', $lowered);
        $t->contains('var __yieldStar2 = (value) => {', $lowered);
        $t->contains('var __forAwait2 = (obj, it, method) =>', $lowered);
        $t->contains('function streamInteractiveAssets(queue) {', $lowered);
        $t->contains('return __asyncGenerator2(this, null, function* () {', $lowered);
        $t->contains('const asset = __using(_stack2, yield new __await2(queue.openNext(metadata.viewScript)), true);', $lowered);
        $t->contains('yield* __yieldStar2(queue.extraAssets(metadata.name));', $lowered);
        $t->contains('for (var iter = __forAwait2(queue.followupAssets(metadata.name)), more, temp, error; more = !(temp = yield new __await2(iter.next())).done; more = false) {', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'async function* streamInteractiveAssets'));
        $t->true(!str_contains($lowered, 'await using'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
        $t->true(!str_contains($lowered, 'return __asyncGenerator(this'));
    },
    'lowers wordpress async generator asset queue class cleanup without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-async-generator-assets.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->contains('import metadata from "./block.json" with { type: "json" };', $lowered);
        $t->contains('export class PreviewAssetQueue {', $lowered);
        $t->contains('async *assets(queue) {', $lowered);
        $t->contains('const asset = __using(_stack2, await queue.openNext(metadata.viewScript), true);', $lowered);
        $t->contains('yield { handle: asset.handle, url: asset.url };', $lowered);
        $t->contains('var _promise2 = __callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('const settings = {name:metadata.name, viewScript:metadata.viewScript,};', $lowered);
        $t->contains('export async function registerQueuedAssets(queue) {', $lowered);
        $t->contains('const previews = new PreviewAssetQueue();', $lowered);
        $t->contains('for await (const asset of previews.assets(queue)) {', $lowered);
        $t->contains('registerAsset(asset.handle, asset.url);', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(strpos($lowered, 'const asset = __using') < strpos($lowered, 'yield { handle: asset.handle'));
        $t->true(strpos($lowered, 'yield { handle: asset.handle') < strpos($lowered, 'var _promise2 = __callDispose'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
    'lowers wordpress object async generator asset queue cleanup without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-object-async-generator-assets.ts');
        $lowered = (new TypeScriptModuleLowerer())->lower($source, lowerUsingDeclarations: true);

        $t->contains('import metadata from "./block.json" with { type: "json" };', $lowered);
        $t->contains('export const previewAssets = {async *assets(queue) {', $lowered);
        $t->contains('const asset = __using(_stack2, await queue.openNext(metadata.viewScript), true);', $lowered);
        $t->contains('yield { handle: asset.handle, url: asset.url };', $lowered);
        $t->contains('var _promise2 = __callDispose(_stack2, _error2, _hasError2);', $lowered);
        $t->contains('for await (const asset of previewAssets.assets(queue)) {', $lowered);
        $t->contains('registerAsset(asset.handle, asset.url);', $lowered);
        $t->contains('wp.blocks.registerBlockType(settings.name, settings);', $lowered);
        $t->true(strpos($lowered, 'const asset = __using') < strpos($lowered, 'yield { handle: asset.handle'));
        $t->true(strpos($lowered, 'yield { handle: asset.handle') < strpos($lowered, 'var _promise2 = __callDispose'));
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, ': AsyncDisposable'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
    },
];
