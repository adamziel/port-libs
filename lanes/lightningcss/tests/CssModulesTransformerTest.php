<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

$export = static fn (string $name, array $composes = []): array => [
    'name' => $name,
    'composes' => $composes,
    'isReferenced' => false,
];
$local = static fn (string $name): array => ['type' => 'local', 'name' => $name];
$global = static fn (string $name): array => ['type' => 'global', 'name' => $name];
$dependency = static fn (string $name, string $specifier): array => [
    'type' => 'dependency',
    'name' => $name,
    'specifier' => $specifier,
];
$referenced = static fn (string $name): array => [
    'name' => $name,
    'composes' => [],
    'isReferenced' => true,
];
$dashed = static fn (string $name, bool $isReferenced = false): array => [
    'name' => $name,
    'composes' => [],
    'isReferenced' => $isReferenced,
];

return [
    'css modules unwraps upstream local and global selector pseudos' => static function (TestRunner $t) use ($export): void {
        $css = <<<'CSS'
:global(.foo) {
  color: red;
}

:local(.bar) {
  color: yellow;
}

.bar :global(.baz) {
  color: purple;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.foo{color:red}.EgL3uq_bar{color:#ff0}.EgL3uq_bar .baz{color:purple}', $result['code']);
        $t->same([
            'bar' => $export('EgL3uq_bar'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules keeps nested local selectors global inside global scope' => static function (TestRunner $t) use ($export): void {
        $css = <<<'CSS'
:global(.wp-block :local(.legacy)) .title {
  color: red;
}

.card :global(.wp-block :local(.legacy)) .title {
  color: yellow;
}

:global(:local(.utility)) {
  color: purple;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.wp-block .legacy .EgL3uq_title{color:red}.EgL3uq_card .wp-block .legacy .EgL3uq_title{color:#ff0}.utility{color:purple}', $result['code']);
        $t->same([
            'title' => $export('EgL3uq_title'),
            'card' => $export('EgL3uq_card'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules rejects local and global selector-list function arguments' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            '.x :global(.foo, .bar) { color: red }',
            ':global(.foo, .bar) .x { color: red }',
            ':local(.foo, .bar) { color: red }',
            ':global() { color: red }',
            ':local() { color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform($css));
        }
    },
    'css modules rejects bare global pseudos from upstream nested regression' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform(<<<'CSS'
.blue {
  background: blue;

  :global {
    .red {
      background: red;
    }
  }
}
CSS));

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform(<<<'CSS'
.blue {
  &:global {
    &.green {
      background: green;
    }
  }
}
CSS));
    },
    'css modules does not treat standard local-link pseudo as local mode syntax' => static function (TestRunner $t): void {
        $result = (new CssModulesTransformer())->transform(':local-link { color: red }');

        $t->same(':local-link{color:red}', $result['code']);
        $t->same([], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules leaves upstream raw custom pseudo function tokens unscoped while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  composes: base;
  color: red;
}

.card:--theme-state(.legacy, :hover, #anchor) {
  color: yellow;
}

.card:is(.featured, :global(.wp-block-card)) {
  color: purple;
}

.item:nth-child(2n of .card, :global(.wp-block)) {
  color: blue;
}

.base {
  color: green;
}
CSS, [
            'pseudoClasses' => [
                'hover' => 'is-hovered',
            ],
        ]);

        $t->same('.EgL3uq_card{color:red}.EgL3uq_card:--theme-state(.legacy,:hover,#anchor){color:#ff0}.EgL3uq_card:is(.EgL3uq_featured,.wp-block-card){color:purple}.EgL3uq_item:nth-child(2n of .EgL3uq_card,.wp-block){color:#00f}.EgL3uq_base{color:green}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'featured' => $export('EgL3uq_featured'),
            'item' => $export('EgL3uq_item'),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $transformer = new CssModulesTransformer();
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform(':--theme-state(.legacy) { color: red }', ['pure' => true]));

        $pureSelectorFunction = $transformer->transform(':is(:--theme-state(.legacy), .card) { color: red }', ['pure' => true]);
        $t->same(':is(:--theme-state(.legacy),.EgL3uq_card){color:red}', $pureSelectorFunction['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
        ], $pureSelectorFunction['exports']);
    },
    'css modules scopes upstream pseudo replacement classes while preserving composes' => static function (TestRunner $t) use ($export, $local): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.card:hover {
  color: red;
}

.card:active {
  color: yellow;
}

.card:focus {
  color: blue;
}

:global(.wp-block-button:hover) .card:focus-visible {
  color: purple;
}

.card:focus-within {
  background: white;
}

.button {
  composes: card;
  color: green;
}
CSS, [
            'pseudoClasses' => [
                'hover' => 'is-hovered',
                'active' => 'is-active',
                'focus' => 'is-focused',
                'focusVisible' => 'focus-visible',
                'focusWithin' => 'has-focus-within',
            ],
        ]);

        $t->same('.EgL3uq_card.EgL3uq_is-hovered{color:red}.EgL3uq_card.EgL3uq_is-active{color:#ff0}.EgL3uq_card.EgL3uq_is-focused{color:#00f}.wp-block-button.is-hovered .EgL3uq_card.EgL3uq_focus-visible{color:purple}.EgL3uq_card.EgL3uq_has-focus-within{background:#fff}.EgL3uq_button{color:green}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'is-hovered' => $export('EgL3uq_is-hovered'),
            'is-active' => $export('EgL3uq_is-active'),
            'is-focused' => $export('EgL3uq_is-focused'),
            'focus-visible' => $export('EgL3uq_focus-visible'),
            'has-focus-within' => $export('EgL3uq_has-focus-within'),
            'button' => $export('EgL3uq_button', [$local('EgL3uq_card')]),
        ], $result['exports']);
        $t->same([], $result['references']);

        $snakeCase = (new CssModulesTransformer())->transform('.foo:focus-visible, .foo:focus-within { color: red }', [
            'pseudo_classes' => [
                'focus_visible' => 'is-visible',
                'focus_within' => 'is-within',
            ],
        ]);

        $t->same('.EgL3uq_foo.EgL3uq_is-visible,.EgL3uq_foo.EgL3uq_is-within{color:red}', $snakeCase['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo'),
            'is-visible' => $export('EgL3uq_is-visible'),
            'is-within' => $export('EgL3uq_is-within'),
        ], $snakeCase['exports']);
    },
    'css modules scopes escaped local selectors and composes idents' => static function (TestRunner $t) use ($export, $local, $global): void {
        $css = <<<'CSS'
.sm\:m-1 {
  composes: base\:one;
  color: red;
}

.hex\3a utility {
  composes: base\3a one;
  color: yellow;
}

.base\:one {
  color: blue;
}

.foo\@bar {
  composes: base\:one other from global;
  background: white;
}

:global(.wp\:block) .foo\@bar {
  border-color: red;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_sm\:m-1{color:red}.EgL3uq_hex\:utility{color:#ff0}.EgL3uq_base\:one{color:#00f}.EgL3uq_foo\@bar{background:#fff}.wp\:block .EgL3uq_foo\@bar{border-color:red}', $result['code']);
        $t->same([
            'sm:m-1' => $export('EgL3uq_sm:m-1', [$local('EgL3uq_base:one')]),
            'hex:utility' => $export('EgL3uq_hex:utility', [$local('EgL3uq_base:one')]),
            'base:one' => $export('EgL3uq_base:one'),
            'foo@bar' => $export('EgL3uq_foo@bar', [$global('base:one'), $global('other')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules pure mode enforces upstream local selector boundaries' => static function (TestRunner $t) use ($export, $local): void {
        $transformer = new CssModulesTransformer();

        $passing = [
            ':local(.foo) { width: 20px }' => '.EgL3uq_foo{width:20px}',
            'div.my-class { color: red }' => 'div.EgL3uq_my-class{color:red}',
            '#id { color: red }' => '#EgL3uq_id{color:red}',
            'a .my-class { color: red }' => 'a .EgL3uq_my-class{color:red}',
            '.my-class a { color: red }' => '.EgL3uq_my-class a{color:red}',
            '.my-class:is(a) { color: red }' => '.EgL3uq_my-class:is(a){color:red}',
            'div:has(.my-class) { color: red }' => 'div:has(.EgL3uq_my-class){color:red}',
        ];

        foreach ($passing as $css => $expected) {
            $result = $transformer->transform($css, ['pure' => true]);
            $t->same($expected, $result['code']);
        }

        $noCheck = $transformer->transform('/* cssmodules-pure-no-check */ :global(.wp-block-button) { color: red }', ['pure' => true]);
        $t->same('.wp-block-button{color:red}', $noCheck['code']);
        $t->same([], $noCheck['exports']);

        $licenseNoCheck = $transformer->transform(<<<'CSS'
/*! Theme block license */
/* cssmodules-pure-no-check */ :global(.wp-block-button) {
  color: red;
}

.card {
  composes: base;
  color: yellow;
}

.base {
  color: blue;
}
CSS, [
            'pure' => true,
        ]);
        $t->same("/*! Theme block license */\n.wp-block-button{color:red}.EgL3uq_card{color:#ff0}.EgL3uq_base{color:#00f}", $licenseNoCheck['code']);
        $t->same([
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $licenseNoCheck['exports']);

        $localResult = $transformer->transform('div:has(.my-class) { color: red }', ['pure' => true]);
        $t->same([
            'my-class' => $export('EgL3uq_my-class'),
        ], $localResult['exports']);
    },
    'css modules pure mode rejects upstream impure global selectors' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            'div { width: 20px }',
            ':global(.foo) { width: 20px }',
            '[foo=bar] { width: 20px }',
            'div, .foo { width: 20px }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform($css, ['pure' => true]));
        }
    },
    'css modules removes local composes declarations and exports composed local class' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
.test {
  composes: foo;
  background: white;
}

.foo {
  color: red;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo{color:red}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$local('EgL3uq_foo')]),
            'foo' => $export('EgL3uq_foo'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules applies local composes to each selector list export' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
.a, .b {
  composes: foo;
  background: white;
}

.foo {
  color: red;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_a,.EgL3uq_b{background:#fff}.EgL3uq_foo{color:red}', $result['code']);
        $t->same([
            'a' => $export('EgL3uq_a', [$local('EgL3uq_foo')]),
            'b' => $export('EgL3uq_b', [$local('EgL3uq_foo')]),
            'foo' => $export('EgL3uq_foo'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules preserves upstream order for multiple local composes names' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
.test {
  composes: foo bar;
  background: white;
}

.foo {
  color: red;
}

.bar {
  color: yellow;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo{color:red}.EgL3uq_bar{color:#ff0}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$local('EgL3uq_foo'), $local('EgL3uq_bar')]),
            'foo' => $export('EgL3uq_foo'),
            'bar' => $export('EgL3uq_bar'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules records single global composes reference without localizing it' => static function (TestRunner $t) use ($export, $global): void {
        $css = <<<'CSS'
.test {
  composes: foo from global;
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$global('foo')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules records multiple global composes references in source order' => static function (TestRunner $t) use ($export, $global): void {
        $css = <<<'CSS'
.test {
  composes: foo bar from global;
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$global('foo'), $global('bar')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules records dependency composes reference without rewriting dependency class' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo from "foo.css";
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$dependency('foo', 'foo.css')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules records multiple dependency composes references in source order' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo bar from "foo.css";
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$dependency('foo', 'foo.css'), $dependency('bar', 'foo.css')]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules accepts upstream important priority on composes declarations' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $localResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo ! important;
  background: white;
}

.foo {
  color: red;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo{color:red}', $localResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$local('EgL3uq_foo')]),
            'foo' => $export('EgL3uq_foo'),
        ], $localResult['exports']);
        $t->same([], $localResult['references']);

        $globalResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo from global !IMPORTANT;
  background: white;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}', $globalResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$global('foo')]),
        ], $globalResult['exports']);
        $t->same([], $globalResult['references']);

        $dependencyResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo from "./foo.css"!important;
  background: white;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}', $dependencyResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$dependency('foo', './foo.css')]),
        ], $dependencyResult['exports']);
        $t->same([], $dependencyResult['references']);

        $escapedResult = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  composes: foo\!important;
  background: white;
}

.foo\!important {
  color: green;
}
CSS);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo\!important{color:green}', $escapedResult['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$local('EgL3uq_foo!important')]),
            'foo!important' => $export('EgL3uq_foo!important'),
        ], $escapedResult['exports']);
        $t->same([], $escapedResult['references']);
    },
    'css modules decodes escaped dependency specifiers in composes metadata' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo from "./theme\ components.css";
  composes: bar from "./theme\000020components.css";
  composes: icon from "./icons\2f arrow.css";
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [
                $dependency('foo', './theme components.css'),
                $dependency('bar', './theme components.css'),
                $dependency('icon', './icons/arrow.css'),
            ]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules parses upstream composes from delimiters strictly' => static function (TestRunner $t) use ($export, $local, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: global none;
  composes: foo from './foo bar.css';
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [
                $local('EgL3uq_global'),
                $local('EgL3uq_none'),
                $dependency('foo', './foo bar.css'),
            ]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules rejects malformed upstream composes grammar' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            '.test { composes: from global; color: red }',
            '.test { composes: foo from; color: red }',
            '.test { composes: foo from bar; color: red }',
            '.test { composes: foo from global bar; color: red }',
            '.test { composes: foo from "foo.css" bar; color: red }',
            '.test { composes: initial; color: red }',
            '.test { composes: revert-layer; color: red }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform($css));
        }
    },
    'css modules merges repeated composes declarations across local and dependency references' => static function (TestRunner $t) use ($export, $local, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo;
  composes: foo from "foo.css";
  composes: bar from "bar.css";
  background: white;
}

.foo {
  color: red;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}.EgL3uq_foo{color:red}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [
                $local('EgL3uq_foo'),
                $dependency('foo', 'foo.css'),
                $dependency('bar', 'bar.css'),
            ]),
            'foo' => $export('EgL3uq_foo'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules maps upstream hash and content-hash patterns through composes exports' => static function (TestRunner $t) use ($export, $dependency): void {
        $patterned = (new CssModulesTransformer())->transform('.foo { color: red }', [
            'pattern' => 'test-[hash]-[local]',
        ]);

        $t->same('.test-EgL3uq-foo{color:red}', $patterned['code']);
        $t->same([
            'foo' => $export('test-EgL3uq-foo'),
        ], $patterned['exports']);

        $projectRootSameFile = (new CssModulesTransformer())->transform('.foo { color: red }', [
            'filename' => '/foo/bar/test.css',
            'projectRoot' => '/foo/bar',
        ]);

        $t->same('.EgL3uq_foo{color:red}', $projectRootSameFile['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo'),
        ], $projectRootSameFile['exports']);

        $projectRoot = (new CssModulesTransformer())->transform('.foo { color: red }', [
            'filename' => '/foo/bar/baz/test.css',
            'projectRoot' => '/foo/bar',
        ]);

        $t->same('.xLEkNW_foo{color:red}', $projectRoot['code']);
        $t->same([
            'foo' => $export('xLEkNW_foo'),
        ], $projectRoot['exports']);

        $contentSource = "\n      .test {\n        composes: foo bar from \"foo.css\";\n        background: white;\n      }\n    ";
        $contentHash = (new CssModulesTransformer())->transform($contentSource, [
            'pattern' => '[content-hash]-[local]',
        ]);

        $t->same('._5h2kwG-test{background:#fff}', $contentHash['code']);
        $t->same([
            'test' => $export('_5h2kwG-test', [
                $dependency('foo', 'foo.css'),
                $dependency('bar', 'foo.css'),
            ]),
        ], $contentHash['exports']);
        $t->same([], $contentHash['references']);
    },
    'css modules rejects upstream invalid patterns before local global and composes output' => static function (TestRunner $t): void {
        $cases = [
            [
                '.test { composes: foo; color: red } .foo { color: blue }',
                '[oops]-[local]',
                'Error parsing CSS modules pattern: unknown placeholder "[oops]" at index 0',
            ],
            [
                ':local(.test) { color: red }',
                'theme-[oops]-[local]',
                'Error parsing CSS modules pattern: unknown placeholder "[oops]" at index 6',
            ],
            [
                ':global(.legacy) .test { color: red }',
                '[hash',
                'Error parsing CSS modules pattern: unclosed brackets at index 0',
            ],
            [
                '.test { composes: foo from global; color: red }',
                'theme-[name]-[bad]',
                'Error parsing CSS modules pattern: unknown placeholder "[bad]" at index 13',
            ],
        ];

        foreach ($cases as [$css, $pattern, $message]) {
            try {
                (new CssModulesTransformer())->transform($css, [
                    'filename' => '/theme/card.module.css',
                    'pattern' => $pattern,
                ]);
            } catch (InvalidArgumentException $exception) {
                $t->same($message, $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected invalid CSS Modules pattern exception');
        }
    },
    'css modules deduplicates repeated composes references from simple class selectors' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $css = <<<'CSS'
.test {
  composes: foo;
  composes: foo;
  composes: foo from global;
  composes: foo from global;
  composes: bar from "bar.css";
  composes: bar from "bar.css";
  background: white;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_test{background:#fff}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [
                $local('EgL3uq_foo'),
                $global('foo'),
                $dependency('bar', 'bar.css'),
            ]),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules rejects composes from functional local selectors like upstream' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        foreach ([
            ':local(.test) { composes: foo; color: red }',
            ':local(.test), .fallback { composes: foo; color: red }',
            '@media (min-width: 1px) { :local(.test) { composes: foo; color: red } }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform($css));
        }
    },
    'css modules rejects composes outside simple local class selectors' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.ancestor .test { composes: foo; color: red }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.test:hover { composes: foo; color: red }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.test.foo { composes: foo; color: red }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('#test { composes: foo; color: red }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform(':global(.test) { composes: foo; color: red }'));
    },
    'css modules rejects composes inside nested local rules' => static function (TestRunner $t): void {
        $transformer = new CssModulesTransformer();

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.foo { .bar { composes: baz; color: red } }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.foo { @media (min-width: 1px) { .bar { composes: baz; color: red } } }'));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('.foo { @media (min-width: 1px) { composes: baz; color: red } }'));
    },
    'css modules allows composes inside top-level conditional rule blocks' => static function (TestRunner $t) use ($export, $local): void {
        $css = <<<'CSS'
@media (min-width: 1px) {
  .foo {
    composes: bar;
    color: red;
  }

  .bar {
    color: blue;
  }
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('@media (width>=1px){.EgL3uq_foo{color:red}.EgL3uq_bar{color:#00f}}', $result['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$local('EgL3uq_bar')]),
            'bar' => $export('EgL3uq_bar'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules keeps parent composes exports while lowering nested local selectors' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.foo {
  color: red;

  .bar {
    color: green;
  }

  composes: test from "foo.css";
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_foo{color:red}.EgL3uq_foo .EgL3uq_bar{color:green}', $result['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$dependency('test', 'foo.css')]),
            'bar' => $export('EgL3uq_bar'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules scopes upstream animation custom idents while preserving composes exports' => static function (TestRunner $t) use ($export, $dependency): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.test {
  animation: rotate var(--duration) linear infinite;
  composes: token from "tokens.css";
}

@keyframes rotate {
  from { opacity: 0 }
  to { opacity: 1 }
}
CSS);

        $t->same('.EgL3uq_test{animation:EgL3uq_rotate var(--duration) linear infinite}@keyframes EgL3uq_rotate{0%{opacity:0}to{opacity:1}}', $result['code']);
        $t->same([
            'test' => $export('EgL3uq_test', [$dependency('token', 'tokens.css')]),
            'rotate' => [
                'name' => 'EgL3uq_rotate',
                'composes' => [],
                'isReferenced' => true,
            ],
        ], $result['exports']);
        $t->same([], $result['references']);

        $none = (new CssModulesTransformer())->transform('.test { animation: none var(--duration); }');
        $t->same('.EgL3uq_test{animation:none var(--duration)}', $none['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
        ], $none['exports']);

        $variable = (new CssModulesTransformer())->transform('.test { animation: var(--animation); }');
        $t->same('.EgL3uq_test{animation:var(--animation)}', $variable['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
        ], $variable['exports']);

        $disabled = (new CssModulesTransformer())->transform('.test { animation: rotate var(--duration); }', ['animation' => false]);
        $t->same('.EgL3uq_test{animation:rotate var(--duration)}', $disabled['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
        ], $disabled['exports']);

        $quoted = (new CssModulesTransformer())->transform('.test { animation: "rotate" var(--duration); }');
        $t->same('.EgL3uq_test{animation:EgL3uq_rotate var(--duration)}', $quoted['code']);
        $t->same([
            'test' => $export('EgL3uq_test'),
            'rotate' => [
                'name' => 'EgL3uq_rotate',
                'composes' => [],
                'isReferenced' => true,
            ],
        ], $quoted['exports']);
    },
    'css modules scopes upstream counter styles and list-style references with composes exports' => static function (TestRunner $t) use ($export, $referenced, $dependency): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
@counter-style circles {
  symbols: A B C;
}

.list {
  list-style: circles outside;
  composes: base from "tokens.css";
}

.item {
  list-style-type: circles;
}

.builtin {
  list-style-type: disc;
}

.none {
  list-style: none;
}
CSS);

        $t->same('@counter-style EgL3uq_circles{symbols:A B C}.EgL3uq_list{list-style:EgL3uq_circles}.EgL3uq_item{list-style-type:EgL3uq_circles}.EgL3uq_builtin{list-style-type:disc}.EgL3uq_none{list-style:none}', $result['code']);
        $t->same([
            'circles' => $referenced('EgL3uq_circles'),
            'list' => $export('EgL3uq_list', [$dependency('base', 'tokens.css')]),
            'item' => $export('EgL3uq_item'),
            'builtin' => $export('EgL3uq_builtin'),
            'none' => $export('EgL3uq_none'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $customIdentsDisabled = (new CssModulesTransformer())->transform(<<<'CSS'
@counter-style circles {
  symbols: A B C;
}

.list {
  list-style-type: circles;
}
CSS, [
            'customIdents' => false,
        ]);

        $t->same('@counter-style circles{symbols:A B C}.EgL3uq_list{list-style-type:circles}', $customIdentsDisabled['code']);
        $t->same([
            'list' => $export('EgL3uq_list'),
            'circles' => $referenced('EgL3uq_circles'),
        ], $customIdentsDisabled['exports']);

        $builtInTypeWins = (new CssModulesTransformer())->transform(<<<'CSS'
@counter-style circles {
  symbols: A B C;
}

.list {
  list-style: square circles;
}
CSS);

        $t->same('@counter-style EgL3uq_circles{symbols:A B C}.EgL3uq_list{list-style:square circles}', $builtInTypeWins['code']);
        $t->same([
            'circles' => $export('EgL3uq_circles'),
            'list' => $export('EgL3uq_list'),
        ], $builtInTypeWins['exports']);
    },
    'css modules scopes upstream grid template names' => static function (TestRunner $t) use ($export): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
body {
  grid: [header-top] "a a a" [header-bottom]
        [main-top] "b b b" 1fr [main-bottom]
        / auto 1fr auto;
}

header {
  grid-area: a;
}

main {
  grid-row: main-top / main-bottom;
}
CSS);

        $t->same('body{grid:[EgL3uq_header-top]"EgL3uq_a EgL3uq_a EgL3uq_a"[EgL3uq_header-bottom EgL3uq_main-top]"EgL3uq_b EgL3uq_b EgL3uq_b"1fr[EgL3uq_main-bottom]/auto 1fr auto}header{grid-area:EgL3uq_a}main{grid-row:EgL3uq_main-top/EgL3uq_main-bottom}', $result['code']);
        $t->same([
            'header-top' => $export('EgL3uq_header-top'),
            'a' => $export('EgL3uq_a'),
            'header-bottom' => $export('EgL3uq_header-bottom'),
            'main-top' => $export('EgL3uq_main-top'),
            'b' => $export('EgL3uq_b'),
            'main-bottom' => $export('EgL3uq_main-bottom'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules scopes upstream grid areas while preserving composes exports' => static function (TestRunner $t) use ($export, $dependency): void {
        $css = <<<'CSS'
.grid {
  composes: utility from "tokens.css";
  grid-template-areas: "foo";
}

.foo {
  grid-area: foo;
}

.bar {
  grid-column-start: foo-start;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_grid{grid-template-areas:"EgL3uq_foo"}.EgL3uq_foo{grid-area:EgL3uq_foo}.EgL3uq_bar{grid-column-start:EgL3uq_foo-start}', $result['code']);
        $t->same([
            'grid' => $export('EgL3uq_grid', [$dependency('utility', 'tokens.css')]),
            'foo' => $export('EgL3uq_foo'),
            'bar' => $export('EgL3uq_bar'),
            'foo-start' => $export('EgL3uq_foo-start'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $disabled = (new CssModulesTransformer())->transform($css, [
            'grid' => false,
        ]);

        $t->same('.EgL3uq_grid{grid-template-areas:"foo"}.EgL3uq_foo{grid-area:foo}.EgL3uq_bar{grid-column-start:foo-start}', $disabled['code']);
        $t->same([
            'grid' => $export('EgL3uq_grid', [$dependency('utility', 'tokens.css')]),
            'foo' => $export('EgL3uq_foo'),
            'bar' => $export('EgL3uq_bar'),
        ], $disabled['exports']);

        try {
            (new CssModulesTransformer())->transform('.grid { grid-template-areas: "foo"; }', [
                'pattern' => 'test-[local]-[hash]',
            ]);
        } catch (InvalidArgumentException $exception) {
            $t->same('The CSS modules `pattern` config must end with `[local]` for use in CSS grid line names.', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected invalid CSS Modules grid pattern exception');
    },
    'css modules scopes upstream container query names while preserving composes exports' => static function (TestRunner $t) use ($export, $dependency): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.box2 {
  @container main (width >= 0) {
    background-color: #90ee90;
  }

  composes: card from "card.css";
}
CSS);

        $t->same('@container EgL3uq_main (width>=0){.EgL3uq_box2{background-color:#90ee90}}', $result['code']);
        $t->same([
            'box2' => $export('EgL3uq_box2', [$dependency('card', 'card.css')]),
            'main' => $export('EgL3uq_main'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $disabled = (new CssModulesTransformer())->transform(<<<'CSS'
.box2 {
  @container main (width >= 0) {
    background-color: #90ee90;
  }
}
CSS, [
            'container' => false,
        ]);

        $t->same('@container main (width>=0){.EgL3uq_box2{background-color:#90ee90}}', $disabled['code']);
        $t->same([
            'box2' => $export('EgL3uq_box2'),
        ], $disabled['exports']);

        $topLevel = (new CssModulesTransformer())->transform(<<<'CSS'
@container layout (inline-size > 45em) {
  .wide {
    color: red;
  }
}

@container style(--responsive: true) {
  .styleQuery {
    color: yellow;
  }
}

@container not (width > 500px) {
  .negated {
    color: blue;
  }
}
CSS);

        $t->same('@container EgL3uq_layout (inline-size>45em){.EgL3uq_wide{color:red}}@container style(--responsive:true){.EgL3uq_styleQuery{color:#ff0}}@container not (width>500px){.EgL3uq_negated{color:#00f}}', $topLevel['code']);
        $t->same([
            'layout' => $export('EgL3uq_layout'),
            'wide' => $export('EgL3uq_wide'),
            'styleQuery' => $export('EgL3uq_styleQuery'),
            'negated' => $export('EgL3uq_negated'),
        ], $topLevel['exports']);
    },
    'css modules scopes upstream scope rule preludes while preserving composes exports' => static function (TestRunner $t) use ($export, $local, $global): void {
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
@scope (.scopeRoot) to (:global(.legacy-stop), .scopeLimit) {
  .card {
    composes: base;
    color: red;
  }

  .base {
    color: blue;
  }
}
CSS);

        $t->same('@scope(.EgL3uq_scopeRoot) to (.legacy-stop,.EgL3uq_scopeLimit){:scope .EgL3uq_card{color:red}:scope .EgL3uq_base{color:#00f}}', $result['code']);
        $t->same([
            'scopeRoot' => $export('EgL3uq_scopeRoot'),
            'scopeLimit' => $export('EgL3uq_scopeLimit'),
            'card' => $export('EgL3uq_card', [$local('EgL3uq_base')]),
            'base' => $export('EgL3uq_base'),
        ], $result['exports']);
        $t->same([], $result['references']);

        $globalLocal = (new CssModulesTransformer())->transform(<<<'CSS'
@scope (:global(.wp-block) :local(.card-scope)) to (:global(.stop)) {
  .card {
    composes: utility from global;
    color: yellow;
  }
}
CSS);

        $t->same('@scope(.wp-block .EgL3uq_card-scope) to (.stop){:scope .EgL3uq_card{color:#ff0}}', $globalLocal['code']);
        $t->same([
            'card-scope' => $export('EgL3uq_card-scope'),
            'card' => $export('EgL3uq_card', [$global('utility')]),
        ], $globalLocal['exports']);
        $t->same([], $globalLocal['references']);
    },
    'css modules pure mode validates upstream scope rule selector boundaries' => static function (TestRunner $t) use ($export): void {
        $transformer = new CssModulesTransformer();

        $accepted = $transformer->transform('@scope (.a) to (.b) { .foo { color: red } }', ['pure' => true]);
        $t->same('@scope(.EgL3uq_a) to (.EgL3uq_b){:scope .EgL3uq_foo{color:red}}', $accepted['code']);
        $t->same([
            'a' => $export('EgL3uq_a'),
            'b' => $export('EgL3uq_b'),
            'foo' => $export('EgL3uq_foo'),
        ], $accepted['exports']);

        foreach ([
            '@scope (div) { .foo { color: red } }',
            '@scope (.a) to (div) { .foo { color: red } }',
            '@scope (.a) to (.b) { div { color: red } }',
        ] as $css) {
            $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform($css, ['pure' => true]));
        }
    },
    'css modules scopes upstream dashed idents and records dependency references' => static function (TestRunner $t) use ($export, $dashed): void {
        $css = <<<'CSS'
.foo {
  --accent: red;
  color: var(--accent);
}

.bar {
  color: var(--color from "./tokens.css");
}
CSS;

        $result = (new CssModulesTransformer())->transform($css, [
            'dashedIdents' => true,
        ]);
        $placeholder = array_key_first($result['references']);

        if (!is_string($placeholder)) {
            throw new RuntimeException('Expected a dashed-ident dependency placeholder');
        }

        $t->contains('.EgL3uq_foo{--EgL3uq_accent:red;color:var(--EgL3uq_accent)}', $result['code']);
        $t->contains('.EgL3uq_bar{color:var(' . $placeholder . ')}', $result['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo'),
            '--accent' => $dashed('--EgL3uq_accent', true),
            'bar' => $export('EgL3uq_bar'),
        ], $result['exports']);
        $t->same([
            $placeholder => [
                'type' => 'dependency',
                'name' => '--color',
                'specifier' => './tokens.css',
            ],
        ], $result['references']);
    },
    'css modules scopes upstream media env dashed idents while preserving composes' => static function (TestRunner $t) use ($export, $dashed, $dependency, $global): void {
        $css = <<<'CSS'
@media (max-width: env(--branding-small)) {
  .foo {
    color: env(--brand-color);
    composes: base from "tokens.css";
    composes: utility from global;
  }
}
CSS;

        $result = (new CssModulesTransformer())->transform($css, [
            'dashedIdents' => true,
        ]);

        $t->same('@media (width<=env(--EgL3uq_branding-small)){.EgL3uq_foo{color:env(--EgL3uq_brand-color)}}', $result['code']);
        $t->same([
            '--branding-small' => $dashed('--EgL3uq_branding-small', true),
            'foo' => $export('EgL3uq_foo', [$dependency('base', 'tokens.css'), $global('utility')]),
            '--brand-color' => $dashed('--EgL3uq_brand-color', true),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules scopes upstream dashed property and font palette idents while preserving composes' => static function (TestRunner $t) use ($export, $dashed, $dependency): void {
        $css = <<<'CSS'
@property --foo {
  syntax: '<color>';
  inherits: false;
  initial-value: yellow;
}

@font-palette-values --Cooler {
  font-family: Bixa;
  base-palette: 1;
  override-colors: 1 #7EB7E4;
}

.foo {
  --foo: red;
  font-palette: --Cooler;
  composes: base from "tokens.css";
  color: var(--foo);
}
CSS;

        $result = (new CssModulesTransformer())->transform($css, [
            'dashedIdents' => true,
        ]);

        $t->same('@property --EgL3uq_foo{syntax:"<color>";inherits:false;initial-value:#ff0}@font-palette-values --EgL3uq_Cooler{font-family:Bixa;base-palette:1;override-colors:1 #7eb7e4}.EgL3uq_foo{--EgL3uq_foo:red;font-palette:--EgL3uq_Cooler;color:var(--EgL3uq_foo)}', $result['code']);
        $t->same([
            '--foo' => $dashed('--EgL3uq_foo', true),
            '--Cooler' => $dashed('--EgL3uq_Cooler', true),
            'foo' => $export('EgL3uq_foo', [$dependency('base', 'tokens.css')]),
        ], $result['exports']);
        $t->same([], $result['references']);

        $disabled = (new CssModulesTransformer())->transform($css);
        $t->contains('@property --foo', $disabled['code']);
        $t->contains('@font-palette-values --Cooler', $disabled['code']);
        $t->contains('font-palette:--Cooler', $disabled['code']);
        $t->same([
            'foo' => $export('EgL3uq_foo', [$dependency('base', 'tokens.css')]),
        ], $disabled['exports']);
    },
    'css modules scopes upstream view transition declaration idents' => static function (TestRunner $t) use ($export): void {
        $css = <<<'CSS'
.card {
  view-transition-name: card-enter;
  view-transition-class: page nav-menu;
  view-transition-group: contain;
}

.panel {
  view-transition-group: modal;
}

@view-transition {
  types: page nav-menu;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same('.EgL3uq_card{view-transition-name:EgL3uq_card-enter;view-transition-class:EgL3uq_page EgL3uq_nav-menu;view-transition-group:contain}.EgL3uq_panel{view-transition-group:EgL3uq_modal}@view-transition{types:EgL3uq_page EgL3uq_nav-menu}', $result['code']);
        $t->same([
            'card' => $export('EgL3uq_card'),
            'card-enter' => $export('EgL3uq_card-enter'),
            'page' => $export('EgL3uq_page'),
            'nav-menu' => $export('EgL3uq_nav-menu'),
            'panel' => $export('EgL3uq_panel'),
            'modal' => $export('EgL3uq_modal'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
    'css modules scopes upstream view transition selector function idents' => static function (TestRunner $t) use ($export): void {
        $css = <<<'CSS'
:root:active-view-transition-type(page, nav-menu) {
  color: red;
}

:root::view-transition-group(hero.card.featured) {
  position: fixed;
}

:root::view-transition-new(.thumb) {
  position: fixed;
}

:root::view-transition-image-pair(card) {
  opacity: 1;
}

:root::view-transition-old(.card) {
  opacity: 0;
}

:global(:root::view-transition-group(public-card)) {
  opacity: .5;
}
CSS;

        $result = (new CssModulesTransformer())->transform($css);

        $t->same(':root:active-view-transition-type(EgL3uq_page,EgL3uq_nav-menu){color:red}:root::view-transition-group(EgL3uq_hero.EgL3uq_card.EgL3uq_featured){position:fixed}:root::view-transition-new(.EgL3uq_thumb){position:fixed}:root::view-transition-image-pair(EgL3uq_card){opacity:1}:root::view-transition-old(.EgL3uq_card){opacity:0}:root::view-transition-group(public-card){opacity:.5}', $result['code']);
        $t->same([
            'page' => $export('EgL3uq_page'),
            'nav-menu' => $export('EgL3uq_nav-menu'),
            'hero' => $export('EgL3uq_hero'),
            'card' => $export('EgL3uq_card'),
            'featured' => $export('EgL3uq_featured'),
            'thumb' => $export('EgL3uq_thumb'),
        ], $result['exports']);
        $t->same([], $result['references']);
    },
];
