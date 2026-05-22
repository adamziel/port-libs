<?php

declare(strict_types=1);

use PortLibs\Esbuild\JsLexer;
use PortLibs\Esbuild\JsModuleAnalyzer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-view.js');
$typeScriptSource = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-view-types.ts');
$tokens = (new JsLexer())->tokenize($source);
$analysis = (new JsModuleAnalyzer())->analyze($source);
$typeScriptAnalysis = (new JsModuleAnalyzer())->analyze($typeScriptSource);

printf("WordPress asset tokens: %d\n", count($tokens));
printf("WordPress package imports: %d\n", count($analysis->wordpressPackageImports()));
printf("WordPress TypeScript runtime imports: %d\n", count($typeScriptAnalysis->runtimeImports()));
printf("WordPress TypeScript type-only imports: %d\n", count($typeScriptAnalysis->typeOnlyImports()));
printf("JSON metadata imports: %d\n", count(array_filter(
    $analysis->relativeImports(),
    static fn ($import): bool => $import->hasJsonTypeAttribute()
)));
printf("Uses import.meta: %s\n", $analysis->hasImportMeta() ? 'yes' : 'no');
printf("Relative module asset references: %d\n", count(array_filter(
    $analysis->assetReferences,
    static fn ($reference): bool => $reference->isRelative()
)));
