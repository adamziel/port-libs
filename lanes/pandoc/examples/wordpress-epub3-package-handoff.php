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
  <links>
    <link href="EPUB/meta/review-record.json" rel="record alternate" media-type="application/ld+json" properties="schema-org reviewer"/>
    <link href="https://metadata.example.test/container-record.json" rel="record" media-type="application/ld+json"/>
    <link href="EPUB/text/chapter.xhtml#epubcfi(/6/2[source]!/4/2/1:12)" rel="preview" media-type="application/xhtml+xml"/>
  </links>
</container>
XML;

$opfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" id="source-package" version="3.0" unique-identifier="source-id" prefix="schema: https://schema.org/ marc: http://id.loc.gov/vocabulary/relators/ ibooks: http://vocabulary.itunes.apple.com/rdf/ibooks/vocabulary-extensions-1.0/">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xml:lang="ar" dir="rtl">
    <dc:identifier id="source-id" scheme="UUID">urn:uuid:wordpress-epub-source</dc:identifier>
    <dc:identifier id="isbn-id" scheme="ISBN">9781234567890</dc:identifier>
    <dc:identifier id="duplicate-source-id" scheme="UUID">urn:uuid:wordpress-epub-source</dc:identifier>
    <dc:title id="main-title" dir="ltr">WordPress EPUB source packet</dc:title>
    <dc:title id="subtitle-title" xml:lang="ar-Latn" dir="rtl">Murajaat al-hijra</dc:title>
    <dc:title id="short-title">WP EPUB packet</dc:title>
    <dc:creator id="creator">Migration Desk</dc:creator>
    <dc:contributor id="editor">Review Editor</dc:contributor>
    <dc:contributor id="translator" xml:lang="fr">Translation Desk</dc:contributor>
    <dc:date id="publication-date" event="publication">2026-06-01</dc:date>
    <dc:date id="review-date">2026-06-05</dc:date>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-04T21:45:00Z</meta>
    <meta property="rendition:layout">reflowable</meta>
    <meta property="rendition:orientation">portrait</meta>
    <meta property="rendition:spread">auto</meta>
    <meta property="rendition:viewport">width=800,height=1200</meta>
    <meta property="ibooks:specified-fonts">true</meta>
    <meta property="ibooks:version">1.2</meta>
    <meta property="calibre:series" content="WordPress migration packets"/>
    <meta property="calibre:series_index" content="7"/>
    <meta refines="#main-title" property="title-type">main</meta>
    <meta refines="#main-title" property="file-as">WordPress EPUB source packet</meta>
    <meta refines="#main-title" property="display-seq">1</meta>
    <meta refines="#subtitle-title" property="title-type">subtitle</meta>
    <meta refines="#subtitle-title" property="display-seq">2</meta>
    <meta refines="#subtitle-title" property="alternate-script" xml:lang="en" dir="ltr">Migration review subtitle</meta>
    <meta refines="#short-title" property="title-type">short</meta>
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
    <meta refines="#isbn-id" property="identifier-type" scheme="onix:codelist5">15</meta>
    <meta refines="#duplicate-source-id" property="identifier-type" scheme="onix:codelist5">15</meta>
    <meta refines="#review-date" property="event">review</meta>
    <meta refines="#review-date" property="display-seq">2</meta>
    <meta refines="#creator" property="file-as">Desk, Migration</meta>
    <meta refines="#creator" property="role" scheme="marc:relators">aut</meta>
    <meta refines="#creator" property="display-seq">1</meta>
    <meta refines="#editor" property="file-as">Editor, Review</meta>
    <meta refines="#editor" property="role" scheme="marc:relators">edt</meta>
    <meta refines="#editor" property="display-seq">2</meta>
    <meta refines="#translator" property="role" scheme="marc:relators">trl</meta>
    <meta refines="#source-package" property="schema:name">WordPress source package record</meta>
    <meta refines="#chapter" property="schema:name">Source chapter publication resource</meta>
    <meta refines="#source-spine" property="schema:position">primary reading order</meta>
    <meta refines="#chapter-spine" property="rendition:viewport">width=1024,height=768</meta>
    <meta refines="#missing-review-subject" property="schema:reviewStatus">needs source audit</meta>
    <meta name="cover" content="legacy-cover"/>
    <link id="review-record" title="WordPress EPUB review record" rel="record alternate" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org reviewer" hreflang="en"/>
    <link id="remote-onix" rel="record" href="https://metadata.example.test/onix/source.xml" media-type="application/xml" properties="onix"/>
    <link id="creator-voicing" rel="voicing" refines="#creator" href="audio/creator-name.mp3" media-type="audio/mpeg"/>
    <link id="a11y-record" rel="record accessibility-summary" href="meta/accessibility.json" media-type="application/ld+json" properties="accessibility-metadata schema-org"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" properties="mathml svg remote-resources schema:encodingFormat" media-overlay="mo-chapter"/>
    <item id="slideshow" href="slides/source-slideshow.xml" media-type="application/x-demo-slideshow" fallback="slideshow-handler" fallback-style="style"/>
    <item id="slideshow-handler" href="text/slideshow-fallback.xhtml" media-type="application/xhtml+xml" properties="scripted"/>
    <item id="bound-tour" href="interactive/tour.bin" media-type="application/x-bound-tour"/>
    <item id="bound-tour-handler" href="text/bound-tour-fallback.xhtml" media-type="application/xhtml+xml" properties="scripted"/>
    <item id="missing-fallback-widget" href="interactive/missing-fallback.bin" media-type="application/x-review-widget" fallback="missing-review-handler"/>
    <item id="mo-chapter" href="overlays/chapter.smil" media-type="application/smil+xml"/>
    <item id="audio-chapter" href="audio/chapter.mp3" media-type="audio/mpeg"/>
    <item id="remote-audio-note" href="https://cdn.example.test/audio/source-note.mp3" media-type="audio/mpeg"/>
    <item id="style" href="styles/review.css" media-type="text/css"/>
    <item id="review-submit" href="meta/review-submit.xhtml" media-type="application/xhtml+xml"/>
    <item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>
    <item id="cover-image" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="legacy-cover" href="images/legacy-cover.jpg" media-type="image/jpeg"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="toc-alias" href="toc.ncx#review" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine id="source-spine" toc="toc" page-progression-direction="rtl">
    <itemref id="chapter-spine" idref="chapter" linear="maybe" properties="rendition:page-spread-right page-spread-right rendition:flow-paginated rendition:align-x-center rendition:layout-pre-paginated rendition:orientation-landscape rendition:spread-none"/>
    <itemref idref="slideshow" linear="no" properties="page-spread-left"/>
    <itemref idref="bound-tour" linear="no"/>
  </spine>
  <guide>
    <reference type="text" title="Begin source" href="text/chapter.xhtml#source"/>
    <reference type="cover" title="Cover image" href="images/cover.png"/>
  </guide>
  <collection id="source-set" role="set" xml:lang="en" dir="rtl">
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
    <mediaType media-type="application/x-bound-tour" handler="bound-tour-handler"/>
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
    <nav id="source-toc" class="source-navigation" epub:type="toc" xml:lang="en" dir="ltr">
      <ol>
        <li epub:type="bodymatter chapter">
          <a id="source-toc-link" class="source-link" xml:lang="en" href="text/chapter.xhtml#source">Source chapter</a>
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
    <nav id="review-figures" epub:type="loi list-of-illustrations">
      <h2>Reviewer figures</h2>
      <ol>
        <li><a href="text/chapter.xhtml#cover-figure">Cover figure</a></li>
        <li><a href="https://cdn.example.test/figures/source.svg">Remote figure source</a></li>
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
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xmlns:ev="http://www.w3.org/2001/xml-events">
  <head>
    <title>Source chapter</title>
    <meta name="viewport" content="width=1024,height=768"/>
    <meta id="source-refresh" http-equiv="refresh" content="0; url=#source"/>
    <link id="chapter-style-link" rel="stylesheet" href="../styles/review.css" type="text/css" media="screen"/>
    <style id="chapter-inline-style" media="screen">
      .inline-cover { background-image: url("../images/cover.png"); }
    </style>
  </head>
  <body epub:type="bodymatter chapter" xml:lang="en" dir="ltr">
    <h1 id="source" epub:type="title">Source chapter</h1>
    <span id="page-1" epub:type="pagebreak" title="1"></span>
    <p>EPUB XHTML content is preserved for WordPress import review.</p>
    <p id="inline-style-review" style="background-image: url('../images/legacy-cover.jpg')">Inline CSS resource metadata stays available for import review.</p>
    <p>Remote media marker: <img src="https://cdn.example.test/images/source.png" alt="remote source"/></p>
    <p>Responsive cover candidate: <img src="../images/cover.png" srcset="../images/cover.png 1x, ../images/legacy-cover.jpg 2x" alt="responsive cover"/></p>
    <form id="source-review-form" action="https://forms.example.test/epub/source-review" method="post">
      <input id="source-reviewer" name="reviewer" type="text" value="Migration Desk"/>
      <button id="source-review-submit" type="submit" formaction="../meta/review-submit.xhtml#draft">Save review draft</button>
    </form>
    <p><a id="source-ping-link" href="#source" ping="https://analytics.example.test/epub/source ../meta/missing-ping.xhtml">Track source review</a></p>
    <p><span id="source-play" role="button" tabindex="0">Play source audio</span></p>
    <audio id="source-audio" src="../audio/chapter.mp3"/>
    <epub:trigger id="source-audio-trigger" ev:observer="source-play" ev:event="click" action="play" ref="source-audio"/>
    <math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi><mo>=</mo><mn>1</mn></math>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><title>Source mark</title><circle cx="5" cy="5" r="4"/></svg>
    <epub:switch id="source-format-choice">
      <epub:case required-namespace="http://www.w3.org/2000/svg"><p>Reading-system SVG path.</p></epub:case>
      <epub:default><p>Fallback text preserved for WordPress review.</p></epub:default>
    </epub:switch>
  </body>
</html>
XML;

$slideshowFallbackXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body onload="prepareSlideshowFallback()">
    <h1>Source slideshow fallback</h1>
    <p>Scripted EPUB slideshow fallback is preserved for WordPress review.</p>
    <script id="slideshow-review-script" type="text/javascript">window.slideshowReview = true;</script>
  </body>
</html>
XML;

$boundTourFallbackXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1>Bound tour fallback</h1>
    <p>OPF media-type binding handler content is preserved for WordPress review.</p>
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
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1" xml:lang="en">
  <head>
    <meta name="dtb:uid" content="urn:uuid:wordpress-epub-source"/>
    <meta name="dtb:depth" content="2"/>
    <meta name="dtb:totalPageCount" content="1"/>
    <meta name="dtb:maxPageNumber" content="1"/>
    <meta name="review:source" content="wordpress-import"/>
  </head>
  <docTitle id="source-ncx-title">
    <text>WordPress EPUB source packet</text>
  </docTitle>
  <docAuthor id="source-ncx-author">
    <text>Migration Desk</text>
  </docAuthor>
  <navMap>
    <navPoint id="source" class="source-toc-point" playOrder="1" xml:lang="en" dir="ltr">
      <navLabel id="source-label" class="source-label"><text>Source chapter</text><audio id="source-label-audio" src="audio/chapter.mp3" clipBegin="0:00:01.000" clipEnd="0:00:03.000"/></navLabel>
      <content id="source-content" src="text/chapter.xhtml#source" data-review="chapter"/>
    </navPoint>
    <navPoint id="remote-note" playOrder="2">
      <navLabel><text>Remote source note</text></navLabel>
      <content src="https://cdn.example.test/epub/source-note.html"/>
    </navPoint>
  </navMap>
  <navList id="review-references" class="loi list-of-illustrations review-links">
    <navLabel id="review-references-label" class="review-list-label"><text id="review-references-title" class="review-list-title">Reviewer reference list</text></navLabel>
    <navTarget id="review-glossary" class="glossary" playOrder="10">
      <navLabel id="review-glossary-label" class="glossary-label"><text id="review-glossary-title" class="glossary-title">Source glossary entry</text></navLabel>
      <content src="text/chapter.xhtml#source"/>
    </navTarget>
    <navTarget id="remote-review-record" playOrder="11">
      <navLabel><text>Remote review record</text></navLabel>
      <content src="https://cdn.example.test/epub/review-record.xhtml"/>
    </navTarget>
  </navList>
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

$metadataXml = <<<'XML'
<metadata xmlns="http://www.idpf.org/2013/metadata" xmlns:review="https://example.invalid/epub-review" xml:lang="en">
  <review:source id="container-source" href="META-INF/review/container-source.json" media-type="application/ld+json">Container source record</review:source>
  <review:policy id="remote-policy" href="https://metadata.example.test/container-policy.json">Remote container policy</review:policy>
</metadata>
XML;

$ocfManifestXml = sprintf(<<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/epub+zip"/>
  <manifest:file-entry manifest:full-path="EPUB/package.opf" manifest:media-type="application/oebps-package+xml"/>
  <manifest:file-entry manifest:full-path="EPUB/text/chapter.xhtml" manifest:media-type="application/xhtml+xml" manifest:size="%d"/>
  <manifest:file-entry manifest:full-path="EPUB/images/unmanifested-review.png" manifest:media-type="image/png" manifest:size="16"/>
</manifest:manifest>
XML, strlen($chapterXhtml));

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

$reviewCss = <<<'CSS'
@media screen and (min-width: 700px), print {
  body { line-height: 1.5; }
}
@supports (display: grid) {
  .review-grid { display: grid; }
}
@page source:left {
  size: 6in 9in;
  margin-left: 1in;
  @bottom-center { content: counter(page); }
}
@font-face { font-family: "Source Review"; src: url("../fonts/source.otf") format("opentype"); }
body { color: #222; background-image: url("../images/cover.png"); }
CSS;

$packageParts = [
    ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/container.xml', 'data' => $containerXml],
    ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
    ['name' => 'META-INF/manifest.xml', 'data' => $ocfManifestXml],
    ['name' => 'META-INF/metadata.xml', 'data' => $metadataXml],
    ['name' => 'META-INF/rights.xml', 'data' => $rightsXml],
    ['name' => 'META-INF/signatures.xml', 'data' => $signaturesXml],
    ['name' => 'META-INF/licenses/source-license.xml', 'data' => '<license source="wordpress-import">review required</license>'],
    ['name' => 'META-INF/review/container-source.json', 'data' => '{"source":"wordpress-import","containerMetadata":true}'],
    ['name' => 'EPUB/package.opf', 'data' => $opfXml],
    ['name' => 'EPUB/fixed/package.opf', 'data' => $alternateOpfXml],
    ['name' => 'EPUB/meta/review-record.json', 'data' => '{"@context":"https://schema.org","name":"WordPress EPUB review record"}'],
    ['name' => 'EPUB/meta/review-submit.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p id="draft">Review draft target</p></body></html>'],
    ['name' => 'EPUB/meta/accessibility.json', 'data' => '{"@context":"https://schema.org","accessibilitySummary":"Reviewer accessibility record"}'],
    ['name' => 'EPUB/nav.xhtml', 'data' => $navXhtml],
    ['name' => 'EPUB/text/chapter.xhtml', 'data' => $chapterXhtml],
    ['name' => 'EPUB/text/slideshow-fallback.xhtml', 'data' => $slideshowFallbackXhtml],
    ['name' => 'EPUB/text/bound-tour-fallback.xhtml', 'data' => $boundTourFallbackXhtml],
    ['name' => 'EPUB/slides/source-slideshow.xml', 'data' => '<slides><slide src="../images/cover.png"/></slides>'],
    ['name' => 'EPUB/interactive/tour.bin', 'data' => 'BOUND-TOUR'],
    ['name' => 'EPUB/interactive/missing-fallback.bin', 'data' => 'MISSING-FALLBACK-WIDGET'],
    ['name' => 'EPUB/overlays/chapter.smil', 'data' => $smilXml],
    ['name' => 'EPUB/audio/chapter.mp3', 'data' => 'MP3-DATA'],
    ['name' => 'EPUB/styles/review.css', 'data' => $reviewCss],
    ['name' => 'EPUB/fonts/source.otf', 'data' => 'OBFUSCATED-FONT'],
    ['name' => 'EPUB/images/cover.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'EPUB/images/legacy-cover.jpg', 'data' => 'LEGACY-JPEG', 'compressionMethod' => 0],
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
    if (($result['metadata']['mainTitle']['titleType'] ?? null) !== 'main' || ($result['metadata']['mainTitle']['fileAs'] ?? null) !== 'WordPress EPUB source packet') {
        throw new RuntimeException('Expected EPUB OPF main title refinements to be summarized');
    }
    if (($result['metadata']['mainTitle']['language'] ?? null) !== 'ar' || ($result['metadata']['mainTitle']['direction'] ?? null) !== 'ltr') {
        throw new RuntimeException('Expected EPUB OPF main title to inherit metadata language while preserving title direction');
    }
    if (($result['metadata']['subtitle']['text'] ?? null) !== 'Murajaat al-hijra' || ($result['metadata']['subtitle']['direction'] ?? null) !== 'rtl') {
        throw new RuntimeException('Expected EPUB OPF subtitle direction metadata to be summarized');
    }
    if (($result['metadata']['subtitle']['alternateScripts'][0]['text'] ?? null) !== 'Migration review subtitle' || ($result['metadata']['subtitle']['alternateScripts'][0]['direction'] ?? null) !== 'ltr') {
        throw new RuntimeException('Expected EPUB OPF subtitle alternate-script metadata to be summarized');
    }
    if (($result['metadata']['shortTitle']['text'] ?? null) !== 'WP EPUB packet' || !isset($result['metadata']['titlesByType']['short'])) {
        throw new RuntimeException('Expected EPUB OPF short title metadata to be grouped by title-type');
    }
    if (($result['document']->attr('metadata')['titlesByType']['subtitle'][0]['text'] ?? null) !== 'Murajaat al-hijra') {
        throw new RuntimeException('Expected WordPress EPUB handoff to expose title-type metadata');
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
    if (($result['container']['linkCount'] ?? null) !== 3 || ($result['container']['links'][0]['target'] ?? null) !== '/EPUB/meta/review-record.json') {
        throw new RuntimeException('Expected EPUB OCF container links to expose package metadata records');
    }
    if (($result['container']['links'][0]['byteSha256'] ?? null) !== hash('sha256', '{"@context":"https://schema.org","name":"WordPress EPUB review record"}')) {
        throw new RuntimeException('Expected EPUB OCF container link to hash local metadata record bytes');
    }
    if (($result['container']['linksByRel']['record'][0]['target'] ?? null) !== '/EPUB/meta/review-record.json') {
        throw new RuntimeException('Expected EPUB OCF container links to be indexed by rel');
    }
    if (($result['container']['links'][1]['diagnostics'][0]['type'] ?? null) !== 'external-container-link-reference') {
        throw new RuntimeException('Expected remote EPUB OCF container link to stay unfetched for review');
    }
    if (($result['container']['links'][2]['fragmentKind'] ?? null) !== 'epub-cfi' || ($result['container']['links'][2]['epubCfi']['path'] ?? null) !== '/6/2[source]!/4/2/1:12') {
        throw new RuntimeException('Expected EPUB OCF container CFI preview link to preserve CFI metadata');
    }
    if (($result['importReport']['container']['linkDiagnostics'][0]['type'] ?? null) !== 'external-container-link-reference') {
        throw new RuntimeException('Expected EPUB import report to expose OCF container link diagnostics');
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
    if (($result['metadata']['metaProperties']['schema:accessibilitySummary'][0]['propertyVocabulary']['iri'] ?? null) !== 'https://schema.org/accessibilitySummary') {
        throw new RuntimeException('Expected EPUB metadata properties to resolve package prefix vocabulary IRIs');
    }
    if (($result['metadata']['vendorMetadata']['ibooks']['specified-fonts'][0]['value'] ?? null) !== 'true') {
        throw new RuntimeException('Expected EPUB ibooks vendor metadata to be summarized');
    }
    if (($result['metadata']['vendorMetadata']['calibre']['series'][0]['content'] ?? null) !== 'WordPress migration packets') {
        throw new RuntimeException('Expected EPUB calibre vendor metadata content to be preserved');
    }
    if (($result['document']->attr('metadata')['vendorMetadata']['calibre']['series_index'][0]['value'] ?? null) !== '7') {
        throw new RuntimeException('Expected WordPress EPUB handoff to expose calibre series index metadata');
    }
    if (($result['metadata']['refinementSubjectSummary']['unknownSubjects'][0] ?? null) !== 'missing-review-subject') {
        throw new RuntimeException('Expected dangling EPUB OPF metadata refinement subjects to remain visible for review');
    }
    if (($result['metadata']['refinementSubjectSummary']['diagnostics'][0]['type'] ?? null) !== 'unknown-metadata-refinement-subject') {
        throw new RuntimeException('Expected dangling EPUB OPF metadata refinement subjects to produce review diagnostics');
    }
    if (($result['importReport']['metadata']['refinementSubjectSummary']['diagnostics'][0]['subjectId'] ?? null) !== 'missing-review-subject') {
        throw new RuntimeException('Expected EPUB import report to expose dangling metadata refinement diagnostics');
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
    if (($result['spine'][0]['flow'] ?? null) !== 'paginated' || ($result['spine'][0]['alignX'] ?? null) !== 'center') {
        throw new RuntimeException('Expected EPUB spine itemrefs to preserve package-declared rendition flow and align-x metadata');
    }
    if (($result['spine'][0]['spineItemProperties']['flow']['value'] ?? null) !== 'paginated') {
        throw new RuntimeException('Expected EPUB spine item property report to expose rendition flow metadata');
    }
    if (($result['spine'][0]['layout'] ?? null) !== 'pre-paginated' || ($result['spine'][0]['orientation'] ?? null) !== 'landscape' || ($result['spine'][0]['spread'] ?? null) !== 'none') {
        throw new RuntimeException('Expected EPUB spine itemrefs to preserve local fixed-layout rendition overrides');
    }
    if (($result['spine'][0]['spineItemProperties']['layout']['fixedLayout'] ?? null) !== true || ($result['spine'][0]['spineItemProperties']['orientation']['value'] ?? null) !== 'landscape') {
        throw new RuntimeException('Expected EPUB spine item property report to expose fixed-layout override metadata');
    }
    if (($result['spine'][0]['effectiveRendition']['layout'] ?? null) !== 'pre-paginated' || ($result['spine'][0]['effectiveRendition']['layoutSource'] ?? null) !== 'itemref') {
        throw new RuntimeException('Expected EPUB effective rendition to prefer itemref layout overrides over package defaults');
    }
    if (($result['spine'][0]['effectiveRendition']['viewportWidth'] ?? null) !== 1024 || ($result['spine'][0]['effectiveRendition']['viewportSource'] ?? null) !== 'itemref-refinement') {
        throw new RuntimeException('Expected EPUB effective rendition to use itemref viewport refinement before package viewport metadata');
    }
    if (($result['spine'][2]['effectiveRendition']['layout'] ?? null) !== 'reflowable' || ($result['spine'][2]['effectiveRendition']['layoutSource'] ?? null) !== 'package') {
        throw new RuntimeException('Expected EPUB effective rendition to fall back to package-level layout metadata for unstyled spine items');
    }
    if (($result['document']->children[0]->attr('pageProgressionDirection') ?? null) !== 'rtl' || ($result['document']->children[0]->attr('pageSpread') ?? null) !== 'right') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB reading-order metadata');
    }
    if (($result['document']->children[0]->attr('flow') ?? null) !== 'paginated' || ($result['document']->children[0]->attr('alignX') ?? null) !== 'center') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB rendition flow and align-x metadata');
    }
    if (($result['document']->children[0]->attr('layout') ?? null) !== 'pre-paginated' || ($result['document']->children[0]->attr('orientation') ?? null) !== 'landscape' || ($result['document']->children[0]->attr('spread') ?? null) !== 'none') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB fixed-layout rendition overrides');
    }
    if (($result['document']->children[0]->attr('effectiveRendition')['viewportSource'] ?? null) !== 'itemref-refinement') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose effective EPUB rendition metadata');
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
    $assetsById = [];
    foreach ($result['assets'] as $asset) {
        $assetsById[$asset['id']] = $asset;
    }
    if (($assetsById['slideshow']['fallbackId'] ?? null) !== 'slideshow-handler') {
        throw new RuntimeException('Expected EPUB non-spine asset report to expose OPF fallback id');
    }
    if (($assetsById['slideshow']['fallbackContentPart'] ?? null) !== '/EPUB/text/slideshow-fallback.xhtml') {
        throw new RuntimeException('Expected EPUB non-spine asset report to resolve fallback content part');
    }
    if (($assetsById['slideshow']['fallbackContentMediaType'] ?? null) !== 'application/xhtml+xml') {
        throw new RuntimeException('Expected EPUB non-spine asset fallback media type to remain visible');
    }
    if (($result['importReport']['assets']['fallbackItems'][0]['id'] ?? null) !== 'slideshow') {
        throw new RuntimeException('Expected EPUB import report to summarize asset fallback chains');
    }
    if (($assetsById['slideshow']['fallbackStyleId'] ?? null) !== 'style') {
        throw new RuntimeException('Expected EPUB non-spine asset report to expose OPF fallback-style id');
    }
    if (($assetsById['slideshow']['fallbackStyleContentPart'] ?? null) !== '/EPUB/styles/review.css') {
        throw new RuntimeException('Expected EPUB non-spine asset report to resolve fallback-style CSS part');
    }
    if (($assetsById['slideshow']['fallbackStyleByteSha256'] ?? null) !== hash('sha256', $reviewCss)) {
        throw new RuntimeException('Expected EPUB non-spine asset fallback-style to hash CSS bytes');
    }
    if (($result['importReport']['assets']['fallbackStyleItems'][0]['id'] ?? null) !== 'slideshow') {
        throw new RuntimeException('Expected EPUB import report to summarize asset fallback-style chains');
    }
    if (($result['document']->attr('assets')['fallbackStyleCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose fallback-style asset metadata');
    }
    if (($result['nav']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB nav href to resolve to the chapter fragment');
    }
    if (($result['nav']['sections'][0]['id'] ?? null) !== 'source-toc' || ($result['nav']['sections'][0]['class'] ?? null) !== 'source-navigation') {
        throw new RuntimeException('Expected EPUB nav section source attributes to remain visible for review');
    }
    if (($result['nav']['sections'][0]['language'] ?? null) !== 'en' || ($result['nav']['sections'][0]['direction'] ?? null) !== 'ltr') {
        throw new RuntimeException('Expected EPUB nav section language and direction metadata');
    }
    if (($result['nav']['hiddenSectionCount'] ?? null) !== 0 || ($result['nav']['hiddenItemCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected visible EPUB nav entries not to be marked hidden');
    }
    if (($result['nav']['items'][0]['id'] ?? null) !== 'source-toc-link' || ($result['nav']['items'][0]['class'] ?? null) !== 'source-link') {
        throw new RuntimeException('Expected EPUB nav item source id and class to remain visible for review');
    }
    if (($result['nav']['items'][0]['type'] ?? null) !== 'bodymatter' || ($result['nav']['items'][0]['itemTypes'] ?? []) !== ['bodymatter', 'chapter']) {
        throw new RuntimeException('Expected EPUB nav item semantic type to fall back to the list item');
    }
    if (($result['nav']['items'][0]['typeSource'] ?? null) !== 'item' || ($result['nav']['items'][0]['typeSources'][0]['element'] ?? null) !== 'li') {
        throw new RuntimeException('Expected EPUB nav item type source provenance to identify the list item');
    }
    if (($result['navigation']['items'][0]['id'] ?? null) !== 'source-toc-link' || ($result['navigation']['items'][0]['classes'] ?? []) !== ['source-link']) {
        throw new RuntimeException('Expected EPUB navigation report to preserve nav item provenance');
    }
    if (($result['navigation']['items'][0]['itemTypes'] ?? []) !== ['bodymatter', 'chapter'] || ($result['navigation']['items'][0]['typeSource'] ?? null) !== 'item') {
        throw new RuntimeException('Expected EPUB navigation report to preserve nav item semantic type source');
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
    if (($result['ncx']['items'][0]['class'] ?? null) !== 'source-toc-point' || ($result['ncx']['items'][0]['classes'] ?? []) !== ['source-toc-point']) {
        throw new RuntimeException('Expected NCX navPoint class provenance to remain visible for review');
    }
    if (($result['ncx']['items'][0]['language'] ?? null) !== 'en' || ($result['ncx']['items'][0]['direction'] ?? null) !== 'ltr') {
        throw new RuntimeException('Expected NCX navPoint language and direction provenance');
    }
    if (($result['ncx']['items'][0]['labelAttributes']['id'] ?? null) !== 'source-label' || ($result['ncx']['items'][0]['contentAttributes']['data-review'] ?? null) !== 'chapter') {
        throw new RuntimeException('Expected NCX navLabel/content attributes to remain visible for review');
    }
    if (($result['ncx']['audioLabelCount'] ?? null) !== 1 || ($result['ncx']['audioLabelReport']['localCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected NCX label audio metadata to be summarized for review');
    }
    if (($result['ncx']['items'][0]['labelAudio'][0]['target'] ?? null) !== '/EPUB/audio/chapter.mp3') {
        throw new RuntimeException('Expected NCX navLabel audio to resolve relative to the NCX part');
    }
    if (($result['ncx']['items'][0]['labelAudio'][0]['manifestId'] ?? null) !== 'audio-chapter' || ($result['ncx']['items'][0]['labelAudio'][0]['mediaType'] ?? null) !== 'audio/mpeg') {
        throw new RuntimeException('Expected NCX navLabel audio to preserve OPF manifest provenance');
    }
    if (($result['ncx']['items'][0]['labelAudio'][0]['byteSha256'] ?? null) !== hash('sha256', 'MP3-DATA')) {
        throw new RuntimeException('Expected NCX navLabel audio to expose package byte provenance');
    }
    if (($result['importReport']['ncx']['audioLabelDiagnostics'] ?? null) !== []) {
        throw new RuntimeException('Expected local NCX navLabel audio to avoid review diagnostics');
    }
    $firstNcxNavigationItem = null;
    foreach ($result['navigation']['items'] as $navigationItem) {
        if (($navigationItem['source'] ?? null) === 'ncx') {
            $firstNcxNavigationItem = $navigationItem;
            break;
        }
    }
    if (($firstNcxNavigationItem['source'] ?? null) !== 'ncx' || ($firstNcxNavigationItem['class'] ?? null) !== 'source-toc-point') {
        throw new RuntimeException('Expected EPUB navigation report to preserve NCX navPoint provenance');
    }
    if (($firstNcxNavigationItem['labelAudio'][0]['manifestId'] ?? null) !== 'audio-chapter') {
        throw new RuntimeException('Expected EPUB navigation report to preserve NCX label audio provenance');
    }
    if (($result['ncx']['docTitle'] ?? null) !== 'WordPress EPUB source packet') {
        throw new RuntimeException('Expected NCX docTitle metadata to remain visible for review');
    }
    if (($result['ncx']['docAuthors'] ?? []) !== ['Migration Desk']) {
        throw new RuntimeException('Expected NCX docAuthor metadata to remain visible for review');
    }
    if (($result['ncx']['head']['uid'] ?? null) !== 'urn:uuid:wordpress-epub-source' || ($result['ncx']['head']['depth'] ?? null) !== '2') {
        throw new RuntimeException('Expected NCX head metadata to expose dtb uid/depth values');
    }
    if (($result['importReport']['ncx']['head']['byName']['review:source'][0]['content'] ?? null) !== 'wordpress-import') {
        throw new RuntimeException('Expected EPUB import report to expose NCX head meta records');
    }
    if (($result['ncx']['items'][1]['external'] ?? null) !== true || ($result['ncx']['items'][1]['diagnostics'][0]['type'] ?? null) !== 'external-ncx-reference') {
        throw new RuntimeException('Expected remote NCX reference to stay unfetched for review');
    }
    if (($result['ncx']['navListCount'] ?? null) !== 1 || ($result['ncx']['navLists'][0]['title'] ?? null) !== 'Reviewer reference list') {
        throw new RuntimeException('Expected NCX navList reviewer references to stay visible in the import report');
    }
    if (($result['ncx']['navLists'][0]['labelAttributes']['id'] ?? null) !== 'review-references-label' || ($result['ncx']['navLists'][0]['labelTextAttributes']['id'] ?? null) !== 'review-references-title') {
        throw new RuntimeException('Expected NCX navList label source attributes to stay visible for review');
    }
    if (($result['ncx']['navLists'][0]['type'] ?? null) !== 'list-of-illustrations' || ($result['ncx']['navLists'][0]['roleAliases'] ?? []) !== ['loi', 'list-of-illustrations']) {
        throw new RuntimeException('Expected NCX navList role metadata to identify list-of-illustrations reviewer references');
    }
    if (($result['ncx']['navListRoleReport']['roles'] ?? []) !== ['list-of-illustrations'] || ($result['ncx']['navListRoleReport']['diagnosticCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected NCX navList role report to summarize typed supplemental lists without diagnostics');
    }
    if (($result['ncx']['navLists'][0]['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected local NCX navTarget to resolve to the source chapter fragment');
    }
    if (($result['ncx']['navLists'][0]['items'][0]['manifestId'] ?? null) !== 'chapter' || ($result['ncx']['navLists'][0]['items'][0]['mediaType'] ?? null) !== 'application/xhtml+xml') {
        throw new RuntimeException('Expected NCX navTarget review metadata to preserve manifest id and media type');
    }
    if (($result['ncx']['navLists'][0]['items'][0]['labelAttributes']['id'] ?? null) !== 'review-glossary-label' || ($result['ncx']['navLists'][0]['items'][0]['labelTextAttributes']['id'] ?? null) !== 'review-glossary-title') {
        throw new RuntimeException('Expected NCX navTarget label source attributes to stay visible for review');
    }
    if (($result['importReport']['ncx']['navListDiagnostics'][0]['type'] ?? null) !== 'external-ncx-nav-list-reference') {
        throw new RuntimeException('Expected remote NCX navTarget to stay unfetched with a review diagnostic');
    }
    if (($result['navigation']['targetCount'] ?? null) !== 5 || ($result['navigation']['mappedSpineTargetCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected EPUB nav/NCX targets to reconcile with resolved spine coverage');
    }
    if (($result['navigation']['ncxNavListCount'] ?? null) !== 1 || ($result['navigation']['ncxNavListTargetCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB navigation report to summarize supplemental NCX navList targets');
    }
    if (($result['navigation']['supplementalMappedSpineTargetCount'] ?? null) !== 1 || ($result['navigation']['supplementalExternalTargetCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected supplemental NCX navList targets to keep local and remote review links separate');
    }
    if (($result['document']->attr('navigation')['supplementalItems'][0]['listId'] ?? null) !== 'review-references') {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose supplemental NCX navList provenance');
    }
    if (($result['document']->attr('navigation')['supplementalItems'][0]['listType'] ?? null) !== 'list-of-illustrations' || ($result['document']->attr('navigation')['supplementalItems'][0]['manifestId'] ?? null) !== 'chapter') {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose supplemental NCX navList role and manifest provenance');
    }
    if (($result['document']->attr('navigation')['supplementalItems'][0]['labelTextAttributes']['id'] ?? null) !== 'review-glossary-title') {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose supplemental NCX label text provenance');
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
    if (($result['navigationOutline']['source'] ?? null) !== 'nav' || ($result['navigationOutline']['itemCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected EPUB navigation outline to prefer the primary nav TOC for review handoff');
    }
    if (($result['navigationOutline']['mappedSpineTargetCount'] ?? null) !== 2 || ($result['navigationOutline']['externalTargetCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB navigation outline to summarize mapped and remote targets');
    }
    if (($result['navigationOutline']['items'][0]['children'][0]['fragmentKind'] ?? null) !== 'epub-cfi') {
        throw new RuntimeException('Expected EPUB navigation outline to preserve nested CFI targets');
    }
    if (!str_contains((string) ($result['navigationOutline']['html'] ?? ''), 'data-epub-source="nav"')) {
        throw new RuntimeException('Expected EPUB navigation outline review HTML to expose source metadata');
    }
    if (($result['document']->attr('navigationOutline')['htmlSha256'] ?? null) !== ($result['navigationOutline']['htmlSha256'] ?? null)) {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose navigation outline review HTML');
    }
    if (($result['nav']['landmarks'][0]['type'] ?? null) !== 'bodymatter') {
        throw new RuntimeException('Expected EPUB nav landmarks to preserve item type');
    }
    if (($result['nav']['pageList'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#page-1') {
        throw new RuntimeException('Expected EPUB page-list target to resolve to the source page marker');
    }
    if (($result['nav']['auxiliaryNavigation']['sectionCount'] ?? null) !== 1 || ($result['nav']['auxiliaryNavigation']['types'] ?? []) !== ['loi', 'list-of-illustrations']) {
        throw new RuntimeException('Expected EPUB auxiliary nav sections to be summarized for review handoff');
    }
    if (($result['nav']['auxiliaryNavigation']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#cover-figure') {
        throw new RuntimeException('Expected EPUB auxiliary nav local target to resolve relative to the package');
    }
    if (($result['nav']['auxiliaryNavigation']['items'][1]['diagnostics'][0]['type'] ?? null) !== 'external-nav-reference') {
        throw new RuntimeException('Expected EPUB auxiliary nav remote targets to stay unfetched for review');
    }
    if (($result['importReport']['nav']['auxiliaryNavigation']['itemCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB import report to expose auxiliary nav item count');
    }
    if (($result['nav']['primaryNavigationTargetPolicy']['itemCount'] ?? null) !== 5 || ($result['nav']['primaryNavigationTargetPolicy']['externalTargetCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB primary nav target policy to report remote TOC targets separately from auxiliary nav');
    }
    if (($result['nav']['primaryNavigationTargetPolicy']['itemsBySectionType']['toc'][2]['diagnostics'][0]['type'] ?? null) !== 'external-primary-nav-target') {
        throw new RuntimeException('Expected EPUB primary nav policy to classify remote TOC targets for review');
    }
    if (($result['importReport']['nav']['primaryNavigationTargetPolicy']['validTargetCount'] ?? null) !== 4) {
        throw new RuntimeException('Expected EPUB import report to expose primary nav target policy coverage');
    }
    if (($result['pageBreaks']['count'] ?? null) !== 1 || ($result['pageBreaks']['items'][0]['fragment'] ?? null) !== 'page-1') {
        throw new RuntimeException('Expected EPUB page-list entries to be summarized as page-break metadata');
    }
    if (($result['pageBreaks']['items'][0]['spineIdref'] ?? null) !== 'chapter' || ($result['document']->children[0]->attr('pageBreakCount') ?? null) !== 1) {
        throw new RuntimeException('Expected WordPress spine block to expose EPUB page-break metadata');
    }
    $mediaFragmentNav = str_replace(
        '        <li><a href="https://cdn.example.test/epub/source-note.html">Remote source note</a></li>',
        "        <li><a href=\"https://cdn.example.test/epub/source-note.html\">Remote source note</a></li>\n        <li><a href=\"audio/chapter.mp3#t=1,4.25\">Audio excerpt</a></li>",
        $navXhtml
    );
    $mediaFragmentResult = $reader->readPackage(ZipPackage::fromParts($withPackagePartData(
        $packageParts,
        'EPUB/nav.xhtml',
        $mediaFragmentNav
    )));
    if (($mediaFragmentResult['navigation']['mediaFragmentTargetCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB navigation report to count timed media-fragment targets for WordPress handoff');
    }
    if (($mediaFragmentResult['navigation']['mediaFragmentTargets'][0]['fragmentKind'] ?? null) !== 'media-fragment') {
        throw new RuntimeException('Expected EPUB navigation media fragment to be classified separately from element ID fragments');
    }
    if (($mediaFragmentResult['navigation']['mediaFragmentTargets'][0]['mediaFragment']['time']['durationSeconds'] ?? null) !== 3.25) {
        throw new RuntimeException('Expected EPUB navigation timed media fragment duration to be summarized');
    }
    if (($mediaFragmentResult['nav']['primaryNavigationTargetPolicy']['mediaFragmentTargetCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB primary nav policy to expose media-fragment target count');
    }
    $chapterSemantics = $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml'] ?? [];
    if (!in_array('bodymatter', $chapterSemantics['semanticTypes'] ?? [], true) || !in_array('pagebreak', $chapterSemantics['semanticTypes'] ?? [], true)) {
        throw new RuntimeException('Expected EPUB XHTML semantic type annotations to be summarized for package review');
    }
    if (($chapterSemantics['embeddedResourcesByKind']['audio'][0]['manifestId'] ?? null) !== 'audio-chapter') {
        throw new RuntimeException('Expected EPUB XHTML embedded audio resources to resolve to OPF manifest assets');
    }
    if (($result['document']->children[0]->attr('contentEmbeddedResources')[0]['kind'] ?? null) !== 'audio') {
        throw new RuntimeException('Expected WordPress spine block to expose EPUB XHTML embedded resource metadata');
    }
    if (($chapterSemantics['semanticItemsByType']['pagebreak'][0]['id'] ?? null) !== 'page-1' || ($chapterSemantics['semanticItemsByType']['pagebreak'][0]['attributes']['title'] ?? null) !== '1') {
        throw new RuntimeException('Expected EPUB XHTML pagebreak semantic metadata to preserve source attributes');
    }
    if (!in_array('title', $result['document']->children[0]->attr('contentSemanticTypes') ?? [], true)) {
        throw new RuntimeException('Expected WordPress spine block to expose EPUB XHTML semantic metadata');
    }
    if (($result['xhtmlResourceReport']['switchCount'] ?? null) !== 1 || ($chapterSemantics['switches'][0]['cases'][0]['requiredNamespace'] ?? null) !== 'http://www.w3.org/2000/svg') {
        throw new RuntimeException('Expected EPUB switch branches to preserve required namespace metadata for review');
    }
    if (($result['document']->children[0]->attr('contentSwitches')[0]['defaults'][0]['text'] ?? null) !== 'Fallback text preserved for WordPress review.') {
        throw new RuntimeException('Expected WordPress spine block to expose EPUB switch fallback text metadata');
    }
    $navWithoutPageList = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter.xhtml#source">Source chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;
    $ncxWithPageList = str_replace(
        '</ncx>',
        '  <pageList id="print-pages" class="legacy-pages print-pages" xml:lang="en" dir="ltr">' . "\n"
        . '    <navLabel id="print-pages-label"><text id="print-pages-title">Print pages</text></navLabel>' . "\n"
        . '    <pageTarget id="print-page-1" type="normal" value="1" playOrder="20" class="legacy-page" xml:lang="en">' . "\n"
        . '      <navLabel id="print-page-label"><text id="print-page-text">1</text></navLabel>' . "\n"
        . '      <content id="print-page-content" src="text/chapter.xhtml#page-1" data-review="legacy-page-list"/>' . "\n"
        . '    </pageTarget>' . "\n"
        . '  </pageList>' . "\n"
        . '</ncx>',
        $ncxXml
    );
    $ncxPageListParts = $withPackagePartData(
        $withPackagePartData($packageParts, 'EPUB/nav.xhtml', $navWithoutPageList),
        'EPUB/toc.ncx',
        $ncxWithPageList
    );
    $ncxPageListResult = $reader->readPackage(ZipPackage::fromParts($ncxPageListParts));
    if (($ncxPageListResult['pageBreaks']['source'] ?? null) !== 'ncx-page-list') {
        throw new RuntimeException('Expected legacy NCX pageList to supply page-break metadata when nav page-list is absent');
    }
    if (($ncxPageListResult['ncx']['pageListReport']['itemCount'] ?? null) !== 1 || ($ncxPageListResult['ncx']['pageListReport']['class'] ?? null) !== 'legacy-pages print-pages') {
        throw new RuntimeException('Expected NCX pageList review report to preserve source list attributes');
    }
    if (($ncxPageListResult['ncx']['pageList'][0]['value'] ?? null) !== '1' || ($ncxPageListResult['pageBreaks']['items'][0]['source'] ?? null) !== 'ncx') {
        throw new RuntimeException('Expected NCX pageTarget metadata to remain visible in the WordPress page-break handoff');
    }
    if (($ncxPageListResult['ncx']['pageList'][0]['classes'] ?? []) !== ['legacy-page'] || ($ncxPageListResult['ncx']['pageList'][0]['byteLength'] ?? null) !== strlen($chapterXhtml)) {
        throw new RuntimeException('Expected NCX pageTarget provenance to include classes and resolved package byte length');
    }
    if (($ncxPageListResult['ncx']['pageList'][0]['crc32'] ?? null) !== hash('crc32b', $chapterXhtml) || ($ncxPageListResult['ncx']['pageList'][0]['contentAttributes']['data-review'] ?? null) !== 'legacy-page-list') {
        throw new RuntimeException('Expected NCX pageTarget content attributes and CRC to remain reviewable');
    }
    if (($ncxPageListResult['document']->children[0]->attr('pageBreaks')[0]['playOrder'] ?? null) !== '20') {
        throw new RuntimeException('Expected WordPress spine block to expose NCX pageTarget playOrder metadata');
    }
    if (($ncxPageListResult['document']->children[0]->attr('pageBreaks')[0]['contentAttributes']['data-review'] ?? null) !== 'legacy-page-list' || ($ncxPageListResult['document']->children[0]->attr('pageBreaks')[0]['byteLength'] ?? null) !== strlen($chapterXhtml)) {
        throw new RuntimeException('Expected WordPress spine block to expose NCX pageTarget resolved-byte provenance');
    }
    $xhtmlPageBreakResult = $reader->readPackage(ZipPackage::fromParts($withPackagePartData(
        $packageParts,
        'EPUB/nav.xhtml',
        $navWithoutPageList
    )));
    if (($xhtmlPageBreakResult['pageBreaks']['source'] ?? null) !== 'xhtml-semantic-pagebreak' || ($xhtmlPageBreakResult['pageBreaks']['count'] ?? null) !== 1) {
        throw new RuntimeException('Expected XHTML epub:type pagebreaks to supply page-break metadata when nav and NCX page lists are absent');
    }
    if (($xhtmlPageBreakResult['pageBreaks']['items'][0]['label'] ?? null) !== '1' || ($xhtmlPageBreakResult['pageBreaks']['items'][0]['source'] ?? null) !== 'xhtml-semantic') {
        throw new RuntimeException('Expected XHTML semantic pagebreak label and source to remain visible in the WordPress handoff');
    }
    if (($xhtmlPageBreakResult['document']->children[0]->attr('pageBreaks')[0]['fragment'] ?? null) !== 'page-1') {
        throw new RuntimeException('Expected WordPress spine block to expose XHTML semantic pagebreak fragments');
    }
    if (($result['guide']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB OPF guide text target to resolve to the source chapter');
    }
    if (($result['guide']['items'][1]['manifestId'] ?? null) !== 'cover-image') {
        throw new RuntimeException('Expected EPUB OPF guide cover reference to match the cover manifest item');
    }
    if (($result['guide']['types'] ?? []) !== ['text', 'cover'] || ($result['guide']['typedItemCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB OPF guide semantic type summary for WordPress review');
    }
    if (($result['guide']['itemsByType']['text'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB OPF guide text reference to be grouped by semantic type');
    }
    if (($result['collections'][0]['role'] ?? null) !== 'set') {
        throw new RuntimeException('Expected EPUB OPF collection role to be preserved');
    }
    if (($result['collections'][0]['metadata']['title'] ?? null) !== 'WordPress source collection') {
        throw new RuntimeException('Expected EPUB OPF collection metadata title');
    }
    if (($result['collections'][0]['metadata']['titleDetails'][0]['language'] ?? null) !== 'en' || ($result['collections'][0]['metadata']['titleDetails'][0]['direction'] ?? null) !== 'rtl') {
        throw new RuntimeException('Expected EPUB OPF collection metadata to inherit collection language and direction');
    }
    if (($result['collections'][0]['links'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB OPF collection internal link to resolve to the source chapter');
    }
    if (($result['collections'][0]['links'][1]['diagnostics'][0]['type'] ?? null) !== 'external-collection-link') {
        throw new RuntimeException('Expected EPUB OPF collection external link to be reported without fetching');
    }
    if (($result['collections'][0]['linkReport']['relTokens'] ?? null) !== ['first', 'record'] || ($result['collections'][0]['linkReport']['propertyTokens'] ?? null) !== ['preview']) {
        throw new RuntimeException('Expected EPUB OPF collection link rel/property summary for WordPress review');
    }
    if (($result['collections'][0]['linkReport']['externalCount'] ?? null) !== 1 || ($result['collections'][0]['linkReport']['diagnostics'][0]['type'] ?? null) !== 'external-collection-link') {
        throw new RuntimeException('Expected EPUB OPF collection link report to retain external record diagnostics');
    }
    if (($result['metadata']['links'][0]['target'] ?? null) !== '/EPUB/meta/review-record.json') {
        throw new RuntimeException('Expected EPUB OPF metadata link to resolve to the review record package part');
    }
    if (($result['metadata']['links'][0]['byteSha256'] ?? null) !== hash('sha256', '{"@context":"https://schema.org","name":"WordPress EPUB review record"}')) {
        throw new RuntimeException('Expected EPUB OPF metadata linked record hash for review deduplication');
    }
    if (($result['metadata']['links'][0]['title'] ?? null) !== 'WordPress EPUB review record') {
        throw new RuntimeException('Expected EPUB OPF metadata linked record title provenance');
    }
    if (($result['metadata']['links'][0]['language'] ?? null) !== 'ar' || ($result['metadata']['links'][0]['direction'] ?? null) !== 'rtl') {
        throw new RuntimeException('Expected EPUB OPF metadata linked record to inherit language and direction');
    }
    if (($result['metadata']['linksByRel']['record'][0]['id'] ?? null) !== 'review-record') {
        throw new RuntimeException('Expected EPUB OPF metadata links to be indexed by rel');
    }
    if (($result['metadata']['linkVocabulary']['relTokenCount'] ?? null) !== 6 || ($result['metadata']['linkVocabulary']['propertyTokenCount'] ?? null) !== 5) {
        throw new RuntimeException('Expected EPUB OPF metadata link vocabulary tokens to be summarized');
    }
    if (($result['metadata']['linkVocabulary']['rels']['record'] ?? null) !== 3 || ($result['metadata']['linkVocabulary']['properties']['schema-org'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB OPF metadata link vocabulary rel/property counts to be preserved');
    }
    if (($result['metadata']['linkVocabulary']['diagnosticCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected EPUB OPF metadata link vocabulary summary to stay diagnostic-free for the smoke fixture');
    }
    $metadataLinkTargets = $result['metadata']['linkTargetReport'] ?? [];
    if (($metadataLinkTargets['linkCount'] ?? null) !== 4 || ($metadataLinkTargets['publicationLinkCount'] ?? null) !== 3 || ($metadataLinkTargets['refinedLinkCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB OPF metadata link target report to separate publication and refined links');
    }
    if (($metadataLinkTargets['publicationItems'][0]['title'] ?? null) !== 'WordPress EPUB review record') {
        throw new RuntimeException('Expected EPUB OPF metadata link target report to preserve title provenance');
    }
    if (($metadataLinkTargets['unmanifestedLocalLinkCount'] ?? null) !== 2 || ($metadataLinkTargets['externalLinkCount'] ?? null) !== 1 || ($metadataLinkTargets['missingLinkCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB OPF metadata link target report to flag unmanifested, external, and missing records');
    }
    if (($metadataLinkTargets['byteExposedLinkCount'] ?? null) !== 2 || ($metadataLinkTargets['diagnosticCount'] ?? null) !== 4) {
        throw new RuntimeException('Expected EPUB OPF metadata link target report to expose local record hashes and diagnostics');
    }
    if (($metadataLinkTargets['publicationItems'][0]['diagnostics'][0]['type'] ?? null) !== 'unmanifested-publication-metadata-link') {
        throw new RuntimeException('Expected EPUB OPF metadata publication record to report unmanifested package bytes');
    }
    if (($metadataLinkTargets['publicationItems'][1]['diagnostics'][0]['type'] ?? null) !== 'external-publication-metadata-link') {
        throw new RuntimeException('Expected EPUB OPF metadata remote record to report external publication metadata');
    }
    if (($metadataLinkTargets['refinedItems'][0]['diagnostics'][0]['type'] ?? null) !== 'missing-refined-metadata-link') {
        throw new RuntimeException('Expected EPUB OPF metadata refined link target to preserve missing-resource diagnostics');
    }
    if (($result['document']->attr('metadata')['linkTargetReport'] ?? null) !== $metadataLinkTargets) {
        throw new RuntimeException('Expected WordPress EPUB AST metadata to expose OPF metadata link target policy');
    }
    if (($result['metadata']['linkedResourceSummary']['subjectCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB OPF metadata link refinements to summarize linked review subjects');
    }
    if (($result['metadata']['linksByRefinedId']['creator'][0]['id'] ?? null) !== 'creator-voicing') {
        throw new RuntimeException('Expected EPUB OPF metadata links to be indexed by refined subject id');
    }
    if (($result['metadata']['linksByRefinedId']['creator'][0]['subjectId'] ?? null) !== 'creator') {
        throw new RuntimeException('Expected EPUB OPF metadata linked resource to preserve its subject id');
    }
    if (($result['metadata']['dc']['creator'][0]['linkedResources'][0]['id'] ?? null) !== 'creator-voicing') {
        throw new RuntimeException('Expected EPUB OPF creator metadata to expose linked voicing resources');
    }
    if (($result['metadata']['creatorDetails'][0]['linkedResources'][0]['rel'] ?? null) !== ['voicing']) {
        throw new RuntimeException('Expected EPUB OPF creator details to expose linked resource relations');
    }
    if (($result['metadata']['creatorDetails'][0]['language'] ?? null) !== 'ar' || ($result['metadata']['creatorDetails'][0]['direction'] ?? null) !== 'rtl') {
        throw new RuntimeException('Expected EPUB OPF creator details to inherit language and direction');
    }
    if (($result['document']->attr('metadata')['linksByRefinedId']['creator'][0]['id'] ?? null) !== 'creator-voicing') {
        throw new RuntimeException('Expected WordPress EPUB AST metadata to retain linked resource subjects');
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
    if (($result['metadata']['identifierDetails'][0]['scheme'] ?? null) !== 'UUID' || ($result['metadata']['identifierDetails'][1]['identifierType'] ?? null) !== '15') {
        throw new RuntimeException('Expected EPUB OPF identifier scheme/type summary to stay reviewable');
    }
    if (($result['metadata']['identifierSummary']['duplicateValueCount'] ?? null) !== 1 || ($result['metadata']['identifierSummary']['duplicatesByValue']['urn:uuid:wordpress-epub-source']['ids'] ?? []) !== ['source-id', 'duplicate-source-id']) {
        throw new RuntimeException('Expected EPUB OPF duplicate identifier values to be reported for review');
    }
    if (($result['document']->attr('metadata')['identifiersByScheme']['ISBN'][0]['text'] ?? null) !== '9781234567890') {
        throw new RuntimeException('Expected WordPress EPUB handoff to expose identifier schemes');
    }
    if (($result['metadata']['dateDetails'][0]['event'] ?? null) !== 'publication' || ($result['metadata']['dateDetails'][1]['eventSource'] ?? null) !== 'refinement') {
        throw new RuntimeException('Expected EPUB OPF date event metadata to be summarized');
    }
    if (($result['document']->attr('metadata')['datesByEvent']['review'][0]['text'] ?? null) !== '2026-06-05') {
        throw new RuntimeException('Expected WordPress EPUB handoff to expose date event metadata');
    }
    if (($result['metadata']['dc']['creator'][0]['refinements']['file-as'][0]['text'] ?? null) !== 'Desk, Migration') {
        throw new RuntimeException('Expected EPUB OPF creator file-as refinement to stay attached to creator metadata');
    }
    if (($result['metadata']['dc']['creator'][0]['refinements']['role'][0]['scheme'] ?? null) !== 'marc:relators') {
        throw new RuntimeException('Expected EPUB OPF creator role scheme to stay reviewable');
    }
    if (($result['metadata']['contributors'] ?? []) !== ['Review Editor', 'Translation Desk']) {
        throw new RuntimeException('Expected EPUB OPF contributor names to be summarized for review');
    }
    if (($result['metadata']['contributorDetails'][0]['fileAs'] ?? null) !== 'Editor, Review') {
        throw new RuntimeException('Expected EPUB OPF contributor file-as refinement to be normalized');
    }
    if (($result['metadata']['contributorsByRole']['edt'][0]['text'] ?? null) !== 'Review Editor') {
        throw new RuntimeException('Expected EPUB OPF contributor editor role to be indexed for review');
    }
    if (($result['document']->attr('metadata')['contributorsByRole']['trl'][0]['language'] ?? null) !== 'fr') {
        throw new RuntimeException('Expected WordPress EPUB handoff to expose contributor role metadata');
    }
    if (($result['metadata']['agentDisplayOrder']['count'] ?? null) !== 3) {
        throw new RuntimeException('Expected EPUB OPF creator/contributor display order to summarize all review agents');
    }
    if (($result['metadata']['agentDisplayOrder']['sequencedCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB OPF display-seq metadata to order sequenced review agents');
    }
    if (($result['metadata']['agentDisplayOrder']['unsequencedCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB OPF display order to retain unsequenced review agents');
    }
    if (($result['metadata']['agentDisplayOrder']['items'][0]['text'] ?? null) !== 'Migration Desk') {
        throw new RuntimeException('Expected EPUB OPF creator to lead display-ordered agent handoff');
    }
    if (($result['metadata']['agentDisplayOrder']['items'][1]['primaryRole'] ?? null) !== 'edt') {
        throw new RuntimeException('Expected EPUB OPF contributor role to remain on display-ordered agent handoff');
    }
    if (($result['metadata']['agentDisplayOrder']['items'][0]['linkedResources'][0]['id'] ?? null) !== 'creator-voicing') {
        throw new RuntimeException('Expected EPUB OPF display-ordered creator to expose linked voicing resources');
    }
    if (($result['document']->attr('metadata')['agentDisplayOrder']['items'][2]['text'] ?? null) !== 'Translation Desk') {
        throw new RuntimeException('Expected WordPress EPUB handoff to expose unsequenced agent display metadata');
    }
    if (($result['package']['id'] ?? null) !== 'source-package' || ($result['package']['refinements']['schema:name'][0]['text'] ?? null) !== 'WordPress source package record') {
        throw new RuntimeException('Expected EPUB OPF package-level refinements to remain reviewable');
    }
    if (($result['package']['refinements']['schema:name'][0]['propertyVocabulary']['iri'] ?? null) !== 'https://schema.org/name') {
        throw new RuntimeException('Expected EPUB package-level refinements to expose resolved vocabulary IRIs');
    }
    if (($result['manifest'][1]['refinements']['schema:name'][0]['text'] ?? null) !== 'Source chapter publication resource') {
        throw new RuntimeException('Expected EPUB manifest resource refinements to attach to publication resources');
    }
    if (($result['manifest'][1]['refinements']['schema:name'][0]['propertyVocabulary']['iri'] ?? null) !== 'https://schema.org/name') {
        throw new RuntimeException('Expected EPUB manifest refinements to expose resolved vocabulary IRIs');
    }
    if (($result['spineProperties']['id'] ?? null) !== 'source-spine' || ($result['spineProperties']['refinements']['schema:position'][0]['text'] ?? null) !== 'primary reading order') {
        throw new RuntimeException('Expected EPUB spine-level refinements to remain reviewable');
    }
    if (($result['spineProperties']['refinements']['schema:position'][0]['propertyVocabulary']['iri'] ?? null) !== 'https://schema.org/position') {
        throw new RuntimeException('Expected EPUB spine refinements to expose resolved vocabulary IRIs');
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
    if (($result['resourceProperties']['summary']['remoteResourcesCount'] ?? null) !== 1 || ($result['resourceProperties']['summary']['scriptedCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB resource-property report to count remote-resource and scripted markers');
    }
    if (($result['resourceProperties']['itemsById']['chapter']['reviewFlags'] ?? []) !== ['mathml', 'svg', 'remote-resources']) {
        throw new RuntimeException('Expected EPUB chapter resource review flags for MathML, SVG, and remote resources');
    }
    if (($result['resourceProperties']['propertyVocabulary']['itemsById']['chapter']['propertyVocabulary']['items'][3]['vocabulary']['iri'] ?? null) !== 'https://schema.org/encodingFormat') {
        throw new RuntimeException('Expected EPUB manifest item properties to resolve package prefix vocabulary IRIs');
    }
    if (($result['document']->attr('resourceProperties')['propertyVocabulary']['itemsById']['chapter']['propertyVocabulary']['items'][3]['vocabulary']['iri'] ?? null) !== 'https://schema.org/encodingFormat') {
        throw new RuntimeException('Expected WordPress EPUB handoff to expose resolved manifest property vocabulary');
    }
    if (($result['resourceProperties']['itemsById']['slideshow-handler']['reviewFlags'] ?? []) !== ['scripted']) {
        throw new RuntimeException('Expected EPUB fallback handler resource review flag for scripting');
    }
    if (($result['resourceProperties']['itemsById']['bound-tour-handler']['reviewFlags'] ?? []) !== ['scripted']) {
        throw new RuntimeException('Expected EPUB binding-only handler resource review flag for scripting');
    }
    if (($result['document']->children[0]->attr('resourceReviewFlags') ?? []) !== ['mathml', 'svg', 'remote-resources']) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB resource review flags');
    }
    if (($result['document']->children[1]->attr('resourceReviewFlags') ?? []) !== ['scripted']) {
        throw new RuntimeException('Expected WordPress fallback handoff block to expose scripted resource review flag');
    }
    $contentFeatureReconciliation = $result['resourceProperties']['contentFeatureReconciliation'] ?? [];
    if (($contentFeatureReconciliation['undeclaredItemCount'] ?? null) !== 1 || ($contentFeatureReconciliation['undeclaredFeatureCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB resource-property report to flag undeclared XHTML content features');
    }
    if (($contentFeatureReconciliation['itemsById']['chapter']['undeclaredFeatures'] ?? []) !== ['switch']) {
        throw new RuntimeException('Expected EPUB chapter feature reconciliation to flag the undeclared switch property');
    }
    if (($contentFeatureReconciliation['diagnostics'][0]['type'] ?? null) !== 'undeclared-xhtml-content-feature-properties') {
        throw new RuntimeException('Expected EPUB content feature reconciliation diagnostics to identify undeclared properties');
    }
    if (($result['document']->attr('resourceProperties')['contentFeatureReconciliation']['itemsById']['chapter']['undeclaredFeatures'] ?? []) !== ['switch']) {
        throw new RuntimeException('Expected WordPress EPUB handoff to expose content feature reconciliation diagnostics');
    }
    if (($result['mediaTypes']['manifestItemCount'] ?? null) !== 17 || ($result['mediaTypes']['coreMediaTypeCount'] ?? null) !== 14) {
        throw new RuntimeException('Expected EPUB OPF media-type report to count core manifest resources');
    }
    if (($result['mediaTypes']['foreignResourceCount'] ?? null) !== 3 || ($result['mediaTypes']['foreignResourceWithoutFallbackCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB OPF media-type report to classify custom resources as covered by fallback or bindings');
    }
    $missingFallbackMedia = $result['mediaTypes']['itemsById']['missing-fallback-widget'] ?? [];
    if (($missingFallbackMedia['fallbackCoverage'] ?? null) !== 'invalid-manifest-fallback') {
        throw new RuntimeException('Expected EPUB OPF media-type report to classify a missing fallback chain as invalid coverage');
    }
    if (($missingFallbackMedia['reviewFlags'] ?? []) !== ['unresolved-manifest-fallback', 'foreign-resource-without-fallback']) {
        throw new RuntimeException('Expected EPUB OPF media-type report to preserve invalid fallback review flags');
    }
    if (($missingFallbackMedia['diagnostics'][0]['type'] ?? null) !== 'missing-manifest-fallback-item' || ($missingFallbackMedia['diagnostics'][1]['type'] ?? null) !== 'foreign-resource-without-fallback') {
        throw new RuntimeException('Expected EPUB OPF media-type report to preserve missing fallback diagnostics');
    }
    if (($result['mediaTypes']['itemsById']['slideshow']['fallbackCoverage'] ?? null) !== 'manifest-fallback') {
        throw new RuntimeException('Expected EPUB OPF media-type report to preserve manifest fallback coverage for slideshow resources');
    }
    if (($result['mediaTypes']['itemsById']['bound-tour']['fallbackCoverage'] ?? null) !== 'binding-handler') {
        throw new RuntimeException('Expected EPUB OPF media-type report to preserve OPF binding coverage for bound tour resources');
    }
    if (($result['mediaTypes']['itemsById']['style']['requiresSpineFallbackWhenDirect'] ?? null) !== true) {
        throw new RuntimeException('Expected EPUB OPF media-type report to distinguish core resources from direct spine content documents');
    }
    if (($result['document']->attr('mediaTypes')['itemsById']['bound-tour']['bindingHandlerId'] ?? null) !== 'bound-tour-handler') {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose OPF media-type fallback coverage');
    }
    if (($result['document']->attr('mediaTypes')['itemsById']['missing-fallback-widget']['reviewFlags'] ?? []) !== ['unresolved-manifest-fallback', 'foreign-resource-without-fallback']) {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose invalid OPF fallback diagnostics');
    }
    if (($result['xhtmlResourceReport']['externalReferenceCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected EPUB XHTML content scan to keep remote references unfetched for review');
    }
    if (($result['xhtmlResourceReport']['mathmlAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['svgAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['switchAssetCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB XHTML content scan to identify embedded MathML, SVG, and switch markers');
    }
    if (($result['xhtmlResourceReport']['triggerAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['triggerCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB XHTML content scan to identify trigger controls');
    }
    if (($result['xhtmlResourceReport']['scriptedAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['scriptCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB XHTML content scan to identify scripted fallback content');
    }
    if (($result['xhtmlResourceReport']['scriptEventHandlerCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['javascriptReferenceCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected EPUB XHTML scripted fallback scan to preserve event-handler metadata without active URL execution');
    }
    if (($result['xhtmlResourceReport']['viewportAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['viewportCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB XHTML content scan to identify viewport metadata');
    }
    if (($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['metadata']['viewport']['width'] ?? null) !== 1024 || ($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['metadata']['viewport']['height'] ?? null) !== 768) {
        throw new RuntimeException('Expected EPUB XHTML viewport dimensions to remain available for review');
    }
    if (($result['xhtmlResourceReport']['linkAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['linkCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['activeLinkCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB XHTML content scan to identify inert link resource policy metadata');
    }
    if (($result['xhtmlResourceReport']['refreshAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['refreshCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB XHTML content scan to identify inert meta refresh target metadata');
    }
    if (($result['xhtmlResourceReport']['sideEffectAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['sideEffectCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected EPUB XHTML content scan to identify inert form and ping side effects');
    }
    if (($result['xhtmlResourceReport']['sideEffectReferenceCount'] ?? null) !== 4 || ($result['xhtmlResourceReport']['externalSideEffectReferenceCount'] ?? null) !== 2 || ($result['xhtmlResourceReport']['missingSideEffectReferenceCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB XHTML side-effect targets to count external and missing targets without loading them');
    }
    if (($result['xhtmlResourceReport']['styleAssetCount'] ?? null) !== 1 || ($result['xhtmlResourceReport']['styleCount'] ?? null) !== 2 || ($result['xhtmlResourceReport']['styleReferenceCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB XHTML inline style resource references to stay available for review');
    }
    $chapterRefreshes = $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['refreshes'] ?? [];
    if (($chapterRefreshes[0]['id'] ?? null) !== 'source-refresh' || ($chapterRefreshes[0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB XHTML meta refresh target to resolve against the source chapter');
    }
    if (($result['document']->children[0]->attr('contentRefreshes')[0]['id'] ?? null) !== 'source-refresh') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB XHTML meta refresh metadata');
    }
    $chapterLinks = $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['links'] ?? [];
    if (($chapterLinks[0]['id'] ?? null) !== 'chapter-style-link' || ($chapterLinks[0]['policy'] ?? null) !== 'stylesheet' || ($chapterLinks[0]['part'] ?? null) !== '/EPUB/styles/review.css') {
        throw new RuntimeException('Expected EPUB XHTML stylesheet link policy to preserve local target metadata');
    }
    if (($chapterLinks[0]['byteSha256'] ?? null) !== hash('sha256', $reviewCss) || ($chapterLinks[0]['diagnostics'][0]['type'] ?? null) !== 'active-xhtml-link-resource') {
        throw new RuntimeException('Expected EPUB XHTML stylesheet link bytes and inert active-resource diagnostic');
    }
    $chapterSideEffects = $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['sideEffects'] ?? [];
    if (($chapterSideEffects[0]['kind'] ?? null) !== 'form' || ($chapterSideEffects[0]['action'] ?? null) !== 'https://forms.example.test/epub/source-review') {
        throw new RuntimeException('Expected EPUB XHTML form side-effect action to stay inert and reviewable');
    }
    if (($chapterSideEffects[1]['id'] ?? null) !== 'source-review-submit' || ($chapterSideEffects[1]['target'] ?? null) !== '/EPUB/meta/review-submit.xhtml#draft') {
        throw new RuntimeException('Expected EPUB XHTML submit formaction side effect to resolve to local package content');
    }
    if (($chapterSideEffects[2]['kind'] ?? null) !== 'anchor-ping' || ($chapterSideEffects[2]['externalPingCount'] ?? null) !== 1 || ($chapterSideEffects[2]['missingPingCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB XHTML anchor ping side effects to count external and missing ping targets');
    }
    if (($result['document']->children[0]->attr('contentSideEffects')[0]['id'] ?? null) !== 'source-review-form') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB XHTML side-effect metadata');
    }
    $chapterStyles = $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['styles'] ?? [];
    if (($chapterStyles[0]['kind'] ?? null) !== 'style-element' || ($chapterStyles[0]['references'][0]['part'] ?? null) !== '/EPUB/images/cover.png') {
        throw new RuntimeException('Expected EPUB XHTML style element resource to resolve against package image parts');
    }
    if (($chapterStyles[1]['kind'] ?? null) !== 'style-attribute' || ($chapterStyles[1]['id'] ?? null) !== 'inline-style-review' || ($chapterStyles[1]['references'][0]['part'] ?? null) !== '/EPUB/images/legacy-cover.jpg') {
        throw new RuntimeException('Expected EPUB XHTML style attribute resource to resolve against package image parts');
    }
    if (($result['document']->children[0]->attr('contentStyles') ?? []) !== $chapterStyles) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB XHTML inline style metadata');
    }
    if (($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['reviewFlags'] ?? []) !== ['mathml', 'svg', 'linked-resources', 'inline-styles', 'switch', 'trigger', 'side-effects', 'remote-resources']) {
        throw new RuntimeException('Expected EPUB XHTML content review flags for the source chapter');
    }
    if (($result['document']->children[0]->attr('contentResourceReviewFlags') ?? []) !== ['mathml', 'svg', 'linked-resources', 'inline-styles', 'switch', 'trigger', 'side-effects', 'remote-resources']) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB XHTML content review flags');
    }
    if (($result['document']->children[0]->attr('contentLinks')[0]['id'] ?? null) !== 'chapter-style-link') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB XHTML link metadata');
    }
    if (($result['document']->children[0]->attr('contentResourceFlags')['switch'] ?? null) !== true) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB switch content metadata');
    }
    if (($result['document']->children[0]->attr('contentResourceFlags')['trigger'] ?? null) !== true) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB trigger content metadata');
    }
    if (($result['document']->children[0]->attr('contentViewport')['raw'] ?? null) !== 'width=1024,height=768') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB XHTML viewport metadata');
    }
    if (($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['metadata']['language'] ?? null) !== 'en' || ($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['metadata']['direction'] ?? null) !== 'ltr') {
        throw new RuntimeException('Expected EPUB XHTML content language and direction metadata to be preserved');
    }
    if (($result['document']->children[0]->attr('contentLanguage') ?? null) !== 'en' || ($result['document']->children[0]->attr('contentDirection') ?? null) !== 'ltr') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB XHTML language and direction');
    }
    if (($result['document']->children[0]->attr('contentBodyEpubTypes') ?? []) !== ['bodymatter', 'chapter']) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB body semantic types');
    }
    $chapterTrigger = $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['triggers'][0] ?? null;
    if (!is_array($chapterTrigger) || ($chapterTrigger['action'] ?? null) !== 'play' || ($chapterTrigger['refElement'] ?? null) !== 'audio' || ($chapterTrigger['observerElement'] ?? null) !== 'span') {
        throw new RuntimeException('Expected EPUB trigger action/ref/observer metadata to remain reviewable');
    }
    if (($result['document']->children[0]->attr('contentTriggers')[0]['id'] ?? null) !== 'source-audio-trigger') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB trigger metadata');
    }
    $fallbackScripts = $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/slideshow-fallback.xhtml']['scripts'] ?? [];
    if (($fallbackScripts[0]['id'] ?? null) !== 'slideshow-review-script' || ($fallbackScripts[0]['inlineTextSha256'] ?? null) !== hash('sha256', 'window.slideshowReview = true;')) {
        throw new RuntimeException('Expected EPUB scripted fallback inline script source to remain hashable for review');
    }
    $fallbackEventHandlers = $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/slideshow-fallback.xhtml']['scriptEventHandlers'] ?? [];
    if (($fallbackEventHandlers[0]['attribute'] ?? null) !== 'onload' || ($fallbackEventHandlers[0]['valueSha256'] ?? null) !== hash('sha256', 'prepareSlideshowFallback()')) {
        throw new RuntimeException('Expected EPUB scripted fallback event-handler metadata to remain hashable for review');
    }
    if (($result['document']->children[1]->attr('contentScripts')[0]['id'] ?? null) !== 'slideshow-review-script') {
        throw new RuntimeException('Expected WordPress fallback handoff block to expose EPUB script metadata');
    }
    if (($result['document']->children[1]->attr('contentScriptEventHandlers')[0]['attribute'] ?? null) !== 'onload') {
        throw new RuntimeException('Expected WordPress fallback handoff block to expose EPUB script event-handler metadata');
    }
    if (($result['cssResourceReport']['assetCount'] ?? null) !== 1 || ($result['cssResourceReport']['referenceCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB CSS resource report to scan the package stylesheet');
    }
    if (($result['cssResourceReport']['fontFaceCount'] ?? null) !== 1 || ($result['cssResourceReport']['encryptedReferenceCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB CSS report to preserve font-face and encrypted font references');
    }
    if (($result['cssResourceReport']['fontFaceSourceCount'] ?? null) !== 1 || ($result['cssResourceReport']['fontFaceFamilies'] ?? []) !== ['Source Review']) {
        throw new RuntimeException('Expected EPUB CSS report to preserve font-face family and source counts');
    }
    if (($result['cssResourceReport']['conditionalRuleCount'] ?? null) !== 2 || ($result['cssResourceReport']['mediaRuleCount'] ?? null) !== 1 || ($result['cssResourceReport']['supportsRuleCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB CSS report to preserve conditional stylesheet metadata');
    }
    if (($result['cssResourceReport']['mediaConditions'] ?? []) !== ['screen and (min-width: 700px)', 'print']) {
        throw new RuntimeException('Expected EPUB CSS media conditions to stay inspectable');
    }
    if (($result['cssResourceReport']['supportsConditions'] ?? []) !== ['(display: grid)']) {
        throw new RuntimeException('Expected EPUB CSS supports conditions to stay inspectable');
    }
    if (($result['cssResourceReport']['pageRuleCount'] ?? null) !== 1 || ($result['cssResourceReport']['namedPageRuleCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB CSS report to preserve paged-media rule counts');
    }
    if (($result['cssResourceReport']['pageRuleNames'] ?? []) !== ['source'] || ($result['cssResourceReport']['pagePseudoClasses'] ?? []) !== ['left']) {
        throw new RuntimeException('Expected EPUB CSS page selector metadata to stay inspectable');
    }
    if (($result['cssResourceReport']['pageMarginBoxCount'] ?? null) !== 1 || ($result['cssResourceReport']['pageMarginBoxNames'] ?? []) !== ['bottom-center']) {
        throw new RuntimeException('Expected EPUB CSS page margin boxes to stay inspectable');
    }
    $reviewCssPageRule = $result['cssResourceReport']['itemsByPart']['/EPUB/styles/review.css']['pageRules'][0] ?? [];
    if (($reviewCssPageRule['selector'] ?? null) !== 'source:left' || ($reviewCssPageRule['name'] ?? null) !== 'source' || ($reviewCssPageRule['pseudoClasses'] ?? []) !== ['left']) {
        throw new RuntimeException('Expected EPUB CSS per-stylesheet page selector metadata');
    }
    if (($reviewCssPageRule['size'] ?? null) !== '6in 9in' || ($reviewCssPageRule['descriptors']['margin-left'] ?? null) !== '1in') {
        throw new RuntimeException('Expected EPUB CSS page descriptors to remain inspectable');
    }
    if (($reviewCssPageRule['marginBoxes'][0]['name'] ?? null) !== 'bottom-center' || ($reviewCssPageRule['marginBoxes'][0]['content'] ?? null) !== 'counter(page)') {
        throw new RuntimeException('Expected EPUB CSS page margin-box content metadata');
    }
    $reviewCssFontFace = $result['cssResourceReport']['itemsByPart']['/EPUB/styles/review.css']['fontFaces'][0] ?? [];
    if (($reviewCssFontFace['family'] ?? null) !== 'Source Review' || ($reviewCssFontFace['sourceCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB CSS font-face descriptor metadata to remain inspectable');
    }
    if (($reviewCssFontFace['sources'][0]['part'] ?? null) !== '/EPUB/fonts/source.otf' || ($reviewCssFontFace['sources'][0]['format'] ?? null) !== 'opentype') {
        throw new RuntimeException('Expected EPUB CSS font-face source to resolve package font metadata');
    }
    if (($reviewCssFontFace['sources'][0]['encrypted'] ?? null) !== true || ($reviewCssFontFace['sources'][0]['diagnostics'][0]['type'] ?? null) !== 'encrypted-css-font-face-source') {
        throw new RuntimeException('Expected EPUB CSS font-face source to expose encrypted font diagnostics');
    }
    if (($result['cssResourceReport']['itemsByPart']['/EPUB/styles/review.css']['reviewFlags'] ?? []) !== ['encrypted-references', 'conditional-styles', 'paged-media']) {
        throw new RuntimeException('Expected EPUB CSS review flags to identify encrypted, conditional, and paged-media stylesheet dependencies');
    }
    $cssExportPolicy = $result['cssResourceReport']['exportPolicy'] ?? [];
    if (($cssExportPolicy['assetCount'] ?? null) !== 1 || ($cssExportPolicy['blockedAssetCount'] ?? null) !== 1 || ($cssExportPolicy['exportableAssetCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected EPUB CSS export policy to classify the review stylesheet as blocked');
    }
    if (($cssExportPolicy['canExportAll'] ?? null) !== false || ($cssExportPolicy['requiresReview'] ?? null) !== true) {
        throw new RuntimeException('Expected EPUB CSS export policy to require manual review before stylesheet export');
    }
    if (($cssExportPolicy['reviewReasons'] ?? []) !== ['conditional-styles', 'paged-media'] || ($cssExportPolicy['blockingReasons'] ?? []) !== ['encrypted-references']) {
        throw new RuntimeException('Expected EPUB CSS export policy to separate review reasons from blocking encrypted font references');
    }
    $reviewCssExportPolicy = $result['cssResourceReport']['itemsByPart']['/EPUB/styles/review.css']['exportPolicy'] ?? [];
    if (($reviewCssExportPolicy['status'] ?? null) !== 'blocked' || ($reviewCssExportPolicy['canExport'] ?? null) !== false || ($reviewCssExportPolicy['requiresReview'] ?? null) !== true) {
        throw new RuntimeException('Expected EPUB CSS per-stylesheet export policy to block encrypted font-dependent styles');
    }
    if (($reviewCssExportPolicy['referenceCount'] ?? null) !== 2 || ($reviewCssExportPolicy['encryptedReferenceCount'] ?? null) !== 1 || ($reviewCssExportPolicy['pageRuleCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB CSS export policy to preserve dependency and paged-media counts');
    }
    if (($result['cssResourceReport']['itemsByPart']['/EPUB/styles/review.css']['conditionalRules'][0]['conditionItems'] ?? []) !== ['screen and (min-width: 700px)', 'print']) {
        throw new RuntimeException('Expected EPUB CSS per-stylesheet media condition metadata');
    }
    if (($result['cssResourceReport']['itemsByPart']['/EPUB/styles/review.css']['references'][0]['part'] ?? null) !== '/EPUB/fonts/source.otf') {
        throw new RuntimeException('Expected EPUB CSS report to resolve font package dependency');
    }
    if (($result['cssResourceReport']['itemsByPart']['/EPUB/styles/review.css']['references'][0]['encrypted'] ?? null) !== true) {
        throw new RuntimeException('Expected EPUB CSS font dependency to inherit OCF encryption metadata');
    }
    if (($result['cssResourceReport']['itemsByPart']['/EPUB/styles/review.css']['references'][0]['diagnostics'][0]['type'] ?? null) !== 'encrypted-css-resource-reference') {
        throw new RuntimeException('Expected EPUB CSS encrypted font dependency diagnostic');
    }
    if (($result['cssResourceReport']['itemsByPart']['/EPUB/styles/review.css']['references'][1]['part'] ?? null) !== '/EPUB/images/cover.png') {
        throw new RuntimeException('Expected EPUB CSS report to resolve local cover image dependency');
    }
    if (($result['importReport']['cssResourceReport'] ?? null) !== $result['cssResourceReport']) {
        throw new RuntimeException('Expected import report to include EPUB CSS resource report');
    }
    if (($result['document']->attr('cssResourceReport') ?? null) !== $result['cssResourceReport']) {
        throw new RuntimeException('Expected WordPress document handoff to expose EPUB CSS resource report');
    }
    $chapterBlockReferences = $result['document']->children[0]->attr('contentReferences') ?? [];
    $remoteChapterReferences = array_values(array_filter(
        is_array($chapterBlockReferences) ? $chapterBlockReferences : [],
        static fn (array $reference): bool => ($reference['target'] ?? null) === 'https://cdn.example.test/images/source.png'
    ));
    if ($remoteChapterReferences === [] || ($remoteChapterReferences[0]['diagnostics'][0]['type'] ?? null) !== 'external-xhtml-content-reference') {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose remote XHTML content reference diagnostics');
    }
    $chapterReferences = $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['references'] ?? [];
    $chapterSrcsetReferences = array_values(array_filter(
        $chapterReferences,
        static fn (array $reference): bool => ($reference['attribute'] ?? null) === 'srcset'
    ));
    if (count($chapterSrcsetReferences) !== 2) {
        throw new RuntimeException('Expected EPUB XHTML content scan to preserve responsive srcset candidates');
    }
    if (($chapterSrcsetReferences[0]['target'] ?? null) !== '/EPUB/images/cover.png' || ($chapterSrcsetReferences[1]['target'] ?? null) !== '/EPUB/images/legacy-cover.jpg') {
        throw new RuntimeException('Expected EPUB XHTML srcset candidates to resolve against package image parts');
    }
    if (($chapterSrcsetReferences[0]['srcsetDescriptor'] ?? null) !== '1x' || ($chapterSrcsetReferences[1]['srcsetDescriptor'] ?? null) !== '2x') {
        throw new RuntimeException('Expected EPUB XHTML srcset descriptors to stay available for review');
    }
    if (($chapterSrcsetReferences[0]['manifestId'] ?? null) !== 'cover-image' || ($chapterSrcsetReferences[1]['manifestId'] ?? null) !== 'legacy-cover') {
        throw new RuntimeException('Expected EPUB XHTML srcset candidates to retain OPF manifest handoff metadata');
    }
    $chapterBlockSrcsetReferences = array_values(array_filter(
        $result['document']->children[0]->attr('contentReferences') ?? [],
        static fn (array $reference): bool => ($reference['attribute'] ?? null) === 'srcset'
    ));
    if ($chapterBlockSrcsetReferences !== $chapterSrcsetReferences) {
        throw new RuntimeException('Expected WordPress chapter handoff block to expose EPUB XHTML srcset metadata');
    }
    if (($result['remoteResources']['declaredCount'] ?? null) !== 1 || ($result['remoteResources']['observedAssetCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB remote-resources declarations to reconcile with observed XHTML resource references');
    }
    if (($result['remoteResources']['remoteReferenceCount'] ?? null) !== 1 || ($result['remoteResources']['xhtmlExternalReferenceCount'] ?? null) !== 3) {
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
    if (($result['bindings']['items'][2]['handlerId'] ?? null) !== 'bound-tour-handler') {
        throw new RuntimeException('Expected EPUB OPF binding-only handler to be reported');
    }
    if (($result['spine'][1]['binding']['handlerId'] ?? null) !== 'slideshow-handler') {
        throw new RuntimeException('Expected custom media-type spine item to carry its OPF binding handler');
    }
    if (($result['document']->children[1]->attr('binding')['handlerId'] ?? null) !== 'slideshow-handler') {
        throw new RuntimeException('Expected WordPress fallback block to expose OPF binding metadata');
    }
    if (($result['spine'][2]['contentId'] ?? null) !== 'bound-tour-handler' || ($result['spine'][2]['fallbackChain'][0]['source'] ?? null) !== 'binding-handler') {
        throw new RuntimeException('Expected EPUB binding-only spine item to resolve to its XHTML media handler');
    }
    if (($result['document']->children[2]->attr('source') ?? null) !== 'epub3-spine-fallback' || ($result['document']->children[2]->attr('binding')['handlerId'] ?? null) !== 'bound-tour-handler') {
        throw new RuntimeException('Expected WordPress binding-only fallback block to expose OPF binding metadata');
    }
    if (!str_contains((string) $result['document']->children[2]->attr('html'), 'OPF media-type binding handler content is preserved')) {
        throw new RuntimeException('Expected EPUB binding-only handler XHTML to remain reviewable in the AST');
    }
    if (($result['encryption']['obfuscatedFonts'][0]['part'] ?? null) !== '/EPUB/fonts/source.otf') {
        throw new RuntimeException('Expected EPUB obfuscated font preflight to identify the package font');
    }
    if (($result['encryption']['exposure']['present'] ?? null) !== true || ($result['encryption']['exposure']['itemCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB encryption exposure summary to report the protected font resource');
    }
    if (($result['encryption']['exposure']['blockedByteExposureCount'] ?? null) !== 1 || ($result['encryption']['exposure']['obfuscatedFontCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB encryption exposure summary to block obfuscated font bytes');
    }
    if (($result['encryption']['exposure']['roles'] ?? null) !== ['font'] || ($result['encryption']['exposure']['roleCounts']['font'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB encryption exposure summary to classify the protected resource role');
    }
    if (($result['encryption']['exposure']['items'][0]['reviewPolicy'] ?? null) !== 'obfuscated-font-review') {
        throw new RuntimeException('Expected EPUB encryption exposure item to carry obfuscated font review policy');
    }
    if (($result['encryption']['exposure']['items'][0]['byteExposurePolicy'] ?? null) !== 'obfuscated-font-bytes-blocked') {
        throw new RuntimeException('Expected EPUB encryption exposure item to carry byte-blocking policy');
    }
    if (($result['encryption']['exposure']['items'][0]['attachmentCandidateBlocked'] ?? null) !== true) {
        throw new RuntimeException('Expected EPUB encrypted font to be blocked as an attachment candidate');
    }
    if (($result['importReport']['encryption']['exposure'] ?? null) !== $result['encryption']['exposure']) {
        throw new RuntimeException('Expected EPUB import report to expose encryption exposure policy');
    }
    if (($result['ocf']['sidecarCount'] ?? null) !== 4 || ($result['ocf']['externalReferenceCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected EPUB OCF manifest/metadata/rights/signatures sidecars to report review references without fetching remote resources');
    }
    if (($result['ocf']['manifest']['format'] ?? null) !== 'odf-manifest' || ($result['ocf']['manifest']['itemCount'] ?? null) !== 4) {
        throw new RuntimeException('Expected EPUB OCF manifest sidecar entries to be reported for review');
    }
    if (($result['ocf']['manifest']['itemsByPart']['/EPUB/text/chapter.xhtml']['byteSha256'] ?? null) !== hash('sha256', $chapterXhtml)) {
        throw new RuntimeException('Expected EPUB OCF manifest sidecar to hash local chapter bytes');
    }
    if (($result['document']->attr('ocf')['manifest']['itemCount'] ?? null) !== 4) {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose OCF manifest sidecar metadata');
    }
    if (($result['ocf']['metadata']['items'][0]['reference']['target'] ?? null) !== '/META-INF/review/container-source.json') {
        throw new RuntimeException('Expected EPUB OCF metadata sidecar to resolve local source metadata');
    }
    if (($result['ocf']['metadata']['items'][1]['diagnostics'][0]['type'] ?? null) !== 'ocf-metadata-remote-reference') {
        throw new RuntimeException('Expected EPUB OCF metadata remote policy to remain unfetched');
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
    if (($result['document']->attr('ocf')['metadata']['itemCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected WordPress EPUB document handoff to expose OCF container metadata');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][0]['audioTarget'] ?? null) !== '/EPUB/audio/chapter.mp3') {
        throw new RuntimeException('Expected EPUB media-overlay audio target to resolve relative to the SMIL part');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][0]['audioManifestId'] ?? null) !== 'audio-chapter' || ($result['mediaOverlays']['mo-chapter']['items'][0]['audioMediaType'] ?? null) !== 'audio/mpeg') {
        throw new RuntimeException('Expected EPUB media-overlay audio target to preserve OPF manifest provenance');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][0]['audioByteSha256'] ?? null) !== hash('sha256', 'MP3-DATA')) {
        throw new RuntimeException('Expected EPUB media-overlay audio target to expose local package byte hash');
    }
    if (($result['mediaOverlays']['mo-chapter']['sequenceCount'] ?? null) !== 1 || ($result['mediaOverlays']['mo-chapter']['sequences'][0]['id'] ?? null) !== 'chapter-overlay') {
        throw new RuntimeException('Expected EPUB media-overlay sequence provenance to expose the chapter overlay group');
    }
    if (($result['mediaOverlays']['mo-chapter']['sequences'][0]['textRefManifestId'] ?? null) !== 'chapter') {
        throw new RuntimeException('Expected EPUB media-overlay sequence textref to resolve through OPF manifest metadata');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][0]['sequencePath'] ?? null) !== ['chapter-overlay']) {
        throw new RuntimeException('Expected EPUB media-overlay audio item to retain sequence ancestry');
    }
    if (($result['document']->children[0]->attr('mediaOverlayReference')['sequenceCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected WordPress EPUB handoff block to expose media-overlay sequence counts');
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
    if (($result['manifest'][1]['mediaOverlayReference']['id'] ?? null) !== 'mo-chapter') {
        throw new RuntimeException('Expected EPUB manifest item to expose its OPF media-overlay binding');
    }
    if (($result['manifest'][1]['mediaOverlayReference']['part'] ?? null) !== '/EPUB/overlays/chapter.smil') {
        throw new RuntimeException('Expected EPUB manifest media-overlay binding to resolve to the SMIL package part');
    }
    if (($result['manifest'][1]['mediaOverlayReference']['itemCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected EPUB manifest media-overlay binding to expose parsed timing item count');
    }
    if (($result['spine'][0]['mediaOverlayReference']['duration'] ?? null) !== '0:00:08.000') {
        throw new RuntimeException('Expected EPUB spine item to expose OPF media-overlay duration binding');
    }
    if (($result['document']->children[0]->attr('mediaOverlayReference')['textRefTarget'] ?? null) !== '/EPUB/text/chapter.xhtml') {
        throw new RuntimeException('Expected WordPress EPUB handoff block to expose media-overlay text reference target');
    }
    if (($result['document']->children[0]->attr('mediaOverlayReference')['textRefManifestId'] ?? null) !== 'chapter' || ($result['document']->children[0]->attr('mediaOverlayReference')['textRefMediaType'] ?? null) !== 'application/xhtml+xml') {
        throw new RuntimeException('Expected WordPress EPUB handoff block to expose media-overlay textref manifest metadata');
    }
    if (($result['document']->children[0]->attr('mediaOverlayReference')['textRefByteSha256'] ?? null) !== hash('sha256', $chapterXhtml)) {
        throw new RuntimeException('Expected WordPress EPUB handoff block to expose media-overlay textref byte hash');
    }
    if (($result['document']->attr('mediaDurations')['overlaysById']['mo-chapter']['duration'] ?? null) !== '0:00:08.000') {
        throw new RuntimeException('Expected WordPress document handoff to expose EPUB media duration metadata');
    }
    $manifestById = [];
    foreach ($result['manifest'] as $manifestItem) {
        $manifestById[$manifestItem['id']] = $manifestItem;
    }
    if (($manifestById['chapter']['byteSha256'] ?? null) !== hash('sha256', $chapterXhtml)) {
        throw new RuntimeException('Expected EPUB OPF manifest chapter item to expose a package byte hash');
    }
    if (($manifestById['style']['byteSha256'] ?? null) !== hash('sha256', $reviewCss)) {
        throw new RuntimeException('Expected EPUB OPF manifest stylesheet item to expose a package byte hash');
    }
    if (($manifestById['font-main']['byteSha256'] ?? null) !== null || ($manifestById['font-main']['encrypted'] ?? false) !== true) {
        throw new RuntimeException('Expected encrypted EPUB OPF manifest font item to stay hash-free');
    }
    $manifestByteProvenance = $result['importReport']['manifest']['byteProvenance'] ?? [];
    if (($manifestByteProvenance['itemCount'] ?? null) !== 17 || ($manifestByteProvenance['hashedItemCount'] ?? null) !== 15) {
        throw new RuntimeException('Expected EPUB manifest byte-provenance report to summarize local hashed resources');
    }
    if (($manifestByteProvenance['encryptedItemCount'] ?? null) !== 1 || ($manifestByteProvenance['externalItemCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected EPUB manifest byte-provenance report to separate encrypted and remote resources');
    }
    if (($manifestByteProvenance['itemsById']['chapter']['byteSha256'] ?? null) !== hash('sha256', $chapterXhtml)) {
        throw new RuntimeException('Expected EPUB import report to index manifest byte hashes by OPF id');
    }
    $encryptedManifestItem = $manifestByteProvenance['encryptedItems'][0] ?? [];
    if (
        ($encryptedManifestItem['id'] ?? null) !== 'font-main'
        || !array_key_exists('byteSha256', $encryptedManifestItem)
        || $encryptedManifestItem['byteSha256'] !== null
    ) {
        throw new RuntimeException('Expected EPUB import report to keep encrypted manifest resources hash-free');
    }
    if (($result['importReport']['manifest']['externalItems'][0]['id'] ?? null) !== 'remote-audio-note') {
        throw new RuntimeException('Expected remote EPUB manifest resource to be reported separately from missing ZIP assets');
    }
    if (($result['importReport']['manifest']['missingItems'] ?? null) !== []) {
        throw new RuntimeException('Expected remote EPUB manifest resource not to be counted as a missing package item');
    }
    if (($result['importReport']['manifest']['duplicatePackagePartCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected duplicate OPF package-part references to be summarized for import preflight');
    }
    if (($result['importReport']['manifest']['duplicatePackagePartItems'][0]['ids'] ?? null) !== ['toc', 'toc-alias']) {
        throw new RuntimeException('Expected duplicate OPF package-part report to retain both manifest item ids');
    }
    if (($result['importReport']['manifest']['itemsByPart']['/EPUB/toc.ncx'][1]['id'] ?? null) !== 'toc-alias') {
        throw new RuntimeException('Expected manifest itemsByPart report to preserve the aliasing NCX item');
    }
    if (($result['importReport']['manifest']['diagnostics'][0]['type'] ?? null) !== 'duplicate-manifest-package-part') {
        throw new RuntimeException('Expected duplicate OPF package-part diagnostic in manifest import report');
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
    if (($result['importReport']['assets']['coverImageCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected EPUB asset report to expose both manifest and legacy meta cover candidates');
    }
    if (($result['importReport']['assets']['coverImages'][1]['id'] ?? null) !== 'legacy-cover') {
        throw new RuntimeException('Expected EPUB legacy meta cover candidate to stay visible for review');
    }
    if (($result['importReport']['assets']['coverImages'][1]['coverImageSources'] ?? null) !== ['meta-name-cover']) {
        throw new RuntimeException('Expected EPUB legacy meta cover source provenance');
    }
    if (($result['importReport']['assets']['coverImageDiagnostics'][0]['type'] ?? null) !== 'multiple-cover-image-candidates') {
        throw new RuntimeException('Expected conflicting EPUB cover-image candidates to produce a review diagnostic');
    }
    if (($result['importReport']['assets']['coverImageDiagnostics'][0]['metaCoverItemId'] ?? null) !== 'legacy-cover') {
        throw new RuntimeException('Expected EPUB cover-image diagnostic to identify the legacy meta cover id');
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
echo 'mainTitleType=' . ($result['metadata']['mainTitle']['titleType'] ?? '') . "\n";
echo 'mainTitleLanguage=' . ($result['metadata']['mainTitle']['language'] ?? '') . "\n";
echo 'mainTitleDirection=' . ($result['metadata']['mainTitle']['direction'] ?? '') . "\n";
echo 'subtitleTitle=' . ($result['metadata']['subtitle']['text'] ?? '') . "\n";
echo 'subtitleDirection=' . ($result['metadata']['subtitle']['direction'] ?? '') . "\n";
echo 'titleTypes=' . implode(',', array_keys($result['metadata']['titlesByType'] ?? [])) . "\n";
echo 'identifier=' . $result['metadata']['identifier'] . "\n";
echo 'uniqueIdentifierId=' . ($result['metadata']['uniqueIdentifier']['id'] ?? '') . "\n";
echo 'uniqueIdentifierSelectedBy=' . ($result['metadata']['uniqueIdentifier']['selectedBy'] ?? '') . "\n";
echo 'uniqueIdentifierDiagnostics=' . count($result['metadata']['uniqueIdentifier']['diagnostics'] ?? []) . "\n";
echo 'opfPart=' . $result['opfPart'] . "\n";
echo 'containerLinks=' . ($result['container']['linkCount'] ?? 0) . "\n";
echo 'containerRecordTarget=' . ($result['container']['links'][0]['target'] ?? '') . "\n";
echo 'containerRecordSha256=' . ($result['container']['links'][0]['byteSha256'] ?? '') . "\n";
echo 'containerRemoteDiagnostics=' . count($result['container']['linkDiagnostics'] ?? []) . "\n";
echo 'containerCfiPath=' . ($result['container']['links'][2]['epubCfi']['path'] ?? '') . "\n";
echo 'opfPrefixes=' . implode(',', array_keys($result['package']['prefixes'] ?? [])) . "\n";
echo 'schemaPrefix=' . ($result['package']['prefixes']['schema'] ?? '') . "\n";
echo 'metadataVocabularyResolved=' . ($result['metadata']['vocabulary']['resolvedPropertyCount'] ?? 0) . "\n";
echo 'vendorMetadataItems=' . ($result['metadata']['vendorMetadata']['itemCount'] ?? 0) . "\n";
echo 'ibooksSpecifiedFonts=' . ($result['metadata']['vendorMetadata']['ibooks']['specified-fonts'][0]['value'] ?? '') . "\n";
echo 'calibreSeries=' . ($result['metadata']['vendorMetadata']['calibre']['series'][0]['value'] ?? '') . "\n";
echo 'spineItems=' . count($result['spine']) . "\n";
echo 'pageProgressionDirection=' . ($result['spineProperties']['pageProgressionDirection'] ?? '') . "\n";
echo 'rightToLeft=' . (($result['spineProperties']['rightToLeft'] ?? false) ? 'yes' : 'no') . "\n";
echo 'firstPageSpread=' . ($result['spine'][0]['pageSpread'] ?? '') . "\n";
echo 'firstEffectiveLayout=' . ($result['spine'][0]['effectiveRendition']['layout'] ?? '') . "\n";
echo 'firstEffectiveViewportSource=' . ($result['spine'][0]['effectiveRendition']['viewportSource'] ?? '') . "\n";
echo 'firstLinearRaw=' . ($result['spine'][0]['linearRaw'] ?? '') . "\n";
echo 'firstLinearValid=' . (($result['spine'][0]['linearValid'] ?? true) ? 'yes' : 'no') . "\n";
echo 'linearSpineItems=' . ($result['spineProperties']['linearItemCount'] ?? 0) . "\n";
echo 'primaryReadingOrderEmpty=' . (($result['spineProperties']['primaryReadingOrderEmpty'] ?? false) ? 'yes' : 'no') . "\n";
echo 'spineLinearDiagnostics=' . count($result['spineProperties']['itemDiagnostics'] ?? []) . "\n";
echo 'fallbackPageSpread=' . ($result['spine'][1]['pageSpread'] ?? '') . "\n";
echo 'boundTourEffectiveLayout=' . ($result['spine'][2]['effectiveRendition']['layout'] ?? '') . "\n";
echo 'boundTourEffectiveLayoutSource=' . ($result['spine'][2]['effectiveRendition']['layoutSource'] ?? '') . "\n";
echo 'fallbackSpineContent=' . ($result['spine'][1]['contentPart'] ?? '') . "\n";
echo 'navTarget=' . ($result['nav']['items'][0]['target'] ?? '') . "\n";
echo 'navSectionId=' . ($result['nav']['sections'][0]['id'] ?? '') . "\n";
echo 'navSectionClass=' . ($result['nav']['sections'][0]['class'] ?? '') . "\n";
echo 'navItemId=' . ($result['nav']['items'][0]['id'] ?? '') . "\n";
echo 'navItemClass=' . ($result['nav']['items'][0]['class'] ?? '') . "\n";
echo 'remoteNavExternal=' . (($result['nav']['items'][1]['external'] ?? false) ? 'yes' : 'no') . "\n";
echo 'navCfiPath=' . ($result['nav']['items'][0]['children'][0]['epubCfi']['path'] ?? '') . "\n";
echo 'ncxTitle=' . ($result['ncx']['docTitle'] ?? '') . "\n";
echo 'ncxAuthors=' . implode(',', $result['ncx']['docAuthors'] ?? []) . "\n";
echo 'ncxUid=' . ($result['ncx']['head']['uid'] ?? '') . "\n";
echo 'ncxDepth=' . ($result['ncx']['head']['depth'] ?? '') . "\n";
echo 'ncxHeadMeta=' . ($result['ncx']['head']['metaCount'] ?? 0) . "\n";
echo 'ncxNavLists=' . ($result['ncx']['navListCount'] ?? 0) . "\n";
echo 'ncxNavListFirstTarget=' . ($result['ncx']['navLists'][0]['items'][0]['target'] ?? '') . "\n";
echo 'ncxNavListRoles=' . implode(',', $result['ncx']['navListRoleReport']['roles'] ?? []) . "\n";
echo 'ncxNavListRoleDiagnostics=' . ($result['ncx']['navListRoleReport']['diagnosticCount'] ?? 0) . "\n";
echo 'ncxNavListDiagnostics=' . count($result['ncx']['navListDiagnostics'] ?? []) . "\n";
echo 'ncxAudioLabels=' . ($result['ncx']['audioLabelCount'] ?? 0) . "\n";
echo 'ncxAudioFirstTarget=' . ($result['ncx']['items'][0]['labelAudio'][0]['target'] ?? '') . "\n";
echo 'ncxAudioFirstManifestId=' . ($result['ncx']['items'][0]['labelAudio'][0]['manifestId'] ?? '') . "\n";
echo 'ncxAudioDiagnostics=' . count($result['ncx']['audioLabelDiagnostics'] ?? []) . "\n";
echo 'navigationTargets=' . ($result['navigation']['targetCount'] ?? 0) . "\n";
echo 'navigationMappedTargets=' . ($result['navigation']['mappedSpineTargetCount'] ?? 0) . "\n";
echo 'navigationNcxNavListTargets=' . ($result['navigation']['ncxNavListTargetCount'] ?? 0) . "\n";
echo 'navigationSupplementalMappedTargets=' . ($result['navigation']['supplementalMappedSpineTargetCount'] ?? 0) . "\n";
echo 'navigationSupplementalExternalTargets=' . ($result['navigation']['supplementalExternalTargetCount'] ?? 0) . "\n";
echo 'navigationCfiTargets=' . ($result['navigation']['cfiTargetCount'] ?? 0) . "\n";
echo 'navigationExternalTargets=' . ($result['navigation']['externalTargetCount'] ?? 0) . "\n";
echo 'navigationUncoveredLinear=' . ($result['navigation']['uncoveredLinearSpineItemCount'] ?? 0) . "\n";
echo 'navigationOutlineSource=' . ($result['navigationOutline']['source'] ?? '') . "\n";
echo 'navigationOutlineItems=' . ($result['navigationOutline']['itemCount'] ?? 0) . "\n";
echo 'navigationOutlineExternal=' . ($result['navigationOutline']['externalTargetCount'] ?? 0) . "\n";
echo 'navigationOutlineHtmlSha256=' . ($result['navigationOutline']['htmlSha256'] ?? '') . "\n";
echo 'landmarkTarget=' . ($result['nav']['landmarks'][0]['target'] ?? '') . "\n";
echo 'tocItemTypeSource=' . ($result['nav']['items'][0]['typeSource'] ?? '') . "\n";
echo 'tocItemTypes=' . implode(',', $result['nav']['items'][0]['itemTypes'] ?? []) . "\n";
echo 'pageListTarget=' . ($result['nav']['pageList'][0]['target'] ?? '') . "\n";
echo 'primaryNavPolicyItems=' . ($result['nav']['primaryNavigationTargetPolicy']['itemCount'] ?? 0) . "\n";
echo 'primaryNavPolicyExternal=' . ($result['nav']['primaryNavigationTargetPolicy']['externalTargetCount'] ?? 0) . "\n";
echo 'primaryNavPolicyDiagnostics=' . ($result['nav']['primaryNavigationTargetPolicy']['diagnosticCount'] ?? 0) . "\n";
echo 'pageBreaks=' . ($result['pageBreaks']['count'] ?? 0) . "\n";
echo 'firstPageBreakFragment=' . ($result['pageBreaks']['items'][0]['fragment'] ?? '') . "\n";
echo 'firstSpinePageBreaks=' . ($result['document']->children[0]->attr('pageBreakCount') ?? 0) . "\n";
echo 'guideReferences=' . count($result['guide']['items'] ?? []) . "\n";
echo 'guideTextTarget=' . ($result['guide']['items'][0]['target'] ?? '') . "\n";
echo 'guideTypes=' . implode(',', $result['guide']['types'] ?? []) . "\n";
echo 'guideTypedReferences=' . ($result['guide']['typedItemCount'] ?? 0) . "\n";
echo 'collectionRole=' . ($result['collections'][0]['role'] ?? '') . "\n";
echo 'collectionTitleLanguage=' . ($result['collections'][0]['metadata']['titleDetails'][0]['language'] ?? '') . "\n";
echo 'collectionTitleDirection=' . ($result['collections'][0]['metadata']['titleDetails'][0]['direction'] ?? '') . "\n";
echo 'collectionFirstTarget=' . ($result['collections'][0]['links'][0]['target'] ?? '') . "\n";
echo 'collectionLinkRels=' . implode(',', $result['collections'][0]['linkReport']['relTokens'] ?? []) . "\n";
echo 'collectionExternalLinks=' . ($result['collections'][0]['linkReport']['externalCount'] ?? 0) . "\n";
echo 'metadataLinks=' . count($result['metadata']['links'] ?? []) . "\n";
echo 'metadataRecordTarget=' . ($result['metadata']['links'][0]['target'] ?? '') . "\n";
echo 'metadataRecordTitle=' . ($result['metadata']['links'][0]['title'] ?? '') . "\n";
echo 'metadataRecordLanguage=' . ($result['metadata']['links'][0]['language'] ?? '') . "\n";
echo 'metadataRecordDirection=' . ($result['metadata']['links'][0]['direction'] ?? '') . "\n";
echo 'metadataRecordSha256=' . ($result['metadata']['links'][0]['byteSha256'] ?? '') . "\n";
echo 'metadataLinkRelTokens=' . ($result['metadata']['linkVocabulary']['relTokenCount'] ?? 0) . "\n";
echo 'metadataLinkPropertyTokens=' . ($result['metadata']['linkVocabulary']['propertyTokenCount'] ?? 0) . "\n";
echo 'metadataLinkRecordRels=' . ($result['metadata']['linkVocabulary']['rels']['record'] ?? 0) . "\n";
echo 'metadataLinkDiagnostics=' . ($result['metadata']['linkVocabulary']['diagnosticCount'] ?? 0) . "\n";
echo 'metadataLinkTargets=' . ($result['metadata']['linkTargetReport']['linkCount'] ?? 0) . "\n";
echo 'metadataLinkTargetDiagnostics=' . ($result['metadata']['linkTargetReport']['diagnosticCount'] ?? 0) . "\n";
echo 'metadataLinkedResourceSubjects=' . ($result['metadata']['linkedResourceSummary']['subjectCount'] ?? 0) . "\n";
echo 'metadataCreatorLinkedResources=' . count($result['metadata']['dc']['creator'][0]['linkedResources'] ?? []) . "\n";
echo 'metadataCreatorDirection=' . ($result['metadata']['creatorDetails'][0]['direction'] ?? '') . "\n";
echo 'remoteMetadataLink=' . (($result['metadata']['links'][1]['external'] ?? false) ? 'yes' : 'no') . "\n";
echo 'identifierType=' . ($result['metadata']['dc']['identifier'][0]['refinements']['identifier-type'][0]['text'] ?? '') . "\n";
echo 'identifierSchemes=' . implode(',', $result['metadata']['identifierSummary']['schemes'] ?? []) . "\n";
echo 'identifierDuplicateValues=' . implode(',', $result['metadata']['identifierSummary']['duplicateValues'] ?? []) . "\n";
echo 'dateEvents=' . implode(',', $result['metadata']['dateSummary']['events'] ?? []) . "\n";
echo 'creatorFileAs=' . ($result['metadata']['dc']['creator'][0]['refinements']['file-as'][0]['text'] ?? '') . "\n";
echo 'creatorRole=' . ($result['metadata']['dc']['creator'][0]['refinements']['role'][0]['text'] ?? '') . "\n";
echo 'contributors=' . implode(',', $result['metadata']['contributors'] ?? []) . "\n";
echo 'contributorRoles=' . implode(',', array_keys($result['metadata']['contributorsByRole'] ?? [])) . "\n";
echo 'agentDisplayOrder=' . implode(',', array_map(
    static fn (array $item): string => $item['text'],
    $result['metadata']['agentDisplayOrder']['items'] ?? []
)) . "\n";
echo 'agentDisplaySequenced=' . ($result['metadata']['agentDisplayOrder']['sequencedCount'] ?? 0) . "\n";
echo 'agentDisplayUnsequenced=' . ($result['metadata']['agentDisplayOrder']['unsequencedCount'] ?? 0) . "\n";
echo 'agentDisplayRoles=' . implode(',', $result['metadata']['agentDisplayOrder']['roles'] ?? []) . "\n";
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
echo 'contentFeatureMismatches=' . ($result['resourceProperties']['contentFeatureReconciliation']['diagnosticCount'] ?? 0) . "\n";
echo 'chapterUndeclaredFeatures=' . implode(',', $result['resourceProperties']['contentFeatureReconciliation']['itemsById']['chapter']['undeclaredFeatures'] ?? []) . "\n";
echo 'mediaCoreTypes=' . ($result['mediaTypes']['coreMediaTypeCount'] ?? 0) . "\n";
echo 'mediaForeignResources=' . ($result['mediaTypes']['foreignResourceCount'] ?? 0) . "\n";
echo 'mediaReviewRequired=' . ($result['mediaTypes']['reviewRequiredCount'] ?? 0) . "\n";
echo 'slideshowMediaCoverage=' . ($result['mediaTypes']['itemsById']['slideshow']['fallbackCoverage'] ?? '') . "\n";
echo 'boundTourMediaCoverage=' . ($result['mediaTypes']['itemsById']['bound-tour']['fallbackCoverage'] ?? '') . "\n";
echo 'missingFallbackMediaCoverage=' . ($result['mediaTypes']['itemsById']['missing-fallback-widget']['fallbackCoverage'] ?? '') . "\n";
echo 'missingFallbackReviewFlags=' . implode(',', $result['mediaTypes']['itemsById']['missing-fallback-widget']['reviewFlags'] ?? []) . "\n";
echo 'xhtmlContentRemoteReferences=' . ($result['xhtmlResourceReport']['externalReferenceCount'] ?? 0) . "\n";
echo 'xhtmlContentMathmlAssets=' . ($result['xhtmlResourceReport']['mathmlAssetCount'] ?? 0) . "\n";
echo 'xhtmlContentSwitchAssets=' . ($result['xhtmlResourceReport']['switchAssetCount'] ?? 0) . "\n";
echo 'xhtmlContentSwitches=' . ($result['xhtmlResourceReport']['switchCount'] ?? 0) . "\n";
echo 'chapterSwitchCaseNamespace=' . ($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['switches'][0]['cases'][0]['requiredNamespace'] ?? '') . "\n";
echo 'chapterSwitchDefault=' . ($result['document']->children[0]->attr('contentSwitches')[0]['defaults'][0]['text'] ?? '') . "\n";
echo 'xhtmlContentTriggerAssets=' . ($result['xhtmlResourceReport']['triggerAssetCount'] ?? 0) . "\n";
echo 'xhtmlContentTriggers=' . ($result['xhtmlResourceReport']['triggerCount'] ?? 0) . "\n";
echo 'xhtmlScriptedAssets=' . ($result['xhtmlResourceReport']['scriptedAssetCount'] ?? 0) . "\n";
echo 'xhtmlScripts=' . ($result['xhtmlResourceReport']['scriptCount'] ?? 0) . "\n";
echo 'xhtmlScriptEventHandlers=' . ($result['xhtmlResourceReport']['scriptEventHandlerCount'] ?? 0) . "\n";
echo 'xhtmlLinkAssets=' . ($result['xhtmlResourceReport']['linkAssetCount'] ?? 0) . "\n";
echo 'xhtmlLinks=' . ($result['xhtmlResourceReport']['linkCount'] ?? 0) . "\n";
echo 'chapterLinkPolicy=' . ($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['links'][0]['policy'] ?? '') . "\n";
echo 'xhtmlRefreshes=' . ($result['xhtmlResourceReport']['refreshCount'] ?? 0) . "\n";
echo 'chapterRefreshTarget=' . ($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['refreshes'][0]['target'] ?? '') . "\n";
echo 'xhtmlSideEffectAssets=' . ($result['xhtmlResourceReport']['sideEffectAssetCount'] ?? 0) . "\n";
echo 'xhtmlSideEffects=' . ($result['xhtmlResourceReport']['sideEffectCount'] ?? 0) . "\n";
echo 'xhtmlSideEffectReferences=' . ($result['xhtmlResourceReport']['sideEffectReferenceCount'] ?? 0) . "\n";
echo 'chapterSideEffectKinds=' . implode(',', array_map(
    static fn (array $item): string => (string) ($item['kind'] ?? ''),
    $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['sideEffects'] ?? []
)) . "\n";
echo 'xhtmlInlineStyleAssets=' . ($result['xhtmlResourceReport']['styleAssetCount'] ?? 0) . "\n";
echo 'xhtmlInlineStyles=' . ($result['xhtmlResourceReport']['styleCount'] ?? 0) . "\n";
echo 'xhtmlInlineStyleReferences=' . ($result['xhtmlResourceReport']['styleReferenceCount'] ?? 0) . "\n";
echo 'chapterInlineStyleKinds=' . implode(',', array_map(
    static fn (array $item): string => (string) ($item['kind'] ?? ''),
    $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['styles'] ?? []
)) . "\n";
echo 'xhtmlViewportAssets=' . ($result['xhtmlResourceReport']['viewportAssetCount'] ?? 0) . "\n";
echo 'chapterViewport=' . ($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['metadata']['viewport']['raw'] ?? '') . "\n";
echo 'chapterLanguage=' . ($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['metadata']['language'] ?? '') . "\n";
echo 'chapterDirection=' . ($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['metadata']['direction'] ?? '') . "\n";
echo 'chapterBodyTypes=' . implode(',', $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['metadata']['bodyEpubTypes'] ?? []) . "\n";
echo 'chapterContentReviewFlags=' . implode(',', $result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['reviewFlags'] ?? []) . "\n";
echo 'chapterTriggerAction=' . ($result['xhtmlResourceReport']['itemsByPart']['/EPUB/text/chapter.xhtml']['triggers'][0]['action'] ?? '') . "\n";
echo 'cssResourceAssets=' . ($result['cssResourceReport']['assetCount'] ?? 0) . "\n";
echo 'cssResourceReferences=' . ($result['cssResourceReport']['referenceCount'] ?? 0) . "\n";
echo 'cssFontFaces=' . ($result['cssResourceReport']['fontFaceCount'] ?? 0) . "\n";
echo 'cssFontFaceSources=' . ($result['cssResourceReport']['fontFaceSourceCount'] ?? 0) . "\n";
echo 'cssEncryptedReferences=' . ($result['cssResourceReport']['encryptedReferenceCount'] ?? 0) . "\n";
echo 'cssConditionalRules=' . ($result['cssResourceReport']['conditionalRuleCount'] ?? 0) . "\n";
echo 'cssMediaConditions=' . implode('|', $result['cssResourceReport']['mediaConditions'] ?? []) . "\n";
echo 'cssSupportsConditions=' . implode('|', $result['cssResourceReport']['supportsConditions'] ?? []) . "\n";
echo 'cssPageRules=' . ($result['cssResourceReport']['pageRuleCount'] ?? 0) . "\n";
echo 'cssPageRuleNames=' . implode('|', $result['cssResourceReport']['pageRuleNames'] ?? []) . "\n";
echo 'cssPageMarginBoxes=' . ($result['cssResourceReport']['pageMarginBoxCount'] ?? 0) . "\n";
echo 'cssExportStatuses=' . implode('|', $result['cssResourceReport']['exportPolicy']['statuses'] ?? []) . "\n";
echo 'cssExportBlockedAssets=' . ($result['cssResourceReport']['exportPolicy']['blockedAssetCount'] ?? 0) . "\n";
echo 'cssExportBlockingReasons=' . implode('|', $result['cssResourceReport']['exportPolicy']['blockingReasons'] ?? []) . "\n";
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
echo 'encryptedExposureBlocked=' . ($result['encryption']['exposure']['blockedByteExposureCount'] ?? 0) . "\n";
echo 'encryptedExposureRoles=' . implode(',', $result['encryption']['exposure']['roles'] ?? []) . "\n";
echo 'ocfSidecars=' . ($result['ocf']['sidecarCount'] ?? 0) . "\n";
echo 'ocfManifestItems=' . ($result['ocf']['manifest']['itemCount'] ?? 0) . "\n";
echo 'ocfManifestDeclaredParts=' . ($result['ocf']['manifest']['declaredPartCount'] ?? 0) . "\n";
echo 'ocfMetadataItems=' . ($result['ocf']['metadata']['itemCount'] ?? 0) . "\n";
echo 'ocfRightsItems=' . ($result['ocf']['rights']['itemCount'] ?? 0) . "\n";
echo 'ocfSignatureReferences=' . ($result['ocf']['signatures']['referenceCount'] ?? 0) . "\n";
echo 'ocfExternalReferences=' . ($result['ocf']['externalReferenceCount'] ?? 0) . "\n";
echo 'mediaOverlayItems=' . count($result['mediaOverlays']['mo-chapter']['items'] ?? []) . "\n";
echo 'mediaOverlaySequences=' . ($result['mediaOverlays']['mo-chapter']['sequenceCount'] ?? 0) . "\n";
echo 'mediaOverlayFirstSequence=' . ($result['mediaOverlays']['mo-chapter']['sequences'][0]['id'] ?? '') . "\n";
echo 'mediaOverlayAudio=' . ($result['mediaOverlays']['mo-chapter']['items'][0]['audioTarget'] ?? '') . "\n";
echo 'mediaOverlayAudioManifestId=' . ($result['mediaOverlays']['mo-chapter']['items'][0]['audioManifestId'] ?? '') . "\n";
echo 'mediaOverlayAudioMediaType=' . ($result['mediaOverlays']['mo-chapter']['items'][0]['audioMediaType'] ?? '') . "\n";
echo 'mediaOverlayAudioSha256=' . ($result['mediaOverlays']['mo-chapter']['items'][0]['audioByteSha256'] ?? '') . "\n";
echo 'mediaOverlayFirstClipSeconds=' . ($result['mediaOverlays']['mo-chapter']['items'][0]['clipDurationSeconds'] ?? '') . "\n";
echo 'mediaOverlayPageClipSeconds=' . ($result['mediaOverlays']['mo-chapter']['items'][1]['clipDurationSeconds'] ?? '') . "\n";
echo 'remoteOverlayAudio=' . ($result['mediaOverlays']['mo-chapter']['items'][2]['audioTarget'] ?? '') . "\n";
echo 'remoteOverlayClipSeconds=' . ($result['mediaOverlays']['mo-chapter']['items'][2]['clipDurationSeconds'] ?? '') . "\n";
echo 'mediaDuration=' . ($result['mediaDurations']['total']['duration'] ?? '') . "\n";
echo 'mediaOverlayDuration=' . ($result['mediaOverlays']['mo-chapter']['duration'] ?? '') . "\n";
echo 'manifestMediaOverlayPart=' . ($result['manifest'][1]['mediaOverlayReference']['part'] ?? '') . "\n";
echo 'manifestMediaOverlayItems=' . ($result['manifest'][1]['mediaOverlayReference']['itemCount'] ?? 0) . "\n";
echo 'spineMediaOverlayDuration=' . ($result['spine'][0]['mediaOverlayReference']['duration'] ?? '') . "\n";
echo 'spineMediaOverlayTextRefManifestId=' . ($result['spine'][0]['mediaOverlayReference']['textRefManifestId'] ?? '') . "\n";
echo 'spineMediaOverlayTextRefSha256=' . ($result['spine'][0]['mediaOverlayReference']['textRefByteSha256'] ?? '') . "\n";
echo 'remoteManifestResources=' . count($result['importReport']['manifest']['externalItems'] ?? []) . "\n";
echo 'manifestByteProvenanceHashed=' . ($result['importReport']['manifest']['byteProvenance']['hashedItemCount'] ?? 0) . "\n";
echo 'manifestChapterSha256=' . ($result['importReport']['manifest']['byteProvenance']['itemsById']['chapter']['byteSha256'] ?? '') . "\n";
echo 'manifestEncryptedHashFree=' . ((($result['importReport']['manifest']['byteProvenance']['itemsById']['font-main']['byteSha256'] ?? null) === null) ? 'yes' : 'no') . "\n";
echo 'manifestDuplicatePackageParts=' . implode(',', $result['importReport']['manifest']['duplicatePackageParts'] ?? []) . "\n";
echo 'manifestDuplicatePackageDiagnostics=' . ($result['importReport']['manifest']['diagnosticCount'] ?? 0) . "\n";
echo 'assets=' . count($result['assets']) . "\n";
echo 'assetFallbacks=' . ($result['importReport']['assets']['fallbackCount'] ?? 0) . "\n";
echo 'coverAttachment=' . ($result['importReport']['assets']['coverImage']['part'] ?? '') . "\n";
echo 'coverSha256=' . ($result['importReport']['assets']['coverImage']['byteSha256'] ?? '') . "\n";
echo 'coverCandidates=' . ($result['importReport']['assets']['coverImageCount'] ?? 0) . "\n";
echo 'coverDiagnostics=' . ($result['importReport']['assets']['coverImageDiagnosticCount'] ?? 0) . "\n";
echo 'legacyCoverAttachment=' . ($result['importReport']['assets']['coverImages'][1]['part'] ?? '') . "\n";
echo 'attachmentCandidates=' . ($result['importReport']['assets']['attachmentCandidateCount'] ?? 0) . "\n";
echo 'unmanifestedAssets=' . ($result['importReport']['assets']['unmanifestedCount'] ?? 0) . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
