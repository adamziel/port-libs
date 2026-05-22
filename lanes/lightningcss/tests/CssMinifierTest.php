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
    'css minifier maps upstream transition longhand value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{transition-duration:.5s,50ms;transition-delay:.5s}',
            $minifier->minify('.foo { transition-duration: 500ms, 50ms; transition-delay: 500ms }')
        );
        $t->same(
            '.foo{transition-duration:99ms;transition-delay:99ms}',
            $minifier->minify('.foo { transition-duration: .099s; transition-delay: 99ms }')
        );
        $t->same(
            '.foo{transition-duration:.95s;transition-delay:2.95s}',
            $minifier->minify('.foo { transition-duration: calc(1s - 50ms); transition-delay: calc(1s - 50ms + 2s) }')
        );
        $t->same(
            '.foo{transition-duration:1.9s}',
            $minifier->minify('.foo { transition-duration: calc((1s - 50ms) * 2) }')
        );
        $t->same(
            '.foo{transition-duration:1.9s}',
            $minifier->minify('.foo { transition-duration: calc(2 * (1s - 50ms)) }')
        );
        $t->same(
            '.foo{transition-duration:1.1s}',
            $minifier->minify('.foo { transition-duration: calc((2s + 50ms) - (1s - 50ms)) }')
        );
        $t->same(
            '.foo{transition-timing-function:ease,ease-in,ease-out,ease-in-out}',
            $minifier->minify('.foo { transition-timing-function: cubic-bezier(0.25, 0.1, 0.25, 1), cubic-bezier(0.42, 0, 1, 1), cubic-bezier(0, 0, 0.58, 1), cubic-bezier(0.42, 0, 0.58, 1) }')
        );
        $t->same(
            '.foo{transition-timing-function:step-start,step-end,steps(5,start),steps(5,end),steps(5,jump-both)}',
            $minifier->minify('.foo { transition-timing-function: steps(1, jump-start), steps(1, end), steps(5, jump-start), steps(5, jump-end), steps(5, jump-both) }')
        );
        $t->same(
            '.foo{transition-timing-function:cubic-bezier(.58,.2,.11,1.2)}',
            $minifier->minify('.foo { transition-timing-function: cubic-bezier(0.58, 0.2, 0.11, 1.2) }')
        );
    },
    'wordpress block theme fixture minifies without breaking custom property math' => static function (TestRunner $t): void {
        $css = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-theme.css');
        $expected = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-theme.min.css');
        $t->same(trim($expected), (new CssMinifier())->minify($css));
    },
    'wordpress block style transition presets minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-button.is-style-animated .wp-element-button {
  transition-duration: calc((1s - 50ms) * 2);
  transition-delay: 500ms;
  transition-timing-function: cubic-bezier(0.42, 0, 1, 1), steps(5, jump-start);
}
CSS;

        $t->same(
            '.wp-block-button.is-style-animated .wp-element-button{transition-duration:1.9s;transition-delay:.5s;transition-timing-function:ease-in,steps(5,start)}',
            (new CssMinifier())->minify($css)
        );
    },
    'declaration parser handles semicolons and colons inside functions' => static function (TestRunner $t): void {
        $parsed = (new DeclarationBlock())->parse('background: url("https://example.test/a;b"); color: red');
        $t->same('url("https://example.test/a;b")', $parsed['background']);
        $t->same('red', $parsed['color']);
    },
];
