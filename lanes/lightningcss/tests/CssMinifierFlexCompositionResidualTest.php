<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

return [
    'css minifier maps upstream flex-flow composition rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_flex flex-flow test() rows.
        $cases = [
            4949 => ['.foo{flex-direction:column;flex-wrap:wrap}', '.foo{flex-flow:column wrap}'],
            4964 => ['.foo{flex-direction:row;flex-wrap:wrap}', '.foo{flex-flow:wrap}'],
            4979 => ['.foo{flex-direction:row;flex-wrap:nowrap}', '.foo{flex-flow:row}'],
            4994 => ['.foo{flex-direction:column;flex-wrap:nowrap}', '.foo{flex-flow:column}'],
        ];

        foreach ($cases as $line => [$input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_flex line ' . $line);
        }
    },
    'css minifier maps upstream flex shorthand composition rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_flex flex item test() rows.
        $cases = [
            5009 => ['.foo{flex-grow:1;flex-shrink:1;flex-basis:0%}', '.foo{flex:1}'],
            5025 => ['.foo{flex-grow:1;flex-shrink:1;flex-basis:0}', '.foo{flex:1 1 0}'],
            5041 => ['.foo{flex-grow:1;flex-shrink:1;flex-basis:0px}', '.foo{flex:1 1 0}'],
            5057 => ['.foo{flex-grow:1;flex-shrink:2;flex-basis:0%}', '.foo{flex:1 2}'],
            5073 => ['.foo{flex-grow:2;flex-shrink:1;flex-basis:0%}', '.foo{flex:2}'],
            5089 => ['.foo{flex-grow:2;flex-shrink:2;flex-basis:0%}', '.foo{flex:2 2}'],
            5105 => ['.foo{flex-grow:1;flex-shrink:1;flex-basis:10px}', '.foo{flex:10px}'],
            5121 => ['.foo{flex-grow:2;flex-shrink:1;flex-basis:10px}', '.foo{flex:2 10px}'],
            5137 => ['.foo{flex-grow:1;flex-shrink:0;flex-basis:0%}', '.foo{flex:1 0}'],
            5153 => ['.foo{flex-grow:1;flex-shrink:0;flex-basis:auto}', '.foo{flex:1 0 auto}'],
            5169 => ['.foo{flex-grow:1;flex-shrink:1;flex-basis:auto}', '.foo{flex:auto}'],
            5185 => ['.foo{flex:0 0;flex-grow:1}', '.foo{flex:1 0}'],
            5200 => ['.foo{flex:0 0;flex-grow:var(--grow)}', '.foo{flex:0 0;flex-grow:var(--grow)}'],
            5425 => ['.foo{-webkit-flex-grow:1;-webkit-flex-shrink:1;-webkit-flex-basis:auto}', '.foo{-webkit-flex:auto}'],
            5440 => [
                '.foo{-webkit-flex-grow:1;-webkit-flex-shrink:1;-webkit-flex-basis:auto;flex-grow:1;flex-shrink:1;flex-basis:auto}',
                '.foo{-webkit-flex:auto;flex:auto}',
            ],
        ];

        foreach ($cases as $line => [$input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_flex line ' . $line);
        }
    },
    'css minifier maps upstream place alignment composition rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_flex place alignment test() rows.
        $cases = [
            5216 => ['.foo{align-content:center;justify-content:center}', '.foo{place-content:center}'],
            5231 => ['.foo{align-content:first baseline;justify-content:safe right}', '.foo{place-content:baseline safe right}'],
            5246 => ['.foo{place-content:first baseline unsafe left}', '.foo{place-content:baseline unsafe left}'],
            5260 => ['.foo{place-content:center center}', '.foo{place-content:center}'],
            5274 => ['.foo{align-self:center;justify-self:center}', '.foo{place-self:center}'],
            5289 => ['.foo{align-self:center;justify-self:unsafe left}', '.foo{place-self:center unsafe left}'],
            5304 => ['.foo{align-items:center;justify-items:center}', '.foo{place-items:center}'],
            5319 => ['.foo{align-items:center;justify-items:legacy left}', '.foo{place-items:center legacy left}'],
            5334 => ['.foo{place-items:center;justify-items:var(--justify)}', '.foo{place-items:center;justify-items:var(--justify)}'],
        ];

        foreach ($cases as $line => [$input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_flex line ' . $line);
        }
    },
    'css minifier maps upstream gap composition rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_flex gap test() rows.
        $cases = [
            5350 => ['.foo{row-gap:10px;column-gap:20px}', '.foo{gap:10px 20px}'],
            5365 => ['.foo{row-gap:10px;column-gap:10px}', '.foo{gap:10px}'],
            5380 => ['.foo{gap:10px;column-gap:20px}', '.foo{gap:10px 20px}'],
            5395 => ['.foo{column-gap:20px;gap:10px}', '.foo{gap:10px}'],
            5410 => ['.foo{row-gap:normal;column-gap:20px}', '.foo{gap:normal 20px}'],
        ];

        foreach ($cases as $line => [$input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_flex line ' . $line);
        }
    },
];
