<?php

declare(strict_types=1);

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

        $t->same(['@wordpress/dom-ready'], array_map(static fn ($import): string => $import->source, $runtimeWordPress));
        $t->same(['@wordpress/blocks', '@wordpress/element'], array_map(static fn ($import): string => $import->source, $typeWordPress));
        $t->same(['./block.json'], array_map(static fn ($import): string => $import->source, $analysis->relativeImports()));
        $t->same(['wp.blocks'], array_map(static fn ($import): string => $import->source, $importEquals));
        $t->same('type-only-re-export-named', $analysis->exports[0]->kind);
        $t->same(['CardBlock'], array_map(static fn ($namespace): string => $namespace->qualifiedName, $analysis->typeScriptNamespaces));
        $t->same(['name', 'register'], array_map(static fn ($member): string => $member->name, $analysis->typeScriptNamespace('CardBlock')?->runtimeExportedMembers() ?? []));
    },
    'rejects upstream invalid namespace import without as' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new JsModuleAnalyzer())->analyze('import * from "foo"'));
    },
];
