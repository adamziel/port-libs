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
    'css formatter maps upstream border printer cases' => static function (TestRunner $t): void {
        $formatter = new CssFormatter();

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
