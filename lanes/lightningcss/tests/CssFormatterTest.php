<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssFormatter;

return [
    'css formatter maps upstream page rule printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
@page :right {
  @bottom-left {
    margin: 10pt;
  }
}

CSS, $formatter->format(<<<'CSS'
@page :right {
  @bottom-left {
    margin: 10pt;
  }
}
CSS));

        $t->same(<<<'CSS'
@page :right {
  margin: 1in;

  @bottom-left-corner {
    content: "Foo";
  }

  @bottom-right-corner {
    content: "Bar";
  }
}

CSS, $formatter->format(<<<'CSS'
@page :right {
  margin: 1in;

  @bottom-left-corner { content: "Foo"; }
  @bottom-right-corner { content: "Bar"; }
}
CSS));
    },
    'css formatter rejects invalid upstream page nested at rules' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->throws(InvalidArgumentException::class, static fn () => $formatter->format(<<<'CSS'
@page {
  @foo {
    margin: 1in;
  }
}
CSS));

        $t->throws(InvalidArgumentException::class, static fn () => $formatter->format(<<<'CSS'
@page {
  @top-left-corner {
    @bottom-left {
      margin: 1in;
    }
  }
}
CSS));
    },
    'css formatter maps upstream namespace printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
@namespace "http://example.com/foo";

x {
  color: red;
}

CSS, $formatter->format(<<<'CSS'
@namespace "http://example.com/foo";

x {
  color: red;
}
CSS));

        $t->same(<<<'CSS'
@namespace toto "http://toto.example.org";

toto|x {
  color: red;
}

[toto|att="val"] {
  color: #00f;
}

CSS, $formatter->format(<<<'CSS'
@namespace toto "http://toto.example.org";

toto|x {
  color: red;
}

[toto|att=val] {
  color: blue
}
CSS));

        $t->same(<<<'CSS'
@namespace "http://example.com/foo";

|x {
  color: red;
}

[att="val"] {
  color: #00f;
}

CSS, $formatter->format(<<<'CSS'
@namespace "http://example.com/foo";

|x {
  color: red;
}

[|att=val] {
  color: blue
}
CSS));

        $t->same(<<<'CSS'
@namespace "http://example.com/foo";

*|x {
  color: red;
}

[*|att="val"] {
  color: #00f;
}

CSS, $formatter->format(<<<'CSS'
@namespace "http://example.com/foo";

*|x {
  color: red;
}

[*|att=val] {
  color: blue
}
CSS));
    },
    'css formatter maps upstream charset printer case' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  color: red;
}

.bar {
  color: #ff0;
}

CSS, $formatter->format(<<<'CSS'
@charset "UTF-8";

.foo {
  color: red;
}

@charset "UTF-8";

.bar {
  color: yellow;
}
CSS));
    },
    'css formatter maps upstream counter-style printer case' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
@counter-style circled-alpha {
  system: fixed;
  symbols: Ⓐ Ⓑ Ⓒ;
  suffix: " ";
}

CSS, $formatter->format(<<<'CSS'
@counter-style circled-alpha {
  system: fixed;
  symbols: Ⓐ Ⓑ Ⓒ;
  suffix: " ";
}
CSS));

        $t->throws(InvalidArgumentException::class, static fn () => $formatter->format(<<<'CSS'
@counter-style circled-alpha {
  @media print {
    system: fixed;
  }
}
CSS));
    },
    'css formatter maps upstream supports rule printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
@supports (foo: bar) {
  .test {
    foo: bar;
  }
}

CSS, $formatter->format(<<<'CSS'
@supports (foo: bar) {
  .test {
    foo: bar;
  }
}
CSS));

        $t->same(<<<'CSS'
@supports (foo: bar) or (bar: baz) {
  .test {
    foo: bar;
  }
}

CSS, $formatter->format(<<<'CSS'
@supports (foo: bar) or (bar: baz) {
  .test {
    foo: bar;
  }
}
CSS));

        $t->same(<<<'CSS'
@supports (foo: bar) or (bar: baz) {
  .test {
    foo: bar;
  }
}

CSS, $formatter->format(<<<'CSS'
@supports (((foo: bar) or (bar: baz))) {
  .test {
    foo: bar;
  }
}
CSS));

        $t->same(<<<'CSS'
@supports (foo: bar) and (bar: baz) {
  .test {
    foo: bar;
  }
}

CSS, $formatter->format(<<<'CSS'
@supports (((foo: bar) and (bar: baz))) {
  .test {
    foo: bar;
  }
}
CSS));
    },
    'css formatter maps upstream important declaration printer case' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  align-items: center;
  justify-items: center !important;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  align-items: center;
  justify-items: center !important;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  align-items: center;
  justify-items: center !important;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  justify-items: center !important;
  align-items: center;
}
CSS));
    },
    'css formatter maps upstream outline printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  outline: 2px solid #00f;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  outline-width: 2px;
  outline-style: solid;
  outline-color: blue;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  outline: 2px solid #00f;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  outline: 2px solid blue;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  outline: 2px solid #00f;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  outline: 2px solid red;
  outline-color: blue;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  outline: 2px solid #ff0;
  outline-color: var(--color);
}

CSS, $formatter->format(<<<'CSS'
.foo {
  outline: 2px solid yellow;
  outline-color: var(--color);
}
CSS));
    },
    'css formatter maps upstream border printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  border: 1px solid;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border: 1px solid currentColor;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 2px solid red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-left: 2px solid red;
  border-right: 2px solid red;
  border-bottom: 2px solid red;
  border-top: 2px solid red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-color: red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-left-color: red;
  border-right-color: red;
  border-bottom-color: red;
  border-top-color: red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-width: thin;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-left-width: thin;
  border-right-width: thin;
  border-bottom-width: thin;
  border-top-width: thin;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-style: dotted;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-left-style: dotted;
  border-right-style: dotted;
  border-bottom-style: dotted;
  border-top-style: dotted;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-left: thin dotted red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-left-width: thin;
  border-left-style: dotted;
  border-left-color: red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-left: thin dotted red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-left-width: thick;
  border-left: thin dotted red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: thin dotted red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-left-width: thick;
  border: thin dotted red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: thin dotted red;
  border-right-width: thick;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border: thin dotted red;
  border-right-width: thick;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: thin dotted red;
  border-right-width: thick;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border: thin dotted red;
  border-right: thick dotted red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: thin dotted red;
  border-right: thick solid red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border: thin dotted red;
  border-right-width: thick;
  border-right-style: solid;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-width: 0;
  border-bottom: var(--test, 1px) solid;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-width: 0;
  border-bottom: var(--test, 1px) solid;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid #000;
  border-width: 1px 1px 0 0;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border: 1px solid black;
  border-width: 1px 1px 0 0;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid #000;
  border-width: 1px 2px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-top: 1px solid black;
  border-bottom: 1px solid black;
  border-left: 2px solid black;
  border-right: 2px solid black;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid #000;
  border-left-width: 2px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-top: 1px solid black;
  border-bottom: 1px solid black;
  border-left: 2px solid black;
  border-right: 1px solid black;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid #000;
  border-color: #000 red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-top: 1px solid black;
  border-bottom: 1px solid black;
  border-left: 1px solid red;
  border-right: 1px solid red;
}
CSS));
    },
    'css formatter maps upstream logical border printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  border-top: thin dotted red;
  border-block-start: thick solid green;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-top: thin dotted red;
  border-block-start: thick solid green;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: thin dotted red;
  border-block-start-width: thick;
  border-left-width: medium;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border: thin dotted red;
  border-block-start-width: thick;
  border-left-width: medium;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-block-start: thin dotted red;
  border-inline-end: thin dotted red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start: thin dotted red;
  border-inline-end: thin dotted red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-block-start: thin dotted red;
  border-inline-end: thin dotted red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start-width: thin;
  border-block-start-style: dotted;
  border-block-start-color: red;
  border-inline-end: thin dotted red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-block: thin dotted red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start: thin dotted red;
  border-block-end: thin dotted red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-width: 1px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-width: 1px;
  border-inline-width: 1px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-width: 1px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start-width: 1px;
  border-block-end-width: 1px;
  border-inline-start-width: 1px;
  border-inline-end-width: 1px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-block-width: 1px;
  border-inline-width: 2px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start-width: 1px;
  border-block-end-width: 1px;
  border-inline-start-width: 2px;
  border-inline-end-width: 2px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-block-width: 1px;
  border-inline-width: 2px 3px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start-width: 1px;
  border-block-end-width: 1px;
  border-inline-start-width: 2px;
  border-inline-end-width: 3px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid #000;
  border-inline-color: red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start: 1px solid black;
  border-block-end: 1px solid black;
  border-inline-start: 1px solid red;
  border-inline-end: 1px solid red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid #000;
  border-inline-width: 2px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start: 1px solid black;
  border-block-end: 1px solid black;
  border-inline-start: 2px solid black;
  border-inline-end: 2px solid black;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid #000;
  border-inline: 2px solid red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start: 1px solid black;
  border-block-end: 1px solid black;
  border-inline-start: 2px solid red;
  border-inline-end: 2px solid red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid #000;
  border-inline-start: 2px solid red;
  border-inline-end: 3px solid red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start: 1px solid black;
  border-block-end: 1px solid black;
  border-inline-start: 2px solid red;
  border-inline-end: 3px solid red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 2px solid red;
  border-block-start-color: #000;
  border-block-end: 1px solid #000;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start: 2px solid black;
  border-block-end: 1px solid black;
  border-inline-start: 2px solid red;
  border-inline-end: 2px solid red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 2px solid red;
  border-block-end-width: 1px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start: 2px solid red;
  border-block-end: 1px solid red;
  border-inline-start: 2px solid red;
  border-inline-end: 2px solid red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 2px solid red;
  border-inline-end-width: 1px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-block-start: 2px solid red;
  border-block-end: 2px solid red;
  border-inline-start: 2px solid red;
  border-inline-end: 1px solid red;
}
CSS));
    },
    'css formatter maps upstream url quoting printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  background-image: url("0123abcd");
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background-image: url("0123abcd");
}
CSS));

        $t->same(<<<'CSS'
.foo {
  background-image: url("0123abcd");
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background-image: url(0123abcd);
}
CSS));

        $t->same(<<<'CSS'
.foo {
  background-image: url(var(--asset));
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background-image: url(var(--asset));
}
CSS));
    },
    'css formatter maps upstream background printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  background: url("img.png") 20px 10px / 50px 100px repeat-x;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background: url(img.png);
  background-position-x: 20px;
  background-position-y: 10px;
  background-size: 50px 100px;
  background-repeat: repeat no-repeat;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  background: red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background-color: red;
  background-position: 0% 0%;
  background-size: auto;
  background-repeat: repeat;
  background-clip: border-box;
  background-origin: padding-box;
  background-attachment: scroll;
  background-image: none
}
CSS));

        $t->same(<<<'CSS'
.foo {
  background: gray url("chess.png") 40% / 10em round fixed border-box;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background-color: gray;
  background-position: 40% 50%;
  background-size: 10em auto;
  background-repeat: round;
  background-clip: border-box;
  background-origin: border-box;
  background-attachment: fixed;
  background-image: url('chess.png');
}
CSS));

        $t->same(<<<'CSS'
.foo {
  background: url("img.png") right 20px top 20px / 50px 50px repeat-x, gray url("test.jpg") 10px 15px no-repeat;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background: url(img.png), url(test.jpg) gray;
  background-position-x: right 20px, 10px;
  background-position-y: top 20px, 15px;
  background-size: 50px 50px, auto;
  background-repeat: repeat no-repeat, no-repeat;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  background: gray url("img.png") padding-box content-box;
  -webkit-background-clip: text;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background: url(img.png) gray;
  background-clip: content-box;
  -webkit-background-clip: text;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  background: gray url("img.png");
  -webkit-background-clip: text;
  background-clip: content-box;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background: url(img.png) gray;
  -webkit-background-clip: text;
  background-clip: content-box;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  background: gray url("img.png");
  background-position: var(--pos);
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background: url(img.png) gray;
  background-position: var(--pos);
}
CSS));

        // Pinned upstream 22bdda3d src/lib.rs::test_background lines 4731-4738.
        $t->same(<<<'CSS'
.foo {
  background: calc(var(--v) / .3);
}

CSS, $formatter->format(<<<'CSS'
.foo {
  background: calc(var(--v) / 0.3);
}
CSS));
    },
    'css formatter maps upstream border image printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  border-image: url("test.png") 60;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-image: url(test.png) 60;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-image: url("foo.png") 60;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-image: url(test.png) 60;
  border-image-source: url(foo.png);
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-image: url("foo.png") 10 40 fill / 10px round;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-image-source: url(foo.png);
  border-image-slice: 10 40 10 40 fill;
  border-image-width: 10px;
  border-image-outset: 0;
  border-image-repeat: round round;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border-image: url("foo.png") 60;
  border-image-source: var(--test);
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-image: url(foo.png) 60;
  border-image-source: var(--test);
}
CSS));

        $t->same(<<<'CSS'
.foo {
  -webkit-border-image: url("test.png") 60;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  -webkit-border-image: url("test.png") 60;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  -webkit-border-image: url("test.png") 60;
  border-image: url("test.png") 60;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  -webkit-border-image: url("test.png") 60;
  border-image: url("test.png") 60;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  -webkit-border-image: url("test.png") 60;
  border-image-source: url("foo.png");
}

CSS, $formatter->format(<<<'CSS'
.foo {
  -webkit-border-image: url("test.png") 60;
  border-image-source: url(foo.png);
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid red;
  border-image: url("test.png") 60;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border: 1px solid red;
  border-image: url(test.png) 60;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid red;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border-image: url(test.png) 60;
  border: 1px solid red;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  border: 1px solid red;
  border-image: var(--border-image);
}

CSS, $formatter->format(<<<'CSS'
.foo {
  border: 1px solid red;
  border-image: var(--border-image);
}
CSS));
    },
    'css formatter maps upstream position try printer case' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
@position-try --foo {
  top: anchor(bottom);
  left: anchor(right);
}

CSS, $formatter->format(<<<'CSS'
@position-try --foo {
  top: anchor(bottom);
  left: anchor(right);
}
CSS));
    },
    'css formatter maps upstream property rule printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
@property --property-name {
  syntax: "*";
  inherits: false;
  initial-value: ;
}

CSS, $formatter->format(<<<'CSS'
@property --property-name {
  syntax: '*';
  inherits: false;
  initial-value: ;
}
CSS));

        $t->same(<<<'CSS'
@property --property-name {
  syntax: "*";
  inherits: false;
  initial-value: ;
}

CSS, $formatter->format(<<<'CSS'
@property --property-name {
  syntax: '*';
  inherits: false;
  initial-value:;
}
CSS));

        $t->same(<<<'CSS'
@property --property-name {
  syntax: "<length> | none";
  inherits: false;
  initial-value: none;
}

CSS, $formatter->format(<<<'CSS'
@property --property-name {
  syntax: '<length>|none';
  inherits: false;
  initial-value: none;
}
CSS));

        $t->same(<<<'CSS'
@media (width < 800px) {
  @property --property-name {
    syntax: "*";
    inherits: false
  }
}

CSS, $formatter->format(<<<'CSS'
@media (width < 800px) {
  @property --property-name {
    syntax: '*';
    inherits: false;
  }
}
CSS));

        $t->same(<<<'CSS'
@layer foo {
  @property --property-name {
    syntax: "*";
    inherits: false
  }
}

CSS, $formatter->format(<<<'CSS'
@layer foo {
  @property --property-name {
    syntax: '*';
    inherits: false;
  }
}
CSS));
    },
    'css formatter maps upstream margin and padding shorthand printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  margin: 20px 10px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  margin-left: 10px;
  margin-right: 10px;
  margin-top: 20px;
  margin-bottom: 20px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  margin-block: 15px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  margin-block-start: 15px;
  margin-block-end: 15px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  margin-left: 10px;
  margin-right: 10px;
  margin-inline: 15px;
  margin-top: 20px;
  margin-bottom: 20px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  margin-left: 10px;
  margin-right: 10px;
  margin-inline-start: 15px;
  margin-inline-end: 15px;
  margin-top: 20px;
  margin-bottom: 20px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  margin: 20px 10px 10px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  margin: 10px;
  margin-top: 20px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  margin: 10px;
  margin-top: var(--top);
}

CSS, $formatter->format(<<<'CSS'
.foo {
  margin: 10px;
  margin-top: var(--top);
}
CSS));

        $t->same(<<<'CSS'
.foo {
  padding: 20px 10px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  padding-left: 10px;
  padding-right: 10px;
  padding-top: 20px;
  padding-bottom: 20px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  padding-block: 15px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  padding-block-start: 15px;
  padding-block-end: 15px;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  padding-left: 10px;
  padding-right: 10px;
  padding-inline: 15px;
  padding-top: 20px;
  padding-bottom: 20px;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  padding-left: 10px;
  padding-right: 10px;
  padding-inline-start: 15px;
  padding-inline-end: 15px;
  padding-top: 20px;
  padding-bottom: 20px;
}
CSS));
    },
    'css formatter maps upstream font shorthand printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  font: italic small-caps bold expanded 12px / 1.2em Helvetica, Times New Roman, sans-serif;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  font-family: "Helvetica", "Times New Roman", sans-serif;
  font-size: 12px;
  font-weight: bold;
  font-style: italic;
  font-stretch: expanded;
  font-variant-caps: small-caps;
  line-height: 1.2em;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  font: italic bold expanded 12px / 1.2em Helvetica, Times New Roman, sans-serif;
  font-variant-caps: all-small-caps;
}

CSS, $formatter->format(<<<'CSS'
.foo {
  font-family: "Helvetica", "Times New Roman", sans-serif;
  font-size: 12px;
  font-weight: bold;
  font-style: italic;
  font-stretch: expanded;
  font-variant-caps: all-small-caps;
  line-height: 1.2em;
}
CSS));

        $t->same(<<<'CSS'
.foo {
  font: 12px / 1.2em Helvetica, Times New Roman, sans-serif;
}

CSS, $formatter->format('.foo { font: 12px "Helvetica", "Times New Roman", sans-serif; line-height: 1.2em; }'));

        $t->same(<<<'CSS'
.foo {
  font: 12px Helvetica, Times New Roman, sans-serif;
  line-height: var(--lh);
}

CSS, $formatter->format('.foo { font: 12px "Helvetica", "Times New Roman", sans-serif; line-height: var(--lh); }'));
    },
    'css formatter maps upstream transform printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        // Pinned upstream 22bdda3d190f1cd321d98026225cfc964af64ad9 src/lib.rs::test_transform line 12882.
        $t->same(<<<'CSS'
.foo {
  transform: perspective(500px) translate3d(10px, 0, 20px) rotateY(30deg);
}

CSS, $formatter->format('.foo { transform: perspective(500px)translate3d(10px, 0, 20px)rotateY(30deg) }'));

        // Pinned upstream 22bdda3d190f1cd321d98026225cfc964af64ad9 src/lib.rs::test_transform line 12890.
        $t->same(<<<'CSS'
.foo {
  transform: translate3d(12px, 50%, 3em) scale(2, .5);
}

CSS, $formatter->format('.foo { transform: translate3d(12px,50%,3em)scale(2,.5) }'));

        // Pinned upstream 22bdda3d190f1cd321d98026225cfc964af64ad9 src/lib.rs::test_transform line 12898.
        $t->same(<<<'CSS'
.foo {
  transform: matrix(1, 2, -1, 1, 80, 80);
}

CSS, $formatter->format('.foo { transform:matrix(1,2,-1,1,80,80) }'));
    },
    'css formatter maps upstream grid template printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

        $t->same(<<<'CSS'
.foo {
  grid-template: [header-top] "a a a" [header-bottom]
                 [main-top] "b b b" 1fr [main-bottom]
                 / auto 1fr auto;
}

CSS, $formatter->format('.foo{grid-template:[header-top]"a a a"[header-bottom main-top]"b b b"1fr[main-bottom]/auto 1fr auto}'));

        $t->same(<<<'CSS'
.foo {
  grid-template: [header-top] "a a a"
                 [main-top] "b b b" 1fr
                 / auto 1fr auto;
}

CSS, $formatter->format('.foo{grid-template:[header-top]"a a a"[main-top]"b b b"1fr/auto 1fr auto}'));

        $t->same(<<<'CSS'
.foo {
  grid-template: auto 1fr / auto 1fr auto;
}

CSS, $formatter->format('.foo{grid-template-rows:auto 1fr;grid-template-columns:auto 1fr auto;grid-template-areas:none}'));

        $t->same(<<<'CSS'
.foo {
  grid-template: [header-top] "a a a" [header-bottom]
                 [main-top] "b b b" 1fr [main-bottom]
                 / auto 1fr auto;
}

CSS, $formatter->format('.foo{grid-template-areas:"a a a" "b b b";grid-template-rows:[header-top] auto [header-bottom main-top] 1fr [main-bottom];grid-template-columns:auto 1fr auto}'));

        $t->same(<<<'CSS'
.foo {
  grid-template: ". a a ."
                 ". b b ." 1fr
                 / 10px 1fr 1fr 10px;
}

CSS, $formatter->format('.foo{grid-template-areas:". a a ." ". b b .";grid-template-rows:auto 1fr;grid-template-columns:10px 1fr 1fr 10px}'));

        $t->same(<<<'CSS'
.foo {
  grid-template: "one"
                 "." 80px
                 / 1fr 90px;
}

CSS, $formatter->format('.foo{grid-template-columns:1fr 90px;grid-template-rows:auto 80px;grid-template-areas:"one"}'));

        $t->same(<<<'CSS'
.foo {
  grid-template: none;
}

CSS, $formatter->format('.foo{grid-template-areas:none;grid-template-columns:none;grid-template-rows:none}'));

        $t->same(<<<'CSS'
.foo {
  grid: [header-top] "a a a" [header-bottom]
        [main-top] "b b b" 1fr [main-bottom]
        / auto 1fr auto;
}

CSS, $formatter->format('.foo{grid-template-areas:"a a a" "b b b";grid-template-rows:[header-top] auto [header-bottom main-top] 1fr [main-bottom];grid-template-columns:auto 1fr auto;grid-auto-flow:row;grid-auto-rows:auto;grid-auto-columns:auto}'));

        $t->same(<<<'CSS'
.foo {
  grid: repeat(2, 1fr) / auto 1fr auto;
}

CSS, $formatter->format('.foo{grid-template-areas:none;grid-template-columns:auto 1fr auto;grid-template-rows:repeat(2, 1fr);grid-auto-flow:row;grid-auto-rows:auto;grid-auto-columns:auto}'));

        $t->same(<<<'CSS'
.foo {
  grid: auto-flow dense 1fr / auto 1fr auto;
}

CSS, $formatter->format('.foo{grid-template-rows:none;grid-template-columns:auto 1fr auto;grid-template-areas:none;grid-auto-flow:row dense;grid-auto-rows:1fr;grid-auto-columns:auto}'));

        $t->same(<<<'CSS'
.foo {
  grid: auto 1fr auto / auto-flow dense 1fr;
}

CSS, $formatter->format('.foo{grid-template-rows:auto 1fr auto;grid-template-columns:none;grid-template-areas:none;grid-auto-flow:column dense;grid-auto-rows:auto;grid-auto-columns:1fr}'));

        $t->same(<<<'CSS'
.foo {
  grid-template: [header-top] "a a a" [header-bottom]
                 [main-top] "b b b" 1fr [main-bottom]
                 / auto 1fr auto;
  grid-auto-rows: 1fr;
  grid-auto-columns: 1fr;
  grid-auto-flow: column;
}

CSS, $formatter->format('.foo{grid-template-areas:"a a a" "b b b";grid-template-rows:[header-top] auto [header-bottom main-top] 1fr [main-bottom];grid-template-columns:auto 1fr auto;grid-auto-flow:column;grid-auto-rows:1fr;grid-auto-columns:1fr}'));

        $t->same(<<<'CSS'
.foo {
  grid-template: auto 1fr auto / none;
  grid-auto-flow: var(--auto-flow);
  grid-auto-rows: auto;
  grid-auto-columns: 1fr;
}

CSS, $formatter->format('.foo{grid-template-rows:auto 1fr auto;grid-template-columns:none;grid-template-areas:none;grid-auto-flow:var(--auto-flow);grid-auto-rows:auto;grid-auto-columns:1fr}'));
    },
    'wordpress property registration formatting preserves design token blocks' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@layer theme.tokens {
  @property --wp--custom--card-accent { syntax: '<color>'; inherits: true; initial-value: blue; }
}
CSS;

        $t->same(<<<'CSS'
@layer theme.tokens {
  @property --wp--custom--card-accent {
    syntax: "<color>";
    inherits: true;
    initial-value: blue;
  }
}

CSS, (new CssFormatter())->format($css));
    },
    'wordpress print export page rules format without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@page chapter:right {
  margin: 1in;
  @bottom-left-corner { content: "Chapter"; }
  @bottom-right-corner { content: counter(page); }
}
CSS;

        $t->same(<<<'CSS'
@page chapter:right {
  margin: 1in;

  @bottom-left-corner {
    content: "Chapter";
  }

  @bottom-right-corner {
    content: counter(page);
  }
}

CSS, (new CssFormatter())->format($css));
    },
    'wordpress custom list marker counter style formats without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@counter-style wp-step-marker { system: fixed; symbols: "①" "②" "③"; suffix: " "; }
CSS;

        $t->same(<<<'CSS'
@counter-style wp-step-marker {
  system: fixed;
  symbols: "①" "②" "③";
  suffix: " ";
}

CSS, (new CssFormatter())->format($css));
    },
];
