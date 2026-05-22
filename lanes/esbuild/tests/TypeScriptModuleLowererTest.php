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
        $t->contains('blockName;', $lowered);
        $t->contains('blocks;', $lowered);
        $t->contains('register() {this.blocks.registerBlockType(this.blockName, {supports:{html:false}});}', $lowered);
        $t->true(!str_contains($lowered, '@wordpress/blocks'));
        $t->true(!str_contains($lowered, 'BlockConfiguration'));
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
];
