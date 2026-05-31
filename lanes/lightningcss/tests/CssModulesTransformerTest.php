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
];
