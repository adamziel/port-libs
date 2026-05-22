<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;
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
