<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

return [
    'css minifier maps residual upstream animation-composition row' => static function (TestRunner $t): void {
        $minifier = new CssMinifier();

        // Pinned upstream 22bdda3d src/lib.rs::test_animation line 12332.
        $t->same(
            '.foo{animation-composition:add}',
            $minifier->minify('.foo { animation-composition: add }'),
            'upstream src/lib.rs::test_animation line 12332'
        );
    },
];
