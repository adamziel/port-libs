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
