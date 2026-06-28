<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

return [
    'css minifier maps residual upstream scroll navigation controls rows' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_selectors lines 6748, 6753, 6758, 6763, and 6768.
        $cases = [
            [6748, 'a:target-current { color: green }', 'a:target-current{color:green}'],
            [6753, 'a:target-before { color: green }', 'a:target-before{color:green}'],
            [6758, 'a:target-after { color: green }', 'a:target-after{color:green}'],
            [6763, ':is(a:target-before, a:target-after) { color: green }', ':is(a:target-before,a:target-after){color:green}'],
            [6768, 'a:where(:target-before, :target-after) { color: green }', 'a:where(:target-before,:target-after){color:green}'],
        ];

        foreach ($cases as [$line, $input, $expected]) {
            $t->same($expected, $minifier->minify($input), 'upstream src/lib.rs::test_selectors line ' . $line);
        }
    },
    'css minifier rejects residual upstream scroll navigation pseudo element tail row' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_selectors line 6793.
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $minifier->minify('a::before:target-current { color: green }')
        );
    },
];
