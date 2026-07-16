<?php

declare(strict_types=1);

use PortLibs\Esbuild\GlobImportResolver;
use PortLibs\Esbuild\JsModuleAnalyzer;

return [
    'maps upstream static and dynamic import forms' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import "side";
import {x as y, z,} from "foo";
import zDefault, * as ns from "pkg";
import('dyn');
JS);

        $t->same(4, count($analysis->imports));
        $t->same('side-effect', $analysis->imports[0]->kind);
        $t->same('side', $analysis->imports[0]->source);
        $t->same('named', $analysis->imports[1]->kind);
        $t->same([
            ['imported' => 'x', 'local' => 'y'],
            ['imported' => 'z', 'local' => 'z'],
        ], $analysis->imports[1]->specifiers);
        $t->same('default-namespace', $analysis->imports[2]->kind);
        $t->same([
            ['imported' => 'default', 'local' => 'zDefault'],
            ['imported' => '*', 'local' => 'ns'],
        ], $analysis->imports[2]->specifiers);
        $t->same('dynamic', $analysis->imports[3]->kind);
        $t->same('dyn', $analysis->imports[3]->source);
    },
    'maps upstream no-substitution template literal sources' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import(`./preview.js`);
import metadata = require(`./block.json`);
const worker = new URL(`./preview-worker.js`, import.meta.url);
JS);

        $t->same(['dynamic', 'ts-import-equals-require'], array_map(static fn ($import): string => $import->kind, $analysis->imports));
        $t->same(['./preview.js', './block.json'], array_map(static fn ($import): string => $import->source, $analysis->imports));
        $t->same([['imported' => './block.json', 'local' => 'metadata']], $analysis->imports[1]->specifiers);
        $t->same(['./preview-worker.js'], array_map(static fn ($reference): string => $reference->source, $analysis->assetReferences));
        $t->same(['new-url'], array_map(static fn ($reference): string => $reference->context, $analysis->assetReferences));
        $t->true($analysis->hasImportMeta());
    },
    'maps upstream conditional dynamic import records and skips dead branches' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import(enabled ? './preview-a.js' : nested ? `./preview-b.js` : 'preview-package');
import(enabled ? nested ? './optional-a.js' : `./optional-b.js` : computedPath);
import(`./${name}.js`);
if (false) {
  import('./dead-preview.js');
}
if (false) import(`./dead-single.js`);
JS);

        $t->same([
            './preview-a.js',
            './preview-b.js',
            'preview-package',
            './optional-a.js',
            './optional-b.js',
            './**/*.js',
        ], array_map(static fn ($import): string => $import->source, $analysis->imports));
        $t->same([
            'dynamic',
            'dynamic',
            'dynamic',
            'dynamic',
            'dynamic',
            'dynamic-glob',
        ], array_map(static fn ($import): string => $import->kind, $analysis->imports));
        $t->same(['preview-package'], array_map(static fn ($import): string => $import->source, $analysis->packageImports()));
        $t->same([
            './preview-a.js',
            './preview-b.js',
            './optional-a.js',
            './optional-b.js',
            './**/*.js',
        ], array_map(static fn ($import): string => $import->source, $analysis->relativeImports()));
        $t->true(!$analysis->isConsideredESModule());
    },
    'maps upstream commonjs require and require resolve string records' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
const metadata = require('./block.json');
const preview = require(`./preview.js`);
const resolved = require.resolve(`./preview-worker.js`);
const dynamic = require(name);
const dynamicTemplate = require(`./${name}.js`);
const tooMany = require('./a', './b');
const indirect = module.require('./skip.js');
require(tag`./tagged.js`);
import(`./${name}.js`);
import typed = require(`./typed.json`);
JS);

        $t->same([
            'commonjs-require',
            'commonjs-require',
            'commonjs-require-resolve',
            'commonjs-require-glob',
            'dynamic-glob',
            'ts-import-equals-require',
        ], array_map(static fn ($import): string => $import->kind, $analysis->imports));
        $t->same([
            './block.json',
            './preview.js',
            './preview-worker.js',
            './**/*.js',
            './**/*.js',
            './typed.json',
        ], array_map(static fn ($import): string => $import->source, $analysis->imports));
        $t->same([
            './block.json',
            './preview.js',
            './preview-worker.js',
            './**/*.js',
            './**/*.js',
            './typed.json',
        ], array_map(static fn ($import): string => $import->source, $analysis->relativeImports()));
        $t->true(!$analysis->isConsideredESModule());
        $t->same([
            'commonjs-require',
            'commonjs-require',
            'commonjs-require-resolve',
            'commonjs-require-glob',
            'dynamic-glob',
            'ts-import-equals-require',
        ], array_map(static fn ($import): string => $import->kind, $analysis->prunedTypeScriptRuntimeImports()));
    },
    'maps upstream conditional commonjs require records and skips dead branches' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
const preview = require(enabled ? './preview-a.js' : nested ? `./preview-b.js` : 'preview-package');
const optional = require(enabled ? nested ? './optional-a.js' : `./optional-b.js` : computedPath);
const resolved = require.resolve('./worker.js');
const dynamicResolved = require.resolve(enabled ? './worker-a.js' : nested ? './worker-b.js' : computedPath);
if (enabled) {
  require('./conditional-live.js');
}
try {
  if (enabled) require('./try-live.js');
} catch {
}
if (false) {
  require('./dead-preview.js');
  require.resolve('./dead-worker.js');
}
if (false) require('./dead-single.js');
JS);

        $t->same([
            './preview-a.js',
            './preview-b.js',
            'preview-package',
            './optional-a.js',
            './optional-b.js',
            './worker.js',
            './conditional-live.js',
            './try-live.js',
        ], array_map(static fn ($import): string => $import->source, $analysis->imports));
        $t->same([
            'commonjs-require',
            'commonjs-require',
            'commonjs-require',
            'commonjs-require',
            'commonjs-require',
            'commonjs-require-resolve',
            'commonjs-require',
            'commonjs-require',
        ], array_map(static fn ($import): string => $import->kind, $analysis->imports));
        $t->same(['preview-package'], array_map(static fn ($import): string => $import->source, $analysis->packageImports()));
        $t->same([
            './preview-a.js',
            './preview-b.js',
            './optional-a.js',
            './optional-b.js',
            './worker.js',
            './conditional-live.js',
            './try-live.js',
        ], array_map(static fn ($import): string => $import->source, $analysis->relativeImports()));
        $t->true(!$analysis->isConsideredESModule());
    },
    'maps upstream dead logical and conditional require import branches' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
true && require('./live-and.js');
false || require('./live-or.js');
false && require('./dead-and.js');
true || require('./dead-or.js');
true ?? require('./dead-nullish.js');
false ? require('./dead-if-a.js') : require('./live-if-a.js');
true ? require('./live-if-b.js') : require('./dead-if-b.js');
false && import('./dead-dyn-and.js');
true || import('./dead-dyn-or.js');
true ?? import('./dead-dyn-nullish.js');
false ? import('./dead-dyn-a.js') : import('./live-dyn-a.js');
true ? import('./live-dyn-b.js') : import('./dead-dyn-b.js');
false ? 0 : require.resolve('./live-worker.js');
true ? require.resolve('./live-resolved.js') : 0;
false ? require.resolve('./dead-resolve-a.js') : 0;
true ? 0 : require.resolve('./dead-resolve-b.js');
JS);

        $t->same([
            './live-and.js',
            './live-or.js',
            './live-if-a.js',
            './live-if-b.js',
            './live-dyn-a.js',
            './live-dyn-b.js',
            './live-worker.js',
            './live-resolved.js',
        ], array_map(static fn ($import): string => $import->source, $analysis->imports));
        $t->same([
            'commonjs-require',
            'commonjs-require',
            'commonjs-require',
            'commonjs-require',
            'dynamic',
            'dynamic',
            'commonjs-require-resolve',
            'commonjs-require-resolve',
        ], array_map(static fn ($import): string => $import->kind, $analysis->imports));
        $t->true(!$analysis->isConsideredESModule());
    },
    'maps upstream relative glob require and dynamic import records' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
const ab = Math.random() < 0.5 ? 'a.js' : 'b.js';
const preview = require('./src/' + ab);
const feature = require('./src/file-' + ab + '.js');
const block = require(`./blocks/${metadata.name}.js`);
const view = import('./chunks/' + metadata.viewScript + '.js');
const variation = import(`./variations/${metadata.variation}.js`);
const notRelative = require(prefix + '/skip.js');
const absolute = import('/abs/' + name + '.js');
JS);

        $t->same([
            'commonjs-require-glob',
            'commonjs-require-glob',
            'commonjs-require-glob',
            'dynamic-glob',
            'dynamic-glob',
        ], array_map(static fn ($import): string => $import->kind, $analysis->imports));
        $t->same([
            './src/**/*',
            './src/file-*.js',
            './blocks/**/*.js',
            './chunks/**/*.js',
            './variations/**/*.js',
        ], array_map(static fn ($import): string => $import->source, $analysis->imports));
        $t->same([], $analysis->packageImports());
        $t->same([
            './src/**/*',
            './src/file-*.js',
            './blocks/**/*.js',
            './chunks/**/*.js',
            './variations/**/*.js',
        ], array_map(static fn ($import): string => $import->source, $analysis->relativeImports()));
        $t->true(!$analysis->isConsideredESModule());
        $t->same([
            'commonjs-require-glob',
            'commonjs-require-glob',
            'commonjs-require-glob',
            'dynamic-glob',
            'dynamic-glob',
        ], array_map(static fn ($import): string => $import->kind, $analysis->prunedTypeScriptRuntimeImports()));
    },
    'resolves upstream relative glob records against a wordpress fixture graph' => static function (TestRunner $t): void {
        $fixtureDir = dirname(__DIR__) . '/fixtures/wordpress-glob-assets';
        $source = (string) file_get_contents($fixtureDir . '/entry.js');
        $analysis = (new JsModuleAnalyzer())->analyze($source);
        $matches = (new GlobImportResolver())->resolve($analysis, $fixtureDir);

        $t->same([
            './blocks/card/view.js',
            './blocks/gallery/view.js',
            './blocks/nested/card/view.js',
            './variations/blue.js',
            './variations/seasonal/sale.js',
            './styles/admin.css',
            './styles/front.css',
            './styles/nested/print.css',
        ], array_map(static fn ($match): string => $match->key, $matches));
        $t->same([
            'commonjs-require-glob',
            'commonjs-require-glob',
            'commonjs-require-glob',
            'dynamic-glob',
            'dynamic-glob',
            'commonjs-require-glob',
            'commonjs-require-glob',
            'commonjs-require-glob',
        ], array_map(static fn ($match): string => $match->import->kind, $matches));
        $t->true(str_ends_with($matches[0]->path, '/blocks/card/view.js'));
        $t->true(str_ends_with($matches[4]->path, '/variations/seasonal/sale.js'));
        $t->same([], array_values(array_filter(
            array_map(static fn ($match): string => $match->key, $matches),
            static fn (string $key): bool => str_ends_with($key, 'editor.js')
                || str_ends_with($key, '.svg')
                || $key === './blocks/view.js'
        )));
    },
    'maps upstream import assertion and attribute clauses' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import data from "./data.json" assert { type: "json" };
import bytes from "./asset.bin" with { type: "bytes", loader: "file" };
export { default } from "./data.json" with { type: "json" };
export * from "./legacy.json" assert { type: "json" };
import("./lazy.json", { with: { type: "json" } });
JS);

        $t->same('assert', $analysis->imports[0]->attributesKeyword);
        $t->same(['type' => 'json'], $analysis->imports[0]->attributes);
        $t->true($analysis->imports[0]->hasJsonTypeAttribute());
        $t->same('with', $analysis->imports[1]->attributesKeyword);
        $t->same(['type' => 'bytes', 'loader' => 'file'], $analysis->imports[1]->attributes);
        $t->same('with', $analysis->imports[2]->attributesKeyword);
        $t->same(['type' => 'json'], $analysis->imports[2]->attributes);
        $t->same('with', $analysis->exports[0]->attributesKeyword);
        $t->same(['type' => 'json'], $analysis->exports[0]->attributes);
        $t->same('assert', $analysis->exports[1]->attributesKeyword);
        $t->same(['type' => 'json'], $analysis->exports[1]->attributes);
    },
    'maps upstream typescript import equals and export equals forms' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import settings = require('./settings.json');
import wpBlocks = wp.blocks
settings.init();
export = settings;
JS);

        $t->same(2, count($analysis->imports));
        $t->same('ts-import-equals-require', $analysis->imports[0]->kind);
        $t->same('./settings.json', $analysis->imports[0]->source);
        $t->same([['imported' => './settings.json', 'local' => 'settings']], $analysis->imports[0]->specifiers);
        $t->same('ts-import-equals-reference', $analysis->imports[1]->kind);
        $t->same('wp.blocks', $analysis->imports[1]->source);
        $t->same([['imported' => 'wp.blocks', 'local' => 'wpBlocks']], $analysis->imports[1]->specifiers);
        $t->same('ts-export-equals', $analysis->exports[0]->kind);
        $t->true(!$analysis->isConsideredESModule());
    },
    'maps upstream typescript type only import and export forms' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import type { BlockConfiguration, BlockEditProps as EditProps } from '@wordpress/blocks';
import type metadata from './block.json';
import domReady, { type WPElement } from '@wordpress/element';
import type from 'bar';
export type { BlockConfiguration as CardBlockConfiguration } from '@wordpress/blocks';
export { domReady, type WPElement as ElementType };
JS);

        $t->same(4, count($analysis->imports));
        $t->same('type-only-named', $analysis->imports[0]->kind);
        $t->true($analysis->imports[0]->typeOnly);
        $t->same([
            ['imported' => 'BlockConfiguration', 'local' => 'BlockConfiguration'],
            ['imported' => 'BlockEditProps', 'local' => 'EditProps'],
        ], $analysis->imports[0]->typeSpecifiers);
        $t->same('type-only-default', $analysis->imports[1]->kind);
        $t->same([['imported' => 'default', 'local' => 'metadata']], $analysis->imports[1]->typeSpecifiers);
        $t->same('default-named', $analysis->imports[2]->kind);
        $t->same([['imported' => 'default', 'local' => 'domReady']], $analysis->imports[2]->specifiers);
        $t->same([['imported' => 'WPElement', 'local' => 'WPElement']], $analysis->imports[2]->typeSpecifiers);
        $t->same('default', $analysis->imports[3]->kind);
        $t->same([['imported' => 'default', 'local' => 'type']], $analysis->imports[3]->specifiers);
        $t->same(3, count($analysis->typeOnlyImports()));
        $t->same(2, count($analysis->runtimeImports()));
        $t->same('type-only-re-export-named', $analysis->exports[0]->kind);
        $t->same('@wordpress/blocks', $analysis->exports[0]->source);
        $t->same([['exported' => 'CardBlockConfiguration', 'local' => 'BlockConfiguration']], $analysis->exports[0]->typeSpecifiers);
        $t->same('named', $analysis->exports[1]->kind);
        $t->same([['exported' => 'domReady', 'local' => 'domReady']], $analysis->exports[1]->specifiers);
        $t->same([['exported' => 'ElementType', 'local' => 'WPElement']], $analysis->exports[1]->typeSpecifiers);
    },
    'maps upstream typescript namespace exports without polluting module exports' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
namespace A {
  export namespace B {
    export function fn() {}
  }
  namespace C {
    export class Class {}
  }
  namespace D {
    function hidden() {}
  }
  export const foo = 1;
  export type Shape = {};
}
export namespace Public {
  export let value = 1;
}
export let topLevel = 1;
JS);

        $t->same(['declaration', 'declaration'], array_map(static fn ($export): string => $export->kind, $analysis->exports));
        $t->same(['A', 'A.B', 'A.C', 'A.D', 'Public'], array_map(static fn ($namespace): string => $namespace->qualifiedName, $analysis->typeScriptNamespaces));

        $a = $analysis->typeScriptNamespace('A');
        $public = $analysis->typeScriptNamespace('Public');
        $b = $analysis->typeScriptNamespace('A.B');
        $c = $analysis->typeScriptNamespace('A.C');
        $d = $analysis->typeScriptNamespace('A.D');

        $t->true($a !== null && !$a->exported);
        $t->true($public !== null && $public->exported);
        $t->same(['B', 'C', 'D', 'foo', 'Shape'], array_map(static fn ($member): string => $member->name, $a->members));
        $t->same(['B', 'foo'], array_map(static fn ($member): string => $member->name, $a->runtimeExportedMembers()));
        $t->same('type', $a->members[4]->kind);
        $t->true($a->members[4]->typeOnly);
        $t->same(['fn'], array_map(static fn ($member): string => $member->name, $b?->runtimeExportedMembers() ?? []));
        $t->same(['Class'], array_map(static fn ($member): string => $member->name, $c?->runtimeExportedMembers() ?? []));
        $t->same([], $d?->runtimeExportedMembers() ?? []);
        $t->same(['value'], array_map(static fn ($member): string => $member->name, $public?->runtimeExportedMembers() ?? []));
    },
    'maps upstream export as namespace as type only metadata' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
export as namespace wp;
const metadata = { name: 'port-libs/card' };
JS);

        $t->same(1, count($analysis->exports));
        $t->same('type-only-export-as-namespace', $analysis->exports[0]->kind);
        $t->true($analysis->exports[0]->typeOnly);
        $t->same([['exported' => 'wp', 'local' => 'wp']], $analysis->exports[0]->typeSpecifiers);
        $t->true(!$analysis->isConsideredESModule());

        $t->throws(InvalidArgumentException::class, static fn () => (new JsModuleAnalyzer())->analyze('export as namespace ns.foo'));
        $t->throws(InvalidArgumentException::class, static fn () => (new JsModuleAnalyzer())->analyze('export as namespace ns function foo() {}'));
    },
    'maps upstream dot qualified typescript namespaces as nested metadata' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
namespace foo.bar {
  export const value = 1;
}
JS);

        $t->same(['foo', 'foo.bar'], array_map(static fn ($namespace): string => $namespace->qualifiedName, $analysis->typeScriptNamespaces));

        $foo = $analysis->typeScriptNamespace('foo');
        $bar = $analysis->typeScriptNamespace('foo.bar');

        $t->true($foo !== null && !$foo->exported);
        $t->true($bar !== null && $bar->exported);
        $t->same('bar', $bar?->name);
        $t->same('foo', $bar?->parent);
        $t->same(['bar'], array_map(static fn ($member): string => $member->name, $foo?->runtimeExportedMembers() ?? []));
        $t->same(['value'], array_map(static fn ($member): string => $member->name, $bar?->runtimeExportedMembers() ?? []));
    },
    'maps upstream typescript import pruning and side effect downgrades' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import {x} from 'drop-named';
import {x as live, y as dead} from 'keep-named'; log(live);
import defaultValue from 'keep-default'; log(defaultValue);
import * as namespaceValue from 'keep-namespace'; log(namespaceValue);
import {onlyDead} from 'dead-control'; if (false) log(onlyDead);
import './side-effect.css';
import type {OnlyType} from 'types';
JS);

        $runtimeImports = $analysis->prunedTypeScriptRuntimeImports();

        $t->same([
            'keep-named',
            'keep-default',
            'keep-namespace',
            'dead-control',
            './side-effect.css',
        ], array_map(static fn ($import): string => $import->source, $runtimeImports));
        $t->same([
            'named',
            'default',
            'namespace',
            'side-effect',
            'side-effect',
        ], array_map(static fn ($import): string => $import->kind, $runtimeImports));
        $t->same([['imported' => 'x', 'local' => 'live']], $runtimeImports[0]->specifiers);
        $t->same([['imported' => 'default', 'local' => 'defaultValue']], $runtimeImports[1]->specifiers);
        $t->same([['imported' => '*', 'local' => 'namespaceValue']], $runtimeImports[2]->specifiers);
        $t->same([], $runtimeImports[3]->specifiers);
    },
    'maps upstream typescript import equals fixed point pruning' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import always = require('always-run');
import unused = foo.unused;
import a = foo.a
import b = a.b
import c = b.c
import x = foo.x
import y = x.y
import z = y.z
export let bar = c;
JS);

        $runtimeImports = $analysis->prunedTypeScriptRuntimeImports();

        $t->same([
            'ts-import-equals-require',
            'ts-import-equals-reference',
            'ts-import-equals-reference',
            'ts-import-equals-reference',
        ], array_map(static fn ($import): string => $import->kind, $runtimeImports));
        $t->same(['always-run', 'foo.a', 'a.b', 'b.c'], array_map(static fn ($import): string => $import->source, $runtimeImports));
        $t->same(['always', 'a', 'b', 'c'], array_map(static fn ($import): string => $import->specifiers[0]['local'], $runtimeImports));
    },
    'maps upstream namespace declare and import equals members' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
namespace ns {
  export declare const L1;
  export import foo = bar.x;
  import localAlias = wp.blocks;
}
JS);

        $namespace = $analysis->typeScriptNamespace('ns');
        $t->true($namespace !== null);
        $t->same(['L1', 'foo'], array_map(
            static fn ($member): string => $member->name,
            array_values(array_filter($namespace->members, static fn ($member): bool => $member->exported))
        ));
        $t->same(['foo'], array_map(static fn ($member): string => $member->name, $namespace->runtimeExportedMembers()));
        $t->true($namespace->members[0]->declared);
        $t->same('import-equals', $namespace->members[1]->kind);
        $t->same('bar.x', $namespace->members[1]->source);
        $t->same('localAlias', $namespace->members[2]->name);
        $t->true(!$namespace->members[2]->exported);
        $t->same([], $analysis->imports);
        $t->same([], $analysis->exports);
        $t->true(!$analysis->isConsideredESModule());
    },
    'maps upstream namespace declared binding pattern members' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
namespace ns {
  export declare let [[L2 = x, { [y]: L3 }]];
}
JS);

        $namespace = $analysis->typeScriptNamespace('ns');
        $t->true($namespace !== null);
        $t->same(['L2', 'L3'], array_map(static fn ($member): string => $member->name, $namespace?->members ?? []));
        $t->same(['L2', 'L3'], array_map(
            static fn ($member): string => $member->name,
            array_values(array_filter($namespace?->members ?? [], static fn ($member): bool => $member->exported && $member->declared))
        ));
        $t->same([], array_map(static fn ($member): string => $member->name, $namespace?->runtimeExportedMembers() ?? []));
    },
    'maps upstream import meta module classification and properties' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze('console.log(import.meta.url, import.meta.path)');

        $t->true($analysis->hasImportMeta());
        $t->true($analysis->isConsideredESModule());
        $t->same(['url', 'path'], array_map(static fn (array $property): string => $property['property'], $analysis->importMetaProperties));
        $t->same([], $analysis->imports);
    },
    'maps upstream new url import meta asset references' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
const hero = new URL('./images/hero.svg', import.meta.url);
new Worker(new URL('../workers/card.js', import.meta.url));
import(new URL('./lazy.js', import.meta.url));
JS);

        $t->same(['./images/hero.svg', '../workers/card.js', './lazy.js'], array_map(static fn ($reference): string => $reference->source, $analysis->assetReferences));
        $t->same(['new-url', 'worker-constructor', 'dynamic-import'], array_map(static fn ($reference): string => $reference->context, $analysis->assetReferences));
        $t->same(['import.meta.url', 'import.meta.url', 'import.meta.url'], array_map(static fn ($reference): string => $reference->base, $analysis->assetReferences));
        $t->true($analysis->assetReferences[0]->isRelative());
    },
    'allows dynamic import expressions while rejecting malformed import calls' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze('import(foo); import(new URL("./chunk.js", import.meta.url));');

        $t->same([], $analysis->imports);
        $t->same(['./chunk.js'], array_map(static fn ($reference): string => $reference->source, $analysis->assetReferences));
        $t->throws(InvalidArgumentException::class, static fn () => (new JsModuleAnalyzer())->analyze('import()'));
        $t->throws(InvalidArgumentException::class, static fn () => (new JsModuleAnalyzer())->analyze('import(...a)'));
    },
    'rejects malformed upstream import attribute objects' => static function (TestRunner $t): void {
        $analyzer = new JsModuleAnalyzer();
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('import "x" with { type: "json", type: "json" }'));
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('import "x" assert { type: json }'));
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('import "x" with {,}'));
    },
    'rejects malformed upstream typescript import export forms' => static function (TestRunner $t): void {
        $analyzer = new JsModuleAnalyzer();
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('import type foo, {bar} from "bar"'));
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('import x = foo()'));
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('export type { default }'));
        $t->throws(InvalidArgumentException::class, static fn () => $analyzer->analyze('export import {foo} from "bar"'));
    },
    'maps upstream export and re-export forms' => static function (TestRunner $t): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
export {};
export {a, b as c};
export {x as y} from "foo";
export * as ns from "path";
export default function() {}
JS);

        $t->same(5, count($analysis->exports));
        $t->same('named', $analysis->exports[0]->kind);
        $t->same('named', $analysis->exports[1]->kind);
        $t->same([
            ['exported' => 'a', 'local' => 'a'],
            ['exported' => 'c', 'local' => 'b'],
        ], $analysis->exports[1]->specifiers);
        $t->same('re-export-named', $analysis->exports[2]->kind);
        $t->same('foo', $analysis->exports[2]->source);
        $t->true($analysis->exports[2]->isReExport());
        $t->same('star-as', $analysis->exports[3]->kind);
        $t->same('path', $analysis->exports[3]->source);
        $t->same([['exported' => 'ns', 'local' => '*']], $analysis->exports[3]->specifiers);
        $t->same('default', $analysis->exports[4]->kind);
    },
    'distinguishes wordpress package imports from relative asset imports' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-view.js');
        $source .= "\nimport './style.css';\nimport imageUrl from '../images/hero.png';\nimport apiFetch from '@wordpress/api-fetch';\nimport('https://cdn.example.test/chunk.js');\n";

        $analysis = (new JsModuleAnalyzer())->analyze($source);

        $t->same(['@wordpress/dom-ready', '@wordpress/api-fetch'], array_map(static fn ($import): string => $import->source, $analysis->wordpressPackageImports()));
        $t->same(['@wordpress/dom-ready', '@wordpress/api-fetch'], array_map(static fn ($import): string => $import->source, $analysis->packageImports()));
        $t->same(['./block.json', './style.css', '../images/hero.png'], array_map(static fn ($import): string => $import->source, $analysis->relativeImports()));
        $t->same(['type' => 'json'], $analysis->relativeImports()[0]->attributes);
        $t->same(['./view.css', './card-worker.js'], array_map(static fn ($reference): string => $reference->source, $analysis->assetReferences));
        $t->true(!$analysis->imports[count($analysis->imports) - 1]->isPackage());
    },
    'distinguishes wordpress typescript type imports from runtime assets' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-view-types.ts');
        $analysis = (new JsModuleAnalyzer())->analyze($source);

        $runtimeWordPress = array_values(array_filter(
            $analysis->runtimeImports(),
            static fn ($import): bool => $import->isWordPressPackage()
        ));
        $typeWordPress = array_values(array_filter(
            $analysis->typeOnlyImports(),
            static fn ($import): bool => $import->isWordPressPackage()
        ));
        $importEquals = array_values(array_filter(
            $analysis->imports,
            static fn ($import): bool => $import->kind === 'ts-import-equals-reference'
        ));
        $prunedRuntime = $analysis->prunedTypeScriptRuntimeImports();

        $t->same(['@wordpress/dom-ready'], array_map(static fn ($import): string => $import->source, $runtimeWordPress));
        $t->same(['@wordpress/blocks', '@wordpress/element'], array_map(static fn ($import): string => $import->source, $typeWordPress));
        $t->same(['./block.json'], array_map(static fn ($import): string => $import->source, $analysis->relativeImports()));
        $t->same(['wp.blocks'], array_map(static fn ($import): string => $import->source, $importEquals));
        $t->same(['@wordpress/dom-ready', './block.json', 'wp.blocks'], array_map(static fn ($import): string => $import->source, $prunedRuntime));
        $t->same('type-only-re-export-named', $analysis->exports[0]->kind);
        $t->same(['CardBlock'], array_map(static fn ($namespace): string => $namespace->qualifiedName, $analysis->typeScriptNamespaces));
        $t->same(['name', 'register'], array_map(static fn ($member): string => $member->name, $analysis->typeScriptNamespace('CardBlock')?->runtimeExportedMembers() ?? []));
    },
    'rejects upstream invalid namespace import without as' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new JsModuleAnalyzer())->analyze('import * from "foo"'));
    },
];
