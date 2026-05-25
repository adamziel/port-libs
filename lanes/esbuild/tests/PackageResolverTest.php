<?php

declare(strict_types=1);

use PortLibs\Esbuild\JsModuleAnalyzer;
use PortLibs\Esbuild\ModuleImport;
use PortLibs\Esbuild\PackageResolver;

$fixtureRoot = dirname(__DIR__) . '/fixtures/wordpress-package-assets';
$entryDir = $fixtureRoot . '/src';
$entrySource = (string) file_get_contents($entryDir . '/entry.js');
$entryAnalysis = (new JsModuleAnalyzer())->analyze($entrySource);

$normalizeFixturePath = static function (string $path) use ($fixtureRoot): string {
    return str_replace('\\', '/', substr($path, strlen((string) realpath($fixtureRoot)) + 1));
};

return [
    'maps upstream package json main field defaults for browser platform' => static function (TestRunner $t) use ($entryAnalysis, $entryDir, $normalizeFixturePath): void {
        $resolutions = (new PackageResolver('browser'))->resolve($entryAnalysis, $entryDir);

        $t->same([
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
        ], array_combine(
            array_map(static fn ($resolution): string => $resolution->import->source, $resolutions),
            array_map(static fn ($resolution): string => $normalizeFixturePath($resolution->path), $resolutions),
        ));
        $t->same([
            'module',
            null,
            'exports',
            'exports',
            'exports',
            'exports',
            'exports',
            'exports',
            'browser',
            'browser',
            'main',
            'imports',
            'imports',
            'imports',
            'imports',
            'browser',
            'module',
            null,
            'exports',
            'exports',
            'imports',
        ], array_map(static fn ($resolution): ?string => $resolution->mainField, $resolutions));
        $t->same('@wordpress/interactivity', $resolutions[0]->packageName);
        $t->same('port-libs-card-runtime', $resolutions[1]->packageName);
        $t->same('./helper', $resolutions[1]->subpath);
    },
    'maps upstream package json main field defaults for node and neutral platforms' => static function (TestRunner $t) use ($entryAnalysis, $entryDir, $normalizeFixturePath): void {
        $nodeResolutions = (new PackageResolver('node'))->resolve($entryAnalysis, $entryDir);
        $neutralResolutions = (new PackageResolver('neutral'))->resolve($entryAnalysis, $entryDir);
        $customNeutralResolutions = (new PackageResolver('neutral', ['module', 'main']))->resolve($entryAnalysis, $entryDir);

        $t->same([
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
        ], array_combine(
            array_map(static fn ($resolution): string => $resolution->import->source, $nodeResolutions),
            array_map(static fn ($resolution): string => $normalizeFixturePath($resolution->path), $nodeResolutions),
        ));
        $t->same([
            'port-libs-card-runtime/helper' => 'node_modules/port-libs-card-runtime/helper.js',
            'wordpress-package-assets-fixture' => 'src/entry.js',
            'wordpress-package-assets-fixture/self-export' => 'src/self-export.js',
            'exports-map-pkg' => 'node_modules/exports-map-pkg/default.js',
            'exports-map-pkg/features/card' => 'node_modules/exports-map-pkg/features/card.js',
            'conditional-array-pkg' => 'node_modules/conditional-array-pkg/default.js',
            'conditional-array-pkg/custom' => 'node_modules/conditional-array-pkg/default.js',
            'browser-map-pkg/feature' => 'node_modules/browser-map-pkg/feature.js',
            '#view' => 'src/internal/view.js',
            '#conditional' => 'src/internal/default.js',
            '#/blocks/card' => 'src/internal/blocks/card.js',
            '#pkg-runtime' => 'node_modules/exports-map-pkg/features/card.js',
            'bad-main-pkg' => 'node_modules/bad-main-pkg/index.js',
            'exports-map-pkg/preview' => 'node_modules/exports-map-pkg/preview.cjs',
            'exports-map-pkg/legacy/admin' => 'node_modules/exports-map-pkg/legacy/admin.js',
            '#require-preview' => 'src/internal/preview.cjs',
        ], array_combine(
            array_map(static fn ($resolution): string => $resolution->import->source, $neutralResolutions),
            array_map(static fn ($resolution): string => $normalizeFixturePath($resolution->path), $neutralResolutions),
        ));
        $t->same([
            '@wordpress/interactivity' => 'node_modules/@wordpress/interactivity/build-module/index.js',
            'port-libs-card-runtime/helper' => 'node_modules/port-libs-card-runtime/helper.js',
            'wordpress-package-assets-fixture' => 'src/entry.js',
            'wordpress-package-assets-fixture/self-export' => 'src/self-export.js',
            'exports-map-pkg' => 'node_modules/exports-map-pkg/default.js',
            'exports-map-pkg/features/card' => 'node_modules/exports-map-pkg/features/card.js',
            'conditional-array-pkg' => 'node_modules/conditional-array-pkg/default.js',
            'conditional-array-pkg/custom' => 'node_modules/conditional-array-pkg/default.js',
            'browser-map-pkg' => 'node_modules/browser-map-pkg/module.js',
            'browser-map-pkg/feature' => 'node_modules/browser-map-pkg/feature.js',
            'containing-browser-map-pkg' => 'node_modules/containing-browser-map-pkg/main.js',
            '#view' => 'src/internal/view.js',
            '#conditional' => 'src/internal/default.js',
            '#/blocks/card' => 'src/internal/blocks/card.js',
            '#pkg-runtime' => 'node_modules/exports-map-pkg/features/card.js',
            'port-libs-card-runtime' => 'node_modules/port-libs-card-runtime/dist/module.js',
            'server-only-package' => 'node_modules/server-only-package/esm.js',
            'bad-main-pkg' => 'node_modules/bad-main-pkg/index.js',
            'exports-map-pkg/preview' => 'node_modules/exports-map-pkg/preview.cjs',
            'exports-map-pkg/legacy/admin' => 'node_modules/exports-map-pkg/legacy/admin.js',
            '#require-preview' => 'src/internal/preview.cjs',
        ], array_combine(
            array_map(static fn ($resolution): string => $resolution->import->source, $customNeutralResolutions),
            array_map(static fn ($resolution): string => $normalizeFixturePath($resolution->path), $customNeutralResolutions),
        ));
    },
    'maps upstream package exports conditions subpaths and patterns' => static function (TestRunner $t) use ($entryDir, $normalizeFixturePath): void {
        $browser = new PackageResolver('browser');
        $node = new PackageResolver('node');
        $neutral = new PackageResolver('neutral');

        $t->same('node_modules/exports-map-pkg/browser.js', $normalizeFixturePath($browser->resolveImport(new ModuleImport('named', 'exports-map-pkg', [], 0), $entryDir)?->path ?? ''));
        $t->same('node_modules/exports-map-pkg/node.js', $normalizeFixturePath($node->resolveImport(new ModuleImport('named', 'exports-map-pkg', [], 0), $entryDir)?->path ?? ''));
        $t->same('node_modules/exports-map-pkg/default.js', $normalizeFixturePath($neutral->resolveImport(new ModuleImport('named', 'exports-map-pkg', [], 0), $entryDir)?->path ?? ''));

        $t->same('node_modules/exports-map-pkg/preview.mjs', $normalizeFixturePath($browser->resolveImport(new ModuleImport('named', 'exports-map-pkg/preview', [], 0), $entryDir)?->path ?? ''));
        $t->same('node_modules/exports-map-pkg/preview.cjs', $normalizeFixturePath($browser->resolveImport(new ModuleImport('commonjs-require', 'exports-map-pkg/preview', [], 0), $entryDir)?->path ?? ''));
        $t->same('exports', $browser->resolveImport(new ModuleImport('named', 'exports-map-pkg/preview', [], 0), $entryDir)?->mainField);

        $t->same('node_modules/exports-map-pkg/features/card.js', $normalizeFixturePath($browser->resolveImport(new ModuleImport('named', 'exports-map-pkg/features/card', [], 0), $entryDir)?->path ?? ''));
        $t->same('node_modules/exports-map-pkg/legacy/admin.js', $normalizeFixturePath($browser->resolveImport(new ModuleImport('named', 'exports-map-pkg/legacy/admin', [], 0), $entryDir)?->path ?? ''));
    },
    'maps package exports arrays and custom conditions' => static function (TestRunner $t) use ($entryDir, $normalizeFixturePath): void {
        $browser = new PackageResolver('browser');
        $node = new PackageResolver('node');
        $wordpress = new PackageResolver('browser', null, ['.tsx', '.ts', '.jsx', '.js', '.css', '.json'], ['wordpress']);

        $browserRoot = $browser->resolveImport(new ModuleImport('named', 'conditional-array-pkg', [], 0), $entryDir);
        $nodeRoot = $node->resolveImport(new ModuleImport('named', 'conditional-array-pkg', [], 0), $entryDir);
        $customDefault = $browser->resolveImport(new ModuleImport('named', 'conditional-array-pkg/custom', [], 0), $entryDir);
        $customWordPress = $wordpress->resolveImport(new ModuleImport('named', 'conditional-array-pkg/custom', [], 0), $entryDir);

        $t->same('node_modules/conditional-array-pkg/browser.js', $normalizeFixturePath($browserRoot?->path ?? ''));
        $t->same('exports', $browserRoot?->mainField);
        $t->same('node_modules/conditional-array-pkg/default.js', $normalizeFixturePath($nodeRoot?->path ?? ''));
        $t->same('node_modules/conditional-array-pkg/default.js', $normalizeFixturePath($customDefault?->path ?? ''));
        $t->same('node_modules/conditional-array-pkg/wordpress.js', $normalizeFixturePath($customWordPress?->path ?? ''));
    },
    'maps upstream package browser object root subpaths and disabled entries' => static function (TestRunner $t) use ($entryDir, $normalizeFixturePath): void {
        $browser = new PackageResolver('browser');
        $node = new PackageResolver('node');
        $neutral = new PackageResolver('neutral', ['module', 'main']);

        $browserRoot = $browser->resolveImport(new ModuleImport('named', 'browser-map-pkg', [], 0), $entryDir);
        $browserFeature = $browser->resolveImport(new ModuleImport('named', 'browser-map-pkg/feature', [], 0), $entryDir);
        $disabled = $browser->resolveImport(new ModuleImport('named', 'browser-map-pkg/disabled.js', [], 0), $entryDir);

        $t->same('node_modules/browser-map-pkg/browser-module.js', $normalizeFixturePath($browserRoot?->path ?? ''));
        $t->same('browser', $browserRoot?->mainField);
        $t->same('node_modules/browser-map-pkg/feature-browser.js', $normalizeFixturePath($browserFeature?->path ?? ''));
        $t->same('browser', $browserFeature?->mainField);
        $t->same(null, $disabled);
        $t->same('node_modules/browser-map-pkg/main.js', $normalizeFixturePath($node->resolveImport(new ModuleImport('named', 'browser-map-pkg', [], 0), $entryDir)?->path ?? ''));
        $t->same('node_modules/browser-map-pkg/feature.js', $normalizeFixturePath($node->resolveImport(new ModuleImport('named', 'browser-map-pkg/feature', [], 0), $entryDir)?->path ?? ''));
        $t->same('node_modules/browser-map-pkg/module.js', $normalizeFixturePath($neutral->resolveImport(new ModuleImport('named', 'browser-map-pkg', [], 0), $entryDir)?->path ?? ''));
    },
    'maps containing package browser object remaps for package imports' => static function (TestRunner $t) use ($fixtureRoot, $normalizeFixturePath): void {
        $packageSourceDir = $fixtureRoot . '/node_modules/containing-browser-map-pkg';
        $browser = new PackageResolver('browser');
        $node = new PackageResolver('node');

        $localShim = $browser->resolveImport(new ModuleImport('named', 'node-pkg', [], 0), $packageSourceDir);
        $packageShim = $browser->resolveImport(new ModuleImport('named', 'node-pkg-package', [], 0), $packageSourceDir);
        $disabled = $browser->resolveImport(new ModuleImport('named', 'node-pkg-disabled', [], 0), $packageSourceDir);
        $builtinPathShim = $browser->resolveImport(new ModuleImport('named', 'path', [], 0), $packageSourceDir);
        $builtinNodePathShim = $browser->resolveImport(new ModuleImport('named', 'node:path', [], 0), $packageSourceDir);

        $t->same('node_modules/containing-browser-map-pkg/node-pkg-browser.js', $normalizeFixturePath($localShim?->path ?? ''));
        $t->same('browser', $localShim?->mainField);
        $t->same('node-pkg', $localShim?->packageName);
        $t->same('node_modules/node-pkg-browser-package/index.js', $normalizeFixturePath($packageShim?->path ?? ''));
        $t->same('node-pkg-browser-package', $packageShim?->packageName);
        $t->same(null, $disabled);
        $t->same('node_modules/containing-browser-map-pkg/path-browser.js', $normalizeFixturePath($builtinPathShim?->path ?? ''));
        $t->same('browser', $builtinPathShim?->mainField);
        $t->same('node_modules/containing-browser-map-pkg/path-browser.js', $normalizeFixturePath($builtinNodePathShim?->path ?? ''));
        $t->same(null, $browser->resolveImport(new ModuleImport('named', 'crypto', [], 0), $packageSourceDir));
        $nodePath = $node->resolveImport(new ModuleImport('named', 'path', [], 0), $packageSourceDir);
        $nodePrefixPath = $node->resolveImport(new ModuleImport('named', 'node:path', [], 0), $packageSourceDir);

        $t->same(true, $nodePath?->external);
        $t->same('node-builtin', $nodePath?->mainField);
        $t->same('path', $nodePath?->packageName);
        $t->same('.', $nodePath?->subpath);
        $t->same('', $nodePath?->path);
        $t->same(true, $nodePrefixPath?->external);
        $t->same('node-builtin', $nodePrefixPath?->mainField);
        $t->same('path', $nodePrefixPath?->packageName);
        $t->same('node_modules/node-pkg/index.js', $normalizeFixturePath($node->resolveImport(new ModuleImport('named', 'node-pkg', [], 0), $packageSourceDir)?->path ?? ''));
        $t->same('node_modules/node-pkg-package/index.js', $normalizeFixturePath($node->resolveImport(new ModuleImport('named', 'node-pkg-package', [], 0), $packageSourceDir)?->path ?? ''));
    },
    'does not resolve unshimmed node prefix builtins through browser node_modules packages' => static function (TestRunner $t) use ($entryDir): void {
        $browser = new PackageResolver('browser');
        $neutral = new PackageResolver('neutral');
        $node = new PackageResolver('node');

        $t->same(null, $browser->resolveImport(new ModuleImport('named', 'node:path', [], 0), $entryDir));
        $t->same(null, $neutral->resolveImport(new ModuleImport('named', 'node:path', [], 0), $entryDir));
        $t->same(true, $node->resolveImport(new ModuleImport('named', 'node:path', [], 0), $entryDir)?->external);
    },
    'records node builtins as external on node platform without probing node_modules' => static function (TestRunner $t) use ($entryDir): void {
        $node = new PackageResolver('node');
        $browser = new PackageResolver('browser');
        $neutral = new PackageResolver('neutral');

        $path = $node->resolveImport(new ModuleImport('named', 'path', [], 0), $entryDir);
        $fsPromises = $node->resolveImport(new ModuleImport('commonjs-require', 'fs/promises', [], 0), $entryDir);
        $nodePath = $node->resolveImport(new ModuleImport('named', 'node:path', [], 0), $entryDir);

        $t->same(true, $path?->external);
        $t->same('node-builtin', $path?->mainField);
        $t->same('path', $path?->packageName);
        $t->same('.', $path?->subpath);
        $t->same([], $path?->tried);
        $t->same(true, $fsPromises?->external);
        $t->same('fs', $fsPromises?->packageName);
        $t->same('./promises', $fsPromises?->subpath);
        $t->same(true, $nodePath?->external);
        $t->same('path', $nodePath?->packageName);
        $t->same('.', $nodePath?->subpath);
        $t->same(null, $browser->resolveImport(new ModuleImport('named', 'fs/promises', [], 0), $entryDir));
        $t->same(null, $browser->resolveImport(new ModuleImport('named', 'node:fs', [], 0), $entryDir));
        $t->same(false, $neutral->resolveImport(new ModuleImport('named', 'path', [], 0), $entryDir)?->external);
        $t->same(null, $neutral->resolveImport(new ModuleImport('named', 'node:fs', [], 0), $entryDir));
    },
    'propagates node builtin externals into import records' => static function (TestRunner $t) use ($entryDir, $normalizeFixturePath): void {
        $analysis = (new JsModuleAnalyzer())->analyze(<<<'JS'
import path from "path";
const promises = require("fs/promises");
import("node:crypto");
import cardRuntime from "port-libs-card-runtime";
JS);

        $records = (new PackageResolver('node'))->importRecords($analysis, $entryDir);

        $t->same([
            'path' => true,
            'fs/promises' => true,
            'node:crypto' => true,
            'port-libs-card-runtime' => false,
        ], array_combine(
            array_map(static fn ($record): string => $record->source, $records),
            array_map(static fn ($record): bool => $record->external, $records),
        ));
        $t->same([
            'path' => '',
            'fs/promises' => '',
            'node:crypto' => '',
            'port-libs-card-runtime' => 'node_modules/port-libs-card-runtime/dist/main.cjs',
        ], array_combine(
            array_map(static fn ($record): string => $record->source, $records),
            array_map(static fn ($record): string => $record->path === '' ? '' : $normalizeFixturePath($record->path), $records),
        ));
        $t->same([
            'path' => 'node-builtin',
            'fs/promises' => 'node-builtin',
            'node:crypto' => 'node-builtin',
            'port-libs-card-runtime' => 'main',
        ], array_combine(
            array_map(static fn ($record): string => $record->source, $records),
            array_map(static fn ($record): ?string => $record->mainField, $records),
        ));
        $t->same([
            'path' => 'default',
            'fs/promises' => 'commonjs-require',
            'node:crypto' => 'dynamic',
            'port-libs-card-runtime' => 'default',
        ], array_combine(
            array_map(static fn ($record): string => $record->source, $records),
            array_map(static fn ($record): string => $record->kind, $records),
        ));
        $t->same('fs', $records[1]->packageName);
        $t->same('./promises', $records[1]->subpath);
    },
    'maps upstream package imports local targets conditions patterns and package remaps' => static function (TestRunner $t) use ($entryDir, $normalizeFixturePath): void {
        $browser = new PackageResolver('browser');
        $node = new PackageResolver('node');
        $neutral = new PackageResolver('neutral');

        $view = $browser->resolveImport(new ModuleImport('named', '#view', [], 0), $entryDir);

        $t->same('src/internal/view.js', $normalizeFixturePath($view?->path ?? ''));
        $t->same('wordpress-package-assets-fixture', $view?->packageName);
        $t->same('#view', $view?->subpath);
        $t->same('imports', $view?->mainField);
        $t->same('src/internal/browser.js', $normalizeFixturePath($browser->resolveImport(new ModuleImport('named', '#conditional', [], 0), $entryDir)?->path ?? ''));
        $t->same('src/internal/node.js', $normalizeFixturePath($node->resolveImport(new ModuleImport('named', '#conditional', [], 0), $entryDir)?->path ?? ''));
        $t->same('src/internal/default.js', $normalizeFixturePath($neutral->resolveImport(new ModuleImport('named', '#conditional', [], 0), $entryDir)?->path ?? ''));
        $t->same('src/internal/preview.mjs', $normalizeFixturePath($browser->resolveImport(new ModuleImport('named', '#require-preview', [], 0), $entryDir)?->path ?? ''));
        $t->same('src/internal/preview.cjs', $normalizeFixturePath($browser->resolveImport(new ModuleImport('commonjs-require', '#require-preview', [], 0), $entryDir)?->path ?? ''));
        $t->same('src/internal/blocks/card.js', $normalizeFixturePath($browser->resolveImport(new ModuleImport('named', '#/blocks/card', [], 0), $entryDir)?->path ?? ''));
        $t->same('node_modules/exports-map-pkg/features/card.js', $normalizeFixturePath($browser->resolveImport(new ModuleImport('named', '#pkg-runtime', [], 0), $entryDir)?->path ?? ''));
    },
    'does not fall back around exact or unsafe package exports targets' => static function (TestRunner $t) use ($entryDir): void {
        $resolver = new PackageResolver('browser');

        $t->same(null, $resolver->resolveImport(new ModuleImport('named', 'exact-extension-pkg/exact', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', 'unsafe-exports-pkg/escape', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', 'unsafe-exports-pkg/nested', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', 'exports-map-pkg/private', [], 0), $entryDir));
    },
    'returns no package resolution for missing packages and relative imports' => static function (TestRunner $t) use ($entryDir): void {
        $resolver = new PackageResolver('browser');

        $t->same(null, $resolver->resolveImport(new ModuleImport('named', './relative.js', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', 'https://cdn.example.test/chunk.js', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', 'missing-package', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', '#', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', '#missing', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', '#unsafe', [], 0), $entryDir));
        $t->same(null, $resolver->resolveImport(new ModuleImport('named', '#nested-unsafe', [], 0), $entryDir));
    },
];
