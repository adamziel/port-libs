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
    'css minifier maps residual upstream inverse trig angle rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_trig inverse-trig rows.
        // These use balanced CSS input for upstream parser-recoverable EOF fixtures.
        $cases = [
            [8455, '.foo { rotate: asin(sin(45deg)); }', '.foo{rotate:45deg}'],
            [8456, '.foo { rotate: asin(1); }', '.foo{rotate:90deg}'],
            [8457, '.foo { rotate: asin(-1); }', '.foo{rotate:-90deg}'],
            [8458, '.foo { rotate: asin(0.5); }', '.foo{rotate:30deg}'],
            [8459, '.foo { rotate: asin(45deg); }', '.foo{rotate:asin(45deg)}'],
            [8460, '.foo { rotate: asin(-20); }', '.foo{rotate:asin(-20)}'],
            [8461, '.foo { width: asin(sin(45deg)); }', '.foo{width:asin(sin(45deg))}'],
            [8463, '.foo { rotate: acos(cos(45deg)); }', '.foo{rotate:45deg}'],
            [8464, '.foo { rotate: acos(-1); }', '.foo{rotate:180deg}'],
            [8465, '.foo { rotate: acos(0); }', '.foo{rotate:90deg}'],
            [8466, '.foo { rotate: acos(1); }', '.foo{rotate:0deg}'],
            [8467, '.foo { rotate: acos(45deg); }', '.foo{rotate:acos(45deg)}'],
            [8468, '.foo { rotate: acos(-20); }', '.foo{rotate:acos(-20)}'],
            [8470, '.foo { rotate: atan(tan(45deg)); }', '.foo{rotate:45deg}'],
            [8471, '.foo { rotate: atan(1); }', '.foo{rotate:45deg}'],
            [8472, '.foo { rotate: atan(0); }', '.foo{rotate:0deg}'],
            [8473, '.foo { rotate: atan(45deg); }', '.foo{rotate:atan(45deg)}'],
            [8475, '.foo { rotate: atan2(1px, -1px); }', '.foo{rotate:135deg}'],
            [8476, '.foo { rotate: atan2(1vw, -1vw); }', '.foo{rotate:135deg}'],
            [8477, '.foo { rotate: atan2(1, -1); }', '.foo{rotate:135deg}'],
            [8478, '.foo { rotate: atan2(1ms, -1ms); }', '.foo{rotate:135deg}'],
            [8479, '.foo { rotate: atan2(1%, -1%); }', '.foo{rotate:135deg}'],
            [8480, '.foo { rotate: atan2(1deg, -1deg); }', '.foo{rotate:135deg}'],
            [8481, '.foo { rotate: atan2(1cm, 1mm); }', '.foo{rotate:84.2894deg}'],
            [8482, '.foo { rotate: atan2(0, -1); }', '.foo{rotate:180deg}'],
            [8483, '.foo { rotate: atan2(-1, 1); }', '.foo{rotate:-45deg}'],
            [8485, '.foo { rotate: atan2(1px, -1vw); }', '.foo{rotate:atan2(1px, -1vw)}'],
            [8487, '.foo { transform: rotate(acos(1)); }', '.foo{transform:rotate(0)}'],
            [8488, '.foo { transform: rotate(atan(0)); }', '.foo{transform:rotate(0)}'],
        ];

        foreach ($cases as [$line, $input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_trig line ' . $line);
        }
    },
];
