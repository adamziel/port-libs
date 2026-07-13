<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:preferred-view-mode="edit"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="manifest.rdf" manifest:media-type="application/rdf+xml"/>
  <manifest:file-entry manifest:full-path="Object%201/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>
  <manifest:file-entry manifest:full-path="Object%201/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/source%20hero.png" manifest:media-type="image/png" manifest:size="2048" manifest:preferred-view-mode="presentation-slide-show">
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
  <manifest:file-entry manifest:full-path="Pictures/review-bullet.svg" manifest:media-type="image/svg+xml"/>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:font-face-decls>
    <style:font-face style:name="SourceMono" svg:font-family="'Source Code Pro'" style:font-family-generic="modern" style:font-pitch="fixed"/>
  </office:font-face-decls>
  <office:automatic-styles>
    <style:page-layout style:name="pmReview" style:page-usage="all">
      <style:page-layout-properties fo:page-width="8.5in" fo:page-height="11in" fo:margin-top="1in" fo:margin-bottom="1in" fo:margin-left="0.75in" fo:margin-right="0.75in" style:print-orientation="portrait" style:writing-mode="lr-tb"/>
    </style:page-layout>
  </office:automatic-styles>
  <office:styles>
    <style:style style:name="ImportHeading" style:family="paragraph" style:display-name="Import Heading" style:default-outline-level="1" style:master-page-name="ReviewPage"/>
    <style:style style:name="ReviewQuote" style:family="paragraph" style:display-name="Review Quote">
      <style:paragraph-properties fo:margin-left="6mm"/>
    </style:style>
    <style:style style:name="StyledSummary" style:family="paragraph" style:display-name="Styled Summary">
      <style:text-properties fo:font-weight="bold"/>
    </style:style>
    <style:style style:name="Preformatted_20_Text" style:family="paragraph" style:display-name="Preformatted Text"/>
    <style:style style:name="SourceCode" style:family="paragraph" style:parent-style-name="Preformatted_20_Text" style:display-name="Source Code"/>
    <style:style style:name="Table" style:family="paragraph" style:display-name="Table"/>
    <number:currency-style style:name="ReviewCurrencyFormat" style:display-name="Review Currency" number:language="en" number:country="US">
      <number:currency-symbol number:language="en" number:country="US">$</number:currency-symbol>
      <number:number number:decimal-places="2" number:min-integer-digits="1" number:grouping="true"/>
      <number:text> reviewed</number:text>
    </number:currency-style>
    <style:style style:name="BaseProtectedCell" style:family="table-cell">
      <style:table-cell-properties style:cell-protect="protected" style:print-content="false"/>
    </style:style>
    <style:style style:name="ReviewStatusCell" style:family="table-cell" style:parent-style-name="BaseProtectedCell" style:data-style-name="ReviewCurrencyFormat">
      <style:table-cell-properties fo:background-color="#fff4cc" fo:border="0.5pt solid #999999" fo:padding-left="3pt" style:vertical-align="middle" style:writing-mode="tb-rl" style:repeat-content="false" style:shrink-to-fit="true"/>
      <style:map style:condition="cell-content()=&quot;Status&quot;" style:apply-style-name="ReadyCell" style:base-cell-address="Review.B1"/>
    </style:style>
    <style:style style:name="ReviewDefaultCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#e6ffed" style:vertical-align="top"/>
    </style:style>
    <style:style style:name="CoveredAuditCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#fff4cc" style:cell-protect="protected"/>
    </style:style>
    <style:style style:name="ReadyCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#e6ffed"/>
    </style:style>
    <style:style style:name="StrongSource" style:family="text">
      <style:text-properties fo:font-weight="bold" fo:font-style="italic"/>
    </style:style>
    <style:style style:name="ReviewerField" style:family="text" style:display-name="Reviewer Field"/>
    <style:style style:name="Source_Text" style:family="text" style:display-name="Source Text"/>
    <style:style style:name="FixedPitchMetadata" style:family="text" style:display-name="Fixed Pitch Metadata">
      <style:text-properties style:font-name="SourceMono"/>
    </style:style>
    <style:style style:name="SourceSuperscript" style:family="text">
      <style:text-properties style:text-position="super 58%"/>
    </style:style>
    <style:style style:name="SourceSubscript" style:family="text">
      <style:text-properties style:text-position="sub 58%"/>
    </style:style>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" style:num-prefix="(" style:num-suffix=")" text:start-value="1"/>
      <text:list-level-style-number text:level="2" style:num-format="a" style:num-suffix=")" text:start-value="4"/>
    </text:list-style>
    <text:list-style style:name="GraphicReviewBullets">
      <text:list-level-style-image text:level="1" xlink:href="Pictures/review-bullet.svg" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad" xlink:title="Review badge" svg:width="0.18in" svg:height="0.18in">
        <style:list-level-properties text:min-label-width="0.28in" text:list-level-position-and-space-mode="label-alignment">
          <style:list-level-label-alignment text:label-followed-by="listtab" text:list-tab-stop-position="0.35in" fo:text-indent="-0.2in" fo:margin-left="0.45in"/>
        </style:list-level-properties>
        <style:text-properties fo:font-style="italic" style:font-name="SourceMono"/>
      </text:list-level-style-image>
    </text:list-style>
    <table:table-template
      table:name="ReviewTemplate"
      table:first-row-start-column="ReviewHeaderStart"
      table:first-row-end-column="ReviewHeaderEnd"
      table:first-column="ReviewFirstColumn"
      table:last-column="ReviewLastColumn"
      table:first-row="ReviewHeaderRow"
      table:last-row="ReviewSummaryRow"
      table:body="ReviewBody"
      table:odd-rows="ReviewOddRow"
      table:even-rows="ReviewEvenRow"/>
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
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:xhtml="http://www.w3.org/1999/xhtml"
  xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <office:body>
    <office:text>
      <draw:layer-set>
        <draw:layer draw:name="review-media" draw:display="screen" draw:protected="true"/>
        <draw:layer draw:name="draft-notes" draw:display="none"/>
      </draw:layer-set>
      <text:notes-configuration
        text:note-class="footnote"
        text:citation-style-name="Footnote_20_Symbol"
        text:citation-body-style-name="Footnote_20_anchor"
        text:default-style-name="Footnote"
        text:start-value="1"
        style:num-format="1"
        text:footnotes-position="page"
        text:start-numbering-at="document">
        <style:footnote-sep
          style:width="0.018cm"
          style:distance-before-sep="0.08cm"
          style:distance-after-sep="0.10cm"
          style:line-style="solid"
          style:adjustment="left"
          style:rel-width="25%"
          style:color="#808080"/>
      </text:notes-configuration>
      <text:notes-configuration
        text:note-class="endnote"
        text:citation-style-name="Endnote_20_Symbol"
        text:citation-body-style-name="Endnote_20_anchor"
        text:default-style-name="Endnote"
        text:master-page-name="Endnotes"
        text:start-value="1"
        style:num-format="i"/>
      <text:linenumbering-configuration
        text:number-lines="true"
        text:style-name="ReviewLineNumber"
        text:offset="0.5cm"
        text:number-position="left"
        text:increment="5"
        text:count-empty-lines="true"
        text:count-in-text-boxes="false"
        text:restart-on-page="true"
        style:num-format="1"
        style:num-letter-sync="true">
        <text:linenumbering-separator text:increment="3">|</text:linenumbering-separator>
      </text:linenumbering-configuration>
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
      <table:tracked-changes>
        <table:tracked-change table:id="tbl-review-status" table:acceptance-state="accepted">
          <office:change-info>
            <dc:creator>Migration Reviewer</dc:creator>
            <dc:date>2026-06-08T18:20:00Z</dc:date>
            <text:p>Updated table status during source review.</text:p>
          </office:change-info>
          <table:cell-content-change table:cell-address="Review.B2" office:value-type="string" office:string-value="Ready">
            <table:previous table:cell-address="Review.B2" office:value-type="string" office:string-value="Draft"><text:p>Draft</text:p></table:previous>
          </table:cell-content-change>
        </table:tracked-change>
        <table:tracked-change table:id="tbl-delete-stale-row" table:acceptance-state="pending" table:rejecting-change-id="tbl-review-status">
          <office:change-info>
            <dc:creator>Migration Reviewer</dc:creator>
            <dc:date>2026-06-08T18:22:00Z</dc:date>
          </office:change-info>
          <table:deletion table:type="row" table:position="Review.4" table:table="Review"/>
        </table:tracked-change>
      </table:tracked-changes>
      <office:forms>
        <form:form form:name="Import Review" xlink:href="https://example.test/import-review" xlink:type="simple" form:method="post" form:target-frame="_blank" form:command-type="table" form:command="import_review_packets" form:datasource="wp_import_queue" form:apply-filter="true">
          <form:checkbox form:id="ctrl-review-approval" form:name="ReviewApproval" form:label="Review approved" form:current-state="checked"/>
          <form:combobox form:id="ctrl-review-disposition" form:name="ReviewDisposition" form:label="Review disposition" form:dropdown="true" form:automatic-completion="true">
            <form:option form:label="Draft" form:value="draft"/>
            <form:option form:label="Ready to publish" form:value="ready" form:current-selected="true"/>
            <form:option form:label="Needs legal review" form:value="legal"/>
          </form:combobox>
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
      <text:h text:outline-level="1" text:style-name="ImportHeading"><text:bookmark-start text:name="ODT source packet"/>ODT source packet<text:bookmark-end text:name="ODT source packet"/></text:h>
      <text:p text:style-name="ImportHeading" xml:id="source-overview-id">Source overview heading</text:p>
      <text:table-of-content text:name="Source Navigation" text:style-name="Contents_20_1" text:protected="true" text:protection-key="toc-review-key" text:protection-key-digest-algorithm="urn:odf:sha1">
        <text:table-of-content-source text:outline-level="2" text:relative-tab-stop-position="true" text:use-index-source-styles="true">
          <text:index-source-styles text:outline-level="1">
            <text:index-source-style text:style-name="ImportHeading"/>
          </text:index-source-styles>
        </text:table-of-content-source>
        <text:index-title text:name="Table of Contents">
          <text:p>Contents</text:p>
        </text:index-title>
        <text:index-body>
          <text:p><text:a xlink:href="#odt-source-packet">ODT source packet</text:a><text:tab/>1</text:p>
          <text:p><text:a xlink:href="#review">Review table</text:a><text:tab/>2</text:p>
        </text:index-body>
      </text:table-of-content>
      <text:illustration-index text:name="Figure Review" text:style-name="IllustrationIndex" text:protected="true" text:protection-key="figure-review-key" text:protection-key-digest-algorithm="urn:odf:sha256">
        <text:illustration-index-source text:caption-sequence-name="Illustration" text:use-caption="true">
          <text:index-title-template text:style-name="FigureTitle">Figures</text:index-title-template>
          <text:illustration-index-entry-template text:style-name="FigureEntry">
            <text:index-entry-link-start xlink:href="#source-hero-seq" xlink:type="simple" xlink:show="replace" xlink:actuate="onRequest"/>
            <text:index-entry-chapter text:style-name="FigureChapter" text:outline-level="1" text:display="number" text:chapter-format="number"/>
            <text:index-entry-text/>
            <text:index-entry-tab-stop style:type="right" style:position="16cm" style:leader-char="."/>
            <text:index-entry-page-number/>
            <text:index-entry-link-end/>
          </text:illustration-index-entry-template>
        </text:illustration-index-source>
        <text:index-title text:name="Illustrations">
          <text:p>Figures</text:p>
        </text:index-title>
        <text:index-body>
          <text:p><text:a xlink:href="#source-hero-seq">Figure 1</text:a><text:tab/>2</text:p>
        </text:index-body>
      </text:illustration-index>
      <text:p text:style-name="Table">Table 1: Review matrix caption</text:p>
      <text:p text:style-name="StyledSummary">Styled source summary keeps <text:a xlink:href="https://example.test/styled-source">review link</text:a> prominent.</text:p>
      <text:section text:name="Linked Policy Appendix" text:protected="true" text:protection-key="review-key" text:protection-key-digest-algorithm="http://www.w3.org/2000/09/xmldsig#sha1">
        <text:section-source xlink:href="Sections/policy-appendix.odt" xlink:type="simple" text:section-name="Policy Appendix" text:filter-name="writer8"/>
        <text:p>Linked appendix fallback text.</text:p>
      </text:section>
      <text:p>Reviewer <text:span text:style-name="StrongSource">summary</text:span> keeps localized <text:ruby><text:ruby-base>漢字</text:ruby-base><text:ruby-text>kanji</text:ruby-text></text:ruby> source mark<text:span text:style-name="SourceSuperscript">TM</text:span> and H<text:span text:style-name="SourceSubscript">2</text:span>O, import helper <text:span text:style-name="Source_Text">wp_insert_post</text:span> and fixed-pitch marker <text:span text:style-name="FixedPitchMetadata">review_code</text:span>, <office:annotation office:name="ann-source-range"><dc:creator>Migration Reviewer</dc:creator><dc:date>2026-06-05T05:58:00Z</dc:date><text:p>Range comment for the annotated source claim.</text:p></office:annotation>annotated source claim<office:annotation-end office:name="ann-source-range"/>, <text:change-start text:change-id="chg-add-source-note"/>tracked source note<text:change-end text:change-id="chg-add-source-note"/> and <text:change text:change-id="chg-delete-draft-claim"/>, <text:bookmark-start text:name="Review Anchor"/>review anchor<text:bookmark-end text:name="Review Anchor"/>, <text:bookmark-ref text:ref-name="Review Anchor" text:reference-format="text">internal reference</text:bookmark-ref>, <text:reference-mark-start text:name="Source Claim"/>source claim<text:reference-mark-end text:name="Source Claim"/> with <text:reference-ref text:ref-name="Source Claim" text:reference-format="text">source claim reference</text:reference-ref>, index mark <text:alphabetical-index-mark-start text:id="idx-source-claim" text:string-value="source claim" text:key1="Migration" text:key2="ODT" text:main-entry="true"/>source claim<text:alphabetical-index-mark-end text:id="idx-source-claim"/>, caption <text:sequence text:name="Illustration" text:formula="ooow:Illustration+1" text:ref-name="source-hero-seq">Figure 1</text:sequence> referenced as <text:sequence-ref text:name="Illustration" text:ref-name="source-hero-seq" text:reference-format="category-and-value">Figure 1</text:sequence-ref> and note <text:note-ref text:ref-name="ftn-review" text:note-class="footnote" text:reference-format="text">1</text:note-ref>, review field <text:variable-set text:name="ReviewStatus" office:value-type="string" office:string-value="Ready">Ready</text:variable-set>, conditional status <text:conditional-text text:condition="ReviewStatus == &quot;Ready&quot;" text:string-value-if-true="Ready to publish" text:string-value-if-false="Hold for review">Ready to publish</text:conditional-text>, hidden review note <text:hidden-text text:condition="NeedsReview == true" text:string-value="reviewer note">reviewer note</text:hidden-text> by <text:user-field-get text:name="Reviewer">Migration Desk</text:user-field-get> from source package <text:user-field-get text:name="SourcePackage"/>, placeholder <text:placeholder text:placeholder-type="text" text:description="Summarize import decision" text:style-name="PlaceholderText">review summary</text:placeholder> on page <text:page-number text:select-page="current" style:num-format="I" style:num-prefix="p. " style:num-suffix=" / source" style:num-letter-sync="true">II</text:page-number>, continuation <text:page-continuation text:select-page="next" text:string-value="continued on next page">continued on next page</text:page-continuation>, source page <text:page-variable-set text:name="SourcePage" text:current-value="4">4</text:page-variable-set>/<text:page-variable-get text:name="SourcePage" text:current-value="4">4</text:page-variable-get>, chapter <text:chapter text:outline-level="1" text:display="name-and-number">1 ODT source packet</text:chapter>, file <text:file-name text:display="full">source/review.odt</text:file-name>, words <text:word-count>64</text:word-count>, source budget <text:expression text:name="ApprovedBudget" text:formula="ooow:approved-budget" office:value-type="currency" office:value="42.50" office:currency="USD"/>, measured approvals <text:measure text:name="ApprovedImports" text:kind="value" text:formula="ooow:COUNT([Review.B2:Review.B12])" office:value-type="float" office:value="11" style:data-style-name="ReviewInteger">11</text:measure>, approval <draw:control draw:control="ctrl-review-approval"/> and disposition <draw:control draw:control="ctrl-review-disposition"/>, <text:a xlink:href="https://example.test/odt-source" xlink:type="simple" xlink:show="new" xlink:actuate="onRequest" office:name="Source Packet Link" office:title="ODT source package" office:target-frame-name="_blank" text:style-name="SourceLink" text:visited-style-name="VisitedSourceLink"><office:event-listeners><script:event-listener script:event-name="dom:click" script:language="ooo:Basic" xlink:href="vnd.sun.star.script:Standard.Module.OpenSource?language=Basic&amp;location=document" xlink:type="simple" xlink:actuate="onRequest"/></office:event-listeners>source URL</text:a> and relative source <text:a xlink:href="../media/source-packet.odt?download=1#review" office:title="Parent source packet">parent packet</text:a>, page boundary <text:soft-page-break/>after source page boundary, tab stop<text:tab/>converted, text-box caption <draw:frame draw:name="Captioned source figure"><draw:text-box><text:p><draw:frame draw:name="Source caption image"><draw:image xlink:href="Pictures/source%20hero.png"><svg:title>Source hero text-box title</svg:title><svg:desc>fallback source hero alt</svg:desc></draw:image></draw:frame>Recovered source figure caption.</text:p></draw:text-box></draw:frame>, citation <text:bibliography-mark text:identifier="source-review" text:number="2">source review packet</text:bibliography-mark>, formula <draw:frame draw:name="Migration formula"><draw:object xlink:href="./Object%201"/></draw:frame>, spreadsheet <draw:frame draw:name="Source spreadsheet"><draw:object-ole xlink:href="./Object%202"/></draw:frame>, chart <draw:frame draw:name="Source chart"><svg:desc>Source chart placeholder</svg:desc><draw:object xlink:href="./Object%203"/></draw:frame>, and annotations<text:note text:id="ftn-review" text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>ODT footnote reviewer context.</text:p></text:note-body></text:note><office:annotation><dc:creator>Migration Desk</dc:creator><dc:date>2026-06-04T23:20:00Z</dc:date><text:p>Check imported captions before publishing.</text:p></office:annotation>.</text:p>
      <text:p>Spreadsheet review sheet <text:sheet-name table:table-name="Review">Review</text:sheet-name> formula <text:table-formula text:formula="of:=SUM([Review.B2:Review.B12])" table:cell-range-address="Review.B2:Review.B12" office:value-type="float" office:value="11" style:data-style-name="ReviewInteger">11</text:table-formula> remains reviewer-visible.</text:p>
      <text:p>Hidden paragraph marker <text:hidden-paragraph text:condition="ArchiveOnly" text:string-value="archive paragraph marker"/> remains available to reviewers.</text:p>
      <text:p>Source metadata fields <text:title>WordPress ODT source packet</text:title> sender <text:sender-firstname/> <text:sender-lastname/> &lt;<text:sender-email/>&gt;, custom id <text:user-defined text:name="wp-source-id" office:value-type="string" office:string-value="packet-42" text:fixed="true">packet-42</text:user-defined> by <text:author-name text:style-name="ReviewerField" text:fixed="true">Migration Desk</text:author-name> created <text:creation-date text:date-value="2026-06-05" text:date-adjust="P1D" style:data-style-name="ReviewDateFormat">June 6, 2026</text:creation-date> at <text:creation-time text:time-value="PT09H30M00S" text:time-adjust="PT30M" style:data-style-name="ReviewTimeFormat">10:00</text:creation-time>, template <text:template-name text:display="full">Templates/import-review.ott</text:template-name>, source line <text:line-number style:num-format="1">37</text:line-number>, metadata span <text:meta xml:id="source-quality-meta" text:name="review:quality" text:description="Manual source-quality flag" xhtml:about="content.xml#source-quality-meta" xhtml:property="wp:review-status" xhtml:content="ready" xhtml:datatype="xsd:string">source quality</text:meta>, and score <text:meta-field text:name="review-score" office:value-type="float" office:value="0.98">98%</text:meta-field>.</text:p>
      <text:p>Input fields <text:text-input text:description="Confirm imported title">Imported packet title</text:text-input>, <text:variable-input text:name="ReviewStatus" office:value-type="string" office:string-value="Ready">Ready</text:variable-input>, <text:user-field-input text:name="Reviewer">Migration Desk</text:user-field-input>, and disposition <text:drop-down text:name="ReviewDisposition"><text:label text:value="Draft"/><text:label text:value="Ready to publish" text:current-selected="true"/><text:label text:value="Needs legal review"/></text:drop-down> remain reviewer-visible.</text:p>
      <text:p>Declared variable fallback <text:variable-set text:name="ReviewStatus" office:value-type="string" office:string-value="Ready"/> resolves as <text:variable-get text:name="ReviewStatus"/>.</text:p>
      <text:p text:style-name="ReviewQuote">Quoted source decision survives as review context.</text:p>
      <text:p text:style-name="SourceCode">define('WP_IMPORTING', true);<text:line-break/>echo sanitize_text_field($title);<text:tab/>// source audit</text:p>
      <text:list text:id="review-checklist" text:style-name="ReviewSteps">
        <text:list-header><text:p>Review packet checklist</text:p></text:list-header>
        <text:list-item>
          <text:p>Match ODT media to WordPress attachments</text:p>
          <text:list>
            <text:list-item>
              <text:p>Check inherited nested checklist style</text:p>
              <text:list>
                <text:list-item><text:p>Check sparse nested fallback style</text:p></text:list-item>
              </text:list>
            </text:list-item>
          </text:list>
        </text:list-item>
        <text:list-item><text:p>Review table spans</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="ReviewSteps" text:continue-numbering="true" text:continue-list="review-checklist">
        <text:list-item><text:p>Publish continued review checklist</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="GraphicReviewBullets">
        <text:list-item><text:p>Review image bullet metadata</text:p></text:list-item>
      </text:list>
      <draw:frame draw:name="Source hero" draw:style-name="HeroFrame" draw:layer="review-media" text:anchor-type="paragraph" text:anchor-page-number="2" svg:x="1.2cm" svg:y="2.4cm" svg:width="6cm" svg:height="3.5cm" draw:z-index="5">
        <draw:image xlink:href="../Pictures/source%20hero.png" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad">
          <svg:title>Source hero</svg:title>
          <svg:desc>ODT source hero alt</svg:desc>
        </draw:image>
        <draw:caption><text:p>Figure 2: ODT source hero reviewed.</text:p></draw:caption>
      </draw:frame>
      <draw:frame draw:name="Reviewer aside" draw:style-name="AsideFrame" draw:layer="draft-notes" text:anchor-type="paragraph" text:anchor-page-number="3" svg:x="2cm" svg:y="5cm" svg:width="7cm" svg:height="2cm" draw:z-index="6">
        <draw:text-box>
          <text:p>Anchored text box note for reviewers.</text:p>
        </draw:text-box>
      </draw:frame>
      <table:table table:name="Review" table:style-name="ReviewTable" table:template-name="ReviewTemplate" table:print-ranges="'Review Sheet'.A1:'Review Sheet'.B2 Review.D1:Review.D4" table:protected="true" table:protection-key="opaque-review-key" table:protection-key-digest-algorithm="urn:odf:sha1">
        <table:scenario table:name="ReadyImport" table:display-border="true" table:border-color="#00843d" table:copy-back="false" table:copy-styles="true" table:copy-formulas="false" table:is-active="true" table:scenario-ranges="'Review Sheet'.A1:'Review Sheet'.B2 Review.D1:Review.D4" table:comment="Approved ODT rows for WordPress import"/>
        <table:table-row table:default-cell-style-name="ReviewDefaultCell">
          <table:table-cell><text:p>Item</text:p></table:table-cell>
          <table:table-cell table:style-name="ReviewStatusCell"><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell table:number-columns-spanned="2" table:formula="of:=COUNT([.A2:.B2])" office:value-type="float" office:value="2">
            <text:p>Ready for block import review</text:p>
            <office:annotation office:name="cell-review-note">
              <dc:creator>Sheet Reviewer</dc:creator>
              <dc:date>2026-06-09T01:11:00Z</dc:date>
              <text:p>Confirm imported source status.</text:p>
            </office:annotation>
          </table:table-cell>
          <table:covered-table-cell table:style-name="CoveredAuditCell" office:value-type="string" office:string-value="draft source"><text:p>Draft state hidden by merge</text:p></table:covered-table-cell>
        </table:table-row>
      </table:table>
      <text:p text:style-name="Table">Table 2: Source package review grid</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:meta>
    <dc:title>WordPress ODT source packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <dc:language>en</dc:language>
    <meta:generator>LibreOffice/7.6.4$Linux_X86_64 LibreOffice_project/7.6.4</meta:generator>
    <meta:editing-duration>PT1H2M3S</meta:editing-duration>
    <meta:modification-date>2026-06-08T19:55:00Z</meta:modification-date>
    <meta:modification-time>PT19H55M00S</meta:modification-time>
    <meta:printed-by>Migration Printer</meta:printed-by>
    <meta:print-date>2026-06-08</meta:print-date>
    <meta:print-time>PT12H34M56S</meta:print-time>
    <meta:template xlink:href="Templates/import-review.ott" xlink:type="simple" xlink:title="Import Review Template" xlink:show="replace" xlink:actuate="onRequest" meta:date="2026-06-01T10:00:00Z"/>
    <meta:auto-reload xlink:href="https://example.test/source.odt" xlink:type="simple" xlink:show="replace" xlink:actuate="onLoad" meta:delay="PT15M"/>
    <meta:hyperlink-behaviour xlink:show="new" office:target-frame-name="_blank"/>
    <meta:user-defined meta:name="requires-legal-review" meta:value-type="boolean" office:boolean-value="true"/>
    <meta:user-defined meta:name="source-score" meta:value-type="float" office:value="97.5"/>
    <meta:keyword>odt</meta:keyword>
    <meta:document-statistic meta:page-count="1" meta:word-count="64" meta:image-count="1"/>
  </office:meta>
</office:document-meta>
XML;

$settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0">
  <office:settings>
    <config:config-item-set config:name="ooo:view-settings">
      <config:config-item config:name="ViewAreaTop" config:type="int">1440</config:config-item>
      <config:config-item config:name="ShowRedlineChanges" config:type="boolean">true</config:config-item>
      <config:config-item-map-indexed config:name="Views">
        <config:config-item-map-entry>
          <config:config-item config:name="ViewId" config:type="string">wp-review-view</config:config-item>
          <config:config-item config:name="ViewLeft" config:type="int">120</config:config-item>
        </config:config-item-map-entry>
      </config:config-item-map-indexed>
    </config:config-item-set>
    <config:config-item-set config:name="ooo:configuration-settings">
      <config:config-item config:name="LoadReadonly" config:type="boolean">false</config:config-item>
    </config:config-item-set>
    <config:config-item-set config:name="ooo:user-settings">
      <config:config-item config:name="FirstName" config:type="string">Maya</config:config-item>
      <config:config-item config:name="LastName" config:type="string">Editor</config:config-item>
      <config:config-item config:name="EMail" config:type="string">desk@example.test</config:config-item>
    </config:config-item-set>
  </office:settings>
</office:document-settings>
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
  xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:chart>
      <chart:chart chart:class="chart:bar" chart:style-name="ReviewChart">
        <chart:title chart:style-name="ReviewChartTitle" svg:x="1cm" svg:y="0.5cm"><text:p>Review chart title</text:p></chart:title>
        <chart:legend chart:style-name="ReviewChartLegend" chart:legend-position="end" chart:legend-align="center"/>
        <chart:plot-area table:cell-range-address="Review.A1:Review.B4" chart:data-source-has-labels="both">
          <chart:axis chart:dimension="x" chart:name="primary-x" chart:style-name="ReviewAxisX"><chart:title><text:p>Review category</text:p></chart:title></chart:axis>
          <chart:axis chart:dimension="y" chart:name="primary-y" chart:style-name="ReviewAxisY"><chart:title><text:p>Review value</text:p></chart:title></chart:axis>
          <chart:categories table:cell-range-address="Review.A2:Review.A4"/>
          <chart:series chart:values-cell-range-address="Review.B2:Review.B4" chart:label-cell-address="Review.B1" chart:attached-axis="primary-y"/>
        </chart:plot-area>
      </chart:chart>
    </office:chart>
  </office:body>
</office:document-content>
XML;

$rdfMetadataXml = <<<'XML'
<rdf:RDF
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:wp="https://example.test/ns/wp#"
  xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <rdf:Description rdf:about="content.xml">
    <dc:title xml:lang="en">WordPress ODT source packet body</dc:title>
    <dc:creator rdf:resource="urn:uuid:migration-reviewer"/>
    <wp:review-status>ready</wp:review-status>
  </rdf:Description>
  <rdf:Description rdf:about="content.xml#source-quality-meta">
    <dc:description>Manual source-quality flag</dc:description>
    <wp:review-status>ready</wp:review-status>
  </rdf:Description>
  <rdf:Description rdf:about="Pictures/source hero.png">
    <dc:format>image/png</dc:format>
  </rdf:Description>
</rdf:RDF>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'settings.xml', 'data' => $settingsXml],
    ['name' => 'manifest.rdf', 'data' => $rdfMetadataXml],
    ['name' => 'Object 1/content.xml', 'data' => $mathObjectXml],
    ['name' => 'Object 2/oleObject.bin', 'data' => 'OLEPAYLOAD'],
    ['name' => 'Object 3/content.xml', 'data' => $chartObjectXml],
    ['name' => 'Pictures/source hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/review-bullet.svg', 'data' => '<svg/>', 'compressionMethod' => 0],
]);

$reader = new OdfReader();
$result = $reader->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (($argv[1] ?? '') === '--self-test') {
    if ($result['metadata']['title'] !== 'WordPress ODT source packet') {
        throw new RuntimeException('Expected ODT title metadata');
    }
    if (($result['metadata']['generator'] ?? '') !== 'LibreOffice/7.6.4$Linux_X86_64 LibreOffice_project/7.6.4') {
        throw new RuntimeException('Expected ODT generator metadata');
    }
    if (($result['metadata']['modificationDate'] ?? '') !== '2026-06-08T19:55:00Z'
        || ($result['metadata']['modificationTime'] ?? '') !== 'PT19H55M00S') {
        throw new RuntimeException('Expected ODT package modification metadata');
    }
    if (($result['metadata']['template']['href'] ?? '') !== 'Templates/import-review.ott'
        || ($result['metadata']['template']['title'] ?? '') !== 'Import Review Template') {
        throw new RuntimeException('Expected ODT template provenance metadata');
    }
    if (($result['metadata']['autoReload']['delay'] ?? '') !== 'PT15M'
        || ($result['metadata']['autoReload']['href'] ?? '') !== 'https://example.test/source.odt') {
        throw new RuntimeException('Expected ODT auto-reload metadata');
    }
    if (($result['metadata']['hyperlinkBehaviour']['show'] ?? '') !== 'new'
        || ($result['metadata']['hyperlinkBehaviour']['targetFrameName'] ?? '') !== '_blank') {
        throw new RuntimeException('Expected ODT default hyperlink behaviour metadata');
    }
    if (($result['metadata']['userDefined']['requires-legal-review'] ?? '') !== 'true'
        || (($result['metadata']['userDefinedDetails']['requires-legal-review']['booleanValue'] ?? null) !== true)) {
        throw new RuntimeException('Expected typed ODT boolean user-defined metadata');
    }
    if (($result['metadata']['userDefined']['source-score'] ?? '') !== '97.5'
        || (($result['metadata']['userDefinedDetails']['source-score']['valueType'] ?? '') !== 'float')) {
        throw new RuntimeException('Expected typed ODT numeric user-defined metadata');
    }
    if (($result['settings']['count'] ?? 0) !== 3
        || (($result['settings']['setsByName']['ooo:view-settings']['itemsByName']['ViewAreaTop']['typedValue'] ?? null) !== 1440)
        || (($result['settings']['setsByName']['ooo:view-settings']['itemsByName']['ShowRedlineChanges']['typedValue'] ?? null) !== true)
        || (($result['settings']['setsByName']['ooo:configuration-settings']['itemsByName']['LoadReadonly']['typedValue'] ?? null) !== false)
        || (($result['settings']['setsByName']['ooo:user-settings']['itemsByName']['EMail']['typedValue'] ?? null) !== 'desk@example.test')) {
        throw new RuntimeException('Expected ODT package settings metadata to be parsed with typed values');
    }
    if (($result['settings']['setsByName']['ooo:view-settings']['mapsByName']['Views']['entries'][0]['itemsByName']['ViewId']['typedValue'] ?? '') !== 'wp-review-view'
        || (($result['importReport']['settings']['itemCount'] ?? 0) !== 8)
        || (($result['document']->attr('settings')['mapEntryCount'] ?? 0) !== 1)) {
        throw new RuntimeException('Expected ODT view settings map metadata to survive import review');
    }
    if (($result['rdfMetadata']['partCount'] ?? 0) !== 1
        || (($result['rdfMetadata']['parsedPartCount'] ?? 0) !== 1)
        || (($result['rdfMetadata']['tripleCount'] ?? 0) !== 6)
        || (($result['rdfMetadata']['resourceCount'] ?? 0) !== 1)
        || (($result['rdfMetadata']['subjectsBySubject']['content.xml']['predicates'] ?? []) !== ['dc:creator', 'dc:title', 'wp:review-status'])
        || (($result['rdfMetadata']['subjectsBySubject']['content.xml#source-quality-meta']['predicates'] ?? []) !== ['dc:description', 'wp:review-status'])
        || (($result['rdfMetadata']['subjectsBySubject']['Pictures/source hero.png']['predicates'] ?? []) !== ['dc:format'])
        || (($result['importReport']['rdfMetadata']['parts'][0]['part'] ?? '') !== 'manifest.rdf')) {
        throw new RuntimeException('Expected ODT RDF metadata sidecar triples to survive package review');
    }
    if (($result['media'][0]['part'] ?? '') !== 'Pictures/source hero.png') {
        throw new RuntimeException('Expected ODT image manifest media to be reported');
    }
    if (($result['media'][0]['canExposeBytes'] ?? true) !== false) {
        throw new RuntimeException('Expected encrypted ODT media bytes to stay unavailable for import');
    }
    if (($result['document']->attr('manifest')['version'] ?? '') !== '1.3'
        || (($result['manifest'][0]['preferredViewMode'] ?? '') !== 'edit')
        || (($result['media'][0]['preferredViewMode'] ?? '') !== 'presentation-slide-show')) {
        throw new RuntimeException('Expected ODT manifest version and preferred-view metadata to survive import review');
    }
    $imageNode = null;
    foreach ($result['document']->children as $block) {
        foreach ($block->children as $child) {
            if ($child instanceof \PortLibs\Pandoc\AstNode && $child->type === 'image' && $child->attr('sourcePart') === 'Pictures/source hero.png' && $child->attr('width') === '6cm') {
                $imageNode = $child;
                break 2;
            }
        }
    }
    if (!$imageNode instanceof \PortLibs\Pandoc\AstNode || $imageNode->attr('width') !== '6cm' || $imageNode->attr('height') !== '3.5cm') {
        throw new RuntimeException('Expected ODT frame image dimensions to survive AST handoff');
    }
    $frameMetadata = $imageNode->attr('odfFrameMetadata');
    if (
        !is_array($frameMetadata)
        || ($frameMetadata['name'] ?? '') !== 'Source hero'
        || ($frameMetadata['styleName'] ?? '') !== 'HeroFrame'
        || ($frameMetadata['layer'] ?? '') !== 'review-media'
        || ($frameMetadata['layerExists'] ?? '') !== 'true'
        || ($frameMetadata['layerDisplay'] ?? '') !== 'screen'
        || ($frameMetadata['layerProtected'] ?? '') !== 'true'
        || ($frameMetadata['anchorType'] ?? '') !== 'paragraph'
        || ($frameMetadata['anchorPageNumber'] ?? '') !== '2'
        || ($frameMetadata['x'] ?? '') !== '1.2cm'
        || ($frameMetadata['y'] ?? '') !== '2.4cm'
        || ($frameMetadata['zIndex'] ?? '') !== '5'
    ) {
        throw new RuntimeException('Expected ODT image frame anchor metadata to survive AST handoff');
    }
    if (($result['contentDeclarations']['drawLayerCount'] ?? 0) !== 2
        || (($result['contentDeclarations']['drawLayersByName']['review-media']['protected'] ?? null) !== true)
        || (($result['contentDeclarations']['drawLayersByName']['draft-notes']['hidden'] ?? null) !== true)
        || (($result['importReport']['content']['frameLayerReferenceCount'] ?? 0) !== 2)) {
        throw new RuntimeException('Expected ODT drawing layers to survive content declaration and frame metadata handoff');
    }
    if (!str_contains($blocks, 'data-odf-frame-layer="review-media" data-odf-frame-layer-exists="true" data-odf-frame-layer-display="screen" data-odf-frame-layer-protected="true"')) {
        throw new RuntimeException('Expected ODT image frame layer metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-frame-layer="draft-notes" data-odf-frame-layer-exists="true" data-odf-frame-layer-display="none" data-odf-frame-layer-hidden="true"')) {
        throw new RuntimeException('Expected ODT text-box layer metadata to render in WordPress blocks');
    }
    if (($result['importReport']['encryption']['encryptedParts'][0] ?? '') !== 'Pictures/source hero.png') {
        throw new RuntimeException('Expected ODT encrypted media to be listed in the import report');
    }
    if (($result['document']->children[0]->attr('id') ?? '') !== 'odt-source-packet') {
        throw new RuntimeException('Expected ODT heading bookmark identifier to survive AST handoff');
    }
    if (($result['document']->children[0]->attr('odfHeadingAnchor')['bookmarkName'] ?? '') !== 'ODT source packet') {
        throw new RuntimeException('Expected ODT heading bookmark metadata to survive AST handoff');
    }
    if (($result['document']->children[1]->attr('id') ?? '') !== 'source-overview-id') {
        throw new RuntimeException('Expected ODT xml:id heading identifier to survive AST handoff');
    }
    if (($result['document']->children[1]->attr('odfHeadingAnchor')['attributeName'] ?? '') !== 'xml:id') {
        throw new RuntimeException('Expected ODT xml:id heading provenance to survive AST handoff');
    }
    if (!str_contains($blocks, '<h1 id="odt-source-packet" data-odf-heading-anchor-source="bookmark" data-odf-heading-bookmark-name="ODT source packet" data-odf-heading-anchor-id="odt-source-packet">ODT source packet</h1>')) {
        throw new RuntimeException('Expected ODT heading to render as a WordPress heading block');
    }
    if (!str_contains($blocks, '<h1 id="source-overview-id" data-odf-heading-anchor-source="attribute" data-odf-heading-source-attribute="xml:id" data-odf-heading-source-id="source-overview-id" data-odf-heading-anchor-id="source-overview-id">Source overview heading</h1>')) {
        throw new RuntimeException('Expected ODT xml:id heading to render as a WordPress heading block');
    }
    if (str_contains($blocks, '<h1 id="odt-source-packet"><span id="odt-source-packet"')) {
        throw new RuntimeException('Expected ODT heading bookmark to avoid nested empty anchor output');
    }
    if (($result['importReport']['content']['tableOfContentsCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT table of contents to be counted in the import report');
    }
    if (!str_contains($blocks, '<div id="source-navigation" class="odf-table-of-contents odf-protected-table-of-contents" data-odf-toc-name="Source Navigation"')) {
        throw new RuntimeException('Expected ODT table-of-contents metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-toc-source-outline-level="2"')) {
        throw new RuntimeException('Expected ODT table-of-contents source settings to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<a href="#odt-source-packet">ODT source packet</a>')) {
        throw new RuntimeException('Expected ODT table-of-contents entry links to render in WordPress blocks');
    }
    if (($result['importReport']['content']['generatedIndexCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT generated indexes to be counted in the import report');
    }
    if (!str_contains($blocks, '<div id="figure-review" class="odf-generated-index odf-illustration-index odf-protected-generated-index" data-odf-index-type="illustration" data-odf-index-element="illustration-index" data-odf-index-name="Figure Review"')) {
        throw new RuntimeException('Expected ODT generated index metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-index-source-caption-sequence-name="Illustration" data-odf-index-source-use-caption="true" data-odf-index-template-count="2"')) {
        throw new RuntimeException('Expected ODT generated index source metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<a href="#source-hero-seq">Figure 1</a>')) {
        throw new RuntimeException('Expected ODT generated index body links to render in WordPress blocks');
    }
    $figureIndex = null;
    foreach ($result['document']->children as $block) {
        if ($block instanceof \PortLibs\Pandoc\AstNode && $block->attr('generatedIndexType') === 'illustration') {
            $figureIndex = $block;
            break;
        }
    }
    $figureIndexComponents = $figureIndex instanceof \PortLibs\Pandoc\AstNode
        ? ($figureIndex->attr('generatedIndexSource')['templates'][1]['components'] ?? [])
        : [];
    if (($figureIndexComponents[0]['href'] ?? '') !== '#source-hero-seq'
        || ($figureIndexComponents[0]['xlinkShow'] ?? '') !== 'replace'
        || ($figureIndexComponents[1]['chapterFormat'] ?? '') !== 'number'
        || ($figureIndexComponents[1]['styleName'] ?? '') !== 'FigureChapter'
        || ($figureIndexComponents[1]['outlineLevel'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT generated index entry-template component metadata to survive import review');
    }
    if (($result['importReport']['content']['tableCaptionCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT standalone and following table caption style paragraphs to be counted in the import report');
    }
    if (!str_contains($blocks, '<div class="caption odf-table-caption" data-odf-table-caption-style-name="Table"><p>Table 1: Review matrix caption</p></div>')) {
        throw new RuntimeException('Expected ODT table caption style paragraphs to render as WordPress caption divs');
    }
    if (!str_contains($blocks, '<p><strong><span data-odf-style-name="StyledSummary">Styled source summary keeps <a href="https://example.test/styled-source">review link</a> prominent.</span></strong></p>')) {
        throw new RuntimeException('Expected ODT paragraph text properties to render as styled WordPress inline content');
    }
    if (!str_contains($blocks, '<sup><span data-odf-style-name="SourceSuperscript">TM</span></sup>')) {
        throw new RuntimeException('Expected ODT superscript source mark to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<sub><span data-odf-style-name="SourceSubscript">2</span></sub>')) {
        throw new RuntimeException('Expected ODT subscript formula cue to render in WordPress blocks');
    }
    if (($result['importReport']['content']['rubyCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT ruby annotations to be counted in the import report');
    }
    if (!str_contains($blocks, '<span class="odf-ruby" data-odf-ruby-text="kanji">漢字</span>')) {
        throw new RuntimeException('Expected ODT ruby annotation metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<code data-odf-style-name="Source_Text">wp_insert_post</code>')) {
        throw new RuntimeException('Expected ODT Source_Text inline styles to render as WordPress code spans');
    }
    if (($result['importReport']['styles']['fontFaceCount'] ?? 0) !== 1
        || (($result['fontFaces']['SourceMono']['fontPitch'] ?? '') !== 'fixed')
        || (($result['styles']['FixedPitchMetadata']['textProperties']['fixedPitch'] ?? null) !== true)) {
        throw new RuntimeException('Expected ODT font-face pitch metadata to survive style parsing');
    }
    if (!str_contains($blocks, '<span data-odf-style-name="FixedPitchMetadata">review_code</span>')) {
        throw new RuntimeException('Expected ODT fixed-pitch source marker style to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<a href="https://example.test/odt-source" title="ODT source package" class="odf-link" data-odf-link-name="Source Packet Link" data-odf-link-style-name="SourceLink" data-odf-link-visited-style-name="VisitedSourceLink" data-odf-link-target-frame-name="_blank" data-odf-link-type="simple" data-odf-link-show="new" data-odf-link-actuate="onRequest" data-odf-link-event-listener-count="1" data-odf-link-event-1-name="dom:click" data-odf-link-event-1-language="ooo:Basic" data-odf-link-event-1-href="vnd.sun.star.script:Standard.Module.OpenSource?language=Basic&amp;location=document" data-odf-link-event-1-type="simple" data-odf-link-event-1-actuate="onRequest">source URL</a>')) {
        throw new RuntimeException('Expected ODT source link metadata to render in WordPress blocks');
    }
    if (($result['importReport']['content']['eventListenerCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT link event-listener metadata to be counted in the import report');
    }
    if (str_contains($blocks, '<script:event-listener')) {
        throw new RuntimeException('Expected ODT event-listener XML to stay out of WordPress blocks');
    }
    if (!str_contains($blocks, '<a href="media/source-packet.odt?download=1#review" title="Parent source packet">parent packet</a>')) {
        throw new RuntimeException('Expected ODT parent-relative source link to normalize in WordPress blocks');
    }
    if (str_contains($blocks, '../media/source-packet.odt')) {
        throw new RuntimeException('Expected ODT parent-relative source link to drop leading ../ in WordPress blocks');
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
    if (($result['importReport']['styles']['tableTemplateCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT table template metadata to be counted in the import report');
    }
    if (($result['importReport']['content']['tableTemplateReferenceCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT table template reference to be counted in the import report');
    }
    if (($result['importReport']['content']['tablePrintRangeCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT table print ranges to be counted in the import report');
    }
    if (($result['tableTemplates']['ReviewTemplate']['styles']['body'] ?? '') !== 'ReviewBody') {
        throw new RuntimeException('Expected ODT table template style slots to survive style parsing');
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
    if (($result['importReport']['content']['blockquoteCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT indented paragraph styles to be counted as blockquote handoffs');
    }
    if (($result['styles']['ReviewQuote']['paragraphProperties']['marginLeft'] ?? '') !== '6mm') {
        throw new RuntimeException('Expected ODT quote paragraph margin to survive style parsing');
    }
    if (!str_contains($blocks, '<blockquote class="wp-block-quote odf-blockquote"><p>Quoted source decision survives as review context.</p></blockquote>')) {
        throw new RuntimeException('Expected ODT indented paragraph style to render as a WordPress quote block');
    }
    if (($result['importReport']['content']['preformattedCodeBlockCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT preformatted paragraph styles to be counted as code block handoffs');
    }
    if (!str_contains($blocks, '<pre class="wp-block-code" data-odf-preformatted="true" data-odf-style-name="SourceCode"><code>define(&#039;WP_IMPORTING&#039;, true);')) {
        throw new RuntimeException('Expected ODT preformatted source style to render as a WordPress code block');
    }
    if (!str_contains($blocks, 'echo sanitize_text_field($title); // source audit</code></pre>')) {
        throw new RuntimeException('Expected ODT preformatted source line-break and tab content to normalize in WordPress code output');
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
    if (!str_contains($blocks, '<span id="source-claim" class="odf-reference-mark odf-reference-mark-range" data-odf-reference-name="Source Claim" data-odf-reference-range="true">source claim</span>')) {
        throw new RuntimeException('Expected ODT reference mark range to render around source text in WordPress blocks');
    }
    if (!str_contains($blocks, '<a href="#source-claim" class="odf-reference-ref" data-odf-ref-name="Source Claim" data-odf-reference-format="text">source claim reference</a>')) {
        throw new RuntimeException('Expected ODT reference-ref to render in WordPress blocks');
    }
    if (($result['importReport']['content']['indexMarkCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT index marks to be counted in the import report');
    }
    if (!str_contains($blocks, '<span class="odf-index-mark odf-index-mark-alphabetical" data-odf-index-mark-type="alphabetical" data-odf-index-mark-element="alphabetical-index-mark-start" data-odf-index-mark-id="idx-source-claim" data-odf-index-mark-string-value="source claim" data-odf-index-mark-key1="Migration" data-odf-index-mark-key2="ODT" data-odf-index-mark-main-entry="true">source claim</span>')) {
        throw new RuntimeException('Expected ODT alphabetical index mark metadata to render in WordPress blocks');
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
    if (!str_contains($blocks, '<span class="odf-field odf-field-sequence-ref" data-odf-field-type="sequence-ref" data-odf-field-name="Illustration" data-odf-field-ref-name="source-hero-seq" data-odf-field-reference-format="category-and-value" data-odf-field-sequence-display-outline-level="0" data-odf-field-sequence-separation-character="." data-odf-field-declared="true">Figure 1</span>')) {
        throw new RuntimeException('Expected ODT sequence-ref field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-note-ref" data-odf-field-type="note-ref" data-odf-field-ref-name="ftn-review" data-odf-field-reference-format="text" data-odf-field-note-class="footnote">1</span>')) {
        throw new RuntimeException('Expected ODT note-ref field to render in WordPress blocks');
    }
    if (($result['importReport']['content']['fieldCount'] ?? 0) !== 35) {
        throw new RuntimeException('Expected ODT variable, input, dropdown, conditional, hidden, hidden paragraph, user, user-defined, typed expression, measure, sheet-name, table-formula, source metadata, template, line-number, page continuation, page variable, chapter, filename, and statistic fields to be counted in the import report');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-template-name" data-odf-field-type="template-name" data-odf-field-display="full">Templates/import-review.ott</span>')) {
        throw new RuntimeException('Expected ODT template-name field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-line-number" data-odf-field-type="line-number" data-odf-field-num-format="1">37</span>')) {
        throw new RuntimeException('Expected ODT line-number field to render in WordPress blocks');
    }
    if (($result['importReport']['content']['metaSpanCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT inline metadata spans to be counted separately from fields in the import report');
    }
    if (!str_contains($blocks, '<span class="odf-meta" data-odf-meta-type="meta" data-odf-meta-source-id="source-quality-meta" data-odf-meta-name="review:quality" data-odf-meta-description="Manual source-quality flag" data-odf-meta-rdf-about="content.xml#source-quality-meta" data-odf-meta-rdf-property="wp:review-status" data-odf-meta-rdf-content="ready" data-odf-meta-rdf-datatype="xsd:string" data-odf-meta-rdf-subject="content.xml#source-quality-meta" data-odf-meta-rdf-subject-part-count="1" data-odf-meta-rdf-subject-triple-count="2" data-odf-meta-rdf-subject-literal-count="2" data-odf-meta-rdf-subject-resource-count="0" data-odf-meta-rdf-subject-parts="manifest.rdf" data-odf-meta-rdf-subject-predicates="dc:description,wp:review-status">source quality</span>')) {
        throw new RuntimeException('Expected ODT text:meta source-quality span to render with RDF sidecar provenance in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-meta odf-meta-field" data-odf-meta-type="meta-field" data-odf-meta-name="review-score" data-odf-meta-value-type="float" data-odf-meta-value="0.98">98%</span>')) {
        throw new RuntimeException('Expected ODT text:meta-field review score to render in WordPress blocks');
    }
    if (($result['importReport']['content']['placeholderCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT placeholders to be counted in the import report');
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
    if (!str_contains($blocks, '<span class="odf-field odf-field-variable-get" data-odf-field-type="variable-get" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready" data-odf-field-declared="true">Ready</span>')) {
        throw new RuntimeException('Expected ODT variable-get declaration fallback to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-conditional-text" data-odf-field-type="conditional-text" data-odf-field-condition="ReviewStatus == &quot;Ready&quot;" data-odf-field-string-value-if-true="Ready to publish" data-odf-field-string-value-if-false="Hold for review">Ready to publish</span>')) {
        throw new RuntimeException('Expected ODT conditional text field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-hidden-text" data-odf-field-type="hidden-text" data-odf-field-condition="NeedsReview == true" data-odf-field-string-value="reviewer note">reviewer note</span>')) {
        throw new RuntimeException('Expected ODT hidden text field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-hidden-paragraph" data-odf-field-type="hidden-paragraph" data-odf-field-condition="ArchiveOnly" data-odf-field-string-value="archive paragraph marker">archive paragraph marker</span>')) {
        throw new RuntimeException('Expected ODT hidden paragraph field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-user-field-get" data-odf-field-type="user-field-get" data-odf-field-name="SourcePackage" data-odf-field-value-type="string" data-odf-field-string-value="package-42" data-odf-field-declared="true">package-42</span>')) {
        throw new RuntimeException('Expected ODT user-field declaration fallback to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-page-number" data-odf-field-type="page-number" data-odf-field-select-page="current" data-odf-field-num-format="I" data-odf-field-num-prefix="p. " data-odf-field-num-suffix=" / source" data-odf-field-num-letter-sync="true">II</span>')) {
        throw new RuntimeException('Expected ODT page-number field format metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-page-continuation" data-odf-field-type="page-continuation" data-odf-field-string-value="continued on next page" data-odf-field-select-page="next">continued on next page</span>')) {
        throw new RuntimeException('Expected ODT page-continuation field metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-sheet-name" data-odf-field-type="sheet-name" data-odf-field-table-name="Review">Review</span>')) {
        throw new RuntimeException('Expected ODT sheet-name field metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-table-formula" data-odf-field-type="table-formula" data-odf-field-formula="of:=SUM([Review.B2:Review.B12])" data-odf-field-cell-range-address="Review.B2:Review.B12" data-odf-field-value-type="float" data-odf-field-value="11" data-odf-field-style-name="ReviewInteger">11</span>')) {
        throw new RuntimeException('Expected ODT table-formula field metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-page-variable-set" data-odf-field-type="page-variable-set" data-odf-field-name="SourcePage" data-odf-field-current-value="4">4</span>')) {
        throw new RuntimeException('Expected ODT page-variable-set field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-page-variable-get" data-odf-field-type="page-variable-get" data-odf-field-name="SourcePage" data-odf-field-current-value="4">4</span>')) {
        throw new RuntimeException('Expected ODT page-variable-get field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-chapter" data-odf-field-type="chapter" data-odf-field-display="name-and-number" data-odf-field-outline-level="1">1 ODT source packet</span>')) {
        throw new RuntimeException('Expected ODT chapter field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-file-name" data-odf-field-type="file-name" data-odf-field-display="full">source/review.odt</span>')) {
        throw new RuntimeException('Expected ODT file-name field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-word-count" data-odf-field-type="word-count">64</span>')) {
        throw new RuntimeException('Expected ODT word-count field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-expression" data-odf-field-type="expression" data-odf-field-name="ApprovedBudget" data-odf-field-formula="ooow:approved-budget" data-odf-field-value-type="currency" data-odf-field-value="42.50" data-odf-field-currency="USD">42.50</span>')) {
        throw new RuntimeException('Expected ODT typed currency expression field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-measure" data-odf-field-type="measure" data-odf-field-name="ApprovedImports" data-odf-field-kind="value" data-odf-field-formula="ooow:COUNT([Review.B2:Review.B12])" data-odf-field-value-type="float" data-odf-field-value="11" data-odf-field-style-name="ReviewInteger">11</span>')) {
        throw new RuntimeException('Expected ODT measure field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-title" data-odf-field-type="title">WordPress ODT source packet</span>')) {
        throw new RuntimeException('Expected ODT source title field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-sender-email" data-odf-field-type="sender-email" data-odf-field-string-value="desk@example.test" data-odf-field-settings-source="settings.xml" data-odf-field-settings-set="ooo:user-settings" data-odf-field-settings-name="EMail">desk@example.test</span>')) {
        throw new RuntimeException('Expected ODT sender-email field to resolve from settings.xml in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-user-defined" data-odf-field-type="user-defined" data-odf-field-name="wp-source-id" data-odf-field-value-type="string" data-odf-field-string-value="packet-42" data-odf-field-fixed="true">packet-42</span>')) {
        throw new RuntimeException('Expected ODT user-defined content field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-author-name" data-odf-field-type="author-name" data-odf-field-style-name="ReviewerField" data-odf-field-fixed="true">Migration Desk</span>')) {
        throw new RuntimeException('Expected ODT styled source author field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-creation-date" data-odf-field-type="creation-date" data-odf-field-date-value="2026-06-05" data-odf-field-date-adjust="P1D" data-odf-field-style-name="ReviewDateFormat">June 6, 2026</span>')) {
        throw new RuntimeException('Expected ODT source creation-date field format metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-creation-time" data-odf-field-type="creation-time" data-odf-field-time-value="PT09H30M00S" data-odf-field-time-adjust="PT30M" data-odf-field-style-name="ReviewTimeFormat">10:00</span>')) {
        throw new RuntimeException('Expected ODT source creation-time field format metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-text-input" data-odf-field-type="text-input" data-odf-field-description="Confirm imported title">Imported packet title</span>')) {
        throw new RuntimeException('Expected ODT text-input field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-variable-input" data-odf-field-type="variable-input" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready">Ready</span>')) {
        throw new RuntimeException('Expected ODT variable-input field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-user-field-input" data-odf-field-type="user-field-input" data-odf-field-name="Reviewer">Migration Desk</span>')) {
        throw new RuntimeException('Expected ODT user-field-input field to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-drop-down" data-odf-field-type="drop-down" data-odf-field-name="ReviewDisposition" data-odf-field-label-count="3" data-odf-field-selected-value="Ready to publish">Ready to publish</span>')) {
        throw new RuntimeException('Expected ODT dropdown field selection to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-placeholder odf-placeholder-text" data-odf-placeholder-type="text" data-odf-placeholder-description="Summarize import decision" data-odf-placeholder-style-name="PlaceholderText">review summary</span>')) {
        throw new RuntimeException('Expected ODT placeholder to render in WordPress blocks');
    }
    if (($result['importReport']['content']['formControlCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT form controls to be counted in the import report');
    }
    if (($result['importReport']['content']['missingFormControlCount'] ?? 0) !== 0) {
        throw new RuntimeException('Expected ODT form controls to resolve from office:forms');
    }
    if (($result['importReport']['content']['formControlOptionCount'] ?? 0) !== 3 || ($result['importReport']['content']['selectedFormControlOptionCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT form control options to be counted in the import report');
    }
    if (!str_contains($blocks, '<span class="odf-form-control odf-control-checkbox" data-odf-control-id="ctrl-review-approval" data-odf-control-type="checkbox" data-odf-control-exists="true" data-odf-control-form-name="Import Review"')) {
        throw new RuntimeException('Expected ODT form control placeholder to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-form-control odf-control-combobox" data-odf-control-id="ctrl-review-disposition" data-odf-control-type="combobox" data-odf-control-exists="true"')) {
        throw new RuntimeException('Expected ODT combobox form control placeholder to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-control-option-count="3" data-odf-control-selected-option-count="1" data-odf-control-selected-option-labels="Ready to publish" data-odf-control-selected-option-values="ready"')) {
        throw new RuntimeException('Expected ODT combobox option metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-control-form-action="https://example.test/import-review" data-odf-control-form-method="post"')) {
        throw new RuntimeException('Expected ODT form submission action metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-control-form-command="import_review_packets" data-odf-control-form-command-type="table" data-odf-control-form-datasource="wp_import_queue" data-odf-control-form-apply-filter="true"')) {
        throw new RuntimeException('Expected ODT form command metadata to render in WordPress blocks');
    }
    if (($result['importReport']['content']['softPageBreakCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT soft page break to be counted in the import report');
    }
    if (!str_contains($blocks, 'page boundary <span class="odf-soft-page-break" data-odf-soft-page-break="true"></span>after source page boundary')) {
        throw new RuntimeException('Expected ODT soft page break marker to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'tab stop converted, text-box caption')) {
        throw new RuntimeException('Expected ODT text:tab to render as a Pandoc space in WordPress blocks');
    }
    if (str_contains($blocks, "tab stop\tconverted")) {
        throw new RuntimeException('Expected ODT text:tab to avoid literal tab output in WordPress blocks');
    }
    if (!str_contains($blocks, '<img src="Pictures/source%20hero.png" alt="Recovered source figure caption." title="fig:Source hero text-box title" class="odf-text-box-image-caption" data-odf-text-box-caption="true" data-odf-text-box-frame-name="Captioned source figure"/>')) {
        throw new RuntimeException('Expected ODT frame text-box image captions to render as captioned WordPress image handoffs');
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
    if (!str_contains($blocks, '<li>Publish continued review checklist</li>')) {
        throw new RuntimeException('Expected ODT continued list numbering to survive WordPress blocks');
    }
    if (!str_contains($blocks, '<ol data-odf-list-id="review-checklist" data-odf-list-id-attribute="text:id"><li>Match ODT media to WordPress attachments')) {
        throw new RuntimeException('Expected ODT list source ids to survive WordPress blocks');
    }
    if (!str_contains($blocks, '<ol start="3" data-odf-list-continue-list="review-checklist" data-odf-list-continued="true"><li>Publish continued review checklist</li></ol>')) {
        throw new RuntimeException('Expected ODT named continued list metadata to survive WordPress blocks');
    }
    if (($result['importReport']['content']['imageListStyleCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT image list style metadata to be counted in the import report');
    }
    if (($result['importReport']['content']['listTextPropertyCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT list marker text style metadata to be counted in the import report');
    }
    if (!str_contains($blocks, '<ul data-odf-list-image-style="true" data-odf-list-image-href="Pictures/review-bullet.svg" data-odf-list-image-type="simple" data-odf-list-image-show="embed" data-odf-list-image-actuate="onLoad" data-odf-list-image-title="Review badge" data-odf-list-image-width="0.18in" data-odf-list-image-height="0.18in"')) {
        throw new RuntimeException('Expected ODT image list style metadata to survive WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-list-text-property-count="5"')
        || !str_contains($blocks, 'data-odf-list-text-font-name="SourceMono"')
        || !str_contains($blocks, 'data-odf-list-text-font-pitch="fixed"')
        || !str_contains($blocks, 'data-odf-list-text-fixed-pitch="true"')
        || !str_contains($blocks, 'data-odf-list-text-italic="true"')) {
        throw new RuntimeException('Expected ODT list marker text style metadata to survive WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-list-label-label-followed-by="listtab"')
        || !str_contains($blocks, '<li>Review image bullet metadata</li></ul>')) {
        throw new RuntimeException('Expected ODT image list alignment metadata to survive WordPress blocks');
    }
    if (!str_contains($blocks, '<ol start="4" type="a"><li>Check inherited nested checklist style')) {
        throw new RuntimeException('Expected ODT nested lists without explicit style names to inherit parent list style');
    }
    if (!str_contains($blocks, '<ol start="4" type="a"><li>Check sparse nested fallback style</li></ol>')) {
        throw new RuntimeException('Expected sparse ODT nested list levels to reuse the nearest lower list style');
    }
    if (($result['importReport']['content']['noteCount'] ?? 0) < 2) {
        throw new RuntimeException('Expected ODT footnote and annotation notes to be reported');
    }
    if (($result['importReport']['content']['noteConfigurationCount'] ?? 0) !== 2
        || ($result['importReport']['contentDeclarations']['noteConfigurationsByClass']['footnote']['footnotesPosition'] ?? '') !== 'page'
        || ($result['importReport']['contentDeclarations']['noteConfigurationsByClass']['endnote']['masterPageName'] ?? '') !== 'Endnotes') {
        throw new RuntimeException('Expected ODT notes-configuration metadata to be preserved for WordPress review');
    }
    if (($result['importReport']['content']['noteConfigurationSeparatorCount'] ?? 0) !== 1
        || ($result['importReport']['contentDeclarations']['noteConfigurationsByClass']['footnote']['footnoteSeparator']['relWidth'] ?? '') !== '25%') {
        throw new RuntimeException('Expected ODT footnote separator metadata to be preserved for WordPress review');
    }
    if (($result['importReport']['content']['lineNumberingConfigurationCount'] ?? 0) !== 1
        || ($result['importReport']['content']['lineNumberingSeparatorCount'] ?? 0) !== 1
        || ($result['contentDeclarations']['lineNumberingConfiguration']['styleName'] ?? '') !== 'ReviewLineNumber'
        || ($result['contentDeclarations']['lineNumberingConfiguration']['separator']['text'] ?? '') !== '|') {
        throw new RuntimeException('Expected ODT line-numbering configuration metadata to be preserved for WordPress review');
    }
    if (($result['importReport']['trackedChanges']['count'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT tracked changes to be reported');
    }
    if (($result['importReport']['contentDeclarations']['tableTrackedChangeCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT tracked table changes to be reported as content declarations');
    }
    if (($result['importReport']['content']['tableTrackedChangeCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT tracked table changes to be counted in content metadata');
    }
    $tableChangesById = $result['contentDeclarations']['tableTrackedChangesById'] ?? [];
    if (($tableChangesById['tbl-review-status']['actionType'] ?? '') !== 'cell-content-change'
        || ($tableChangesById['tbl-review-status']['action']['attributes']['cellAddress'] ?? '') !== 'Review.B2'
        || ($tableChangesById['tbl-review-status']['action']['previous'][0]['attributes']['stringValue'] ?? '') !== 'Draft') {
        throw new RuntimeException('Expected ODT tracked table cell-content-change metadata to survive import review');
    }
    if (($tableChangesById['tbl-delete-stale-row']['actionType'] ?? '') !== 'deletion'
        || ($tableChangesById['tbl-delete-stale-row']['action']['attributes']['position'] ?? '') !== 'Review.4'
        || ($tableChangesById['tbl-delete-stale-row']['rejectingChangeId'] ?? '') !== 'tbl-review-status') {
        throw new RuntimeException('Expected ODT tracked table deletion metadata to survive import review');
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
    if (($result['importReport']['content']['chartObjectCount'] ?? 0) !== 1 || ($result['importReport']['content']['chartMetadataCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT chart object metadata to be counted in the import report');
    }
    if (($result['importReport']['content']['chartTitleCount'] ?? 0) !== 1
        || ($result['importReport']['content']['chartAxisCount'] ?? 0) !== 2
        || ($result['importReport']['content']['chartLegendCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT chart title, axis, and legend metadata to be counted in the import report');
    }
    if (($result['importReport']['content']['missingEmbeddedObjectCount'] ?? 0) !== 0) {
        throw new RuntimeException('Expected ODT embedded object package parts to be present');
    }
    if (!str_contains($blocks, '<span class="odf-embedded-object odf-object-ole" data-odf-object-type="ole" data-odf-object-href="./Object%202" data-odf-object-path="Object 2" data-odf-object-source-part="Object 2/" data-odf-object-media-type="application/vnd.oasis.opendocument.spreadsheet" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="10" data-odf-object-can-expose-bytes="false">Source spreadsheet</span>')) {
        throw new RuntimeException('Expected ODT object-ole frame to render as a WordPress review placeholder');
    }
    if (!str_contains($blocks, '<span class="odf-embedded-object odf-object-chart" data-odf-object-type="chart" data-odf-object-href="./Object%203" data-odf-object-path="Object 3" data-odf-object-source-part="Object 3/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="' . strlen($chartObjectXml) . '" data-odf-object-can-expose-bytes="false" data-odf-chart-source-part="Object 3/content.xml" data-odf-chart-class="bar" data-odf-chart-cell-range="Review.A1:Review.B4" data-odf-chart-data-source-has-labels="both" data-odf-chart-series-count="1" data-odf-chart-axis-count="2" data-odf-chart-title="Review chart title" data-odf-chart-legend-position="end" data-odf-chart-legend-align="center" data-odf-chart-categories-range="Review.A2:Review.A4">Source chart placeholder</span>')) {
        throw new RuntimeException('Expected ODT chart object frame to render as a WordPress review placeholder');
    }
    if (str_contains($blocks, 'OLEPAYLOAD')) {
        throw new RuntimeException('Expected ODT object-ole payload bytes to stay out of WordPress output');
    }
    if (str_contains($blocks, 'chart:bar')) {
        throw new RuntimeException('Expected ODT chart object XML to stay out of WordPress output');
    }
    if (!str_contains($blocks, '<img src="Pictures/source%20hero.png" alt="ODT source hero alt" title="Source hero" width="6cm" height="3.5cm" data-odf-image-xlink-type="simple" data-odf-image-xlink-show="embed" data-odf-image-xlink-actuate="onLoad" data-odf-frame-name="Source hero" data-odf-frame-style-name="HeroFrame" data-odf-frame-anchor-type="paragraph" data-odf-frame-anchor-page-number="2" data-odf-frame-x="1.2cm" data-odf-frame-y="2.4cm" data-odf-frame-z-index="5" data-odf-frame-layer="review-media" data-odf-frame-layer-exists="true" data-odf-frame-layer-display="screen" data-odf-frame-layer-protected="true"/>')) {
        throw new RuntimeException('Expected ODT image dimensions, xlink metadata, and frame anchor metadata to render in WordPress blocks');
    }
    if (($result['importReport']['content']['frameCaptionCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT draw:caption frames to be counted in the import report');
    }
    if (!str_contains($blocks, '<figure class="wp-block-image odf-frame-caption" data-odf-frame-caption-source="draw:caption" data-odf-frame-caption-frame-name="Source hero"><img src="Pictures/source%20hero.png" alt="ODT source hero alt" title="Source hero" width="6cm" height="3.5cm" data-odf-image-xlink-type="simple" data-odf-image-xlink-show="embed" data-odf-image-xlink-actuate="onLoad" data-odf-frame-name="Source hero" data-odf-frame-style-name="HeroFrame" data-odf-frame-anchor-type="paragraph" data-odf-frame-anchor-page-number="2" data-odf-frame-x="1.2cm" data-odf-frame-y="2.4cm" data-odf-frame-z-index="5" data-odf-frame-layer="review-media" data-odf-frame-layer-exists="true" data-odf-frame-layer-display="screen" data-odf-frame-layer-protected="true"/><figcaption>Figure 2: ODT source hero reviewed.</figcaption></figure>')) {
        throw new RuntimeException('Expected ODT draw:caption figure metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<div class="odf-text-box" data-odf-frame-name="Reviewer aside" data-odf-frame-style-name="AsideFrame" data-odf-frame-anchor-type="paragraph" data-odf-frame-anchor-page-number="3" data-odf-frame-x="2cm" data-odf-frame-y="5cm" data-odf-frame-width="7cm" data-odf-frame-height="2cm" data-odf-frame-z-index="6" data-odf-frame-layer="draft-notes" data-odf-frame-layer-exists="true" data-odf-frame-layer-display="none" data-odf-frame-layer-hidden="true"><p>Anchored text box note for reviewers.</p></div>')) {
        throw new RuntimeException('Expected ODT text-box frame metadata to render in WordPress blocks');
    }
    if (str_contains($blocks, '../Pictures/source%20hero.png')) {
        throw new RuntimeException('Expected ODT parent-relative image hrefs to normalize before WordPress output');
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
    if (!str_contains($blocks, '<td class="odf-table-cell-value odf-table-cell-formula odf-table-cell-annotation odf-covered-cell-source" data-odf-cell-formula="of:=COUNT([.A2:.B2])" data-odf-cell-value-type="float" data-odf-cell-value="2" data-odf-cell-annotation-count="1" data-odf-cell-annotation-authors="Sheet Reviewer" data-odf-cell-annotation-dates="2026-06-09T01:11:00Z" data-odf-cell-annotation-text-count="1" data-odf-covered-cell-count="1" data-odf-covered-cell-source-columns="1" data-odf-covered-cell-style-names="CoveredAuditCell" data-odf-covered-cell-text-count="1" data-odf-covered-cell-value-count="1" colspan="2"><p>Ready for block import review</p></td>')) {
        throw new RuntimeException('Expected ODT calculated table colspan and covered-cell metadata to survive WordPress table handoff');
    }
    if (($result['importReport']['content']['tableCellAnnotationCount'] ?? 0) !== 1 || str_contains($blocks, 'Confirm imported source status.')) {
        throw new RuntimeException('Expected ODT table-cell annotations to stay review metadata outside visible WordPress cell text');
    }
    if (($result['importReport']['content']['tableStyledCellCount'] ?? 0) !== 2
        || ($result['importReport']['content']['tableDataStyledCellCount'] ?? 0) !== 1
        || ($result['importReport']['content']['tableDataStyleDefinitionCellCount'] ?? 0) !== 1
        || ($result['importReport']['content']['tableProtectedCellCount'] ?? 0) !== 1
        || ($result['importReport']['content']['tablePrintHiddenCellCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT styled/protected/print-hidden table cell metadata to be counted in the import report');
    }
    if (($result['importReport']['styles']['styleMapCount'] ?? 0) !== 1
        || ($result['importReport']['styles']['dataStyleCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT table-cell style-map and data-style metadata to be counted in the import report');
    }
    if (($result['styles']['ReviewStatusCell']['tableCellProperties']['backgroundColor'] ?? '') !== '#fff4cc') {
        throw new RuntimeException('Expected ODT table-cell background style to survive style parsing');
    }
    if (($result['styles']['ReviewStatusCell']['dataStyleName'] ?? '') !== 'ReviewCurrencyFormat') {
        throw new RuntimeException('Expected ODT table-cell data-style-name metadata to survive style parsing');
    }
    if (($result['dataStyles']['ReviewCurrencyFormat']['type'] ?? '') !== 'currency'
        || ($result['dataStyles']['ReviewCurrencyFormat']['formatSignature'] ?? '') !== 'currency-symbol:$|number[decimalPlaces=2,grouping=true,minIntegerDigits=1]|text: reviewed') {
        throw new RuntimeException('Expected ODT currency data-style grammar to survive style parsing');
    }
    $reviewTable = null;
    foreach ($result['document']->children as $block) {
        if ($block instanceof \PortLibs\Pandoc\AstNode && $block->type === 'table') {
            $reviewTable = $block;
            break;
        }
    }
    if (!$reviewTable instanceof \PortLibs\Pandoc\AstNode || ($reviewTable->attr('tableGeometry')['caption'] ?? '') !== 'Table 2: Source package review grid') {
        throw new RuntimeException('Expected following ODT table-caption paragraph to survive table geometry review packets');
    }
    $captionSource = $reviewTable->attr('captionSource') ?? [];
    if (($captionSource['source'] ?? '') !== 'odf-table-caption-paragraph' || ($captionSource['sourceElement'] ?? '') !== 'text:p' || ($captionSource['sourcePosition'] ?? '') !== 'following-table') {
        throw new RuntimeException('Expected following ODT table-caption paragraph provenance on table geometry review packets');
    }
    $reviewCoverage = $reviewTable->attr('tableGeometry')['coverage'] ?? [];
    $itemCellAttributes = $reviewCoverage[0]['sourceAttributes']['htmlAttributes'] ?? [];
    if (($itemCellAttributes['data-odf-cell-style-name'] ?? '') !== 'ReviewDefaultCell'
        || ($itemCellAttributes['data-odf-cell-default-style-name'] ?? '') !== 'ReviewDefaultCell'
        || ($itemCellAttributes['data-odf-cell-default-style-source'] ?? '') !== 'row'
        || ($itemCellAttributes['data-odf-cell-background-color'] ?? '') !== '#e6ffed') {
        throw new RuntimeException('Expected ODT row default-cell-style-name metadata to survive table geometry review packets');
    }
    $statusCellAttributes = $reviewCoverage[1]['sourceAttributes']['htmlAttributes'] ?? [];
    if (($statusCellAttributes['data-odf-cell-style-name'] ?? '') !== 'ReviewStatusCell'
        || ($statusCellAttributes['data-odf-cell-data-style-name'] ?? '') !== 'ReviewCurrencyFormat'
        || ($statusCellAttributes['data-odf-cell-data-style-type'] ?? '') !== 'currency'
        || ($statusCellAttributes['data-odf-cell-data-style-component-count'] ?? '') !== '3'
        || ($statusCellAttributes['data-odf-cell-data-style-signature'] ?? '') !== 'currency-symbol:$|number[decimalPlaces=2,grouping=true,minIntegerDigits=1]|text: reviewed'
        || ($statusCellAttributes['data-odf-cell-background-color'] ?? '') !== '#fff4cc'
        || ($statusCellAttributes['data-odf-cell-protect'] ?? '') !== 'protected'
        || ($statusCellAttributes['data-odf-cell-style-map-1-apply-style-name'] ?? '') !== 'ReadyCell') {
        throw new RuntimeException('Expected ODT styled cell metadata to survive table geometry review packets');
    }
    $calculatedCellAttributes = $reviewCoverage[2]['sourceAttributes']['htmlAttributes'] ?? [];
    if (($calculatedCellAttributes['data-odf-cell-formula'] ?? '') !== 'of:=COUNT([.A2:.B2])' || ($calculatedCellAttributes['data-odf-cell-value'] ?? '') !== '2') {
        throw new RuntimeException('Expected ODT calculated cell metadata to survive table geometry review packets');
    }
    if (($calculatedCellAttributes['data-odf-covered-cell-count'] ?? '') !== '1'
        || ($calculatedCellAttributes['data-odf-covered-cell-style-names'] ?? '') !== 'CoveredAuditCell') {
        throw new RuntimeException('Expected ODT covered-cell provenance to survive table geometry review packets');
    }
    if (($result['importReport']['content']['tableCoveredCellCount'] ?? 0) !== 1
        || ($result['importReport']['content']['tableCoveredCellMetadataCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT covered-cell provenance to be counted in the import report');
    }
    if (!str_contains($blocks, 'data-odf-cell-style-name="ReviewDefaultCell"')
        || !str_contains($blocks, 'data-odf-cell-default-style-name="ReviewDefaultCell"')
        || !str_contains($blocks, 'data-odf-cell-default-style-source="row"')) {
        throw new RuntimeException('Expected ODT row default table cell style metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'data-odf-table-print-range-count="2" data-odf-table-print-ranges="&#039;Review Sheet&#039;.A1:&#039;Review Sheet&#039;.B2;Review.D1:Review.D4"')) {
        throw new RuntimeException('Expected ODT table print-range metadata to render in WordPress blocks');
    }
    if (($result['importReport']['content']['tableScenarioCount'] ?? 0) !== 1
        || ($result['importReport']['content']['activeTableScenarioCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT table scenario metadata to be counted in the import report');
    }
    if (!str_contains($blocks, 'data-odf-table-scenario-count="1" data-odf-table-active-scenario-count="1" data-odf-table-scenario-names="ReadyImport" data-odf-table-scenario-ranges="&#039;Review Sheet&#039;.A1:&#039;Review Sheet&#039;.B2;Review.D1:Review.D4"')) {
        throw new RuntimeException('Expected ODT table scenario metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, 'class="odf-table-cell-style odf-table-cell-background odf-table-cell-protected odf-table-cell-print-hidden odf-table-cell-vertical-align-middle odf-table-cell-data-style odf-table-cell-style-map"')
        || !str_contains($blocks, 'data-odf-cell-data-style-name="ReviewCurrencyFormat"')
        || !str_contains($blocks, 'data-odf-cell-data-style-type="currency"')
        || !str_contains($blocks, 'data-odf-cell-data-style-component-count="3"')
        || !str_contains($blocks, 'data-odf-cell-data-style-signature="currency-symbol:$|number[decimalPlaces=2,grouping=true,minIntegerDigits=1]|text: reviewed"')
        || !str_contains($blocks, 'data-odf-cell-style-map-count="1"')
        || !str_contains($blocks, 'data-odf-cell-style-map-1-condition="cell-content()=&quot;Status&quot;"')
        || !str_contains($blocks, 'data-odf-cell-style-map-1-apply-style-name="ReadyCell"')
        || !str_contains($blocks, 'data-odf-cell-style-map-1-base-cell-address="Review.B1"')) {
        throw new RuntimeException('Expected ODT styled table cell metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<table class="odf-table-scenario odf-table-template" data-odf-table-name="Review" data-odf-table-print-range-count="2" data-odf-table-print-ranges="&#039;Review Sheet&#039;.A1:&#039;Review Sheet&#039;.B2;Review.D1:Review.D4" data-odf-table-scenario-count="1" data-odf-table-active-scenario-count="1" data-odf-table-scenario-names="ReadyImport" data-odf-table-scenario-ranges="&#039;Review Sheet&#039;.A1:&#039;Review Sheet&#039;.B2;Review.D1:Review.D4" data-odf-table-style-name="ReviewTable" data-odf-table-template-name="ReviewTemplate" data-odf-table-template-exists="true" data-odf-table-template-style-count="9" data-odf-table-protected="true" data-odf-table-protection-key-present="true" data-odf-table-protection-key-digest-algorithm="urn:odf:sha1">')) {
        throw new RuntimeException('Expected ODT named protected table metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption odf-table-caption" data-odf-table-caption-source="following-paragraph" data-odf-table-caption-style-name="Table"><p>Table 2: Source package review grid</p></figcaption>')) {
        throw new RuntimeException('Expected following ODT table-caption paragraph to render as the review table caption');
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
echo 'dataStyleCount=' . count($result['dataStyles']) . "\n";
echo 'pageLayoutCount=' . count($result['pageLayouts']) . "\n";
echo 'masterPageCount=' . count($result['masterPages']) . "\n";
echo 'settingsSets=' . ($result['settings']['count'] ?? 0) . "\n";
echo 'trackedTableChanges=' . ($result['importReport']['contentDeclarations']['tableTrackedChangeCount'] ?? 0) . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
