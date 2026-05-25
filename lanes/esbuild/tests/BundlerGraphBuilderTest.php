<?php

declare(strict_types=1);

use PortLibs\Esbuild\BundlerGraphBuilder;
use PortLibs\Esbuild\BundlerMetafile;
use PortLibs\Esbuild\BundlerOutput;
use PortLibs\Esbuild\PackageResolver;

$fixtureRoot = dirname(__DIR__) . '/fixtures/wordpress-package-assets';
$normalizeFixturePath = static function (?string $path) use ($fixtureRoot): ?string {
    if ($path === null) {
        return null;
    }

    return str_replace('\\', '/', substr($path, strlen((string) realpath($fixtureRoot)) + 1));
};

return [
    'builds a bounded browser dependency graph from package and relative imports' => static function (TestRunner $t) use ($fixtureRoot, $normalizeFixturePath): void {
        $entry = $fixtureRoot . '/src/entry.js';
        $graph = (new BundlerGraphBuilder())->build($entry);
        $modulePaths = array_map($normalizeFixturePath, array_keys($graph->modules));
        sort($modulePaths);

        $t->same('src/entry.js', $normalizeFixturePath($graph->entry));
        $t->same(true, in_array('src/entry.js', $modulePaths, true));
        $t->same(true, in_array('node_modules/@wordpress/interactivity/build-module/index.js', $modulePaths, true));
        $t->same(true, in_array('node_modules/port-libs-card-runtime/helper.js', $modulePaths, true));
        $t->same(true, in_array('src/internal/view.js', $modulePaths, true));
        $t->same(true, in_array('src/internal/preview.cjs', $modulePaths, true));
        $t->same([], $graph->externalEdges);
        $t->same(['node-pkg-disabled'], array_map(static fn ($edge): string => $edge->source, $graph->missingEdges));

        $entryEdges = array_combine(
            array_map(static fn ($edge): string => $edge->source, $graph->modules[(string) realpath($entry)]->edges),
            array_map(static fn ($edge): ?string => $normalizeFixturePath($edge->path), $graph->modules[(string) realpath($entry)]->edges),
        );
        $t->same('node_modules/@wordpress/interactivity/build-module/index.js', $entryEdges['@wordpress/interactivity']);
        $t->same('node_modules/exports-map-pkg/browser.js', $entryEdges['exports-map-pkg']);
        $t->same('src/internal/browser.js', $entryEdges['#conditional']);
    },
    'records node builtins as graph externals without following fixture node_modules packages' => static function (TestRunner $t) use ($fixtureRoot, $normalizeFixturePath): void {
        $entry = $fixtureRoot . '/src/node-entry.js';
        $graph = (new BundlerGraphBuilder(new PackageResolver('node')))->build($entry);

        $t->same(2, count($graph->externalEdges));
        $t->same(['path', 'node:crypto'], array_map(static fn ($edge): string => $edge->source, $graph->externalEdges));
        $t->same([null, null], array_map(static fn ($edge): ?string => $edge->path, $graph->externalEdges));
        $t->same([true, true], array_map(static fn ($edge): bool => $edge->external, $graph->externalEdges));
        $t->same(true, isset($graph->modules[(string) realpath($fixtureRoot . '/src/local-preview.js')]));
        $t->same('src/local-preview.js', $normalizeFixturePath($graph->modules[(string) realpath($entry)]->edges[2]->path));
    },
    'records missing relative and package graph edges without aborting traversal' => static function (TestRunner $t) use ($fixtureRoot): void {
        $graph = (new BundlerGraphBuilder())->build($fixtureRoot . '/src/missing-entry.js');

        $t->same(2, count($graph->missingEdges));
        $t->same(['./missing-preview', 'missing-package'], array_map(static fn ($edge): string => $edge->source, $graph->missingEdges));
        $t->same([true, true], array_map(static fn ($edge): bool => $edge->missing, $graph->missingEdges));
        $t->same(false, $graph->missingEdges[0]->external);
    },
    'records loader metadata for terminal css and json graph edges' => static function (TestRunner $t) use ($fixtureRoot, $normalizeFixturePath): void {
        $entry = $fixtureRoot . '/src/loader-entry.js';
        $graph = (new BundlerGraphBuilder())->build($entry);
        $entryModule = $graph->modules[(string) realpath($entry)];
        $edgesBySource = array_combine(
            array_map(static fn ($edge): string => $edge->source, $entryModule->edges),
            $entryModule->edges,
        );

        $t->same('css', $edgesBySource['./block.css']->loader);
        $t->same('json', $edgesBySource['./block.json']->loader);
        $t->same('js', $edgesBySource['./local-preview.js']->loader);
        $t->same('src/block.css', $normalizeFixturePath($edgesBySource['./block.css']->path));
        $t->same('src/block.json', $normalizeFixturePath($edgesBySource['./block.json']->path));
        $t->same(false, isset($graph->modules[(string) realpath($fixtureRoot . '/src/block.css')]));
        $t->same(false, isset($graph->modules[(string) realpath($fixtureRoot . '/src/block.json')]));
        $t->same(true, isset($graph->modules[(string) realpath($fixtureRoot . '/src/local-preview.js')]));
    },
    'records unsupported resolved asset loaders without parsing them as JavaScript' => static function (TestRunner $t) use ($fixtureRoot, $normalizeFixturePath): void {
        $entry = $fixtureRoot . '/src/unsupported-loader-entry.js';
        $graph = (new BundlerGraphBuilder())->build($entry);

        $t->same(1, count($graph->unsupportedEdges));
        $t->same('./asset.bin', $graph->unsupportedEdges[0]->source);
        $t->same('src/asset.bin', $normalizeFixturePath($graph->unsupportedEdges[0]->path));
        $t->same(null, $graph->unsupportedEdges[0]->loader);
        $t->same(false, $graph->unsupportedEdges[0]->missing);
        $t->same([], $graph->missingEdges);
        $t->same(false, isset($graph->modules[(string) realpath($fixtureRoot . '/src/asset.bin')]));
        $t->same(true, isset($graph->modules[(string) realpath($fixtureRoot . '/src/local-preview.js')]));
    },
    'summarizes graph inputs and diagnostics for a bounded metafile surface' => static function (TestRunner $t) use ($fixtureRoot): void {
        $graph = (new BundlerGraphBuilder())->build($fixtureRoot . '/src/unsupported-loader-entry.js');
        $metafile = (new BundlerMetafile())->summarize($graph, $fixtureRoot);

        $t->same('src/unsupported-loader-entry.js', $metafile['entry']);
        $t->same(true, isset($metafile['inputs']['src/unsupported-loader-entry.js']));
        $t->same(true, isset($metafile['inputs']['src/local-preview.js']));
        $t->same(false, isset($metafile['inputs']['src/asset.bin']));
        $t->same(['./asset.bin'], array_map(static fn (array $diagnostic): string => $diagnostic['path'], $metafile['diagnostics']['unsupported']));
        $t->same('src/asset.bin', $metafile['diagnostics']['unsupported'][0]['resolved']);
        $t->same([], $metafile['diagnostics']['missing']);

        $importsByPath = array_combine(
            array_map(static fn (array $import): string => $import['path'], $metafile['inputs']['src/unsupported-loader-entry.js']['imports']),
            $metafile['inputs']['src/unsupported-loader-entry.js']['imports'],
        );
        $t->same(true, $importsByPath['src/asset.bin']['unsupported']);
        $t->same('js', $importsByPath['src/local-preview.js']['loader']);
    },
    'summarizes node builtin externals in the bounded metafile surface' => static function (TestRunner $t) use ($fixtureRoot): void {
        $graph = (new BundlerGraphBuilder(new PackageResolver('node')))->build($fixtureRoot . '/src/node-entry.js');
        $metafile = (new BundlerMetafile())->summarize($graph, $fixtureRoot);

        $t->same(['path', 'node:crypto'], array_map(static fn (array $diagnostic): string => $diagnostic['path'], $metafile['diagnostics']['external']));
        $entryImports = $metafile['inputs']['src/node-entry.js']['imports'];
        $externalImports = array_values(array_filter($entryImports, static fn (array $import): bool => $import['external']));
        $t->same(['path', 'node:crypto'], array_map(static fn (array $import): string => $import['path'], $externalImports));
        $t->same(['default', 'dynamic'], array_map(static fn (array $import): string => $import['kind'], $externalImports));
    },
    'builds a bounded JavaScript output summary from already resolved graph modules' => static function (TestRunner $t) use ($fixtureRoot): void {
        $graph = (new BundlerGraphBuilder())->build($fixtureRoot . '/src/loader-entry.js');
        $output = (new BundlerOutput())->build($graph, $fixtureRoot, 'block-view.js');

        $t->same('src/loader-entry.js', $output['entry']);
        $t->same('block-view.js', $output['output']['path']);
        $t->same(true, str_contains($output['output']['contents'], "// src/loader-entry.js\n"));
        $t->same(true, str_contains($output['output']['contents'], "// src/local-preview.js\n"));
        $t->same(true, str_contains($output['output']['contents'], "export const preview = 'card-preview';"));
        $t->same(false, str_contains($output['output']['contents'], 'front-end stylesheet fixture'));
        $t->same(true, $output['output']['bytes'] > $output['inputs']['src/loader-entry.js']['bytes']);
        $t->same(true, isset($output['inputs']['src/local-preview.js']));
        $t->same(false, isset($output['inputs']['src/block.css']));
        $t->same(false, isset($output['inputs']['src/block.json']));
        $t->same([], $output['diagnostics']['missing']);
        $t->same([], $output['diagnostics']['unsupported']);
    },
    'preserves output diagnostics for external and unsupported graph edges' => static function (TestRunner $t) use ($fixtureRoot): void {
        $nodeGraph = (new BundlerGraphBuilder(new PackageResolver('node')))->build($fixtureRoot . '/src/node-entry.js');
        $nodeOutput = (new BundlerOutput())->build($nodeGraph, $fixtureRoot);
        $unsupportedGraph = (new BundlerGraphBuilder())->build($fixtureRoot . '/src/unsupported-loader-entry.js');
        $unsupportedOutput = (new BundlerOutput())->build($unsupportedGraph, $fixtureRoot);

        $t->same(['path', 'node:crypto'], array_map(static fn (array $diagnostic): string => $diagnostic['path'], $nodeOutput['diagnostics']['external']));
        $t->same(true, str_contains($nodeOutput['output']['contents'], "// src/node-entry.js\n"));
        $t->same(true, str_contains($nodeOutput['output']['contents'], "// src/local-preview.js\n"));
        $t->same(['./asset.bin'], array_map(static fn (array $diagnostic): string => $diagnostic['path'], $unsupportedOutput['diagnostics']['unsupported']));
        $t->same(false, isset($unsupportedOutput['inputs']['src/asset.bin']));
        $t->same(true, isset($unsupportedOutput['inputs']['src/local-preview.js']));
    },
];
