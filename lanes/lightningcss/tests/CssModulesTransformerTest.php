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
    'css modules deduplicates repeated composes references from simple local selectors' => static function (TestRunner $t) use ($export, $local, $global, $dependency): void {
        $css = <<<'CSS'
:local(.test) {
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
