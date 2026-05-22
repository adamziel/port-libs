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
    'wordpress block theme fixture minifies without breaking custom property math' => static function (TestRunner $t): void {
        $css = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-theme.css');
        $expected = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-theme.min.css');
        $t->same(trim($expected), (new CssMinifier())->minify($css));
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
    'declaration parser handles semicolons and colons inside functions' => static function (TestRunner $t): void {
        $parsed = (new DeclarationBlock())->parse('background: url("https://example.test/a;b"); color: red');
        $t->same('url("https://example.test/a;b")', $parsed['background']);
        $t->same('red', $parsed['color']);
    },
];
