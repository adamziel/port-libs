<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes import map module target provenance for reviewer handoff' => static function (TestRunner $t): void {
        $source = json_encode([
            'imports' => [
                'app' => '/assets/app.js',
                'pkg/' => '/vendor/pkg/',
                'bad-prefix/' => '/vendor/bad-prefix.js',
                'inline' => 'javascript:alert(1)',
                'empty' => '',
                'object-address' => ['url' => '/array.js'],
            ],
            'scopes' => [
                '/admin/' => [
                    'app' => '/admin/app.js',
                    'pkg/' => '/admin/pkg/',
                ],
                'javascript:alert(1)' => [
                    'evil' => '/evil.js',
                ],
                '/broken/' => 'not-object',
            ],
            'integrity' => [
                '/assets/app.js' => 'sha384-app',
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="imports" type="importmap">' . $source . '</script>'
                . '<script id="invalid-imports" type="importmap">{"imports":["bad"],"scopes":[],"integrity":"bad"}</script>',
            'import map module target review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/import-map-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $importMap = $summary[0];
        $invalidImportMap = $summary[1];

        $t->same('script', $importMap['name']);
        $t->same('importmap', $importMap['scriptPayloadKind']);
        $t->same(true, $importMap['scriptJsonParsed']);
        $t->same(['imports', 'scopes', 'integrity'], $importMap['scriptJsonObjectKeys']);
        $t->same(6, $importMap['importMapImportsCount']);
        $t->same(3, $importMap['importMapScopesCount']);
        $t->same(1, $importMap['importMapIntegrityCount']);
        $t->same('import-map-module-target-provenance-review', $importMap['importMapReviewPolicy']);
        $t->same([
            'app',
            'pkg/',
            'bad-prefix/',
            'inline',
            'empty',
            'object-address',
        ], $importMap['importMapImportSpecifiers']);

        $app = $importMap['importMapImportRecords'][0];
        $package = $importMap['importMapImportRecords'][1];
        $badPrefix = $importMap['importMapImportRecords'][2];
        $inline = $importMap['importMapImportRecords'][3];
        $empty = $importMap['importMapImportRecords'][4];
        $objectAddress = $importMap['importMapImportRecords'][5];

        $t->same('/assets/app.js', $app['address']);
        $t->same('relative', $app['addressKind']);
        $t->same(false, $app['addressUnsafe']);
        $t->same(true, $app['valid']);
        $t->same(true, $package['specifierPrefix']);
        $t->same(true, $package['addressPrefix']);
        $t->same([], $package['issueCodes']);

        $t->same(['import-map-prefix-address-mismatch'], $badPrefix['issueCodes']);
        $t->same(true, $badPrefix['specifierPrefix']);
        $t->same(false, $badPrefix['addressPrefix']);
        $t->same(['unsafe-import-map-address'], $inline['issueCodes']);
        $t->same('javascript', $inline['addressScheme']);
        $t->same(true, $inline['addressUnsafe']);
        $t->same(['empty-import-map-address'], $empty['issueCodes']);
        $t->same(['non-string-import-map-address'], $objectAddress['issueCodes']);
        $t->same('object', $objectAddress['addressType']);

        $t->same(['/admin/', 'javascript:alert(1)', '/broken/'], $importMap['importMapScopePrefixes']);
        $admin = $importMap['importMapScopeRecords'][0];
        $unsafeScope = $importMap['importMapScopeRecords'][1];
        $brokenScope = $importMap['importMapScopeRecords'][2];

        $t->same('relative', $admin['scopePrefixKind']);
        $t->same(2, $admin['importCount']);
        $t->same(['app', 'pkg/'], $admin['importSpecifiers']);
        $t->same(true, $admin['valid']);
        $t->same(['unsafe-import-map-scope-prefix'], $unsafeScope['issueCodes']);
        $t->same('javascript', $unsafeScope['scopePrefixScheme']);
        $t->same(false, $unsafeScope['valid']);
        $t->same(null, $brokenScope['importCount']);
        $t->same(['invalid-import-map-scope-imports'], $brokenScope['issueCodes']);
        $t->same(false, $brokenScope['valid']);

        $t->same([
            'import-map-prefix-address-mismatch',
            'unsafe-import-map-address',
            'empty-import-map-address',
            'non-string-import-map-address',
            'unsafe-import-map-scope-prefix',
            'invalid-import-map-scope-imports',
        ], $importMap['importMapIssueCodes']);
        $t->same(['bad-prefix/', 'inline', 'empty', 'object-address'], $importMap['importMapInvalidImportSpecifiers']);
        $t->same(['importmap-scope-imports-not-object'], $importMap['scriptJsonDiagnostics']);
        $t->same(false, $importMap['importMapValid']);

        $t->same(['importmap-imports-not-object', 'importmap-scopes-not-object', 'importmap-integrity-not-object'], $invalidImportMap['scriptJsonDiagnostics']);
        $t->same([], $invalidImportMap['importMapImportRecords']);
        $t->same([], $invalidImportMap['importMapScopeRecords']);
        $t->same([
            'invalid-import-map-imports',
            'invalid-import-map-scopes',
            'invalid-import-map-integrity',
        ], $invalidImportMap['importMapIssueCodes']);
        $t->same(false, $invalidImportMap['importMapValid']);

        $t->contains('"imports":{"app":"/assets/app.js"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/import-map-review.html', $document->children[0]->attr('part'));
        json_encode($importMap, JSON_THROW_ON_ERROR);
    },
];
