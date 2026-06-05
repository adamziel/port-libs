<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$containerXml = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="EPUB/fixed/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$opfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" id="source-package" version="3.0" unique-identifier="source-id" prefix="schema: https://schema.org/ marc: http://id.loc.gov/vocabulary/relators/">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="source-id">urn:uuid:wordpress-epub-source</dc:identifier>
    <dc:title>WordPress EPUB source packet</dc:title>
    <dc:creator id="creator">Migration Desk</dc:creator>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-04T21:45:00Z</meta>
    <meta property="media:duration">0:00:08.000</meta>
    <meta property="media:duration" refines="#mo-chapter">0:00:08.000</meta>
    <meta property="schema:accessMode">textual</meta>
    <meta property="schema:accessMode">visual</meta>
    <meta property="schema:accessModeSufficient">textual</meta>
    <meta property="schema:accessibilityFeature">alternativeText</meta>
    <meta property="schema:accessibilityFeature">MathML</meta>
    <meta property="schema:accessibilityFeature">pageNavigation</meta>
    <meta property="schema:accessibilityHazard">noFlashingHazard</meta>
    <meta property="schema:accessibilityHazard">noSoundHazard</meta>
    <meta property="schema:accessibilitySummary">Images have alternative text and MathML is preserved for review.</meta>
    <meta property="a11y:certifiedBy">Migration Desk</meta>
    <meta property="dcterms:conformsTo">EPUB Accessibility 1.1 - WCAG 2.1 AA</meta>
    <meta refines="#source-id" property="identifier-type" scheme="onix:codelist5">15</meta>
    <meta refines="#creator" property="file-as">Desk, Migration</meta>
    <meta refines="#creator" property="role" scheme="marc:relators">aut</meta>
    <meta refines="#creator" property="display-seq">1</meta>
    <meta refines="#source-package" property="schema:name">WordPress source package record</meta>
    <meta refines="#chapter" property="schema:name">Source chapter publication resource</meta>
    <meta refines="#source-spine" property="schema:position">primary reading order</meta>
    <meta refines="#chapter-spine" property="rendition:viewport">width=1024,height=768</meta>
    <meta name="cover" content="cover-image"/>
    <link id="review-record" rel="record alternate" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org reviewer" hreflang="en"/>
    <link id="remote-onix" rel="record" href="https://metadata.example.test/onix/source.xml" media-type="application/xml" properties="onix"/>
    <link id="creator-voicing" rel="voicing" refines="#creator" href="audio/creator-name.mp3" media-type="audio/mpeg"/>
    <link id="a11y-record" rel="record accessibility-summary" href="meta/accessibility.json" media-type="application/ld+json" properties="accessibility-metadata schema-org"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" properties="mathml svg remote-resources" media-overlay="mo-chapter"/>
    <item id="slideshow" href="slides/source-slideshow.xml" media-type="application/x-demo-slideshow" fallback="slideshow-handler"/>
    <item id="slideshow-handler" href="text/slideshow-fallback.xhtml" media-type="application/xhtml+xml" properties="scripted"/>
    <item id="mo-chapter" href="overlays/chapter.smil" media-type="application/smil+xml"/>
    <item id="audio-chapter" href="audio/chapter.mp3" media-type="audio/mpeg"/>
    <item id="remote-audio-note" href="https://cdn.example.test/audio/source-note.mp3" media-type="audio/mpeg"/>
    <item id="style" href="styles/review.css" media-type="text/css"/>
    <item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>
    <item id="cover-image" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine id="source-spine" toc="toc" page-progression-direction="rtl">
    <itemref id="chapter-spine" idref="chapter" linear="maybe" properties="rendition:page-spread-right page-spread-right"/>
    <itemref idref="slideshow" linear="no" properties="page-spread-left"/>
  </spine>
  <guide>
    <reference type="text" title="Begin source" href="text/chapter.xhtml#source"/>
    <reference type="cover" title="Cover image" href="images/cover.png"/>
  </guide>
  <collection id="source-set" role="set" xml:lang="en">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>WordPress source collection</dc:title>
      <meta property="group-position">1</meta>
    </metadata>
    <link rel="first" href="text/chapter.xhtml#source" media-type="application/xhtml+xml" properties="preview"/>
    <link rel="record" href="https://example.invalid/wp-source" media-type="text/html"/>
  </collection>
  <bindings>
    <mediaType media-type="application/x-demo-slideshow" handler="slideshow-handler"/>
    <mediaType media-type="application/x-review-widget" handler="missing-widget-handler"/>
  </bindings>
</package>
XML;

$alternateOpfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="fixed-id" xml:lang="en">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="fixed-id">urn:uuid:wordpress-epub-fixed-layout</dc:identifier>
    <dc:title>WordPress EPUB fixed-layout review packet</dc:title>
    <dc:creator>Migration Layout Desk</dc:creator>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-04T22:15:00Z</meta>
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

$navXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li>
          <a href="text/chapter.xhtml#source">Source chapter</a>
          <ol>
            <li><a href="text/chapter.xhtml#epubcfi(/6/2[source]!/4/2/1:12)">CFI review offset</a></li>
          </ol>
        </li>
        <li><a href="https://cdn.example.test/epub/source-note.html">Remote source note</a></li>
      </ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="text/chapter.xhtml#source">Begin source</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter.xhtml#page-1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

$chapterXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1 id="source">Source chapter</h1>
    <span id="page-1"></span>
    <p>EPUB XHTML content is preserved for WordPress import review.</p>
    <p>Remote media marker: <img src="https://cdn.example.test/images/source.png" alt="remote source"/></p>
    <math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi><mo>=</mo><mn>1</mn></math>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><title>Source mark</title><circle cx="5" cy="5" r="4"/></svg>
  </body>
</html>
XML;

$slideshowFallbackXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1>Source slideshow fallback</h1>
    <p>Scripted EPUB slideshow fallback is preserved for WordPress review.</p>
  </body>
</html>
XML;

$smilXml = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <seq id="chapter-overlay" epub:textref="../text/chapter.xhtml">
      <par id="source-audio" epub:type="bodymatter">
        <text src="../text/chapter.xhtml#source"/>
        <audio src="../audio/chapter.mp3" clipBegin="0:00:00.000" clipEnd="0:00:04.250"/>
      </par>
      <par id="page-audio" epub:type="pagebreak">
        <text src="../text/chapter.xhtml#page-1"/>
        <audio src="../audio/chapter.mp3" clipBegin="0:00:04.250" clipEnd="0:00:05.000"/>
      </par>
      <par id="remote-audio" epub:type="annotation">
        <text src="../text/chapter.xhtml#source"/>
        <audio src="https://cdn.example.test/audio/source-note.mp3" clipBegin="0:00:05.000" clipEnd="0:00:08.000"/>
      </par>
    </seq>
  </body>
</smil>
XML;

$ncxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="source" playOrder="1">
      <navLabel><text>Source chapter</text></navLabel>
      <content src="text/chapter.xhtml#source"/>
    </navPoint>
    <navPoint id="remote-note" playOrder="2">
      <navLabel><text>Remote source note</text></navLabel>
      <content src="https://cdn.example.test/epub/source-note.html"/>
    </navPoint>
  </navMap>
</ncx>
XML;

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

$rightsXml = <<<'XML'
<rights xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:drm="https://example.invalid/epub-drm" xml:lang="en">
  <drm:license id="local-license" href="META-INF/licenses/source-license.xml" media-type="application/xml">Migration license</drm:license>
  <drm:policy id="remote-policy" href="https://rights.example.invalid/policy.xml">Remote policy</drm:policy>
</rights>
XML;

$signaturesXml = <<<'XML'
<signatures xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:Signature Id="package-signature">
    <ds:SignedInfo>
      <ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
      <ds:Reference URI="EPUB/text/chapter.xhtml#source">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>chapter-digest</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="https://signatures.example.invalid/source-manifest.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>remote-digest</ds:DigestValue>
      </ds:Reference>
    </ds:SignedInfo>
    <ds:SignatureValue>signed-review-packet</ds:SignatureValue>
  </ds:Signature>
</signatures>
XML;

$packageParts = [
    ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/container.xml', 'data' => $containerXml],
    ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
    ['name' => 'META-INF/rights.xml', 'data' => $rightsXml],
    ['name' => 'META-INF/signatures.xml', 'data' => $signaturesXml],
    ['name' => 'META-INF/licenses/source-license.xml', 'data' => '<license source="wordpress-import">review required</license>'],
    ['name' => 'EPUB/package.opf', 'data' => $opfXml],
    ['name' => 'EPUB/fixed/package.opf', 'data' => $alternateOpfXml],
    ['name' => 'EPUB/meta/review-record.json', 'data' => '{"@context":"https://schema.org","name":"WordPress EPUB review record"}'],
    ['name' => 'EPUB/meta/accessibility.json', 'data' => '{"@context":"https://schema.org","accessibilitySummary":"Reviewer accessibility record"}'],
    ['name' => 'EPUB/nav.xhtml', 'data' => $navXhtml],
    ['name' => 'EPUB/text/chapter.xhtml', 'data' => $chapterXhtml],
    ['name' => 'EPUB/text/slideshow-fallback.xhtml', 'data' => $slideshowFallbackXhtml],
    ['name' => 'EPUB/slides/source-slideshow.xml', 'data' => '<slides><slide src="../images/cover.png"/></slides>'],
    ['name' => 'EPUB/overlays/chapter.smil', 'data' => $smilXml],
    ['name' => 'EPUB/audio/chapter.mp3', 'data' => 'MP3-DATA'],
    ['name' => 'EPUB/styles/review.css', 'data' => 'body { color: #222; }'],
    ['name' => 'EPUB/fonts/source.otf', 'data' => 'OBFUSCATED-FONT'],
    ['name' => 'EPUB/images/cover.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'EPUB/images/unmanifested-review.png', 'data' => 'UNMANIFESTED-PNG', 'compressionMethod' => 0],
    ['name' => 'EPUB/toc.ncx', 'data' => $ncxXml],
];
$package = ZipPackage::fromParts($packageParts);
$withPackagePartData = static function (array $parts, string $name, string $data): array {
    foreach ($parts as $index => $part) {
        if (($part['name'] ?? null) === $name) {
            $parts[$index]['data'] = $data;

            return $parts;
        }
    }

    throw new RuntimeException('Missing EPUB fixture part: ' . $name);
};

$reader = new EpubReader();
$result = $reader->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (($argv[1] ?? '') === '--self-test') {
    if ($result['metadata']['title'] !== 'WordPress EPUB source packet') {
        throw new RuntimeException('Expected EPUB OPF title metadata');
    }
    if (($result['metadata']['uniqueIdentifier']['id'] ?? null) !== 'source-id' || ($result['metadata']['uniqueIdentifier']['value'] ?? null) !== 'urn:uuid:wordpress-epub-source') {
        throw new RuntimeException('Expected EPUB OPF unique identifier binding to be preserved');
    }
    if (($result['metadata']['uniqueIdentifier']['valid'] ?? null) !== true || ($result['metadata']['uniqueIdentifier']['diagnostics'] ?? null) !== []) {
        throw new RuntimeException('Expected EPUB OPF unique identifier binding to avoid review diagnostics');
    }
    if (($result['package']['uniqueIdentifier']['selectedBy'] ?? null) !== 'unique-identifier') {
        throw new RuntimeException('Expected EPUB package report to expose the canonical identifier source');
    }
    $missingIdentifierParts = $withPackagePartData(
        $packageParts,
        'EPUB/package.opf',
        str_replace('unique-identifier="source-id"', 'unique-identifier="missing-id"', $opfXml)
    );
    $missingIdentifierResult = $reader->readPackage(ZipPackage::fromParts($missingIdentifierParts));
    if (($missingIdentifierResult['metadata']['uniqueIdentifier']['diagnostics'][0]['type'] ?? null) !== 'unique-identifier-not-found') {
        throw new RuntimeException('Expected unresolved EPUB unique identifier to remain a review diagnostic');
    }
    if (($missingIdentifierResult['metadata']['uniqueIdentifier']['value'] ?? null) !== 'urn:uuid:wordpress-epub-source') {
        throw new RuntimeException('Expected unresolved EPUB unique identifier to keep first dc:identifier fallback visible');
    }
    if (($result['package']['prefixes']['schema'] ?? null) !== 'https://schema.org/' || ($result['package']['prefixes']['marc'] ?? null) !== 'http://id.loc.gov/vocabulary/relators/') {
        throw new RuntimeException('Expected EPUB OPF prefix declarations to be preserved for metadata vocabulary review');
    }
    if (($result['importReport']['package']['prefixBindings'][0]['prefix'] ?? null) !== 'schema') {
        throw new RuntimeException('Expected EPUB import report to expose OPF prefix bindings');
    }
    if ($result['spine'][0]['part'] !== '/EPUB/text/chapter.xhtml') {
        throw new RuntimeException('Expected spine chapter part to resolve relative to the OPF');
    }
    if (($result['spineProperties']['pageProgressionDirection'] ?? null) !== 'rtl' || ($result['spineProperties']['rightToLeft'] ?? null) !== true) {
        throw new RuntimeException('Expected EPUB OPF spine reading order to preserve right-to-left page progression');
    }
    if (($result['importReport']['spine']['properties']['pageProgressionDirection'] ?? null) !== 'rtl') {
        throw new RuntimeException('Expected EPUB import report to expose spine page progression direction');
    }
    if (($result['spine'][0]['pageSpread'] ?? null) !== 'right' || ($result['spine'][1]['pageSpread'] ?? null) !== 'left') {
        throw new RuntimeException('Expected EPUB spine itemrefs to preserve package-declared page-spread placement');
    }
    if (($result['document']->children[0]->attr('pageProgressionDirection') ?? null) !== 'rtl' || ($result['document']->children[0]->attr('pageSpread') ?? null) !== 'right') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB reading-order metadata');
    }
    if (($result['spine'][0]['linearRaw'] ?? null) !== 'maybe' || ($result['spine'][0]['linearValid'] ?? null) !== false) {
        throw new RuntimeException('Expected invalid EPUB spine linear value to remain visible for review');
    }
    if (($result['spineProperties']['itemDiagnostics'][0]['type'] ?? null) !== 'invalid-spine-linear-value') {
        throw new RuntimeException('Expected invalid EPUB spine linear value to be reported as a package diagnostic');
    }
    if (($result['document']->children[0]->attr('linearRaw') ?? null) !== 'maybe' || ($result['document']->children[0]->attr('linearValid') ?? null) !== false) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose invalid EPUB spine linear metadata');
    }
    $nonLinearParts = $withPackagePartData(
        $packageParts,
        'EPUB/package.opf',
        str_replace('linear="maybe"', 'linear="no"', $opfXml)
    );
    $nonLinearResult = $reader->readPackage(ZipPackage::fromParts($nonLinearParts));
    if (($nonLinearResult['spineProperties']['linearItemCount'] ?? null) !== 0 || ($nonLinearResult['spineProperties']['primaryReadingOrderEmpty'] ?? null) !== true) {
        throw new RuntimeException('Expected all non-linear EPUB spine itemrefs to report an empty primary reading order');
    }
    if (($nonLinearResult['spineProperties']['diagnostics'][0]['type'] ?? null) !== 'spine-has-no-linear-items') {
        throw new RuntimeException('Expected all non-linear EPUB spine itemrefs to produce a package diagnostic');
    }
    if (($nonLinearResult['document']->attr('spineProperties')['linearItemCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected WordPress document handoff to expose non-linear-only spine metadata');
    }
    if (($result['document']->children[1]->attr('pageSpread') ?? null) !== 'left') {
        throw new RuntimeException('Expected WordPress fallback handoff block to expose EPUB page-spread metadata');
    }
    if (($result['spine'][1]['contentId'] ?? null) !== 'slideshow-handler' || ($result['spine'][1]['contentPart'] ?? null) !== '/EPUB/text/slideshow-fallback.xhtml') {
        throw new RuntimeException('Expected EPUB foreign spine item to resolve to its XHTML fallback handler');
    }
    if (($result['document']->children[1]->attr('source') ?? null) !== 'epub3-spine-fallback') {
        throw new RuntimeException('Expected EPUB document AST to mark the slideshow handler as a fallback block');
    }
    if (!str_contains((string) $result['document']->children[1]->attr('html'), 'Scripted EPUB slideshow fallback is preserved')) {
        throw new RuntimeException('Expected EPUB fallback XHTML to remain reviewable in the AST');
    }
    if (($result['nav']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB nav href to resolve to the chapter fragment');
    }
    if (($result['nav']['items'][0]['children'][0]['fragmentKind'] ?? null) !== 'epub-cfi') {
        throw new RuntimeException('Expected EPUB nav CFI fragment to be classified for review');
    }
    if (($result['nav']['items'][0]['children'][0]['epubCfi']['path'] ?? null) !== '/6/2[source]!/4/2/1:12') {
        throw new RuntimeException('Expected EPUB nav CFI path to remain visible for review');
    }
    if (($result['nav']['items'][1]['external'] ?? null) !== true || ($result['nav']['items'][1]['diagnostics'][0]['type'] ?? null) !== 'external-nav-reference') {
        throw new RuntimeException('Expected remote EPUB nav reference to stay unfetched for review');
    }
    if (($result['ncx']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected NCX content src to resolve to the chapter fragment');
    }
    if (($result['ncx']['items'][1]['external'] ?? null) !== true || ($result['ncx']['items'][1]['diagnostics'][0]['type'] ?? null) !== 'external-ncx-reference') {
        throw new RuntimeException('Expected remote NCX reference to stay unfetched for review');
    }
    if (($result['navigation']['targetCount'] ?? null) !== 5 || ($result['navigation']['mappedSpineTargetCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected EPUB nav/NCX targets to reconcile with resolved spine coverage');
    }
    if (($result['navigation']['cfiTargetCount'] ?? null) !== 1 || ($result['document']->attr('navigation')['cfiTargets'][0]['fragmentKind'] ?? null) !== 'epub-cfi') {
        throw new RuntimeException('Expected EPUB navigation report to summarize CFI targets');
    }
    if (($result['navigation']['externalTargetCount'] ?? null) !== 2 || ($result['navigation']['uncoveredLinearSpineItemCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected EPUB navigation report to keep remote targets visible without flagging covered linear spine items');
    }
    if (($result['navigation']['spineCoverage'][0]['targetCount'] ?? null) !== 3 || ($result['navigation']['spineCoverage'][0]['idref'] ?? null) !== 'chapter') {
        throw new RuntimeException('Expected EPUB navigation coverage to attach nav and NCX targets to the source chapter');
    }
    if (($result['importReport']['navigation']['externalTargetCount'] ?? null) !== 2 || ($result['document']->attr('navigation')['mappedSpineTargetCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose navigation coverage metadata');
    }
    if (($result['nav']['landmarks'][0]['type'] ?? null) !== 'bodymatter') {
        throw new RuntimeException('Expected EPUB nav landmarks to preserve item type');
    }
    if (($result['nav']['pageList'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#page-1') {
        throw new RuntimeException('Expected EPUB page-list target to resolve to the source page marker');
    }
    if (($result['pageBreaks']['count'] ?? null) !== 1 || ($result['pageBreaks']['items'][0]['fragment'] ?? null) !== 'page-1') {
        throw new RuntimeException('Expected EPUB page-list entries to be summarized as page-break metadata');
    }
    if (($result['pageBreaks']['items'][0]['spineIdref'] ?? null) !== 'chapter' || ($result['document']->children[0]->attr('pageBreakCount') ?? null) !== 1) {
        throw new RuntimeException('Expected WordPress spine block to expose EPUB page-break metadata');
    }
    if (($result['guide']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB OPF guide text target to resolve to the source chapter');
    }
    if (($result['guide']['items'][1]['manifestId'] ?? null) !== 'cover-image') {
        throw new RuntimeException('Expected EPUB OPF guide cover reference to match the cover manifest item');
    }
    if (($result['collections'][0]['role'] ?? null) !== 'set') {
        throw new RuntimeException('Expected EPUB OPF collection role to be preserved');
    }
    if (($result['collections'][0]['metadata']['title'] ?? null) !== 'WordPress source collection') {
        throw new RuntimeException('Expected EPUB OPF collection metadata title');
    }
    if (($result['collections'][0]['links'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB OPF collection internal link to resolve to the source chapter');
    }
    if (($result['collections'][0]['links'][1]['diagnostics'][0]['type'] ?? null) !== 'external-collection-link') {
        throw new RuntimeException('Expected EPUB OPF collection external link to be reported without fetching');
    }
    if (($result['metadata']['links'][0]['target'] ?? null) !== '/EPUB/meta/review-record.json') {
        throw new RuntimeException('Expected EPUB OPF metadata link to resolve to the review record package part');
    }
    if (($result['metadata']['links'][0]['byteSha256'] ?? null) !== hash('sha256', '{"@context":"https://schema.org","name":"WordPress EPUB review record"}')) {
        throw new RuntimeException('Expected EPUB OPF metadata linked record hash for review deduplication');
    }
    if (($result['metadata']['linksByRel']['record'][0]['id'] ?? null) !== 'review-record') {
        throw new RuntimeException('Expected EPUB OPF metadata links to be indexed by rel');
    }
    if (($result['metadata']['links'][1]['diagnostics'][0]['type'] ?? null) !== 'external-metadata-reference') {
        throw new RuntimeException('Expected remote EPUB OPF metadata link to stay unfetched');
    }
    if (($result['metadata']['links'][2]['diagnostics'][0]['type'] ?? null) !== 'missing-metadata-reference') {
        throw new RuntimeException('Expected missing EPUB OPF metadata link to remain a review diagnostic');
    }
    if (($result['metadata']['refinementsById']['source-id']['identifier-type'][0]['text'] ?? null) !== '15') {
        throw new RuntimeException('Expected EPUB OPF identifier-type refinement to stay attached to source-id');
    }
    if (($result['metadata']['dc']['identifier'][0]['refinements']['identifier-type'][0]['scheme'] ?? null) !== 'onix:codelist5') {
        throw new RuntimeException('Expected EPUB OPF identifier refinement scheme to remain reviewable');
    }
    if (($result['metadata']['dc']['creator'][0]['refinements']['file-as'][0]['text'] ?? null) !== 'Desk, Migration') {
        throw new RuntimeException('Expected EPUB OPF creator file-as refinement to stay attached to creator metadata');
    }
    if (($result['metadata']['dc']['creator'][0]['refinements']['role'][0]['scheme'] ?? null) !== 'marc:relators') {
        throw new RuntimeException('Expected EPUB OPF creator role scheme to stay reviewable');
    }
    if (($result['package']['id'] ?? null) !== 'source-package' || ($result['package']['refinements']['schema:name'][0]['text'] ?? null) !== 'WordPress source package record') {
        throw new RuntimeException('Expected EPUB OPF package-level refinements to remain reviewable');
    }
    if (($result['manifest'][1]['refinements']['schema:name'][0]['text'] ?? null) !== 'Source chapter publication resource') {
        throw new RuntimeException('Expected EPUB manifest resource refinements to attach to publication resources');
    }
    if (($result['spineProperties']['id'] ?? null) !== 'source-spine' || ($result['spineProperties']['refinements']['schema:position'][0]['text'] ?? null) !== 'primary reading order') {
        throw new RuntimeException('Expected EPUB spine-level refinements to remain reviewable');
    }
    if (($result['spine'][0]['id'] ?? null) !== 'chapter-spine' || ($result['spine'][0]['refinements']['rendition:viewport'][0]['text'] ?? null) !== 'width=1024,height=768') {
        throw new RuntimeException('Expected EPUB spine itemref refinements to remain attached to the reading-order item');
    }
    if (($result['document']->children[0]->attr('spineItemId') ?? null) !== 'chapter-spine' || ($result['document']->children[0]->attr('refinements')['rendition:viewport'][0]['text'] ?? null) !== 'width=1024,height=768') {
        throw new RuntimeException('Expected WordPress spine block to expose EPUB itemref refinements');
    }
    if (($result['accessibility']['accessModes'] ?? []) !== ['textual', 'visual']) {
        throw new RuntimeException('Expected EPUB accessibility access modes to be summarized');
    }
    if (($result['accessibility']['accessibilityFeatures'] ?? []) !== ['alternativeText', 'MathML', 'pageNavigation']) {
        throw new RuntimeException('Expected EPUB accessibility feature metadata to be summarized');
    }
    if (($result['accessibility']['accessibilityHazards'] ?? []) !== ['noFlashingHazard', 'noSoundHazard']) {
        throw new RuntimeException('Expected EPUB accessibility hazard metadata to be summarized');
    }
    if (($result['accessibility']['certification']['conformsTo'][0] ?? null) !== 'EPUB Accessibility 1.1 - WCAG 2.1 AA') {
        throw new RuntimeException('Expected EPUB accessibility conformance metadata to be summarized');
    }
    if (($result['accessibility']['linkedRecords'][0]['target'] ?? null) !== '/EPUB/meta/accessibility.json') {
        throw new RuntimeException('Expected EPUB linked accessibility record to resolve to a package part');
    }
    if (($result['document']->attr('accessibility')['accessibilitySummary'] ?? null) !== 'Images have alternative text and MathML is preserved for review.') {
        throw new RuntimeException('Expected WordPress document handoff to expose EPUB accessibility summary');
    }
    if (($result['resourceProperties']['summary']['mathmlCount'] ?? null) !== 1 || ($result['resourceProperties']['summary']['svgCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB resource-property report to count MathML and SVG content markers');
    }
    if (($result['resourceProperties']['summary']['remoteResourcesCount'] ?? null) !== 1 || ($result['resourceProperties']['summary']['scriptedCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB resource-property report to count remote-resource and scripted markers');
    }
    if (($result['resourceProperties']['itemsById']['chapter']['reviewFlags'] ?? []) !== ['mathml', 'svg', 'remote-resources']) {
        throw new RuntimeException('Expected EPUB chapter resource review flags for MathML, SVG, and remote resources');
    }
    if (($result['resourceProperties']['itemsById']['slideshow-handler']['reviewFlags'] ?? []) !== ['scripted']) {
        throw new RuntimeException('Expected EPUB fallback handler resource review flag for scripting');
    }
    if (($result['document']->children[0]->attr('resourceReviewFlags') ?? []) !== ['mathml', 'svg', 'remote-resources']) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB resource review flags');
    }
    if (($result['document']->children[1]->attr('resourceReviewFlags') ?? []) !== ['scripted']) {
        throw new RuntimeException('Expected WordPress fallback handoff block to expose scripted resource review flag');
    }
    if (($result['xhtmlResourceReport']['externalReferenceCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB XHTML content scan to keep remote references unfetched for review');
    }
    if (($result['xhtmlResourceReport']['mathmlAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['svgAssetCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB XHTML content scan to identify embedded MathML and SVG markers');
    }
    if (($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['reviewFlags'] ?? []) !== ['mathml', 'svg', 'remote-resources']) {
        throw new RuntimeException('Expected EPUB XHTML content review flags for the source chapter');
    }
    if (($result['document']->children[0]->attr('contentResourceReviewFlags') ?? []) !== ['mathml', 'svg', 'remote-resources']) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB XHTML content review flags');
    }
    if (($result['document']->children[0]->attr('contentReferences')[0]['target'] ?? null) !== 'https://cdn.example.test/images/source.png') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose remote XHTML content reference diagnostics');
    }
    if (($result['remoteResources']['declaredCount'] ?? null) !== 1 || ($result['remoteResources']['observedAssetCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB remote-resources declarations to reconcile with observed XHTML resource references');
    }
    if (($result['remoteResources']['remoteReferenceCount'] ?? null) !== 1 || ($result['remoteResources']['xhtmlExternalReferenceCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB remote resource report to separate resource loads from external navigation links');
    }
    if (($result['remoteResources']['undeclaredAssetCount'] ?? null) !== 0 || ($result['remoteResources']['declaredButUnobservedCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected EPUB declared remote resources to avoid mismatch diagnostics in the review packet');
    }
    if (($result['remoteResources']['observedItemsByPart']['/EPUB/text/chapter.xhtml']['remoteReferences'][0]['target'] ?? null) !== 'https://cdn.example.test/images/source.png') {
        throw new RuntimeException('Expected EPUB remote resource report to expose the chapter remote image without fetching it');
    }
    if (($result['document']->attr('remoteResources')['remoteReferenceCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected WordPress document handoff to expose EPUB remote resource reconciliation metadata');
    }
    if (($result['renditions']['count'] ?? null) !== 2 || ($result['renditions']['alternateCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB multiple rootfile renditions to be summarized');
    }
    if (($result['renditions']['items'][0]['selected'] ?? null) !== true || ($result['renditions']['items'][0]['path'] ?? null) !== '/EPUB/package.opf') {
        throw new RuntimeException('Expected EPUB primary rendition to remain selected');
    }
    if (($result['renditions']['items'][1]['metadata']['title'] ?? null) !== 'WordPress EPUB fixed-layout review packet') {
        throw new RuntimeException('Expected alternate EPUB rendition metadata title');
    }
    if (($result['renditions']['items'][1]['renditionProperties']['layout'] ?? null) !== 'pre-paginated') {
        throw new RuntimeException('Expected alternate EPUB rendition layout metadata');
    }
    if (($result['document']->attr('renditions')['alternateCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB document attrs to expose rendition review metadata');
    }
    if (($result['bindings']['items'][0]['handlerId'] ?? null) !== 'slideshow-handler') {
        throw new RuntimeException('Expected EPUB OPF binding handler to be reported');
    }
    if (($result['bindings']['items'][0]['handlerPart'] ?? null) !== '/EPUB/text/slideshow-fallback.xhtml') {
        throw new RuntimeException('Expected EPUB OPF binding handler part to resolve');
    }
    if (($result['bindings']['items'][1]['diagnostics'][0]['type'] ?? null) !== 'missing-binding-handler-manifest-item') {
        throw new RuntimeException('Expected missing EPUB OPF binding handler to remain a review diagnostic');
    }
    if (($result['spine'][1]['binding']['handlerId'] ?? null) !== 'slideshow-handler') {
        throw new RuntimeException('Expected custom media-type spine item to carry its OPF binding handler');
    }
    if (($result['document']->children[1]->attr('binding')['handlerId'] ?? null) !== 'slideshow-handler') {
        throw new RuntimeException('Expected WordPress fallback block to expose OPF binding metadata');
    }
    if (($result['encryption']['obfuscatedFonts'][0]['part'] ?? null) !== '/EPUB/fonts/source.otf') {
        throw new RuntimeException('Expected EPUB obfuscated font preflight to identify the package font');
    }
    if (($result['ocf']['sidecarCount'] ?? null) !== 2 || ($result['ocf']['externalReferenceCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB OCF rights/signatures sidecars to report remote references without fetching');
    }
    if (($result['ocf']['rights']['items'][0]['reference']['target'] ?? null) !== '/META-INF/licenses/source-license.xml') {
        throw new RuntimeException('Expected EPUB OCF rights sidecar to resolve local license reference');
    }
    if (($result['ocf']['rights']['items'][1]['diagnostics'][0]['type'] ?? null) !== 'ocf-rights-remote-reference') {
        throw new RuntimeException('Expected EPUB OCF rights remote policy to remain unfetched');
    }
    if (($result['ocf']['signatures']['items'][0]['references'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB OCF signature reference to resolve package-root target');
    }
    if (($result['ocf']['signatures']['items'][0]['references'][1]['diagnostics'][0]['type'] ?? null) !== 'ocf-signature-remote-reference') {
        throw new RuntimeException('Expected EPUB OCF signature remote reference to remain unfetched');
    }
    if (($result['document']->attr('ocf')['signatures']['signatureCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose OCF signature metadata');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][0]['audioTarget'] ?? null) !== '/EPUB/audio/chapter.mp3') {
        throw new RuntimeException('Expected EPUB media-overlay audio target to resolve relative to the SMIL part');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][0]['clipBeginSeconds'] ?? null) !== 0.0 || ($result['mediaOverlays']['mo-chapter']['items'][0]['clipEndSeconds'] ?? null) !== 4.25) {
        throw new RuntimeException('Expected EPUB media-overlay first clip timing to normalize to seconds');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][0]['clipDurationSeconds'] ?? null) !== 4.25 || ($result['mediaOverlays']['mo-chapter']['items'][0]['clipValid'] ?? null) !== true) {
        throw new RuntimeException('Expected EPUB media-overlay first clip duration to stay reviewable');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][1]['textTarget'] ?? null) !== '/EPUB/text/chapter.xhtml#page-1') {
        throw new RuntimeException('Expected EPUB media-overlay page marker to stay addressable for review');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][1]['clipDurationSeconds'] ?? null) !== 0.75) {
        throw new RuntimeException('Expected EPUB media-overlay page marker clip duration to normalize to seconds');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][2]['audioExternal'] ?? null) !== true || ($result['mediaOverlays']['mo-chapter']['items'][2]['diagnostics'][0]['type'] ?? null) !== 'external-media-overlay-reference') {
        throw new RuntimeException('Expected remote EPUB media-overlay audio to stay unfetched for review');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][2]['clipDurationSeconds'] ?? null) !== 3.0) {
        throw new RuntimeException('Expected remote EPUB media-overlay clip duration to normalize without fetching audio');
    }
    if (($result['mediaDurations']['total']['duration'] ?? null) !== '0:00:08.000' || ($result['mediaDurations']['total']['durationSeconds'] ?? null) !== 8.0) {
        throw new RuntimeException('Expected EPUB package-level media duration metadata');
    }
    if (($result['mediaDurations']['overlaysById']['mo-chapter']['referencedBy'] ?? []) !== ['chapter']) {
        throw new RuntimeException('Expected EPUB media duration refinement to resolve to the SMIL overlay manifest item');
    }
    if (($result['mediaOverlays']['mo-chapter']['duration'] ?? null) !== '0:00:08.000' || ($result['mediaOverlays']['mo-chapter']['durationSeconds'] ?? null) !== 8.0) {
        throw new RuntimeException('Expected EPUB media-overlay report to expose OPF duration metadata');
    }
    if (($result['document']->attr('mediaDurations')['overlaysById']['mo-chapter']['duration'] ?? null) !== '0:00:08.000') {
        throw new RuntimeException('Expected WordPress document handoff to expose EPUB media duration metadata');
    }
    if (($result['importReport']['manifest']['externalItems'][0]['id'] ?? null) !== 'remote-audio-note') {
        throw new RuntimeException('Expected remote EPUB manifest resource to be reported separately from missing ZIP assets');
    }
    if (($result['importReport']['manifest']['missingItems'] ?? null) !== []) {
        throw new RuntimeException('Expected remote EPUB manifest resource not to be counted as a missing package item');
    }
    $foundEncryptedFont = false;
    $foundRemoteAudio = false;
    foreach ($result['assets'] as $asset) {
        if ($asset['id'] === 'font-main' && (($asset['encrypted'] ?? false) !== true || ($asset['canExposeBytes'] ?? true) !== false)) {
            throw new RuntimeException('Expected obfuscated font asset bytes to require follow-up review');
        }
        if ($asset['id'] === 'font-main') {
            $foundEncryptedFont = true;
        }
        if ($asset['id'] === 'remote-audio-note') {
            if (($asset['external'] ?? false) !== true || ($asset['diagnostics'][0]['type'] ?? null) !== 'external-manifest-resource') {
                throw new RuntimeException('Expected remote EPUB manifest audio to stay unfetched with a review diagnostic');
            }
            if (($asset['byteSha256'] ?? null) !== null || ($asset['attachmentCandidate'] ?? true) !== false) {
                throw new RuntimeException('Expected remote EPUB manifest audio bytes not to be exposed as an attachment');
            }
            $foundRemoteAudio = true;
        }
    }
    if (!$foundEncryptedFont) {
        throw new RuntimeException('Expected obfuscated font asset in EPUB import report');
    }
    if (!$foundRemoteAudio) {
        throw new RuntimeException('Expected remote EPUB manifest audio in EPUB import report');
    }
    if (($result['importReport']['assets']['coverImage']['id'] ?? null) !== 'cover-image') {
        throw new RuntimeException('Expected EPUB asset report to identify the cover image');
    }
    if (($result['importReport']['assets']['coverImage']['attachmentCandidate'] ?? null) !== true) {
        throw new RuntimeException('Expected EPUB cover image to be an attachment candidate');
    }
    if (($result['importReport']['assets']['coverImage']['byteSha256'] ?? null) !== hash('sha256', 'PNGDATA')) {
        throw new RuntimeException('Expected EPUB cover image hash for import deduplication');
    }
    if (($result['importReport']['assets']['unmanifestedItems'][0]['part'] ?? null) !== '/EPUB/images/unmanifested-review.png') {
        throw new RuntimeException('Expected unmanifested EPUB package image to stay visible for review');
    }
    foreach ($result['importReport']['assets']['unmanifestedItems'] as $unmanifestedItem) {
        if (($unmanifestedItem['part'] ?? null) === '/EPUB/meta/review-record.json') {
            throw new RuntimeException('Expected EPUB metadata-linked record not to be reported as an undeclared package asset');
        }
    }
    if (($result['importReport']['assets']['unmanifestedItems'][0]['attachmentCandidate'] ?? null) !== true) {
        throw new RuntimeException('Expected unmanifested EPUB image to be marked as a review attachment candidate');
    }
    if (!str_contains($blocks, '<!-- wp:html -->') || !str_contains($blocks, 'EPUB XHTML content is preserved')) {
        throw new RuntimeException('Expected EPUB XHTML spine item to hand off as a WordPress HTML block');
    }

    echo "epub3 package handoff self-test ok\n";
    exit(0);
}

echo "EPUB3 package handoff for WordPress import:\n";
echo 'title=' . $result['metadata']['title'] . "\n";
echo 'identifier=' . $result['metadata']['identifier'] . "\n";
echo 'uniqueIdentifierId=' . ($result['metadata']['uniqueIdentifier']['id'] ?? '') . "\n";
echo 'uniqueIdentifierSelectedBy=' . ($result['metadata']['uniqueIdentifier']['selectedBy'] ?? '') . "\n";
echo 'uniqueIdentifierDiagnostics=' . count($result['metadata']['uniqueIdentifier']['diagnostics'] ?? []) . "\n";
echo 'opfPart=' . $result['opfPart'] . "\n";
echo 'opfPrefixes=' . implode(',', array_keys($result['package']['prefixes'] ?? [])) . "\n";
echo 'schemaPrefix=' . ($result['package']['prefixes']['schema'] ?? '') . "\n";
echo 'spineItems=' . count($result['spine']) . "\n";
echo 'pageProgressionDirection=' . ($result['spineProperties']['pageProgressionDirection'] ?? '') . "\n";
echo 'rightToLeft=' . (($result['spineProperties']['rightToLeft'] ?? false) ? 'yes' : 'no') . "\n";
echo 'firstPageSpread=' . ($result['spine'][0]['pageSpread'] ?? '') . "\n";
echo 'firstLinearRaw=' . ($result['spine'][0]['linearRaw'] ?? '') . "\n";
echo 'firstLinearValid=' . (($result['spine'][0]['linearValid'] ?? true) ? 'yes' : 'no') . "\n";
echo 'linearSpineItems=' . ($result['spineProperties']['linearItemCount'] ?? 0) . "\n";
echo 'primaryReadingOrderEmpty=' . (($result['spineProperties']['primaryReadingOrderEmpty'] ?? false) ? 'yes' : 'no') . "\n";
echo 'spineLinearDiagnostics=' . count($result['spineProperties']['itemDiagnostics'] ?? []) . "\n";
echo 'fallbackPageSpread=' . ($result['spine'][1]['pageSpread'] ?? '') . "\n";
echo 'fallbackSpineContent=' . ($result['spine'][1]['contentPart'] ?? '') . "\n";
echo 'navTarget=' . ($result['nav']['items'][0]['target'] ?? '') . "\n";
echo 'remoteNavExternal=' . (($result['nav']['items'][1]['external'] ?? false) ? 'yes' : 'no') . "\n";
echo 'navCfiPath=' . ($result['nav']['items'][0]['children'][0]['epubCfi']['path'] ?? '') . "\n";
echo 'navigationTargets=' . ($result['navigation']['targetCount'] ?? 0) . "\n";
echo 'navigationMappedTargets=' . ($result['navigation']['mappedSpineTargetCount'] ?? 0) . "\n";
echo 'navigationCfiTargets=' . ($result['navigation']['cfiTargetCount'] ?? 0) . "\n";
echo 'navigationExternalTargets=' . ($result['navigation']['externalTargetCount'] ?? 0) . "\n";
echo 'navigationUncoveredLinear=' . ($result['navigation']['uncoveredLinearSpineItemCount'] ?? 0) . "\n";
echo 'landmarkTarget=' . ($result['nav']['landmarks'][0]['target'] ?? '') . "\n";
echo 'pageListTarget=' . ($result['nav']['pageList'][0]['target'] ?? '') . "\n";
echo 'pageBreaks=' . ($result['pageBreaks']['count'] ?? 0) . "\n";
echo 'firstPageBreakFragment=' . ($result['pageBreaks']['items'][0]['fragment'] ?? '') . "\n";
echo 'firstSpinePageBreaks=' . ($result['document']->children[0]->attr('pageBreakCount') ?? 0) . "\n";
echo 'guideReferences=' . count($result['guide']['items'] ?? []) . "\n";
echo 'guideTextTarget=' . ($result['guide']['items'][0]['target'] ?? '') . "\n";
echo 'collectionRole=' . ($result['collections'][0]['role'] ?? '') . "\n";
echo 'collectionFirstTarget=' . ($result['collections'][0]['links'][0]['target'] ?? '') . "\n";
echo 'metadataLinks=' . count($result['metadata']['links'] ?? []) . "\n";
echo 'metadataRecordTarget=' . ($result['metadata']['links'][0]['target'] ?? '') . "\n";
echo 'metadataRecordSha256=' . ($result['metadata']['links'][0]['byteSha256'] ?? '') . "\n";
echo 'remoteMetadataLink=' . (($result['metadata']['links'][1]['external'] ?? false) ? 'yes' : 'no') . "\n";
echo 'identifierType=' . ($result['metadata']['dc']['identifier'][0]['refinements']['identifier-type'][0]['text'] ?? '') . "\n";
echo 'creatorFileAs=' . ($result['metadata']['dc']['creator'][0]['refinements']['file-as'][0]['text'] ?? '') . "\n";
echo 'creatorRole=' . ($result['metadata']['dc']['creator'][0]['refinements']['role'][0]['text'] ?? '') . "\n";
echo 'packageRefinement=' . ($result['package']['refinements']['schema:name'][0]['text'] ?? '') . "\n";
echo 'chapterResourceRefinement=' . ($result['manifest'][1]['refinements']['schema:name'][0]['text'] ?? '') . "\n";
echo 'spineRefinement=' . ($result['spineProperties']['refinements']['schema:position'][0]['text'] ?? '') . "\n";
echo 'spineItemRefinement=' . ($result['spine'][0]['refinements']['rendition:viewport'][0]['text'] ?? '') . "\n";
echo 'accessibilityModes=' . implode(',', $result['accessibility']['accessModes'] ?? []) . "\n";
echo 'accessibilityFeatures=' . implode(',', $result['accessibility']['accessibilityFeatures'] ?? []) . "\n";
echo 'accessibilityHazards=' . implode(',', $result['accessibility']['accessibilityHazards'] ?? []) . "\n";
echo 'accessibilityConformsTo=' . implode(',', $result['accessibility']['certification']['conformsTo'] ?? []) . "\n";
echo 'accessibilityRecord=' . ($result['accessibility']['linkedRecords'][0]['target'] ?? '') . "\n";
echo 'resourceReviewItems=' . ($result['resourceProperties']['summary']['reviewRequiredCount'] ?? 0) . "\n";
echo 'chapterReviewFlags=' . implode(',', $result['resourceProperties']['itemsById']['chapter']['reviewFlags'] ?? []) . "\n";
echo 'fallbackReviewFlags=' . implode(',', $result['resourceProperties']['itemsById']['slideshow-handler']['reviewFlags'] ?? []) . "\n";
echo 'xhtmlContentRemoteReferences=' . ($result['xhtmlResourceReport']['externalReferenceCount'] ?? 0) . "\n";
echo 'xhtmlContentMathmlAssets=' . ($result['xhtmlResourceReport']['mathmlAssetCount'] ?? 0) . "\n";
echo 'chapterContentReviewFlags=' . implode(',', $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['reviewFlags'] ?? []) . "\n";
echo 'remoteResourceDeclaredItems=' . ($result['remoteResources']['declaredCount'] ?? 0) . "\n";
echo 'remoteResourceObservedAssets=' . ($result['remoteResources']['observedAssetCount'] ?? 0) . "\n";
echo 'remoteResourceReferences=' . ($result['remoteResources']['remoteReferenceCount'] ?? 0) . "\n";
echo 'remoteResourceMismatches=' . count($result['remoteResources']['diagnostics'] ?? []) . "\n";
echo 'renditions=' . ($result['renditions']['count'] ?? 0) . "\n";
echo 'alternateRenditionTitle=' . ($result['renditions']['items'][1]['metadata']['title'] ?? '') . "\n";
echo 'alternateRenditionLayout=' . ($result['renditions']['items'][1]['renditionProperties']['layout'] ?? '') . "\n";
echo 'bindings=' . count($result['bindings']['items'] ?? []) . "\n";
echo 'bindingHandler=' . ($result['bindings']['items'][0]['handlerId'] ?? '') . "\n";
echo 'bindingDiagnostics=' . count($result['bindings']['diagnostics'] ?? []) . "\n";
echo 'obfuscatedFonts=' . count($result['encryption']['obfuscatedFonts']) . "\n";
echo 'ocfSidecars=' . ($result['ocf']['sidecarCount'] ?? 0) . "\n";
echo 'ocfRightsItems=' . ($result['ocf']['rights']['itemCount'] ?? 0) . "\n";
echo 'ocfSignatureReferences=' . ($result['ocf']['signatures']['referenceCount'] ?? 0) . "\n";
echo 'ocfExternalReferences=' . ($result['ocf']['externalReferenceCount'] ?? 0) . "\n";
echo 'mediaOverlayItems=' . count($result['mediaOverlays']['mo-chapter']['items'] ?? []) . "\n";
echo 'mediaOverlayAudio=' . ($result['mediaOverlays']['mo-chapter']['items'][0]['audioTarget'] ?? '') . "\n";
echo 'mediaOverlayFirstClipSeconds=' . ($result['mediaOverlays']['mo-chapter']['items'][0]['clipDurationSeconds'] ?? '') . "\n";
echo 'mediaOverlayPageClipSeconds=' . ($result['mediaOverlays']['mo-chapter']['items'][1]['clipDurationSeconds'] ?? '') . "\n";
echo 'remoteOverlayAudio=' . ($result['mediaOverlays']['mo-chapter']['items'][2]['audioTarget'] ?? '') . "\n";
echo 'remoteOverlayClipSeconds=' . ($result['mediaOverlays']['mo-chapter']['items'][2]['clipDurationSeconds'] ?? '') . "\n";
echo 'mediaDuration=' . ($result['mediaDurations']['total']['duration'] ?? '') . "\n";
echo 'mediaOverlayDuration=' . ($result['mediaOverlays']['mo-chapter']['duration'] ?? '') . "\n";
echo 'remoteManifestResources=' . count($result['importReport']['manifest']['externalItems'] ?? []) . "\n";
echo 'assets=' . count($result['assets']) . "\n";
echo 'coverAttachment=' . ($result['importReport']['assets']['coverImage']['part'] ?? '') . "\n";
echo 'coverSha256=' . ($result['importReport']['assets']['coverImage']['byteSha256'] ?? '') . "\n";
echo 'attachmentCandidates=' . ($result['importReport']['assets']['attachmentCandidateCount'] ?? 0) . "\n";
echo 'unmanifestedAssets=' . ($result['importReport']['assets']['unmanifestedCount'] ?? 0) . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
