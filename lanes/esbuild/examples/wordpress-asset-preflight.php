<?php

declare(strict_types=1);

use PortLibs\Esbuild\BundlerGraphBuilder;
use PortLibs\Esbuild\BundlerMetafile;
use PortLibs\Esbuild\BundlerOutput;
use PortLibs\Esbuild\GlobImportResolver;
use PortLibs\Esbuild\JsLexer;
use PortLibs\Esbuild\JsModuleAnalyzer;
use PortLibs\Esbuild\ModuleImport;
use PortLibs\Esbuild\PackageResolver;
use PortLibs\Esbuild\TsConfigPathResolver;
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
$privateStaticCacheTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-private-static-cache.ts');
$defaultStaticControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-default-static-controller.ts');
$classExpressionControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-class-expression-controller.ts');
$decoratedClassExpressionControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-decorated-class-expression-controller.ts');
$computedClassFieldsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-computed-class-fields.ts');
$computedSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-computed-super-controller.ts');
$conditionalSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-conditional-super-controller.ts');
$lazySuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-lazy-super-controller.ts');
$commaSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-comma-super-controller.ts');
$returnSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-return-super-controller.ts');
$controlSuperTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-control-super-controller.ts');
$privateSettingsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-private-settings-controller.ts');
$privateAccessorTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-private-accessor-controller.ts');
$autoAccessorTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-auto-accessor-controller.ts');
$decoratedControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-decorated-controller.ts');
$decoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-decorator-legacy-controller.ts');
$defaultDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-default-decorator-legacy-controller.ts');
$methodDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-method-decorator-legacy-controller.ts');
$methodDecoratorConstructorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-method-decorator-constructor-legacy-controller.ts');
$derivedMethodDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-derived-method-decorator-legacy-controller.ts');
$mixedDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-mixed-decorator-legacy-controller.ts');
$defaultMixedDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-default-mixed-decorator-legacy-controller.ts');
$staticMethodDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-static-method-decorator-legacy-controller.ts');
$staticFieldDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-static-field-decorator-legacy-controller.ts');
$privateFieldDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-private-field-decorator-legacy-controller.ts');
$privateAccessorDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-private-accessor-decorator-legacy-controller.ts');
$privateGetterDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-private-getter-decorator-legacy-controller.ts');
$accessorDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-accessor-decorator-legacy-controller.ts');
$computedFieldDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-computed-field-decorator-legacy-controller.ts');
$computedMethodDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-computed-method-decorator-legacy-controller.ts');
$computedAccessorDecoratorLegacyControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-computed-accessor-decorator-legacy-controller.ts');
$usingDisposableTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-using-disposable.ts');
$usingImportHoistTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-using-import-hoist.ts');
$usingExportLocalTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-using-export-local.ts');
$usingDestructuredExportTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-using-destructured-export.ts');
$usingExportFunctionTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-using-export-function.ts');
$usingDefaultControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-using-default-controller.ts');
$usingLocalControllerTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-using-local-controller.ts');
$functionUsingDisposableTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-function-using-disposable.ts');
$blockUsingDisposableTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-block-using-disposable.ts');
$forUsingAssetsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-for-using-assets.ts');
$forUsingHelperCollisionTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-for-using-helper-collision.ts');
$switchUsingAssetsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-switch-using-assets.ts');
$whileUsingAssetsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-while-using-assets.ts');
$asyncGeneratorFunctionAssetsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-async-generator-function-assets.ts');
$defaultAsyncGeneratorAssetsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-default-async-generator-assets.ts');
$exportedAsyncGeneratorConstantTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-exported-async-generator-constant.ts');
$asyncGeneratorRegistryAssetsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-async-generator-registry-assets.ts');
$asyncGeneratorHelperCollisionTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-async-generator-helper-collision.ts');
$asyncGeneratorAssetsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-async-generator-assets.ts');
$objectAsyncGeneratorAssetsTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-object-async-generator-assets.ts');
$namespaceExportTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-namespace-export.ts');
$namespaceRuntimeTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-namespace-runtime.ts');
$nestedNamespaceEnumTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-nested-namespace-enum.ts');
$dotNamespaceTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-dot-namespace.ts');
$destructuredNamespaceTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-destructured-settings.ts');
$functionNamespaceTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-function-namespace.ts');
$namespaceUsingPreviewTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-namespace-using-preview.ts');
$namespaceUsingHelperCollisionTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-namespace-using-helper-collision.ts');
$namespaceAwaitUsingPreviewTypeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-namespace-await-using-preview.ts');
$tokens = (new JsLexer())->tokenize($source);
$analysis = (new JsModuleAnalyzer())->analyze($source);
$typeScriptAnalysis = (new JsModuleAnalyzer())->analyze($typeScriptSource);
$templateLiteralAnalysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import(`./preview.js`);
import metadata = require(`./block.json`);
const legacyPreview = require(metadata.viewScript ? `./legacy-preview.js` : `./legacy-editor.js`);
const resolvedWorker = require.resolve(`./preview-worker.js`);
if (false) {
  require(`./debug-preview.js`);
}
const worker = new URL(`./preview-worker.js`, import.meta.url);
JS);
$conditionalDynamicImportAnalysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import(metadata.viewScript ? `./view-preview.js` : `./editor-preview.js`);
import(metadata.variation ? `./variation-preview.js` : metadata.runtimePath);
if (false) {
  import(`./debug-preview.js`);
}
JS);
$globAssetAnalysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
const viewAsset = require(`./blocks/${metadata.name}/view.js`);
const variationAsset = import('./variations/' + metadata.variation + '.js');
const styleAsset = require('./styles/' + metadata.style + '.css');
JS);
$globFixtureDir = dirname(__DIR__) . '/fixtures/wordpress-glob-assets';
$globFixtureSource = (string) file_get_contents($globFixtureDir . '/entry.js');
$globFixtureAnalysis = (new JsModuleAnalyzer())->analyze($globFixtureSource);
$globFixtureMatches = (new GlobImportResolver())->resolve($globFixtureAnalysis, $globFixtureDir);
$packageFixtureDir = dirname(__DIR__) . '/fixtures/wordpress-package-assets';
$packageEntryDir = $packageFixtureDir . '/src';
$packageEntrySource = (string) file_get_contents($packageEntryDir . '/entry.js');
$packageEntryAnalysis = (new JsModuleAnalyzer())->analyze($packageEntrySource);
$packageBrowserResolutions = (new PackageResolver('browser'))->resolve($packageEntryAnalysis, $packageEntryDir);
$packageNodeResolutions = (new PackageResolver('node'))->resolve($packageEntryAnalysis, $packageEntryDir);
$packagePreviewImportResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'exports-map-pkg/preview', [], 0), $packageEntryDir);
$packageConditionalArrayResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'conditional-array-pkg', [], 0), $packageEntryDir);
$packageCustomConditionResolution = (new PackageResolver('browser', null, ['.tsx', '.ts', '.jsx', '.js', '.css', '.json'], ['wordpress']))->resolveImport(new ModuleImport('named', 'conditional-array-pkg/custom', [], 0), $packageEntryDir);
$packageBrowserMapDisabledResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'browser-map-pkg/disabled.js', [], 0), $packageEntryDir);
$containingBrowserMapDir = $packageFixtureDir . '/node_modules/containing-browser-map-pkg';
$containingBrowserLocalResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'node-pkg', [], 0), $containingBrowserMapDir);
$containingBrowserPackageResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'node-pkg-package', [], 0), $containingBrowserMapDir);
$containingBrowserDisabledResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'node-pkg-disabled', [], 0), $containingBrowserMapDir);
$containingBrowserBuiltinResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'path', [], 0), $containingBrowserMapDir);
$containingBrowserNodeBuiltinResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'node:path', [], 0), $containingBrowserMapDir);
$containingBrowserBuiltinDisabledResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'crypto', [], 0), $containingBrowserMapDir);
$nodeBuiltinResolution = (new PackageResolver('node'))->resolveImport(new ModuleImport('named', 'path', [], 0), $containingBrowserMapDir);
$nodePrefixBuiltinResolution = (new PackageResolver('node'))->resolveImport(new ModuleImport('named', 'node:path', [], 0), $containingBrowserMapDir);
$nodeFsPromisesBuiltinResolution = (new PackageResolver('node'))->resolveImport(new ModuleImport('commonjs-require', 'fs/promises', [], 0), $packageEntryDir);
$nodeImportRecordAnalysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import path from "path";
const promises = require("fs/promises");
import("node:crypto");
import cardRuntime from "port-libs-card-runtime";
JS);
$nodeImportRecords = (new PackageResolver('node'))->importRecords($nodeImportRecordAnalysis, $packageEntryDir);
$browserBundlerGraph = (new BundlerGraphBuilder())->build($packageEntryDir . '/entry.js');
$nodeBundlerGraph = (new BundlerGraphBuilder(new PackageResolver('node')))->build($packageEntryDir . '/node-entry.js');
$loaderBundlerGraph = (new BundlerGraphBuilder())->build($packageEntryDir . '/loader-entry.js');
$loaderGraphEdges = array_combine(
    array_map(static fn ($edge): string => $edge->source, $loaderBundlerGraph->modules[(string) realpath($packageEntryDir . '/loader-entry.js')]->edges),
    $loaderBundlerGraph->modules[(string) realpath($packageEntryDir . '/loader-entry.js')]->edges,
);
$unsupportedLoaderGraph = (new BundlerGraphBuilder())->build($packageEntryDir . '/unsupported-loader-entry.js');
$unsupportedLoaderMetafile = (new BundlerMetafile())->summarize($unsupportedLoaderGraph, $packageFixtureDir);
$loaderBundlerOutput = (new BundlerOutput())->build($loaderBundlerGraph, $packageFixtureDir, 'block-view.js');
$loaderOutputMetafile = (new BundlerMetafile())->summarize($loaderBundlerGraph, $packageFixtureDir, $loaderBundlerOutput);
$staticImportOutputGraph = (new BundlerGraphBuilder())->build($packageEntryDir . '/output-static-entry.js');
$staticImportOutput = (new BundlerOutput())->build($staticImportOutputGraph, $packageFixtureDir, 'build/output-static.js');
$reExportOutputGraph = (new BundlerGraphBuilder())->build($packageEntryDir . '/output-reexport-entry.js');
$reExportOutput = (new BundlerOutput())->build($reExportOutputGraph, $packageFixtureDir, 'build/output-reexport.js');
$nodeBundlerOutput = (new BundlerOutput())->build($nodeBundlerGraph, $packageFixtureDir, 'node-block-view.js');
$nodeOutputMetafile = (new BundlerMetafile())->summarize($nodeBundlerGraph, $packageFixtureDir, $nodeBundlerOutput);
$unsupportedLoaderOutput = (new BundlerOutput())->build($unsupportedLoaderGraph, $packageFixtureDir, 'block-view.js');
$unshimmedBrowserNodePrefixResolution = (new PackageResolver('browser'))->resolveImport(new ModuleImport('named', 'node:path', [], 0), $packageEntryDir);
$unshimmedNeutralNodePrefixResolution = (new PackageResolver('neutral'))->resolveImport(new ModuleImport('named', 'node:path', [], 0), $packageEntryDir);
$normalizePackageFixturePath = static function (string $path) use ($packageFixtureDir): string {
    return str_replace('\\', '/', substr($path, strlen((string) realpath($packageFixtureDir)) + 1));
};
$tsconfigFixtureDir = dirname(__DIR__) . '/fixtures/wordpress-tsconfig-assets';
$tsconfigEntryDir = $tsconfigFixtureDir . '/src';
$tsconfigEntrySource = (string) file_get_contents($tsconfigEntryDir . '/entry.ts');
$tsconfigAnalysis = (new JsModuleAnalyzer())->analyze($tsconfigEntrySource);
$tsconfigResolutions = (new TsConfigPathResolver())->resolve($tsconfigAnalysis, $tsconfigEntryDir);
$normalizeTsconfigFixturePath = static function (string $path) use ($tsconfigFixtureDir): string {
    return str_replace('\\', '/', substr($path, strlen((string) realpath($tsconfigFixtureDir)) + 1));
};
$deadAssetAnalysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
metadata.debug && require('./debug-preview.js');
false && require('./dead-debug-preview.js');
true || import('./dead-preview-chunk.js');
true ?? require('./dead-nullish-preview.js');
false ? require('./dead-legacy-preview.js') : require('./live-legacy-preview.js');
true ? import('./live-preview-chunk.js') : import('./dead-preview-fallback.js');
false ? 0 : require.resolve('./live-preview-worker.js');
JS);
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
$classFieldsAssignLegacyLowered = (new TypeScriptModuleLowerer())->lower($classFieldsAssignTypeScriptSource, false, targetYear: 2021);
$privateStaticCacheLegacyLowered = (new TypeScriptModuleLowerer())->lower($privateStaticCacheTypeScriptSource, false, targetYear: 2021);
$defaultStaticControllerLegacyLowered = (new TypeScriptModuleLowerer())->lower($defaultStaticControllerTypeScriptSource, false, targetYear: 2021);
$classExpressionControllerLegacyLowered = (new TypeScriptModuleLowerer())->lower($classExpressionControllerTypeScriptSource, false, targetYear: 2021);
$decoratedClassExpressionControllerLegacyLowered = (new TypeScriptModuleLowerer())->lower($decoratedClassExpressionControllerTypeScriptSource, false, targetYear: 2021);
$computedClassFieldsLowered = (new TypeScriptModuleLowerer())->lower($computedClassFieldsTypeScriptSource, false);
$computedSuperLowered = (new TypeScriptModuleLowerer())->lower($computedSuperTypeScriptSource, false);
$conditionalSuperLowered = (new TypeScriptModuleLowerer())->lower($conditionalSuperTypeScriptSource, false);
$lazySuperLowered = (new TypeScriptModuleLowerer())->lower($lazySuperTypeScriptSource, false);
$commaSuperLowered = (new TypeScriptModuleLowerer())->lower($commaSuperTypeScriptSource, false);
$returnSuperLowered = (new TypeScriptModuleLowerer())->lower($returnSuperTypeScriptSource, false);
$controlSuperLowered = (new TypeScriptModuleLowerer())->lower($controlSuperTypeScriptSource, false);
$privateSettingsLowered = (new TypeScriptModuleLowerer())->lower($privateSettingsTypeScriptSource, false);
$privateAccessorLowered = (new TypeScriptModuleLowerer())->lower($privateAccessorTypeScriptSource, false);
$autoAccessorLowered = (new TypeScriptModuleLowerer())->lower($autoAccessorTypeScriptSource);
$decoratedControllerLowered = (new TypeScriptModuleLowerer())->lower($decoratedControllerTypeScriptSource, false, targetYear: 2021);
$decoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($decoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$defaultDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($defaultDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$methodDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($methodDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$methodDecoratorConstructorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($methodDecoratorConstructorLegacyControllerTypeScriptSource, lowerDecorators: true);
$derivedMethodDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($derivedMethodDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$mixedDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($mixedDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$defaultMixedDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($defaultMixedDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$staticMethodDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($staticMethodDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$staticFieldDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($staticFieldDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$privateFieldDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($privateFieldDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$privateAccessorDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($privateAccessorDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$privateGetterDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($privateGetterDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$accessorDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($accessorDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$computedFieldDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($computedFieldDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$computedMethodDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($computedMethodDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$computedAccessorDecoratorLegacyControllerLowered = (new TypeScriptModuleLowerer())->lower($computedAccessorDecoratorLegacyControllerTypeScriptSource, lowerDecorators: true);
$usingDisposableLowered = (new TypeScriptModuleLowerer())->lower($usingDisposableTypeScriptSource);
$usingDisposableLegacyLowered = (new TypeScriptModuleLowerer())->lower($usingDisposableTypeScriptSource, lowerUsingDeclarations: true);
$usingImportHoistLegacyLowered = (new TypeScriptModuleLowerer())->lower($usingImportHoistTypeScriptSource, lowerUsingDeclarations: true);
$usingExportLocalLegacyLowered = (new TypeScriptModuleLowerer())->lower($usingExportLocalTypeScriptSource, lowerUsingDeclarations: true);
$usingDestructuredExportLegacyLowered = (new TypeScriptModuleLowerer())->lower($usingDestructuredExportTypeScriptSource, lowerUsingDeclarations: true);
$usingExportFunctionLegacyLowered = (new TypeScriptModuleLowerer())->lower($usingExportFunctionTypeScriptSource, lowerUsingDeclarations: true);
$usingDefaultControllerLegacyLowered = (new TypeScriptModuleLowerer())->lower($usingDefaultControllerTypeScriptSource, lowerUsingDeclarations: true);
$usingLocalControllerLegacyLowered = (new TypeScriptModuleLowerer())->lower($usingLocalControllerTypeScriptSource, lowerUsingDeclarations: true);
$functionUsingDisposableLowered = (new TypeScriptModuleLowerer())->lower($functionUsingDisposableTypeScriptSource);
$functionUsingDisposableLegacyLowered = (new TypeScriptModuleLowerer())->lower($functionUsingDisposableTypeScriptSource, lowerUsingDeclarations: true);
$blockUsingDisposableLegacyLowered = (new TypeScriptModuleLowerer())->lower($blockUsingDisposableTypeScriptSource, lowerUsingDeclarations: true);
$forUsingAssetsLowered = (new TypeScriptModuleLowerer())->lower($forUsingAssetsTypeScriptSource);
$forUsingAssetsLegacyLowered = (new TypeScriptModuleLowerer())->lower($forUsingAssetsTypeScriptSource, lowerUsingDeclarations: true);
$forUsingHelperCollisionLegacyLowered = (new TypeScriptModuleLowerer())->lower($forUsingHelperCollisionTypeScriptSource, lowerUsingDeclarations: true);
$switchUsingAssetsLegacyLowered = (new TypeScriptModuleLowerer())->lower($switchUsingAssetsTypeScriptSource, lowerUsingDeclarations: true);
$whileUsingAssetsLegacyLowered = (new TypeScriptModuleLowerer())->lower($whileUsingAssetsTypeScriptSource, lowerUsingDeclarations: true);
$asyncGeneratorFunctionAssetsLegacyLowered = (new TypeScriptModuleLowerer())->lower($asyncGeneratorFunctionAssetsTypeScriptSource, lowerUsingDeclarations: true, lowerAsyncGenerators: true);
$defaultAsyncGeneratorAssetsLegacyLowered = (new TypeScriptModuleLowerer())->lower($defaultAsyncGeneratorAssetsTypeScriptSource, lowerUsingDeclarations: true, lowerAsyncGenerators: true);
$exportedAsyncGeneratorConstantLegacyLowered = (new TypeScriptModuleLowerer())->lower($exportedAsyncGeneratorConstantTypeScriptSource, lowerUsingDeclarations: true, lowerAsyncGenerators: true);
$asyncGeneratorRegistryAssetsLegacyLowered = (new TypeScriptModuleLowerer())->lower($asyncGeneratorRegistryAssetsTypeScriptSource, lowerUsingDeclarations: true, lowerAsyncGenerators: true);
$asyncGeneratorHelperCollisionLegacyLowered = (new TypeScriptModuleLowerer())->lower($asyncGeneratorHelperCollisionTypeScriptSource, lowerUsingDeclarations: true, lowerAsyncGenerators: true);
$asyncGeneratorAssetsLegacyLowered = (new TypeScriptModuleLowerer())->lower($asyncGeneratorAssetsTypeScriptSource, lowerUsingDeclarations: true);
$objectAsyncGeneratorAssetsLegacyLowered = (new TypeScriptModuleLowerer())->lower($objectAsyncGeneratorAssetsTypeScriptSource, lowerUsingDeclarations: true);
$namespaceExportLowered = (new TypeScriptNamespaceLowerer())->lower($namespaceExportTypeScriptSource);
$namespaceRuntimeLowered = (new TypeScriptNamespaceLowerer())->lower($namespaceRuntimeTypeScriptSource);
$nestedNamespaceEnumLowered = (new TypeScriptNamespaceLowerer())->lower($nestedNamespaceEnumTypeScriptSource);
$dotNamespaceLowered = (new TypeScriptNamespaceLowerer())->lower($dotNamespaceTypeScriptSource);
$destructuredNamespaceLowered = (new TypeScriptNamespaceLowerer())->lower($destructuredNamespaceTypeScriptSource);
$functionNamespaceLowered = (new TypeScriptNamespaceLowerer())->lower($functionNamespaceTypeScriptSource);
$namespaceUsingPreviewLowered = (new TypeScriptNamespaceLowerer())->lower($namespaceUsingPreviewTypeScriptSource);
$namespaceUsingHelperCollisionLowered = (new TypeScriptNamespaceLowerer())->lower($namespaceUsingHelperCollisionTypeScriptSource);
$namespaceAwaitUsingPreviewLowered = (new TypeScriptNamespaceLowerer())->lower($namespaceAwaitUsingPreviewTypeScriptSource);
$switchCaseUsingDiagnostic = 'no';
try {
    (new TypeScriptModuleLowerer())->lower(<<<'TS'
switch (metadata.viewScript) {
  case "view":
    using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);
    queueAsset(previewAsset.url);
}
TS);
} catch (InvalidArgumentException) {
    $switchCaseUsingDiagnostic = 'yes';
}
$decoratorBoundaryDiagnostic = 'no';
try {
    (new TypeScriptModuleLowerer())->lower(<<<'TS'
@blockController<BlockConfiguration>(metadata)
function registerBlock() {
  wp.blocks.registerBlockType(metadata.name, metadata);
}
TS);
} catch (InvalidArgumentException) {
    $decoratorBoundaryDiagnostic = 'yes';
}
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
printf("WordPress TypeScript ES2021 static field bytes: %d\n", strlen($classFieldsAssignLegacyLowered));
printf("WordPress TypeScript private static cache bytes: %d\n", strlen($privateStaticCacheLegacyLowered));
printf("WordPress TypeScript default static controller bytes: %d\n", strlen($defaultStaticControllerLegacyLowered));
printf("WordPress TypeScript class expression controller bytes: %d\n", strlen($classExpressionControllerLegacyLowered));
printf("WordPress TypeScript decorated class expression controller bytes: %d\n", strlen($decoratedClassExpressionControllerLegacyLowered));
printf("WordPress TypeScript computed class field bytes: %d\n", strlen($computedClassFieldsLowered));
printf("WordPress TypeScript computed super controller bytes: %d\n", strlen($computedSuperLowered));
printf("WordPress TypeScript conditional super controller bytes: %d\n", strlen($conditionalSuperLowered));
printf("WordPress TypeScript lazy super controller bytes: %d\n", strlen($lazySuperLowered));
printf("WordPress TypeScript comma super controller bytes: %d\n", strlen($commaSuperLowered));
printf("WordPress TypeScript return super controller bytes: %d\n", strlen($returnSuperLowered));
printf("WordPress TypeScript control super controller bytes: %d\n", strlen($controlSuperLowered));
printf("WordPress TypeScript private settings controller bytes: %d\n", strlen($privateSettingsLowered));
printf("WordPress TypeScript private accessor controller bytes: %d\n", strlen($privateAccessorLowered));
printf("WordPress TypeScript auto accessor controller bytes: %d\n", strlen($autoAccessorLowered));
printf("WordPress TypeScript decorated controller bytes: %d\n", strlen($decoratedControllerLowered));
printf("WordPress TypeScript legacy decorator helper bytes: %d\n", strlen($decoratorLegacyControllerLowered));
printf("WordPress TypeScript default legacy decorator helper bytes: %d\n", strlen($defaultDecoratorLegacyControllerLowered));
printf("WordPress TypeScript method legacy decorator helper bytes: %d\n", strlen($methodDecoratorLegacyControllerLowered));
printf("WordPress TypeScript method constructor decorator helper bytes: %d\n", strlen($methodDecoratorConstructorLegacyControllerLowered));
printf("WordPress TypeScript derived method decorator helper bytes: %d\n", strlen($derivedMethodDecoratorLegacyControllerLowered));
printf("WordPress TypeScript mixed decorator helper bytes: %d\n", strlen($mixedDecoratorLegacyControllerLowered));
printf("WordPress TypeScript default mixed decorator helper bytes: %d\n", strlen($defaultMixedDecoratorLegacyControllerLowered));
printf("WordPress TypeScript static method legacy decorator helper bytes: %d\n", strlen($staticMethodDecoratorLegacyControllerLowered));
printf("WordPress TypeScript static field legacy decorator helper bytes: %d\n", strlen($staticFieldDecoratorLegacyControllerLowered));
printf("WordPress TypeScript private field legacy decorator helper bytes: %d\n", strlen($privateFieldDecoratorLegacyControllerLowered));
printf("WordPress TypeScript private accessor legacy decorator helper bytes: %d\n", strlen($privateAccessorDecoratorLegacyControllerLowered));
printf("WordPress TypeScript private getter legacy decorator helper bytes: %d\n", strlen($privateGetterDecoratorLegacyControllerLowered));
printf("WordPress TypeScript accessor legacy decorator helper bytes: %d\n", strlen($accessorDecoratorLegacyControllerLowered));
printf("WordPress TypeScript computed field legacy decorator helper bytes: %d\n", strlen($computedFieldDecoratorLegacyControllerLowered));
printf("WordPress TypeScript computed method legacy decorator helper bytes: %d\n", strlen($computedMethodDecoratorLegacyControllerLowered));
printf("WordPress TypeScript computed accessor legacy decorator helper bytes: %d\n", strlen($computedAccessorDecoratorLegacyControllerLowered));
printf("WordPress TypeScript using disposable asset bytes: %d\n", strlen($usingDisposableLowered));
printf("WordPress TypeScript legacy using helper bytes: %d\n", strlen($usingDisposableLegacyLowered));
printf("WordPress TypeScript imported using helper bytes: %d\n", strlen($usingImportHoistLegacyLowered));
printf("WordPress TypeScript exported using helper bytes: %d\n", strlen($usingExportLocalLegacyLowered));
printf("WordPress TypeScript destructured exported using helper bytes: %d\n", strlen($usingDestructuredExportLegacyLowered));
printf("WordPress TypeScript exported function using helper bytes: %d\n", strlen($usingExportFunctionLegacyLowered));
printf("WordPress TypeScript default controller using helper bytes: %d\n", strlen($usingDefaultControllerLegacyLowered));
printf("WordPress TypeScript local controller using helper bytes: %d\n", strlen($usingLocalControllerLegacyLowered));
printf("WordPress TypeScript function scoped using bytes: %d\n", strlen($functionUsingDisposableLowered));
printf("WordPress TypeScript function scoped using helper bytes: %d\n", strlen($functionUsingDisposableLegacyLowered));
printf("WordPress TypeScript block scoped using helper bytes: %d\n", strlen($blockUsingDisposableLegacyLowered));
printf("WordPress TypeScript for using asset loop bytes: %d\n", strlen($forUsingAssetsLowered));
printf("WordPress TypeScript for using asset helper bytes: %d\n", strlen($forUsingAssetsLegacyLowered));
printf("WordPress TypeScript for using collision helper bytes: %d\n", strlen($forUsingHelperCollisionLegacyLowered));
printf("WordPress TypeScript switch using asset helper bytes: %d\n", strlen($switchUsingAssetsLegacyLowered));
printf("WordPress TypeScript while using asset helper bytes: %d\n", strlen($whileUsingAssetsLegacyLowered));
printf("WordPress TypeScript async generator function helper bytes: %d\n", strlen($asyncGeneratorFunctionAssetsLegacyLowered));
printf("WordPress TypeScript default async generator helper bytes: %d\n", strlen($defaultAsyncGeneratorAssetsLegacyLowered));
printf("WordPress TypeScript exported async generator constant helper bytes: %d\n", strlen($exportedAsyncGeneratorConstantLegacyLowered));
printf("WordPress TypeScript async generator registry helper bytes: %d\n", strlen($asyncGeneratorRegistryAssetsLegacyLowered));
printf("WordPress TypeScript async generator helper collision bytes: %d\n", strlen($asyncGeneratorHelperCollisionLegacyLowered));
printf("WordPress TypeScript async generator asset helper bytes: %d\n", strlen($asyncGeneratorAssetsLegacyLowered));
printf("WordPress TypeScript object async generator asset helper bytes: %d\n", strlen($objectAsyncGeneratorAssetsLegacyLowered));
printf("WordPress TypeScript lowered namespace bytes: %d\n", strlen($namespaceLowered));
printf("WordPress TypeScript namespace export bytes: %d\n", strlen($namespaceExportLowered));
printf("WordPress TypeScript namespace runtime bytes: %d\n", strlen($namespaceRuntimeLowered));
printf("WordPress TypeScript nested namespace enum bytes: %d\n", strlen($nestedNamespaceEnumLowered));
printf("WordPress TypeScript dot namespace bytes: %d\n", strlen($dotNamespaceLowered));
printf("WordPress TypeScript destructured namespace bytes: %d\n", strlen($destructuredNamespaceLowered));
printf("WordPress TypeScript function namespace bytes: %d\n", strlen($functionNamespaceLowered));
printf("WordPress TypeScript namespace using helper bytes: %d\n", strlen($namespaceUsingPreviewLowered));
printf("WordPress TypeScript namespace using collision helper bytes: %d\n", strlen($namespaceUsingHelperCollisionLowered));
printf("WordPress TypeScript namespace async using bytes: %d\n", strlen($namespaceAwaitUsingPreviewLowered));
printf("WordPress TypeScript switch case using diagnostic: %s\n", $switchCaseUsingDiagnostic);
printf("WordPress TypeScript decorator boundary diagnostic: %s\n", $decoratorBoundaryDiagnostic);
printf("JSON metadata imports: %d\n", count(array_filter(
    $analysis->relativeImports(),
    static fn ($import): bool => $import->hasJsonTypeAttribute()
)));
printf("Uses import.meta: %s\n", $analysis->hasImportMeta() ? 'yes' : 'no');
printf("Relative module asset references: %d\n", count(array_filter(
    $analysis->assetReferences,
    static fn ($reference): bool => $reference->isRelative()
)));
printf("WordPress no-substitution template literal sources: %s\n", (
    count($templateLiteralAnalysis->imports) === 5
    && ($templateLiteralAnalysis->imports[0]->source ?? null) === './preview.js'
    && ($templateLiteralAnalysis->imports[1]->source ?? null) === './block.json'
    && ($templateLiteralAnalysis->imports[2]->source ?? null) === './legacy-preview.js'
    && ($templateLiteralAnalysis->imports[3]->source ?? null) === './legacy-editor.js'
    && ($templateLiteralAnalysis->imports[4]->source ?? null) === './preview-worker.js'
    && ($templateLiteralAnalysis->assetReferences[0]->source ?? null) === './preview-worker.js'
) ? 'yes' : 'no');
printf("WordPress conditional CommonJS template sources: %s\n", (
    array_map(static fn ($import): string => $import->source, $templateLiteralAnalysis->imports) === [
        './preview.js',
        './block.json',
        './legacy-preview.js',
        './legacy-editor.js',
        './preview-worker.js',
    ]
) ? 'yes' : 'no');
printf("WordPress dead expression asset pruning: %s\n", (
    array_map(static fn ($import): string => $import->source, $deadAssetAnalysis->imports) === [
        './debug-preview.js',
        './live-legacy-preview.js',
        './live-preview-chunk.js',
        './live-preview-worker.js',
    ]
    && array_map(static fn ($import): string => $import->kind, $deadAssetAnalysis->imports) === [
        'commonjs-require',
        'commonjs-require',
        'dynamic',
        'commonjs-require-resolve',
    ]
) ? 'yes' : 'no');
printf("WordPress conditional dynamic import sources: %s\n", (
    array_map(static fn ($import): string => $import->source, $conditionalDynamicImportAnalysis->imports) === [
        './view-preview.js',
        './editor-preview.js',
        './variation-preview.js',
    ]
) ? 'yes' : 'no');
printf("WordPress glob asset sources: %s\n", (
    array_map(static fn ($import): string => $import->kind, $globAssetAnalysis->imports) === [
        'commonjs-require-glob',
        'dynamic-glob',
        'commonjs-require-glob',
    ]
    && array_map(static fn ($import): string => $import->source, $globAssetAnalysis->imports) === [
        './blocks/**/*/view.js',
        './variations/**/*.js',
        './styles/**/*.css',
    ]
) ? 'yes' : 'no');
printf("WordPress glob fixture matches: %s\n", (
    array_map(static fn ($match): string => $match->key, $globFixtureMatches) === [
        './blocks/card/view.js',
        './blocks/gallery/view.js',
        './blocks/nested/card/view.js',
        './variations/blue.js',
        './variations/seasonal/sale.js',
        './styles/admin.css',
        './styles/front.css',
        './styles/nested/print.css',
    ]
) ? 'yes' : 'no');
printf("WordPress package resolver browser fields: %s\n", (
    array_combine(
        array_map(static fn ($resolution): string => $resolution->import->source, $packageBrowserResolutions),
        array_map(static fn ($resolution): string => $normalizePackageFixturePath($resolution->path), $packageBrowserResolutions),
    ) === [
        '@wordpress/interactivity' => 'node_modules/@wordpress/interactivity/build-module/index.js',
        'port-libs-card-runtime/helper' => 'node_modules/port-libs-card-runtime/helper.js',
        'wordpress-package-assets-fixture' => 'src/entry.js',
        'wordpress-package-assets-fixture/self-export' => 'src/self-export.js',
        'exports-map-pkg' => 'node_modules/exports-map-pkg/browser.js',
        'exports-map-pkg/features/card' => 'node_modules/exports-map-pkg/features/card.js',
        'conditional-array-pkg' => 'node_modules/conditional-array-pkg/browser.js',
        'conditional-array-pkg/custom' => 'node_modules/conditional-array-pkg/default.js',
        'browser-map-pkg' => 'node_modules/browser-map-pkg/browser-module.js',
        'browser-map-pkg/feature' => 'node_modules/browser-map-pkg/feature-browser.js',
        'containing-browser-map-pkg' => 'node_modules/containing-browser-map-pkg/main.js',
        '#view' => 'src/internal/view.js',
        '#conditional' => 'src/internal/browser.js',
        '#/blocks/card' => 'src/internal/blocks/card.js',
        '#pkg-runtime' => 'node_modules/exports-map-pkg/features/card.js',
        'port-libs-card-runtime' => 'node_modules/port-libs-card-runtime/dist/browser.js',
        'server-only-package' => 'node_modules/server-only-package/esm.js',
        'bad-main-pkg' => 'node_modules/bad-main-pkg/index.js',
        'exports-map-pkg/preview' => 'node_modules/exports-map-pkg/preview.cjs',
        'exports-map-pkg/legacy/admin' => 'node_modules/exports-map-pkg/legacy/admin.js',
        '#require-preview' => 'src/internal/preview.cjs',
    ]
) ? 'yes' : 'no');
printf("WordPress package resolver node fields: %s\n", (
    array_combine(
        array_map(static fn ($resolution): string => $resolution->import->source, $packageNodeResolutions),
        array_map(static fn ($resolution): string => $normalizePackageFixturePath($resolution->path), $packageNodeResolutions),
    ) === [
        '@wordpress/interactivity' => 'node_modules/@wordpress/interactivity/build/index.js',
        'port-libs-card-runtime/helper' => 'node_modules/port-libs-card-runtime/helper.js',
        'wordpress-package-assets-fixture' => 'src/entry.js',
        'wordpress-package-assets-fixture/self-export' => 'src/self-export.js',
        'exports-map-pkg' => 'node_modules/exports-map-pkg/node.js',
        'exports-map-pkg/features/card' => 'node_modules/exports-map-pkg/features/card.js',
        'conditional-array-pkg' => 'node_modules/conditional-array-pkg/default.js',
        'conditional-array-pkg/custom' => 'node_modules/conditional-array-pkg/default.js',
        'browser-map-pkg' => 'node_modules/browser-map-pkg/main.js',
        'browser-map-pkg/feature' => 'node_modules/browser-map-pkg/feature.js',
        'containing-browser-map-pkg' => 'node_modules/containing-browser-map-pkg/main.js',
        '#view' => 'src/internal/view.js',
        '#conditional' => 'src/internal/node.js',
        '#/blocks/card' => 'src/internal/blocks/card.js',
        '#pkg-runtime' => 'node_modules/exports-map-pkg/features/card.js',
        'port-libs-card-runtime' => 'node_modules/port-libs-card-runtime/dist/main.cjs',
        'server-only-package' => 'node_modules/server-only-package/server.cjs',
        'bad-main-pkg' => 'node_modules/bad-main-pkg/index.js',
        'exports-map-pkg/preview' => 'node_modules/exports-map-pkg/preview.cjs',
        'exports-map-pkg/legacy/admin' => 'node_modules/exports-map-pkg/legacy/admin.js',
        '#require-preview' => 'src/internal/preview.cjs',
    ]
) ? 'yes' : 'no');
printf("WordPress package exports map resolution: %s\n", (
    $packagePreviewImportResolution !== null
    && $normalizePackageFixturePath($packagePreviewImportResolution->path) === 'node_modules/exports-map-pkg/preview.mjs'
    && $packagePreviewImportResolution->mainField === 'exports'
) ? 'yes' : 'no');
printf("WordPress package exports condition arrays: %s\n", (
    $packageConditionalArrayResolution !== null
    && $normalizePackageFixturePath($packageConditionalArrayResolution->path) === 'node_modules/conditional-array-pkg/browser.js'
    && $packageCustomConditionResolution !== null
    && $normalizePackageFixturePath($packageCustomConditionResolution->path) === 'node_modules/conditional-array-pkg/wordpress.js'
) ? 'yes' : 'no');
printf("WordPress package browser object maps: %s\n", (
    $packageBrowserMapDisabledResolution === null
    && in_array('browser', array_map(static fn ($resolution): ?string => $resolution->mainField, $packageBrowserResolutions), true)
) ? 'yes' : 'no');
printf("WordPress containing package browser maps: %s\n", (
    $containingBrowserLocalResolution !== null
    && $normalizePackageFixturePath($containingBrowserLocalResolution->path) === 'node_modules/containing-browser-map-pkg/node-pkg-browser.js'
    && $containingBrowserPackageResolution !== null
    && $normalizePackageFixturePath($containingBrowserPackageResolution->path) === 'node_modules/node-pkg-browser-package/index.js'
    && $containingBrowserDisabledResolution === null
) ? 'yes' : 'no');
printf("WordPress node builtin browser maps: %s\n", (
    $containingBrowserBuiltinResolution !== null
    && $normalizePackageFixturePath($containingBrowserBuiltinResolution->path) === 'node_modules/containing-browser-map-pkg/path-browser.js'
    && $containingBrowserNodeBuiltinResolution !== null
    && $normalizePackageFixturePath($containingBrowserNodeBuiltinResolution->path) === 'node_modules/containing-browser-map-pkg/path-browser.js'
    && $containingBrowserBuiltinDisabledResolution === null
    && $nodeBuiltinResolution !== null
    && $nodeBuiltinResolution->external
    && $nodeBuiltinResolution->mainField === 'node-builtin'
    && $nodePrefixBuiltinResolution !== null
    && $nodePrefixBuiltinResolution->external
    && $nodePrefixBuiltinResolution->mainField === 'node-builtin'
) ? 'yes' : 'no');
printf("WordPress node builtin external records: %s\n", (
    $nodeBuiltinResolution !== null
    && $nodeBuiltinResolution->external
    && $nodeBuiltinResolution->packageName === 'path'
    && $nodePrefixBuiltinResolution !== null
    && $nodePrefixBuiltinResolution->external
    && $nodePrefixBuiltinResolution->packageName === 'path'
    && $nodeFsPromisesBuiltinResolution !== null
    && $nodeFsPromisesBuiltinResolution->external
    && $nodeFsPromisesBuiltinResolution->packageName === 'fs'
    && $nodeFsPromisesBuiltinResolution->subpath === './promises'
    && $unshimmedBrowserNodePrefixResolution === null
    && $unshimmedNeutralNodePrefixResolution === null
) ? 'yes' : 'no');
printf("WordPress node builtin import-record propagation: %s\n", (
    array_combine(
        array_map(static fn ($record): string => $record->source, $nodeImportRecords),
        array_map(static fn ($record): bool => $record->external, $nodeImportRecords),
    ) === [
        'path' => true,
        'fs/promises' => true,
        'node:crypto' => true,
        'port-libs-card-runtime' => false,
    ]
    && array_combine(
        array_map(static fn ($record): string => $record->source, $nodeImportRecords),
        array_map(static fn ($record): ?string => $record->mainField, $nodeImportRecords),
    ) === [
        'path' => 'node-builtin',
        'fs/promises' => 'node-builtin',
        'node:crypto' => 'node-builtin',
        'port-libs-card-runtime' => 'main',
    ]
) ? 'yes' : 'no');
printf("WordPress browser bundler graph assembly: %s\n", (
    isset($browserBundlerGraph->modules[(string) realpath($packageEntryDir . '/entry.js')])
    && isset($browserBundlerGraph->modules[(string) realpath($packageFixtureDir . '/node_modules/@wordpress/interactivity/build-module/index.js')])
    && isset($browserBundlerGraph->modules[(string) realpath($packageEntryDir . '/internal/view.js')])
    && $browserBundlerGraph->externalEdges === []
    && array_map(static fn ($edge): string => $edge->source, $browserBundlerGraph->missingEdges) === ['node-pkg-disabled']
) ? 'yes' : 'no');
printf("WordPress node bundler graph externals: %s\n", (
    array_map(static fn ($edge): string => $edge->source, $nodeBundlerGraph->externalEdges) === ['path', 'node:crypto']
    && isset($nodeBundlerGraph->modules[(string) realpath($packageEntryDir . '/local-preview.js')])
) ? 'yes' : 'no');
printf("WordPress loader-aware graph assets: %s\n", (
    $loaderGraphEdges['./block.css']->loader === 'css'
    && $loaderGraphEdges['./block.json']->loader === 'json'
    && isset($loaderBundlerGraph->modules[(string) realpath($packageEntryDir . '/local-preview.js')])
    && !isset($loaderBundlerGraph->modules[(string) realpath($packageEntryDir . '/block.css')])
    && !isset($loaderBundlerGraph->modules[(string) realpath($packageEntryDir . '/block.json')])
) ? 'yes' : 'no');
printf("WordPress unsupported loader diagnostics: %s\n", (
    array_map(static fn ($edge): string => $edge->source, $unsupportedLoaderGraph->unsupportedEdges) === ['./asset.bin']
    && $unsupportedLoaderGraph->unsupportedEdges[0]->loader === null
    && isset($unsupportedLoaderGraph->modules[(string) realpath($packageEntryDir . '/local-preview.js')])
    && !isset($unsupportedLoaderGraph->modules[(string) realpath($packageEntryDir . '/asset.bin')])
) ? 'yes' : 'no');
printf("WordPress graph metafile diagnostics: %s\n", (
    $unsupportedLoaderMetafile['entry'] === 'src/unsupported-loader-entry.js'
    && isset($unsupportedLoaderMetafile['inputs']['src/unsupported-loader-entry.js'])
    && isset($unsupportedLoaderMetafile['inputs']['src/local-preview.js'])
    && !isset($unsupportedLoaderMetafile['inputs']['src/asset.bin'])
    && ($unsupportedLoaderMetafile['diagnostics']['unsupported'][0]['path'] ?? null) === './asset.bin'
    && ($unsupportedLoaderMetafile['diagnostics']['unsupported'][0]['resolved'] ?? null) === 'src/asset.bin'
) ? 'yes' : 'no');
printf("WordPress bounded JS output bytes: %s\n", (
    $loaderBundlerOutput['entry'] === 'src/loader-entry.js'
    && $loaderBundlerOutput['output']['path'] === 'block-view.js'
    && $loaderBundlerOutput['output']['bytes'] > 0
    && str_contains($loaderBundlerOutput['output']['contents'], "// src/loader-entry.js\n")
    && str_contains($loaderBundlerOutput['output']['contents'], "// src/local-preview.js\n")
    && !str_contains($loaderBundlerOutput['output']['contents'], "import './local-preview.js';")
    && str_contains($loaderBundlerOutput['output']['contents'], "import './src/block.css';")
    && ($loaderBundlerOutput['inputs']['src/loader-entry.js']['importsRemoved'] ?? null) === 1
    && isset($loaderBundlerOutput['inputs']['src/local-preview.js'])
    && !isset($loaderBundlerOutput['inputs']['src/block.css'])
    && ($unsupportedLoaderOutput['diagnostics']['unsupported'][0]['path'] ?? null) === './asset.bin'
) ? 'yes' : 'no');
printf("WordPress terminal import path rewrites: %s\n", (
    ($loaderBundlerOutput['inputs']['src/loader-entry.js']['importsRewritten'] ?? null) === 2
    && ($loaderBundlerOutput['inputs']['src/loader-entry.js']['rewrites'][0]['to'] ?? null) === './src/block.css'
    && ($loaderBundlerOutput['inputs']['src/loader-entry.js']['rewrites'][1]['to'] ?? null) === './src/block.json'
    && str_contains($loaderBundlerOutput['output']['contents'], "import metadata from './src/block.json' with { type: 'json' };")
) ? 'yes' : 'no');
printf("WordPress static output import elision: %s\n", (
    ($staticImportOutput['inputs']['src/output-static-entry.js']['importsRemoved'] ?? null) === 2
    && ($staticImportOutput['inputs']['src/output-static-entry.js']['importsRewritten'] ?? null) === 2
    && !str_contains($staticImportOutput['output']['contents'], "import './local-preview.js';")
    && !str_contains($staticImportOutput['output']['contents'], "import { preview } from './local-preview.js';")
    && str_contains($staticImportOutput['output']['contents'], "import '../src/block.css';")
    && str_contains($staticImportOutput['output']['contents'], "import metadata from '../src/block.json' with { type: 'json' };")
) ? 'yes' : 'no');
printf("WordPress bundled re-export clauses: %s\n", (
    ($reExportOutput['inputs']['src/output-reexport-entry.js']['exportsRewritten'] ?? null) === 2
    && str_contains($reExportOutput['output']['contents'], "export { preview };")
    && str_contains($reExportOutput['output']['contents'], "export { runtime as helperRuntime };")
    && !str_contains($reExportOutput['output']['contents'], "export { preview } from './local-preview.js';")
    && !str_contains($reExportOutput['output']['contents'], "export { runtime as helperRuntime } from 'port-libs-card-runtime/helper';")
    && str_contains($reExportOutput['output']['contents'], "import '../src/block.css';")
) ? 'yes' : 'no');
printf("WordPress node output external imports: %s\n", (
    ($nodeBundlerOutput['inputs']['src/node-entry.js']['importsExternal'] ?? null) === 2
    && ($nodeBundlerOutput['inputs']['src/node-entry.js']['externalImports'][0]['path'] ?? null) === 'path'
    && ($nodeBundlerOutput['inputs']['src/node-entry.js']['externalImports'][1]['path'] ?? null) === 'node:crypto'
    && ($nodeOutputMetafile['outputs']['node-block-view.js']['importsExternal'] ?? null) === 2
    && str_contains($nodeBundlerOutput['output']['contents'], "import path from 'path';")
    && str_contains($nodeBundlerOutput['output']['contents'], "import('node:crypto');")
) ? 'yes' : 'no');
printf("WordPress metafile output bytes: %s\n", (
    ($loaderOutputMetafile['outputs']['block-view.js']['bytes'] ?? null) === $loaderBundlerOutput['output']['bytes']
    && ($loaderOutputMetafile['outputs']['block-view.js']['importsRemoved'] ?? null) === 1
    && ($loaderOutputMetafile['outputs']['block-view.js']['inputs']['src/loader-entry.js']['bytesInOutput'] ?? null) === $loaderBundlerOutput['inputs']['src/loader-entry.js']['outputBytes']
    && ($loaderOutputMetafile['outputs']['block-view.js']['inputs']['src/loader-entry.js']['importsRemoved'] ?? null) === 1
    && isset($loaderOutputMetafile['outputs']['block-view.js']['inputs']['src/local-preview.js'])
    && !isset($loaderOutputMetafile['outputs']['block-view.js']['inputs']['src/block.css'])
) ? 'yes' : 'no');
printf("WordPress tsconfig paths aliases: %s\n", (
    array_combine(
        array_map(static fn ($resolution): string => $resolution->import->source, $tsconfigResolutions),
        array_map(static fn ($resolution): string => $normalizeTsconfigFixturePath($resolution->path), $tsconfigResolutions),
    ) === [
        '@blocks/card/view' => 'src/blocks/card/view.ts',
        '@blocks/card/style.css' => 'src/blocks/card/style.css',
        '@shared/settings' => 'src/shared/settings.ts',
        'shared-config' => 'src/shared/config.ts',
        '@theme/card' => 'src/theme/card.ts',
        'wordpress-runtime' => 'src/vendor/wordpress-runtime/index.ts',
        '/virtual/card' => 'src/virtual/card.ts',
        '@wordpress/block-runtime' => 'src/package-shared/block-runtime.ts',
        '@wordpress/package-theme/card' => 'src/package-theme/card.ts',
        '@package-shared/card' => 'src/package-shared/card.ts',
        '@preset-block/card/view' => 'src/blocks/card/view.ts',
        'wp-element' => 'src/vendor/wp-element/index.ts',
        'blocks/card/view' => 'src/blocks/card/view.ts',
        '@legacy-fallback/card' => 'src/legacy-fallback/card.ts',
    ]
) ? 'yes' : 'no');
