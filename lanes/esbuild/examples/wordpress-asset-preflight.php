<?php

declare(strict_types=1);

use PortLibs\Esbuild\JsLexer;
use PortLibs\Esbuild\JsModuleAnalyzer;
use PortLibs\Esbuild\TypeScriptModuleLowerer;
use PortLibs\Esbuild\TypeScriptNamespaceLowerer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-view.js');
$typeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-view-types.ts');
$commonJsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-commonjs-export.ts');
$typedCallbackTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-typed-callback.ts');
$enumConfigTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-enum-config.ts');
$constEnumConfigTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-const-enum-config.ts');
$namespaceExportTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-namespace-export.ts');
$namespaceRuntimeTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-namespace-runtime.ts');
$nestedNamespaceEnumTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-nested-namespace-enum.ts');
$tokens = (new JsLexer())->tokenize($source);
$analysis = (new JsModuleAnalyzer())->analyze($source);
$typeScriptAnalysis = (new JsModuleAnalyzer())->analyze($typeScriptSource);
$commonJsLowered = (new TypeScriptModuleLowerer())->lower($commonJsTypeScriptSource);
$typedCallbackLowered = (new TypeScriptModuleLowerer())->lower($typedCallbackTypeScriptSource);
$enumConfigLowered = (new TypeScriptModuleLowerer())->lower($enumConfigTypeScriptSource);
$constEnumConfigLowered = (new TypeScriptModuleLowerer())->lower($constEnumConfigTypeScriptSource);
$namespaceExportLowered = (new TypeScriptNamespaceLowerer())->lower($namespaceExportTypeScriptSource);
$namespaceRuntimeLowered = (new TypeScriptNamespaceLowerer())->lower($namespaceRuntimeTypeScriptSource);
$nestedNamespaceEnumLowered = (new TypeScriptNamespaceLowerer())->lower($nestedNamespaceEnumTypeScriptSource);
$namespaceLowered = (new TypeScriptNamespaceLowerer())->lower(<<<'TS'
namespace CardBlockRuntime {
  export import blocks = wp.blocks;
  blocks.registerBlockType(metadata.name, metadata);
}
TS);

printf("WordPress asset tokens: %d\n", count($tokens));
printf("WordPress package imports: %d\n", count($analysis->wordpressPackageImports()));
printf("WordPress TypeScript runtime imports: %d\n", count($typeScriptAnalysis->runtimeImports()));
printf("WordPress TypeScript type-only imports: %d\n", count($typeScriptAnalysis->typeOnlyImports()));
printf("WordPress TypeScript pruned runtime imports: %d\n", count($typeScriptAnalysis->prunedTypeScriptRuntimeImports()));
printf("WordPress TypeScript namespaces: %d\n", count($typeScriptAnalysis->typeScriptNamespaces));
printf("WordPress TypeScript namespace runtime exports: %d\n", count(
    $typeScriptAnalysis->typeScriptNamespace('CardBlock')?->runtimeExportedMembers() ?? []
));
printf("WordPress TypeScript CommonJS export bytes: %d\n", strlen($commonJsLowered));
printf("WordPress TypeScript typed callback bytes: %d\n", strlen($typedCallbackLowered));
printf("WordPress TypeScript runtime enum config bytes: %d\n", strlen($enumConfigLowered));
printf("WordPress TypeScript const enum config bytes: %d\n", strlen($constEnumConfigLowered));
printf("WordPress TypeScript lowered namespace bytes: %d\n", strlen($namespaceLowered));
printf("WordPress TypeScript namespace export bytes: %d\n", strlen($namespaceExportLowered));
printf("WordPress TypeScript namespace runtime bytes: %d\n", strlen($namespaceRuntimeLowered));
printf("WordPress TypeScript nested namespace enum bytes: %d\n", strlen($nestedNamespaceEnumLowered));
printf("JSON metadata imports: %d\n", count(array_filter(
    $analysis->relativeImports(),
    static fn ($import): bool => $import->hasJsonTypeAttribute()
)));
printf("Uses import.meta: %s\n", $analysis->hasImportMeta() ? 'yes' : 'no');
printf("Relative module asset references: %d\n", count(array_filter(
    $analysis->assetReferences,
    static fn ($reference): bool => $reference->isRelative()
)));
