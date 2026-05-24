<?php

declare(strict_types=1);

use PortLibs\Esbuild\JsModuleAnalyzer;
use PortLibs\Esbuild\ModuleImport;
use PortLibs\Esbuild\TsConfigPathResolver;

$fixtureRoot = dirname(__DIR__) . '/fixtures/wordpress-tsconfig-assets';
$entryDir = $fixtureRoot . '/src';
$entrySource = (string) file_get_contents($entryDir . '/entry.ts');
$entryAnalysis = (new JsModuleAnalyzer())->analyze($entrySource);
$normalizeFixturePath = static function (string $path) use ($fixtureRoot): string {
    return str_replace('\\', '/', substr($path, strlen((string) realpath($fixtureRoot)) + 1));
};

return [
    'maps upstream tsconfig baseUrl paths exact star and fallback targets' => static function (TestRunner $t) use ($entryAnalysis, $entryDir, $normalizeFixturePath): void {
        $resolutions = (new TsConfigPathResolver())->resolve($entryAnalysis, $entryDir);

        $t->same([
            '@blocks/card/view' => 'src/blocks/card/view.ts',
            '@blocks/card/style.css' => 'src/blocks/card/style.css',
            '@shared/settings' => 'src/shared/settings.ts',
            'shared-config' => 'src/shared/config.ts',
            '@theme/card' => 'src/theme/card.ts',
            'wordpress-runtime' => 'src/vendor/wordpress-runtime/index.ts',
            '/virtual/card' => 'src/virtual/card.ts',
            '@wordpress/block-runtime' => 'src/package-shared/block-runtime.ts',
            '@wordpress/package-theme/card' => 'src/package-theme/card.ts',
        ], array_combine(
            array_map(static fn ($resolution): string => $resolution->import->source, $resolutions),
            array_map(static fn ($resolution): string => $normalizeFixturePath($resolution->path), $resolutions),
        ));
        $t->same(['@blocks/*', '@blocks/*', '@shared/*', 'shared-config', '@theme/*', 'wordpress-runtime', '/virtual/*', '@wordpress/block-runtime', '@wordpress/package-theme/*'], array_map(static fn ($resolution): string => $resolution->matchedPattern, $resolutions));
        $t->same(['./blocks/*', './blocks/*', './shared/*', './shared/config', './theme/*', './vendor/wordpress-runtime', './virtual/*', './package-shared/block-runtime', './package-theme/*'], array_map(static fn ($resolution): string => $resolution->targetPattern, $resolutions));
        $t->same(['tsconfig.json'], array_values(array_unique(array_map(static fn ($resolution): string => basename($resolution->tsconfigPath), $resolutions))));
        $t->same('src', basename($resolutions[0]->baseUrl));
    },
    'maps package exported tsconfig extends through node_modules' => static function (TestRunner $t) use ($entryDir, $normalizeFixturePath): void {
        $resolver = new TsConfigPathResolver();

        $blockRuntime = $resolver->resolveImport(new ModuleImport('named', '@wordpress/block-runtime', [], 0), $entryDir);
        $packageTheme = $resolver->resolveImport(new ModuleImport('named', '@wordpress/package-theme/card', [], 0), $entryDir);

        $t->same('src/package-shared/block-runtime.ts', $normalizeFixturePath($blockRuntime?->path ?? ''));
        $t->same('@wordpress/block-runtime', $blockRuntime?->matchedPattern);
        $t->same('./package-shared/block-runtime', $blockRuntime?->targetPattern);
        $t->same('tsconfig.json', basename($blockRuntime?->tsconfigPath ?? ''));
        $t->same('src/package-theme/card.ts', $normalizeFixturePath($packageTheme?->path ?? ''));
        $t->same('@wordpress/package-theme/*', $packageTheme?->matchedPattern);
    },
    'maps direct tsconfig path imports and ignores package-relative-unsafe cases' => static function (TestRunner $t) use ($entryDir, $normalizeFixturePath): void {
        $resolver = new TsConfigPathResolver();

        $t->same('src/blocks/card/view.ts', $normalizeFixturePath($resolver->resolveImport(new ModuleImport('named', '@blocks/card/view', [], 0), $entryDir)?->path ?? ''));
        $t->same('src/vendor/wordpress-runtime/index.ts', $normalizeFixturePath($resolver->resolveImport(new ModuleImport('named', 'wordpress-runtime', [], 0), $entryDir)?->path ?? ''));
        $t->same('src/package-shared/block-runtime.ts', $normalizeFixturePath($resolver->resolveImport(new ModuleImport('named', '@wordpress/block-runtime', [], 0), $entryDir)?->path ?? ''));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', './relative.js', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', '@missing/card', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', 'unsafe-runtime', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', 'http://example.test/runtime.js', [], 0), $entryDir));
    },
];
