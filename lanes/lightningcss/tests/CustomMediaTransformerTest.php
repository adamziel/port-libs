<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;
use PortLibs\LightningCSS\CustomMediaException;
use PortLibs\LightningCSS\CustomMediaTransformer;

$transformAndMinify = static function (string $css): string {
    $transformed = (new CustomMediaTransformer())->transform($css);

    return (new CssMinifier())->minify($transformed);
};

return [
    'custom media transformer maps upstream list substitution' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --modern (color), (hover);

@media (--modern) and (width > 1024px) {
  .a {
    color: green;
  }
}
CSS;

        $t->same(
            '@media ((color) or (hover)) and (width>1024px){.a{color:green}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer maps upstream recursive references and later declarations' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@media (--b) and (width > 1024px) {
  .a {
    color: green;
  }
}

@custom-media --a (color);
@custom-media --b (--a);
CSS;

        $t->same(
            '@media (color) and (width>1024px){.a{color:green}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer maps upstream media type and negation simplification' => static function (TestRunner $t) use ($transformAndMinify): void {
        $t->same(
            '@media print and (color){.a{color:green}}',
            $transformAndMinify('@custom-media --color-print print and (color); @media print and (--color-print) { .a { color: green } }')
        );
        $t->same(
            '@media print{.a{color:green}}',
            $transformAndMinify('@custom-media --print not print; @media not (--print) { .a { color: green } }')
        );
        $t->same(
            '@media (color){.a{color:green}}',
            $transformAndMinify('@custom-media --not-color not (color); @media not (--not-color) { .a { color: green } }')
        );
        $t->same(
            '@media not print and (color){.a{color:green}}',
            $transformAndMinify('@custom-media --not-print-color not print and (color); @media not print and (--not-print-color) { .a { color: green } }')
        );
    },
    'custom media transformer maps upstream common media type factoring' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --color print and (color), print and (script);

@media (--color) {
  .a {
    color: green;
  }
}
CSS;

        $t->same(
            '@media print and ((color) or (script)){.a{color:green}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer resolves import media query tails' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wide (min-width: 782px);
@custom-media --coarse (pointer: coarse), (hover: none);

@import url(./blocks/query.css) supports((display: grid)) screen and (--wide);
@import "./blocks/touch.css" (--coarse);

@media (--wide) {
  .wp-block-query {
    color: yellow;
  }
}
CSS;

        $t->same(
            '@import "./blocks/query.css" supports(display:grid) screen and (width>=782px);@import "./blocks/touch.css" (pointer:coarse) or (hover:none);@media (width>=782px){.wp-block-query{color:#ff0}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer preserves escaped url delimiters while resolving import tails' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wide (min-width: 782px);

@import url(./blocks/icon\).css) screen and (--wide);
@import url(./blocks/icon\(.css) (--wide);
CSS;

        $t->same(
            '@import "./blocks/icon).css" screen and (width>=782px);@import "./blocks/icon(.css" (width>=782px);',
            $transformAndMinify($css)
        );
    },
    'custom media transformer consumes crlf after hex escaped import sources' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = "@custom-media --wide (min-width: 782px);\n\n@import url(./blocks/card\\2e\r\ncss) (--wide);";

        $t->same(
            '@import "./blocks/card.css" (width>=782px);',
            $transformAndMinify($css)
        );
    },
    'custom media transformer resolves import media tails after layer modifiers and comments' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wide (min-width: 782px);
@custom-media --motion (prefers-reduced-motion: no-preference);

@import url(./blocks/cards.css) /* wp block layer */ layer(theme.blocks) screen and (--wide);
@import "./blocks/animations.css" layer supports((animation-name: fade)) (--motion);
CSS;

        $t->same(
            '@import "./blocks/cards.css" layer(theme.blocks) screen and (width>=782px);@import "./blocks/animations.css" layer supports(animation-name:fade)(prefers-reduced-motion:no-preference);',
            $transformAndMinify($css)
        );
    },
    'custom media transformer ignores references inside media and import comments' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wide (min-width: 782px);

@import url(./blocks/cards.css) /* (--missing-import) */ screen and (--wide);

@media /* (--missing-media) */ (--wide) {
  .wp-block-group {
    color: yellow;
  }
}
CSS;

        $t->same(
            '@import "./blocks/cards.css" screen and (width>=782px);@media (width>=782px){.wp-block-group{color:#ff0}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer ignores comment parentheses while resolving media and import tails' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wide (min-width: 782px);
@custom-media --motion (prefers-reduced-motion: no-preference);

@import "./blocks/cards.css" screen and (min-width: 480px /* stale ) (--missing-import) */) and (--wide);

@media (min-width: 480px /* stale ) (--missing-media) */) and (--wide) and (--motion) {
  .wp-block-group {
    color: yellow;
  }
}
CSS;

        $t->same(
            '@import "./blocks/cards.css" screen and (width>=480px) and (width>=782px);@media (width>=480px) and (width>=782px) and (prefers-reduced-motion:no-preference){.wp-block-group{color:#ff0}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer ignores comment commas while splitting media lists' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wide (min-width: 782px);
@custom-media --motion (prefers-reduced-motion: no-preference);

@import "./blocks/cards.css" screen and (min-width: 480px /* stale, (--missing-import) */) and (--wide), print and (--motion);

@media (min-width: 480px /* stale, (--missing-media) */) and (--wide), print and (--motion) {
  .wp-block-group {
    color: yellow;
  }
}
CSS;

        $t->same(
            '@import "./blocks/cards.css" screen and (width>=480px) and (width>=782px),print and (prefers-reduced-motion:no-preference);@media (width>=480px) and (width>=782px),print and (prefers-reduced-motion:no-preference){.wp-block-group{color:#ff0}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer ignores stale references inside definition comments' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wide (min-width: 782px /* stale, ) (--missing-definition) */);
@custom-media --motion (prefers-reduced-motion: no-preference /* old alias: (--legacy-motion), */);

@media (--wide), (--motion) {
  .wp-block-group {
    color: yellow;
  }
}
CSS;

        $t->same(
            '@media (width>=782px),(prefers-reduced-motion:no-preference){.wp-block-group{color:#ff0}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer ignores stale import modifier tokens inside comments' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wide (min-width: 782px);

@import url(./blocks/cards.css) /* stale layer(old) supports((display:flex)) (--missing-import), ) */ layer(theme.blocks) supports((display: grid)) screen and (--wide);
CSS;

        $t->same(
            '@import "./blocks/cards.css" layer(theme.blocks) supports(display:grid) screen and (width>=782px);',
            $transformAndMinify($css)
        );
    },
    'custom media transformer skips escaped import modifiers before resolving media tails' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wide (min-width: 782px);
@custom-media --motion (prefers-reduced-motion: no-preference);

@import \75rl(tokens.css) s\75pports((--wide)) screen and (--wide);
@import \75rl(./blocks/cards.css) l\61yer(theme.blocks) s\75pports(display: grid) print and (--motion);
CSS;

        $t->same(
            '@import "tokens.css" supports((--wide)) screen and (width>=782px);@import "./blocks/cards.css" layer(theme.blocks) supports(display:grid) print and (prefers-reduced-motion:no-preference);',
            $transformAndMinify($css)
        );
    },
    'custom media transformer maps upstream negated range aliases' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --not-width not (min-width: 300px);

@media screen and ((prefers-color-scheme: dark) or (--not-width)) {
  .foo {
    order: 6;
  }
}
CSS;

        $t->same(
            '@media screen and ((prefers-color-scheme:dark) or ((width<300px))){.foo{order:6}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer preserves unknown legacy-looking range aliases in layers' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --not-theme-min not (min-Theme-Breakpoint: 2);
@custom-media --not-token-max not (max---WP-Breakpoint: 4);

@layer theme.blocks {
  @media (--not-theme-min) {
    .min {
      color: yellow;
    }
  }

  @media (--not-token-max) {
    .max {
      color: blue;
    }
  }
}
CSS;

        $t->same(
            '@layer theme.blocks{@media not (min-Theme-Breakpoint:2){.min{color:#ff0}}@media not (max---WP-Breakpoint:4){.max{color:#00f}}}',
            $transformAndMinify($css)
        );
    },
    'custom media transformer preserves declarations when requested' => static function (TestRunner $t): void {
        $css = '@custom-media --foo print; @media (--foo) { .a { color: red } }';
        $transformed = (new CustomMediaTransformer())->transform($css, true);

        $t->contains('@custom-media --foo print;', $transformed);
        $t->contains('@media print', $transformed);
    },
    'custom media transformer rejects undefined and circular references' => static function (TestRunner $t): void {
        $transformer = new CustomMediaTransformer();

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('@media (--missing) { .a { color: red } }'));
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $transformer->transform('@custom-media --a (--b); @custom-media --b (--a); @media (--a) { body { color: red } }')
        );
    },
    'custom media transformer rejects upstream unsupported media type boolean logic' => static function (TestRunner $t): void {
        $transformer = new CustomMediaTransformer();

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $transformer->transform('@custom-media --color-print print and (color); @media screen and (--color-print) { .a { color: green } }')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $transformer->transform('@custom-media --color-print print and (color); @custom-media --color-screen screen and (color); @media (--color-print) or (--color-screen) {}')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $transformer->transform('@custom-media --screen screen; @custom-media --print print; @media (--print) and (--screen) {}')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $transformer->transform('@custom-media --color screen and (color), print and (color); @media (--color) { .a { color: green } }')
        );
    },
    'custom media transformer reports upstream diagnostic locations' => static function (TestRunner $t): void {
        $source = <<<'CSS'

      @custom-media --color-print print and (color);

      @media screen and (--color-print) {
        .a {
          color: green;
        }
      }
CSS;

        try {
            (new CustomMediaTransformer())->transform($source);
        } catch (CustomMediaException $exception) {
            $t->same('unsupported-custom-media-boolean-logic', $exception->kind);
            $t->same('--color-print', $exception->name);
            $t->same(['line' => 3, 'column' => 7], $exception->mediaLocation);
            $t->same(['line' => 1, 'column' => 7], $exception->customMediaLocation);
            $t->contains('@media line 3, column 7', $exception->getMessage());
            $t->contains('@custom-media line 1, column 7', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected custom media location exception');
    },
    'custom media transformer reports undefined and circular reference locations' => static function (TestRunner $t): void {
        $sawUndefined = false;
        try {
            (new CustomMediaTransformer())->transform("\n      @media (--not-defined) { .a { color: green } }");
        } catch (CustomMediaException $exception) {
            $sawUndefined = true;
            $t->same('custom-media-not-defined', $exception->kind);
            $t->same('--not-defined', $exception->name);
            $t->same(['line' => 1, 'column' => 7], $exception->mediaLocation);
            $t->same(null, $exception->customMediaLocation);
        }
        if (!$sawUndefined) {
            throw new RuntimeException('Expected custom media undefined exception');
        }

        $source = <<<'CSS'

      @custom-media --circular-mq-a (--circular-mq-b);
      @custom-media --circular-mq-b (--circular-mq-a);

      @media (--circular-mq-a) {
        body {
          order: 3;
        }
      }
CSS;

        try {
            (new CustomMediaTransformer())->transform($source);
        } catch (CustomMediaException $exception) {
            $t->same('circular-custom-media', $exception->kind);
            $t->same('--circular-mq-a', $exception->name);
            $t->same(['line' => 4, 'column' => 7], $exception->mediaLocation);

            return;
        }

        throw new RuntimeException('Expected custom media circular exception');
    },
    'wordpress custom media transformer expands block theme breakpoints without node' => static function (TestRunner $t) use ($transformAndMinify): void {
        $css = <<<'CSS'
@custom-media --wp-mobile (max-width: 599px);
@custom-media --wp-motion (prefers-reduced-motion: no-preference);

@media (--wp-mobile) and (--wp-motion) {
  .wp-block-cover.is-style-animated {
    animation-duration: 100ms;
    color: yellow;
  }
}
CSS;

        $t->same(
            '@media (width<=599px) and (prefers-reduced-motion:no-preference){.wp-block-cover.is-style-animated{animation-duration:.1s;color:#ff0}}',
            $transformAndMinify($css)
        );
    },
    'wordpress custom media transformer rejects incompatible print aliases in screen styles' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@custom-media --wp-print-color print and (color);

@media screen and (--wp-print-color) {
  .wp-block-post-title {
    color: green;
  }
}
CSS;

        $t->throws(InvalidArgumentException::class, static fn () => (new CustomMediaTransformer())->transform($css));
    },
];
