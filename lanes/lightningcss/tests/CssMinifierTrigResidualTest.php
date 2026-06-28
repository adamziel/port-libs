<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

return [
    'css minifier maps residual upstream forward trig calc rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_trig forward-trig rows.
        // These use balanced CSS input for upstream parser-recoverable EOF fixtures.
        $cases = [
            [8440, '.foo { width: calc(100px * sin(45deg)); }', '.foo{width:70.7107px}'],
            [8441, '.foo { width: calc(100px * sin(.125turn)); }', '.foo{width:70.7107px}'],
            [8442, '.foo { width: calc(100px * sin(3.14159265 / 4)); }', '.foo{width:70.7107px}'],
            [8446, '.foo { width: calc(100px * sin(pi / 4)); }', '.foo{width:70.7107px}'],
            [8447, '.foo { width: calc(100px * sin(22deg + 23deg)); }', '.foo{width:70.7107px}'],
            [8452, '.foo { width: calc(2px * cos(45deg)); }', '.foo{width:1.41421px}'],
            [8453, '.foo { width: calc(2px * tan(45deg)); }', '.foo{width:2px}'],
        ];

        foreach ($cases as [$line, $input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_trig line ' . $line);
        }
    },
];
