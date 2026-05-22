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
$enumAliasConfigTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-enum-alias-config.ts');
$constEnumConfigTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-const-enum-config.ts');
$ambientTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-ambient-types.ts');
$ambientExportsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-ambient-exports.ts');
$classDeclareTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-class-declare-settings.ts');
$constructorPropertiesTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-constructor-properties.ts');
$classFieldsAssignTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-class-fields-assign.ts');
$computedClassFieldsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-computed-class-fields.ts');
$computedSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-computed-super-controller.ts');
$conditionalSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-conditional-super-controller.ts');
$lazySuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-lazy-super-controller.ts');
$commaSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-comma-super-controller.ts');
$returnSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-return-super-controller.ts');
$controlSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-control-super-controller.ts');
$privateSettingsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-private-settings-controller.ts');
$autoAccessorTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-auto-accessor-controller.ts');
$usingDisposableTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-using-disposable.ts');
$functionUsingDisposableTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-function-using-disposable.ts');
$forUsingAssetsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-for-using-assets.ts');
$namespaceExportTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-namespace-export.ts');
$namespaceRuntimeTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-namespace-runtime.ts');
$nestedNamespaceEnumTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-nested-namespace-enum.ts');
$dotNamespaceTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-dot-namespace.ts');
$destructuredNamespaceTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-destructured-settings.ts');
$functionNamespaceTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-function-namespace.ts');
$tokens = (new JsLexer())->tokenize($source);
$analysis = (new JsModuleAnalyzer())->analyze($source);
$typeScriptAnalysis = (new JsModuleAnalyzer())->analyze($typeScriptSource);
$commonJsLowered = (new TypeScriptModuleLowerer())->lower($commonJsTypeScriptSource);
$typedCallbackLowered = (new TypeScriptModuleLowerer())->lower($typedCallbackTypeScriptSource);
$enumConfigLowered = (new TypeScriptModuleLowerer())->lower($enumConfigTypeScriptSource);
$enumAliasConfigLowered = (new TypeScriptModuleLowerer())->lower($enumAliasConfigTypeScriptSource);
$constEnumConfigLowered = (new TypeScriptModuleLowerer())->lower($constEnumConfigTypeScriptSource);
$ambientLowered = (new TypeScriptModuleLowerer())->lower($ambientTypeScriptSource);
$ambientExportsLowered = (new TypeScriptModuleLowerer())->lower($ambientExportsTypeScriptSource);
$classDeclareLowered = (new TypeScriptModuleLowerer())->lower($classDeclareTypeScriptSource);
$constructorPropertiesLowered = (new TypeScriptModuleLowerer())->lower($constructorPropertiesTypeScriptSource);
$classFieldsAssignLowered = (new TypeScriptModuleLowerer())->lower($classFieldsAssignTypeScriptSource, false);
$computedClassFieldsLowered = (new TypeScriptModuleLowerer())->lower($computedClassFieldsTypeScriptSource, false);
$computedSuperLowered = (new TypeScriptModuleLowerer())->lower($computedSuperTypeScriptSource, false);
$conditionalSuperLowered = (new TypeScriptModuleLowerer())->lower($conditionalSuperTypeScriptSource, false);
$lazySuperLowered = (new TypeScriptModuleLowerer())->lower($lazySuperTypeScriptSource, false);
$commaSuperLowered = (new TypeScriptModuleLowerer())->lower($commaSuperTypeScriptSource, false);
$returnSuperLowered = (new TypeScriptModuleLowerer())->lower($returnSuperTypeScriptSource, false);
$controlSuperLowered = (new TypeScriptModuleLowerer())->lower($controlSuperTypeScriptSource, false);
$privateSettingsLowered = (new TypeScriptModuleLowerer())->lower($privateSettingsTypeScriptSource, false);
$autoAccessorLowered = (new TypeScriptModuleLowerer())->lower($autoAccessorTypeScriptSource);
$usingDisposableLowered = (new TypeScriptModuleLowerer())->lower($usingDisposableTypeScriptSource);
$usingDisposableLegacyLowered = (new TypeScriptModuleLowerer())->lower($usingDisposableTypeScriptSource, lowerUsingDeclarations: true);
$functionUsingDisposableLowered = (new TypeScriptModuleLowerer())->lower($functionUsingDisposableTypeScriptSource);
$forUsingAssetsLowered = (new TypeScriptModuleLowerer())->lower($forUsingAssetsTypeScriptSource);
$namespaceExportLowered = (new TypeScriptNamespaceLowerer())->lower($namespaceExportTypeScriptSource);
$namespaceRuntimeLowered = (new TypeScriptNamespaceLowerer())->lower($namespaceRuntimeTypeScriptSource);
$nestedNamespaceEnumLowered = (new TypeScriptNamespaceLowerer())->lower($nestedNamespaceEnumTypeScriptSource);
$dotNamespaceLowered = (new TypeScriptNamespaceLowerer())->lower($dotNamespaceTypeScriptSource);
$destructuredNamespaceLowered = (new TypeScriptNamespaceLowerer())->lower($destructuredNamespaceTypeScriptSource);
$functionNamespaceLowered = (new TypeScriptNamespaceLowerer())->lower($functionNamespaceTypeScriptSource);
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
printf("WordPress TypeScript enum alias config bytes: %d\n", strlen($enumAliasConfigLowered));
printf("WordPress TypeScript const enum config bytes: %d\n", strlen($constEnumConfigLowered));
printf("WordPress TypeScript ambient declaration bytes: %d\n", strlen($ambientLowered));
printf("WordPress TypeScript ambient export declaration bytes: %d\n", strlen($ambientExportsLowered));
printf("WordPress TypeScript declared class field bytes: %d\n", strlen($classDeclareLowered));
printf("WordPress TypeScript constructor property bytes: %d\n", strlen($constructorPropertiesLowered));
printf("WordPress TypeScript class field assign-semantics bytes: %d\n", strlen($classFieldsAssignLowered));
printf("WordPress TypeScript computed class field bytes: %d\n", strlen($computedClassFieldsLowered));
printf("WordPress TypeScript computed super controller bytes: %d\n", strlen($computedSuperLowered));
printf("WordPress TypeScript conditional super controller bytes: %d\n", strlen($conditionalSuperLowered));
printf("WordPress TypeScript lazy super controller bytes: %d\n", strlen($lazySuperLowered));
printf("WordPress TypeScript comma super controller bytes: %d\n", strlen($commaSuperLowered));
printf("WordPress TypeScript return super controller bytes: %d\n", strlen($returnSuperLowered));
printf("WordPress TypeScript control super controller bytes: %d\n", strlen($controlSuperLowered));
printf("WordPress TypeScript private settings controller bytes: %d\n", strlen($privateSettingsLowered));
printf("WordPress TypeScript auto accessor controller bytes: %d\n", strlen($autoAccessorLowered));
printf("WordPress TypeScript using disposable asset bytes: %d\n", strlen($usingDisposableLowered));
printf("WordPress TypeScript legacy using helper bytes: %d\n", strlen($usingDisposableLegacyLowered));
printf("WordPress TypeScript function scoped using bytes: %d\n", strlen($functionUsingDisposableLowered));
printf("WordPress TypeScript for using asset loop bytes: %d\n", strlen($forUsingAssetsLowered));
printf("WordPress TypeScript lowered namespace bytes: %d\n", strlen($namespaceLowered));
printf("WordPress TypeScript namespace export bytes: %d\n", strlen($namespaceExportLowered));
printf("WordPress TypeScript namespace runtime bytes: %d\n", strlen($namespaceRuntimeLowered));
printf("WordPress TypeScript nested namespace enum bytes: %d\n", strlen($nestedNamespaceEnumLowered));
printf("WordPress TypeScript dot namespace bytes: %d\n", strlen($dotNamespaceLowered));
printf("WordPress TypeScript destructured namespace bytes: %d\n", strlen($destructuredNamespaceLowered));
printf("WordPress TypeScript function namespace bytes: %d\n", strlen($functionNamespaceLowered));
printf("JSON metadata imports: %d\n", count(array_filter(
    $analysis->relativeImports(),
    static fn ($import): bool => $import->hasJsonTypeAttribute()
)));
printf("Uses import.meta: %s\n", $analysis->hasImportMeta() ? 'yes' : 'no');
printf("Relative module asset references: %d\n", count(array_filter(
    $analysis->assetReferences,
    static fn ($reference): bool => $reference->isRelative()
)));
