<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubPackage;
use PortLibs\Pandoc\ZipPackage;

$epubContainerXml = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$epub3OpfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:isbn:9780000000001</dc:identifier>
    <dc:title>WordPress Migration Guide</dc:title>
    <dc:creator id="creator">Data Liberation Team</dc:creator>
    <dc:language>en-US</dc:language>
    <meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="style" href="styles/book.css" media-type="text/css"/>
    <item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2" linear="no" properties="page-spread-right"/>
  </spine>
</package>
XML;

$epub3NavXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <head><title>Contents</title></head>
  <body>
    <nav epub:type="toc" id="toc">
      <h1>Contents</h1>
      <ol>
        <li>
          <a href="text/chapter1.xhtml">Introduction</a>
          <ol>
            <li><a href="text/chapter1.xhtml#install">Install notes</a></li>
          </ol>
        </li>
        <li><a href="text/chapter2.xhtml">Review checklist</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

$epub2OpfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="legacy-id">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="legacy-id">legacy-42</dc:identifier>
    <dc:title>Legacy Export</dc:title>
    <dc:creator>Classic Press</dc:creator>
    <dc:language>en</dc:language>
    <meta name="cover" content="legacy-cover"/>
  </metadata>
  <manifest>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="legacy-cover" href="cover.jpg" media-type="image/jpeg"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine toc="toc">
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

$epub2NcxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="navPoint-1" playOrder="1">
      <navLabel><text>Legacy Start</text></navLabel>
      <content src="chapter.xhtml"/>
      <navPoint id="navPoint-1-1" playOrder="2">
        <navLabel><text>Legacy Detail</text></navLabel>
        <content src="chapter.xhtml#detail"/>
      </navPoint>
    </navPoint>
  </navMap>
</ncx>
XML;

$epub3Package = static function () use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
        ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
        ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
        ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
        ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
        ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
        ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
        ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
    ], 'epub3 preflight');
};

$buildZipPackage = static function (array $entries): ZipPackage {
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'] ?? '';
        $method = $entry['method'] ?? 8;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $crc = (int) sprintf('%u', crc32($data));
        $offset = strlen($body);
        $flags = 0x0800;
        $externalAttributes = str_ends_with($name, '/') ? 0x10 : 0;

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0
        );
        $body .= $name . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0,
            0,
            0,
            0,
            $externalAttributes,
            $offset
        );
        $central .= $name;
    }

    $centralOffset = strlen($body);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $centralOffset, 0)
    );
};

return [
    'preflights EPUB3 container OPF metadata manifest spine and nav handoff' => static function (TestRunner $t) use ($epub3Package): void {
        $epub = EpubPackage::fromPackage($epub3Package());
        $summary = $epub->summary();

        $t->same('/EPUB/package.opf', $epub->opfPartName());
        $t->same('3.0', $epub->metadata()['version']);
        $t->same('urn:isbn:9780000000001', $epub->metadata()['identifier']);
        $t->same('WordPress Migration Guide', $epub->metadata()['title']);
        $t->same(['Data Liberation Team'], $epub->metadata()['creators']);
        $t->same('en-US', $epub->metadata()['language']);
        $t->same('2026-06-03T22:09:50Z', $epub->metadata()['modified']);

        $t->same('/EPUB/text/chapter1.xhtml', $epub->readingOrder()[0]['partName']);
        $t->same(true, $epub->readingOrder()[0]['linear']);
        $t->same('/EPUB/text/chapter2.xhtml', $epub->readingOrder()[1]['partName']);
        $t->same(false, $epub->readingOrder()[1]['linear']);
        $t->same(['page-spread-right'], $epub->readingOrder()[1]['properties']);

        $t->same('nav', $epub->navigation()['type']);
        $t->same('/EPUB/nav.xhtml', $epub->navigation()['partName']);
        $t->same('Introduction', $epub->navigation()['entries'][0]['label']);
        $t->same('/EPUB/text/chapter1.xhtml', $epub->navigation()['entries'][0]['target']);
        $t->same(1, $epub->navigation()['entries'][0]['depth']);
        $t->same('Install notes', $epub->navigation()['entries'][1]['label']);
        $t->same('/EPUB/text/chapter1.xhtml#install', $epub->navigation()['entries'][1]['target']);
        $t->same(2, $epub->navigation()['entries'][1]['depth']);
        $t->same('Review checklist', $epub->navigation()['entries'][2]['label']);

        $t->same('/EPUB/images/cover.png', $epub->assetSummary()['coverImagePart']);
        $t->same(['/EPUB/styles/book.css'], $epub->assetSummary()['stylesheetParts']);
        $t->same(['/EPUB/images/cover.png'], $epub->assetSummary()['imageParts']);
        $t->same(['/EPUB/nav.xhtml', '/EPUB/text/chapter1.xhtml', '/EPUB/text/chapter2.xhtml'], array_column($epub->xhtmlAssets(), 'partName'));
        $t->same(['Introduction', 'Install notes', 'Review checklist'], $summary['wordpressImport']['navigationLabels']);
        $t->same(['/EPUB/text/chapter1.xhtml', '/EPUB/text/chapter2.xhtml'], $summary['wordpressImport']['readingOrderParts']);
    },

    'falls back to NCX navigation and legacy cover metadata' => static function (TestRunner $t) use ($epubContainerXml, $epub2OpfXml, $epub2NcxXml): void {
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub2OpfXml],
            ['name' => 'EPUB/toc.ncx', 'data' => $epub2NcxXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Legacy</body></html>'],
            ['name' => 'EPUB/cover.jpg', 'data' => 'JPG'],
        ]));

        $t->same('2.0', $epub->metadata()['version']);
        $t->same('legacy-42', $epub->metadata()['identifier']);
        $t->same('/EPUB/cover.jpg', $epub->assetSummary()['coverImagePart']);
        $t->same('ncx', $epub->navigation()['type']);
        $t->same('/EPUB/toc.ncx', $epub->navigation()['partName']);
        $t->same('Legacy Start', $epub->navigation()['entries'][0]['label']);
        $t->same('/EPUB/chapter.xhtml', $epub->navigation()['entries'][0]['target']);
        $t->same('Legacy Detail', $epub->navigation()['entries'][1]['label']);
        $t->same('/EPUB/chapter.xhtml#detail', $epub->navigation()['entries'][1]['target']);
        $t->same(2, $epub->navigation()['entries'][1]['depth']);
    },

    'exposes OPF manifest items by id and preserves non-spine assets' => static function (TestRunner $t) use ($epub3Package): void {
        $epub = EpubPackage::fromPackage($epub3Package());

        $nav = $epub->manifestItem('nav');
        $style = $epub->manifestItem('style');
        $missing = $epub->manifestItem('missing');

        $t->same('/EPUB/nav.xhtml', $nav['partName']);
        $t->same(['nav'], $nav['properties']);
        $t->same('text/css', $style['mediaType']);
        $t->same('/EPUB/styles/book.css', $style['partName']);
        $t->same(null, $missing);
        $t->same(['nav', 'style', 'cover', 'chapter1', 'chapter2'], array_column($epub->manifestItems(), 'id'));
        $t->same(['chapter1', 'chapter2'], array_column($epub->spine(), 'idref'));
    },

    'preserves OPF manifest media type parameter provenance for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithMediaTypeParameters = str_replace(
            '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>',
            '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml; charset=UTF-8" properties="nav"/>',
            $epub3OpfXml
        );
        $opfWithMediaTypeParameters = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/book.css" media-type="text/css; charset=&quot;UTF-8&quot;"/>',
            $opfWithMediaTypeParameters
        );
        $opfWithMediaTypeParameters = str_replace(
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>',
            '<item id="cover" href="images/cover.png" media-type="image/png; profile=&quot;cover;review&quot;" properties="cover-image"/>',
            $opfWithMediaTypeParameters
        );
        $opfWithMediaTypeParameters = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml; charset=UTF-8"/>',
            $opfWithMediaTypeParameters
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMediaTypeParameters],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $nav = $epub->manifestItem('nav');
        $style = $epub->manifestItem('style');
        $cover = $epub->manifestItem('cover');
        $validation = $epub->validationReport();
        $manifestValidation = $validation['manifest'];
        $summary = $epub->summary();

        $t->same('application/xhtml+xml; charset=UTF-8', $nav['mediaType']);
        $t->same('application/xhtml+xml', $nav['mediaTypeBase']);
        $t->same(true, $nav['mediaTypeHasParameters']);
        $t->same(1, $nav['mediaTypeParameterCount']);
        $t->same([['name' => 'charset', 'value' => 'UTF-8', 'raw' => 'charset=UTF-8']], $nav['mediaTypeParameters']);
        $t->same(['charset' => 'UTF-8'], $nav['mediaTypeParameterMap']);
        $t->same('application/xhtml+xml; charset=utf-8', $nav['normalizedMediaType']);
        $t->same(true, $nav['mediaTypeSyntaxValid']);
        $t->same([], $nav['mediaTypeDiagnostics']);

        $t->same('text/css', $style['mediaTypeBase']);
        $t->same('charset="UTF-8"', $style['mediaTypeParameters'][0]['raw']);
        $t->same(['charset' => 'UTF-8'], $style['mediaTypeParameterMap']);
        $t->same('image/png', $cover['mediaTypeBase']);
        $t->same('profile="cover;review"', $cover['mediaTypeParameters'][0]['raw']);
        $t->same('cover;review', $cover['mediaTypeParameterMap']['profile']);

        $t->same('nav', $epub->navigation()['type']);
        $t->same('/EPUB/nav.xhtml', $epub->navigation()['partName']);
        $t->same(['/EPUB/nav.xhtml', '/EPUB/text/chapter1.xhtml', '/EPUB/text/chapter2.xhtml'], array_column($epub->xhtmlAssets(), 'partName'));
        $t->same(['/EPUB/styles/book.css'], $epub->assetSummary()['stylesheetParts']);
        $t->same(['/EPUB/images/cover.png'], $epub->assetSummary()['imageParts']);
        $t->same('/EPUB/images/cover.png', $epub->assetSummary()['coverImagePart']);

        $t->same(true, $validation['valid']);
        $t->same(0, $validation['diagnosticCount']);
        $t->same(4, $manifestValidation['mediaTypeParameterItemCount']);
        $t->same(4, $manifestValidation['mediaTypeParameterCount']);
        $t->same(['charset', 'profile'], $manifestValidation['mediaTypeParameterNames']);
        $t->same(['nav', 'style', 'cover', 'chapter1'], array_column($manifestValidation['mediaTypeParameterItems'], 'id'));
        $t->same('cover;review', $manifestValidation['mediaTypeParameterItems'][2]['parameterMap']['profile']);
        $t->same(0, $manifestValidation['mediaTypeDiagnosticCount']);
        $t->same([], $manifestValidation['mediaTypeDiagnostics']);
        $t->same($manifestValidation['mediaTypeParameterItems'], $summary['wordpressImport']['manifestMediaTypeParameterItems']);
        $t->same($manifestValidation['mediaTypeParameterNames'], $summary['wordpressImport']['manifestMediaTypeParameterNames']);
        $t->same([], $summary['wordpressImport']['manifestMediaTypeDiagnostics']);
    },

    'preserves OPF manifest href query and fragment suffixes for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithHrefSuffixes = str_replace(
            'href="styles/book.css"',
            'href="styles/book.css?revision=20260610"',
            $epub3OpfXml
        );
        $opfWithHrefSuffixes = str_replace(
            'href="text/chapter1.xhtml"',
            'href="text/chapter1.xhtml?source=archive#start"',
            $opfWithHrefSuffixes
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithHrefSuffixes],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $chapter = $epub->manifestItem('chapter1');
        $style = $epub->manifestItem('style');
        $validation = $epub->validationReport();
        $manifest = $validation['manifest'];
        $summary = $epub->summary();

        $t->same('/EPUB/text/chapter1.xhtml?source=archive#start', $chapter['target']);
        $t->same('/EPUB/text/chapter1.xhtml', $chapter['partName']);
        $t->same(true, $chapter['hrefHasQuery']);
        $t->same('source=archive', $chapter['hrefQuery']);
        $t->same(true, $chapter['hrefHasFragment']);
        $t->same('start', $chapter['hrefFragment']);
        $t->same('/EPUB/styles/book.css?revision=20260610', $style['target']);
        $t->same('/EPUB/styles/book.css', $style['partName']);
        $t->same(true, $style['hrefHasQuery']);
        $t->same('revision=20260610', $style['hrefQuery']);
        $t->same(false, $style['hrefHasFragment']);
        $t->same(null, $style['hrefFragment']);
        $t->same('/EPUB/text/chapter1.xhtml', $epub->readingOrder()[0]['partName']);
        $t->same('/EPUB/styles/book.css', $epub->assetSummary()['stylesheetParts'][0]);
        $t->same(false, $validation['valid']);
        $t->same(3, $validation['diagnosticCount']);
        $t->same(2, $manifest['hrefSuffixCount']);
        $t->same(['style', 'chapter1'], array_column($manifest['hrefSuffixItems'], 'id'));
        $t->same(['manifest-href-query-component', 'manifest-href-query-component', 'manifest-href-fragment-component'], array_column($manifest['diagnostics'], 'type'));
        $t->same('source=archive', $manifest['hrefSuffixItems'][1]['query']);
        $t->same('start', $manifest['hrefSuffixItems'][1]['fragment']);
        $t->same($manifest['hrefSuffixItems'], $summary['wordpressImport']['packageValidation']['manifest']['hrefSuffixItems']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
    },

    'reports external OPF manifest hrefs without aborting package ingestion' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithExternalManifestItem = str_replace(
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>',
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="remote-preview" href="https://cdn.example.invalid/previews/cover.jpg" media-type="image/jpeg"/>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithExternalManifestItem],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $remote = $epub->manifestItem('remote-preview');
        $validation = $epub->validationReport();
        $manifestValidation = $validation['manifest'];
        $summary = $epub->summary();

        $t->same('https://cdn.example.invalid/previews/cover.jpg', $remote['target']);
        $t->same(null, $remote['partName']);
        $t->same(true, $remote['external']);
        $t->same(false, $remote['exists']);
        $t->same(null, $remote['byteLength']);
        $t->same(false, $remote['canExposeBytes']);
        $t->same('external-manifest-href-target', $remote['diagnostics'][0]['type']);
        $t->same(['/EPUB/images/cover.png'], $epub->assetSummary()['imageParts']);

        $t->same(false, $validation['valid']);
        $t->same(['external-manifest-href-target'], array_column($validation['diagnostics'], 'type'));
        $t->same(0, $manifestValidation['missingItemCount']);
        $t->same(1, $manifestValidation['externalItemCount']);
        $t->same('remote-preview', $manifestValidation['externalItems'][0]['id']);
        $t->same('https://cdn.example.invalid/previews/cover.jpg', $manifestValidation['externalItems'][0]['target']);
        $t->same(1, $manifestValidation['itemDiagnosticCount']);
        $t->same('external-manifest-href-target', $manifestValidation['itemDiagnostics'][0]['type']);
        $t->same(null, $manifestValidation['itemDiagnostics'][0]['partName']);
        $t->same($manifestValidation['externalItems'], $summary['wordpressImport']['manifestExternalItems']);
        $t->same($manifestValidation['itemDiagnostics'], $summary['wordpressImport']['manifestItemDiagnostics']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
    },

    'records EPUB manifest ZIP compression provenance without inflating unsupported parts' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml, $buildZipPackage): void {
        $opfWithZipProvenance = str_replace(
            '</metadata>',
            '    <link id="review-source-link" rel="record" href="assets/source.bin" media-type="application/octet-stream"/>
  </metadata>',
            $epub3OpfXml
        );
        $opfWithZipProvenance = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="review-source" href="assets/source.bin" media-type="application/octet-stream"/>',
            $opfWithZipProvenance
        );
        $chapter1 = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>';
        $sourceBytes = 'UNSUPPORTED-RAW-REVIEW-PACKET';

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml, 'method' => 8],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithZipProvenance, 'method' => 8],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml, 'method' => 8],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => $chapter1, 'method' => 8],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>', 'method' => 8],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }', 'method' => 8],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG', 'method' => 0],
            ['name' => 'EPUB/assets/source.bin', 'data' => $sourceBytes, 'method' => 12],
        ]));
        $summary = $epub->summary();

        $source = $epub->manifestItem('review-source');
        $t->same(true, $source['exists']);
        $t->same(strlen($sourceBytes), $source['byteLength']);
        $t->same(strlen($sourceBytes), $source['compressedByteLength']);
        $t->same(12, $source['compressionMethod']);
        $t->same('unsupported', $source['compressionMethodName']);
        $t->same(false, $source['compressionSupported']);
        $t->same(hash('crc32b', $sourceBytes), $source['crc32']);
        $t->same(false, $source['canExposeBytes']);

        $cover = $epub->manifestItem('cover');
        $t->same(0, $cover['compressionMethod']);
        $t->same('stored', $cover['compressionMethodName']);
        $t->same(3, $cover['compressedByteLength']);
        $t->same(true, $cover['canExposeBytes']);

        $chapter = $epub->manifestItem('chapter1');
        $t->same(8, $chapter['compressionMethod']);
        $t->same('deflated', $chapter['compressionMethodName']);
        $t->same(strlen(gzdeflate($chapter1)), $chapter['compressedByteLength']);
        $t->same(true, $chapter['compressionSupported']);
        $t->same(true, $chapter['canExposeBytes']);

        $link = $epub->packageLinks()[0];
        $t->same('review-source-link', $link['id']);
        $t->same('review-source', $link['manifestId']);
        $t->same(12, $link['compressionMethod']);
        $t->same('unsupported', $link['compressionMethodName']);
        $t->same(false, $link['compressionSupported']);
        $t->same(false, $link['canExposeBytes']);
        $t->same($source, $summary['manifest'][5]);
        $t->same($link, $summary['packageLinks'][0]);
    },

    'preserves OCF metadata link ZIP provenance for package handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml, $buildZipPackage): void {
        $containerMetadataXml = <<<'XML'
<metadata xmlns="http://www.idpf.org/2013/metadata">
  <link id="container-review-record" rel="record" href="EPUB/meta/container-review.json" media-type="application/ld+json" properties="schema-org"/>
</metadata>
XML;
        $opfWithContainerRecord = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="container-review-record" href="meta/container-review.json" media-type="application/ld+json"/>',
            $epub3OpfXml
        );
        $recordBytes = '{"name":"container review"}';

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml, 'method' => 8],
            ['name' => 'META-INF/metadata.xml', 'data' => $containerMetadataXml, 'method' => 8],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithContainerRecord, 'method' => 8],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml, 'method' => 8],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>', 'method' => 8],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>', 'method' => 8],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }', 'method' => 8],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG', 'method' => 0],
            ['name' => 'EPUB/meta/container-review.json', 'data' => $recordBytes, 'method' => 12],
        ]));

        $link = $epub->containerLinks()[0];
        $manifestItem = $epub->manifestItem('container-review-record');
        $summary = $epub->summary();

        $t->same('container-review-record', $link['id']);
        $t->same('/EPUB/meta/container-review.json', $link['partName']);
        $t->same('container-review-record', $link['manifestId']);
        $t->same(true, $link['exists']);
        $t->same(strlen($recordBytes), $link['byteLength']);
        $t->same(strlen($recordBytes), $link['compressedByteLength']);
        $t->same(12, $link['compressionMethod']);
        $t->same('unsupported', $link['compressionMethodName']);
        $t->same(false, $link['compressionSupported']);
        $t->same(hash('crc32b', $recordBytes), $link['crc32']);
        $t->same(false, $link['canExposeBytes']);
        $t->same(false, $manifestItem['canExposeBytes']);
        $t->same($link, $summary['containerLinks'][0]);
        $t->same($link, $summary['wordpressImport']['containerLinks'][0]);
        $t->same('/EPUB/meta/container-review.json', $summary['wordpressImport']['containerLinkTargets'][0]);
    },

    'preserves compact OPF package root identity and direction for package handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithPackageAttributes = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" id="package-record" version="3.0" unique-identifier="bookid" xml:lang="ar" dir="rtl">',
            $epub3OpfXml
        );
        $opfWithPackageAttributes = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta refines="#package-record" property="schema:name" xml:lang="en" dir="ltr">Importer package record</meta>',
            $opfWithPackageAttributes
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithPackageAttributes],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $package = $metadata['package'];

        $t->same('package-record', $metadata['packageId']);
        $t->same('ar', $metadata['packageLanguage']);
        $t->same('rtl', $metadata['packageDirection']);
        $t->same('package-record', $package['id']);
        $t->same('3.0', $package['version']);
        $t->same('bookid', $package['uniqueIdentifierId']);
        $t->same('ar', $package['language']);
        $t->same('rtl', $package['direction']);
        $t->same('Importer package record', $package['refinements']['schema:name'][0]['content']);
        $t->same('#package-record', $package['refinements']['schema:name'][0]['refines']);
        $t->same('package-record', $package['refinements']['schema:name'][0]['subjectId']);
        $t->same('en', $package['refinements']['schema:name'][0]['language']);
        $t->same('ltr', $package['refinements']['schema:name'][0]['direction']);
        $t->same($package, $summary['metadata']['package']);
        $t->same($package, $summary['wordpressImport']['metadataDetails']['package']);
        $t->same('package-record', $summary['wordpressImport']['metadataDetails']['packageId']);
        $t->same('ar', $summary['wordpressImport']['metadataDetails']['packageLanguage']);
        $t->same('rtl', $summary['wordpressImport']['metadataDetails']['packageDirection']);
    },

    'preserves compact OPF spine itemref ids and refinement provenance' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithSpineRefinements = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/">',
            $epub3OpfXml
        );
        $opfWithSpineRefinements = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta refines="#chapter1-spine" property="rendition:viewport" xml:lang="en" dir="ltr">width=600,height=900</meta>
    <meta id="chapter1-spine-position" refines="#chapter1-spine" property="schema:position" content="primary reading-order entry"/>',
            $opfWithSpineRefinements
        );
        $opfWithSpineRefinements = str_replace(
            '<spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2" linear="no" properties="page-spread-right"/>
  </spine>',
            '<spine>
    <itemref id="chapter1-spine" idref="chapter1" linear="yes" properties="rendition:orientation-landscape"/>
    <itemref id="chapter2-spine" idref="chapter2" linear="no" properties="page-spread-right"/>
  </spine>',
            $opfWithSpineRefinements
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithSpineRefinements],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $spine = $epub->spine();
        $summary = $epub->summary();

        $t->same('chapter1-spine', $spine[0]['id']);
        $t->same('chapter1', $spine[0]['idref']);
        $t->same('yes', $spine[0]['linearRaw']);
        $t->same(true, $spine[0]['linear']);
        $t->same(['rendition:orientation-landscape'], $spine[0]['properties']);
        $t->same('width=600,height=900', $spine[0]['refinements']['rendition:viewport'][0]['content']);
        $t->same('#chapter1-spine', $spine[0]['refinements']['rendition:viewport'][0]['refines']);
        $t->same('chapter1-spine', $spine[0]['refinements']['rendition:viewport'][0]['subjectId']);
        $t->same('en', $spine[0]['refinements']['rendition:viewport'][0]['language']);
        $t->same('ltr', $spine[0]['refinements']['rendition:viewport'][0]['direction']);
        $t->same('chapter1-spine-position', $spine[0]['refinements']['schema:position'][0]['id']);
        $t->same('primary reading-order entry', $spine[0]['refinements']['schema:position'][0]['content']);
        $t->same('width=600,height=900', $spine[0]['renditionViewportRefinements'][0]['content']);
        $t->same('chapter1-spine', $spine[0]['renditionViewportRefinements'][0]['subjectId']);
        $t->same('chapter2-spine', $spine[1]['id']);
        $t->same('no', $spine[1]['linearRaw']);
        $t->same(false, $spine[1]['linear']);
        $t->same([], $spine[1]['refinements']);
        $t->same($spine, $epub->readingOrder());
        $t->same($spine[0]['refinements'], $summary['metadata']['refinementsById']['chapter1-spine']);
        $t->same('chapter1-spine', $summary['readingOrder'][0]['id']);
        $t->same($spine[0]['refinements'], $summary['readingOrder'][0]['refinements']);
    },

    'summarizes OPF spine page progression metadata for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithSpineProgression = str_replace(
            '<spine>',
            '<spine page-progression-direction="rtl">',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithSpineProgression],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $spineMetadata = $epub->spineMetadata();
        $summary = $epub->summary();
        $validation = $epub->validationReport();

        $t->same(true, $spineMetadata['pageProgressionDirectionSpecified']);
        $t->same('rtl', $spineMetadata['pageProgressionDirectionRaw']);
        $t->same('rtl', $spineMetadata['pageProgressionDirection']);
        $t->same('right-to-left', $spineMetadata['readingProgression']);
        $t->same(true, $spineMetadata['rightToLeft']);
        $t->same(false, $spineMetadata['leftToRight']);
        $t->same(false, $spineMetadata['defaultProgression']);
        $t->same(false, $spineMetadata['tocSpecified']);
        $t->same(null, $spineMetadata['tocId']);
        $t->same(true, $spineMetadata['valid']);
        $t->same([], $spineMetadata['diagnostics']);
        $t->same($spineMetadata, $summary['spineMetadata']);
        $t->same($spineMetadata, $validation['spine']['metadata']);
        $t->same($spineMetadata, $summary['wordpressImport']['spineMetadata']);
        $t->same('rtl', $summary['wordpressImport']['pageProgressionDirection']);
        $t->same('right-to-left', $summary['wordpressImport']['readingProgression']);
        $t->same([], $summary['wordpressImport']['spinePackageDiagnostics']);

        $invalidEpub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => str_replace('page-progression-direction="rtl"', 'page-progression-direction="sideways"', $opfWithSpineProgression)],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $invalidMetadata = $invalidEpub->spineMetadata();
        $invalidSummary = $invalidEpub->summary();
        $invalidValidation = $invalidEpub->validationReport();

        $t->same('sideways', $invalidMetadata['pageProgressionDirectionRaw']);
        $t->same(null, $invalidMetadata['pageProgressionDirection']);
        $t->same(null, $invalidMetadata['readingProgression']);
        $t->same(false, $invalidMetadata['valid']);
        $t->same(1, $invalidMetadata['diagnosticCount']);
        $t->same(['invalid-spine-page-progression-direction'], array_column($invalidMetadata['diagnostics'], 'type'));
        $t->same(false, $invalidValidation['spine']['valid']);
        $t->same('invalid-spine-page-progression-direction', $invalidValidation['spine']['diagnostics'][0]['type']);
        $t->same($invalidMetadata['diagnostics'], $invalidSummary['wordpressImport']['spinePackageDiagnostics']);
        $t->same($invalidMetadata, $invalidSummary['wordpressImport']['packageValidation']['spine']['metadata']);
    },

    'summarizes OPF spine itemref page-spread properties for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithPageSpread = str_replace(
            '<itemref idref="chapter1"/>',
            '<itemref idref="chapter1" properties="rendition:page-spread-left page-spread-left"/>',
            $epub3OpfXml
        );
        $opfWithPageSpread = str_replace(
            '<itemref idref="chapter2" linear="no" properties="page-spread-right"/>',
            '<itemref idref="chapter2" linear="no" properties="page-spread-right rendition:page-spread-center"/>',
            $opfWithPageSpread
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithPageSpread],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $readingOrder = $epub->readingOrder();
        $validation = $epub->validationReport();
        $summary = $epub->summary();

        $t->same('left', $readingOrder[0]['pageSpread']);
        $t->same(['rendition:page-spread-left', 'page-spread-left'], $readingOrder[0]['pageSpreadProperties']);
        $t->same(false, $readingOrder[0]['spineItemProperties']['pageSpread']['conflicting']);
        $t->same([], $readingOrder[0]['spineItemDiagnostics']);

        $t->same('right', $readingOrder[1]['pageSpread']);
        $t->same(['page-spread-right', 'rendition:page-spread-center'], $readingOrder[1]['pageSpreadProperties']);
        $t->same(true, $readingOrder[1]['spineItemProperties']['pageSpread']['conflicting']);
        $t->same(['right', 'center'], $readingOrder[1]['spineItemProperties']['pageSpread']['placements']);
        $t->same(['conflicting-spine-page-spread-properties'], array_column($readingOrder[1]['spineItemDiagnostics'], 'type'));

        $spineValidation = $validation['spine'];
        $t->same(false, $spineValidation['valid']);
        $t->same(2, $spineValidation['pageSpreadCount']);
        $t->same(1, $spineValidation['pageSpreadLeftCount']);
        $t->same(1, $spineValidation['pageSpreadRightCount']);
        $t->same(0, $spineValidation['pageSpreadCenterCount']);
        $t->same('left', $spineValidation['pageSpreadItems'][0]['placement']);
        $t->same('right', $spineValidation['pageSpreadItems'][1]['placement']);
        $t->same(true, $spineValidation['pageSpreadItems'][1]['conflicting']);
        $t->same(1, $spineValidation['itemDiagnosticCount']);
        $t->same('conflicting-spine-page-spread-properties', $spineValidation['itemDiagnostics'][0]['type']);
        $t->same(1, $spineValidation['itemDiagnostics'][0]['index']);
        $t->same('chapter2', $spineValidation['itemDiagnostics'][0]['idref']);
        $t->same(['right', 'center'], $spineValidation['itemDiagnostics'][0]['placements']);
        $t->same($spineValidation['pageSpreadItems'], $summary['wordpressImport']['spinePageSpreadItems']);
        $t->same($spineValidation['itemDiagnostics'], $summary['wordpressImport']['spineItemDiagnostics']);
        $t->same('conflicting-spine-page-spread-properties', $summary['wordpressImport']['packageValidationDiagnostics'][0]['type']);
    },

    'summarizes OPF media-type bindings for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $handlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Slideshow fallback</h1></body></html>';
        $opfWithBindings = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="slideshow" href="widgets/source-slideshow.bin" media-type="application/x-demo-slideshow"/>
    <item id="slideshow-handler" href="widgets/slideshow-fallback.xhtml" media-type="application/xhtml+xml" properties="scripted"/>',
            $epub3OpfXml
        );
        $opfWithBindings = str_replace(
            '</spine>',
            '</spine>
  <bindings>
    <mediaType media-type="application/x-demo-slideshow" handler="slideshow-handler"/>
    <mediaType media-type="application/x-review-widget" handler="missing-handler"/>
    <mediaType handler="slideshow-handler"/>
  </bindings>',
            $opfWithBindings
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithBindings],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/widgets/source-slideshow.bin', 'data' => 'SLIDESHOW'],
            ['name' => 'EPUB/widgets/slideshow-fallback.xhtml', 'data' => $handlerXhtml],
        ]));

        $bindings = $epub->bindings();
        $summary = $epub->summary();

        $t->same(true, $bindings['present']);
        $t->same(3, $bindings['itemCount']);
        $t->same(['application/x-demo-slideshow', 'application/x-review-widget'], $bindings['boundMediaTypes']);
        $t->same('application/x-demo-slideshow', $bindings['items'][0]['mediaType']);
        $t->same('slideshow-handler', $bindings['items'][0]['handlerId']);
        $t->same('widgets/slideshow-fallback.xhtml', $bindings['items'][0]['handlerHref']);
        $t->same('/EPUB/widgets/slideshow-fallback.xhtml', $bindings['items'][0]['handlerPartName']);
        $t->same('application/xhtml+xml', $bindings['items'][0]['handlerMediaType']);
        $t->same(['scripted'], $bindings['items'][0]['handlerProperties']);
        $t->same(true, $bindings['items'][0]['handlerExists']);
        $t->same(strlen($handlerXhtml), $bindings['items'][0]['handlerByteLength']);
        $t->same([], $bindings['items'][0]['diagnostics']);
        $t->same('application/x-review-widget', $bindings['items'][1]['mediaType']);
        $t->same('missing-handler', $bindings['items'][1]['handlerId']);
        $t->same(false, $bindings['items'][1]['handlerExists']);
        $t->same(null, $bindings['items'][1]['handlerPartName']);
        $t->same('missing-binding-handler-manifest-item', $bindings['items'][1]['diagnostics'][0]['type']);
        $t->same(null, $bindings['items'][2]['mediaType']);
        $t->same('missing-binding-media-type', $bindings['items'][2]['diagnostics'][0]['type']);
        $t->same(2, count($bindings['diagnostics']));
        $t->same(1, $bindings['diagnostics'][0]['index']);
        $t->same(2, $bindings['diagnostics'][1]['index']);
        $t->same($bindings, $summary['bindings']);
        $t->same($bindings['items'], $summary['wordpressImport']['mediaTypeBindings']);
        $t->same($bindings['diagnostics'], $summary['wordpressImport']['mediaTypeBindingDiagnostics']);
    },

    'preserves OPF binding handler target provenance for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $localHandlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Local widget fallback</h1></body></html>';
        $opfWithBindingTargets = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="local-handler" href="widgets/local-handler.xhtml?mode=review#boot" media-type="application/xhtml+xml" properties="scripted"/>
    <item id="remote-handler" href="https://cdn.example.invalid/widgets/remote-handler.xhtml?profile=review#boot" media-type="application/xhtml+xml" properties="scripted remote-resources"/>',
            $epub3OpfXml
        );
        $opfWithBindingTargets = str_replace(
            '</spine>',
            '</spine>
  <bindings>
    <mediaType media-type="application/x-local-widget" handler="local-handler"/>
    <mediaType media-type="application/x-remote-widget" handler="remote-handler"/>
  </bindings>',
            $opfWithBindingTargets
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithBindingTargets],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/widgets/local-handler.xhtml', 'data' => $localHandlerXhtml],
        ]));

        $bindings = $epub->bindings();
        $summary = $epub->summary();
        $local = $bindings['items'][0];
        $remote = $bindings['items'][1];

        $t->same(true, $bindings['present']);
        $t->same(2, $bindings['itemCount']);
        $t->same(['application/x-local-widget', 'application/x-remote-widget'], $bindings['boundMediaTypes']);
        $t->same('application/x-local-widget', $local['mediaType']);
        $t->same('local-handler', $local['handlerId']);
        $t->same('widgets/local-handler.xhtml?mode=review#boot', $local['handlerHref']);
        $t->same('/EPUB/widgets/local-handler.xhtml?mode=review#boot', $local['handlerTarget']);
        $t->same('/EPUB/widgets/local-handler.xhtml', $local['handlerPartName']);
        $t->same(false, $local['handlerExternal']);
        $t->same(true, $local['handlerExists']);
        $t->same(true, $local['handlerCanExposeBytes']);
        $t->same(true, $local['handlerHrefHasQuery']);
        $t->same('mode=review', $local['handlerHrefQuery']);
        $t->same(true, $local['handlerHrefHasFragment']);
        $t->same('boot', $local['handlerHrefFragment']);
        $t->same([], $local['handlerManifestDiagnostics']);
        $t->same([], $local['diagnostics']);
        $t->same('application/x-remote-widget', $remote['mediaType']);
        $t->same('remote-handler', $remote['handlerId']);
        $t->same('https://cdn.example.invalid/widgets/remote-handler.xhtml?profile=review#boot', $remote['handlerHref']);
        $t->same('https://cdn.example.invalid/widgets/remote-handler.xhtml?profile=review#boot', $remote['handlerTarget']);
        $t->same(null, $remote['handlerPartName']);
        $t->same(true, $remote['handlerExternal']);
        $t->same(false, $remote['handlerExists']);
        $t->same(false, $remote['handlerCanExposeBytes']);
        $t->same(true, $remote['handlerHrefHasQuery']);
        $t->same('profile=review', $remote['handlerHrefQuery']);
        $t->same(true, $remote['handlerHrefHasFragment']);
        $t->same('boot', $remote['handlerHrefFragment']);
        $t->same('external-manifest-href-target', $remote['handlerManifestDiagnostics'][0]['type']);
        $t->same('external-binding-handler', $remote['diagnostics'][0]['type']);
        $t->same('https://cdn.example.invalid/widgets/remote-handler.xhtml?profile=review#boot', $remote['diagnostics'][0]['handlerTarget']);
        $t->same(1, count($bindings['diagnostics']));
        $t->same(1, $bindings['diagnostics'][0]['index']);
        $t->same('external-binding-handler', $bindings['diagnostics'][0]['type']);
        $t->same($bindings, $summary['bindings']);
        $t->same($bindings['items'], $summary['wordpressImport']['mediaTypeBindings']);
        $t->same($bindings['diagnostics'], $summary['wordpressImport']['mediaTypeBindingDiagnostics']);
    },

    'flags encrypted OPF binding handlers for compact package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $handlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Locked widget fallback</h1></body></html>';
        $opfWithEncryptedBinding = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="locked-handler" href="widgets/locked-handler.xhtml" media-type="application/xhtml+xml" properties="scripted"/>',
            $epub3OpfXml
        );
        $opfWithEncryptedBinding = str_replace(
            '</spine>',
            '</spine>
  <bindings>
    <mediaType media-type="application/x-locked-widget" handler="locked-handler"/>
  </bindings>',
            $opfWithEncryptedBinding
        );
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/widgets/locked-handler.xhtml"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithEncryptedBinding],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/widgets/locked-handler.xhtml', 'data' => $handlerXhtml],
        ]));

        $bindings = $epub->bindings();
        $summary = $epub->summary();
        $handler = $bindings['items'][0];
        $diagnostic = $handler['diagnostics'][0];

        $t->same(true, $bindings['present']);
        $t->same(1, $bindings['itemCount']);
        $t->same('locked-handler', $handler['handlerId']);
        $t->same('/EPUB/widgets/locked-handler.xhtml', $handler['handlerPartName']);
        $t->same(true, $handler['handlerExists']);
        $t->same(true, $handler['handlerEncrypted']);
        $t->same(false, $handler['handlerCanExposeBytes']);
        $t->same(strlen($handlerXhtml), $handler['handlerByteLength']);
        $t->same('xhtml', $handler['handlerEncryption']['role']);
        $t->same('encrypted-resource-review', $handler['handlerEncryption']['reviewPolicy']);
        $t->same('encrypted-resource-bytes-blocked', $handler['handlerEncryption']['byteExposurePolicy']);
        $t->same('encrypted-binding-handler', $diagnostic['type']);
        $t->same('application/x-locked-widget', $diagnostic['mediaType']);
        $t->same('/EPUB/widgets/locked-handler.xhtml', $diagnostic['handlerPartName']);
        $t->same('encrypted-resource-review', $diagnostic['reviewPolicy']);
        $t->same(1, count($bindings['diagnostics']));
        $t->same(0, $bindings['diagnostics'][0]['index']);
        $t->same('encrypted-binding-handler', $bindings['diagnostics'][0]['type']);
        $t->same($bindings, $summary['bindings']);
        $t->same($bindings['items'], $summary['wordpressImport']['mediaTypeBindings']);
        $t->same($bindings['diagnostics'], $summary['wordpressImport']['mediaTypeBindingDiagnostics']);
    },

    'preserves OPF metadata refinements for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithRefinements = str_replace(
            '<dc:title>WordPress Migration Guide</dc:title>',
            '<dc:title id="main-title" xml:lang="en">WordPress Migration Guide</dc:title>
    <dc:title id="subtitle-title" xml:lang="es" dir="ltr">Guia de migracion</dc:title>',
            $epub3OpfXml
        );
        $opfWithRefinements = str_replace(
            '</metadata>',
            '    <meta refines="#main-title" property="title-type">main</meta>
    <meta refines="#main-title" property="file-as">WordPress Migration Guide, The</meta>
    <meta refines="#main-title" property="display-seq">1</meta>
    <meta refines="#subtitle-title" property="title-type">subtitle</meta>
    <meta refines="#subtitle-title" property="alternate-script" xml:lang="en" dir="ltr">Migration guide subtitle</meta>
    <meta refines="#creator" property="role" scheme="marc:relators">aut</meta>
    <meta refines="#creator" property="file-as">Team, Data Liberation</meta>
    <meta refines="#bookid" property="identifier-type" scheme="onix:codelist5">15</meta>
  </metadata>',
            $opfWithRefinements
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithRefinements],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();

        $t->same('WordPress Migration Guide', $metadata['title']);
        $t->same(['WordPress Migration Guide', 'Guia de migracion'], $metadata['titles']);
        $t->same('main', $metadata['mainTitle']['titleType']);
        $t->same('WordPress Migration Guide, The', $metadata['mainTitle']['fileAs']);
        $t->same('1', $metadata['mainTitle']['displaySeq']);
        $t->same('en', $metadata['mainTitle']['language']);
        $t->same('WordPress Migration Guide, The', $metadata['sortTitle']);
        $t->same('subtitle', $metadata['subtitle']['titleType']);
        $t->same('Guia de migracion', $metadata['titlesByType']['subtitle'][0]['text']);
        $t->same('Migration guide subtitle', $metadata['subtitle']['alternateScripts'][0]['text']);
        $t->same('en', $metadata['subtitle']['alternateScripts'][0]['language']);
        $t->same('ltr', $metadata['subtitle']['alternateScripts'][0]['direction']);

        $t->same('Data Liberation Team', $metadata['creatorDetails'][0]['text']);
        $t->same('Team, Data Liberation', $metadata['creatorDetails'][0]['fileAs']);
        $t->same(['aut'], $metadata['creatorDetails'][0]['roleValues']);
        $t->same('aut', $metadata['creatorDetails'][0]['primaryRole']);
        $t->same('Data Liberation Team', $metadata['creatorsByRole']['aut'][0]['text']);

        $t->same('urn:isbn:9780000000001', $metadata['identifierDetails'][0]['value']);
        $t->same('bookid', $metadata['identifierDetails'][0]['id']);
        $t->same('15', $metadata['identifierDetails'][0]['identifierType']);
        $t->same('onix:codelist5', $metadata['identifierDetails'][0]['identifierTypes'][0]['scheme']);
        $t->same('urn:isbn:9780000000001', $metadata['identifiersByType']['15'][0]['value']);
        $t->same('#bookid', $metadata['refinementsById']['bookid']['identifier-type'][0]['refines']);
        $t->same('#main-title', $metadata['refinementsById']['main-title']['file-as'][0]['refines']);

        $t->same('WordPress Migration Guide, The', $summary['wordpressImport']['metadataDetails']['sortTitle']);
        $t->same('Guia de migracion', $summary['wordpressImport']['metadataDetails']['titlesByType']['subtitle'][0]['text']);
        $t->same('Data Liberation Team', $summary['wordpressImport']['metadataDetails']['creatorsByRole']['aut'][0]['text']);
        $t->same('urn:isbn:9780000000001', $summary['wordpressImport']['metadataDetails']['identifiersByType']['15'][0]['value']);
    },

    'preflights OPF unique identifier and duplicate identifier diagnostics for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithIdentifierDiagnostics = str_replace(
            '<dc:identifier id="bookid">urn:isbn:9780000000001</dc:identifier>',
            '<dc:identifier id="bookid" scheme="UUID">urn:uuid:primary-source</dc:identifier>
    <dc:identifier id="bookid" scheme="UUID">urn:uuid:secondary-source</dc:identifier>
    <dc:identifier id="isbn-id" scheme="ISBN">9780000000001</dc:identifier>
    <dc:identifier id="duplicate-isbn" scheme="ISBN">9780000000001</dc:identifier>',
            $epub3OpfXml
        );
        $opfWithIdentifierDiagnostics = str_replace(
            '</metadata>',
            '    <meta refines="#bookid" property="identifier-type" scheme="onix:codelist5">15</meta>
    <meta refines="#duplicate-isbn" property="identifier-type" scheme="onix:codelist5">15</meta>
  </metadata>',
            $opfWithIdentifierDiagnostics
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithIdentifierDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $uniqueIdentifier = $metadata['uniqueIdentifier'];
        $identifierSummary = $metadata['identifierSummary'];
        $identifierDetails = $metadata['identifierDetails'];

        $t->same('urn:uuid:primary-source', $metadata['identifier']);
        $t->same(true, $uniqueIdentifier['specified']);
        $t->same('bookid', $uniqueIdentifier['id']);
        $t->same(true, $uniqueIdentifier['matched']);
        $t->same('urn:uuid:primary-source', $uniqueIdentifier['value']);
        $t->same('unique-identifier', $uniqueIdentifier['selectedBy']);
        $t->same(4, $uniqueIdentifier['identifierCount']);
        $t->same(2, $uniqueIdentifier['matchCount']);
        $t->same(1, $uniqueIdentifier['duplicateMatchCount']);
        $t->same(false, $uniqueIdentifier['valid']);
        $t->same('duplicate-unique-identifier-id', $uniqueIdentifier['diagnostics'][0]['type']);
        $t->same(['urn:uuid:primary-source', 'urn:uuid:secondary-source'], $uniqueIdentifier['diagnostics'][0]['values']);

        $t->same(true, $identifierDetails[0]['selectedByUniqueIdentifier']);
        $t->same(true, $identifierDetails[1]['selectedByUniqueIdentifier']);
        $t->same(false, $identifierDetails[0]['duplicateValue']);
        $t->same(true, $identifierDetails[2]['duplicateValue']);
        $t->same(['isbn-id', 'duplicate-isbn'], $identifierDetails[2]['duplicateIds']);
        $t->same([2, 3], $identifierDetails[2]['duplicateIndexes']);
        $t->same('15', $identifierDetails[3]['identifierType']);

        $t->same(true, $identifierSummary['present']);
        $t->same(4, $identifierSummary['count']);
        $t->same(3, $identifierSummary['typedCount']);
        $t->same(['UUID', 'ISBN'], $identifierSummary['schemes']);
        $t->same(['15'], $identifierSummary['identifierTypes']);
        $t->same('urn:uuid:primary-source', $identifierSummary['selectedValue']);
        $t->same('bookid', $identifierSummary['selectedId']);
        $t->same(0, $identifierSummary['selectedIndex']);
        $t->same(1, $identifierSummary['duplicateValueCount']);
        $t->same('9780000000001', $identifierSummary['duplicatesByValue'][0]['value']);
        $t->same('duplicate-metadata-identifier-value', $identifierSummary['diagnostics'][0]['type']);

        $t->same(['duplicate-unique-identifier-id', 'duplicate-metadata-identifier-value'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $metadata['identifierDiagnostics']));
        $t->same($uniqueIdentifier, $summary['wordpressImport']['metadataDetails']['uniqueIdentifier']);
        $t->same($identifierSummary, $summary['wordpressImport']['metadataDetails']['identifierSummary']);
        $t->same($metadata['identifierDiagnostics'], $summary['wordpressImport']['metadataDetails']['identifierDiagnostics']);
    },

    'summarizes OPF date event metadata for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithDates = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:language>en-US</dc:language>
    <dc:date id="published" scheme="W3CDTF" event="publication">2026-06-01</dc:date>
    <dc:date id="reviewed" xml:lang="en" dir="ltr">2026-06-05</dc:date>',
            $epub3OpfXml
        );
        $opfWithDates = str_replace(
            '</metadata>',
            '    <meta refines="#reviewed" property="event">review</meta>
    <meta refines="#reviewed" property="display-seq">2</meta>
    <meta refines="#reviewed" property="alternate-script" xml:lang="fr" dir="ltr">5 juin 2026</meta>
  </metadata>',
            $opfWithDates
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithDates],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $dateDetails = $metadata['dateDetails'];

        $t->same(['2026-06-01', '2026-06-05'], $metadata['dates']);
        $t->same('2026-06-01', $metadata['date']);
        $t->same(2, count($dateDetails));
        $t->same('published', $dateDetails[0]['id']);
        $t->same('W3CDTF', $dateDetails[0]['scheme']);
        $t->same('publication', $dateDetails[0]['event']);
        $t->same('attribute', $dateDetails[0]['eventSource']);
        $t->same('reviewed', $dateDetails[1]['id']);
        $t->same('review', $dateDetails[1]['event']);
        $t->same('refinement', $dateDetails[1]['eventSource']);
        $t->same('2', $dateDetails[1]['displaySeq']);
        $t->same('5 juin 2026', $dateDetails[1]['alternateScripts'][0]['text']);
        $t->same('fr', $dateDetails[1]['alternateScripts'][0]['language']);
        $t->same('2026-06-01', $metadata['datesByEvent']['publication'][0]['text']);
        $t->same('2026-06-05', $metadata['datesByEvent']['review'][0]['text']);
        $t->same([
            'present' => true,
            'count' => 2,
            'eventCount' => 2,
            'events' => ['publication', 'review'],
        ], $metadata['dateSummary']);
        $t->same($dateDetails, $summary['wordpressImport']['metadataDetails']['dateDetails']);
        $t->same($metadata['datesByEvent'], $summary['wordpressImport']['metadataDetails']['datesByEvent']);
        $t->same($metadata['dateSummary'], $summary['wordpressImport']['metadataDetails']['dateSummary']);
    },

    'summarizes OPF language declarations for compact package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithLanguageMetadata = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:language id="lang-primary" scheme="BCP47" xml:lang="en">en-US</dc:language>
    <dc:language id="lang-secondary" xml:lang="fr">fr-CA</dc:language>
    <dc:language id="lang-duplicate">en-us</dc:language>
    <dc:language id="lang-invalid">review language</dc:language>',
            $epub3OpfXml
        );
        $opfWithLanguageMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta refines="#lang-primary" property="display-seq">1</meta>
    <meta refines="#lang-secondary" property="display-seq">2</meta>',
            $opfWithLanguageMetadata
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithLanguageMetadata],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $languageDetails = $metadata['languageDetails'];
        $languageSummary = $metadata['languageSummary'];

        $t->same('en-US', $metadata['language']);
        $t->same(['en-US', 'fr-CA', 'en-us', 'review language'], $metadata['languages']);
        $t->same(4, count($languageDetails));

        $primary = $languageDetails[0];
        $t->same('language', $primary['kind']);
        $t->same(0, $primary['index']);
        $t->same('lang-primary', $primary['id']);
        $t->same('en-US', $primary['tag']);
        $t->same('en-us', $primary['normalizedTag']);
        $t->same('en', $primary['primarySubtag']);
        $t->same('US', $primary['regionSubtag']);
        $t->same(true, $primary['wellFormed']);
        $t->same('BCP47', $primary['scheme']);
        $t->same('en', $primary['language']);
        $t->same('1', $primary['displaySeq']);
        $t->same(1, $primary['displaySeqNumber']);
        $t->same(true, $primary['duplicateTag']);
        $t->same([0, 2], $primary['duplicateIndexes']);
        $t->same('duplicate-language-tag', $primary['diagnostics'][0]['type']);

        $secondary = $languageDetails[1];
        $t->same('fr-CA', $secondary['tag']);
        $t->same('fr-ca', $secondary['normalizedTag']);
        $t->same('fr', $secondary['primarySubtag']);
        $t->same('CA', $secondary['regionSubtag']);
        $t->same(false, $secondary['duplicateTag']);
        $t->same('2', $secondary['displaySeq']);
        $t->same(2, $secondary['displaySeqNumber']);

        $invalid = $languageDetails[3];
        $t->same('review language', $invalid['tag']);
        $t->same(false, $invalid['wellFormed']);
        $t->same('invalid-language-tag', $invalid['diagnostics'][0]['type']);

        $t->same(true, $languageSummary['present']);
        $t->same(4, $languageSummary['count']);
        $t->same('en-US', $languageSummary['primaryLanguage']);
        $t->same(2, $languageSummary['uniqueTagCount']);
        $t->same(['en-us', 'fr-ca'], $languageSummary['normalizedTags']);
        $t->same(2, $languageSummary['primarySubtagCount']);
        $t->same(['en', 'fr'], $languageSummary['primarySubtags']);
        $t->same(['US', 'CA'], $languageSummary['regionSubtags']);
        $t->same(1, $languageSummary['duplicateTagCount']);
        $t->same(['en-us'], $languageSummary['duplicateTags']);
        $t->same(1, $languageSummary['invalidTagCount']);
        $t->same(['duplicate-language-tag', 'duplicate-language-tag', 'invalid-language-tag'], array_column($languageSummary['diagnostics'], 'type'));
        $t->same([$languageDetails[0], $languageDetails[2]], $metadata['languagesByPrimarySubtag']['en']);
        $t->same([$languageDetails[1]], $metadata['languagesByPrimarySubtag']['fr']);
        $t->same($languageDetails, $summary['wordpressImport']['metadataDetails']['languageDetails']);
        $t->same($metadata['languagesByPrimarySubtag'], $summary['wordpressImport']['metadataDetails']['languagesByPrimarySubtag']);
        $t->same($languageSummary, $summary['wordpressImport']['metadataDetails']['languageSummary']);
    },

    'summarizes OPF source provenance metadata for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithSources = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:language>en-US</dc:language>
    <dc:source id="print-source" scheme="ISBN" xml:lang="en">9781491905012</dc:source>
    <dc:source id="archive-source" xml:lang="fr" dir="ltr">Archive scan packet A</dc:source>',
            $epub3OpfXml
        );
        $opfWithSources = str_replace(
            '</metadata>',
            '    <meta refines="#print-source" property="source-of">pagination</meta>
    <meta refines="#print-source" property="identifier-type" scheme="onix:codelist5">15</meta>
    <meta refines="#print-source" property="display-seq">1</meta>
    <meta refines="#archive-source" property="source-of">transcription</meta>
    <meta refines="#archive-source" property="alternate-script" xml:lang="en" dir="ltr">Archive scan packet A translated</meta>
  </metadata>',
            $opfWithSources
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithSources],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $sourceDetails = $metadata['sourceDetails'];

        $t->same(['9781491905012', 'Archive scan packet A'], $metadata['sources']);
        $t->same('9781491905012', $metadata['source']);
        $t->same(2, count($sourceDetails));
        $t->same('print-source', $sourceDetails[0]['id']);
        $t->same('ISBN', $sourceDetails[0]['scheme']);
        $t->same('en', $sourceDetails[0]['language']);
        $t->same('pagination', $sourceDetails[0]['sourceOf']);
        $t->same(['pagination'], $sourceDetails[0]['sourceOfValues']);
        $t->same('15', $sourceDetails[0]['identifierType']);
        $t->same('onix:codelist5', $sourceDetails[0]['identifierTypes'][0]['scheme']);
        $t->same('1', $sourceDetails[0]['displaySeq']);
        $t->same('archive-source', $sourceDetails[1]['id']);
        $t->same('fr', $sourceDetails[1]['language']);
        $t->same('ltr', $sourceDetails[1]['direction']);
        $t->same('transcription', $sourceDetails[1]['sourceOf']);
        $t->same('Archive scan packet A translated', $sourceDetails[1]['alternateScripts'][0]['text']);
        $t->same('en', $sourceDetails[1]['alternateScripts'][0]['language']);
        $t->same('9781491905012', $metadata['sourcesByType']['pagination'][0]['text']);
        $t->same('Archive scan packet A', $metadata['sourcesByType']['transcription'][0]['text']);
        $t->same([
            'present' => true,
            'count' => 2,
            'typedCount' => 2,
            'schemeCount' => 1,
            'identifierTypeCount' => 1,
            'sourceOfValues' => ['pagination', 'transcription'],
            'identifierTypes' => ['15'],
            'schemes' => ['ISBN'],
        ], $metadata['sourceSummary']);

        $t->same($sourceDetails, $summary['wordpressImport']['metadataDetails']['sourceDetails']);
        $t->same($metadata['sourcesByType'], $summary['wordpressImport']['metadataDetails']['sourcesByType']);
        $t->same($metadata['sourceSummary'], $summary['wordpressImport']['metadataDetails']['sourceSummary']);
    },

    'summarizes OPF bibliographic Dublin Core metadata for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithBibliographicMetadata = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:subject>Data Liberation</dc:subject>
    <dc:description id="summary" xml:lang="en" dir="ltr">Importer review summary.</dc:description>
    <dc:publisher id="publisher">Migration Publisher</dc:publisher>
    <dc:rights id="license" xml:lang="en">Creative Commons Attribution-ShareAlike 4.0</dc:rights>
    <dc:type id="resource-type" scheme="dcterms:DCMIType">Text</dc:type>
    <dc:format id="format" scheme="IANA">application/epub+zip</dc:format>
    <dc:relation id="source-post">https://example.test/posts/42</dc:relation>
    <dc:coverage id="coverage">Global migration packet</dc:coverage>
    <dc:language>en-US</dc:language>',
            $epub3OpfXml
        );
        $opfWithBibliographicMetadata = str_replace(
            '</metadata>',
            '    <meta refines="#license" property="authority">Creative Commons</meta>
    <meta refines="#license" property="term">CC-BY-SA-4.0</meta>
    <meta refines="#resource-type" property="authority">DCMI Type Vocabulary</meta>
    <meta refines="#resource-type" property="term">Text</meta>
    <meta refines="#source-post" property="display-seq">1</meta>
    <meta refines="#source-post" property="file-as">Post 42</meta>
    <meta refines="#coverage" property="alternate-script" xml:lang="fr">Dossier de migration mondial</meta>
  </metadata>',
            $opfWithBibliographicMetadata
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithBibliographicMetadata],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $details = $metadata['bibliographicDetails'];
        $byKind = $metadata['bibliographicDetailsByKind'];
        $bibliographicSummary = $metadata['bibliographicSummary'];

        $t->same(['Data Liberation'], $metadata['subjects']);
        $t->same('Importer review summary.', $metadata['description']);
        $t->same('Migration Publisher', $metadata['publisher']);
        $t->same(7, count($details));
        $t->same(['description', 'publisher', 'rights', 'type', 'format', 'relation', 'coverage'], $bibliographicSummary['kinds']);
        $t->same(7, $bibliographicSummary['count']);
        $t->same(7, $bibliographicSummary['kindCount']);
        $t->same(2, $bibliographicSummary['authorityCount']);
        $t->same(2, $bibliographicSummary['termCount']);
        $t->same(0, $bibliographicSummary['linkedResourceCount']);
        $t->same(1, $bibliographicSummary['kindCounts']['rights']);
        $t->same([], $bibliographicSummary['diagnostics']);

        $description = $byKind['description'][0];
        $t->same('summary', $description['id']);
        $t->same('Importer review summary.', $description['text']);
        $t->same('en', $description['language']);
        $t->same('ltr', $description['direction']);

        $rights = $byKind['rights'][0];
        $t->same('license', $rights['id']);
        $t->same('Creative Commons Attribution-ShareAlike 4.0', $rights['text']);
        $t->same('Creative Commons', $rights['authority']);
        $t->same('CC-BY-SA-4.0', $rights['term']);
        $t->same('Creative Commons', $rights['authorityEntries'][0]['text']);
        $t->same('CC-BY-SA-4.0', $rights['termEntries'][0]['value']);

        $type = $byKind['type'][0];
        $t->same('resource-type', $type['id']);
        $t->same('dcterms:DCMIType', $type['scheme']);
        $t->same('DCMI Type Vocabulary', $type['authority']);
        $t->same('Text', $type['term']);

        $format = $byKind['format'][0];
        $t->same('IANA', $format['scheme']);
        $t->same('application/epub+zip', $format['text']);

        $relation = $byKind['relation'][0];
        $t->same('source-post', $relation['id']);
        $t->same('https://example.test/posts/42', $relation['text']);
        $t->same('1', $relation['displaySeq']);
        $t->same('Post 42', $relation['fileAs']);

        $coverage = $byKind['coverage'][0];
        $t->same('Global migration packet', $coverage['text']);
        $t->same('Dossier de migration mondial', $coverage['alternateScripts'][0]['text']);
        $t->same('fr', $coverage['alternateScripts'][0]['language']);

        $t->same($details, $summary['wordpressImport']['metadataDetails']['bibliographicDetails']);
        $t->same($byKind, $summary['wordpressImport']['metadataDetails']['bibliographicDetailsByKind']);
        $t->same($bibliographicSummary, $summary['wordpressImport']['metadataDetails']['bibliographicSummary']);
    },

    'preserves OPF guide references and XHTML nav sections for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithGuide = str_replace(
            '</spine>',
            '</spine>
  <guide>
    <reference type="text" title="Start reading" href="text/chapter1.xhtml?source=guide#install"/>
    <reference type="cover" title="Cover image" href="images/cover.png"/>
    <reference type="glossary" title="Legacy glossary" href="https://example.invalid/glossary.xhtml"/>
  </guide>',
            $epub3OpfXml
        );
        $navWithSections = str_replace(
            '</body>',
            '    <nav epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="text/chapter1.xhtml#install">Start reading</a></li>
        <li><a epub:type="backmatter bibliography" href="text/chapter2.xhtml#refs">References</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter1.xhtml#page-1">1</a></li>
        <li><a epub:type="pagebreak" href="text/chapter2.xhtml#page-2">2</a></li>
      </ol>
    </nav>
  </body>',
            $epub3NavXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithGuide],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithSections],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="install">Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="refs">References</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $summary = $epub->summary();

        $t->same(['text', 'cover', 'glossary'], array_column($epub->guideReferences(), 'type'));
        $t->same('/EPUB/text/chapter1.xhtml?source=guide#install', $epub->guideReferences()[0]['target']);
        $t->same('/EPUB/text/chapter1.xhtml', $epub->guideReferences()[0]['partName']);
        $t->same(true, $epub->guideReferences()[0]['exists']);
        $t->same(true, $epub->guideReferences()[0]['hrefHasQuery']);
        $t->same('source=guide', $epub->guideReferences()[0]['hrefQuery']);
        $t->same(true, $epub->guideReferences()[0]['hrefHasFragment']);
        $t->same('install', $epub->guideReferences()[0]['hrefFragment']);
        $t->same('/EPUB/images/cover.png', $epub->guideReferences()[1]['partName']);
        $t->same(false, $epub->guideReferences()[1]['hrefHasQuery']);
        $t->same(false, $epub->guideReferences()[1]['hrefHasFragment']);
        $t->same('https://example.invalid/glossary.xhtml', $epub->guideReferences()[2]['target']);
        $t->same(true, $epub->guideReferences()[2]['external']);

        $t->same(['toc', 'landmarks', 'page-list'], array_column($epub->navigationSections(), 'type'));
        $t->same(['Introduction', 'Install notes', 'Review checklist'], array_column($epub->navigationSections()[0]['entries'], 'label'));
        $t->same(['Start reading', 'References'], array_column($epub->navigationSections()[1]['entries'], 'label'));
        $t->same(['/EPUB/text/chapter1.xhtml#install', '/EPUB/text/chapter2.xhtml#refs'], array_column($epub->navigationSections()[1]['entries'], 'target'));
        $t->same(['1', '2'], array_column($epub->navigationSections()[2]['entries'], 'label'));
        $t->same(['/EPUB/text/chapter1.xhtml#page-1', '/EPUB/text/chapter2.xhtml#page-2'], array_column($summary['wordpressImport']['pageListTargets'], 'target'));
        $t->same($epub->guideReferences(), $summary['wordpressImport']['guideReferences']);
        $t->same(['/EPUB/text/chapter1.xhtml?source=guide#install', '/EPUB/images/cover.png', 'https://example.invalid/glossary.xhtml'], array_column($summary['wordpressImport']['guideReferences'], 'target'));
    },

    'summarizes OPF guide reference provenance for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $chapter1 = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="install">Intro</h1></body></html>';
        $opfWithGuide = str_replace(
            '</spine>',
            '</spine>
  <guide>
    <reference type="text" title="Start reading" href="text/chapter1.xhtml#install"/>
    <reference type="cover" title="Cover thumbnail" href="images/cover.png?rendition=thumb"/>
    <reference type="appendix" title="Missing appendix" href="text/missing.xhtml"/>
    <reference type="glossary" title="Remote glossary" href="https://example.invalid/glossary.xhtml"/>
    <reference type="loi" title="Loose illustration" href="images/unmanifested.svg"/>
    <reference type="toc" title="Guide entry without href"/>
  </guide>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithGuide],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => $chapter1],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/images/unmanifested.svg', 'data' => '<svg xmlns="http://www.w3.org/2000/svg"/>'],
        ]));
        $guide = $epub->guideReferences();
        $report = $epub->guideReport();
        $summary = $epub->summary();

        $t->same(6, count($guide));
        $t->same('chapter1', $guide[0]['manifestId']);
        $t->same('application/xhtml+xml', $guide[0]['manifestMediaType']);
        $t->same(strlen($chapter1), $guide[0]['byteLength']);
        $t->same(true, $guide[0]['canExposeBytes']);
        $t->same(true, $guide[0]['hrefHasFragment']);
        $t->same('install', $guide[0]['hrefFragment']);
        $t->same('cover', $guide[1]['manifestId']);
        $t->same(true, $guide[1]['hrefHasQuery']);
        $t->same('rendition=thumb', $guide[1]['hrefQuery']);
        $t->same(false, $guide[2]['exists']);
        $t->same('missing-guide-reference-target', $guide[2]['diagnostics'][0]['type']);
        $t->same(true, $guide[3]['external']);
        $t->same('external-guide-reference-target', $guide[3]['diagnostics'][0]['type']);
        $t->same(true, $guide[4]['exists']);
        $t->same(null, $guide[4]['manifestId']);
        $t->same('guide-reference-target-not-in-manifest', $guide[4]['diagnostics'][0]['type']);
        $t->same(null, $guide[5]['href']);
        $t->same('missing-guide-reference-href', $guide[5]['diagnostics'][0]['type']);

        $t->same(true, $report['present']);
        $t->same(6, $report['referenceCount']);
        $t->same(6, $report['typeCount']);
        $t->same(['text', 'cover', 'appendix', 'glossary', 'loi', 'toc'], $report['types']);
        $t->same(3, $report['localTargetCount']);
        $t->same(1, $report['externalTargetCount']);
        $t->same(1, $report['missingTargetCount']);
        $t->same(1, $report['missingHrefCount']);
        $t->same(2, $report['manifestLinkedTargetCount']);
        $t->same(['/EPUB/text/chapter1.xhtml#install', '/EPUB/images/cover.png?rendition=thumb', '/EPUB/text/missing.xhtml', 'https://example.invalid/glossary.xhtml', '/EPUB/images/unmanifested.svg'], $report['targets']);
        $t->same(['/EPUB/text/chapter1.xhtml#install', '/EPUB/images/cover.png?rendition=thumb', '/EPUB/images/unmanifested.svg'], $report['localTargets']);
        $t->same(['https://example.invalid/glossary.xhtml'], $report['externalTargets']);
        $t->same(['/EPUB/text/missing.xhtml'], $report['missingTargets']);
        $t->same(['chapter1', 'cover'], array_column($report['manifestLinkedTargets'], 'manifestId'));
        $t->same(4, $report['diagnosticCount']);
        $t->same(['missing-guide-reference-target', 'external-guide-reference-target', 'guide-reference-target-not-in-manifest', 'missing-guide-reference-href'], array_column($report['diagnostics'], 'type'));
        $t->same($report, $summary['guideReport']);
        $t->same($report, $summary['wordpressImport']['guideReferenceReport']);
        $t->same($report['targets'], $summary['wordpressImport']['guideReferenceTargets']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['guideReferenceDiagnostics']);
    },

    'preserves OPF guide reference manifest media type parameter provenance' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithGuideMediaTypes = str_replace(
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>',
            '<item id="cover" href="images/cover.png" media-type="image/png; profile=&quot;guide-cover&quot;" properties="cover-image"/>',
            $epub3OpfXml
        );
        $opfWithGuideMediaTypes = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml; charset=UTF-8"/>',
            $opfWithGuideMediaTypes
        );
        $opfWithGuideMediaTypes = str_replace(
            '</spine>',
            '</spine>
  <guide>
    <reference type="text" title="Start reading" href="text/chapter1.xhtml#install"/>
    <reference type="cover" title="Cover thumbnail" href="images/cover.png?rendition=thumb"/>
  </guide>',
            $opfWithGuideMediaTypes
        );

        $chapter1 = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="install">Intro</h1></body></html>';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithGuideMediaTypes],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => $chapter1],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $guide = $epub->guideReferences();
        $report = $epub->guideReport();
        $summary = $epub->summary();

        $t->same(2, count($guide));
        $t->same('chapter1', $guide[0]['manifestId']);
        $t->same('application/xhtml+xml; charset=UTF-8', $guide[0]['manifestMediaType']);
        $t->same('application/xhtml+xml; charset=utf-8', $guide[0]['manifestNormalizedMediaType']);
        $t->same('application/xhtml+xml', $guide[0]['manifestMediaTypeBase']);
        $t->same(true, $guide[0]['manifestMediaTypeHasParameters']);
        $t->same(1, $guide[0]['manifestMediaTypeParameterCount']);
        $t->same([['name' => 'charset', 'value' => 'UTF-8', 'raw' => 'charset=UTF-8']], $guide[0]['manifestMediaTypeParameters']);
        $t->same(['charset' => 'UTF-8'], $guide[0]['manifestMediaTypeParameterMap']);
        $t->same(true, $guide[0]['manifestMediaTypeSyntaxValid']);
        $t->same([], $guide[0]['manifestMediaTypeDiagnostics']);
        $t->same('cover', $guide[1]['manifestId']);
        $t->same('image/png; profile="guide-cover"', $guide[1]['manifestMediaType']);
        $t->same('image/png', $guide[1]['manifestMediaTypeBase']);
        $t->same(['profile' => 'guide-cover'], $guide[1]['manifestMediaTypeParameterMap']);

        $t->same(2, $report['manifestLinkedTargetCount']);
        $t->same(['application/xhtml+xml', 'image/png'], array_column($report['manifestLinkedTargets'], 'manifestMediaTypeBase'));
        $t->same(2, $report['manifestMediaTypeParameterItemCount']);
        $t->same(2, $report['manifestMediaTypeParameterCount']);
        $t->same(['charset', 'profile'], $report['manifestMediaTypeParameterNames']);
        $t->same(['chapter1', 'cover'], array_column($report['manifestMediaTypeParameterItems'], 'manifestId'));
        $t->same('guide-cover', $report['manifestMediaTypeParameterItems'][1]['parameterMap']['profile']);
        $t->same(0, $report['manifestMediaTypeDiagnosticCount']);
        $t->same([], $report['manifestMediaTypeDiagnostics']);
        $t->same($report['manifestMediaTypeParameterItems'], $summary['wordpressImport']['guideReferenceManifestMediaTypeParameterItems']);
        $t->same($report['manifestMediaTypeParameterNames'], $summary['wordpressImport']['guideReferenceManifestMediaTypeParameterNames']);
        $t->same([], $summary['wordpressImport']['guideReferenceManifestMediaTypeDiagnostics']);
    },

    'summarizes EPUB3 auxiliary navigation sections for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $navWithAuxiliarySections = str_replace(
            '</body>',
            '    <nav id="figures-nav" epub:type="loi list-of-illustrations">
      <h2>Figures</h2>
      <ol>
        <li><a href="text/chapter1.xhtml#fig-1">Figure 1</a></li>
        <li><a href="text/chapter2.xhtml#fig-2">Figure 2</a></li>
      </ol>
    </nav>
    <nav id="tables-nav" epub:type="lot" hidden="hidden">
      <h2>Tables</h2>
      <ol>
        <li><a href="text/chapter2.xhtml#table-1">Table 1</a></li>
      </ol>
    </nav>
  </body>',
            $epub3NavXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithAuxiliarySections],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1><figure id="fig-1"></figure></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1><figure id="fig-2"></figure><table id="table-1"></table></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $summary = $epub->summary();
        $auxiliary = $summary['auxiliaryNavigation'];

        $t->same($auxiliary, $summary['wordpressImport']['auxiliaryNavigation']);
        $t->same($auxiliary['sections'], $summary['wordpressImport']['auxiliaryNavigationSections']);
        $t->same($auxiliary['items'], $summary['wordpressImport']['auxiliaryNavigationTargets']);
        $t->same(true, $auxiliary['present']);
        $t->same(2, $auxiliary['sectionCount']);
        $t->same(3, $auxiliary['itemCount']);
        $t->same(['loi', 'list-of-illustrations', 'lot'], $auxiliary['types']);
        $t->same(false, isset($auxiliary['sectionsByType']['toc']));

        $figures = $auxiliary['sections'][0];
        $t->same(1, $figures['sectionIndex']);
        $t->same('figures-nav', $figures['id']);
        $t->same('loi', $figures['type']);
        $t->same(['loi', 'list-of-illustrations'], $figures['auxiliaryTypes']);
        $t->same('Figures', $figures['title']);
        $t->same(false, $figures['hidden']);
        $t->same('/EPUB/nav.xhtml', $figures['partName']);
        $t->same($figures, $auxiliary['sectionsByType']['list-of-illustrations'][0]);

        $firstFigure = $auxiliary['items'][0];
        $t->same('figures-nav', $firstFigure['sectionId']);
        $t->same('loi', $firstFigure['sectionType']);
        $t->same(['loi', 'list-of-illustrations'], $firstFigure['sectionTypes']);
        $t->same('Figure 1', $firstFigure['label']);
        $t->same('/EPUB/text/chapter1.xhtml#fig-1', $firstFigure['target']);

        $tableSection = $auxiliary['sectionsByType']['lot'][0];
        $t->same('tables-nav', $tableSection['id']);
        $t->same(true, $tableSection['hidden']);
        $t->same('lot', $auxiliary['items'][2]['sectionType']);
        $t->same('/EPUB/text/chapter2.xhtml#table-1', $auxiliary['items'][2]['target']);
    },

    'preserves OPF collections and collection links for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithCollections = str_replace(
            '</spine>',
            '</spine>
  <collection id="series" role="series" xml:lang="en" dir="ltr">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Migration packets</dc:title>
      <meta property="group-position">2</meta>
    </metadata>
    <link id="series-record" rel="record" href="meta/series.json" media-type="application/ld+json" properties="review"/>
    <link id="start" rel="first" href="text/chapter1.xhtml#install" media-type="application/xhtml+xml"/>
    <link id="remote-record" rel="record alternate" href="https://example.invalid/series.json" media-type="application/json"/>
    <link id="missing-review" rel="review" href="text/missing.xhtml" media-type="application/xhtml+xml"/>
    <collection id="samples" role="preview">
      <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
        <dc:title>Review samples</dc:title>
      </metadata>
      <link rel="sample" href="text/chapter2.xhtml#checklist" media-type="application/xhtml+xml"/>
    </collection>
  </collection>',
            $epub3OpfXml
        );

        $seriesRecord = '{"kind":"series","source":"wordpress-epub"}';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithCollections],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="install">Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="checklist">Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/series.json', 'data' => $seriesRecord],
        ]));

        $collections = $epub->collections();
        $summary = $epub->summary();

        $t->same(1, count($collections));
        $series = $collections[0];
        $t->same('series', $series['id']);
        $t->same('series', $series['role']);
        $t->same(['series'], $series['roleTokens']);
        $t->same('en', $series['language']);
        $t->same('ltr', $series['direction']);
        $t->same('Migration packets', $series['metadata']['title']);
        $t->same('2', $series['metadata']['properties']['group-position'][0]);
        $t->same(4, $series['linkCount']);
        $t->same(3, $series['localLinkCount']);
        $t->same(1, $series['externalLinkCount']);
        $t->same(1, $series['missingLinkCount']);
        $t->same(['record', 'first', 'alternate', 'review'], $series['linkRelTokens']);
        $t->same(['record' => 2, 'first' => 1, 'alternate' => 1, 'review' => 1], $series['linkRelCounts']);
        $t->same('/EPUB/meta/series.json', $series['links'][0]['target']);
        $t->same('/EPUB/meta/series.json', $series['links'][0]['partName']);
        $t->same(true, $series['links'][0]['exists']);
        $t->same(strlen($seriesRecord), $series['links'][0]['byteLength']);
        $t->same('series-record', $series['linksByRel']['record'][0]['id']);
        $t->same('/EPUB/text/chapter1.xhtml#install', $series['linksByRel']['first'][0]['target']);
        $t->same('remote-record', $series['linksByRel']['record'][1]['id']);
        $t->same(true, $series['linksByRel']['record'][1]['external']);
        $t->same('missing-collection-link-target', $series['linksByRel']['review'][0]['diagnostics'][0]['type']);
        $t->same(2, $series['diagnosticCount']);
        $t->same(['external-collection-link-target', 'missing-collection-link-target'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $series['diagnostics']));
        $t->same(1, count($series['children']));
        $t->same('samples', $series['children'][0]['id']);
        $t->same('Review samples', $series['children'][0]['metadata']['title']);
        $t->same('/EPUB/text/chapter2.xhtml#checklist', $series['children'][0]['links'][0]['target']);

        $t->same($collections, $summary['collections']);
        $t->same($collections, $summary['wordpressImport']['collections']);
        $t->same(['Migration packets', 'Review samples'], $summary['wordpressImport']['collectionTitles']);
        $t->same(['/EPUB/meta/series.json', '/EPUB/text/chapter1.xhtml#install', 'https://example.invalid/series.json', '/EPUB/text/missing.xhtml', '/EPUB/text/chapter2.xhtml#checklist'], $summary['wordpressImport']['collectionLinkTargets']);
        $t->same(['external-collection-link-target', 'missing-collection-link-target'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $summary['wordpressImport']['collectionDiagnostics']));
    },

    'summarizes OCF metadata links for EPUB3 package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $containerRecord = '{"@context":"https://schema.org","name":"OCF container review packet"}';
        $containerMetadataXml = <<<'XML'
<metadata xmlns="http://www.idpf.org/2013/metadata" xmlns:dc="http://purl.org/dc/elements/1.1/" unique-identifier="pub-id">
  <dc:identifier id="pub-id">urn:uuid:container-metadata-review</dc:identifier>
  <meta property="dcterms:modified">2026-06-10T19:45:47Z</meta>
  <link id="container-review" rel="record" href="EPUB/meta/container-record.json" media-type="application/ld+json" properties="review audit" title="Container review" hreflang="en" xml:lang="en" dir="ltr"/>
  <link id="remote-rights" rel="license" href="https://rights.example.invalid/license.json" media-type="application/json"/>
  <link id="missing-alt" rel="alternate" href="EPUB/meta/missing-container.json" media-type="application/json"/>
  <link id="bad-traversal" rel="review" href="../outside.json"/>
  <link id="unclassified-local" href="EPUB/meta/container-record.json"/>
</metadata>
XML;
        $opfWithContainerRecord = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="container-record" href="meta/container-record.json" media-type="application/ld+json"/>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/metadata.xml', 'data' => $containerMetadataXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithContainerRecord],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/container-record.json', 'data' => $containerRecord],
        ]));

        $links = $epub->containerLinks();
        $summary = $epub->summary();
        $policy = $summary['remoteResourcePolicy'];

        $t->same(5, count($links));
        $t->same('container-review', $links[0]['id']);
        $t->same(['record'], $links[0]['rel']);
        $t->same('EPUB/meta/container-record.json', $links[0]['href']);
        $t->same('/EPUB/meta/container-record.json', $links[0]['target']);
        $t->same('/EPUB/meta/container-record.json', $links[0]['partName']);
        $t->same(true, $links[0]['exists']);
        $t->same('container-record', $links[0]['manifestId']);
        $t->same('application/ld+json', $links[0]['manifestMediaType']);
        $t->same(strlen($containerRecord), $links[0]['byteLength']);
        $t->same(hash('crc32b', $containerRecord), $links[0]['crc32']);
        $t->same(['review', 'audit'], $links[0]['properties']);
        $t->same('Container review', $links[0]['title']);
        $t->same('en', $links[0]['hreflang']);
        $t->same('en', $links[0]['language']);
        $t->same('ltr', $links[0]['direction']);

        $t->same(true, $links[1]['external']);
        $t->same('external-container-link-target', $links[1]['diagnostics'][0]['type']);
        $t->same(false, $links[2]['exists']);
        $t->same('/EPUB/meta/missing-container.json', $links[2]['partName']);
        $t->same('missing-container-link-target', $links[2]['diagnostics'][0]['type']);
        $t->same(null, $links[3]['target']);
        $t->same('invalid-container-link-href', $links[3]['diagnostics'][0]['type']);
        $t->same([], $links[4]['rel']);
        $t->same('missing-container-link-rel', $links[4]['diagnostics'][0]['type']);

        $t->same(['record' => 1, 'license' => 1, 'alternate' => 1, 'review' => 1], $summary['containerLinkRelCounts']);
        $t->same('container-review', $summary['containerLinksByRel']['record'][0]['id']);
        $t->same(['/EPUB/meta/container-record.json', 'https://rights.example.invalid/license.json', '/EPUB/meta/missing-container.json', '/EPUB/meta/container-record.json'], $summary['wordpressImport']['containerLinkTargets']);
        $t->same(['external-container-link-target', 'missing-container-link-target', 'invalid-container-link-href', 'missing-container-link-rel'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $summary['wordpressImport']['containerLinkDiagnostics']));

        $t->same(true, $policy['present']);
        $t->same(5, $policy['itemCount']);
        $t->same(5, $policy['containerLinkCount']);
        $t->same(0, $policy['packageLinkCount']);
        $t->same(0, $policy['collectionLinkCount']);
        $t->same(2, $policy['localTargetCount']);
        $t->same(1, $policy['externalTargetCount']);
        $t->same(2, $policy['missingTargetCount']);
        $t->same(['local-package' => 2, 'remote-no-fetch' => 1, 'missing-package' => 2], $policy['policyCounts']);
        $t->same('container-link', $policy['items'][0]['source']);
        $t->same('local-package', $policy['items'][0]['policy']);
        $t->same('remote-no-fetch', $policy['items'][1]['policy']);
        $t->same('missing-package', $policy['items'][2]['policy']);
        $t->same('missing-package', $policy['items'][3]['policy']);
        $t->same('container-link', $policy['diagnostics'][0]['source']);
        $t->same('external-container-link-target', $policy['diagnostics'][0]['type']);
        $t->same($links, $summary['containerLinks']);
        $t->same($links, $summary['wordpressImport']['containerLinks']);
        $t->same($policy, $summary['wordpressImport']['remoteResourcePolicy']);
    },

    'summarizes OCF metadata link vocabulary tokens for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $containerRecord = '{"@context":"https://schema.org","name":"OCF vocabulary packet"}';
        $containerMetadataXml = <<<'XML'
<metadata xmlns="http://www.idpf.org/2013/metadata" prefix="review: https://example.invalid/ocf-review#">
  <link id="container-vocab" rel="record review:associatedMedia https://example.invalid/container-rel#review bad/token record unknown:missing" href="EPUB/meta/container-vocab.json" media-type="application/ld+json" properties="schema-org review:packet https://example.invalid/container-props#review bad/property schema-org unknown:flag"/>
</metadata>
XML;
        $opfWithContainerRecord = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="container-vocab-record" href="meta/container-vocab.json" media-type="application/ld+json"/>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/metadata.xml', 'data' => $containerMetadataXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithContainerRecord],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/container-vocab.json', 'data' => $containerRecord],
        ]));

        $link = $epub->containerLinks()[0];
        $summary = $epub->summary();
        $relVocabulary = $link['relVocabulary'];
        $propertyVocabulary = $link['propertyVocabulary'];

        $t->same('container-vocab', $link['id']);
        $t->same('/EPUB/meta/container-vocab.json', $link['target']);
        $t->same('container-vocab-record', $link['manifestId']);
        $t->same(strlen($containerRecord), $link['byteLength']);
        $t->same(hash('crc32b', $containerRecord), $link['crc32']);

        $t->same(6, $relVocabulary['count']);
        $t->same(5, $relVocabulary['validCount']);
        $t->same(1, $relVocabulary['invalidCount']);
        $t->same(1, $relVocabulary['resolvedCount']);
        $t->same(1, $relVocabulary['absoluteUrlCount']);
        $t->same(1, $relVocabulary['duplicateCount']);
        $t->same('prefixed-nmtoken', $relVocabulary['items'][1]['kind']);
        $t->same('review', $relVocabulary['items'][1]['prefix']);
        $t->same('associatedMedia', $relVocabulary['items'][1]['localName']);
        $t->same('https://example.invalid/ocf-review#associatedMedia', $relVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $relVocabulary['items'][2]['kind']);
        $t->same('invalid-metadata-link-rel-token', $relVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-metadata-link-rel-token', $relVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-metadata-link-rel-prefix', $relVocabulary['items'][5]['diagnostics'][0]['type']);

        $t->same(6, $propertyVocabulary['count']);
        $t->same(5, $propertyVocabulary['validCount']);
        $t->same(1, $propertyVocabulary['invalidCount']);
        $t->same(1, $propertyVocabulary['resolvedCount']);
        $t->same(1, $propertyVocabulary['absoluteUrlCount']);
        $t->same(1, $propertyVocabulary['duplicateCount']);
        $t->same('schema-org', $propertyVocabulary['items'][0]['value']);
        $t->same('https://example.invalid/ocf-review#packet', $propertyVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $propertyVocabulary['items'][2]['kind']);
        $t->same('invalid-metadata-link-properties-token', $propertyVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-metadata-link-properties-token', $propertyVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-metadata-link-properties-prefix', $propertyVocabulary['items'][5]['diagnostics'][0]['type']);

        $vocabulary = $summary['containerLinkVocabulary'];
        $t->same(true, $vocabulary['present']);
        $t->same(1, $vocabulary['linkCount']);
        $t->same(6, $vocabulary['relTokenCount']);
        $t->same(6, $vocabulary['propertyTokenCount']);
        $t->same(2, $vocabulary['resolvedTokenCount']);
        $t->same(2, $vocabulary['absoluteUrlTokenCount']);
        $t->same(2, $vocabulary['duplicateTokenCount']);
        $t->same(6, $vocabulary['diagnosticCount']);
        $t->same($vocabulary, $summary['wordpressImport']['containerLinkVocabulary']);
        $t->same($vocabulary['diagnostics'], $summary['wordpressImport']['containerLinkVocabularyDiagnostics']);
    },

    'summarizes OCF rights and signatures sidecar ZIP provenance for package handoff' => static function (TestRunner $t) use ($buildZipPackage, $epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $rightsXml = '<rights xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><license href="../EPUB/meta/license.xml">Review license</license></rights>';
        $signaturesXml = '<signatures xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/></signatures>';

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/rights.xml', 'data' => $rightsXml, 'method' => 0],
            ['name' => 'META-INF/signatures.xml', 'data' => $signaturesXml, 'method' => 12],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $sidecars = $epub->ocfSidecars();
        $summary = $epub->summary();
        $rights = $sidecars['itemsByKind']['rights'];
        $signatures = $sidecars['itemsByKind']['signatures'];

        $t->same(true, $sidecars['present']);
        $t->same(2, $sidecars['sidecarCount']);
        $t->same(2, $sidecars['count']);
        $t->same(true, $sidecars['rightsPresent']);
        $t->same(true, $sidecars['signaturesPresent']);
        $t->same(['rights', 'signatures'], $sidecars['kinds']);

        $t->same('rights', $rights['kind']);
        $t->same('/META-INF/rights.xml', $rights['part']);
        $t->same('/META-INF/rights.xml', $rights['partName']);
        $t->same('META-INF/rights.xml', $rights['packagePath']);
        $t->same('rights', $rights['expectedRootName']);
        $t->same(EpubPackage::OCF_CONTAINER_NAMESPACE, $rights['expectedRootNamespace']);
        $t->same('ocf-rights-sidecar-review', $rights['reviewPolicy']);
        $t->same('ocf-sidecar-metadata-only', $rights['byteExposurePolicy']);
        $t->same(false, $rights['canExposeBytes']);
        $t->same(true, $rights['xmlRootChecked']);
        $t->same(true, $rights['xmlWellFormed']);
        $t->same('rights', $rights['rootName']);
        $t->same(EpubPackage::OCF_CONTAINER_NAMESPACE, $rights['rootNamespace']);
        $t->same(true, $rights['rootValid']);
        $t->same([], $rights['rootDiagnostics']);
        $t->same(strlen($rightsXml), $rights['byteLength']);
        $t->same(strlen($rightsXml), $rights['compressedByteLength']);
        $t->same(0, $rights['compressionMethod']);
        $t->same('stored', $rights['compressionMethodName']);
        $t->same(true, $rights['compressionSupported']);
        $t->same(hash('crc32b', $rightsXml), $rights['crc32']);
        $t->same(0, $rights['diagnosticCount']);
        $t->same([], $rights['diagnostics']);

        $t->same('signatures', $signatures['kind']);
        $t->same('/META-INF/signatures.xml', $signatures['partName']);
        $t->same('signatures', $signatures['expectedRootName']);
        $t->same('ocf-signatures-sidecar-review', $signatures['reviewPolicy']);
        $t->same('ocf-sidecar-metadata-only', $signatures['byteExposurePolicy']);
        $t->same(false, $signatures['canExposeBytes']);
        $t->same(false, $signatures['xmlRootChecked']);
        $t->same(null, $signatures['xmlWellFormed']);
        $t->same(null, $signatures['rootName']);
        $t->same(null, $signatures['rootNamespace']);
        $t->same(null, $signatures['rootValid']);
        $t->same([], $signatures['rootDiagnostics']);
        $t->same(strlen($signaturesXml), $signatures['byteLength']);
        $t->same(strlen($signaturesXml), $signatures['compressedByteLength']);
        $t->same(12, $signatures['compressionMethod']);
        $t->same('unsupported', $signatures['compressionMethodName']);
        $t->same(false, $signatures['compressionSupported']);
        $t->same(hash('crc32b', $signaturesXml), $signatures['crc32']);
        $t->same(1, $signatures['diagnosticCount']);
        $t->same('ocf-sidecar-unsupported-compression-method', $signatures['diagnostics'][0]['type']);
        $t->same(12, $signatures['diagnostics'][0]['compressionMethod']);

        $t->same(1, $sidecars['diagnosticCount']);
        $t->same(['ocf-sidecar-unsupported-compression-method'], array_column($sidecars['diagnostics'], 'type'));
        $t->same($sidecars, $summary['ocfSidecars']);
        $t->same($sidecars['diagnostics'], $summary['ocfSidecarDiagnostics']);
        $t->same($sidecars, $summary['wordpressImport']['ocfSidecars']);
        $t->same($sidecars['items'], $summary['wordpressImport']['ocfSidecarItems']);
        $t->same($sidecars['diagnostics'], $summary['wordpressImport']['ocfSidecarDiagnostics']);
    },

    'summarizes OCF manifest sidecar entries for package handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $chapter1Xhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>';
        $styleCss = 'body { font-family: serif; }';
        $unmanifestedBytes = 'UNMANIFESTED-PNG';
        $privateBytes = 'PRIVATE-DATA';
        $ocfManifestXml = sprintf(<<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/epub+zip"/>
  <manifest:file-entry manifest:full-path="EPUB/package.opf" manifest:media-type="application/oebps-package+xml"/>
  <manifest:file-entry manifest:full-path="EPUB/text/chapter1.xhtml" manifest:media-type="application/xhtml+xml" manifest:size="%d"/>
  <manifest:file-entry manifest:full-path="EPUB/styles/book.css" manifest:media-type="text/css" manifest:size="4"/>
  <manifest:file-entry manifest:full-path="EPUB/images/unmanifested-review.png" manifest:media-type="image/png" manifest:size="%d"/>
  <manifest:file-entry manifest:full-path="EPUB/images/missing-review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="../outside.txt" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="EPUB/private.bin" manifest:media-type="application/octet-stream"><manifest:encryption-data/></manifest:file-entry>
</manifest:manifest>
XML, strlen($chapter1Xhtml), strlen($unmanifestedBytes));

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/manifest.xml', 'data' => $ocfManifestXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => $chapter1Xhtml],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => $styleCss],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/images/unmanifested-review.png', 'data' => $unmanifestedBytes, 'compressionMethod' => 0],
            ['name' => 'EPUB/private.bin', 'data' => $privateBytes],
        ]));

        $sidecars = $epub->ocfSidecars();
        $summary = $epub->summary();
        $manifest = $sidecars['itemsByKind']['manifest'];

        $t->same(true, $sidecars['present']);
        $t->same(1, $sidecars['sidecarCount']);
        $t->same(true, $sidecars['manifestPresent']);
        $t->same(false, $sidecars['rightsPresent']);
        $t->same(false, $sidecars['signaturesPresent']);
        $t->same(['manifest'], $sidecars['kinds']);
        $t->same(8, $sidecars['referenceCount']);
        $t->same(6, $sidecars['localReferenceCount']);
        $t->same(0, $sidecars['externalReferenceCount']);
        $t->same(2, $sidecars['missingReferenceCount']);

        $t->same('manifest', $manifest['kind']);
        $t->same('/META-INF/manifest.xml', $manifest['partName']);
        $t->same('manifest', $manifest['rootName']);
        $t->same(EpubPackage::ODF_MANIFEST_NAMESPACE, $manifest['rootNamespace']);
        $t->same(true, $manifest['rootValid']);
        $t->same('odf-manifest', $manifest['format']);
        $t->same(true, $manifest['odfCompatible']);
        $t->same('1.3', $manifest['version']);
        $t->same('ocf-manifest-sidecar-review', $manifest['reviewPolicy']);
        $t->same('ocf-sidecar-metadata-only', $manifest['byteExposurePolicy']);
        $t->same(false, $manifest['canExposeBytes']);
        $t->same(strlen($ocfManifestXml), $manifest['byteLength']);
        $t->same(hash('crc32b', $ocfManifestXml), $manifest['crc32']);
        $t->same(9, $manifest['itemCount']);
        $t->same(8, $manifest['declaredPartCount']);
        $t->same(3, $manifest['missingItemCount']);
        $t->same(1, $manifest['sizeMismatchCount']);
        $t->same(['ocf-manifest-size-mismatch', 'ocf-manifest-missing-reference', 'ocf-manifest-invalid-reference', 'missing-ocf-manifest-full-path'], array_column($manifest['diagnostics'], 'type'));

        $root = $manifest['items'][0];
        $t->same('/', $root['fullPath']);
        $t->same('/', $root['target']);
        $t->same(null, $root['part']);
        $t->same(true, $root['root']);
        $t->same(true, $root['directory']);
        $t->same(true, $root['exists']);
        $t->same(false, $root['canExposeBytes']);

        $chapter = $manifest['itemsByPart']['/EPUB/text/chapter1.xhtml'];
        $t->same(strlen($chapter1Xhtml), $chapter['declaredSize']);
        $t->same(strlen($chapter1Xhtml), $chapter['byteLength']);
        $t->same(hash('sha256', $chapter1Xhtml), $chapter['byteSha256']);
        $t->same(true, $chapter['sizeMatches']);
        $t->same(true, $chapter['canExposeBytes']);

        $style = $manifest['itemsByPart']['/EPUB/styles/book.css'];
        $t->same(false, $style['sizeMatches']);
        $t->same(4, $style['declaredSize']);
        $t->same(strlen($styleCss), $style['byteLength']);

        $unmanifested = $manifest['itemsByPart']['/EPUB/images/unmanifested-review.png'];
        $t->same(true, $unmanifested['exists']);
        $t->same(strlen($unmanifestedBytes), $unmanifested['byteLength']);
        $t->same(0, $unmanifested['compressionMethod']);
        $t->same('stored', $unmanifested['compressionMethodName']);

        $private = $manifest['itemsByPart']['/EPUB/private.bin'];
        $t->same(true, $private['encrypted']);
        $t->same(true, $private['exists']);
        $t->same(false, $private['canExposeBytes']);
        $t->same(null, $private['byteSha256']);

        $t->same($sidecars, $summary['ocfSidecars']);
        $t->same($sidecars, $summary['wordpressImport']['ocfSidecars']);
        $t->same($sidecars['diagnostics'], $summary['wordpressImport']['ocfSidecarDiagnostics']);
    },

    'reports OCF sidecar XML root diagnostics without aborting package ingestion' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $rightsXml = '<license xmlns="urn:oasis:names:tc:opendocument:xmlns:container">Review license</license>';
        $signaturesXml = '<signatures xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><Signature></signatures>';

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/rights.xml', 'data' => $rightsXml],
            ['name' => 'META-INF/signatures.xml', 'data' => $signaturesXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $sidecars = $epub->ocfSidecars();
        $summary = $epub->summary();
        $rights = $sidecars['itemsByKind']['rights'];
        $signatures = $sidecars['itemsByKind']['signatures'];

        $t->same(true, $rights['xmlRootChecked']);
        $t->same(true, $rights['xmlWellFormed']);
        $t->same('license', $rights['rootName']);
        $t->same(EpubPackage::OCF_CONTAINER_NAMESPACE, $rights['rootNamespace']);
        $t->same(false, $rights['rootValid']);
        $t->same(['unexpected-ocf-sidecar-root'], array_column($rights['rootDiagnostics'], 'type'));
        $t->same('rights', $rights['rootDiagnostics'][0]['expectedRootName']);
        $t->same('license', $rights['rootDiagnostics'][0]['rootName']);

        $t->same(true, $signatures['xmlRootChecked']);
        $t->same(false, $signatures['xmlWellFormed']);
        $t->same(null, $signatures['rootName']);
        $t->same(null, $signatures['rootNamespace']);
        $t->same(false, $signatures['rootValid']);
        $t->same(['invalid-ocf-sidecar-xml'], array_column($signatures['rootDiagnostics'], 'type'));
        $t->same('/META-INF/signatures.xml', $signatures['rootDiagnostics'][0]['partName']);

        $t->same(2, $sidecars['diagnosticCount']);
        $t->same(['unexpected-ocf-sidecar-root', 'invalid-ocf-sidecar-xml'], array_column($sidecars['diagnostics'], 'type'));
        $t->same($sidecars, $summary['ocfSidecars']);
        $t->same($sidecars['diagnostics'], $summary['ocfSidecarDiagnostics']);
        $t->same($sidecars, $summary['wordpressImport']['ocfSidecars']);
        $t->same($sidecars['diagnostics'], $summary['wordpressImport']['ocfSidecarDiagnostics']);
    },

    'preserves OPF metadata link records for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithLinks = str_replace(
            '</metadata>',
            '    <link id="review-record" rel="record alternate" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org reviewer" hreflang="en" title="Review record"/>
    <link id="remote-onix" rel="record" href="https://metadata.example.invalid/onix.xml" media-type="application/xml" properties="onix"/>
    <link id="creator-voicing" rel="voicing" refines="#creator" href="audio/creator-name.mp3" media-type="audio/mpeg" properties="pronunciation"/>
    <link id="missing-record" rel="record" href="meta/missing-record.json" media-type="application/json"/>
  </metadata>',
            $epub3OpfXml
        );
        $opfWithLinks = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="creator-audio" href="audio/creator-name.mp3" media-type="audio/mpeg"/>',
            $opfWithLinks
        );

        $reviewRecord = '{"@context":"https://schema.org","name":"WordPress EPUB review record"}';
        $creatorAudio = 'MP3-CREATOR-NAME';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithLinks],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/review-record.json', 'data' => $reviewRecord],
            ['name' => 'EPUB/audio/creator-name.mp3', 'data' => $creatorAudio],
        ]));

        $links = $epub->packageLinks();
        $summary = $epub->summary();

        $t->same(4, count($links));
        $t->same('review-record', $links[0]['id']);
        $t->same(['record', 'alternate'], $links[0]['rel']);
        $t->same('/EPUB/meta/review-record.json', $links[0]['target']);
        $t->same('/EPUB/meta/review-record.json', $links[0]['partName']);
        $t->same(true, $links[0]['exists']);
        $t->same(strlen($reviewRecord), $links[0]['byteLength']);
        $t->same(hash('crc32b', $reviewRecord), $links[0]['crc32']);
        $t->same('application/ld+json', $links[0]['mediaType']);
        $t->same(null, $links[0]['manifestId']);
        $t->same(['schema-org', 'reviewer'], $links[0]['properties']);
        $t->same('en', $links[0]['hreflang']);
        $t->same('Review record', $links[0]['title']);
        $t->same('remote-onix', $links[1]['id']);
        $t->same(true, $links[1]['external']);
        $t->same('external-package-link-target', $links[1]['diagnostics'][0]['type']);
        $t->same('creator', $links[2]['subjectId']);
        $t->same('/EPUB/audio/creator-name.mp3', $links[2]['target']);
        $t->same('creator-audio', $links[2]['manifestId']);
        $t->same('audio/mpeg', $links[2]['manifestMediaType']);
        $t->same(strlen($creatorAudio), $links[2]['byteLength']);
        $t->same('missing-package-link-target', $links[3]['diagnostics'][0]['type']);

        $t->same(['record' => 3, 'alternate' => 1, 'voicing' => 1], $summary['packageLinkRelCounts']);
        $t->same('review-record', $summary['packageLinksByRel']['record'][0]['id']);
        $t->same('creator-voicing', $summary['packageLinksByRel']['voicing'][0]['id']);
        $t->same($links, $summary['packageLinks']);
        $t->same($links, $summary['metadata']['links']);
        $t->same($summary['packageLinkRelCounts'], $summary['metadata']['linkRelCounts']);
        $t->same(['/EPUB/meta/review-record.json', 'https://metadata.example.invalid/onix.xml', '/EPUB/audio/creator-name.mp3', '/EPUB/meta/missing-record.json'], $summary['wordpressImport']['packageLinkTargets']);
        $t->same(['external-package-link-target', 'missing-package-link-target'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $summary['wordpressImport']['packageLinkDiagnostics']));
        $t->same('creator-voicing', $summary['wordpressImport']['packageLinksByRel']['voicing'][0]['id']);
    },

    'preserves EPUB package link href suffix provenance for preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $containerMetadataXml = <<<'XML'
<metadata xmlns="http://www.idpf.org/2013/metadata">
  <link id="container-suffix" rel="record" href="EPUB/meta/container-record.json?profile=ocf#record" media-type="application/ld+json"/>
</metadata>
XML;
        $opfWithSuffixedLinks = str_replace(
            '</metadata>',
            '    <link id="package-suffix" rel="record" href="meta/package-record.json?edition=review#package" media-type="application/ld+json"/>
  </metadata>',
            $epub3OpfXml
        );
        $opfWithSuffixedLinks = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="container-record" href="meta/container-record.json" media-type="application/ld+json"/>
    <item id="package-record" href="meta/package-record.json" media-type="application/ld+json"/>',
            $opfWithSuffixedLinks
        );
        $opfWithSuffixedLinks = str_replace(
            '</spine>',
            '</spine>
  <collection id="review-links" role="review">
    <link id="collection-suffix" rel="first" href="text/chapter1.xhtml?view=review#install" media-type="application/xhtml+xml"/>
  </collection>',
            $opfWithSuffixedLinks
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/metadata.xml', 'data' => $containerMetadataXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithSuffixedLinks],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="install">Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/container-record.json', 'data' => '{"name":"container"}'],
            ['name' => 'EPUB/meta/package-record.json', 'data' => '{"name":"package"}'],
        ]));
        $summary = $epub->summary();
        $containerLink = $epub->containerLinks()[0];
        $packageLink = $epub->packageLinks()[0];
        $collectionLink = $epub->collections()[0]['links'][0];

        $t->same('/EPUB/meta/container-record.json?profile=ocf#record', $containerLink['target']);
        $t->same('/EPUB/meta/container-record.json', $containerLink['partName']);
        $t->same('container-record', $containerLink['manifestId']);
        $t->same(true, $containerLink['hrefHasQuery']);
        $t->same('profile=ocf', $containerLink['hrefQuery']);
        $t->same(true, $containerLink['hrefHasFragment']);
        $t->same('record', $containerLink['hrefFragment']);

        $t->same('/EPUB/meta/package-record.json?edition=review#package', $packageLink['target']);
        $t->same('/EPUB/meta/package-record.json', $packageLink['partName']);
        $t->same('package-record', $packageLink['manifestId']);
        $t->same(true, $packageLink['hrefHasQuery']);
        $t->same('edition=review', $packageLink['hrefQuery']);
        $t->same(true, $packageLink['hrefHasFragment']);
        $t->same('package', $packageLink['hrefFragment']);

        $t->same('/EPUB/text/chapter1.xhtml?view=review#install', $collectionLink['target']);
        $t->same('/EPUB/text/chapter1.xhtml', $collectionLink['partName']);
        $t->same('chapter1', $collectionLink['manifestId']);
        $t->same(true, $collectionLink['hrefHasQuery']);
        $t->same('view=review', $collectionLink['hrefQuery']);
        $t->same(true, $collectionLink['hrefHasFragment']);
        $t->same('install', $collectionLink['hrefFragment']);

        $t->same($containerLink, $summary['containerLinks'][0]);
        $t->same($packageLink, $summary['packageLinks'][0]);
        $t->same($collectionLink, $summary['collections'][0]['links'][0]);
        $t->same(['/EPUB/meta/container-record.json?profile=ocf#record'], $summary['wordpressImport']['containerLinkTargets']);
        $t->same(['/EPUB/meta/package-record.json?edition=review#package'], $summary['wordpressImport']['packageLinkTargets']);
        $t->same(['/EPUB/text/chapter1.xhtml?view=review#install'], $summary['wordpressImport']['collectionLinkTargets']);
    },

    'summarizes OPF accessibility metadata for compact package handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $a11yRecord = '{"@context":"https://schema.org","accessibilitySummary":"Compact package accessibility record"}';
        $opfWithAccessibility = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta property="schema:accessMode">textual</meta>
    <meta property="schema:accessMode">visual</meta>
    <meta property="schema:accessModeSufficient" xml:lang="en" dir="ltr">textual visual</meta>
    <meta property="schema:accessibilityFeature">alternativeText</meta>
    <meta property="schema:accessibilityFeature">MathML</meta>
    <meta name="schema:accessibilityHazard" content="noFlashingHazard"/>
    <meta property="schema:accessibilitySummary">Images and MathML are preserved for compact review.</meta>
    <meta property="a11y:certifiedBy">Migration Desk</meta>
    <meta property="a11y:certifierCredential">WAS reviewer</meta>
    <meta property="a11y:certifierReport">https://example.invalid/a11y/report</meta>
    <meta property="dcterms:conformsTo">EPUB Accessibility 1.1 - WCAG 2.1 AA</meta>',
            $epub3OpfXml
        );
        $opfWithAccessibility = str_replace(
            '</metadata>',
            '    <link id="a11y-record" rel="record accessibility-summary" href="meta/accessibility.json" media-type="application/ld+json" properties="accessibility-metadata schema-org" hreflang="en"/>
  </metadata>',
            $opfWithAccessibility
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithAccessibility],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/accessibility.json', 'data' => $a11yRecord],
        ]));

        $accessibility = $epub->metadata()['accessibility'];
        $summary = $epub->summary();

        $t->same(true, $accessibility['present']);
        $t->same(['textual', 'visual'], $accessibility['accessModes']);
        $t->same(['textual', 'visual'], $accessibility['accessModeSufficient'][0]['modes']);
        $t->same('en', $accessibility['accessModeSufficient'][0]['language']);
        $t->same('ltr', $accessibility['accessModeSufficient'][0]['direction']);
        $t->same(['alternativeText', 'MathML'], $accessibility['accessibilityFeatures']);
        $t->same(['noFlashingHazard'], $accessibility['accessibilityHazards']);
        $t->same('Images and MathML are preserved for compact review.', $accessibility['accessibilitySummary']);
        $t->same('Migration Desk', $accessibility['certification']['certifiedBy']);
        $t->same('WAS reviewer', $accessibility['certification']['certifierCredential']);
        $t->same('https://example.invalid/a11y/report', $accessibility['certification']['certifierReport']);
        $t->same(['EPUB Accessibility 1.1 - WCAG 2.1 AA'], $accessibility['certification']['conformsTo']);
        $t->same('schema:accessMode', $accessibility['entriesByProperty']['accessMode'][0]['rawProperty']);
        $t->same('property', $accessibility['entriesByProperty']['accessMode'][0]['source']);
        $t->same('schema:accessibilityHazard', $accessibility['entriesByProperty']['accessibilityHazard'][0]['rawName']);
        $t->same('name', $accessibility['entriesByProperty']['accessibilityHazard'][0]['source']);

        $record = $accessibility['linkedRecords'][0];
        $t->same('a11y-record', $record['id']);
        $t->same(['record', 'accessibility-summary'], $record['rel']);
        $t->same('/EPUB/meta/accessibility.json', $record['target']);
        $t->same('/EPUB/meta/accessibility.json', $record['partName']);
        $t->same(true, $record['exists']);
        $t->same(strlen($a11yRecord), $record['byteLength']);
        $t->same(hash('crc32b', $a11yRecord), $record['crc32']);
        $t->same(true, $record['canExposeBytes']);
        $t->same('application/ld+json', $record['mediaType']);
        $t->same(['accessibility-metadata', 'schema-org'], $record['properties']);
        $t->same([], $accessibility['diagnostics']);
        $t->same($accessibility, $summary['accessibility']);
        $t->same($accessibility, $summary['wordpressImport']['accessibility']);
    },

    'summarizes OPF metadata link vocabulary tokens for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithPrefix = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/ review: https://example.invalid/epub-review# review: https://example.invalid/review-vocab-2# bad-prefix">',
            $epub3OpfXml
        );
        $opfWithPrefix = str_replace(
            '</metadata>',
            '    <link id="vocab-record" rel="record schema:associatedMedia https://example.invalid/link-rel#review bad/token record unknown:missing" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org review:packet https://example.invalid/props#review bad/property schema-org unknown:flag"/>
  </metadata>',
            $opfWithPrefix
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithPrefix],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/review-record.json', 'data' => '{"name":"package record"}'],
        ]));

        $metadata = $epub->metadata();
        $link = $epub->packageLinks()[0];
        $summary = $epub->summary();
        $relVocabulary = $link['relVocabulary'];
        $propertyVocabulary = $link['propertyVocabulary'];

        $t->same('https://schema.org/', $metadata['prefixBindings']['schema']);
        $t->same('https://example.invalid/review-vocab-2#', $metadata['prefixBindings']['review']);
        $t->same(['duplicate-package-prefix-declaration', 'invalid-package-prefix-declaration'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $metadata['prefixDiagnostics']));

        $t->same(6, $relVocabulary['count']);
        $t->same(5, $relVocabulary['validCount']);
        $t->same(1, $relVocabulary['invalidCount']);
        $t->same(1, $relVocabulary['resolvedCount']);
        $t->same(1, $relVocabulary['absoluteUrlCount']);
        $t->same(1, $relVocabulary['duplicateCount']);
        $t->same('prefixed-nmtoken', $relVocabulary['items'][1]['kind']);
        $t->same('schema', $relVocabulary['items'][1]['prefix']);
        $t->same('associatedMedia', $relVocabulary['items'][1]['localName']);
        $t->same('https://schema.org/associatedMedia', $relVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $relVocabulary['items'][2]['kind']);
        $t->same('invalid-metadata-link-rel-token', $relVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-metadata-link-rel-token', $relVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-metadata-link-rel-prefix', $relVocabulary['items'][5]['diagnostics'][0]['type']);

        $t->same(6, $propertyVocabulary['count']);
        $t->same(5, $propertyVocabulary['validCount']);
        $t->same(1, $propertyVocabulary['invalidCount']);
        $t->same(1, $propertyVocabulary['resolvedCount']);
        $t->same(1, $propertyVocabulary['absoluteUrlCount']);
        $t->same(1, $propertyVocabulary['duplicateCount']);
        $t->same('schema-org', $propertyVocabulary['items'][0]['value']);
        $t->same('https://example.invalid/review-vocab-2#packet', $propertyVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $propertyVocabulary['items'][2]['kind']);
        $t->same('invalid-metadata-link-properties-token', $propertyVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-metadata-link-properties-token', $propertyVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-metadata-link-properties-prefix', $propertyVocabulary['items'][5]['diagnostics'][0]['type']);

        $vocabulary = $summary['packageLinkVocabulary'];
        $t->same(true, $vocabulary['present']);
        $t->same(1, $vocabulary['linkCount']);
        $t->same(6, $vocabulary['relTokenCount']);
        $t->same(6, $vocabulary['propertyTokenCount']);
        $t->same(2, $vocabulary['resolvedTokenCount']);
        $t->same(2, $vocabulary['absoluteUrlTokenCount']);
        $t->same(2, $vocabulary['duplicateTokenCount']);
        $t->same(6, $vocabulary['diagnosticCount']);
        $t->same($vocabulary, $metadata['linkVocabulary']);
        $t->same($vocabulary, $summary['wordpressImport']['packageLinkVocabulary']);
        $t->same($vocabulary['diagnostics'], $summary['wordpressImport']['packageLinkVocabularyDiagnostics']);
    },

    'summarizes OPF collection link vocabulary tokens for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithCollectionVocabulary = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/ review: https://example.invalid/epub-review#">',
            $epub3OpfXml
        );
        $opfWithCollectionVocabulary = str_replace(
            '</spine>',
            '</spine>
  <collection id="review-links" role="review">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Collection vocabulary review</dc:title></metadata>
    <link id="series-vocab" rel="record schema:associatedMedia https://example.invalid/link-rel#sample bad/token record unknown:missing" href="meta/series.json" media-type="application/ld+json" properties="schema-org review:packet https://example.invalid/props#flag bad/property schema-org unknown:flag"/>
  </collection>',
            $opfWithCollectionVocabulary
        );

        $seriesRecord = '{"kind":"series-vocabulary"}';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithCollectionVocabulary],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/series.json', 'data' => $seriesRecord],
        ]));

        $summary = $epub->summary();
        $collection = $epub->collections()[0];
        $link = $collection['links'][0];
        $relVocabulary = $link['relVocabulary'];
        $propertyVocabulary = $link['propertyVocabulary'];

        $t->same('/EPUB/meta/series.json', $link['target']);
        $t->same(true, $link['exists']);
        $t->same(strlen($seriesRecord), $link['byteLength']);
        $t->same(hash('crc32b', $seriesRecord), $link['crc32']);
        $t->same('https://schema.org/associatedMedia', $relVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $relVocabulary['items'][2]['kind']);
        $t->same('invalid-collection-link-rel-token', $relVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-collection-link-rel-token', $relVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-collection-link-rel-prefix', $relVocabulary['items'][5]['diagnostics'][0]['type']);
        $t->same('https://example.invalid/epub-review#packet', $propertyVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $propertyVocabulary['items'][2]['kind']);
        $t->same('invalid-collection-link-properties-token', $propertyVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-collection-link-properties-token', $propertyVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-collection-link-properties-prefix', $propertyVocabulary['items'][5]['diagnostics'][0]['type']);
        $t->same(6, $collection['diagnosticCount']);
        $t->same([
            'invalid-collection-link-rel-token',
            'duplicate-collection-link-rel-token',
            'unknown-collection-link-rel-prefix',
            'invalid-collection-link-properties-token',
            'duplicate-collection-link-properties-token',
            'unknown-collection-link-properties-prefix',
        ], array_column($collection['diagnostics'], 'type'));

        $vocabulary = $summary['collectionLinkVocabulary'];
        $t->same(true, $vocabulary['present']);
        $t->same(1, $vocabulary['linkCount']);
        $t->same(6, $vocabulary['relTokenCount']);
        $t->same(6, $vocabulary['propertyTokenCount']);
        $t->same(2, $vocabulary['resolvedTokenCount']);
        $t->same(2, $vocabulary['absoluteUrlTokenCount']);
        $t->same(2, $vocabulary['duplicateTokenCount']);
        $t->same(6, $vocabulary['diagnosticCount']);
        $t->same(2, $vocabulary['rels']['record']);
        $t->same(2, $vocabulary['properties']['schema-org']);
        $t->same($vocabulary, $summary['wordpressImport']['collectionLinkVocabulary']);
        $t->same($vocabulary['diagnostics'], $summary['wordpressImport']['collectionLinkVocabularyDiagnostics']);
        $t->same([
            'invalid-collection-link-rel-token',
            'duplicate-collection-link-rel-token',
            'unknown-collection-link-rel-prefix',
            'invalid-collection-link-properties-token',
            'duplicate-collection-link-properties-token',
            'unknown-collection-link-properties-prefix',
        ], array_column($summary['wordpressImport']['collectionDiagnostics'], 'type'));
    },

    'summarizes OPF package and collection remote link policy for preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithLinks = str_replace(
            '</metadata>',
            '    <link id="review-record" rel="record alternate" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org reviewer"/>
    <link id="remote-onix" rel="record" href="https://metadata.example.invalid/onix.xml" media-type="application/xml"/>
    <link id="missing-record" rel="record" href="meta/missing-record.json" media-type="application/json"/>
  </metadata>',
            $epub3OpfXml
        );
        $opfWithLinks = str_replace(
            '</spine>',
            '</spine>
  <collection id="series" role="series">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Migration packets</dc:title></metadata>
    <link id="series-record" rel="record" href="meta/series.json" media-type="application/ld+json"/>
    <link id="remote-series" rel="alternate" href="https://example.invalid/epub/series.json" media-type="application/json"/>
    <link id="missing-sample" rel="sample" href="text/missing.xhtml" media-type="application/xhtml+xml"/>
  </collection>',
            $opfWithLinks
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithLinks],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/review-record.json', 'data' => '{"name":"package record"}'],
            ['name' => 'EPUB/meta/series.json', 'data' => '{"name":"series record"}'],
        ]));

        $summary = $epub->summary();
        $policy = $summary['remoteResourcePolicy'];

        $t->same(true, $policy['present']);
        $t->same(6, $policy['itemCount']);
        $t->same(3, $policy['packageLinkCount']);
        $t->same(3, $policy['collectionLinkCount']);
        $t->same(2, $policy['localTargetCount']);
        $t->same(2, $policy['externalTargetCount']);
        $t->same(2, $policy['missingTargetCount']);
        $t->same(2, $policy['remoteNoFetchCount']);
        $t->same(['/EPUB/meta/review-record.json', '/EPUB/meta/series.json'], $policy['localTargets']);
        $t->same(['https://metadata.example.invalid/onix.xml', 'https://example.invalid/epub/series.json'], $policy['externalTargets']);
        $t->same(['/EPUB/meta/missing-record.json', '/EPUB/text/missing.xhtml'], $policy['missingTargets']);
        $t->same(['local-package' => 2, 'remote-no-fetch' => 2, 'missing-package' => 2], $policy['policyCounts']);

        $t->same('package-link', $policy['items'][0]['source']);
        $t->same('review-record', $policy['items'][0]['id']);
        $t->same('local-package', $policy['items'][0]['policy']);
        $t->same('package-link', $policy['items'][1]['source']);
        $t->same('remote-no-fetch', $policy['items'][1]['policy']);
        $t->same('missing-package', $policy['items'][2]['policy']);
        $t->same('collection-link', $policy['items'][3]['source']);
        $t->same('series', $policy['items'][3]['collectionId']);
        $t->same([0], $policy['items'][3]['collectionPath']);
        $t->same('local-package', $policy['items'][3]['policy']);
        $t->same('remote-no-fetch', $policy['items'][4]['policy']);
        $t->same('missing-package', $policy['items'][5]['policy']);
        $t->same(['external-package-link-target', 'missing-package-link-target', 'external-collection-link-target', 'missing-collection-link-target'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $policy['diagnostics']));
        $t->same($policy, $summary['wordpressImport']['remoteResourcePolicy']);
        $t->same($policy['externalTargets'], $summary['wordpressImport']['remoteResourceExternalTargets']);
        $t->same($policy['diagnostics'], $summary['wordpressImport']['remoteResourcePolicyDiagnostics']);
    },

    'summarizes OPF media-overlay preflight without running playback' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithMediaOverlay = str_replace(
            '</metadata>',
            '    <meta property="media:duration">0:00:07.000</meta>
    <meta property="media:duration" refines="#mo-chapter">0:00:07.000</meta>
  </metadata>',
            $epub3OpfXml
        );
        $opfWithMediaOverlay = str_replace(
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>',
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image" media-overlay="missing-overlay"/>',
            $opfWithMediaOverlay
        );
        $opfWithMediaOverlay = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter"/>',
            $opfWithMediaOverlay
        );
        $opfWithMediaOverlay = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml" media-overlay="bad-overlay"/>
    <item id="audio" href="audio/chapter.mp3" media-type="audio/mpeg"/>
    <item id="mo-chapter" href="overlays/chapter.smil" media-type="application/smil+xml"/>
    <item id="bad-overlay" href="styles/book.css" media-type="text/css"/>',
            $opfWithMediaOverlay
        );

        $smil = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL">
  <body>
    <seq id="chapter-seq">
      <par id="intro">
        <text src="../text/chapter1.xhtml#intro"/>
        <audio src="../audio/chapter.mp3" clipBegin="0:00:00.000" clipEnd="0:00:04.250"/>
      </par>
      <par id="remote-note">
        <text src="../text/missing.xhtml"/>
        <audio src="https://cdn.example.test/audio/review.mp3" clipBegin="4.25s" clipEnd="7s"/>
      </par>
      <par id="invalid-clip">
        <text src="../text/chapter2.xhtml"/>
        <audio src="../audio/chapter.mp3" clipBegin="bad-clock" clipEnd="6s"/>
      </par>
    </seq>
  </body>
</smil>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMediaOverlay],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="intro">Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/audio/chapter.mp3', 'data' => 'MP3'],
            ['name' => 'EPUB/overlays/chapter.smil', 'data' => $smil],
        ]));

        $overlays = $epub->mediaOverlays();
        $summary = $epub->summary();
        $chapter = $overlays['itemsById']['mo-chapter'];
        $bad = $overlays['itemsById']['bad-overlay'];
        $missing = $overlays['itemsById']['missing-overlay'];

        $t->same('mo-chapter', $epub->manifestItem('chapter1')['mediaOverlay']);
        $t->same('mo-chapter', $epub->spine()[0]['mediaOverlay']);
        $t->same(true, $overlays['present']);
        $t->same(3, $overlays['overlayCount']);
        $t->same(3, $overlays['referencedContentItemCount']);
        $t->same(2, $overlays['resolvedOverlayCount']);
        $t->same(1, $overlays['missingOverlayCount']);
        $t->same(2, $overlays['durationCount']);

        $t->same('mo-chapter', $chapter['id']);
        $t->same('/EPUB/overlays/chapter.smil', $chapter['partName']);
        $t->same('application/smil+xml', $chapter['mediaType']);
        $t->same(true, $chapter['exists']);
        $t->same(strlen($smil), $chapter['byteLength']);
        $t->same(hash('crc32b', $smil), $chapter['crc32']);
        $t->same(['chapter1'], $chapter['referencedByIds']);
        $t->same('0:00:07.000', $chapter['duration']);
        $t->same(7.0, $chapter['durationSeconds']);
        $t->same(3, $chapter['itemCount']);
        $t->same(['/EPUB/text/chapter1.xhtml#intro', '/EPUB/text/missing.xhtml', '/EPUB/text/chapter2.xhtml'], $chapter['textTargets']);
        $t->same(['/EPUB/audio/chapter.mp3', 'https://cdn.example.test/audio/review.mp3'], $chapter['audioTargets']);
        $t->same('/EPUB/text/chapter1.xhtml#intro', $chapter['items'][0]['textTarget']);
        $t->same('chapter1', $chapter['items'][0]['textManifestId']);
        $t->same('/EPUB/audio/chapter.mp3', $chapter['items'][0]['audioTarget']);
        $t->same('audio', $chapter['items'][0]['audioManifestId']);
        $t->same(4.25, $chapter['items'][0]['clipDurationSeconds']);
        $t->same('missing-media-overlay-text-reference', $chapter['items'][1]['diagnostics'][0]['type']);
        $t->same('external-media-overlay-audio-reference', $chapter['items'][1]['diagnostics'][1]['type']);
        $t->same(2.75, $chapter['items'][1]['clipDurationSeconds']);
        $t->same('invalid-media-overlay-clip-begin', $chapter['items'][2]['diagnostics'][0]['type']);

        $t->same('unexpected-media-overlay-type', $bad['diagnostics'][0]['type']);
        $t->same('text/css', $bad['mediaType']);
        $t->same('missing-media-overlay-manifest-item', $missing['diagnostics'][0]['type']);
        $t->same(['missing-overlay', 'mo-chapter', 'bad-overlay'], array_column($overlays['items'], 'id'));
        $t->same(['/EPUB/text/chapter1.xhtml#intro', '/EPUB/text/missing.xhtml', '/EPUB/text/chapter2.xhtml'], $overlays['textTargets']);
        $t->same(['/EPUB/audio/chapter.mp3', 'https://cdn.example.test/audio/review.mp3'], $overlays['audioTargets']);
        $t->same([
            'missing-media-overlay-manifest-item',
            'missing-media-overlay-text-reference',
            'external-media-overlay-audio-reference',
            'invalid-media-overlay-clip-begin',
            'unexpected-media-overlay-type',
        ], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $overlays['diagnostics']));

        $t->same($overlays, $summary['mediaOverlays']);
        $t->same($overlays, $summary['wordpressImport']['mediaOverlays']);
        $t->same($overlays['items'], $summary['wordpressImport']['mediaOverlayItems']);
        $t->same($overlays['textTargets'], $summary['wordpressImport']['mediaOverlayTargets']);
        $t->same($overlays['audioTargets'], $summary['wordpressImport']['mediaOverlayAudioTargets']);
        $t->same($overlays['diagnostics'], $summary['wordpressImport']['mediaOverlayDiagnostics']);
    },

    'preserves media-overlay reference ZIP provenance for package handoff' => static function (TestRunner $t) use ($buildZipPackage, $epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithMediaOverlay = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter"/>',
            $epub3OpfXml
        );
        $opfWithMediaOverlay = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="audio" href="audio/chapter.mp3" media-type="audio/mpeg"/>
    <item id="mo-chapter" href="overlays/chapter.smil" media-type="application/smil+xml"/>',
            $opfWithMediaOverlay
        );
        $smil = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL">
  <body>
    <seq>
      <par id="intro">
        <text src="../text/chapter1.xhtml?view=review#intro"/>
        <audio src="../audio/chapter.mp3?clip=1#t=0,4" clipBegin="0s" clipEnd="4s"/>
      </par>
    </seq>
  </body>
</smil>
XML;
        $chapter1 = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="intro">Intro</h1></body></html>';
        $audio = 'MP3-CHAPTER-AUDIO';

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml, 'method' => 8],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMediaOverlay, 'method' => 8],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml, 'method' => 8],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => $chapter1, 'method' => 8],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>', 'method' => 8],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }', 'method' => 8],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG', 'method' => 0],
            ['name' => 'EPUB/audio/chapter.mp3', 'data' => $audio, 'method' => 12],
            ['name' => 'EPUB/overlays/chapter.smil', 'data' => $smil, 'method' => 8],
        ]));
        $overlays = $epub->mediaOverlays();
        $summary = $epub->summary();
        $chapter = $overlays['itemsById']['mo-chapter'];
        $item = $chapter['items'][0];

        $t->same('/EPUB/text/chapter1.xhtml?view=review#intro', $item['textTarget']);
        $t->same('/EPUB/text/chapter1.xhtml', $item['textPartName']);
        $t->same('chapter1', $item['textManifestId']);
        $t->same('application/xhtml+xml', $item['textManifestMediaType']);
        $t->same(true, $item['textHrefHasQuery']);
        $t->same('view=review', $item['textHrefQuery']);
        $t->same(true, $item['textHrefHasFragment']);
        $t->same('intro', $item['textHrefFragment']);
        $t->same(strlen($chapter1), $item['textByteLength']);
        $t->same(strlen(gzdeflate($chapter1)), $item['textCompressedByteLength']);
        $t->same(8, $item['textCompressionMethod']);
        $t->same('deflated', $item['textCompressionMethodName']);
        $t->same(true, $item['textCompressionSupported']);
        $t->same(hash('crc32b', $chapter1), $item['textCrc32']);
        $t->same(true, $item['textCanExposeBytes']);

        $t->same('/EPUB/audio/chapter.mp3?clip=1#t=0,4', $item['audioTarget']);
        $t->same('/EPUB/audio/chapter.mp3', $item['audioPartName']);
        $t->same('audio', $item['audioManifestId']);
        $t->same('audio/mpeg', $item['audioManifestMediaType']);
        $t->same(true, $item['audioHrefHasQuery']);
        $t->same('clip=1', $item['audioHrefQuery']);
        $t->same(true, $item['audioHrefHasFragment']);
        $t->same('t=0,4', $item['audioHrefFragment']);
        $t->same(strlen($audio), $item['audioByteLength']);
        $t->same(strlen($audio), $item['audioCompressedByteLength']);
        $t->same(12, $item['audioCompressionMethod']);
        $t->same('unsupported', $item['audioCompressionMethodName']);
        $t->same(false, $item['audioCompressionSupported']);
        $t->same(hash('crc32b', $audio), $item['audioCrc32']);
        $t->same(false, $item['audioCanExposeBytes']);
        $t->same(4.0, $item['clipDurationSeconds']);

        $t->same($overlays, $summary['mediaOverlays']);
        $t->same($overlays, $summary['wordpressImport']['mediaOverlays']);
        $t->same($overlays['items'], $summary['wordpressImport']['mediaOverlayItems']);
    },

    'summarizes OPF manifest fallback chains for compact package preflight' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $fallbackXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Fallback review content.</p></body></html>';
        $fallbackCss = 'body { color: #123456; }';
        $opfWithFallbacks = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="custom-ok" href="widgets/custom-ok.bin" media-type="application/x-review-widget" fallback="ok-fallback" fallback-style="widget-style"/>
    <item id="ok-fallback" href="text/ok-fallback.xhtml" media-type="application/xhtml+xml"/>
    <item id="poster-heic" href="images/poster.heic" media-type="image/heic" fallback="cover"/>
    <item id="custom-missing" href="widgets/custom-missing.bin" media-type="application/x-review-widget" fallback="missing-fallback"/>
    <item id="custom-cycle-a" href="widgets/cycle-a.bin" media-type="application/x-review-widget" fallback="custom-cycle-b"/>
    <item id="custom-cycle-b" href="widgets/cycle-b.bin" media-type="application/x-review-widget" fallback="custom-cycle-a"/>
    <item id="bad-style-widget" href="widgets/bad-style.bin" media-type="application/x-review-widget" fallback-style="cover"/>
    <item id="missing-style-widget" href="widgets/missing-style.bin" media-type="application/x-review-widget" fallback-style="missing-style"/>
    <item id="style-cycle-a" href="widgets/style-cycle-a.bin" media-type="application/x-review-widget" fallback-style="style-cycle-b"/>
    <item id="style-cycle-b" href="widgets/style-cycle-b.bin" media-type="application/x-review-widget" fallback-style="style-cycle-a"/>
    <item id="widget-style" href="styles/widget.css" media-type="text/css"/>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithFallbacks],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/text/ok-fallback.xhtml', 'data' => $fallbackXhtml],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/styles/widget.css', 'data' => $fallbackCss],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/images/poster.heic', 'data' => 'HEIC'],
            ['name' => 'EPUB/widgets/custom-ok.bin', 'data' => 'CUSTOM-OK'],
            ['name' => 'EPUB/widgets/custom-missing.bin', 'data' => 'CUSTOM-MISSING'],
            ['name' => 'EPUB/widgets/cycle-a.bin', 'data' => 'CYCLE-A'],
            ['name' => 'EPUB/widgets/cycle-b.bin', 'data' => 'CYCLE-B'],
            ['name' => 'EPUB/widgets/bad-style.bin', 'data' => 'BAD-STYLE'],
            ['name' => 'EPUB/widgets/missing-style.bin', 'data' => 'MISSING-STYLE'],
            ['name' => 'EPUB/widgets/style-cycle-a.bin', 'data' => 'STYLE-CYCLE-A'],
            ['name' => 'EPUB/widgets/style-cycle-b.bin', 'data' => 'STYLE-CYCLE-B'],
        ]));

        $fallbacks = $epub->manifestFallbacks();
        $summary = $epub->summary();
        $itemsById = $fallbacks['itemsById'];

        $t->same(true, $fallbacks['present']);
        $t->same(9, $fallbacks['itemCount']);
        $t->same(5, $fallbacks['fallbackCount']);
        $t->same(2, $fallbacks['resolvedFallbackCount']);
        $t->same(3, $fallbacks['fallbackDiagnosticCount']);
        $t->same(5, $fallbacks['fallbackStyleCount']);
        $t->same(1, $fallbacks['resolvedFallbackStyleCount']);
        $t->same(4, $fallbacks['fallbackStyleDiagnosticCount']);

        $ok = $itemsById['custom-ok'];
        $t->same('ok-fallback', $ok['fallbackId']);
        $t->same(true, $ok['fallbackResolved']);
        $t->same(true, $ok['fallbackUsable']);
        $t->same('ok-fallback', $ok['fallbackTerminalId']);
        $t->same('/EPUB/text/ok-fallback.xhtml', $ok['fallbackTerminalPartName']);
        $t->same('application/xhtml+xml', $ok['fallbackTerminalMediaType']);
        $t->same(true, $ok['fallbackTerminalEpubContentDocument']);
        $t->same(strlen($fallbackXhtml), $ok['fallbackChain'][0]['byteLength']);
        $t->same(hash('crc32b', $fallbackXhtml), $ok['fallbackChain'][0]['crc32']);
        $t->same('widget-style', $ok['fallbackStyleId']);
        $t->same(true, $ok['fallbackStyleResolved']);
        $t->same('/EPUB/styles/widget.css', $ok['fallbackStyleTerminalPartName']);
        $t->same(hash('crc32b', $fallbackCss), $ok['fallbackStyleChain'][0]['crc32']);

        $poster = $itemsById['poster-heic'];
        $t->same('cover', $poster['fallbackId']);
        $t->same(true, $poster['fallbackResolved']);
        $t->same('image/png', $poster['fallbackTerminalMediaType']);
        $t->same(false, $poster['fallbackTerminalEpubContentDocument']);
        $t->same('image', $poster['fallbackChain'][0]['coreMediaTypeKind']);

        $missing = $itemsById['custom-missing'];
        $t->same(false, $missing['fallbackResolved']);
        $t->same('missing-fallback', $missing['fallbackId']);
        $t->same('missing-manifest-fallback-item', $missing['fallbackDiagnostics'][0]['type']);

        $cycle = $itemsById['custom-cycle-a'];
        $t->same(false, $cycle['fallbackResolved']);
        $t->same(['custom-cycle-b'], array_column($cycle['fallbackChain'], 'id'));
        $t->same('cyclic-manifest-fallback-chain', $cycle['fallbackDiagnostics'][0]['type']);

        $badStyle = $itemsById['bad-style-widget'];
        $t->same('cover', $badStyle['fallbackStyleId']);
        $t->same(false, $badStyle['fallbackStyleResolved']);
        $t->same('non-css-manifest-fallback-style', $badStyle['fallbackStyleDiagnostics'][0]['type']);

        $missingStyle = $itemsById['missing-style-widget'];
        $t->same('missing-manifest-fallback-style-item', $missingStyle['fallbackStyleDiagnostics'][0]['type']);

        $styleCycle = $itemsById['style-cycle-a'];
        $t->same(['style-cycle-b'], array_column($styleCycle['fallbackStyleChain'], 'id'));
        $t->same('cyclic-manifest-fallback-style-chain', $styleCycle['fallbackStyleDiagnostics'][0]['type']);

        $t->same($fallbacks, $summary['manifestFallbacks']);
        $t->same($fallbacks, $summary['wordpressImport']['manifestFallbacks']);
        $t->same(['custom-ok', 'poster-heic', 'custom-missing', 'custom-cycle-a', 'custom-cycle-b'], array_column($summary['wordpressImport']['manifestFallbackItems'], 'id'));
        $t->same(['custom-ok', 'bad-style-widget', 'missing-style-widget', 'style-cycle-a', 'style-cycle-b'], array_column($summary['wordpressImport']['manifestFallbackStyleItems'], 'id'));
        $t->same(['missing-manifest-fallback-item', 'cyclic-manifest-fallback-chain', 'cyclic-manifest-fallback-chain', 'non-css-manifest-fallback-style', 'missing-manifest-fallback-style-item', 'cyclic-manifest-fallback-style-chain', 'cyclic-manifest-fallback-style-chain'], array_column($summary['wordpressImport']['manifestFallbackDiagnostics'], 'type'));
    },

    'reports OPF non core manifest resources without fallback for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithMissingFallback = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="orphan-widget" href="widgets/orphan.bin" media-type="application/x-review-widget"/>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMissingFallback],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/widgets/orphan.bin', 'data' => 'ORPHAN-WIDGET'],
        ]));

        $fallbacks = $epub->manifestFallbacks();
        $summary = $epub->summary();
        $orphan = $fallbacks['itemsById']['orphan-widget'];
        $diagnostic = $orphan['fallbackDiagnostics'][0];

        $t->same(true, $fallbacks['present']);
        $t->same(1, $fallbacks['itemCount']);
        $t->same(0, $fallbacks['fallbackCount']);
        $t->same(0, $fallbacks['fallbackStyleCount']);
        $t->same(1, $fallbacks['missingFallbackCount']);
        $t->same(1, $fallbacks['missingFallbackDiagnosticCount']);
        $t->same(1, count($fallbacks['diagnostics']));

        $t->same('orphan-widget', $orphan['id']);
        $t->same('/EPUB/widgets/orphan.bin', $orphan['partName']);
        $t->same('application/x-review-widget', $orphan['mediaType']);
        $t->same(null, $orphan['fallbackId']);
        $t->same(false, $orphan['fallbackResolved']);
        $t->same(false, $orphan['fallbackUsable']);
        $t->same([], $orphan['fallbackChain']);
        $t->same('missing-manifest-fallback-for-non-core-media-type', $diagnostic['type']);
        $t->same('/EPUB/widgets/orphan.bin', $diagnostic['partName']);
        $t->same('application/x-review-widget', $diagnostic['mediaType']);

        $t->same(['orphan-widget'], array_column($fallbacks['missingFallbackItems'], 'id'));
        $t->same([], $summary['wordpressImport']['manifestFallbackItems']);
        $t->same($fallbacks['missingFallbackItems'], $summary['wordpressImport']['manifestFallbacks']['missingFallbackItems']);
        $t->same(['missing-manifest-fallback-for-non-core-media-type'], array_column($summary['wordpressImport']['manifestFallbackDiagnostics'], 'type'));
    },

    'summarizes OPF manifest resource properties for compact package preflight' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithResourceProperties = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/ review: https://example.invalid/epub-review#">',
            $epub3OpfXml
        );
        $opfWithResourceProperties = str_replace(
            '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>',
            '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav rendition:layout-pre-paginated"/>',
            $opfWithResourceProperties
        );
        $opfWithResourceProperties = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" properties="mathml svg remote-resources schema:encodingFormat review:source-record unknown:review-flag"/>',
            $opfWithResourceProperties
        );
        $opfWithResourceProperties = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml" properties="scripted switch"/>',
            $opfWithResourceProperties
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithResourceProperties],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><math/><svg/></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><script>review()</script></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $report = $epub->resourceProperties();
        $summary = $epub->summary();

        $t->same(1, $report['summary']['navCount']);
        $t->same(1, $report['summary']['coverImageCount']);
        $t->same(1, $report['summary']['mathmlCount']);
        $t->same(1, $report['summary']['svgCount']);
        $t->same(1, $report['summary']['remoteResourcesCount']);
        $t->same(1, $report['summary']['scriptedCount']);
        $t->same(1, $report['summary']['switchCount']);
        $t->same(2, $report['summary']['reviewRequiredCount']);

        $t->same('chapter1', $report['itemsByProperty']['mathml'][0]['id']);
        $t->same('chapter1', $report['itemsByProperty']['remote-resources'][0]['id']);
        $t->same('chapter2', $report['itemsByProperty']['scripted'][0]['id']);
        $t->same('/EPUB/text/chapter1.xhtml', $report['itemsById']['chapter1']['partName']);
        $t->same(['mathml', 'svg', 'remote-resources'], $report['itemsById']['chapter1']['reviewFlags']);
        $t->same(['scripted', 'switch'], $report['itemsById']['chapter2']['reviewFlags']);
        $t->same(true, $report['itemsById']['chapter1']['reviewRequired']);
        $t->same('chapter2', $report['reviewItems'][1]['id']);

        $vocabulary = $report['propertyVocabulary'];
        $t->same(true, $vocabulary['present']);
        $t->same(4, $vocabulary['itemCount']);
        $t->same(11, $vocabulary['propertyTokenCount']);
        $t->same(4, $vocabulary['prefixedPropertyCount']);
        $t->same(3, $vocabulary['resolvedPropertyCount']);
        $t->same(1, $vocabulary['unresolvedPropertyCount']);
        $t->same('http://www.idpf.org/vocab/rendition/#layout-pre-paginated', $vocabulary['itemsById']['nav']['propertyVocabulary']['items'][1]['vocabulary']['iri']);
        $t->same('https://schema.org/encodingFormat', $report['itemsById']['chapter1']['propertyVocabulary']['items'][3]['vocabulary']['iri']);
        $t->same('https://example.invalid/epub-review#source-record', $report['itemsById']['chapter1']['propertyVocabulary']['items'][4]['vocabulary']['iri']);
        $t->same(['schema:encodingFormat'], $vocabulary['byPrefix']['schema']['properties']);
        $t->same(['chapter1'], $vocabulary['byPrefix']['schema']['manifestIds']);
        $t->same(['unknown:review-flag'], $vocabulary['byPrefix']['unknown']['properties']);
        $t->same(1, $vocabulary['diagnosticCount']);
        $t->same('unknown-manifest-property-prefix', $vocabulary['diagnostics'][0]['type']);
        $t->same('unknown:review-flag', $vocabulary['diagnostics'][0]['property']);

        $t->same($report, $summary['resourceProperties']);
        $t->same($report, $summary['wordpressImport']['resourceProperties']);
        $t->same($report['summary'], $summary['wordpressImport']['resourcePropertySummary']);
        $t->same($report['reviewItems'], $summary['wordpressImport']['resourcePropertyReviewItems']);
        $t->same($vocabulary['diagnostics'], $summary['wordpressImport']['resourcePropertyDiagnostics']);
    },

    'summarizes OPF package rendition metadata for compact package preflight' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithRenditionMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta id="fixed-layout" property="rendition:layout" xml:lang="en" dir="ltr">pre-paginated</meta>
    <meta property="rendition:layout">reflowable</meta>
    <meta id="scroll-flow" property="rendition:flow" xml:lang="en" dir="ltr">scrolled-continuous</meta>
    <meta property="rendition:flow">sideways</meta>
    <meta property="rendition:orientation" content="landscape"/>
    <meta property="rendition:spread">diagonal</meta>
    <meta property="rendition:spread">none</meta>
    <meta property="rendition:viewport">width=1024, height=768</meta>
    <meta id="bad-viewport" property="rendition:viewport">width=cover,height=0,scale=1</meta>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithRenditionMetadata],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $rendition = $epub->metadata()['renditionLayout'];
        $summary = $epub->summary();

        $t->same(true, $rendition['present']);
        $t->same(true, $rendition['fixedLayout']);
        $t->same('pre-paginated', $rendition['layout']);
        $t->same('pre-paginated', $rendition['layoutRaw']);
        $t->same('fixed-layout', $rendition['layoutProperty']['selected']['id']);
        $t->same('en', $rendition['layoutProperty']['selected']['language']);
        $t->same('ltr', $rendition['layoutProperty']['selected']['direction']);
        $t->same(false, $rendition['layoutProperty']['valid']);
        $t->same(['pre-paginated', 'reflowable'], $rendition['layoutProperty']['diagnostics'][0]['values']);
        $t->same('scrolled-continuous', $rendition['flow']);
        $t->same('scrolled-continuous', $rendition['flowRaw']);
        $t->same('scroll-flow', $rendition['flowProperty']['selected']['id']);
        $t->same('en', $rendition['flowProperty']['selected']['language']);
        $t->same('ltr', $rendition['flowProperty']['selected']['direction']);
        $t->same(1, $rendition['flowProperty']['invalidCount']);
        $t->same('invalid-rendition-flow-value', $rendition['flowProperty']['diagnostics'][0]['type']);
        $t->same('landscape', $rendition['orientation']);
        $t->same('none', $rendition['spread']);
        $t->same(1, $rendition['spreadProperty']['invalidCount']);
        $t->same('invalid-rendition-spread-value', $rendition['spreadProperty']['diagnostics'][0]['type']);

        $t->same('width=1024, height=768', $rendition['viewportRaw']);
        $t->same(1024, $rendition['viewportWidth']);
        $t->same(768, $rendition['viewportHeight']);
        $t->same(2, $rendition['viewportCount']);
        $t->same(1, $rendition['validViewportCount']);
        $t->same(1, $rendition['invalidViewportCount']);
        $t->same(['width' => '1024', 'height' => '768'], $rendition['viewport']['parameters']);
        $t->same('bad-viewport', $rendition['viewports'][1]['id']);
        $t->same(false, $rendition['viewports'][1]['valid']);
        $t->same(['width' => 'cover', 'height' => '0', 'scale' => '1'], $rendition['viewports'][1]['parameters']);
        $t->same(['conflicting-rendition-layout-values', 'invalid-rendition-flow-value', 'invalid-rendition-spread-value', 'invalid-rendition-viewport-width', 'invalid-rendition-viewport-height', 'unknown-rendition-viewport-parameter'], array_column($rendition['diagnostics'], 'type'));

        $t->same($rendition, $summary['renditionLayout']);
        $t->same($rendition, $summary['metadata']['renditionLayout']);
        $t->same($rendition, $summary['wordpressImport']['metadataDetails']['renditionLayout']);
    },

    'summarizes OCF encrypted resource exposure for compact package preflight' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithEncryptedResources = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/book.css" media-type="text/css"/>
    <item id="locked-style" href="styles/locked.css" media-type="text/css"/>
    <item id="locked-audio" href="audio/locked.mp3" media-type="audio/mpeg"/>
    <item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>',
            $epub3OpfXml
        );
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/images/cover.png"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/styles/locked.css"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/audio/locked.mp3"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData><CipherReference URI="EPUB/fonts/source.otf"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithEncryptedResources],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/styles/locked.css', 'data' => 'body { color: red; }'],
            ['name' => 'EPUB/audio/locked.mp3', 'data' => 'LOCKED-MP3'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/fonts/source.otf', 'data' => 'OBFUSCATED-FONT'],
        ]));

        $encryption = $epub->encryption();
        $summary = $epub->summary();
        $exposure = $encryption['exposure'];
        $itemsByPart = [];
        foreach ($exposure['items'] as $item) {
            $itemsByPart[$item['partName']] = $item;
        }

        $t->same(true, $encryption['present']);
        $t->same('/META-INF/encryption.xml', $encryption['part']);
        $t->same(['/EPUB/images/cover.png', '/EPUB/styles/locked.css', '/EPUB/audio/locked.mp3', '/EPUB/fonts/source.otf'], $encryption['encryptedParts']);
        $t->same('/EPUB/fonts/source.otf', $encryption['obfuscatedFonts'][0]['partName']);
        $t->same([], $encryption['diagnostics']);

        $t->same(true, $exposure['present']);
        $t->same(4, $exposure['itemCount']);
        $t->same(4, $exposure['blockedByteExposureCount']);
        $t->same(1, $exposure['obfuscatedFontCount']);
        $t->same(3, $exposure['nonObfuscatedEncryptedCount']);
        $t->same(3, $exposure['attachmentCandidateBlockedCount']);
        $t->same(['audio', 'cover-image', 'font', 'stylesheet'], $exposure['roles']);
        $t->same([
            'audio' => 1,
            'cover-image' => 1,
            'font' => 1,
            'stylesheet' => 1,
        ], $exposure['roleCounts']);
        $t->same(['/EPUB/fonts/source.otf'], $exposure['obfuscatedFontParts']);
        $t->same(['/EPUB/audio/locked.mp3', '/EPUB/images/cover.png', '/EPUB/styles/locked.css'], $exposure['nonObfuscatedEncryptedParts']);

        $t->same('stylesheet', $itemsByPart['/EPUB/styles/locked.css']['role']);
        $t->same('encrypted-resource-review', $itemsByPart['/EPUB/styles/locked.css']['reviewPolicy']);
        $t->same('encrypted-resource-bytes-blocked', $itemsByPart['/EPUB/styles/locked.css']['byteExposurePolicy']);
        $t->same(false, $itemsByPart['/EPUB/styles/locked.css']['attachmentCandidateBlocked']);
        $t->same('cover-image', $itemsByPart['/EPUB/images/cover.png']['role']);
        $t->same(true, $itemsByPart['/EPUB/images/cover.png']['attachmentCandidateBlocked']);
        $t->same('audio', $itemsByPart['/EPUB/audio/locked.mp3']['role']);
        $t->same(true, $itemsByPart['/EPUB/audio/locked.mp3']['attachmentCandidateBlocked']);
        $t->same('font', $itemsByPart['/EPUB/fonts/source.otf']['role']);
        $t->same('obfuscated-font-review', $itemsByPart['/EPUB/fonts/source.otf']['reviewPolicy']);
        $t->same('obfuscated-font-bytes-blocked', $itemsByPart['/EPUB/fonts/source.otf']['byteExposurePolicy']);

        $t->same(true, $epub->manifestItem('cover')['encrypted']);
        $t->same(false, $epub->manifestItem('cover')['canExposeBytes']);
        $t->same('cover-image', $epub->manifestItem('cover')['encryption']['role']);
        $t->same('stylesheet', $epub->manifestItem('locked-style')['encryption']['role']);
        $t->same('audio', $epub->manifestItem('locked-audio')['encryption']['role']);
        $t->same('font', $epub->manifestItem('font-main')['encryption']['role']);
        $t->same('obfuscated-font-review', $epub->manifestItem('font-main')['encryption']['reviewPolicy']);

        $t->same($encryption, $summary['encryption']);
        $t->same($encryption, $summary['wordpressImport']['encryption']);
        $t->same($exposure, $summary['wordpressImport']['encryptedResourceExposure']);
        $t->same([], $summary['wordpressImport']['encryptedResourceDiagnostics']);
    },

    'summarizes compact EPUB package validation diagnostics for review handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithReviewDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:diagnostic-review</dc:identifier>
    <dc:title>Diagnostic EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="nav-css" href="styles/nav.css" media-type="text/css" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter-alias" href="chapter.xhtml#duplicate" media-type="application/xhtml+xml"/>
    <item id="audio" href="audio/chapter.mp3" media-type="audio/mpeg"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="audio" linear="no"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithReviewDiagnostics],
            ['name' => 'EPUB/styles/nav.css', 'data' => 'nav { display: block; }'],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Diagnostic</body></html>'],
            ['name' => 'EPUB/audio/chapter.mp3', 'data' => 'MP3'],
        ]));

        $validation = $epub->validationReport();
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same('3.0', $validation['packageVersion']);
        $t->same(true, $validation['epub3']);
        $t->same(7, $validation['diagnosticCount']);
        $t->same([
            'missing-epub-metadata-language',
            'missing-epub3-modified-metadata',
            'nav-property-non-xhtml-manifest-item',
            'manifest-href-fragment-component',
            'missing-epub3-nav-document',
            'duplicate-manifest-part-target',
            'non-content-document-spine-item',
        ], array_column($validation['diagnostics'], 'type'));

        $t->same(false, $validation['metadata']['valid']);
        $t->same(true, $validation['metadata']['titlePresent']);
        $t->same(true, $validation['metadata']['identifierPresent']);
        $t->same(false, $validation['metadata']['languagePresent']);
        $t->same(false, $validation['metadata']['modifiedPresent']);
        $t->same(['missing-epub-metadata-language', 'missing-epub3-modified-metadata'], array_column($validation['metadata']['diagnostics'], 'type'));

        $t->same(false, $validation['manifest']['valid']);
        $t->same(4, $validation['manifest']['itemCount']);
        $t->same(1, $validation['manifest']['navItemCount']);
        $t->same(0, $validation['manifest']['usableNavItemCount']);
        $t->same(['nav-css'], array_column($validation['manifest']['navItems'], 'id'));
        $t->same(['nav-css'], array_column($validation['manifest']['invalidNavItems'], 'id'));
        $t->same(1, $validation['manifest']['duplicatePartCount']);
        $t->same('/EPUB/chapter.xhtml', $validation['manifest']['duplicatePartItems'][0]['partName']);
        $t->same(['chapter', 'chapter-alias'], $validation['manifest']['duplicatePartItems'][0]['ids']);
        $t->same(1, $validation['manifest']['hrefSuffixCount']);
        $t->same('chapter-alias', $validation['manifest']['hrefSuffixItems'][0]['id']);
        $t->same('duplicate', $validation['manifest']['hrefSuffixItems'][0]['fragment']);

        $t->same(false, $validation['spine']['valid']);
        $t->same(2, $validation['spine']['itemCount']);
        $t->same(1, $validation['spine']['linearCount']);
        $t->same(1, $validation['spine']['nonLinearCount']);
        $t->same(1, $validation['spine']['nonContentDocumentCount']);
        $t->same('audio', $validation['spine']['nonContentDocumentItems'][0]['idref']);

        $t->same(true, $validation['navigation']['valid']);
        $t->same(null, $validation['navigation']['source']);
        $t->same(0, $validation['navigation']['entryCount']);
        $t->same([], $validation['navigation']['diagnostics']);

        $opfWithNavTargetDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-diagnostic-review</dc:identifier>
    <dc:title>Navigation diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T12:04:26Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="nav-alt" href="nav-alt.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML;
        $navWithMissingAndRemoteTargets = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Navigation target diagnostics</h1>
      <ol>
        <li><a href="chapter.xhtml">Start</a></li>
        <li><a href="missing.xhtml">Missing appendix</a></li>
        <li><a href="https://example.invalid/remote.xhtml">Remote appendix</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epubWithNavDiagnostics = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavTargetDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithMissingAndRemoteTargets],
            ['name' => 'EPUB/nav-alt.xhtml', 'data' => $navWithMissingAndRemoteTargets],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Start</body></html>'],
        ]));
        $navValidation = $epubWithNavDiagnostics->validationReport();

        $t->same(false, $navValidation['valid']);
        $t->same(3, $navValidation['diagnosticCount']);
        $t->same(['multiple-epub3-nav-documents', 'missing-navigation-target', 'external-navigation-target'], array_column($navValidation['diagnostics'], 'type'));
        $t->same(2, $navValidation['manifest']['usableNavItemCount']);
        $t->same(['nav', 'nav-alt'], array_column($navValidation['manifest']['usableNavItems'], 'id'));
        $t->same('nav', $navValidation['navigation']['source']);
        $t->same(3, $navValidation['navigation']['entryCount']);
        $t->same(1, $navValidation['navigation']['missingTargetCount']);
        $t->same(1, $navValidation['navigation']['externalTargetCount']);
        $t->same('/EPUB/missing.xhtml', $navValidation['navigation']['diagnostics'][0]['partName']);
        $t->same('https://example.invalid/remote.xhtml', $navValidation['navigation']['diagnostics'][1]['target']);

        $t->same($validation, $summary['validation']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
    },

    'reports malformed EPUB3 nav documents without aborting package ingestion' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithMalformedNav = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:malformed-nav-document</dc:identifier>
    <dc:title>Malformed navigation package</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-11T20:11:10Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML;
        $malformedNav = '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><h1>Contents</h1>';

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMalformedNav],
            ['name' => 'EPUB/nav.xhtml', 'data' => $malformedNav],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
        ]));
        $navigation = $epub->navigation();
        $validation = $epub->validationReport();
        $documentDiagnostics = $validation['navigation']['documentDiagnostics'];
        $summary = $epub->summary();

        $t->same('nav', $navigation['type']);
        $t->same('/EPUB/nav.xhtml', $navigation['partName']);
        $t->same(false, $navigation['valid']);
        $t->same([], $navigation['entries']);
        $t->same(['invalid-nav-document'], array_column($navigation['diagnostics'], 'type'));
        $t->contains('Unable to parse EPUB navigation document', $navigation['diagnostics'][0]['error']);

        $t->same(false, $validation['valid']);
        $t->same(1, $validation['diagnosticCount']);
        $t->same(['invalid-nav-document'], array_column($validation['diagnostics'], 'type'));
        $t->same('nav', $validation['navigation']['source']);
        $t->same(0, $validation['navigation']['sectionCount']);
        $t->same(0, $validation['navigation']['entryCount']);
        $t->same(1, $validation['navigation']['diagnosticCount']);
        $t->same(1, $validation['navigation']['documentDiagnosticCount']);
        $t->same(true, $documentDiagnostics['present']);
        $t->same('/EPUB/nav.xhtml', $documentDiagnostics['part']);
        $t->same(0, $documentDiagnostics['sectionCount']);
        $t->same(1, $documentDiagnostics['documentParseDiagnosticCount']);
        $t->same(['invalid-nav-document'], array_column($documentDiagnostics['documentParseDiagnostics'], 'type'));
        $t->same(['invalid-nav-document'], array_column($documentDiagnostics['diagnostics'], 'type'));

        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
    },

    'reports compact EPUB nav document diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavDocumentDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-document-diagnostics</dc:identifier>
    <dc:title>Navigation document diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T19:30:47Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter2" href="chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
  </spine>
</package>
XML;
        $navWithDocumentDiagnostics = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="hidden-toc" epub:type="toc" hidden="hidden">
      <h1>Hidden source contents</h1>
    </nav>
    <nav id="visible-toc" epub:type="toc">
      <h2>Visible contents</h2>
      <ol>
        <li><a href="chapter1.xhtml">Imported packet</a></li>
      </ol>
    </nav>
    <nav id="untyped-review">
      <ol>
        <li><a href="chapter2.xhtml">Untyped review trail</a></li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <h2>Print page list</h2>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavDocumentDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithDocumentDiagnostics],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter 1</body></html>'],
            ['name' => 'EPUB/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter 2</body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(7, $validation['diagnosticCount']);
        $t->same([
            'hidden-primary-nav-section',
            'missing-nav-section-ordered-list',
            'empty-nav-section',
            'missing-nav-section-type',
            'missing-nav-section-ordered-list',
            'empty-nav-section',
            'duplicate-primary-nav-section',
        ], array_column($validation['diagnostics'], 'type'));

        $t->same('nav', $navigation['source']);
        $t->same(4, $navigation['sectionCount']);
        $t->same(2, $navigation['entryCount']);
        $t->same(2, $navigation['localTargetCount']);
        $t->same(0, $navigation['missingTargetCount']);
        $t->same(0, $navigation['externalTargetCount']);
        $t->same(7, $navigation['documentDiagnosticCount']);
        $t->same(7, $navigation['diagnosticCount']);

        $t->same(true, $documentDiagnostics['present']);
        $t->same('/EPUB/nav.xhtml', $documentDiagnostics['part']);
        $t->same(4, $documentDiagnostics['sectionCount']);
        $t->same(3, $documentDiagnostics['primarySectionCount']);
        $t->same(2, $documentDiagnostics['tocSectionCount']);
        $t->same(0, $documentDiagnostics['landmarksSectionCount']);
        $t->same(1, $documentDiagnostics['pageListSectionCount']);
        $t->same(1, $documentDiagnostics['duplicatePrimaryTypeCount']);
        $t->same(2, $documentDiagnostics['emptySectionCount']);
        $t->same(1, $documentDiagnostics['hiddenPrimarySectionCount']);
        $t->same(2, $documentDiagnostics['missingOrderedListSectionCount']);
        $t->same(1, $documentDiagnostics['untypedSectionCount']);
        $t->same('hidden-toc', $documentDiagnostics['diagnostics'][0]['sectionId']);
        $t->same(['toc'], $documentDiagnostics['diagnostics'][0]['sectionTypes']);
        $t->same('untyped-review', $documentDiagnostics['diagnostics'][3]['sectionId']);
        $t->same('toc', $documentDiagnostics['diagnostics'][6]['sectionType']);
        $t->same([0, 1], $documentDiagnostics['diagnostics'][6]['sectionIndexes']);
        $t->same(['hidden-toc', 'visible-toc'], $documentDiagnostics['diagnostics'][6]['sectionIds']);

        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB nav heading diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavHeadingDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-heading-diagnostics</dc:identifier>
    <dc:title>Navigation heading diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T20:40:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
  </spine>
</package>
XML;
        $navWithMissingHeading = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <ol>
        <li><a href="chapter1.xhtml">Imported packet</a></li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <h2>Print page list</h2>
      <ol>
        <li><a href="chapter1.xhtml#page-1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavHeadingDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithMissingHeading],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 1</h1><span id="page-1"></span></body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(['missing-primary-nav-section-heading'], array_column($validation['diagnostics'], 'type'));
        $t->same(1, $navigation['diagnosticCount']);
        $t->same(1, $navigation['documentDiagnosticCount']);
        $t->same(2, $navigation['entryCount']);
        $t->same(2, $navigation['localTargetCount']);
        $t->same(0, $navigation['missingTargetCount']);
        $t->same(true, $documentDiagnostics['present']);
        $t->same('/EPUB/nav.xhtml', $documentDiagnostics['part']);
        $t->same(2, $documentDiagnostics['primarySectionCount']);
        $t->same(1, $documentDiagnostics['missingHeadingSectionCount']);
        $t->same(0, $documentDiagnostics['missingOrderedListSectionCount']);
        $t->same('main-toc', $documentDiagnostics['diagnostics'][0]['sectionId']);
        $t->same(['toc'], $documentDiagnostics['diagnostics'][0]['sectionTypes']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB nav item label diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavItemLabelDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-item-label-diagnostics</dc:identifier>
    <dc:title>Navigation item label diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T22:47:34Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter2" href="chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2"/>
  </spine>
</package>
XML;
        $navWithMissingItemLabels = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="chapter1.xhtml"></a></li>
        <li><a href="chapter2.xhtml">Chapter two</a></li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <h2>Print page list</h2>
      <ol>
        <li><a href="chapter1.xhtml#page-1"></a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavItemLabelDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithMissingItemLabels],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 1</h1><span id="page-1"></span></body></html>'],
            ['name' => 'EPUB/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 2</h1></body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same([
            'missing-primary-nav-item-label',
            'empty-nav-item-label',
            'missing-primary-nav-item-label',
            'empty-nav-item-label',
        ], array_column($validation['diagnostics'], 'type'));
        $t->same(4, $navigation['diagnosticCount']);
        $t->same(4, $navigation['documentDiagnosticCount']);
        $t->same(3, $navigation['entryCount']);
        $t->same(3, $navigation['localTargetCount']);
        $t->same(0, $navigation['missingTargetCount']);
        $t->same(0, $navigation['externalTargetCount']);
        $t->same(true, $documentDiagnostics['present']);
        $t->same(2, $documentDiagnostics['primarySectionCount']);
        $t->same(0, $documentDiagnostics['missingHeadingSectionCount']);
        $t->same(2, $documentDiagnostics['missingEntryLabelCount']);
        $t->same(2, $documentDiagnostics['missingPrimaryItemLabelCount']);

        $tocDiagnostic = $documentDiagnostics['diagnostics'][0];
        $t->same('main-toc', $tocDiagnostic['sectionId']);
        $t->same('toc', $tocDiagnostic['sectionType']);
        $t->same(0, $tocDiagnostic['entryIndex']);
        $t->same('chapter1.xhtml', $tocDiagnostic['href']);
        $t->same('/EPUB/chapter1.xhtml', $tocDiagnostic['target']);
        $t->same(1, $tocDiagnostic['depth']);

        $pageDiagnostic = $documentDiagnostics['diagnostics'][2];
        $t->same('print-pages', $pageDiagnostic['sectionId']);
        $t->same(['page-list'], $pageDiagnostic['sectionTypes']);
        $t->same('/EPUB/chapter1.xhtml#page-1', $pageDiagnostic['target']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB nav entry label diagnostics for non primary sections' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavEntryLabelDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-entry-label-diagnostics</dc:identifier>
    <dc:title>Navigation entry label diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T22:40:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
  </spine>
</package>
XML;
        $navWithEntryLabelDiagnostics = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="chapter1.xhtml#review">Review packet</a></li>
      </ol>
    </nav>
    <nav id="review-trails" epub:type="loa">
      <h2>Review trails</h2>
      <ol>
        <li><a href="chapter1.xhtml#cover"><span class="icon"></span></a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavEntryLabelDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithEntryLabelDiagnostics],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="review">Chapter 1</h1><figure id="cover"></figure></body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(['missing-nav-entry-label', 'empty-nav-item-label'], array_column($validation['diagnostics'], 'type'));
        $t->same(2, $navigation['diagnosticCount']);
        $t->same(2, $navigation['documentDiagnosticCount']);
        $t->same(2, $navigation['entryCount']);
        $t->same(2, $navigation['localTargetCount']);
        $t->same(0, $navigation['missingTargetCount']);
        $t->same(true, $documentDiagnostics['present']);
        $t->same(1, $documentDiagnostics['primarySectionCount']);
        $t->same(0, $documentDiagnostics['missingHeadingSectionCount']);
        $t->same(0, $documentDiagnostics['missingOrderedListSectionCount']);
        $t->same(1, $documentDiagnostics['missingEntryLabelCount']);
        $t->same(0, $documentDiagnostics['missingPrimaryItemLabelCount']);

        $entryDiagnostic = $documentDiagnostics['diagnostics'][0];
        $t->same('review-trails', $entryDiagnostic['sectionId']);
        $t->same(['loa'], $entryDiagnostic['sectionTypes']);
        $t->same(0, $entryDiagnostic['entryIndex']);
        $t->same('chapter1.xhtml#cover', $entryDiagnostic['href']);
        $t->same('/EPUB/chapter1.xhtml#cover', $entryDiagnostic['target']);
        $t->same(1, $entryDiagnostic['depth']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB nav item diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavItemDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-item-diagnostics</dc:identifier>
    <dc:title>Navigation item diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T20:58:30Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter2" href="chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2"/>
  </spine>
</package>
XML;
        $navWithItemDiagnostics = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li id="empty-label"><a id="empty-label-link" href="chapter1.xhtml"> </a></li>
        <li id="missing-href"><a id="missing-href-link">Untargeted chapter</a></li>
        <li id="missing-label"><ol><li><a href="chapter2.xhtml">Nested recovery</a></li></ol></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavItemDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithItemDiagnostics],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 1</h1></body></html>'],
            ['name' => 'EPUB/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 2</h1></body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $section = $epub->navigationSections()[0];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same([
            'missing-primary-nav-item-label',
            'empty-nav-item-label',
            'missing-nav-item-href',
            'missing-nav-item-label',
        ], array_column($validation['diagnostics'], 'type'));
        $t->same(3, $navigation['entryCount']);
        $t->same(2, $navigation['localTargetCount']);
        $t->same(4, $navigation['documentDiagnosticCount']);
        $t->same(4, $section['rawItemCount']);
        $t->same(1, $section['emptyItemLabelCount']);
        $t->same(1, $section['missingItemHrefCount']);
        $t->same(1, $section['missingItemLabelCount']);
        $t->same(3, $section['itemDiagnosticCount']);
        $t->same(1, $documentDiagnostics['missingPrimaryItemLabelCount']);
        $t->same(1, $documentDiagnostics['emptyItemLabelCount']);
        $t->same(1, $documentDiagnostics['missingItemHrefCount']);
        $t->same(1, $documentDiagnostics['missingItemLabelCount']);
        $t->same(3, $documentDiagnostics['itemDiagnosticCount']);
        $t->same('empty-label', $documentDiagnostics['diagnostics'][1]['itemId']);
        $t->same('empty-label-link', $documentDiagnostics['diagnostics'][1]['labelId']);
        $t->same('Untargeted chapter', $documentDiagnostics['diagnostics'][2]['label']);
        $t->same('missing-label', $documentDiagnostics['diagnostics'][3]['itemId']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB NCX spine toc binding diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $ncxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="chapter" playOrder="1">
      <navLabel><text>Legacy chapter</text></navLabel>
      <content src="chapter.xhtml"/>
    </navPoint>
  </navMap>
</ncx>
XML;
        $opfWithNonNcxToc = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:ncx-binding-diagnostics</dc:identifier>
    <dc:title>NCX binding diagnostics</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="wrong-toc" href="wrong-toc.xhtml" media-type="application/xhtml+xml"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine toc="wrong-toc">
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNonNcxToc],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
            ['name' => 'EPUB/wrong-toc.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Wrong toc</body></html>'],
            ['name' => 'EPUB/toc.ncx', 'data' => $ncxXml],
        ]));
        $validation = $epub->validationReport();
        $summary = $epub->summary();
        $ncx = $validation['ncx'];

        $t->same(false, $validation['valid']);
        $t->same(1, $validation['diagnosticCount']);
        $t->same(['spine-toc-non-ncx-manifest-item'], array_column($validation['diagnostics'], 'type'));
        $t->same(false, $ncx['valid']);
        $t->same(true, $ncx['tocSpecified']);
        $t->same('wrong-toc', $ncx['tocId']);
        $t->same('wrong-toc', $ncx['tocItem']['id']);
        $t->same('/EPUB/wrong-toc.xhtml', $ncx['tocItem']['partName']);
        $t->same('application/xhtml+xml', $ncx['tocItem']['mediaType']);
        $t->same(1, $ncx['manifestNcxItemCount']);
        $t->same('toc', $ncx['manifestNcxItems'][0]['id']);
        $t->same('manifest-scan', $ncx['selectedBy']);
        $t->same('toc', $ncx['selectedItem']['id']);
        $t->same('/EPUB/toc.ncx', $ncx['selectedItem']['partName']);
        $t->same('spine-toc-non-ncx-manifest-item', $ncx['diagnostics'][0]['type']);
        $t->same('/EPUB/wrong-toc.xhtml', $ncx['diagnostics'][0]['partName']);
        $t->same('ncx', $validation['navigation']['source']);
        $t->same(1, $validation['navigation']['entryCount']);
        $t->same(1, $validation['navigation']['localTargetCount']);
        $t->same('/EPUB/toc.ncx', $epub->navigation()['partName']);
        $t->same('Legacy chapter', $epub->navigation()['entries'][0]['label']);
        $t->same('/EPUB/chapter.xhtml', $epub->navigation()['entries'][0]['target']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($ncx, $summary['wordpressImport']['packageValidation']['ncx']);

        $opfWithMissingToc = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:ncx-missing-binding-diagnostics</dc:identifier>
    <dc:title>Missing NCX binding diagnostics</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine toc="missing-toc">
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

        $missingTocEpub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMissingToc],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
            ['name' => 'EPUB/toc.ncx', 'data' => $ncxXml],
        ]));
        $missingValidation = $missingTocEpub->validationReport();

        $t->same(false, $missingValidation['valid']);
        $t->same(['missing-spine-toc-manifest-item'], array_column($missingValidation['diagnostics'], 'type'));
        $t->same('missing-toc', $missingValidation['ncx']['tocId']);
        $t->same(null, $missingValidation['ncx']['tocItem']);
        $t->same('manifest-scan', $missingValidation['ncx']['selectedBy']);
        $t->same('toc', $missingValidation['ncx']['selectedItem']['id']);
        $t->same('ncx', $missingValidation['navigation']['source']);
        $t->same('Legacy chapter', $missingTocEpub->navigation()['entries'][0]['label']);
    },

    'preserves EPUB container rootfile ZIP provenance for package handoff' => static function (TestRunner $t) use ($epub3OpfXml, $epub3NavXml): void {
        $containerWithRootfileProvenance = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml; profile=&quot;primary-opf&quot;"/>
    <rootfile full-path="EPUB/preview.xhtml" media-type="application/xhtml+xml; charset=UTF-8"/>
    <rootfile full-path="EPUB/missing-preview.xhtml" media-type="application/xhtml+xml"/>
  </rootfiles>
</container>
XML;
        $previewXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Preview</h1></body></html>';

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $containerWithRootfileProvenance],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/preview.xhtml', 'data' => $previewXhtml, 'compressionMethod' => 0],
        ]));
        $declaredRootfiles = $epub->rootfiles();
        $rootfiles = $epub->validationReport()['rootfiles'];
        $summary = $epub->summary();

        $t->same(3, $rootfiles['rootfileCount']);
        $t->same('/EPUB/package.opf', $rootfiles['items'][0]['partName']);
        $t->same($rootfiles['items'][0], $rootfiles['selectedRootfile']);
        $t->same(['/EPUB/package.opf', '/EPUB/preview.xhtml', '/EPUB/missing-preview.xhtml'], $rootfiles['rootfileParts']);
        $t->same(['/EPUB/package.opf'], $rootfiles['opfRootfileParts']);
        $t->same(['/EPUB/preview.xhtml', '/EPUB/missing-preview.xhtml'], $rootfiles['alternateRootfileParts']);
        $t->same(['/EPUB/package.opf', '/EPUB/preview.xhtml'], $rootfiles['existingRootfileParts']);
        $t->same(['/EPUB/missing-preview.xhtml'], $rootfiles['missingRootfileParts']);
        $t->same(['/EPUB/preview.xhtml', '/EPUB/missing-preview.xhtml'], $rootfiles['nonOpfRootfileParts']);
        $t->same(2, $rootfiles['existingRootfileCount']);
        $t->same([
            'application/oebps-package+xml' => 1,
            'application/xhtml+xml' => 2,
        ], $rootfiles['mediaTypeCounts']);
        $t->same([
            'application/oebps-package+xml' => ['/EPUB/package.opf'],
            'application/xhtml+xml' => ['/EPUB/preview.xhtml', '/EPUB/missing-preview.xhtml'],
        ], $rootfiles['partsByMediaType']);
        $t->same('application/oebps-package+xml; profile="primary-opf"', $declaredRootfiles[0]['mediaType']);
        $t->same('application/oebps-package+xml; profile="primary-opf"', $rootfiles['items'][0]['mediaType']);
        $t->same('application/oebps-package+xml', $rootfiles['items'][0]['mediaTypeBase']);
        $t->same(true, $rootfiles['items'][0]['mediaTypeHasParameters']);
        $t->same(1, $rootfiles['items'][0]['mediaTypeParameterCount']);
        $t->same([['name' => 'profile', 'value' => 'primary-opf', 'raw' => 'profile="primary-opf"']], $rootfiles['items'][0]['mediaTypeParameters']);
        $t->same(['profile' => 'primary-opf'], $rootfiles['items'][0]['mediaTypeParameterMap']);
        $t->same('application/oebps-package+xml; profile=primary-opf', $rootfiles['items'][0]['normalizedMediaType']);
        $t->same(true, $rootfiles['items'][0]['mediaTypeSyntaxValid']);
        $t->same([], $rootfiles['items'][0]['mediaTypeDiagnostics']);
        $t->same(true, $rootfiles['items'][0]['selected']);
        $t->same(strlen($epub3OpfXml), $rootfiles['items'][0]['byteLength']);
        $t->same(strlen(gzdeflate($epub3OpfXml)), $rootfiles['items'][0]['compressedByteLength']);
        $t->same(8, $rootfiles['items'][0]['compressionMethod']);
        $t->same('deflated', $rootfiles['items'][0]['compressionMethodName']);
        $t->same(true, $rootfiles['items'][0]['compressionSupported']);
        $t->same(hash('crc32b', $epub3OpfXml), $rootfiles['items'][0]['crc32']);
        $t->same(true, $rootfiles['items'][0]['canExposeBytes']);

        $t->same('/EPUB/preview.xhtml', $rootfiles['items'][1]['partName']);
        $t->same('application/xhtml+xml; charset=UTF-8', $rootfiles['items'][1]['mediaType']);
        $t->same('application/xhtml+xml', $rootfiles['items'][1]['mediaTypeBase']);
        $t->same(['charset' => 'UTF-8'], $rootfiles['items'][1]['mediaTypeParameterMap']);
        $t->same(strlen($previewXhtml), $rootfiles['items'][1]['byteLength']);
        $t->same(strlen($previewXhtml), $rootfiles['items'][1]['compressedByteLength']);
        $t->same(0, $rootfiles['items'][1]['compressionMethod']);
        $t->same('stored', $rootfiles['items'][1]['compressionMethodName']);
        $t->same(true, $rootfiles['items'][1]['compressionSupported']);
        $t->same(hash('crc32b', $previewXhtml), $rootfiles['items'][1]['crc32']);
        $t->same(true, $rootfiles['items'][1]['canExposeBytes']);

        $t->same('/EPUB/missing-preview.xhtml', $rootfiles['items'][2]['partName']);
        $t->same(false, $rootfiles['items'][2]['exists']);
        $t->same(null, $rootfiles['items'][2]['byteLength']);
        $t->same(null, $rootfiles['items'][2]['compressionSupported']);
        $t->same(null, $rootfiles['items'][2]['crc32']);
        $t->same(false, $rootfiles['items'][2]['canExposeBytes']);
        $t->same(2, $rootfiles['mediaTypeParameterItemCount']);
        $t->same(2, $rootfiles['mediaTypeParameterCount']);
        $t->same(['profile', 'charset'], $rootfiles['mediaTypeParameterNames']);
        $t->same([0, 1], array_column($rootfiles['mediaTypeParameterItems'], 'index'));
        $t->same(['profile'], $rootfiles['mediaTypeParameterItems'][0]['parameterNames']);
        $t->same(['charset'], $rootfiles['mediaTypeParameterItems'][1]['parameterNames']);
        $t->same(['profile' => 'primary-opf'], $rootfiles['mediaTypeParameterItems'][0]['parameterMap']);
        $t->same(['charset' => 'UTF-8'], $rootfiles['mediaTypeParameterItems'][1]['parameterMap']);
        $t->same(0, $rootfiles['mediaTypeDiagnosticCount']);
        $t->same([], $rootfiles['mediaTypeDiagnostics']);
        $t->same($declaredRootfiles, $summary['rootfiles']);
        $t->same($rootfiles, $summary['wordpressImport']['packageValidation']['rootfiles']);
    },

    'reports compact EPUB container rootfile diagnostics for validation handoff' => static function (TestRunner $t) use ($epub3OpfXml, $epub3NavXml): void {
        $containerWithAlternateRootfiles = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="EPUB/missing-alternate.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="EPUB/preview.xhtml" media-type="application/xhtml+xml"/>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;
        $previewXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Preview</h1></body></html>';

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $containerWithAlternateRootfiles],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/preview.xhtml', 'data' => $previewXhtml],
        ]));
        $validation = $epub->validationReport();
        $rootfiles = $validation['rootfiles'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(3, $validation['diagnosticCount']);
        $t->same([
            'missing-rootfile-package-part',
            'non-opf-container-rootfile',
            'duplicate-rootfile-package-part',
        ], array_column($validation['diagnostics'], 'type'));
        $t->same(false, $rootfiles['valid']);
        $t->same(0, $rootfiles['selectedIndex']);
        $t->same('/EPUB/package.opf', $rootfiles['selectedPart']);
        $t->same(4, $rootfiles['rootfileCount']);
        $t->same(3, $rootfiles['opfRootfileCount']);
        $t->same(3, $rootfiles['alternateRootfileCount']);
        $t->same(3, $rootfiles['existingRootfileCount']);
        $t->same(1, $rootfiles['missingRootfileCount']);
        $t->same(1, $rootfiles['nonOpfRootfileCount']);
        $t->same(1, $rootfiles['duplicatePartCount']);
        $t->same($rootfiles['items'][0], $rootfiles['selectedRootfile']);
        $t->same(['/EPUB/package.opf', '/EPUB/missing-alternate.opf', '/EPUB/preview.xhtml', '/EPUB/package.opf'], $rootfiles['rootfileParts']);
        $t->same(['/EPUB/package.opf', '/EPUB/missing-alternate.opf', '/EPUB/package.opf'], $rootfiles['opfRootfileParts']);
        $t->same(['/EPUB/missing-alternate.opf', '/EPUB/preview.xhtml', '/EPUB/package.opf'], $rootfiles['alternateRootfileParts']);
        $t->same(['/EPUB/package.opf', '/EPUB/preview.xhtml', '/EPUB/package.opf'], $rootfiles['existingRootfileParts']);
        $t->same(['/EPUB/missing-alternate.opf'], $rootfiles['missingRootfileParts']);
        $t->same(['/EPUB/preview.xhtml'], $rootfiles['nonOpfRootfileParts']);
        $t->same([
            'application/oebps-package+xml' => 3,
            'application/xhtml+xml' => 1,
        ], $rootfiles['mediaTypeCounts']);
        $t->same([
            'application/oebps-package+xml' => ['/EPUB/package.opf', '/EPUB/missing-alternate.opf', '/EPUB/package.opf'],
            'application/xhtml+xml' => ['/EPUB/preview.xhtml'],
        ], $rootfiles['partsByMediaType']);
        $t->same(true, $rootfiles['items'][0]['selected']);
        $t->same(false, $rootfiles['items'][3]['selected']);
        $t->same(strlen($epub3OpfXml), $rootfiles['items'][0]['byteLength']);
        $t->same(strlen(gzdeflate($epub3OpfXml)), $rootfiles['items'][0]['compressedByteLength']);
        $t->same(8, $rootfiles['items'][0]['compressionMethod']);
        $t->same('deflated', $rootfiles['items'][0]['compressionMethodName']);
        $t->same(true, $rootfiles['items'][0]['compressionSupported']);
        $t->same(hash('crc32b', $epub3OpfXml), $rootfiles['items'][0]['crc32']);
        $t->same(true, $rootfiles['items'][0]['canExposeBytes']);
        $t->same(null, $rootfiles['items'][1]['byteLength']);
        $t->same(null, $rootfiles['items'][1]['compressedByteLength']);
        $t->same(null, $rootfiles['items'][1]['compressionMethod']);
        $t->same(null, $rootfiles['items'][1]['crc32']);
        $t->same(false, $rootfiles['items'][1]['canExposeBytes']);
        $t->same(strlen($previewXhtml), $rootfiles['items'][2]['byteLength']);
        $t->same(hash('crc32b', $previewXhtml), $rootfiles['items'][2]['crc32']);
        $t->same(true, $rootfiles['items'][2]['canExposeBytes']);
        $t->same('/EPUB/missing-alternate.opf', $rootfiles['missingRootfiles'][0]['partName']);
        $t->same(false, $rootfiles['missingRootfiles'][0]['canExposeBytes']);
        $t->same('/EPUB/preview.xhtml', $rootfiles['nonOpfRootfiles'][0]['partName']);
        $t->same(hash('crc32b', $previewXhtml), $rootfiles['nonOpfRootfiles'][0]['crc32']);
        $t->same('/EPUB/package.opf', $rootfiles['duplicatePartItems'][0]['partName']);
        $t->same([0, 3], $rootfiles['duplicatePartItems'][0]['indexes']);
        $t->same($rootfiles, $summary['wordpressImport']['packageValidation']['rootfiles']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
    },

    'rejects EPUB OCF packages with invalid mimetype or container rootfile' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $t->throws(\RuntimeException::class, static fn (): EpubPackage => EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/styles/book.css', 'data' => ''],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ])));

        $t->throws(\RuntimeException::class, static fn (): EpubPackage => EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip'],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/styles/book.css', 'data' => ''],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ])));

        $t->throws(\InvalidArgumentException::class, static fn (): EpubPackage => EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="../evil.opf" media-type="application/oebps-package+xml"/></rootfiles></container>'],
        ])));
    },

    'reports OPF spine itemrefs that miss manifest ids' => static function (TestRunner $t) use ($epubContainerXml): void {
        $badSpineOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:bad-spine-review</dc:identifier>
    <dc:title>Broken spine</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-11T00:00:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine><itemref idref="missing"/></spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol><li><a href="nav.xhtml">Package review</a></li></ol>
    </nav>
  </body>
</html>
XML;

        $badSpine = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $badSpineOpf],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html/>'],
        ]));
        $badSpineValidation = $badSpine->validationReport();
        $badSpineSummary = $badSpine->summary();

        $t->same(false, $badSpineValidation['valid']);
        $t->same(['missing-spine-manifest-item'], array_column($badSpineValidation['diagnostics'], 'type'));
        $t->same(false, $badSpineValidation['spine']['valid']);
        $t->same(1, $badSpineValidation['spine']['missingManifestItemCount']);
        $t->same('missing', $badSpineValidation['spine']['missingManifestItems'][0]['idref']);
        $t->same('missing-spine-manifest-item', $badSpineValidation['spine']['diagnostics'][0]['type']);
        $t->same('missing', $badSpine->spine()[0]['idref']);
        $t->same(true, $badSpine->spine()[0]['manifestItemMissing']);
        $t->same(false, $badSpine->spine()[0]['exists']);
        $t->same($badSpineValidation, $badSpineSummary['wordpressImport']['packageValidation']);
    },

    'reports duplicate OPF manifest ids without aborting package ingestion' => static function (TestRunner $t) use ($epubContainerXml): void {
        $duplicateIdOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:duplicate-manifest-id-review</dc:identifier>
    <dc:title>Duplicate manifest id review</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-11T00:00:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter" href="chapter-review.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol><li><a href="chapter.xhtml">Package review</a></li></ol>
    </nav>
  </body>
</html>
XML;

        $duplicateId = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $duplicateIdOpf],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/chapter-review.xhtml', 'data' => '<html/>'],
        ]));
        $validation = $duplicateId->validationReport();
        $summary = $duplicateId->summary();
        $manifest = $duplicateId->manifestItems();
        $duplicate = $validation['manifest']['duplicateIdItems'][0];

        $t->same('/EPUB/chapter.xhtml', $duplicateId->manifestItem('chapter')['partName']);
        $t->same('/EPUB/chapter.xhtml', $duplicateId->spine()[0]['partName']);
        $t->same(false, $validation['valid']);
        $t->same(['duplicate-manifest-item-id'], array_column($validation['diagnostics'], 'type'));
        $t->same(1, $validation['manifest']['duplicateIdCount']);
        $t->same(1, $validation['manifest']['duplicateManifestIdCount']);
        $t->same('chapter', $duplicate['id']);
        $t->same([1, 2], $duplicate['indexes']);
        $t->same(['chapter.xhtml', 'chapter-review.xhtml'], $duplicate['hrefs']);
        $t->same(['/EPUB/chapter.xhtml', '/EPUB/chapter-review.xhtml'], $duplicate['partNames']);
        $t->same(1, $duplicate['selectedIndex']);
        $t->same('/EPUB/chapter.xhtml', $duplicate['selectedPartName']);
        $t->same('duplicate-manifest-item-id', $validation['manifest']['diagnostics'][0]['type']);
        $t->same($validation['manifest']['duplicateIdItems'], $validation['manifest']['duplicateManifestIdItems']);
        $t->same(true, $manifest[1]['duplicateManifestId']);
        $t->same(true, $manifest[1]['duplicateManifestIdSelected']);
        $t->same([1, 2], $manifest[1]['duplicateManifestIdIndexes']);
        $t->same(0, $manifest[1]['duplicateManifestIdOrdinal']);
        $t->same(true, $manifest[2]['duplicateManifestId']);
        $t->same(false, $manifest[2]['duplicateManifestIdSelected']);
        $t->same(1, $manifest[2]['duplicateManifestIdOrdinal']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
    },

    'reports OPF manifest href targets missing from the package' => static function (TestRunner $t) use ($epubContainerXml): void {
        $missingPartOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:missing-part-review</dc:identifier>
    <dc:title>Missing package part</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-11T00:00:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol><li><a href="nav.xhtml">Package review</a></li></ol>
    </nav>
  </body>
</html>
XML;

        $missingPart = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $missingPartOpf],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
        ]));
        $missingPartValidation = $missingPart->validationReport();
        $missingPartSummary = $missingPart->summary();
        $chapter = $missingPart->manifestItem('chapter');

        $t->same(false, $missingPartValidation['valid']);
        $t->same(['missing-manifest-href-target', 'missing-spine-item-package-part'], array_column($missingPartValidation['diagnostics'], 'type'));
        $t->same(false, $missingPartValidation['manifest']['valid']);
        $t->same(1, $missingPartValidation['manifest']['missingItemCount']);
        $t->same('chapter', $missingPartValidation['manifest']['missingItems'][0]['id']);
        $t->same('/EPUB/chapter.xhtml', $missingPartValidation['manifest']['missingItems'][0]['partName']);
        $t->same('missing-manifest-href-target', $missingPartValidation['manifest']['diagnostics'][0]['type']);
        $t->same(false, $chapter['exists']);
        $t->same(null, $chapter['byteLength']);
        $t->same(false, $chapter['canExposeBytes']);
        $t->same(1, $missingPartValidation['spine']['missingPackagePartCount']);
        $t->same('chapter', $missingPartValidation['spine']['missingPackagePartItems'][0]['idref']);
        $t->same('/EPUB/chapter.xhtml', $missingPartValidation['spine']['missingPackagePartItems'][0]['partName']);
        $t->same('missing-spine-item-package-part', $missingPartValidation['spine']['diagnostics'][0]['type']);
        $t->same(false, $missingPart->spine()[0]['exists']);
        $t->same(false, $missingPart->spine()[0]['manifestItemMissing']);
        $t->same($missingPartValidation, $missingPartSummary['wordpressImport']['packageValidation']);
    },

    'reports OPF manifest media-type parameter provenance for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithMediaTypeParameters = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/book.css" media-type="text/css; charset=UTF-8; profile=print"/>',
            $epub3OpfXml
        );
        $opfWithMediaTypeParameters = str_replace(
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>',
            '<item id="cover" href="images/cover.png" media-type="image/png; review-flag" properties="cover-image"/>',
            $opfWithMediaTypeParameters
        );
        $opfWithMediaTypeParameters = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml; charset=UTF-8; charset=windows-1252"/>',
            $opfWithMediaTypeParameters
        );
        $opfWithMediaTypeParameters = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="review-packet" href="meta/review.packet" media-type="review-packet"/>',
            $opfWithMediaTypeParameters
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMediaTypeParameters],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/review.packet', 'data' => 'REVIEW'],
        ]));
        $validation = $epub->validationReport();
        $manifest = $validation['manifest'];
        $summary = $epub->summary();
        $itemsById = [];
        foreach ($manifest['mediaTypeItems'] as $item) {
            $itemsById[$item['id']] = $item;
        }

        $t->same(false, $validation['valid']);
        $t->same([
            'invalid-manifest-media-type-parameter',
            'duplicate-manifest-media-type-parameter',
            'invalid-manifest-media-type',
        ], array_column($validation['diagnostics'], 'type'));
        $t->same(6, $manifest['itemCount']);
        $t->same(4, $manifest['mediaTypeParameterCount']);
        $t->same(2, $manifest['mediaTypeParameterizedItemCount']);
        $t->same(3, $manifest['invalidMediaTypeCount']);
        $t->same(1, $manifest['duplicateMediaTypeParameterCount']);

        $chapter = $itemsById['chapter1'];
        $t->same('application/xhtml+xml; charset=UTF-8; charset=windows-1252', $chapter['mediaType']);
        $t->same('application/xhtml+xml', $chapter['baseMediaType']);
        $t->same('application/xhtml+xml; charset=windows-1252', $chapter['normalizedMediaType']);
        $t->same(['charset' => 'windows-1252'], $chapter['mediaTypeParameters']);
        $t->same(2, $chapter['parameterCount']);
        $t->same(true, $chapter['parameterItems'][1]['duplicate']);
        $t->same('UTF-8', $chapter['parameterItems'][1]['previousValue']);
        $t->same('windows-1252', $chapter['duplicateParameters'][0]['value']);
        $t->same('duplicate-manifest-media-type-parameter', $chapter['diagnostics'][0]['type']);
        $t->same('chapter1', $chapter['diagnostics'][0]['id']);

        $style = $itemsById['style'];
        $t->same(true, $style['valid']);
        $t->same('text/css', $style['baseMediaType']);
        $t->same(['charset' => 'UTF-8', 'profile' => 'print'], $style['mediaTypeParameters']);
        $t->same('text/css; charset=utf-8; profile=print', $style['normalizedMediaType']);
        $t->same([], $style['diagnostics']);

        $cover = $itemsById['cover'];
        $t->same(false, $cover['valid']);
        $t->same('image/png', $cover['baseMediaType']);
        $t->same([], $cover['mediaTypeParameters']);
        $t->same('invalid-manifest-media-type-parameter', $cover['diagnostics'][0]['type']);
        $t->same('image/png; review-flag', $cover['diagnostics'][0]['mediaType']);

        $reviewPacket = $itemsById['review-packet'];
        $t->same(false, $reviewPacket['valid']);
        $t->same('review-packet', $reviewPacket['baseMediaType']);
        $t->same('invalid-manifest-media-type', $reviewPacket['diagnostics'][0]['type']);
        $t->same('/EPUB/meta/review.packet', $reviewPacket['diagnostics'][0]['partName']);

        $t->same(['style', 'chapter1'], array_column($manifest['mediaTypeParameterItems'], 'id'));
        $t->same(['cover', 'chapter1', 'review-packet'], array_column($manifest['invalidMediaTypeItems'], 'id'));
        $t->same(['chapter1'], array_column($manifest['duplicateMediaTypeParameterItems'], 'id'));
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($manifest['mediaTypeItems'], $summary['wordpressImport']['packageValidation']['manifest']['mediaTypeItems']);
    },
];
