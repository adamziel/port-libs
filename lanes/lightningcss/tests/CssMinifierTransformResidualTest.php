<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

return [
    'css minifier maps residual upstream transform function scale rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_transform residual minify_test rows.
        // Lines 12927 and 12955-12957 use the existing transform-test convention of balanced
        // CSS input for upstream fixtures that omit parser-recoverable closing syntax.
        $cases = [
            [12927, '.foo { transform: translate3d(2px, 3px, 4px) }', '.foo{transform:translate3d(2px,3px,4px)}'],
            [12955, '.foo { transform: scaleX(2) }', '.foo{transform:scaleX(2)}'],
            [12956, '.foo { transform: scaleY(2) }', '.foo{transform:scaleY(2)}'],
            [12957, '.foo { transform: scaleZ(2) }', '.foo{transform:scaleZ(2)}'],
            [12973, '.foo { transform: scale(1%) }', '.foo{transform:scale(.01)}'],
            [12974, '.foo { transform: scale(0%) }', '.foo{transform:scale(0)}'],
            [12975, '.foo { transform: scale(0.0%) }', '.foo{transform:scale(0)}'],
            [12976, '.foo { transform: scale(-0%) }', '.foo{transform:scale(0)}'],
            [12977, '.foo { transform: scale(-0) }', '.foo{transform:scale(0)}'],
            [12978, '.foo { transform: scale(-0.0) }', '.foo{transform:scale(0)}'],
            [12979, '.foo { transform: scale(100%) }', '.foo{transform:scale(1)}'],
            [12980, '.foo { transform: scale(-100%) }', '.foo{transform:scale(-1)}'],
            [12981, '.foo { transform: scale(68%) }', '.foo{transform:scale(.68)}'],
            [12982, '.foo { transform: scale(5.96%) }', '.foo{transform:scale(.0596)}'],
            [12984, '.foo { transform: scale(100%, 100%) }', '.foo{transform:scale(1)}'],
            [12985, '.foo { transform: scale3d(100%, 100%, 1) }', '.foo{transform:scale(1)}'],
            [12986, '.foo { transform: scale(-100%, -100%) }', '.foo{transform:scale(-1)}'],
            [12988, '.foo { transform: scale3d(-100%, -100%, 1) }', '.foo{transform:scale(-1)}'],
            [12993, '.foo { transform: scale3d(100%, 200%, 1) }', '.foo{transform:scaleY(2)}'],
            [12997, '.foo { transform: scale3d(100%, 100%, 0%) }', '.foo{transform:scaleZ(0)}'],
            [13005, '.foo { transform: scale3d(-0%, -0%, -0%) }', '.foo{transform:scale3d(0,0,0)}'],
            [13009, '.foo { transform: scale(2, 100%) }', '.foo{transform:scaleX(2)}'],
            [13010, '.foo { transform: scale(2, -50%) }', '.foo{transform:scale(2,-.5)}'],
            [13011, '.foo { transform: scale(-90%, -1) }', '.foo{transform:scale(-.9,-1)}'],
            [13017, '.foo { transform: scale(calc(150% - 50%), 200%) }', '.foo{transform:scaleY(2)}'],
            [13021, '.foo { transform: scale(200%, calc(50% - 80%)) }', '.foo{transform:scale(2,-.3)}'],
            [13032, '.foo { transform: scaleX(10%) }', '.foo{transform:scaleX(.1)}'],
            [13033, '.foo { transform: scaleY(20%) }', '.foo{transform:scaleY(.2)}'],
            [13034, '.foo { transform: scaleZ(30%) }', '.foo{transform:scaleZ(.3)}'],
            [13035, '.foo { transform: scaleX(0%) }', '.foo{transform:scaleX(0)}'],
            [13036, '.foo { transform: scaleX(-0%) }', '.foo{transform:scaleX(0)}'],
            [13038, '.foo { transform: scaleX(calc(10% + 20%)) }', '.foo{transform:scaleX(.3)}'],
            [13042, '.foo { transform: scaleX(calc(180% - 20%)) }', '.foo{transform:scaleX(1.6)}'],
            [13046, '.foo { transform: scaleX(calc(50% - 80%)) }', '.foo{transform:scaleX(-.3)}'],
        ];

        foreach ($cases as [$line, $input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_transform line ' . $line);
        }
    },
];
