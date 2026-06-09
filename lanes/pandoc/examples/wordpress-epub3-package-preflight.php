<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\EpubPackage;
use PortLibs\Pandoc\ZipPackage;

$containerXml = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/content.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$opfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" prefix="schema: https://schema.org/ review: https://example.invalid/epub-review#">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:wordpress-import-epub</dc:identifier>
    <dc:title id="main-title">WordPress EPUB Import Packet</dc:title>
    <dc:creator id="creator">Data Liberation Team</dc:creator>
    <dc:language>en</dc:language>
    <meta refines="#main-title" property="title-type">main</meta>
    <meta refines="#main-title" property="file-as">WordPress EPUB Import Packet</meta>
    <meta refines="#creator" property="role" scheme="marc:relators">aut</meta>
    <meta refines="#bookid" property="identifier-type" scheme="onix:codelist5">15</meta>
    <meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta property="media:duration">0:00:04.000</meta>
    <meta property="media:duration" refines="#mo-intro">0:00:04.000</meta>
    <link id="review-record" rel="record alternate schema:associatedMedia https://example.invalid/link-rel#review record" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org review:packet https://example.invalid/props#review" hreflang="en"/>
    <link id="remote-onix" rel="record" href="https://metadata.example.invalid/onix.xml" media-type="application/xml" properties="onix"/>
    <link id="creator-voicing" rel="voicing" refines="#creator" href="audio/creator-name.mp3" media-type="audio/mpeg" properties="pronunciation"/>
    <link id="missing-record" rel="record" href="meta/missing-record.json" media-type="application/json"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapters/intro.xhtml" media-type="application/xhtml+xml" properties="mathml svg remote-resources" media-overlay="mo-intro"/>
    <item id="chapter2" href="chapters/review.xhtml" media-type="application/xhtml+xml" properties="scripted switch"/>
    <item id="cover" href="media/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="css" href="styles/import.css" media-type="text/css"/>
    <item id="creator-audio" href="audio/creator-name.mp3" media-type="audio/mpeg"/>
    <item id="intro-audio" href="audio/intro.mp3" media-type="audio/mpeg"/>
    <item id="mo-intro" href="overlays/intro.smil" media-type="application/smil+xml"/>
    <item id="review-widget" href="widgets/review-widget.bin" media-type="application/x-wordpress-review-widget" fallback="review-widget-handler" fallback-style="css"/>
    <item id="review-widget-handler" href="widgets/review-widget.xhtml" media-type="application/xhtml+xml" properties="scripted"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2"/>
  </spine>
  <guide>
    <reference type="text" title="Start reading" href="chapters/intro.xhtml"/>
    <reference type="cover" title="Cover" href="media/cover.png"/>
  </guide>
  <collection id="series" role="series" xml:lang="en">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Import packet series</dc:title>
      <meta property="group-position">1</meta>
    </metadata>
    <link id="series-record" rel="record" href="meta/series.json" media-type="application/ld+json"/>
    <link id="remote-review" rel="alternate" href="https://example.invalid/epub/series.json" media-type="application/json"/>
  </collection>
  <bindings>
    <mediaType media-type="application/x-wordpress-review-widget" handler="review-widget-handler"/>
    <mediaType media-type="application/x-missing-widget" handler="missing-widget-handler"/>
  </bindings>
</package>
XML;

$navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapters/intro.xhtml">Intro</a></li>
        <li><a href="chapters/review.xhtml">Review checklist</a></li>
      </ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="chapters/intro.xhtml">Start reading</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="chapters/review.xhtml#page-2">2</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

$smilXml = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL">
  <body>
    <par id="intro-audio">
      <text src="../chapters/intro.xhtml"/>
      <audio src="../audio/intro.mp3" clipBegin="0s" clipEnd="4s"/>
    </par>
  </body>
</smil>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
    ['name' => 'META-INF/container.xml', 'data' => $containerXml],
    ['name' => 'EPUB/content.opf', 'data' => $opfXml],
    ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
    ['name' => 'EPUB/chapters/intro.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
    ['name' => 'EPUB/chapters/review.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review checklist</h1></body></html>'],
    ['name' => 'EPUB/media/cover.png', 'data' => 'PNG'],
    ['name' => 'EPUB/styles/import.css', 'data' => 'body { line-height: 1.5; }'],
    ['name' => 'EPUB/meta/review-record.json', 'data' => '{"@context":"https://schema.org","name":"WordPress EPUB review record"}'],
    ['name' => 'EPUB/audio/creator-name.mp3', 'data' => 'MP3-CREATOR-NAME'],
    ['name' => 'EPUB/audio/intro.mp3', 'data' => 'MP3-INTRO'],
    ['name' => 'EPUB/overlays/intro.smil', 'data' => $smilXml],
    ['name' => 'EPUB/meta/series.json', 'data' => '{"name":"Import packet series"}'],
    ['name' => 'EPUB/widgets/review-widget.bin', 'data' => 'WIDGET'],
    ['name' => 'EPUB/widgets/review-widget.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review widget fallback</h1></body></html>'],
]);

$epub = EpubPackage::fromPackage($package);
$summary = $epub->summary();

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        'WordPress EPUB Import Packet',
        ['/EPUB/chapters/intro.xhtml', '/EPUB/chapters/review.xhtml'],
        ['Intro', 'Review checklist'],
        '/EPUB/media/cover.png',
        ['/EPUB/styles/import.css'],
        ['/EPUB/chapters/intro.xhtml', '/EPUB/media/cover.png'],
        ['/EPUB/chapters/intro.xhtml'],
        ['/EPUB/chapters/review.xhtml#page-2'],
        'WordPress EPUB Import Packet',
        'Data Liberation Team',
        'urn:uuid:wordpress-import-epub',
        [true, 'unique-identifier', 1, 'urn:uuid:wordpress-import-epub', []],
        ['Import packet series'],
        ['/EPUB/meta/series.json', 'https://example.invalid/epub/series.json'],
        'external-collection-link-target',
        'application/x-wordpress-review-widget',
        '/EPUB/widgets/review-widget.xhtml',
        'missing-binding-handler-manifest-item',
        ['/EPUB/meta/review-record.json', 'https://metadata.example.invalid/onix.xml', '/EPUB/audio/creator-name.mp3', '/EPUB/meta/missing-record.json'],
        'external-package-link-target',
        'creator-voicing',
        'creator-audio',
        ['local-package' => 3, 'remote-no-fetch' => 2, 'missing-package' => 1],
        ['https://metadata.example.invalid/onix.xml', 'https://example.invalid/epub/series.json'],
        ['external-package-link-target', 'missing-package-link-target', 'external-collection-link-target'],
        [8, 5, 2, 2, 1, 1],
        'https://schema.org/associatedMedia',
        'https://example.invalid/epub-review#packet',
        'duplicate-metadata-link-rel-token',
        [true, 1, 1, ['chapter1'], 4.0, 1],
        ['/EPUB/chapters/intro.xhtml'],
        ['/EPUB/audio/intro.mp3'],
        [true, 1, 1, 0, 1, 1, 0],
        ['review-widget', 'review-widget-handler', '/EPUB/widgets/review-widget.xhtml', 'application/xhtml+xml', 'css', '/EPUB/styles/import.css'],
        [1, 1, 1, 1, 1, 2, 1, 3],
        ['chapter1', 'chapter2', 'review-widget-handler'],
        ['mathml', 'svg', 'remote-resources'],
        ['scripted', 'switch'],
        [true, 5, 8, 0, 0],
        [false, '3.0', 3, 1, 1, 10, 2, 4, 3],
    ];
    $actual = [
        $summary['wordpressImport']['title'],
        $summary['wordpressImport']['readingOrderParts'],
        $summary['wordpressImport']['navigationLabels'],
        $summary['wordpressImport']['coverImagePart'],
        $summary['wordpressImport']['stylesheetParts'],
        array_column($summary['wordpressImport']['guideReferences'], 'target'),
        array_column($summary['wordpressImport']['landmarkTargets'], 'target'),
        array_column($summary['wordpressImport']['pageListTargets'], 'target'),
        $summary['wordpressImport']['metadataDetails']['sortTitle'],
        $summary['wordpressImport']['metadataDetails']['creatorsByRole']['aut'][0]['text'] ?? null,
        $summary['wordpressImport']['metadataDetails']['identifiersByType']['15'][0]['value'] ?? null,
        [
            $summary['wordpressImport']['metadataDetails']['uniqueIdentifier']['valid'] ?? null,
            $summary['wordpressImport']['metadataDetails']['uniqueIdentifier']['selectedBy'] ?? null,
            $summary['wordpressImport']['metadataDetails']['identifierSummary']['count'] ?? null,
            $summary['wordpressImport']['metadataDetails']['identifierSummary']['selectedValue'] ?? null,
            $summary['wordpressImport']['metadataDetails']['identifierDiagnostics'] ?? null,
        ],
        $summary['wordpressImport']['collectionTitles'],
        $summary['wordpressImport']['collectionLinkTargets'],
        $summary['wordpressImport']['collectionDiagnostics'][0]['type'] ?? null,
        $summary['wordpressImport']['mediaTypeBindings'][0]['mediaType'] ?? null,
        $summary['wordpressImport']['mediaTypeBindings'][0]['handlerPartName'] ?? null,
        $summary['wordpressImport']['mediaTypeBindingDiagnostics'][0]['type'] ?? null,
        $summary['wordpressImport']['packageLinkTargets'],
        $summary['wordpressImport']['packageLinkDiagnostics'][0]['type'] ?? null,
        $summary['wordpressImport']['packageLinksByRel']['voicing'][0]['id'] ?? null,
        $summary['wordpressImport']['packageLinksByRel']['voicing'][0]['manifestId'] ?? null,
        $summary['wordpressImport']['remoteResourcePolicy']['policyCounts'],
        $summary['wordpressImport']['remoteResourceExternalTargets'],
        array_map(
            static fn (array $diagnostic): string => (string) $diagnostic['type'],
            $summary['wordpressImport']['remoteResourcePolicyDiagnostics'],
        ),
        [
            $summary['wordpressImport']['packageLinkVocabulary']['relTokenCount'],
            $summary['wordpressImport']['packageLinkVocabulary']['propertyTokenCount'],
            $summary['wordpressImport']['packageLinkVocabulary']['resolvedTokenCount'],
            $summary['wordpressImport']['packageLinkVocabulary']['absoluteUrlTokenCount'],
            $summary['wordpressImport']['packageLinkVocabulary']['duplicateTokenCount'],
            $summary['wordpressImport']['packageLinkVocabulary']['diagnosticCount'],
        ],
        $summary['wordpressImport']['packageLinks'][0]['relVocabulary']['items'][2]['iri'] ?? null,
        $summary['wordpressImport']['packageLinks'][0]['propertyVocabulary']['items'][1]['iri'] ?? null,
        $summary['wordpressImport']['packageLinkVocabularyDiagnostics'][0]['type'] ?? null,
        [
            $summary['wordpressImport']['mediaOverlays']['present'] ?? null,
            $summary['wordpressImport']['mediaOverlays']['overlayCount'] ?? null,
            $summary['wordpressImport']['mediaOverlays']['resolvedOverlayCount'] ?? null,
            $summary['wordpressImport']['mediaOverlays']['itemsById']['mo-intro']['referencedByIds'] ?? null,
            $summary['wordpressImport']['mediaOverlays']['itemsById']['mo-intro']['durationSeconds'] ?? null,
            $summary['wordpressImport']['mediaOverlays']['itemsById']['mo-intro']['itemCount'] ?? null,
        ],
        $summary['wordpressImport']['mediaOverlayTargets'],
        $summary['wordpressImport']['mediaOverlayAudioTargets'],
        [
            $summary['wordpressImport']['manifestFallbacks']['present'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['fallbackCount'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['resolvedFallbackCount'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['fallbackDiagnosticCount'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['fallbackStyleCount'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['resolvedFallbackStyleCount'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['fallbackStyleDiagnosticCount'] ?? null,
        ],
        [
            $summary['wordpressImport']['manifestFallbacks']['itemsById']['review-widget']['id'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['itemsById']['review-widget']['fallbackTerminalId'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['itemsById']['review-widget']['fallbackTerminalPartName'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['itemsById']['review-widget']['fallbackTerminalMediaType'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['itemsById']['review-widget']['fallbackStyleTerminalId'] ?? null,
            $summary['wordpressImport']['manifestFallbacks']['itemsById']['review-widget']['fallbackStyleTerminalPartName'] ?? null,
        ],
        [
            $summary['wordpressImport']['resourcePropertySummary']['navCount'] ?? null,
            $summary['wordpressImport']['resourcePropertySummary']['coverImageCount'] ?? null,
            $summary['wordpressImport']['resourcePropertySummary']['mathmlCount'] ?? null,
            $summary['wordpressImport']['resourcePropertySummary']['svgCount'] ?? null,
            $summary['wordpressImport']['resourcePropertySummary']['remoteResourcesCount'] ?? null,
            $summary['wordpressImport']['resourcePropertySummary']['scriptedCount'] ?? null,
            $summary['wordpressImport']['resourcePropertySummary']['switchCount'] ?? null,
            $summary['wordpressImport']['resourcePropertySummary']['reviewRequiredCount'] ?? null,
        ],
        array_column($summary['wordpressImport']['resourcePropertyReviewItems'], 'id'),
        $summary['wordpressImport']['resourceProperties']['itemsById']['chapter1']['reviewFlags'] ?? null,
        $summary['wordpressImport']['resourceProperties']['itemsById']['chapter2']['reviewFlags'] ?? null,
        [
            $summary['wordpressImport']['resourceProperties']['propertyVocabulary']['present'] ?? null,
            $summary['wordpressImport']['resourceProperties']['propertyVocabulary']['itemCount'] ?? null,
            $summary['wordpressImport']['resourceProperties']['propertyVocabulary']['propertyTokenCount'] ?? null,
            $summary['wordpressImport']['resourceProperties']['propertyVocabulary']['prefixedPropertyCount'] ?? null,
            $summary['wordpressImport']['resourceProperties']['propertyVocabulary']['diagnosticCount'] ?? null,
        ],
        [
            $summary['wordpressImport']['packageValidation']['valid'] ?? null,
            $summary['wordpressImport']['packageValidation']['packageVersion'] ?? null,
            $summary['wordpressImport']['packageValidation']['diagnosticCount'] ?? null,
            $summary['wordpressImport']['packageValidation']['manifest']['usableNavItemCount'] ?? null,
            $summary['wordpressImport']['packageValidation']['manifest']['navItemCount'] ?? null,
            $summary['wordpressImport']['packageValidation']['manifest']['itemCount'] ?? null,
            $summary['wordpressImport']['packageValidation']['spine']['itemCount'] ?? null,
            $summary['wordpressImport']['packageValidation']['navigation']['entryCount'] ?? null,
            count($summary['wordpressImport']['packageValidationDiagnostics'] ?? []),
        ],
    ];

    if ($actual !== $expected) {
        throw new RuntimeException('EPUB3 package preflight self-test failed');
    }

    echo "epub3 package preflight self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
