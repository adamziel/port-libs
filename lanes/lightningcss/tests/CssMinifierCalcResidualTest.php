<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

return [
    'css minifier maps residual upstream calc tail rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_calc calc-tail helper rows.
        $cases = [
            [8078, '.foo { width: calc(20px + 100% + 10vw - 30px) }', '.foo{width:calc(100% + 10vw - 10px)}'],
            [8111, '.foo { width: calc(1px - (2em + 4vh + 3%)) }', '.foo{width:calc(1px - 2em - 4vh - 3%)}'],
            [8115, '.foo { width: calc(1px + (2em + (3vh + 4px))) }', '.foo{width:calc(5px + 2em + 3vh)}'],
            [8119, '.foo { width: calc(1px - (2em + 4px - 6vh) / 2) }', '.foo{width:calc(3vh - 1px - 1em)}'],
            [8123, '.foo { width: calc(100% - calc(50% + 25px)) }', '.foo{width:calc(50% - 25px)}'],
            [8128, '.foo { width: calc(100vw / 2 - 6px + 0px) }', '.foo{width:calc(50vw - 6px)}'],
            [8132, '.foo { width: calc(1px + 1) }', '.foo{width:calc(1px + 1)}'],
            [8133, '.foo { width: calc( (1em - calc( 10px + 1em)) / 2) }', '.foo{width:-5px}'],
            [8137, '.foo { width: calc((100px - 1em) + (-50px + 1em)) }', '.foo{width:50px}'],
            [8141, '.foo { width: calc(100% + (2 * 100px) - ((75.37% - 63.5px) - 900px)) }', '.foo{width:calc(24.63% + 1163.5px)}'],
            [8145, '.foo { width: calc(((((100% + (2 * 30px) + 63.5px) / 0.7537) - (100vw - 60px)) / 2) + 30px) }', '.foo{width:calc(66.3394% + 141.929px - 50vw)}'],
            [8149, '.foo { width: calc(((75.37% - 63.5px) - 900px) + (2 * 100px)) }', '.foo{width:calc(75.37% - 763.5px)}'],
            [8153, '.foo { width: calc((900px - (10% - 63.5px)) + (2 * 100px)) }', '.foo{width:calc(1163.5px - 10%)}'],
            [8157, '.foo { width: calc(500px/0) }', '.foo{width:calc(500px/0)}'],
            [8158, '.foo { width: calc(500px/2px) }', '.foo{width:calc(500px/2px)}'],
        ];

        foreach ($cases as [$line, $input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_calc line ' . $line);
        }
    },
];
