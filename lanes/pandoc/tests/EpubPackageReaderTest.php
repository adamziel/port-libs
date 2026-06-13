<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubPackageReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => dirname(__DIR__) . '/fixtures/epub3-package';
$writePackageFile = static function (string $root, string $relativePath, string $bytes): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new \RuntimeException('Unable to create EPUB fixture directory: ' . $directory);
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new \RuntimeException('Unable to write EPUB fixture file: ' . $path);
    }
};
$removeDirectory = static function (string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
};

return [
    'maps epub container opf manifest spine and metadata handoff' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $meta = $document->attr('meta');
        $epub = $document->attr('epub');
        $manifest = $epub['manifestById'];
        $spine = $epub['spine'];

        $t->same('document', $document->type);
        $t->same('WordPress EPUB Import Packet', $meta['title']);
        $t->same(['Migration Team'], $meta['creators']);
        $t->same('en', $meta['language']);
        $t->same('urn:isbn:9780000000002', $meta['identifier']);
        $t->same('Port Libs Press', $meta['publisher']);
        $t->same('2026-06-09', $meta['date']);
        $t->same('EPUB/package.opf', $epub['containerRootfile']);
        $t->same('3.0', $epub['packageVersion']);
        $t->same('pub-id', $epub['uniqueIdentifierId']);
        $t->same(3, count($epub['metadataProperties']));
        $t->same('dcterms:modified', $epub['metadataProperties'][0]['property']);
        $t->same('2026-06-09T11:50:37Z', $epub['metadataProperties'][0]['value']);
        $t->same('file-as', $epub['metadataProperties'][2]['property']);
        $t->same('#creator', $epub['metadataProperties'][2]['refines']);
        $t->same(2, count($epub['metadataLinks']));
        $t->same('source-record', $epub['metadataLinks'][0]['id']);
        $t->same(['record'], $epub['metadataLinks'][0]['rel']);
        $t->same('chapter1.xhtml#opening-title', $epub['metadataLinks'][0]['href']);
        $t->same('EPUB/chapter1.xhtml', $epub['metadataLinks'][0]['path']);
        $t->same('opening-title', $epub['metadataLinks'][0]['fragment']);
        $t->same('application/xhtml+xml', $epub['metadataLinks'][0]['mediaType']);
        $t->same(['preview'], $epub['metadataLinks'][0]['properties']);
        $t->same('#pub-id', $epub['metadataLinks'][0]['refines']);
        $t->same(false, $epub['metadataLinks'][0]['external']);
        $t->same('remote-a11y', $epub['metadataLinks'][1]['id']);
        $t->same(['record', 'accessibility'], $epub['metadataLinks'][1]['rel']);
        $t->same('https://example.test/a11y/report.json', $epub['metadataLinks'][1]['path']);
        $t->same(true, $epub['metadataLinks'][1]['external']);
        $t->same(5, count($epub['manifest']));
        $t->same('nav.xhtml', $manifest['nav']['href']);
        $t->same('EPUB/nav.xhtml', $manifest['nav']['path']);
        $t->same('application/xhtml+xml', $manifest['nav']['mediaType']);
        $t->same(['nav'], $manifest['nav']['properties']);
        $t->same('EPUB/images/cover.png', $manifest['cover']['path']);
        $t->same(['cover-image'], $manifest['cover']['properties']);
        $t->same(2, count($spine));
        $t->same('chapter1', $spine[0]['idref']);
        $t->same('EPUB/chapter1.xhtml', $spine[0]['path']);
        $t->same(true, $spine[0]['linear']);
        $t->same('chapter2', $spine[1]['idref']);
        $t->same('EPUB/chapter2.xhtml', $spine[1]['path']);
        $t->same(true, $spine[1]['linear']);
    },
    'maps epub guide references for compact package review' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $guide = $epub['guide'];

        $t->same(true, $guide['present']);
        $t->same(4, $guide['itemCount']);
        $t->same(3, $guide['typedItemCount']);
        $t->same(1, $guide['missingTypeCount']);
        $t->same(['cover', 'text', 'glossary', 'appendix'], $guide['types']);
        $t->same(['cover' => 1, 'text' => 1, 'glossary' => 1, 'appendix' => 1], $guide['typeCounts']);
        $t->same(4, count($guide['items']));
        $t->same('cover', $guide['items'][0]['type']);
        $t->same(['cover'], $guide['items'][0]['types']);
        $t->same('Cover image', $guide['items'][0]['title']);
        $t->same('images/cover.png', $guide['items'][0]['href']);
        $t->same('EPUB/images/cover.png', $guide['items'][0]['path']);
        $t->same('', $guide['items'][0]['fragment']);
        $t->same(true, $guide['items'][0]['exists']);
        $t->same('cover', $guide['items'][0]['manifestId']);
        $t->same('image/png', $guide['items'][0]['mediaType']);
        $t->same([], $guide['items'][0]['diagnostics']);
        $t->same('EPUB/chapter1.xhtml', $guide['items'][1]['path']);
        $t->same('opening-title', $guide['items'][1]['fragment']);
        $t->same('Start reading', $guide['itemsByType']['text'][0]['title']);
        $t->same(['glossary', 'appendix'], $guide['items'][2]['types']);
        $t->same('EPUB/glossary.xhtml', $guide['items'][2]['path']);
        $t->same(false, $guide['items'][2]['exists']);
        $t->same('missing-guide-reference', $guide['items'][2]['diagnostics'][0]['type']);
        $t->same('Glossary', $guide['itemsByType']['appendix'][0]['title']);
        $t->same('', $guide['items'][3]['type']);
        $t->same('', $guide['items'][3]['typeRaw']);
        $t->same([], $guide['items'][3]['types']);
        $t->same('EPUB/chapter2.xhtml', $guide['items'][3]['path']);
        $t->same('details', $guide['items'][3]['fragment']);
        $t->same(true, $guide['items'][3]['exists']);
        $t->same('chapter2', $guide['items'][3]['manifestId']);
        $t->same('missing-guide-reference-type', $guide['items'][3]['diagnostics'][0]['type']);
        $t->same(2, $guide['diagnosticCount']);
        $t->same(2, $guide['diagnostics'][0]['index']);
        $t->same(3, $guide['diagnostics'][1]['index']);
    },
    'maps epub nav document and ncx fallback outlines' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $toc = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'toc'
        ));
        $landmarks = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'landmarks'
        ));
        $ncx = $epub['ncx'];

        $t->same(2, count($toc));
        $t->same('Opening Packet', $toc[0]['label']);
        $t->same('chapter1.xhtml', $toc[0]['href']);
        $t->same('EPUB/chapter1.xhtml', $toc[0]['path']);
        $t->same('', $toc[0]['fragment']);
        $t->same(1, count($toc[0]['children']));
        $t->same('Opening Note', $toc[0]['children'][0]['label']);
        $t->same('EPUB/chapter1.xhtml', $toc[0]['children'][0]['path']);
        $t->same('opening-note', $toc[0]['children'][0]['fragment']);
        $t->same('Details', $toc[1]['label']);
        $t->same('EPUB/chapter2.xhtml', $toc[1]['path']);
        $t->same('details', $toc[1]['fragment']);
        $t->same(2, count($landmarks));
        $t->same('Start', $landmarks[0]['label']);
        $t->same('EPUB/chapter1.xhtml', $landmarks[0]['path']);
        $t->same('Table of contents', $landmarks[1]['label']);
        $t->same('EPUB/nav.xhtml', $landmarks[1]['path']);
        $t->same(2, count($ncx));
        $t->same('Opening Packet', $ncx[0]['label']);
        $t->same(1, $ncx[0]['playOrder']);
        $t->same('EPUB/chapter1.xhtml', $ncx[0]['path']);
        $t->same(1, count($ncx[0]['children']));
        $t->same('Opening Note', $ncx[0]['children'][0]['label']);
        $t->same(2, $ncx[0]['children'][0]['playOrder']);
        $t->same('opening-note', $ncx[0]['children'][0]['fragment']);
        $t->same('Details', $ncx[1]['label']);
        $t->same(3, $ncx[1]['playOrder']);
        $t->same('details', $ncx[1]['fragment']);
    },
    'preserves epub nav and ncx label provenance for package review' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $toc = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'toc'
        ));
        $ncx = $epub['ncx'];

        $navLabel = $toc[0]['labelProvenance'];
        $t->same('xhtml-nav', $navLabel['source']);
        $t->same('a', $navLabel['element']);
        $t->same('Opening Packet', $navLabel['text']);
        $t->same('nav-opening-label', $navLabel['attributes']['id']);
        $t->same('source-label', $navLabel['attributes']['class']);
        $t->same('en', $navLabel['language']);
        $t->same('ltr', $navLabel['direction']);
        $t->same(['bodymatter'], $navLabel['epubTypes']);
        $t->same(1, $navLabel['imageLabelCount']);
        $t->same('images/cover.png', $navLabel['imageLabels'][0]['src']);
        $t->same('EPUB/images/cover.png', $navLabel['imageLabels'][0]['path']);
        $t->same('Cover label', $navLabel['imageLabels'][0]['alt']);
        $t->same('Cover thumbnail', $navLabel['imageLabels'][0]['title']);

        $ncxLabel = $ncx[0]['labelProvenance'];
        $t->same('ncx-navLabel', $ncxLabel['source']);
        $t->same('navLabel', $ncxLabel['element']);
        $t->same('Opening Packet', $ncxLabel['text']);
        $t->same('np-1-label', $ncxLabel['attributes']['id']);
        $t->same('source-label', $ncxLabel['attributes']['class']);
        $t->same('en', $ncxLabel['language']);
        $t->same('ltr', $ncxLabel['direction']);
        $t->same('np-1-text', $ncxLabel['textAttributes']['id']);
        $t->same('source-text', $ncxLabel['textAttributes']['class']);
    },
    'reports ncx hierarchy duplicate targets and play order diagnostics' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-ncx-review</dc:identifier>
    <dc:title>NCX Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine toc="ncx">
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/toc.ncx', <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <head>
    <meta name="dtb:uid" content="urn:reader-ncx-review"/>
  </head>
  <docTitle><text>NCX Review</text></docTitle>
  <navMap>
    <navPoint id="np-start" playOrder="1">
      <navLabel><text>Start</text></navLabel>
      <content src="chapter.xhtml#start"/>
      <navPoint id="np-overview" playOrder="2">
        <navLabel><text>Overview</text></navLabel>
        <content src="chapter.xhtml#overview"/>
      </navPoint>
      <navPoint id="np-overview-duplicate" playOrder="2">
        <navLabel><text>Duplicate Overview</text></navLabel>
        <content src="chapter.xhtml#overview"/>
      </navPoint>
    </navPoint>
    <navPoint id="np-closing" playOrder="1">
      <navLabel><text>Closing</text></navLabel>
      <content src="chapter.xhtml#closing"/>
    </navPoint>
  </navMap>
</ncx>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable NCX packet.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $ncx = $epub['ncx'];
            $report = $epub['ncxReport'];

            $t->same(2, count($ncx));
            $t->same('Start', $ncx[0]['label']);
            $t->same(2, count($ncx[0]['children']));
            $t->same('Duplicate Overview', $ncx[0]['children'][1]['label']);
            $t->same('Duplicate Overview', $ncx[0]['children'][1]['labelProvenance']['text']);
            $t->same('chapter.xhtml#overview', $ncx[0]['children'][1]['href']);
            $t->same('EPUB/chapter.xhtml', $ncx[0]['children'][1]['path']);
            $t->same('overview', $ncx[0]['children'][1]['fragment']);

            $t->same(4, $report['pointCount']);
            $t->same(2, $report['topLevelPointCount']);
            $t->same(2, $report['maxDepth']);
            $t->same(0, $report['missingPlayOrderCount']);
            $t->same(2, $report['nonIncreasingPlayOrderCount']);
            $t->same(1, $report['duplicateTargetCount']);
            $t->same(3, $report['diagnosticCount']);
            $t->same(['non-increasing-ncx-play-order', 'duplicate-ncx-target', 'non-increasing-ncx-play-order'], array_column($report['diagnostics'], 'type'));
            $t->same(2, $report['diagnostics'][0]['index']);
            $t->same('Duplicate Overview', $report['diagnostics'][0]['label']);
            $t->same(2, $report['diagnostics'][0]['playOrder']);
            $t->same(2, $report['diagnostics'][0]['previousPlayOrder']);
            $t->same(2, $report['diagnostics'][1]['index']);
            $t->same(1, $report['diagnostics'][1]['firstIndex']);
            $t->same('EPUB/chapter.xhtml#overview', $report['diagnostics'][1]['target']);
            $t->same('Overview', $report['diagnostics'][1]['firstLabel']);
            $t->same(3, $report['diagnostics'][2]['index']);
            $t->same('Closing', $report['diagnostics'][2]['label']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub nav landmarks relations and preserves label and ncx summaries' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-landmarks-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-landmarks-review</dc:identifier>
    <dc:title>Navigation Landmark Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine toc="ncx">
    <itemref idref="chapter"/>
  </spine>
  <guide>
    <reference type="text" title="Start reading" href="chapter.xhtml"/>
    <reference type="appendix" title="Appendix review" href="appendix.xhtml#appendix"/>
  </guide>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
    <nav id="landmark-source" epub:type="landmarks">
      <h2>Landmark routes</h2>
      <ol>
        <li id="body-li"><a id="body-link" epub:type="bodymatter" href="chapter.xhtml">Start</a></li>
        <li id="appendix-li"><a id="appendix-link" href="appendix.xhtml#appendix">Appendix without type</a></li>
        <li id="missing-li" epub:type="glossary"><a id="missing-link" href="missing.xhtml">Missing glossary</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/toc.ncx', <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="np-1" playOrder="1">
      <navLabel id="np-1-label"><text id="np-1-text">Chapter</text></navLabel>
      <content src="chapter.xhtml"/>
      <navPoint id="np-1-1" playOrder="2">
        <navLabel><text>Chapter section</text></navLabel>
        <content src="chapter.xhtml#section"/>
      </navPoint>
    </navPoint>
    <navPoint id="np-2" playOrder="3">
      <navLabel><text>Appendix</text></navLabel>
      <content src="appendix.xhtml#appendix"/>
    </navPoint>
  </navMap>
</ncx>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="section">Chapter</h1></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="appendix">Appendix</h1></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $landmarks = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'landmarks'
            ));
            $navReport = $epub['navReport'];
            $landmarkReport = $navReport['landmarks'];
            $ncxReport = $epub['ncxReport'];

            $t->same($navReport, $epub['tocReport']);
            $t->same(3, count($landmarks));
            $t->same('anchor', $landmarks[0]['labelProvenance']['source']);
            $t->same('body-link', $landmarks[0]['labelProvenance']['labelId']);
            $t->same(['bodymatter'], $landmarks[0]['labelTypes']);
            $t->same('label', $landmarks[0]['typeSource']);

            $t->same(true, $landmarkReport['present']);
            $t->same(3, $landmarkReport['itemCount']);
            $t->same(2, $landmarkReport['typedItemCount']);
            $t->same(1, $landmarkReport['missingTypeCount']);
            $t->same(3, $landmarkReport['targetedItemCount']);
            $t->same(1, $landmarkReport['spineMappedCount']);
            $t->same(2, $landmarkReport['manifestMappedCount']);
            $t->same(2, $landmarkReport['guideRelationCount']);
            $t->same(1, $landmarkReport['outsideSpineTargetCount']);
            $t->same(1, $landmarkReport['missingReferenceCount']);
            $t->same(3, $landmarkReport['diagnosticCount']);
            $t->same(['missing-landmark-nav-type', 'landmark-target-outside-spine', 'missing-landmark-reference'], array_column($landmarkReport['diagnostics'], 'type'));

            $body = $landmarkReport['items'][0];
            $t->same('Start', $body['label']);
            $t->same('bodymatter', $body['semanticType']);
            $t->same('chapter', $body['manifestId']);
            $t->same(0, $body['spineIndex']);
            $t->same('chapter', $body['spineIdref']);
            $t->same(['text'], $body['guideTypes']);
            $t->same([], $body['diagnostics']);

            $appendix = $landmarkReport['items'][1];
            $t->same([], $appendix['semanticTypes']);
            $t->same('appendix', $appendix['manifestId']);
            $t->same(null, $appendix['spineIndex']);
            $t->same(['appendix'], $appendix['guideTypes']);
            $t->same(['missing-landmark-nav-type', 'landmark-target-outside-spine'], array_column($appendix['diagnostics'], 'type'));

            $missing = $landmarkReport['items'][2];
            $t->same(['glossary'], $missing['itemTypes']);
            $t->same('item', $missing['typeSource']);
            $t->same(false, $missing['exists']);
            $t->same('missing-landmark-reference', $missing['diagnostics'][0]['type']);

            $t->same(true, $ncxReport['present']);
            $t->same(3, $ncxReport['itemCount']);
            $t->same(2, $ncxReport['topLevelItemCount']);
            $t->same(3, $ncxReport['playOrderCount']);
            $t->same(0, $ncxReport['diagnosticCount']);
            $t->same(['topLevelItemCount' => 2, 'branchItemCount' => 1, 'leafItemCount' => 2, 'maxDepth' => 1], $ncxReport['hierarchy']);
            $t->same('ncx-navLabel', $ncxReport['items'][0]['labelProvenance']['source']);
            $t->same('np-1-label', $ncxReport['items'][0]['labelProvenance']['labelId']);
            $t->same('np-1-text', $ncxReport['items'][0]['labelProvenance']['textId']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub toc accessibility labels roles and duplicate nav relations' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-toc-a11y-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-toc-a11y-review</dc:identifier>
    <dc:title>Navigation TOC Accessibility Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine toc="ncx">
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li id="chapter-li"><a id="chapter-link" href="chapter.xhtml">Chapter</a></li>
        <li id="duplicate-li"><a id="duplicate-link" href="chapter.xhtml" aria-label="Chapter duplicate"></a></li>
        <li id="role-li" epub:type="landmarks"><a id="role-link" href="appendix.xhtml">Appendix role leak</a></li>
        <li id="empty-li"><span id="empty-label"></span></li>
      </ol>
    </nav>
    <nav id="landmarks" epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="chapter.xhtml">Start</a></li>
      </ol>
    </nav>
    <nav id="pages" epub:type="page-list">
      <ol>
        <li><a href="chapter.xhtml#p1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/toc.ncx', <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="np-1" playOrder="1">
      <navLabel id="np-1-label"><text id="np-1-text">Chapter</text></navLabel>
      <content src="chapter.xhtml"/>
    </navPoint>
  </navMap>
</ncx>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="p1">Chapter</h1></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Appendix</h1></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $navReport = $epub['navReport'];
            $tocReport = $navReport['toc'];
            $tocItems = $tocReport['items'];

            $t->same($navReport, $epub['tocReport']);
            $t->same(true, $tocReport['present']);
            $t->same(4, $tocReport['itemCount']);
            $t->same(3, $tocReport['targetedItemCount']);
            $t->same(3, $tocReport['accessibilityLabelCount']);
            $t->same(1, $tocReport['missingLabelCount']);
            $t->same(1, $tocReport['missingTargetCount']);
            $t->same(1, $tocReport['roleConflictCount']);
            $t->same(1, $tocReport['duplicateTargetCount']);
            $t->same(2, $tocReport['landmarkRelationCount']);
            $t->same(0, $tocReport['pageListRelationCount']);
            $t->same(4, $tocReport['diagnosticCount']);
            $t->same(['toc-nav-role-conflict', 'missing-toc-target', 'missing-toc-accessibility-label', 'duplicate-toc-target'], array_column($tocReport['diagnostics'], 'type'));

            $t->same('Chapter', $tocItems[0]['accessibilityLabel']);
            $t->same('text', $tocItems[0]['accessibilityLabelSource']);
            $t->same('xhtml-nav', $tocItems[0]['labelProvenance']['source']);
            $t->same(['landmarks'], $tocItems[0]['relationSections']);
            $t->same(2, $tocItems[0]['duplicateTargetCount']);
            $t->same('duplicate-toc-target', $tocItems[0]['diagnostics'][0]['type']);

            $t->same('', $tocItems[1]['label']);
            $t->same('Chapter duplicate', $tocItems[1]['accessibilityLabel']);
            $t->same('aria-label', $tocItems[1]['accessibilityLabelSource']);
            $t->same(2, $tocItems[1]['duplicateTargetCount']);

            $t->same(['landmarks'], $tocItems[2]['itemTypes']);
            $t->same('item', $tocItems[2]['typeSource']);
            $t->same('toc-nav-role-conflict', $tocItems[2]['diagnostics'][0]['type']);

            $t->same('', $tocItems[3]['href']);
            $t->same('', $tocItems[3]['accessibilityLabel']);
            $t->same(null, $tocItems[3]['accessibilityLabelSource']);
            $t->same(['missing-toc-target', 'missing-toc-accessibility-label'], array_column($tocItems[3]['diagnostics'], 'type'));

            $ncxReport = $epub['ncxReport'];
            $t->same(true, $ncxReport['present']);
            $t->same(1, $ncxReport['itemCount']);
            $t->same(0, $ncxReport['diagnosticCount']);
            $t->same('ncx-navLabel', $ncxReport['items'][0]['labelProvenance']['source']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub nav fragment target diagnostics across toc landmarks and page list' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-fragments-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-fragment-review</dc:identifier>
    <dc:title>Navigation Fragment Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a id="known-link" href="chapter.xhtml#known">Known</a></li>
        <li><a href="chapter.xhtml#missing">Missing</a></li>
        <li><a href="chapter.xhtml#duplicate">Duplicate</a></li>
        <li><a href="chapter.xhtml">Whole chapter</a></li>
      </ol>
    </nav>
    <nav id="landmarks" epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="chapter.xhtml#known">Start</a></li>
      </ol>
    </nav>
    <nav id="pages" epub:type="page-list">
      <ol>
        <li><a href="chapter.xhtml#page-1">1</a></li>
        <li><a href="chapter.xhtml#missing-page">2</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1 id="known">Known section</h1>
    <section id="duplicate"><p>First duplicate anchor.</p></section>
    <aside id="duplicate"><p>Second duplicate anchor.</p></aside>
    <p id="page-1">Printed page one.</p>
  </body>
</html>
XML);

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $navReport = $epub['navReport'];
            $fragmentReport = $navReport['fragmentTargets'];
            $tocReport = $navReport['toc'];
            $landmarkReport = $navReport['landmarks'];
            $items = $fragmentReport['items'];

            $t->same($navReport, $epub['tocReport']);
            $t->same(true, $fragmentReport['present']);
            $t->same(7, $fragmentReport['itemCount']);
            $t->same(7, $fragmentReport['targetedItemCount']);
            $t->same(6, $fragmentReport['fragmentItemCount']);
            $t->same(1, $fragmentReport['fragmentlessTargetCount']);
            $t->same(3, $fragmentReport['resolvedFragmentCount']);
            $t->same(2, $fragmentReport['missingFragmentCount']);
            $t->same(1, $fragmentReport['duplicateFragmentCount']);
            $t->same(0, $fragmentReport['missingDocumentCount']);
            $t->same(['toc' => 4, 'landmarks' => 1, 'page-list' => 2], $fragmentReport['sectionTypeCounts']);
            $t->same(3, $fragmentReport['diagnosticCount']);
            $t->same(['missing-nav-fragment-target', 'duplicate-nav-fragment-target', 'missing-nav-fragment-target'], array_column($fragmentReport['diagnostics'], 'type'));

            $t->same('resolved-fragment', $items[0]['fragmentState']);
            $t->same('known', $items[0]['fragment']);
            $t->same(1, $items[0]['fragmentMatchCount']);
            $t->same(4, $items[0]['targetIdCount']);
            $t->same(3, $items[0]['targetUniqueIdCount']);
            $t->same('xhtml-nav', $items[0]['labelProvenance']['source']);
            $t->same('known-link', $items[0]['labelProvenance']['labelId']);

            $t->same('missing-fragment', $items[1]['fragmentState']);
            $t->same('missing-nav-fragment-target', $items[1]['diagnostics'][0]['type']);
            $t->same(0, $items[1]['fragmentMatchCount']);

            $t->same('duplicate-fragment', $items[2]['fragmentState']);
            $t->same('duplicate-nav-fragment-target', $items[2]['diagnostics'][0]['type']);
            $t->same(2, $items[2]['fragmentMatchCount']);

            $t->same('document', $items[3]['fragmentState']);
            $t->same('', $items[3]['fragment']);
            $t->same([], $items[3]['diagnostics']);

            $t->same('landmarks', $items[4]['sectionType']);
            $t->same('resolved-fragment', $items[4]['fragmentState']);
            $t->same('page-list', $items[5]['sectionType']);
            $t->same('resolved-fragment', $items[5]['fragmentState']);
            $t->same('page-list', $items[6]['sectionType']);
            $t->same('missing-fragment', $items[6]['fragmentState']);

            $t->same(4, $tocReport['itemCount']);
            $t->same(0, $tocReport['diagnosticCount']);
            $t->same('xhtml-nav', $tocReport['items'][0]['labelProvenance']['source']);
            $t->same(1, $tocReport['landmarkRelationCount']);
            $t->same(0, $tocReport['pageListRelationCount']);
            $t->same(1, $landmarkReport['itemCount']);
            $t->same(0, $landmarkReport['diagnosticCount']);
        } finally {
            $removeDirectory($root);
        }
    },
    'maps epub page-list navigation targets for print provenance' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $pageList = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'page-list'
        ));

        $t->same(2, count($pageList));
        $t->same('1', $pageList[0]['label']);
        $t->same('chapter1.xhtml#opening-title', $pageList[0]['href']);
        $t->same('EPUB/chapter1.xhtml', $pageList[0]['path']);
        $t->same('opening-title', $pageList[0]['fragment']);
        $t->same('page-list', $pageList[0]['type']);
        $t->same('2', $pageList[1]['label']);
        $t->same('EPUB/chapter2.xhtml', $pageList[1]['path']);
        $t->same('details', $pageList[1]['fragment']);

        $pageListReport = $epub['tocReport']['pageList'];
        $t->same($epub['navReport'], $epub['tocReport']);
        $t->same(true, $pageListReport['present']);
        $t->same(2, $pageListReport['itemCount']);
        $t->same(2, $pageListReport['pageBreakItemCount']);
        $t->same(2, $pageListReport['targetedItemCount']);
        $t->same(2, $pageListReport['manifestTargetCount']);
        $t->same(2, $pageListReport['spineReadingOrderTargetCount']);
        $t->same(0, $pageListReport['missingManifestTargetCount']);
        $t->same(0, $pageListReport['outsideSpineTargetCount']);
        $t->same(0, $pageListReport['externalTargetCount']);
        $t->same(0, $pageListReport['unresolvedTargetCount']);
        $t->same(0, $pageListReport['diagnosticCount']);
        $t->same('chapter1', $pageListReport['items'][0]['manifestId']);
        $t->same('application/xhtml+xml', $pageListReport['items'][0]['mediaType']);
        $t->same(0, $pageListReport['items'][0]['spineIndex']);
        $t->same('chapter1', $pageListReport['items'][0]['spineIdref']);
        $t->same(true, $pageListReport['items'][0]['inSpineReadingOrder']);
        $t->same('chapter2', $pageListReport['items'][1]['manifestId']);
        $t->same(1, $pageListReport['items'][1]['spineIndex']);
        $t->same(true, $pageListReport['items'][1]['spineLinear']);
    },
    'reports epub page-list manifest and spine reading order diagnostics' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-page-list-spine-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-page-list-spine-review</dc:identifier>
    <dc:title>Page List Spine Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="appendix" linear="no"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="chapter.xhtml#page-1">1</a></li>
        <li><a epub:type="pagebreak" href="stray.xhtml#page-2">2</a></li>
        <li><a epub:type="pagebreak" href="appendix.xhtml#page-a">A</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="page-1">Chapter</h1></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="page-a">Appendix</h1></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $pageList = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'page-list'
            ));
            $pageListReport = $epub['tocReport']['pageList'];
            $items = $pageListReport['items'];

            $t->same(['1', '2', 'A'], array_column($pageList, 'label'));
            $t->same('EPUB/stray.xhtml', $pageList[1]['path']);
            $t->same('EPUB/appendix.xhtml', $pageList[2]['path']);
            $t->same($epub['navReport'], $epub['tocReport']);
            $t->same(4, $epub['tocReport']['itemCount']);
            $t->same(true, $pageListReport['present']);
            $t->same(3, $pageListReport['itemCount']);
            $t->same(3, $pageListReport['pageBreakItemCount']);
            $t->same(3, $pageListReport['targetedItemCount']);
            $t->same(2, $pageListReport['manifestTargetCount']);
            $t->same(1, $pageListReport['spineReadingOrderTargetCount']);
            $t->same(1, $pageListReport['missingManifestTargetCount']);
            $t->same(1, $pageListReport['outsideSpineTargetCount']);
            $t->same(0, $pageListReport['externalTargetCount']);
            $t->same(0, $pageListReport['unresolvedTargetCount']);
            $t->same(2, $pageListReport['diagnosticCount']);
            $t->same(['missing-page-list-manifest-item', 'page-list-target-outside-spine-reading-order'], array_column($pageListReport['diagnostics'], 'type'));

            $t->same('chapter', $items[0]['manifestId']);
            $t->same(0, $items[0]['spineIndex']);
            $t->same('chapter', $items[0]['spineIdref']);
            $t->same(true, $items[0]['spineLinear']);
            $t->same(true, $items[0]['inSpineReadingOrder']);
            $t->same([], $items[0]['diagnostics']);

            $t->same(null, $items[1]['manifestId']);
            $t->same(false, $items[1]['inSpineReadingOrder']);
            $t->same(['missing-page-list-manifest-item'], array_column($items[1]['diagnostics'], 'type'));
            $t->same('EPUB/stray.xhtml', $items[1]['diagnostics'][0]['path']);

            $t->same('appendix', $items[2]['manifestId']);
            $t->same(1, $items[2]['spineIndex']);
            $t->same('appendix', $items[2]['spineIdref']);
            $t->same(false, $items[2]['spineLinear']);
            $t->same(false, $items[2]['inSpineReadingOrder']);
            $t->same(['page-list-target-outside-spine-reading-order'], array_column($items[2]['diagnostics'], 'type'));
            $t->same('nonlinear-spine-item', $items[2]['diagnostics'][0]['reason']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub nav external and unsafe href policy by section' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-policy-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-policy-review</dc:identifier>
    <dc:title>Navigation Policy Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml#safe">Safe chapter</a></li>
        <li><a href="http://example.invalid/remote.xhtml#r">HTTP resource</a></li>
        <li><a href="https://example.invalid/secure.xhtml?review=1">HTTPS resource</a></li>
      </ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a href="mailto:desk@example.invalid">Mail contact</a></li>
        <li><a href="data:text/html,&lt;p&gt;inline&lt;/p&gt;">Data payload</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="java&#x20;script:alert(1)">Script action</a></li>
        <li><a epub:type="pagebreak" href="../../outside.xhtml#outside">Package escape</a></li>
        <li><a epub:type="pagebreak" href="chapter.xhtml#page-2">Page 2</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="safe">Safe chapter</h1><p id="page-2">Page two.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $policy = $epub['navReport']['hrefPolicy'];
            $entriesByLabel = [];
            $collect = static function (array $entries) use (&$collect, &$entriesByLabel): void {
                foreach ($entries as $entry) {
                    $entriesByLabel[$entry['label']] = $entry;
                    $collect($entry['children']);
                }
            };
            $collect($epub['toc']);

            $t->same($epub['navReport'], $epub['navigationReport']);
            $t->same(true, $policy['present']);
            $t->same(3, $policy['sectionCount']);
            $t->same(['toc' => 1, 'landmarks' => 1, 'page-list' => 1], $policy['sectionTypeCounts']);
            $t->same(8, $policy['itemCount']);
            $t->same(8, $policy['targetedItemCount']);
            $t->same(2, $policy['localTargetCount']);
            $t->same(2, $policy['safeLocalTargetCount']);
            $t->same(6, $policy['externalTargetCount']);
            $t->same(2, $policy['unsafeTargetCount']);
            $t->same(8, $policy['diagnosticCount']);
            $t->same(['toc', 'landmarks', 'page-list'], array_column($policy['sections'], 'type'));
            $t->same([3, 2, 3], array_column($policy['sections'], 'itemCount'));
            $t->same([2, 2, 2], array_column($policy['sections'], 'externalTargetCount'));
            $t->same([0, 1, 1], array_column($policy['sections'], 'unsafeTargetCount'));

            $safe = $entriesByLabel['Safe chapter'];
            $t->same('EPUB/chapter.xhtml', $safe['path']);
            $t->same('safe', $safe['fragment']);
            $t->same('local', $safe['hrefKind']);
            $t->same(false, $safe['external']);
            $t->same(false, $safe['unsafe']);
            $t->same([], $safe['hrefDiagnostics']);
            $page = $entriesByLabel['Page 2'];
            $t->same('EPUB/chapter.xhtml', $page['path']);
            $t->same('page-2', $page['fragment']);
            $t->same([], $page['hrefDiagnostics']);

            $t->same('http', $entriesByLabel['HTTP resource']['hrefScheme']);
            $t->same(true, $entriesByLabel['HTTP resource']['external']);
            $t->same(false, $entriesByLabel['HTTP resource']['unsafe']);
            $t->same(['external-nav-href-target'], array_column($entriesByLabel['HTTP resource']['hrefDiagnostics'], 'type'));
            $t->same('https', $entriesByLabel['HTTPS resource']['hrefScheme']);
            $t->same(['external-nav-href-target'], array_column($entriesByLabel['HTTPS resource']['hrefDiagnostics'], 'type'));
            $t->same('mailto', $entriesByLabel['Mail contact']['hrefScheme']);
            $t->same(true, $entriesByLabel['Mail contact']['external']);
            $t->same(false, $entriesByLabel['Mail contact']['unsafe']);
            $t->same('data', $entriesByLabel['Data payload']['hrefScheme']);
            $t->same(true, $entriesByLabel['Data payload']['unsafe']);
            $t->same(['external-nav-href-target', 'unsafe-nav-href-target'], array_column($entriesByLabel['Data payload']['hrefDiagnostics'], 'type'));
            $t->same('javascript', $entriesByLabel['Script action']['hrefScheme']);
            $t->same('unsafe-uri', $entriesByLabel['Script action']['hrefKind']);
            $t->same(true, $entriesByLabel['Script action']['external']);
            $t->same(true, $entriesByLabel['Script action']['unsafe']);
            $t->same(['external-nav-href-target', 'unsafe-nav-href-target'], array_column($entriesByLabel['Script action']['hrefDiagnostics'], 'type'));
            $t->same('package-relative-external', $entriesByLabel['Package escape']['hrefKind']);
            $t->same('../../outside.xhtml', $entriesByLabel['Package escape']['path']);
            $t->same('outside', $entriesByLabel['Package escape']['fragment']);
            $t->same(true, $entriesByLabel['Package escape']['external']);
            $t->same(false, $entriesByLabel['Package escape']['unsafe']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub page-list pagebreak href and label diagnostics' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-pages-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-page-list-review</dc:identifier>
    <dc:title>Page List Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="chapter.xhtml#p1">1</a></li>
        <li><a epub:type="pagebreak" href="chapter.xhtml#p1">1</a></li>
        <li><a epub:type="pagebreak">3</a></li>
        <li><span epub:type="pagebreak">4</span></li>
        <li><a epub:type="pagebreak" href="chapter.xhtml#p5">   </a></li>
        <li><a href="chapter.xhtml#p6">6</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="p1">Chapter</h1><p>Readable page list package.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $pageList = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'page-list'
            ));
            $report = $epub['tocReport'];

            $t->same(7, $report['itemCount']);
            $t->same(['toc' => 1, 'page-list' => 6], $report['typeCounts']);
            $t->same(6, $report['pageListItemCount']);
            $t->same(5, $report['pageBreakItemCount']);
            $t->same(6, $report['diagnosticCount']);
            $t->same([
                'duplicate-page-list-href',
                'duplicate-page-list-label',
                'missing-page-list-href',
                'missing-page-list-href',
                'missing-page-list-label',
                'missing-pagebreak-type',
            ], array_column($report['diagnostics'], 'type'));
            $t->same(1, $report['diagnostics'][0]['firstIndex']);
            $t->same(2, $report['diagnostics'][0]['index']);
            $t->same('EPUB/chapter.xhtml#p1', $report['diagnostics'][0]['target']);
            $t->same('href', $report['diagnostics'][0]['source']);
            $t->same('1', $report['diagnostics'][1]['label']);
            $t->same('label', $report['diagnostics'][1]['source']);
            $t->same('a@href', $report['diagnostics'][2]['source']);
            $t->same('span@href', $report['diagnostics'][3]['source']);
            $t->same('textContent', $report['diagnostics'][4]['source']);
            $t->same('epub:type', $report['diagnostics'][5]['source']);

            $t->same(6, count($pageList));
            $t->same(true, $pageList[0]['pageBreakProvenance']['present']);
            $t->same(['pagebreak'], $pageList[0]['epubTypes']);
            $t->same('textContent', $pageList[0]['labelProvenance']['source']);
            $t->same([], $pageList[0]['diagnostics']);
            $t->same(['missing-page-list-href'], array_column($pageList[2]['diagnostics'], 'type'));
            $t->same(['missing-page-list-href'], array_column($pageList[3]['diagnostics'], 'type'));
            $t->same(['missing-page-list-label'], array_column($pageList[4]['diagnostics'], 'type'));
            $t->same(['missing-pagebreak-type'], array_column($pageList[5]['diagnostics'], 'type'));
            $t->same(false, $pageList[5]['pageBreakProvenance']['present']);
            $t->same('missing', $pageList[5]['pageBreakProvenance']['source']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub nav href normalization diagnostics by section' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-normalization-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-normalization</dc:identifier>
    <dc:title>Navigation Normalization</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="text/space%20file.xhtml#encoded-frag">Encoded Local</a></li>
        <li><a href="text/../chapter2.xhtml">Dot Local</a><ol>
          <li><a href="chapter2.xhtml#nested">Nested Local</a></li>
        </ol></li>
        <li><a href="https://example.invalid/book.xhtml#remote">Remote</a></li>
        <li><a href="../../outside.xhtml">Escape</a></li>
        <li><a href="casetarget.xhtml">Case Target</a></li>
        <li><a href="">Empty Target</a></li>
        <li><a href="chapter2.xhtml#details"> </a></li>
      </ol>
    </nav>
    <nav id="landmarks" epub:type="landmarks">
      <ol>
        <li><a href="chapter2.xhtml#details">Details</a></li>
      </ol>
    </nav>
    <nav id="pages" epub:type="page-list">
      <ol>
        <li><a href="text/space%20file.xhtml#page-1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter2.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/text/space file.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Encoded target.</p></body></html>');
            $writePackageFile($root, 'EPUB/CaseTarget.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Case target.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $toc = $epub['toc'];
            $report = $epub['navReport'];
            $normalization = $report['hrefNormalization'];

            $t->same(1, count($document->children));
            $t->same('Readable chapter.', $document->children[0]->attr('text'));
            $t->same(9, count($toc));
            $t->same(true, $normalization['present']);
            $t->same(3, $normalization['sectionCount']);
            $t->same(10, $normalization['entryCount']);
            $t->same(['toc' => 1, 'landmarks' => 1, 'page-list' => 1], $normalization['typeCounts']);
            $t->same(7, $normalization['localTargetCount']);
            $t->same(1, $normalization['externalTargetCount']);
            $t->same(1, $normalization['missingTargetCount']);
            $t->same(6, $normalization['fragmentTargetCount']);
            $t->same(4, $normalization['normalizedHrefCount']);
            $t->same(2, $normalization['percentDecodedHrefCount']);
            $t->same(1, $normalization['dotSegmentNormalizedHrefCount']);
            $t->same(1, $normalization['packageRootEscapeCount']);
            $t->same(1, $normalization['caseMismatchCount']);
            $t->same(1, $normalization['emptyHrefCount']);
            $t->same(14, $normalization['diagnosticCount']);
            $t->same([
                'nav-href-percent-decoded' => 2,
                'nav-href-fragment-component' => 6,
                'nav-href-dot-segment-normalized' => 1,
                'external-nav-reference' => 1,
                'nav-href-package-root-escape' => 1,
                'case-sensitive-nav-target-mismatch' => 1,
                'missing-nav-item-href' => 1,
                'empty-nav-item-label' => 1,
            ], $normalization['diagnosticTypes']);

            $tocSection = $normalization['sectionsByType']['toc'][0];
            $t->same('main-toc', $tocSection['id']);
            $t->same(8, $tocSection['entryCount']);
            $t->same(3, $tocSection['normalizedHrefCount']);
            $t->same(1, $tocSection['percentDecodedHrefCount']);
            $t->same(1, $tocSection['dotSegmentNormalizedHrefCount']);
            $t->same(1, $tocSection['packageRootEscapeCount']);
            $t->same(1, $tocSection['caseMismatchCount']);
            $t->same(1, $tocSection['emptyHrefCount']);
            $t->same(11, $tocSection['diagnosticCount']);
            $t->same(1, $normalization['sectionsByType']['landmarks'][0]['fragmentTargetCount']);
            $t->same(1, $normalization['sectionsByType']['page-list'][0]['percentDecodedHrefCount']);

            $encoded = $toc[0];
            $t->same('Encoded Local', $encoded['label']);
            $t->same('text/space%20file.xhtml#encoded-frag', $encoded['href']);
            $t->same('EPUB/text/space file.xhtml', $encoded['path']);
            $t->same('EPUB/text/space file.xhtml#encoded-frag', $encoded['target']);
            $t->same('encoded-frag', $encoded['fragment']);
            $t->same(true, $encoded['exists']);
            $t->same(true, $encoded['normalization']['percentDecoded']);
            $t->same(['nav-href-percent-decoded', 'nav-href-fragment-component'], array_column($encoded['diagnostics'], 'type'));

            $dot = $toc[1];
            $t->same('Dot Local', $dot['label']);
            $t->same('EPUB/chapter2.xhtml', $dot['path']);
            $t->same(true, $dot['exists']);
            $t->same(true, $dot['normalization']['dotSegmentNormalized']);
            $t->same(['nav-href-dot-segment-normalized'], array_column($dot['diagnostics'], 'type'));
            $t->same(1, count($dot['children']));
            $t->same('Nested Local', $dot['children'][0]['label']);
            $t->same(1, $dot['children'][0]['depth']);
            $t->same('nested', $dot['children'][0]['fragment']);

            $remote = $toc[2];
            $t->same(true, $remote['external']);
            $t->same('https://example.invalid/book.xhtml', $remote['path']);
            $t->same('https://example.invalid/book.xhtml#remote', $remote['target']);
            $t->same('remote', $remote['fragment']);
            $t->same(['external-nav-reference', 'nav-href-fragment-component'], array_column($remote['diagnostics'], 'type'));

            $escape = $toc[3];
            $t->same(true, $escape['external']);
            $t->same(false, $escape['unsafe']);
            $t->same('../../outside.xhtml', $escape['path']);
            $t->same('../../outside.xhtml', $escape['target']);
            $t->same(true, $escape['normalization']['packageRootEscape']);
            $t->same(['nav-href-package-root-escape'], array_column($escape['diagnostics'], 'type'));

            $caseMismatch = $toc[4];
            $t->same('EPUB/casetarget.xhtml', $caseMismatch['path']);
            $t->same(false, $caseMismatch['exists']);
            $t->same('EPUB/CaseTarget.xhtml', $caseMismatch['caseMatchedPath']);
            $t->same(true, $caseMismatch['normalization']['caseMismatch']);
            $t->same(['case-sensitive-nav-target-mismatch'], array_column($caseMismatch['diagnostics'], 'type'));

            $empty = $toc[5];
            $t->same('Empty Target', $empty['label']);
            $t->same('', $empty['href']);
            $t->same('', $empty['path']);
            $t->same(['missing-nav-item-href'], array_column($empty['diagnostics'], 'type'));

            $emptyLabel = $toc[6];
            $t->same('', $emptyLabel['label']);
            $t->same('details', $emptyLabel['fragment']);
            $t->same(['nav-href-fragment-component', 'empty-nav-item-label'], array_column($emptyLabel['diagnostics'], 'type'));

            $landmark = $toc[7];
            $t->same('landmarks', $landmark['type']);
            $t->same('EPUB/chapter2.xhtml', $landmark['path']);
            $t->same('details', $landmark['fragment']);

            $page = $toc[8];
            $t->same('page-list', $page['type']);
            $t->same('EPUB/text/space file.xhtml', $page['path']);
            $t->same('page-1', $page['fragment']);
            $t->same(true, $page['normalization']['percentDecoded']);
        } finally {
            $removeDirectory($root);
        }
    },
    'maps epub spine xhtml assets into shared ast and wordpress blocks' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(7, count($document->children));
        $t->same(['heading', 'paragraph', 'blockquote', 'heading', 'paragraph', 'bullet_list', 'definition_list'], array_map(
            static fn (AstNode $node): string => $node->type,
            $document->children
        ));

        $heading = $document->children[0];
        $intro = $document->children[1];
        $quote = $document->children[2];
        $details = $document->children[4];
        $list = $document->children[5];
        $definitions = $document->children[6];

        $t->same(1, $heading->attr('level'));
        $t->same('opening-title', $heading->attr('id'));
        $t->same('Opening Packet', $heading->attr('text'));
        $t->same('paragraph', $intro->type);
        $t->same('Intro EPUB packet with details and Cover image.', $intro->attr('text'));
        $t->same(['text', 'strong', 'text', 'link', 'text', 'image', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $intro->children
        ));
        $t->same('EPUB', $intro->children[1]->children[0]->attr('text'));
        $t->same('EPUB/chapter2.xhtml#details', $intro->children[3]->attr('url'));
        $t->same('Details', $intro->children[3]->attr('title'));
        $t->same('details', $intro->children[3]->children[0]->attr('text'));
        $t->same('EPUB/images/cover.png', $intro->children[5]->attr('url'));
        $t->same('Cover image', $intro->children[5]->attr('alt'));
        $t->same('Cover', $intro->children[5]->attr('title'));
        $t->same('blockquote', $quote->type);
        $t->same('Reviewer note with wp_insert_post.', $quote->children[0]->attr('text'));
        $t->same('code', $quote->children[0]->children[1]->type);
        $t->same('wp_insert_post', $quote->children[0]->children[1]->attr('text'));
        $t->same(['text', 'emph', 'linebreak', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $details->children
        ));
        $t->same('reading order', $details->children[1]->children[0]->attr('text'));
        $t->same(2, count($list->children));
        $t->same('First migration check', $list->children[0]->children[0]->attr('text'));
        $t->same('Second check with source', $list->children[1]->children[0]->attr('text'));
        $t->same('https://example.test/source', $list->children[1]->children[0]->children[1]->attr('url'));
        $t->same('Review status Resource note', $definitions->attr('text'));
        $t->same('review-glossary', $definitions->attr('htmlAttributes')['id']);
        $t->same('migration-terms', $definitions->attr('htmlAttributes')['class']);
        $t->same(2, count($definitions->children));
        $t->same('Review status', $definitions->children[0]->attr('term'));
        $t->same('Ready for direct XHTML handoff.', $definitions->children[0]->children[1]->children[0]->attr('text'));
        $t->same('strong', $definitions->children[0]->children[1]->children[0]->children[1]->type);
        $t->same('Resource note', $definitions->children[1]->attr('term'));
        $t->same(true, $definitions->children[1]->children[1]->attr('loose'));
        $t->same('EPUB/chapter1.xhtml#opening-note', $definitions->children[1]->children[1]->children[0]->children[1]->attr('url'));
        $t->same('bullet_list', $definitions->children[1]->children[1]->children[1]->type);
        $t->contains('<h1 id="opening-title">Opening Packet</h1>', $blocks);
        $t->contains('<strong>EPUB</strong>', $blocks);
        $t->contains('<a href="EPUB/chapter2.xhtml#details" title="Details">details</a>', $blocks);
        $t->contains('<img src="EPUB/images/cover.png" alt="Cover image" title="Cover"/>', $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p>Reviewer note with <code>wp_insert_post</code>.</p></blockquote>', $blocks);
        $t->contains('<em>reading order</em><br/>and a hard break.', $blocks);
        $t->contains('<li>First migration check</li><li>Second check with <a href="https://example.test/source">source</a></li>', $blocks);
        $t->contains('<dl id="review-glossary" class="migration-terms"><dt>Review status</dt><dd>Ready for <strong>direct XHTML</strong> handoff.</dd>', $blocks);
        $t->contains('<dt>Resource note</dt><dd><p>Keep package-local links like <a href="EPUB/chapter1.xhtml#opening-note">opening note</a> reviewable.</p><ul><li>Preserve nested checks.</li></ul></dd></dl>', $blocks);
    },
    'maps epub xhtml definition lists into shared ast and wordpress blocks' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $blocks = (new WordPressBlockWriter())->write($document);
        $definitions = $document->children[6];
        $firstItem = $definitions->children[0];
        $secondItem = $definitions->children[1];

        $t->same('definition_list', $definitions->type);
        $t->same('review-glossary', $definitions->attr('htmlAttributes')['id']);
        $t->same('migration-terms', $definitions->attr('htmlAttributes')['class']);
        $t->same('Review status', $firstItem->attr('term'));
        $t->same('term', $firstItem->children[0]->type);
        $t->same('Ready for direct XHTML handoff.', $firstItem->children[1]->children[0]->attr('text'));
        $t->same('direct XHTML', $firstItem->children[1]->children[0]->children[1]->children[0]->attr('text'));
        $t->same('Resource note', $secondItem->attr('term'));
        $t->same(true, $secondItem->children[1]->attr('loose'));
        $t->same('EPUB/chapter1.xhtml#opening-note', $secondItem->children[1]->children[0]->children[1]->attr('url'));
        $t->same('Preserve nested checks.', $secondItem->children[1]->children[1]->children[0]->children[0]->attr('text'));
        $t->contains('<dl id="review-glossary" class="migration-terms"><dt>Review status</dt><dd>Ready for <strong>direct XHTML</strong> handoff.</dd>', $blocks);
        $t->contains('<dt>Resource note</dt><dd><p>Keep package-local links like <a href="EPUB/chapter1.xhtml#opening-note">opening note</a> reviewable.</p><ul><li>Preserve nested checks.</li></ul></dd></dl>', $blocks);
    },
    'reports direct package manifest suffixes and skipped spine entries' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-manifest-review</dc:identifier>
    <dc:title>Manifest Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml?draft=review#appendix-start" media-type="application/xhtml+xml"/>
    <item id="remote" href="https://cdn.example.invalid/remote.xhtml?edition=review#remote" media-type="application/xhtml+xml"/>
    <item id="missing" href="missing.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="appendix" linear="no"/>
    <itemref idref="remote"/>
    <itemref idref="missing"/>
    <itemref idref="ghost"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Nonlinear appendix.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifest = $epub['manifestById'];
            $manifestReport = $epub['manifestReport'];
            $spine = $epub['spine'];
            $spineReport = $epub['spineReport'];

            $t->same(1, count($document->children));
            $t->same('Readable chapter.', $document->children[0]->attr('text'));
            $t->same('EPUB/appendix.xhtml?draft=review#appendix-start', $manifest['appendix']['target']);
            $t->same('EPUB/appendix.xhtml', $manifest['appendix']['path']);
            $t->same(true, $manifest['appendix']['exists']);
            $t->same(true, $manifest['appendix']['hrefHasQuery']);
            $t->same('draft=review', $manifest['appendix']['hrefQuery']);
            $t->same(true, $manifest['appendix']['hrefHasFragment']);
            $t->same('appendix-start', $manifest['appendix']['hrefFragment']);
            $t->same(['manifest-href-query-component', 'manifest-href-fragment-component'], array_column($manifest['appendix']['diagnostics'], 'type'));

            $t->same(true, $manifest['remote']['external']);
            $t->same('https://cdn.example.invalid/remote.xhtml?edition=review#remote', $manifest['remote']['target']);
            $t->same('https://cdn.example.invalid/remote.xhtml', $manifest['remote']['path']);
            $t->same(false, $manifest['remote']['exists']);
            $t->same(['external-manifest-href-target', 'manifest-href-query-component', 'manifest-href-fragment-component'], array_column($manifest['remote']['diagnostics'], 'type'));
            $t->same(false, $manifest['missing']['exists']);
            $t->same(['missing-manifest-href-target'], array_column($manifest['missing']['diagnostics'], 'type'));

            $t->same(4, $manifestReport['itemCount']);
            $t->same(1, $manifestReport['externalItemCount']);
            $t->same(1, $manifestReport['missingItemCount']);
            $t->same(2, $manifestReport['hrefSuffixCount']);
            $t->same(6, $manifestReport['diagnosticCount']);
            $t->same(['appendix', 'remote'], array_column($manifestReport['hrefSuffixItems'], 'id'));
            $t->same(['manifest-href-query-component', 'manifest-href-fragment-component', 'external-manifest-href-target', 'manifest-href-query-component', 'manifest-href-fragment-component', 'missing-manifest-href-target'], array_column($manifestReport['diagnostics'], 'type'));

            $t->same(5, $spineReport['itemCount']);
            $t->same(4, $spineReport['linearItemCount']);
            $t->same(1, $spineReport['nonlinearItemCount']);
            $t->same(1, $spineReport['readableItemCount']);
            $t->same(4, $spineReport['skippedItemCount']);
            $t->same(1, $spineReport['externalItemCount']);
            $t->same(1, $spineReport['missingPackagePartItemCount']);
            $t->same(1, $spineReport['missingManifestItemCount']);
            $t->same(3, $spineReport['diagnosticCount']);
            $t->same('EPUB/appendix.xhtml?draft=review#appendix-start', $spine[1]['target']);
            $t->same(false, $spine[1]['readable']);
            $t->same('external-spine-item', $spine[2]['diagnostics'][0]['type']);
            $t->same('missing-spine-item-package-part', $spine[3]['diagnostics'][0]['type']);
            $t->same('missing-spine-manifest-item', $spine[4]['diagnostics'][0]['type']);
        } finally {
            $removeDirectory($root);
        }
    },
    'rejects missing epub package directories before parsing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static function (): void {
            (new EpubPackageReader())->readDirectory(dirname(__DIR__) . '/fixtures/missing-epub-package');
        });
    },
];
