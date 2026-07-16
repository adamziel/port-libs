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
        $t->same(false, str_contains($output['output']['contents'], "import './local-preview.js';"));
        $t->same(false, str_contains($output['output']['contents'], "import './block.css';"));
        $t->same(false, str_contains($output['output']['contents'], "import metadata from './block.json' with { type: 'json' };"));
        $t->same(true, str_contains($output['output']['contents'], "import './src/block.css';"));
        $t->same(true, str_contains($output['output']['contents'], "import metadata from './src/block.json' with { type: 'json' };"));
        $t->same(false, str_contains($output['output']['contents'], 'front-end stylesheet fixture'));
        $t->same(true, $output['output']['bytes'] > $output['inputs']['src/loader-entry.js']['bytes']);
        $t->same(true, isset($output['inputs']['src/local-preview.js']));
        $t->same(false, isset($output['inputs']['src/block.css']));
        $t->same(false, isset($output['inputs']['src/block.json']));
        $t->same(1, $output['inputs']['src/loader-entry.js']['importsRemoved']);
        $t->same(2, $output['inputs']['src/loader-entry.js']['importsRewritten']);
        $t->same([
            ['from' => './block.css', 'to' => './src/block.css', 'kind' => 'side-effect'],
            ['from' => './block.json', 'to' => './src/block.json', 'kind' => 'default'],
        ], $output['inputs']['src/loader-entry.js']['rewrites']);
        $t->same(0, $output['inputs']['src/local-preview.js']['importsRemoved']);
        $t->same(0, $output['inputs']['src/local-preview.js']['importsRewritten']);
        $t->same([], $output['diagnostics']['missing']);
        $t->same([], $output['diagnostics']['unsupported']);
    },
    'propagates bounded output byte accounting into the metafile surface' => static function (TestRunner $t) use ($fixtureRoot): void {
        $graph = (new BundlerGraphBuilder())->build($fixtureRoot . '/src/loader-entry.js');
        $output = (new BundlerOutput())->build($graph, $fixtureRoot, 'block-view.js');
        $metafile = (new BundlerMetafile())->summarize($graph, $fixtureRoot, $output);

        $t->same(true, isset($metafile['outputs']['block-view.js']));
        $t->same($output['output']['bytes'], $metafile['outputs']['block-view.js']['bytes']);
        $t->same(1, $metafile['outputs']['block-view.js']['importsRemoved']);
        $t->same($output['inputs']['src/loader-entry.js']['outputBytes'], $metafile['outputs']['block-view.js']['inputs']['src/loader-entry.js']['bytesInOutput']);
        $t->same($output['inputs']['src/local-preview.js']['outputBytes'], $metafile['outputs']['block-view.js']['inputs']['src/local-preview.js']['bytesInOutput']);
        $t->same(1, $metafile['outputs']['block-view.js']['inputs']['src/loader-entry.js']['importsRemoved']);
        $t->same(0, $metafile['outputs']['block-view.js']['inputs']['src/local-preview.js']['importsRemoved']);
        $t->same(false, isset($metafile['outputs']['block-view.js']['inputs']['src/block.css']));
        $t->same(false, isset($metafile['outputs']['block-view.js']['inputs']['src/block.json']));
        $t->same(true, isset($metafile['inputs']['src/loader-entry.js']));
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
    'preserves and accounts for external imports in node platform output previews' => static function (TestRunner $t) use ($fixtureRoot): void {
        $graph = (new BundlerGraphBuilder(new PackageResolver('node')))->build($fixtureRoot . '/src/node-entry.js');
        $output = (new BundlerOutput())->build($graph, $fixtureRoot, 'node-block-view.js');
        $metafile = (new BundlerMetafile())->summarize($graph, $fixtureRoot, $output);

        $t->same(true, str_contains($output['output']['contents'], "import path from 'path';"));
        $t->same(true, str_contains($output['output']['contents'], "import('node:crypto');"));
        $t->same(2, $output['inputs']['src/node-entry.js']['importsExternal']);
        $t->same([
            ['path' => 'path', 'kind' => 'default'],
            ['path' => 'node:crypto', 'kind' => 'dynamic'],
        ], $output['inputs']['src/node-entry.js']['externalImports']);
        $t->same(0, $output['inputs']['src/local-preview.js']['importsExternal']);
        $t->same(2, $metafile['outputs']['node-block-view.js']['importsExternal']);
        $t->same([
            ['path' => 'path', 'kind' => 'default', 'input' => 'src/node-entry.js'],
            ['path' => 'node:crypto', 'kind' => 'dynamic', 'input' => 'src/node-entry.js'],
        ], $metafile['outputs']['node-block-view.js']['externalImports']);
        $t->same(2, $metafile['outputs']['node-block-view.js']['inputs']['src/node-entry.js']['importsExternal']);
    },
    'rewrites retained terminal asset imports relative to nested output paths' => static function (TestRunner $t) use ($fixtureRoot): void {
        $graph = (new BundlerGraphBuilder())->build($fixtureRoot . '/src/loader-entry.js');
        $output = (new BundlerOutput())->build($graph, $fixtureRoot, 'build/block-view.js');

        $t->same(true, str_contains($output['output']['contents'], "import '../src/block.css';"));
        $t->same(true, str_contains($output['output']['contents'], "import metadata from '../src/block.json' with { type: 'json' };"));
        $t->same([
            ['from' => './block.css', 'to' => '../src/block.css', 'kind' => 'side-effect'],
            ['from' => './block.json', 'to' => '../src/block.json', 'kind' => 'default'],
        ], $output['inputs']['src/loader-entry.js']['rewrites']);
    },
    'removes multiple static JavaScript imports while retaining terminal asset imports' => static function (TestRunner $t) use ($fixtureRoot): void {
        $graph = (new BundlerGraphBuilder())->build($fixtureRoot . '/src/output-static-entry.js');
        $output = (new BundlerOutput())->build($graph, $fixtureRoot, 'build/output-static.js');

        $t->same(true, str_contains($output['output']['contents'], "// src/output-static-entry.js\n"));
        $t->same(true, str_contains($output['output']['contents'], "// src/local-preview.js\n"));
        $t->same(false, str_contains($output['output']['contents'], "import './local-preview.js';"));
        $t->same(false, str_contains($output['output']['contents'], "import { preview } from './local-preview.js';"));
        $t->same(true, str_contains($output['output']['contents'], "import '../src/block.css';"));
        $t->same(true, str_contains($output['output']['contents'], "import metadata from '../src/block.json' with { type: 'json' };"));
        $t->same(2, $output['inputs']['src/output-static-entry.js']['importsRemoved']);
        $t->same(2, $output['inputs']['src/output-static-entry.js']['importsRewritten']);
        $t->same([
            ['from' => './block.css', 'to' => '../src/block.css', 'kind' => 'side-effect'],
            ['from' => './block.json', 'to' => '../src/block.json', 'kind' => 'default'],
        ], $output['inputs']['src/output-static-entry.js']['rewrites']);
    },
    'rewrites named re-export clauses for already bundled JavaScript modules' => static function (TestRunner $t) use ($fixtureRoot): void {
        $graph = (new BundlerGraphBuilder())->build($fixtureRoot . '/src/output-reexport-entry.js');
        $output = (new BundlerOutput())->build($graph, $fixtureRoot, 'build/output-reexport.js');

        $t->same(true, str_contains($output['output']['contents'], "// src/output-reexport-entry.js\n"));
        $t->same(true, str_contains($output['output']['contents'], "// src/local-preview.js\n"));
        $t->same(true, str_contains($output['output']['contents'], "// node_modules/port-libs-card-runtime/helper.js\n"));
        $t->same(true, str_contains($output['output']['contents'], "export { preview };"));
        $t->same(true, str_contains($output['output']['contents'], "export { runtime as helperRuntime };"));
        $t->same(false, str_contains($output['output']['contents'], "export { preview } from './local-preview.js';"));
        $t->same(false, str_contains($output['output']['contents'], "export { runtime as helperRuntime } from 'port-libs-card-runtime/helper';"));
        $t->same(true, str_contains($output['output']['contents'], "import '../src/block.css';"));
        $t->same(2, $output['inputs']['src/output-reexport-entry.js']['exportsRewritten']);
        $t->same(1, $output['inputs']['src/output-reexport-entry.js']['importsRewritten']);
        $t->same(0, $output['inputs']['src/output-reexport-entry.js']['importsRemoved']);
    },
];
