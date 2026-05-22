<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;
use PortLibs\LightningCSS\DeclarationBlock;

return [
    'css minifier removes comments and insignificant whitespace' => static function (TestRunner $t): void {
        $css = "article /* keep strings */ > p {\n  color : red ;\n  margin : calc( 1rem + 2px );\n}\n";
        $t->same('article>p{color:red;margin:calc(1rem + 2px)}', (new CssMinifier())->minify($css));
    },
    'css comments inside strings survive minification' => static function (TestRunner $t): void {
        $css = '.x { content: "/* not a comment */"; font-family: Open Sans, sans-serif; }';
        $t->same('.x{content:"/* not a comment */";font-family:Open Sans,sans-serif}', (new CssMinifier())->minify($css));
    },
    'css minifier shortens upstream color keywords in declaration values' => static function (TestRunner $t): void {
        $css = '.foo { color: yellow; background: linear-gradient(blue, white); border-color: black; }';
        $t->same('.foo{color:#ff0;background:linear-gradient(#00f,#fff);border-color:#000}', (new CssMinifier())->minify($css));
    },
    'css minifier preserves strings urls custom properties and calc operator spacing' => static function (TestRunner $t): void {
        $css = '.asset { background: url("/yellow/blue.svg"); content: "yellow"; --brand-color: yellow; color: var(--yellow); width: calc(100% + 8px); }';
        $t->same('.asset{background:url("/yellow/blue.svg");content:"yellow";--brand-color:yellow;color:var(--yellow);width:calc(100% + 8px)}', (new CssMinifier())->minify($css));
    },
    'css minifier maps upstream animation longhand value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{animation-duration:.1s}', $minifier->minify('.foo { animation-duration: 100ms }'));
        $t->same(
            '.foo{animation-duration:.1s,2s;animation-delay:.1s,2s}',
            $minifier->minify('.foo { animation-duration: 100ms, 2000ms; animation-delay: 100ms, 2000ms }')
        );
        $t->same(
            '.foo{animation-iteration-count:2,infinite;animation-direction:alternate,reverse;animation-play-state:running,paused}',
            $minifier->minify('.foo { animation-iteration-count: 2.0, infinite; animation-direction: Alternate, reverse; animation-play-state: running, Paused }')
        );
        $t->same(
            '.foo{animation-fill-mode:backwards,forwards;animation-composition:add}',
            $minifier->minify('.foo { animation-fill-mode: Backwards,forwards; animation-composition: ADD }')
        );
    },
    'wordpress block theme fixture minifies without breaking custom property math' => static function (TestRunner $t): void {
        $css = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-theme.css');
        $expected = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-theme.min.css');
        $t->same(trim($expected), (new CssMinifier())->minify($css));
    },
    'declaration parser handles semicolons and colons inside functions' => static function (TestRunner $t): void {
        $parsed = (new DeclarationBlock())->parse('background: url("https://example.test/a;b"); color: red');
        $t->same('url("https://example.test/a;b")', $parsed['background']);
        $t->same('red', $parsed['color']);
    },
];
