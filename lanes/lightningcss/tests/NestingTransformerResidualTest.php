<?php

declare(strict_types=1);

use PortLibs\LightningCSS\NestingTransformer;

return [
    'nesting transformer maps residual upstream at-rule tail rows' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{display:grid}@supports (foo:bar){.foo{grid-auto-flow:column}}',
            $transformer->lower('.foo { display: grid; @supports (foo: bar) { grid-auto-flow: column; } }'),
            'Pinned upstream 22bdda3d src/lib.rs::test_nesting nesting_test line 24751'
        );
        $t->same(
            '.foo{display:grid}@container (width>=100px){.foo{grid-auto-flow:column}}',
            $transformer->lower('.foo { display: grid; @container (min-width: 100px) { grid-auto-flow: column; } }'),
            'Pinned upstream 22bdda3d src/lib.rs::test_nesting nesting_test line 24774'
        );
    },
    'nesting transformer maps residual upstream simple attached selector tail rows' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            'div.bar{background:green}',
            $transformer->lower('div { &.bar { background: green; } }'),
            'Pinned upstream 22bdda3d src/lib.rs::test_nesting nesting_test line 24911'
        );
        $t->same(
            '.foo h1{background:green}',
            $transformer->lower('.foo { & h1 { background: green; } }'),
            'Pinned upstream 22bdda3d src/lib.rs::test_nesting nesting_test line 24941'
        );
    },
];
