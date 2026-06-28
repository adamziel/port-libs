<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

return [
    'css minifier maps residual upstream transition tail composition rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_transitions residual rows.
        $cases = [
            [
                11574,
                '.foo { transition-property: opacity, color; transition-duration: 2s; transition-timing-function: ease-in-out; transition-delay: 500ms; }',
                '.foo{transition:opacity 2s ease-in-out .5s,color 2s ease-in-out .5s}',
            ],
            [
                11605,
                '.foo { -webkit-transition-property: opacity, color; -webkit-transition-duration: 2s, 4s; -webkit-transition-timing-function: ease-in-out, ease-in; -webkit-transition-delay: 500ms, 0s; }',
                '.foo{-webkit-transition:opacity 2s ease-in-out .5s,color 4s ease-in}',
            ],
            [
                11647,
                '.foo { -webkit-transition-property: opacity, color; -moz-transition-property: opacity, color; transition-property: opacity, color; -webkit-transition-duration: 2s, 4s; -moz-transition-duration: 2s, 4s; transition-duration: 2s, 4s; -webkit-transition-timing-function: ease-in-out, ease-in; transition-timing-function: ease-in-out, ease-in; -moz-transition-timing-function: ease-in-out, ease-in; -webkit-transition-delay: 500ms, 0s; -moz-transition-delay: 500ms, 0s; transition-delay: 500ms, 0s; }',
                '.foo{-webkit-transition:opacity 2s ease-in-out .5s,color 4s ease-in;-moz-transition:opacity 2s ease-in-out .5s,color 4s ease-in;transition:opacity 2s ease-in-out .5s,color 4s ease-in}',
            ],
        ];

        foreach ($cases as [$line, $input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_transitions line ' . $line);
        }
    },
];
