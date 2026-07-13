<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssDependencyAnalyzer;

return [
    'css dependency analyzer maps upstream dependency placeholders' => static function (TestRunner $t): void {
        $analyzer = new CssDependencyAnalyzer();

        // Pinned upstream 22bdda3d src/lib.rs::test_dependencies lines 29239-29315.
        $cases = [
            [
                ".foo { background: image-set('./img12x.png', './img21x.png' 2x)}",
                '.foo{background:image-set("hXFI8W" 1x,"5TkpBa" 2x)}',
                [
                    ['type' => 'url', 'url' => './img12x.png', 'placeholder' => 'hXFI8W'],
                    ['type' => 'url', 'url' => './img21x.png', 'placeholder' => '5TkpBa'],
                ],
            ],
            [
                ".foo { background: image-set(url(./img12x.png), url('./img21x.png') 2x)}",
                '.foo{background:image-set("hXFI8W" 1x,"5TkpBa" 2x)}',
                [
                    ['type' => 'url', 'url' => './img12x.png', 'placeholder' => 'hXFI8W'],
                    ['type' => 'url', 'url' => './img21x.png', 'placeholder' => '5TkpBa'],
                ],
            ],
            [
                '.foo { --test: url(/foo.png) }',
                '.foo{--test:url("lDnnrG")}',
                [['type' => 'url', 'url' => '/foo.png', 'placeholder' => 'lDnnrG']],
            ],
            [
                '.foo { --test: url("/foo.png") }',
                '.foo{--test:url("lDnnrG")}',
                [['type' => 'url', 'url' => '/foo.png', 'placeholder' => 'lDnnrG']],
            ],
            [
                '.foo { --test: url("http://example.com/foo.png") }',
                '.foo{--test:url("3X1zSW")}',
                [['type' => 'url', 'url' => 'http://example.com/foo.png', 'placeholder' => '3X1zSW']],
            ],
            [
                '.foo { --test: url("data:image/svg+xml;utf8,<svg></svg>") }',
                '.foo{--test:url("-vl-rG")}',
                [['type' => 'url', 'url' => 'data:image/svg+xml;utf8,<svg></svg>', 'placeholder' => '-vl-rG']],
            ],
            [
                '.foo { background: url("foo.png") var(--test) }',
                '.foo{background:url("Vwkwkq") var(--test)}',
                [['type' => 'url', 'url' => 'foo.png', 'placeholder' => 'Vwkwkq']],
            ],
            [
                '.foo { behavior: url(#foo) }',
                '.foo{behavior:url("Zn9-2q")}',
                [['type' => 'url', 'url' => '#foo', 'placeholder' => 'Zn9-2q']],
            ],
            [
                '.foo { --foo: url(#foo) }',
                '.foo{--foo:url("Zn9-2q")}',
                [['type' => 'url', 'url' => '#foo', 'placeholder' => 'Zn9-2q']],
            ],
            [
                '@import "test.css"; .foo { color: red }',
                '@import "hHsogW";.foo{color:red}',
                [['type' => 'import', 'url' => 'test.css', 'placeholder' => 'hHsogW']],
            ],
        ];

        foreach ($cases as [$source, $expected, $dependencies]) {
            $result = $analyzer->analyze($source);
            $t->same($expected, $result['code']);
            $t->same($dependencies, $result['dependencies']);
        }
    },
    'css dependency analyzer rejects upstream ambiguous custom property urls' => static function (TestRunner $t): void {
        $analyzer = new CssDependencyAnalyzer();

        // Pinned upstream 22bdda3d src/lib.rs::test_dependencies lines 29281-29296.
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('.foo { --test: url("foo.png") }'));
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('.foo { --test: url(foo.png) }'));
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('.foo { --test: url(./foo.png) }'));
    },
];
