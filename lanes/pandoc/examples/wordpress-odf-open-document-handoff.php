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
  <manifest:file-entry manifest:full-path="Object%201/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>
  <manifest:file-entry manifest:full-path="Object%201/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/source%20hero.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="review-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="review-iv"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:iteration-count="1024" manifest:salt="review-salt"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="Object%202/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/>
  <manifest:file-entry manifest:full-path="Object%202/oleObject.bin" manifest:media-type="application/vnd.openxmlformats-officedocument.oleObject" manifest:size="10"/>
  <manifest:file-entry manifest:full-path="Object%203/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
  <manifest:file-entry manifest:full-path="Object%203/content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:automatic-styles>
    <style:page-layout style:name="pmReview" style:page-usage="all">
      <style:page-layout-properties fo:page-width="8.5in" fo:page-height="11in" fo:margin-top="1in" fo:margin-bottom="1in" fo:margin-left="0.75in" fo:margin-right="0.75in" style:print-orientation="portrait" style:writing-mode="lr-tb"/>
    </style:page-layout>
  </office:automatic-styles>
  <office:styles>
    <style:style style:name="ImportHeading" style:family="paragraph" style:display-name="Import Heading" style:default-outline-level="1" style:master-page-name="ReviewPage"/>
    <style:style style:name="StrongSource" style:family="text">
      <style:text-properties fo:font-weight="bold" fo:font-style="italic"/>
    </style:style>
    <style:style style:name="SourceSuperscript" style:family="text">
      <style:text-properties style:text-position="super 58%"/>
    </style:style>
    <style:style style:name="SourceSubscript" style:family="text">
      <style:text-properties style:text-position="sub 58%"/>
    </style:style>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="1"/>
      <text:list-level-style-number text:level="2" style:num-format="a" text:start-value="4"/>
    </text:list-style>
  </office:styles>
  <office:master-styles>
    <style:master-page style:name="ReviewPage" style:display-name="Review Page" style:page-layout-name="pmReview">
      <style:header><text:p>WordPress import review packet</text:p></style:header>
      <style:footer><text:p>Source page <text:page-number>1</text:page-number></text:p></style:footer>
    </style:master-page>
  </office:master-styles>
</office:document-styles>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0"
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
      <office:forms>
        <form:form form:name="Import Review">
          <form:checkbox form:id="ctrl-review-approval" form:name="ReviewApproval" form:label="Review approved" form:current-state="checked"/>
        </form:form>
      </office:forms>
      <text:sequence-decls>
        <text:sequence-decl text:name="Illustration" text:display-outline-level="0" text:separation-character="."/>
      </text:sequence-decls>
      <text:variable-decls>
        <text:variable-decl text:name="ReviewStatus" office:value-type="string"/>
      </text:variable-decls>
      <text:user-field-decls>
        <text:user-field-decl text:name="Reviewer" office:value-type="string" office:string-value="Migration Desk"/>
        <text:user-field-decl text:name="SourcePackage" office:value-type="string" office:string-value="package-42"/>
      </text:user-field-decls>
      <text:h text:outline-level="1" text:style-name="ImportHeading">ODT source packet</text:h>
      <text:section text:name="Linked Policy Appendix" text:protected="true" text:protection-key="review-key" text:protection-key-digest-algorithm="http://www.w3.org/2000/09/xmldsig#sha1">
        <text:section-source xlink:href="Sections/policy-appendix.odt" xlink:type="simple" text:section-name="Policy Appendix" text:filter-name="writer8"/>
        <text:p>Linked appendix fallback text.</text:p>
      </text:section>
      <text:p>Reviewer <text:span text:style-name="StrongSource">summary</text:span> keeps source mark<text:span text:style-name="SourceSuperscript">TM</text:span> and H<text:span text:style-name="SourceSubscript">2</text:span>O, <office:annotation office:name="ann-source-range"><dc:creator>Migration Reviewer</dc:creator><dc:date>2026-06-05T05:58:00Z</dc:date><text:p>Range comment for the annotated source claim.</text:p></office:annotation>annotated source claim<office:annotation-end office:name="ann-source-range"/>, <text:change-start text:change-id="chg-add-source-note"/>tracked source note<text:change-end text:change-id="chg-add-source-note"/> and <text:change text:change-id="chg-delete-draft-claim"/>, <text:bookmark-start text:name="Review Anchor"/>review anchor<text:bookmark-end text:name="Review Anchor"/>, <text:bookmark-ref text:ref-name="Review Anchor" text:reference-format="text">internal reference</text:bookmark-ref>, <text:reference-mark-start text:name="Source Claim"/>source claim<text:reference-mark-end text:name="Source Claim"/> with <text:reference-ref text:ref-name="Source Claim" text:reference-format="text">source claim reference</text:reference-ref>, caption <text:sequence text:name="Illustration" text:formula="ooow:Illustration+1" text:ref-name="source-hero-seq">Figure 1</text:sequence>, review field <text:variable-set text:name="ReviewStatus" office:value-type="string" office:string-value="Ready">Ready</text:variable-set> by <text:user-field-get text:name="Reviewer">Migration Desk</text:user-field-get> from source package <text:user-field-get text:name="SourcePackage"/> on page <text:page-number text:select-page="current">2</text:page-number>, approval <draw:control draw:control="ctrl-review-approval"/>, <text:a xlink:href="https://example.test/odt-source" xlink:type="simple" xlink:show="new" xlink:actuate="onRequest" office:name="Source Packet Link" office:title="ODT source package" office:target-frame-name="_blank" text:style-name="SourceLink" text:visited-style-name="VisitedSourceLink">source URL</text:a>, page boundary <text:soft-page-break/>after source page boundary, citation <text:bibliography-mark text:identifier="source-review" text:number="2">source review packet</text:bibliography-mark>, formula <draw:frame draw:name="Migration formula"><draw:object xlink:href="./Object%201"/></draw:frame>, spreadsheet <draw:frame draw:name="Source spreadsheet"><draw:object-ole xlink:href="./Object%202"/></draw:frame>, chart <draw:frame draw:name="Source chart"><svg:desc>Source chart placeholder</svg:desc><draw:object xlink:href="./Object%203"/></draw:frame>, and annotations<text:note text:id="ftn-review" text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>ODT footnote reviewer context.</text:p></text:note-body></text:note><office:annotation><dc:creator>Migration Desk</dc:creator><dc:date>2026-06-04T23:20:00Z</dc:date><text:p>Check imported captions before publishing.</text:p></office:annotation>.</text:p>
      <text:list text:style-name="ReviewSteps">
        <text:list-header><text:p>Review packet checklist</text:p></text:list-header>
        <text:list-item>
          <text:p>Match ODT media to WordPress attachments</text:p>
          <text:list>
            <text:list-item><text:p>Check inherited nested checklist style</text:p></text:list-item>
          </text:list>
        </text:list-item>
        <text:list-item><text:p>Review table spans</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="ReviewSteps" text:continue-numbering="true">
        <text:list-item><text:p>Publish continued review checklist</text:p></text:list-item>
      </text:list>
      <draw:frame draw:name="Source hero" svg:width="6cm" svg:height="3.5cm">
        <draw:image xlink:href="Pictures/source%20hero.png">
          <svg:title>Source hero</svg:title>
          <svg:desc>ODT source hero alt</svg:desc>
        </draw:image>
      </draw:frame>
      <table:table table:name="Review" table:style-name="ReviewTable" table:protected="true" table:protection-key="opaque-review-key" table:protection-key-digest-algorithm="urn:odf:sha1">
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

$chartObjectXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0">
  <office:body>
    <office:chart>
      <chart:chart chart:class="chart:bar"/>
    </office:chart>
  </office:body>
</office:document-content>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'Object 1/content.xml', 'data' => $mathObjectXml],
    ['name' => 'Object 2/oleObject.bin', 'data' => 'OLEPAYLOAD'],
    ['name' => 'Object 3/content.xml', 'data' => $chartObjectXml],
    ['name' => 'Pictures/source hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
]);

$reader = new OdfReader();
$result = $reader->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (($argv[1] ?? '') === '--self-test') {
    if ($result['metadata']['title'] !== 'WordPress ODT source packet') {
        throw new RuntimeException('Expected ODT title metadata');
    }
    if (($result['media'][0]['part'] ?? '') !== 'Pictures/source hero.png') {
        throw new RuntimeException('Expected ODT image manifest media to be reported');
    }
    if (($result['media'][0]['canExposeBytes'] ?? true) !== false) {
        throw new RuntimeException('Expected encrypted ODT media bytes to stay unavailable for import');
    }
    $imageNode = null;
    foreach ($result['document']->children as $block) {
        foreach ($block->children as $child) {
            if ($child instanceof \PortLibs\Pandoc\AstNode && $child->type === 'image' && $child->attr('sourcePart') === 'Pictures/source hero.png') {
                $imageNode = $child;
                break 2;
            }
        }
    }
    if (!$imageNode instanceof \PortLibs\Pandoc\AstNode || $imageNode->attr('width') !== '6cm' || $imageNode->attr('height') !== '3.5cm') {
        throw new RuntimeException('Expected ODT frame image dimensions to survive AST handoff');
    }
    if (($result['importReport']['encryption']['encryptedParts'][0] ?? '') !== 'Pictures/source hero.png') {
        throw new RuntimeException('Expected ODT encrypted media to be listed in the import report');
    }
    if (!str_contains($blocks, '<h1>ODT source packet</h1>')) {
        throw new RuntimeException('Expected ODT heading to render as a WordPress heading block');
    }
    if (!str_contains($blocks, '<sup><span data-odf-style-name="SourceSuperscript">TM</span></sup>')) {
        throw new RuntimeException('Expected ODT superscript source mark to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<sub><span data-odf-style-name="SourceSubscript">2</span></sub>')) {
        throw new RuntimeException('Expected ODT subscript formula cue to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<a href="https://example.test/odt-source" title="ODT source package" class="odf-link" data-odf-link-name="Source Packet Link" data-odf-link-style-name="SourceLink" data-odf-link-visited-style-name="VisitedSourceLink" data-odf-link-target-frame-name="_blank" data-odf-link-type="simple" data-odf-link-show="new" data-odf-link-actuate="onRequest">source URL</a>')) {
        throw new RuntimeException('Expected ODT source link metadata to render in WordPress blocks');
    }
    if (($result['importReport']['content']['linkedSectionCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT linked section to be counted in the import report');
    }
    if (($result['importReport']['content']['protectedSectionCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT protected section to be counted in the import report');
    }
    if (($result['importReport']['styles']['pageLayoutCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT page layout metadata to be counted in the import report');
    }
    if (($result['importReport']['styles']['masterPageCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT master page metadata to be counted in the import report');
    }
    if (($result['pageLayouts']['pmReview']['properties']['pageWidth'] ?? '') !== '8.5in') {
        throw new RuntimeException('Expected ODT page width to survive style parsing');
    }
    if (($result['masterPages']['ReviewPage']['headerText'][0] ?? '') !== 'WordPress import review packet') {
        throw new RuntimeException('Expected ODT master-page header text to survive style parsing');
    }
    if (($result['styles']['ImportHeading']['masterPageName'] ?? '') !== 'ReviewPage') {
        throw new RuntimeException('Expected ODT paragraph style to retain its master-page link');
    }
    if (!str_contains($blocks, '<div id="linked-policy-appendix" class="odf-section odf-linked-section odf-protected-section" data-odf-section-name="Linked Policy Appendix"')) {
        throw new RuntimeException('Expected ODT linked section metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-section-source-href="Sections/policy-appendix.odt"')) {
        throw new RuntimeException('Expected ODT linked section source href to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-section-protection-key-present="true"')) {
        throw new RuntimeException('Expected ODT protected section key-presence metadata to render in WordPress blocks');
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
    if (($result['importReport']['content']['annotationRangeCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT annotation range to be counted in the import report');
    }
    if (!str_contains($blocks, '<span class="odf-annotation-range" data-odf-annotation-name="ann-source-range" data-odf-annotation-author="Migration Reviewer" data-odf-annotation-date="2026-06-05T05:58:00Z">annotated source claim<sup id="fnref-1">')) {
        throw new RuntimeException('Expected ODT annotation range to render around annotated source text');
    }
    if (!str_contains($blocks, 'Range comment for the annotated source claim.')) {
        throw new RuntimeException('Expected ODT annotation range comment to render in WordPress footnotes');
    }
    if (($result['importReport']['content']['sequenceCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT sequence fields to be counted in the import report');
    }
    if (!str_contains($blocks, '<span class="odf-sequence" data-odf-sequence-name="Illustration" data-odf-sequence-formula="ooow:Illustration+1" data-odf-sequence-ref-name="source-hero-seq">Figure 1</span>')) {
        throw new RuntimeException('Expected ODT sequence field to render in WordPress blocks');
    }
    if (($result['importReport']['content']['fieldCount'] ?? 0) !== 4) {
        throw new RuntimeException('Expected ODT variable and user fields to be counted in the import report');
    }
    if (($result['importReport']['contentDeclarations']['sequenceDeclarationCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT sequence declarations to be counted in the import report');
    }
    if (($result['importReport']['contentDeclarations']['variableDeclarationCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT variable declarations to be counted in the import report');
    }
    if (($result['importReport']['contentDeclarations']['userFieldDeclarationCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT user-field declarations to be counted in the import report');
    }
    if (($result['contentDeclarations']['userFieldDeclarations']['SourcePackage']['stringValue'] ?? '') !== 'package-42') {
        throw new RuntimeException('Expected ODT source package user-field declaration to survive import metadata');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-variable-set" data-odf-field-type="variable-set" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready">Ready</span>')) {
        throw new RuntimeException('Expected ODT variable field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-user-field-get" data-odf-field-type="user-field-get" data-odf-field-name="SourcePackage" data-odf-field-value-type="string" data-odf-field-string-value="package-42" data-odf-field-declared="true">package-42</span>')) {
        throw new RuntimeException('Expected ODT user-field declaration fallback to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-page-number" data-odf-field-type="page-number" data-odf-field-select-page="current">2</span>')) {
        throw new RuntimeException('Expected ODT page-number field to render in WordPress blocks');
    }
    if (($result['importReport']['content']['formControlCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT form controls to be counted in the import report');
    }
    if (($result['importReport']['content']['missingFormControlCount'] ?? 0) !== 0) {
        throw new RuntimeException('Expected ODT form controls to resolve from office:forms');
    }
    if (!str_contains($blocks, '<span class="odf-form-control odf-control-checkbox" data-odf-control-id="ctrl-review-approval" data-odf-control-type="checkbox" data-odf-control-exists="true" data-odf-control-form-name="Import Review" data-odf-control-name="ReviewApproval" data-odf-control-label="Review approved" data-odf-control-current-state="checked">Review approved</span>')) {
        throw new RuntimeException('Expected ODT form control placeholder to render in WordPress blocks');
    }
    if (($result['importReport']['content']['softPageBreakCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT soft page break to be counted in the import report');
    }
    if (!str_contains($blocks, 'page boundary <span class="odf-soft-page-break" data-odf-soft-page-break="true"></span>after source page boundary')) {
        throw new RuntimeException('Expected ODT soft page break marker to render in WordPress blocks');
    }
    if (($result['importReport']['content']['citationCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT bibliography mark citations to be counted in the import report');
    }
    if (!str_contains($blocks, 'citation [@source-review], formula')) {
        throw new RuntimeException('Expected ODT bibliography mark citation source to render in WordPress blocks');
    }
    if (($result['importReport']['content']['continuedListCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT continued list to be counted in the import report');
    }
    if (($result['importReport']['content']['listHeaderCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT list headers to be counted in the import report');
    }
    if (!str_contains($blocks, '<div class="odf-list-header" data-odf-list-header="true" data-odf-list-level="1"><p>Review packet checklist</p></div>')) {
        throw new RuntimeException('Expected ODT list header to render as unnumbered WordPress review content');
    }
    if (!str_contains($blocks, '<ol start="3"><li>Publish continued review checklist</li></ol>')) {
        throw new RuntimeException('Expected ODT continued list numbering to survive WordPress blocks');
    }
    if (!str_contains($blocks, '<ol start="4" type="a"><li>Check inherited nested checklist style</li></ol>')) {
        throw new RuntimeException('Expected ODT nested lists without explicit style names to inherit parent list style');
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
    if (($result['importReport']['content']['embeddedObjectCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT embedded object to be counted in the import report');
    }
    if (($result['importReport']['content']['missingEmbeddedObjectCount'] ?? 0) !== 0) {
        throw new RuntimeException('Expected ODT embedded object package parts to be present');
    }
    if (!str_contains($blocks, '<span class="odf-embedded-object odf-object-ole" data-odf-object-type="ole" data-odf-object-href="./Object%202" data-odf-object-path="Object 2" data-odf-object-source-part="Object 2/" data-odf-object-media-type="application/vnd.oasis.opendocument.spreadsheet" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="10" data-odf-object-can-expose-bytes="false">Source spreadsheet</span>')) {
        throw new RuntimeException('Expected ODT object-ole frame to render as a WordPress review placeholder');
    }
    if (!str_contains($blocks, '<span class="odf-embedded-object odf-object-chart" data-odf-object-type="chart" data-odf-object-href="./Object%203" data-odf-object-path="Object 3" data-odf-object-source-part="Object 3/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="' . strlen($chartObjectXml) . '" data-odf-object-can-expose-bytes="false">Source chart placeholder</span>')) {
        throw new RuntimeException('Expected ODT chart object frame to render as a WordPress review placeholder');
    }
    if (str_contains($blocks, 'OLEPAYLOAD')) {
        throw new RuntimeException('Expected ODT object-ole payload bytes to stay out of WordPress output');
    }
    if (str_contains($blocks, 'chart:bar')) {
        throw new RuntimeException('Expected ODT chart object XML to stay out of WordPress output');
    }
    if (!str_contains($blocks, '<img src="Pictures/source%20hero.png" alt="ODT source hero alt" title="Source hero" width="6cm" height="3.5cm"/>')) {
        throw new RuntimeException('Expected ODT image dimensions to render in WordPress blocks');
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
    $reviewTable = null;
    foreach ($result['document']->children as $block) {
        if ($block instanceof \PortLibs\Pandoc\AstNode && $block->type === 'table') {
            $reviewTable = $block;
            break;
        }
    }
    if (!$reviewTable instanceof \PortLibs\Pandoc\AstNode || ($reviewTable->attr('tableGeometry')['caption'] ?? '') !== 'Review') {
        throw new RuntimeException('Expected ODT table name to survive table geometry review packets');
    }
    if (!str_contains($blocks, '<table data-odf-table-name="Review" data-odf-table-style-name="ReviewTable" data-odf-table-protected="true" data-odf-table-protection-key-present="true" data-odf-table-protection-key-digest-algorithm="urn:odf:sha1">')) {
        throw new RuntimeException('Expected ODT named protected table metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">Review</figcaption>')) {
        throw new RuntimeException('Expected ODT table name to render as the review table caption');
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
echo 'pageLayoutCount=' . count($result['pageLayouts']) . "\n";
echo 'masterPageCount=' . count($result['masterPages']) . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
