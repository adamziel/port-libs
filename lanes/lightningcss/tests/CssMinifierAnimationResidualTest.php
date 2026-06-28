<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

return [
    'css minifier maps residual upstream animation longhand rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_animation lines 12176, 12180, and 12217.
        $cases = [
            [12176, '.foo { animation-iteration-count: 5 }', '.foo{animation-iteration-count:5}'],
            [12180, '.foo { animation-iteration-count: 2.5 }', '.foo{animation-iteration-count:2.5}'],
            [12217, '.foo { animation-fill-mode: forwards }', '.foo{animation-fill-mode:forwards}'],
        ];

        foreach ($cases as [$line, $input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_animation line ' . $line);
        }
    },
    'css minifier maps residual upstream animation quoted name rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_animation lines 12227, 12228, 12234, 12242, 12244, 12249, 12250, 12251, 12256, and 12271.
        $cases = [
            [12227, '.foo { animation: "None" }', '.foo{animation:"None"}'],
            [12228, '.foo { animation: "none", none }', '.foo{animation:"none",none}'],
            [12234, '.foo { animation: 2s both "none"}', '.foo{animation:2s both "none"}'],
            [12242, '.foo { animation: "none" 2s none}', '.foo{animation:2s "none"}'],
            [12244, '.foo { animation: none, "none" 2s forwards}', '.foo{animation:none,2s forwards "none"}'],
            [12249, '.foo { animation: "unset" }', '.foo{animation:"unset"}'],
            [12250, '.foo { animation: "string" .5s }', '.foo{animation:.5s string}'],
            [12251, '.foo { animation: "unset" .5s }', '.foo{animation:.5s "unset"}'],
            [12256, '.foo { animation: "unset" 0s 3s infinite, none }', '.foo{animation:0s 3s infinite "unset",none}'],
            [12271, '.foo { animation: "reverse" 2s alternate }', '.foo{animation:2s alternate reverse}'],
        ];

        foreach ($cases as [$line, $input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_animation line ' . $line);
        }
    },
    'css minifier maps residual upstream animation timeline rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_animation lines 12294, 12307, 12311, 12315, 12323, 12327, and 12331.
        $cases = [
            [12294, '.foo { animation: foo 3s scroll() }', '.foo{animation:3s foo scroll()}'],
            [12307, '.foo { animation: foo 3s scroll(inline nearest) }', '.foo{animation:3s foo scroll(inline)}'],
            [12311, '.foo { animation: foo 3s view(block) }', '.foo{animation:3s foo view()}'],
            [12315, '.foo { animation: foo 3s view(inline) }', '.foo{animation:3s foo view(inline)}'],
            [12323, '.foo { animation: foo 3s view(inline 10px 12px) }', '.foo{animation:3s foo view(inline 10px 12px)}'],
            [12327, '.foo { animation: foo 3s view(inline auto auto) }', '.foo{animation:3s foo view(inline)}'],
            [12331, '.foo { animation: foo 3s auto }', '.foo{animation:3s foo}'],
        ];

        foreach ($cases as [$line, $input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_animation line ' . $line);
        }
    },
];
