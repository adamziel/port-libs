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
    $centralRecords = [];

    foreach ($entries as $entryIndex => $entry) {
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

        $centralRecord = pack(
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
        $centralRecord .= $name;
        $centralRecords[] = [
            'index' => $entry['centralIndex'] ?? $entryIndex,
            'record' => $centralRecord,
        ];
    }

    usort(
        $centralRecords,
        static fn (array $left, array $right): int => [$left['index']] <=> [$right['index']]
    );
    $central = implode('', array_column($centralRecords, 'record'));
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

    'exposes EPUB mimetype stored-first provenance for compact import' => static function (TestRunner $t) use ($epub3Package): void {
        $epub = EpubPackage::fromPackage($epub3Package());
        $mimetype = $epub->mimetypeEntry();
        $summary = $epub->summary();

        $t->same('mimetype', $mimetype['entryName']);
        $t->same(true, $mimetype['exists']);
        $t->same('mimetype', $mimetype['firstLocalEntryName']);
        $t->same(true, $mimetype['isFirstLocalEntry']);
        $t->same(0, $mimetype['compressionMethod']);
        $t->same('stored', $mimetype['compressionMethodName']);
        $t->same(false, $mimetype['usesDataDescriptor']);
        $t->same(true, $mimetype['isStored']);
        $t->same([], $mimetype['centralExtraFieldIds']);
        $t->same([], $mimetype['localExtraFieldIds']);
        $t->same(strlen(EpubPackage::EPUB_MIMETYPE), $mimetype['expectedBytes']);
        $t->same(strlen(EpubPackage::EPUB_MIMETYPE), $mimetype['contentBytes']);
        $t->same(true, $mimetype['contentsMatch']);
        $t->same(true, $mimetype['isValid']);
        $t->same([], $mimetype['diagnostics']);
        $t->same($mimetype, $summary['mimetypeEntry']);
        $t->same($mimetype, $summary['wordpressImport']['mimetypeEntry']);
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

    'preserves compact NCX label audio provenance for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub2OpfXml): void {
        $audioBytes = 'NCX-AUDIO-BYTES';
        $opfWithAudio = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="nav-audio" href="audio/nav-label.mp3" media-type="audio/mpeg"/>',
            $epub2OpfXml
        );
        $ncxWithAudio = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="intro-point" playOrder="1">
      <navLabel>
        <text>Audio introduction</text>
        <audio id="intro-clip" src="audio/nav-label.mp3?clip=1#t=1,3" clipBegin="0:00:01.000" clipEnd="0:00:03.500"/>
      </navLabel>
      <content src="chapter.xhtml"/>
      <navPoint id="missing-point" playOrder="2">
        <navLabel>
          <text>Missing audio label</text>
          <audio src="audio/missing-label.mp3" clipBegin="bad-clock" clipEnd="0:00:04.000"/>
        </navLabel>
        <content src="chapter.xhtml#missing"/>
      </navPoint>
    </navPoint>
    <navPoint id="remote-point" playOrder="3">
      <navLabel>
        <text>Remote audio label</text>
        <audio src="https://audio.example.invalid/nav-label.mp3"/>
      </navLabel>
      <content src="chapter.xhtml#remote"/>
    </navPoint>
  </navMap>
</ncx>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithAudio],
            ['name' => 'EPUB/toc.ncx', 'data' => $ncxWithAudio],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Legacy</body></html>'],
            ['name' => 'EPUB/cover.jpg', 'data' => 'JPG'],
            ['name' => 'EPUB/audio/nav-label.mp3', 'data' => $audioBytes],
        ]));
        $navigation = $epub->navigation();
        $summary = $epub->summary();
        $report = $summary['ncxAudioLabelReport'];
        $localAudio = $navigation['entries'][0]['labelAudio'][0];
        $missingAudio = $navigation['entries'][1]['labelAudio'][0];
        $remoteAudio = $navigation['entries'][2]['labelAudio'][0];

        $t->same('ncx', $navigation['type']);
        $t->same('/EPUB/toc.ncx', $navigation['partName']);
        $t->same(3, count($navigation['entries']));
        $t->same(1, $navigation['entries'][0]['labelAudioCount']);
        $t->same('intro-clip', $localAudio['id']);
        $t->same('/EPUB/audio/nav-label.mp3?clip=1#t=1,3', $localAudio['target']);
        $t->same('/EPUB/audio/nav-label.mp3', $localAudio['partName']);
        $t->same('nav-audio', $localAudio['manifestId']);
        $t->same('audio/mpeg', $localAudio['manifestMediaType']);
        $t->same(true, $localAudio['exists']);
        $t->same(strlen($audioBytes), $localAudio['byteLength']);
        $t->same(hash('crc32b', $audioBytes), $localAudio['crc32']);
        $t->same(hash('sha256', $audioBytes), $localAudio['byteSha256']);
        $t->same(true, $localAudio['hrefHasQuery']);
        $t->same('clip=1', $localAudio['hrefQuery']);
        $t->same(true, $localAudio['hrefHasFragment']);
        $t->same('t=1,3', $localAudio['hrefFragment']);
        $t->same(1.0, $localAudio['clipBeginSeconds']);
        $t->same(3.5, $localAudio['clipEndSeconds']);
        $t->same(2.5, $localAudio['clipDurationSeconds']);
        $t->same([], $localAudio['diagnostics']);

        $t->same(false, $missingAudio['exists']);
        $t->same('/EPUB/audio/missing-label.mp3', $missingAudio['partName']);
        $t->same('missing-ncx-audio-reference', $missingAudio['diagnostics'][0]['type']);
        $t->same('invalid-ncx-audio-clip-begin', $missingAudio['clipDiagnostics'][0]['type']);
        $t->same(true, $remoteAudio['external']);
        $t->same('external-ncx-audio-reference', $remoteAudio['diagnostics'][0]['type']);

        $t->same(true, $report['present']);
        $t->same(3, $report['count']);
        $t->same(1, $report['localCount']);
        $t->same(1, $report['externalCount']);
        $t->same(1, $report['missingCount']);
        $t->same(3, $report['diagnosticCount']);
        $t->same(['/EPUB/audio/nav-label.mp3?clip=1#t=1,3'], $report['localTargets']);
        $t->same(['https://audio.example.invalid/nav-label.mp3'], $report['externalTargets']);
        $t->same(['/EPUB/audio/missing-label.mp3'], $report['missingTargets']);
        $t->same($report['items'], $summary['wordpressImport']['ncxAudioLabels']);
        $t->same($report, $summary['wordpressImport']['ncxAudioLabelReport']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['ncxAudioLabelDiagnostics']);
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

    'preserves OCF metadata sidecar ZIP provenance in package inventory handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml, $buildZipPackage): void {
        $containerMetadataXml = <<<'XML'
<metadata xmlns="http://www.idpf.org/2013/metadata" prefix="schema: http://schema.org/">
  <link id="container-record" rel="record" href="EPUB/meta/container.json" media-type="application/ld+json" properties="schema:about"/>
</metadata>
XML;
        $opfWithContainerRecord = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="container-record" href="meta/container.json" media-type="application/ld+json"/>',
            $epub3OpfXml
        );

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
            ['name' => 'EPUB/meta/container.json', 'data' => '{"source":"metadata-sidecar"}', 'method' => 0],
        ]));

        $summary = $epub->summary();
        $sidecars = $epub->ocfSidecars();
        $metadata = $sidecars['itemsByKind']['metadata'];
        $inventory = $summary['packageInventory'];
        $metadataEntry = $inventory['byPackagePath']['META-INF/metadata.xml'];
        $link = $epub->containerLinks()[0];

        $t->same(true, $sidecars['present']);
        $t->same(1, $sidecars['sidecarCount']);
        $t->same(true, $sidecars['metadataPresent']);
        $t->same(['metadata'], $sidecars['kinds']);
        $t->same('metadata', $metadata['kind']);
        $t->same('/META-INF/metadata.xml', $metadata['partName']);
        $t->same('META-INF/metadata.xml', $metadata['packagePath']);
        $t->same('metadata', $metadata['expectedRootName']);
        $t->same(EpubPackage::EPUB_METADATA_NAMESPACE, $metadata['expectedRootNamespace']);
        $t->same('ocf-metadata-sidecar-review', $metadata['reviewPolicy']);
        $t->same('ocf-sidecar-metadata-only', $metadata['byteExposurePolicy']);
        $t->same(false, $metadata['canExposeBytes']);
        $t->same(true, $metadata['xmlRootChecked']);
        $t->same(true, $metadata['xmlWellFormed']);
        $t->same('metadata', $metadata['rootName']);
        $t->same(EpubPackage::EPUB_METADATA_NAMESPACE, $metadata['rootNamespace']);
        $t->same(true, $metadata['rootValid']);
        $t->same(strlen($containerMetadataXml), $metadata['byteLength']);
        $t->same(strlen(gzdeflate($containerMetadataXml)), $metadata['compressedByteLength']);
        $t->same(8, $metadata['compressionMethod']);
        $t->same('deflated', $metadata['compressionMethodName']);
        $t->same(true, $metadata['compressionSupported']);
        $t->same(hash('crc32b', $containerMetadataXml), $metadata['crc32']);
        $t->same(0, $metadata['diagnosticCount']);
        $t->same($sidecars, $summary['wordpressImport']['ocfSidecars']);
        $t->same($sidecars['items'], $summary['wordpressImport']['ocfSidecarItems']);
        $t->same(true, $metadataEntry['declaredPackageEntry']);
        $t->same(false, $metadataEntry['undeclared']);
        $t->same(['ocf-meta-inf', 'ocf-sidecar', 'ocf-metadata-sidecar'], $metadataEntry['roles']);
        $t->same(1, $inventory['roleCounts']['ocf-metadata-sidecar']);
        $t->same(1, $inventory['roleCounts']['ocf-sidecar']);
        $t->true(!in_array('/META-INF/metadata.xml', $inventory['undeclaredPartNames'], true));
        $t->same(strlen($containerMetadataXml), $metadataEntry['byteLength']);
        $t->same('/EPUB/meta/container.json', $link['partName']);
        $t->same('container-record', $link['manifestId']);
        $t->same($link, $summary['wordpressImport']['containerLinks'][0]);
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

    'preserves OPF package xml base provenance for package handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithPackageBase = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" id="base-package" version="3.0" unique-identifier="bookid" xml:base="https://publisher.example.invalid/books/migration/" xml:lang="en">',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithPackageBase],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $package = $metadata['package'];

        $t->same('base-package', $metadata['packageId']);
        $t->same('https://publisher.example.invalid/books/migration/', $metadata['packageBase']);
        $t->same('base-package', $package['id']);
        $t->same('3.0', $package['version']);
        $t->same('bookid', $package['uniqueIdentifierId']);
        $t->same('https://publisher.example.invalid/books/migration/', $package['base']);
        $t->same($package, $summary['metadata']['package']);
        $t->same($package, $summary['wordpressImport']['metadataDetails']['package']);
        $t->same('https://publisher.example.invalid/books/migration/', $summary['wordpressImport']['metadataDetails']['packageBase']);
        $t->same('/EPUB/package.opf', $summary['opfPart']);
        $t->same(['/EPUB/text/chapter1.xhtml', '/EPUB/text/chapter2.xhtml'], $summary['wordpressImport']['readingOrderParts']);
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
        $compactBinding = $summary['compactPackageReport']['casesById']['media-type-bindings'];

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
        $t->same(hash('sha256', $handlerXhtml), $bindings['items'][0]['handlerByteSha256']);
        $t->same([], $bindings['items'][0]['diagnostics']);
        $t->same('application/x-review-widget', $bindings['items'][1]['mediaType']);
        $t->same('missing-handler', $bindings['items'][1]['handlerId']);
        $t->same(false, $bindings['items'][1]['handlerExists']);
        $t->same(null, $bindings['items'][1]['handlerPartName']);
        $t->same(null, $bindings['items'][1]['handlerByteSha256']);
        $t->same('missing-binding-handler-manifest-item', $bindings['items'][1]['diagnostics'][0]['type']);
        $t->same(null, $bindings['items'][2]['mediaType']);
        $t->same('missing-binding-media-type', $bindings['items'][2]['diagnostics'][0]['type']);
        $t->same(2, count($bindings['diagnostics']));
        $t->same(1, $bindings['diagnostics'][0]['index']);
        $t->same(2, $bindings['diagnostics'][1]['index']);
        $t->same($bindings, $summary['bindings']);
        $t->same($bindings['items'], $summary['wordpressImport']['mediaTypeBindings']);
        $t->same($bindings['diagnostics'], $summary['wordpressImport']['mediaTypeBindingDiagnostics']);
        $t->true(in_array('media-type-bindings', $summary['compactPackageReport']['caseIds'], true));
        $t->true(in_array('media-type-bindings', $summary['compactPackageReport']['presentCaseIds'], true));
        $t->true(in_array('media-type-bindings', $summary['compactPackageReport']['diagnosticCaseIds'], true));
        $t->true(in_array('media-type-bindings', $summary['compactPackageReport']['reviewRequiredCaseIds'], true));
        $t->same(3, $compactBinding['itemCount']);
        $t->same(2, $compactBinding['diagnosticCount']);
        $t->same(['missing-binding-handler-manifest-item', 'missing-binding-media-type'], $compactBinding['diagnosticTypes']);
        $t->same(['application/x-demo-slideshow', 'application/x-review-widget'], $compactBinding['boundMediaTypes']);
        $t->same(['slideshow-handler', 'missing-handler'], $compactBinding['handlerIds']);
        $t->same(['/EPUB/widgets/slideshow-fallback.xhtml'], $compactBinding['handlerPartNames']);
        $t->same(true, $compactBinding['present']);
        $t->same(2, $compactBinding['localHandlerCount']);
        $t->same(2, $compactBinding['resolvedHandlerCount']);
        $t->same(0, $compactBinding['externalHandlerCount']);
        $t->same(1, $compactBinding['missingHandlerCount']);
        $t->same(0, $compactBinding['encryptedHandlerCount']);
        $t->same(2, $compactBinding['exposableHandlerCount']);
        $t->same(2, $compactBinding['byteExposableHandlerCount']);
        $t->same(1, $compactBinding['blockedHandlerCount']);
        $t->same('manifest', $compactBinding['diagnostics'][0]['domain']);
        $t->same('media-type-bindings', $compactBinding['diagnostics'][0]['caseId']);
        $t->same($bindings['diagnostics'][0]['type'], $compactBinding['diagnostics'][0]['type']);
        $t->same($summary['compactPackageReport'], $summary['wordpressImport']['compactPackageReport']);
    },

    'reports OPF binding media-type parameter provenance for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $handlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Widget handler</h1></body></html>';
        $reviewHandlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review handler</h1></body></html>';
        $opfWithBindingMediaTypes = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="widget-handler" href="widgets/widget-handler.xhtml" media-type="application/xhtml+xml" properties="scripted"/>
    <item id="review-handler" href="widgets/review-handler.xhtml" media-type="application/xhtml+xml" properties="scripted"/>',
            $epub3OpfXml
        );
        $opfWithBindingMediaTypes = str_replace(
            '</spine>',
            '</spine>
  <bindings>
    <mediaType media-type="application/x-review-widget; charset=UTF-8; profile=&quot;author review&quot;" handler="widget-handler"/>
    <mediaType media-type="review-widget" handler="widget-handler"/>
    <mediaType media-type="application/x-repeat; charset=UTF-8; charset=windows-1252" handler="review-handler"/>
    <mediaType media-type="application/x-flag; broken" handler="missing-handler"/>
  </bindings>',
            $opfWithBindingMediaTypes
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithBindingMediaTypes],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/widgets/widget-handler.xhtml', 'data' => $handlerXhtml],
            ['name' => 'EPUB/widgets/review-handler.xhtml', 'data' => $reviewHandlerXhtml],
        ]));

        $bindings = $epub->bindings();
        $summary = $epub->summary();
        $first = $bindings['items'][0];
        $invalid = $bindings['items'][1];
        $duplicate = $bindings['items'][2];
        $flagged = $bindings['items'][3];

        $t->same(true, $bindings['present']);
        $t->same(4, $bindings['itemCount']);
        $t->same(4, $bindings['mediaTypeItemCount']);
        $t->same(4, $bindings['mediaTypeParameterCount']);
        $t->same(2, $bindings['mediaTypeParameterizedItemCount']);
        $t->same(['charset', 'profile'], $bindings['mediaTypeParameterNames']);
        $t->same(3, $bindings['mediaTypeDiagnosticCount']);
        $t->same(3, $bindings['invalidMediaTypeCount']);
        $t->same(1, $bindings['duplicateMediaTypeParameterCount']);

        $t->same('application/x-review-widget', $first['baseMediaType']);
        $t->same('application/x-review-widget; charset=utf-8; profile=author review', $first['normalizedMediaType']);
        $t->same(['charset' => 'UTF-8', 'profile' => 'author review'], $first['mediaTypeParameters']);
        $t->same('profile="author review"', $first['mediaTypeParameterItems'][1]['raw']);
        $t->same([], $first['mediaTypeDiagnostics']);
        $t->same($first['mediaTypeReport'], $bindings['mediaTypeItems'][0]);

        $t->same(false, $invalid['mediaTypeValid']);
        $t->same('invalid-binding-media-type', $invalid['mediaTypeDiagnostics'][0]['type']);
        $t->same('review-widget', $invalid['mediaTypeDiagnostics'][0]['mediaType']);
        $t->same(['invalid-binding-media-type'], array_column($invalid['diagnostics'], 'type'));

        $t->same('application/x-repeat; charset=windows-1252', $duplicate['normalizedMediaType']);
        $t->same(['charset' => 'windows-1252'], $duplicate['mediaTypeParameters']);
        $t->same(true, $duplicate['mediaTypeParameterItems'][1]['duplicate']);
        $t->same('UTF-8', $duplicate['mediaTypeParameterItems'][1]['previousValue']);
        $t->same('duplicate-binding-media-type-parameter', $duplicate['mediaTypeDiagnostics'][0]['type']);
        $t->same('windows-1252', $bindings['duplicateMediaTypeParameterItems'][0]['duplicateParameters'][0]['value']);

        $t->same(['invalid-binding-media-type-parameter', 'missing-binding-handler-manifest-item'], array_column($flagged['diagnostics'], 'type'));
        $t->same('broken', $flagged['mediaTypeDiagnostics'][0]['parameter']);
        $t->same(null, $flagged['handlerPartName']);
        $t->same([
            'invalid-binding-media-type',
            'duplicate-binding-media-type-parameter',
            'invalid-binding-media-type-parameter',
            'missing-binding-handler-manifest-item',
        ], array_column($bindings['diagnostics'], 'type'));
        $t->same([
            'invalid-binding-media-type',
            'duplicate-binding-media-type-parameter',
            'invalid-binding-media-type-parameter',
        ], array_column($bindings['mediaTypeDiagnostics'], 'type'));
        $t->same(['application/x-review-widget; charset=UTF-8; profile="author review"', 'application/x-repeat; charset=UTF-8; charset=windows-1252'], array_column($bindings['mediaTypeParameterItems'], 'mediaType'));

        $t->same($bindings['mediaTypeItems'], $summary['wordpressImport']['mediaTypeBindingMediaTypeItems']);
        $t->same($bindings['mediaTypeParameterItems'], $summary['wordpressImport']['mediaTypeBindingMediaTypeParameterItems']);
        $t->same($bindings['mediaTypeParameterNames'], $summary['wordpressImport']['mediaTypeBindingMediaTypeParameterNames']);
        $t->same($bindings['mediaTypeDiagnostics'], $summary['wordpressImport']['mediaTypeBindingMediaTypeDiagnostics']);
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
        $compactBinding = $summary['compactPackageReport']['casesById']['media-type-bindings'];
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
        $t->same(hash('sha256', $localHandlerXhtml), $local['handlerByteSha256']);
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
        $t->same(null, $remote['handlerByteSha256']);
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
        $t->same(2, $compactBinding['itemCount']);
        $t->same(['application/x-local-widget', 'application/x-remote-widget'], $compactBinding['boundMediaTypes']);
        $t->same(['/EPUB/widgets/local-handler.xhtml'], $compactBinding['handlerPartNames']);
        $t->same(1, $compactBinding['localHandlerCount']);
        $t->same(1, $compactBinding['externalHandlerCount']);
        $t->same(0, $compactBinding['missingHandlerCount']);
        $t->same(1, $compactBinding['exposableHandlerCount']);
        $t->same(1, $compactBinding['blockedHandlerCount']);
        $t->same(['external-binding-handler'], $compactBinding['diagnosticTypes']);
        $t->true(in_array('media-type-bindings', $summary['compactPackageReport']['diagnosticCaseIds'], true));
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
        $compactBinding = $summary['compactPackageReport']['casesById']['media-type-bindings'];
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
        $t->same(null, $handler['handlerByteSha256']);
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
        $t->same(1, $compactBinding['itemCount']);
        $t->same(['application/x-locked-widget'], $compactBinding['boundMediaTypes']);
        $t->same(['locked-handler'], $compactBinding['handlerIds']);
        $t->same(['/EPUB/widgets/locked-handler.xhtml'], $compactBinding['handlerPartNames']);
        $t->same(1, $compactBinding['localHandlerCount']);
        $t->same(1, $compactBinding['encryptedHandlerCount']);
        $t->same(0, $compactBinding['exposableHandlerCount']);
        $t->same(1, $compactBinding['blockedHandlerCount']);
        $t->same(['encrypted-binding-handler'], $compactBinding['diagnosticTypes']);
        $t->true(in_array('media-type-bindings', $summary['compactPackageReport']['reviewRequiredCaseIds'], true));
    },

    'summarizes compact EPUB media type binding inventory for review handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $localHandler = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Local widget handler</h1></body></html>';
        $lockedHandler = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Locked widget handler</h1></body></html>';
        $opfWithBindingInventory = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:compact-binding-inventory</dc:identifier>
    <dc:title>Compact binding inventory</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="local-handler" href="widgets/local-handler.xhtml" media-type="application/xhtml+xml" properties="scripted"/>
    <item id="remote-handler" href="https://cdn.example.invalid/widgets/remote-handler.xhtml" media-type="application/xhtml+xml" properties="scripted remote-resources"/>
    <item id="locked-handler" href="widgets/locked-handler.xhtml" media-type="application/xhtml+xml" properties="scripted"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
  <bindings>
    <mediaType media-type="application/x-local-widget" handler="local-handler"/>
    <mediaType media-type="application/x-remote-widget" handler="remote-handler"/>
    <mediaType media-type="application/x-locked-widget" handler="locked-handler"/>
    <mediaType media-type="application/x-missing-widget" handler="missing-handler"/>
  </bindings>
</package>
XML;
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
            ['name' => 'EPUB/package.opf', 'data' => $opfWithBindingInventory],
            ['name' => 'EPUB/nav.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">Chapter</a></li></ol></nav></body></html>'],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
            ['name' => 'EPUB/widgets/local-handler.xhtml', 'data' => $localHandler],
            ['name' => 'EPUB/widgets/locked-handler.xhtml', 'data' => $lockedHandler],
        ]));

        $summary = $epub->summary();
        $report = $summary['compactPackageReport'];
        $bindingCase = $report['casesById']['media-type-bindings'];

        $t->same(true, $bindingCase['present']);
        $t->same(4, $bindingCase['itemCount']);
        $t->same([
            'application/x-local-widget',
            'application/x-remote-widget',
            'application/x-locked-widget',
            'application/x-missing-widget',
        ], $bindingCase['boundMediaTypes']);
        $t->same(['local-handler', 'remote-handler', 'locked-handler', 'missing-handler'], $bindingCase['handlerIds']);
        $t->same(['/EPUB/widgets/local-handler.xhtml', '/EPUB/widgets/locked-handler.xhtml'], $bindingCase['handlerPartNames']);
        $t->same(2, $bindingCase['localHandlerCount']);
        $t->same(1, $bindingCase['externalHandlerCount']);
        $t->same(1, $bindingCase['missingHandlerCount']);
        $t->same(1, $bindingCase['encryptedHandlerCount']);
        $t->same(1, $bindingCase['exposableHandlerCount']);
        $t->same(3, $bindingCase['blockedHandlerCount']);
        $t->same(strlen($localHandler) + strlen($lockedHandler), $bindingCase['totalByteLength']);
        $t->same(3, $bindingCase['diagnosticCount']);
        $t->same([
            'external-binding-handler',
            'encrypted-binding-handler',
            'missing-binding-handler-manifest-item',
        ], $bindingCase['diagnosticTypes']);
        $t->same(['media-type-bindings'], array_values(array_intersect(['media-type-bindings'], $report['presentCaseIds'])));
        $t->same(['media-type-bindings'], array_values(array_intersect(['media-type-bindings'], $report['diagnosticCaseIds'])));
        $t->same(['media-type-bindings'], array_values(array_intersect(['media-type-bindings'], $report['reviewRequiredCaseIds'])));
        $t->same($report, $summary['wordpressImport']['compactPackageReport']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['compactPackageReportDiagnostics']);
        $t->same(4, $report['caseCounts']['media-type-bindings']);
    },

    'preserves OPF binding authoring attributes for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $handlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Authoring widget fallback</h1></body></html>';
        $opfWithBindingAuthoring = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" xmlns:review="https://example.invalid/epub-review" version="3.0" unique-identifier="bookid" xml:lang="en">',
            $epub3OpfXml
        );
        $opfWithBindingAuthoring = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="widget-handler" href="widgets/widget-handler.xhtml" media-type="application/xhtml+xml" properties="scripted"/>',
            $opfWithBindingAuthoring
        );
        $opfWithBindingAuthoring = str_replace(
            '</spine>',
            '</spine>
  <bindings>
    <mediaType id="primary-binding" media-type="application/x-authoring-widget" handler="widget-handler" xml:lang="en-GB" dir="rtl" data-source="package-review" review:packet="author"/>
    <mediaType media-type="application/x-secondary-widget" handler="widget-handler" data-source="secondary-review"/>
  </bindings>',
            $opfWithBindingAuthoring
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithBindingAuthoring],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/widgets/widget-handler.xhtml', 'data' => $handlerXhtml],
        ]));

        $bindings = $epub->bindings();
        $summary = $epub->summary();
        $authoring = $summary['bindingAuthoring'];
        $primary = $bindings['items'][0];
        $primaryAuthoring = $authoring['items'][0];
        $secondaryAuthoring = $authoring['items'][1];

        $t->same('primary-binding', $primary['id']);
        $t->same('en-GB', $primary['language']);
        $t->same('rtl', $primary['direction']);
        $t->same('package-review', $primary['customAttributes']['data-source']);
        $t->same('author', $primary['customAttributes']['review:packet']);
        $t->same([
            'data-source' => 'package-review',
            'review:packet' => 'author',
        ], $primary['customAttributes']);
        $t->same(true, $authoring['present']);
        $t->same(2, $authoring['itemCount']);
        $t->same(10, $authoring['attributeCount']);
        $t->same(3, $authoring['customAttributeCount']);
        $t->same(2, $authoring['customAttributeItemCount']);
        $t->same(['data-source', 'review:packet'], $authoring['customAttributeNames']);
        $t->same(1, $authoring['languageItemCount']);
        $t->same(1, $authoring['directionItemCount']);
        $t->same('primary-binding', $primaryAuthoring['id']);
        $t->same('application/x-authoring-widget', $primaryAuthoring['mediaType']);
        $t->same('widget-handler', $primaryAuthoring['handlerId']);
        $t->same('/EPUB/widgets/widget-handler.xhtml', $primaryAuthoring['handlerPartName']);
        $t->same('en-GB', $primaryAuthoring['language']);
        $t->same('rtl', $primaryAuthoring['direction']);
        $t->same(7, $primaryAuthoring['attributeCount']);
        $t->same(2, $primaryAuthoring['customAttributeCount']);
        $t->same('secondary-review', $secondaryAuthoring['customAttributes']['data-source']);
        $t->same($primaryAuthoring, $authoring['itemsByIndex'][0]);
        $t->same($primaryAuthoring, $authoring['itemsByMediaType']['application/x-authoring-widget']);
        $t->same($authoring, $summary['wordpressImport']['mediaTypeBindingAuthoring']);
        $t->same($authoring['items'], $summary['wordpressImport']['mediaTypeBindingAuthoringItems']);
        $t->same($authoring['customAttributeItems'], $summary['wordpressImport']['mediaTypeBindingAuthoringCustomAttributeItems']);
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

    'reports OPF metadata refinement target resolution for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithTargetedRefinements = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" id="package-record" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/">',
            $epub3OpfXml
        );
        $opfWithTargetedRefinements = str_replace(
            '<dc:title>WordPress Migration Guide</dc:title>',
            '<dc:title id="main-title">WordPress Migration Guide</dc:title>',
            $opfWithTargetedRefinements
        );
        $opfWithTargetedRefinements = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <link id="source-record" rel="record" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>
    <meta id="package-name" refines="#package-record" property="schema:name">Package record</meta>
    <meta refines="#main-title" property="file-as">WordPress Migration Guide, The</meta>
    <meta refines="#cover" property="schema:about">Cover manifest asset</meta>
    <meta refines="#chapter1-spine" property="schema:position">1</meta>
    <meta refines="#source-record" property="schema:about">Source link target</meta>
    <meta refines="#series" property="schema:name">Series collection</meta>
    <meta refines="#missing-subject" property="schema:name">Missing compact target</meta>',
            $opfWithTargetedRefinements
        );
        $opfWithTargetedRefinements = str_replace(
            '<itemref idref="chapter1"/>',
            '<itemref id="chapter1-spine" idref="chapter1"/>',
            $opfWithTargetedRefinements
        );
        $opfWithTargetedRefinements = str_replace(
            '</spine>',
            '</spine>
  <collection id="series" role="series">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Migration series</dc:title>
    </metadata>
  </collection>',
            $opfWithTargetedRefinements
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithTargetedRefinements],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $metadata = $epub->metadata();
        $targets = $metadata['refinementTargets'];
        $validation = $epub->validationReport();
        $summary = $epub->summary();
        $itemsBySubject = [];
        foreach ($targets['items'] as $item) {
            $itemsBySubject[$item['subjectId']] = $item;
        }

        $t->same(true, $targets['present']);
        $t->same(7, $targets['refinementCount']);
        $t->same(7, $targets['localRefinementCount']);
        $t->same(6, $targets['resolvedRefinementCount']);
        $t->same(1, $targets['unresolvedRefinementCount']);
        $t->same(1, $targets['diagnosticCount']);
        $t->same('unresolved-metadata-refinement-target', $targets['diagnostics'][0]['type']);
        $t->same('missing-subject', $targets['diagnostics'][0]['subjectId']);
        $t->same('Package record', $itemsBySubject['package-record']['value']);
        $t->same(['package'], $itemsBySubject['package-record']['targetKinds']);
        $t->same(['dc-metadata'], $itemsBySubject['main-title']['targetKinds']);
        $t->same(['manifest-item'], $itemsBySubject['cover']['targetKinds']);
        $t->same('/EPUB/images/cover.png', $itemsBySubject['cover']['targets'][0]['partName']);
        $t->same(['spine-itemref'], $itemsBySubject['chapter1-spine']['targetKinds']);
        $t->same('chapter1', $itemsBySubject['chapter1-spine']['targets'][0]['idref']);
        $t->same(['metadata-link'], $itemsBySubject['source-record']['targetKinds']);
        $t->same('text/chapter1.xhtml', $itemsBySubject['source-record']['targets'][0]['href']);
        $t->same(['collection'], $itemsBySubject['series']['targetKinds']);
        $t->same('series', $itemsBySubject['series']['targets'][0]['role']);
        $t->same(false, $itemsBySubject['missing-subject']['resolved']);
        $t->same('unresolved-metadata-refinement-target', $itemsBySubject['missing-subject']['diagnostics'][0]['type']);
        $t->same(false, $validation['metadata']['valid']);
        $t->same(false, $validation['metadata']['refinementTargetValid']);
        $t->same(1, $validation['metadata']['refinementTargetDiagnosticCount']);
        $t->same($targets['diagnostics'], $validation['metadata']['refinementTargetDiagnostics']);
        $t->same($targets, $validation['metadata']['refinementTargets']);
        $t->same($targets, $summary['metadataRefinementTargets']);
        $t->same($targets, $summary['wordpressImport']['metadataDetails']['refinementTargets']);
        $t->same($targets, $summary['wordpressImport']['metadataRefinementTargets']);
        $t->same($targets['diagnostics'], $summary['wordpressImport']['metadataRefinementTargetDiagnostics']);
    },

    'reports OPF package and collection link refines sanitation for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3NavXml): void {
        $opfWithLinkRefines = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" id="package-record" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:link-refines-sanitation</dc:identifier>
    <dc:title id="main-title">Link Refines Sanitation</dc:title>
    <dc:creator id="creator">Sanitation Desk</dc:creator>
    <dc:language>en-US</dc:language>
    <meta property="dcterms:modified">2026-06-25T09:45:00Z</meta>
    <meta id="package-label" refines="#package-self" property="schema:name">Package document self reference</meta>
    <link id="creator-record" rel="record" refines="#creator" href="meta/creator.json" media-type="application/json"/>
    <link id="package-self" rel="record" refines="#package-record" href="#package-record" media-type="application/oebps-package+xml"/>
    <link id="missing-package-link" rel="record" refines="#missing-package-target" href="meta/missing.json" media-type="application/json"/>
    <link id="bad fragment link" rel="record" refines="#bad fragment" href="meta/creator.json" media-type="application/json"/>
    <link id="dup-record" rel="record" refines="#bookid" href="meta/creator.json" media-type="application/json"/>
    <link id="dup-record" rel="alternate" refines="meta/foreign.opf#foreign" href="https://example.invalid/remote.json" media-type="application/json"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="creator-json" href="meta/creator.json" media-type="application/json"/>
    <item id="series-json" href="meta/series.json" media-type="application/json"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
  <collection id="series" role="series" xml:lang="en" dir="ltr">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title id="series-title">Series Packet</dc:title>
      <meta id="series-title-script" refines="#series-title" property="alternate-script" xml:lang="pl" dir="ltr">Pakiet serii</meta>
      <link id="series-metadata-record" rel="record" refines="#series-title" href="meta/series.json?kind=metadata#record" media-type="application/json"/>
    </metadata>
    <link id="series-record" rel="record" refines="#series-title-script" href="meta/series.json" media-type="application/json"/>
    <link id="series-metadata-member" rel="record" refines="#series-metadata-record" href="meta/series.json" media-type="application/json"/>
    <link id="series-missing" rel="record" refines="#missing-collection-target" href="meta/missing-series.json" media-type="application/json"/>
    <link id="bad collection link" rel="record" refines="#series-title" href="meta/series.json" media-type="application/json"/>
  </collection>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithLinkRefines],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Sanitation</h1></body></html>'],
            ['name' => 'EPUB/meta/creator.json', 'data' => '{"name":"Sanitation Desk"}'],
            ['name' => 'EPUB/meta/series.json', 'data' => '{"name":"Series Packet"}'],
        ]));

        $metadata = $epub->metadata();
        $targets = $metadata['refinementTargets'];
        $collections = $epub->collections();
        $collectionMetadataMeta = $collections[0]['metadata']['meta'][0];
        $collectionMetadataLinks = $collections[0]['metadata']['links'];
        $validation = $epub->validationReport();
        $summary = $epub->summary();
        $find = static function (string $source, string $refines, ?string $id = null) use ($targets): array {
            foreach ($targets['items'] as $item) {
                if (($item['source'] ?? null) !== $source || ($item['refines'] ?? null) !== $refines) {
                    continue;
                }
                if ($id !== null && ($item['id'] ?? null) !== $id) {
                    continue;
                }

                return $item;
            }

            throw new RuntimeException('Missing refinement item: ' . $source . ' ' . $refines);
        };

        $packageLinkDiagnostics = array_column($metadata['linkDiagnostics'], 'type');
        $collectionLinkDiagnostics = array_column($collections[0]['diagnostics'], 'type');

        $t->same(true, $targets['present']);
        $t->same(13, $targets['refinementCount']);
        $t->same(9, $targets['resolvedRefinementCount']);
        $t->same(4, $targets['unresolvedRefinementCount']);
        $t->same(1, $targets['packageRelativeRefinementCount']);
        $t->same(['unresolved-metadata-refinement-target', 'invalid-metadata-refinement-fragment', 'unresolved-metadata-refinement-target'], array_column($targets['diagnostics'], 'type'));

        $t->same(['dc-metadata'], $find('metadata-link', '#creator', 'creator-record')['targetKinds']);
        $t->same(['package'], $find('metadata-link', '#package-record', 'package-self')['targetKinds']);
        $t->same(['metadata-link'], $find('metadata-meta', '#package-self', 'package-label')['targetKinds']);
        $t->same(false, $find('metadata-link', '#missing-package-target', 'missing-package-link')['resolved']);
        $t->same('invalid-metadata-refinement-fragment', $find('metadata-link', '#bad fragment', 'bad fragment link')['diagnostics'][0]['type']);
        $t->same(true, $find('metadata-link', 'meta/foreign.opf#foreign', 'dup-record')['targetPackageRelative']);

        $t->same(['collection-dc-metadata'], $find('collection-metadata-meta', '#series-title', 'series-title-script')['targetKinds']);
        $t->same('series-title-script', $collectionMetadataMeta['id']);
        $t->same('pl', $collectionMetadataMeta['language']);
        $t->same('ltr', $collectionMetadataMeta['direction']);
        $t->same(1, $collections[0]['metadata']['linkCount']);
        $t->same('series-metadata-record', $collectionMetadataLinks[0]['id']);
        $t->same('/EPUB/meta/series.json?kind=metadata#record', $collectionMetadataLinks[0]['target']);
        $t->same(true, $collectionMetadataLinks[0]['hrefHasQuery']);
        $t->same('kind=metadata', $collectionMetadataLinks[0]['hrefQuery']);
        $t->same(true, $collectionMetadataLinks[0]['hrefHasFragment']);
        $t->same('record', $collectionMetadataLinks[0]['hrefFragment']);
        $t->same([], $collectionMetadataLinks[0]['diagnostics']);
        $t->same(['collection-dc-metadata'], $find('collection-metadata-link', '#series-title', 'series-metadata-record')['targetKinds']);
        $t->same(['collection-metadata-meta'], $find('collection-link', '#series-title-script', 'series-record')['targetKinds']);
        $t->same(['collection-metadata-link'], $find('collection-link', '#series-metadata-record', 'series-metadata-member')['targetKinds']);
        $t->same(false, $find('collection-link', '#missing-collection-target', 'series-missing')['resolved']);
        $t->same(['collection-dc-metadata'], $find('collection-link', '#series-title', 'bad collection link')['targetKinds']);

        $t->true(in_array('invalid-package-link-id', $packageLinkDiagnostics, true), 'Invalid package link id should be diagnosed');
        $t->same(2, count(array_filter($packageLinkDiagnostics, static fn (string $type): bool => $type === 'duplicate-package-link-id')));
        $t->true(in_array('external-package-link-target', $packageLinkDiagnostics, true), 'External package link target should be diagnosed');
        $t->true(in_array('missing-package-link-target', $packageLinkDiagnostics, true), 'Missing package link target should be diagnosed');
        $t->true(in_array('invalid-collection-link-id', $collectionLinkDiagnostics, true), 'Invalid collection link id should be diagnosed');
        $t->true(in_array('missing-collection-link-target', $collectionLinkDiagnostics, true), 'Missing collection link target should be diagnosed');

        $t->same(false, $validation['metadata']['valid']);
        $t->same(false, $validation['metadata']['refinementTargetValid']);
        $t->same($targets, $validation['metadata']['refinementTargets']);
        $t->same($targets, $summary['metadataRefinementTargets']);
        $t->same(1, $summary['remoteResourcePolicy']['collectionMetadataLinkCount']);
        $t->same(1, $summary['linkHrefSuffixes']['collectionMetadataLinkCount']);
        $t->same($targets['diagnostics'], $summary['wordpressImport']['metadataRefinementTargetDiagnostics']);
    },

    'summarizes compact OPF creator contributor display order for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithAgentOrder = str_replace(
            '<dc:creator id="creator">Data Liberation Team</dc:creator>',
            '<dc:creator id="creator">Data Liberation Team</dc:creator>
    <dc:contributor id="editor" xml:lang="en">Review Editor</dc:contributor>
    <dc:contributor id="illustrator">Illustration Desk</dc:contributor>
    <dc:contributor id="untyped">Untyped Reviewer</dc:contributor>',
            $epub3OpfXml
        );
        $opfWithAgentOrder = str_replace(
            '</metadata>',
            '    <meta refines="#creator" property="role" scheme="marc:relators">aut</meta>
    <meta refines="#creator" property="display-seq">2</meta>
    <meta refines="#creator" property="file-as">Team, Data Liberation</meta>
    <meta refines="#editor" property="role" scheme="marc:relators">edt</meta>
    <meta refines="#editor" property="display-seq">1</meta>
    <meta refines="#illustrator" property="role" scheme="marc:relators">ill</meta>
    <meta refines="#illustrator" property="display-seq">appendix</meta>
    <meta refines="#untyped" property="alternate-script" xml:lang="en">Untyped reviewer alternate</meta>
  </metadata>',
            $opfWithAgentOrder
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithAgentOrder],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $agentOrder = $metadata['agentDisplayOrder'];

        $t->same(true, $agentOrder['present']);
        $t->same(4, $agentOrder['count']);
        $t->same(2, $agentOrder['sequencedCount']);
        $t->same(1, $agentOrder['invalidDisplaySeqCount']);
        $t->same(1, $agentOrder['unsequencedCount']);
        $t->same(['Review Editor', 'Data Liberation Team', 'Illustration Desk', 'Untyped Reviewer'], array_map(
            static fn (array $item): string => $item['text'],
            $agentOrder['items']
        ));
        $t->same('contributor', $agentOrder['items'][0]['kind']);
        $t->same(1, $agentOrder['items'][0]['displaySeqNumber']);
        $t->same('edt', $agentOrder['items'][0]['primaryRole']);
        $t->same('creator', $agentOrder['items'][1]['kind']);
        $t->same(2, $agentOrder['items'][1]['displaySeqNumber']);
        $t->same('Team, Data Liberation', $agentOrder['items'][1]['fileAs']);
        $t->same('appendix', $agentOrder['items'][2]['displaySeq']);
        $t->same(false, $agentOrder['items'][2]['displaySeqValid']);
        $t->same('invalid-agent-display-seq', $agentOrder['items'][2]['diagnostics'][0]['type']);
        $t->same(true, $agentOrder['items'][3]['unsequenced']);
        $t->same('Untyped reviewer alternate', $agentOrder['items'][3]['alternateScripts'][0]['text']);
        $t->same(1, count($agentOrder['byKind']['creator']));
        $t->same(3, count($agentOrder['byKind']['contributor']));
        $t->same('Review Editor', $agentOrder['byRole']['edt'][0]['text']);
        $t->same('invalid-agent-display-seq', $agentOrder['diagnostics'][0]['type']);
        $t->same('Illustration Desk', $agentOrder['diagnostics'][0]['text']);
        $t->same(['Review Editor', 'Illustration Desk', 'Untyped Reviewer'], $metadata['contributors']);
        $t->same($metadata['contributorDetails'], $summary['wordpressImport']['metadataDetails']['contributorDetails']);
        $t->same($agentOrder, $summary['wordpressImport']['metadataDetails']['agentDisplayOrder']);
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
        $validation = $epub->validationReport();
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
        $t->same(false, $validation['valid']);
        $t->same(false, $validation['metadata']['identifierValid']);
        $t->same(2, $validation['metadata']['identifierDiagnosticCount']);
        $t->same($metadata['identifierDiagnostics'], $validation['metadata']['identifierDiagnostics']);
        $t->same($metadata['identifierDiagnostics'], $validation['metadata']['diagnostics']);
        $t->same($metadata['identifierDiagnostics'], $validation['diagnostics']);
        $t->same($uniqueIdentifier, $summary['wordpressImport']['metadataDetails']['uniqueIdentifier']);
        $t->same($identifierSummary, $summary['wordpressImport']['metadataDetails']['identifierSummary']);
        $t->same($metadata['identifierDiagnostics'], $summary['wordpressImport']['metadataDetails']['identifierDiagnostics']);
        $t->same($metadata['identifierDiagnostics'], $summary['wordpressImport']['packageValidation']['metadata']['identifierDiagnostics']);
        $t->same($metadata['identifierDiagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
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

    'preserves namespaced OPF date event attributes for compact package handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithDateNamespace = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf"',
            '<package xmlns="http://www.idpf.org/2007/opf" xmlns:opf="http://www.idpf.org/2007/opf"',
            $epub3OpfXml
        );
        $opfWithDateNamespace = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:language>en-US</dc:language>
    <dc:date id="created" opf:scheme="W3CDTF" opf:event="creation">2026-06-02</dc:date>',
            $opfWithDateNamespace
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithDateNamespace],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $date = $metadata['dateDetails'][0];

        $t->same('created', $date['id']);
        $t->same('2026-06-02', $date['text']);
        $t->same('W3CDTF', $date['scheme']);
        $t->same('creation', $date['event']);
        $t->same('attribute', $date['eventSource']);
        $t->same('2026-06-02', $metadata['datesByEvent']['creation'][0]['text']);
        $t->same([
            'present' => true,
            'count' => 1,
            'eventCount' => 1,
            'events' => ['creation'],
        ], $metadata['dateSummary']);
        $t->same($metadata['dateDetails'], $summary['wordpressImport']['metadataDetails']['dateDetails']);
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
        $sourceJson = '{"source":"print","records":1}';
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
    <link id="source-record" rel="record source" refines="#print-source" href="meta/source.json?profile=review#record" media-type="application/ld+json"/>
    <link id="archive-missing-record" rel="record" refines="#archive-source" href="meta/archive-missing.json" media-type="application/json"/>
    <link id="archive-remote-record" rel="record" refines="#archive-source" href="https://example.invalid/archive-source.json" media-type="application/json"/>
  </metadata>',
            $opfWithSources
        );
        $opfWithSources = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="source-record" href="meta/source.json" media-type="application/ld+json"/>',
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
            ['name' => 'EPUB/meta/source.json', 'data' => $sourceJson],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $sourceDetails = $metadata['sourceDetails'];
        $printSourceRecord = $sourceDetails[0]['linkedResources'][0];
        $archiveMissingRecord = $sourceDetails[1]['linkedResources'][0];
        $archiveRemoteRecord = $sourceDetails[1]['linkedResources'][1];

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
        $t->same(1, $sourceDetails[0]['linkedResourceCount']);
        $t->same(1, $sourceDetails[0]['localLinkedResourceCount']);
        $t->same(0, $sourceDetails[0]['externalLinkedResourceCount']);
        $t->same(0, $sourceDetails[0]['missingLinkedResourceCount']);
        $t->same('source-record', $printSourceRecord['id']);
        $t->same(['record', 'source'], $printSourceRecord['rel']);
        $t->same('/EPUB/meta/source.json?profile=review#record', $printSourceRecord['target']);
        $t->same('/EPUB/meta/source.json', $printSourceRecord['partName']);
        $t->same('source-record', $printSourceRecord['manifestId']);
        $t->same('application/ld+json', $printSourceRecord['manifestMediaType']);
        $t->same(true, $printSourceRecord['exists']);
        $t->same(strlen($sourceJson), $printSourceRecord['byteLength']);
        $t->same(hash('crc32b', $sourceJson), $printSourceRecord['crc32']);
        $t->same('profile=review', $printSourceRecord['hrefQuery']);
        $t->same('record', $printSourceRecord['hrefFragment']);
        $t->same('archive-source', $sourceDetails[1]['id']);
        $t->same('fr', $sourceDetails[1]['language']);
        $t->same('ltr', $sourceDetails[1]['direction']);
        $t->same('transcription', $sourceDetails[1]['sourceOf']);
        $t->same('Archive scan packet A translated', $sourceDetails[1]['alternateScripts'][0]['text']);
        $t->same('en', $sourceDetails[1]['alternateScripts'][0]['language']);
        $t->same(2, $sourceDetails[1]['linkedResourceCount']);
        $t->same(1, $sourceDetails[1]['localLinkedResourceCount']);
        $t->same(1, $sourceDetails[1]['externalLinkedResourceCount']);
        $t->same(1, $sourceDetails[1]['missingLinkedResourceCount']);
        $t->same('archive-missing-record', $archiveMissingRecord['id']);
        $t->same('/EPUB/meta/archive-missing.json', $archiveMissingRecord['partName']);
        $t->same(false, $archiveMissingRecord['exists']);
        $t->same('missing-package-link-target', $archiveMissingRecord['diagnostics'][0]['type']);
        $t->same('archive-remote-record', $archiveRemoteRecord['id']);
        $t->same(true, $archiveRemoteRecord['external']);
        $t->same('https://example.invalid/archive-source.json', $archiveRemoteRecord['target']);
        $t->same('external-package-link-target', $archiveRemoteRecord['diagnostics'][0]['type']);
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
            'linkedResourceCount' => 3,
            'localLinkedResourceCount' => 2,
            'externalLinkedResourceCount' => 1,
            'missingLinkedResourceCount' => 1,
            'linkedResourceRelCounts' => ['record' => 3, 'source' => 1],
            'diagnosticCount' => 2,
            'diagnostics' => [
                [
                    'sourceIndex' => 1,
                    'sourceId' => 'archive-source',
                    'linkIndex' => 1,
                    'linkId' => 'archive-missing-record',
                    'type' => 'missing-package-link-target',
                    'href' => 'meta/archive-missing.json',
                    'partName' => '/EPUB/meta/archive-missing.json',
                    'message' => 'EPUB OPF metadata link target is missing from the package',
                ],
                [
                    'sourceIndex' => 1,
                    'sourceId' => 'archive-source',
                    'linkIndex' => 2,
                    'linkId' => 'archive-remote-record',
                    'type' => 'external-package-link-target',
                    'href' => 'https://example.invalid/archive-source.json',
                    'message' => 'EPUB OPF metadata link points outside the package and was not fetched',
                ],
            ],
        ], $metadata['sourceSummary']);

        $t->same($sourceDetails, $summary['wordpressImport']['metadataDetails']['sourceDetails']);
        $t->same($metadata['sourcesByType'], $summary['wordpressImport']['metadataDetails']['sourcesByType']);
        $t->same($metadata['sourceSummary'], $summary['wordpressImport']['metadataDetails']['sourceSummary']);
    },

    'summarizes OPF belongs to collection metadata for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithCollectionMembership = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta id="series-membership" property="belongs-to-collection" xml:lang="en" dir="ltr">Migration Series</meta>
    <meta refines="#series-membership" property="collection-type">series</meta>
    <meta refines="#series-membership" property="group-position">3</meta>
    <meta refines="#series-membership" property="display-seq">1</meta>
    <meta refines="#series-membership" property="file-as">Migration Series</meta>
    <meta id="set-membership" property="belongs-to-collection" content="Reviewer Set"/>
    <meta refines="#set-membership" property="collection-type">set</meta>
    <meta refines="#set-membership" property="group-position">appendix</meta>
    <link id="series-record" rel="record" refines="#series-membership" href="meta/series.json" media-type="application/ld+json"/>
    <link id="remote-set-record" rel="record" refines="#set-membership" href="https://example.invalid/set.json" media-type="application/json"/>',
            $epub3OpfXml
        );
        $opfWithCollectionMembership = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="series-record" href="meta/series.json" media-type="application/ld+json"/>',
            $opfWithCollectionMembership
        );

        $seriesRecord = '{"name":"Migration Series","source":"compact-epub"}';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithCollectionMembership],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/series.json', 'data' => $seriesRecord],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $membership = $metadata['collectionMembership'];

        $t->same(true, $membership['present']);
        $t->same(2, $membership['count']);
        $t->same(['series', 'set'], $membership['types']);
        $t->same(2, $membership['typedCount']);
        $t->same(1, $membership['positionedCount']);
        $t->same(1, $membership['invalidGroupPositionCount']);
        $t->same(['invalid-collection-group-position'], array_column($membership['diagnostics'], 'type'));

        $series = $membership['items'][0];
        $t->same('series-membership', $series['id']);
        $t->same('Migration Series', $series['title']);
        $t->same('Migration Series', $series['text']);
        $t->same('Migration Series', $series['content']);
        $t->same('series', $series['collectionType']);
        $t->same('3', $series['groupPosition']);
        $t->same(3.0, $series['groupPositionNumber']);
        $t->same('1', $series['displaySeq']);
        $t->same('Migration Series', $series['fileAs']);
        $t->same('en', $series['language']);
        $t->same('ltr', $series['direction']);
        $t->same(1, $series['linkedResourceCount']);
        $t->same(1, $series['localLinkedResourceCount']);
        $t->same('series-record', $series['linkedResources'][0]['id']);
        $t->same('/EPUB/meta/series.json', $series['linkedResources'][0]['partName']);
        $t->same(strlen($seriesRecord), $series['linkedResources'][0]['byteLength']);

        $set = $membership['items'][1];
        $t->same('set-membership', $set['id']);
        $t->same('Reviewer Set', $set['title']);
        $t->same('', $set['text']);
        $t->same('Reviewer Set', $set['content']);
        $t->same('set', $set['collectionType']);
        $t->same('appendix', $set['groupPosition']);
        $t->same(null, $set['groupPositionNumber']);
        $t->same('invalid-collection-group-position', $set['diagnostics'][0]['type']);
        $t->same(1, $set['linkedResourceCount']);
        $t->same(1, $set['externalLinkedResourceCount']);
        $t->same('remote-set-record', $set['linkedResources'][0]['id']);
        $t->same('external-package-link-target', $set['linkedResources'][0]['diagnostics'][0]['type']);

        $case = $summary['compactPackageReport']['casesById']['metadata-collection-membership'];
        $t->same(2, $case['itemCount']);
        $t->same(['series', 'set'], $case['types']);
        $t->same(1, $case['positionedCount']);
        $t->same(1, $case['invalidGroupPositionCount']);
        $t->same(2, $case['linkedResourceCount']);
        $t->same(['invalid-collection-group-position'], $case['diagnosticTypes']);
        $t->same($membership, $summary['collectionMembership']);
        $t->same($membership, $summary['wordpressImport']['metadataDetails']['collectionMembership']);
        $t->same($membership, $summary['wordpressImport']['metadataCollectionMembership']);
        $t->same($membership['diagnostics'], $summary['wordpressImport']['metadataCollectionMembershipDiagnostics']);
    },

    'summarizes OPF subject authority metadata for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $subjectRecord = '{"@context":"https://schema.org","about":"subject review"}';
        $opfWithSubjects = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:subject id="subject-genre" scheme="BISAC" xml:lang="en" dir="ltr">Computers / Data Migration</dc:subject>
    <dc:subject id="subject-tag">WordPress import</dc:subject>
    <dc:subject>Review orphan</dc:subject>
    <dc:language>en-US</dc:language>',
            $epub3OpfXml
        );
        $opfWithSubjects = str_replace(
            '</metadata>',
            '    <meta refines="#subject-genre" property="authority">BISAC Subject Headings</meta>
    <meta refines="#subject-genre" property="term">COM051000</meta>
    <meta refines="#subject-genre" property="display-seq">1</meta>
    <meta refines="#subject-genre" property="file-as">Data migration</meta>
    <meta refines="#subject-genre" property="alternate-script" xml:lang="es" dir="ltr">Migracion de datos</meta>
    <meta refines="#subject-tag" property="term">wordpress-import</meta>
    <meta refines="#subject-tag" property="display-seq">appendix</meta>
    <link id="subject-record" rel="record" refines="#subject-genre" href="meta/subject.json" media-type="application/ld+json"/>
    <link id="subject-missing" rel="record" refines="#subject-tag" href="meta/missing.json" media-type="application/json"/>
  </metadata>',
            $opfWithSubjects
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithSubjects],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/subject.json', 'data' => $subjectRecord],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $subjectDetails = $metadata['subjectDetails'];
        $subjectSummary = $metadata['subjectSummary'];

        $t->same(['Computers / Data Migration', 'WordPress import', 'Review orphan'], $metadata['subjects']);
        $t->same(3, count($subjectDetails));

        $t->same('subject-genre', $subjectDetails[0]['id']);
        $t->same('BISAC', $subjectDetails[0]['scheme']);
        $t->same('en', $subjectDetails[0]['language']);
        $t->same('ltr', $subjectDetails[0]['direction']);
        $t->same('BISAC Subject Headings', $subjectDetails[0]['authority']);
        $t->same('COM051000', $subjectDetails[0]['term']);
        $t->same('1', $subjectDetails[0]['displaySeq']);
        $t->same(1, $subjectDetails[0]['displaySeqNumber']);
        $t->same(true, $subjectDetails[0]['displaySeqValid']);
        $t->same('Data migration', $subjectDetails[0]['fileAs']);
        $t->same('Migracion de datos', $subjectDetails[0]['alternateScripts'][0]['text']);
        $t->same('es', $subjectDetails[0]['alternateScripts'][0]['language']);
        $t->same('subject-record', $subjectDetails[0]['linkedResources'][0]['id']);
        $t->same('/EPUB/meta/subject.json', $subjectDetails[0]['linkedResources'][0]['partName']);
        $t->same(strlen($subjectRecord), $subjectDetails[0]['linkedResources'][0]['byteLength']);
        $t->same(1, $subjectDetails[0]['localLinkedResourceCount']);

        $t->same('subject-tag', $subjectDetails[1]['id']);
        $t->same('wordpress-import', $subjectDetails[1]['term']);
        $t->same('appendix', $subjectDetails[1]['displaySeq']);
        $t->same(null, $subjectDetails[1]['displaySeqNumber']);
        $t->same(false, $subjectDetails[1]['displaySeqValid']);
        $t->same('invalid-subject-display-seq', $subjectDetails[1]['diagnostics'][0]['type']);
        $t->same('subject-missing', $subjectDetails[1]['linkedResources'][0]['id']);
        $t->same('/EPUB/meta/missing.json', $subjectDetails[1]['linkedResources'][0]['partName']);
        $t->same(1, $subjectDetails[1]['missingLinkedResourceCount']);

        $t->same('Review orphan', $subjectDetails[2]['text']);
        $t->same(null, $subjectDetails[2]['id']);
        $t->same([], $subjectDetails[2]['linkedResources']);

        $t->same(true, $subjectSummary['present']);
        $t->same(3, $subjectSummary['count']);
        $t->same(1, $subjectSummary['schemeCount']);
        $t->same(1, $subjectSummary['authorityCount']);
        $t->same(2, $subjectSummary['termCount']);
        $t->same(1, $subjectSummary['sequencedCount']);
        $t->same(1, $subjectSummary['invalidDisplaySeqCount']);
        $t->same(1, $subjectSummary['alternateScriptCount']);
        $t->same(2, $subjectSummary['linkedResourceCount']);
        $t->same(2, $subjectSummary['localLinkedResourceCount']);
        $t->same(1, $subjectSummary['missingLinkedResourceCount']);
        $t->same(['BISAC'], $subjectSummary['schemes']);
        $t->same(['BISAC Subject Headings'], $subjectSummary['authorities']);
        $t->same(['COM051000', 'wordpress-import'], $subjectSummary['terms']);
        $t->same('invalid-subject-display-seq', $subjectSummary['diagnostics'][0]['type']);

        $t->same('Computers / Data Migration', $metadata['subjectsByScheme']['BISAC'][0]['text']);
        $t->same('Computers / Data Migration', $metadata['subjectsByAuthority']['BISAC Subject Headings'][0]['text']);
        $t->same('WordPress import', $metadata['subjectsByTerm']['wordpress-import'][0]['text']);
        $t->same($subjectDetails, $summary['wordpressImport']['metadataDetails']['subjectDetails']);
        $t->same($metadata['subjectsByAuthority'], $summary['wordpressImport']['metadataDetails']['subjectsByAuthority']);
        $t->same($subjectSummary, $summary['wordpressImport']['metadataDetails']['subjectSummary']);
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

    'links OPF rights metadata to license resources for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithRightsLinks = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:rights id="license-json" xml:lang="en">Creative Commons Attribution 4.0</dc:rights>
    <dc:rights id="license-url">Publisher migration rights page</dc:rights>
    <dc:language>en-US</dc:language>',
            $epub3OpfXml
        );
        $opfWithRightsLinks = str_replace(
            '</metadata>',
            '    <meta refines="#license-json" property="authority">Creative Commons</meta>
    <meta refines="#license-json" property="term">CC-BY-4.0</meta>
    <link id="license-record" rel="license record" refines="#license-json" href="meta/license.json?profile=review#license" media-type="application/ld+json" properties="schema-org"/>
    <link id="remote-license" rel="license" refines="#license-url" href="https://creativecommons.org/licenses/by/4.0/" media-type="text/html"/>
  </metadata>',
            $opfWithRightsLinks
        );
        $opfWithRightsLinks = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="license-record" href="meta/license.json" media-type="application/ld+json"/>',
            $opfWithRightsLinks
        );

        $licenseJson = '{"license":"CC-BY-4.0","source":"publisher"}';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithRightsLinks],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/license.json', 'data' => $licenseJson],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $rightsDetails = $metadata['rightsDetails'];
        $rightsSummary = $metadata['rightsSummary'];
        $localRights = $rightsDetails[0];
        $remoteRights = $rightsDetails[1];
        $localLicense = $localRights['linkedResources'][0];
        $remoteLicense = $remoteRights['linkedResources'][0];

        $t->same(['Creative Commons Attribution 4.0', 'Publisher migration rights page'], $metadata['rights']);
        $t->same(2, count($rightsDetails));
        $t->same('license-json', $localRights['id']);
        $t->same('Creative Commons Attribution 4.0', $localRights['text']);
        $t->same('Creative Commons', $localRights['authority']);
        $t->same('CC-BY-4.0', $localRights['term']);
        $t->same(1, $localRights['linkedResourceCount']);
        $t->same(1, $localRights['localLinkedResourceCount']);
        $t->same(0, $localRights['externalLinkedResourceCount']);

        $t->same('license-record', $localLicense['id']);
        $t->same(['license', 'record'], $localLicense['rel']);
        $t->same('/EPUB/meta/license.json?profile=review#license', $localLicense['target']);
        $t->same('/EPUB/meta/license.json', $localLicense['partName']);
        $t->same(true, $localLicense['hrefHasQuery']);
        $t->same('profile=review', $localLicense['hrefQuery']);
        $t->same(true, $localLicense['hrefHasFragment']);
        $t->same('license', $localLicense['hrefFragment']);
        $t->same('license-record', $localLicense['manifestId']);
        $t->same('application/ld+json', $localLicense['manifestMediaType']);
        $t->same(true, $localLicense['exists']);
        $t->same(strlen($licenseJson), $localLicense['byteLength']);
        $t->same(hash('crc32b', $licenseJson), $localLicense['crc32']);
        $t->same(true, $localLicense['canExposeBytes']);

        $t->same('license-url', $remoteRights['id']);
        $t->same(1, $remoteRights['linkedResourceCount']);
        $t->same(0, $remoteRights['localLinkedResourceCount']);
        $t->same(1, $remoteRights['externalLinkedResourceCount']);
        $t->same('remote-license', $remoteLicense['id']);
        $t->same(true, $remoteLicense['external']);
        $t->same('https://creativecommons.org/licenses/by/4.0/', $remoteLicense['target']);
        $t->same('external-package-link-target', $remoteLicense['diagnostics'][0]['type']);

        $t->same(true, $rightsSummary['present']);
        $t->same(2, $rightsSummary['count']);
        $t->same(['Creative Commons'], $rightsSummary['authorities']);
        $t->same(['CC-BY-4.0'], $rightsSummary['terms']);
        $t->same(2, $rightsSummary['linkedResourceCount']);
        $t->same(1, $rightsSummary['localLinkedResourceCount']);
        $t->same(1, $rightsSummary['externalLinkedResourceCount']);
        $t->same(0, $rightsSummary['missingLinkedResourceCount']);
        $t->same(['license' => 2, 'record' => 1], $rightsSummary['linkedResourceRelCounts']);
        $t->same(1, $rightsSummary['diagnosticCount']);
        $t->same('external-package-link-target', $rightsSummary['diagnostics'][0]['type']);
        $t->same('remote-license', $rightsSummary['diagnostics'][0]['linkId']);

        $t->same(2, $metadata['bibliographicSummary']['linkedResourceCount']);
        $t->same($rightsDetails, $metadata['bibliographicDetailsByKind']['rights']);
        $t->same($rightsDetails, $summary['wordpressImport']['metadataDetails']['rightsDetails']);
        $t->same($rightsSummary, $summary['wordpressImport']['metadataDetails']['rightsSummary']);
        $t->same($metadata['bibliographicSummary'], $summary['wordpressImport']['metadataDetails']['bibliographicSummary']);
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

    'preserves OPF guide reference authoring attributes for package review handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithGuideAuthoring = str_replace(
            '</spine>',
            '</spine>
  <guide xmlns:review="https://example.invalid/epub-review">
    <reference id="start-ref" type="text" title="Start reading" href="text/chapter1.xhtml#install" xml:lang="fr" dir="rtl" data-review="primary" review:source="wp-import"/>
    <reference id="cover-ref" type="cover" title="Cover image" href="images/cover.png" xml:base="guide/" data-review="cover"/>
    <reference type="glossary" title="Remote glossary" href="https://example.invalid/glossary.xhtml" dir="ltr"/>
  </guide>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithGuideAuthoring],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="install">Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $guide = $epub->guideReferences();
        $summary = $epub->summary();
        $report = $summary['guideReferenceAuthoring'];
        $start = $guide[0];
        $cover = $guide[1];
        $remote = $guide[2];

        $t->same($summary['guideAuthoring'], $report);
        $t->same('start-ref', $start['id']);
        $t->same('fr', $start['language']);
        $t->same('rtl', $start['direction']);
        $t->same(null, $start['base']);
        $t->same('primary', $start['attributes']['data-review']);
        $t->same('wp-import', $start['attributes']['review:source']);
        $t->same(['data-review' => 'primary', 'review:source' => 'wp-import'], $start['customAttributes']);
        $t->same([
            'dir' => 'rtl',
            'href' => 'text/chapter1.xhtml#install',
            'id' => 'start-ref',
            'title' => 'Start reading',
            'type' => 'text',
            'xml:lang' => 'fr',
        ], $report['itemsById']['start-ref']['structuralAttributes']);

        $t->same('cover-ref', $cover['id']);
        $t->same(null, $cover['language']);
        $t->same(null, $cover['direction']);
        $t->same('guide/', $cover['base']);
        $t->same(['data-review' => 'cover'], $cover['customAttributes']);
        $t->same('/EPUB/images/cover.png', $cover['partName']);
        $t->same('cover', $cover['manifestId']);
        $t->same('glossary', $remote['type']);
        $t->same('ltr', $remote['direction']);
        $t->same(true, $remote['external']);

        $t->same(true, $report['present']);
        $t->same(3, $report['itemCount']);
        $t->same(18, $report['attributeCount']);
        $t->same(3, $report['customAttributeCount']);
        $t->same(1, $report['languageItemCount']);
        $t->same(2, $report['directionItemCount']);
        $t->same(1, $report['baseItemCount']);
        $t->same(2, $report['customAttributeItemCount']);
        $t->same(['data-review', 'review:source'], $report['customAttributeNames']);
        $t->same(['start-ref'], array_column($report['languageItems'], 'id'));
        $t->same(['start-ref', null], array_column($report['directionItems'], 'id'));
        $t->same(['cover-ref'], array_column($report['baseItems'], 'id'));
        $t->same('reported-not-applied-to-package-paths', $report['itemsById']['cover-ref']['baseResolutionPolicy']);
        $t->same(false, $report['itemsById']['cover-ref']['baseResolution']['appliesToPackagePaths']);
        $t->same($report, $summary['wordpressImport']['guideReferenceAuthoring']);
        $t->same($report['items'], $summary['wordpressImport']['guideReferenceAuthoringItems']);
        $t->same($report['customAttributeItems'], $summary['wordpressImport']['guideReferenceAuthoringCustomAttributeItems']);
    },

    'summarizes EPUB3 auxiliary navigation sections for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $navWithAuxiliarySections = str_replace(
            '</body>',
            '    <nav id="figures-nav" epub:type="loi list-of-illustrations">
      <h2>Figures</h2>
      <ol>
        <li><a href="text/chapter1.xhtml#fig-1">Figure 1</a></li>
        <li><a href="https://cdn.example.invalid/figures/source.svg">Remote figure source</a></li>
      </ol>
    </nav>
    <nav id="tables-nav" epub:type="lot" hidden="hidden">
      <h2>Tables</h2>
      <ol>
        <li><a href="text/missing.xhtml#table-1">Missing table</a></li>
      </ol>
    </nav>
  </body>',
            $epub3NavXml
        );
        $chapter1 = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1><figure id="fig-1"></figure></body></html>';
        $chapter2 = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1><figure id="fig-2"></figure><table id="table-1"></table></body></html>';

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithAuxiliarySections],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => $chapter1],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => $chapter2],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $summary = $epub->summary();
        $auxiliary = $summary['auxiliaryNavigation'];
        $policy = $summary['auxiliaryNavigationTargetPolicy'];

        $t->same($auxiliary, $summary['wordpressImport']['auxiliaryNavigation']);
        $t->same($auxiliary['sections'], $summary['wordpressImport']['auxiliaryNavigationSections']);
        $t->same($auxiliary['items'], $summary['wordpressImport']['auxiliaryNavigationTargets']);
        $t->same($policy, $summary['wordpressImport']['auxiliaryNavigationTargetPolicy']);
        $t->same($policy['items'], $summary['wordpressImport']['auxiliaryNavigationTargetPolicyItems']);
        $t->same($policy['diagnostics'], $summary['wordpressImport']['auxiliaryNavigationTargetPolicyDiagnostics']);
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
        $t->same('/EPUB/text/missing.xhtml#table-1', $auxiliary['items'][2]['target']);

        $t->same(true, $policy['present']);
        $t->same(2, $policy['sectionCount']);
        $t->same(3, $policy['itemCount']);
        $t->same(3, $policy['targetedItemCount']);
        $t->same(2, $policy['localTargetCount']);
        $t->same(1, $policy['validTargetCount']);
        $t->same(1, $policy['externalTargetCount']);
        $t->same(0, $policy['missingTargetCount']);
        $t->same(1, $policy['missingReferenceCount']);
        $t->same(0, $policy['blockedTargetCount']);
        $t->same(2, $policy['diagnosticCount']);
        $t->same(['loi', 'list-of-illustrations', 'lot'], $policy['types']);
        $t->same(['external-auxiliary-nav-target', 'missing-auxiliary-nav-reference'], array_column($policy['diagnostics'], 'type'));

        $localTarget = $policy['items'][0];
        $t->same('Figure 1', $localTarget['label']);
        $t->same('/EPUB/text/chapter1.xhtml#fig-1', $localTarget['target']);
        $t->same('/EPUB/text/chapter1.xhtml', $localTarget['partName']);
        $t->same(false, $localTarget['external']);
        $t->same(true, $localTarget['exists']);
        $t->same(true, $localTarget['validTarget']);
        $t->same(true, $localTarget['hrefHasFragment']);
        $t->same('fig-1', $localTarget['hrefFragment']);
        $t->same(strlen($chapter1), $localTarget['byteLength']);
        $t->same(hash('sha256', $chapter1), $localTarget['byteSha256']);
        $t->same([], $localTarget['diagnostics']);

        $remoteTarget = $policy['itemsBySectionType']['list-of-illustrations'][1];
        $t->same('Remote figure source', $remoteTarget['label']);
        $t->same(true, $remoteTarget['external']);
        $t->same(false, $remoteTarget['exists']);
        $t->same('external-auxiliary-nav-target', $remoteTarget['diagnostics'][0]['type']);

        $missingTarget = $policy['itemsBySectionType']['lot'][0];
        $t->same('Missing table', $missingTarget['label']);
        $t->same('/EPUB/text/missing.xhtml#table-1', $missingTarget['target']);
        $t->same('/EPUB/text/missing.xhtml', $missingTarget['partName']);
        $t->same(false, $missingTarget['exists']);
        $t->same('missing-auxiliary-nav-reference', $missingTarget['diagnostics'][0]['type']);
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
    <link id="series-record" rel="record" href="meta/series.json" media-type="application/ld+json" properties="review" title="Series record" hreflang="en-GB" xml:lang="en" dir="ltr"/>
    <link id="start" rel="first" href="text/chapter1.xhtml#install" media-type="application/xhtml+xml" hreflang="fr" xml:lang="fr" dir="rtl"/>
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
        $t->same('Series record', $series['links'][0]['title']);
        $t->same('en-GB', $series['links'][0]['hreflang']);
        $t->same('en', $series['links'][0]['language']);
        $t->same('ltr', $series['links'][0]['direction']);
        $t->same('fr', $series['links'][1]['hreflang']);
        $t->same('fr', $series['links'][1]['language']);
        $t->same('rtl', $series['links'][1]['direction']);
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

    'preserves OPF collection link authoring language fields for package handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithCollectionLinkAuthoring = str_replace(
            '</spine>',
            '</spine>
  <collection id="localized-series" role="series" xml:lang="en">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Localized series links</dc:title></metadata>
    <link id="series-fr" rel="record" href="meta/series-fr.json" media-type="application/ld+json" title="Fiche de serie" hreflang="fr-CA" xml:lang="fr" dir="rtl"/>
  </collection>',
            $epub3OpfXml
        );

        $seriesRecord = '{"name":"fiche de serie"}';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithCollectionLinkAuthoring],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/series-fr.json', 'data' => $seriesRecord],
        ]));

        $summary = $epub->summary();
        $collection = $epub->collections()[0];
        $link = $collection['links'][0];
        $policyItem = $summary['remoteResourcePolicy']['items'][0];

        $t->same('localized-series', $collection['id']);
        $t->same('en', $collection['language']);
        $t->same('series-fr', $link['id']);
        $t->same('/EPUB/meta/series-fr.json', $link['target']);
        $t->same('/EPUB/meta/series-fr.json', $link['partName']);
        $t->same(true, $link['exists']);
        $t->same('Fiche de serie', $link['title']);
        $t->same('fr-CA', $link['hreflang']);
        $t->same('fr', $link['language']);
        $t->same('rtl', $link['direction']);
        $t->same(strlen($seriesRecord), $link['byteLength']);
        $t->same(hash('crc32b', $seriesRecord), $link['crc32']);

        $t->same($link, $summary['collections'][0]['links'][0]);
        $t->same($link, $summary['wordpressImport']['collections'][0]['links'][0]);
        $t->same(['/EPUB/meta/series-fr.json'], $summary['wordpressImport']['collectionLinkTargets']);
        $t->same('collection-link', $policyItem['source']);
        $t->same('series-fr', $policyItem['id']);
        $t->same('Fiche de serie', $policyItem['title']);
        $t->same('fr-CA', $policyItem['hreflang']);
        $t->same('fr', $policyItem['language']);
        $t->same('rtl', $policyItem['direction']);
        $t->same('local-package', $policyItem['policy']);
    },

    'preserves OPF collection authoring attributes for package review handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithCollectionAuthoring = str_replace(
            '</spine>',
            '</spine>
  <collection xmlns:review="https://example.invalid/epub-review" id="curated-series" role="series curated" xml:base="collections/" xml:lang="fr" dir="rtl" data-review="series" review:source="wp-import">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Curated series</dc:title></metadata>
    <collection id="preview-samples" role="preview" xml:base="samples/" data-review="samples">
      <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Preview samples</dc:title></metadata>
    </collection>
  </collection>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithCollectionAuthoring],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $collections = $epub->collections();
        $summary = $epub->summary();
        $collection = $collections[0];
        $child = $collection['children'][0];
        $authoring = $summary['collectionAuthoring'];
        $item = $authoring['itemsByPathKey']['0'];
        $childItem = $authoring['itemsByPathKey']['0.0'];

        $t->same('collections/', $collection['base']);
        $t->same('series', $collection['customAttributes']['data-review']);
        $t->same('wp-import', $collection['customAttributes']['review:source']);
        $t->same(false, array_key_exists('xmlns:review', $collection['customAttributes']));
        $t->same('samples/', $child['base']);
        $t->same(['data-review' => 'samples'], $child['customAttributes']);

        $t->same(true, $authoring['present']);
        $t->same(2, $authoring['collectionCount']);
        $t->same(1, $authoring['languageItemCount']);
        $t->same(1, $authoring['directionItemCount']);
        $t->same(2, $authoring['baseItemCount']);
        $t->same(2, $authoring['customAttributeItemCount']);
        $t->same(['0', '0.0'], array_column($authoring['items'], 'pathKey'));
        $t->same(['curated-series', 'preview-samples'], array_column($authoring['items'], 'id'));

        $t->same('curated-series', $item['id']);
        $t->same(['series', 'curated'], $item['roleTokens']);
        $t->same('fr', $item['language']);
        $t->same('rtl', $item['direction']);
        $t->same('collections/', $item['base']);
        $t->same('collections/', $item['attributes']['xml:base']);
        $t->same([
            'dir' => 'rtl',
            'id' => 'curated-series',
            'role' => 'series curated',
            'xml:base' => 'collections/',
            'xml:lang' => 'fr',
        ], $item['structuralAttributes']);
        $t->same(['data-review' => 'series', 'review:source' => 'wp-import'], $item['customAttributes']);
        $t->same(2, $item['customAttributeCount']);
        $t->same(true, $item['hasBase']);
        $t->same('reported-not-applied-to-package-paths', $item['baseResolutionPolicy']);
        $t->same(false, $item['baseResolution']['appliesToPackagePaths']);

        $t->same('preview-samples', $childItem['id']);
        $t->same([0, 0], $childItem['path']);
        $t->same('samples/', $childItem['base']);
        $t->same(false, $childItem['hasLanguage']);
        $t->same(false, $childItem['hasDirection']);
        $t->same(['data-review' => 'samples'], $childItem['customAttributes']);

        $t->same($authoring, $summary['wordpressImport']['collectionAuthoring']);
        $t->same($authoring['items'], $summary['wordpressImport']['collectionAuthoringItems']);
        $t->same($authoring['customAttributeItems'], $summary['wordpressImport']['collectionAuthoringCustomAttributeItems']);
    },

    'summarizes OPF collection hierarchy for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithCollectionHierarchy = str_replace(
            '</spine>',
            '</spine>
  <collection id="series" role="series curated" xml:lang="en" dir="ltr">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Migration packet series</dc:title>
    </metadata>
    <link id="series-record" rel="record first" href="meta/series.json" media-type="application/ld+json"/>
    <link id="missing-review" rel="review" href="meta/missing.json" media-type="application/json"/>
    <collection id="samples" role="preview">
      <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
        <dc:title>Review samples</dc:title>
      </metadata>
      <link id="sample-one" rel="sample" href="text/chapter2.xhtml#checklist" media-type="application/xhtml+xml"/>
    </collection>
    <collection id="external-records" role="supplement">
      <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
        <dc:title>External records</dc:title>
      </metadata>
      <link id="remote-record" rel="alternate" href="https://example.invalid/series.json" media-type="application/json"/>
    </collection>
  </collection>',
            $epub3OpfXml
        );

        $seriesRecord = '{"kind":"series","source":"hierarchy"}';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithCollectionHierarchy],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="checklist">Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/series.json', 'data' => $seriesRecord],
        ]));

        $summary = $epub->summary();
        $hierarchy = $summary['collectionHierarchy'];
        $itemsByPath = $hierarchy['itemsByPath'];
        $compactCollections = $summary['compactPackageReport']['casesById']['collections'];

        $t->same(true, $hierarchy['present']);
        $t->same(3, $hierarchy['collectionCount']);
        $t->same(1, $hierarchy['rootCollectionCount']);
        $t->same(2, $hierarchy['leafCollectionCount']);
        $t->same(2, $hierarchy['maxDepth']);
        $t->same(['0', '0/0', '0/1'], $hierarchy['pathKeys']);
        $t->same([1 => 1, 2 => 2], $hierarchy['depthCounts']);
        $t->same(['curated' => 1, 'preview' => 1, 'series' => 1, 'supplement' => 1], $hierarchy['roleCounts']);
        $t->same(['preview' => 1, 'series' => 1, 'supplement' => 1], $hierarchy['primaryRoleCounts']);
        $t->same(['alternate' => 1, 'first' => 1, 'record' => 1, 'review' => 1, 'sample' => 1], $hierarchy['linkRelCounts']);
        $t->same(3, $hierarchy['localLinkCount']);
        $t->same(1, $hierarchy['externalLinkCount']);
        $t->same(1, $hierarchy['missingLinkCount']);
        $t->same(['Migration packet series', 'Review samples', 'External records'], $hierarchy['titles']);
        $t->same(['/EPUB/meta/series.json', '/EPUB/meta/missing.json', '/EPUB/text/chapter2.xhtml#checklist', 'https://example.invalid/series.json'], $hierarchy['linkTargets']);
        $t->same(2, $hierarchy['diagnosticCount']);
        $t->same(['missing-collection-link-target', 'external-collection-link-target'], array_column($hierarchy['diagnostics'], 'type'));

        $t->same(null, $itemsByPath['0']['parentPathKey']);
        $t->same('series', $itemsByPath['0']['primaryRole']);
        $t->same(['series', 'curated'], $itemsByPath['0']['roleTokens']);
        $t->same('Migration packet series', $itemsByPath['0']['title']);
        $t->same('en', $itemsByPath['0']['language']);
        $t->same('ltr', $itemsByPath['0']['direction']);
        $t->same(2, $itemsByPath['0']['childCount']);
        $t->same(false, $itemsByPath['0']['leaf']);
        $t->same(2, $itemsByPath['0']['linkCount']);
        $t->same(1, $itemsByPath['0']['missingLinkCount']);
        $t->same(['/EPUB/meta/series.json', '/EPUB/meta/missing.json'], $itemsByPath['0']['linkTargets']);
        $t->same('missing-collection-link-target', $itemsByPath['0']['diagnostics'][0]['type']);

        $t->same('0', $itemsByPath['0/0']['parentPathKey']);
        $t->same(2, $itemsByPath['0/0']['depth']);
        $t->same('samples', $itemsByPath['0/0']['id']);
        $t->same('preview', $itemsByPath['0/0']['primaryRole']);
        $t->same(true, $itemsByPath['0/0']['leaf']);
        $t->same(['/EPUB/text/chapter2.xhtml#checklist'], $itemsByPath['0/0']['linkTargets']);
        $t->same(1, $itemsByPath['0/1']['externalLinkCount']);
        $t->same('external-collection-link-target', $itemsByPath['0/1']['diagnostics'][0]['type']);

        $t->same($hierarchy, $summary['wordpressImport']['collectionHierarchy']);
        $t->same($hierarchy['items'], $summary['wordpressImport']['collectionHierarchyItems']);
        $t->same($hierarchy['diagnostics'], $summary['wordpressImport']['collectionHierarchyDiagnostics']);
        $t->same($hierarchy['pathKeys'], $compactCollections['pathKeys']);
        $t->same(2, $compactCollections['maxDepth']);
        $t->same(2, $compactCollections['leafCollectionCount']);
        $t->same($hierarchy['roleCounts'], $compactCollections['roleCounts']);
        $t->same($hierarchy['linkRelCounts'], $compactCollections['linkRelCounts']);
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

    'preserves OPF metadata link media-type parameters for compact package handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithLinkMediaTypes = str_replace(
            '</metadata>',
            '    <link id="review-record" rel="record" href="meta/review-record.json" media-type="application/ld+json; profile=&quot;review;packet&quot;; charset=UTF-8"/>
    <link id="creator-voicing" rel="voicing" href="audio/creator-name.mp3"/>
    <link id="broken-media-type" rel="record" href="meta/broken.json" media-type="application/json; charset=UTF-8; bad-param; charset=latin1"/>
  </metadata>',
            $epub3OpfXml
        );
        $opfWithLinkMediaTypes = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="review-record-item" href="meta/review-record.json" media-type="application/ld+json"/>
    <item id="creator-audio" href="audio/creator-name.mp3" media-type="audio/mpeg; codecs=mp3"/>
    <item id="broken-record" href="meta/broken.json" media-type="application/json"/>',
            $opfWithLinkMediaTypes
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithLinkMediaTypes],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/review-record.json', 'data' => '{"name":"review"}'],
            ['name' => 'EPUB/audio/creator-name.mp3', 'data' => 'MP3'],
            ['name' => 'EPUB/meta/broken.json', 'data' => '{"name":"broken"}'],
        ]));
        $links = $epub->packageLinks();
        $summary = $epub->summary();
        $report = $summary['packageLinkMediaTypes'];

        $review = $links[0];
        $t->same('application/ld+json; profile="review;packet"; charset=UTF-8', $review['declaredMediaType']);
        $t->same($review['declaredMediaType'], $review['effectiveMediaType']);
        $t->same('link', $review['mediaTypeSource']);
        $t->same('application/ld+json', $review['baseMediaType']);
        $t->same('application/ld+json; profile=review;packet; charset=utf-8', $review['normalizedMediaType']);
        $t->same(['profile', 'charset'], $review['mediaTypeParameterNames']);
        $t->same(['profile' => 'review;packet', 'charset' => 'UTF-8'], $review['mediaTypeParameterMap']);
        $t->same(true, $review['mediaTypeSyntaxValid']);
        $t->same([], $review['mediaTypeDiagnostics']);

        $creator = $links[1];
        $t->same(null, $creator['declaredMediaType']);
        $t->same('audio/mpeg; codecs=mp3', $creator['effectiveMediaType']);
        $t->same('manifest', $creator['mediaTypeSource']);
        $t->same('creator-audio', $creator['manifestId']);
        $t->same('audio/mpeg', $creator['baseMediaType']);
        $t->same(['codecs' => 'mp3'], $creator['mediaTypeParameterMap']);

        $broken = $links[2];
        $t->same('application/json; charset=UTF-8; bad-param; charset=latin1', $broken['declaredMediaType']);
        $t->same('application/json; charset=latin1', $broken['normalizedMediaType']);
        $t->same(['charset' => 'latin1'], $broken['mediaTypeParameterMap']);
        $t->same(false, $broken['mediaTypeSyntaxValid']);
        $t->same(['invalid-package-link-media-type-parameter', 'duplicate-package-link-media-type-parameter'], array_column($broken['mediaTypeDiagnostics'], 'type'));

        $t->same(true, $report['present']);
        $t->same(3, $report['linkCount']);
        $t->same(3, $report['itemCount']);
        $t->same(2, $report['declaredCount']);
        $t->same(1, $report['manifestInheritedCount']);
        $t->same(3, $report['parameterLinkCount']);
        $t->same(5, $report['parameterCount']);
        $t->same(['charset', 'codecs', 'profile'], $report['parameterNames']);
        $t->same(2, $report['diagnosticCount']);
        $t->same('review-record', $report['items'][0]['id']);
        $t->same('creator-voicing', $report['items'][1]['id']);
        $t->same('manifest', $report['items'][1]['mediaTypeSource']);
        $t->same('broken-media-type', $report['parameterItems'][2]['id']);
        $t->same(['invalid-package-link-media-type-parameter', 'duplicate-package-link-media-type-parameter'], array_column($report['diagnostics'], 'type'));
        $t->same($report, $summary['metadata']['linkMediaTypes']);
        $t->same($report, $summary['wordpressImport']['packageLinkMediaTypes']);
        $t->same($report['parameterItems'], $summary['wordpressImport']['packageLinkMediaTypeParameterItems']);
        $t->same($report['parameterNames'], $summary['wordpressImport']['packageLinkMediaTypeParameterNames']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['packageLinkMediaTypeDiagnostics']);
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

    'summarizes EPUB link href suffixes across package link sources' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
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
    <link id="collection-local" rel="first" href="text/chapter1.xhtml?view=review#install" media-type="application/xhtml+xml"/>
    <link id="collection-missing" rel="review" href="meta/missing-record.json#missing" media-type="application/json"/>
    <collection id="external-records" role="supplement">
      <link id="collection-remote" rel="alternate" href="https://example.invalid/review.json?source=collection#packet" media-type="application/json"/>
    </collection>
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
        $report = $summary['linkHrefSuffixes'];

        $t->same(true, $report['present']);
        $t->same(5, $report['itemCount']);
        $t->same(1, $report['containerLinkCount']);
        $t->same(1, $report['packageLinkCount']);
        $t->same(3, $report['collectionLinkCount']);
        $t->same(4, $report['queryCount']);
        $t->same(5, $report['fragmentCount']);
        $t->same(3, $report['localTargetCount']);
        $t->same(1, $report['externalTargetCount']);
        $t->same(1, $report['missingTargetCount']);
        $t->same(['container-link' => 1, 'package-link' => 1, 'collection-link' => 3], $report['sourceCounts']);
        $t->same(['profile=ocf', 'edition=review', 'view=review', 'source=collection'], $report['queryValues']);
        $t->same(['record', 'package', 'install', 'missing', 'packet'], $report['fragmentValues']);
        $t->same([
            '/EPUB/meta/container-record.json?profile=ocf#record',
            '/EPUB/meta/package-record.json?edition=review#package',
            '/EPUB/text/chapter1.xhtml?view=review#install',
            '/EPUB/meta/missing-record.json#missing',
            'https://example.invalid/review.json?source=collection#packet',
        ], $report['targets']);
        $t->same([
            '/EPUB/meta/container-record.json',
            '/EPUB/meta/package-record.json',
            '/EPUB/text/chapter1.xhtml',
            '/EPUB/meta/missing-record.json',
        ], $report['partNames']);

        $t->same('container-link', $report['items'][0]['source']);
        $t->same('container-suffix', $report['items'][0]['id']);
        $t->same('package-link', $report['items'][1]['source']);
        $t->same('package-suffix', $report['items'][1]['id']);
        $t->same('collection-link', $report['items'][2]['source']);
        $t->same([0], $report['items'][2]['collectionPath']);
        $t->same('review-links', $report['items'][2]['collectionId']);
        $t->same(true, $report['items'][2]['exists']);
        $t->same('collection-missing', $report['items'][3]['id']);
        $t->same(false, $report['items'][3]['exists']);
        $t->same('missing-collection-link-target', $report['items'][3]['diagnostics'][0]['type']);
        $t->same('collection-remote', $report['items'][4]['id']);
        $t->same([0, 0], $report['items'][4]['collectionPath']);
        $t->same('external-records', $report['items'][4]['collectionId']);
        $t->same('supplement', $report['items'][4]['collectionRole']);
        $t->same(true, $report['items'][4]['external']);
        $t->same('collection-remote', $report['itemsBySource']['collection-link'][2]['id']);
        $t->same($report, $summary['wordpressImport']['linkHrefSuffixes']);
        $t->same($report['items'], $summary['wordpressImport']['linkHrefSuffixItems']);
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

    'reports OPF package prefix diagnostics in package validation handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithPrefixDiagnostics = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="review: https://example.invalid/review-vocab# review: https://example.invalid/review-vocab-2# bad-prefix">',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithPrefixDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $validation = $epub->validationReport();
        $summary = $epub->summary();
        $metadataValidation = $validation['metadata'];

        $t->same(false, $validation['valid']);
        $t->same(2, $validation['diagnosticCount']);
        $t->same(['duplicate-package-prefix-declaration', 'invalid-package-prefix-declaration'], array_column($validation['diagnostics'], 'type'));
        $t->same(false, $metadataValidation['valid']);
        $t->same(true, $metadataValidation['titlePresent']);
        $t->same(true, $metadataValidation['identifierPresent']);
        $t->same(true, $metadataValidation['languagePresent']);
        $t->same(true, $metadataValidation['modifiedPresent']);
        $t->same(false, $metadataValidation['prefixValid']);
        $t->same(2, $metadataValidation['prefixDiagnosticCount']);
        $t->same(['duplicate-package-prefix-declaration', 'invalid-package-prefix-declaration'], array_column($metadataValidation['prefixDiagnostics'], 'type'));
        $t->same('review', $metadataValidation['prefixDiagnostics'][0]['prefix']);
        $t->same('https://example.invalid/review-vocab#', $metadataValidation['prefixDiagnostics'][0]['previousIri']);
        $t->same('https://example.invalid/review-vocab-2#', $metadataValidation['prefixDiagnostics'][0]['iri']);
        $t->contains('bad-prefix', $metadataValidation['prefixDiagnostics'][1]['value']);
        $t->same($metadata['prefixDiagnostics'], $metadataValidation['prefixDiagnostics']);
        $t->same($validation, $summary['validation']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
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

    'summarizes OPF collection role vocabulary tokens for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithCollectionRoles = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/">',
            $epub3OpfXml
        );
        $opfWithCollectionRoles = str_replace(
            '</spine>',
            '</spine>
  <collection id="series-vocab" role="series schema:isPartOf https://example.invalid/collection-role#review bad/role series unknown:missing">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Collection role vocabulary review</dc:title></metadata>
    <collection id="preview-vocab" role="preview schema:hasPart">
      <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Preview role vocabulary review</dc:title></metadata>
    </collection>
  </collection>',
            $opfWithCollectionRoles
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithCollectionRoles],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $summary = $epub->summary();
        $series = $epub->collections()[0];
        $preview = $series['children'][0];
        $seriesVocabulary = $series['roleVocabulary'];
        $previewVocabulary = $preview['roleVocabulary'];

        $t->same('series', $series['primaryRole']);
        $t->same(['series', 'schema:isPartOf', 'https://example.invalid/collection-role#review', 'bad/role', 'series', 'unknown:missing'], $series['roleTokens']);
        $t->same(6, $seriesVocabulary['count']);
        $t->same(5, $seriesVocabulary['validCount']);
        $t->same(1, $seriesVocabulary['invalidCount']);
        $t->same(1, $seriesVocabulary['resolvedCount']);
        $t->same(1, $seriesVocabulary['absoluteUrlCount']);
        $t->same(1, $seriesVocabulary['duplicateCount']);
        $t->same('prefixed-nmtoken', $seriesVocabulary['items'][1]['kind']);
        $t->same('schema', $seriesVocabulary['items'][1]['prefix']);
        $t->same('isPartOf', $seriesVocabulary['items'][1]['localName']);
        $t->same('https://schema.org/isPartOf', $seriesVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $seriesVocabulary['items'][2]['kind']);
        $t->same('invalid-collection-role-token', $seriesVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-collection-role-token', $seriesVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-collection-role-prefix', $seriesVocabulary['items'][5]['diagnostics'][0]['type']);
        $t->same(3, $series['diagnosticCount']);
        $t->same(['invalid-collection-role-token', 'duplicate-collection-role-token', 'unknown-collection-role-prefix'], array_column($series['diagnostics'], 'type'));

        $t->same('preview', $preview['primaryRole']);
        $t->same(2, $previewVocabulary['count']);
        $t->same(1, $previewVocabulary['resolvedCount']);
        $t->same('https://schema.org/hasPart', $previewVocabulary['items'][1]['iri']);

        $vocabulary = $summary['collectionRoleVocabulary'];
        $t->same(true, $vocabulary['present']);
        $t->same(2, $vocabulary['collectionCount']);
        $t->same(8, $vocabulary['roleTokenCount']);
        $t->same(7, $vocabulary['validTokenCount']);
        $t->same(1, $vocabulary['invalidTokenCount']);
        $t->same(2, $vocabulary['resolvedTokenCount']);
        $t->same(1, $vocabulary['absoluteUrlTokenCount']);
        $t->same(1, $vocabulary['duplicateTokenCount']);
        $t->same(2, $vocabulary['roles']['series']);
        $t->same(1, $vocabulary['roles']['preview']);
        $t->same(1, $vocabulary['roles']['schema:isPartOf']);
        $t->same(1, $vocabulary['roles']['schema:hasPart']);
        $t->same(3, $vocabulary['diagnosticCount']);
        $t->same([0], $vocabulary['diagnostics'][0]['collectionPath']);
        $t->same('series-vocab', $vocabulary['diagnostics'][0]['collectionId']);
        $t->same(['invalid-collection-role-token', 'duplicate-collection-role-token', 'unknown-collection-role-prefix'], array_column($vocabulary['diagnostics'], 'type'));
        $t->same($vocabulary, $summary['wordpressImport']['collectionRoleVocabulary']);
        $t->same($vocabulary['diagnostics'], $summary['wordpressImport']['collectionRoleVocabularyDiagnostics']);
        $t->same(['invalid-collection-role-token', 'duplicate-collection-role-token', 'unknown-collection-role-prefix'], array_column($summary['wordpressImport']['collectionDiagnostics'], 'type'));
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

    'classifies media-overlay remote text and missing audio targets for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
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
      <par id="local">
        <text src="../text/chapter1.xhtml#intro"/>
        <audio src="../audio/chapter.mp3" clipBegin="0s" clipEnd="2s"/>
      </par>
      <par id="remote-text-missing-audio">
        <text src="https://publisher.example.invalid/transcripts/chapter1.xhtml#caption"/>
        <audio src="../audio/missing.mp3" clipBegin="2s" clipEnd="5s"/>
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
        $local = $chapter['items'][0];
        $remote = $chapter['items'][1];

        $t->same(true, $local['textExists']);
        $t->same(false, $local['textExternal']);
        $t->same('/EPUB/text/chapter1.xhtml#intro', $local['textTarget']);
        $t->same(true, $local['audioExists']);
        $t->same(false, $local['audioExternal']);
        $t->same('/EPUB/audio/chapter.mp3', $local['audioTarget']);
        $t->same([], $local['textDiagnostics']);
        $t->same([], $local['audioDiagnostics']);

        $t->same('https://publisher.example.invalid/transcripts/chapter1.xhtml#caption', $remote['textTarget']);
        $t->same(null, $remote['textPartName']);
        $t->same(false, $remote['textExists']);
        $t->same(true, $remote['textExternal']);
        $t->same(true, $remote['textHrefHasFragment']);
        $t->same('caption', $remote['textHrefFragment']);
        $t->same('external-media-overlay-text-reference', $remote['textDiagnostics'][0]['type']);
        $t->same('/EPUB/audio/missing.mp3', $remote['audioTarget']);
        $t->same('/EPUB/audio/missing.mp3', $remote['audioPartName']);
        $t->same(false, $remote['audioExists']);
        $t->same(false, $remote['audioExternal']);
        $t->same('missing-media-overlay-audio-reference', $remote['audioDiagnostics'][0]['type']);
        $t->same(['external-media-overlay-text-reference', 'missing-media-overlay-audio-reference'], array_column($remote['diagnostics'], 'type'));
        $t->same(3.0, $remote['clipDurationSeconds']);

        $t->same(['/EPUB/text/chapter1.xhtml#intro'], $chapter['textLocalTargets']);
        $t->same(['https://publisher.example.invalid/transcripts/chapter1.xhtml#caption'], $chapter['textExternalTargets']);
        $t->same([], $chapter['textMissingTargets']);
        $t->same(['/EPUB/audio/chapter.mp3'], $chapter['audioLocalTargets']);
        $t->same([], $chapter['audioExternalTargets']);
        $t->same(['/EPUB/audio/missing.mp3'], $chapter['audioMissingTargets']);
        $t->same(1, $chapter['textLocalTargetCount']);
        $t->same(1, $chapter['textExternalTargetCount']);
        $t->same(0, $chapter['textMissingTargetCount']);
        $t->same(1, $chapter['audioLocalTargetCount']);
        $t->same(0, $chapter['audioExternalTargetCount']);
        $t->same(1, $chapter['audioMissingTargetCount']);

        $t->same($chapter['textLocalTargets'], $overlays['textLocalTargets']);
        $t->same($chapter['textExternalTargets'], $overlays['textExternalTargets']);
        $t->same($chapter['textMissingTargets'], $overlays['textMissingTargets']);
        $t->same($chapter['audioLocalTargets'], $overlays['audioLocalTargets']);
        $t->same($chapter['audioExternalTargets'], $overlays['audioExternalTargets']);
        $t->same($chapter['audioMissingTargets'], $overlays['audioMissingTargets']);
        $t->same(['external-media-overlay-text-reference', 'missing-media-overlay-audio-reference'], array_column($overlays['diagnostics'], 'type'));
        $t->same($overlays['textLocalTargets'], $summary['wordpressImport']['mediaOverlayTextLocalTargets']);
        $t->same($overlays['textExternalTargets'], $summary['wordpressImport']['mediaOverlayTextExternalTargets']);
        $t->same($overlays['textMissingTargets'], $summary['wordpressImport']['mediaOverlayTextMissingTargets']);
        $t->same($overlays['audioLocalTargets'], $summary['wordpressImport']['mediaOverlayAudioLocalTargets']);
        $t->same($overlays['audioExternalTargets'], $summary['wordpressImport']['mediaOverlayAudioExternalTargets']);
        $t->same($overlays['audioMissingTargets'], $summary['wordpressImport']['mediaOverlayAudioMissingTargets']);
        $t->same($overlays['diagnostics'], $summary['wordpressImport']['mediaOverlayDiagnostics']);
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

    'preserves OPF manifest fallback roles in ZIP package inventory' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithFallbackInventory = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="custom-ok" href="widgets/custom-ok.bin" media-type="application/x-review-widget" fallback="ok-fallback" fallback-style="widget-style"/>
    <item id="ok-fallback" href="text/ok-fallback.xhtml" media-type="application/xhtml+xml"/>
    <item id="poster-heic" href="images/poster.heic" media-type="image/heic" fallback="cover"/>
    <item id="orphan-widget" href="widgets/orphan.bin" media-type="application/x-review-widget"/>
    <item id="widget-style" href="styles/widget.css" media-type="text/css"/>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithFallbackInventory],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/widgets/custom-ok.bin', 'data' => 'CUSTOM-OK'],
            ['name' => 'EPUB/text/ok-fallback.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Fallback review content.</p></body></html>'],
            ['name' => 'EPUB/styles/widget.css', 'data' => 'body { color: #123456; }'],
            ['name' => 'EPUB/images/poster.heic', 'data' => 'HEIC'],
            ['name' => 'EPUB/widgets/orphan.bin', 'data' => 'ORPHAN'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $byPath = $inventory['byPackagePath'];

        $t->same($inventory, $summary['wordpressImport']['packageInventory']);
        $t->same([
            '/EPUB/widgets/custom-ok.bin',
            '/EPUB/text/ok-fallback.xhtml',
            '/EPUB/styles/widget.css',
            '/EPUB/images/poster.heic',
            '/EPUB/widgets/orphan.bin',
            '/EPUB/images/cover.png',
        ], $inventory['manifestFallbackPartNames']);
        $t->same(['/EPUB/widgets/custom-ok.bin', '/EPUB/images/poster.heic'], $inventory['manifestFallbackSourcePartNames']);
        $t->same(['/EPUB/widgets/custom-ok.bin'], $inventory['manifestFallbackStyleSourcePartNames']);
        $t->same(['/EPUB/widgets/orphan.bin'], $inventory['manifestFallbackMissingSourcePartNames']);
        $t->same(['/EPUB/text/ok-fallback.xhtml', '/EPUB/images/cover.png'], $inventory['manifestFallbackTerminalPartNames']);
        $t->same(['/EPUB/styles/widget.css'], $inventory['manifestFallbackStyleTerminalPartNames']);

        $source = $byPath['EPUB/widgets/custom-ok.bin'];
        $t->same(['manifest-fallback-source', 'manifest-fallback-style-source'], $source['manifestFallbackRoles']);
        $t->same(['custom-ok'], $source['manifestFallbackSourceIds']);
        $t->same(['custom-ok'], $source['manifestFallbackStyleSourceIds']);
        $t->true(in_array('manifest-fallback-source', $source['roles'], true), 'fallback source role missing');
        $t->true(in_array('manifest-fallback-style-source', $source['roles'], true), 'fallback-style source role missing');

        $fallback = $byPath['EPUB/text/ok-fallback.xhtml'];
        $t->same(['manifest-fallback-chain-item', 'manifest-fallback-terminal'], $fallback['manifestFallbackRoles']);
        $t->same(['custom-ok'], $fallback['manifestFallbackChainForIds']);
        $t->same(['custom-ok'], $fallback['manifestFallbackTerminalForIds']);
        $t->true(in_array('manifest-fallback-terminal', $fallback['roles'], true), 'fallback terminal role missing');

        $style = $byPath['EPUB/styles/widget.css'];
        $t->same(['manifest-fallback-style-chain-item', 'manifest-fallback-style-terminal'], $style['manifestFallbackRoles']);
        $t->same(['custom-ok'], $style['manifestFallbackStyleChainForIds']);
        $t->same(['custom-ok'], $style['manifestFallbackStyleTerminalForIds']);

        $poster = $byPath['EPUB/images/poster.heic'];
        $t->same(['manifest-fallback-source'], $poster['manifestFallbackRoles']);
        $t->same(['poster-heic'], $poster['manifestFallbackSourceIds']);

        $orphan = $byPath['EPUB/widgets/orphan.bin'];
        $t->same(['manifest-fallback-missing-source'], $orphan['manifestFallbackRoles']);
        $t->same(['orphan-widget'], $orphan['manifestFallbackMissingSourceIds']);

        $cover = $byPath['EPUB/images/cover.png'];
        $t->same(['manifest-fallback-chain-item', 'manifest-fallback-terminal'], $cover['manifestFallbackRoles']);
        $t->same(['poster-heic'], $cover['manifestFallbackTerminalForIds']);
        $t->same(2, $inventory['roleCounts']['manifest-fallback-source']);
        $t->same(1, $inventory['roleCounts']['manifest-fallback-style-source']);
        $t->same(1, $inventory['roleCounts']['manifest-fallback-missing-source']);
        $t->same(2, $inventory['roleCounts']['manifest-fallback-terminal']);
        $t->same(1, $inventory['roleCounts']['manifest-fallback-style-terminal']);
    },

    'reports OPF manifest fallback chains whose terminal package part is missing' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithMissingFallbackPart = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:missing-fallback-part-review</dc:identifier>
    <dc:title>Missing fallback package part</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-12T02:01:23Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="review-widget" href="widgets/review.bin" media-type="application/x-review-widget" fallback="missing-fallback"/>
    <item id="missing-fallback" href="text/missing-fallback.xhtml" media-type="application/xhtml+xml"/>
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

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMissingFallbackPart],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
            ['name' => 'EPUB/widgets/review.bin', 'data' => 'REVIEW'],
        ]));
        $fallbacks = $epub->manifestFallbacks();
        $reviewWidget = $fallbacks['itemsById']['review-widget'];
        $fallbackChainItem = $reviewWidget['fallbackChain'][0];
        $validation = $epub->validationReport();
        $summary = $epub->summary();

        $t->same(true, $fallbacks['present']);
        $t->same(1, $fallbacks['itemCount']);
        $t->same(1, $fallbacks['fallbackCount']);
        $t->same(1, $fallbacks['resolvedFallbackCount']);
        $t->same(0, $fallbacks['usableFallbackCount']);
        $t->same(1, $fallbacks['fallbackDiagnosticCount']);
        $t->same(['missing-manifest-fallback-package-part'], array_column($fallbacks['diagnostics'], 'type'));

        $t->same('missing-fallback', $reviewWidget['fallbackId']);
        $t->same(true, $reviewWidget['fallbackResolved']);
        $t->same(false, $reviewWidget['fallbackUsable']);
        $t->same('missing-fallback', $reviewWidget['fallbackTerminalId']);
        $t->same('/EPUB/text/missing-fallback.xhtml', $reviewWidget['fallbackTerminalPartName']);
        $t->same('application/xhtml+xml', $reviewWidget['fallbackTerminalMediaType']);
        $t->same(true, $reviewWidget['fallbackTerminalCoreMediaType']);
        $t->same(true, $reviewWidget['fallbackTerminalEpubContentDocument']);
        $t->same(false, $fallbackChainItem['exists']);
        $t->same(false, $fallbackChainItem['canExposeBytes']);
        $t->same('missing-manifest-fallback-package-part', $reviewWidget['fallbackDiagnostics'][0]['type']);
        $t->same('/EPUB/text/missing-fallback.xhtml', $reviewWidget['fallbackDiagnostics'][0]['terminalPartName']);

        $t->same(false, $validation['valid']);
        $t->same(['missing-manifest-href-target'], array_column($validation['manifest']['diagnostics'], 'type'));
        $t->same('missing-fallback', $validation['manifest']['missingItems'][0]['id']);
        $t->same($fallbacks, $summary['wordpressImport']['manifestFallbacks']);
        $t->same($fallbacks['diagnostics'], $summary['wordpressImport']['manifestFallbackDiagnostics']);
    },

    'summarizes OPF manifest resource properties for compact package preflight' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml, $buildZipPackage): void {
        $chapter1Xhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><math/><svg/></body></html>';
        $chapter2Xhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><script>review()</script></body></html>';
        $reviewScriptBytes = 'SCRIPTED-REVIEW';
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
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml" properties="scripted switch"/>
    <item id="review-script" href="scripts/review.bin" media-type="application/octet-stream" properties="scripted"/>',
            $opfWithResourceProperties
        );

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithResourceProperties],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => $chapter1Xhtml],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => $chapter2Xhtml],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/scripts/review.bin', 'data' => $reviewScriptBytes, 'method' => 12],
        ]));

        $report = $epub->resourceProperties();
        $summary = $epub->summary();

        $t->same(1, $report['summary']['navCount']);
        $t->same(1, $report['summary']['coverImageCount']);
        $t->same(1, $report['summary']['mathmlCount']);
        $t->same(1, $report['summary']['svgCount']);
        $t->same(1, $report['summary']['remoteResourcesCount']);
        $t->same(2, $report['summary']['scriptedCount']);
        $t->same(1, $report['summary']['switchCount']);
        $t->same(3, $report['summary']['reviewRequiredCount']);
        $t->same(4, $report['summary']['exposableItemCount']);
        $t->same(1, $report['summary']['blockedByteExposureCount']);
        $t->same(0, $report['summary']['missingItemCount']);
        $t->same(0, $report['summary']['externalItemCount']);
        $t->same(0, $report['summary']['encryptedItemCount']);
        $t->same(1, $report['summary']['unsupportedCompressionItemCount']);
        $t->same([
            'manifest-resource-bytes-exposable' => 4,
            'unsupported-compression-metadata-only' => 1,
        ], $report['summary']['byteExposurePolicyCounts']);

        $t->same('chapter1', $report['itemsByProperty']['mathml'][0]['id']);
        $t->same('chapter1', $report['itemsByProperty']['remote-resources'][0]['id']);
        $t->same('chapter2', $report['itemsByProperty']['scripted'][0]['id']);
        $t->same('review-script', $report['itemsByProperty']['scripted'][1]['id']);
        $t->same('/EPUB/text/chapter1.xhtml', $report['itemsById']['chapter1']['partName']);
        $t->same(['mathml', 'svg', 'remote-resources'], $report['itemsById']['chapter1']['reviewFlags']);
        $t->same(['scripted', 'switch'], $report['itemsById']['chapter2']['reviewFlags']);
        $t->same(true, $report['itemsById']['chapter1']['reviewRequired']);
        $t->same(true, $report['itemsById']['chapter1']['exists']);
        $t->same(false, $report['itemsById']['chapter1']['external']);
        $t->same(false, $report['itemsById']['chapter1']['encrypted']);
        $t->same(true, $report['itemsById']['chapter1']['canExposeBytes']);
        $t->same('manifest-resource-bytes-exposable', $report['itemsById']['chapter1']['byteExposurePolicy']);
        $t->same(strlen($chapter1Xhtml), $report['itemsById']['chapter1']['byteLength']);
        $t->same(strlen(gzdeflate($chapter1Xhtml)), $report['itemsById']['chapter1']['compressedByteLength']);
        $t->same(8, $report['itemsById']['chapter1']['compressionMethod']);
        $t->same('deflated', $report['itemsById']['chapter1']['compressionMethodName']);
        $t->same(true, $report['itemsById']['chapter1']['compressionSupported']);
        $t->same(hash('crc32b', $chapter1Xhtml), $report['itemsById']['chapter1']['crc32']);
        $t->same('chapter2', $report['reviewItems'][1]['id']);
        $t->same('review-script', $report['reviewItems'][2]['id']);
        $t->same('review-script', $report['blockedByteExposureItems'][0]['id']);
        $t->same('review-script', $report['unsupportedCompressionItems'][0]['id']);
        $t->same(false, $report['itemsById']['review-script']['canExposeBytes']);
        $t->same('unsupported-compression-metadata-only', $report['itemsById']['review-script']['byteExposurePolicy']);
        $t->same(strlen($reviewScriptBytes), $report['itemsById']['review-script']['byteLength']);
        $t->same(strlen($reviewScriptBytes), $report['itemsById']['review-script']['compressedByteLength']);
        $t->same(12, $report['itemsById']['review-script']['compressionMethod']);
        $t->same('unsupported', $report['itemsById']['review-script']['compressionMethodName']);
        $t->same(false, $report['itemsById']['review-script']['compressionSupported']);
        $t->same(hash('crc32b', $reviewScriptBytes), $report['itemsById']['review-script']['crc32']);

        $vocabulary = $report['propertyVocabulary'];
        $t->same(true, $vocabulary['present']);
        $t->same(5, $vocabulary['itemCount']);
        $t->same(12, $vocabulary['propertyTokenCount']);
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

    'reports duplicate OPF manifest resource property tokens for compact package review' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithDuplicateProperties = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" prefix="review: https://example.invalid/epub-review#">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:duplicate-manifest-properties</dc:identifier>
    <dc:title>Duplicate Manifest Properties</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T09:59:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" properties="mathml review:flag review:flag unknown:flag bad/token"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithDuplicateProperties],
            ['name' => 'EPUB/nav.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="text/chapter.xhtml">Chapter</a></li></ol></nav></body></html>'],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><math><mi>x</mi></math></body></html>'],
        ]));

        $report = $epub->resourceProperties();
        $summary = $epub->summary();
        $vocabulary = $report['propertyVocabulary'];
        $navVocabulary = $report['itemsById']['nav']['propertyVocabulary'];
        $chapterVocabulary = $report['itemsById']['chapter']['propertyVocabulary'];

        $t->same(true, $vocabulary['present']);
        $t->same(2, $vocabulary['itemCount']);
        $t->same(7, $vocabulary['propertyTokenCount']);
        $t->same(3, $vocabulary['prefixedPropertyCount']);
        $t->same(2, $vocabulary['resolvedPropertyCount']);
        $t->same(1, $vocabulary['unresolvedPropertyCount']);
        $t->same(2, $vocabulary['duplicatePropertyCount']);
        $t->same(4, $vocabulary['diagnosticCount']);
        $t->same([
            'duplicate-manifest-property-token',
            'duplicate-manifest-property-token',
            'unknown-manifest-property-prefix',
            'invalid-manifest-property-token',
        ], array_column($vocabulary['diagnostics'], 'type'));

        $t->same(1, $navVocabulary['duplicateCount']);
        $t->same(true, $navVocabulary['items'][1]['duplicate']);
        $t->same(0, $navVocabulary['items'][1]['previousIndex']);
        $t->same('duplicate-manifest-property-token', $navVocabulary['items'][1]['vocabulary']['diagnostics'][0]['type']);
        $t->same(1, $chapterVocabulary['duplicateCount']);
        $t->same(true, $chapterVocabulary['items'][2]['duplicate']);
        $t->same(1, $chapterVocabulary['items'][2]['previousIndex']);
        $t->same('https://example.invalid/epub-review#flag', $chapterVocabulary['items'][1]['vocabulary']['iri']);
        $t->same('duplicate-manifest-property-token', $chapterVocabulary['items'][2]['vocabulary']['diagnostics'][0]['type']);
        $t->same('unknown-manifest-property-prefix', $chapterVocabulary['items'][3]['vocabulary']['diagnostics'][0]['type']);
        $t->same('invalid-manifest-property-token', $chapterVocabulary['items'][4]['vocabulary']['diagnostics'][0]['type']);
        $t->same(['review:flag'], $vocabulary['byPrefix']['review']['properties']);
        $t->same(['chapter'], $vocabulary['byPrefix']['review']['manifestIds']);
        $t->same($report, $summary['resourceProperties']);
        $t->same($report, $summary['wordpressImport']['resourceProperties']);
        $t->same($vocabulary['diagnostics'], $summary['wordpressImport']['resourcePropertyDiagnostics']);
    },

    'summarizes OPF manifest resource kind matrix for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3NavXml): void {
        $opfWithResourceKinds = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:resource-kind-matrix</dc:identifier>
    <dc:title>Resource Kind Matrix</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T00:40:40Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="style" href="styles/book.css" media-type="text/css"/>
    <item id="diagram" href="images/diagram.svg" media-type="image/svg+xml"/>
    <item id="font" href="fonts/source.woff2" media-type="font/woff2"/>
    <item id="audio" href="audio/narration.mp3" media-type="audio/mpeg"/>
    <item id="video" href="video/clip.mp4" media-type="video/mp4"/>
    <item id="script" href="scripts/review.js" media-type="application/javascript"/>
    <item id="packet" href="data/review.bin" media-type="application/octet-stream"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithResourceKinds],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { color: black; }'],
            ['name' => 'EPUB/images/diagram.svg', 'data' => '<svg xmlns="http://www.w3.org/2000/svg"><title>Diagram</title></svg>'],
            ['name' => 'EPUB/fonts/source.woff2', 'data' => 'WOFF2'],
            ['name' => 'EPUB/audio/narration.mp3', 'data' => 'MP3'],
            ['name' => 'EPUB/video/clip.mp4', 'data' => 'MP4'],
            ['name' => 'EPUB/scripts/review.js', 'data' => 'window.review = true;'],
            ['name' => 'EPUB/data/review.bin', 'data' => 'REVIEW'],
        ]));

        $report = $epub->manifestResourceKinds();
        $summary = $epub->summary();
        $expectedKindCounts = [
            'asset' => 1,
            'audio' => 1,
            'cover-image' => 1,
            'font' => 1,
            'navigation' => 1,
            'script' => 1,
            'style' => 1,
            'svg' => 1,
            'video' => 1,
            'xhtml' => 1,
        ];
        $expectedKindsById = [
            'nav' => 'navigation',
            'cover' => 'cover-image',
            'chapter' => 'xhtml',
            'style' => 'style',
            'diagram' => 'svg',
            'font' => 'font',
            'audio' => 'audio',
            'video' => 'video',
            'script' => 'script',
            'packet' => 'asset',
        ];

        $t->same(true, $report['present']);
        $t->same(10, $report['itemCount']);
        $t->same(10, $report['kindCount']);
        $t->same(array_keys($expectedKindCounts), $report['kinds']);
        $t->same($expectedKindCounts, $report['kindCounts']);
        $t->same($expectedKindCounts, $report['summary']['kindCounts']);
        $t->same($expectedKindCounts, $summary['packageInventory']['resourceKindCounts']);
        $t->same(10, $report['existingItemCount']);
        $t->same(10, $report['exposableItemCount']);
        $t->same(0, $report['missingItemCount']);
        $t->same(0, $report['externalItemCount']);
        $t->same(2, $report['mediaTypeBaseCounts']['application/xhtml+xml']);
        $t->same(1, $report['mediaTypeBaseCounts']['application/javascript']);
        $t->same(1, $report['mediaTypeBaseCounts']['video/mp4']);
        $t->same(['/EPUB/nav.xhtml'], $report['kindPartNames']['navigation']);
        $t->same(['/EPUB/data/review.bin'], $report['kindPartNames']['asset']);

        foreach ($expectedKindsById as $id => $kind) {
            $t->same($kind, $report['itemsById'][$id]['resourceKind']);
            $t->same($id, $report['itemsByKind'][$kind][0]['id']);
        }

        $t->same('cover-image', $report['itemsById']['cover']['properties'][0]);
        $t->same('/EPUB/fonts/source.woff2', $report['itemsById']['font']['partName']);
        $t->same('application/octet-stream', $report['itemsById']['packet']['mediaTypeBase']);
        $t->same($report, $summary['manifestResourceKinds']);
        $t->same($report, $summary['wordpressImport']['manifestResourceKinds']);
        $t->same($report['summary'], $summary['wordpressImport']['manifestResourceKindSummary']);
        $t->same($report['items'], $summary['wordpressImport']['manifestResourceKindItems']);
        $t->same($expectedKindCounts, $summary['wordpressImport']['manifestResourceKindCounts']);
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

    'preserves OCF encryption XML provenance for compact package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container"
    xmlns:xenc="http://www.w3.org/2001/04/xmlenc#"
    xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
    xmlns:review="https://example.invalid/epub-encryption-review">
  <xenc:EncryptedData Id="ed-cover" Type="http://www.w3.org/2001/04/xmlenc#Element" MimeType="image/png" Encoding="http://www.w3.org/2000/09/xmldsig#base64" review:source="wp-import">
    <xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes128-cbc" review:strength="legacy"/>
    <ds:KeyInfo Id="key-cover">
      <ds:KeyName>cover-key</ds:KeyName>
      <ds:RetrievalMethod URI="keys.xml#cover"/>
      <ds:X509Data/>
    </ds:KeyInfo>
    <xenc:CipherData>
      <xenc:CipherReference URI="EPUB/images/cover.png" Id="cipher-cover">
        <xenc:Transforms>
          <xenc:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#base64"/>
          <xenc:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
        </xenc:Transforms>
      </xenc:CipherReference>
    </xenc:CipherData>
  </xenc:EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $encryption = $epub->encryption();
        $item = $encryption['items'][0];
        $summary = $epub->summary();
        $exposure = $summary['wordpressImport']['encryptedResourceExposure'];
        $exposureItem = $exposure['items'][0];

        $t->same('ed-cover', $item['encryptedDataId']);
        $t->same('http://www.w3.org/2001/04/xmlenc#Element', $item['encryptedDataType']);
        $t->same('image/png', $item['encryptedDataMimeType']);
        $t->same('http://www.w3.org/2000/09/xmldsig#base64', $item['encryptedDataEncoding']);
        $t->same('wp-import', $item['encryptedDataAttributes']['review:source']);
        $t->same('http://www.w3.org/2001/04/xmlenc#aes128-cbc', $item['algorithm']);
        $t->same('legacy', $item['encryptionMethodAttributes']['review:strength']);
        $t->same('cipher-cover', $item['cipherReferenceAttributes']['Id']);
        $t->same(2, $item['cipherReferenceTransformCount']);
        $t->same([
            'http://www.w3.org/2000/09/xmldsig#base64',
            'http://www.w3.org/2001/10/xml-exc-c14n#',
        ], $item['cipherReferenceTransformAlgorithms']);
        $t->same('http://www.w3.org/2000/09/xmldsig#base64', $item['cipherReferenceTransforms'][0]['algorithm']);
        $t->same('http://www.w3.org/2001/10/xml-exc-c14n#', $item['cipherReferenceTransforms'][1]['attributes']['Algorithm']);
        $t->same(true, $item['keyInfo']['present']);
        $t->same('key-cover', $item['keyInfo']['attributes']['Id']);
        $t->same(['ds:KeyName', 'ds:RetrievalMethod', 'ds:X509Data'], $item['keyInfo']['childElementNames']);
        $t->same(1, $item['keyInfo']['keyNameCount']);
        $t->same(['cover-key'], $item['keyInfo']['keyNames']);
        $t->same(1, $item['keyInfo']['retrievalMethodCount']);
        $t->same(1, $item['keyInfo']['x509DataCount']);

        $t->same($item['keyInfo'], $epub->manifestItem('cover')['encryption']['items'][0]['keyInfo']);
        $t->same(2, $exposure['cipherReferenceTransformCount']);
        $t->same([
            'http://www.w3.org/2000/09/xmldsig#base64',
            'http://www.w3.org/2001/10/xml-exc-c14n#',
        ], $exposure['cipherReferenceTransformAlgorithms']);
        $t->same([
            'http://www.w3.org/2000/09/xmldsig#base64' => 1,
            'http://www.w3.org/2001/10/xml-exc-c14n#' => 1,
        ], $exposure['cipherReferenceTransformAlgorithmCounts']);
        $t->same(1, $exposure['keyInfoCount']);
        $t->same(['cover-key'], $exposure['keyNames']);
        $t->same('ed-cover', $exposureItem['encryptedDataId']);
        $t->same($item['cipherReferenceTransforms'], $exposureItem['cipherReferenceTransforms']);
        $t->same($item['keyInfo'], $exposureItem['keyInfo']);
        $t->same($encryption, $summary['encryption']);
        $t->same($encryption, $summary['wordpressImport']['encryption']);
    },

    'summarizes compact EPUB package matrix across metadata navigation sidecars overlays and encryption' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $containerMetadataXml = <<<'XML'
<metadata xmlns="http://www.idpf.org/2013/metadata">
  <link id="container-record" rel="record" href="EPUB/meta/container.json" media-type="application/json"/>
</metadata>
XML;
        $opfWithMatrix = str_replace(
            '<dc:title>WordPress Migration Guide</dc:title>',
            '<dc:title id="title-main">WordPress Migration Guide</dc:title>',
            $epub3OpfXml
        );
        $opfWithMatrix = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta refines="#title-main" property="title-type">main</meta>
    <meta refines="#creator" property="role" scheme="marc:relators">aut</meta>
    <link id="creator-record" rel="record" refines="#creator" href="meta/creator.json" media-type="application/json"/>',
            $opfWithMatrix
        );
        $opfWithMatrix = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter"/>',
            $opfWithMatrix
        );
        $opfWithMatrix = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="audio" href="audio/chapter.mp3" media-type="audio/mpeg"/>
    <item id="mo-chapter" href="overlays/chapter.smil" media-type="application/smil+xml"/>
    <item id="custom-widget" href="widgets/review.bin" media-type="application/x-review-widget" fallback="chapter1"/>',
            $opfWithMatrix
        );
        $opfWithMatrix = str_replace(
            '</spine>',
            '</spine>
  <guide>
    <reference type="text" title="Start" href="text/chapter1.xhtml#intro"/>
    <reference type="cover" title="Cover" href="images/cover.png"/>
  </guide>
  <collection id="review-set" role="preview">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Review packet collection</dc:title>
    </metadata>
    <link rel="record" href="meta/collection.json" media-type="application/json"/>
  </collection>',
            $opfWithMatrix
        );

        $navWithPageList = str_replace(
            '  </body>',
            '    <nav epub:type="page-list" id="pages">
      <h2>Pages</h2>
      <ol><li><a href="text/chapter1.xhtml#page-1">1</a></li></ol>
    </nav>
  </body>',
            $epub3NavXml
        );
        $smil = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL">
  <body>
    <seq>
      <par>
        <text src="../text/chapter1.xhtml#intro"/>
        <audio src="../audio/chapter.mp3" clipBegin="0:00:00.000" clipEnd="0:00:01.500"/>
      </par>
    </seq>
  </body>
</smil>
XML;
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/images/cover.png"/></CipherData>
  </EncryptedData>
</encryption>
XML;
        $signaturesXml = <<<'XML'
<signatures xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
    <SignedInfo/>
  </Signature>
</signatures>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/metadata.xml', 'data' => $containerMetadataXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'META-INF/signatures.xml', 'data' => $signaturesXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMatrix],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithPageList],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="intro">Intro</h1><span id="page-1">1</span></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/audio/chapter.mp3', 'data' => 'MP3'],
            ['name' => 'EPUB/overlays/chapter.smil', 'data' => $smil],
            ['name' => 'EPUB/widgets/review.bin', 'data' => 'WIDGET'],
            ['name' => 'EPUB/meta/creator.json', 'data' => '{"name":"Data Liberation Team"}'],
            ['name' => 'EPUB/meta/collection.json', 'data' => '{"name":"Review packet collection"}'],
            ['name' => 'EPUB/meta/container.json', 'data' => '{"name":"Container metadata"}'],
        ]));

        $summary = $epub->summary();
        $report = $summary['compactPackageReport'];
        $casesById = $report['casesById'];

        $t->same(19, $report['caseCount']);
        $t->same(16, $report['presentCaseCount']);
        $t->same(0, $report['diagnosticCaseCount']);
        $t->same(0, $report['diagnosticCount']);
        $t->same([], $report['diagnosticTypes']);
        $t->same([
            'package-validation',
            'metadata-refinements',
            'metadata-collection-membership',
            'metadata-item-authoring',
            'package-links',
            'container-links',
            'navigation-sections',
            'spine-itemrefs',
            'guide-references',
            'collections',
            'media-type-bindings',
            'media-overlays',
            'manifest-fallbacks',
            'manifest-dependencies',
            'stylesheet-resources',
            'manifest-resource-kinds',
            'manifest-resource-properties',
            'encrypted-resources',
            'ocf-sidecars',
        ], $report['caseIds']);
        $t->same([
            'package-validation',
            'metadata-refinements',
            'metadata-item-authoring',
            'package-links',
            'container-links',
            'navigation-sections',
            'spine-itemrefs',
            'guide-references',
            'collections',
            'media-overlays',
            'manifest-fallbacks',
            'manifest-dependencies',
            'manifest-resource-kinds',
            'manifest-resource-properties',
            'encrypted-resources',
            'ocf-sidecars',
        ], $report['presentCaseIds']);
        $t->same([
            'package-validation' => 1,
            'metadata-refinements' => 2,
            'metadata-collection-membership' => 0,
            'metadata-item-authoring' => 7,
            'package-links' => 1,
            'container-links' => 1,
            'navigation-sections' => 2,
            'spine-itemrefs' => 2,
            'guide-references' => 2,
            'collections' => 1,
            'media-type-bindings' => 0,
            'media-overlays' => 1,
            'manifest-fallbacks' => 1,
            'manifest-dependencies' => 2,
            'stylesheet-resources' => 0,
            'manifest-resource-kinds' => 8,
            'manifest-resource-properties' => 2,
            'encrypted-resources' => 1,
            'ocf-sidecars' => 2,
        ], $report['caseCounts']);
        $t->same([
            'validation' => 1,
            'metadata' => 10,
            'ocf' => 3,
            'navigation' => 2,
            'spine' => 2,
            'guide' => 2,
            'collections' => 1,
            'manifest' => 13,
            'media-overlays' => 1,
            'encryption' => 1,
        ], $report['domainCounts']);

        $t->same(true, $casesById['package-validation']['valid']);
        $t->same('3.0', $casesById['package-validation']['packageVersion']);
        $t->same(true, $casesById['package-validation']['epub3']);
        $t->same(0, $casesById['package-validation']['packageDiagnosticCount']);
        $t->same([], $casesById['package-validation']['invalidDomains']);
        $t->same([
            'rootfiles' => true,
            'metadata' => true,
            'manifest' => true,
            'spine' => true,
            'ncx' => true,
            'navigation' => true,
        ], $casesById['package-validation']['domainValidity']);
        $t->same([
            'rootfiles' => 0,
            'metadata' => 0,
            'manifest' => 0,
            'spine' => 0,
            'ncx' => 0,
            'navigation' => 0,
        ], $casesById['package-validation']['domainDiagnosticCounts']);
        $t->same(['title-main', 'creator'], $casesById['metadata-refinements']['targetIds']);
        $t->same(1, $casesById['metadata-refinements']['packageLinkCount']);
        $t->same(0, $casesById['metadata-collection-membership']['itemCount']);
        $t->same([], $casesById['metadata-collection-membership']['types']);
        $t->same(['/EPUB/meta/creator.json'], $casesById['package-links']['targets']);
        $t->same(['record' => 1], $casesById['package-links']['relCounts']);
        $t->same(1, $casesById['package-links']['localLinkCount']);
        $t->same(0, $casesById['package-links']['externalLinkCount']);
        $t->same(0, $casesById['package-links']['missingLinkCount']);
        $t->same(['/EPUB/meta/container.json'], $casesById['container-links']['targets']);
        $t->same(['record' => 1], $casesById['container-links']['relCounts']);
        $t->same(1, $casesById['container-links']['localLinkCount']);
        $t->same('nav', $casesById['navigation-sections']['navigationType']);
        $t->same(['toc', 'page-list'], $casesById['navigation-sections']['sectionTypes']);
        $t->same(3, $casesById['navigation-sections']['entryCount']);
        $t->same(2, $casesById['spine-itemrefs']['itemCount']);
        $t->same(1, $casesById['spine-itemrefs']['linearCount']);
        $t->same(1, $casesById['spine-itemrefs']['nonLinearCount']);
        $t->same(1, $casesById['spine-itemrefs']['pageSpreadCount']);
        $t->same(1, $casesById['spine-itemrefs']['pageSpreadRightCount']);
        $t->same('right', $casesById['spine-itemrefs']['pageSpreadItems'][0]['placement']);
        $t->same(null, $casesById['spine-itemrefs']['readingProgression']);
        $t->same(['/EPUB/text/chapter1.xhtml#intro', '/EPUB/images/cover.png'], $casesById['guide-references']['targets']);
        $t->same(['Review packet collection'], $casesById['collections']['titles']);
        $t->same(['/EPUB/meta/collection.json'], $casesById['collections']['linkTargets']);
        $t->same(['/EPUB/text/chapter1.xhtml#intro'], $casesById['media-overlays']['textTargets']);
        $t->same(['/EPUB/audio/chapter.mp3'], $casesById['media-overlays']['audioTargets']);
        $t->same(1, $casesById['manifest-fallbacks']['fallbackCount']);
        $t->same(2, $casesById['manifest-dependencies']['itemCount']);
        $t->same([
            'fallback' => 1,
            'media-overlay' => 1,
        ], $casesById['manifest-dependencies']['relationCounts']);
        $t->same([
            'manifest-dependency-target-bytes-exposable' => 2,
        ], $casesById['manifest-dependencies']['byteExposurePolicyCounts']);
        $t->same(['chapter1', 'custom-widget'], $casesById['manifest-dependencies']['sourceIds']);
        $t->same(['mo-chapter', 'chapter1'], $casesById['manifest-dependencies']['targetIds']);
        $t->same(2, $casesById['manifest-dependencies']['exposableTargetCount']);
        $t->same(0, $casesById['manifest-dependencies']['blockedTargetCount']);
        $t->same(7, $casesById['manifest-resource-kinds']['kindCount']);
        $t->same([
            'asset' => 1,
            'audio' => 1,
            'cover-image' => 1,
            'media-overlay' => 1,
            'navigation' => 1,
            'style' => 1,
            'xhtml' => 2,
        ], $casesById['manifest-resource-kinds']['kindCounts']);
        $t->same(2, $casesById['manifest-resource-properties']['itemCount']);
        $t->same(1, $casesById['manifest-resource-properties']['blockedByteExposureCount']);
        $t->same(['cover'], $casesById['manifest-resource-properties']['blockedByteExposureItemIds']);
        $t->same([
            'encrypted-resource-bytes-blocked' => 1,
            'manifest-resource-bytes-exposable' => 1,
        ], $casesById['manifest-resource-properties']['byteExposurePolicyCounts']);
        $t->same(['/EPUB/images/cover.png'], $casesById['encrypted-resources']['encryptedParts']);
        $t->same(1, $casesById['encrypted-resources']['blockedByteExposureCount']);
        $t->same(['metadata', 'signatures'], $casesById['ocf-sidecars']['kinds']);
        $t->same($report, $summary['wordpressImport']['compactPackageReport']);
        $t->same($report['cases'], $summary['wordpressImport']['compactPackageReportCases']);
        $t->same($report['presentCaseIds'], $summary['wordpressImport']['compactPackageReportPresentCaseIds']);
        $t->same([], $summary['wordpressImport']['compactPackageReportDiagnostics']);
    },

    'summarizes compact EPUB metadata link cases for review handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $containerMetadataXml = <<<'XML'
<metadata xmlns="http://www.idpf.org/2013/metadata">
  <link id="container-record" rel="record" href="EPUB/meta/container.json" media-type="application/json"/>
  <link id="container-remote" rel="record" href="https://metadata.example.invalid/container.json" media-type="application/json"/>
  <link id="container-missing" rel="record" href="EPUB/meta/missing-container.json" media-type="application/json"/>
</metadata>
XML;
        $opfWithLinkCases = str_replace(
            '</metadata>',
            '    <link id="package-record" rel="record" href="meta/package.json" media-type="application/json"/>
    <link id="package-remote" rel="record" href="https://metadata.example.invalid/package.json" media-type="application/json"/>
    <link id="package-missing" rel="record" href="meta/missing-package.json" media-type="application/json"/>
  </metadata>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/metadata.xml', 'data' => $containerMetadataXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithLinkCases],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/package.json', 'data' => '{"name":"package"}'],
            ['name' => 'EPUB/meta/container.json', 'data' => '{"name":"container"}'],
        ]));

        $summary = $epub->summary();
        $report = $summary['compactPackageReport'];
        $packageLinks = $report['casesById']['package-links'];
        $containerLinks = $report['casesById']['container-links'];

        $t->same(19, $report['caseCount']);
        $t->true(in_array('package-links', $report['presentCaseIds'], true));
        $t->true(in_array('container-links', $report['presentCaseIds'], true));
        $t->same(['package-links', 'container-links'], $report['diagnosticCaseIds']);
        $t->same(4, $report['diagnosticCount']);
        $t->same([
            'external-package-link-target',
            'missing-package-link-target',
            'external-container-link-target',
            'missing-container-link-target',
        ], array_column($report['diagnostics'], 'type'));

        $t->same(true, $packageLinks['present']);
        $t->same(3, $packageLinks['itemCount']);
        $t->same([
            '/EPUB/meta/package.json',
            'https://metadata.example.invalid/package.json',
            '/EPUB/meta/missing-package.json',
        ], $packageLinks['targets']);
        $t->same(['record' => 3], $packageLinks['relCounts']);
        $t->same(2, $packageLinks['localLinkCount']);
        $t->same(1, $packageLinks['externalLinkCount']);
        $t->same(1, $packageLinks['missingLinkCount']);
        $t->same(2, $packageLinks['diagnosticCount']);
        $t->same(['external-package-link-target', 'missing-package-link-target'], array_column($packageLinks['diagnostics'], 'type'));

        $t->same(true, $containerLinks['present']);
        $t->same(3, $containerLinks['itemCount']);
        $t->same([
            '/EPUB/meta/container.json',
            'https://metadata.example.invalid/container.json',
            '/EPUB/meta/missing-container.json',
        ], $containerLinks['targets']);
        $t->same(['record' => 3], $containerLinks['relCounts']);
        $t->same(2, $containerLinks['localLinkCount']);
        $t->same(1, $containerLinks['externalLinkCount']);
        $t->same(1, $containerLinks['missingLinkCount']);
        $t->same(2, $containerLinks['diagnosticCount']);
        $t->same(['external-container-link-target', 'missing-container-link-target'], array_column($containerLinks['diagnostics'], 'type'));

        $t->same($report, $summary['wordpressImport']['compactPackageReport']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['compactPackageReportDiagnostics']);
    },

    'summarizes compact EPUB manifest resource readiness cases for review handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $opfWithResourceReadiness = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" prefix="review: https://example.invalid/epub-review#">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:compact-resource-readiness</dc:identifier>
    <dc:title>Compact resource readiness</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T03:20:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" properties="mathml unknown:review-flag"/>
    <item id="remote" href="https://cdn.example.invalid/cover.png" media-type="image/png" properties="remote-resources"/>
    <item id="missing" href="images/missing.svg" media-type="image/svg+xml" properties="svg"/>
    <item id="script" href="scripts/review.js" media-type="application/javascript" properties="scripted"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithResourceReadiness],
            ['name' => 'EPUB/nav.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><h1>Contents</h1><ol><li><a href="text/chapter.xhtml">Chapter</a></li></ol></nav></body></html>'],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><math><mi>x</mi></math></body></html>'],
            ['name' => 'EPUB/scripts/review.js', 'data' => 'SCRIPTED-REVIEW', 'method' => 12],
        ]));
        $summary = $epub->summary();
        $report = $summary['compactPackageReport'];
        $resourceKinds = $report['casesById']['manifest-resource-kinds'];
        $resourceProperties = $report['casesById']['manifest-resource-properties'];

        $t->true(in_array('manifest-resource-kinds', $report['caseIds'], true));
        $t->true(in_array('manifest-resource-properties', $report['caseIds'], true));
        $t->same(['package-validation', 'manifest-resource-properties'], $report['diagnosticCaseIds']);
        $t->same(['package-validation', 'manifest-resource-kinds', 'manifest-resource-properties'], $report['reviewRequiredCaseIds']);
        $t->same(3, $report['diagnosticCount']);
        $t->same(['external-manifest-href-target', 'missing-manifest-href-target', 'unknown-manifest-property-prefix'], $report['diagnosticTypes']);
        $t->same('package-validation', $report['diagnostics'][0]['caseId']);
        $t->same('external-manifest-href-target', $report['diagnostics'][0]['type']);
        $t->same('remote', $report['diagnostics'][0]['id']);
        $t->same('package-validation', $report['diagnostics'][1]['caseId']);
        $t->same('missing-manifest-href-target', $report['diagnostics'][1]['type']);
        $t->same('missing', $report['diagnostics'][1]['id']);
        $t->same('manifest-resource-properties', $report['diagnostics'][2]['caseId']);
        $t->same('unknown-manifest-property-prefix', $report['diagnostics'][2]['type']);
        $t->same('chapter', $report['diagnostics'][2]['manifestId']);

        $packageValidation = $report['casesById']['package-validation'];
        $t->same(false, $packageValidation['valid']);
        $t->same(['manifest'], $packageValidation['invalidDomains']);
        $t->same(2, $packageValidation['packageDiagnosticCount']);
        $t->same([
            'rootfiles' => true,
            'metadata' => true,
            'manifest' => false,
            'spine' => true,
            'ncx' => true,
            'navigation' => true,
        ], $packageValidation['domainValidity']);
        $t->same([
            'rootfiles' => 0,
            'metadata' => 0,
            'manifest' => 2,
            'spine' => 0,
            'ncx' => 0,
            'navigation' => 0,
        ], $packageValidation['domainDiagnosticCounts']);
        $t->same(5, $resourceKinds['itemCount']);
        $t->same([
            'image' => 1,
            'navigation' => 1,
            'script' => 1,
            'svg' => 1,
            'xhtml' => 1,
        ], $resourceKinds['kindCounts']);
        $t->same(3, $resourceKinds['existingItemCount']);
        $t->same(2, $resourceKinds['missingItemCount']);
        $t->same(1, $resourceKinds['externalItemCount']);
        $t->same(2, $resourceKinds['exposableItemCount']);

        $t->same(5, $resourceProperties['itemCount']);
        $t->same(4, $resourceProperties['reviewRequiredCount']);
        $t->same(3, $resourceProperties['blockedByteExposureCount']);
        $t->same(2, $resourceProperties['missingItemCount']);
        $t->same(1, $resourceProperties['externalItemCount']);
        $t->same(1, $resourceProperties['unsupportedCompressionItemCount']);
        $t->same(['chapter', 'remote', 'missing', 'script'], $resourceProperties['reviewItemIds']);
        $t->same(['remote', 'missing', 'script'], $resourceProperties['blockedByteExposureItemIds']);
        $t->same([
            'external-resource-metadata-only' => 1,
            'manifest-resource-bytes-exposable' => 2,
            'missing-resource-metadata-only' => 1,
            'unsupported-compression-metadata-only' => 1,
        ], $resourceProperties['byteExposurePolicyCounts']);
        $t->same($report, $summary['wordpressImport']['compactPackageReport']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['compactPackageReportDiagnostics']);
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

        $compactValidation = $summary['compactPackageReport']['casesById']['package-validation'];
        $t->same(true, in_array('package-validation', $summary['compactPackageReport']['diagnosticCaseIds'], true));
        $t->same(false, $compactValidation['valid']);
        $t->same(7, $compactValidation['diagnosticCount']);
        $t->same(7, $compactValidation['packageDiagnosticCount']);
        $t->same(['metadata', 'manifest', 'spine'], $compactValidation['invalidDomains']);
        $t->same([
            'rootfiles' => true,
            'metadata' => false,
            'manifest' => false,
            'spine' => false,
            'ncx' => true,
            'navigation' => true,
        ], $compactValidation['domainValidity']);
        $t->same([
            'rootfiles' => 0,
            'metadata' => 2,
            'manifest' => 4,
            'spine' => 1,
            'ncx' => 0,
            'navigation' => 0,
        ], $compactValidation['domainDiagnosticCounts']);
        $t->same(array_column($validation['diagnostics'], 'type'), $compactValidation['diagnosticTypes']);
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
        $selection = $summary['ncxNavigationSelection'];
        $t->same($selection, $summary['wordpressImport']['ncxNavigationSelection']);
        $t->same(true, $selection['present']);
        $t->same(false, $selection['valid']);
        $t->same('ncx', $selection['source']);
        $t->same(true, $selection['sourceIsNcx']);
        $t->same(true, $selection['tocSpecified']);
        $t->same('wrong-toc', $selection['tocId']);
        $t->same(false, $selection['tocUsable']);
        $t->same(false, $selection['selectedMatchesToc']);
        $t->same(true, $selection['fallbackToManifestScan']);
        $t->same('manifest-scan', $summary['wordpressImport']['ncxNavigationSelectedBy']);
        $t->same('/EPUB/toc.ncx', $selection['selectedPartName']);
        $t->same('toc', $summary['wordpressImport']['ncxNavigationSelectedItem']['id']);
        $t->same(1, $selection['entryCount']);
        $t->same(1, $selection['localTargetCount']);
        $t->same(1, $selection['manifestNcxItemCount']);
        $t->same(['spine-toc-non-ncx-manifest-item'], $selection['diagnosticTypes']);
        $t->same($selection['diagnostics'], $summary['wordpressImport']['ncxNavigationSelectionDiagnostics']);

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
        $missingSummary = $missingTocEpub->summary();
        $missingSelection = $missingSummary['wordpressImport']['ncxNavigationSelection'];

        $t->same(false, $missingValidation['valid']);
        $t->same(['missing-spine-toc-manifest-item'], array_column($missingValidation['diagnostics'], 'type'));
        $t->same('missing-toc', $missingValidation['ncx']['tocId']);
        $t->same(null, $missingValidation['ncx']['tocItem']);
        $t->same('manifest-scan', $missingValidation['ncx']['selectedBy']);
        $t->same('toc', $missingValidation['ncx']['selectedItem']['id']);
        $t->same('ncx', $missingValidation['navigation']['source']);
        $t->same('Legacy chapter', $missingTocEpub->navigation()['entries'][0]['label']);
        $t->same(true, $missingSelection['present']);
        $t->same(false, $missingSelection['valid']);
        $t->same('missing-toc', $missingSelection['tocId']);
        $t->same(null, $missingSelection['tocItem']);
        $t->same(false, $missingSelection['tocUsable']);
        $t->same(true, $missingSelection['fallbackToManifestScan']);
        $t->same('/EPUB/toc.ncx', $missingSelection['selectedPartName']);
        $t->same(['missing-spine-toc-manifest-item'], $missingSelection['diagnosticTypes']);
    },

    'reports empty OPF spine toc binding while preserving NCX manifest fallback' => static function (TestRunner $t) use ($epubContainerXml): void {
        $ncxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="chapter" playOrder="1">
      <navLabel><text>Fallback NCX chapter</text></navLabel>
      <content src="chapter.xhtml"/>
    </navPoint>
  </navMap>
</ncx>
XML;
        $opfWithEmptyToc = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:empty-ncx-binding</dc:identifier>
    <dc:title>Empty NCX binding</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine toc="">
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithEmptyToc],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
            ['name' => 'EPUB/toc.ncx', 'data' => $ncxXml],
        ]));
        $spineMetadata = $epub->spineMetadata();
        $validation = $epub->validationReport();
        $summary = $epub->summary();
        $ncx = $validation['ncx'];
        $binding = $ncx['binding'];
        $selection = $summary['ncxNavigationSelection'];

        $t->same(false, $validation['valid']);
        $t->same(1, $validation['diagnosticCount']);
        $t->same(['empty-spine-toc-attribute'], array_column($validation['diagnostics'], 'type'));
        $t->same(true, $spineMetadata['tocSpecified']);
        $t->same('', $spineMetadata['tocRaw']);
        $t->same(null, $spineMetadata['tocId']);
        $t->same(true, $ncx['tocSpecified']);
        $t->same('', $ncx['tocRaw']);
        $t->same(null, $ncx['tocId']);
        $t->same(true, $ncx['tocEmpty']);
        $t->same('empty', $ncx['bindingStatus']);
        $t->same(null, $ncx['tocItem']);
        $t->same(1, $ncx['manifestNcxItemCount']);
        $t->same('manifest-scan', $ncx['selectedBy']);
        $t->same('toc', $ncx['selectedItem']['id']);
        $t->same('/EPUB/toc.ncx', $ncx['selectedPartName']);
        $t->same(false, $ncx['selectedMatchesToc']);
        $t->same(true, $ncx['fallbackToManifestScan']);
        $t->same('empty-spine-toc-attribute', $ncx['diagnostics'][0]['type']);
        $t->same('empty', $binding['status']);
        $t->same('', $binding['tocRaw']);
        $t->same(true, $binding['tocEmpty']);
        $t->same('manifest-scan', $binding['selectedBy']);
        $t->same('toc', $binding['selectedItem']['id']);
        $t->same(true, $binding['fallbackToManifestScan']);
        $t->same('ncx', $validation['navigation']['source']);
        $t->same(1, $validation['navigation']['entryCount']);
        $t->same('Fallback NCX chapter', $epub->navigation()['entries'][0]['label']);
        $t->same($selection, $summary['wordpressImport']['ncxNavigationSelection']);
        $t->same('empty', $selection['bindingStatus']);
        $t->same($binding, $selection['binding']);
        $t->same($binding, $summary['wordpressImport']['ncxSpineTocBinding']);
        $t->same('empty', $summary['wordpressImport']['ncxSpineTocBindingStatus']);
        $t->same($binding['diagnostics'], $summary['wordpressImport']['ncxSpineTocBindingDiagnostics']);
    },

    'preserves EPUB container rootfile full-path suffix provenance for package review' => static function (TestRunner $t) use ($epub3OpfXml, $epub3NavXml): void {
        $containerWithRootfileSuffix = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf?profile=compact#primary" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $containerWithRootfileSuffix],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $declaredRootfiles = $epub->rootfiles();
        $validation = $epub->validationReport();
        $rootfiles = $validation['rootfiles'];
        $summary = $epub->summary();

        $t->same('/EPUB/package.opf', $epub->opfPartName());
        $t->same('EPUB/package.opf?profile=compact#primary', $declaredRootfiles[0]['fullPath']);
        $t->same('/EPUB/package.opf?profile=compact#primary', $declaredRootfiles[0]['target']);
        $t->same('/EPUB/package.opf', $declaredRootfiles[0]['partName']);
        $t->same(true, $declaredRootfiles[0]['fullPathHasQuery']);
        $t->same('profile=compact', $declaredRootfiles[0]['fullPathQuery']);
        $t->same(true, $declaredRootfiles[0]['fullPathHasFragment']);
        $t->same('primary', $declaredRootfiles[0]['fullPathFragment']);
        $t->same(false, $validation['valid']);
        $t->same(2, $validation['diagnosticCount']);
        $t->same(['rootfile-full-path-query-component', 'rootfile-full-path-fragment-component'], array_column($validation['diagnostics'], 'type'));
        $t->same(1, $rootfiles['fullPathSuffixCount']);
        $t->same('profile=compact', $rootfiles['fullPathSuffixItems'][0]['query']);
        $t->same('primary', $rootfiles['fullPathSuffixItems'][0]['fragment']);
        $t->same('/EPUB/package.opf', $rootfiles['items'][0]['partName']);
        $t->same('/EPUB/package.opf?profile=compact#primary', $rootfiles['items'][0]['target']);
        $t->same(strlen($epub3OpfXml), $rootfiles['items'][0]['byteLength']);
        $t->same(true, $rootfiles['items'][0]['exists']);
        $t->same($rootfiles['items'][0], $rootfiles['selectedRootfile']);
        $t->same($declaredRootfiles, $summary['rootfiles']);
        $t->same($rootfiles, $summary['wordpressImport']['packageValidation']['rootfiles']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
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

    'summarizes compact EPUB rootfile renditions for package handoff' => static function (TestRunner $t) use ($epub3OpfXml, $epub3NavXml): void {
        $containerWithRenditions = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml; profile=&quot;primary&quot;"/>
    <rootfile full-path="EPUB/fixed/package.opf" media-type="application/oebps-package+xml; rendition=&quot;fixed-layout&quot;"/>
    <rootfile full-path="EPUB/missing/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;
        $alternateOpfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" id="fixed-package" version="3.0" unique-identifier="fixed-id" xml:lang="en" dir="ltr">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="fixed-id">urn:uuid:fixed-layout-review</dc:identifier>
    <dc:title>Fixed layout compact edition</dc:title>
    <dc:creator>Layout Desk</dc:creator>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-12T02:10:08Z</meta>
    <meta property="rendition:layout">pre-paginated</meta>
    <meta property="rendition:orientation">landscape</meta>
    <meta property="rendition:spread">none</meta>
    <meta property="rendition:viewport">width=1024, height=768</meta>
  </metadata>
  <manifest>
    <item id="fixed-nav" href="fixed-nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="fixed-page" href="fixed-page.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="fixed-page"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $containerWithRenditions],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/fixed/package.opf', 'data' => $alternateOpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $renditions = $epub->renditions();
        $summary = $epub->summary();

        $t->same('/EPUB/package.opf', $renditions['selectedPath']);
        $t->same(0, $renditions['selectedIndex']);
        $t->same(3, $renditions['count']);
        $t->same(2, $renditions['alternateCount']);
        $t->same(1, $renditions['diagnosticCount']);
        $t->same('missing-alternate-rendition-rootfile', $renditions['diagnostics'][0]['type']);
        $t->same('/EPUB/missing/package.opf', $renditions['diagnostics'][0]['path']);

        $selected = $renditions['items'][0];
        $alternate = $renditions['items'][1];
        $missing = $renditions['items'][2];
        $t->same(true, $selected['selected']);
        $t->same('/EPUB/package.opf', $selected['partName']);
        $t->same('application/oebps-package+xml; profile="primary"', $selected['mediaType']);
        $t->same(['profile' => 'primary'], $selected['mediaTypeParameterMap']);
        $t->same('WordPress Migration Guide', $selected['metadata']['title']);
        $t->same('bookid', $selected['package']['uniqueIdentifierId']);
        $t->same('/EPUB/package.opf', $selected['package']['opfPart']);
        $t->same(5, $selected['manifestCount']);
        $t->same(2, $selected['spineCount']);
        $t->same(strlen($epub3OpfXml), $selected['byteLength']);

        $t->same(false, $alternate['selected']);
        $t->same(true, $alternate['exists']);
        $t->same('/EPUB/fixed/package.opf', $alternate['partName']);
        $t->same(['rendition' => 'fixed-layout'], $alternate['mediaTypeParameterMap']);
        $t->same('fixed-package', $alternate['package']['id']);
        $t->same('ltr', $alternate['package']['direction']);
        $t->same('/EPUB/fixed/package.opf', $alternate['package']['opfPart']);
        $t->same('Fixed layout compact edition', $alternate['metadata']['title']);
        $t->same('urn:uuid:fixed-layout-review', $alternate['metadata']['identifier']);
        $t->same(['Layout Desk'], $alternate['metadata']['creators']);
        $t->same('2026-06-12T02:10:08Z', $alternate['metadata']['modified']);
        $t->same('pre-paginated', $alternate['renditionProperties']['layout']);
        $t->same('landscape', $alternate['renditionProperties']['orientation']);
        $t->same('none', $alternate['renditionProperties']['spread']);
        $t->same('width=1024, height=768', $alternate['renditionProperties']['viewport']);
        $t->same(true, $alternate['renditionLayout']['fixedLayout']);
        $t->same(2, $alternate['manifestCount']);
        $t->same(1, $alternate['spineCount']);
        $t->same([], $alternate['diagnostics']);

        $t->same(false, $missing['exists']);
        $t->same(null, $missing['byteLength']);
        $t->same('missing-alternate-rendition-rootfile', $missing['diagnostics'][0]['type']);
        $t->same($renditions, $summary['renditions']);
        $t->same($renditions, $summary['wordpressImport']['renditions']);
        $t->same($renditions['diagnostics'], $summary['wordpressImport']['renditionDiagnostics']);
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

    'reports malformed OPF spine itemref attributes without aborting package ingestion' => static function (TestRunner $t) use ($epubContainerXml): void {
        $malformedSpineOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:spine-attribute-review</dc:identifier>
    <dc:title>Spine attribute review</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-12T01:52:09Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref id="orphan-spine" linear="no" properties="page-spread-left"/>
    <itemref idref="chapter"/>
  </spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol><li><a href="chapter.xhtml">Spine attribute review</a></li></ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $malformedSpineOpf],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
        ]));
        $spine = $epub->spine();
        $validation = $epub->validationReport();
        $spineValidation = $validation['spine'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(['missing-spine-itemref-idref'], array_column($validation['diagnostics'], 'type'));
        $t->same(2, $spineValidation['itemCount']);
        $t->same(1, $spineValidation['linearCount']);
        $t->same(1, $spineValidation['nonLinearCount']);
        $t->same(1, $spineValidation['missingRequiredAttributeItemCount']);
        $t->same(1, $spineValidation['missingRequiredAttributeCount']);
        $t->same(['idref'], $spineValidation['missingRequiredAttributeNames']);
        $t->same(0, $spineValidation['missingManifestItemCount']);
        $t->same(0, $spineValidation['missingPackagePartCount']);
        $t->same(0, $spineValidation['nonContentDocumentCount']);
        $t->same('orphan-spine', $spineValidation['missingRequiredAttributeItems'][0]['id']);
        $t->same('', $spineValidation['missingRequiredAttributeItems'][0]['idref']);
        $t->same(['idref'], $spineValidation['missingRequiredAttributeItems'][0]['missingAttributes']);
        $t->same('missing-spine-itemref-idref', $spineValidation['itemDiagnostics'][0]['type']);
        $t->same('', $spineValidation['itemDiagnostics'][0]['idref']);
        $t->same('idref', $spineValidation['itemDiagnostics'][0]['attribute']);
        $t->same(false, $spine[0]['requiredAttributesPresent']);
        $t->same(['idref'], $spine[0]['missingRequiredAttributes']);
        $t->same('orphan-spine', $spine[0]['id']);
        $t->same(false, $spine[0]['linear']);
        $t->same('no', $spine[0]['linearRaw']);
        $t->same('left', $spine[0]['pageSpread']);
        $t->same('', $spine[0]['partName']);
        $t->same(false, $spine[0]['manifestItemMissing']);
        $t->same('/EPUB/chapter.xhtml', $spine[1]['partName']);
        $t->same(true, $spine[1]['requiredAttributesPresent']);
        $t->same($spineValidation['missingRequiredAttributeItems'], $summary['wordpressImport']['spineMissingRequiredAttributeItems']);
        $t->same($spineValidation['missingRequiredAttributeNames'], $summary['wordpressImport']['spineMissingRequiredAttributeNames']);
        $t->same($spineValidation['itemDiagnostics'], $summary['wordpressImport']['spineItemDiagnostics']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
    },

    'reports invalid OPF spine linear values without changing reading order ingestion' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithInvalidLinear = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:spine-linear-review</dc:identifier>
    <dc:title>Spine linear review</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T08:59:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
    <item id="colophon" href="colophon.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref id="chapter-spine" idref="chapter" linear="sometimes"/>
    <itemref id="appendix-spine" idref="appendix" linear="NO"/>
    <itemref id="colophon-spine" idref="colophon"/>
  </spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
        <li><a href="appendix.xhtml">Appendix</a></li>
        <li><a href="colophon.xhtml">Colophon</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithInvalidLinear],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
            ['name' => 'EPUB/appendix.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Appendix</body></html>'],
            ['name' => 'EPUB/colophon.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Colophon</body></html>'],
        ]));
        $spine = $epub->spine();
        $summary = $epub->summary();
        $validation = $epub->validationReport();
        $spineValidation = $validation['spine'];
        $authoring = $summary['spineAuthoring'];
        $inventory = $summary['readingOrderInventory'];

        $t->same(false, $validation['valid']);
        $t->same(['invalid-spine-linear-value'], array_column($validation['diagnostics'], 'type'));
        $t->same(3, $spineValidation['itemCount']);
        $t->same(2, $spineValidation['linearCount']);
        $t->same(1, $spineValidation['nonLinearCount']);
        $t->same(1, $spineValidation['invalidLinearItemCount']);
        $t->same(1, $spineValidation['itemDiagnosticCount']);
        $t->same('invalid-spine-linear-value', $spineValidation['itemDiagnostics'][0]['type']);
        $t->same('chapter', $spineValidation['itemDiagnostics'][0]['idref']);
        $t->same('sometimes', $spineValidation['invalidLinearItems'][0]['linearRaw']);
        $t->same('sometimes', $spineValidation['invalidLinearItems'][0]['linearValue']);
        $t->same(true, $spineValidation['invalidLinearItems'][0]['linear']);

        $t->same('sometimes', $spine[0]['linearRaw']);
        $t->same(true, $spine[0]['linearSpecified']);
        $t->same('sometimes', $spine[0]['linearValue']);
        $t->same(false, $spine[0]['linearValid']);
        $t->same(true, $spine[0]['linear']);
        $t->same('invalid-spine-linear-value', $spine[0]['linearDiagnostics'][0]['type']);
        $t->same('NO', $spine[1]['linearRaw']);
        $t->same('no', $spine[1]['linearValue']);
        $t->same(true, $spine[1]['linearValid']);
        $t->same(false, $spine[1]['linear']);
        $t->same(null, $spine[2]['linearRaw']);
        $t->same(false, $spine[2]['linearSpecified']);
        $t->same(true, $spine[2]['linearValid']);

        $t->same('sometimes', $authoring['items'][0]['linearRaw']);
        $t->same(false, $authoring['items'][0]['linearValid']);
        $t->same('NO', $authoring['items'][1]['linearRaw']);
        $t->same(false, $authoring['items'][1]['linear']);
        $t->same($spineValidation['invalidLinearItems'], $summary['wordpressImport']['spineInvalidLinearItems']);
        $t->same(1, $summary['wordpressImport']['spineInvalidLinearItemCount']);

        $t->same(3, $inventory['itemCount']);
        $t->same(2, $inventory['linearItemCount']);
        $t->same(1, $inventory['nonLinearItemCount']);
        $t->same(1, $inventory['diagnosticCount']);
        $t->same(['invalid-spine-linear-value'], $inventory['diagnosticTypes']);
        $t->same(false, $inventory['itemsByIdref']['chapter'][0]['linearValid']);
        $t->same('sometimes', $inventory['itemsByIdref']['chapter'][0]['linearRaw']);
        $t->same(false, $inventory['itemsByIdref']['appendix'][0]['linear']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
    },

    'reports duplicate OPF spine idrefs without aborting package ingestion' => static function (TestRunner $t) use ($epubContainerXml): void {
        $duplicateSpineOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:duplicate-spine-idref-review</dc:identifier>
    <dc:title>Duplicate spine idref review</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T06:50:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref id="chapter-spine-a" idref="chapter"/>
    <itemref id="chapter-spine-b" idref="chapter" linear="no"/>
  </spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol><li><a href="chapter.xhtml">Duplicate spine review</a></li></ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $duplicateSpineOpf],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
        ]));
        $spine = $epub->spine();
        $validation = $epub->validationReport();
        $spineValidation = $validation['spine'];
        $summary = $epub->summary();
        $duplicate = $spineValidation['duplicateIdrefItems'][0];

        $t->same(false, $validation['valid']);
        $t->same(['duplicate-spine-itemref-idref'], array_column($validation['diagnostics'], 'type'));
        $t->same(false, $spineValidation['valid']);
        $t->same(2, $spineValidation['itemCount']);
        $t->same(1, $spineValidation['linearCount']);
        $t->same(1, $spineValidation['nonLinearCount']);
        $t->same(1, $spineValidation['duplicateIdrefCount']);
        $t->same(1, $spineValidation['duplicateSpineIdrefCount']);
        $t->same('chapter', $duplicate['idref']);
        $t->same([0, 1], $duplicate['indexes']);
        $t->same(['chapter-spine-a', 'chapter-spine-b'], $duplicate['ids']);
        $t->same(['/EPUB/chapter.xhtml'], $duplicate['partNames']);
        $t->same(['application/xhtml+xml'], $duplicate['mediaTypes']);
        $t->same([true, false], $duplicate['linearValues']);
        $t->same(0, $duplicate['selectedIndex']);
        $t->same('/EPUB/chapter.xhtml', $duplicate['selectedPartName']);
        $t->same('duplicate-spine-itemref-idref', $spineValidation['diagnostics'][0]['type']);
        $t->same('chapter', $spineValidation['diagnostics'][0]['idref']);
        $t->same([0, 1], $spineValidation['diagnostics'][0]['indexes']);
        $t->same('/EPUB/chapter.xhtml', $spine[0]['partName']);
        $t->same('/EPUB/chapter.xhtml', $spine[1]['partName']);
        $t->same(true, $spine[0]['linear']);
        $t->same(false, $spine[1]['linear']);
        $t->same($spineValidation['duplicateIdrefItems'], $spineValidation['duplicateSpineIdrefItems']);
        $t->same($spineValidation['duplicateIdrefItems'], $summary['wordpressImport']['spineDuplicateIdrefItems']);
        $t->same($spineValidation['duplicateSpineIdrefItems'], $summary['wordpressImport']['spineDuplicateItemrefIdrefItems']);
        $t->same(1, $summary['wordpressImport']['spineDuplicateIdrefCount']);
        $t->same(1, $summary['wordpressImport']['spineDuplicateItemrefIdrefCount']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
    },

    'reports malformed OPF manifest item attributes without aborting package ingestion' => static function (TestRunner $t) use ($epubContainerXml): void {
        $malformedManifestOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:manifest-attribute-review</dc:identifier>
    <dc:title>Manifest attribute review</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-12T00:00:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item href="assets/no-id.bin" media-type="application/octet-stream"/>
    <item id="missing-href" media-type="text/css"/>
    <item id="missing-type" href="assets/no-type.bin"/>
    <item id="bad-href" href="../../outside.bin" media-type="application/octet-stream"/>
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

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $malformedManifestOpf],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/assets/no-id.bin', 'data' => 'NO-ID'],
            ['name' => 'EPUB/assets/no-type.bin', 'data' => 'NO-TYPE'],
        ]));
        $validation = $epub->validationReport();
        $summary = $epub->summary();
        $manifest = $epub->manifestItems();
        $manifestValidation = $validation['manifest'];

        $t->same(false, $validation['valid']);
        $t->same(6, $manifestValidation['itemCount']);
        $t->same([
            'missing-manifest-item-id',
            'missing-manifest-item-href',
            'invalid-manifest-media-type',
            'missing-manifest-item-media-type',
            'invalid-manifest-href-target',
        ], array_column($validation['diagnostics'], 'type'));
        $t->same(3, $manifestValidation['missingRequiredAttributeItemCount']);
        $t->same(3, $manifestValidation['missingRequiredAttributeCount']);
        $t->same(['id', 'href', 'media-type'], $manifestValidation['missingRequiredAttributeNames']);
        $t->same([2, 3, 4], array_column($manifestValidation['missingRequiredAttributeItems'], 'index'));
        $t->same([['id'], ['href'], ['media-type']], array_column($manifestValidation['missingRequiredAttributeItems'], 'missingAttributes'));
        $t->same(1, $manifestValidation['invalidHrefItemCount']);
        $t->same('bad-href', $manifestValidation['invalidHrefItems'][0]['id']);
        $t->same('../../outside.bin', $manifestValidation['invalidHrefItems'][0]['href']);
        $t->same(4, $manifestValidation['itemDiagnosticCount']);
        $t->same(1, $manifestValidation['mediaTypeDiagnosticCount']);
        $t->same(null, $epub->manifestItem(''));
        $t->same(['id'], $manifest[2]['missingRequiredAttributes']);
        $t->same(false, $manifest[2]['requiredAttributesPresent']);
        $t->same('/EPUB/assets/no-id.bin', $manifest[2]['partName']);
        $t->same(['href'], $epub->manifestItem('missing-href')['missingRequiredAttributes']);
        $t->same(null, $epub->manifestItem('missing-href')['partName']);
        $t->same(['media-type'], $epub->manifestItem('missing-type')['missingRequiredAttributes']);
        $t->same('/EPUB/assets/no-type.bin', $epub->manifestItem('missing-type')['partName']);
        $t->same(null, $epub->manifestItem('bad-href')['partName']);
        $t->same('invalid-manifest-href-target', $epub->manifestItem('bad-href')['diagnostics'][0]['type']);
        $t->same($manifestValidation['missingRequiredAttributeItems'], $summary['wordpressImport']['manifestMissingRequiredAttributeItems']);
        $t->same($manifestValidation['missingRequiredAttributeNames'], $summary['wordpressImport']['manifestMissingRequiredAttributeNames']);
        $t->same($manifestValidation['invalidHrefItems'], $summary['wordpressImport']['manifestInvalidHrefItems']);
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

    'preserves duplicate OPF manifest id package inventory roles for review' => static function (TestRunner $t) use ($epubContainerXml): void {
        $duplicateIdOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:duplicate-manifest-inventory</dc:identifier>
    <dc:title>Duplicate manifest inventory</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T10:10:00Z</meta>
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

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $duplicateIdOpf],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/chapter-review.xhtml', 'data' => '<html/>'],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $byPath = $inventory['byPackagePath'];
        $chapter = $byPath['EPUB/chapter.xhtml'];
        $review = $byPath['EPUB/chapter-review.xhtml'];
        $items = $inventory['duplicateManifestIdItems'];

        $t->same(2, $inventory['duplicateManifestIdEntryCount']);
        $t->same(['/EPUB/chapter.xhtml', '/EPUB/chapter-review.xhtml'], $inventory['duplicateManifestIdPartNames']);
        $t->same(['EPUB/chapter.xhtml', 'EPUB/chapter-review.xhtml'], $inventory['duplicateManifestIdPackagePaths']);
        $t->same(2, $inventory['roleCounts']['duplicate-opf-manifest-id']);
        $t->same($items, $summary['wordpressImport']['packageInventoryDuplicateManifestIdItems']);
        $t->same($inventory['duplicateManifestIdPartNames'], $summary['wordpressImport']['packageInventoryDuplicateManifestIdPartNames']);
        $t->true(in_array('duplicate-opf-manifest-id', $chapter['roles'], true), 'chapter duplicate-id inventory role missing');
        $t->true(in_array('duplicate-opf-manifest-id', $review['roles'], true), 'review duplicate-id inventory role missing');
        $t->same(true, $chapter['duplicateManifestId']);
        $t->same(true, $review['duplicateManifestId']);
        $t->same(['chapter'], $chapter['duplicateManifestIds']);
        $t->same(['chapter'], $review['duplicateManifestIds']);
        $t->same([1, 2], $chapter['duplicateManifestIdIndexes']);
        $t->same([1, 2], $review['duplicateManifestIdIndexes']);
        $t->same(true, $chapter['duplicateManifestIdSelected']);
        $t->same(false, $review['duplicateManifestIdSelected']);
        $t->same(['chapter' => [0]], $chapter['duplicateManifestIdOrdinalsById']);
        $t->same(['chapter' => [1]], $review['duplicateManifestIdOrdinalsById']);
        $t->same('EPUB/chapter.xhtml', $items[0]['packagePath']);
        $t->same('EPUB/chapter-review.xhtml', $items[1]['packagePath']);
        $t->same(2, $items[0]['manifestItemCount']);
        $t->same(2, $items[1]['manifestItemCount']);
        $t->same(true, $items[0]['selected']);
        $t->same(false, $items[1]['selected']);
        $t->same([1, 2], $items[0]['manifestItemIndexes']);
        $t->same([1, 2], $items[1]['manifestItemIndexes']);
    },

    'summarizes duplicate OPF manifest package part declarations in inventory handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithDuplicatePartDeclaration = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter1-review" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            $epub3OpfXml
        );
        $chapter1 = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>';

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithDuplicatePartDeclaration],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => $chapter1],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $validation = $epub->validationReport();
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $report = $inventory['opfManifestPartDeclarationsByPartName']['/EPUB/text/chapter1.xhtml'];
        $duplicate = $inventory['opfManifestDuplicatePartDeclarationItems'][0];
        $entry = $inventory['byPackagePath']['EPUB/text/chapter1.xhtml'];

        $t->same(false, $validation['valid']);
        $t->same(['duplicate-manifest-part-target'], array_column($validation['diagnostics'], 'type'));
        $t->same(1, $validation['manifest']['duplicatePartCount']);
        $t->same(['/EPUB/text/chapter1.xhtml'], array_column($validation['manifest']['duplicatePartItems'], 'partName'));
        $t->same(5, $inventory['opfManifestPartDeclarationCount']);
        $t->same(6, $inventory['opfManifestPartDeclarationItemCount']);
        $t->same(1, $inventory['opfManifestDuplicatePartDeclarationCount']);
        $t->same(2, $inventory['opfManifestDuplicatePartDeclarationItemCount']);
        $t->same(['/EPUB/text/chapter1.xhtml'], $inventory['opfManifestDuplicatePartDeclarationPartNames']);
        $t->same('duplicate-opf-manifest-package-part-declaration', $inventory['opfManifestDuplicatePartDeclarationDiagnostics'][0]['type']);
        $t->same($report, $duplicate);
        $t->same('/EPUB/text/chapter1.xhtml', $report['partName']);
        $t->same('EPUB/text/chapter1.xhtml', $report['packagePath']);
        $t->same(2, $report['declarationCount']);
        $t->same(true, $report['duplicateDeclaration']);
        $t->same([3, 4], $report['indexes']);
        $t->same(['chapter1', 'chapter1-review'], $report['ids']);
        $t->same(['text/chapter1.xhtml'], $report['hrefs']);
        $t->same(['application/xhtml+xml'], $report['mediaTypes']);
        $t->same(['application/xhtml+xml'], $report['mediaTypeBases']);
        $t->same(['xhtml'], $report['resourceKinds']);
        $t->same(3, $report['selectedIndex']);
        $t->same('chapter1', $report['selectedId']);
        $t->same('chapter1-review', $report['declarations'][1]['id']);
        $t->same(true, $report['exists']);
        $t->same(strlen($chapter1), $report['byteLength']);
        $t->same('epub-package-entry-metadata-only', $report['byteExposurePolicy']);
        $t->same(1, $report['diagnosticCount']);
        $t->same('duplicate-opf-manifest-package-part-declaration', $report['diagnostics'][0]['type']);
        $t->same(['chapter1', 'chapter1-review'], $entry['manifestIds']);
        $t->same(2, $entry['manifestItemCount']);
        $t->same($inventory['opfManifestPartDeclarations'], $summary['wordpressImport']['packageInventoryOpfManifestPartDeclarations']);
        $t->same($inventory['opfManifestPartDeclarationsByPartName'], $summary['wordpressImport']['packageInventoryOpfManifestPartDeclarationsByPartName']);
        $t->same($inventory['opfManifestDuplicatePartDeclarationItems'], $summary['wordpressImport']['packageInventoryOpfManifestDuplicatePartDeclarations']);
        $t->same($inventory['opfManifestDuplicatePartDeclarationDiagnostics'], $summary['wordpressImport']['packageInventoryOpfManifestDuplicatePartDeclarationDiagnostics']);
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

    'reports missing OPF manifest package parts in inventory review handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithMissingInventoryParts = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:missing-inventory-parts</dc:identifier>
    <dc:title>Missing inventory parts</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T07:08:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="cover" href="images/missing-cover.png" media-type="image/png" properties="cover-image"/>
    <item id="audio" href="audio/missing-theme.mp3" media-type="audio/mpeg"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol><li><a href="chapter.xhtml">Inventory review</a></li></ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMissingInventoryParts],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Readable</h1></body></html>'],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $missing = $inventory['missingOpfManifestDeclaredItems'];
        $byPartName = $inventory['missingOpfManifestDeclaredItemsByPartName'];
        $cover = $byPartName['/EPUB/images/missing-cover.png'][0];
        $audio = $byPartName['/EPUB/audio/missing-theme.mp3'][0];

        $t->same(2, $inventory['missingOpfManifestDeclaredItemCount']);
        $t->same(2, $inventory['missingOpfManifestDeclaredPartCount']);
        $t->same(['/EPUB/images/missing-cover.png', '/EPUB/audio/missing-theme.mp3'], $inventory['missingOpfManifestDeclaredPartNames']);
        $t->same(['cover', 'audio'], array_column($missing, 'id'));
        $t->same([
            'audio' => 1,
            'cover-image' => 1,
        ], $inventory['missingOpfManifestDeclaredResourceKindCounts']);
        $t->same([
            'missing-package-part' => 2,
            'opf-manifest-declared' => 2,
            'resource-kind-audio' => 1,
            'resource-kind-cover-image' => 1,
        ], $inventory['missingOpfManifestDeclaredRoleCounts']);
        $t->same([
            'missing-opf-manifest-package-part-metadata-only' => 2,
        ], $inventory['missingOpfManifestDeclaredByteExposurePolicyCounts']);
        $t->same(2, $inventory['missingOpfManifestDeclaredDiagnosticCount']);
        $t->same(['missing-opf-manifest-package-part', 'missing-opf-manifest-package-part'], array_column($inventory['missingOpfManifestDeclaredDiagnostics'], 'type'));
        $t->same($missing, $summary['wordpressImport']['packageInventoryMissingOpfManifestDeclaredItems']);
        $t->same($inventory['missingOpfManifestDeclaredDiagnostics'], $summary['wordpressImport']['packageInventoryMissingOpfManifestDeclaredDiagnostics']);

        $t->same(2, $cover['index']);
        $t->same('cover', $cover['id']);
        $t->same('images/missing-cover.png', $cover['href']);
        $t->same('/EPUB/images/missing-cover.png', $cover['partName']);
        $t->same('EPUB/images/missing-cover.png', $cover['packagePath']);
        $t->same('image/png', $cover['mediaType']);
        $t->same(['cover-image'], $cover['properties']);
        $t->same('cover-image', $cover['resourceKind']);
        $t->same(['opf-manifest-declared', 'missing-package-part', 'resource-kind-cover-image'], $cover['roles']);
        $t->same(false, $cover['exists']);
        $t->same(null, $cover['byteLength']);
        $t->same(null, $cover['compressedByteLength']);
        $t->same(false, $cover['canExposeBytes']);
        $t->same('missing-opf-manifest-package-part-metadata-only', $cover['byteExposurePolicy']);
        $t->same('missing-opf-manifest-package-part', $cover['diagnostics'][0]['type']);

        $t->same(3, $audio['index']);
        $t->same('audio', $audio['id']);
        $t->same('audio/missing-theme.mp3', $audio['href']);
        $t->same('/EPUB/audio/missing-theme.mp3', $audio['partName']);
        $t->same('audio/mpeg', $audio['mediaTypeBase']);
        $t->same('audio', $audio['resourceKind']);
        $t->same(['opf-manifest-declared', 'missing-package-part', 'resource-kind-audio'], $audio['roles']);
        $t->same(false, isset($inventory['byPackagePath']['EPUB/images/missing-cover.png']));
        $t->same(false, isset($inventory['byPackagePath']['EPUB/audio/missing-theme.mp3']));
    },

    'preserves duplicate OPF manifest package parts in ZIP package inventory' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithDuplicatePackageParts = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:duplicate-manifest-package-inventory</dc:identifier>
    <dc:title>Duplicate manifest package inventory</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T11:17:53Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter-review" href="text/chapter.xhtml#review" media-type="application/xhtml+xml"/>
    <item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="cover-review" href="images/cover.png?role=review" media-type="image/png"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol><li><a href="text/chapter.xhtml">Chapter</a></li></ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithDuplicatePackageParts],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter</h1></body></html>'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $summary = $epub->summary();
        $validation = $epub->validationReport();
        $inventory = $summary['packageInventory'];
        $chapter = $inventory['byPackagePath']['EPUB/text/chapter.xhtml'];
        $cover = $inventory['byPackagePath']['EPUB/images/cover.png'];
        $duplicates = $inventory['duplicateManifestPackagePartItems'];

        $t->same($inventory, $summary['wordpressImport']['packageInventory']);
        $t->same(2, $inventory['duplicateManifestPackagePartCount']);
        $t->same(4, $inventory['duplicateManifestPackageItemCount']);
        $t->same(['/EPUB/text/chapter.xhtml', '/EPUB/images/cover.png'], $inventory['duplicateManifestPackagePartNames']);
        $t->same(2, $inventory['duplicateManifestPackagePartDiagnosticCount']);
        $t->same(['duplicate-opf-manifest-package-part', 'duplicate-opf-manifest-package-part'], array_column($inventory['duplicateManifestPackagePartDiagnostics'], 'type'));
        $t->same(2, $inventory['roleCounts']['duplicate-opf-manifest-package-part']);
        $t->same(2, $inventory['roleCounts']['duplicate-opf-manifest-package-path']);
        $t->same(3, $inventory['opfManifestDeclaredEntryCount']);
        $t->same(3, $inventory['opfManifestDeclaredPartCount']);

        $t->same('EPUB/text/chapter.xhtml', $duplicates[0]['packagePath']);
        $t->same('/EPUB/text/chapter.xhtml', $duplicates[0]['partName']);
        $t->same(2, $duplicates[0]['itemCount']);
        $t->same([1, 2], $duplicates[0]['indexes']);
        $t->same(['chapter', 'chapter-review'], $duplicates[0]['ids']);
        $t->same(['text/chapter.xhtml', 'text/chapter.xhtml#review'], $duplicates[0]['hrefs']);
        $t->same(['/EPUB/text/chapter.xhtml', '/EPUB/text/chapter.xhtml#review'], $duplicates[0]['targets']);
        $t->same(['application/xhtml+xml'], $duplicates[0]['mediaTypes']);
        $t->same(['cover', 'cover-review'], $duplicates[1]['ids']);
        $t->same(['images/cover.png', 'images/cover.png?role=review'], $duplicates[1]['hrefs']);
        $t->same(['/EPUB/images/cover.png', '/EPUB/images/cover.png?role=review'], $duplicates[1]['targets']);
        $t->same(['image/png'], $duplicates[1]['mediaTypes']);

        $t->same(2, $chapter['manifestItemCount']);
        $t->same(['chapter', 'chapter-review'], $chapter['manifestIds']);
        $t->same(true, $chapter['duplicateManifestPackagePart']);
        $t->same(['chapter', 'chapter-review'], $chapter['duplicateManifestPackagePartIds']);
        $t->same(['text/chapter.xhtml', 'text/chapter.xhtml#review'], $chapter['duplicateManifestPackagePartHrefs']);
        $t->same([1, 2], $chapter['duplicateManifestPackagePartIndexes']);
        $t->same(true, $chapter['duplicateOpfManifestPackagePath']);
        $t->same(['opf-manifest-declared', 'duplicate-opf-manifest-package-part', 'duplicate-opf-manifest-package-path', 'resource-kind-xhtml', 'spine-reading-order'], $chapter['roles']);

        $t->same(2, $cover['manifestItemCount']);
        $t->same(['cover', 'cover-review'], $cover['manifestIds']);
        $t->same(true, $cover['duplicateManifestPackagePart']);
        $t->same(['cover', 'cover-review'], $cover['duplicateManifestPackagePartIds']);
        $t->same(['images/cover.png', 'images/cover.png?role=review'], $cover['duplicateManifestPackagePartHrefs']);
        $t->same([3, 4], $cover['duplicateManifestPackagePartIndexes']);
        $t->same(true, $cover['duplicateOpfManifestPackagePath']);
        $t->same(['opf-manifest-declared', 'duplicate-opf-manifest-package-part', 'duplicate-opf-manifest-package-path', 'resource-kind-cover-image'], $cover['roles']);

        $t->same(false, $validation['valid']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same(2, $validation['manifest']['duplicatePartCount']);
        $t->same(2, $validation['manifest']['duplicateHrefTargetCount']);
        $t->same(2, $validation['manifest']['hrefSuffixCount']);
        $t->same(['/EPUB/text/chapter.xhtml', '/EPUB/images/cover.png'], array_column($validation['manifest']['duplicatePartItems'], 'partName'));
        $t->same(['chapter', 'chapter-review'], $validation['manifest']['duplicatePartItems'][0]['ids']);
        $t->same(['images/cover.png', 'images/cover.png?role=review'], $validation['manifest']['duplicatePartItems'][1]['hrefs']);
    },

    'reports duplicate OPF manifest package paths in inventory review handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithDuplicateInventoryPaths = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:duplicate-inventory-paths</dc:identifier>
    <dc:title>Duplicate inventory paths</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T11:45:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter-review" href="chapter.xhtml#review-note" media-type="application/xhtml+xml"/>
    <item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="cover-print" href="images/cover.png?rendition=print" media-type="image/png"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol><li><a href="chapter.xhtml">Inventory review</a></li></ol>
    </nav>
  </body>
</html>
XML;
        $chapterXml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Readable</h1></body></html>';

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithDuplicateInventoryPaths],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => $chapterXml],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $duplicates = $inventory['duplicateOpfManifestPackagePathItems'];
        $byPartName = $inventory['duplicateOpfManifestPackagePathItemsByPartName'];
        $chapter = $byPartName['/EPUB/chapter.xhtml'][0];
        $cover = $byPartName['/EPUB/images/cover.png'][0];
        $chapterEntry = $inventory['byPackagePath']['EPUB/chapter.xhtml'];

        $t->same(2, $inventory['duplicateOpfManifestPackagePathCount']);
        $t->same(2, $inventory['duplicateOpfManifestPackagePathPartCount']);
        $t->same(['/EPUB/chapter.xhtml', '/EPUB/images/cover.png'], $inventory['duplicateOpfManifestPackagePathPartNames']);
        $t->same(['/EPUB/chapter.xhtml'], $inventory['duplicateOpfManifestPackagePathExistingPartNames']);
        $t->same(2, $inventory['duplicateOpfManifestPackagePathDiagnosticCount']);
        $t->same(['duplicate-opf-manifest-package-path', 'duplicate-opf-manifest-package-path'], array_column($inventory['duplicateOpfManifestPackagePathDiagnostics'], 'type'));
        $t->same($duplicates, $summary['wordpressImport']['packageInventoryDuplicateOpfManifestPackagePathItems']);
        $t->same($inventory['duplicateOpfManifestPackagePathDiagnostics'], $summary['wordpressImport']['packageInventoryDuplicateOpfManifestPackagePathDiagnostics']);

        $t->same('EPUB/chapter.xhtml', $chapter['packagePath']);
        $t->same('/EPUB/chapter.xhtml', $chapter['partName']);
        $t->same(2, $chapter['manifestItemCount']);
        $t->same(['chapter', 'chapter-review'], $chapter['ids']);
        $t->same([1, 2], $chapter['indexes']);
        $t->same(['chapter.xhtml', 'chapter.xhtml#review-note'], $chapter['hrefs']);
        $t->same(['/EPUB/chapter.xhtml', '/EPUB/chapter.xhtml#review-note'], $chapter['targets']);
        $t->same(['application/xhtml+xml'], $chapter['mediaTypes']);
        $t->same(['application/xhtml+xml'], $chapter['mediaTypeBases']);
        $t->same(true, $chapter['exists']);
        $t->same(strlen($chapterXml), $chapter['byteLength']);
        $t->same(hash('crc32b', $chapterXml), $chapter['crc32']);
        $t->same(true, $chapter['canExposeBytes']);
        $t->same('epub-package-entry-metadata-only', $chapter['byteExposurePolicy']);
        $t->same('duplicate-opf-manifest-package-path', $chapter['diagnostics'][0]['type']);

        $t->same(true, $chapterEntry['duplicateOpfManifestPackagePath']);
        $t->same(2, $chapterEntry['manifestItemCount']);
        $t->same(['chapter', 'chapter-review'], $chapterEntry['manifestIds']);
        $t->true(in_array('duplicate-opf-manifest-package-path', $chapterEntry['roles'], true), 'duplicate package path role missing');
        $t->same(1, $inventory['roleCounts']['duplicate-opf-manifest-package-path']);

        $t->same('EPUB/images/cover.png', $cover['packagePath']);
        $t->same('/EPUB/images/cover.png', $cover['partName']);
        $t->same(['cover', 'cover-print'], $cover['ids']);
        $t->same([3, 4], $cover['indexes']);
        $t->same(['images/cover.png', 'images/cover.png?rendition=print'], $cover['hrefs']);
        $t->same(false, $cover['exists']);
        $t->same(null, $cover['byteLength']);
        $t->same(false, $cover['canExposeBytes']);
        $t->same('missing-opf-manifest-package-part-metadata-only', $cover['byteExposurePolicy']);
        $t->same(false, isset($inventory['byPackagePath']['EPUB/images/cover.png']));
    },

    'reports EPUB OCF ZIP package inventory roles without exposing payload bytes' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithInventoryResources = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="audio" href="audio/theme.mp3" media-type="audio/mpeg"/>
    <item id="font" href="fonts/source.otf" media-type="font/otf"/>',
            $epub3OpfXml
        );
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData>
      <CipherReference URI="EPUB/fonts/source.otf"/>
    </CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'META-INF/rights.xml', 'data' => '<rights xmlns="urn:oasis:names:tc:opendocument:xmlns:container"/>'],
            ['name' => 'META-INF/signatures.xml', 'data' => '<signatures xmlns="urn:oasis:names:tc:opendocument:xmlns:container"/>'],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithInventoryResources],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/audio/theme.mp3', 'data' => 'AUDIO'],
            ['name' => 'EPUB/fonts/source.otf', 'data' => 'OBFUSCATED-FONT'],
            ['name' => 'EPUB/notes/source.txt', 'data' => 'review note'],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $byPath = $inventory['byPackagePath'];
        $byDirectory = [];
        foreach ($inventory['directorySummaries'] as $directory) {
            $byDirectory[$directory['directory']] = $directory;
        }
        $byExtension = [];
        foreach ($inventory['extensionSummaries'] as $extension) {
            $byExtension[$extension['extension'] ?? '(none)'] = $extension;
        }

        $t->same($inventory, $summary['wordpressImport']['packageInventory']);
        $t->same(15, $inventory['entryCount']);
        $t->same(14, $inventory['fileEntryCount']);
        $t->same(1, $inventory['directoryEntryCount']);
        $t->same(7, $inventory['opfManifestDeclaredEntryCount']);
        $t->same(7, $inventory['opfManifestDeclaredPartCount']);
        $t->same(0, $inventory['missingOpfManifestDeclaredPartCount']);
        $t->same(2, $inventory['undeclaredEntryCount']);
        $t->same(2, $inventory['spineEntryCount']);
        $t->same(1, $inventory['encryptedEntryCount']);
        $t->same(1, $inventory['obfuscatedFontEntryCount']);
        $t->same(0, $inventory['unsupportedCompressionMethodCount']);
        $t->same('epub-package-inventory-metadata-only', $inventory['byteExposurePolicy']);
        $t->same(false, $inventory['canExposeBytes']);
        $t->same([
            'audio' => 1,
            'cover-image' => 1,
            'font' => 1,
            'navigation' => 1,
            'style' => 1,
            'xhtml' => 2,
        ], $inventory['resourceKindCounts']);
        $t->same([
            '/EPUB/nav.xhtml',
            '/EPUB/styles/book.css',
            '/EPUB/images/cover.png',
            '/EPUB/text/chapter1.xhtml',
            '/EPUB/text/chapter2.xhtml',
            '/EPUB/audio/theme.mp3',
            '/EPUB/fonts/source.otf',
        ], $inventory['opfManifestDeclaredPartNames']);
        $t->same(['/EPUB/text/', '/EPUB/notes/source.txt'], $inventory['undeclaredPartNames']);
        $t->same(['/EPUB/text/chapter1.xhtml', '/EPUB/text/chapter2.xhtml'], $inventory['spinePartNames']);
        $t->same(['/EPUB/fonts/source.otf'], $inventory['encryptedPartNames']);
        $t->same(['/EPUB/fonts/source.otf'], $inventory['obfuscatedFontPartNames']);
        $t->same('mimetype', $inventory['localPackagePaths'][0]);
        $t->same('EPUB/notes/source.txt', $inventory['centralPackagePaths'][14]);

        $t->same(['epub-mimetype', 'ocf-core'], $byPath['mimetype']['roles']);
        $t->same(['ocf-container', 'ocf-core', 'ocf-meta-inf'], $byPath['META-INF/container.xml']['roles']);
        $t->true(in_array('ocf-encryption-sidecar', $byPath['META-INF/encryption.xml']['roles'], true), 'encryption sidecar role missing');
        $t->true(in_array('ocf-rights-sidecar', $byPath['META-INF/rights.xml']['roles'], true), 'rights sidecar role missing');
        $t->true(in_array('ocf-signatures-sidecar', $byPath['META-INF/signatures.xml']['roles'], true), 'signatures sidecar role missing');
        $t->true(in_array('container-rootfile', $byPath['EPUB/package.opf']['roles'], true), 'rootfile role missing');
        $t->true(in_array('opf-package-document', $byPath['EPUB/package.opf']['roles'], true), 'OPF document role missing');
        $t->true(in_array('resource-kind-navigation', $byPath['EPUB/nav.xhtml']['roles'], true), 'navigation role missing');
        $t->true(in_array('spine-reading-order', $byPath['EPUB/text/chapter1.xhtml']['roles'], true), 'spine role missing');
        $t->true(in_array('zip-directory', $byPath['EPUB/text/']['roles'], true), 'directory role missing');
        $t->true(in_array('undeclared-package-entry', $byPath['EPUB/text/']['roles'], true), 'directory undeclared role missing');
        $t->true(in_array('undeclared-package-entry', $byPath['EPUB/notes/source.txt']['roles'], true), 'undeclared note role missing');
        $t->same('META-INF', $byPath['META-INF/container.xml']['directory']);
        $t->same(1, $byPath['META-INF/container.xml']['directoryDepth']);
        $t->same('container.xml', $byPath['META-INF/container.xml']['baseName']);
        $t->same('xml', $byPath['META-INF/container.xml']['extension']);
        $t->same('EPUB', $byPath['EPUB/text/']['directory']);
        $t->same('text', $byPath['EPUB/text/']['baseName']);
        $t->same(null, $byPath['EPUB/text/']['extension']);
        $t->same('/', $byPath['mimetype']['directory']);
        $t->same(null, $byPath['mimetype']['extension']);

        $t->same(9, $inventory['directoryCount']);
        $t->same(['/', 'EPUB', 'EPUB/audio', 'EPUB/fonts', 'EPUB/images', 'EPUB/notes', 'EPUB/styles', 'EPUB/text', 'META-INF'], $inventory['directories']);
        $t->same(3, $byDirectory['EPUB']['entryCount']);
        $t->same(1, $byDirectory['EPUB']['directoryEntryCount']);
        $t->same(['EPUB/package.opf', 'EPUB/nav.xhtml', 'EPUB/text/'], $byDirectory['EPUB']['packagePaths']);
        $t->same(1, $byDirectory['EPUB']['roleCounts']['opf-manifest-declared']);
        $t->same(1, $byDirectory['EPUB']['roleCounts']['undeclared-package-entry']);
        $t->same(1, $byDirectory['EPUB']['resourceKindCounts']['navigation']);
        $t->same(4, $byDirectory['META-INF']['entryCount']);
        $t->same(4, $byDirectory['META-INF']['roleCounts']['ocf-meta-inf']);
        $t->same(2, $byDirectory['EPUB/text']['spineEntryCount']);
        $t->same(2, $byDirectory['EPUB/text']['resourceKindCounts']['xhtml']);
        $t->same($byPath['EPUB/text/chapter1.xhtml']['byteLength'] + $byPath['EPUB/text/chapter2.xhtml']['byteLength'], $byDirectory['EPUB/text']['byteLength']);
        $t->same(1, $byDirectory['EPUB/fonts']['encryptedEntryCount']);
        $t->same(1, $byDirectory['EPUB/fonts']['obfuscatedFontEntryCount']);
        $t->same(9, $inventory['extensionCount']);
        $t->same([null, 'css', 'mp3', 'opf', 'otf', 'png', 'txt', 'xhtml', 'xml'], $inventory['extensions']);
        $t->same(2, $byExtension['(none)']['entryCount']);
        $t->same(1, $byExtension['(none)']['directoryEntryCount']);
        $t->same(['mimetype', 'EPUB/text/'], $byExtension['(none)']['packagePaths']);
        $t->same(3, $byExtension['xhtml']['entryCount']);
        $t->same(2, $byExtension['xhtml']['spineEntryCount']);
        $t->same(['navigation' => 1, 'xhtml' => 2], $byExtension['xhtml']['resourceKindCounts']);
        $t->same($byPath['EPUB/nav.xhtml']['byteLength'] + $byDirectory['EPUB/text']['byteLength'], $byExtension['xhtml']['byteLength']);
        $t->same(4, $byExtension['xml']['entryCount']);
        $t->same(['ocf-container' => 1, 'ocf-core' => 1, 'ocf-encryption-sidecar' => 1, 'ocf-meta-inf' => 4, 'ocf-rights-sidecar' => 1, 'ocf-sidecar' => 2, 'ocf-signatures-sidecar' => 1], $byExtension['xml']['roleCounts']);
        $t->same(1, $byExtension['otf']['encryptedEntryCount']);
        $t->same(1, $byExtension['otf']['obfuscatedFontEntryCount']);

        $font = $byPath['EPUB/fonts/source.otf'];
        $t->same('font', $font['resourceKind']);
        $t->same(['font'], $font['manifestIds']);
        $t->same(true, $font['encrypted']);
        $t->same(true, $font['obfuscatedFont']);
        $t->same(false, $font['canExposeBytes']);
        $t->same('obfuscated-font-bytes-blocked', $font['byteExposurePolicy']);
        $t->true(in_array('encrypted-resource', $font['roles'], true), 'encrypted resource role missing');
        $t->true(in_array('obfuscated-font', $font['roles'], true), 'obfuscated font role missing');
        $t->same(7, $inventory['roleCounts']['opf-manifest-declared']);
        $t->same(2, $inventory['roleCounts']['spine-reading-order']);
        $t->same(2, $inventory['roleCounts']['undeclared-package-entry']);
        $t->same(1, $inventory['roleCounts']['obfuscated-font']);
    },

    'summarizes EPUB package inventory byte buckets for review handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml, $buildZipPackage): void {
        $opfWithAudio = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="audio" href="audio/theme.mp3" media-type="audio/mpeg"/>',
            $epub3OpfXml
        );
        $chapter1 = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>';
        $chapter2 = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>';
        $cover = 'PNG';
        $audio = 'AUDIO-REVIEW-BYTES';
        $note = 'private review note';

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml, 'method' => 8],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithAudio, 'method' => 8],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml, 'method' => 8],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => $chapter1, 'method' => 8],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => $chapter2, 'method' => 8],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }', 'method' => 8],
            ['name' => 'EPUB/images/cover.png', 'data' => $cover, 'method' => 0],
            ['name' => 'EPUB/audio/theme.mp3', 'data' => $audio, 'method' => 12],
            ['name' => 'EPUB/notes/source.txt', 'data' => $note, 'method' => 0],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $manifestBytes = strlen($epub3NavXml)
            + strlen($chapter1)
            + strlen($chapter2)
            + strlen('body { font-family: serif; }')
            + strlen($cover)
            + strlen($audio);
        $manifestCompressedBytes = strlen(gzdeflate($epub3NavXml))
            + strlen(gzdeflate($chapter1))
            + strlen(gzdeflate($chapter2))
            + strlen(gzdeflate('body { font-family: serif; }'))
            + strlen($cover)
            + strlen($audio);
        $exposableBytes = $inventory['totalByteLength'] - strlen($audio);
        $exposableCompressedBytes = $inventory['totalCompressedByteLength'] - strlen($audio);

        $t->same($inventory, $summary['wordpressImport']['packageInventory']);
        $t->same(10, $inventory['entryCount']);
        $t->same(6, $inventory['opfManifestDeclaredEntryCount']);
        $t->same(1, $inventory['undeclaredEntryCount']);
        $t->same(1, $inventory['unsupportedCompressionMethodCount']);
        $t->same(9, $inventory['exposableEntryCount']);
        $t->same(1, $inventory['blockedEntryCount']);
        $t->same($exposableBytes, $inventory['exposableByteLength']);
        $t->same($exposableCompressedBytes, $inventory['exposableCompressedByteLength']);
        $t->same(strlen($audio), $inventory['blockedByteLength']);
        $t->same(strlen($audio), $inventory['blockedCompressedByteLength']);
        $t->same(strlen($audio), $inventory['unsupportedCompressionByteLength']);
        $t->same(strlen($audio), $inventory['unsupportedCompressionCompressedByteLength']);
        $t->same($manifestBytes, $inventory['roleByteLengths']['opf-manifest-declared']);
        $t->same($manifestCompressedBytes, $inventory['roleCompressedByteLengths']['opf-manifest-declared']);
        $t->same(strlen($note), $inventory['roleByteLengths']['undeclared-package-entry']);
        $t->same(strlen($note), $inventory['roleCompressedByteLengths']['undeclared-package-entry']);
        $t->same(strlen($audio), $inventory['resourceKindByteLengths']['audio']);
        $t->same(strlen($audio), $inventory['resourceKindCompressedByteLengths']['audio']);
        $t->same(strlen($cover), $inventory['resourceKindByteLengths']['cover-image']);
        $t->same(strlen($cover), $inventory['resourceKindCompressedByteLengths']['cover-image']);
        $t->same(strlen($chapter1) + strlen($chapter2), $inventory['resourceKindByteLengths']['xhtml']);
        $t->same(strlen(gzdeflate($chapter1)) + strlen(gzdeflate($chapter2)), $inventory['resourceKindCompressedByteLengths']['xhtml']);
        $t->same(['/EPUB/audio/theme.mp3'], $inventory['unsupportedCompressionPartNames']);
        $t->same(false, $inventory['byPackagePath']['EPUB/audio/theme.mp3']['canExposeBytes']);
        $t->same('unsupported', $inventory['byPackagePath']['EPUB/audio/theme.mp3']['compressionMethodName']);
        $t->same('audio', $inventory['byPackagePath']['EPUB/audio/theme.mp3']['resourceKind']);
    },

    'reports EPUB package inventory local header order drift for review handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $chapter = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Order drift</h1></body></html>';
        $navXml = '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="text/chapter.xhtml">Order drift</a></li></ol></nav></body></html>';
        $opfWithOrderDrift = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:package-local-order-drift</dc:identifier>
    <dc:title>Package Local Order Drift</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0, 'centralIndex' => 5],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml, 'centralIndex' => 0],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithOrderDrift, 'centralIndex' => 1],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml, 'centralIndex' => 2],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => $chapter, 'centralIndex' => 3],
            ['name' => 'EPUB/notes/reviewer.txt', 'data' => 'local order drift note', 'method' => 0, 'centralIndex' => 4],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $order = $inventory['localHeaderOrder'];
        $diagnostics = $inventory['localHeaderOrderDiagnostics'];

        $t->same($inventory, $summary['wordpressImport']['packageInventory']);
        $t->same($order, $summary['wordpressImport']['packageInventoryLocalHeaderOrder']);
        $t->same($diagnostics, $summary['wordpressImport']['packageInventoryLocalHeaderOrderDiagnostics']);
        $t->same($diagnostics, $summary['wordpressImport']['packageInventoryDiagnostics']);
        $t->same(['mimetype', 'META-INF/container.xml', 'EPUB/package.opf', 'EPUB/nav.xhtml', 'EPUB/text/chapter.xhtml', 'EPUB/notes/reviewer.txt'], $order['localHeaderOrderNames']);
        $t->same(['META-INF/container.xml', 'EPUB/package.opf', 'EPUB/nav.xhtml', 'EPUB/text/chapter.xhtml', 'EPUB/notes/reviewer.txt', 'mimetype'], $order['centralDirectoryOrderNames']);
        $t->same(true, $order['hasCentralDirectoryOrderMismatch']);
        $t->same(true, $inventory['hasCentralDirectoryOrderMismatch']);
        $t->same(6, $order['mismatchedEntryCount']);
        $t->same(6, $inventory['centralDirectoryOrderMismatchCount']);
        $t->same(6, $inventory['diagnosticCount']);
        $t->same(['central-directory-local-header-order-mismatch'], $inventory['diagnosticTypes']);
        $t->same([
            '/META-INF/container.xml',
            '/EPUB/package.opf',
            '/EPUB/nav.xhtml',
            '/EPUB/text/chapter.xhtml',
            '/EPUB/notes/reviewer.txt',
            '/mimetype',
        ], $inventory['centralDirectoryOrderMismatchedPartNames']);
        $t->same('META-INF/container.xml', $diagnostics[0]['packagePath']);
        $t->same('/META-INF/container.xml', $diagnostics[0]['partName']);
        $t->same(0, $diagnostics[0]['centralDirectoryIndex']);
        $t->same(1, $diagnostics[0]['localHeaderOrder']);
        $t->same('mimetype', $diagnostics[0]['localHeaderNameAtCentralDirectoryIndex']);
        $t->same('EPUB/package.opf', $diagnostics[0]['centralDirectoryNameAtLocalHeaderOrder']);
        $t->same('central-directory-local-header-order-mismatch', $diagnostics[5]['type']);
        $t->same('/mimetype', $diagnostics[5]['partName']);
        $t->same(5, $diagnostics[5]['centralDirectoryIndex']);
        $t->same(0, $diagnostics[5]['localHeaderOrder']);
        $t->same('EPUB/notes/reviewer.txt', $diagnostics[5]['localHeaderNameAtCentralDirectoryIndex']);
        $t->same('META-INF/container.xml', $diagnostics[5]['centralDirectoryNameAtLocalHeaderOrder']);
        $t->same(0, $inventory['byPackagePath']['META-INF/container.xml']['index']);
        $t->same(1, $inventory['byPackagePath']['META-INF/container.xml']['localOrder']);
        $t->same(5, $inventory['byPackagePath']['mimetype']['index']);
        $t->same(0, $inventory['byPackagePath']['mimetype']['localOrder']);
        $t->same(false, $order['entries'][0]['matchesCentralDirectoryOrder']);
        $t->same('central-directory-local-header-order-mismatch', $diagnostics[0]['type']);
    },

    'summarizes EPUB package inventory byte exposure policy buckets for review handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $chapter = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Policy buckets</h1></body></html>';
        $navXml = '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><h1>Contents</h1><ol><li><a href="text/chapter.xhtml">Policy buckets</a></li></ol></nav></body></html>';
        $audio = 'AUDIO-UNSUPPORTED-COMPRESSION';
        $font = 'OBFUSCATED-FONT-PAYLOAD';
        $note = 'undeclared reviewer note';
        $opfWithPolicies = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:package-policy-buckets</dc:identifier>
    <dc:title>Package Policy Buckets</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-15T05:09:22Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="audio" href="audio/theme.mp3" media-type="audio/mpeg"/>
    <item id="font" href="fonts/source.otf" media-type="font/otf"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData><CipherReference URI="EPUB/fonts/source.otf"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml, 'method' => 8],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml, 'method' => 8],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithPolicies, 'method' => 8],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml, 'method' => 8],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => $chapter, 'method' => 8],
            ['name' => 'EPUB/audio/theme.mp3', 'data' => $audio, 'method' => 12],
            ['name' => 'EPUB/fonts/source.otf', 'data' => $font, 'method' => 8],
            ['name' => 'EPUB/notes/source.txt', 'data' => $note, 'method' => 0],
        ]));

        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $byDirectory = [];
        foreach ($inventory['directorySummaries'] as $directory) {
            $byDirectory[$directory['directory']] = $directory;
        }
        $byExtension = [];
        foreach ($inventory['extensionSummaries'] as $extension) {
            $byExtension[$extension['extension'] ?? '(none)'] = $extension;
        }

        $metadataOnlyBytes = strlen(EpubPackage::EPUB_MIMETYPE)
            + strlen($epubContainerXml)
            + strlen($encryptionXml)
            + strlen($opfWithPolicies)
            + strlen($navXml)
            + strlen($chapter)
            + strlen($note);
        $metadataOnlyCompressedBytes = strlen(EpubPackage::EPUB_MIMETYPE)
            + strlen(gzdeflate($epubContainerXml))
            + strlen(gzdeflate($encryptionXml))
            + strlen(gzdeflate($opfWithPolicies))
            + strlen(gzdeflate($navXml))
            + strlen(gzdeflate($chapter))
            + strlen($note);

        $t->same($inventory, $summary['wordpressImport']['packageInventory']);
        $t->same([
            'epub-package-entry-metadata-only' => 7,
            'obfuscated-font-bytes-blocked' => 1,
            'unsupported-compression-metadata-only' => 1,
        ], $inventory['byteExposurePolicyCounts']);
        $t->same([
            'epub-package-entry-metadata-only' => $metadataOnlyBytes,
            'obfuscated-font-bytes-blocked' => strlen($font),
            'unsupported-compression-metadata-only' => strlen($audio),
        ], $inventory['byteExposurePolicyByteLengths']);
        $t->same([
            'epub-package-entry-metadata-only' => $metadataOnlyCompressedBytes,
            'obfuscated-font-bytes-blocked' => strlen(gzdeflate($font)),
            'unsupported-compression-metadata-only' => strlen($audio),
        ], $inventory['byteExposurePolicyCompressedByteLengths']);

        $audioEntry = $inventory['byPackagePath']['EPUB/audio/theme.mp3'];
        $fontEntry = $inventory['byPackagePath']['EPUB/fonts/source.otf'];
        $t->same(false, $audioEntry['canExposeBytes']);
        $t->same('unsupported-compression-metadata-only', $audioEntry['byteExposurePolicy']);
        $t->same(false, $fontEntry['canExposeBytes']);
        $t->same(true, $fontEntry['obfuscatedFont']);
        $t->same('obfuscated-font-bytes-blocked', $fontEntry['byteExposurePolicy']);

        $t->same(['unsupported-compression-metadata-only' => 1], $byDirectory['EPUB/audio']['byteExposurePolicyCounts']);
        $t->same(strlen($audio), $byDirectory['EPUB/audio']['byteExposurePolicyByteLengths']['unsupported-compression-metadata-only']);
        $t->same(['obfuscated-font-bytes-blocked' => 1], $byDirectory['EPUB/fonts']['byteExposurePolicyCounts']);
        $t->same(strlen($font), $byDirectory['EPUB/fonts']['byteExposurePolicyByteLengths']['obfuscated-font-bytes-blocked']);
        $t->same(['unsupported-compression-metadata-only' => 1], $byExtension['mp3']['byteExposurePolicyCounts']);
        $t->same(strlen($audio), $byExtension['mp3']['byteExposurePolicyCompressedByteLengths']['unsupported-compression-metadata-only']);
        $t->same(['obfuscated-font-bytes-blocked' => 1], $byExtension['otf']['byteExposurePolicyCounts']);
        $t->same(strlen(gzdeflate($font)), $byExtension['otf']['byteExposurePolicyCompressedByteLengths']['obfuscated-font-bytes-blocked']);
    },

    'summarizes EPUB package inventory ZIP compression method buckets for review handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $chapter = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Compression buckets</h1></body></html>';
        $navXml = '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">Compression buckets</a></li></ol></nav></body></html>';
        $cover = 'PNG-COVER';
        $audio = 'AUDIO-UNSUPPORTED';
        $note = 'review note';
        $opfWithCompressionBuckets = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:package-compression-buckets</dc:identifier>
    <dc:title>Package Compression Buckets</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="audio" href="audio/theme.mp3" media-type="audio/mpeg"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml, 'method' => 8],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithCompressionBuckets, 'method' => 8],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml, 'method' => 8],
            ['name' => 'EPUB/chapter.xhtml', 'data' => $chapter, 'method' => 8],
            ['name' => 'EPUB/images/cover.png', 'data' => $cover, 'method' => 0],
            ['name' => 'EPUB/audio/theme.mp3', 'data' => $audio, 'method' => 12],
            ['name' => 'EPUB/notes/source.txt', 'data' => $note, 'method' => 0],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $byDirectory = [];
        foreach ($inventory['directorySummaries'] as $directory) {
            $byDirectory[$directory['directory']] = $directory;
        }
        $byExtension = [];
        foreach ($inventory['extensionSummaries'] as $extension) {
            $byExtension[$extension['extension'] ?? '(none)'] = $extension;
        }
        $deflatedBytes = strlen($epubContainerXml) + strlen($opfWithCompressionBuckets) + strlen($navXml) + strlen($chapter);
        $deflatedCompressedBytes = strlen(gzdeflate($epubContainerXml))
            + strlen(gzdeflate($opfWithCompressionBuckets))
            + strlen(gzdeflate($navXml))
            + strlen(gzdeflate($chapter));
        $storedBytes = strlen(EpubPackage::EPUB_MIMETYPE) + strlen($cover) + strlen($note);

        $t->same($inventory, $summary['wordpressImport']['packageInventory']);
        $t->same([
            'deflated' => 4,
            'stored' => 3,
            'unsupported' => 1,
        ], $inventory['compressionMethodCounts']);
        $t->same([
            'deflated' => $deflatedBytes,
            'stored' => $storedBytes,
            'unsupported' => strlen($audio),
        ], $inventory['compressionMethodByteLengths']);
        $t->same([
            'deflated' => $deflatedCompressedBytes,
            'stored' => $storedBytes,
            'unsupported' => strlen($audio),
        ], $inventory['compressionMethodCompressedByteLengths']);
        $t->same(['deflated' => 1], $byDirectory['META-INF']['compressionMethodCounts']);
        $t->same(['deflated' => 3], $byDirectory['EPUB']['compressionMethodCounts']);
        $t->same(['unsupported' => 1], $byDirectory['EPUB/audio']['compressionMethodCounts']);
        $t->same(['stored' => 1], $byDirectory['EPUB/images']['compressionMethodCounts']);
        $t->same(['stored' => 1], $byExtension['(none)']['compressionMethodCounts']);
        $t->same(['deflated' => 2], $byExtension['xhtml']['compressionMethodCounts']);
        $t->same($deflatedCompressedBytes, array_sum($inventory['compressionMethodCompressedByteLengths']) - $storedBytes - strlen($audio));
        $t->same(strlen($audio), $byExtension['mp3']['compressionMethodByteLengths']['unsupported']);
        $t->same(strlen($cover), $byExtension['png']['compressionMethodCompressedByteLengths']['stored']);
        $t->same('unsupported', $inventory['byPackagePath']['EPUB/audio/theme.mp3']['compressionMethodName']);
        $t->same('stored', $inventory['byPackagePath']['EPUB/images/cover.png']['compressionMethodName']);
    },

    'summarizes EPUB reading order ZIP byte provenance for compact handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $chapter = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Readable</h1></body></html>';
        $locked = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Locked</h1></body></html>';
        $packed = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Packed</h1></body></html>';
        $opfWithReadingOrder = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:reading-order-inventory</dc:identifier>
    <dc:title>Reading Order Inventory</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="locked" href="text/locked.xhtml" media-type="application/xhtml+xml"/>
    <item id="packed" href="text/packed.xhtml" media-type="application/xhtml+xml"/>
    <item id="missing" href="text/missing.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="locked"/>
    <itemref idref="packed" linear="no"/>
    <itemref idref="missing"/>
    <itemref idref="absent"/>
  </spine>
</package>
XML;
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/text/locked.xhtml"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithReadingOrder],
            ['name' => 'EPUB/nav.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><h1>Contents</h1><ol><li><a href="text/chapter.xhtml">Readable</a></li></ol></nav></body></html>'],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => $chapter, 'method' => 8],
            ['name' => 'EPUB/text/locked.xhtml', 'data' => $locked, 'method' => 8],
            ['name' => 'EPUB/text/packed.xhtml', 'data' => $packed, 'method' => 12],
        ]));
        $summary = $epub->summary();
        $report = $summary['readingOrderInventory'];
        $byIdref = $report['itemsByIdref'];
        $chapterRow = $byIdref['chapter'][0];
        $lockedRow = $byIdref['locked'][0];
        $packedRow = $byIdref['packed'][0];
        $missingRow = $byIdref['missing'][0];
        $absentRow = $byIdref['absent'][0];

        $t->same($report, $summary['wordpressImport']['readingOrderInventory']);
        $t->same(5, $report['itemCount']);
        $t->same(4, $report['linearItemCount']);
        $t->same(1, $report['nonLinearItemCount']);
        $t->same(3, $report['existingItemCount']);
        $t->same(2, $report['missingItemCount']);
        $t->same(1, $report['missingPackagePartCount']);
        $t->same(1, $report['manifestItemMissingCount']);
        $t->same(1, $report['encryptedItemCount']);
        $t->same(1, $report['unsupportedCompressionItemCount']);
        $t->same(1, $report['exposableItemCount']);
        $t->same(4, $report['blockedItemCount']);
        $t->same(strlen($chapter) + strlen($locked) + strlen($packed), $report['totalByteLength']);
        $t->same(strlen(gzdeflate($chapter)) + strlen(gzdeflate($locked)) + strlen($packed), $report['totalCompressedByteLength']);
        $t->same(strlen($chapter), $report['exposableByteLength']);
        $t->same(strlen(gzdeflate($chapter)), $report['exposableCompressedByteLength']);
        $t->same(strlen($locked) + strlen($packed), $report['blockedByteLength']);
        $t->same(strlen(gzdeflate($locked)) + strlen($packed), $report['blockedCompressedByteLength']);
        $t->same(strlen($packed), $report['unsupportedCompressionByteLength']);
        $t->same(strlen($packed), $report['unsupportedCompressionCompressedByteLength']);
        $t->same([
            'encrypted-resource-bytes-blocked' => 1,
            'missing-spine-manifest-item-metadata-only' => 1,
            'missing-spine-package-part-metadata-only' => 1,
            'spine-content-bytes-exposable' => 1,
            'unsupported-compression-metadata-only' => 1,
        ], $report['byteExposurePolicyCounts']);
        $t->same(['deflated' => 2, 'unsupported' => 1], $report['compressionMethodCounts']);
        $t->same(['/EPUB/text/missing.xhtml'], $report['missingPartNames']);
        $t->same(['/EPUB/text/locked.xhtml'], $report['encryptedPartNames']);
        $t->same(['/EPUB/text/packed.xhtml'], $report['unsupportedCompressionPartNames']);
        $t->same(4, $report['diagnosticCount']);
        $t->same([
            'encrypted-spine-package-part',
            'unsupported-spine-package-compression',
            'missing-spine-package-part',
            'missing-spine-manifest-item',
        ], $report['diagnosticTypes']);

        $t->same(true, $chapterRow['exists']);
        $t->same(true, $chapterRow['canExposeBytes']);
        $t->same('spine-content-bytes-exposable', $chapterRow['byteExposurePolicy']);
        $t->same(strlen($chapter), $chapterRow['byteLength']);
        $t->same('deflated', $chapterRow['compressionMethodName']);
        $t->same(false, $lockedRow['canExposeBytes']);
        $t->same(true, $lockedRow['encrypted']);
        $t->same('encrypted-resource-bytes-blocked', $lockedRow['byteExposurePolicy']);
        $t->same('encrypted-spine-package-part', $lockedRow['diagnostics'][0]['type']);
        $t->same(false, $packedRow['linear']);
        $t->same(true, $packedRow['unsupportedCompression']);
        $t->same('unsupported', $packedRow['compressionMethodName']);
        $t->same('unsupported-compression-metadata-only', $packedRow['byteExposurePolicy']);
        $t->same(false, $missingRow['exists']);
        $t->same('missing-spine-package-part-metadata-only', $missingRow['byteExposurePolicy']);
        $t->same('missing-spine-package-part', $missingRow['diagnostics'][0]['type']);
        $t->same(true, $absentRow['manifestItemMissing']);
        $t->same(null, $absentRow['packagePath']);
        $t->same('missing-spine-manifest-item-metadata-only', $absentRow['byteExposurePolicy']);
    },

    'summarizes EPUB manifest dependency ZIP provenance for compact handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $chapter = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="intro">Chapter</h1></body></html>';
        $chapterMissingOverlay = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Missing overlay</h1></body></html>';
        $widget = 'WIDGET';
        $stylelessWidget = 'STYLELESS';
        $fallback = '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Fallback package content.</p></body></html>';
        $widgetCss = 'body { color: #446688; }';
        $nav = '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">Chapter</a></li></ol></nav></body></html>';
        $overlay = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL">
  <body>
    <seq>
      <par id="intro">
        <text src="../chapter.xhtml#intro"/>
        <audio src="../audio/chapter.mp3"/>
      </par>
    </seq>
  </body>
</smil>
XML;
        $opfWithDependencies = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:manifest-dependency-inventory</dc:identifier>
    <dc:title>Manifest Dependency Inventory</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter"/>
    <item id="chapter-missing-overlay" href="chapter-missing-overlay.xhtml" media-type="application/xhtml+xml" media-overlay="missing-overlay"/>
    <item id="widget" href="widgets/review.bin" media-type="application/x-review-widget" fallback="fallback-html" fallback-style="widget-css"/>
    <item id="styleless-widget" href="widgets/styleless.bin" media-type="application/x-review-widget" fallback-style="missing-css"/>
    <item id="fallback-html" href="fallback.xhtml" media-type="application/xhtml+xml"/>
    <item id="widget-css" href="styles/widget.css" media-type="text/css"/>
    <item id="mo-chapter" href="overlays/chapter.smil" media-type="application/smil+xml"/>
    <item id="chapter-audio" href="audio/chapter.mp3" media-type="audio/mpeg"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="chapter-missing-overlay"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithDependencies],
            ['name' => 'EPUB/nav.xhtml', 'data' => $nav],
            ['name' => 'EPUB/chapter.xhtml', 'data' => $chapter],
            ['name' => 'EPUB/chapter-missing-overlay.xhtml', 'data' => $chapterMissingOverlay],
            ['name' => 'EPUB/widgets/review.bin', 'data' => $widget, 'method' => 12],
            ['name' => 'EPUB/widgets/styleless.bin', 'data' => $stylelessWidget],
            ['name' => 'EPUB/fallback.xhtml', 'data' => $fallback],
            ['name' => 'EPUB/styles/widget.css', 'data' => $widgetCss, 'method' => 12],
            ['name' => 'EPUB/overlays/chapter.smil', 'data' => $overlay],
            ['name' => 'EPUB/audio/chapter.mp3', 'data' => 'AUDIO'],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['manifestDependencyInventory'];
        $widgetFallback = $inventory['edgesBySourceId']['widget'][0];
        $widgetStyle = $inventory['edgesBySourceId']['widget'][1];
        $chapterOverlay = $inventory['edgesBySourceId']['chapter'][0];
        $missingStyle = $inventory['edgesBySourceId']['styleless-widget'][0];
        $missingOverlay = $inventory['edgesBySourceId']['chapter-missing-overlay'][0];

        $t->same($inventory, $summary['wordpressImport']['manifestDependencyInventory']);
        $t->same($inventory['edges'], $summary['wordpressImport']['manifestDependencyEdges']);
        $t->same($inventory['diagnostics'], $summary['wordpressImport']['manifestDependencyDiagnostics']);
        $t->same(true, $inventory['present']);
        $t->same(5, $inventory['edgeCount']);
        $t->same(1, $inventory['fallbackEdgeCount']);
        $t->same(2, $inventory['fallbackStyleEdgeCount']);
        $t->same(2, $inventory['mediaOverlayEdgeCount']);
        $t->same(3, $inventory['manifestTargetCount']);
        $t->same(3, $inventory['existingTargetCount']);
        $t->same(2, $inventory['missingManifestTargetCount']);
        $t->same(0, $inventory['missingPackagePartTargetCount']);
        $t->same(1, $inventory['unsupportedCompressionTargetCount']);
        $t->same(2, $inventory['exposableTargetCount']);
        $t->same(3, $inventory['blockedTargetCount']);
        $t->same(5, $inventory['sourceExistingEdgeCount']);
        $t->same(2, $inventory['sourceUnsupportedCompressionEdgeCount']);
        $t->same(3, $inventory['sourceExposableEdgeCount']);
        $t->same(2, $inventory['sourceBlockedEdgeCount']);
        $t->same(
            strlen($chapter) + strlen($chapterMissingOverlay) + (2 * strlen($widget)) + strlen($stylelessWidget),
            $inventory['sourceTotalByteLength']
        );
        $t->same(
            strlen(gzdeflate($chapter)) + strlen(gzdeflate($chapterMissingOverlay)) + (2 * strlen($widget)) + strlen(gzdeflate($stylelessWidget)),
            $inventory['sourceTotalCompressedByteLength']
        );
        $t->same(strlen($chapter) + strlen($chapterMissingOverlay) + strlen($stylelessWidget), $inventory['sourceExposableByteLength']);
        $t->same(strlen(gzdeflate($chapter)) + strlen(gzdeflate($chapterMissingOverlay)) + strlen(gzdeflate($stylelessWidget)), $inventory['sourceExposableCompressedByteLength']);
        $t->same(2 * strlen($widget), $inventory['sourceBlockedByteLength']);
        $t->same(2 * strlen($widget), $inventory['sourceBlockedCompressedByteLength']);
        $t->same(strlen($fallback) + strlen($widgetCss) + strlen($overlay), $inventory['totalByteLength']);
        $t->same(strlen(gzdeflate($fallback)) + strlen($widgetCss) + strlen(gzdeflate($overlay)), $inventory['totalCompressedByteLength']);
        $t->same(strlen($fallback) + strlen($overlay), $inventory['exposableByteLength']);
        $t->same(strlen(gzdeflate($fallback)) + strlen(gzdeflate($overlay)), $inventory['exposableCompressedByteLength']);
        $t->same(strlen($widgetCss), $inventory['blockedByteLength']);
        $t->same(strlen($widgetCss), $inventory['blockedCompressedByteLength']);
        $t->same([
            'fallback' => 1,
            'fallback-style' => 2,
            'media-overlay' => 2,
        ], $inventory['relationCounts']);
        $t->same([
            'manifest-dependency-target-bytes-exposable' => 2,
            'missing-manifest-dependency-target-metadata-only' => 2,
            'unsupported-compression-metadata-only' => 1,
        ], $inventory['byteExposurePolicyCounts']);
        $t->same([
            'manifest-dependency-source-bytes-exposable' => 3,
            'unsupported-compression-metadata-only' => 2,
        ], $inventory['sourceByteExposurePolicyCounts']);
        $t->same(['deflated' => 2, 'unsupported' => 1], $inventory['compressionMethodCounts']);
        $t->same(['deflated' => 3, 'unsupported' => 2], $inventory['sourceCompressionMethodCounts']);
        $t->same(['mo-chapter', 'missing-overlay', 'fallback-html', 'widget-css', 'missing-css'], $inventory['targetIds']);
        $t->same(['missing-overlay', 'missing-css'], $inventory['missingManifestTargetIds']);
        $t->same(['/EPUB/widgets/review.bin'], $inventory['sourceUnsupportedCompressionPartNames']);
        $t->same(['/EPUB/styles/widget.css'], $inventory['unsupportedCompressionTargetPartNames']);
        $t->same([
            'missing-manifest-dependency-target',
            'missing-media-overlay-manifest-item',
            'unsupported-manifest-dependency-compression',
            'unreadable-manifest-fallback-style-package-part',
            'missing-manifest-fallback-style-item',
        ], $inventory['diagnosticTypes']);

        $t->same('fallback', $widgetFallback['relation']);
        $t->same(true, $widgetFallback['sourceExists']);
        $t->same(true, $widgetFallback['sourceUnsupportedCompression']);
        $t->same('unsupported', $widgetFallback['sourceCompressionMethodName']);
        $t->same(false, $widgetFallback['sourceCanExposeBytes']);
        $t->same('unsupported-compression-metadata-only', $widgetFallback['sourceByteExposurePolicy']);
        $t->same(strlen($widget), $widgetFallback['sourceByteLength']);
        $t->same('fallback-html', $widgetFallback['targetId']);
        $t->same('/EPUB/fallback.xhtml', $widgetFallback['targetPartName']);
        $t->same(true, $widgetFallback['targetCanExposeBytes']);
        $t->same('manifest-dependency-target-bytes-exposable', $widgetFallback['targetByteExposurePolicy']);
        $t->same(strlen($fallback), $widgetFallback['targetByteLength']);
        $t->same('fallback-html', $widgetFallback['relationTerminalId']);
        $t->same(true, $widgetFallback['relationUsable']);

        $t->same('fallback-style', $widgetStyle['relation']);
        $t->same('/EPUB/widgets/review.bin', $widgetStyle['sourcePartName']);
        $t->same('unsupported-compression-metadata-only', $widgetStyle['sourceByteExposurePolicy']);
        $t->same('widget-css', $widgetStyle['targetId']);
        $t->same(true, $widgetStyle['targetUnsupportedCompression']);
        $t->same('unsupported', $widgetStyle['targetCompressionMethodName']);
        $t->same('unsupported-compression-metadata-only', $widgetStyle['targetByteExposurePolicy']);
        $t->same(false, $widgetStyle['relationUsable']);

        $t->same('media-overlay', $chapterOverlay['relation']);
        $t->same(true, $chapterOverlay['sourceCanExposeBytes']);
        $t->same('manifest-dependency-source-bytes-exposable', $chapterOverlay['sourceByteExposurePolicy']);
        $t->same('mo-chapter', $chapterOverlay['targetId']);
        $t->same('/EPUB/overlays/chapter.smil', $chapterOverlay['targetPartName']);
        $t->same('media-overlay', $chapterOverlay['targetResourceKind']);
        $t->same(true, $chapterOverlay['relationResolved']);
        $t->same('manifest-dependency-target-bytes-exposable', $chapterOverlay['targetByteExposurePolicy']);

        $t->same('missing-css', $missingStyle['targetId']);
        $t->same(false, $missingStyle['targetPresentInManifest']);
        $t->same('missing-manifest-dependency-target-metadata-only', $missingStyle['targetByteExposurePolicy']);
        $t->same('missing-manifest-dependency-target', $missingStyle['diagnostics'][0]['type']);
        $t->same('missing-manifest-fallback-style-item', $missingStyle['diagnostics'][1]['type']);

        $t->same('missing-overlay', $missingOverlay['targetId']);
        $t->same(false, $missingOverlay['targetPresentInManifest']);
        $t->same(false, $missingOverlay['relationResolved']);
        $t->same('missing-media-overlay-manifest-item', $missingOverlay['diagnostics'][1]['type']);
    },

    'marks EPUB media overlay package inventory roles for compact handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $chapter = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="intro">Overlay chapter</h1></body></html>';
        $audio = 'AUDIO-OVERLAY-PAYLOAD';
        $overlay = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL">
  <body>
    <seq id="chapter-seq">
      <par id="intro-audio">
        <text src="../text/chapter.xhtml#intro"/>
        <audio src="../audio/chapter.mp3?clip=main#t=1,4" clipBegin="0:00:01.000" clipEnd="0:00:04.000"/>
      </par>
    </seq>
  </body>
</smil>
XML;
        $opfWithOverlayInventory = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:media-overlay-package-inventory</dc:identifier>
    <dc:title>Media Overlay Package Inventory</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" media-overlay="mo"/>
    <item id="mo" href="overlays/chapter.smil" media-type="application/smil+xml"/>
    <item id="audio" href="audio/chapter.mp3" media-type="audio/mpeg"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithOverlayInventory],
            ['name' => 'EPUB/nav.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="text/chapter.xhtml">Overlay chapter</a></li></ol></nav></body></html>'],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => $chapter],
            ['name' => 'EPUB/overlays/chapter.smil', 'data' => $overlay],
            ['name' => 'EPUB/audio/chapter.mp3', 'data' => $audio],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $byPath = $inventory['byPackagePath'];
        $dependencies = $summary['manifestDependencyInventory'];
        $audioDirectory = null;
        foreach ($inventory['directorySummaries'] as $directory) {
            if (($directory['directory'] ?? null) === 'EPUB/audio') {
                $audioDirectory = $directory;
                break;
            }
        }

        $t->same($inventory, $summary['wordpressImport']['packageInventory']);
        $t->same(['/EPUB/text/chapter.xhtml', '/EPUB/overlays/chapter.smil', '/EPUB/audio/chapter.mp3'], $inventory['mediaOverlayPartNames']);
        $t->same(['/EPUB/overlays/chapter.smil'], $inventory['mediaOverlayDocumentPartNames']);
        $t->same(['/EPUB/text/chapter.xhtml'], $inventory['mediaOverlaySourcePartNames']);
        $t->same(['/EPUB/text/chapter.xhtml'], $inventory['mediaOverlayTextTargetPartNames']);
        $t->same(['/EPUB/audio/chapter.mp3'], $inventory['mediaOverlayAudioTargetPartNames']);
        $t->same(1, $inventory['roleCounts']['media-overlay-document']);
        $t->same(1, $inventory['roleCounts']['media-overlay-source']);
        $t->same(1, $inventory['roleCounts']['media-overlay-text-target']);
        $t->same(1, $inventory['roleCounts']['media-overlay-audio-target']);

        $chapterEntry = $byPath['EPUB/text/chapter.xhtml'];
        $overlayEntry = $byPath['EPUB/overlays/chapter.smil'];
        $audioEntry = $byPath['EPUB/audio/chapter.mp3'];
        $t->same(['media-overlay-source', 'media-overlay-text-target'], $chapterEntry['mediaOverlayRoles']);
        $t->same(['mo'], $chapterEntry['mediaOverlaySourceForIds']);
        $t->same(['mo'], $chapterEntry['mediaOverlayTextTargetForIds']);
        $t->same(['media-overlay-document'], $overlayEntry['mediaOverlayRoles']);
        $t->same(['mo'], $overlayEntry['mediaOverlayIds']);
        $t->same(['chapter'], $overlayEntry['mediaOverlayReferencedByIds']);
        $t->same(['media-overlay-audio-target'], $audioEntry['mediaOverlayRoles']);
        $t->same(['mo'], $audioEntry['mediaOverlayAudioTargetForIds']);
        $t->true(in_array('media-overlay-source', $chapterEntry['roles'], true), 'media overlay source package role missing');
        $t->true(in_array('media-overlay-text-target', $chapterEntry['roles'], true), 'media overlay text target package role missing');
        $t->true(in_array('media-overlay-document', $overlayEntry['roles'], true), 'media overlay document package role missing');
        $t->true(in_array('media-overlay-audio-target', $audioEntry['roles'], true), 'media overlay audio target package role missing');
        $t->same('media-overlay', $overlayEntry['resourceKind']);
        $t->same('audio', $audioEntry['resourceKind']);
        $t->same(1, $dependencies['mediaOverlayEdgeCount']);
        $t->same('mo', $dependencies['edgesBySourceId']['chapter'][0]['targetId']);
        $t->same(1, $audioDirectory['roleCounts']['media-overlay-audio-target']);
        $t->same(strlen($audio), $audioDirectory['byteLength']);
    },

    'includes OPF binding handlers in manifest dependency inventory handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $handlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Local handler</h1></body></html>';
        $opfWithBindingDependencies = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:binding-dependency-inventory</dc:identifier>
    <dc:title>Binding Dependency Inventory</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="local-handler" href="widgets/local-handler.xhtml" media-type="application/xhtml+xml" properties="scripted"/>
    <item id="remote-handler" href="https://cdn.example.invalid/widgets/remote-handler.xhtml" media-type="application/xhtml+xml" properties="scripted remote-resources"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
  <bindings>
    <mediaType media-type="application/x-local-widget" handler="local-handler"/>
    <mediaType media-type="application/x-remote-widget" handler="remote-handler"/>
    <mediaType media-type="application/x-missing-widget" handler="missing-handler"/>
  </bindings>
</package>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithBindingDependencies],
            ['name' => 'EPUB/nav.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">Chapter</a></li></ol></nav></body></html>'],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter</h1></body></html>'],
            ['name' => 'EPUB/widgets/local-handler.xhtml', 'data' => $handlerXhtml],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['manifestDependencyInventory'];
        $case = $summary['compactPackageReport']['casesById']['manifest-dependencies'];
        $local = $inventory['edgesByTargetId']['local-handler'][0];
        $remote = $inventory['edgesByTargetId']['remote-handler'][0];
        $missing = $inventory['edgesByTargetId']['missing-handler'][0];

        $t->same($inventory, $summary['wordpressImport']['manifestDependencyInventory']);
        $t->same($inventory['edges'], $summary['wordpressImport']['manifestDependencyEdges']);
        $t->same($inventory['diagnostics'], $summary['wordpressImport']['manifestDependencyDiagnostics']);
        $t->same(true, $inventory['present']);
        $t->same(3, $inventory['edgeCount']);
        $t->same(3, $inventory['bindingHandlerEdgeCount']);
        $t->same(0, $inventory['fallbackEdgeCount']);
        $t->same(0, $inventory['fallbackStyleEdgeCount']);
        $t->same(0, $inventory['mediaOverlayEdgeCount']);
        $t->same(2, $inventory['manifestTargetCount']);
        $t->same(1, $inventory['existingTargetCount']);
        $t->same(1, $inventory['missingManifestTargetCount']);
        $t->same(0, $inventory['missingPackagePartTargetCount']);
        $t->same(1, $inventory['externalTargetCount']);
        $t->same(1, $inventory['exposableTargetCount']);
        $t->same(2, $inventory['blockedTargetCount']);
        $t->same(strlen($handlerXhtml), $inventory['totalByteLength']);
        $t->same(strlen(gzdeflate($handlerXhtml)), $inventory['totalCompressedByteLength']);
        $t->same(strlen($handlerXhtml), $inventory['exposableByteLength']);
        $t->same(strlen(gzdeflate($handlerXhtml)), $inventory['exposableCompressedByteLength']);
        $t->same(0, $inventory['blockedByteLength']);
        $t->same(0, $inventory['blockedCompressedByteLength']);
        $t->same(['binding-handler' => 3], $inventory['relationCounts']);
        $t->same([
            'external-manifest-dependency-target-metadata-only' => 1,
            'manifest-dependency-target-bytes-exposable' => 1,
            'missing-manifest-dependency-target-metadata-only' => 1,
        ], $inventory['byteExposurePolicyCounts']);
        $t->same(['deflated' => 1], $inventory['compressionMethodCounts']);
        $t->same([
            'binding:application/x-local-widget',
            'binding:application/x-remote-widget',
            'binding:application/x-missing-widget',
        ], $inventory['sourceIds']);
        $t->same(['local-handler', 'remote-handler', 'missing-handler'], $inventory['targetIds']);
        $t->same(['/EPUB/widgets/local-handler.xhtml'], $inventory['targetPartNames']);
        $t->same(['missing-handler'], $inventory['missingManifestTargetIds']);
        $t->same(['remote-handler'], $inventory['externalTargetIds']);
        $t->same(4, $inventory['diagnosticCount']);
        $t->same([
            'external-manifest-dependency-target',
            'external-binding-handler',
            'missing-manifest-dependency-target',
            'missing-binding-handler-manifest-item',
        ], $inventory['diagnosticTypes']);

        $t->same('binding-handler', $local['relation']);
        $t->same('binding:application/x-local-widget', $local['sourceId']);
        $t->same('application/x-local-widget', $local['sourceMediaType']);
        $t->same('local-handler', $local['targetId']);
        $t->same('/EPUB/widgets/local-handler.xhtml', $local['targetPartName']);
        $t->same('xhtml', $local['targetResourceKind']);
        $t->same(true, $local['targetCanExposeBytes']);
        $t->same('manifest-dependency-target-bytes-exposable', $local['targetByteExposurePolicy']);
        $t->same(true, $local['relationResolved']);
        $t->same(true, $local['relationUsable']);
        $t->same('local-handler', $local['relationTerminalId']);
        $t->same('/EPUB/widgets/local-handler.xhtml', $local['relationTerminalPartName']);

        $t->same('remote-handler', $remote['targetId']);
        $t->same(true, $remote['targetExternal']);
        $t->same(false, $remote['targetExists']);
        $t->same('external-manifest-dependency-target-metadata-only', $remote['targetByteExposurePolicy']);
        $t->same(true, $remote['relationResolved']);
        $t->same(false, $remote['relationUsable']);
        $t->same('external-manifest-dependency-target', $remote['diagnostics'][0]['type']);
        $t->same('external-binding-handler', $remote['diagnostics'][1]['type']);

        $t->same('missing-handler', $missing['targetId']);
        $t->same(false, $missing['targetPresentInManifest']);
        $t->same(false, $missing['relationResolved']);
        $t->same(false, $missing['relationUsable']);
        $t->same('missing-manifest-dependency-target-metadata-only', $missing['targetByteExposurePolicy']);
        $t->same('missing-manifest-dependency-target', $missing['diagnostics'][0]['type']);
        $t->same('missing-binding-handler-manifest-item', $missing['diagnostics'][1]['type']);

        $t->same(3, $case['itemCount']);
        $t->same(3, $case['bindingHandlerEdgeCount']);
        $t->same(['binding-handler' => 3], $case['relationCounts']);
        $t->same($inventory['diagnosticTypes'], $case['diagnosticTypes']);
    },

    'summarizes EPUB stylesheet package resource references for compact handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $styleCss = <<<'CSS'
@import "theme.css";
body { background-image: url("../images/bg.png?rev=1#cover"); }
.remote { background-image: url("https://example.invalid/remote.png"); }
.inline { background-image: url(data:image/png;base64,AAA=); }
.missing { background-image: url("../images/missing.png"); }
@font-face { font-family: Source; src: url("../fonts/source.woff2"); }
.unlisted { background-image: url("../images/unlisted.png"); }
.locked { font-family: Locked; src: url("../fonts/locked.otf"); }
CSS;
        $themeCss = 'body { color: #224466; }';
        $bgBytes = 'BG';
        $fontBytes = 'FONT';
        $unlistedBytes = 'UNLISTED';
        $lockedFontBytes = 'LOCKED-FONT';
        $opfWithStylesheets = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:stylesheet-resource-review</dc:identifier>
    <dc:title>Stylesheet Resource Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="style" href="styles/book.css" media-type="text/css"/>
    <item id="theme" href="styles/theme.css" media-type="text/css"/>
    <item id="bg" href="images/bg.png" media-type="image/png"/>
    <item id="font" href="fonts/source.woff2" media-type="font/woff2"/>
    <item id="locked-font" href="fonts/locked.otf" media-type="font/otf"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData><CipherReference URI="EPUB/fonts/locked.otf"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithStylesheets],
            ['name' => 'EPUB/nav.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">Chapter</a></li></ol></nav></body></html>'],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => $styleCss],
            ['name' => 'EPUB/styles/theme.css', 'data' => $themeCss],
            ['name' => 'EPUB/images/bg.png', 'data' => $bgBytes],
            ['name' => 'EPUB/images/unlisted.png', 'data' => $unlistedBytes],
            ['name' => 'EPUB/fonts/source.woff2', 'data' => $fontBytes],
            ['name' => 'EPUB/fonts/locked.otf', 'data' => $lockedFontBytes],
        ]));

        $summary = $epub->summary();
        $report = $epub->stylesheetResources();
        $case = $summary['compactPackageReport']['casesById']['stylesheet-resources'];
        $itemsByHref = [];
        foreach ($report['itemsBySourceId']['style'] as $item) {
            $itemsByHref[$item['href']] = $item;
        }

        $t->same($report, $summary['stylesheetResources']);
        $t->same($report, $summary['wordpressImport']['stylesheetResources']);
        $t->same($report['items'], $summary['wordpressImport']['stylesheetResourceItems']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['stylesheetResourceDiagnostics']);
        $t->same($report['targetPartNames'], $summary['wordpressImport']['stylesheetResourceTargetPartNames']);
        $t->same(true, $report['present']);
        $t->same(2, $report['stylesheetCount']);
        $t->same(8, $report['referenceCount']);
        $t->same(6, $report['localReferenceCount']);
        $t->same(1, $report['externalReferenceCount']);
        $t->same(1, $report['dataReferenceCount']);
        $t->same(1, $report['missingReferenceCount']);
        $t->same(1, $report['unmanifestedReferenceCount']);
        $t->same(1, $report['blockedReferenceCount']);
        $t->same(4, $report['exposableReferenceCount']);
        $t->same(strlen($themeCss) + strlen($bgBytes) + strlen($fontBytes) + strlen($unlistedBytes) + strlen($lockedFontBytes), $report['totalByteLength']);
        $t->same([
            'embedded-stylesheet-data-uri-metadata-only' => 1,
            'external-stylesheet-resource-metadata-only' => 1,
            'missing-stylesheet-resource-metadata-only' => 1,
            'obfuscated-font-bytes-blocked' => 1,
            'stylesheet-resource-bytes-exposable' => 3,
            'unmanifested-stylesheet-resource-bytes-exposable' => 1,
        ], $report['byteExposurePolicyCounts']);
        $t->same(['style', 'theme'], $report['sourceIds']);
        $t->same([
            '/EPUB/fonts/locked.otf',
            '/EPUB/fonts/source.woff2',
            '/EPUB/images/bg.png',
            '/EPUB/images/missing.png',
            '/EPUB/images/unlisted.png',
            '/EPUB/styles/theme.css',
        ], $report['targetPartNames']);
        $t->same(['/EPUB/images/missing.png'], $report['missingPartNames']);
        $t->same(['/EPUB/images/unlisted.png'], $report['unmanifestedPartNames']);
        $t->same([
            'external-stylesheet-resource-reference',
            'embedded-stylesheet-data-uri',
            'missing-stylesheet-resource-package-part',
            'unmanifested-stylesheet-resource-reference',
            'obfuscated-stylesheet-font-reference',
        ], $report['diagnosticTypes']);

        $t->same('import', $itemsByHref['theme.css']['relation']);
        $t->same('/EPUB/styles/theme.css', $itemsByHref['theme.css']['targetPartName']);
        $t->same('theme', $itemsByHref['theme.css']['targetManifestId']);
        $t->same(true, $itemsByHref['theme.css']['canExposeBytes']);
        $t->same('stylesheet-resource-bytes-exposable', $itemsByHref['theme.css']['byteExposurePolicy']);
        $t->same(strlen($themeCss), $itemsByHref['theme.css']['byteLength']);

        $t->same('/EPUB/images/bg.png?rev=1#cover', $itemsByHref['../images/bg.png?rev=1#cover']['target']);
        $t->same('/EPUB/images/bg.png', $itemsByHref['../images/bg.png?rev=1#cover']['targetPartName']);
        $t->same('bg', $itemsByHref['../images/bg.png?rev=1#cover']['targetManifestId']);
        $t->same('image/png', $itemsByHref['../images/bg.png?rev=1#cover']['targetMediaType']);

        $t->same(true, $itemsByHref['https://example.invalid/remote.png']['external']);
        $t->same('external-stylesheet-resource-metadata-only', $itemsByHref['https://example.invalid/remote.png']['byteExposurePolicy']);
        $t->same('external-stylesheet-resource-reference', $itemsByHref['https://example.invalid/remote.png']['diagnostics'][0]['type']);

        $t->same(true, $itemsByHref['data:image/png;base64,AAA=']['dataReference']);
        $t->same('embedded-stylesheet-data-uri-metadata-only', $itemsByHref['data:image/png;base64,AAA=']['byteExposurePolicy']);

        $t->same(true, $itemsByHref['../images/missing.png']['missing']);
        $t->same('/EPUB/images/missing.png', $itemsByHref['../images/missing.png']['targetPartName']);
        $t->same('missing-stylesheet-resource-package-part', $itemsByHref['../images/missing.png']['diagnostics'][0]['type']);

        $t->same(true, $itemsByHref['../images/unlisted.png']['unmanifested']);
        $t->same(false, $itemsByHref['../images/unlisted.png']['targetPresentInManifest']);
        $t->same('unmanifested-stylesheet-resource-bytes-exposable', $itemsByHref['../images/unlisted.png']['byteExposurePolicy']);
        $t->same(strlen($unlistedBytes), $itemsByHref['../images/unlisted.png']['byteLength']);

        $t->same(true, $itemsByHref['../fonts/locked.otf']['encrypted']);
        $t->same(true, $itemsByHref['../fonts/locked.otf']['obfuscatedFont']);
        $t->same(false, $itemsByHref['../fonts/locked.otf']['canExposeBytes']);
        $t->same('obfuscated-font-bytes-blocked', $itemsByHref['../fonts/locked.otf']['byteExposurePolicy']);

        $t->same(true, $case['present']);
        $t->same(8, $case['itemCount']);
        $t->same(true, $case['reviewRequired']);
        $t->same(2, $case['stylesheetCount']);
        $t->same(1, $case['missingReferenceCount']);
        $t->same(1, $case['unmanifestedReferenceCount']);
        $t->same(1, $case['blockedReferenceCount']);
        $t->same($report['byteExposurePolicyCounts'], $case['byteExposurePolicyCounts']);
    },

    'summarizes encrypted OPF manifest dependency targets for compact handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $lockedStyle = 'body { color: #660000; }';
        $lockedOverlay = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL">
  <body>
    <seq>
      <par>
        <text src="../chapter.xhtml#locked"/>
        <audio src="../audio/locked.mp3"/>
      </par>
    </seq>
  </body>
</smil>
XML;
        $obfuscatedFont = 'OBFUSCATED-FONT-DATA';
        $opfWithEncryptedDependencies = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:manifest-dependency-encryption</dc:identifier>
    <dc:title>Manifest Dependency Encryption</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml" media-overlay="locked-overlay"/>
    <item id="remote-chapter" href="remote-chapter.xhtml" media-type="application/xhtml+xml" media-overlay="remote-overlay"/>
    <item id="widget" href="widgets/review.bin" media-type="application/x-review-widget" fallback="font-main" fallback-style="locked-style"/>
    <item id="locked-style" href="styles/locked.css" media-type="text/css"/>
    <item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>
    <item id="locked-overlay" href="overlays/locked.smil" media-type="application/smil+xml"/>
    <item id="remote-overlay" href="https://example.invalid/remote.smil" media-type="application/smil+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="remote-chapter"/>
  </spine>
</package>
XML;
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData><CipherReference URI="EPUB/fonts/source.otf"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/styles/locked.css"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/overlays/locked.smil"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithEncryptedDependencies],
            ['name' => 'EPUB/nav.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">Chapter</a></li></ol></nav></body></html>'],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="locked">Locked chapter</h1></body></html>'],
            ['name' => 'EPUB/remote-chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Remote overlay chapter</h1></body></html>'],
            ['name' => 'EPUB/widgets/review.bin', 'data' => 'WIDGET'],
            ['name' => 'EPUB/styles/locked.css', 'data' => $lockedStyle],
            ['name' => 'EPUB/fonts/source.otf', 'data' => $obfuscatedFont],
            ['name' => 'EPUB/overlays/locked.smil', 'data' => $lockedOverlay],
            ['name' => 'EPUB/audio/locked.mp3', 'data' => 'LOCKED-MP3'],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['manifestDependencyInventory'];
        $compact = $summary['compactPackageReport']['casesById']['manifest-dependencies'];
        $widgetFallback = $inventory['edgesBySourceId']['widget'][0];
        $widgetStyle = $inventory['edgesBySourceId']['widget'][1];
        $chapterOverlay = $inventory['edgesBySourceId']['chapter'][0];
        $remoteOverlay = $inventory['edgesBySourceId']['remote-chapter'][0];

        $t->same($inventory, $summary['wordpressImport']['manifestDependencyInventory']);
        $t->same(4, $inventory['edgeCount']);
        $t->same([
            'fallback' => 1,
            'fallback-style' => 1,
            'media-overlay' => 2,
        ], $inventory['relationCounts']);
        $t->same(4, $inventory['manifestTargetCount']);
        $t->same(3, $inventory['existingTargetCount']);
        $t->same(1, $inventory['externalTargetCount']);
        $t->same(3, $inventory['encryptedTargetCount']);
        $t->same(1, $inventory['obfuscatedFontTargetCount']);
        $t->same(0, $inventory['exposableTargetCount']);
        $t->same(4, $inventory['blockedTargetCount']);
        $t->same([
            'encrypted-resource-bytes-blocked' => 2,
            'external-manifest-dependency-target-metadata-only' => 1,
            'obfuscated-font-bytes-blocked' => 1,
        ], $inventory['byteExposurePolicyCounts']);
        $t->same(['/EPUB/overlays/locked.smil', '/EPUB/fonts/source.otf', '/EPUB/styles/locked.css'], $inventory['encryptedTargetPartNames']);
        $t->same(['/EPUB/fonts/source.otf'], $inventory['obfuscatedFontTargetPartNames']);
        $t->same(['remote-overlay'], $inventory['externalTargetIds']);
        $t->same(strlen($obfuscatedFont) + strlen($lockedStyle) + strlen($lockedOverlay), $inventory['encryptedByteLength']);
        $t->same(strlen($obfuscatedFont), $inventory['obfuscatedFontByteLength']);

        $t->same('fallback', $widgetFallback['relation']);
        $t->same('font-main', $widgetFallback['targetId']);
        $t->same(true, $widgetFallback['targetEncrypted']);
        $t->same(true, $widgetFallback['targetObfuscatedFont']);
        $t->same(false, $widgetFallback['targetCanExposeBytes']);
        $t->same('obfuscated-font-bytes-blocked', $widgetFallback['targetByteExposurePolicy']);
        $t->true(in_array('obfuscated-font-manifest-dependency-target', array_column($widgetFallback['diagnostics'], 'type'), true));

        $t->same('fallback-style', $widgetStyle['relation']);
        $t->same('locked-style', $widgetStyle['targetId']);
        $t->same(true, $widgetStyle['targetEncrypted']);
        $t->same(false, $widgetStyle['targetObfuscatedFont']);
        $t->same('encrypted-resource-bytes-blocked', $widgetStyle['targetByteExposurePolicy']);
        $t->true(in_array('encrypted-manifest-dependency-target', array_column($widgetStyle['diagnostics'], 'type'), true));

        $t->same('media-overlay', $chapterOverlay['relation']);
        $t->same('locked-overlay', $chapterOverlay['targetId']);
        $t->same(true, $chapterOverlay['targetEncrypted']);
        $t->same('/EPUB/overlays/locked.smil', $chapterOverlay['targetPartName']);
        $t->same('encrypted-resource-bytes-blocked', $chapterOverlay['targetByteExposurePolicy']);

        $t->same('remote-overlay', $remoteOverlay['targetId']);
        $t->same(true, $remoteOverlay['targetExternal']);
        $t->same('external-manifest-dependency-target-metadata-only', $remoteOverlay['targetByteExposurePolicy']);
        $t->same('external-manifest-dependency-target', $remoteOverlay['diagnostics'][0]['type']);

        $t->same(4, $compact['itemCount']);
        $t->same(1, $compact['externalTargetCount']);
        $t->same(3, $compact['encryptedTargetCount']);
        $t->same(1, $compact['obfuscatedFontTargetCount']);
        $t->same($inventory['encryptedTargetPartNames'], $compact['encryptedTargetPartNames']);
        $t->same($inventory['obfuscatedFontTargetPartNames'], $compact['obfuscatedFontTargetPartNames']);
        $t->same($inventory['externalTargetIds'], $compact['externalTargetIds']);
        $t->same($inventory['encryptedByteLength'], $compact['encryptedByteLength']);
        $t->same($inventory['obfuscatedFontByteLength'], $compact['obfuscatedFontByteLength']);
        $t->true(in_array('obfuscated-font-manifest-dependency-target', $compact['diagnosticTypes'], true));
    },

    'reports OPF metadata meta property vocabulary diagnostics for package review' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithMetaVocabulary = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" prefix="review: https://example.invalid/epub-review#">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:meta-property-vocabulary</dc:identifier>
    <dc:title>Meta property vocabulary review</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-14T04:30:00Z</meta>
    <meta id="local-record" property="review:source-record">Local source record</meta>
    <meta id="absolute-record" property="https://example.invalid/meta#record">Absolute source record</meta>
    <meta id="bad-url" property="https://example.invalid/no-fragment">Missing URL fragment</meta>
    <meta id="bad-token" property="bad/property">Bad token</meta>
    <meta id="unknown-record" property="unknown:record">Unknown source record</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol><li><a href="text/chapter.xhtml">Meta properties</a></li></ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMetaVocabulary],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
            ['name' => 'EPUB/text/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Meta properties</h1></body></html>'],
        ]));
        $metadata = $epub->metadata();
        $vocabulary = $metadata['metaPropertyVocabulary'];
        $validation = $epub->validationReport();
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(6, $vocabulary['propertyCount']);
        $t->same(3, $vocabulary['validCount']);
        $t->same(3, $vocabulary['diagnosticPropertyCount']);
        $t->same(2, $vocabulary['resolvedCount']);
        $t->same(1, $vocabulary['absoluteUrlCount']);
        $t->same(3, $vocabulary['diagnosticCount']);
        $t->same([
            'bad/property',
            'dcterms:modified',
            'https://example.invalid/meta#record',
            'https://example.invalid/no-fragment',
            'review:source-record',
            'unknown:record',
        ], $vocabulary['properties']);

        $itemsById = [];
        foreach ($vocabulary['items'] as $item) {
            $itemsById[$item['id'] ?? $item['property']] = $item;
        }

        $t->same('http://purl.org/dc/terms/modified', $vocabulary['items'][0]['iri']);
        $t->same('https://example.invalid/epub-review#source-record', $itemsById['local-record']['iri']);
        $t->same(true, $itemsById['absolute-record']['absoluteUrlWithFragment']);
        $t->same(null, $itemsById['bad-token']['iri']);
        $t->same([
            'invalid-metadata-meta-property-url-fragment',
            'invalid-metadata-meta-property-token',
            'unknown-metadata-meta-property-prefix',
        ], array_column($vocabulary['diagnostics'], 'type'));
        $t->same('unknown', $vocabulary['diagnostics'][2]['prefix']);
        $t->same(false, $validation['metadata']['metaPropertyValid']);
        $t->same(3, $validation['metadata']['metaPropertyDiagnosticCount']);
        $t->same($vocabulary['diagnostics'], $validation['metadata']['metaPropertyDiagnostics']);
        $t->same($vocabulary['diagnostics'], $validation['diagnostics']);
        $t->same($vocabulary, $summary['metaPropertyVocabulary']);
        $t->same($vocabulary, $summary['wordpressImport']['metadataPropertyVocabulary']);
        $t->same($vocabulary, $summary['wordpressImport']['metadataDetails']['metaPropertyVocabulary']);
        $t->same($vocabulary['diagnostics'], $summary['wordpressImport']['metadataPropertyDiagnostics']);
        $t->same($metadata['meta'][4]['propertyVocabulary']['diagnostics'][0]['type'], $itemsById['bad-token']['diagnostics'][0]['type']);
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
    'preserves compact OPF manifest and spine authoring attributes for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithAuthoring = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" xmlns:review="https://example.invalid/epub-review" version="3.0" unique-identifier="bookid" xml:lang="en">',
            $epub3OpfXml
        );
        $opfWithAuthoring = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" xml:base="../review-candidates/" xml:lang="fr" dir="rtl" data-review="primary" review:source="wp-import"/>',
            $opfWithAuthoring
        );
        $opfWithAuthoring = str_replace(
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>',
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image" dir="ltr" data-review="cover"/>',
            $opfWithAuthoring
        );
        $opfWithAuthoring = str_replace(
            '<itemref idref="chapter1"/>',
            '<itemref id="chapter-one-spine" idref="chapter1" xml:lang="fr" dir="rtl" data-review="primary" review:source="wp-import"/>',
            $opfWithAuthoring
        );
        $opfWithAuthoring = str_replace(
            '<itemref idref="chapter2" linear="no" properties="page-spread-right"/>',
            '<itemref idref="chapter2" linear="no" properties="page-spread-right" dir="ltr" data-review="appendix"/>',
            $opfWithAuthoring
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithAuthoring],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $chapter = $epub->manifestItem('chapter1');
        $cover = $epub->manifestItem('cover');
        $spine = $epub->spine();
        $summary = $epub->summary();
        $manifestAuthoring = $summary['manifestAuthoring'];
        $spineAuthoring = $summary['spineAuthoring'];
        $resourceKinds = $summary['manifestResourceKinds'];
        $packageInventory = $summary['packageInventory'];
        $chapterInventory = $packageInventory['byPackagePath']['EPUB/text/chapter1.xhtml'];
        $chapterResource = $resourceKinds['itemsById']['chapter1'];

        $t->same('fr', $chapter['language']);
        $t->same('rtl', $chapter['direction']);
        $t->same('../review-candidates/', $chapter['base']);
        $t->same('reported-not-applied-to-manifest-hrefs', $chapter['baseResolutionPolicy']);
        $t->same(false, $chapter['baseResolution']['appliesToManifestHrefs']);
        $t->same('fr', $chapter['attributes']['xml:lang']);
        $t->same('../review-candidates/', $chapter['attributes']['xml:base']);
        $t->same('wp-import', $chapter['attributes']['review:source']);
        $t->same(['data-review' => 'primary', 'review:source' => 'wp-import'], $chapter['customAttributes']);
        $t->same('ltr', $cover['direction']);
        $t->same(['data-review' => 'cover'], $cover['customAttributes']);

        $t->same('chapter-one-spine', $spine[0]['id']);
        $t->same('fr', $spine[0]['language']);
        $t->same('rtl', $spine[0]['direction']);
        $t->same('chapter1', $spine[0]['attributes']['idref']);
        $t->same('wp-import', $spine[0]['attributes']['review:source']);
        $t->same(['data-review' => 'primary', 'review:source' => 'wp-import'], $spine[0]['customAttributes']);
        $t->same('ltr', $spine[1]['direction']);
        $t->same(['data-review' => 'appendix'], $spine[1]['customAttributes']);

        $t->same(5, $manifestAuthoring['itemCount']);
        $t->same(1, $manifestAuthoring['languageItemCount']);
        $t->same(2, $manifestAuthoring['directionItemCount']);
        $t->same(1, $manifestAuthoring['baseItemCount']);
        $t->same(2, $manifestAuthoring['customAttributeItemCount']);
        $t->same(['chapter1'], array_column($manifestAuthoring['languageItems'], 'id'));
        $t->same(['cover', 'chapter1'], array_column($manifestAuthoring['directionItems'], 'id'));
        $t->same(['chapter1'], array_column($manifestAuthoring['baseItems'], 'id'));
        $t->same('../review-candidates/', $manifestAuthoring['itemsById']['chapter1']['base']);
        $t->same('reported-not-applied-to-manifest-hrefs', $manifestAuthoring['itemsById']['chapter1']['baseResolutionPolicy']);
        $t->same('primary', $manifestAuthoring['itemsById']['chapter1']['customAttributes']['data-review']);
        $t->same('cover', $manifestAuthoring['itemsById']['cover']['customAttributes']['data-review']);
        $t->same($manifestAuthoring, $summary['wordpressImport']['manifestAuthoring']);
        $t->same($manifestAuthoring['items'], $summary['wordpressImport']['manifestAuthoringItems']);
        $t->same(1, $resourceKinds['baseItemCount']);
        $t->same(['chapter1'], $resourceKinds['baseItemIds']);
        $t->same(['/EPUB/text/chapter1.xhtml'], $resourceKinds['baseItemPartNames']);
        $t->same(1, $resourceKinds['summary']['baseItemCount']);
        $t->same('../review-candidates/', $chapterResource['base']);
        $t->same('reported-not-applied-to-manifest-hrefs', $chapterResource['baseResolutionPolicy']);
        $t->same(false, $chapterResource['baseResolution']['appliesToManifestHrefs']);
        $t->same('/EPUB/text/chapter1.xhtml', $chapterResource['partName']);
        $t->same(1, $packageInventory['manifestBaseItemCount']);
        $t->same(['/EPUB/text/chapter1.xhtml'], $packageInventory['manifestBasePartNames']);
        $t->same(['EPUB/text/chapter1.xhtml'], $packageInventory['manifestBasePackagePaths']);
        $t->same(1, $chapterInventory['manifestBaseItemCount']);
        $t->same('../review-candidates/', $chapterInventory['manifestBase']);
        $t->same('reported-not-applied-to-manifest-hrefs', $chapterInventory['manifestBaseResolutionPolicy']);
        $t->same(false, $chapterInventory['manifestBaseResolution']['appliesToManifestHrefs']);
        $t->same(true, in_array('opf-manifest-xml-base-candidate', $chapterInventory['roles'], true));
        $t->same('/EPUB/text/chapter1.xhtml', $chapterInventory['partName']);
        $t->same($packageInventory, $summary['wordpressImport']['packageInventory']);

        $t->same(2, $spineAuthoring['itemCount']);
        $t->same(1, $spineAuthoring['languageItemCount']);
        $t->same(2, $spineAuthoring['directionItemCount']);
        $t->same(2, $spineAuthoring['customAttributeItemCount']);
        $t->same(['chapter-one-spine'], array_column($spineAuthoring['languageItems'], 'id'));
        $t->same([0, 1], array_column($spineAuthoring['directionItems'], 'index'));
        $t->same('primary', $spineAuthoring['itemsByIndex'][0]['customAttributes']['data-review']);
        $t->same('wp-import', $spineAuthoring['itemsByIndex'][0]['customAttributes']['review:source']);
        $t->same('appendix', $spineAuthoring['itemsByIndex'][1]['customAttributes']['data-review']);
        $t->same($spineAuthoring, $summary['wordpressImport']['spineAuthoring']);
        $t->same($spineAuthoring['items'], $summary['wordpressImport']['spineAuthoringItems']);
    },

    'preserves OPF metadata root authoring attributes for package review handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithMetadataAuthoring = str_replace(
            '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">',
            '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:review="https://example.invalid/epub-review" id="metadata-root" xml:lang="fr" dir="rtl" xml:base="metadata/" data-review="metadata" review:source="wp-import">',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMetadataAuthoring],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $authoring = $metadata['metadataAuthoring'];

        $t->same(true, $authoring['present']);
        $t->same('fr', $authoring['language']);
        $t->same('rtl', $authoring['direction']);
        $t->same('metadata/', $authoring['base']);
        $t->same('metadata-root', $authoring['attributes']['id']);
        $t->same('fr', $authoring['attributes']['xml:lang']);
        $t->same('metadata/', $authoring['attributes']['xml:base']);
        $t->same(6, $authoring['attributeCount']);
        $t->same([
            'dir' => 'rtl',
            'id' => 'metadata-root',
            'xml:base' => 'metadata/',
            'xml:lang' => 'fr',
        ], $authoring['structuralAttributes']);
        $t->same(4, $authoring['structuralAttributeCount']);
        $t->same(['data-review' => 'metadata', 'review:source' => 'wp-import'], $authoring['customAttributes']);
        $t->same(2, $authoring['customAttributeCount']);
        $t->same(true, $authoring['hasLanguage']);
        $t->same(true, $authoring['hasDirection']);
        $t->same(true, $authoring['hasBase']);
        $t->same('reported-not-applied-to-package-paths', $authoring['baseResolutionPolicy']);
        $t->same(false, $authoring['baseResolution']['appliesToPackagePaths']);
        $t->same(true, $authoring['baseResolution']['metadataOnly']);
        $t->same($authoring, $summary['metadataAuthoring']);
        $t->same($authoring, $summary['wordpressImport']['metadataDetails']['metadataAuthoring']);
    },

    'preserves OPF metadata child authoring attributes for package review handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithMetadataItemAuthoring = str_replace(
            '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">',
            '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf" xmlns:review="https://example.invalid/epub-review">',
            $epub3OpfXml
        );
        $opfWithMetadataItemAuthoring = str_replace(
            '<dc:identifier id="bookid">urn:isbn:9780000000001</dc:identifier>',
            '<dc:identifier id="bookid" opf:scheme="ISBN">urn:isbn:9780000000001</dc:identifier>',
            $opfWithMetadataItemAuthoring
        );
        $opfWithMetadataItemAuthoring = str_replace(
            '<dc:title>WordPress Migration Guide</dc:title>',
            '<dc:title id="title-main" xml:lang="fr" dir="rtl" xml:base="titles/" data-review="title" review:source="wp-import">Guide FR</dc:title>',
            $opfWithMetadataItemAuthoring
        );
        $opfWithMetadataItemAuthoring = str_replace(
            '<dc:creator id="creator">Data Liberation Team</dc:creator>',
            '<dc:creator id="creator" xml:lang="pl" dir="ltr" opf:file-as="Team, Data" opf:role="aut">Data Liberation Team</dc:creator>',
            $opfWithMetadataItemAuthoring
        );
        $opfWithMetadataItemAuthoring = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta id="modified" property="dcterms:modified" refines="#title-main" scheme="dcterms:W3CDTF" xml:lang="en" dir="ltr" xml:base="meta/" data-review="modified">2026-06-03T22:09:50Z</meta>',
            $opfWithMetadataItemAuthoring
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMetadataItemAuthoring],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $authoring = $metadata['metadataItemAuthoring'];
        $itemsById = $authoring['itemsById'];
        $title = $itemsById['title-main'];
        $creator = $itemsById['creator'];
        $identifier = $itemsById['bookid'];
        $modified = $itemsById['modified'];
        $compactCase = $summary['compactPackageReport']['casesById']['metadata-item-authoring'];

        $t->same(true, $authoring['present']);
        $t->same(5, $authoring['itemCount']);
        $t->same(['creator', 'identifier', 'language', 'meta', 'title'], $authoring['kinds']);
        $t->same(['creator' => 1, 'identifier' => 1, 'language' => 1, 'meta' => 1, 'title' => 1], $authoring['kindCounts']);
        $t->same(4, $authoring['idItemCount']);
        $t->same(3, $authoring['languageItemCount']);
        $t->same(3, $authoring['directionItemCount']);
        $t->same(2, $authoring['baseItemCount']);
        $t->same(2, $authoring['schemeItemCount']);
        $t->same(2, $authoring['customAttributeItemCount']);

        $t->same(1, $title['index']);
        $t->same('title', $title['kind']);
        $t->same('dc:title', $title['qualifiedName']);
        $t->same('fr', $title['language']);
        $t->same('rtl', $title['direction']);
        $t->same('titles/', $title['base']);
        $t->same([
            'dir' => 'rtl',
            'id' => 'title-main',
            'xml:base' => 'titles/',
            'xml:lang' => 'fr',
        ], $title['structuralAttributes']);
        $t->same(['data-review' => 'title', 'review:source' => 'wp-import'], $title['customAttributes']);
        $t->same('metadata-only-not-applied-to-package-paths', $title['baseResolutionPolicy']);
        $t->same(false, $title['baseResolution']['appliesToPackagePaths']);

        $t->same('ISBN', $identifier['scheme']);
        $t->same(['id' => 'bookid', 'opf:scheme' => 'ISBN'], $identifier['structuralAttributes']);
        $t->same('Team, Data', $creator['structuralAttributes']['opf:file-as']);
        $t->same('aut', $creator['structuralAttributes']['opf:role']);
        $t->same(0, $creator['customAttributeCount']);

        $t->same('dcterms:modified', $modified['property']);
        $t->same('#title-main', $modified['refines']);
        $t->same('title-main', $modified['subjectId']);
        $t->same('dcterms:W3CDTF', $modified['scheme']);
        $t->same(['data-review' => 'modified'], $modified['customAttributes']);

        $t->same($authoring, $summary['metadataItemAuthoring']);
        $t->same($authoring, $summary['wordpressImport']['metadataItemAuthoring']);
        $t->same($authoring, $summary['wordpressImport']['metadataDetails']['metadataItemAuthoring']);
        $t->same($title, $summary['wordpressImport']['metadataItemAuthoringItemsById']['title-main']);
        $t->same(5, $compactCase['itemCount']);
        $t->same(2, $compactCase['schemeItemCount']);
        $t->same(2, $compactCase['customAttributeItemCount']);
    },

    'preserves OPF package authoring attributes for package review handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithPackageAuthoring = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" xmlns:review="https://example.invalid/epub-review" id="package-authoring" version="3.0" unique-identifier="bookid" xml:base="https://publisher.example.invalid/epub/" xml:lang="ja" dir="rtl" prefix="review: https://example.invalid/epub-review#" data-review="package" review:source="wp-import">',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithPackageAuthoring],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $package = $metadata['package'];
        $authoring = $summary['packageAuthoring'];

        $t->same('package-authoring', $package['id']);
        $t->same('https://publisher.example.invalid/epub/', $package['base']);
        $t->same('ja', $package['language']);
        $t->same('rtl', $package['direction']);
        $t->same('package', $package['customAttributes']['data-review']);
        $t->same('wp-import', $package['customAttributes']['review:source']);
        $t->same(false, array_key_exists('xmlns:review', $package['customAttributes']));

        $t->same('package-authoring', $authoring['id']);
        $t->same('3.0', $authoring['version']);
        $t->same('bookid', $authoring['uniqueIdentifierId']);
        $t->same(true, $authoring['hasBase']);
        $t->same(true, $authoring['hasLanguage']);
        $t->same(true, $authoring['hasDirection']);
        $t->same(true, $authoring['hasCustomAttributes']);
        $t->same('https://publisher.example.invalid/epub/', $authoring['attributes']['xml:base']);
        $t->same('review: https://example.invalid/epub-review#', $authoring['attributes']['prefix']);
        $t->same(['data-review' => 'package', 'review:source' => 'wp-import'], $authoring['customAttributes']);
        $t->same(2, $authoring['customAttributeCount']);
        $t->same($package['attributes'], $metadata['packageAttributes']);
        $t->same($package['customAttributes'], $metadata['packageCustomAttributes']);
        $t->same($authoring, $summary['wordpressImport']['packageAuthoring']);
        $t->same($package, $summary['wordpressImport']['metadataDetails']['package']);
    },
    'preserves compact OCF rootfile authoring attributes for package review handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $containerWithAuthoring = str_replace(
            '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">',
            '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:review="https://example.invalid/epub-review" version="1.0">',
            $epubContainerXml
        );
        $containerWithAuthoring = str_replace(
            '<rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>',
            '<rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml" xml:lang="en" data-review-state="selected" review:profile="primary"/>',
            $containerWithAuthoring
        );
        $containerWithAuthoring = str_replace(
            '</rootfiles>',
            '    <rootfile full-path="EPUB/fixed/package.opf" media-type="application/oebps-package+xml; profile=&quot;fixed&quot;" data-review-state="alternate" review:profile="fixed-layout"/>' . "\n"
            . '  </rootfiles>',
            $containerWithAuthoring
        );
        $alternateOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="fixed-id">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="fixed-id">urn:uuid:fixed-layout</dc:identifier>
    <dc:title>Fixed Layout Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="fixed-chapter" href="text/fixed.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="fixed-chapter"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $containerWithAuthoring],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/fixed/package.opf', 'data' => $alternateOpf],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $rootfiles = $epub->rootfiles();
        $summary = $epub->summary();
        $authoring = $summary['rootfileAuthoring'];
        $validation = $summary['validation']['rootfiles'];
        $renditions = $summary['renditions'];

        $t->same(2, count($rootfiles));
        $t->same('EPUB/package.opf', $rootfiles[0]['fullPath']);
        $t->same([
            'data-review-state' => 'selected',
            'full-path' => 'EPUB/package.opf',
            'media-type' => 'application/oebps-package+xml',
            'review:profile' => 'primary',
            'xml:lang' => 'en',
        ], $rootfiles[0]['attributes']);
        $t->same(5, $rootfiles[0]['attributeCount']);
        $t->same([
            'data-review-state' => 'selected',
            'review:profile' => 'primary',
            'xml:lang' => 'en',
        ], $rootfiles[0]['customAttributes']);
        $t->same(3, $rootfiles[0]['customAttributeCount']);
        $t->same(true, $rootfiles[0]['hasCustomAttributes']);

        $t->same('EPUB/fixed/package.opf', $rootfiles[1]['fullPath']);
        $t->same('application/oebps-package+xml; profile="fixed"', $rootfiles[1]['mediaType']);
        $t->same(['profile' => 'fixed'], $rootfiles[1]['mediaTypeParameterMap']);
        $t->same([
            'data-review-state' => 'alternate',
            'full-path' => 'EPUB/fixed/package.opf',
            'media-type' => 'application/oebps-package+xml; profile="fixed"',
            'review:profile' => 'fixed-layout',
        ], $rootfiles[1]['attributes']);
        $t->same([
            'data-review-state' => 'alternate',
            'review:profile' => 'fixed-layout',
        ], $rootfiles[1]['customAttributes']);

        $t->same(true, $authoring['present']);
        $t->same(2, $authoring['itemCount']);
        $t->same(0, $authoring['selectedIndex']);
        $t->same('/EPUB/package.opf', $authoring['selectedPartName']);
        $t->same(1, $authoring['alternateItemCount']);
        $t->same(9, $authoring['attributeCount']);
        $t->same(5, $authoring['customAttributeCount']);
        $t->same(2, $authoring['customAttributeItemCount']);
        $t->same(['data-review-state', 'review:profile', 'xml:lang'], $authoring['customAttributeNames']);
        $t->same($rootfiles[0]['customAttributes'], $authoring['itemsByIndex'][0]['customAttributes']);
        $t->same($rootfiles[1]['customAttributes'], $authoring['itemsByPartName']['/EPUB/fixed/package.opf']['customAttributes']);

        $t->same($rootfiles[0]['attributes'], $validation['items'][0]['attributes']);
        $t->same($rootfiles[1]['customAttributes'], $validation['alternateRootfiles'][0]['customAttributes']);
        $t->same($rootfiles[0]['attributes'], $renditions['items'][0]['attributes']);
        $t->same($rootfiles[1]['customAttributes'], $renditions['items'][1]['customAttributes']);
        $t->same($authoring, $summary['wordpressImport']['rootfileAuthoring']);
        $t->same($authoring['items'], $summary['wordpressImport']['rootfileAuthoringItems']);
        $t->same($rootfiles, $summary['wordpressImport']['containerRootfiles']);
    },
    'summarizes repeated OPF spine idrefs in reading order inventory handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithRepeatedSpineIdref = str_replace(
            '<spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2" linear="no" properties="page-spread-right"/>
  </spine>',
            '<spine>
    <itemref id="chapter1-primary" idref="chapter1"/>
    <itemref id="chapter2-spine" idref="chapter2" linear="no" properties="page-spread-right"/>
    <itemref id="chapter1-review-copy" idref="chapter1" linear="no"/>
  </spine>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithRepeatedSpineIdref],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $summary = $epub->summary();
        $validation = $epub->validationReport();
        $report = $summary['readingOrderInventory'];
        $repeat = $report['repeatedIdrefItems'][0];
        $chapterRows = $report['itemsByIdref']['chapter1'];

        $t->same(false, $validation['valid']);
        $t->same(false, $validation['spine']['valid']);
        $t->same(['duplicate-spine-itemref-idref'], array_column($validation['diagnostics'], 'type'));
        $t->same(['duplicate-spine-itemref-idref'], array_column($validation['spine']['diagnostics'], 'type'));
        $t->same('chapter1', $validation['spine']['diagnostics'][0]['idref']);
        $t->same([0, 2], $validation['spine']['diagnostics'][0]['indexes']);
        $t->same(3, $report['itemCount']);
        $t->same(1, $report['linearItemCount']);
        $t->same(2, $report['nonLinearItemCount']);
        $t->same(1, $report['repeatedIdrefCount']);
        $t->same(2, $report['repeatedIdrefItemCount']);
        $t->same(['chapter1'], $report['repeatedIdrefs']);
        $t->same('chapter1', $repeat['idref']);
        $t->same(2, $repeat['occurrenceCount']);
        $t->same([0, 2], $repeat['indexes']);
        $t->same(0, $repeat['firstIndex']);
        $t->same(2, $repeat['lastIndex']);
        $t->same(['chapter1-primary', 'chapter1-review-copy'], $repeat['spineIds']);
        $t->same(['/EPUB/text/chapter1.xhtml'], $repeat['partNames']);
        $t->same(['EPUB/text/chapter1.xhtml'], $repeat['packagePaths']);
        $t->same(1, $repeat['linearCount']);
        $t->same(1, $repeat['nonLinearCount']);
        $t->same(1, $report['diagnosticCount']);
        $t->same(['repeated-spine-idref'], $report['diagnosticTypes']);
        $t->same('repeated-spine-idref', $report['repeatedIdrefDiagnostics'][0]['type']);
        $t->same('chapter1', $report['repeatedIdrefDiagnostics'][0]['idref']);
        $t->same([0, 2], $report['repeatedIdrefDiagnostics'][0]['indexes']);
        $t->same(2, count($chapterRows));
        $t->same([0, 2], array_column($chapterRows, 'index'));
        $t->same(['spine-content-bytes-exposable', 'spine-content-bytes-exposable'], array_column($chapterRows, 'byteExposurePolicy'));
        $t->same($report['repeatedIdrefs'], $summary['wordpressImport']['readingOrderRepeatedIdrefs']);
        $t->same($report['repeatedIdrefItems'], $summary['wordpressImport']['readingOrderRepeatedIdrefItems']);
        $t->same($report['repeatedIdrefDiagnostics'], $summary['wordpressImport']['readingOrderRepeatedIdrefDiagnostics']);
        $t->same($report, $summary['wordpressImport']['readingOrderInventory']);
    },

    'summarizes container rootfile selection buckets for package review' => static function (TestRunner $t) use ($epub3OpfXml, $epub3NavXml): void {
        $containerXml = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type='application/oebps-package+xml; profile="primary"'/>
    <rootfile full-path="EPUB/fixed.opf?rendition=fixed#package" media-type="application/oebps-package+xml;profile=fixed"/>
    <rootfile full-path="EPUB/missing.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="EPUB/source.xml" media-type="application/xml"/>
  </rootfiles>
</container>
XML;
        $alternateOpfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="fixed-id" xml:lang="ja">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="fixed-id">alt-id</dc:identifier>
    <dc:title>Fixed Layout Rendition</dc:title>
    <dc:language>ja</dc:language>
    <meta property="dcterms:modified">2026-06-15T08:30:00Z</meta>
  </metadata>
  <manifest>
    <item id="fixed-page" href="fixed.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="fixed-page"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $containerXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/fixed.opf', 'data' => $alternateOpfXml],
            ['name' => 'EPUB/source.xml', 'data' => '<source>review</source>'],
        ]));

        $summary = $epub->summary();
        $report = $summary['containerRootfileSelection'];
        $itemsByPart = [];
        foreach ($report['items'] as $item) {
            $itemsByPart[$item['partName']] = $item;
        }

        $t->same('/EPUB/package.opf', $report['selectedPart']);
        $t->same(0, $report['selectedIndex']);
        $t->same(4, $report['rootfileCount']);
        $t->same(3, $report['opfRootfileCount']);
        $t->same(3, $report['alternateRootfileCount']);
        $t->same(3, $report['existingRootfileCount']);
        $t->same(1, $report['missingRootfileCount']);
        $t->same(1, $report['nonOpfRootfileCount']);
        $t->same(1, $report['fullPathSuffixCount']);
        $t->same(2, $report['mediaTypeParameterItemCount']);
        $t->same([
            'rootfile-full-path-query-component',
            'rootfile-full-path-fragment-component',
            'missing-rootfile-package-part',
            'missing-alternate-rendition-rootfile',
            'non-opf-container-rootfile',
        ], $report['diagnosticTypes']);

        $t->same('/EPUB/package.opf', $report['selectedItem']['partName']);
        $t->same('selected-opf-rootfile', $report['selectedItem']['role']);
        $t->same('WordPress Migration Guide', $report['selectedItem']['renditionTitle']);
        $t->same(['profile'], $report['selectedItem']['mediaTypeParameterNames']);
        $t->same('package-bytes-available', $report['selectedItem']['byteExposurePolicy']);

        $t->same(['/EPUB/package.opf', '/EPUB/fixed.opf', '/EPUB/missing.opf'], $report['buckets']['opfParts']);
        $t->same(['/EPUB/fixed.opf', '/EPUB/missing.opf', '/EPUB/source.xml'], $report['buckets']['alternateParts']);
        $t->same(['/EPUB/missing.opf'], $report['buckets']['missingParts']);
        $t->same(['/EPUB/source.xml'], $report['buckets']['nonOpfParts']);
        $t->same(['/EPUB/fixed.opf'], $report['buckets']['fullPathSuffixParts']);
        $t->same(['/EPUB/package.opf', '/EPUB/fixed.opf'], $report['buckets']['mediaTypeParameterizedParts']);

        $t->same('alternate-opf-rootfile', $itemsByPart['/EPUB/fixed.opf']['role']);
        $t->same(true, $itemsByPart['/EPUB/fixed.opf']['fullPathHasSuffix']);
        $t->same('rendition=fixed', $itemsByPart['/EPUB/fixed.opf']['fullPathQuery']);
        $t->same('package', $itemsByPart['/EPUB/fixed.opf']['fullPathFragment']);
        $t->same('Fixed Layout Rendition', $itemsByPart['/EPUB/fixed.opf']['renditionTitle']);
        $t->same('alt-id', $itemsByPart['/EPUB/fixed.opf']['renditionIdentifier']);
        $t->same('ja', $itemsByPart['/EPUB/fixed.opf']['renditionLanguage']);
        $t->same(1, $itemsByPart['/EPUB/fixed.opf']['manifestCount']);
        $t->same(1, $itemsByPart['/EPUB/fixed.opf']['spineCount']);

        $t->same(false, $itemsByPart['/EPUB/missing.opf']['exists']);
        $t->same('missing-package-part', $itemsByPart['/EPUB/missing.opf']['byteExposurePolicy']);
        $t->same(['missing-rootfile-package-part', 'missing-alternate-rendition-rootfile'], $itemsByPart['/EPUB/missing.opf']['diagnosticTypes']);
        $t->same('non-opf-rootfile', $itemsByPart['/EPUB/source.xml']['role']);
        $t->same(false, $itemsByPart['/EPUB/source.xml']['opfRootfile']);
        $t->same(['non-opf-container-rootfile'], $itemsByPart['/EPUB/source.xml']['diagnosticTypes']);

        $t->same($report, $summary['wordpressImport']['containerRootfileSelection']);
        $t->same($report['items'], $summary['wordpressImport']['containerRootfileSelectionItems']);
        $t->same($report['buckets'], $summary['wordpressImport']['containerRootfileSelectionBuckets']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['containerRootfileSelectionDiagnostics']);
    },

    'summarizes undeclared EPUB ZIP package entries for review handoff' => static function (TestRunner $t) use ($epubContainerXml, $buildZipPackage): void {
        $note = 'private reviewer note';
        $cover = 'PNG-COVER';
        $audio = 'ENCRYPTED-AUDIO';
        $packed = 'PACKED-PRIVATE-BYTES';
        $opfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:undeclared-package-entry-report</dc:identifier>
    <dc:title>Undeclared Package Entries</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $navXml = '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">Chapter</a></li></ol></nav></body></html>';
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/private/locked.mp3"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage($buildZipPackage([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'method' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml, 'method' => 8],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml, 'method' => 8],
            ['name' => 'EPUB/package.opf', 'data' => $opfXml, 'method' => 8],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navXml, 'method' => 8],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter</h1></body></html>', 'method' => 8],
            ['name' => 'EPUB/private/', 'data' => '', 'method' => 0],
            ['name' => 'EPUB/private/notes.txt', 'data' => $note, 'method' => 0],
            ['name' => 'EPUB/private/cover.png', 'data' => $cover, 'method' => 8],
            ['name' => 'EPUB/private/locked.mp3', 'data' => $audio, 'method' => 8],
            ['name' => 'EPUB/private/packed.bin', 'data' => $packed, 'method' => 12],
        ]));
        $summary = $epub->summary();
        $inventory = $summary['packageInventory'];
        $report = $inventory['undeclaredEntryReport'];
        $byPath = $report['itemsByPackagePath'];
        $coverRow = $byPath['EPUB/private/cover.png'];
        $audioRow = $byPath['EPUB/private/locked.mp3'];
        $packedRow = $byPath['EPUB/private/packed.bin'];

        $t->same($report, $summary['wordpressImport']['packageInventory']['undeclaredEntryReport']);
        $t->same($report, $summary['wordpressImport']['packageInventoryUndeclaredEntryReport']);
        $t->same($report['items'], $inventory['undeclaredPackageEntries']);
        $t->same($report['items'], $summary['wordpressImport']['packageInventoryUndeclaredPackageEntries']);
        $t->same($report['itemsByPackagePath'], $inventory['undeclaredPackageEntriesByPackagePath']);
        $t->same($report['diagnostics'], $inventory['undeclaredPackageEntryDiagnostics']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['packageInventoryUndeclaredPackageEntryDiagnostics']);
        $t->same(5, $report['itemCount']);
        $t->same(4, $report['fileEntryCount']);
        $t->same(1, $report['directoryEntryCount']);
        $t->same(3, $report['exposableEntryCount']);
        $t->same(2, $report['blockedEntryCount']);
        $t->same(1, $report['encryptedEntryCount']);
        $t->same(1, $report['unsupportedCompressionMethodCount']);
        $t->same(2, $report['attachmentCandidateCount']);
        $t->same([
            'encrypted-resource' => 1,
            'undeclared-package-entry' => 5,
            'zip-directory' => 1,
        ], $report['roleCounts']);
        $t->same([
            'application/octet-stream' => 1,
            'audio/mpeg' => 1,
            'image/png' => 1,
            'text/plain' => 1,
        ], $report['inferredMediaTypeCounts']);
        $t->same([
            'asset' => 2,
            'audio' => 1,
            'directory' => 1,
            'image' => 1,
        ], $report['inferredResourceKindCounts']);
        $t->same([
            'encrypted-resource-bytes-blocked' => 1,
            'epub-package-entry-metadata-only' => 3,
            'unsupported-compression-metadata-only' => 1,
        ], $report['byteExposurePolicyCounts']);
        $t->same([
            '/EPUB/private/',
            '/EPUB/private/notes.txt',
            '/EPUB/private/cover.png',
            '/EPUB/private/locked.mp3',
            '/EPUB/private/packed.bin',
        ], $report['partNames']);
        $t->same(['/EPUB/private/cover.png', '/EPUB/private/locked.mp3'], $report['attachmentCandidatePartNames']);
        $t->same(['/EPUB/private/locked.mp3'], $report['encryptedPartNames']);
        $t->same(['/EPUB/private/packed.bin'], $report['unsupportedCompressionPartNames']);
        $t->same(7, $report['diagnosticCount']);
        $t->same([
            'undeclared-epub-package-entry',
            'undeclared-epub-package-entry-encrypted',
            'undeclared-epub-package-entry-unsupported-compression',
        ], $report['diagnosticTypes']);

        $t->same('image/png', $coverRow['inferredMediaType']);
        $t->same('extension', $coverRow['inferredMediaTypeSource']);
        $t->same('image', $coverRow['inferredResourceKind']);
        $t->same(true, $coverRow['attachmentCandidate']);
        $t->same(true, $coverRow['canExposeBytes']);
        $t->same('epub-package-entry-metadata-only', $coverRow['byteExposurePolicy']);
        $t->same('audio/mpeg', $audioRow['inferredMediaType']);
        $t->same('audio', $audioRow['inferredResourceKind']);
        $t->same(false, $audioRow['canExposeBytes']);
        $t->same(true, $audioRow['encrypted']);
        $t->same('encrypted-resource-bytes-blocked', $audioRow['byteExposurePolicy']);
        $t->same('undeclared-epub-package-entry-encrypted', $audioRow['diagnostics'][1]['type']);
        $t->same('application/octet-stream', $packedRow['inferredMediaType']);
        $t->same('fallback', $packedRow['inferredMediaTypeSource']);
        $t->same(false, $packedRow['compressionSupported']);
        $t->same('unsupported', $packedRow['compressionMethodName']);
        $t->same('unsupported-compression-metadata-only', $packedRow['byteExposurePolicy']);
        $t->same('undeclared-epub-package-entry-unsupported-compression', $packedRow['diagnostics'][1]['type']);
    },

    'reports duplicate OPF manifest property tokens for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithRepeatedProperties = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" properties="remote-resources scripted scripted"/>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithRepeatedProperties],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $summary = $epub->summary();
        $validation = $summary['validation'];
        $report = $validation['manifest']['propertyTokenReport'];
        $chapter = $report['itemsById']['chapter1'];
        $diagnostic = $report['diagnostics'][0];

        $t->same(false, $validation['valid']);
        $t->same(false, $validation['manifest']['valid']);
        $t->same(3, $report['itemCount']);
        $t->same(5, $report['propertyTokenCount']);
        $t->same(['cover-image', 'nav', 'remote-resources', 'scripted'], $report['properties']);
        $t->same(2, $report['propertyCounts']['scripted']);
        $t->same(['chapter1'], $report['propertyIds']['scripted']);
        $t->same(['/EPUB/text/chapter1.xhtml'], $report['propertyPartNames']['scripted']);
        $t->same(1, $report['duplicatePropertyItemCount']);
        $t->same(1, $report['duplicatePropertyTokenCount']);
        $t->same(['remote-resources', 'scripted', 'scripted'], $chapter['properties']);
        $t->same(['remote-resources', 'scripted'], $chapter['uniqueProperties']);
        $t->same(['scripted' => 2], $chapter['duplicateProperties']);
        $t->same(true, $chapter['hasDuplicateProperties']);
        $t->same('duplicate-manifest-property-token', $diagnostic['type']);
        $t->same('chapter1', $diagnostic['id']);
        $t->same('/EPUB/text/chapter1.xhtml', $diagnostic['partName']);
        $t->same('scripted', $diagnostic['property']);
        $t->same(2, $diagnostic['count']);
        $t->same($report, $summary['wordpressImport']['manifestPropertyTokenReport']);
        $t->same($report['items'], $summary['wordpressImport']['manifestPropertyTokenItems']);
        $t->same($report['duplicatePropertyItems'], $summary['wordpressImport']['manifestDuplicatePropertyTokenItems']);
        $t->same($report['diagnostics'], $summary['wordpressImport']['manifestPropertyTokenDiagnostics']);
    },
];
