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
    'css minifier preserves upstream license comments' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            "/*! Copyright 2023 Someone awesome */\n.foo{color:red}",
            $minifier->minify(
                <<<'CSS'
/*! Copyright 2023 Someone awesome */
/* Some other comment */
.foo {
  color: red;
}
CSS
            )
        );
        $t->same(
            "/*! Copyright 2023 Someone awesome */\n/*! Copyright 2023 Someone else */\n.foo{color:red}",
            $minifier->minify(
                <<<'CSS'
/*! Copyright 2023 Someone awesome */
/*! Copyright 2023 Someone else */
.foo {
  color: red;
}
CSS
            )
        );
    },
    'css minifier preserves descendant spaces before functional pseudo classes' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $css = '.scope :is(.title, .summary) { color: blue; } .scope:not(.is-hidden) { color: red; }';

        $t->same(
            '.scope :is(.title,.summary){color:#00f}.scope:not(.is-hidden){color:red}',
            $minifier->minify($css)
        );
        $t->same(
            '.theme :global(.legacy),.theme :local(.local){color:red}',
            $minifier->minify('.theme :global(.legacy), .theme :local(.local) { color: red; }')
        );
    },
    'css minifier preserves upstream no-target nested parent-reference spaces' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{color:#00f;@nest .bar &{color:red;&.baz{color:green}}}',
            $minifier->minify('.foo { color: blue; @nest .bar & { color: red; &.baz { color: green; } } }')
        );
        $t->same(
            '.error,.invalid{&:hover>.baz{color:red}}',
            $minifier->minify('.error, .invalid { &:hover > .baz { color: red; } }')
        );
        $t->same(
            '.scope{.parent &{color:red}:not(&){color:#00f}}',
            $minifier->minify('.scope { .parent & { color: red; } :not(&) { color: blue; } }')
        );
    },
    'css minifier canonicalizes upstream no-target implicit nested selectors' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{color:#00f;& .bar{color:red}}',
            $minifier->minify('.foo { color: blue; .bar { color: red; } }')
        );
        $t->same(
            '.foo{color:red;& .bar{color:green}color:#00f;& .baz{color:pink}}',
            $minifier->minify('.foo { color: red; .bar { color: green; } color: blue; .baz { color: pink; } }')
        );
        $t->same(
            '.wp-block-query{& .wp-block-post-title,& .wp-block-post-excerpt{margin-block-start:0}}',
            $minifier->minify('.wp-block-query { .wp-block-post-title, .wp-block-post-excerpt { margin-block-start: 0; } }')
        );
    },
    'css minifier preserves upstream no-target attached nested selectors' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{color:#00f;&div{color:red}&span{color:purple}}',
            $minifier->minify('.foo { color: blue; &div { color: red; } &span { color: purple; } }')
        );
    },
    'css minifier maps upstream legacy pseudo-element colon compaction' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo:before,.foo:after{content:"::before";color:#00f}a:first-line{color:red}',
            $minifier->minify('.foo::before, .foo::after { content: "::before"; color: blue; } a::first-line { color: red; }')
        );
        $t->same(
            '.foo::placeholder{color:red}',
            $minifier->minify('.foo::placeholder { color: red; }')
        );
        $t->same(
            '.foo{--selector:.bar::before}.foo:before{color:red}',
            $minifier->minify('.foo { --selector: .bar::before; } .foo::before { color: red; }')
        );
    },
    'css minifier maps upstream attribute selector value compaction' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('[foo=baz]{color:red}', $minifier->minify('[foo="baz"] { color: red }'));
        $t->same('[foo=foo\\ bar]{color:red}', $minifier->minify('[foo="foo bar"] { color: red }'));
        $t->same('[foo="foo bar baz"]{color:red}', $minifier->minify('[foo="foo bar baz"] { color: red }'));
        $t->same('[foo=""]{color:red}', $minifier->minify('[foo=""] { color: red }'));
        $t->same('.test:not([foo=bar]){color:red}', $minifier->minify('.test:not([foo="bar"]) { color: red }'));
    },
    'css minifier shortens upstream color keywords in declaration values' => static function (TestRunner $t): void {
        $css = '.foo { color: yellow; background: linear-gradient(blue, white); border-color: black; }';
        $t->same('.foo{color:#ff0;background:linear-gradient(#00f,#fff);border-color:#000}', (new CssMinifier())->minify($css));
    },
    'css minifier maps upstream background position value normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $cases = [
            '.foo { background-position: center center; }' => '.foo{background-position:50%}',
            '.foo { background-position: bottom left }' => '.foo{background-position:0 100%}',
            '.foo { background-position: left 10px center }' => '.foo{background-position:10px}',
            '.foo { background-position: right 10px center }' => '.foo{background-position:right 10px center}',
            '.foo { background-position: center top 10px }' => '.foo{background-position:50% 10px}',
            '.foo { background-position: center bottom 10px }' => '.foo{background-position:center bottom 10px}',
            '.foo { background-position: center 10px }' => '.foo{background-position:50% 10px}',
            '.foo { background-position: right 10px top 20px }' => '.foo{background-position:right 10px top 20px}',
            '.foo { background-position: left 10px top 20px }' => '.foo{background-position:10px 20px}',
            '.foo { background-position: left 10px bottom 20px }' => '.foo{background-position:left 10px bottom 20px}',
            '.foo { background-position: left 10px top }' => '.foo{background-position:10px 0}',
            '.foo { background-position: bottom right }' => '.foo{background-position:100% 100%}',
            '.foo { background-position: center top }' => '.foo{background-position:top}',
            '.foo { background-position: center bottom }' => '.foo{background-position:bottom}',
            '.foo { background-position: left center }' => '.foo{background-position:0}',
            '.foo { background-position: right center }' => '.foo{background-position:100%}',
            '.foo { background-position: 20px center }' => '.foo{background-position:20px}',
            '.foo { background: url("img-sprite.png") no-repeat bottom right }' => '.foo{background:url(img-sprite.png) 100% 100% no-repeat}',
            '.foo { background: transparent }' => '.foo{background:0 0}',
            '.foo { background: none center }' => '.foo{background:50%}',
            '.foo { background: none }' => '.foo{background:0 0}',
            '.foo { background: url("data:image/svg+xml,%3Csvg width=\'168\' height=\'24\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3C/svg%3E") }' => '.foo{background:url("data:image/svg+xml,%3Csvg width=\'168\' height=\'24\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3C/svg%3E")}',
        ];

        foreach ($cases as $input => $expected) {
            $t->same($expected, $minifier->minify($input));
        }
    },
    'css minifier maps upstream border spacing value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{border-spacing:0}', $minifier->minify('.foo { border-spacing: 0px; }'));
        $t->same('.foo{border-spacing:0}', $minifier->minify('.foo { border-spacing: 0px 0px; }'));
        $t->same('.foo{border-spacing:12px 0}', $minifier->minify('.foo { border-spacing: 12px   0px; }'));
        $t->same('.foo{border-spacing:6px 0}', $minifier->minify('.foo { border-spacing: calc(3px * 2) calc(5px * 0); }'));
        $t->same('.foo{border-spacing:6px 8px}', $minifier->minify('.foo { border-spacing: calc(3px * 2) max(0px, 8px); }'));
        $t->same('.foo{border-spacing:-20px}', $minifier->minify('.foo { border-spacing: -20px; }'));
    },
    'css minifier maps upstream basic color value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{color:#7bffff80}', $minifier->minify('.foo { color: rgb(123 255 255 / 50%) }'));
        $t->same('.foo{color:#7affff80}', $minifier->minify('.foo { color: rgb(48% 100% 100% / 50%) }'));
        $t->same('.foo{color:#5f0}', $minifier->minify('.foo { color: hsl(100deg, 100%, 50%) }'));
        $t->same('.foo{color:#5f0}', $minifier->minify('.foo { color: hsl(100, 100%, 50%) }'));
        $t->same('.foo{color:#5f0}', $minifier->minify('.foo { color: hsl(100 100% 50%) }'));
        $t->same('.foo{color:#5f0}', $minifier->minify('.foo { color: hsl(100 100 50) }'));
        $t->same('.foo{color:#5f0c}', $minifier->minify('.foo { color: hsl(100, 100%, 50%, .8) }'));
        $t->same('.foo{color:#5f0c}', $minifier->minify('.foo { color: hsl(100 100% 50% / .8) }'));
        $t->same('.foo{color:#5f0c}', $minifier->minify('.foo { color: hsla(100, 100%, 50%, .8) }'));
        $t->same('.foo{color:#5f0c}', $minifier->minify('.foo { color: hsla(100 100% 50% / .8) }'));
        $t->same('.foo{color:#0000}', $minifier->minify('.foo { color: transparent }'));
        $t->same('.foo{color:currentColor}', $minifier->minify('.foo { color: currentColor }'));
        $t->same('.foo{color:buttonborder}', $minifier->minify('.foo { color: ButtonBorder }'));
        $t->same('.foo{color:#00c4ff}', $minifier->minify('.foo { color: hwb(194 0% 0%) }'));
        $t->same('.foo{color:#00c4ff80}', $minifier->minify('.foo { color: hwb(194 0% 0% / 50%) }'));
        $t->same('.foo{color:#006280}', $minifier->minify('.foo { color: hwb(194 0% 50%) }'));
        $t->same('.foo{color:#80e1ff}', $minifier->minify('.foo { color: hwb(194 50% 0%) }'));
        $t->same('.foo{color:#80e1ff}', $minifier->minify('.foo { color: hwb(194 50 0) }'));
        $t->same('.foo{color:gray}', $minifier->minify('.foo { color: hwb(194 50% 50%) }'));
        $t->same('.foo{color:#fff}', $minifier->minify('.foo { color: light-dark(#FFF, #FFF) }'));
        $t->same('.foo{color:#000}', $minifier->minify('.foo { color: hsl(none none none) }'));
        $t->same('.foo{color:red}', $minifier->minify('.foo { color: hwb(none none none) }'));
        $t->same('.foo{color:#000}', $minifier->minify('.foo { color: rgb(none none none) }'));
    },
    'css minifier maps upstream linear gradient value normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{background:linear-gradient(#ff0,#00f)}', $minifier->minify('.foo { background: linear-gradient(to bottom, yellow, blue); }'));
        $t->same('.foo{background:linear-gradient(#ff0,#00f)}', $minifier->minify('.foo { background: linear-gradient(180deg, yellow, blue); }'));
        $t->same('.foo{background:linear-gradient(#ff0,#00f)}', $minifier->minify('.foo { background: linear-gradient(0.5turn, yellow, blue); }'));
        $t->same('.foo{background:linear-gradient(#ff0,#00f)}', $minifier->minify('.foo { background: linear-gradient(to top, blue, yellow); }'));
        $t->same('.foo{background:linear-gradient(#ff0 80%,#00f 90%)}', $minifier->minify('.foo { background: linear-gradient(to top, blue 10%, yellow 20%); }'));
        $t->same('.foo{background:linear-gradient(0deg,#00f 10px,#ff0 20px)}', $minifier->minify('.foo { background: linear-gradient(to top, blue 10px, yellow 20px); }'));
        $t->same('.foo{background:linear-gradient(#ff0,#00f)}', $minifier->minify('.foo { background: linear-gradient(yellow, 50%, blue); }'));
        $t->same('.foo{background:linear-gradient(#ff0,red 30% 40%,#00f)}', $minifier->minify('.foo { background: linear-gradient(yellow, red 30%, red 40%, blue); }'));
        $t->same('.foo{background:linear-gradient(#00f,#ff0)}', $minifier->minify('.foo { background: linear-gradient(0, yellow, blue); }'));
    },
    'css minifier maps upstream radial and conic gradient value normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{background:radial-gradient(#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(at 0 0,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(at top left, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(5em at 0 0,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(5em circle at top left, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(circle at 100%,#333,#333 50%,#eee 75%,#333 75%)}', $minifier->minify('.foo { background: radial-gradient(circle at 100%, #333, #333 50%, #eee 75%, #333 75%) }'));
        $t->same('.foo{background:radial-gradient(circle at 100%,#333,#333 50%,#eee 75%,#333 75%)}', $minifier->minify('.foo { background: radial-gradient(farthest-corner circle at 100% 50%, #333, #333 50%, #eee 75%, #333 75%) }'));
        $t->same('.foo{background:radial-gradient(circle,#333,#333 50%,#eee 75%,#333 75%)}', $minifier->minify('.foo { background: radial-gradient(farthest-corner circle at 50% 50%, #333, #333 50%, #eee 75%, #333 75%) }'));
        $t->same('.foo{background:radial-gradient(at top,#e66465,#0000)}', $minifier->minify('.foo { background: radial-gradient(ellipse at top, #e66465, transparent) }'));
        $t->same('.foo{background:radial-gradient(20px,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(20px, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(20px,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(circle 20px, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(20px 40px,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(20px 40px, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(20px 40px,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(ellipse 20px 40px, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(30px 40px,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(ellipse calc(20px + 10px) 40px, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(circle farthest-side,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(circle farthest-side, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(circle farthest-side,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(farthest-side circle, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(farthest-side,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(ellipse farthest-side, yellow, blue) }'));
        $t->same('.foo{background:radial-gradient(farthest-side,#ff0,#00f)}', $minifier->minify('.foo { background: radial-gradient(farthest-side ellipse, yellow, blue) }'));
        $t->same('.foo{background:-webkit-radial-gradient(#ff0,#00f)}', $minifier->minify('.foo { background: -webkit-radial-gradient(yellow, blue) }'));
        $t->same('.foo{background:-moz-radial-gradient(#ff0,#00f)}', $minifier->minify('.foo { background: -moz-radial-gradient(yellow, blue) }'));
        $t->same('.foo{background:-o-radial-gradient(#ff0,#00f)}', $minifier->minify('.foo { background: -o-radial-gradient(yellow, blue) }'));
        $t->same('.foo{background:repeating-radial-gradient(20px,#ff0,#00f)}', $minifier->minify('.foo { background: repeating-radial-gradient(circle 20px, yellow, blue) }'));
        $t->same('.foo{background:-webkit-repeating-radial-gradient(20px,#ff0,#00f)}', $minifier->minify('.foo { background: -webkit-repeating-radial-gradient(circle 20px, yellow, blue) }'));
        $t->same('.foo{background:-moz-repeating-radial-gradient(20px,#ff0,#00f)}', $minifier->minify('.foo { background: -moz-repeating-radial-gradient(circle 20px, yellow, blue) }'));
        $t->same('.foo{background:-o-repeating-radial-gradient(20px,#ff0,#00f)}', $minifier->minify('.foo { background: -o-repeating-radial-gradient(circle 20px, yellow, blue) }'));
        $t->same('.foo{background:-webkit-gradient(radial,50% 50%,0,50% 50%,100,from(#00f),to(#ff0))}', $minifier->minify('.foo { background: -webkit-gradient(radial, center center, 0, center center, 100, from(blue), to(yellow)) }'));
        $t->same('.foo{background:conic-gradient(#f06,gold)}', $minifier->minify('.foo { background: conic-gradient(#f06, gold) }'));
        $t->same('.foo{background:conic-gradient(#f06,gold)}', $minifier->minify('.foo { background: conic-gradient(at 50% 50%, #f06, gold) }'));
        $t->same('.foo{background:conic-gradient(#f06,gold)}', $minifier->minify('.foo { background: conic-gradient(from 0deg, #f06, gold) }'));
        $t->same('.foo{background:conic-gradient(#f06,gold)}', $minifier->minify('.foo { background: conic-gradient(from 0, #f06, gold) }'));
        $t->same('.foo{background:conic-gradient(#f06,gold)}', $minifier->minify('.foo { background: conic-gradient(from 0deg at center, #f06, gold) }'));
        $t->same('.foo{background:conic-gradient(#fff -50%,#000 150%)}', $minifier->minify('.foo { background: conic-gradient(white -50%, black 150%) }'));
        $t->same('.foo{background:conic-gradient(#fff -180deg,#000 540deg)}', $minifier->minify('.foo { background: conic-gradient(white -180deg, black 540deg) }'));
        $t->same('.foo{background:conic-gradient(from 45deg,#fff,#000,#fff)}', $minifier->minify('.foo { background: conic-gradient(from 45deg, white, black, white) }'));
        $t->same('.foo{background:repeating-conic-gradient(from 45deg,#fff,#000,#fff)}', $minifier->minify('.foo { background: repeating-conic-gradient(from 45deg, white, black, white) }'));
        $t->same('.foo{background:repeating-conic-gradient(#000 0deg 25%,#fff 0deg 50%)}', $minifier->minify('.foo { background: repeating-conic-gradient(black 0deg 25%, white 0deg 50%) }'));
    },
    'css minifier maps upstream rgb relative color sRGB origins' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'rgb(from rebeccapurple r g b)' => '#639',
            'rgb(from rebeccapurple r g b / alpha)' => '#639',
            'rgb(from rgb(20%, 40%, 60%, 80%) r g b / alpha)' => '#369c',
            'rgb(from hsl(120deg 20% 50% / .5) r g b / alpha)' => '#66996680',
            'rgb(from rgb(from rebeccapurple r g b) r g b)' => '#639',
            'rgb(from rebeccapurple 0 0 0)' => '#000',
            'rgb(from rebeccapurple 0 0 0 / 0)' => '#0000',
            'rgb(from rebeccapurple 0 g b / alpha)' => '#039',
            'rgb(from rebeccapurple r 0 b / alpha)' => '#609',
            'rgb(from rebeccapurple r g 0 / alpha)' => '#630',
            'rgb(from rebeccapurple r g b / 0)' => '#6390',
            'rgb(from rgb(20%, 40%, 60%, 80%) 0 g b / alpha)' => '#069c',
            'rgb(from rgb(20%, 40%, 60%, 80%) r 0 b / alpha)' => '#309c',
            'rgb(from rgb(20%, 40%, 60%, 80%) r g 0 / alpha)' => '#360c',
            'rgb(from rgb(20%, 40%, 60%, 80%) r g b / 0)' => '#3690',
            'rgb(from rebeccapurple 25 g b / alpha)' => '#193399',
            'rgb(from rebeccapurple r 25 b / alpha)' => '#661999',
            'rgb(from rebeccapurple r g 25 / alpha)' => '#663319',
            'rgb(from rebeccapurple r g b / .25)' => '#66339940',
            'rgb(from rgb(20%, 40%, 60%, 80%) 25 g b / alpha)' => '#196699cc',
            'rgb(from rgb(20%, 40%, 60%, 80%) r 25 b / alpha)' => '#331999cc',
            'rgb(from rgb(20%, 40%, 60%, 80%) r g 25 / alpha)' => '#336619cc',
            'rgb(from rgb(20%, 40%, 60%, 80%) r g b / .20)' => '#3693',
            'rgb(from rebeccapurple 20% g b / alpha)' => '#339',
            'rgb(from rebeccapurple r 20% b / alpha)' => '#639',
            'rgb(from rebeccapurple r g 20% / alpha)' => '#633',
            'rgb(from rebeccapurple r g b / 20%)' => '#6393',
            'rgb(from rebeccapurple 25 g b / 25%)' => '#19339940',
            'rgb(from rebeccapurple r 25 b / 25%)' => '#66199940',
            'rgb(from rebeccapurple r g 25 / 25%)' => '#66331940',
            'rgb(from rgb(20%, 40%, 60%, 80%) 25 g b / 25%)' => '#19669940',
            'rgb(from rgb(20%, 40%, 60%, 80%) r 25 b / 25%)' => '#33199940',
            'rgb(from rgb(20%, 40%, 60%, 80%) r g 25 / 25%)' => '#33661940',
            'rgb(from rebeccapurple g b r)' => '#396',
            'rgb(from rebeccapurple b alpha r / g)' => '#990166',
            'rgb(from rebeccapurple r r r / r)' => '#666',
            'rgb(from rebeccapurple alpha alpha alpha / alpha)' => '#010101',
            'rgb(from rgb(20%, 40%, 60%, 80%) g b r)' => '#693',
            'rgb(from rgb(20%, 40%, 60%, 80%) b alpha r / g)' => '#990133',
            'rgb(from rgb(20%, 40%, 60%, 80%) r r r / r)' => '#333',
            'rgb(from rgb(20%, 40%, 60%, 80%) alpha alpha alpha / alpha)' => '#010101cc',
            'rgb(from rebeccapurple r 20% 10)' => '#66330a',
            'rgb(from rebeccapurple r 10 20%)' => '#660a33',
            'rgb(from rebeccapurple 0% 10 10)' => '#000a0a',
            'rgb(from rgb(20%, 40%, 60%, 80%) r 20% 10)' => '#33330a',
            'rgb(from rgb(20%, 40%, 60%, 80%) r 10 20%)' => '#330a33',
            'rgb(from rgb(20%, 40%, 60%, 80%) 0% 10 10)' => '#000a0a',
            'rgb(from rebeccapurple calc(r) calc(g) calc(b))' => '#639',
            'rgb(from rebeccapurple r calc(g * 2) 10)' => '#66660a',
            'rgb(from rebeccapurple b calc(r * .5) 10)' => '#99330a',
            'rgb(from rebeccapurple r calc(g * .5 + g * .5) 10)' => '#66330a',
            'rgb(from rebeccapurple r calc(b * .5 - g * .5) 10)' => '#66330a',
            'rgb(from rgb(20%, 40%, 60%, 80%) calc(r) calc(g) calc(b) / calc(alpha))' => '#369c',
            'rgb(from rebeccapurple none none none)' => '#000',
            'rgb(from rebeccapurple none none none / none)' => '#0000',
            'rgb(from rebeccapurple r g none)' => '#630',
            'rgb(from rebeccapurple r g none / alpha)' => '#630',
            'rgb(from rebeccapurple r g b / none)' => '#6390',
            'rgb(from rgb(20% 40% 60% / 80%) r g none / alpha)' => '#360c',
            'rgb(from rgb(20% 40% 60% / 80%) r g b / none)' => '#3690',
            'rgb(from rgb(none none none) r g b)' => '#000',
            'rgb(from rgb(none none none / none) r g b / alpha)' => '#0000',
            'rgb(from rgb(20% none 60%) r g b)' => '#309',
            'rgb(from rgb(20% 40% 60% / none) r g b / alpha)' => '#3690',
        ];

        foreach ($cases as $input => $expectedColor) {
            $t->same('.foo{color:' . $expectedColor . '}', $minifier->minify('.foo { color: ' . $input . '; }'));
        }
    },
    'css minifier maps upstream hsl relative color sRGB origins' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'hsl(from rebeccapurple h s l)' => '#639',
            'hsl(from rebeccapurple h s l / alpha)' => '#639',
            'hsl(from rgb(20%, 40%, 60%, 80%) h s l / alpha)' => '#369c',
            'hsl(from hsl(120deg 20% 50% / .5) h s l / alpha)' => '#66996680',
            'hsl(from hsl(from rebeccapurple h s l) h s l)' => '#639',
            'hsl(from rebeccapurple 0 0% 0%)' => '#000',
            'hsl(from rebeccapurple 0deg 0% 0% / 0)' => '#0000',
            'hsl(from rebeccapurple 0 s l / alpha)' => '#933',
            'hsl(from rebeccapurple h 0% l / alpha)' => '#666',
            'hsl(from rebeccapurple h s 0% / alpha)' => '#000',
            'hsl(from rebeccapurple h s l / 0)' => '#6390',
            'hsl(from rgb(20%, 40%, 60%, 80%) h 0% l / alpha)' => '#666c',
            'hsl(from rgb(20%, 40%, 60%, 80%) h s 0% / alpha)' => '#000c',
            'hsl(from rebeccapurple 25 s l / alpha)' => '#995e33',
            'hsl(from rebeccapurple 25deg s l / alpha)' => '#995e33',
            'hsl(from rebeccapurple h 20% l / alpha)' => '#66527a',
            'hsl(from rebeccapurple h s l / .25)' => '#66339940',
            'hsl(from rgb(20%, 40%, 60%, 80%) h 20% l / alpha)' => '#52667acc',
            'hsl(from rebeccapurple h l s)' => '#804db3',
            'hsl(from rebeccapurple h calc(alpha * 100) l / calc(s / 100))' => '#6600cc80',
            'hsl(from rebeccapurple h l l / calc(l / 100))' => '#663d8f66',
            'hsl(from rebeccapurple h calc(alpha * 100) calc(alpha * 100) / calc(alpha * 100))' => '#fff',
            'hsl(from rgb(20%, 40%, 60%, 80%) h calc(alpha * 100) l / calc(s / 100))' => '#1466b880',
            'hsl(from rebeccapurple calc(h) calc(s) calc(l))' => '#639',
            'hsl(from rgb(20%, 40%, 60%, 80%) calc(h) calc(s) calc(l) / calc(alpha))' => '#369c',
            'hsl(from rebeccapurple none none none)' => '#000',
            'hsl(from rebeccapurple none none none / none)' => '#0000',
            'hsl(from rebeccapurple h s none)' => '#000',
            'hsl(from rebeccapurple h s l / none)' => '#6390',
            'hsl(from rebeccapurple none s l / alpha)' => '#933',
            'hsl(from hsl(120deg 20% 50% / .5) h s none / alpha)' => '#00000080',
            'hsl(from hsl(120deg 20% 50% / .5) none s l / alpha)' => '#99666680',
            'hsl(from hsl(none none none) h s l)' => '#000',
            'hsl(from hsl(120deg none 50% / .5) h s l)' => 'gray',
            'hsl(from hsl(none 20% 50% / .5) h s l / alpha)' => '#99666680',
        ];

        foreach ($cases as $input => $expectedColor) {
            $t->same('.foo{color:' . $expectedColor . '}', $minifier->minify('.foo { color: ' . $input . '; }'));
        }
    },
    'css minifier maps upstream hwb relative color sRGB origins' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'hwb(from rebeccapurple h w b)' => '#639',
            'hwb(from rebeccapurple h w b / alpha)' => '#639',
            'hwb(from rgb(20%, 40%, 60%, 80%) h w b / alpha)' => '#369c',
            'hwb(from hsl(120deg 20% 50% / .5) h w b / alpha)' => '#66996680',
            'hwb(from hwb(from rebeccapurple h w b) h w b)' => '#639',
            'hwb(from rebeccapurple 0 0% 0%)' => 'red',
            'hwb(from rebeccapurple 0deg 0% 0% / 0)' => '#f000',
            'hwb(from rebeccapurple 0 w b / alpha)' => '#933',
            'hwb(from rebeccapurple h 0% b / alpha)' => '#4d0099',
            'hwb(from rebeccapurple h w 0% / alpha)' => '#93f',
            'hwb(from rebeccapurple h w b / 0)' => '#6390',
            'hwb(from rgb(20%, 40%, 60%, 80%) h 0% b / alpha)' => '#004d99cc',
            'hwb(from rgb(20%, 40%, 60%, 80%) h w 0% / alpha)' => '#39fc',
            'hwb(from rebeccapurple 25 w b / alpha)' => '#995e33',
            'hwb(from rebeccapurple 25deg w b / alpha)' => '#995e33',
            'hwb(from rebeccapurple h 20% b / alpha)' => '#639',
            'hwb(from rebeccapurple h w 20% / alpha)' => '#8033cc',
            'hwb(from rebeccapurple h w b / .2)' => '#6393',
            'hwb(from rgb(20%, 40%, 60%, 80%) h 20% b / alpha)' => '#369c',
            'hwb(from rgb(20%, 40%, 60%, 80%) h w 20% / alpha)' => '#3380cccc',
            'hwb(from rebeccapurple h b w)' => '#96c',
            'hwb(from rebeccapurple h calc(alpha * 100) w / calc(b / 100))' => '#d5d5d566',
            'hwb(from rebeccapurple h w w / calc(w / 100))' => '#8033cc33',
            'hwb(from rebeccapurple h calc(alpha * 100) calc(alpha * 100) / alpha)' => 'gray',
            'hwb(from rgb(20%, 40%, 60%, 80%) h b w)' => '#69c',
            'hwb(from rebeccapurple calc(h) calc(w) calc(b))' => '#639',
            'hwb(from rgb(20%, 40%, 60%, 80%) calc(h) calc(w) calc(b) / calc(alpha))' => '#369c',
            'hwb(from rebeccapurple none none none)' => 'red',
            'hwb(from rebeccapurple none none none / none)' => '#f000',
            'hwb(from rebeccapurple h w none)' => '#93f',
            'hwb(from rebeccapurple h w b / none)' => '#6390',
            'hwb(from rebeccapurple none w b / alpha)' => '#933',
            'hwb(from hwb(120deg 20% 50% / .5) h w none / alpha)' => '#33ff3380',
            'hwb(from hwb(120deg 20% 50% / .5) h w b / none)' => '#33803300',
            'hwb(from hwb(120deg 20% 50% / .5) none w b / alpha)' => '#80333380',
            'hwb(from hwb(none none none) h w b)' => 'red',
            'hwb(from hwb(120deg none 50% / .5) h w b)' => '#008000',
            'hwb(from hwb(none 20% 50% / .5) h w b / alpha)' => '#80333380',
        ];

        foreach ($cases as $input => $expectedColor) {
            $t->same('.foo{color:' . $expectedColor . '}', $minifier->minify('.foo { color: ' . $input . '; }'));
        }
    },
    'css minifier maps upstream relative color non-srgb origins' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color(display-p3 0 1 0)' => '#00f942',
            'lab(100% 104.3 -50.9)' => '#fff',
            'lab(0% 104.3 -50.9)' => '#2a0022',
            'lch(100% 116 334)' => '#fff',
            'lch(0% 116 334)' => '#2a0022',
            'oklab(100% 0.365 -0.16)' => '#fff',
            'oklab(0% 0.365 -0.16)' => '#000',
            'oklch(100% 0.399 336.3)' => '#fff',
            'oklch(0% 0.399 336.3)' => '#000',
        ];

        foreach ($cases as $origin => $expectedColor) {
            $t->same(
                '.foo{color:' . $expectedColor . '}',
                $minifier->minify('.foo { color: rgb(from ' . $origin . ' r g b / alpha); }')
            );
            $t->same(
                '.foo{color:' . $expectedColor . '}',
                $minifier->minify('.foo { color: hsl(from ' . $origin . ' h s l / alpha); }')
            );
            $t->same(
                '.foo{color:' . $expectedColor . '}',
                $minifier->minify('.foo { color: hwb(from ' . $origin . ' h w b / alpha); }')
            );
        }

        $t->same(
            '.foo{color:lch(0% 0 0)}',
            $minifier->minify('.foo { color: lch(from color(display-p3 0 0 0) l c h / alpha); }')
        );
        $t->same(
            '.foo{color:oklch(0% 0 0)}',
            $minifier->minify('.foo { color: oklch(from color(display-p3 0 0 0) l c h / alpha); }')
        );
    },
    'css minifier maps upstream advanced color function normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            '.foo { color: lab(29.2345% 39.3825 20.0664); }' => '.foo{color:lab(29.2345% 39.3825 20.0664)}',
            '.foo { color: lab(29.2345 39.3825 20.0664); }' => '.foo{color:lab(29.2345% 39.3825 20.0664)}',
            '.foo { color: lab(29.2345% 39.3825% 20.0664%); }' => '.foo{color:lab(29.2345% 49.2281 25.083)}',
            '.foo { color: lab(29.2345% 39.3825 20.0664 / 100%); }' => '.foo{color:lab(29.2345% 39.3825 20.0664)}',
            '.foo { color: lab(29.2345% 39.3825 20.0664 / 50%); }' => '.foo{color:lab(29.2345% 39.3825 20.0664/.5)}',
            '.foo { color: lch(29.2345% 44.2 27); }' => '.foo{color:lch(29.2345% 44.2 27)}',
            '.foo { color: lch(29.2345 44.2 27); }' => '.foo{color:lch(29.2345% 44.2 27)}',
            '.foo { color: lch(29.2345% 44.2% 27deg); }' => '.foo{color:lch(29.2345% 66.3 27)}',
            '.foo { color: lch(29.2345% 44.2 45deg); }' => '.foo{color:lch(29.2345% 44.2 45)}',
            '.foo { color: lch(29.2345% 44.2 .5turn); }' => '.foo{color:lch(29.2345% 44.2 180)}',
            '.foo { color: lch(29.2345% 44.2 27 / 100%); }' => '.foo{color:lch(29.2345% 44.2 27)}',
            '.foo { color: lch(29.2345% 44.2 27 / 50%); }' => '.foo{color:lch(29.2345% 44.2 27/.5)}',
            '.foo { color: oklab(40.101% 0.1147 0.0453); }' => '.foo{color:oklab(40.101% .1147 .0453)}',
            '.foo { color: oklab(.40101 0.1147 0.0453); }' => '.foo{color:oklab(40.101% .1147 .0453)}',
            '.foo { color: oklab(40.101% 0.1147% 0.0453%); }' => '.foo{color:oklab(40.101% .0004588 .0001812)}',
            '.foo { color: oklch(40.101% 0.12332 21.555); }' => '.foo{color:oklch(40.101% .12332 21.555)}',
            '.foo { color: oklch(.40101 0.12332 21.555); }' => '.foo{color:oklch(40.101% .12332 21.555)}',
            '.foo { color: oklch(40.101% 0.12332% 21.555); }' => '.foo{color:oklch(40.101% .00049328 21.555)}',
            '.foo { color: oklch(40.101% 0.12332 .5turn); }' => '.foo{color:oklch(40.101% .12332 180)}',
            '.foo { color: color(display-p3 1 0.5 0); }' => '.foo{color:color(display-p3 1 .5 0)}',
            '.foo { color: color(display-p3 100% 50% 0%); }' => '.foo{color:color(display-p3 1 .5 0)}',
            '.foo { color: color(xyz-d50 0.2005 0.14089 0.4472); }' => '.foo{color:color(xyz-d50 .2005 .14089 .4472)}',
            '.foo { color: color(xyz-d50 20.05% 14.089% 44.72%); }' => '.foo{color:color(xyz-d50 .2005 .14089 .4472)}',
            '.foo { color: color(xyz-d65 0.2005 0.14089 0.4472); }' => '.foo{color:color(xyz .2005 .14089 .4472)}',
            '.foo { color: color(xyz-d65 20.05% 14.089% 44.72%); }' => '.foo{color:color(xyz .2005 .14089 .4472)}',
            '.foo { color: color(xyz 0.2005 0.14089 0.4472); }' => '.foo{color:color(xyz .2005 .14089 .4472)}',
            '.foo { color: color(xyz 20.05% 14.089% 44.72%); }' => '.foo{color:color(xyz .2005 .14089 .4472)}',
            '.foo { color: color(xyz 0.2005 0 0); }' => '.foo{color:color(xyz .2005 0 0)}',
            '.foo { color: color(xyz 0 0 0); }' => '.foo{color:color(xyz 0 0 0)}',
            '.foo { color: color(xyz 0 1 0); }' => '.foo{color:color(xyz 0 1 0)}',
            '.foo { color: color(xyz 0 1 0 / 20%); }' => '.foo{color:color(xyz 0 1 0/.2)}',
            '.foo { color: color(xyz 0 0 0 / 20%); }' => '.foo{color:color(xyz 0 0 0/.2)}',
            '.foo { color: color(display-p3 100% 50% 0 / 20%); }' => '.foo{color:color(display-p3 1 .5 0/.2)}',
            '.foo { color: color(display-p3 100% 0 0 / 20%); }' => '.foo{color:color(display-p3 1 0 0/.2)}',
        ];

        foreach ($cases as $input => $expected) {
            $t->same($expected, $minifier->minify($input));
        }
    },
    'css minifier maps upstream relative color function same-space colors' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color(from color(%1$s 0.7 0.5 0.3) %1$s r g b)' => 'color(%1$s .7 .5 .3)',
            'color(from color(%1$s 0.7 0.5 0.3 / 40%%) %1$s r g b / alpha)' => 'color(%1$s .7 .5 .3/.4)',
            'color(from color(from color(%1$s 0.7 0.5 0.3) %1$s r g b) %1$s r g b)' => 'color(%1$s .7 .5 .3)',
            'color(from color(%1$s 0.7 0.5 0.3) %1$s 0 g b / alpha)' => 'color(%1$s 0 .5 .3)',
            'color(from color(%1$s 0.7 0.5 0.3) %1$s 20%% g b / 20%%)' => 'color(%1$s .2 .5 .3/.2)',
            'color(from color(%1$s 0.7 0.5 0.3) %1$s 2 3 4 / 5)' => 'color(%1$s 2 3 4)',
            'color(from color(%1$s 0.7 0.5 0.3) %1$s -2 -3 -4 / -5)' => 'color(%1$s -2 -3 -4/0)',
            'color(from color(%1$s 0.7 0.5 0.3) %1$s g b r)' => 'color(%1$s .5 .3 .7)',
            'color(from color(%1$s 0.7 0.5 0.3) %1$s b alpha r / g)' => 'color(%1$s .3 1 .7/.5)',
            'color(from color(%1$s 0.7 0.5 0.3 / 40%%) %1$s b alpha r / g)' => 'color(%1$s .3 .4 .7/.5)',
            'color(from color(%1$s 0.7 0.5 0.3 / 40%%) %1$s calc(r) calc(g) calc(b) / calc(alpha))' => 'color(%1$s .7 .5 .3/.4)',
            'color(from color(%1$s 0.7 0.5 0.3) %1$s none none none / none)' => 'color(%1$s none none none/none)',
            'color(from color(%1$s none none none / none) %1$s r g b / alpha)' => 'color(%1$s 0 0 0/0)',
            'color(from color(%1$s 0.7 none 0.3) %1$s r g b)' => 'color(%1$s .7 0 .3)',
        ];

        foreach (['srgb', 'srgb-linear', 'a98-rgb', 'rec2020', 'prophoto-rgb'] as $space) {
            foreach ($cases as $input => $expected) {
                $t->same(
                    '.foo{color:' . sprintf($expected, $space) . '}',
                    $minifier->minify('.foo { color: ' . sprintf($input, $space) . '; }')
                );
            }
        }
    },
    'css minifier maps upstream xyz relative color function same-space colors' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color(from color(%1$s 7 -20.5 100) %1$s x y z)' => 'color(%2$s 7 -20.5 100)',
            'color(from color(%1$s 7 -20.5 100 / 40%%) %1$s x y z / alpha)' => 'color(%2$s 7 -20.5 100/.4)',
            'color(from color(from color(%1$s 7 -20.5 100) %1$s x y z) %1$s x y z)' => 'color(%2$s 7 -20.5 100)',
            'color(from color(%1$s 7 -20.5 100) %1$s 0 y z / alpha)' => 'color(%2$s 0 -20.5 100)',
            'color(from color(%1$s 7 -20.5 100) %1$s 20%% y z / 20%%)' => 'color(%2$s .2 -20.5 100/.2)',
            'color(from color(%1$s 7 -20.5 100) %1$s 2 3 4 / 5)' => 'color(%2$s 2 3 4)',
            'color(from color(%1$s 7 -20.5 100) %1$s -2 -3 -4 / -5)' => 'color(%2$s -2 -3 -4/0)',
            'color(from color(%1$s 7 -20.5 100) %1$s y z x)' => 'color(%2$s -20.5 100 7)',
            'color(from color(%1$s 7 -20.5 100) %1$s x x x / x)' => 'color(%2$s 7 7 7)',
            'color(from color(%1$s 7 -20.5 100 / 40%%) %1$s calc(x) calc(y) calc(z) / calc(alpha))' => 'color(%2$s 7 -20.5 100/.4)',
            'color(from color(%1$s 7 -20.5 100) %1$s none none none / none)' => 'color(%2$s none none none/none)',
            'color(from color(%1$s none none none / none) %1$s x y z / alpha)' => 'color(%2$s 0 0 0/0)',
        ];

        foreach (['xyz' => 'xyz', 'xyz-d50' => 'xyz-d50', 'xyz-d65' => 'xyz'] as $space => $outputSpace) {
            foreach ($cases as $input => $expected) {
                $t->same(
                    '.foo{color:' . sprintf($expected, $space, $outputSpace) . '}',
                    $minifier->minify('.foo { color: ' . sprintf($input, $space, $outputSpace) . '; }')
                );
            }
        }
    },
    'css minifier maps upstream lab and oklab relative same-space colors' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            '%1$s(from %1$s(25%% 20 50) l a b)' => '%1$s(25%% 20 50)',
            '%1$s(from %1$s(25%% 20 50) l a b / alpha)' => '%1$s(25%% 20 50)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l a b / alpha)' => '%1$s(25%% 20 50/.4)',
            '%1$s(from %1$s(200%% 300 400 / 500%%) l a b / alpha)' => '%1$s(200%% 300 400)',
            '%1$s(from %1$s(-200%% -300 -400 / -500%%) l a b / alpha)' => '%1$s(0%% -300 -400/0)',
            '%1$s(from %1$s(from %1$s(25%% 20 50) l a b) l a b)' => '%1$s(25%% 20 50)',
            '%1$s(from %1$s(25%% 20 50) 0%% 0 0)' => '%1$s(0%% 0 0)',
            '%1$s(from %1$s(25%% 20 50) 0%% 0 0 / 0)' => '%1$s(0%% 0 0/0)',
            '%1$s(from %1$s(25%% 20 50) 0%% a b / alpha)' => '%1$s(0%% 20 50)',
            '%1$s(from %1$s(25%% 20 50) l 0 b / alpha)' => '%1$s(25%% 0 50)',
            '%1$s(from %1$s(25%% 20 50) l a 0 / alpha)' => '%1$s(25%% 20 0)',
            '%1$s(from %1$s(25%% 20 50) l a b / 0)' => '%1$s(25%% 20 50/0)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) 0%% a b / alpha)' => '%1$s(0%% 20 50/.4)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l 0 b / alpha)' => '%1$s(25%% 0 50/.4)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l a 0 / alpha)' => '%1$s(25%% 20 0/.4)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l a b / 0)' => '%1$s(25%% 20 50/0)',
            '%1$s(from %1$s(25%% 20 50) 35%% a b / alpha)' => '%1$s(35%% 20 50)',
            '%1$s(from %1$s(25%% 20 50) l 35 b / alpha)' => '%1$s(25%% 35 50)',
            '%1$s(from %1$s(25%% 20 50) l a 35 / alpha)' => '%1$s(25%% 20 35)',
            '%1$s(from %1$s(25%% 20 50) l a b / .35)' => '%1$s(25%% 20 50/.35)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) 35%% a b / alpha)' => '%1$s(35%% 20 50/.4)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l 35 b / alpha)' => '%1$s(25%% 35 50/.4)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l a 35 / alpha)' => '%1$s(25%% 20 35/.4)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l a b / .35)' => '%1$s(25%% 20 50/.35)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) 200%% 300 400 / 500)' => '%1$s(200%% 300 400)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) -200%% -300 -400 / -500)' => '%1$s(0%% -300 -400/0)',
            '%1$s(from %1$s(25%% 20 50) l b a)' => '%1$s(25%% 50 20)',
            '%1$s(from %1$s(25%% 20 50) l a a / a)' => '%1$s(25%% 20 20)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l b a)' => '%1$s(25%% 50 20)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l a a / a)' => '%1$s(25%% 20 20)',
            '%1$s(from %1$s(25%% 20 50) calc(l) calc(a) calc(b))' => '%1$s(25%% 20 50)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) calc(l) calc(a) calc(b) / calc(alpha))' => '%1$s(25%% 20 50/.4)',
            '%1$s(from %1$s(25%% 20 50) none none none)' => '%1$s(none none none)',
            '%1$s(from %1$s(25%% 20 50) none none none / none)' => '%1$s(none none none/none)',
            '%1$s(from %1$s(25%% 20 50) l a none)' => '%1$s(25%% 20 none)',
            '%1$s(from %1$s(25%% 20 50) l a none / alpha)' => '%1$s(25%% 20 none)',
            '%1$s(from %1$s(25%% 20 50) l a b / none)' => '%1$s(25%% 20 50/none)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l a none / alpha)' => '%1$s(25%% 20 none/.4)',
            '%1$s(from %1$s(25%% 20 50 / 40%%) l a b / none)' => '%1$s(25%% 20 50/none)',
            '%1$s(from %1$s(none none none) l a b)' => '%1$s(0%% 0 0)',
            '%1$s(from %1$s(none none none / none) l a b / alpha)' => '%1$s(0%% 0 0/0)',
            '%1$s(from %1$s(25%% none 50) l a b)' => '%1$s(25%% 0 50)',
            '%1$s(from %1$s(25%% 20 50 / none) l a b / alpha)' => '%1$s(25%% 20 50/0)',
        ];

        foreach (['lab', 'oklab'] as $space) {
            foreach ($cases as $input => $expected) {
                $t->same(
                    '.foo{color:' . sprintf($expected, $space) . '}',
                    $minifier->minify('.foo { color: ' . sprintf($input, $space) . '; }')
                );
            }
        }
    },
    'css minifier maps upstream lch and oklch relative same-space colors' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            '%1$s(from %1$s(70%% 45 30) l c h)' => '%1$s(70%% 45 30)',
            '%1$s(from %1$s(70%% 45 30) l c h / alpha)' => '%1$s(70%% 45 30)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c h / alpha)' => '%1$s(70%% 45 30/.4)',
            '%1$s(from %1$s(200%% 300 400 / 500%%) l c h / alpha)' => '%1$s(200%% 300 40)',
            '%1$s(from %1$s(-200%% -300 -400 / -500%%) l c h / alpha)' => '%1$s(0%% 0 320/0)',
            '%1$s(from %1$s(from %1$s(70%% 45 30) l c h) l c h)' => '%1$s(70%% 45 30)',
            '%1$s(from %1$s(70%% 45 30) 0%% 0 0)' => '%1$s(0%% 0 0)',
            '%1$s(from %1$s(70%% 45 30) 0%% 0 0deg)' => '%1$s(0%% 0 0)',
            '%1$s(from %1$s(70%% 45 30) 0%% 0 0 / 0)' => '%1$s(0%% 0 0/0)',
            '%1$s(from %1$s(70%% 45 30) 0%% 0 0deg / 0)' => '%1$s(0%% 0 0/0)',
            '%1$s(from %1$s(70%% 45 30) 0%% c h / alpha)' => '%1$s(0%% 45 30)',
            '%1$s(from %1$s(70%% 45 30) l 0 h / alpha)' => '%1$s(70%% 0 30)',
            '%1$s(from %1$s(70%% 45 30) l c 0 / alpha)' => '%1$s(70%% 45 0)',
            '%1$s(from %1$s(70%% 45 30) l c 0deg / alpha)' => '%1$s(70%% 45 0)',
            '%1$s(from %1$s(70%% 45 30) l c h / 0)' => '%1$s(70%% 45 30/0)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) 0%% c h / alpha)' => '%1$s(0%% 45 30/.4)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l 0 h / alpha)' => '%1$s(70%% 0 30/.4)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c 0 / alpha)' => '%1$s(70%% 45 0/.4)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c 0deg / alpha)' => '%1$s(70%% 45 0/.4)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c h / 0)' => '%1$s(70%% 45 30/0)',
            '%1$s(from %1$s(70%% 45 30) 25%% c h / alpha)' => '%1$s(25%% 45 30)',
            '%1$s(from %1$s(70%% 45 30) l 25 h / alpha)' => '%1$s(70%% 25 30)',
            '%1$s(from %1$s(70%% 45 30) l c 25 / alpha)' => '%1$s(70%% 45 25)',
            '%1$s(from %1$s(70%% 45 30) l c 25deg / alpha)' => '%1$s(70%% 45 25)',
            '%1$s(from %1$s(70%% 45 30) l c h / .25)' => '%1$s(70%% 45 30/.25)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) 25%% c h / alpha)' => '%1$s(25%% 45 30/.4)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l 25 h / alpha)' => '%1$s(70%% 25 30/.4)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c 25 / alpha)' => '%1$s(70%% 45 25/.4)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c 25deg / alpha)' => '%1$s(70%% 45 25/.4)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c h / .25)' => '%1$s(70%% 45 30/.25)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) 200%% 300 400 / 500)' => '%1$s(200%% 300 400)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) -200%% -300 -400 / -500)' => '%1$s(0%% 0 -400/0)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) 50%% 120 400deg / 500)' => '%1$s(50%% 120 400)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) 50%% 120 -400deg / -500)' => '%1$s(50%% 120 -400/0)',
            '%1$s(from %1$s(70%% 45 30) l c c / alpha)' => '%1$s(70%% 45 45)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c c / alpha)' => '%1$s(70%% 45 45/.4)',
            '%1$s(from %1$s(70%% 45 30) calc(l) calc(c) calc(h))' => '%1$s(70%% 45 30)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) calc(l) calc(c) calc(h) / calc(alpha))' => '%1$s(70%% 45 30/.4)',
            '%1$s(from %1$s(70%% 45 30) none none none)' => '%1$s(none none none)',
            '%1$s(from %1$s(70%% 45 30) none none none / none)' => '%1$s(none none none/none)',
            '%1$s(from %1$s(70%% 45 30) l c none)' => '%1$s(70%% 45 none)',
            '%1$s(from %1$s(70%% 45 30) l c none / alpha)' => '%1$s(70%% 45 none)',
            '%1$s(from %1$s(70%% 45 30) l c h / none)' => '%1$s(70%% 45 30/none)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c none / alpha)' => '%1$s(70%% 45 none/.4)',
            '%1$s(from %1$s(70%% 45 30 / 40%%) l c h / none)' => '%1$s(70%% 45 30/none)',
            '%1$s(from %1$s(none none none) l c h)' => '%1$s(0%% 0 0)',
            '%1$s(from %1$s(none none none / none) l c h / alpha)' => '%1$s(0%% 0 0/0)',
            '%1$s(from %1$s(70%% none 30) l c h)' => '%1$s(70%% 0 30)',
            '%1$s(from %1$s(70%% 45 30 / none) l c h / alpha)' => '%1$s(70%% 45 30/0)',
        ];

        foreach (['lch', 'oklch'] as $space) {
            foreach ($cases as $input => $expected) {
                $t->same(
                    '.foo{color:' . sprintf($expected, $space) . '}',
                    $minifier->minify('.foo { color: ' . sprintf($input, $space) . '; }')
                );
            }
        }

        $spaceSpecificCases = [
            'lch' => [
                'lch(from lch(70% 45 30) alpha c h / l)' => 'lch(1 45 30/1)',
                'lch(from lch(70% 45 30) alpha c h / alpha)' => 'lch(1 45 30)',
                'lch(from lch(70% 45 30) alpha c c / alpha)' => 'lch(1 45 45)',
                'lch(from lch(70% 45 30 / 40%) alpha c h / l)' => 'lch(.4 45 30/1)',
                'lch(from lch(70% 45 30 / 40%) alpha c h / alpha)' => 'lch(.4 45 30/.4)',
                'lch(from lch(70% 45 30 / 40%) alpha c c / alpha)' => 'lch(.4 45 45/.4)',
            ],
            'oklch' => [
                'oklch(from oklch(70% 45 30) alpha c h / l)' => 'oklch(1 45 30/.7)',
                'oklch(from oklch(70% 45 30) alpha c h / alpha)' => 'oklch(1 45 30)',
                'oklch(from oklch(70% 45 30) alpha c c / alpha)' => 'oklch(1 45 45)',
                'oklch(from oklch(70% 45 30 / 40%) alpha c h / l)' => 'oklch(.4 45 30/.7)',
                'oklch(from oklch(70% 45 30 / 40%) alpha c h / alpha)' => 'oklch(.4 45 30/.4)',
                'oklch(from oklch(70% 45 30 / 40%) alpha c c / alpha)' => 'oklch(.4 45 45/.4)',
            ],
        ];

        foreach ($spaceSpecificCases as $casesBySpace) {
            foreach ($casesBySpace as $input => $expected) {
                $t->same(
                    '.foo{color:' . $expected . '}',
                    $minifier->minify('.foo { color: ' . $input . '; }')
                );
            }
        }
    },
    'css minifier maps upstream lab and lch relative color srgb origins' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'lab(from indianred calc(l * .8) a b)' => 'lab(43.1402% 45.7516 23.1557)',
            'lch(from indianred calc(l + 10) c h)' => 'lch(63.9252% 51.2776 26.8448)',
            'lch(from indianred l calc(c - 50) h)' => 'lch(53.9252% 1.27763 26.8448)',
            'lch(from indianred l c calc(h + 180deg))' => 'lch(53.9252% 51.2776 206.845)',
            'lch(from orchid l 30 h)' => 'lch(62.7526% 30 326.969)',
            'lch(from peru calc(l * 0.8) c h)' => 'lch(49.8022% 54.0117 63.6804)',
            'lch(from indianred l sin(c) h)' => 'lch(53.9252% .84797 26.8448)',
            'lch(from indianred l sqrt(c) h)' => 'lch(53.9252% 7.16084 26.8448)',
            'lch(from indianred l c sin(h))' => 'lch(53.9252% 51.2776 .451575)',
            'lch(from indianred calc(10% + 20%) c h)' => 'lch(30% 51.2776 26.8448)',
            'lch(from indianred calc(10 + 20) c h)' => 'lch(30% 51.2776 26.8448)',
            'lch(from indianred l c calc(10 + 20))' => 'lch(53.9252% 51.2776 30)',
            'lch(from indianred l c calc(10deg + 20deg))' => 'lch(53.9252% 51.2776 30)',
            'lch(from indianred l c calc(10deg + 0.35rad))' => 'lch(53.9252% 51.2776 30.0535)',
        ];

        foreach ($cases as $input => $expectedColor) {
            $t->same('.foo{color:' . $expectedColor . '}', $minifier->minify('.foo { color: ' . $input . '; }'));
        }
    },
    'css minifier maps upstream color calc components in custom property values' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{--a:#80808080}', $minifier->minify('.foo { --a: rgb(50% 50% 50% / calc(100% / 2)); }'));
        $t->same('.foo{--b:#40bfbf}', $minifier->minify('.foo { --b: hsl(calc(360deg / 2) 50% 50%); }'));
        $t->same('.foo{--c:oklab(40.101% .3 .0453)}', $minifier->minify('.foo { --c: oklab(40.101% calc(0.1 + 0.2) 0.0453); }'));
        $t->same('.foo{--d:color(display-p3 .43313 .50108 .3)}', $minifier->minify('.foo { --d: color(display-p3 0.43313 0.50108 calc(0.1 + 0.2)); }'));
        $t->same('.foo{--e:gray}', $minifier->minify('.foo { --e: rgb(calc(255 / 2), calc(255 / 2), calc(255 / 2)); }'));
    },
    'css minifier maps upstream aspect-ratio value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{aspect-ratio:auto}', $minifier->minify('.foo { aspect-ratio: auto }'));
        $t->same('.foo{aspect-ratio:2/3}', $minifier->minify('.foo { aspect-ratio: 2 / 3 }'));
        $t->same('.foo{aspect-ratio:auto 2/3}', $minifier->minify('.foo { aspect-ratio: auto 2 / 3 }'));
        $t->same('.foo{aspect-ratio:auto 2/3}', $minifier->minify('.foo { aspect-ratio: 2 / 3 auto }'));
    },
    'css minifier maps upstream vertical-align value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{vertical-align:middle}', $minifier->minify('.foo { vertical-align: middle }'));
        $t->same('.foo{vertical-align:.3em}', $minifier->minify('.foo { vertical-align: 0.3em }'));
    },
    'css minifier maps upstream srgb color-mix value normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{color:#8080ff}', $minifier->minify('.foo { color: color-mix(in srgb, white, blue); }'));
        $t->same('.foo{color:#8080ff}', $minifier->minify('.foo { color: color-mix(in srgb, blue, white); }'));
        $t->same('.foo{color:gray}', $minifier->minify('.foo { color: color-mix(in srgb, rgb(128 128 none), rgb(none none 128)); }'));
        $t->same('.foo{color:gray}', $minifier->minify('.foo { color: color-mix(in srgb, rgb(50% 50% none), rgb(none none 50%)); }'));
        $t->same('.foo{color:gray}', $minifier->minify('.foo { color: color-mix(in srgb, rgb(none 50% none), rgb(50% none 50%)); }'));
        $t->same(
            '.foo{color:#89760053}',
            $minifier->minify('.foo { color: color-mix(in srgb, rgb(100% 0% 0% / 0.7) 25%, rgb(0% 100% 0% / 0.2)); }')
        );
        $t->same(
            '.foo{color:#89760042}',
            $minifier->minify('.foo { color: color-mix(in srgb, rgb(100% 0% 0% / 0.7) 20%, rgb(0% 100% 0% / 0.2) 60%); }')
        );
        $t->same(
            '.foo{color:color-mix(in srgb, currentColor, blue)}',
            $minifier->minify('.foo { color: color-mix(in srgb, currentColor, blue); }')
        );
        $t->same(
            '.foo{color:color-mix(in srgb, blue, currentColor)}',
            $minifier->minify('.foo { color: color-mix(in srgb, blue, currentColor); }')
        );
        $t->same(
            '.foo{color:color-mix(in srgb, accentcolor, blue)}',
            $minifier->minify('.foo { color: color-mix(in srgb, accentcolor, blue); }')
        );
        $t->same(
            '.foo{color:color-mix(in srgb, blue, accentcolor)}',
            $minifier->minify('.foo { color: color-mix(in srgb, blue, accentcolor); }')
        );
    },
    'css minifier maps upstream srgb color-mix missing rgb components' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{color:gray}',
            $minifier->minify('.foo { color: color-mix(in srgb, rgb(128 128 none), rgb(none none 128)); }')
        );
        $t->same(
            '.foo{color:gray}',
            $minifier->minify('.foo { color: color-mix(in srgb, rgb(50% 50% none), rgb(none none 50%)); }')
        );
        $t->same(
            '.foo{color:gray}',
            $minifier->minify('.foo { color: color-mix(in srgb, rgb(none 50% none), rgb(50% none 50%)); }')
        );
    },
    'css minifier maps upstream lab and oklab color-mix value normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color-mix(in %1$s, %1$s(10%% 20 30), %1$s(50%% 60 70))' => '%1$s(30%% 40 50)',
            'color-mix(in %1$s, %1$s(10%% 20 30) 25%%, %1$s(50%% 60 70))' => '%1$s(40%% 50 60)',
            'color-mix(in %1$s, 25%% %1$s(10%% 20 30), %1$s(50%% 60 70))' => '%1$s(40%% 50 60)',
            'color-mix(in %1$s, %1$s(10%% 20 30), 25%% %1$s(50%% 60 70))' => '%1$s(20%% 30 40)',
            'color-mix(in %1$s, %1$s(10%% 20 30), %1$s(50%% 60 70) 25%%)' => '%1$s(20%% 30 40)',
            'color-mix(in %1$s, %1$s(10%% 20 30) 25%%, %1$s(50%% 60 70) 75%%)' => '%1$s(40%% 50 60)',
            'color-mix(in %1$s, %1$s(10%% 20 30) 30%%, %1$s(50%% 60 70) 90%%)' => '%1$s(40%% 50 60)',
            'color-mix(in %1$s, %1$s(10%% 20 30) 12.5%%, %1$s(50%% 60 70) 37.5%%)' => '%1$s(40%% 50 60/.5)',
            'color-mix(in %1$s, %1$s(10%% 20 30) 0%%, %1$s(50%% 60 70))' => '%1$s(50%% 60 70)',
            'color-mix(in %1$s, %1$s(10%% 20 30 / .4), %1$s(50%% 60 70 / .8))' => '%1$s(36.6667%% 46.6667 56.6667/.6)',
            'color-mix(in %1$s, %1$s(10%% 20 30 / .4) 25%%, %1$s(50%% 60 70 / .8))' => '%1$s(44.2857%% 54.2857 64.2857/.7)',
            'color-mix(in %1$s, %1$s(10%% 20 30 / .4), %1$s(50%% 60 70 / .8) 25%%)' => '%1$s(26%% 36 46/.5)',
            'color-mix(in %1$s, %1$s(10%% 20 30 / .4) 12.5%%, %1$s(50%% 60 70 / .8) 37.5%%)' => '%1$s(44.2857%% 54.2857 64.2857/.35)',
            'color-mix(in %1$s, %1$s(none none none), %1$s(none none none))' => '%1$s(none none none)',
            'color-mix(in %1$s, %1$s(none none none), %1$s(50%% 60 70))' => '%1$s(50%% 60 70)',
            'color-mix(in %1$s, %1$s(10%% 20 none), %1$s(50%% 60 70))' => '%1$s(30%% 40 70)',
            'color-mix(in %1$s, %1$s(none 20 30), %1$s(50%% none 70))' => '%1$s(50%% 20 50)',
            'color-mix(in %1$s, %1$s(10%% 20 30 / none), %1$s(50%% 60 70 / 0.5))' => '%1$s(30%% 40 50/.5)',
            'color-mix(in %1$s, %1$s(10%% 20 30 / none), %1$s(50%% 60 70 / none))' => '%1$s(30%% 40 50/none)',
        ];

        foreach (['lab', 'oklab'] as $space) {
            foreach ($cases as $input => $expected) {
                $t->same(
                    '.foo{color:' . sprintf($expected, $space) . '}',
                    $minifier->minify('.foo { color: ' . sprintf($input, $space) . '; }')
                );
            }
        }
    },
    'css minifier maps upstream lch and oklch color-mix value normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color-mix(in %1$s, %1$s(10%% 20 30deg), %1$s(50%% 60 70deg))' => '%1$s(30%% 40 50)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg) 25%%, %1$s(50%% 60 70deg))' => '%1$s(40%% 50 60)',
            'color-mix(in %1$s, 25%% %1$s(10%% 20 30deg), %1$s(50%% 60 70deg))' => '%1$s(40%% 50 60)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg), 25%% %1$s(50%% 60 70deg))' => '%1$s(20%% 30 40)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg) 30%%, %1$s(50%% 60 70deg) 90%%)' => '%1$s(40%% 50 60)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg) 12.5%%, %1$s(50%% 60 70deg) 37.5%%)' => '%1$s(40%% 50 60/.5)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg) 0%%, %1$s(50%% 60 70deg))' => '%1$s(50%% 60 70)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg / .4), %1$s(50%% 60 70deg / .8))' => '%1$s(36.6667%% 46.6667 50/.6)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg / .4) 25%%, %1$s(50%% 60 70deg / .8))' => '%1$s(44.2857%% 54.2857 60/.7)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg / .4), 25%% %1$s(50%% 60 70deg / .8))' => '%1$s(26%% 36 40/.5)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg / .4) 12.5%%, %1$s(50%% 60 70deg / .8) 37.5%%)' => '%1$s(44.2857%% 54.2857 60/.35)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg / .4) 0%%, %1$s(50%% 60 70deg / .8))' => '%1$s(50%% 60 70/.8)',
            'color-mix(in %1$s, %1$s(none none none), %1$s(none none none))' => '%1$s(none none none)',
            'color-mix(in %1$s, %1$s(none none none), %1$s(50%% 60 70deg))' => '%1$s(50%% 60 70)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg), %1$s(none none none))' => '%1$s(10%% 20 30)',
            'color-mix(in %1$s, %1$s(10%% 20 none), %1$s(50%% 60 70deg))' => '%1$s(30%% 40 70)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg), %1$s(50%% 60 none))' => '%1$s(30%% 40 30)',
            'color-mix(in %1$s, %1$s(none 20 30deg), %1$s(50%% none 70deg))' => '%1$s(50%% 20 50)',
            'color-mix(in %1$s, %1$s(10%% 20 30deg / none), %1$s(50%% 60 70deg / none))' => '%1$s(30%% 40 50/none)',
        ];

        foreach (['lch', 'oklch'] as $space) {
            foreach ($cases as $input => $expected) {
                $t->same(
                    '.foo{color:' . sprintf($expected, $space) . '}',
                    $minifier->minify('.foo { color: ' . sprintf($input, $space) . '; }')
                );
            }
        }

        $t->same(
            '.foo{color:lch(58.8143% 141.732 218.684)}',
            $minifier->minify('.foo { color: color-mix(in lch, color(display-p3 0 1 none), color(display-p3 0 0 1)); }')
        );
    },
    'css minifier maps upstream lch and oklch color-mix hue interpolation modes' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color-mix(in %1$s, %1$s(100%% 0 50deg), %1$s(100%% 0 330deg))' => '%1$s(100%% 0 10)',
            'color-mix(in %1$s shorter hue, %1$s(100%% 0 20deg), %1$s(100%% 0 320deg))' => '%1$s(100%% 0 350)',
            'color-mix(in %1$s longer hue, %1$s(100%% 0 40deg), %1$s(100%% 0 60deg))' => '%1$s(100%% 0 230)',
            'color-mix(in %1$s longer hue, %1$s(100%% 0 20deg), %1$s(100%% 0 320deg))' => '%1$s(100%% 0 170)',
            'color-mix(in %1$s increasing hue, %1$s(100%% 0 60deg), %1$s(100%% 0 40deg))' => '%1$s(100%% 0 230)',
            'color-mix(in %1$s increasing hue, %1$s(100%% 0 330deg), %1$s(100%% 0 50deg))' => '%1$s(100%% 0 10)',
            'color-mix(in %1$s decreasing hue, %1$s(100%% 0 40deg), %1$s(100%% 0 60deg))' => '%1$s(100%% 0 230)',
            'color-mix(in %1$s decreasing hue, %1$s(100%% 0 330deg), %1$s(100%% 0 50deg))' => '%1$s(100%% 0 190)',
            'color-mix(in %1$s specified hue, %1$s(100%% 0 50deg), %1$s(100%% 0 330deg))' => '%1$s(100%% 0 190)',
        ];

        foreach (['lch', 'oklch'] as $space) {
            foreach ($cases as $input => $expected) {
                $t->same(
                    '.foo{color:' . sprintf($expected, $space) . '}',
                    $minifier->minify('.foo { color: ' . sprintf($input, $space) . '; }')
                );
            }
        }
    },
    'css minifier maps upstream hsl color-mix value normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color-mix(in hsl, hsl(120deg 10% 20%), hsl(30deg 30% 40%))' => '#545c3d',
            'color-mix(in hsl, hsl(120deg 10% 20%) 25%, hsl(30deg 30% 40%))' => '#706a43',
            'color-mix(in hsl, 25% hsl(120deg 10% 20%), hsl(30deg 30% 40%))' => '#706a43',
            'color-mix(in hsl, hsl(120deg 10% 20%), 25% hsl(30deg 30% 40%))' => '#3d4936',
            'color-mix(in hsl, hsl(120deg 10% 20%), hsl(30deg 30% 40%) 25%)' => '#3d4936',
            'color-mix(in hsl, hsl(120deg 10% 20%) 25%, hsl(30deg 30% 40%) 75%)' => '#706a43',
            'color-mix(in hsl, hsl(120deg 10% 20%) 30%, hsl(30deg 30% 40%) 90%)' => '#706a43',
            'color-mix(in hsl, hsl(120deg 10% 20%) 12.5%, hsl(30deg 30% 40%) 37.5%)' => '#706a4380',
            'color-mix(in hsl, hsl(120deg 10% 20%) 0%, hsl(30deg 30% 40%))' => '#856647',
            'color-mix(in hsl, hsl(120deg 10% 20% / .4), hsl(30deg 30% 40% / .8))' => '#5f694199',
            'color-mix(in hsl, hsl(120deg 10% 20%) 25%, hsl(30deg 30% 40% / .8))' => '#6c6742d9',
            'color-mix(in hsl, 25% hsl(120deg 10% 20% / .4), hsl(30deg 30% 40% / .8))' => '#797245b3',
            'color-mix(in hsl, hsl(120deg 10% 20% / .4), 25% hsl(30deg 30% 40% / .8))' => '#44543b80',
            'color-mix(in hsl, hsl(120deg 10% 20% / .4), hsl(30deg 30% 40% / .8) 25%)' => '#44543b80',
            'color-mix(in hsl, hsl(120deg 10% 20% / .4) 25%, hsl(30deg 30% 40% / .8) 75%)' => '#797245b3',
            'color-mix(in hsl, hsl(120deg 10% 20% / .4) 30%, hsl(30deg 30% 40% / .8) 90%)' => '#797245b3',
            'color-mix(in hsl, hsl(120deg 10% 20% / .4) 12.5%, hsl(30deg 30% 40% / .8) 37.5%)' => '#79724559',
            'color-mix(in hsl, hsl(120deg 10% 20% / .4) 0%, hsl(30deg 30% 40% / .8))' => '#856647cc',
            'color-mix(in hsl, color(display-p3 0 1 0) 100%, rgb(0, 0, 0) 0%)' => '#00f942',
            'color-mix(in hsl, lab(100% 104.3 -50.9) 100%, rgb(0, 0, 0) 0%)' => '#fff',
            'color-mix(in hsl, lab(0% 104.3 -50.9) 100%, rgb(0, 0, 0) 0%)' => '#2a0022',
            'color-mix(in hsl, lch(100% 116 334) 100%, rgb(0, 0, 0) 0%)' => '#fff',
            'color-mix(in hsl, lch(0% 116 334) 100%, rgb(0, 0, 0) 0%)' => '#2a0022',
            'color-mix(in hsl, oklab(100% 0.365 -0.16) 100%, rgb(0, 0, 0) 0%)' => '#fff',
            'color-mix(in hsl, oklab(0% 0.365 -0.16) 100%, rgb(0, 0, 0) 0%)' => '#000',
            'color-mix(in hsl, oklch(100% 0.399 336.3) 100%, rgb(0, 0, 0) 0%)' => '#fff',
            'color-mix(in hsl, oklch(0% 0.399 336.3) 100%, rgb(0, 0, 0) 0%)' => '#000',
            'color-mix(in hsl, hsl(120 100% 49.898%) 80%, yellow)' => '#33fe00',
        ];

        foreach ($cases as $input => $expected) {
            $t->same('.foo{color:' . $expected . '}', $minifier->minify('.foo { color: ' . $input . '; }'));
        }
    },
    'css minifier maps upstream hsl color-mix hue interpolation modes' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color-mix(in hsl, hsl(40deg 50% 50%), hsl(60deg 50% 50%))' => '#bfaa40',
            'color-mix(in hsl, hsl(50deg 50% 50%), hsl(330deg 50% 50%))' => '#bf5540',
            'color-mix(in hsl shorter hue, hsl(20deg 50% 50%), hsl(320deg 50% 50%))' => '#bf4055',
            'color-mix(in hsl longer hue, hsl(40deg 50% 50%), hsl(60deg 50% 50%))' => '#4055bf',
            'color-mix(in hsl increasing hue, hsl(60deg 50% 50%), hsl(40deg 50% 50%))' => '#4055bf',
            'color-mix(in hsl decreasing hue, hsl(40deg 50% 50%), hsl(60deg 50% 50%))' => '#4055bf',
            'color-mix(in hsl specified hue, hsl(50deg 50% 50%), hsl(330deg 50% 50%))' => '#40aabf',
        ];

        foreach ($cases as $input => $expected) {
            $t->same('.foo{color:' . $expected . '}', $minifier->minify('.foo { color: ' . $input . '; }'));
        }
    },
    'css minifier maps upstream hwb color-mix value normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color-mix(in hwb, hwb(120deg 10% 20%), hwb(30deg 30% 40%))' => '#93b334',
            'color-mix(in hwb, hwb(120deg 10% 20%) 25%, hwb(30deg 30% 40%))' => '#a69940',
            'color-mix(in hwb, 25% hwb(120deg 10% 20%), hwb(30deg 30% 40%))' => '#a69940',
            'color-mix(in hwb, hwb(120deg 10% 20%), 25% hwb(30deg 30% 40%))' => '#60bf27',
            'color-mix(in hwb, hwb(120deg 10% 20%), hwb(30deg 30% 40%) 25%)' => '#60bf27',
            'color-mix(in hwb, hwb(120deg 10% 20%) 25%, hwb(30deg 30% 40%) 75%)' => '#a69940',
            'color-mix(in hwb, hwb(120deg 10% 20%) 30%, hwb(30deg 30% 40%) 90%)' => '#a69940',
            'color-mix(in hwb, hwb(120deg 10% 20%) 12.5%, hwb(30deg 30% 40%) 37.5%)' => '#a6994080',
            'color-mix(in hwb, hwb(120deg 10% 20%) 0%, hwb(30deg 30% 40%))' => '#99734d',
            'color-mix(in hwb, hwb(120deg 10% 20% / .4), hwb(30deg 30% 40% / .8))' => '#8faa3c99',
            'color-mix(in hwb, hwb(120deg 10% 20% / .4) 25%, hwb(30deg 30% 40% / .8))' => '#a09546b3',
            'color-mix(in hwb, 25% hwb(120deg 10% 20% / .4), hwb(30deg 30% 40% / .8))' => '#a09546b3',
            'color-mix(in hwb, hwb(120deg 10% 20%), 25% hwb(30deg 30% 40% / .8))' => '#5fc125f2',
            'color-mix(in hwb, hwb(120deg 10% 20% / .4), hwb(30deg 30% 40% / .8) 25%)' => '#62b82e80',
            'color-mix(in hwb, hwb(120deg 10% 20% / .4) 25%, hwb(30deg 30% 40% / .8) 75%)' => '#a09546b3',
            'color-mix(in hwb, hwb(120deg 10% 20% / .4) 30%, hwb(30deg 30% 40% / .8) 90%)' => '#a09546b3',
            'color-mix(in hwb, hwb(120deg 10% 20% / .4) 12.5%, hwb(30deg 30% 40% / .8) 37.5%)' => '#a0954659',
            'color-mix(in hwb, hwb(120deg 10% 20% / .4) 0%, hwb(30deg 30% 40% / .8))' => '#99734dcc',
        ];

        foreach ($cases as $input => $expected) {
            $t->same('.foo{color:' . $expected . '}', $minifier->minify('.foo { color: ' . $input . '; }'));
        }
    },
    'css minifier maps upstream hwb color-mix advanced origin normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            'color-mix(in hwb, color(display-p3 0 1 0) 100%, rgb(0, 0, 0) 0%)' => '#00f942',
            'color-mix(in hwb, lab(100% 104.3 -50.9) 100%, rgb(0, 0, 0) 0%)' => '#fff',
            'color-mix(in hwb, lab(0% 104.3 -50.9) 100%, rgb(0, 0, 0) 0%)' => '#2a0022',
            'color-mix(in hwb, lch(100% 116 334) 100%, rgb(0, 0, 0) 0%)' => '#fff',
            'color-mix(in hwb, lch(0% 116 334) 100%, rgb(0, 0, 0) 0%)' => '#2a0022',
            'color-mix(in hwb, oklab(100% 0.365 -0.16) 100%, rgb(0, 0, 0) 0%)' => '#fff',
            'color-mix(in hwb, oklab(0% 0.365 -0.16) 100%, rgb(0, 0, 0) 0%)' => '#000',
            'color-mix(in hwb, oklch(100% 0.399 336.3) 100%, rgb(0, 0, 0) 0%)' => '#fff',
            'color-mix(in hwb, oklch(0% 0.399 336.3) 100%, rgb(0, 0, 0) 0%)' => '#000',
        ];

        foreach ($cases as $input => $expected) {
            $t->same('.foo{color:' . $expected . '}', $minifier->minify('.foo { color: ' . $input . '; }'));
        }
    },
    'css minifier maps upstream color function color-mix value normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $spaces = [
            'srgb-linear' => 'srgb-linear',
            'xyz' => 'xyz',
            'xyz-d50' => 'xyz-d50',
            'xyz-d65' => 'xyz',
        ];

        foreach ($spaces as $inputSpace => $outputSpace) {
            $cases = [
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3), color({$inputSpace} .5 .6 .7))" => "color({$outputSpace} .3 .4 .5)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3) 25%, color({$inputSpace} .5 .6 .7))" => "color({$outputSpace} .4 .5 .6)",
                "color-mix(in {$inputSpace}, 25% color({$inputSpace} .1 .2 .3), color({$inputSpace} .5 .6 .7))" => "color({$outputSpace} .4 .5 .6)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3), color({$inputSpace} .5 .6 .7) 25%)" => "color({$outputSpace} .2 .3 .4)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3), 25% color({$inputSpace} .5 .6 .7))" => "color({$outputSpace} .2 .3 .4)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3 / .5), color({$inputSpace} .5 .6 .7 / .8))" => "color({$outputSpace} .346154 .446154 .546154/.65)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3 / .4) 25%, color({$inputSpace} .5 .6 .7 / .8))" => "color({$outputSpace} .442857 .542857 .642857/.7)",
                "color-mix(in {$inputSpace}, 25% color({$inputSpace} .1 .2 .3 / .4), color({$inputSpace} .5 .6 .7 / .8))" => "color({$outputSpace} .442857 .542857 .642857/.7)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3 / .4), 25% color({$inputSpace} .5 .6 .7 / .8))" => "color({$outputSpace} .26 .36 .46/.5)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3) 12.5%, color({$inputSpace} .5 .6 .7) 37.5%)" => "color({$outputSpace} .4 .5 .6/.5)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3 / .4) 12.5%, color({$inputSpace} .5 .6 .7 / .8) 37.5%)" => "color({$outputSpace} .442857 .542857 .642857/.35)",
                "color-mix(in {$inputSpace}, color({$inputSpace} 2 3 4 / 5), color({$inputSpace} 4 6 8 / 10))" => "color({$outputSpace} 3 4.5 6)",
                "color-mix(in {$inputSpace}, color({$inputSpace} -2 -3 -4), color({$inputSpace} -4 -6 -8))" => "color({$outputSpace} -3 -4.5 -6)",
                "color-mix(in {$inputSpace}, color({$inputSpace} -2 -3 -4 / -5), color({$inputSpace} -4 -6 -8 / -10))" => "color({$outputSpace} 0 0 0/0)",
                "color-mix(in {$inputSpace}, color({$inputSpace} none none none), color({$inputSpace} .5 .6 .7))" => "color({$outputSpace} .5 .6 .7)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 none), color({$inputSpace} .5 .6 .7))" => "color({$outputSpace} .3 .4 .7)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3), color({$inputSpace} .5 .6 none))" => "color({$outputSpace} .3 .4 .3)",
                "color-mix(in {$inputSpace}, color({$inputSpace} none .2 .3), color({$inputSpace} .5 none .7))" => "color({$outputSpace} .5 .2 .5)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3 / none), color({$inputSpace} .5 .6 .7 / 0.5))" => "color({$outputSpace} .3 .4 .5/.5)",
                "color-mix(in {$inputSpace}, color({$inputSpace} .1 .2 .3 / none), color({$inputSpace} .5 .6 .7 / none))" => "color({$outputSpace} .3 .4 .5/none)",
            ];

            foreach ($cases as $input => $expected) {
                $t->same('.foo{color:' . $expected . '}', $minifier->minify('.foo { color: ' . $input . '; }'));
            }
        }

        $t->same(
            '.foo{color:color(xyz .0771883 .154377 .0257295/.65)}',
            $minifier->minify('.foo { color: color-mix(in xyz, transparent, green 65%); }')
        );
    },
    'css minifier maps upstream non-srgb named color-mix normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            '.foo { color: color-mix(in lab, purple 50%, plum 50%); }' => '.foo{color:lab(51.5117% 43.3777 -29.0443)}',
            '.foo { color: color-mix(in lch, peru 40%, palegoldenrod); }' => '.foo{color:lch(79.7255% 40.4542 84.7634)}',
            '.foo { color: color-mix(in lch, teal 65%, olive); }' => '.foo{color:lch(49.4431% 40.4806 162.546)}',
            '.foo { color: color-mix(in lch, white, black); }' => '.foo{color:lch(50% 0 none)}',
            '.foo { color: color-mix(in xyz, rgb(82.02% 30.21% 35.02%) 75.23%, rgb(5.64% 55.94% 85.31%)); }' => '.foo{color:color(xyz .287458 .208776 .260566)}',
            '.foo { color: color-mix(in lch, white, blue); }' => '.foo{color:lch(64.7842% 65.6007 301.364)}',
            '.foo { color: color-mix(in oklch, white, blue); }' => '.foo{color:oklch(72.6007% .156607 264.052)}',
            '.foo { color: color-mix(in lch, blue, white); }' => '.foo{color:lch(64.7842% 65.6007 301.364)}',
            '.foo { color: color-mix(in oklch, blue, white); }' => '.foo{color:oklch(72.6007% .156607 264.052)}',
            '.foo { color: color-mix(in lch, color(display-p3 0 1 none), color(display-p3 0 0 1)); }' => '.foo{color:lch(58.8143% 141.732 218.684)}',
            '.foo { --color: color-mix(in lch, teal 65%, olive); }' => '.foo{--color:lch(49.4431% 40.4806 162.546)}',
        ];

        foreach ($cases as $css => $expected) {
            $t->same($expected, $minifier->minify($css));
        }
    },
    'css minifier maps upstream color-scheme value ordering' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{color-scheme:normal}', $minifier->minify('.foo { color-scheme: normal; }'));
        $t->same('.foo{color-scheme:light}', $minifier->minify('.foo { color-scheme: light; }'));
        $t->same('.foo{color-scheme:dark}', $minifier->minify('.foo { color-scheme: dark; }'));
        $t->same('.foo{color-scheme:light dark}', $minifier->minify('.foo { color-scheme: light dark; }'));
        $t->same('.foo{color-scheme:light dark}', $minifier->minify('.foo { color-scheme: dark light; }'));
        $t->same('.foo{color-scheme:light only}', $minifier->minify('.foo { color-scheme: only light; }'));
        $t->same('.foo{color-scheme:dark only}', $minifier->minify('.foo { color-scheme: only dark; }'));
        $t->same('.foo{color-scheme:inherit}', $minifier->minify('.foo { color-scheme: inherit; }'));
        $t->same(':root{color-scheme:unset}', $minifier->minify(':root { color-scheme: unset; }'));
        $t->same('.foo{color-scheme:unknow}', $minifier->minify('.foo { color-scheme: unknow; }'));
        $t->same('.foo{color-scheme:only}', $minifier->minify('.foo { color-scheme: only; }'));
        $t->same('.foo{color-scheme:dark foo}', $minifier->minify('.foo { color-scheme: dark foo; }'));
        $t->same('.foo{color-scheme:normal dark}', $minifier->minify('.foo { color-scheme: normal dark; }'));
        $t->same('.foo{color-scheme:light dark only}', $minifier->minify('.foo { color-scheme: dark light only; }'));
        $t->same('.foo{color-scheme:foo bar light}', $minifier->minify('.foo { color-scheme: foo bar light; }'));
        $t->same('.foo{color-scheme:only foo dark bar}', $minifier->minify('.foo { color-scheme: only foo dark bar; }'));
    },
    'css minifier maps upstream light-dark color function minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{color:light-dark(#ff0,red)}',
            $minifier->minify('.foo { color: light-dark(yellow, red); }')
        );
        $t->same(
            '.foo{color:light-dark(#ff0,red)}',
            $minifier->minify('.foo { color: light-dark(light-dark(yellow, red), light-dark(yellow, red)); }')
        );
        $t->same(
            '.foo{color:light-dark(#00f,#40bf40)}',
            $minifier->minify('.foo { color: light-dark(rgb(0, 0, 255), hsl(120deg, 50%, 50%)); }')
        );
    },
    'css minifier maps upstream all reset declaration pruning' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{all:initial}', $minifier->minify('.foo { all: initial; all: initial }'));
        $t->same('.foo{all:revert}', $minifier->minify('.foo { all: initial; all: revert }'));
        $t->same('.foo{all:revert-layer}', $minifier->minify('.foo { background: red; all: revert-layer }'));
        $t->same(
            '.foo{all:revert-layer;background:green}',
            $minifier->minify('.foo { background: red; all: revert-layer; background: green }')
        );
        $t->same(
            '.foo{--test:red;all:revert-layer}',
            $minifier->minify('.foo { --test: red; all: revert-layer }')
        );
        $t->same(
            '.foo{all:revert-layer;unicode-bidi:embed}',
            $minifier->minify('.foo { unicode-bidi: embed; all: revert-layer }')
        );
        $t->same(
            '.foo{all:revert-layer;direction:rtl}',
            $minifier->minify('.foo { direction: rtl; all: revert-layer }')
        );
        $t->same(
            '.foo{all:revert-layer;direction:ltr}',
            $minifier->minify('.foo { direction: rtl; all: revert-layer; direction: ltr }')
        );
        $t->same('.foo{all:unset}', $minifier->minify('.foo { background: var(--foo); all: unset; }'));
        $t->same(
            '.foo{all:unset;background:var(--foo)}',
            $minifier->minify('.foo { all: unset; background: var(--foo); }')
        );
        $t->same(
            '.foo{--bar:currentcolor;--foo:1.1em;all:unset}',
            $minifier->minify('.foo {--bar:currentcolor; --foo:1.1em; all:unset}')
        );
    },
    'css minifier maps upstream font-family string serialization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{font-family:"sans-serif"}', $minifier->minify(".foo { font-family: 'sans-serif'; }"));
        $t->same('.foo{font-family:sans-serif}', $minifier->minify('.foo { font-family: sans-serif; }'));
        $t->same('.foo{font-family:"default"}', $minifier->minify(".foo { font-family: 'default'; }"));
        $t->same('.foo{font-family:default}', $minifier->minify('.foo { font-family: default; }'));
        $t->same('.foo{font-family:"inherit"}', $minifier->minify(".foo { font-family: 'inherit'; }"));
        $t->same('.foo{font-family:inherit}', $minifier->minify('.foo { font-family: inherit; }'));
        $t->same('.foo{font-family:inherit test}', $minifier->minify('.foo { font-family: inherit test; }'));
        $t->same('.foo{font-family:inherit test}', $minifier->minify(".foo { font-family: 'inherit test'; }"));
        $t->same('.foo{font-family:revert}', $minifier->minify('.foo { font-family: revert; }'));
        $t->same('.foo{font-family:"revert"}', $minifier->minify(".foo { font-family: 'revert'; }"));
        $t->same('.foo{font-family:revert-layer}', $minifier->minify('.foo { font-family: revert-layer; }'));
        $t->same('.foo{font-family:revert-layer,serif}', $minifier->minify('.foo { font-family: revert-layer, serif; }'));
        $t->same('.foo{font-family:"revert",sans-serif}', $minifier->minify(".foo { font-family: 'revert', sans-serif; }"));
        $t->same('.foo{font-family:"revert",foo,sans-serif}', $minifier->minify(".foo { font-family: 'revert', foo, sans-serif; }"));
        $t->same('.foo{font-family:""}', $minifier->minify(".foo { font-family: ''; }"));
        $t->same('@font-face{font-family:"revert"}', $minifier->minify("@font-face { font-family: 'revert'; }"));
        $t->same('@font-face{font-family:"revert-layer"}', $minifier->minify("@font-face { font-family: 'revert-layer'; }"));
        $t->same(
            '.foo{font-family:Helvetica,Times New Roman,sans-serif;font-size:12px;font-stretch:125%}',
            $minifier->minify('.foo { font-family: "Helvetica", "Times New Roman", sans-serif; font-size: 12px; font-stretch: expanded; }')
        );
    },
    'css minifier maps upstream font shorthand composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{font:italic small-caps 700 125% 12px/1.2em Helvetica,Times New Roman,sans-serif}',
            $minifier->minify(
                '.foo { font-family: "Helvetica", "Times New Roman", sans-serif; font-size: 12px; font-weight: bold; font-style: italic; font-stretch: expanded; font-variant-caps: small-caps; line-height: 1.2em; }'
            )
        );
        $t->same(
            '.foo{font:italic 700 125% 12px/1.2em Helvetica,Times New Roman,sans-serif;font-variant-caps:all-small-caps}',
            $minifier->minify(
                '.foo { font-family: "Helvetica", "Times New Roman", sans-serif; font-size: 12px; font-weight: bold; font-style: italic; font-stretch: expanded; font-variant-caps: all-small-caps; line-height: 1.2em; }'
            )
        );
        $t->same(
            '.foo{font:12px/1.2em Helvetica,Times New Roman,sans-serif}',
            $minifier->minify('.foo { font: 12px "Helvetica", "Times New Roman", sans-serif; line-height: 1.2em; }')
        );
        $t->same(
            '.foo{font:12px Helvetica,Times New Roman,sans-serif;line-height:var(--lh)}',
            $minifier->minify('.foo { font: 12px "Helvetica", "Times New Roman", sans-serif; line-height: var(--lh); }')
        );
    },
    'css minifier maps upstream font shorthand default omission' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{font:600 9px Charcoal}', $minifier->minify('.foo { font: normal normal 600 9px/normal Charcoal; }'));
        $t->same('.foo{font:500 medium Charcoal}', $minifier->minify('.foo { font: normal normal 500 medium/normal Charcoal; }'));
        $t->same('.foo{font:400 medium Charcoal}', $minifier->minify('.foo { font: normal normal 400 medium Charcoal; }'));
        $t->same('.foo{font:500 medium/10px Charcoal}', $minifier->minify('.foo { font: normal normal 500 medium/10px Charcoal; }'));
    },
    'css minifier maps upstream font-face src descriptors and unicode ranges' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@font-face{src:url(test.woff);font-family:Helvetica;font-weight:700;font-style:italic}',
            $minifier->minify(
                '@font-face { src: url("test.woff"); font-family: "Helvetica"; font-weight: bold; font-style: italic; }'
            )
        );
        $t->same('@font-face{src:url(test.woff)}', $minifier->minify('@font-face {src: url(test.woff);}'));
        $t->same('@font-face{src:local(Test)}', $minifier->minify('@font-face {src: local("Test");}'));
        $t->same('@font-face{src:local(Foo Bar)}', $minifier->minify('@font-face {src: local("Foo Bar");}'));
        $t->same('@font-face{src:local(Test)}', $minifier->minify('@font-face {src: local(Test);}'));
        $t->same('@font-face{src:local(Foo Bar)}', $minifier->minify('@font-face {src: local(Foo Bar);}'));
        $t->same(
            '@font-face{src:url(test.woff)format("woff")}',
            $minifier->minify('@font-face {src: url("test.woff") format(woff);}')
        );
        $t->same(
            '@font-face{src:url(test.ttc)format("collection"),url(test.ttf)format("truetype")}',
            $minifier->minify('@font-face {src: url("test.ttc") format(collection), url(test.ttf) format(truetype);}')
        );
        $t->same(
            '@font-face{src:url(test.otf)format("opentype")tech(features-aat)}',
            $minifier->minify('@font-face {src: url("test.otf") format(opentype) tech(features-aat);}')
        );
        $t->same(
            '@font-face{src:url(test.woff)format("woff")tech(color-colrv1)}',
            $minifier->minify('@font-face {src: url("test.woff") format(woff) tech(color-colrv1);}')
        );
        $t->same(
            '@font-face{src:url(test.woff2)format("woff2")tech(variations)}',
            $minifier->minify('@font-face {src: url("test.woff2") format(woff2) tech(variations);}')
        );
        $t->same(
            '@font-face{src:url(test.woff)format("woff")tech(palettes)}',
            $minifier->minify('@font-face {src: url("test.woff") format(woff) tech(palettes);}')
        );
        $t->same(
            '@font-face{src:url(foo.ttf)format("opentype")tech(color-colrv1)}',
            $minifier->minify('@font-face {src: url("foo.ttf") format(opentype) tech(color-colrv1);}')
        );
        $t->same(
            '@font-face{src:url(test.woff)format("woff")tech(features-opentype,color-sbix)}',
            $minifier->minify('@font-face {src: url("test.woff") format(woff) tech(features-opentype, color-sbix);}')
        );
        $t->same(
            '@font-face{src:url(test.woff)format("woff")tech(incremental,color-svg,features-graphite,features-aat)}',
            $minifier->minify('@font-face {src: url("test.woff")   format(woff)    tech(incremental, color-svg, features-graphite, features-aat);}')
        );
        $t->same(
            '@font-face{src:url(foo.ttf)tech(color-svg)}',
            $minifier->minify('@font-face {src: url("foo.ttf") tech(color-SVG);}')
        );
        $t->same(
            '@font-face{src:url(foo.ttf) tech(palettes color-colrv0 variations) format(opentype)}',
            $minifier->minify('@font-face {src: url("foo.ttf") tech(palettes  color-colrv0  variations) format(opentype);}')
        );
        $t->same(
            '@font-face{src:local("") url(test.woff)}',
            $minifier->minify('@font-face {src: local("") url("test.woff");}')
        );
        $t->same('@font-face{font-weight:200 400}', $minifier->minify('@font-face {font-weight: 200 400}'));
        $t->same('@font-face{font-weight:400}', $minifier->minify('@font-face {font-weight: 400 400}'));
        $t->same('@font-face{font-stretch:50% 200%}', $minifier->minify('@font-face {font-stretch: 50% 200%}'));
        $t->same('@font-face{font-stretch:50%}', $minifier->minify('@font-face {font-stretch: 50% 50%}'));
        $t->same('@font-face{unicode-range:U+26}', $minifier->minify('@font-face {unicode-range: u+26;}'));
        $t->same('@font-face{unicode-range:U+26}', $minifier->minify('@font-face {unicode-range: U+26;}'));
        $t->same('@font-face{unicode-range:U+0-7F}', $minifier->minify('@font-face {unicode-range: U+0-7F;}'));
        $t->same('@font-face{unicode-range:U+25-FF}', $minifier->minify('@font-face {unicode-range: U+0025-00FF;}'));
        $t->same('@font-face{unicode-range:U+4??}', $minifier->minify('@font-face {unicode-range: U+4??;}'));
        $t->same('@font-face{unicode-range:U+4??}', $minifier->minify('@font-face {unicode-range: U+400-4FF;}'));
        $t->same(
            '@font-face{unicode-range:U+25-FF,U+4??}',
            $minifier->minify('@font-face {unicode-range: U+0025-00FF, U+4??;}')
        );
        $t->same(
            '@font-face{unicode-range:U+A5,U+4E00-9FFF,U+30??,U+FF00-FF9F}',
            $minifier->minify('@font-face {unicode-range: U+A5, U+4E00-9FFF, U+30??, U+FF00-FF9F;}')
        );
        $t->same('@font-face{unicode-range:U+????}', $minifier->minify('@font-face {unicode-range: U+0000-FFFF;}'));
        $t->same('@font-face{unicode-range:U+10????}', $minifier->minify('@font-face {unicode-range: U+10????;}'));
        $t->same('@font-face{unicode-range:U+10????}', $minifier->minify('@font-face {unicode-range: U+100000-10FFFF;}'));
        $t->same('@font-face{unicode-range:U+1E1E?}', $minifier->minify('@font-face {unicode-range: U+1e1e?;}'));
        $t->same('@font-face{unicode-range:U+????}', $minifier->minify('@font-face {unicode-range: U+????;}'));
        $t->same(
            '@font-face{unicode-range:U+????,U+1????,U+10????}',
            $minifier->minify('@font-face {unicode-range: u+????, U+1????, U+10????;}')
        );
        $t->same(
            '@font-face{font-family:Inter;font-style:oblique 0deg 10deg;font-weight:100 900;src:url(../fonts/Inter.var.woff2?v=3.19)format("woff2");font-display:swap}',
            $minifier->minify(
                '@font-face { font-family: Inter; font-style: oblique 0deg 10deg; font-weight: 100 900; src: url("../fonts/Inter.var.woff2?v=3.19") format("woff2"); font-display: swap; }'
            )
        );
        $t->same(
            '@font-face{font-family:Inter;font-style:oblique;font-weight:100 900;src:url(../fonts/Inter.var.woff2?v=3.19)format("woff2");font-display:swap}',
            $minifier->minify(
                '@font-face { font-family: Inter; font-style: oblique 14deg 14deg; font-weight: 100 900; src: url("../fonts/Inter.var.woff2?v=3.19") format("woff2"); font-display: swap; }'
            )
        );
    },
    'css minifier maps upstream font palette values minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@font-palette-values --Cooler{font-family:Bixa;base-palette:1;override-colors:1 #7eb7e4}',
            $minifier->minify(
                '@font-palette-values --Cooler { font-family: Bixa; base-palette: 1; override-colors: 1 #7EB7E4; }'
            )
        );
        $t->same(
            '@font-palette-values --Cooler{font-family:Handover Sans;base-palette:3;override-colors:1 #2b0c09,3 #0f0}',
            $minifier->minify(
                '@font-palette-values --Cooler { font-family: Handover Sans; base-palette: 3; override-colors: 1 rgb(43, 12, 9), 3 lime; }'
            )
        );
        $t->same(
            '@font-palette-values --Cooler{font-family:Handover Sans;base-palette:3;override-colors:1 #2b0c09, 3 var(--highlight)}',
            $minifier->minify(
                '@font-palette-values --Cooler { font-family: Handover Sans; base-palette: 3; override-colors: 1 rgb(43, 12, 9), 3 var(--highlight); }'
            )
        );
        $t->same('.foo{font-palette:--Custom}', $minifier->minify('.foo { font-palette: --Custom; }'));
    },
    'css minifier maps upstream font feature values minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@font-feature-values Fancy Font Name{@styleset{cursive:1;swoopy:7 16}@character-variant{ampersand:1;capital-q:2}@stylistic{two-story-g:1;straight-y:2}@swash{swishy:1;flowing:2}@ornaments{clover:1;fleuron:2}@annotation{circled:1;boxed:2}}',
            $minifier->minify(
                '@font-feature-values "Fancy Font Name" { @styleset { cursive: 1; swoopy: 7 16; } @character-variant { ampersand: 1; capital-q: 2; } @stylistic { two-story-g: 1; straight-y: 2; } @swash { swishy: 1; flowing: 2; } @ornaments { clover: 1; fleuron: 2; } @annotation { circled: 1; boxed: 2; } }'
            )
        );
        $t->same(
            '@font-feature-values Inter,Inter var,Inter var experimental{@styleset{open-digits:1;disambiguation:2;curved-r:3;disambiguation-without-zero:4}@character-variant{alt-one:1;open-four:2;open-six:3;open-nine:4;lower-l-with-tail:5;curved-lower-r:6;german-double-s:7;upper-i-with-serif:8;flat-top-three:9;upper-g-with-spur:10;single-storey-a:11}}',
            $minifier->minify(
                '@font-feature-values "Inter", "Inter var", "Inter var experimental" { @styleset { open-digits: 1; disambiguation: 2; curved-r: 3; disambiguation-without-zero: 4; } @character-variant { alt-one: 1; open-four: 2; open-six: 3; open-nine: 4; lower-l-with-tail: 5; curved-lower-r: 6; german-double-s: 7; upper-i-with-serif: 8; flat-top-three: 9; upper-g-with-spur: 10; single-storey-a: 11; } }'
            )
        );
        $t->same(
            '@font-feature-values Inconsolata LGC{@styleset{alternative-umlaut:1}@character-variant{zero-plain:1 1;zero-dotted:1 2;zero-longslash:1 3;r-with-serif:2 1;eng-descender:3 1;eng-uppercase:3 2;dollar-open:4 1;dollar-oldstyle:4 2;dollar-cifrao:4 2;ezh-no-descender:5 1;ezh-reversed-sigma:5 2;triangle-text-form:6 1;el-with-hook-old:7 1;qa-enlarged-lowercase:8 1;qa-reversed-p:8 2;che-with-hook:9 1;che-with-hook-alt:9 2;ge-with-hook:10 1;ge-with-hook-alt:10 2;ge-with-stroke-and-descender:11 1}}',
            $minifier->minify(
                '@font-feature-values "Inconsolata LGC" { @styleset { alternative-umlaut: 1; } @character-variant { zero-plain: 1 1; zero-dotted: 1 2; zero-longslash: 1 3; r-with-serif: 2 1; eng-descender: 3 1; eng-uppercase: 3 2; dollar-open: 4 1; dollar-oldstyle: 4 2; dollar-cifrao: 4 2; ezh-no-descender: 5 1; ezh-reversed-sigma: 5 2; triangle-text-form: 6 1; el-with-hook-old: 7 1; qa-enlarged-lowercase: 8 1; qa-reversed-p: 8 2; che-with-hook: 9 1; che-with-hook-alt: 9 2; ge-with-hook: 10 1; ge-with-hook-alt: 10 2; ge-with-stroke-and-descender: 11 1; } }'
            )
        );
        $t->same(
            '@font-feature-values Fancy Font Name{@styleset{cursive:1;swoopy:7 16}@character-variant{ampersand:1;capital-q:2}}',
            $minifier->minify(
                '@font-feature-values "Fancy Font Name" { @styleset { cursive: 1; swoopy: 7 16; } @character-variant { ampersand: 1; capital-q: 2; } }'
            )
        );
        $t->same(
            '@font-feature-values foo{@swash{pretty:1;cool:2}}',
            $minifier->minify('@font-feature-values foo { @swash { pretty: 0; pretty: 1; cool: 2; } }')
        );
        $t->same(
            '@font-feature-values foo{@swash{pretty:1;cool:2}}',
            $minifier->minify('@font-feature-values foo { @swash { pretty: 1; } @swash { cool: 2; } }')
        );
        $t->same(
            '@font-feature-values foo{@swash{pretty:1;cool:2}}',
            $minifier->minify('@font-feature-values foo { @swash { pretty: 1; } } @font-feature-values foo { @swash { cool: 2; } }')
        );
    },
    'css minifier maps upstream grid track area and placement values' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{grid-template-columns:[first nav-start]150px[main-start]1fr[last]}',
            $minifier->minify('.foo { grid-template-columns: [first nav-start]  150px [main-start] 1fr [last]; }')
        );
        $t->same(
            '.foo{grid-template-columns:repeat(4,[col-start]250px[col-end])}',
            $minifier->minify('.foo { grid-template-columns: repeat(4, [col-start] 250px [col-end]); }')
        );
        $t->same(
            '.foo{grid-template-columns:repeat(auto-fill,[col-start]minmax(100px,1fr)[col-end])}',
            $minifier->minify('.foo { grid-template-columns: repeat(auto-fill, [col-start] minmax(100px, 1fr) [col-end]); }')
        );
        $t->same(
            '.foo{grid-auto-rows:100px minmax(100px,auto) 10% .5fr fit-content(400px)}',
            $minifier->minify('.foo { grid-auto-rows: 100px minmax(100px, auto) 10% 0.5fr fit-content(400px); }')
        );
        $t->same(
            '.foo{grid-template-areas:"head head""nav main""foot."}',
            $minifier->minify('.foo { grid-template-areas: "head head" "nav  main" "foot ...."; }')
        );
        $t->same(
            '.foo{grid-template-areas:"head head""nav main"". ."}',
            $minifier->minify('.foo { grid-template-areas: "head head" "nav  main" ".... ...."; }')
        );
        $t->same(
            '.foo{grid-template:[header-top]"a a a"[header-bottom main-top]"b b b"1fr[main-bottom]/auto 1fr auto}',
            $minifier->minify('.foo { grid-template: [header-top] "a   a   a" [header-bottom] [main-top] "b   b   b" 1fr [main-bottom] / auto 1fr auto; }')
        );
        $t->same(
            '.foo{grid-template:"head head""nav main"1fr"foot."}',
            $minifier->minify('.foo { grid-template: "head head" "nav  main" 1fr "foot ...."; }')
        );
        $t->same(
            '.foo{grid-template:[header-top]"a a a"[header-bottom main-top]"b b b"1fr[main-bottom]}',
            $minifier->minify('.foo { grid-template: [header-top] "a   a   a" [header-bottom] [main-top] "b   b   b" 1fr [main-bottom]; }')
        );
        $t->same(
            '.foo{grid:"a"100px"b"1fr}',
            $minifier->minify('.foo { grid: "a" 100px "b" 1fr; }')
        );
        $t->same(
            '.foo{grid:[linename1]"a"100px[linename2]}',
            $minifier->minify('.foo { grid: [linename1] "a" 100px [linename2]; }')
        );
        $t->same(
            '.foo{grid:"a"200px"b"min-content}',
            $minifier->minify('.foo { grid: "a" 200px "b" min-content; }')
        );
        $t->same(
            '.foo{grid:"a"minmax(100px,max-content)"b"20%}',
            $minifier->minify('.foo { grid: "a" minmax(100px, max-content) "b" 20%; }')
        );
        $t->same('.foo{grid:100px/200px}', $minifier->minify('.foo { grid: 100px / 200px; }'));
        $t->same(
            '.foo{grid:minmax(400px,min-content)/repeat(auto-fill,50px)}',
            $minifier->minify('.foo { grid: minmax(400px, min-content) / repeat(auto-fill, 50px); }')
        );
        $t->same('.foo{grid:200px/auto-flow}', $minifier->minify('.foo { grid: 200px / auto-flow; }'));
        $t->same('.foo{grid:30%/auto-flow dense}', $minifier->minify('.foo { grid: 30% / dense auto-flow; }'));
        $t->same('.foo{grid:none/auto-flow 1fr}', $minifier->minify('.foo { grid: none / auto-flow 1fr; }'));
        $t->same('.foo{grid:none/200px}', $minifier->minify('.foo { grid: auto-flow / 200px; }'));
        $t->same(
            '.foo{grid:auto-flow 300px/repeat(3,[line1 line2 line3]200px)}',
            $minifier->minify('.foo { grid: auto-flow 300px / repeat(3, [line1 line2 line3] 200px); }')
        );
        $t->same(
            '.foo{grid:auto-flow dense 40%/[line1]minmax(20em,max-content)}',
            $minifier->minify('.foo { grid: auto-flow dense 40% / [line1] minmax(20em, max-content); }')
        );
        $t->same('.foo{grid-auto-flow:dense}', $minifier->minify('.foo { grid-auto-flow: row dense; }'));
        $t->same('.foo{grid-auto-flow:dense}', $minifier->minify('.foo { grid-auto-flow: dense row; }'));
        $t->same('.foo{grid-auto-flow:column dense}', $minifier->minify('.foo { grid-auto-flow: dense column; }'));
        $t->same('.foo{grid-row-start:2 some-line}', $minifier->minify('.foo { grid-row-start: some-line 2; }'));
        $t->same('.foo{grid-row-start:span some-line}', $minifier->minify('.foo { grid-row-start: span some-line 1; }'));
        $t->same('.foo{grid-row:main-start}', $minifier->minify('.foo { grid-row: main-start / main-start; }'));
        $t->same('.foo{grid-column:1}', $minifier->minify('.foo { grid-column: 1 / auto; }'));
        $t->same('.foo{grid-area:a}', $minifier->minify('.foo { grid-area: a / a / a / a; }'));
        $t->same('.foo{grid-area:a/b}', $minifier->minify('.foo { grid-area: a / b / a / b; }'));
        $t->same('.foo{grid-area:a/b/c}', $minifier->minify('.foo { grid-area: a / b / c / b; }'));
        $t->same('.foo{grid-area:1/1/1/1}', $minifier->minify('.foo { grid-area: 1 / 1 / 1 / 1; }'));
    },
    'css minifier maps upstream explicit grid track list compaction' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $cases = [
            '.foo { grid-template-columns: 150px 1fr; }' => '.foo{grid-template-columns:150px 1fr}',
            '.foo { grid-template-columns: repeat(4, 1fr); }' => '.foo{grid-template-columns:repeat(4,1fr)}',
            '.foo { grid-template-columns: repeat(2, [e] 40px); }' => '.foo{grid-template-columns:repeat(2,[e]40px)}',
            '.foo { grid-template-columns: repeat(4, [col-start] 60% [col-end]); }' => '.foo{grid-template-columns:repeat(4,[col-start]60%[col-end])}',
            '.foo { grid-template-columns: repeat(4, [col-start] 1fr [col-end]); }' => '.foo{grid-template-columns:repeat(4,[col-start]1fr[col-end])}',
            '.foo { grid-template-columns: repeat(4, [col-start] min-content [col-end]); }' => '.foo{grid-template-columns:repeat(4,[col-start]min-content[col-end])}',
            '.foo { grid-template-columns: repeat(4, [col-start] max-content [col-end]); }' => '.foo{grid-template-columns:repeat(4,[col-start]max-content[col-end])}',
            '.foo { grid-template-columns: repeat(4, [col-start] auto [col-end]); }' => '.foo{grid-template-columns:repeat(4,[col-start]auto[col-end])}',
            '.foo { grid-template-columns: repeat(4, [col-start] minmax(100px, 1fr) [col-end]); }' => '.foo{grid-template-columns:repeat(4,[col-start]minmax(100px,1fr)[col-end])}',
            '.foo { grid-template-columns: repeat(4, [col-start] fit-content(200px) [col-end]); }' => '.foo{grid-template-columns:repeat(4,[col-start]fit-content(200px)[col-end])}',
            '.foo { grid-template-columns: repeat(4, 10px [col-start] 30% [col-middle] auto [col-end]); }' => '.foo{grid-template-columns:repeat(4,10px[col-start]30%[col-middle]auto[col-end])}',
            '.foo { grid-template-columns: repeat(5, auto); }' => '.foo{grid-template-columns:repeat(5,auto)}',
            '.foo { grid-template-columns: repeat(auto-fill, 250px); }' => '.foo{grid-template-columns:repeat(auto-fill,250px)}',
            '.foo { grid-template-columns: repeat(auto-fit, 250px); }' => '.foo{grid-template-columns:repeat(auto-fit,250px)}',
            '.foo { grid-template-columns: repeat(auto-fill, [col-start] 250px [col-end]); }' => '.foo{grid-template-columns:repeat(auto-fill,[col-start]250px[col-end])}',
            '.foo { grid-template-columns: minmax(min-content, 1fr); }' => '.foo{grid-template-columns:minmax(min-content,1fr)}',
            '.foo { grid-template-columns: 200px repeat(auto-fill, 100px) 300px; }' => '.foo{grid-template-columns:200px repeat(auto-fill,100px) 300px}',
            '.foo { grid-template-columns: [linename1 linename2] 100px repeat(auto-fit, [linename1] 300px) [linename3]; }' => '.foo{grid-template-columns:[linename1 linename2]100px repeat(auto-fit,[linename1]300px)[linename3]}',
            '.foo { grid-template-rows: [linename1 linename2] 100px repeat(auto-fit, [linename1] 300px) [linename3]; }' => '.foo{grid-template-rows:[linename1 linename2]100px repeat(auto-fit,[linename1]300px)[linename3]}',
        ];

        foreach ($cases as $css => $expected) {
            $t->same($expected, $minifier->minify($css));
        }
    },
    'css minifier composes upstream grid template longhands' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.test-miss-areas{grid-template:"one""."80px/1fr 90px}',
            $minifier->minify(
                '.test-miss-areas { grid-template-columns: 1fr 90px; grid-template-rows: auto 80px; grid-template-areas: "one"; }'
            )
        );
        $t->same(
            '.test-miss-areas-2{grid-template:"a a a"30px"b c c"60px". . ."100px/1fr 1fr 1fr}',
            $minifier->minify(
                '.test-miss-areas-2 { grid-template-columns: 1fr 1fr 1fr; grid-template-rows: 30px 60px 100px; grid-template-areas: "a a a" "b c c"; }'
            )
        );
        $t->same(
            '.foo{grid-template:auto 1fr/auto 1fr auto}',
            $minifier->minify(
                '.foo { grid-template-rows: auto 1fr; grid-template-columns: auto 1fr auto; grid-template-areas: none; }'
            )
        );
        $t->same(
            '.foo{grid-template:none}',
            $minifier->minify(
                '.foo { grid-template-areas: none; grid-template-columns: none; grid-template-rows: none; }'
            )
        );
        $t->same(
            '.foo{grid:[header-top]"a a a"[header-bottom main-top]"b b b"1fr[main-bottom]/auto 1fr auto}',
            $minifier->minify(
                '.foo { grid-template-areas: "a a a" "b b b"; grid-template-rows: [header-top] auto [header-bottom main-top] 1fr [main-bottom]; grid-template-columns: auto 1fr auto; grid-auto-flow: row; grid-auto-rows: auto; grid-auto-columns: auto; }'
            )
        );
        $t->same(
            '.foo{grid:repeat(2,1fr)/auto 1fr auto}',
            $minifier->minify(
                '.foo { grid-template-areas: none; grid-template-columns: auto 1fr auto; grid-template-rows: repeat(2, 1fr); grid-auto-flow: row; grid-auto-rows: auto; grid-auto-columns: auto; }'
            )
        );
        $t->same(
            '.foo{grid:none}',
            $minifier->minify(
                '.foo { grid-template-areas: none; grid-template-columns: none; grid-template-rows: none; grid-auto-flow: row; grid-auto-rows: auto; grid-auto-columns: auto; }'
            )
        );
        $t->same(
            '.foo{grid-template-areas:"a a a""b b b";grid-template-columns:repeat(3,1fr);grid-template-rows:auto 1fr}',
            $minifier->minify(
                '.foo { grid-template-areas: "a a a" "b b b"; grid-template-columns: repeat(3, 1fr); grid-template-rows: auto 1fr; }'
            )
        );
    },
    'css minifier composes upstream grid auto-flow and placement longhands' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{grid-template:[header-top]"a a a"[header-bottom main-top]"b b b"1fr[main-bottom]/auto 1fr auto;grid-auto-rows:1fr;grid-auto-columns:1fr;grid-auto-flow:column}',
            $minifier->minify(
                '.foo { grid-template-areas: "a a a" "b b b"; grid-template-rows: [header-top] auto [header-bottom main-top] 1fr [main-bottom]; grid-template-columns: auto 1fr auto; grid-auto-flow: column; grid-auto-rows: 1fr; grid-auto-columns: 1fr; }'
            )
        );
        $t->same(
            '.foo{grid-template:auto 1fr/auto 1fr auto;grid-auto-rows:1fr;grid-auto-columns:1fr;grid-auto-flow:column}',
            $minifier->minify(
                '.foo { grid-template-rows: auto 1fr; grid-template-columns: auto 1fr auto; grid-template-areas: none; grid-auto-flow: column; grid-auto-rows: 1fr; grid-auto-columns: 1fr; }'
            )
        );
        $t->same(
            '.foo{grid:auto-flow 1fr/auto 1fr auto}',
            $minifier->minify(
                '.foo { grid-template-rows: none; grid-template-columns: auto 1fr auto; grid-template-areas: none; grid-auto-flow: row; grid-auto-rows: 1fr; grid-auto-columns: auto; }'
            )
        );
        $t->same(
            '.foo{grid:auto-flow dense 1fr/auto 1fr auto}',
            $minifier->minify(
                '.foo { grid-template-rows: none; grid-template-columns: auto 1fr auto; grid-template-areas: none; grid-auto-flow: row dense; grid-auto-rows: 1fr; grid-auto-columns: auto; }'
            )
        );
        $t->same(
            '.foo{grid:auto-flow 40px/1fr 90px;grid-template-areas:"a"}',
            $minifier->minify(
                '.foo { grid-template-areas: "a"; grid-template-rows: none; grid-template-columns: 1fr 90px; grid-auto-flow: row; grid-auto-rows: 40px; grid-auto-columns: auto; }'
            )
        );
        $t->same(
            '.foo{grid:auto-flow dense 40px max-content/1fr;grid-template-areas:".a"}',
            $minifier->minify(
                '.foo { grid-template-areas: ". a"; grid-template-rows: none; grid-template-columns: 1fr; grid-auto-flow: row dense; grid-auto-rows: 40px max-content; grid-auto-columns: auto; }'
            )
        );
        $t->same(
            '.foo{grid:auto 1fr auto/auto-flow 1fr}',
            $minifier->minify(
                '.foo { grid-template-rows: auto 1fr auto; grid-template-columns: none; grid-template-areas: none; grid-auto-flow: column; grid-auto-rows: auto; grid-auto-columns: 1fr; }'
            )
        );
        $t->same(
            '.foo{grid:auto 1fr auto/auto-flow dense 1fr}',
            $minifier->minify(
                '.foo { grid-template-rows: auto 1fr auto; grid-template-columns: none; grid-template-areas: none; grid-auto-flow: column dense; grid-auto-rows: auto; grid-auto-columns: 1fr; }'
            )
        );
        $t->same(
            '.foo{grid:1fr 3fr/auto-flow 40px;grid-template-areas:"a"}',
            $minifier->minify(
                '.foo { grid-template-areas: "a"; grid-template-rows: 1fr 3fr; grid-template-columns: none; grid-auto-flow: column; grid-auto-rows: auto; grid-auto-columns: 40px; }'
            )
        );
        $t->same(
            '.foo{grid:1fr/auto-flow dense 40px max-content;grid-template-areas:".a"}',
            $minifier->minify(
                '.foo { grid-template-areas: ". a"; grid-template-rows: 1fr; grid-template-columns: none; grid-auto-flow: column dense; grid-auto-rows: auto; grid-auto-columns: 40px max-content; }'
            )
        );
        $t->same(
            '.foo{grid-template:auto 1fr auto/none;grid-auto-flow:var(--auto-flow);grid-auto-rows:auto;grid-auto-columns:1fr}',
            $minifier->minify(
                '.foo { grid-template-rows: auto 1fr auto; grid-template-columns: none; grid-template-areas: none; grid-auto-flow: var(--auto-flow); grid-auto-rows: auto; grid-auto-columns: 1fr; }'
            )
        );
        $t->same(
            '.foo{grid:1fr 1fr 1fr/auto-flow dense 1fr}',
            $minifier->minify(
                '.foo { grid: auto 1fr auto / auto-flow dense 1fr; grid-template-rows: 1fr 1fr 1fr; }'
            )
        );
        $t->same(
            '.foo{grid-area:a}',
            $minifier->minify(
                '.foo { grid-row-start: a; grid-row-end: a; grid-column-start: a; grid-column-end: a; }'
            )
        );
        $t->same(
            '.foo{grid-area:1/3/2/4}',
            $minifier->minify(
                '.foo { grid-row-start: 1; grid-row-end: 2; grid-column-start: 3; grid-column-end: 4; }'
            )
        );
        $t->same(
            '.foo{grid-row:a}',
            $minifier->minify(
                '.foo { grid-row-start: a; grid-row-end: a; }'
            )
        );
        $t->same(
            '.foo{grid-column:a}',
            $minifier->minify(
                '.foo { grid-column-start: a; grid-column-end: a; }'
            )
        );
    },
    'css minifier composes upstream grid shorthands with area rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.test-miss-areas-3{grid-template:"a a a"30px"b c c"60px". . ."100px/1fr 1fr 1fr}',
            $minifier->minify(
                '.test-miss-areas-3 { grid-template: 30px 60px 100px / 1fr 1fr 1fr; grid-template-areas: "a a a" "b c c"; }'
            )
        );
        $t->same(
            '.test-miss-areas-4{grid:"a a a"30px"b c c"60px". . ."100px/1fr 1fr 1fr}',
            $minifier->minify(
                '.test-miss-areas-4 { grid: 30px 60px 100px / 1fr 1fr 1fr; grid-template-areas: "a a a" "b c c"; }'
            )
        );
        $t->same(
            '.grid-shorthand-areas{grid:".content."/1fr 3fr}',
            $minifier->minify(
                '.grid-shorthand-areas { grid: auto / 1fr 3fr; grid-template-areas: ". content ."; }'
            )
        );
        $t->same(
            '.grid-shorthand-areas-rows{grid:".content."20px/1fr 3fr}',
            $minifier->minify(
                '.grid-shorthand-areas-rows { grid: auto / 1fr 3fr; grid-template-rows: 20px; grid-template-areas: ". content ."; }'
            )
        );
        $t->same(
            '.test-auto-flow-row-1{grid:none/1fr 2fr 1fr;grid-template-areas:".one."}',
            $minifier->minify(
                '.test-auto-flow-row-1 { grid: auto-flow / 1fr 2fr 1fr; grid-template-areas: "  .   one  .  "; }'
            )
        );
        $t->same(
            '.test-auto-flow-row-2{grid:none/100px 100px;grid-template-areas:"one two"}',
            $minifier->minify(
                '.test-auto-flow-row-2 { grid: auto-flow auto / 100px 100px; grid-template-areas: " one two "; }'
            )
        );
    },
    'css minifier composes upstream later grid template areas into shorthands' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.grid-shorthand-areas{grid:".content."/1fr 3fr}',
            $minifier->minify(
                '.grid-shorthand-areas { grid: auto / 1fr 3fr; grid-template-areas: ". content ."; }'
            )
        );
        $t->same(
            '.grid-shorthand-areas-rows{grid:".content."20px/1fr 3fr}',
            $minifier->minify(
                '.grid-shorthand-areas-rows { grid: auto / 1fr 3fr; grid-template-rows: 20px; grid-template-areas: ". content ."; }'
            )
        );
        $t->same(
            '.test-miss-areas-3{grid-template:"a a a"30px"b c c"60px". . ."100px/1fr 1fr 1fr}',
            $minifier->minify(
                '.test-miss-areas-3 { grid-template: 30px 60px 100px / 1fr 1fr 1fr; grid-template-areas: "a a a" "b c c"; }'
            )
        );
        $t->same(
            '.test-miss-areas-4{grid:"a a a"30px"b c c"60px". . ."100px/1fr 1fr 1fr}',
            $minifier->minify(
                '.test-miss-areas-4 { grid: 30px 60px 100px / 1fr 1fr 1fr; grid-template-areas: "a a a" "b c c"; }'
            )
        );
        $t->same(
            '.duplicate-grid-areas{grid:"new new"/1fr 1fr}',
            $minifier->minify(
                '.duplicate-grid-areas { grid: auto / 1fr 1fr; grid-template-areas: "old old"; grid-template-areas: "new new"; }'
            )
        );
        $t->same(
            '.grid-auto-flow-row-auto-rows{grid:auto-flow 40px/1fr 90px;grid-template-areas:"a"}',
            $minifier->minify(
                '.grid-auto-flow-row-auto-rows { grid: auto-flow 40px / 1fr 90px; grid-template-areas: "a"; }'
            )
        );
    },
    'css minifier maps upstream property rule minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@property --property-name{syntax:"<color>";inherits:false;initial-value:#ff0}',
            $minifier->minify("@property --property-name { syntax: '<color>'; inherits: false; initial-value: yellow; }")
        );
        $t->same(
            '@property --property-name{syntax:"*";inherits:false;initial-value:}',
            $minifier->minify("@property --property-name { syntax: '*'; inherits: false; initial-value: ; }")
        );
        $t->same(
            '@property --property-name{syntax:"*";inherits:false;initial-value:}',
            $minifier->minify("@property --property-name { syntax: '*'; inherits: false; initial-value:; }")
        );
        $t->same(
            '@property --property-name{syntax:"*";inherits:false;initial-value:foo bar}',
            $minifier->minify("@property --property-name { syntax: '*'; inherits: false; initial-value: foo bar; }")
        );
        $t->same(
            '@property --property-name{syntax:"<length>";inherits:true;initial-value:25px}',
            $minifier->minify("@property --property-name { syntax: '<length>'; inherits: true; initial-value: 25px; }")
        );
        $t->same(
            '@property --property-name{syntax:"<string>";inherits:true;initial-value:"hi"}',
            $minifier->minify('@property --property-name { syntax: \'<string>\'; inherits: true; initial-value: "hi"; }')
        );
        $t->same(
            '@property --property-name{syntax:"*";inherits:false}',
            $minifier->minify("@property --property-name { syntax: '*'; inherits: false; }")
        );
        $t->same(
            '@property --property-name{syntax:"custom|<color>";inherits:false;initial-value:#ff0}',
            $minifier->minify("@property --property-name { syntax: 'custom | <color>'; inherits: false; initial-value: yellow; }")
        );
        $t->same(
            '@property --property-name{syntax:"<time>";inherits:false;initial-value:1s}',
            $minifier->minify("@property --property-name { syntax: '<time>'; inherits: false; initial-value: 1000ms; }")
        );
        $t->same(
            '@property --property-name{syntax:"<url>";inherits:false;initial-value:url(foo.png)}',
            $minifier->minify('@property --property-name { syntax: \'<url>\'; inherits: false; initial-value: url("foo.png"); }')
        );
        $t->same(
            '@property --property-name{syntax:"<image>";inherits:false;initial-value:linear-gradient(#ff0,#00f)}',
            $minifier->minify("@property --property-name { syntax: '<image>'; inherits: false; initial-value: linear-gradient(yellow, blue); }")
        );
        $t->same(
            '@property --property-name{syntax:"<image>";inherits:false;initial-value:linear-gradient(#ff0,#00f)}',
            $minifier->minify("@property --property-name { initial-value: linear-gradient(yellow, blue); inherits: false; syntax: '<image>'; }")
        );
        $t->same(
            '@property --property-name{syntax:"<color>#";inherits:false;initial-value:#ff0,#00f}',
            $minifier->minify("@property --property-name { syntax: '<color>#'; inherits: false; initial-value: yellow, blue; }")
        );
        $t->same(
            '@property --property-name{syntax:"<color>+";inherits:false;initial-value:#ff0 #00f}',
            $minifier->minify("@property --property-name { syntax: '<color>+'; inherits: false; initial-value: yellow blue; }")
        );
        $t->same(
            '@property --property-name{syntax:"<color>";inherits:true;initial-value:#00f}.foo{color:var(--property-name)}',
            $minifier->minify("@property --property-name { syntax: '<color>'; inherits: false; initial-value: yellow; } .foo { color: var(--property-name); } @property --property-name { syntax: '<color>'; inherits: true; initial-value: blue; }")
        );
    },
    'css minifier maps upstream property rule validation errors' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify("@property --property-name { syntax: '<color>'; inherits: false; initial-value: 25px; }"));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify("@property --property-name { syntax: '<length>'; inherits: false; initial-value: var(--some-value); }"));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify("@property --property-name { syntax: '<color>'; inherits: false; }"));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify("@property --property-name { syntax: '*'; }"));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@property --property-name { inherits: false; }'));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify("@property property-name { syntax: '*'; inherits: false; }"));
    },
    'css minifier maps upstream physical and logical inset composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{inset:0}', $minifier->minify('.foo { top: 0; left: 0; bottom: 0; right: 0; }'));
        $t->same('.foo{inset:2px 4px}', $minifier->minify('.foo { top: 2px; left: 4px; bottom: 2px; right: 4px; }'));
        $t->same('.foo{inset:1px 4px 3px 2px}', $minifier->minify('.foo { top: 1px; left: 2px; bottom: 3px; right: 4px; }'));
        $t->same(
            '.foo{inset-block:2px;inset-inline:4px}',
            $minifier->minify('.foo { inset-block-start: 2px; inset-block-end: 2px; inset-inline-start: 4px; inset-inline-end: 4px; }')
        );
        $t->same(
            '.foo{inset-block:2px 3px;inset-inline:4px 5px}',
            $minifier->minify('.foo { inset-block-start: 2px; inset-block-end: 3px; inset-inline-start: 4px; inset-inline-end: 5px; }')
        );
        $t->same(
            '.foo{inset:4px;inset-inline:4px 5px}',
            $minifier->minify('.foo { inset-block-start: 2px; inset-block-end: 3px; inset: 4px; inset-inline-start: 4px; inset-inline-end: 5px; }')
        );
    },
    'css minifier maps upstream page rule minification and validation' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('@page{margin:.5cm}', $minifier->minify('@page {margin: 0.5cm}'));
        $t->same('@page:left{margin:.5cm}', $minifier->minify('@page :left {margin: 0.5cm}'));
        $t->same('@page:right{margin:.5cm}', $minifier->minify('@page :right {margin: 0.5cm}'));
        $t->same('@page LandscapeTable{margin:.5cm}', $minifier->minify('@page LandscapeTable {margin: 0.5cm}'));
        $t->same(
            '@page CompanyLetterHead:first{margin:.5cm}',
            $minifier->minify('@page CompanyLetterHead:first {margin: 0.5cm}')
        );
        $t->same('@page:first{margin:.5cm}', $minifier->minify('@page:first {margin: 0.5cm}'));
        $t->same('@page:blank:first{margin:.5cm}', $minifier->minify('@page :blank:first {margin: 0.5cm}'));
        $t->same('@page toc,index{margin:.5cm}', $minifier->minify('@page toc, index {margin: 0.5cm}'));
        $t->same(
            '@page:right{@bottom-left{margin:10pt}}',
            $minifier->minify('@page :right { @bottom-left { margin: 10pt; } }')
        );
        $t->same(
            '@page:right{margin:1in;@bottom-left{margin:10pt}}',
            $minifier->minify('@page :right { margin: 1in; @bottom-left { margin: 10pt; } }')
        );

        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@page { @foo { margin: 1in; } }'));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@page { @top-left-corner { @bottom-left { margin: 1in; } } }'));
    },
    'css minifier maps upstream namespace rule minification and ordering' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('@namespace "http://toto.example.org";', $minifier->minify('@namespace url(http://toto.example.org);'));
        $t->same('@namespace "http://toto.example.org";', $minifier->minify('@namespace "http://toto.example.org";'));
        $t->same('@namespace toto "http://toto.example.org";', $minifier->minify('@namespace toto "http://toto.example.org";'));
        $t->same('@namespace toto "http://toto.example.org";', $minifier->minify('@namespace toto url(http://toto.example.org);'));
        $t->same(
            '@namespace "http://example.com/foo";x{color:red}',
            $minifier->minify('@namespace "http://example.com/foo"; x { color: red; }')
        );
        $t->same(
            '@namespace toto "http://toto.example.org";toto|x{color:red}[toto|att="val"]{color:#00f}',
            $minifier->minify('@namespace toto "http://toto.example.org"; toto|x { color: red; } [toto|att=val] { color: blue }')
        );
        $t->same(
            '@namespace "http://example.com/foo";|x{color:red}[att="val"]{color:#00f}',
            $minifier->minify('@namespace "http://example.com/foo"; |x { color: red; } [|att=val] { color: blue }')
        );
        $t->same(
            '@namespace "http://example.com/foo";*|x{color:red}[*|att="val"]{color:#00f}',
            $minifier->minify('@namespace "http://example.com/foo"; *|x { color: red; } [*|att=val] { color: blue }')
        );

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $minifier->minify('.foo { color: red } @namespace "http://example.com/foo";')
        );
    },
    'css minifier maps upstream scope rule prelude spacing' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@scope(.card){.title{color:#ff0}}',
            $minifier->minify('@scope (.card) { .title { color: yellow; } }')
        );
        $t->same(
            '@scope(.card) to (.footer){.title{color:#ff0}}',
            $minifier->minify('@scope (.card) to (.footer) { .title { color: yellow; } }')
        );
        $t->same(
            '@scope(.card) to (.footer){.title{color:#ff0}}',
            $minifier->minify('@scope (.card) TO(.footer) { .title { color: yellow; } }')
        );
        $t->same(
            '@scope(.card,.panel) to (.footer,.aside){.title{color:#ff0}}',
            $minifier->minify('@scope (.card, .panel) to (.footer, .aside) { .title { color: yellow; } }')
        );
    },
    'css minifier maps upstream starting-style rule minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@starting-style{h1{background:#ff0}}',
            $minifier->minify('@starting-style { h1 { background: yellow; } }')
        );
        $t->same('', $minifier->minify('@starting-style {}'));
        $t->same(
            '.foo{content:"@starting-style{}"}',
            $minifier->minify('.foo { content: "@starting-style{}"; }')
        );
        $t->same(
            '.foo{--wp--custom--tokens:@starting-style{}}',
            $minifier->minify('.foo { --wp--custom--tokens: @starting-style{}; }')
        );
    },
    'css minifier maps upstream view-transition rule minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@view-transition{navigation:auto}',
            $minifier->minify('@view-transition { navigation: auto }')
        );
        $t->same(
            '@view-transition{navigation:auto;types:none}',
            $minifier->minify('@view-transition { navigation: auto; types: none; }')
        );
        $t->same(
            '@view-transition{navigation:auto;types:foo bar}',
            $minifier->minify('@view-transition { navigation: auto; types: foo bar; }')
        );
        $t->same(
            '@layer{@view-transition{navigation:auto;types:foo bar}}',
            $minifier->minify('@layer { @view-transition { navigation: auto; types: foo bar; } }')
        );
        $t->same(
            '.foo{--wp--custom--view-transition:@view-transition{navigation:auto}}',
            $minifier->minify('.foo { --wp--custom--view-transition: @view-transition{navigation: auto}; }')
        );
    },
    'css minifier maps upstream import rule minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('@import "foo.css";', $minifier->minify('@import url(foo.css);'));
        $t->same('@import "foo.css";', $minifier->minify('@import "foo.css";'));
        $t->same('@import "foo\"bar\\\\baz.css";', $minifier->minify('@import "foo\"bar\\\\baz.css";'));
        $t->same('@import "foo bar.css";', $minifier->minify('@import "foo\20 bar.css";'));
        $t->same('@import "foo.css" print;', $minifier->minify('@import url(foo.css) print;'));
        $t->same('@import "foo.css" print;', $minifier->minify('@import "foo.css" print;'));
        $t->same(
            '@import "foo.css" screen and (orientation:landscape);',
            $minifier->minify('@import "foo.css" screen and (orientation: landscape);')
        );
        $t->same(
            '@import "foo.css" (width>=960px);',
            $minifier->minify('@import "foo.css" (not (width < 960px));')
        );
        $t->same(
            '@import "foo.css" screen and (width>=960px);',
            $minifier->minify('@import "foo.css" screen and (not (width < 960px));')
        );
        $t->same(
            '@import "foo.css" supports(display:flex);',
            $minifier->minify('@import url(foo.css) supports(display: flex);')
        );
        $t->same(
            '@import "foo.css" supports(display:flex) print;',
            $minifier->minify('@import url(foo.css) supports(display: flex) print;')
        );
        $t->same(
            '@import "foo.css" layer(theme.blocks) supports(display:grid) screen;',
            $minifier->minify('@import u\72l(foo.css) l\61yer(theme.blocks) s\75pports(display: grid) screen;')
        );
        $t->same(
            '@import "foo.css" supports(display:flex) print;',
            $minifier->minify('@import \75 rl(foo.css) s\75pports(display: flex) print;')
        );
        $t->same(
            '@import "foo.css" supports(not (display:flex));',
            $minifier->minify('@import url(foo.css) supports(not (display: flex));')
        );
        $t->same(
            '@import "foo.css" supports(display:flex);',
            $minifier->minify('@import url(foo.css) supports((display: flex));')
        );
        $t->same('@import "foo.css";', $minifier->minify('@charset "UTF-8"; @import url(foo.css);'));
        $t->same('@layer foo;@import "foo.css";', $minifier->minify('@layer foo; @import url(foo.css);'));
        $t->same('@import "test.css" layer;', $minifier->minify("@import 'test.css' layer;"));
        $t->same('@import "test.css" layer(foo);', $minifier->minify("@import 'test.css' layer(foo);"));
        $t->same('@import "test.css" layer(foo.bar);', $minifier->minify("@import 'test.css' layer(foo.bar);"));
        $t->same('@import "test.css" layer(foo\\ bar);', $minifier->minify("@import 'test.css' layer(foo\\20 bar);"));
        $t->same('@import "test.css" layer(foo\\.bar);', $minifier->minify("@import 'test.css' layer(foo\\2e bar);"));
        $t->same('@import "test.css" layer(foo\\,bar);', $minifier->minify("@import 'test.css' layer(foo\\2c bar);"));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify("@import 'test.css' layer(foo, bar) {};"));
    },
    'css minifier maps upstream layer statement and block consolidation' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('@layer foo;', $minifier->minify('@layer foo;'));
        $t->same('@layer foo,bar;', $minifier->minify('@layer foo, bar;'));
        $t->same('@layer foo.bar,baz;', $minifier->minify('@layer foo.bar, baz;'));
        $t->same('@layer foo{.bar{color:red}}', $minifier->minify('@layer foo { .bar { color: red; } }'));
        $t->same('@layer foo.bar{.bar{color:red}}', $minifier->minify('@layer foo.bar { .bar { color: red; } }'));
        $t->same('@layer foo\\.bar{.bar{color:red}}', $minifier->minify('@layer foo\\2e bar { .bar { color: red; } }'));
        $t->same('@layer foo\\,bar{.bar{color:red}}', $minifier->minify('@layer foo\\2c bar { .bar { color: red; } }'));
        $t->same('@layer{.bar{color:red}}', $minifier->minify('@layer { .bar { color: red; } }'));
        $t->same('@layer foo\\ bar,baz;', $minifier->minify('@layer foo\\20 bar, baz;'));
        $t->same(
            '@layer one.two\\ three\\#four\\.five{.bar{color:red}}',
            $minifier->minify('@layer one.two\\20 three\\#four\\.five { .bar { color: red; } }')
        );
        $t->same('@layer a,b,c;', $minifier->minify('@layer a; @layer b; @layer c;'));
        $t->same('@layer a,b,c;', $minifier->minify('@layer a {} @layer b {} @layer c {}'));
        $t->same(
            '@layer foo{.foo{color:red;background:#fff}.baz{color:#fff}}',
            $minifier->minify('@layer foo { .foo { color: red; } } @layer foo { .foo { background: #fff; } .baz { color: #fff; } }')
        );
        $t->same(
            '@layer a;@layer b{.foo{color:red}}@layer c;',
            $minifier->minify('@layer a; @layer b { .foo { color: red; } } @layer c {}')
        );
        $t->same(
            '@layer a,b,c;@layer d{foo{color:red}}',
            $minifier->minify('@layer a, b; @layer c {} @layer d { foo { color: red; } }')
        );
        $t->same(
            '@layer a{foo{color:red}}@layer b,c;',
            $minifier->minify('@layer a, b, c; @layer a { foo { color: red; } }')
        );
        $t->same(
            '@layer a,b;@import "a.css" layer(x);@layer c;@layer d{foo{color:red}}',
            $minifier->minify('@layer a; @layer b; @import "a.css" layer(x); @layer c; @layer d { foo { color: red; } }')
        );
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@layer;'));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@layer foo, bar {};'));
    },
    'css minifier maps upstream supports rule condition normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@supports (foo:bar){.test{foo:bar}}',
            $minifier->minify('@supports (foo: bar) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports not (foo:bar){.test{foo:bar}}',
            $minifier->minify('@supports not (foo: bar) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports (foo:bar) or (bar:baz){.test{foo:bar}}',
            $minifier->minify('@supports (((foo: bar) or (bar: baz))) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports (foo:bar) and (bar:baz){.test{foo:bar}}',
            $minifier->minify('@supports (((foo: bar) and (bar: baz))) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports (foo:bar) and ((bar:baz) or (test:foo)){.test{foo:bar}}',
            $minifier->minify('@supports (foo: bar) and (((bar: baz) or (test: foo))) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports ((display:flex) or (display:grid)) and (color:red){.test{foo:bar}}',
            $minifier->minify('@supports (((display: flex) or (display: grid))) and (color: red) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports ((display:flex) and (color:red)) or (display:grid){.test{foo:bar}}',
            $minifier->minify('@supports (((display: flex) and (color: red))) or (display: grid) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports not ((foo:bar) and (bar:baz)){.test{foo:bar}}',
            $minifier->minify('@supports not (((foo: bar) and (bar: baz))) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports selector(a > b){.test{foo:bar}}',
            $minifier->minify('@supports selector(a > b) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports unknown(test){.test{foo:bar}}',
            $minifier->minify('@supports unknown(test) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports (unknown){.test{foo:bar}}',
            $minifier->minify('@supports (unknown) { .test { foo: bar; } }')
        );
        $t->same(
            '@supports (display:grid) and (not (display:inline-grid)){.test{foo:bar}}',
            $minifier->minify('@supports (display: grid) and (not (display: inline-grid)) { .test { foo: bar; } }')
        );
    },
    'css minifier maps upstream adjacent supports rule merging' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@supports (flex: 1) {
  .foo {
    color: red;
  }
}
@supports (flex: 1) {
  .foo {
    background: #fff;
  }
  .baz {
    color: #fff;
  }
}
CSS;

        $t->same(
            '@supports (flex:1){.foo{color:red;background:#fff}.baz{color:#fff}}',
            (new CssMinifier())->minify($css)
        );
    },
    'css minifier maps upstream supports rule declaration value minification boundary' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@supports (width:calc(10px * 2)){.test{width:20px}}',
            $minifier->minify('@supports (width: calc(10px * 2)) { .test { width: calc(10px * 2); } }')
        );
        $t->same(
            '@supports (color:hsl(0deg, 0%, 0%)){.test{color:#000}}',
            $minifier->minify('@supports (color: hsl(0deg, 0%, 0%)) { .test { color: hsl(0deg, 0%, 0%); } }')
        );
    },
    'css minifier maps upstream image-set string url type and gradient normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{background:image-set("foo.png" 2x,"bar.png" 1x)}',
            $minifier->minify('.foo { background: image-set("foo.png" 2x, url(bar.png) 1x) }')
        );
        $t->same(
            '.foo{background:image-set("foo.webp" 1x type("webp"),"foo.jpg" 1x)}',
            $minifier->minify(".foo { background: image-set('foo.webp' type('webp'), url(foo.jpg)) }")
        );
        $t->same(
            '.foo{background:image-set("foo.avif" 2x type("image/avif"),"foo.png" 1x)}',
            $minifier->minify(".foo { background: image-set('foo.avif' 2x type('image/avif'), url(foo.png)) }")
        );
        $t->same(
            '.foo{background:image-set("example.png" 3x type("image/png"))}',
            $minifier->minify(".foo { background: image-set(url('example.png') 3x type('image/png')) }")
        );
        $t->same(
            '.foo{background:image-set("example.png" 1x type("image/png"))}',
            $minifier->minify(".foo { background: image-set(url(example.png) type('image/png') 1x) }")
        );
        $t->same(
            '.foo{background:-webkit-image-set(url(foo.png) 2x,url(bar.png) 1x)}',
            $minifier->minify('.foo { background: -webkit-image-set(url("foo.png") 2x, url(bar.png) 1x) }')
        );
        $t->same(
            '.foo{background:image-set(linear-gradient(#6495ed,#fff) 1x,"detailed-gradient.png" 3x)}',
            $minifier->minify('.foo { background: image-set(linear-gradient(cornflowerblue, white) 1x, url("detailed-gradient.png") 3x); }')
        );
        $t->same(
            '.foo{content:"image-set(url(foo.png) 2x)";background:url(image-set.png)}',
            $minifier->minify('.foo { content: "image-set(url(foo.png) 2x)"; background: url(image-set.png); }')
        );
    },
    'css minifier maps upstream typed attr property values' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{background-color:attr(data-color type(<color>))}',
            $minifier->minify('.foo { background-color: attr(data-color type(<color>)); }')
        );
        $t->same(
            '.foo{width:attr(data-width type(<length>), 100px)}',
            $minifier->minify('.foo { width: attr(data-width type(<length>), 100px); }')
        );
        $t->same(
            '.foo{width:attr(data-foo %)}',
            $minifier->minify('.foo { width: attr( data-foo    % ); }')
        );
        $t->same(
            '.foo{width:attr(data-foo %,)}',
            $minifier->minify('.foo { width: attr( data-foo    %, ); }')
        );
        $t->same(
            '.foo{width:attr(data-foo px)}',
            $minifier->minify('.foo { width: attr( data-foo    px ); }')
        );
        $t->same(
            '.foo{width:attr(data-foo number)}',
            $minifier->minify('.foo { width: attr(data-foo    number ); }')
        );
        $t->same(
            '.foo{width:attr(data-foo raw-string)}',
            $minifier->minify('.foo { width: attr(data-foo    raw-string); }')
        );
    },
    'css minifier preserves strings urls custom properties and calc operator spacing' => static function (TestRunner $t): void {
        $css = '.asset { background: url("/yellow/blue.svg"); content: "yellow"; --brand-color: yellow; color: var(--yellow); width: calc(100% + 8px); }';
        $t->same('.asset{background:url("/yellow/blue.svg");content:"yellow";--brand-color:yellow;color:var(--yellow);width:calc(100% + 8px)}', (new CssMinifier())->minify($css));
    },
    'css minifier maps upstream linear calc arithmetic cluster' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{width:120px}', $minifier->minify('.foo { width: calc(20px * 2 * 3) }'));
        $t->same('.foo{width:90px}', $minifier->minify('.foo { width: calc(20px + 30px + 40px) }'));
        $t->same('.foo{width:calc(100% - 10px)}', $minifier->minify('.foo { width: calc(100% - 30px + 20px) }'));
        $t->same('.foo{width:calc(100% - 10px)}', $minifier->minify('.foo { width: calc(20px + 100% - 30px) }'));
        $t->same('.foo{width:calc(200% - 40px)}', $minifier->minify('.foo { width: calc(2 * (100% - 20px)) }'));
        $t->same('.foo{width:calc(200% - 40px)}', $minifier->minify('.foo { width: calc((100% - 20px) * 2) }'));
        $t->same('.foo{width:calc(100% - 40px)}', $minifier->minify('.foo { width: calc(100% - 20px * 2) }'));
        $t->same('.foo{width:2px}', $minifier->minify('.foo { width: calc(1px + 1px) }'));
        $t->same('.foo{width:50vw}', $minifier->minify('.foo { width: calc(100vw / 2) }'));
        $t->same('.foo{width:60px}', $minifier->minify('.foo { width: calc(50px - (20px - 30px)) }'));
        $t->same('.foo{width:100%}', $minifier->minify('.foo { width: calc(100px - (100px - 100%)) }'));
        $t->same('.foo{width:calc(200px - 100%)}', $minifier->minify('.foo { width: calc(100px + (100px - 100%)) }'));
        $t->same('.foo{width:calc(1px - 2em - 3%)}', $minifier->minify('.foo { width: calc(1px - (2em + 3%)) }'));
        $t->same('.foo{width:calc(50vw - 25em)}', $minifier->minify('.foo { width: calc((100vw - 50em) / 2) }'));
        $t->same('.foo{width:.01px}', $minifier->minify('.foo { width: calc(1px/100) }'));
        $t->same('.foo{width:100%}', $minifier->minify('.foo { width: calc(100% / 3 * 3) }'));
        $t->same('.foo{width:200px}', $minifier->minify('.foo { width: calc(+100px + +100px) }'));
        $t->same('.foo{width:0}', $minifier->minify('.foo { width: calc(+100px - +100px) }'));
        $t->same('.foo{width:200px}', $minifier->minify('.foo { width: calc(200px * +1) }'));
        $t->same('.foo{width:200px}', $minifier->minify('.foo { width: calc(200px / +1) }'));
        $t->same('.foo{width:22px}', $minifier->minify('.foo { width: calc(1.1e+1px + 1.1e+1px) }'));
        $t->same('.foo{border-width:3px}', $minifier->minify('.foo { border-width: calc(1px + 2px) }'));
        $t->same('.foo{border-width:calc(3em + 5px)}', $minifier->minify('.foo { border-width: calc(1em + 2px + 2em + 3px) }'));
        $t->same('.foo{width:calc(1x + 2x)}', $minifier->minify('.foo { width: calc(1x + 2x) }'));
        $t->same('.foo{width:calc(var(--gap) + 2px)}', $minifier->minify('.foo { width: calc(var(--gap) + 2px) }'));
    },
    'css minifier maps upstream min max and clamp math function cluster' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{border-width:min(1em,2px)}', $minifier->minify('.foo { border-width: min(1em, 2px) }'));
        $t->same('.foo{border-width:min(3em,4px)}', $minifier->minify('.foo { border-width: min(1em + 2em, 2px + 2px) }'));
        $t->same('.foo{border-width:min(1em + 2px,2px + 1em)}', $minifier->minify('.foo { border-width: min(1em + 2px, 2px + 1em) }'));
        $t->same('.foo{border-width:min(1em + 4px,3px + 1em)}', $minifier->minify('.foo { border-width: min(1em + 2px + 2px, 2px + 1em + 1px) }'));
        $t->same('.foo{border-width:3px}', $minifier->minify('.foo { border-width: min(2px + 1px, 3px + 4px) }'));
        $t->same('.foo{border-width:min(1px,1em)}', $minifier->minify('.foo { border-width: min(1px, 1em, 2px, 3in) }'));

        $t->same('.foo{border-width:max(1em,2px)}', $minifier->minify('.foo { border-width: max(1em, 2px) }'));
        $t->same('.foo{border-width:max(3em,4px)}', $minifier->minify('.foo { border-width: max(1em + 2em, 2px + 2px) }'));
        $t->same('.foo{border-width:max(1em + 2px,2px + 1em)}', $minifier->minify('.foo { border-width: max(1em + 2px, 2px + 1em) }'));
        $t->same('.foo{border-width:max(1em + 4px,3px + 1em)}', $minifier->minify('.foo { border-width: max(1em + 2px + 2px, 2px + 1em + 1px) }'));
        $t->same('.foo{border-width:7px}', $minifier->minify('.foo { border-width: max(2px + 1px, 3px + 4px) }'));
        $t->same('.foo{border-width:max(3in,1em)}', $minifier->minify('.foo { border-width: max(1px, 1em, 2px, 3in) }'));

        $t->same('.foo{border-width:2px}', $minifier->minify('.foo { border-width: clamp(1px, 2px, 3px) }'));
        $t->same('.foo{border-width:3px}', $minifier->minify('.foo { border-width: clamp(1px, 10px, 3px) }'));
        $t->same('.foo{border-width:5px}', $minifier->minify('.foo { border-width: clamp(5px, 2px, 10px) }'));
        $t->same('.foo{border-width:100px}', $minifier->minify('.foo { border-width: clamp(100px, 2px, 10px) }'));
        $t->same('.foo{border-width:12px}', $minifier->minify('.foo { border-width: clamp(5px + 5px, 5px + 7px, 10px + 20px) }'));
        $t->same('.foo{border-width:clamp(1em,2px,4vh)}', $minifier->minify('.foo { border-width: clamp(1em, 2px, 4vh) }'));
        $t->same('.foo{border-width:clamp(1em,2em,4vh)}', $minifier->minify('.foo { border-width: clamp(1em, 2em, 4vh) }'));
        $t->same('.foo{border-width:max(1em,2vh)}', $minifier->minify('.foo { border-width: clamp(1em, 2vh, 4vh) }'));
        $t->same('.foo{border-width:2pt}', $minifier->minify('.foo { border-width: clamp(1px, 2pt, 1in) }'));
        $t->same('.foo{width:clamp(-100px,0px,50% - 50vw)}', $minifier->minify('.foo { width: clamp(-100px, 0px, 50% - 50vw); }'));
    },
    'css minifier maps upstream round rem and mod math function cluster' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{width:20px}', $minifier->minify('.foo { width: round(22px, 5px) }'));
        $t->same('.foo{width:20px}', $minifier->minify('.foo { width: round(nearest, 22px, 5px) }'));
        $t->same('.foo{width:20px}', $minifier->minify('.foo { width: round(down, 22px, 5px) }'));
        $t->same('.foo{width:20px}', $minifier->minify('.foo { width: round(to-zero, 22px, 5px) }'));
        $t->same('.foo{width:25px}', $minifier->minify('.foo { width: round(up, 22px, 5px) }'));
        $t->same('.foo{width:25px}', $minifier->minify('.foo { width: round(23px, 5px) }'));
        $t->same('.foo{width:25px}', $minifier->minify('.foo { width: round(nearest, 23px, 5px) }'));
        $t->same('.foo{width:20px}', $minifier->minify('.foo { width: round(down, 23px, 5px) }'));
        $t->same('.foo{width:20px}', $minifier->minify('.foo { width: round(to-zero, 23px, 5px) }'));
        $t->same('.foo{width:25px}', $minifier->minify('.foo { width: round(up, 23px, 5px) }'));
        $t->same('.foo{width:round(22px,5vw)}', $minifier->minify('.foo { width: round(22px, 5vw) }'));
        $t->same('.foo{rotate:20deg}', $minifier->minify('.foo { rotate: round(22deg, 5deg) }'));
        $t->same('.foo{transition-duration:20ms}', $minifier->minify('.foo { transition-duration: round(22ms, 5ms) }'));
        $t->same('.foo{margin:-20px}', $minifier->minify('.foo { margin: round(to-zero, -23px, 5px) }'));
        $t->same('.foo{margin:-25px}', $minifier->minify('.foo { margin: round(nearest, -23px, 5px) }'));
        $t->same('.foo{margin:200px}', $minifier->minify('.foo { margin: calc(10px * round(22, 5)) }'));

        $t->same('.foo{width:3px}', $minifier->minify('.foo { width: rem(18px, 5px) }'));
        $t->same('.foo{width:-3px}', $minifier->minify('.foo { width: rem(-18px, 5px) }'));
        $t->same('.foo{width:rem(18px,5vw)}', $minifier->minify('.foo { width: rem(18px, 5vw) }'));
        $t->same('.foo{rotate:-50deg}', $minifier->minify('.foo { rotate: rem(-140deg, -90deg) }'));
        $t->same('.foo{rotate:50deg}', $minifier->minify('.foo { rotate: rem(140deg, -90deg) }'));
        $t->same('.foo{width:30px}', $minifier->minify('.foo { width: calc(10px * rem(18, 5)) }'));

        $t->same('.foo{width:3px}', $minifier->minify('.foo { width: mod(18px, 5px) }'));
        $t->same('.foo{width:2px}', $minifier->minify('.foo { width: mod(-18px, 5px) }'));
        $t->same('.foo{rotate:-50deg}', $minifier->minify('.foo { rotate: mod(-140deg, -90deg) }'));
        $t->same('.foo{rotate:-40deg}', $minifier->minify('.foo { rotate: mod(140deg, -90deg) }'));
        $t->same('.foo{width:mod(18px,5vw)}', $minifier->minify('.foo { width: mod(18px, 5vw) }'));
        $t->same(
            '.foo{transform:rotateX(-40deg)rotateY(50deg)}',
            $minifier->minify('.foo { transform: rotateX(mod(140deg, -90deg)) rotateY(rem(140deg, -90deg)) }')
        );
        $t->same('.foo{width:30px}', $minifier->minify('.foo { width: calc(10px * mod(18, 5)) }'));
    },
    'css minifier maps upstream exponential and sign math function cluster' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{width:hypot()}', $minifier->minify('.foo { width: hypot() }'));
        $t->same('.foo{width:1px}', $minifier->minify('.foo { width: hypot(1px) }'));
        $t->same('.foo{width:2.23607px}', $minifier->minify('.foo { width: hypot(1px, 2px) }'));
        $t->same('.foo{width:3.74166px}', $minifier->minify('.foo { width: hypot(1px, 2px, 3px) }'));
        $t->same('.foo{width:hypot(1px,2vw)}', $minifier->minify('.foo { width: hypot(1px, 2vw) }'));
        $t->same('.foo{width:hypot(1px,2px,3vw)}', $minifier->minify('.foo { width: hypot(1px, 2px, 3vw) }'));
        $t->same('.foo{width:500px}', $minifier->minify('.foo { width: calc(100px * hypot(3, 4)) }'));
        $t->same('.foo{width:1024px}', $minifier->minify('.foo { width: calc(1px * pow(2, sqrt(100))) }'));
        $t->same('.foo{width:1600px}', $minifier->minify('.foo { width: calc(100px * pow(2, pow(2, 2))) }'));
        $t->same('.foo{width:0}', $minifier->minify('.foo { width: calc(1px * log(1)) }'));
        $t->same('.foo{width:1px}', $minifier->minify('.foo { width: calc(1px * log(10, 10)) }'));
        $t->same('.foo{width:1px}', $minifier->minify('.foo { width: calc(1px * exp(0)) }'));
        $t->same('.foo{width:1px}', $minifier->minify('.foo { width: calc(1px * log(e)) }'));
        $t->same('.foo{width:0}', $minifier->minify('.foo { width: calc(1px * (e - exp(1))) }'));
        $t->same('.foo{width:7.38906px}', $minifier->minify('.foo { width: calc(1px * exp(log(1) + exp(0) * 2)) }'));
        $t->same('.foo{width:1px}', $minifier->minify('.foo { width: abs(1px) }'));
        $t->same('.foo{width:1px}', $minifier->minify('.foo { width: abs(-1px) }'));
        $t->same('.foo{width:abs(1%)}', $minifier->minify('.foo { width: abs(1%) }'));
        $t->same('.foo{width:-10px}', $minifier->minify('.foo { width: calc(10px * sign(-1vw)) }'));
        $t->same('.foo{width:calc(10px * sign(1%))}', $minifier->minify('.foo { width: calc(10px * sign(1%)) }'));
    },
    'css minifier maps upstream nested math functions inside calc' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{top:calc(-1*clamp(1.75rem,8vw,4rem))}',
            $minifier->minify('.foo { top: calc(-1 * clamp(1.75rem, 8vw, 4rem)) }')
        );
        $t->same(
            '.foo{top:calc(-1*min(1.75rem,8vw))}',
            $minifier->minify('.foo { top: calc(-1 * min(1.75rem, 8vw, 4rem)) }')
        );
        $t->same(
            '.foo{top:calc(-1*max(4rem,8vw))}',
            $minifier->minify('.foo { top: calc(-1 * max(1.75rem, 8vw, 4rem)) }')
        );
        $t->same(
            '.foo{top:calc(clamp(1.75rem,8vw,4rem)/2)}',
            $minifier->minify('.foo { top: calc(clamp(1.75rem, 8vw, 4rem) / 2) }')
        );
        $t->same(
            '.foo{left:calc(50% - 100px + clamp(0px,50vw - 50px,100px))}',
            $minifier->minify('.foo { left: calc(50% - 100px + clamp(0px, calc(50vw - 50px), 100px)) }')
        );
    },
    'css minifier maps upstream transform translate and scale normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{transform:scale(.5)translate(10px)}',
            $minifier->minify('.foo { transform: scale(  0.5 ) translateX(10px ) }')
        );
        $t->same('.foo{transform:translate(2px,3px)}', $minifier->minify('.foo { transform: translate(2px, 3px) }'));
        $t->same('.foo{transform:translate(2px)}', $minifier->minify('.foo { transform: translate(2px, 0px) }'));
        $t->same('.foo{transform:translateY(2px)}', $minifier->minify('.foo { transform: translate(0px, 2px) }'));
        $t->same('.foo{transform:translate(2px)}', $minifier->minify('.foo { transform: translateX(2px) }'));
        $t->same('.foo{transform:translateY(2px)}', $minifier->minify('.foo { transform: translateY(2px) }'));
        $t->same('.foo{transform:translateZ(2px)}', $minifier->minify('.foo { transform: translateZ(2px) }'));
        $t->same(
            '.foo{transform:translate3d(10%,20%,4px)}',
            $minifier->minify('.foo { transform: translate3d(10%, 20%, 4px) }')
        );
        $t->same('.foo{transform:translate(2px)}', $minifier->minify('.foo { transform: translate3d(2px, 0px, 0px) }'));
        $t->same('.foo{transform:translateY(2px)}', $minifier->minify('.foo { transform: translate3d(0px, 2px, 0px) }'));
        $t->same('.foo{transform:translateZ(2px)}', $minifier->minify('.foo { transform: translate3d(0px, 0px, 2px) }'));
        $t->same('.foo{transform:translate(2px,3px)}', $minifier->minify('.foo { transform: translate3d(2px, 3px, 0px) }'));

        $t->same('.foo{transform:scale(2,3)}', $minifier->minify('.foo { transform: scale(2, 3) }'));
        $t->same('.foo{transform:scale(.1,.2)}', $minifier->minify('.foo { transform: scale(10%, 20%) }'));
        $t->same('.foo{transform:scale(2)}', $minifier->minify('.foo { transform: scale(2, 2) }'));
        $t->same('.foo{transform:scaleX(2)}', $minifier->minify('.foo { transform: scale(2, 1) }'));
        $t->same('.foo{transform:scaleY(2)}', $minifier->minify('.foo { transform: scale(1, 2) }'));
        $t->same('.foo{transform:scale3d(2,3,4)}', $minifier->minify('.foo { transform: scale3d(2, 3, 4) }'));
        $t->same('.foo{transform:scaleX(2)}', $minifier->minify('.foo { transform: scale3d(2, 1, 1) }'));
        $t->same('.foo{transform:scaleY(2)}', $minifier->minify('.foo { transform: scale3d(1, 2, 1) }'));
        $t->same('.foo{transform:scaleZ(2)}', $minifier->minify('.foo { transform: scale3d(1, 1, 2) }'));
        $t->same('.foo{transform:scale(2)}', $minifier->minify('.foo { transform: scale3d(2, 2, 1) }'));
        $t->same('.foo{transform:scale(1)}', $minifier->minify('.foo { transform: scale3d(100%, 100%, 100%) }'));
        $t->same('.foo{transform:scaleY(2)}', $minifier->minify('.foo { transform: scale(100%, 200%) }'));
        $t->same('.foo{transform:scale(.3)}', $minifier->minify('.foo { transform: scale(calc(10% + 20%)) }'));
        $t->same('.foo{transform:scale(.333333)}', $minifier->minify('.foo { transform: scale(calc(100% / 3)) }'));
        $t->same('.foo{-webkit-transform:scale(.3)}', $minifier->minify('.foo { -webkit-transform: scale(calc(10% + 20%)) }'));
    },
    'css minifier maps upstream transform rotate skew matrix normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{transform:rotate(20deg)}', $minifier->minify('.foo { transform: rotate(20deg) }'));
        $t->same('.foo{transform:rotateX(20deg)}', $minifier->minify('.foo { transform: rotateX(20deg) }'));
        $t->same('.foo{transform:rotateY(20deg)}', $minifier->minify('.foo { transform: rotateY(20deg) }'));
        $t->same('.foo{transform:rotate(20deg)}', $minifier->minify('.foo { transform: rotateZ(20deg) }'));
        $t->same('.foo{transform:rotate(360deg)}', $minifier->minify('.foo { transform: rotate(360deg) }'));
        $t->same('.foo{transform:rotate3d(2,3,4,20deg)}', $minifier->minify('.foo { transform: rotate3d(2, 3, 4, 20deg) }'));
        $t->same('.foo{transform:rotateX(20deg)}', $minifier->minify('.foo { transform: rotate3d(1, 0, 0, 20deg) }'));
        $t->same('.foo{transform:rotateY(20deg)}', $minifier->minify('.foo { transform: rotate3d(0, 1, 0, 20deg) }'));
        $t->same('.foo{transform:rotate(20deg)}', $minifier->minify('.foo { transform: rotate3d(0, 0, 1, 20deg) }'));
        $t->same('.foo{transform:rotate(405deg)}', $minifier->minify('.foo { transform: rotate(405deg) }'));
        $t->same('.foo{transform:rotateX(405deg)}', $minifier->minify('.foo { transform: rotateX(405deg) }'));
        $t->same('.foo{transform:rotateY(405deg)}', $minifier->minify('.foo { transform: rotateY(405deg) }'));
        $t->same('.foo{transform:rotate(-200deg)}', $minifier->minify('.foo { transform: rotate(-200deg) }'));
        $t->same('.foo{transform:rotate(0)}', $minifier->minify('.foo { transform: rotate(0) }'));
        $t->same('.foo{transform:rotate(0)}', $minifier->minify('.foo { transform: rotate(0deg) }'));
        $t->same('.foo{transform:rotateX(-200deg)}', $minifier->minify('.foo { transform: rotateX(-200deg) }'));
        $t->same('.foo{transform:rotateY(-200deg)}', $minifier->minify('.foo { transform: rotateY(-200deg) }'));
        $t->same('.foo{transform:rotate3d(1,1,0,-200deg)}', $minifier->minify('.foo { transform: rotate3d(1, 1, 0, -200deg) }'));
        $t->same('.foo{transform:skew(20deg)}', $minifier->minify('.foo { transform: skew(20deg) }'));
        $t->same('.foo{transform:skew(20deg)}', $minifier->minify('.foo { transform: skew(20deg, 0deg) }'));
        $t->same('.foo{transform:skewY(20deg)}', $minifier->minify('.foo { transform: skew(0deg, 20deg) }'));
        $t->same('.foo{transform:skew(20deg)}', $minifier->minify('.foo { transform: skewX(20deg) }'));
        $t->same('.foo{transform:skewY(20deg)}', $minifier->minify('.foo { transform: skewY(20deg) }'));
        $t->same('.foo{transform:perspective(10px)}', $minifier->minify('.foo { transform: perspective(10px) }'));
        $t->same('.foo{transform:matrix(1,2,-1,1,80,80)}', $minifier->minify('.foo { transform: matrix(1, 2, -1, 1, 80, 80) }'));
        $t->same(
            '.foo{transform:matrix3d(1,0,0,0,0,1,6,0,0,0,1,0,50,100,0,1.1)}',
            $minifier->minify('.foo { transform: matrix3d(1, 0, 0, 0, 0, 1, 6, 0, 0, 0, 1, 0, 50, 100, 0, 1.1) }')
        );
        $t->same(
            '.foo{transform:translate(100px,200px)rotate(45deg)}',
            $minifier->minify('.foo{transform:translate(100px,200px) rotate(45deg)}')
        );
        $t->same(
            '.foo{transform:rotate3d(1,1,1,45deg)translate3d(100px,100px,10px)}',
            $minifier->minify('.foo{transform:rotate3d(1, 1, 1, 45deg) translate3d(100px, 100px, 10px)}')
        );
        $t->same('.foo{transform:translate(242px)}', $minifier->minify('.foo{transform:translateX(calc(2in + 50px))}'));
        $t->same('.foo{transform:translate(50%)}', $minifier->minify('.foo{transform:translateX(50%)}'));
        $t->same(
            '.foo{transform:translate(calc(50% - 80px))}',
            $minifier->minify('.foo{transform:translateX(calc(50% - 100px + 20px))}')
        );
        $t->same('.foo{transform:rotate(30deg)}', $minifier->minify('.foo{transform:rotate(calc(10deg + 20deg))}'));
        $t->same('.foo{transform:rotate(30deg)}', $minifier->minify('.foo{transform:rotate(calc(10deg + 0.349066rad))}'));
        $t->same('.foo{transform:rotate(550deg)}', $minifier->minify('.foo{transform:rotate(calc(10deg + 1.5turn))}'));
        $t->same('.foo{transform:rotate(20deg)}', $minifier->minify('.foo{transform:rotate(calc(10deg * 2))}'));
        $t->same('.foo{transform:rotate(-20deg)}', $minifier->minify('.foo{transform:rotate(calc(-10deg * 2))}'));
        $t->same(
            '.foo{transform:rotate(calc(10deg + var(--test)))}',
            $minifier->minify('.foo{transform:rotate(calc(10deg + var(--test)))}')
        );
    },
    'css minifier maps upstream transform longhand normalization' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{translate:1px 2px 3px}', $minifier->minify('.foo { translate: 1px 2px 3px }'));
        $t->same('.foo{translate:1px}', $minifier->minify('.foo { translate: 1px 0px 0px }'));
        $t->same('.foo{translate:1px 2px}', $minifier->minify('.foo { translate: 1px 2px 0px }'));
        $t->same('.foo{translate:1px 0 2px}', $minifier->minify('.foo { translate: 1px 0px 2px }'));
        $t->same('.foo{translate:none}', $minifier->minify('.foo { translate: none }'));

        $t->same('.foo{rotate:none}', $minifier->minify('.foo { rotate: none }'));
        $t->same('.foo{rotate:0deg}', $minifier->minify('.foo { rotate: 0deg }'));
        $t->same('.foo{rotate:0deg}', $minifier->minify('.foo { rotate: -0deg }'));
        $t->same('.foo{rotate:10deg}', $minifier->minify('.foo { rotate: 10deg }'));
        $t->same('.foo{rotate:10deg}', $minifier->minify('.foo { rotate: z 10deg }'));
        $t->same('.foo{rotate:10deg}', $minifier->minify('.foo { rotate: 0 0 1 10deg }'));
        $t->same('.foo{rotate:x 10deg}', $minifier->minify('.foo { rotate: x 10deg }'));
        $t->same('.foo{rotate:x 10deg}', $minifier->minify('.foo { rotate: 1 0 0 10deg }'));
        $t->same('.foo{rotate:x 10deg}', $minifier->minify('.foo { rotate: 2 0 0 10deg }'));
        $t->same('.foo{rotate:y 10deg}', $minifier->minify('.foo { rotate: 0 2 0 10deg }'));
        $t->same('.foo{rotate:10deg}', $minifier->minify('.foo { rotate: 0 0 2 10deg }'));
        $t->same('.foo{rotate:10deg}', $minifier->minify('.foo { rotate: 0 0 5.3 10deg }'));
        $t->same('.foo{rotate:0deg}', $minifier->minify('.foo { rotate: 0 0 1 0deg }'));
        $t->same('.foo{rotate:-10deg}', $minifier->minify('.foo { rotate: 10deg 0 0 -1 }'));
        $t->same('.foo{rotate:-10deg}', $minifier->minify('.foo { rotate: 10deg 0 0 -233 }'));
        $t->same('.foo{rotate:x 0deg}', $minifier->minify('.foo { rotate: -1 0 0 0deg }'));
        $t->same('.foo{rotate:0deg}', $minifier->minify('.foo { rotate: 0deg 0 0 1 }'));
        $t->same('.foo{rotate:0deg}', $minifier->minify('.foo { rotate: 0deg 0 0 -1 }'));
        $t->same('.foo{rotate:y 10deg}', $minifier->minify('.foo { rotate: 0 1 0 10deg }'));
        $t->same('.foo{rotate:x 0deg}', $minifier->minify('.foo { rotate: x 0rad }'));
        $t->same('.foo{rotate:0deg}', $minifier->minify('.foo { rotate: z 0deg }'));
        $t->same('.foo{rotate:y 10deg}', $minifier->minify('.foo { rotate: 10deg y }'));
        $t->same('.foo{rotate:1 1 1 10deg}', $minifier->minify('.foo { rotate: 1 1 1 10deg }'));

        $t->same('.foo{scale:1}', $minifier->minify('.foo { scale: 1 }'));
        $t->same('.foo{scale:1}', $minifier->minify('.foo { scale: 1 1 }'));
        $t->same('.foo{scale:1}', $minifier->minify('.foo { scale: 1 1 1 }'));
        $t->same('.foo{scale:none}', $minifier->minify('.foo { scale: none }'));
        $t->same('.foo{scale:1 0}', $minifier->minify('.foo { scale: 1 0 }'));
        $t->same('.foo{scale:1 0}', $minifier->minify('.foo { scale: 1 0 1 }'));
        $t->same('.foo{scale:1 0 0}', $minifier->minify('.foo { scale: 1 0 0 }'));
        $t->same('.foo{scale:.5 1 2}', $minifier->minify('.foo { scale: 50% 1 200% }'));
        $t->same('.foo{scale:.01}', $minifier->minify('.foo { scale: 1% }'));
        $t->same('.foo{scale:0}', $minifier->minify('.foo { scale: 0% }'));
        $t->same('.foo{scale:0}', $minifier->minify('.foo { scale: 0.0% }'));
        $t->same('.foo{scale:0}', $minifier->minify('.foo { scale: -0% }'));
        $t->same('.foo{scale:0}', $minifier->minify('.foo { scale: -0 }'));
        $t->same('.foo{scale:0}', $minifier->minify('.foo { scale: -0.0 }'));
        $t->same('.foo{scale:1}', $minifier->minify('.foo { scale: 100% }'));
        $t->same('.foo{scale:-1}', $minifier->minify('.foo { scale: -100% }'));
        $t->same('.foo{scale:.68}', $minifier->minify('.foo { scale: 68% }'));
        $t->same('.foo{scale:.0596}', $minifier->minify('.foo { scale: 5.96% }'));
        $t->same('.foo{scale:1}', $minifier->minify('.foo { scale: 100% 100% }'));
        $t->same('.foo{scale:1}', $minifier->minify('.foo { scale: 100% 100% 1 }'));
        $t->same('.foo{scale:-1}', $minifier->minify('.foo { scale: -100% -100% }'));
        $t->same('.foo{scale:-1}', $minifier->minify('.foo { scale: -100% -100% 1 }'));
        $t->same('.foo{scale:1 2}', $minifier->minify('.foo { scale: 100% 200% }'));
        $t->same('.foo{scale:1 2}', $minifier->minify('.foo { scale: 100% 200% 1 }'));
        $t->same('.foo{scale:1 1 0}', $minifier->minify('.foo { scale: 100% 100% 0% }'));
        $t->same('.foo{scale:1}', $minifier->minify('.foo { scale: 100% 100% 100% }'));
        $t->same('.foo{scale:0 0 0}', $minifier->minify('.foo { scale: -0% -0% -0% }'));
        $t->same('.foo{scale:2 1}', $minifier->minify('.foo { scale: 2 100% }'));
        $t->same('.foo{scale:2 -.5}', $minifier->minify('.foo { scale: 2 -50% }'));
        $t->same('.foo{scale:-.9 -1}', $minifier->minify('.foo { scale: -90% -1 }'));
        $t->same('.foo{scale:.3}', $minifier->minify('.foo { scale: calc(10% + 20%) }'));
        $t->same('.foo{scale:1 2}', $minifier->minify('.foo { scale: calc(150% - 50%) 200% }'));
        $t->same('.foo{scale:2 -.3}', $minifier->minify('.foo { scale: 200% calc(50% - 80%) }'));
        $t->same('.foo{scale:.333333}', $minifier->minify('.foo { scale: calc(100% / 3) }'));
    },
    'css minifier maps upstream keyframes rule minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@keyframes test{to{background:#00f}}',
            $minifier->minify('@keyframes "test" { 100% { background: blue } }')
        );
        $t->same(
            '@keyframes test{to{background:#00f}}',
            $minifier->minify('@keyframes test { 100% { background: blue } }')
        );
        $t->same(
            '@keyframes test{entry 0%{background:#00f}exit 100%{background:green}}',
            $minifier->minify('@keyframes test { entry 0% { background: blue } exit 100% { background: green } }')
        );
        $t->same(
            '@keyframes "revert"{0%{background:green}}',
            $minifier->minify('@keyframes "revert" { from { background: green; } }')
        );
        $t->same(
            '@keyframes "none"{0%{background:green}}',
            $minifier->minify('@keyframes "none" { from { background: green; } }')
        );
        $t->same(
            '@keyframes test{}',
            $minifier->minify('@keyframes test { entry to { background: blue } }')
        );
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@keyframes revert {}'));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@keyframes revert-layer {}'));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@keyframes none {}'));
        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@keyframes NONE {}'));
        $t->same(
            '@-webkit-keyframes test{0%{background:red}to{background:#00f}}',
            $minifier->minify('@-webkit-keyframes test { from { background: green; background-color: red; } 100% { background: blue } }')
        );
        $t->same(
            '@-moz-keyframes test{0%{background:red}to{background:#00f}}',
            $minifier->minify('@-moz-keyframes test { from { background: green; background-color: red; } 100% { background: blue } }')
        );
        $t->same(
            '@-webkit-keyframes test{0%{background:red}to{background:#00f}}@-moz-keyframes test{0%{background:red}to{background:#00f}}',
            $minifier->minify('@-webkit-keyframes test { from { background: green; background-color: red; } 100% { background: blue } } @-moz-keyframes test { from { background: green; background-color: red; } 100% { background: blue } }')
        );
        $t->same(
            '@keyframes test{to{background:red}}',
            $minifier->minify('@keyframes test { 100% { background: blue } } @keyframes test { 100% { background: red } }')
        );
        $t->same(
            '@keyframes test{to{background:#00f}}@-webkit-keyframes test{to{background:red}}',
            $minifier->minify('@keyframes test { 100% { background: blue } } @-webkit-keyframes test { 100% { background: red } }')
        );
    },
    'css minifier maps upstream animation longhand value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{animation-name:test,foo,"none","unset","revert","default"}',
            $minifier->minify('.foo { animation-name: "test", foo, "none", "unset", "revert", "default" }')
        );
        $t->same('.foo{animation-duration:.1s}', $minifier->minify('.foo { animation-duration: 100ms }'));
        $t->same(
            '.foo{animation-duration:.1s,2s;animation-delay:.1s,2s}',
            $minifier->minify('.foo { animation-duration: 100ms, 2000ms; animation-delay: 100ms, 2000ms }')
        );
        $t->same(
            '.foo{animation-timing-function:ease,ease-in,steps(5,start)}',
            $minifier->minify('.foo { animation-timing-function: ease, cubic-bezier(0.42, 0, 1, 1), steps(5, jump-start) }')
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
    'css minifier maps upstream animation shorthand value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{animation:none}', $minifier->minify('.foo { animation: none none }'));
        $t->same('.foo{animation:"none"}', $minifier->minify('.foo { animation: "none" none }'));
        $t->same('.foo{animation:2s both "none"}', $minifier->minify('.foo { animation: both "none" 2s }'));
        $t->same('.foo{animation:2s "none"}', $minifier->minify('.foo { animation: none "none" 2s }'));
        $t->same('.foo{animation:none,.5s "unset"}', $minifier->minify('.foo { animation: none, "unset" .5s }'));
        $t->same('.foo{animation:2s 1 infinite}', $minifier->minify('.foo { animation: "infinite" 2s 1 }'));
        $t->same('.foo{animation:2s running paused}', $minifier->minify('.foo { animation: "paused" 2s }'));
        $t->same('.foo{animation:2s none forwards}', $minifier->minify('.foo { animation: "forwards" 2s }'));
        $t->same('.foo{animation:2s normal reverse}', $minifier->minify('.foo { animation: "reverse" 2s }'));
        $t->same(
            '.foo{animation:3s ease-in 1s infinite reverse both slidein}',
            $minifier->minify('.foo { animation: 3s ease-in 1s infinite reverse both running slidein }')
        );
        $t->same(
            '.foo{animation:3s 1s reverse both paused slidein}',
            $minifier->minify('.foo { animation: 3s slidein paused ease 1s 1 reverse both }')
        );
        $t->same('.foo{animation:3s ease ease}', $minifier->minify('.foo { animation: 3s ease ease }'));
        $t->same(
            '.foo{animation:3s foo}',
            $minifier->minify('.foo { animation: 3s cubic-bezier(0.25, 0.1, 0.25, 1) foo }')
        );
        $t->same('.foo{animation:0s 3s infinite foo}', $minifier->minify('.foo { animation: foo 0s 3s infinite }'));
        $t->same('.foo{animation:3s foo --test}', $minifier->minify('.foo { animation: foo 3s --test }'));
        $t->same('.foo{animation:3s foo scroll()}', $minifier->minify('.foo { animation: foo 3s scroll(block) }'));
        $t->same('.foo{animation:3s foo view(inline 10px)}', $minifier->minify('.foo { animation: foo 3s view(inline 10px 10px) }'));
    },
    'css minifier maps upstream animation longhand composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{animation:90ms ease-in-out .1s 2 alternate forwards foo}',
            $minifier->minify('.foo { animation-name: foo; animation-duration: 0.09s; animation-timing-function: ease-in-out; animation-iteration-count: 2; animation-direction: alternate; animation-play-state: running; animation-delay: 100ms; animation-fill-mode: forwards; animation-timeline: auto; }')
        );
        $t->same(
            '.foo{animation:90ms ease-in-out .1s 2 alternate forwards foo,.2s paused bar}',
            $minifier->minify('.foo { animation-name: foo, bar; animation-duration: 0.09s, 200ms; animation-timing-function: ease-in-out, ease; animation-iteration-count: 2, 1; animation-direction: alternate, normal; animation-play-state: running, paused; animation-delay: 100ms, 0s; animation-fill-mode: forwards, none; animation-timeline: auto, auto; }')
        );
        $t->same(
            '.foo{animation:.2s ease-in-out bar}',
            $minifier->minify('.foo { animation: bar 200ms; animation-timing-function: ease-in-out; }')
        );
        $t->same(
            '.foo{animation:.2s bar;animation-timing-function:var(--ease)}',
            $minifier->minify('.foo { animation: bar 200ms; animation-timing-function: var(--ease); }')
        );
        $t->same(
            '.foo{animation-name:foo,bar;animation-duration:90ms;animation-timing-function:ease-in-out;animation-iteration-count:2;animation-direction:alternate;animation-play-state:running;animation-delay:.1s;animation-fill-mode:forwards;animation-timeline:auto}',
            $minifier->minify('.foo { animation-name: foo, bar; animation-duration: 0.09s; animation-timing-function: ease-in-out; animation-iteration-count: 2; animation-direction: alternate; animation-play-state: running; animation-delay: 100ms; animation-fill-mode: forwards; animation-timeline: auto; }')
        );
        $t->same(
            '.foo{animation:90ms ease-in-out .1s 2 alternate forwards foo scroll()}',
            $minifier->minify('.foo { animation-name: foo; animation-duration: 0.09s; animation-timing-function: ease-in-out; animation-iteration-count: 2; animation-direction: alternate; animation-play-state: running; animation-delay: 100ms; animation-fill-mode: forwards; animation-timeline: scroll(); }')
        );
        $t->same(
            '.foo{animation-name:foo;animation-duration:90ms;animation-timing-function:ease-in-out;animation-iteration-count:2;animation-direction:alternate;animation-play-state:running;animation-delay:.1s;animation-fill-mode:forwards;animation-timeline:scroll(),view()}',
            $minifier->minify('.foo { animation-name: foo; animation-duration: 0.09s; animation-timing-function: ease-in-out; animation-iteration-count: 2; animation-direction: alternate; animation-play-state: running; animation-delay: 100ms; animation-fill-mode: forwards; animation-timeline: scroll(), view(); }')
        );
    },
    'css minifier maps upstream prefixed animation composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{-webkit-animation:90ms ease-in-out .1s 2 alternate forwards foo}',
            $minifier->minify('.foo { -webkit-animation-name: foo; -webkit-animation-duration: 0.09s; -webkit-animation-timing-function: ease-in-out; -webkit-animation-iteration-count: 2; -webkit-animation-direction: alternate; -webkit-animation-play-state: running; -webkit-animation-delay: 100ms; -webkit-animation-fill-mode: forwards; }')
        );
        $t->same(
            '.foo{-moz-animation:.2s ease-in-out bar}',
            $minifier->minify('.foo { -moz-animation: bar 200ms; -moz-animation-timing-function: ease-in-out; }')
        );
        $t->same(
            '.foo{-webkit-animation:.2s ease-in-out bar;-moz-animation:.2s ease-in-out bar}',
            $minifier->minify('.foo { -webkit-animation: bar 200ms; -webkit-animation-timing-function: ease-in-out; -moz-animation: bar 200ms; -moz-animation-timing-function: ease-in-out; }')
        );
    },
    'css minifier maps upstream animation range value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{animation-range-start:entry 10%}', $minifier->minify('.foo { animation-range-start: entry 10% }'));
        $t->same('.foo{animation-range-start:entry}', $minifier->minify('.foo { animation-range-start: entry 0% }'));
        $t->same('.foo{animation-range-start:entry}', $minifier->minify('.foo { animation-range-start: entry }'));
        $t->same('.foo{animation-range-start:50%}', $minifier->minify('.foo { animation-range-start: 50% }'));
        $t->same('.foo{animation-range-end:exit 10%}', $minifier->minify('.foo { animation-range-end: exit 10% }'));
        $t->same('.foo{animation-range-end:exit}', $minifier->minify('.foo { animation-range-end: exit 100% }'));
        $t->same('.foo{animation-range-end:exit}', $minifier->minify('.foo { animation-range-end: exit }'));
        $t->same('.foo{animation-range-end:50%}', $minifier->minify('.foo { animation-range-end: 50% }'));
        $t->same('.foo{animation-range:entry 10% exit 90%}', $minifier->minify('.foo { animation-range: entry 10% exit 90% }'));
        $t->same('.foo{animation-range:entry exit}', $minifier->minify('.foo { animation-range: entry 0% exit 100% }'));
        $t->same('.foo{animation-range:entry}', $minifier->minify('.foo { animation-range: entry }'));
        $t->same('.foo{animation-range:entry}', $minifier->minify('.foo { animation-range: entry 0% entry 100% }'));
        $t->same('.foo{animation-range:50%}', $minifier->minify('.foo { animation-range: 50% normal }'));
        $t->same('.foo{animation-range:normal}', $minifier->minify('.foo { animation-range: normal normal }'));
    },
    'css minifier maps upstream animation range composition and resets' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{animation-range:entry 10% exit 90%}',
            $minifier->minify('.foo { animation-range-start: entry 10%; animation-range-end: exit 90%; }')
        );
        $t->same(
            '.foo{animation-range:entry}',
            $minifier->minify('.foo { animation-range-start: entry 0%; animation-range-end: entry 100%; }')
        );
        $t->same(
            '.foo{animation-range:entry exit}',
            $minifier->minify('.foo { animation-range-start: entry 0%; animation-range-end: exit 100%; }')
        );
        $t->same(
            '.foo{animation-range:10%}',
            $minifier->minify('.foo { animation-range-start: 10%; animation-range-end: normal; }')
        );
        $t->same(
            '.foo{animation-range:10% 90%}',
            $minifier->minify('.foo { animation-range-start: 10%; animation-range-end: 90%; }')
        );
        $t->same(
            '.foo{animation-range:entry 10% exit}',
            $minifier->minify('.foo { animation-range-start: entry 10%; animation-range-end: exit 100%; }')
        );
        $t->same(
            '.foo{animation-range:10% exit 90%}',
            $minifier->minify('.foo { animation-range-start: 10%; animation-range-end: exit 90%; }')
        );
        $t->same(
            '.foo{animation-range:entry 10% 90%}',
            $minifier->minify('.foo { animation-range-start: entry 10%; animation-range-end: 90%; }')
        );
        $t->same(
            '.foo{animation-range:entry 90%}',
            $minifier->minify('.foo { animation-range: entry; animation-range-end: 90%; }')
        );
        $t->same(
            '.foo{animation-range:entry;animation-range-end:var(--end)}',
            $minifier->minify('.foo { animation-range: entry; animation-range-end: var(--end); }')
        );
        $t->same(
            '.foo{animation-range-start:entry 10%,entry 50%;animation-range-end:exit 90%}',
            $minifier->minify('.foo { animation-range-start: entry 10%, entry 50%; animation-range-end: exit 90%; }')
        );
        $t->same(
            '.foo{animation-range:entry 10% exit 90%,entry 50% exit}',
            $minifier->minify('.foo { animation-range-start: entry 10%, entry 50%; animation-range-end: exit 90%, exit 100%; }')
        );
        $t->same(
            '.foo{animation:.1s spin}',
            $minifier->minify('.foo { animation-range: entry; animation-range-end: 90%; animation: spin 100ms; }')
        );
        $t->same(
            '.foo{animation:.1s spin;animation-range:entry 90%}',
            $minifier->minify('.foo { animation: spin 100ms; animation-range: entry; animation-range-end: 90%; }')
        );
        $t->same(
            '.foo{animation:var(--animation) .1s}',
            $minifier->minify('.foo { animation-range: entry; animation-range-end: 90%; animation: var(--animation) 100ms; }')
        );
    },
    'css minifier maps upstream container query prelude minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '@container foo{.inner{background:green}}',
            $minifier->minify('@container foo { .inner { background: green; } }')
        );
        $t->same(
            '@container my-layout (inline-size>45em){.foo{color:red}}',
            $minifier->minify('@container my-layout (inline-size > 45em) { .foo { color: red; } }')
        );
        $t->same(
            '@container my-layout not (width>500px){.foo{color:red}}',
            $minifier->minify('@container my-layout ( not (width > 500px) ) { .foo { color: red; } }')
        );
        $t->same(
            '@container not (width>500px){.foo{color:red}}',
            $minifier->minify('@container not (width > 500px) { .foo { color: red; } }')
        );
        $t->same(
            '@container my-layout (width:100px) and (not (height:100px)){.foo{color:red}}',
            $minifier->minify('@container my-layout ((width: 100px) and (not (height: 100px))) { .foo { color: red; } }')
        );
        $t->same(
            '@container (inline-size>45em) and (inline-size<100em){.foo{color:red}}',
            $minifier->minify('@container (inline-size > 45em) and (inline-size < 100em) { .foo { color: red; } }')
        );
        $t->same(
            '@container (height>=calc(100vh - 50px)){.foo{color:red}}',
            $minifier->minify('@container (calc(100vh - 50px) <= height) { .foo { color: red; } }')
        );
        $t->same(
            '@container style(--responsive:true) and style(color:#ff0){.foo{color:red}}',
            $minifier->minify('@container style(--responsive: true) and style(color: yellow) { .foo { color: red; } }')
        );
        $t->same(
            '@container style(not ((width:30px) and (--bar:url(x)))){.foo{color:red}}',
            $minifier->minify('@container style(not ((width: calc(10px + 20px)) and ((--bar: url(x))))) { .foo { color: red; } }')
        );
        $t->same(
            '@container scroll-state((stuck:top) and (stuck:left)){.foo{color:red}}',
            $minifier->minify('@container scroll-state((stuck: top) and (stuck: left)) { .foo { color: red; } }')
        );
    },
    'css minifier maps upstream container declaration composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{container:foo bar/size}',
            $minifier->minify('.foo { container-name: foo bar; container-type: size; }')
        );
        $t->same(
            '.foo{container:foo bar}',
            $minifier->minify('.foo { container-name: foo bar; container-type: normal; }')
        );
        $t->same('.foo{container-type:inline-size}', $minifier->minify('.foo { container-type: inline-size }'));
        $t->same('.foo{container-name:none}', $minifier->minify('.foo { container-name: none; }'));
        $t->same('.foo{container-name:foo}', $minifier->minify('.foo { container-name: foo; }'));
        $t->same('.foo{container:foo}', $minifier->minify('.foo { container: foo / normal; }'));
        $t->same('.foo{container:foo/inline-size}', $minifier->minify('.foo { container: foo / inline-size; }'));
    },
    'css minifier maps upstream adjacent container query merging' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $css = <<<'CSS'
@container my-layout (inline-size > 45em) {
  .foo {
    color: red;
  }
}

@container my-layout (inline-size > 45em) {
  .foo {
    background: yellow;
  }

  .bar {
    color: white;
  }
}
CSS;

        $t->same(
            '@container my-layout (inline-size>45em){.foo{color:red;background:#ff0}.bar{color:#fff}}',
            $minifier->minify($css)
        );
    },
    'css minifier maps upstream container query unit calc folding' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{width:3cqw}', $minifier->minify('.foo { width: calc(1cqw + 2cqw) }'));
        $t->same('.foo{width:3cqh}', $minifier->minify('.foo { width: calc(1cqh + 2cqh) }'));
        $t->same('.foo{width:3cqi}', $minifier->minify('.foo { width: calc(1cqi + 2cqi) }'));
        $t->same('.foo{width:3cqb}', $minifier->minify('.foo { width: calc(1cqb + 2cqb) }'));
        $t->same('.foo{width:3cqmin}', $minifier->minify('.foo { width: calc(1cqmin + 2cqmin) }'));
        $t->same('.foo{width:3cqmax}', $minifier->minify('.foo { width: calc(1cqmax + 2cqmax) }'));
        $t->same(
            '.foo{background:url(calc(1cqw + 2cqw));width:calc(1rem + 2px)}',
            $minifier->minify('.foo { background: url(calc(1cqw + 2cqw)); width: calc(1rem + 2px); }')
        );
    },
    'css minifier maps upstream container query validation errors' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $invalid = [
            '@container none (width < 100vw) {}',
            '@container and (width < 100vw) {}',
            '@container or (width < 100vw) {}',
            '@container revert-layer (width < 100vw) {}',
            '@container initial (width < 100vw) {}',
            '@container foo bar (width < 100vw) {}',
            '@container (inline-size <= foo) {}',
            '@container (orientation <= 10px) {}',
            '@container style(style(--foo: bar)) {}',
            '@container scroll-state(scroll-state(scrollable: top)) {}',
            '@container unknown(foo) {}',
            '@container {}',
            '@container () {}',
            '@container foo () {}',
            '@container foo bar {}',
        ];

        foreach ($invalid as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify($css));
        }
    },
    'css minifier maps upstream media and container error recovery option' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->throws(InvalidArgumentException::class, static fn () => $minifier->minify('@container unknown(foo) {}'));

        $container = $minifier->minifyWithErrorRecovery("\n@container unknown(foo) {}\n.ok { color: yellow; }", 'wp.css');
        $t->same('.ok{color:#ff0}', $container['code']);
        $t->same(
            [
                [
                    'message' => 'Unexpected token Function("unknown")',
                    'type' => 'UnexpectedToken',
                    'loc' => ['filename' => 'wp.css', 'line' => 2, 'column' => 11],
                ],
            ],
            $container['warnings']
        );

        $media = $minifier->minifyWithErrorRecovery('@media unknown(foo) {} .ok { color: yellow; }', 'media.css');
        $t->same('.ok{color:#ff0}', $media['code']);
        $t->same(
            [
                [
                    'message' => 'Unexpected token Function("unknown")',
                    'type' => 'UnexpectedToken',
                    'loc' => ['filename' => 'media.css', 'line' => 1, 'column' => 7],
                ],
            ],
            $media['warnings']
        );

        $compound = $minifier->minifyWithErrorRecovery(
            '@container card (width > 30em) and unknown(foo) { .bad { color: red; } } @media screen and unknown(foo) { .bad { color: red; } } .ok { color: yellow; }',
            'compound.css'
        );
        $t->same('.ok{color:#ff0}', $compound['code']);
        $t->same(
            [
                [
                    'message' => 'Unexpected token Function("unknown")',
                    'type' => 'UnexpectedToken',
                    'loc' => ['filename' => 'compound.css', 'line' => 1, 'column' => 35],
                ],
                [
                    'message' => 'Unexpected token Function("unknown")',
                    'type' => 'UnexpectedToken',
                    'loc' => ['filename' => 'compound.css', 'line' => 1, 'column' => 91],
                ],
            ],
            $compound['warnings']
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
    'css minifier maps upstream transition shorthand value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{transition:width 2s}', $minifier->minify('.foo { transition: width 2s ease }'));
        $t->same(
            '.foo{transition:width 2s,height 1s}',
            $minifier->minify('.foo { transition: width 2s ease, height 1000ms cubic-bezier(0.25, 0.1, 0.25, 1) }')
        );
        $t->same('.foo{transition:width 2s 1s}', $minifier->minify('.foo { transition: width 2s 1s }'));
        $t->same('.foo{transition:width 2s 1s}', $minifier->minify('.foo { transition: width 2s ease 1s }'));
        $t->same('.foo{transition:width 1s ease-in 4s}', $minifier->minify('.foo { transition: ease-in 1s width 4s }'));
        $t->same('.foo{transition:opacity 0s .6s}', $minifier->minify('.foo { transition: opacity 0s .6s }'));
        $t->same(
            '.foo{-webkit-transition:background .2s;-moz-transition:background .2s;transition:background .23s}',
            $minifier->minify('.foo { -webkit-transition: background 200ms; -moz-transition: background 200ms; transition: background 230ms }')
        );
    },
    'css minifier maps upstream list-style value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $star = "\u{2605}";
        $openCircle = "\u{25CB}";
        $filledCircle = "\u{25CF}";

        $t->same('.foo{list-style-type:disc}', $minifier->minify('.foo { list-style-type: disc; }'));
        $t->same('.foo{list-style-type:"' . $star . '"}', $minifier->minify('.foo { list-style-type: "' . $star . '"; }'));
        $t->same(
            '.foo{list-style-type:symbols(cyclic "' . $openCircle . '" "' . $filledCircle . '")}',
            $minifier->minify(".foo { list-style-type: symbols(cyclic '" . $openCircle . "' '" . $filledCircle . "'); }")
        );
        $t->same(
            '.foo{list-style-type:symbols("' . $openCircle . '" "' . $filledCircle . '")}',
            $minifier->minify(".foo { list-style-type: symbols('" . $openCircle . "' '" . $filledCircle . "'); }")
        );
        $t->same(
            '.foo{list-style-type:symbols("' . $openCircle . '" "' . $filledCircle . '")}',
            $minifier->minify(".foo { list-style-type: symbols(symbolic '" . $openCircle . "' '" . $filledCircle . "'); }")
        );
        $t->same(
            '.foo{list-style-type:symbols(url(ellipse.png))}',
            $minifier->minify(".foo { list-style-type: symbols(symbolic url('ellipse.png')); }")
        );
        $t->same('.foo{list-style-image:url(ellipse.png)}', $minifier->minify(".foo { list-style-image: url('ellipse.png'); }"));
        $t->same('.foo{list-style-position:outside}', $minifier->minify('.foo { list-style-position: outside; }'));
        $t->same('.foo{list-style:url(ellipse.png) "' . $star . '"}', $minifier->minify('.foo { list-style: "' . $star . '" url(ellipse.png) outside; }'));
        $t->same('.foo{list-style:none}', $minifier->minify('.foo { list-style: none; }'));
        $t->same('.foo{list-style:none}', $minifier->minify('.foo { list-style: none none outside; }'));
        $t->same('.foo{list-style:inside none}', $minifier->minify('.foo { list-style: none none inside; }'));
        $t->same('.foo{list-style:inside none}', $minifier->minify('.foo { list-style: none inside; }'));
        $t->same('.foo{list-style:outside}', $minifier->minify('.foo { list-style: none disc; }'));
        $t->same('.foo{list-style:inside}', $minifier->minify('.foo { list-style: none inside disc; }'));
        $t->same('.foo{list-style:"' . $star . '"}', $minifier->minify('.foo { list-style: none "' . $star . '"; }'));
        $t->same('.foo{list-style:url(foo.png) none}', $minifier->minify('.foo { list-style: none url(foo.png); }'));
    },
    'css minifier maps upstream list-style longhand composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();
        $star = "\u{2605}";

        $t->same(
            '.foo{list-style:url("ellipse.png")}',
            $minifier->minify('.foo { list-style-type: disc; list-style-image: url(ellipse.png); list-style-position: outside; }')
        );
        $t->same(
            '.foo{list-style:"' . $star . '"}',
            $minifier->minify('.foo { list-style: "' . $star . '" url(ellipse.png) outside; list-style-image: none; }')
        );
        $t->same(
            '.foo{list-style:url("ellipse.png") "' . $star . '";list-style-image:var(--img)}',
            $minifier->minify('.foo { list-style: "' . $star . '" url(ellipse.png) outside; list-style-image: var(--img); }')
        );
        $t->same(
            '.foo{list-style:inside}',
            $minifier->minify('.foo { list-style: inside; list-style-type: disc; }')
        );
        $t->same(
            '.foo{list-style:inside decimal}',
            $minifier->minify('.foo { list-style: inside; list-style-type: decimal; }')
        );
    },
    'css minifier maps upstream transition longhand composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{transition:opacity 90ms ease-in-out .5s}',
            $minifier->minify('.foo { transition-property: opacity; transition-duration: 0.09s; transition-timing-function: ease-in-out; transition-delay: 500ms; }')
        );
        $t->same(
            '.foo{transition:opacity 2s .5s}',
            $minifier->minify('.foo { transition: opacity 2s; transition-timing-function: ease; transition-delay: 500ms; }')
        );
        $t->same(
            '.foo{transition:opacity .5s;transition-timing-function:var(--ease)}',
            $minifier->minify('.foo { transition: opacity 500ms; transition-timing-function: var(--ease); }')
        );
        $t->same(
            '.foo{transition:color 2s}',
            $minifier->minify('.foo { transition-property: opacity; transition-duration: 0.09s; transition-timing-function: ease-in-out; transition-delay: 500ms; transition: color 2s; }')
        );
    },
    'css minifier maps upstream transition list and prefix composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{transition:opacity 2s ease-in-out .5s,color 4s ease-in}',
            $minifier->minify('.foo { transition-property: opacity, color; transition-duration: 2s, 4s; transition-timing-function: ease-in-out, ease-in; transition-delay: 500ms, 0s; }')
        );
        $t->same(
            '.foo{transition:opacity 2s,color 4s,width 2s,height 4s}',
            $minifier->minify('.foo { transition-property: opacity, color, width, height; transition-duration: 2s, 4s; transition-timing-function: ease; transition-delay: 0s; }')
        );
        $t->same(
            '.foo{-webkit-transition:opacity 2s ease-in-out .5s,color 4s ease-in;-moz-transition:opacity 2s ease-in-out .5s,color 4s ease-in;transition:opacity 2s ease-in-out .5s,color 4s ease-in}',
            $minifier->minify('.foo { -webkit-transition-property: opacity, color; -webkit-transition-duration: 2s, 4s; -webkit-transition-timing-function: ease-in-out, ease-in; -webkit-transition-delay: 500ms, 0s; -moz-transition-property: opacity, color; -moz-transition-duration: 2s, 4s; -moz-transition-timing-function: ease-in-out, ease-in; -moz-transition-delay: 500ms, 0s; transition-property: opacity, color; transition-duration: 2s, 4s; transition-timing-function: ease-in-out, ease-in; transition-delay: 500ms, 0s; }')
        );
    },
    'css minifier maps upstream transition logical block properties' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{transition-property:margin-top,margin-bottom}',
            $minifier->minify('.foo { transition-property: margin-block; }')
        );
        $t->same(
            '.foo{transition:margin-top 2s}',
            $minifier->minify('.foo { transition: margin-block-start 2s; }')
        );
        $t->same(
            '.foo{transition:padding-top .2s,padding-bottom .2s}',
            $minifier->minify('.foo { transition: padding-block 200ms; }')
        );
    },
    'css minifier maps upstream filter value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{filter:url(filters.svg#filter-id)}',
            $minifier->minify(".foo { filter: url('filters.svg#filter-id'); }")
        );
        $t->same('.foo{filter:blur(5px)}', $minifier->minify('.foo { filter: blur(5px); }'));
        $t->same('.foo{filter:blur()}', $minifier->minify('.foo { filter: blur(0px); }'));
        $t->same('.foo{filter:brightness(10%)}', $minifier->minify('.foo { filter: brightness(10%); }'));
        $t->same('.foo{filter:brightness()}', $minifier->minify('.foo { filter: brightness(100%); }'));
        $t->same(
            '.foo{filter:drop-shadow(16px 16px 20px #ff0)}',
            $minifier->minify('.foo { filter: drop-shadow(16px 16px 20px yellow); }')
        );
        $t->same(
            '.foo{filter:contrast(175%)brightness(3%)}',
            $minifier->minify('.foo { filter: contrast(175%) brightness(3%); }')
        );
        $t->same('.foo{filter:hue-rotate()}', $minifier->minify('.foo { filter: hue-rotate(0) }'));
    },
    'css minifier maps upstream box shadow value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{box-shadow:64px 64px 12px 40px #0006}',
            $minifier->minify('.foo { box-shadow: 64px 64px 12px 40px rgba(0,0,0,0.4) }')
        );
        $t->same(
            '.foo{box-shadow:inset 12px 12px 0 8px #0006}',
            $minifier->minify('.foo { box-shadow: 12px 12px 0px 8px rgba(0,0,0,0.4) inset }')
        );
        $t->same(
            '.foo{box-shadow:inset 12px 12px 0 8px #0006}',
            $minifier->minify('.foo { box-shadow: inset 12px 12px 0px 8px rgba(0,0,0,0.4) }')
        );
        $t->same(
            '.foo{box-shadow:12px 12px 8px #0006}',
            $minifier->minify('.foo { box-shadow: 12px 12px 8px 0px rgba(0,0,0,0.4) }')
        );
        $t->same(
            '.foo{box-shadow:12px 12px #0006}',
            $minifier->minify('.foo { box-shadow: 12px 12px 0px 0px rgba(0,0,0,0.4) }')
        );
        $t->same(
            '.foo{box-shadow:64px 64px 12px 40px #0006,inset 12px 12px 0 8px #0006}',
            $minifier->minify('.foo { box-shadow: 64px 64px 12px 40px rgba(0,0,0,0.4), 12px 12px 0px 8px rgba(0,0,0,0.4) inset }')
        );
    },
    'css minifier maps upstream text shadow value minification' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{text-shadow:1px 1px 2px #ff0}',
            $minifier->minify('.foo { text-shadow: 1px 1px 2px yellow; }')
        );
        $t->same(
            '.foo{text-shadow:1px 1px 2px 3px #ff0}',
            $minifier->minify('.foo { text-shadow: 1px 1px 2px 3px yellow; }')
        );
        $t->same(
            '.foo{text-shadow:1px 1px #ff0}',
            $minifier->minify('.foo { text-shadow: 1px 1px 0 yellow; }')
        );
        $t->same(
            '.foo{text-shadow:1px 1px #ff0}',
            $minifier->minify('.foo { text-shadow: 1px 1px yellow; }')
        );
        $t->same(
            '.foo{text-shadow:1px 1px #ff0,2px 3px red}',
            $minifier->minify('.foo { text-shadow: 1px 1px yellow, 2px 3px red; }')
        );
    },
    'css minifier maps upstream caret values' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{caret-color:auto}', $minifier->minify('.foo { caret-color: auto }'));
        $t->same('.foo{caret-color:#ff0}', $minifier->minify('.foo { caret-color: yellow }'));
        $t->same('.foo{caret-shape:block}', $minifier->minify('.foo { caret-shape: block }'));
        $t->same('.foo{caret:#ff0 block}', $minifier->minify('.foo { caret: yellow block }'));
        $t->same('.foo{caret:#ff0 block}', $minifier->minify('.foo { caret: block yellow }'));
        $t->same('.foo{caret:block}', $minifier->minify('.foo { caret: block }'));
        $t->same('.foo{caret:#ff0}', $minifier->minify('.foo { caret: yellow }'));
        $t->same('.foo{caret:auto}', $minifier->minify('.foo { caret: auto auto }'));
        $t->same('.foo{caret:auto}', $minifier->minify('.foo { caret: auto }'));
        $t->same('.foo{caret:#ff0}', $minifier->minify('.foo { caret: yellow auto }'));
        $t->same('.foo{caret:block}', $minifier->minify('.foo { caret: auto block }'));
    },
    'css minifier maps upstream text emphasis values and composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same('.foo{text-emphasis-style:none}', $minifier->minify('.foo { text-emphasis-style: none }'));
        $t->same('.foo{text-emphasis-style:filled}', $minifier->minify('.foo { text-emphasis-style: filled }'));
        $t->same('.foo{text-emphasis-style:open}', $minifier->minify('.foo { text-emphasis-style: open }'));
        $t->same('.foo{text-emphasis-style:dot}', $minifier->minify('.foo { text-emphasis-style: dot }'));
        $t->same('.foo{text-emphasis-style:dot}', $minifier->minify('.foo { text-emphasis-style: filled dot }'));
        $t->same('.foo{text-emphasis-style:dot}', $minifier->minify('.foo { text-emphasis-style: dot filled }'));
        $t->same('.foo{text-emphasis-style:open dot}', $minifier->minify('.foo { text-emphasis-style: open dot }'));
        $t->same('.foo{text-emphasis-style:open dot}', $minifier->minify('.foo { text-emphasis-style: dot open }'));
        $t->same('.foo{text-emphasis-style:"x"}', $minifier->minify('.foo { text-emphasis-style: "x" }'));
        $t->same('.foo{text-emphasis-color:#ff0}', $minifier->minify('.foo { text-emphasis-color: yellow }'));
        $t->same('.foo{text-emphasis:none}', $minifier->minify('.foo { text-emphasis: none }'));
        $t->same('.foo{text-emphasis:filled}', $minifier->minify('.foo { text-emphasis: filled }'));
        $t->same('.foo{text-emphasis:filled #ff0}', $minifier->minify('.foo { text-emphasis: filled yellow }'));
        $t->same('.foo{text-emphasis:dot #ff0}', $minifier->minify('.foo { text-emphasis: dot filled yellow }'));
        $t->same('.foo{text-emphasis:filled #ff0}', $minifier->minify('.foo { text-emphasis-style: filled; text-emphasis-color: yellow; }'));
        $t->same('.foo{text-emphasis:filled #ff0}', $minifier->minify('.foo { text-emphasis: filled red; text-emphasis-color: yellow; }'));
        $t->same('.foo{text-emphasis:filled #ff0;text-emphasis-color:var(--color)}', $minifier->minify('.foo { text-emphasis: filled yellow; text-emphasis-color: var(--color); }'));
        $t->same('.foo{text-emphasis-position:over}', $minifier->minify('.foo { text-emphasis-position: over }'));
        $t->same('.foo{text-emphasis-position:under}', $minifier->minify('.foo { text-emphasis-position: under }'));
        $t->same('.foo{text-emphasis-position:over}', $minifier->minify('.foo { text-emphasis-position: over right }'));
        $t->same('.foo{text-emphasis-position:over left}', $minifier->minify('.foo { text-emphasis-position: over left }'));
    },
    'wordpress block theme fixture minifies without breaking custom property math' => static function (TestRunner $t): void {
        $css = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-theme.css');
        $expected = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-theme.min.css');
        $t->same(trim($expected), (new CssMinifier())->minify($css));
    },
    'wordpress cover overlay insets compose without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover .wp-block-cover__background {
  top: 0;
  left: 0;
  bottom: 0;
  right: 0;
}

.wp-block-cover.alignwide .wp-block-cover__inner-container {
  inset-block-start: var(--wp--preset--spacing--40);
  inset-block-end: var(--wp--preset--spacing--40);
  inset-inline-start: 24px;
  inset-inline-end: 32px;
}
CSS;

        $t->same(
            '.wp-block-cover .wp-block-cover__background{inset:0}.wp-block-cover.alignwide .wp-block-cover__inner-container{inset-block:var(--wp--preset--spacing--40);inset-inline:24px 32px}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress fluid block spacing math functions minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-query {
  gap: round(23px, 5px);
  margin-block-start: rem(38px, 12px);
  padding-inline: mod(42px, 16px);
  width: calc(10px * round(22, 5));
  border-width: clamp(1rem + 1rem, 1rem + 3rem, 6rem);
}
CSS;

        $t->same(
            '.wp-block-query{gap:25px;margin-block-start:2px;padding-inline:10px;width:200px;border-width:4rem}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress block depth math functions minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-depth {
  outline-offset: abs(-4px);
  margin-block-start: calc(1px * hypot(3, 4));
  padding-block: calc(1rem * pow(2, 2));
  translate: 0 calc(10px * sign(-1vw));
  width: calc(100% + 10px * sign(1%));
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-depth{outline-offset:4px;margin-block-start:5px;padding-block:4rem;translate:0 -10px;width:calc(100% + 10px * sign(1%))}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress cover transform functions normalize without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-lift:hover {
  transform: translate3d(0px, 12px, 0px) scale(100%, 105%);
}

.wp-block-cover.is-style-lift:active {
  transform: translateX(calc(4px + 8px)) scale3d(100%, 100%, 100%);
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-lift:hover{transform:translateY(12px)scaleY(1.05)}.wp-block-cover.is-style-lift:active{transform:translate(12px)scale(1)}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress gallery transform rotate and longhands minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-gallery.is-style-tilt .wp-block-image {
  transform: translateX(calc(2in + 50px)) rotate3d(0, 1, 0, 15deg) skew(0deg, 3deg);
  rotate: 10deg 0 0 -1;
  scale: 100% 105% 1;
}
CSS;

        $t->same(
            '.wp-block-gallery.is-style-tilt .wp-block-image{transform:translate(242px)rotateY(15deg)skewY(3deg);rotate:-10deg;scale:1 1.05}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress editor color scheme values minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
:root {
  color-scheme: dark light only;
}

.editor-styles-wrapper.is-light-theme {
  color-scheme: only light;
}

.editor-styles-wrapper.is-dark-theme {
  color-scheme: only dark;
}
CSS;

        $t->same(
            ':root{color-scheme:light dark only}.editor-styles-wrapper.is-light-theme{color-scheme:light only}.editor-styles-wrapper.is-dark-theme{color-scheme:dark only}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress view transition rules minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@view-transition {
  navigation: auto;
  types: page nav-menu;
}

:root:active-view-transition-type(page, nav-menu) {
  color: yellow;
}

.wp-block-navigation__responsive-container {
  view-transition-name: wp-nav-menu;
}
CSS;

        $t->same(
            '@view-transition{navigation:auto;types:page nav-menu}:root:active-view-transition-type(page,nav-menu){color:#ff0}.wp-block-navigation__responsive-container{view-transition-name:wp-nav-menu}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress block interaction transition shorthands minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-button.is-style-slide .wp-element-button {
  transition: ease-in 1s transform 400ms, opacity 1000ms cubic-bezier(0.25, 0.1, 0.25, 1);
}
CSS;

        $t->same(
            '.wp-block-button.is-style-slide .wp-element-button{transition:transform 1s ease-in .4s,opacity 1s}',
            (new CssMinifier())->minify($css)
        );
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
    'wordpress block interaction longhands compose to transition shorthand without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-query-pagination a {
  transition-property: opacity, transform;
  transition-duration: 90ms, 200ms;
  transition-timing-function: ease-in-out, cubic-bezier(0.25, 0.1, 0.25, 1);
  transition-delay: 500ms, 0s;
}
CSS;

        $t->same(
            '.wp-block-query-pagination a{transition:opacity 90ms ease-in-out .5s,transform .2s}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress theme font stacks serialize without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-post-title {
  font-family: "Inter", "Helvetica Neue", sans-serif;
  font-stretch: expanded;
}

@font-face {
  font-family: "revert";
  src: url("./fonts/revert.woff2") format("woff2");
}
CSS;

        $t->same(
            '.wp-block-post-title{font-family:Inter,Helvetica Neue,sans-serif;font-stretch:125%}@font-face{font-family:"revert";src:url(./fonts/revert.woff2)format("woff2")}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress variable font palettes minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@font-palette-values --wp-duotone-accent {
  font-family: Bixa;
  base-palette: 1;
  override-colors: 1 #7EB7E4, 3 var(--wp--preset--color--accent);
}

.wp-block-heading.is-style-color-font {
  font-palette: --wp-duotone-accent;
}
CSS;

        $t->same(
            '@font-palette-values --wp-duotone-accent{font-family:Bixa;base-palette:1;override-colors:1 #7eb7e4, 3 var(--wp--preset--color--accent)}.wp-block-heading.is-style-color-font{font-palette:--wp-duotone-accent}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress OpenType feature aliases minify and merge without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@font-feature-values "Inter", "Inter Variable" {
  @styleset {
    open-digits: 1;
  }
  @styleset {
    disambiguation: 2;
  }
}

@font-feature-values "Inter", "Inter Variable" {
  @character-variant {
    single-storey-a: 11;
  }
}
CSS;

        $t->same(
            '@font-feature-values Inter,Inter Variable{@styleset{open-digits:1;disambiguation:2}@character-variant{single-storey-a:11}}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress registered design tokens minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@property --wp--custom--card-accent {
  syntax: '<color>';
  inherits: false;
  initial-value: yellow;
}

.wp-block-query .wp-block-post {
  color: var(--wp--custom--card-accent);
}

@property --wp--custom--card-accent {
  initial-value: blue;
  inherits: true;
  syntax: '<color>';
}

@property --wp--custom--motion-duration {
  syntax: '<time>';
  inherits: false;
  initial-value: 1000ms;
}
CSS;

        $t->same(
            '@property --wp--custom--card-accent{syntax:"<color>";inherits:true;initial-value:#00f}.wp-block-query .wp-block-post{color:var(--wp--custom--card-accent)}@property --wp--custom--motion-duration{syntax:"<time>";inherits:false;initial-value:1s}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress logical spacing transitions expand block axis properties without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-group.is-style-reveal {
  transition-property: margin-block;
  transition-duration: 200ms;
  transition-timing-function: ease;
  transition-delay: 0s;
}
CSS;

        $t->same(
            '.wp-block-group.is-style-reveal{transition:margin-top .2s,margin-bottom .2s}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress block animation names and timing aliases minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-entrance {
  animation-name: "wp-cover-entrance", "unset";
  animation-timing-function: cubic-bezier(0.42, 0, 1, 1), steps(5, jump-start);
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-entrance{animation-name:wp-cover-entrance,"unset";animation-timing-function:ease-in,steps(5,start)}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress block animation shorthand presets minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-entrance {
  animation: "wp-cover-entrance" 3s cubic-bezier(0.42, 0, 1, 1) 100ms 2.0 alternate Backwards running scroll(block);
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-entrance{animation:3s ease-in .1s 2 alternate backwards wp-cover-entrance scroll()}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress block keyframes minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@keyframes "wp-cover-reveal" {
  from {
    opacity: 0;
    transform: translateY(calc(10px + 20px));
  }

  100% {
    opacity: 1;
    background: blue;
  }
}

.wp-block-cover.is-style-reveal {
  animation: "wp-cover-reveal" 600ms cubic-bezier(0.42, 0, 1, 1) both;
}
CSS;

        $t->same(
            '@keyframes wp-cover-reveal{0%{opacity:0;transform:translateY(30px)}to{opacity:1;background:#00f}}.wp-block-cover.is-style-reveal{animation:.6s ease-in both wp-cover-reveal}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress block animation longhands compose without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-entrance {
  animation-name: wp-cover-entrance;
  animation-duration: 90ms;
  animation-timing-function: ease-in-out;
  animation-delay: 100ms;
  animation-iteration-count: 2;
  animation-direction: alternate;
  animation-fill-mode: forwards;
  animation-play-state: running;
  animation-timeline: scroll();
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-entrance{animation:90ms ease-in-out .1s 2 alternate forwards wp-cover-entrance scroll()}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress scroll linked cover animation ranges compose without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-cover.is-style-scroll-reveal {
  animation: wp-cover-reveal 500ms ease;
  animation-range-start: entry 0%;
  animation-range-end: exit 90%;
}
CSS;

        $t->same(
            '.wp-block-cover.is-style-scroll-reveal{animation:.5s wp-cover-reveal;animation-range:entry exit 90%}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress block container queries and container shorthand minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-query {
  container-name: wp-query-card;
  container-type: inline-size;
}

@container wp-query-card (inline-size > 45em) and style(--wp--custom--dense: true) {
  .wp-block-post-template {
    color: yellow;
  }
}
CSS;

        $t->same(
            '.wp-block-query{container:wp-query-card/inline-size}@container wp-query-card (inline-size>45em) and style(--wp--custom--dense:true){.wp-block-post-template{color:#ff0}}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress adjacent block container rules merge and fold cqw units without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@container wp-query-card (inline-size > 45em) {
  .wp-block-post-template {
    gap: calc(1cqw + 2cqw);
  }
}

@container wp-query-card (inline-size > 45em) {
  .wp-block-post-template {
    color: yellow;
  }
}
CSS;

        $t->same(
            '@container wp-query-card (inline-size>45em){.wp-block-post-template{gap:3cqw;color:#ff0}}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress invalid block container queries fail before shipping css' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@container none (width < 100vw) {
  .wp-block-query {
    gap: 1rem;
  }
}
CSS;

        $t->throws(InvalidArgumentException::class, static fn () => (new CssMinifier())->minify($css));
    },
    'wordpress conditional block stylesheet imports minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@charset "UTF-8";
@layer blocks;
@import url(./blocks/query-card.css) supports((display: grid)) screen and (min-width: 600px);

.wp-block-query {
  color: yellow;
}
CSS;

        $t->same(
            '@layer blocks;@import "./blocks/query-card.css" supports(display:grid) screen and (width>=600px);.wp-block-query{color:#ff0}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress layer ordered responsive block css minifies without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@layer theme;
@layer blocks {}
@layer utilities;

@layer blocks {
  .wp-block-query {
    color: red;
  }
}

@layer blocks {
  .wp-block-query {
    background: #fff;
  }

  @media all {
    .wp-block-query__empty {
      color: chartreuse;
    }
  }

  @media all, all {
    .wp-block-query__all-list {
      color: chartreuse;
    }
  }

  @media not all {
    .wp-block-query__debug {
      color: red;
    }
  }

  @media not all, not all {
    .wp-block-query__dead-list {
      color: red;
    }
  }

  @media (min-width: 600px) and ((hover) and (color)) {
    .wp-block-query {
      color: yellow;
    }
  }
}
CSS;

        $t->same(
            '@layer theme;@layer blocks{.wp-block-query{color:red;background:#fff}.wp-block-query__empty{color:#7fff00}.wp-block-query__all-list{color:#7fff00}@media (width>=600px) and (hover) and (color){.wp-block-query{color:#ff0}}}@layer utilities;',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress svg namespace selectors minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@namespace svg url(http://www.w3.org/2000/svg);
@namespace xlink url(http://www.w3.org/1999/xlink);

.wp-block-navigation svg|svg {
  fill: currentColor;
}

.wp-block-social-links svg|a[xlink|href=icon] {
  color: yellow;
}
CSS;

        $t->same(
            '@namespace svg "http://www.w3.org/2000/svg";@namespace xlink "http://www.w3.org/1999/xlink";.wp-block-navigation svg|svg{fill:currentColor}.wp-block-social-links svg|a[xlink|href="icon"]{color:#ff0}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress print export page rules minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@page chapter:left {
  margin: 0.5in;
  @bottom-left {
    content: "Chapter";
    margin: 10pt;
  }
}

@page toc, index {
  margin: 0.5cm;
}
CSS;

        $t->same(
            '@page chapter:left{margin:.5in;@bottom-left{content:"Chapter";margin:10pt}}@page toc,index{margin:.5cm}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress scoped block styles minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@scope (.wp-block-group.is-style-card) to (.wp-block-buttons) {
  .wp-block-heading {
    color: yellow;
  }

  .wp-block-image img {
    border-color: blue;
  }
}
CSS;

        $t->same(
            '@scope(.wp-block-group.is-style-card) to (.wp-block-buttons){.wp-block-heading{color:#ff0}.wp-block-image img{border-color:#00f}}',
            (new CssMinifier())->minify($css)
        );
    },
    'css minifier maps upstream border-radius shorthand compaction' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{border-radius:10px 100px}',
            $minifier->minify('.foo { border-radius: 10px 100px 10px 100px; }')
        );
        $t->same(
            '.foo{border-radius:10px 100px/120px}',
            $minifier->minify('.foo { border-radius: 10px 100px 10px 100px / 120px 120px; }')
        );
        $t->same(
            '.foo{-webkit-border-radius:10px 100px;-moz-border-radius:10px 100px;border-radius:10px 100px}',
            $minifier->minify('.foo { -webkit-border-radius: 10px 100px 10px 100px; -moz-border-radius: 10px 100px 10px 100px; border-radius: 10px 100px 10px 100px; }')
        );
        $t->same(
            '.foo{border-radius:0 10px}',
            $minifier->minify('.foo { border-radius: 0px 10px 0px 10px; }')
        );
    },
    'css minifier maps upstream border-radius longhand composition' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        $t->same(
            '.foo{border-radius:10px}',
            $minifier->minify(
                '.foo { border-top-left-radius: 10px; border-top-right-radius: 10px; border-bottom-right-radius: 10px; border-bottom-left-radius: 10px; }'
            )
        );
        $t->same(
            '.foo{border-radius:10px 20px/1px 2px}',
            $minifier->minify(
                '.foo { border-top-left-radius: 10px 1px; border-top-right-radius: 20px 2px; border-bottom-right-radius: 10px 1px; border-bottom-left-radius: 20px 2px; }'
            )
        );
        $t->same(
            '.foo{border-radius:10px 20px}',
            $minifier->minify(
                '.foo { border-top-left-radius: 2px; border-radius: 4px; border-top-left-radius: 10px; border-top-right-radius: 20px; border-bottom-right-radius: 10px; border-bottom-left-radius: 20px; }'
            )
        );
        $t->same(
            '.foo{border-radius:8px 16px/4px 12px}',
            $minifier->minify(
                '.foo { border-radius: 999px; border-top-left-radius: 8px 4px; border-top-right-radius: 16px 12px; border-bottom-right-radius: 8px 4px; border-bottom-left-radius: 16px 12px; }'
            )
        );
        $t->same(
            '.foo{border-radius:10px 4px 4px}',
            $minifier->minify('.foo { border-radius: 4px; border-top-left-radius: 10px; }')
        );
        $t->same(
            '.foo{border-radius:20px 10px 10px}',
            $minifier->minify('.foo { border-radius: 10px; border-top-left-radius: 20px; }')
        );
        $t->same(
            '.foo{border-radius:10px;border-top-left-radius:var(--test)}',
            $minifier->minify('.foo { border-radius: 10px; border-top-left-radius: var(--test); }')
        );
        $t->same(
            '.foo{-webkit-border-radius:8px 16px;-moz-border-radius:8px 16px}',
            $minifier->minify(
                '.foo { -webkit-border-top-left-radius: 8px; -webkit-border-top-right-radius: 16px; -webkit-border-bottom-right-radius: 8px; -webkit-border-bottom-left-radius: 16px; -moz-border-top-left-radius: 8px; -moz-border-top-right-radius: 16px; -moz-border-bottom-right-radius: 8px; -moz-border-bottom-left-radius: 16px; }'
            )
        );
        $t->same(
            '.foo{border-radius:10px 100px/120px;border-start-start-radius:10px}',
            $minifier->minify(
                '.foo { border-radius: 10px 100px 10px 100px / 120px 120px; border-start-start-radius: 10px; }'
            )
        );
        $t->same(
            '.foo{border-radius:10px 100px/120px}',
            $minifier->minify(
                '.foo { border-start-start-radius: 10px; border-radius: 10px 100px 10px 100px / 120px 120px; }'
            )
        );
        $t->same(
            '.foo{border-top-left-radius:10px 120px;border-top-right-radius:100px 120px;border-start-start-radius:10px;border-bottom-right-radius:100px 120px;border-bottom-left-radius:10px 120px}',
            $minifier->minify(
                '.foo { border-top-left-radius: 10px 120px; border-top-right-radius: 100px 120px; border-start-start-radius: 10px; border-bottom-right-radius: 100px 120px; border-bottom-left-radius: 10px 120px; }'
            )
        );
    },
    'wordpress supports-gated block layouts minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@supports (((display: grid) and (not (display: subgrid)))) {
  .wp-block-query > .wp-block-post-template {
    display: grid;
    color: yellow;
  }
}
CSS;

        $t->same(
            '@supports (display:grid) and (not (display:subgrid)){.wp-block-query>.wp-block-post-template{display:grid;color:#ff0}}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress supports-gated block values minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@supports (width: calc(10px * 2)) {
  .wp-block-gallery {
    width: calc(10px * 2);
  }
}

@supports (color: hsl(0deg, 0%, 0%)) {
  .wp-block-post-title {
    color: hsl(0deg, 0%, 0%);
  }
}
CSS;

        $t->same(
            '@supports (width:calc(10px * 2)){.wp-block-gallery{width:20px}}@supports (color:hsl(0deg, 0%, 0%)){.wp-block-post-title{color:#000}}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress image filter presets minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-image.is-style-duotone img {
  filter: contrast(175%) brightness(100%) hue-rotate(0);
}
CSS;

        $t->same(
            '.wp-block-image.is-style-duotone img{filter:contrast(175%)brightness()hue-rotate()}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress card shadow presets minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-post-template .wp-block-post {
  box-shadow: 12px 12px 0px 0px rgba(0,0,0,0.4), 0px 0px 12px 4px rgba(0,0,0,0.4) inset;
}
CSS;

        $t->same(
            '.wp-block-post-template .wp-block-post{box-shadow:12px 12px #0006,inset 0 0 12px 4px #0006}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress table border spacing presets minify without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-table.is-style-loose-spacing table {
  border-collapse: separate;
  border-spacing: 0px 12px;
}

.wp-block-table.is-style-compact-spacing table {
  border-spacing: 0px 0px;
}
CSS;

        $t->same(
            '.wp-block-table.is-style-loose-spacing table{border-collapse:separate;border-spacing:0 12px}.wp-block-table.is-style-compact-spacing table{border-spacing:0}',
            (new CssMinifier())->minify($css)
        );
    },
    'wordpress block reset declarations prune reset properties without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-quote.is-style-plain {
  --wp--custom--quote-accent: currentcolor;
  margin-block-start: var(--wp--preset--spacing--40);
  background: var(--wp--preset--color--base);
  direction: rtl;
  all: unset;
  display: block;
  background: var(--wp--preset--color--contrast);
}
CSS;

        $t->same(
            '.wp-block-quote.is-style-plain{--wp--custom--quote-accent:currentcolor;all:unset;direction:rtl;display:block;background:var(--wp--preset--color--contrast)}',
            (new CssMinifier())->minify($css)
        );
    },
    'declaration parser handles semicolons and colons inside functions' => static function (TestRunner $t): void {
        $parsed = (new DeclarationBlock())->parse('background: url("https://example.test/a;b"); color: red');
        $t->same('url("https://example.test/a;b")', $parsed['background']);
        $t->same('red', $parsed['color']);
    },
];
