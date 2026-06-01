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
