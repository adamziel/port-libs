<?php

declare(strict_types=1);

use PortLibs\Esbuild\TypeScriptNamespaceLowerer;

return [
    'lowers upstream namespace import equals emission cases' => static function (TestRunner $t): void {
        $lowerer = new TypeScriptNamespaceLowerer();

        $t->same('', $lowerer->lower('namespace ns { import foo = bar }'));
        $t->same('', $lowerer->lower('namespace ns { import foo = bar; type x = foo.x }'));
        $t->same(<<<'JS'
var ns;
((ns) => {
  const foo = bar.x;
  foo;
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { import foo = bar.x; foo }'));
        $t->same(<<<'JS'
var ns;
((ns) => {
  ns.foo = bar;
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { export import foo = bar }'));
        $t->same(<<<'JS'
var ns;
((ns) => {
  ns.foo = bar.x;
  ns.foo;
})(ns || (ns = {}));
JS . "\n", $lowerer->lower('namespace ns { export import foo = bar.x; foo }'));

        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower("namespace ns { import {foo} from 'bar' }"));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower("namespace ns { export import foo from 'bar' }"));
        $t->throws(InvalidArgumentException::class, static fn (): string => $lowerer->lower('namespace ns { { import foo = bar } }'));
    },
    'lowers wordpress namespace import equals aliases without node' => static function (TestRunner $t): void {
        $source = <<<'TS'
namespace CardBlockRuntime {
  export import blocks = wp.blocks;
  blocks.registerBlockType(metadata.name, metadata);
}
TS;

        $lowered = (new TypeScriptNamespaceLowerer())->lower($source);

        $t->same(<<<'JS'
var CardBlockRuntime;
((CardBlockRuntime) => {
  CardBlockRuntime.blocks = wp.blocks;
  CardBlockRuntime.blocks.registerBlockType(metadata.name, metadata);
})(CardBlockRuntime || (CardBlockRuntime = {}));
JS . "\n", $lowered);
    },
];
