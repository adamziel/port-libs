<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object 1/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>
  <manifest:file-entry manifest:full-path="Object 1/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/source-hero.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="review-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="review-iv"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:iteration-count="1024" manifest:salt="review-salt"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
  </manifest:file-entry>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="ImportHeading" style:family="paragraph" style:display-name="Import Heading" style:default-outline-level="1"/>
    <style:style style:name="StrongSource" style:family="text">
      <style:text-properties fo:font-weight="bold" fo:font-style="italic"/>
    </style:style>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="1"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:body>
    <office:text>
      <text:tracked-changes>
        <text:changed-region text:id="chg-add-source-note">
          <text:insertion>
            <office:change-info>
              <dc:creator>Migration Reviewer</dc:creator>
              <dc:date>2026-06-05T00:20:00Z</dc:date>
              <text:p>Inserted while reconciling source ODT revisions.</text:p>
            </office:change-info>
          </text:insertion>
        </text:changed-region>
        <text:changed-region text:id="chg-delete-draft-claim">
          <text:deletion>
            <office:change-info>
              <dc:creator>Migration Reviewer</dc:creator>
              <dc:date>2026-06-05T00:22:00Z</dc:date>
            </office:change-info>
            <text:p>removed draft claim</text:p>
          </text:deletion>
        </text:changed-region>
      </text:tracked-changes>
      <text:h text:outline-level="1" text:style-name="ImportHeading">ODT source packet</text:h>
      <text:p>Reviewer <text:span text:style-name="StrongSource">summary</text:span> keeps <text:change-start text:change-id="chg-add-source-note"/>tracked source note<text:change-end text:change-id="chg-add-source-note"/> and <text:change text:change-id="chg-delete-draft-claim"/>, <text:bookmark-start text:name="Review Anchor"/>review anchor<text:bookmark-end text:name="Review Anchor"/>, <text:bookmark-ref text:ref-name="Review Anchor" text:reference-format="text">internal reference</text:bookmark-ref>, <text:reference-mark-start text:name="Source Claim"/>source claim<text:reference-mark-end text:name="Source Claim"/> with <text:reference-ref text:ref-name="Source Claim" text:reference-format="text">source claim reference</text:reference-ref>, <text:a xlink:href="https://example.test/odt-source">source URL</text:a>, formula <draw:frame draw:name="Migration formula"><draw:object xlink:href="./Object 1"/></draw:frame>, and annotations<text:note text:id="ftn-review" text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>ODT footnote reviewer context.</text:p></text:note-body></text:note><office:annotation><dc:creator>Migration Desk</dc:creator><dc:date>2026-06-04T23:20:00Z</dc:date><text:p>Check imported captions before publishing.</text:p></office:annotation>.</text:p>
      <text:list text:style-name="ReviewSteps">
        <text:list-item><text:p>Match ODT media to WordPress attachments</text:p></text:list-item>
        <text:list-item><text:p>Review table spans</text:p></text:list-item>
      </text:list>
      <draw:frame draw:name="Source hero">
        <draw:image xlink:href="Pictures/source-hero.png">
          <svg:title>Source hero</svg:title>
          <svg:desc>ODT source hero alt</svg:desc>
        </draw:image>
      </draw:frame>
      <table:table table:name="Review">
        <table:table-row>
          <table:table-cell><text:p>Item</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell table:number-columns-spanned="2"><text:p>Ready for block import review</text:p></table:table-cell>
          <table:covered-table-cell/>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <dc:title>WordPress ODT source packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <dc:language>en</dc:language>
    <meta:keyword>odt</meta:keyword>
    <meta:document-statistic meta:page-count="1" meta:word-count="64" meta:image-count="1"/>
  </office:meta>
</office:document-meta>
XML;

$mathObjectXml = <<<'XML'
<office:document
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:body>
    <office:math>
      <math xmlns="http://www.w3.org/1998/Math/MathML" display="block">
        <semantics>
          <mrow><msub><mi>p</mi><mi>i</mi></msub><mo>→</mo><msub><mi>m</mi><mi>i</mi></msub></mrow>
          <annotation encoding="application/x-tex">p_i \to m_i</annotation>
        </semantics>
      </math>
    </office:math>
  </office:body>
</office:document>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'Object 1/content.xml', 'data' => $mathObjectXml],
    ['name' => 'Pictures/source-hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
]);

$reader = new OdfReader();
$result = $reader->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (($argv[1] ?? '') === '--self-test') {
    if ($result['metadata']['title'] !== 'WordPress ODT source packet') {
        throw new RuntimeException('Expected ODT title metadata');
    }
    if (($result['media'][0]['part'] ?? '') !== 'Pictures/source-hero.png') {
        throw new RuntimeException('Expected ODT image manifest media to be reported');
    }
    if (($result['media'][0]['canExposeBytes'] ?? true) !== false) {
        throw new RuntimeException('Expected encrypted ODT media bytes to stay unavailable for import');
    }
    if (($result['importReport']['encryption']['encryptedParts'][0] ?? '') !== 'Pictures/source-hero.png') {
        throw new RuntimeException('Expected ODT encrypted media to be listed in the import report');
    }
    if (!str_contains($blocks, '<h1>ODT source packet</h1>')) {
        throw new RuntimeException('Expected ODT heading to render as a WordPress heading block');
    }
    if (!str_contains($blocks, '<a href="https://example.test/odt-source">source URL</a>')) {
        throw new RuntimeException('Expected ODT source link to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span id="review-anchor" class="anchor odf-bookmark" data-odf-bookmark-name="Review Anchor"></span>')) {
        throw new RuntimeException('Expected ODT bookmark anchor to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<a href="#review-anchor" class="odf-bookmark-ref" data-odf-ref-name="Review Anchor" data-odf-reference-format="text">internal reference</a>')) {
        throw new RuntimeException('Expected ODT bookmark reference to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span id="source-claim" class="anchor odf-reference-mark" data-odf-reference-name="Source Claim"></span>source claim')) {
        throw new RuntimeException('Expected ODT reference mark to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<a href="#source-claim" class="odf-reference-ref" data-odf-ref-name="Source Claim" data-odf-reference-format="text">source claim reference</a>')) {
        throw new RuntimeException('Expected ODT reference-ref to render in WordPress blocks');
    }
    if (($result['importReport']['content']['referenceMarkCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT reference marks to be counted in the import report');
    }
    if (($result['importReport']['content']['referenceReferenceCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT reference refs to be counted in the import report');
    }
    if (($result['importReport']['content']['noteCount'] ?? 0) < 2) {
        throw new RuntimeException('Expected ODT footnote and annotation notes to be reported');
    }
    if (($result['importReport']['trackedChanges']['count'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT tracked changes to be reported');
    }
    if (($result['importReport']['content']['mathCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT MathML object to be reported');
    }
    if (!str_contains($blocks, '<span class="math display"><math xmlns="http://www.w3.org/1998/Math/MathML" display="block">')) {
        throw new RuntimeException('Expected ODT MathML object to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<annotation encoding="application/x-tex">p_i \to m_i</annotation>')) {
        throw new RuntimeException('Expected ODT MathML source annotation to survive WordPress handoff');
    }
    if (!str_contains($blocks, '<span class="odf-change odf-insertion" data-odf-change-id="chg-add-source-note" data-odf-change-type="insertion" data-odf-change-creator="Migration Reviewer" data-odf-change-date="2026-06-05T00:20:00Z">tracked source note</span>')) {
        throw new RuntimeException('Expected ODT insertion tracked change to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-change odf-deletion" data-odf-change-id="chg-delete-draft-claim" data-odf-change-type="deletion" data-odf-change-creator="Migration Reviewer" data-odf-change-date="2026-06-05T00:22:00Z">removed draft claim</span>')) {
        throw new RuntimeException('Expected ODT deletion tracked change to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<section class="footnotes" role="doc-endnotes">')) {
        throw new RuntimeException('Expected ODT annotation to render as a review footnote');
    }
    if (!str_contains($blocks, 'ODT footnote reviewer context.')) {
        throw new RuntimeException('Expected ODT footnote body to render in WordPress footnotes');
    }
    if (!str_contains($blocks, '<td colspan="2"><p>Ready for block import review</p></td>')) {
        throw new RuntimeException('Expected ODT table colspan to survive WordPress table handoff');
    }

    echo "odf open document handoff self-test ok\n";
    exit(0);
}

echo "ODF OpenDocument handoff for WordPress import:\n";
echo 'title=' . ($result['metadata']['title'] ?? '') . "\n";
echo 'creator=' . ($result['metadata']['creator'] ?? '') . "\n";
echo 'manifestItems=' . count($result['manifest']) . "\n";
echo 'mediaItems=' . count($result['media']) . "\n";
echo 'styleCount=' . count($result['styles']) . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
