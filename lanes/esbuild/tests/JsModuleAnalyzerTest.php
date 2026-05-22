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
        $t->same(['./style.css', '../images/hero.png'], array_map(static fn ($import): string => $import->source, $analysis->relativeImports()));
        $t->true(!$analysis->imports[4]->isPackage());
    },
    'rejects upstream invalid namespace import without as' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new JsModuleAnalyzer())->analyze('import * from "foo"'));
    },
];
