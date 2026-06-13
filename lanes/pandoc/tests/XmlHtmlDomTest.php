<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'loads safe XML documents and preserves namespace attributes' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            '<pkg xmlns="urn:packet"><item xml:lang="en">Review &amp; Import</item></pkg>',
            'review packet XML'
        );

        $root = $dom->documentElement;
        $item = $dom->getElementsByTagNameNS('urn:packet', 'item')->item(0);

        $t->true($root instanceof DOMElement);
        $t->same('pkg', $root->localName);
        $t->same('urn:packet', $root->namespaceURI);
        $t->true($item instanceof DOMElement);
        $t->same('en', $item->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        $t->same('Review & Import', $item->textContent);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<pkg><item></pkg>', 'broken XML'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<!DOCTYPE pkg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><pkg>&xxe;</pkg>', 'unsafe XML'));
    },
    'allows XML declarations but rejects XML processing instructions' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            '<?xml version="1.0" encoding="UTF-8"?><pkg><item>Review packet</item></pkg>',
            'declared review packet XML',
            preserveWhiteSpace: false
        );

        $t->true($dom->documentElement instanceof DOMElement);
        $t->same('pkg', $dom->documentElement->tagName);
        $t->same('Review packet', $dom->documentElement->textContent);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument(
            '<?xml-stylesheet href="https://example.invalid/review.xsl"?><pkg><item>review</item></pkg>',
            'stylesheet XML'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument(
            '<?xml version="1.0"?><pkg><?review href="file:///etc/passwd"?><item>review</item></pkg>',
            'review PI XML'
        ));
    },
    'queries namespaced XML DOM nodes for package reader handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<pkg:package xmlns:pkg="urn:pkg" xmlns:w="urn:word" xmlns:rel="urn:relationship" rel:id="root">
  <pkg:metadata>
    <w:title xml:lang="en">  Review
      Packet </w:title>
    <w:title xml:lang="fr">Ignored</w:title>
  </pkg:metadata>
  <pkg:body>
    <w:p rel:id="rId1"> First <w:r> run </w:r></w:p>
    <pkg:p>Package paragraph</pkg:p>
  </pkg:body>
</pkg:package>
XML, 'package reader XML');
        $root = XmlHtmlDom::rootElement($dom, 'package', 'urn:pkg');
        $metadata = $root instanceof DOMElement ? XmlHtmlDom::firstChildElement($root, 'metadata', 'urn:pkg') : null;
        $body = $root instanceof DOMElement ? XmlHtmlDom::firstChildElement($root, 'body', 'urn:pkg') : null;
        $titles = $root instanceof DOMElement ? XmlHtmlDom::descendantElements($root, 'title', 'urn:word') : [];
        $paragraph = $body instanceof DOMElement ? XmlHtmlDom::firstDescendantElement($body, 'p', 'urn:word') : null;

        $t->true($root instanceof DOMElement);
        $t->true(XmlHtmlDom::elementMatches($root, 'package', 'urn:pkg'));
        $t->true(XmlHtmlDom::elementMatches($root, null, 'urn:pkg'));
        $t->true(!XmlHtmlDom::elementMatches($root, 'package', 'urn:word'));
        $t->same($root, XmlHtmlDom::rootElement($dom, null, 'urn:pkg'));
        $t->same(null, XmlHtmlDom::rootElement($dom, 'package', 'urn:word'));
        $t->true($metadata instanceof DOMElement);
        $t->true($body instanceof DOMElement);
        $t->same(2, count($titles));
        $t->same('Review Packet', XmlHtmlDom::normalizedText($titles[0]));
        $t->same('en', XmlHtmlDom::attribute($titles[0], 'lang', 'http://www.w3.org/XML/1998/namespace'));
        $t->same('root', XmlHtmlDom::attribute($root, 'id', 'urn:relationship'));
        $t->same(null, XmlHtmlDom::attribute($root, 'missing', 'urn:relationship'));
        $t->same(0, count($root instanceof DOMElement ? XmlHtmlDom::childElements($root, 'p', 'urn:word') : []));
        $t->true($paragraph instanceof DOMElement);
        $t->same('rId1', $paragraph instanceof DOMElement ? XmlHtmlDom::attribute($paragraph, 'id', 'urn:relationship') : null);
        $t->same('First run', $paragraph instanceof DOMElement ? XmlHtmlDom::normalizedText($paragraph) : null);
    },
    'summarizes jats and bits front matter plus body diagnostics without reader parity claims' => static function (TestRunner $t): void {
        $jats = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article" dtd-version="1.3" xml:lang="en" xmlns:xlink="http://www.w3.org/1999/xlink">
  <front>
    <journal-meta>
      <journal-title-group><journal-title>Journal &amp; Review</journal-title></journal-title-group>
      <publisher><publisher-name>Port Libs Press</publisher-name></publisher>
    </journal-meta>
    <article-meta>
      <article-id pub-id-type="doi">10.5555/review.42</article-id>
      <title-group>
        <article-title>Import <italic>Safety</italic> Study</article-title>
        <subtitle>Escaping &amp; attributes</subtitle>
      </title-group>
      <contrib-group>
        <contrib contrib-type="author"><name><surname>Zed</surname><given-names>Ada</given-names></name><xref ref-type="aff" rid="aff1"/></contrib>
        <contrib contrib-type="editor"><collab>Review Board</collab></contrib>
      </contrib-group>
      <aff id="aff1"><label>1</label><institution>Port Libs Lab</institution></aff>
      <pub-date date-type="pub"><year>2026</year><month>06</month><day>12</day></pub-date>
      <abstract><p>Native PHP <bold>review</bold> packet.</p></abstract>
      <kwd-group><kwd>XML</kwd><kwd>JATS</kwd></kwd-group>
    </article-meta>
  </front>
  <body>
    <sec id="s1" sec-type="intro"><title>Scope</title><p>Body <xref ref-type="bibr" rid="r1">[1]</xref> <xref ref-type="fig" rid="f1 missing-fig">Fig. 1</xref>.</p><sec id="s1-1" sec-type="methods"><title>Nested</title><p>Nested paragraph <xref ref-type="table" rid="t1">Table 1</xref>.</p></sec></sec>
    <fig id="f1"><label>Figure 1</label><caption><p>Figure caption</p></caption><graphic xlink:href="figures/f1.png"/></fig>
    <table-wrap id="t1"><label>Table 1</label><caption><p>Table caption</p></caption><table><tbody><tr><td>Cell</td></tr></tbody></table></table-wrap>
  </body>
  <back><ref-list><ref id="r1"><label>1</label></ref></ref-list></back>
</article>
XML, 'JATS article XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($jats);

        $t->same('xml-html5-jats-dom', $packet['formatFamily']);
        $t->same('jats', $packet['format']);
        $t->same('jats-bits-front-matter-and-body-diagnostics-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'body-sections-review-only',
            'references-review-only',
            'figures-review-only',
            'table-wraps-review-only',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(5, $packet['directReaderDiagnosticCount']);
        $t->same(false, $packet['directReaderDiagnostics'][0]['directReaderParity'] ?? null);
        $t->same(true, $packet['directReaderDiagnostics'][0]['coveredByPacket'] ?? null);
        $t->same('jats', $packet['directReaderDiagnostics'][0]['details']['format'] ?? null);
        $t->same(false, $packet['directReaderDiagnostics'][1]['coveredByPacket'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][1]['details']['sectionCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['referenceCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][3]['details']['figureCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][4]['details']['tableWrapCount'] ?? null);
        $t->same('article', $packet['rootName']);
        $t->same('research-article', $packet['documentType']);
        $t->same('1.3', $packet['dtdVersion']);
        $t->same('en', $packet['language']);
        $t->same('en', $packet['rootAttributes']['xml:lang'] ?? null);
        $t->same('article-meta', $packet['metadataRoot']);
        $t->same('Import Safety Study', $packet['title']);
        $t->same('Escaping & attributes', $packet['subtitle']);
        $t->same('Journal & Review', $packet['journalTitle']);
        $t->same('Port Libs Press', $packet['publisherName']);
        $t->same([['element' => 'article-id', 'type' => 'doi', 'value' => '10.5555/review.42']], $packet['articleIds']);
        $t->same(1, $packet['identifierCount']);
        $t->same('Native PHP review packet.', $packet['abstractText']);
        $t->same(['XML', 'JATS'], $packet['keywords']);
        $t->same(2, $packet['contributorCount']);
        $t->same(['Ada Zed', 'Review Board'], $packet['contributorNames']);
        $t->same(['author', 'editor'], $packet['contributorRoles']);
        $t->same(['aff1'], $packet['contributors'][0]['xrefTargets'] ?? null);
        $t->same('2026-06-12', $packet['publicationDates'][0]['iso'] ?? null);
        $t->same(true, $packet['hasBody']);
        $t->same('body', $packet['bodyRoot']);
        $t->same(2, $packet['sectionCount']);
        $t->same(['Scope', 'Nested'], $packet['sectionTitles']);
        $t->same('s1', $packet['sections'][0]['id'] ?? null);
        $t->same(1, $packet['sections'][0]['depth'] ?? null);
        $t->same('intro', $packet['sections'][0]['type'] ?? null);
        $t->same(1, $packet['sections'][0]['directParagraphCount'] ?? null);
        $t->same(2, $packet['sections'][0]['paragraphCount'] ?? null);
        $t->same(1, $packet['sections'][0]['childSectionCount'] ?? null);
        $t->same('s1', $packet['sections'][1]['parentId'] ?? null);
        $t->same(2, $packet['sections'][1]['depth'] ?? null);
        $t->same('methods', $packet['sections'][1]['type'] ?? null);
        $t->same('body', $packet['bodySummary']['bodyRoot'] ?? null);
        $t->same(4, $packet['bodySummary']['paragraphCount'] ?? null);
        $t->same(2, $packet['bodySummary']['sectionDepthMax'] ?? null);
        $t->same(['intro', 'methods'], $packet['bodySummary']['sectionTypes'] ?? null);
        $t->same(3, $packet['bodySummary']['xrefCount'] ?? null);
        $t->same(['missing-fig'], $packet['bodySummary']['unresolvedXrefTargets'] ?? null);
        $t->same(['f1', 'missing-fig'], $packet['bodySummary']['figureReferenceTargets'] ?? null);
        $t->same(['t1'], $packet['bodySummary']['tableWrapReferenceTargets'] ?? null);
        $t->same(['aff1', 'r1', 'f1', 'missing-fig', 't1'], $packet['xrefTargets']);
        $t->same(4, $packet['xrefCount']);
        $t->same(['missing-fig'], $packet['unresolvedXrefTargets']);
        $t->same('fig', $packet['xrefs'][2]['refType'] ?? null);
        $t->same(['missing-fig'], $packet['xrefs'][2]['missingTargets'] ?? null);
        $t->same(['r1'], $packet['referenceIds']);
        $t->same('1', $packet['references'][0]['label'] ?? null);
        $t->same(1, $packet['references'][0]['referenceCount'] ?? null);
        $t->same(['f1'], $packet['figureIds']);
        $t->same('Figure 1', $packet['figures'][0]['label'] ?? null);
        $t->same('Figure caption', $packet['figures'][0]['caption'] ?? null);
        $t->same(['figures/f1.png'], $packet['figures'][0]['graphicHrefs'] ?? null);
        $t->same(1, $packet['figures'][0]['referenceCount'] ?? null);
        $t->same([], $packet['unreferencedFigureIds']);
        $t->same(['t1'], $packet['tableWrapIds']);
        $t->same('Table 1', $packet['tableWraps'][0]['label'] ?? null);
        $t->same('Table caption', $packet['tableWraps'][0]['caption'] ?? null);
        $t->same(1, $packet['tableWraps'][0]['rowCount'] ?? null);
        $t->same(1, $packet['tableWraps'][0]['referenceCount'] ?? null);
        $t->same([], $packet['unreferencedTableWrapIds']);
        $t->same(0, $packet['bookPartCount']);
        json_encode($packet, JSON_THROW_ON_ERROR);

        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="monograph" xml:lang="fr">
  <book-meta>
    <book-id book-id-type="isbn">978-1-55555-042-0</book-id>
    <title-group><book-title>Review Book</book-title><subtitle>Bounded XML metadata</subtitle></title-group>
    <contrib-group><contrib contrib-type="editor"><string-name>Camille Editor</string-name></contrib></contrib-group>
    <pub-date pub-type="ppub"><year>2025</year></pub-date>
  </book-meta>
  <book-body><book-part id="ch1" book-part-type="chapter"><book-part-meta><title-group><title>Chapter One</title></title-group></book-part-meta><body><sec id="ch1s1"><title>Inside</title><p>Chapter body.</p></sec></body></book-part></book-body>
</book>
XML, 'BITS book XML', preserveWhiteSpace: false);
        $bitsPacket = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');

        $t->same('bits', $bitsPacket['format']);
        $t->same('book', $bitsPacket['rootName']);
        $t->same('monograph', $bitsPacket['documentType']);
        $t->same('fr', $bitsPacket['language']);
        $t->same('book-meta', $bitsPacket['metadataRoot']);
        $t->same('Review Book', $bitsPacket['title']);
        $t->same('Bounded XML metadata', $bitsPacket['subtitle']);
        $t->same([['element' => 'book-id', 'type' => 'isbn', 'value' => '978-1-55555-042-0']], $bitsPacket['bookIds']);
        $t->same(['Camille Editor'], $bitsPacket['contributorNames']);
        $t->same('2025', $bitsPacket['publicationDates'][0]['iso'] ?? null);
        $t->same('book-body', $bitsPacket['bodyRoot']);
        $t->same(['Inside'], $bitsPacket['sectionTitles']);
        $t->same(1, $bitsPacket['bodySummary']['bookPartCount'] ?? null);
        $t->same('chapter', $bitsPacket['bookParts'][0]['type'] ?? null);
        $t->same('Chapter One', $bitsPacket['bookParts'][0]['title'] ?? null);
        $t->same('body', $bitsPacket['bookParts'][0]['bodyRoot'] ?? null);
        $t->same(1, $bitsPacket['bookParts'][0]['sectionCount'] ?? null);
        $t->same(1, $bitsPacket['bookPartCount']);
        $t->same(false, $bitsPacket['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'book-parts-review-only',
        ], $bitsPacket['directReaderDiagnosticCodes']);
        $t->same(2, $bitsPacket['directReaderDiagnosticCount']);
        $t->same('bits', $bitsPacket['directReaderDiagnostics'][0]['details']['format'] ?? null);
        $t->same(false, $bitsPacket['directReaderDiagnostics'][1]['coveredByPacket'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][1]['details']['bookPartCount'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeJatsFrontMatter($jats, 'xml'));
        json_encode($bitsPacket, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook structure review packets without direct reader parity claims' => static function (TestRunner $t): void {
        $docbook = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.2" xml:lang="en">
  <info>
    <title>DocBook Review Article</title>
    <subtitle>Structure packet</subtitle>
    <author><personname><firstname>Ada</firstname><surname>Review</surname></personname></author>
    <editor><orgname>Editorial Board</orgname></editor>
    <biblioid class="doi">10.5555/docbook.42</biblioid>
    <abstract><para>Native PHP review packet.</para></abstract>
  </info>
  <section xml:id="intro" role="scope">
    <title>Scope</title>
    <para>Body <xref linkend="fig1"/> text.</para>
    <note xml:id="n1"><title>Review Note</title><para>Check this.</para></note>
    <figure xml:id="fig1">
      <title>Figure A</title>
      <mediaobject><imageobject><imagedata fileref="images/a.png"/></imageobject></mediaobject>
    </figure>
    <informaltable xml:id="tbl1"><tgroup cols="1"><tbody><row><entry>Cell</entry></row></tbody></tgroup></informaltable>
    <section xml:id="nested"><title>Nested</title><simpara>Nested text.</simpara></section>
  </section>
  <bibliography><biblioentry xml:id="ref1"><title>Reference</title></biblioentry></bibliography>
  <para><link linkend="ref1">Reference</link><link xlink:href="https://example.invalid/review">Remote</link></para>
</article>
XML, 'DocBook 5 structure XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeDocBookStructure($docbook, 'docbook5');

        $t->same('xml-html5-docbook-dom', $packet['formatFamily']);
        $t->same('docbook5', $packet['format']);
        $t->same('docbook-structure-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(['docbook-direct-reader-incomplete', 'docbook-body-conversion-review-only'], $packet['unsupportedDiagnostics']);
        $t->same('article', $packet['rootName']);
        $t->same('5.2', $packet['docbookVersion']);
        $t->same('http://docbook.org/ns/docbook', $packet['namespaceUri']);
        $t->same('en', $packet['language']);
        $t->same('en', $packet['rootAttributes']['xml:lang'] ?? null);
        $t->same('info', $packet['metadataRoot']);
        $t->same('DocBook Review Article', $packet['title']);
        $t->same('Structure packet', $packet['subtitle']);
        $t->same('Native PHP review packet.', $packet['abstractText']);
        $t->same([['element' => 'biblioid', 'type' => 'doi', 'value' => '10.5555/docbook.42']], $packet['identifiers']);
        $t->same(1, $packet['identifierCount']);
        $t->same(['Ada Review', 'Editorial Board'], $packet['contributorNames']);
        $t->same(['author', 'editor'], $packet['contributorRoles']);
        $t->same(2, $packet['sectionCount']);
        $t->same(['Scope', 'Nested'], $packet['sectionTitles']);
        $t->same('intro', $packet['sections'][0]['id'] ?? null);
        $t->same('scope', $packet['sections'][0]['role'] ?? null);
        $t->same(3, $packet['sections'][0]['paragraphCount'] ?? null);
        $t->same(1, $packet['sections'][0]['directParagraphCount'] ?? null);
        $t->same(1, $packet['sections'][0]['childSectionCount'] ?? null);
        $t->same(1, $packet['sections'][0]['figureCount'] ?? null);
        $t->same(1, $packet['sections'][0]['tableCount'] ?? null);
        $t->same(1, $packet['sections'][0]['admonitionCount'] ?? null);
        $t->same(['fig1'], $packet['figureIds']);
        $t->same(1, $packet['figureCount']);
        $t->same('Figure A', $packet['figures'][0]['title'] ?? null);
        $t->same(['tbl1'], $packet['tableIds']);
        $t->same(1, $packet['tableCount']);
        $t->same(['n1'], $packet['admonitionIds']);
        $t->same('note', $packet['admonitions'][0]['type'] ?? null);
        $t->same(['fig1', 'ref1'], $packet['xrefTargets']);
        $t->same(['https://example.invalid/review'], $packet['externalTargets']);
        $t->same(1, $packet['bibliographyCount']);
        $t->same(1, $packet['bibliographyEntryCount']);
        $t->same(1, $packet['mediaObjectCount']);
        $t->same(1, $packet['imageObjectCount']);
        $t->same(['images/a.png'], $packet['imageDataRefs']);
        json_encode($packet, JSON_THROW_ON_ERROR);

        $docbook4 = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book lang="de">
  <bookinfo>
    <title>Legacy Book</title>
    <isbn>978-1-55555-042-0</isbn>
    <editor><firstname>Eva</firstname><surname>Alt</surname></editor>
  </bookinfo>
  <chapter id="ch1"><title>Chapter One</title><para>Text.</para></chapter>
</book>
XML, 'DocBook 4 structure XML', preserveWhiteSpace: false);
        $legacyPacket = XmlHtmlDom::summarizeDocBookStructure($docbook4, 'docbook4');

        $t->same('docbook4', $legacyPacket['format']);
        $t->same('book', $legacyPacket['rootName']);
        $t->same(null, $legacyPacket['namespaceUri']);
        $t->same('de', $legacyPacket['language']);
        $t->same('bookinfo', $legacyPacket['metadataRoot']);
        $t->same('Legacy Book', $legacyPacket['title']);
        $t->same([['element' => 'isbn', 'type' => null, 'value' => '978-1-55555-042-0']], $legacyPacket['identifiers']);
        $t->same(['Eva Alt'], $legacyPacket['contributorNames']);
        $t->same(1, $legacyPacket['sectionCount']);
        $t->same('chapter', $legacyPacket['sections'][0]['element'] ?? null);
        $t->same('ch1', $legacyPacket['sections'][0]['id'] ?? null);
        $t->same(1, $legacyPacket['sections'][0]['paragraphCount'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeDocBookStructure($docbook, 'jats'));
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeDocBookStructure($docbook = XmlHtmlDom::loadXmlDocument('<topic><title>Nope</title></topic>', 'non docbook XML')));
        json_encode($legacyPacket, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook bibliography media crosslink diagnostics' => static function (TestRunner $t): void {
        $docbook = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.2">
  <info><title>DocBook Bibliography Media Links</title></info>
  <section xml:id="media-section">
    <title>Media Targets</title>
    <figure xml:id="fig-photo">
      <title>Plate A</title>
      <mediaobject><imageobject><imagedata fileref="media/plate-a.png"/></imageobject></mediaobject>
    </figure>
    <mediaobject id="dup-media"><imageobject><imagedata fileref="media/dup-a.png"/></imageobject></mediaobject>
    <mediaobject id="dup-media"><imageobject><imagedata fileref="media/dup-b.png"/></imageobject></mediaobject>
  </section>
  <bibliography xml:id="refs">
    <biblioentry xml:id="ref-media">
      <author><personname><firstname>Mira</firstname><surname>Lens</surname></personname></author>
      <title>Media Study</title>
      <pubdate>2025</pubdate>
      <para>See <xref linkend="fig-photo missing-media dup-media fig-photo"/>.</para>
    </biblioentry>
    <bibliomixed xml:id="ref-inline">
      <author><personname><firstname>Ira</firstname><surname>Inline</surname></personname></author>
      <citetitle>Inline Media Appendix</citetitle>
      <year>2024</year>
      <mediaobject xml:id="bib-media"><imageobject><imagedata fileref="media/bib-inline.png"/></imageobject></mediaobject>
    </bibliomixed>
  </bibliography>
</article>
XML, 'DocBook bibliography media crosslink XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeDocBookStructure($docbook, 'docbook5');

        $t->same(false, $packet['directReaderParity']);
        $t->same(1, $packet['bibliographyCount']);
        $t->same(2, $packet['bibliographyEntryCount']);
        $t->same('ref-media', $packet['bibliographyEntries'][0]['id'] ?? null);
        $t->same('Media Study', $packet['bibliographyEntries'][0]['title'] ?? null);
        $t->same('2025', $packet['bibliographyEntries'][0]['year'] ?? null);
        $t->same(['Mira Lens'], $packet['bibliographyEntries'][0]['contributorNames'] ?? null);
        $t->same(['fig-photo', 'missing-media', 'dup-media'], $packet['bibliographyEntries'][0]['linkTargets'] ?? null);
        $t->same('ref-inline', $packet['bibliographyEntries'][1]['id'] ?? null);
        $t->same('Inline Media Appendix', $packet['bibliographyEntries'][1]['title'] ?? null);
        $t->same(['media/bib-inline.png'], $packet['bibliographyEntries'][1]['mediaRefs'] ?? null);

        $t->same(1, $packet['bibliographyMediaObjectCount']);
        $t->same('ref-inline', $packet['bibliographyMediaObjects'][0]['entryId'] ?? null);
        $t->same(['media/bib-inline.png'], $packet['bibliographyMediaObjects'][0]['mediaRefs'] ?? null);

        $manifest = $packet['mediaTargetManifest'];
        $t->same(4, $manifest['targetCount']);
        $t->same(['fig-photo', 'dup-media', 'bib-media'], $manifest['targetIds']);
        $t->same(['media/plate-a.png', 'media/dup-a.png', 'media/dup-b.png', 'media/bib-inline.png'], $manifest['mediaRefs']);
        $t->same(false, $manifest['targets'][0]['duplicateId'] ?? null);
        $t->same(true, $manifest['targets'][1]['duplicateId'] ?? null);
        $t->same(true, $manifest['targets'][2]['duplicateId'] ?? null);

        $crosslinks = $packet['bibliographyMediaCrosslinks'];
        $t->same(2, $crosslinks['entryCount']);
        $t->same(['ref-media'], $crosslinks['entriesWithMediaLinks']);
        $t->same(1, $crosslinks['resolvedCount']);
        $t->same(1, $crosslinks['missingCount']);
        $t->same(2, $crosslinks['duplicateCount']);
        $t->same([
            'missing-bibliography-media-target',
            'duplicate-bibliography-media-crosslink',
            'duplicate-media-target-id',
        ], $crosslinks['diagnosticCodes']);
        $t->same('ref-media', $crosslinks['resolved'][0]['entryId'] ?? null);
        $t->same('Media Study', $crosslinks['resolved'][0]['entryTitle'] ?? null);
        $t->same('2025', $crosslinks['resolved'][0]['entryYear'] ?? null);
        $t->same(['Mira Lens'], $crosslinks['resolved'][0]['entryContributorNames'] ?? null);
        $t->same('fig-photo', $crosslinks['resolved'][0]['targetId'] ?? null);
        $t->same('figure', $crosslinks['resolved'][0]['targetElement'] ?? null);
        $t->same(['media/plate-a.png'], $crosslinks['resolved'][0]['mediaRefs'] ?? null);
        $t->same('missing-media', $crosslinks['missing'][0]['targetId'] ?? null);
        $t->same('duplicate-bibliography-media-crosslink', $crosslinks['duplicates'][0]['code'] ?? null);
        $t->same('fig-photo', $crosslinks['duplicates'][0]['targetId'] ?? null);
        $t->same(2, $crosslinks['duplicates'][0]['occurrences'] ?? null);
        $t->same('duplicate-media-target-id', $crosslinks['duplicates'][1]['code'] ?? null);
        $t->same('dup-media', $crosslinks['duplicates'][1]['targetId'] ?? null);
        $t->same(2, $crosslinks['duplicates'][1]['targetCount'] ?? null);
        $t->same(['media/dup-a.png', 'media/dup-b.png'], $crosslinks['duplicates'][1]['mediaRefs'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'recovers HTML5 fragments with list autoclose and void elements' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-id="42">Intro<br>Next<img src="cover.png?x=1&amp;y=2" alt="Cover"></p><ul><li>One<li>Two</ul>',
            'review HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('p', $summary[0]['name']);
        $t->same(['data-id' => '42'], $summary[0]['attributes']);
        $t->same('br', $summary[0]['children'][1]['name']);
        $t->same('img', $summary[0]['children'][3]['name']);
        $t->same(['alt' => 'Cover', 'src' => 'cover.png?x=1&y=2'], $summary[0]['children'][3]['attributes']);
        $t->same('ul', $summary[1]['name']);
        $t->same('li', $summary[1]['children'][0]['name']);
        $t->same('One', $summary[1]['children'][0]['text']);
        $t->same('Two', $summary[1]['children'][1]['text']);
        $t->same('<p data-id="42">Intro<br>Next<img alt="Cover" src="cover.png?x=1&amp;y=2"></p><ul><li>One</li><li>Two</li></ul>', $html);
    },
    'summarizes html break and separator elements for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>Alpha<br id="hard">Beta<wbr data-source="wrap">Gamma</p><hr id="rule" class="review-separator">',
            'break element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/break-elements-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $hardBreak = $paragraph['children'][1];
        $wordBreak = $paragraph['children'][3];
        $rule = $summary[1];

        $t->same('p', $paragraph['name']);
        $t->same('AlphaBetaGamma', $paragraph['text']);
        $t->same('br', $hardBreak['name']);
        $t->same('line-break', $hardBreak['breakElement']);
        $t->same('br', $hardBreak['breakTag']);
        $t->same("\n", $hardBreak['textEquivalent']);
        $t->same(true, $hardBreak['hardBreak']);
        $t->same('hard', $hardBreak['elementId']);
        $t->same('wbr', $wordBreak['name']);
        $t->same('word-break-opportunity', $wordBreak['breakElement']);
        $t->same('', $wordBreak['textEquivalent']);
        $t->same(true, $wordBreak['softBreakOpportunity']);
        $t->same(['source' => 'wrap'], $wordBreak['dataset']);
        $t->same('hr', $rule['name']);
        $t->same('thematic-break', $rule['breakElement']);
        $t->same(true, $rule['blockSeparator']);
        $t->same(['review-separator'], $rule['classList']);
        $t->same('<p>Alpha<br id="hard">Beta<wbr data-source="wrap">Gamma</p><hr class="review-separator" id="rule">', $html);
        $t->contains('<wbr data-source="wrap">', $blocks);
        $t->contains('<hr class="review-separator" id="rule">', $blocks);
        $t->same('/migration/break-elements-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html global attributes and dataset state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="packet" class="alpha  beta alpha" lang="en-US" dir="RTL" title="Review &amp; Source" data-review-id="A-42" data-package-part="word/document.xml" hidden="until-found" translate="no" contenteditable="plaintext-only" draggable="true" spellcheck="false" tabindex="-1" role="doc-chapter region" aria-label="Packet Section"><p class="child">Body</p></section>'
                . '<p data-review-stage="preflight" dir="sideways" translate="maybe" contenteditable="maybe" draggable="maybe" spellcheck="maybe">Fallback</p>'
                . '<table id="review-table" class="data-grid" data-package-part="word/tables.xml"><tr><td>Cell</td></tr></table>',
            'global attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $section = $summary[0];
        $fallback = $summary[1];
        $table = $summary[2];

        $t->same('packet', $section['elementId']);
        $t->same('alpha  beta alpha', $section['classRaw']);
        $t->same(['alpha', 'beta', 'alpha'], $section['classList']);
        $t->same('en-US', $section['languageRaw']);
        $t->same('en-US', $section['language']);
        $t->same('RTL', $section['dirRaw']);
        $t->same('rtl', $section['direction']);
        $t->same('Review & Source', $section['titleAttribute']);
        $t->same('until-found', $section['hiddenRaw']);
        $t->same('until-found', $section['hiddenState']);
        $t->same('no', $section['translateRaw']);
        $t->same(false, $section['translate']);
        $t->same('plaintext-only', $section['contentEditable']);
        $t->same(true, $section['draggable']);
        $t->same(false, $section['spellcheck']);
        $t->same('-1', $section['tabIndexRaw']);
        $t->same(-1, $section['tabIndex']);
        $t->same('doc-chapter region', $section['roleRaw']);
        $t->same(['doc-chapter', 'region'], $section['roles']);
        $t->same(['aria-label' => 'Packet Section'], $section['ariaAttributes']);
        $t->same([
            'data-package-part' => 'word/document.xml',
            'data-review-id' => 'A-42',
        ], $section['dataAttributes']);
        $t->same([
            'packagePart' => 'word/document.xml',
            'reviewId' => 'A-42',
        ], $section['dataset']);
        $t->same('child', $section['children'][0]['classRaw']);
        $t->same(['child'], $section['children'][0]['classList']);

        $t->same('sideways', $fallback['dirRaw']);
        $t->same(null, $fallback['direction']);
        $t->same('maybe', $fallback['translateRaw']);
        $t->same(null, $fallback['translate']);
        $t->same(null, $fallback['contentEditable']);
        $t->same(null, $fallback['draggable']);
        $t->same(null, $fallback['spellcheck']);
        $t->same(['reviewStage' => 'preflight'], $fallback['dataset']);

        $t->same('review-table', $table['elementId']);
        $t->same(['data-grid'], $table['classList']);
        $t->same(['packagePart' => 'word/tables.xml'], $table['dataset']);
        $t->same('table', $table['tablePart']);
        $t->same(
            '<section aria-label="Packet Section" class="alpha  beta alpha" contenteditable="plaintext-only" data-package-part="word/document.xml" data-review-id="A-42" dir="RTL" draggable="true" hidden="until-found" id="packet" lang="en-US" role="doc-chapter region" spellcheck="false" tabindex="-1" title="Review &amp; Source" translate="no"><p class="child">Body</p></section>'
                . '<p contenteditable="maybe" data-review-stage="preflight" dir="sideways" draggable="maybe" spellcheck="maybe" translate="maybe">Fallback</p>'
                . '<table class="data-grid" data-package-part="word/tables.xml" id="review-table"><tr><td>Cell</td></tr></table>',
            $html
        );
    },
    'summarizes html microdata attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="review" itemscope itemtype="https://schema.org/Article ./Local bad&lt;tag" itemid="./articles/42" itemref="headline author missing bad&lt;tag">'
                . '<h1 id="headline" itemprop="headline schema:name bad&lt;tag headline">Title</h1><p id="author" itemprop="author">Ada</p></article>'
                . '<span itemtype="javascript:alert(1)" itemid=" bad id ">Loose</span>',
            'microdata attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/microdata-attribute-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $headline = $article['children'][0];
        $author = $article['children'][1];
        $invalid = $summary[1];

        $t->same('item', $article['microdata']);
        $t->same('', $article['itemScopeRaw']);
        $t->same(true, $article['itemScope']);
        $t->same('https://schema.org/Article ./Local bad<tag', $article['itemTypeRaw']);
        $t->same(['https://schema.org/Article', './Local', 'bad<tag'], $article['itemTypeTokens']);
        $t->same(['https://schema.org/Article', './Local'], $article['itemTypes']);
        $t->same(['bad<tag'], $article['invalidItemTypes']);
        $t->same(false, $article['itemTypeValid']);
        $t->same('./articles/42', $article['itemIdRaw']);
        $t->same('./articles/42', $article['itemId']);
        $t->same(true, $article['itemIdValid']);
        $t->same('headline author missing bad<tag', $article['itemRefRaw']);
        $t->same(['headline', 'author', 'missing', 'bad<tag'], $article['itemRefTokens']);
        $t->same(['headline', 'author', 'missing'], $article['itemRefIds']);
        $t->same(['bad<tag'], $article['invalidItemRefIds']);
        $t->same(false, $article['itemRefValid']);
        $t->same(['headline', 'author'], $article['itemRefResolvedIds']);
        $t->same(['missing'], $article['itemRefMissingIds']);

        $t->same('property', $headline['microdata']);
        $t->same('headline schema:name bad<tag headline', $headline['itemPropRaw']);
        $t->same(['headline', 'schema:name', 'bad<tag', 'headline'], $headline['itemPropTokens']);
        $t->same(['headline', 'schema:name'], $headline['itemProperties']);
        $t->same(['bad<tag'], $headline['invalidItemProperties']);
        $t->same(false, $headline['itemPropValid']);
        $t->same('author', $author['itemPropRaw']);
        $t->same(['author'], $author['itemProperties']);
        $t->same(true, $author['itemPropValid']);

        $t->same('metadata', $invalid['microdata']);
        $t->same(['javascript:alert(1)'], $invalid['itemTypeTokens']);
        $t->same([], $invalid['itemTypes']);
        $t->same(['javascript:alert(1)'], $invalid['invalidItemTypes']);
        $t->same(false, $invalid['itemTypeValid']);
        $t->same(' bad id ', $invalid['itemIdRaw']);
        $t->same('bad id', $invalid['itemId']);
        $t->same(false, $invalid['itemIdValid']);
        $t->same('<article id="review" itemid="./articles/42" itemref="headline author missing bad&lt;tag" itemscope itemtype="https://schema.org/Article ./Local bad&lt;tag"><h1 id="headline" itemprop="headline schema:name bad&lt;tag headline">Title</h1><p id="author" itemprop="author">Ada</p></article><span itemid=" bad id " itemtype="javascript:alert(1)">Loose</span>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/microdata-attribute-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html rdfa semantic attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="review-rdfa" vocab="https://schema.org/" typeof="Article ReviewNewsArticle bad&lt;term" about="./articles/42" prefix="dc: http://purl.org/dc/terms/ schema: https://schema.org/ bad-prefix javascript:alert(1) dangling:" inlist="inlist">'
                . '<h1 property="headline schema:name bad&lt;prop" content="RDFa title">Visible Title</h1>'
                . '<a rel="author next javascript:alert(1)" rev="reviewedBy" resource="#author" href="/authors/ada">Ada</a>'
                . '<span property="datePublished" datatype="xsd:date" content="2026-06-12">June 12</span>'
                . '<span about=" bad id " typeof="javascript:alert(1)">Invalid</span></article>',
            'RDFa semantic review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/rdfa-semantic-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $heading = $article['children'][0];
        $link = $article['children'][1];
        $date = $article['children'][2];
        $invalid = $article['children'][3];

        $t->same('article', $article['name']);
        $t->same('review-rdfa', $article['elementId']);
        $t->same('resource', $article['rdfa']);
        $t->same(['about', 'inlist', 'prefix', 'typeof', 'vocab'], $article['rdfaAttributes']);
        $t->same('https://schema.org/', $article['rdfaVocab']);
        $t->same(true, $article['rdfaVocabValid']);
        $t->same([
            'dc' => 'http://purl.org/dc/terms/',
            'schema' => 'https://schema.org/',
        ], $article['rdfaPrefixes']);
        $t->same([
            ['raw' => 'dc: http://purl.org/dc/terms/', 'prefix' => 'dc', 'iri' => 'http://purl.org/dc/terms/', 'valid' => true],
            ['raw' => 'schema: https://schema.org/', 'prefix' => 'schema', 'iri' => 'https://schema.org/', 'valid' => true],
            ['raw' => 'bad-prefix javascript:alert(1)', 'prefix' => null, 'iri' => 'javascript:alert(1)', 'valid' => false],
            ['raw' => 'dangling:', 'prefix' => 'dangling', 'iri' => null, 'valid' => false],
        ], $article['rdfaPrefixMappings']);
        $t->same(['bad-prefix javascript:alert(1)', 'dangling:'], $article['invalidRdfaPrefixMappings']);
        $t->same(false, $article['rdfaPrefixValid']);
        $t->same(['Article', 'ReviewNewsArticle', 'bad<term'], $article['rdfaTypeofTokens']);
        $t->same(['Article', 'ReviewNewsArticle'], $article['rdfaTypes']);
        $t->same(['bad<term'], $article['invalidRdfaTypes']);
        $t->same(false, $article['rdfaTypeofValid']);
        $t->same('./articles/42', $article['rdfaAbout']);
        $t->same(true, $article['rdfaAboutValid']);
        $t->same('inlist', $article['rdfaInListRaw']);
        $t->same(true, $article['rdfaInList']);

        $t->same('property', $heading['rdfa']);
        $t->same(['content', 'property'], $heading['rdfaAttributes']);
        $t->same(['headline', 'schema:name', 'bad<prop'], $heading['rdfaPropertyTokens']);
        $t->same(['headline', 'schema:name'], $heading['rdfaProperties']);
        $t->same(['bad<prop'], $heading['invalidRdfaProperties']);
        $t->same(false, $heading['rdfaPropertyValid']);
        $t->same('RDFa title', $heading['rdfaContent']);
        $t->same(true, $heading['rdfaContentValid']);
        $t->same('heading', $heading['documentOutline']);

        $t->same('relationship', $link['rdfa']);
        $t->same(['rel', 'resource', 'rev'], $link['rdfaAttributes']);
        $t->same(['author', 'next', 'javascript:alert(1)'], $link['rdfaRelTokens']);
        $t->same(['author', 'next'], $link['rdfaRelations']);
        $t->same(['javascript:alert(1)'], $link['invalidRdfaRelations']);
        $t->same(false, $link['rdfaRelValid']);
        $t->same(['reviewedBy'], $link['rdfaReverseRelations']);
        $t->same(true, $link['rdfaRevValid']);
        $t->same('#author', $link['rdfaResource']);
        $t->same(true, $link['rdfaResourceValid']);
        $t->same('a', $link['hyperlink']);

        $t->same('property', $date['rdfa']);
        $t->same(['datePublished'], $date['rdfaProperties']);
        $t->same('xsd:date', $date['rdfaDatatype']);
        $t->same(true, $date['rdfaDatatypeValid']);
        $t->same('2026-06-12', $date['rdfaContent']);
        $t->same(true, $date['rdfaContentValid']);

        $t->same('resource', $invalid['rdfa']);
        $t->same(['javascript:alert(1)'], $invalid['invalidRdfaTypes']);
        $t->same(false, $invalid['rdfaTypeofValid']);
        $t->same('bad id', $invalid['rdfaAbout']);
        $t->same(false, $invalid['rdfaAboutValid']);

        $t->same('<article about="./articles/42" id="review-rdfa" inlist="inlist" prefix="dc: http://purl.org/dc/terms/ schema: https://schema.org/ bad-prefix javascript:alert(1) dangling:" typeof="Article ReviewNewsArticle bad&lt;term" vocab="https://schema.org/"><h1 content="RDFa title" property="headline schema:name bad&lt;prop">Visible Title</h1><a href="/authors/ada" rel="author next javascript:alert(1)" resource="#author" rev="reviewedBy">Ada</a><span content="2026-06-12" datatype="xsd:date" property="datePublished">June 12</span><span about=" bad id " typeof="javascript:alert(1)">Invalid</span></article>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/rdfa-semantic-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html aria reference attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="region" role="region" aria-labelledby="title missing title" aria-describedby="desc help" aria-controls="panel,ghost" aria-owns="row1 row1 row2" aria-details="details">'
                . '<h2 id="title">Title</h2><p id="desc">Description</p><p id="help">Help</p><div id="panel"></div><aside id="details"></aside><span id="row1"></span></section>'
                . '<button id="active" aria-activedescendant="item-1 item-2" aria-errormessage="error" aria-flowto="next-step missing-flow">Next</button><span id="item-1"></span><p id="next-step"></p>',
            'ARIA reference review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/aria-reference-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $region = $summary[0];
        $button = $summary[1];

        $t->same('region', $region['elementId']);
        $t->same(['region'], $region['roles']);
        $t->same([
            'aria-controls' => 'panel,ghost',
            'aria-describedby' => 'desc help',
            'aria-details' => 'details',
            'aria-labelledby' => 'title missing title',
            'aria-owns' => 'row1 row1 row2',
        ], $region['ariaAttributes']);
        $t->same(['aria-controls', 'aria-describedby', 'aria-details', 'aria-labelledby', 'aria-owns'], $region['ariaReferenceAttributes']);
        $t->same(5, $region['ariaReferenceCount']);
        $t->same([
            'raw' => 'title missing title',
            'multiple' => true,
            'tokens' => ['title', 'missing', 'title'],
            'ids' => ['title', 'missing'],
            'duplicateIds' => ['title'],
            'invalidTokens' => [],
            'presentIds' => ['title'],
            'missingIds' => ['missing'],
            'valid' => true,
            'resolved' => false,
        ], $region['ariaReferences']['aria-labelledby']);
        $t->same(['desc', 'help'], $region['ariaReferences']['aria-describedby']['presentIds']);
        $t->same([], $region['ariaReferences']['aria-describedby']['missingIds']);
        $t->same(true, $region['ariaReferences']['aria-describedby']['resolved']);
        $t->same(['panel,ghost'], $region['ariaReferences']['aria-controls']['invalidTokens']);
        $t->same([], $region['ariaReferences']['aria-controls']['ids']);
        $t->same(false, $region['ariaReferences']['aria-controls']['valid']);
        $t->same(['details'], $region['ariaReferences']['aria-details']['presentIds']);
        $t->same(true, $region['ariaReferences']['aria-details']['resolved']);
        $t->same(['row1', 'row2'], $region['ariaReferences']['aria-owns']['ids']);
        $t->same(['row1'], $region['ariaReferences']['aria-owns']['duplicateIds']);
        $t->same(['row2'], $region['ariaReferences']['aria-owns']['missingIds']);

        $t->same('active', $button['elementId']);
        $t->same(['aria-activedescendant', 'aria-errormessage', 'aria-flowto'], $button['ariaReferenceAttributes']);
        $t->same(['item-1', 'item-2'], $button['ariaReferences']['aria-activedescendant']['ids']);
        $t->same(['item-1'], $button['ariaReferences']['aria-activedescendant']['presentIds']);
        $t->same(['item-2'], $button['ariaReferences']['aria-activedescendant']['missingIds']);
        $t->same(false, $button['ariaReferences']['aria-activedescendant']['valid']);
        $t->same(false, $button['ariaReferences']['aria-activedescendant']['resolved']);
        $t->same(['error'], $button['ariaReferences']['aria-errormessage']['missingIds']);
        $t->same(true, $button['ariaReferences']['aria-errormessage']['valid']);
        $t->same(['next-step'], $button['ariaReferences']['aria-flowto']['presentIds']);
        $t->same(['missing-flow'], $button['ariaReferences']['aria-flowto']['missingIds']);
        $t->same(false, $button['ariaReferences']['aria-flowto']['resolved']);

        $t->same('<section aria-controls="panel,ghost" aria-describedby="desc help" aria-details="details" aria-labelledby="title missing title" aria-owns="row1 row1 row2" id="region" role="region"><h2 id="title">Title</h2><p id="desc">Description</p><p id="help">Help</p><div id="panel"></div><aside id="details"></aside><span id="row1"></span></section><button aria-activedescendant="item-1 item-2" aria-errormessage="error" aria-flowto="next-step missing-flow" id="active">Next</button><span id="item-1"></span><p id="next-step"></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/aria-reference-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html focus navigation attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="focus-region" accesskey="s x s" autofocus="autofocus" tabindex="3"><button id="save" accesskey="k Enter" tabindex="-2">Save</button></section>'
                . '<p id="invalid-focus" accesskey="wide key" tabindex="bogus">No focus</p>',
            'focus navigation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/focus-navigation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $button = $section['children'][0];
        $invalid = $summary[1];

        $t->same('focus-region', $section['elementId']);
        $t->same('s x s', $section['accessKeyRaw']);
        $t->same(['s', 'x', 's'], $section['accessKeyTokens']);
        $t->same(['s', 'x'], $section['accessKeys']);
        $t->same([], $section['invalidAccessKeyTokens']);
        $t->same(true, $section['accessKeyValid']);
        $t->same('autofocus', $section['autofocusRaw']);
        $t->same(true, $section['autofocus']);
        $t->same('3', $section['tabIndexRaw']);
        $t->same(3, $section['tabIndex']);
        $t->same(true, $section['tabIndexValid']);

        $t->same('button', $button['name']);
        $t->same('button', $button['formControl']);
        $t->same('save', $button['elementId']);
        $t->same('k Enter', $button['accessKeyRaw']);
        $t->same(['k', 'Enter'], $button['accessKeyTokens']);
        $t->same(['k'], $button['accessKeys']);
        $t->same(['Enter'], $button['invalidAccessKeyTokens']);
        $t->same(false, $button['accessKeyValid']);
        $t->same('-2', $button['tabIndexRaw']);
        $t->same(-2, $button['tabIndex']);
        $t->same(true, $button['tabIndexValid']);

        $t->same('invalid-focus', $invalid['elementId']);
        $t->same('wide key', $invalid['accessKeyRaw']);
        $t->same(['wide', 'key'], $invalid['accessKeyTokens']);
        $t->same([], $invalid['accessKeys']);
        $t->same(['wide', 'key'], $invalid['invalidAccessKeyTokens']);
        $t->same(false, $invalid['accessKeyValid']);
        $t->same('bogus', $invalid['tabIndexRaw']);
        $t->same(null, $invalid['tabIndex']);
        $t->same(false, $invalid['tabIndexValid']);

        $t->same('<section accesskey="s x s" autofocus id="focus-region" tabindex="3"><button accesskey="k Enter" id="save" tabindex="-2">Save</button></section><p accesskey="wide key" id="invalid-focus" tabindex="bogus">No focus</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/focus-navigation-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html inert and custom element export attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="widget-host" inert part="card title card" exportparts="title:review-title, icon, bad:mapping:extra, invalid name:alias" slot="primary-panel" is="review-widget"><button part="action primary" slot="controls" inert>Save</button></section>'
                . '<p part="valid invalid=name" slot="bad slot" is="InvalidWidget">Fallback</p>',
            'inert custom element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/custom-element-attributes-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $host = $summary[0];
        $button = $host['children'][0];
        $fallback = $summary[1];

        $t->same('widget-host', $host['elementId']);
        $t->same('', $host['inertRaw']);
        $t->same(true, $host['inert']);
        $t->same('primary-panel', $host['slotRaw']);
        $t->same('primary-panel', $host['slotName']);
        $t->same(true, $host['slotValid']);
        $t->same('card title card', $host['partRaw']);
        $t->same(['card', 'title', 'card'], $host['partTokens']);
        $t->same(['card', 'title'], $host['partNames']);
        $t->same([], $host['invalidPartTokens']);
        $t->same(true, $host['partValid']);
        $t->same('title:review-title, icon, bad:mapping:extra, invalid name:alias', $host['exportPartsRaw']);
        $t->same(['title', 'icon'], $host['exportPartNames']);
        $t->same(['review-title', 'icon'], $host['exportPartAliases']);
        $t->same(['bad:mapping:extra', 'invalid name:alias'], $host['invalidExportParts']);
        $t->same(false, $host['exportPartsValid']);
        $t->same([
            ['raw' => 'title:review-title', 'source' => 'title', 'alias' => 'review-title', 'renamed' => true, 'valid' => true],
            ['raw' => 'icon', 'source' => 'icon', 'alias' => 'icon', 'renamed' => false, 'valid' => true],
            ['raw' => 'bad:mapping:extra', 'source' => 'bad', 'alias' => 'mapping', 'renamed' => false, 'valid' => false],
            ['raw' => 'invalid name:alias', 'source' => 'invalid name', 'alias' => 'alias', 'renamed' => false, 'valid' => false],
        ], $host['exportParts']);
        $t->same('review-widget', $host['isRaw']);
        $t->same('review-widget', $host['customElementName']);
        $t->same(true, $host['customElementValid']);

        $t->same(true, $button['inert']);
        $t->same('controls', $button['slotName']);
        $t->same(['action', 'primary'], $button['partNames']);
        $t->same(true, $button['partValid']);

        $t->same('bad slot', $fallback['slotRaw']);
        $t->same('bad slot', $fallback['slotName']);
        $t->same(false, $fallback['slotValid']);
        $t->same(['valid', 'invalid=name'], $fallback['partTokens']);
        $t->same(['invalid=name'], $fallback['invalidPartTokens']);
        $t->same(false, $fallback['partValid']);
        $t->same('InvalidWidget', $fallback['isRaw']);
        $t->same('InvalidWidget', $fallback['customElementName']);
        $t->same(false, $fallback['customElementValid']);

        $t->same('<section exportparts="title:review-title, icon, bad:mapping:extra, invalid name:alias" id="widget-host" inert is="review-widget" part="card title card" slot="primary-panel"><button inert part="action primary" slot="controls">Save</button></section><p is="InvalidWidget" part="valid invalid=name" slot="bad slot">Fallback</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/custom-element-attributes-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html input hint attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="entry" autocapitalize="on"><input id="amount" inputmode="Decimal" enterkeyhint="Done" autocapitalize="characters">'
                . '<textarea id="message" inputmode="search" enterkeyhint="send" autocapitalize="off">Note</textarea></form>'
                . '<p id="fallback" inputmode="kana" enterkeyhint="compose" autocapitalize="maybe">Fallback</p>',
            'input hint review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/input-hints-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $input = $form['children'][0];
        $textarea = $form['children'][1];
        $fallback = $summary[1];

        $t->same('entry', $form['elementId']);
        $t->same('on', $form['autocapitalizeRaw']);
        $t->same('sentences', $form['autocapitalize']);
        $t->same(true, $form['autocapitalizeValid']);

        $t->same('input', $input['formControl']);
        $t->same('Decimal', $input['inputModeRaw']);
        $t->same('decimal', $input['inputMode']);
        $t->same(true, $input['inputModeValid']);
        $t->same('Done', $input['enterKeyHintRaw']);
        $t->same('done', $input['enterKeyHint']);
        $t->same(true, $input['enterKeyHintValid']);
        $t->same('characters', $input['autocapitalizeRaw']);
        $t->same('characters', $input['autocapitalize']);
        $t->same(true, $input['autocapitalizeValid']);

        $t->same('textarea', $textarea['formControl']);
        $t->same('search', $textarea['inputMode']);
        $t->same(true, $textarea['inputModeValid']);
        $t->same('send', $textarea['enterKeyHint']);
        $t->same(true, $textarea['enterKeyHintValid']);
        $t->same('none', $textarea['autocapitalize']);
        $t->same(true, $textarea['autocapitalizeValid']);

        $t->same('kana', $fallback['inputModeRaw']);
        $t->same(null, $fallback['inputMode']);
        $t->same(false, $fallback['inputModeValid']);
        $t->same('compose', $fallback['enterKeyHintRaw']);
        $t->same(null, $fallback['enterKeyHint']);
        $t->same(false, $fallback['enterKeyHintValid']);
        $t->same('maybe', $fallback['autocapitalizeRaw']);
        $t->same(null, $fallback['autocapitalize']);
        $t->same(false, $fallback['autocapitalizeValid']);

        $t->same('<form autocapitalize="on" id="entry"><input autocapitalize="characters" enterkeyhint="Done" id="amount" inputmode="Decimal"><textarea autocapitalize="off" enterkeyhint="send" id="message" inputmode="search">Note</textarea></form><p autocapitalize="maybe" enterkeyhint="compose" id="fallback" inputmode="kana">Fallback</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/input-hints-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html list marker and item ordinal metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<ol id="steps" start="3" reversed type="A"><li value="7">Inspect<li>Repair<ol start="-2" type="i"><li value="-1">Nested</ol></ol>'
                . '<ul id="bullets" type="square"><li>Loose</li></ul><menu id="actions"><li value="4">Action</li></menu>'
                . '<ol id="invalid" start="abc"><li value="bad">Invalid</li></ol>',
            'list metadata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $steps = $summary[0];
        $inspect = $steps['children'][0];
        $repair = $steps['children'][1];
        $nested = $repair['children'][1];
        $nestedItem = $nested['children'][0];
        $bullets = $summary[1];
        $loose = $bullets['children'][0];
        $menu = $summary[2];
        $action = $menu['children'][0];
        $invalid = $summary[3];
        $invalidItem = $invalid['children'][0];

        $t->same('ordered', $steps['list']);
        $t->same(true, $steps['reversed']);
        $t->same('3', $steps['startRaw']);
        $t->same(3, $steps['start']);
        $t->same('A', $steps['markerType']);
        $t->same(true, $inspect['listItem']);
        $t->same('7', $inspect['valueRaw']);
        $t->same(7, $inspect['value']);
        $t->same('ordered', $nested['list']);
        $t->same(false, $nested['reversed']);
        $t->same('-2', $nested['startRaw']);
        $t->same(-2, $nested['start']);
        $t->same('i', $nested['markerType']);
        $t->same('-1', $nestedItem['valueRaw']);
        $t->same(-1, $nestedItem['value']);
        $t->same('unordered', $bullets['list']);
        $t->same('square', $bullets['markerType']);
        $t->same(true, $loose['listItem']);
        $t->same(null, $loose['valueRaw']);
        $t->same(null, $loose['value']);
        $t->same('menu', $menu['list']);
        $t->same('4', $action['valueRaw']);
        $t->same(4, $action['value']);
        $t->same('ordered', $invalid['list']);
        $t->same('abc', $invalid['startRaw']);
        $t->same(1, $invalid['start']);
        $t->same('bad', $invalidItem['valueRaw']);
        $t->same(null, $invalidItem['value']);
        $t->same('<ol id="steps" reversed start="3" type="A"><li value="7">Inspect</li><li>Repair<ol start="-2" type="i"><li value="-1">Nested</li></ol></li></ol><ul id="bullets" type="square"><li>Loose</li></ul><menu id="actions"><li value="4">Action</li></menu><ol id="invalid" start="abc"><li value="bad">Invalid</li></ol>', $html);
    },
    'summarizes html definition list term and description groups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dl id="glossary"><dt>Term <em>one</em></dt><dt>Alias</dt><dd>Definition <strong>primary</strong></dd><dd>Secondary note</dd><dt>Next</dt><dd><p>Nested text</p><dl><dt>Inner</dt><dd>Inside</dd></dl></dd></dl>'
                . '<dl id="orphan"><dd>Leading definition</dd><dt>Recovered term</dt><dd>Recovered body</dd></dl>',
            'definition list review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/definition-list-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $glossary = $summary[0];
        $term = $glossary['children'][0];
        $alias = $glossary['children'][1];
        $definition = $glossary['children'][2];
        $nestedDefinition = $glossary['children'][5];
        $nestedList = $nestedDefinition['children'][1];
        $orphan = $summary[1];

        $t->same('dl', $glossary['name']);
        $t->same('dl', $glossary['definitionList']);
        $t->same(3, $glossary['termCount']);
        $t->same(3, $glossary['definitionCount']);
        $t->same(2, $glossary['itemCount']);
        $t->same(['Term one', 'Alias', 'Next'], $glossary['terms']);
        $t->same(['Definition primary', 'Secondary note', 'Nested textInnerInside'], $glossary['definitions']);
        $t->same(['Term one', 'Alias'], $glossary['items'][0]['terms']);
        $t->same(['Definition primary', 'Secondary note'], $glossary['items'][0]['definitions']);
        $t->same(2, $glossary['items'][0]['termCount']);
        $t->same(2, $glossary['items'][0]['definitionCount']);
        $t->same(['Next'], $glossary['items'][1]['terms']);
        $t->same(['Nested textInnerInside'], $glossary['items'][1]['definitions']);

        $t->same('dt', $term['name']);
        $t->same('term', $term['definitionListPart']);
        $t->same('Term one', $term['termText']);
        $t->same('Alias', $alias['termText']);
        $t->same('dd', $definition['name']);
        $t->same('definition', $definition['definitionListPart']);
        $t->same('Definition primary', $definition['definitionText']);
        $t->same('dl', $nestedList['definitionList']);
        $t->same(['Inner'], $nestedList['terms']);
        $t->same(['Inside'], $nestedList['definitions']);

        $t->same('dl', $orphan['definitionList']);
        $t->same(2, $orphan['itemCount']);
        $t->same([], $orphan['items'][0]['terms']);
        $t->same(['Leading definition'], $orphan['items'][0]['definitions']);
        $t->same(['Recovered term'], $orphan['items'][1]['terms']);
        $t->same(['Recovered body'], $orphan['items'][1]['definitions']);

        $t->same('<dl id="glossary"><dt>Term <em>one</em></dt><dt>Alias</dt><dd>Definition <strong>primary</strong></dd><dd>Secondary note</dd><dt>Next</dt><dd><p>Nested text</p><dl><dt>Inner</dt><dd>Inside</dd></dl></dd></dl><dl id="orphan"><dd>Leading definition</dd><dt>Recovered term</dt><dd>Recovered body</dd></dl>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/definition-list-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html ordered list effective ordinal provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<ol id="reverse" reversed><li>Review</li><li value="9">Patch</li><li>Verify</li></ol>'
                . '<ol id="forward" start="4"><li>Draft</li><li value="-2">Pinned</li><li>Next</li></ol>'
                . '<ul id="plain"><li value="5">Loose</li></ul>',
            'ordered list effective ordinal review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/list-ordinal-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $reverse = $summary[0];
        $reverseReview = $reverse['children'][0];
        $reversePatch = $reverse['children'][1];
        $reverseVerify = $reverse['children'][2];
        $forward = $summary[1];
        $forwardDraft = $forward['children'][0];
        $forwardPinned = $forward['children'][1];
        $forwardNext = $forward['children'][2];
        $plainLoose = $summary[2]['children'][0];

        $t->same('ordered', $reverse['list']);
        $t->same(true, $reverse['reversed']);
        $t->same(3, $reverseReview['listOrdinal']);
        $t->same('reversed-count', $reverseReview['listOrdinalSource']);
        $t->same('9', $reversePatch['valueRaw']);
        $t->same(9, $reversePatch['listOrdinal']);
        $t->same('value-attribute', $reversePatch['listOrdinalSource']);
        $t->same(8, $reverseVerify['listOrdinal']);
        $t->same('previous-value', $reverseVerify['listOrdinalSource']);
        $t->same('ordered', $forward['list']);
        $t->same(4, $forward['start']);
        $t->same(4, $forwardDraft['listOrdinal']);
        $t->same('start-attribute', $forwardDraft['listOrdinalSource']);
        $t->same(-2, $forwardPinned['listOrdinal']);
        $t->same('value-attribute', $forwardPinned['listOrdinalSource']);
        $t->same(-1, $forwardNext['listOrdinal']);
        $t->same('previous-value', $forwardNext['listOrdinalSource']);
        $t->same('unordered', $summary[2]['list']);
        $t->same(null, $plainLoose['listOrdinal']);
        $t->same(null, $plainLoose['listOrdinalSource']);
        $t->same('<ol id="reverse" reversed><li>Review</li><li value="9">Patch</li><li>Verify</li></ol><ol id="forward" start="4"><li>Draft</li><li value="-2">Pinned</li><li>Next</li></ol><ul id="plain"><li value="5">Loose</li></ul>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/list-ordinal-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html heading and sectioning outline metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="story"><header><h1>Primary <em>Title</em></h1></header><section id="chapter"><h2>Chapter</h2><p>Body</p></section></article>'
                . '<nav aria-label="Contents"><div><h3>Navigation</h3></div><a href="#chapter">Chapter</a></nav>'
                . '<aside id="notes"><section id="nested-note"><h4>Nested note</h4></section></aside>'
                . '<main id="main"><p>No title</p></main>',
            'outline review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/outline-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $articleHeading = $article['children'][0]['children'][0];
        $section = $article['children'][1];
        $sectionHeading = $section['children'][0];
        $nav = $summary[1];
        $navHeading = $nav['children'][0]['children'][0];
        $aside = $summary[2];
        $nestedSection = $aside['children'][0];
        $main = $summary[3];

        $t->same('article', $article['name']);
        $t->same('article', $article['documentOutline']);
        $t->same('article', $article['outlineRoot']);
        $t->same('Primary Title', $article['sectionHeadingText']);
        $t->same('h1', $article['sectionHeadingTag']);
        $t->same(1, $article['sectionHeadingLevel']);
        $t->same('heading', $articleHeading['documentOutline']);
        $t->same(true, $articleHeading['heading']);
        $t->same('h1', $articleHeading['headingTag']);
        $t->same(1, $articleHeading['headingLevel']);
        $t->same('Primary Title', $articleHeading['headingText']);

        $t->same('section', $section['documentOutline']);
        $t->same('section', $section['outlineRoot']);
        $t->same('Chapter', $section['sectionHeadingText']);
        $t->same('h2', $section['sectionHeadingTag']);
        $t->same(2, $section['sectionHeadingLevel']);
        $t->same('heading', $sectionHeading['documentOutline']);
        $t->same(2, $sectionHeading['headingLevel']);
        $t->same('Chapter', $sectionHeading['headingText']);

        $t->same('navigation', $nav['documentOutline']);
        $t->same('nav', $nav['outlineRoot']);
        $t->same('Navigation', $nav['sectionHeadingText']);
        $t->same('h3', $nav['sectionHeadingTag']);
        $t->same(3, $nav['sectionHeadingLevel']);
        $t->same(['aria-label' => 'Contents'], $nav['ariaAttributes']);
        $t->same('heading', $navHeading['documentOutline']);
        $t->same(3, $navHeading['headingLevel']);

        $t->same('aside', $aside['documentOutline']);
        $t->same('aside', $aside['outlineRoot']);
        $t->same(null, $aside['sectionHeadingText']);
        $t->same(null, $aside['sectionHeadingTag']);
        $t->same(null, $aside['sectionHeadingLevel']);
        $t->same('section', $nestedSection['documentOutline']);
        $t->same('Nested note', $nestedSection['sectionHeadingText']);
        $t->same(4, $nestedSection['sectionHeadingLevel']);

        $t->same('main', $main['documentOutline']);
        $t->same('main', $main['outlineRoot']);
        $t->same(null, $main['sectionHeadingText']);
        $t->same(null, $main['sectionHeadingLevel']);
        $t->same('<article id="story"><header><h1>Primary <em>Title</em></h1></header><section id="chapter"><h2>Chapter</h2><p>Body</p></section></article><nav aria-label="Contents"><div><h3>Navigation</h3></div><a href="#chapter">Chapter</a></nav><aside id="notes"><section id="nested-note"><h4>Nested note</h4></section></aside><main id="main"><p>No title</p></main>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/outline-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html hgroup heading metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="packet"><hgroup id="title-group"><p class="eyebrow">Review packet</p><h2>Draft ingestion summary</h2><h1>Migration <em>Plan</em></h1><p>ODT and HTML checkpoints</p></hgroup><p>Body</p></section>',
            'hgroup outline review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hgroup-outline-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $hgroup = $section['children'][0];
        $secondaryHeading = $hgroup['children'][1];
        $mainHeading = $hgroup['children'][2];

        $t->same('section', $section['documentOutline']);
        $t->same('Migration Plan', $section['sectionHeadingText']);
        $t->same('h1', $section['sectionHeadingTag']);
        $t->same(1, $section['sectionHeadingLevel']);

        $t->same('hgroup', $hgroup['name']);
        $t->same('heading-group', $hgroup['documentOutline']);
        $t->same('hgroup', $hgroup['headingGroup']);
        $t->same('Review packetDraft ingestion summaryMigration PlanODT and HTML checkpoints', $hgroup['headingGroupText']);
        $t->same('Migration Plan', $hgroup['headingGroupHeadingText']);
        $t->same('h1', $hgroup['headingGroupHeadingTag']);
        $t->same(1, $hgroup['headingGroupHeadingLevel']);
        $t->same(2, $hgroup['headingGroupHeadingCount']);
        $t->same(['Draft ingestion summary', 'Migration Plan'], $hgroup['headingGroupHeadingTexts']);
        $t->same([
            ['tag' => 'h2', 'level' => 2, 'text' => 'Draft ingestion summary'],
            ['tag' => 'h1', 'level' => 1, 'text' => 'Migration Plan'],
        ], $hgroup['headingGroupHeadings']);
        $t->same(2, $hgroup['headingGroupSubtitleCount']);
        $t->same(['Review packet', 'ODT and HTML checkpoints'], $hgroup['headingGroupSubtitleTexts']);

        $t->same('heading', $secondaryHeading['documentOutline']);
        $t->same(2, $secondaryHeading['headingLevel']);
        $t->same('heading', $mainHeading['documentOutline']);
        $t->same(1, $mainHeading['headingLevel']);
        $t->same('<section id="packet"><hgroup id="title-group"><p class="eyebrow">Review packet</p><h2>Draft ingestion summary</h2><h1>Migration <em>Plan</em></h1><p>ODT and HTML checkpoints</p></hgroup><p>Body</p></section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hgroup-outline-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html search and address landmark metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<search id="site-search" aria-label="Site search"><form id="search-form" role="search" action="/find" method="post">'
                . '<label for="q">Search terms</label><input id="q" name="q" type="search" value="pandoc">'
                . '<button name="go" value="1">Go</button></form></search>'
                . '<address id="contact">Maintained by <a href="mailto:docs@example.test" rel="author">Docs Team</a> '
                . '<a href="/legal" rel="help external">Legal</a></address>',
            'search and address landmark review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/search-address-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $search = $summary[0];
        $form = $search['searchForms'][0];
        $input = $search['searchControls'][0];
        $button = $search['searchControls'][1];
        $address = $summary[1];
        $email = $address['contactLinks'][0];
        $legal = $address['contactLinks'][1];

        $t->same('search', $search['name']);
        $t->same('search', $search['landmark']);
        $t->same('search', $search['searchRegion']);
        $t->same('Search termsGo', $search['searchText']);
        $t->same(1, $search['searchFormCount']);
        $t->same('search-form', $form['id']);
        $t->same('/find', $form['action']);
        $t->same('post', $form['method']);
        $t->same('search', $form['role']);
        $t->same(2, count($form['controls']));
        $t->same('input', $input['control']);
        $t->same('q', $input['id']);
        $t->same('q', $input['controlName']);
        $t->same('search', $input['type']);
        $t->same('pandoc', $input['value']);
        $t->same(['Search terms'], $input['label']);
        $t->same('button', $button['control']);
        $t->same('go', $button['controlName']);
        $t->same('submit', $button['type']);
        $t->same('Go', $button['text']);
        $t->same('address', $address['name']);
        $t->same('address', $address['contactInfo']);
        $t->same('Maintained by Docs Team Legal', $address['contactText']);
        $t->same(2, $address['contactLinkCount']);
        $t->same('mailto:docs@example.test', $email['href']);
        $t->same('Docs Team', $email['label']);
        $t->same(['author'], $email['relTokens']);
        $t->same('/legal', $legal['href']);
        $t->same(['help', 'external'], $legal['relTokens']);
        $t->same(['mailto:docs@example.test', '/legal'], $address['contactHrefs']);
        $t->same(['mailto:docs@example.test'], $address['contactEmailHrefs']);
        $t->same('<search aria-label="Site search" id="site-search"><form action="/find" id="search-form" method="post" role="search"><label for="q">Search terms</label><input id="q" name="q" type="search" value="pandoc"><button name="go" value="1">Go</button></form></search><address id="contact">Maintained by <a href="mailto:docs@example.test" rel="author">Docs Team</a> <a href="/legal" rel="help external">Legal</a></address>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/search-address-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html text-level semantic elements for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><abbr title="HyperText Markup Language">HTML</abbr> <dfn title="Review term">term</dfn> <mark>note</mark> '
                . '<code>printf()</code> <kbd>Ctrl+S</kbd> <samp>saved</samp> <var>x</var> <small>fine print</small> '
                . '<sub>2</sub><sup>n</sup> <bdi dir="auto">Review ID</bdi> <bdo dir="rtl">source</bdo> <u>spelling</u> <s>old</s></p>',
            'text-level semantic review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/text-semantics-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $abbr = $paragraph['children'][0];
        $dfn = $paragraph['children'][2];
        $mark = $paragraph['children'][4];
        $code = $paragraph['children'][6];
        $kbd = $paragraph['children'][8];
        $samp = $paragraph['children'][10];
        $var = $paragraph['children'][12];
        $small = $paragraph['children'][14];
        $sub = $paragraph['children'][16];
        $sup = $paragraph['children'][17];
        $bdi = $paragraph['children'][19];
        $bdo = $paragraph['children'][21];
        $u = $paragraph['children'][23];
        $s = $paragraph['children'][25];

        $t->same('p', $paragraph['name']);
        $t->same('HTML term note printf() Ctrl+S saved x fine print 2n Review ID source spelling old', $paragraph['text']);
        $t->same('abbreviation', $abbr['textSemantic']);
        $t->same('HyperText Markup Language', $abbr['abbreviationTitle']);
        $t->same('definition', $dfn['textSemantic']);
        $t->same('term', $dfn['definitionTerm']);
        $t->same('Review term', $dfn['definitionTitle']);
        $t->same('mark', $mark['textSemantic']);
        $t->same('code', $code['textSemantic']);
        $t->same('keyboard-input', $kbd['textSemantic']);
        $t->same('sample-output', $samp['textSemantic']);
        $t->same('variable', $var['textSemantic']);
        $t->same('side-comment', $small['textSemantic']);
        $t->same('subscript', $sub['textSemantic']);
        $t->same('superscript', $sup['textSemantic']);
        $t->same('bidirectional-isolate', $bdi['textSemantic']);
        $t->same('auto', $bdi['textDirection']);
        $t->same('bidirectional-override', $bdo['textSemantic']);
        $t->same('rtl', $bdo['textDirection']);
        $t->same('unarticulated-annotation', $u['textSemantic']);
        $t->same('struck-text', $s['textSemantic']);
        $t->same('<p><abbr title="HyperText Markup Language">HTML</abbr> <dfn title="Review term">term</dfn> <mark>note</mark> <code>printf()</code> <kbd>Ctrl+S</kbd> <samp>saved</samp> <var>x</var> <small>fine print</small> <sub>2</sub><sup>n</sup> <bdi dir="auto">Review ID</bdi> <bdo dir="rtl">source</bdo> <u>spelling</u> <s>old</s></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/text-semantics-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html emphasis and importance semantics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><em>Stress</em><strong>Important</strong><b>Keyword</b><i>Taxon</i></p>',
            'emphasis importance semantic review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/emphasis-semantics-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $emphasis = $paragraph['children'][0];
        $strong = $paragraph['children'][1];
        $attention = $paragraph['children'][2];
        $offset = $paragraph['children'][3];

        $t->same('p', $paragraph['name']);
        $t->same('StressImportantKeywordTaxon', $paragraph['text']);
        $t->same('em', $emphasis['semanticTag']);
        $t->same('stress-emphasis', $emphasis['textSemantic']);
        $t->same('Stress', $emphasis['semanticText']);
        $t->same('strong', $strong['semanticTag']);
        $t->same('strong-importance', $strong['textSemantic']);
        $t->same('Important', $strong['semanticText']);
        $t->same('b', $attention['semanticTag']);
        $t->same('bring-attention', $attention['textSemantic']);
        $t->same('Keyword', $attention['semanticText']);
        $t->same('i', $offset['semanticTag']);
        $t->same('idiomatic-offset', $offset['textSemantic']);
        $t->same('Taxon', $offset['semanticText']);
        $t->same('<p><em>Stress</em><strong>Important</strong><b>Keyword</b><i>Taxon</i></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/emphasis-semantics-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html ruby annotation provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><ruby id="term">base<rp>(</rp><rt>annotation</rt><rp>)</rp><rtc><rt>alternate</rt><rt>source</rt></rtc><rb>tail</rb><rt>tail-note</rt></ruby></p>',
            'ruby annotation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/ruby-annotations-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $ruby = $paragraph['children'][0];
        $fallbackOpen = $ruby['children'][1];
        $firstAnnotation = $ruby['children'][2];
        $fallbackClose = $ruby['children'][3];
        $container = $ruby['children'][4];
        $containerAnnotation = $container['children'][0];
        $base = $ruby['children'][5];
        $tailAnnotation = $ruby['children'][6];

        $t->same('p', $paragraph['name']);
        $t->same('base(annotation)alternatesourcetailtail-note', $paragraph['text']);
        $t->same('ruby', $ruby['name']);
        $t->same('ruby', $ruby['ruby']);
        $t->same('term', $ruby['elementId']);
        $t->same('base(annotation)alternatesourcetailtail-note', $ruby['rubyText']);
        $t->same(['base', 'tail'], $ruby['rubyBaseTexts']);
        $t->same(2, $ruby['rubyBaseCount']);
        $t->same(['annotation', 'alternate', 'source', 'tail-note'], $ruby['rubyAnnotationTexts']);
        $t->same(4, $ruby['rubyAnnotationCount']);
        $t->same([
            ['container' => null, 'text' => 'annotation'],
            ['container' => 'rtc', 'text' => 'alternate'],
            ['container' => 'rtc', 'text' => 'source'],
            ['container' => null, 'text' => 'tail-note'],
        ], $ruby['rubyAnnotations']);
        $t->same(['(', ')'], $ruby['rubyFallbackTexts']);
        $t->same(2, $ruby['rubyFallbackCount']);

        $t->same('fallback-parenthesis', $fallbackOpen['rubyPart']);
        $t->same('(', $fallbackOpen['rubyFallbackText']);
        $t->same('annotation', $firstAnnotation['rubyPart']);
        $t->same('annotation', $firstAnnotation['rubyAnnotationText']);
        $t->same(')', $fallbackClose['rubyFallbackText']);
        $t->same('annotation-container', $container['rubyPart']);
        $t->same(['alternate', 'source'], $container['rubyAnnotationTexts']);
        $t->same(2, $container['rubyAnnotationCount']);
        $t->same('annotation', $containerAnnotation['rubyPart']);
        $t->same('alternate', $containerAnnotation['rubyAnnotationText']);
        $t->same('base', $base['rubyPart']);
        $t->same('tail', $base['rubyBaseText']);
        $t->same('tail-note', $tailAnnotation['rubyAnnotationText']);
        $t->same('<p><ruby id="term">base<rp>(</rp><rt>annotation</rt><rp>)</rp><rtc><rt>alternate</rt><rt>source</rt></rtc><rb>tail</rb><rt>tail-note</rt></ruby></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/ruby-annotations-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html data element value provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>SKU <data id="sku" value=" SKU-42 ">Packet <strong>42</strong></data> <data data-review="missing">No value</data></p>',
            'data element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/data-element-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $valued = $paragraph['children'][1];
        $missing = $paragraph['children'][3];

        $t->same('p', $paragraph['name']);
        $t->same('SKU Packet 42 No value', $paragraph['text']);
        $t->same('data', $valued['name']);
        $t->same('data', $valued['dataElement']);
        $t->same('sku', $valued['elementId']);
        $t->same('Packet 42', $valued['dataText']);
        $t->same(' SKU-42 ', $valued['dataValueRaw']);
        $t->same('SKU-42', $valued['dataValue']);
        $t->same('value-attribute', $valued['dataValueSource']);
        $t->same('strong', $valued['children'][1]['name']);
        $t->same('data', $missing['dataElement']);
        $t->same('No value', $missing['dataText']);
        $t->same(null, $missing['dataValueRaw']);
        $t->same(null, $missing['dataValue']);
        $t->same('missing', $missing['dataValueSource']);
        $t->same(['review' => 'missing'], $missing['dataset']);
        $t->same('<p>SKU <data id="sku" value=" SKU-42 ">Packet <strong>42</strong></data> <data data-review="missing">No value</data></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/data-element-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html time datetime provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article><time datetime=" 2026-06-11 ">June 11</time>'
                . '<time datetime="2026-06-11 18:45:30Z">Published</time>'
                . '<time datetime="2026-06-11T12:30">Local</time>'
                . '<time datetime="2026-W24">Week 24</time>'
                . '<time datetime="PT2H30M">Duration</time>'
                . '<time>2026-06</time>'
                . '<time datetime="2026-02-30">Bad date</time>'
                . '<time></time></article>',
            'time datetime review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/time-datetime-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $date = $article['children'][0];
        $global = $article['children'][1];
        $local = $article['children'][2];
        $week = $article['children'][3];
        $duration = $article['children'][4];
        $textFallback = $article['children'][5];
        $invalid = $article['children'][6];
        $missing = $article['children'][7];

        $t->same('article', $article['name']);
        $t->same('June 11PublishedLocalWeek 24Duration2026-06Bad date', $article['text']);
        $t->same('time', $date['time']);
        $t->same('June 11', $date['timeText']);
        $t->same(' 2026-06-11 ', $date['timeDatetimeRaw']);
        $t->same('datetime-attribute', $date['timeDatetimeSource']);
        $t->same('2026-06-11', $date['timeDatetime']);
        $t->same('date', $date['timeDatetimeKind']);
        $t->same(true, $date['timeDatetimeValid']);
        $t->same('2026-06-11T18:45:30Z', $global['timeDatetime']);
        $t->same('global-datetime', $global['timeDatetimeKind']);
        $t->same(true, $global['timeDatetimeValid']);
        $t->same('2026-06-11T12:30', $local['timeDatetime']);
        $t->same('local-datetime', $local['timeDatetimeKind']);
        $t->same(true, $local['timeDatetimeValid']);
        $t->same('2026-W24', $week['timeDatetime']);
        $t->same('week', $week['timeDatetimeKind']);
        $t->same(true, $week['timeDatetimeValid']);
        $t->same('PT2H30M', $duration['timeDatetime']);
        $t->same('duration', $duration['timeDatetimeKind']);
        $t->same(true, $duration['timeDatetimeValid']);
        $t->same('2026-06', $textFallback['timeText']);
        $t->same(null, $textFallback['timeDatetimeRaw']);
        $t->same('text', $textFallback['timeDatetimeSource']);
        $t->same('2026-06', $textFallback['timeDatetime']);
        $t->same('month', $textFallback['timeDatetimeKind']);
        $t->same(true, $textFallback['timeDatetimeValid']);
        $t->same('2026-02-30', $invalid['timeDatetimeRaw']);
        $t->same('datetime-attribute', $invalid['timeDatetimeSource']);
        $t->same(null, $invalid['timeDatetime']);
        $t->same('invalid', $invalid['timeDatetimeKind']);
        $t->same(false, $invalid['timeDatetimeValid']);
        $t->same('', $missing['timeText']);
        $t->same('missing', $missing['timeDatetimeSource']);
        $t->same('missing', $missing['timeDatetimeKind']);
        $t->same(false, $missing['timeDatetimeValid']);
        $t->same('<article><time datetime=" 2026-06-11 ">June 11</time><time datetime="2026-06-11 18:45:30Z">Published</time><time datetime="2026-06-11T12:30">Local</time><time datetime="2026-W24">Week 24</time><time datetime="PT2H30M">Duration</time><time>2026-06</time><time datetime="2026-02-30">Bad date</time><time></time></article>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/time-datetime-review.html', $document->children[0]->attr('part'));
    },
    'serializes entities comments and boolean attributes for HTML blocks' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            'Text&nbsp;<span title="A &quot;quote&quot; &amp; source">source &lt;em&gt;</span><!--review--><input checked>',
            'entity HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same("Text\u{00A0}", $summary[0]['text']);
        $t->same('span', $summary[1]['name']);
        $t->same(['title' => 'A "quote" & source'], $summary[1]['attributes']);
        $t->same('source <em>', $summary[1]['text']);
        $t->same('comment', $summary[2]['type']);
        $t->same('review', $summary[2]['text']);
        $t->same('input', $summary[3]['name']);
        $t->same(['checked' => 'checked'], $summary[3]['attributes']);
        $t->same("Text\u{00A0}<span title=\"A &quot;quote&quot; &amp; source\">source &lt;em&gt;</span><!--review--><input checked>", $html);
    },
    'decodes bounded html5 math spacing references before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-math="&af;&it;&ic;">f&ApplyFunction;g&InvisibleTimes;h&MediumSpace;comma&InvisibleComma;zero&ZeroWidthSpace;neg&NegativeThinSpace;</p>'
                . '<p data-spacing="&NonBreakingSpace;&ThinSpace;&ThickSpace;&VeryThinSpace;&hairsp;">Spaces: non&NonBreakingSpace;thin&ThinSpace;alias&thinsp;thick&ThickSpace;very&VeryThinSpace;hair&hairsp;neg&NegativeVeryThinSpace;&NegativeMediumSpace;&NegativeThickSpace;</p>',
            'math spacing entity HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('p', $summary[0]['name']);
        $t->same(['data-math' => "\u{2061}\u{2062}\u{2063}"], $summary[0]['attributes']);
        $t->same("f\u{2061}g\u{2062}h\u{205F}comma\u{2063}zero\u{200B}neg\u{200B}", $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same("f\u{2061}g\u{2062}h\u{205F}comma\u{2063}zero\u{200B}neg\u{200B}", $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same(['data-spacing' => "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}"], $summary[1]['attributes']);
        $t->same("Spaces: non\u{00A0}thin\u{2009}alias\u{2009}thick\u{205F}\u{200A}very\u{200A}hair\u{200A}neg\u{200B}\u{200B}\u{200B}", $summary[1]['text']);
        $t->same("Spaces: non\u{00A0}thin\u{2009}alias\u{2009}thick\u{205F}\u{200A}very\u{200A}hair\u{200A}neg\u{200B}\u{200B}\u{200B}", $summary[1]['children'][0]['text']);
        $t->same('<p data-math="' . "\u{2061}\u{2062}\u{2063}" . '">f' . "\u{2061}" . 'g' . "\u{2062}" . 'h' . "\u{205F}" . 'comma' . "\u{2063}" . 'zero' . "\u{200B}" . 'neg' . "\u{200B}" . '</p><p data-spacing="' . "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}" . '">Spaces: non' . "\u{00A0}" . 'thin' . "\u{2009}" . 'alias' . "\u{2009}" . 'thick' . "\u{205F}\u{200A}" . 'very' . "\u{200A}" . 'hair' . "\u{200A}" . 'neg' . "\u{200B}\u{200B}\u{200B}" . '</p>', $html);
        $t->true(!str_contains($html, '&amp;ApplyFunction;'), 'Expected ApplyFunction to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;ZeroWidthSpace;'), 'Expected ZeroWidthSpace to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;NonBreakingSpace;'), 'Expected NonBreakingSpace to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;ThickSpace;'), 'Expected ThickSpace to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;NegativeMediumSpace;'), 'Expected negative spacing aliases to decode before raw HTML handoff');
    },
    'decodes safe semicolon html5 named references before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-math="&NotEqualTilde;&DoubleLongRightArrow;&realine;">'
                . '&CounterClockwiseContourIntegral;&LeftTriangleBar;&NotNestedGreaterGreater;&angmsdaa;&bnequiv;&nparsl;&suphsol;&rarrfs;&nGg;&gesles;&lesg;&angzarr;'
                . '</p><p data-core="&quot;&amp;&lt;">core &quot;&amp;&lt;</p>'
                . '<script type="application/json">{"literal":"&NotEqualTilde;"}</script>',
            'broad html5 named entity fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $attribute = "\u{2242}\u{0338}\u{27F9}\u{211B}";
        $text = "\u{2233}\u{29CF}\u{2AA2}\u{0338}\u{29A8}\u{2261}\u{20E5}\u{2AFD}\u{20E5}\u{27C9}\u{291E}\u{22D9}\u{0338}\u{2A94}\u{22DA}\u{FE00}\u{237C}";

        $t->same($attribute, $summary[0]['attributes']['data-math']);
        $t->same($text, $summary[0]['text']);
        $t->same(['data-core' => '"&<'], $summary[1]['attributes']);
        $t->same('core "&<', $summary[1]['text']);
        $t->same('{"literal":"&NotEqualTilde;"}', $summary[2]['text']);
        $t->same('<p data-math="' . $attribute . '">' . $text . '</p><p data-core="&quot;&amp;&lt;">core "&amp;&lt;</p><script type="application/json">{"literal":"&NotEqualTilde;"}</script>', $html);
        foreach (['NotEqualTilde', 'CounterClockwiseContourIntegral', 'NotNestedGreaterGreater', 'bnequiv', 'angzarr'] as $entityName) {
            $t->true(!str_contains($html, '&amp;' . $entityName . ';'), 'Expected HTML5 reference ' . $entityName . ' to decode before raw HTML handoff');
        }
        $t->contains('{"literal":"&NotEqualTilde;"}', $html);
    },
    'normalizes unsafe html comment boundaries before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<!--review---><p>Imported comment boundary</p><!--source -- boundary--><!--triple---tail--->',
            'comment boundary HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('comment', $summary[0]['type']);
        $t->same('review-', $summary[0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('comment', $summary[2]['type']);
        $t->same('source -- boundary', $summary[2]['text']);
        $t->same('comment', $summary[3]['type']);
        $t->same('triple---tail-', $summary[3]['text']);
        $t->same('<!--review- --><p>Imported comment boundary</p><!--source - - boundary--><!--triple- - -tail- -->', $html);
        $t->true(!str_contains($html, '--->'), 'Expected trailing hyphen comments to be padded before the closing delimiter');
        $t->true(!str_contains($html, 'source -- boundary'), 'Expected interior comment delimiters to be split before serialization');
        $t->true(!str_contains($html, 'triple---tail'), 'Expected overlapping comment delimiters to be split before serialization');
    },
    'serializes raw text elements and expanded html5 boolean attributes' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script defer src="review.js">if (a < b && c > d) { window.review = "&"; }</script>'
                . '<style disabled>.legacy > .target::before { content: "&"; }</style>',
            'raw text HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('script', $summary[0]['name']);
        $t->same(['defer' => 'defer', 'src' => 'review.js'], $summary[0]['attributes']);
        $t->same('if (a < b && c > d) { window.review = "&"; }', $summary[0]['text']);
        $t->same('style', $summary[1]['name']);
        $t->same(['disabled' => 'disabled'], $summary[1]['attributes']);
        $t->same('.legacy > .target::before { content: "&"; }', $summary[1]['text']);
        $t->same('<script defer src="review.js">if (a < b && c > d) { window.review = "&"; }</script><style disabled>.legacy > .target::before { content: "&"; }</style>', $html);
    },
    'summarizes html script and style active source provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script type="module" src="app.js" async defer crossorigin="anonymous" integrity="sha384-review" referrerpolicy="no-referrer" fetchpriority="high" blocking="render"></script>'
                . '<script nomodule>console.log("<review> & source");</script>'
                . '<style type="text/css" media="print" disabled blocking="render">body > .review { color: red; }</style>',
            'active content provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/active-content-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $externalScript = $summary[0];
        $inlineScript = $summary[1];
        $style = $summary[2];

        $t->same('script', $externalScript['name']);
        $t->same('script', $externalScript['activeContent']);
        $t->same('external', $externalScript['scriptSourceKind']);
        $t->same('app.js', $externalScript['src']);
        $t->same('module', $externalScript['scriptTypeRaw']);
        $t->same('module', $externalScript['scriptType']);
        $t->same(true, $externalScript['module']);
        $t->same(true, $externalScript['async']);
        $t->same(true, $externalScript['defer']);
        $t->same(false, $externalScript['nomodule']);
        $t->same('anonymous', $externalScript['crossorigin']);
        $t->same('sha384-review', $externalScript['integrity']);
        $t->same('no-referrer', $externalScript['referrerpolicy']);
        $t->same('high', $externalScript['fetchpriority']);
        $t->same('render', $externalScript['blockingRaw']);
        $t->same(['render'], $externalScript['blockingTokens']);
        $t->same('', $externalScript['scriptText']);
        $t->same(0, $externalScript['scriptTextLength']);
        $t->same(hash('sha256', ''), $externalScript['scriptTextSha256']);
        $t->same('external-script-source', $externalScript['activeReviewPolicy']);
        $t->same('script', $inlineScript['activeContent']);
        $t->same('inline', $inlineScript['scriptSourceKind']);
        $t->same(null, $inlineScript['src']);
        $t->same(false, $inlineScript['module']);
        $t->same(false, $inlineScript['async']);
        $t->same(false, $inlineScript['defer']);
        $t->same(true, $inlineScript['nomodule']);
        $t->same('console.log("<review> & source");', $inlineScript['scriptText']);
        $t->same(strlen('console.log("<review> & source");'), $inlineScript['scriptTextLength']);
        $t->same(hash('sha256', 'console.log("<review> & source");'), $inlineScript['scriptTextSha256']);
        $t->same('inline-script-source', $inlineScript['activeReviewPolicy']);
        $t->same('style', $style['name']);
        $t->same('style', $style['activeContent']);
        $t->same('inline', $style['styleSourceKind']);
        $t->same('text/css', $style['styleTypeRaw']);
        $t->same('text/css', $style['styleType']);
        $t->same('print', $style['media']);
        $t->same(true, $style['disabled']);
        $t->same('render', $style['blockingRaw']);
        $t->same(['render'], $style['blockingTokens']);
        $t->same('body > .review { color: red; }', $style['styleText']);
        $t->same(strlen('body > .review { color: red; }'), $style['styleTextLength']);
        $t->same(hash('sha256', 'body > .review { color: red; }'), $style['styleTextSha256']);
        $t->same('inline-style-source', $style['activeReviewPolicy']);
        $t->same('<script async blocking="render" crossorigin="anonymous" defer fetchpriority="high" integrity="sha384-review" referrerpolicy="no-referrer" src="app.js" type="module"></script><script nomodule>console.log("<review> & source");</script><style blocking="render" disabled media="print" type="text/css">body > .review { color: red; }</style>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/active-content-review.html', $document->children[0]->attr('part'));
    },
    'preflights html declarations outside protected raw text serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script type="application/json">{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}</script>'
                . '<style>body:before{content:"<!ENTITY reviewer SYSTEM file>"}</style>'
                . '<textarea><!ENTITY reviewer SYSTEM "file:///etc/passwd"></textarea>'
                . '<template><?xml-stylesheet href="file"?></template>'
                . '<iframe><!DOCTYPE html></iframe>',
            'raw text declaration HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('script', $summary[0]['name']);
        $t->same('{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}', $summary[0]['text']);
        $t->same('body:before{content:"<!ENTITY reviewer SYSTEM file>"}', $summary[1]['text']);
        $t->same('<!ENTITY reviewer SYSTEM "file:///etc/passwd">', $summary[2]['text']);
        $t->same('<?xml-stylesheet href="file"?>', $summary[3]['text']);
        $t->same('<!DOCTYPE html>', $summary[4]['text']);
        $t->same(
            '<script type="application/json">{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}</script>'
                . '<style>body:before{content:"<!ENTITY reviewer SYSTEM file>"}</style>'
                . '<textarea>&lt;!ENTITY reviewer SYSTEM "file:///etc/passwd"&gt;</textarea>'
                . '<template>&lt;?xml-stylesheet href="file"?&gt;</template>'
                . '<iframe>&lt;!DOCTYPE html&gt;</iframe>',
            $html
        );
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<p>bad</p><!DOCTYPE html>', 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<p><?review href="file"?></p>', 'unsafe HTML fragment'));
    },
    'summarizes html select option state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<select name="review-status" multiple><option value="draft">Draft<option selected value="review">Review<optgroup label="Archive" disabled><option value="a1">Archive One<option selected>Archive Two</optgroup></select><p>after</p>',
            'select review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('select', $summary[0]['name']);
        $t->same('select', $summary[0]['formControl']);
        $t->same(['multiple' => 'multiple', 'name' => 'review-status'], $summary[0]['attributes']);
        $t->same(['review', 'Archive Two'], $summary[0]['selectedValues']);
        $t->same([
            ['value' => 'draft', 'label' => 'Draft', 'text' => 'Draft', 'selected' => false, 'disabled' => false],
            ['value' => 'review', 'label' => 'Review', 'text' => 'Review', 'selected' => true, 'disabled' => false],
            ['value' => 'a1', 'label' => 'Archive One', 'text' => 'Archive One', 'selected' => false, 'disabled' => true, 'group' => 'Archive', 'groupDisabled' => true],
            ['value' => 'Archive Two', 'label' => 'Archive Two', 'text' => 'Archive Two', 'selected' => true, 'disabled' => true, 'group' => 'Archive', 'groupDisabled' => true],
        ], $summary[0]['selectOptions']);
        $t->same('<select multiple name="review-status"><option value="draft">Draft</option><option selected value="review">Review</option><optgroup disabled label="Archive"><option value="a1">Archive One</option><option selected>Archive Two</option></optgroup></select><p>after</p>', $html);
    },
    'summarizes html input textarea and button state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="review-form"><input name="title" value="Draft &amp; Source"><input type="checkbox" name="publish" checked disabled><textarea name="notes" readonly>Reviewer &amp; editor' . "\n" . 'note</textarea><button name="action" value="publish">Publish <strong>now</strong></button><button type="reset" disabled>Clear</button></form>',
            'form control review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $textInput = $form['children'][0];
        $checkbox = $form['children'][1];
        $textarea = $form['children'][2];
        $submitButton = $form['children'][3];
        $resetButton = $form['children'][4];

        $t->same('form', $form['name']);
        $t->same(['id' => 'review-form'], $form['attributes']);
        $t->same('input', $textInput['formControl']);
        $t->same('text', $textInput['inputType']);
        $t->same('Draft & Source', $textInput['value']);
        $t->same(false, $textInput['checked']);
        $t->same(false, $textInput['disabled']);
        $t->same('input', $checkbox['formControl']);
        $t->same('checkbox', $checkbox['inputType']);
        $t->same('', $checkbox['value']);
        $t->same(true, $checkbox['checked']);
        $t->same(true, $checkbox['disabled']);
        $t->same('textarea', $textarea['formControl']);
        $t->same("Reviewer & editor\nnote", $textarea['value']);
        $t->same(false, $textarea['disabled']);
        $t->same(true, $textarea['readonly']);
        $t->same('button', $submitButton['formControl']);
        $t->same('submit', $submitButton['buttonType']);
        $t->same('publish', $submitButton['value']);
        $t->same('Publish now', $submitButton['label']);
        $t->same(false, $submitButton['disabled']);
        $t->same('button', $resetButton['formControl']);
        $t->same('reset', $resetButton['buttonType']);
        $t->same('', $resetButton['value']);
        $t->same('Clear', $resetButton['label']);
        $t->same(true, $resetButton['disabled']);
        $t->same('<form id="review-form"><input name="title" value="Draft &amp; Source"><input checked disabled name="publish" type="checkbox"><textarea name="notes" readonly>Reviewer &amp; editor' . "\n" . 'note</textarea><button name="action" value="publish">Publish <strong>now</strong></button><button disabled type="reset">Clear</button></form>', $html);
    },
    'summarizes html form control constraint attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="constraints"><label for="slug">Slug</label><input id="slug" name="slug" type="text" value="post-42" minlength="3" maxlength="12" pattern="[a-z0-9-]+" autocomplete="section-review shipping url" dirname="slug.dir" required readonly size="24">'
                . '<input id="score" name="score" type="number" min="-5" max="10" step="0.5" value="4"><input id="any-step" name="any" type="number" min="bad" max="20" step="any">'
                . '<textarea id="summary" name="summary" minlength="10" maxlength="5" dirname="summary.dir" autocomplete="bad&lt;tag" required>Text</textarea>'
                . '<select id="choices" name="choices" multiple size="3" autocomplete="off"><option selected>A</option><option>B</option></select></form>',
            'form constraint review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-constraints-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $slug = $form['children'][1];
        $score = $form['children'][2];
        $anyStep = $form['children'][3];
        $textarea = $form['children'][4];
        $select = $form['children'][5];

        $t->same('form-control', $slug['constraintValidation']);
        $t->same(true, $slug['required']);
        $t->same(true, $slug['readonly']);
        $t->same('3', $slug['minLengthRaw']);
        $t->same(3, $slug['minLength']);
        $t->same(true, $slug['minLengthValid']);
        $t->same('12', $slug['maxLengthRaw']);
        $t->same(12, $slug['maxLength']);
        $t->same(true, $slug['maxLengthValid']);
        $t->same(true, $slug['lengthRangeValid']);
        $t->same('[a-z0-9-]+', $slug['patternRaw']);
        $t->same(strlen('[a-z0-9-]+'), $slug['patternLength']);
        $t->same('pattern-source-no-regex-execution', $slug['patternReviewPolicy']);
        $t->same('section-review shipping url', $slug['autocompleteRaw']);
        $t->same(['section-review', 'shipping', 'url'], $slug['autocompleteTokens']);
        $t->same(['section-review', 'shipping', 'url'], $slug['autocompleteNormalizedTokens']);
        $t->same('detail', $slug['autocompleteState']);
        $t->same(true, $slug['autocompleteValid']);
        $t->same('slug.dir', $slug['dirnameRaw']);
        $t->same('slug.dir', $slug['dirname']);
        $t->same(true, $slug['dirnameValid']);
        $t->same('24', $slug['controlSizeRaw']);
        $t->same(24, $slug['controlSize']);
        $t->same(true, $slug['controlSizeValid']);

        $t->same('number', $score['inputType']);
        $t->same('-5', $score['constraintMinRaw']);
        $t->same(-5.0, $score['constraintMin']);
        $t->same(true, $score['constraintMinValid']);
        $t->same('10', $score['constraintMaxRaw']);
        $t->same(10.0, $score['constraintMax']);
        $t->same(true, $score['constraintMaxValid']);
        $t->same(true, $score['constraintRangeValid']);
        $t->same('0.5', $score['constraintStepRaw']);
        $t->same(0.5, $score['constraintStep']);
        $t->same(true, $score['constraintStepValid']);

        $t->same('bad', $anyStep['constraintMinRaw']);
        $t->same(null, $anyStep['constraintMin']);
        $t->same(false, $anyStep['constraintMinValid']);
        $t->same(20.0, $anyStep['constraintMax']);
        $t->same(null, $anyStep['constraintRangeValid']);
        $t->same('any', $anyStep['constraintStep']);
        $t->same(true, $anyStep['constraintStepValid']);

        $t->same('textarea', $textarea['formControl']);
        $t->same(true, $textarea['required']);
        $t->same(10, $textarea['minLength']);
        $t->same(5, $textarea['maxLength']);
        $t->same(false, $textarea['lengthRangeValid']);
        $t->same('bad<tag', $textarea['autocompleteRaw']);
        $t->same(['bad<tag'], $textarea['invalidAutocompleteTokens']);
        $t->same(false, $textarea['autocompleteValid']);
        $t->same('summary.dir', $textarea['dirname']);
        $t->same(true, $textarea['dirnameValid']);

        $t->same('select', $select['formControl']);
        $t->same(true, $select['multiple']);
        $t->same('3', $select['controlSizeRaw']);
        $t->same(3, $select['controlSize']);
        $t->same('off', $select['autocompleteState']);
        $t->same(true, $select['autocompleteValid']);
        $t->same(['A'], $select['selectedValues']);

        $t->same('<form id="constraints"><label for="slug">Slug</label><input autocomplete="section-review shipping url" dirname="slug.dir" id="slug" maxlength="12" minlength="3" name="slug" pattern="[a-z0-9-]+" readonly required size="24" type="text" value="post-42"><input id="score" max="10" min="-5" name="score" step="0.5" type="number" value="4"><input id="any-step" max="20" min="bad" name="any" step="any" type="number"><textarea autocomplete="bad&lt;tag" dirname="summary.dir" id="summary" maxlength="5" minlength="10" name="summary" required>Text</textarea><select autocomplete="off" id="choices" multiple name="choices" size="3"><option selected>A</option><option>B</option></select></form>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-constraints-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html form submission state and submitter overrides for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="remote-review" action="https://forms.example.invalid/submit" method="POST" enctype="multipart/form-data" target="_blank" autocomplete="off" accept-charset="UTF-8 ISO-8859-1" novalidate><input name="title" value="Packet"><input type="image" src="submit.png" formaction="/image-submit" formmethod="POST" formenctype="multipart/form-data" formtarget="_parent" formnovalidate><button type="submit" formaction="/local-submit" formmethod="dialog" formenctype="text/plain" formtarget="_self" formnovalidate>Send</button></form>'
                . '<form id="invalid-method" method="TRACE" enctype="application/json" autocomplete="maybe"><button>Default</button></form>',
            'form submission review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $imageSubmitter = $form['children'][1];
        $buttonSubmitter = $form['children'][2];
        $fallbackForm = $summary[1];
        $defaultButton = $fallbackForm['children'][0];

        $t->same('form', $form['name']);
        $t->same('form', $form['formSubmission']);
        $t->same('https://forms.example.invalid/submit', $form['action']);
        $t->same('post', $form['method']);
        $t->same('multipart/form-data', $form['enctype']);
        $t->same('_blank', $form['target']);
        $t->same('off', $form['autocomplete']);
        $t->same(true, $form['novalidate']);
        $t->same('UTF-8 ISO-8859-1', $form['acceptCharsetRaw']);
        $t->same(['UTF-8', 'ISO-8859-1'], $form['acceptCharsets']);
        $t->same('image', $imageSubmitter['inputType']);
        $t->same([
            'form' => null,
            'formAction' => '/image-submit',
            'formMethod' => 'post',
            'formEnctype' => 'multipart/form-data',
            'formTarget' => '_parent',
            'formNoValidate' => true,
        ], $imageSubmitter['submitter']);
        $t->same('submit', $buttonSubmitter['buttonType']);
        $t->same([
            'form' => null,
            'formAction' => '/local-submit',
            'formMethod' => 'dialog',
            'formEnctype' => 'text/plain',
            'formTarget' => '_self',
            'formNoValidate' => true,
        ], $buttonSubmitter['submitter']);
        $t->same('get', $fallbackForm['method']);
        $t->same('application/x-www-form-urlencoded', $fallbackForm['enctype']);
        $t->same('on', $fallbackForm['autocomplete']);
        $t->same(false, $fallbackForm['novalidate']);
        $t->same(null, $fallbackForm['acceptCharsetRaw']);
        $t->same([], $fallbackForm['acceptCharsets']);
        $t->same([
            'form' => null,
            'formAction' => null,
            'formMethod' => null,
            'formEnctype' => null,
            'formTarget' => null,
            'formNoValidate' => false,
        ], $defaultButton['submitter']);
        $t->same('<form accept-charset="UTF-8 ISO-8859-1" action="https://forms.example.invalid/submit" autocomplete="off" enctype="multipart/form-data" id="remote-review" method="POST" novalidate target="_blank"><input name="title" value="Packet"><input formaction="/image-submit" formenctype="multipart/form-data" formmethod="POST" formnovalidate formtarget="_parent" src="submit.png" type="image"><button formaction="/local-submit" formenctype="text/plain" formmethod="dialog" formnovalidate formtarget="_self" type="submit">Send</button></form><form autocomplete="maybe" enctype="application/json" id="invalid-method" method="TRACE"><button>Default</button></form>', $html);
    },
    'summarizes html form owner associations for remote controls' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="primary" action="/save" method="POST" enctype="multipart/form-data" target="_blank"><input id="inside" name="title" value="Draft"></form>'
                . '<label for="remote-title">Remote title</label><input id="remote-title" name="title" form="primary" value="Remote">'
                . '<select id="state" name="state" form="primary"><option value="draft">Draft<option selected value="review">Review</select>'
                . '<textarea id="orphan" name="notes" form="missing">Lost</textarea><button id="empty" form="">No form</button>'
                . '<form id="fallback"><button id="fallback-button" name="fallback" value="1">Fallback</button></form>',
            'remote form owner review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-owner-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $primary = $summary[0];
        $inside = $primary['children'][0];
        $remote = $summary[2];
        $select = $summary[3];
        $orphan = $summary[4];
        $empty = $summary[5];
        $fallback = $summary[6];
        $fallbackButton = $fallback['children'][0];

        $t->same('form', $primary['formSubmission']);
        $t->same(3, $primary['controlCount']);
        $t->same(2, $primary['externalControlCount']);
        $t->same(['title', 'state'], $primary['controlNames']);
        $t->same([
            [
                'tag' => 'input',
                'id' => 'inside',
                'controlName' => 'title',
                'formOwnerSource' => 'ancestor',
                'effectiveDisabled' => false,
                'type' => 'text',
                'value' => 'Draft',
                'checked' => false,
            ],
            [
                'tag' => 'input',
                'id' => 'remote-title',
                'controlName' => 'title',
                'formOwnerSource' => 'form-attribute',
                'effectiveDisabled' => false,
                'type' => 'text',
                'value' => 'Remote',
                'checked' => false,
            ],
            [
                'tag' => 'select',
                'id' => 'state',
                'controlName' => 'state',
                'formOwnerSource' => 'form-attribute',
                'effectiveDisabled' => false,
                'selectedValues' => ['review'],
            ],
        ], $primary['controls']);

        $t->same('ancestor', $inside['formOwnerSource']);
        $t->same(null, $inside['formOwnerRaw']);
        $t->same('primary', $inside['formOwnerId']);
        $t->same(true, $inside['formOwnerFound']);
        $t->same('/save', $inside['formOwnerAction']);
        $t->same('post', $inside['formOwnerMethod']);
        $t->same('multipart/form-data', $inside['formOwnerEnctype']);
        $t->same('_blank', $inside['formOwnerTarget']);

        $t->same('form-attribute', $remote['formOwnerSource']);
        $t->same('primary', $remote['formOwnerRaw']);
        $t->same('primary', $remote['formOwnerTargetId']);
        $t->same('primary', $remote['formOwnerId']);
        $t->same(['Remote title'], $remote['labels']);
        $t->same('/save', $remote['formOwnerAction']);

        $t->same('select', $select['formControl']);
        $t->same('form-attribute', $select['formOwnerSource']);
        $t->same(['review'], $select['selectedValues']);
        $t->same('post', $select['formOwnerMethod']);

        $t->same('missing-form-attribute', $orphan['formOwnerSource']);
        $t->same('missing', $orphan['formOwnerTargetId']);
        $t->same(false, $orphan['formOwnerFound']);
        $t->same(null, $orphan['formOwnerAction']);
        $t->same('missing-form-attribute', $empty['formOwnerSource']);
        $t->same(null, $empty['formOwnerTargetId']);
        $t->same(false, $empty['formOwnerFound']);

        $t->same(1, $fallback['controlCount']);
        $t->same(['fallback'], $fallback['controlNames']);
        $t->same('ancestor', $fallbackButton['formOwnerSource']);
        $t->same('fallback', $fallbackButton['formOwnerId']);
        $t->same('<form action="/save" enctype="multipart/form-data" id="primary" method="POST" target="_blank"><input id="inside" name="title" value="Draft"></form><label for="remote-title">Remote title</label><input form="primary" id="remote-title" name="title" value="Remote"><select form="primary" id="state" name="state"><option value="draft">Draft</option><option selected value="review">Review</option></select><textarea form="missing" id="orphan" name="notes">Lost</textarea><button form="" id="empty">No form</button><form id="fallback"><button id="fallback-button" name="fallback" value="1">Fallback</button></form>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-owner-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html output control state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="calc-form"><input id="source-a" name="a" value="5"><button id="source-b" type="button">Add</button><label for="checksum">Checksum</label><label>Total <output id="checksum" name="checksum" for="source-a  source-b missing">Ready <strong>hash</strong></output></label></form>',
            'output control review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $output = $form['children'][3]['children'][1];

        $t->same('output', $output['name']);
        $t->same('output', $output['formControl']);
        $t->same(['Checksum', 'Total Ready hash'], $output['labels']);
        $t->same('Ready hash', $output['text']);
        $t->same('Ready hash', $output['value']);
        $t->same('source-a  source-b missing', $output['forRaw']);
        $t->same(['source-a', 'source-b', 'missing'], $output['forIds']);
        $t->same('<form id="calc-form"><input id="source-a" name="a" value="5"><button id="source-b" type="button">Add</button><label for="checksum">Checksum</label><label>Total <output for="source-a  source-b missing" id="checksum" name="checksum">Ready <strong>hash</strong></output></label></form>', $html);
    },
    'summarizes html label control associations for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="labels"><label for="title">Title <span>required</span></label><input id="title" name="title" value="Draft"><label>Wrapped <textarea id="notes" name="notes">Note</textarea></label><label for="missing">Missing</label><label for="save">Explicit <button id="ignored" name="ignored">Ignored</button></label><button id="save" name="save" disabled>Save</button><label><input type="hidden" id="secret" name="secret" value="x"> Hidden</label></form>',
            'label association review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $explicitLabel = $form['children'][0];
        $wrappedLabel = $form['children'][2];
        $missingLabel = $form['children'][3];
        $overrideLabel = $form['children'][4];
        $hiddenLabel = $form['children'][6];

        $t->same('label', $explicitLabel['formLabel']);
        $t->same('Title required', $explicitLabel['labelText']);
        $t->same('title', $explicitLabel['forRaw']);
        $t->same('title', $explicitLabel['forId']);
        $t->same('for-attribute', $explicitLabel['labeledControlSource']);
        $t->same([
            'tag' => 'input',
            'id' => 'title',
            'controlName' => 'title',
            'effectiveDisabled' => false,
            'type' => 'text',
        ], $explicitLabel['labeledControl']);
        $t->same(0, $explicitLabel['nestedControlCount']);
        $t->same([], $explicitLabel['nestedControls']);

        $t->same('label', $wrappedLabel['formLabel']);
        $t->same('Wrapped Note', $wrappedLabel['labelText']);
        $t->same(null, $wrappedLabel['forRaw']);
        $t->same(null, $wrappedLabel['forId']);
        $t->same('descendant', $wrappedLabel['labeledControlSource']);
        $t->same([
            'tag' => 'textarea',
            'id' => 'notes',
            'controlName' => 'notes',
            'effectiveDisabled' => false,
        ], $wrappedLabel['labeledControl']);
        $t->same(1, $wrappedLabel['nestedControlCount']);
        $t->same([$wrappedLabel['labeledControl']], $wrappedLabel['nestedControls']);

        $t->same('missing-for-target', $missingLabel['labeledControlSource']);
        $t->same('missing', $missingLabel['forId']);
        $t->same(null, $missingLabel['labeledControl']);

        $t->same('for-attribute', $overrideLabel['labeledControlSource']);
        $t->same([
            'tag' => 'button',
            'id' => 'save',
            'controlName' => 'save',
            'effectiveDisabled' => true,
            'type' => 'submit',
        ], $overrideLabel['labeledControl']);
        $t->same(1, $overrideLabel['nestedControlCount']);
        $t->same([
            [
                'tag' => 'button',
                'id' => 'ignored',
                'controlName' => 'ignored',
                'effectiveDisabled' => false,
                'type' => 'submit',
            ],
        ], $overrideLabel['nestedControls']);

        $t->same('Hidden', $hiddenLabel['labelText']);
        $t->same('missing', $hiddenLabel['labeledControlSource']);
        $t->same(null, $hiddenLabel['labeledControl']);
        $t->same(0, $hiddenLabel['nestedControlCount']);
        $t->same('<form id="labels"><label for="title">Title <span>required</span></label><input id="title" name="title" value="Draft"><label>Wrapped <textarea id="notes" name="notes">Note</textarea></label><label for="missing">Missing</label><label for="save">Explicit <button id="ignored" name="ignored">Ignored</button></label><button disabled id="save" name="save">Save</button><label><input id="secret" name="secret" type="hidden" value="x"> Hidden</label></form>', $html);
    },
    'summarizes html form labels datalist and inherited disabled state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="import-form"><label for="format">Format</label><input id="format" name="format" list="format-options" required placeholder="Choose format"><datalist id="format-options"><option value="docx" label="Word"></option><option value="epub">EPUB</option><option>ODT</option></datalist><fieldset disabled><legend>Batch <button id="legend-action">Keep enabled</button></legend><label>Confirm <input id="confirm" name="confirm" type="checkbox" checked></label><select id="state" name="state" required><option value="draft">Draft</option></select><textarea id="notes" name="notes" placeholder="Reviewer note">Ready</textarea><button id="submit" type="submit" name="save" value="1">Save</button></fieldset></form>',
            'form label and datalist review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $formatInput = $form['children'][1];
        $datalist = $form['children'][2];
        $fieldset = $form['children'][3];
        $legend = $fieldset['children'][0];
        $legendButton = $legend['children'][1];
        $confirmInput = $fieldset['children'][1]['children'][1];
        $stateSelect = $fieldset['children'][2];
        $notes = $fieldset['children'][3];
        $submitButton = $fieldset['children'][4];
        $expectedOptions = [
            ['value' => 'docx', 'label' => 'Word', 'text' => '', 'disabled' => false],
            ['value' => 'epub', 'label' => 'EPUB', 'text' => 'EPUB', 'disabled' => false],
            ['value' => 'ODT', 'label' => 'ODT', 'text' => 'ODT', 'disabled' => false],
        ];

        $t->same('form', $form['name']);
        $t->same('input', $formatInput['formControl']);
        $t->same(['Format'], $formatInput['labels']);
        $t->same(true, $formatInput['required']);
        $t->same('Choose format', $formatInput['placeholder']);
        $t->same(false, $formatInput['effectiveDisabled']);
        $t->same('format-options', $formatInput['list']);
        $t->same($expectedOptions, $formatInput['datalistOptions']);
        $t->same('datalist', $datalist['formControl']);
        $t->same($expectedOptions, $datalist['datalistOptions']);
        $t->same(['disabled' => 'disabled'], $fieldset['attributes']);
        $t->same('fieldset', $fieldset['formGroup']);
        $t->same(true, $fieldset['disabled']);
        $t->same('Batch Keep enabled', $fieldset['legendText']);
        $t->same(1, $fieldset['legendCount']);
        $t->same(5, $fieldset['controlCount']);
        $t->same(1, $fieldset['legendControlCount']);
        $t->same(['confirm', 'state', 'notes', 'save'], $fieldset['controlNames']);
        $t->same([
            [
                'tag' => 'button',
                'id' => 'legend-action',
                'controlName' => null,
                'effectiveDisabled' => false,
                'inFirstLegend' => true,
                'type' => 'submit',
            ],
            [
                'tag' => 'input',
                'id' => 'confirm',
                'controlName' => 'confirm',
                'effectiveDisabled' => true,
                'inFirstLegend' => false,
                'type' => 'checkbox',
            ],
            [
                'tag' => 'select',
                'id' => 'state',
                'controlName' => 'state',
                'effectiveDisabled' => true,
                'inFirstLegend' => false,
            ],
            [
                'tag' => 'textarea',
                'id' => 'notes',
                'controlName' => 'notes',
                'effectiveDisabled' => true,
                'inFirstLegend' => false,
            ],
            [
                'tag' => 'button',
                'id' => 'submit',
                'controlName' => 'save',
                'effectiveDisabled' => true,
                'inFirstLegend' => false,
                'type' => 'submit',
            ],
        ], $fieldset['controls']);
        $t->same('legend', $legend['formGroupPart']);
        $t->same('Batch Keep enabled', $legend['legendText']);
        $t->same(true, $legend['fieldsetDisabled']);
        $t->same(true, $legend['firstLegend']);
        $t->same('button', $legendButton['formControl']);
        $t->same(false, $legendButton['effectiveDisabled']);
        $t->same('input', $confirmInput['formControl']);
        $t->same(['Confirm'], $confirmInput['labels']);
        $t->same(true, $confirmInput['checked']);
        $t->same(false, $confirmInput['disabled']);
        $t->same(true, $confirmInput['effectiveDisabled']);
        $t->same('select', $stateSelect['formControl']);
        $t->same(true, $stateSelect['required']);
        $t->same(true, $stateSelect['effectiveDisabled']);
        $t->same('textarea', $notes['formControl']);
        $t->same('Reviewer note', $notes['placeholder']);
        $t->same(true, $notes['effectiveDisabled']);
        $t->same('button', $submitButton['formControl']);
        $t->same(true, $submitButton['effectiveDisabled']);
        $t->same('<form id="import-form"><label for="format">Format</label><input id="format" list="format-options" name="format" placeholder="Choose format" required><datalist id="format-options"><option label="Word" value="docx"></option><option value="epub">EPUB</option><option>ODT</option></datalist><fieldset disabled><legend>Batch <button id="legend-action">Keep enabled</button></legend><label>Confirm <input checked id="confirm" name="confirm" type="checkbox"></label><select id="state" name="state" required><option value="draft">Draft</option></select><textarea id="notes" name="notes" placeholder="Reviewer note">Ready</textarea><button id="submit" name="save" type="submit" value="1">Save</button></fieldset></form>', $html);
    },
    'summarizes html progress and meter state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<label for="upload-progress">Upload</label><progress id="upload-progress" value="3" max="4">75%</progress><progress id="pending">Pending</progress><label>Quality <meter id="quality" value="0.82" min="0" max="1" low="0.4" high="0.9" optimum="0.95">82%</meter></label><meter id="clamped" value="12" min="2" max="10">Too high</meter>',
            'progress meter review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $progressLabel = $summary[0];
        $progress = $summary[1];
        $pending = $summary[2];
        $qualityLabel = $summary[3];
        $quality = $summary[3]['children'][1];
        $clamped = $summary[4];

        $t->same('label', $progressLabel['formLabel']);
        $t->same('for-attribute', $progressLabel['labeledControlSource']);
        $t->same('progress', $progressLabel['labeledControl']['tag']);
        $t->same('progress', $progressLabel['labeledControl']['measurement']);
        $t->same(3.0, $progressLabel['labeledControl']['value']);
        $t->same(4.0, $progressLabel['labeledControl']['max']);
        $t->same(0.75, $progressLabel['labeledControl']['position']);
        $t->same(false, $progressLabel['labeledControl']['indeterminate']);
        $t->same('progress', $progress['measurement']);
        $t->same(['Upload'], $progress['labels']);
        $t->same(3.0, $progress['value']);
        $t->same(4.0, $progress['max']);
        $t->same(0.75, $progress['position']);
        $t->same(false, $progress['indeterminate']);
        $t->same(null, $pending['value']);
        $t->same(null, $pending['position']);
        $t->same(true, $pending['indeterminate']);
        $t->same('label', $qualityLabel['formLabel']);
        $t->same('descendant', $qualityLabel['labeledControlSource']);
        $t->same('meter', $qualityLabel['labeledControl']['tag']);
        $t->same('meter', $qualityLabel['labeledControl']['measurement']);
        $t->same(0.82, $qualityLabel['labeledControl']['value']);
        $t->same(0.0, $qualityLabel['labeledControl']['min']);
        $t->same(1.0, $qualityLabel['labeledControl']['max']);
        $t->same(0.4, $qualityLabel['labeledControl']['low']);
        $t->same(0.9, $qualityLabel['labeledControl']['high']);
        $t->same(0.95, $qualityLabel['labeledControl']['optimum']);
        $t->same(1, $qualityLabel['nestedControlCount']);
        $t->same($qualityLabel['labeledControl'], $qualityLabel['nestedControls'][0]);
        $t->same('meter', $quality['measurement']);
        $t->same(['Quality 82%'], $quality['labels']);
        $t->same(0.82, $quality['value']);
        $t->same(0.0, $quality['min']);
        $t->same(1.0, $quality['max']);
        $t->same(0.4, $quality['low']);
        $t->same(0.9, $quality['high']);
        $t->same(0.95, $quality['optimum']);
        $t->same('meter', $clamped['measurement']);
        $t->same(10.0, $clamped['value']);
        $t->same(2.0, $clamped['min']);
        $t->same(10.0, $clamped['max']);
        $t->same('<label for="upload-progress">Upload</label><progress id="upload-progress" max="4" value="3">75%</progress><progress id="pending">Pending</progress><label>Quality <meter high="0.9" id="quality" low="0.4" max="1" min="0" optimum="0.95" value="0.82">82%</meter></label><meter id="clamped" max="10" min="2" value="12">Too high</meter>', $html);
    },
    'summarizes html disclosure state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<details id="packet" name="review" open><summary id="primary-summary">Package <span>review</span></summary><summary id="secondary-summary">Secondary label</summary><p>Body</p></details>'
                . '<details id="review-next" name=" review " open><summary>Next packet</summary></details>'
                . '<details id="missing-summary"><p>No summary</p></details>'
                . '<summary id="loose-summary">Loose label</summary>',
            'disclosure review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/disclosure-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $details = $summary[0];
        $detailsSummary = $details['children'][0];
        $secondarySummary = $details['children'][1];
        $secondDetails = $summary[1];
        $missingSummary = $summary[2];
        $looseSummary = $summary[3];

        $t->same('details', $details['name']);
        $t->same('details', $details['disclosure']);
        $t->same(true, $details['open']);
        $t->same('open', $details['detailsState']);
        $t->same('review', $details['detailsNameRaw']);
        $t->same('review', $details['detailsName']);
        $t->same(1, $details['detailsGroupIndex']);
        $t->same(2, $details['detailsGroupSize']);
        $t->same(2, $details['detailsGroupOpenCount']);
        $t->same(true, $details['detailsGroupOpenConflict']);
        $t->same('Package review', $details['summaryText']);
        $t->same('primary-summary', $details['primarySummaryId']);
        $t->same(2, $details['summaryElementCount']);
        $t->same([
            ['index' => 0, 'id' => 'primary-summary', 'text' => 'Package review', 'primary' => true, 'childElementCount' => 1],
            ['index' => 1, 'id' => 'secondary-summary', 'text' => 'Secondary label', 'primary' => false, 'childElementCount' => 0],
        ], $details['summaryElements']);
        $t->same('summary', $detailsSummary['name']);
        $t->same('summary', $detailsSummary['disclosure']);
        $t->same('Package review', $detailsSummary['label']);
        $t->same('packet', $detailsSummary['summaryForDetailsId']);
        $t->same('review', $detailsSummary['summaryForDetailsName']);
        $t->same(0, $detailsSummary['summaryIndex']);
        $t->same(true, $detailsSummary['summaryPrimary']);
        $t->same(1, $secondarySummary['summaryIndex']);
        $t->same(false, $secondarySummary['summaryPrimary']);

        $t->same(' review ', $secondDetails['detailsNameRaw']);
        $t->same('review', $secondDetails['detailsName']);
        $t->same(2, $secondDetails['detailsGroupIndex']);
        $t->same(true, $secondDetails['detailsGroupOpenConflict']);

        $t->same(false, $missingSummary['open']);
        $t->same('closed', $missingSummary['detailsState']);
        $t->same(null, $missingSummary['detailsName']);
        $t->same(0, $missingSummary['detailsGroupSize']);
        $t->same(null, $missingSummary['summaryText']);
        $t->same(0, $missingSummary['summaryElementCount']);
        $t->same([], $missingSummary['summaryElements']);

        $t->same('summary', $looseSummary['disclosure']);
        $t->same('Loose label', $looseSummary['label']);
        $t->same(null, $looseSummary['summaryForDetailsId']);
        $t->same(null, $looseSummary['summaryIndex']);
        $t->same(null, $looseSummary['summaryPrimary']);
        $t->same('<details id="packet" name="review" open><summary id="primary-summary">Package <span>review</span></summary><summary id="secondary-summary">Secondary label</summary><p>Body</p></details><details id="review-next" name=" review " open><summary>Next packet</summary></details><details id="missing-summary"><p>No summary</p></details><summary id="loose-summary">Loose label</summary>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/disclosure-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html dialog and popover state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dialog id="confirm" open popover="manual" aria-modal="true"><form method="dialog"><button value="ok">OK</button><button popovertarget="details-popover" popovertargetaction="show">More</button></form></dialog>'
                . '<aside id="details-popover" popover="auto">Extra</aside>'
                . '<button popovertarget="bad target" popovertargetaction="dismiss">Bad</button>',
            'dialog popover review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $dialog = $summary[0];
        $form = $dialog['children'][0];
        $okButton = $form['children'][0];
        $moreButton = $form['children'][1];
        $popover = $summary[1];
        $invalidButton = $summary[2];

        $t->same('dialog', $dialog['name']);
        $t->same('dialog', $dialog['dialog']);
        $t->same(true, $dialog['dialogOpen']);
        $t->same('OKMore', $dialog['dialogText']);
        $t->same('manual', $dialog['popoverRaw']);
        $t->same('manual', $dialog['popoverState']);
        $t->same(true, $dialog['popoverValid']);
        $t->same(['aria-modal' => 'true'], $dialog['ariaAttributes']);
        $t->same('form', $form['formSubmission']);
        $t->same('dialog', $form['method']);
        $t->same('button', $okButton['formControl']);
        $t->same('button', $moreButton['formControl']);
        $t->same('details-popover', $moreButton['popoverTargetRaw']);
        $t->same('details-popover', $moreButton['popoverTarget']);
        $t->same(true, $moreButton['popoverTargetValid']);
        $t->same('show', $moreButton['popoverTargetActionRaw']);
        $t->same('show', $moreButton['popoverTargetAction']);
        $t->same(true, $moreButton['popoverTargetActionValid']);
        $t->same('auto', $popover['popoverRaw']);
        $t->same('auto', $popover['popoverState']);
        $t->same(true, $popover['popoverValid']);
        $t->same('bad target', $invalidButton['popoverTargetRaw']);
        $t->same(null, $invalidButton['popoverTarget']);
        $t->same(false, $invalidButton['popoverTargetValid']);
        $t->same('dismiss', $invalidButton['popoverTargetActionRaw']);
        $t->same(null, $invalidButton['popoverTargetAction']);
        $t->same(false, $invalidButton['popoverTargetActionValid']);
        $t->same('<dialog aria-modal="true" id="confirm" open popover="manual"><form method="dialog"><button value="ok">OK</button><button popovertarget="details-popover" popovertargetaction="show">More</button></form></dialog><aside id="details-popover" popover="auto">Extra</aside><button popovertarget="bad target" popovertargetaction="dismiss">Bad</button>', $html);
    },
    'summarizes html dialog state and method dialog controls for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dialog id="review-dialog" open aria-labelledby="dialog-title"><h2 id="dialog-title">Review packet</h2>'
                . '<form id="review-close" method="dialog" action="/ignored"><button name="decision" value="approve">Approve</button>'
                . '<button value="cancel" formmethod="post">Cancel remotely</button><input type="submit" name="close" value="close"></form><p>Body</p></dialog>'
                . '<dialog id="closed"><form method="POST"><button value="noop">No close</button></form></dialog>',
            'dialog state review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/dialog-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $dialog = $summary[0];
        $dialogForm = $dialog['dialogMethodForms'][0];
        $approve = $dialogForm['submitters'][0];
        $remoteCancel = $dialogForm['submitters'][1];
        $inputClose = $dialogForm['submitters'][2];
        $closed = $summary[1];

        $t->same('dialog', $dialog['name']);
        $t->same('dialog', $dialog['dialog']);
        $t->same(true, $dialog['dialogOpen']);
        $t->same('open', $dialog['dialogState']);
        $t->same('Review packet', $dialog['dialogHeadingText']);
        $t->same('h2', $dialog['dialogHeadingTag']);
        $t->same(2, $dialog['dialogHeadingLevel']);
        $t->same(1, $dialog['dialogMethodFormCount']);
        $t->same('review-close', $dialogForm['id']);
        $t->same('dialog', $dialogForm['methodRaw']);
        $t->same('/ignored', $dialogForm['action']);
        $t->same(['approve', 'close'], $dialog['dialogCloseValues']);

        $t->same('button', $approve['tag']);
        $t->same('decision', $approve['name']);
        $t->same('approve', $approve['value']);
        $t->same('Approve', $approve['label']);
        $t->same('dialog', $approve['effectiveFormMethod']);
        $t->same(true, $approve['dialogCloses']);
        $t->same('post', $remoteCancel['formMethod']);
        $t->same('post', $remoteCancel['effectiveFormMethod']);
        $t->same(false, $remoteCancel['dialogCloses']);
        $t->same('input', $inputClose['tag']);
        $t->same('submit', $inputClose['type']);
        $t->same('close', $inputClose['name']);
        $t->same('close', $inputClose['value']);
        $t->same(false, $inputClose['effectiveDisabled']);

        $t->same('closed', $closed['elementId']);
        $t->same(false, $closed['dialogOpen']);
        $t->same('closed', $closed['dialogState']);
        $t->same(0, $closed['dialogMethodFormCount']);
        $t->same([], $closed['dialogCloseValues']);
        $t->same('<dialog aria-labelledby="dialog-title" id="review-dialog" open><h2 id="dialog-title">Review packet</h2><form action="/ignored" id="review-close" method="dialog"><button name="decision" value="approve">Approve</button><button formmethod="post" value="cancel">Cancel remotely</button><input name="close" type="submit" value="close"></form><p>Body</p></dialog><dialog id="closed"><form method="POST"><button value="noop">No close</button></form></dialog>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/dialog-state-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html insertion and deletion revision metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><ins cite="./changes/insert.html" datetime="2026-06-11 12:30Z">Inserted <em>text</em></ins>'
                . '<del cite="https://example.test/revision#old" datetime="2026-06-10T09:15:30-0500">Removed</del>'
                . '<ins datetime="2026-02-30">Invalid date</ins></p>',
            'revision review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $paragraph = $summary[0];
        $inserted = $paragraph['children'][0];
        $deleted = $paragraph['children'][1];
        $invalid = $paragraph['children'][2];

        $t->same('p', $paragraph['name']);
        $t->same('ins', $inserted['name']);
        $t->same('insertion', $inserted['revision']);
        $t->same('ins', $inserted['revisionTag']);
        $t->same('./changes/insert.html', $inserted['revisionCite']);
        $t->same('2026-06-11 12:30Z', $inserted['revisionDatetimeRaw']);
        $t->same('2026-06-11T12:30Z', $inserted['revisionDatetime']);
        $t->same('global-datetime', $inserted['revisionDatetimeKind']);
        $t->same(true, $inserted['revisionDatetimeValid']);
        $t->same('Inserted text', $inserted['text']);
        $t->same('em', $inserted['children'][1]['name']);
        $t->same('del', $deleted['name']);
        $t->same('deletion', $deleted['revision']);
        $t->same('https://example.test/revision#old', $deleted['revisionCite']);
        $t->same('2026-06-10T09:15:30-05:00', $deleted['revisionDatetime']);
        $t->same('global-datetime', $deleted['revisionDatetimeKind']);
        $t->same(true, $deleted['revisionDatetimeValid']);
        $t->same('ins', $invalid['name']);
        $t->same('2026-02-30', $invalid['revisionDatetimeRaw']);
        $t->same(null, $invalid['revisionDatetime']);
        $t->same('invalid', $invalid['revisionDatetimeKind']);
        $t->same(false, $invalid['revisionDatetimeValid']);
        $t->same(
            '<p><ins cite="./changes/insert.html" datetime="2026-06-11 12:30Z">Inserted <em>text</em></ins><del cite="https://example.test/revision#old" datetime="2026-06-10T09:15:30-0500">Removed</del><ins datetime="2026-02-30">Invalid date</ins></p>',
            $html
        );
    },
    'summarizes html quote citation provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<blockquote cite="https://example.test/source#quote"><p>Quoted <em>source</em></p></blockquote>'
                . '<p>Inline <q cite="./inline.html">quoted <strong>claim</strong></q> and <q>uncited</q> from <cite>Packet Title</cite>.</p>',
            'quote citation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $blockquote = $summary[0];
        $paragraph = $summary[1];
        $inlineQuote = $paragraph['children'][1];
        $uncitedQuote = $paragraph['children'][3];
        $citedWork = $paragraph['children'][5];

        $t->same('blockquote', $blockquote['name']);
        $t->same('block', $blockquote['quote']);
        $t->same('blockquote', $blockquote['quoteTag']);
        $t->same('https://example.test/source#quote', $blockquote['quoteCite']);
        $t->same('Quoted source', $blockquote['quoteText']);
        $t->same('p', $blockquote['children'][0]['name']);
        $t->same('q', $inlineQuote['name']);
        $t->same('inline', $inlineQuote['quote']);
        $t->same('q', $inlineQuote['quoteTag']);
        $t->same('./inline.html', $inlineQuote['quoteCite']);
        $t->same('quoted claim', $inlineQuote['quoteText']);
        $t->same('strong', $inlineQuote['children'][1]['name']);
        $t->same('q', $uncitedQuote['name']);
        $t->same(null, $uncitedQuote['quoteCite']);
        $t->same('uncited', $uncitedQuote['quoteText']);
        $t->same('cite', $citedWork['name']);
        $t->same('cite', $citedWork['citedWork']);
        $t->same('Packet Title', $citedWork['citedWorkText']);
        $t->same(
            '<blockquote cite="https://example.test/source#quote"><p>Quoted <em>source</em></p></blockquote><p>Inline <q cite="./inline.html">quoted <strong>claim</strong></q> and <q>uncited</q> from <cite>Packet Title</cite>.</p>',
            $html
        );
    },
    'summarizes html quote attribution and cite text rollups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<blockquote id="packet-quote" cite=" https://example.test/review#source "><p>Imported <q cite=" ./inline.html ">inline <cite>Manual</cite></q> note.</p><footer>Source <cite>Reviewer Handbook</cite></footer></blockquote>'
                . '<p>Standalone <cite data-review="work">Packet Guide</cite></p>',
            'quote attribution review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $blockquote = $summary[0];
        $inlineQuote = $blockquote['children'][0]['children'][1];
        $inlineCitation = $inlineQuote['children'][1];
        $footer = $blockquote['children'][1];
        $footerCitation = $footer['children'][1];
        $standaloneCitation = $summary[1]['children'][1];

        $t->same('block', $blockquote['quote']);
        $t->same(' https://example.test/review#source ', $blockquote['quoteCite']);
        $t->same(' https://example.test/review#source ', $blockquote['quoteCiteRaw']);
        $t->same('https://example.test/review#source', $blockquote['quoteCiteNormalized']);
        $t->same('Imported inline Manual note.Source Reviewer Handbook', $blockquote['quoteText']);
        $t->same('Source Reviewer Handbook', $blockquote['attributionText']);
        $t->same(['Manual', 'Reviewer Handbook'], $blockquote['citationTexts']);
        $t->same(2, $blockquote['citationCount']);
        $t->same('footer', $footer['name']);

        $t->same('inline', $inlineQuote['quote']);
        $t->same(' ./inline.html ', $inlineQuote['quoteCiteRaw']);
        $t->same('./inline.html', $inlineQuote['quoteCiteNormalized']);
        $t->same('inline Manual', $inlineQuote['quoteText']);
        $t->same(null, $inlineQuote['attributionText']);
        $t->same(['Manual'], $inlineQuote['citationTexts']);
        $t->same(1, $inlineQuote['citationCount']);

        $t->same('cite', $inlineCitation['citedWork']);
        $t->same('Manual', $inlineCitation['citedWorkText']);
        $t->same('cite', $inlineCitation['citation']);
        $t->same('Manual', $inlineCitation['citationText']);
        $t->same('Reviewer Handbook', $footerCitation['citationText']);
        $t->same('Packet Guide', $standaloneCitation['citationText']);
        $t->same(['review' => 'work'], $standaloneCitation['dataset']);
        $t->same('<blockquote cite=" https://example.test/review#source " id="packet-quote"><p>Imported <q cite=" ./inline.html ">inline <cite>Manual</cite></q> note.</p><footer>Source <cite>Reviewer Handbook</cite></footer></blockquote><p>Standalone <cite data-review="work">Packet Guide</cite></p>', $html);
    },
    'summarizes html media resource state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="preview" controls muted loop poster="cover.jpg" preload="metadata"><source src="movie.webm" type="video/webm"><source src="movie.mp4" type="video/mp4" media="(min-width: 40em)"><track default kind="captions" label="English" srclang="en" src="captions.vtt">Fallback <a href="movie.mp4">download</a></video>'
                . '<audio id="sample" autoplay preload="bogus" src="sample.mp3"><source src="sample.ogg" type="audio/ogg"><track kind="chapters" src="chapters.vtt" srclang="en" label="Chapters">Audio fallback</audio>',
            'media resource review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $video = $summary[0];
        $audio = $summary[1];

        $t->same('video', $video['media']);
        $t->same(true, $video['controls']);
        $t->same(false, $video['autoplay']);
        $t->same(true, $video['loop']);
        $t->same(true, $video['muted']);
        $t->same('metadata', $video['preload']);
        $t->same('cover.jpg', $video['poster']);
        $t->same([
            ['src' => 'movie.webm', 'type' => 'video/webm'],
            ['src' => 'movie.mp4', 'type' => 'video/mp4', 'media' => '(min-width: 40em)'],
        ], $video['sources']);
        $t->same([
            ['kind' => 'captions', 'src' => 'captions.vtt', 'srclang' => 'en', 'label' => 'English', 'default' => true],
        ], $video['tracks']);
        $t->same('Fallback download', $video['fallbackText']);
        $t->same('audio', $audio['media']);
        $t->same(false, $audio['controls']);
        $t->same(true, $audio['autoplay']);
        $t->same(false, $audio['loop']);
        $t->same(false, $audio['muted']);
        $t->same('auto', $audio['preload']);
        $t->same([
            ['src' => 'sample.mp3'],
            ['src' => 'sample.ogg', 'type' => 'audio/ogg'],
        ], $audio['sources']);
        $t->same([
            ['kind' => 'chapters', 'src' => 'chapters.vtt', 'srclang' => 'en', 'label' => 'Chapters', 'default' => false],
        ], $audio['tracks']);
        $t->same('Audio fallback', $audio['fallbackText']);
        $t->same('<video controls id="preview" loop muted poster="cover.jpg" preload="metadata"><source src="movie.webm" type="video/webm"><source media="(min-width: 40em)" src="movie.mp4" type="video/mp4"><track default kind="captions" label="English" src="captions.vtt" srclang="en">Fallback <a href="movie.mp4">download</a></video><audio autoplay id="sample" preload="bogus" src="sample.mp3"><source src="sample.ogg" type="audio/ogg"><track kind="chapters" label="Chapters" src="chapters.vtt" srclang="en">Audio fallback</audio>', $html);
    },
    'summarizes html media text track provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="review" controls>'
                . '<track default kind="CAPTIONS" srclang="EN-us" label="English captions" src="captions-en.vtt">'
                . '<track default kind="subtitles" label="No language" src="captions-missing.vtt">'
                . '<track kind="transcript" srclang="bad&lt;tag&gt;" label="" src="bad.vtt">'
                . '<track kind="metadata" srclang="x-review" label="Cue data" src="metadata.vtt">'
                . '</video>',
            'media text track review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/media-text-track-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $video = $summary[0];
        $tracks = $video['textTracks'];

        $t->same(4, $video['textTrackCount']);
        $t->same(['captions' => 1, 'metadata' => 1, 'subtitles' => 2], $video['textTrackKinds']);
        $t->same(['en-US', 'x-review'], $video['textTrackLanguages']);
        $t->same(['en-US'], $video['subtitleTextTrackLanguages']);
        $t->same(2, $video['defaultTextTrackCount']);
        $t->same(['English captions', 'No language'], $video['defaultTextTrackLabels']);
        $t->same(true, $video['defaultTextTrackConflict']);
        $t->same(1, $video['invalidTextTrackKindCount']);
        $t->same(1, $video['invalidTextTrackLanguageCount']);
        $t->same(2, $video['missingSubtitleLanguageCount']);
        $t->same([
            ['code' => 'multiple-default-tracks', 'count' => 2],
            ['code' => 'missing-text-track-language', 'trackIndex' => 1, 'kind' => 'subtitles', 'label' => 'No language', 'src' => 'captions-missing.vtt'],
            ['code' => 'invalid-text-track-kind', 'trackIndex' => 2, 'kindRaw' => 'transcript', 'normalizedKind' => 'subtitles'],
            ['code' => 'invalid-text-track-language', 'trackIndex' => 2, 'srclangRaw' => 'bad<tag>'],
            ['code' => 'missing-text-track-language', 'trackIndex' => 2, 'kind' => 'subtitles', 'label' => '', 'src' => 'bad.vtt'],
        ], $video['textTrackIssues']);
        $t->same([
            'index' => 0,
            'src' => 'captions-en.vtt',
            'kindRaw' => 'CAPTIONS',
            'kind' => 'captions',
            'kindValid' => true,
            'srclangRaw' => 'EN-us',
            'srclang' => 'en-US',
            'srclangValid' => true,
            'label' => 'English captions',
            'default' => true,
            'languageRequired' => true,
            'languageMissing' => false,
        ], $tracks[0]);
        $t->same(false, $tracks[2]['kindValid']);
        $t->same(false, $tracks[2]['srclangValid']);
        $t->same(true, $tracks[2]['languageMissing']);
        $t->contains($html, $blocks);
        $t->same('/migration/media-text-track-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html canvas fallback state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<canvas id="chart" width="640" height="360"><p>Quarterly <a href="chart-data.csv">data table</a></p><img src="chart.png" alt="Static chart"></canvas>'
                . '<canvas width="-1" height="bad">Fallback only</canvas>',
            'canvas fallback review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/canvas-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $canvas = $summary[0];
        $invalidCanvas = $summary[1];

        $t->same('canvas', $canvas['name']);
        $t->same('canvas', $canvas['embeddedResource']);
        $t->same('640', $canvas['width']);
        $t->same('360', $canvas['height']);
        $t->same(640, $canvas['bitmapWidth']);
        $t->same(360, $canvas['bitmapHeight']);
        $t->same(['p', 'img'], $canvas['fallbackElementNames']);
        $t->same(2, $canvas['fallbackElementCount']);
        $t->same('Quarterly data table', $canvas['fallbackText']);
        $t->same(strlen('Quarterly data table'), $canvas['fallbackTextLength']);
        $t->same(hash('sha256', 'Quarterly data table'), $canvas['fallbackTextSha256']);
        $t->same('canvas-fallback-source', $canvas['canvasReviewPolicy']);
        $t->same('a', $canvas['children'][0]['children'][1]['name']);
        $t->same('chart-data.csv', $canvas['children'][0]['children'][1]['href']);
        $t->same('image', $canvas['children'][1]['embeddedResource']);
        $t->same('chart.png', $canvas['children'][1]['src']);

        $t->same('canvas', $invalidCanvas['embeddedResource']);
        $t->same('-1', $invalidCanvas['width']);
        $t->same('bad', $invalidCanvas['height']);
        $t->same(300, $invalidCanvas['bitmapWidth']);
        $t->same(150, $invalidCanvas['bitmapHeight']);
        $t->same([], $invalidCanvas['fallbackElementNames']);
        $t->same(0, $invalidCanvas['fallbackElementCount']);
        $t->same('Fallback only', $invalidCanvas['fallbackText']);
        $t->same(strlen('Fallback only'), $invalidCanvas['fallbackTextLength']);
        $t->same(hash('sha256', 'Fallback only'), $invalidCanvas['fallbackTextSha256']);
        $t->same('<canvas height="360" id="chart" width="640"><p>Quarterly <a href="chart-data.csv">data table</a></p><img alt="Static chart" src="chart.png"></canvas><canvas height="bad" width="-1">Fallback only</canvas>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/canvas-fallback-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html embedded image and media source candidates for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<picture><source media="(min-width: 60em)" type="image/avif" srcset="hero.avif 1x, hero@2x.avif 2x"><source type="image/webp" srcset="hero.webp 800w"><img src="hero.jpg" srcset="hero-small.jpg 400w, hero-large.jpg 1200w" sizes="100vw" alt="Hero &amp; Source" loading="lazy" decoding="async"></picture>'
                . '<video controls poster="poster.jpg" preload="metadata"><source src="clip.webm" type="video/webm"><source src="clip.mp4" type="video/mp4" media="screen"><track kind="captions" srclang="en" label="English" src="captions.vtt" default></video>'
                . '<audio src="chapter.mp3" controls><source src="chapter.ogg" type="audio/ogg"></audio>'
                . '<iframe src="frame.html" srcdoc="&lt;p&gt;Preview&lt;/p&gt;" sandbox="allow-scripts allow-forms" allowfullscreen loading="lazy" referrerpolicy="no-referrer" width="640" height="360">Legacy frame fallback</iframe>'
                . '<embed src="plugin.swf" type="application/x-shockwave-flash" width="320" height="32"></embed>'
                . '<object data="diagram.svg" type="image/svg+xml" name="diagram" width="640" height="480"><param name="quality" value="high"><param name="review-url" value="packet.html" valuetype="ref" type="text/html">Object fallback</object>',
            'embedded media review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $picture = $summary[0];
        $image = $picture['image'];
        $video = $summary[1];
        $audio = $summary[2];
        $iframe = $summary[3];
        $embed = $summary[4];
        $object = $summary[5];

        $t->same('picture', $picture['embeddedResource']);
        $t->same(2, count($picture['pictureSources']));
        $t->same('image/avif', $picture['pictureSources'][0]['type']);
        $t->same('(min-width: 60em)', $picture['pictureSources'][0]['media']);
        $t->same('hero.avif', $picture['pictureSources'][0]['srcsetCandidates'][0]['url']);
        $t->same(['2x'], $picture['pictureSources'][0]['srcsetCandidates'][1]['descriptors']);
        $t->same('image', $image['embeddedResource']);
        $t->same('hero.jpg', $image['src']);
        $t->same('Hero & Source', $image['alt']);
        $t->same('hero-small.jpg', $image['srcsetCandidates'][0]['url']);
        $t->same('1200w', $image['srcsetCandidates'][1]['descriptor']);
        $t->same('100vw', $image['sizes']);
        $t->same('lazy', $image['loading']);
        $t->same('async', $image['decoding']);

        $t->same('video', $video['embeddedResource']);
        $t->same(true, $video['controls']);
        $t->same('poster.jpg', $video['poster']);
        $t->same('metadata', $video['preload']);
        $t->same('clip.webm', $video['mediaSources'][0]['src']);
        $t->same('video/mp4', $video['mediaSources'][1]['type']);
        $t->same('screen', $video['mediaSources'][1]['media']);
        $t->same('captions', $video['tracks'][0]['kind']);
        $t->same('en', $video['tracks'][0]['srclang']);
        $t->same('English', $video['tracks'][0]['label']);
        $t->same('captions.vtt', $video['tracks'][0]['src']);
        $t->same(true, $video['tracks'][0]['default']);

        $t->same('audio', $audio['embeddedResource']);
        $t->same('chapter.mp3', $audio['src']);
        $t->same(true, $audio['controls']);
        $t->same('chapter.ogg', $audio['mediaSources'][0]['src']);
        $t->same('audio/ogg', $audio['mediaSources'][0]['type']);

        $t->same('iframe', $iframe['embeddedResource']);
        $t->same('frame.html', $iframe['src']);
        $t->same('<p>Preview</p>', $iframe['srcdoc']);
        $t->same(['allow-scripts', 'allow-forms'], $iframe['sandboxTokens']);
        $t->same(true, $iframe['allowFullscreen']);
        $t->same('Legacy frame fallback', $iframe['fallbackText']);

        $t->same('embed', $embed['embeddedResource']);
        $t->same('plugin.swf', $embed['src']);
        $t->same('application/x-shockwave-flash', $embed['mimeType']);
        $t->same('320', $embed['width']);

        $t->same('object', $object['embeddedResource']);
        $t->same('diagram.svg', $object['data']);
        $t->same('image/svg+xml', $object['mimeType']);
        $t->same('diagram', $object['nameAttribute']);
        $t->same([
            ['paramName' => 'quality', 'value' => 'high', 'valueType' => null, 'mimeType' => null],
            ['paramName' => 'review-url', 'value' => 'packet.html', 'valueType' => 'ref', 'mimeType' => 'text/html'],
        ], $object['params']);
        $t->same('param', $object['children'][0]['embeddedResource']);
        $t->same('quality', $object['children'][0]['paramName']);
        $t->same('Object fallback', $object['fallbackText']);
        $t->same('<picture><source media="(min-width: 60em)" srcset="hero.avif 1x, hero@2x.avif 2x" type="image/avif"><source srcset="hero.webp 800w" type="image/webp"><img alt="Hero &amp; Source" decoding="async" loading="lazy" sizes="100vw" src="hero.jpg" srcset="hero-small.jpg 400w, hero-large.jpg 1200w"></picture><video controls poster="poster.jpg" preload="metadata"><source src="clip.webm" type="video/webm"><source media="screen" src="clip.mp4" type="video/mp4"><track default kind="captions" label="English" src="captions.vtt" srclang="en"></video><audio controls src="chapter.mp3"><source src="chapter.ogg" type="audio/ogg"></audio><iframe allowfullscreen height="360" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-forms" src="frame.html" srcdoc="&lt;p&gt;Preview&lt;/p&gt;" width="640">Legacy frame fallback</iframe><embed height="32" src="plugin.swf" type="application/x-shockwave-flash" width="320"><object data="diagram.svg" height="480" name="diagram" type="image/svg+xml" width="640"><param name="quality" value="high"></param><param name="review-url" type="text/html" value="packet.html" valuetype="ref"></param>Object fallback</object>', $html);
    },
    'summarizes html object param review provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<object id="player" data="player.swf" type="application/x-shockwave-flash">'
                . '<param name="Movie" value="movie.swf" valuetype="ref" type="application/x-shockwave-flash">'
                . '<param name="movie" value="override.swf" valuetype="REF">'
                . '<param name="controller" value="control-panel" valuetype="object">'
                . '<param value="loose"><param name=" " value="blank">'
                . '<param name="bad&lt;tag" value="bad" valuetype="bogus">Fallback</object>',
            'object param review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/object-param-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $object = $summary[0];
        $firstParam = $object['paramDetails'][0];
        $implicitParam = $object['paramDetails'][3];
        $invalidParam = $object['paramDetails'][5];
        $childParam = $object['children'][0];

        $t->same('object', $object['embeddedResource']);
        $t->same(6, $object['paramCount']);
        $t->same(['Movie', 'controller'], $object['paramNames']);
        $t->same(['Movie'], $object['duplicateParamNames']);
        $t->same(2, $object['unnamedParamCount']);
        $t->same(1, $object['invalidParamNameCount']);
        $t->same(1, $object['invalidParamValueTypeCount']);
        $t->same(2, $object['refParamCount']);
        $t->same(1, $object['objectReferenceParamCount']);
        $t->same([
            ['index' => 0, 'paramName' => 'Movie', 'value' => 'movie.swf', 'mimeType' => 'application/x-shockwave-flash', 'valueType' => 'ref'],
            ['index' => 1, 'paramName' => 'movie', 'value' => 'override.swf', 'mimeType' => null, 'valueType' => 'ref'],
        ], $object['refParams']);
        $t->same([
            ['index' => 2, 'paramName' => 'controller', 'value' => 'control-panel', 'mimeType' => null, 'valueType' => 'object'],
        ], $object['objectReferenceParams']);
        $t->same([
            ['code' => 'duplicate-param-name', 'paramName' => 'Movie', 'paramNameKey' => 'movie'],
            ['code' => 'missing-param-name', 'paramIndex' => 3, 'value' => 'loose'],
            ['code' => 'missing-param-name', 'paramIndex' => 4, 'value' => 'blank'],
            ['code' => 'invalid-param-name', 'paramIndex' => 5, 'paramNameRaw' => 'bad<tag'],
            ['code' => 'invalid-param-valuetype', 'paramIndex' => 5, 'paramName' => 'bad<tag', 'valueTypeRaw' => 'bogus'],
        ], $object['paramIssues']);

        $t->same('Movie', $firstParam['paramNameRaw']);
        $t->same('Movie', $firstParam['paramNameNormalized']);
        $t->same('movie', $firstParam['paramNameKey']);
        $t->same(true, $firstParam['paramNameValid']);
        $t->same('movie.swf', $firstParam['valueRaw']);
        $t->same('ref', $firstParam['valueTypeRaw']);
        $t->same('ref', $firstParam['valueTypeState']);
        $t->same(true, $firstParam['valueTypeExplicit']);
        $t->same(true, $firstParam['valueTypeValid']);
        $t->same('application/x-shockwave-flash', $firstParam['mimeType']);

        $t->same(null, $implicitParam['paramNameNormalized']);
        $t->same(null, $implicitParam['paramNameKey']);
        $t->same(false, $implicitParam['paramNameValid']);
        $t->same('data', $implicitParam['valueTypeState']);
        $t->same(false, $implicitParam['valueTypeExplicit']);
        $t->same(true, $implicitParam['valueTypeValid']);

        $t->same('bad<tag', $invalidParam['paramNameNormalized']);
        $t->same(false, $invalidParam['paramNameValid']);
        $t->same('bogus', $invalidParam['valueTypeRaw']);
        $t->same('data', $invalidParam['valueTypeState']);
        $t->same(true, $invalidParam['valueTypeExplicit']);
        $t->same(false, $invalidParam['valueTypeValid']);

        $t->same('param', $childParam['embeddedResource']);
        $t->same('Movie', $childParam['paramNameNormalized']);
        $t->same('ref', $childParam['valueTypeState']);
        $t->same('Fallback', $object['fallbackText']);
        $t->same('<object data="player.swf" id="player" type="application/x-shockwave-flash"><param name="Movie" type="application/x-shockwave-flash" value="movie.swf" valuetype="ref"></param><param name="movie" value="override.swf" valuetype="REF"></param><param name="controller" value="control-panel" valuetype="object"></param><param value="loose"></param><param name=" " value="blank"></param><param name="bad&lt;tag" value="bad" valuetype="bogus"></param>Fallback</object>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/object-param-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html object form and image-map associations for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="review-form" action="/review" method="post"></form>'
                . '<object id="packet-diagram" data="diagram.svg" type="image/svg+xml" name="diagram" form="review-form" usemap="#diagram-map" typemustmatch>Diagram fallback</object>'
                . '<object id="missing-form-object" data="fallback.bin" form="missing-form" usemap="bad target"></object>'
                . '<map name="diagram-map"><area alt="Detail" href="#detail" shape="rect" coords="0,0,10,10"></map>',
            'object association review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/object-association-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $object = $summary[1];
        $missingFormObject = $summary[2];
        $map = $summary[3];

        $t->same('object', $object['embeddedResource']);
        $t->same('diagram.svg', $object['data']);
        $t->same('image/svg+xml', $object['mimeType']);
        $t->same(true, $object['typeMustMatch']);
        $t->same('review-form', $object['formOwnerRaw']);
        $t->same('review-form', $object['formOwnerTargetId']);
        $t->same('review-form', $object['formOwnerId']);
        $t->same('form-attribute', $object['formOwnerSource']);
        $t->same(true, $object['formOwnerFound']);
        $t->same('/review', $object['formOwnerAction']);
        $t->same('post', $object['formOwnerMethod']);
        $t->same('#diagram-map', $object['useMapRaw']);
        $t->same('diagram-map', $object['useMapName']);
        $t->same(true, $object['useMapValid']);
        $t->same('Diagram fallback', $object['fallbackText']);

        $t->same('object', $missingFormObject['embeddedResource']);
        $t->same(false, $missingFormObject['typeMustMatch']);
        $t->same('missing-form', $missingFormObject['formOwnerTargetId']);
        $t->same('missing-form-attribute', $missingFormObject['formOwnerSource']);
        $t->same(false, $missingFormObject['formOwnerFound']);
        $t->same('bad target', $missingFormObject['useMapRaw']);
        $t->same('bad target', $missingFormObject['useMapName']);
        $t->same(false, $missingFormObject['useMapValid']);

        $t->same('map', $map['imageMap']);
        $t->same('diagram-map', $map['mapName']);
        $t->same(true, $map['mapNameValid']);
        $t->same(['#detail'], $map['areaHrefs']);
        $t->same(['Detail'], $map['areaLabels']);
        $t->same('<form action="/review" id="review-form" method="post"></form><object data="diagram.svg" form="review-form" id="packet-diagram" name="diagram" type="image/svg+xml" typemustmatch usemap="#diagram-map">Diagram fallback</object><object data="fallback.bin" form="missing-form" id="missing-form-object" usemap="bad target"></object><map name="diagram-map"><area alt="Detail" coords="0,0,10,10" href="#detail" shape="rect"></map>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/object-association-review.html', $document->children[0]->attr('part'));
    },
    'summarizes iframe srcdoc as inert parsed review provenance' => static function (TestRunner $t): void {
        $srcdoc = implode("\n", [
            '<article data-review="srcdoc">',
            '<h1>Preview</h1>',
            '<p>Open <a href="chapter.html#one">chapter</a><img src="cover.jpg" alt="Cover"></p>',
            '<form action="/review" method="post"><input name="q" value="ok"></form>',
            '<iframe src="nested.html">Nested</iframe>',
            '<canvas>Fallback chart</canvas>',
            '</article>',
        ]);
        $unsafeSrcdoc = '<section><!DOCTYPE html [<!ENTITY reviewer SYSTEM "file:///etc/passwd">]><p>Unsafe</p></section>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe src="frame.html" srcdoc="' . htmlspecialchars($srcdoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Legacy frame fallback</iframe>'
                . '<iframe srcdoc="' . htmlspecialchars($unsafeSrcdoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Unsafe fallback</iframe>',
            'iframe srcdoc review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $iframe = $summary[0];
        $unsafeIframe = $summary[1];

        $t->same('iframe', $iframe['embeddedResource']);
        $t->same($srcdoc, $iframe['srcdoc']);
        $t->same('iframe-srcdoc-inert-fragment-review', $iframe['srcdocReviewPolicy']);
        $t->same(strlen($srcdoc), $iframe['srcdocByteLength']);
        $t->same(hash('sha256', $srcdoc), $iframe['srcdocSha256']);
        $t->same(true, $iframe['srcdocParsed']);
        $t->same([], $iframe['srcdocDiagnostics']);
        $t->same(['article'], $iframe['srcdocTopLevelElementNames']);
        $t->same(1, $iframe['srcdocTopLevelElementCount']);
        $t->same('Preview Open chapter Nested Fallback chart', $iframe['srcdocText']);
        $t->same(strlen('Preview Open chapter Nested Fallback chart'), $iframe['srcdocTextLength']);
        $t->same(hash('sha256', 'Preview Open chapter Nested Fallback chart'), $iframe['srcdocTextSha256']);
        $t->same(['chapter.html#one'], $iframe['srcdocLinkHrefs']);
        $t->same(['cover.jpg'], $iframe['srcdocImageSources']);
        $t->same(1, $iframe['srcdocFormCount']);
        $t->same(['/review'], $iframe['srcdocFormActions']);
        $t->same([], $iframe['srcdocActiveElementNames']);
        $t->same(['iframe', 'canvas'], $iframe['srcdocEmbeddedElementNames']);
        $t->same('Legacy frame fallback', $iframe['fallbackText']);

        $t->same($unsafeSrcdoc, $unsafeIframe['srcdoc']);
        $t->same(false, $unsafeIframe['srcdocParsed']);
        $t->same(['srcdoc-unsafe-or-unparseable'], $unsafeIframe['srcdocDiagnostics']);
        $t->contains('document type', $unsafeIframe['srcdocError']);
        $t->same('Unsafe fallback', $unsafeIframe['fallbackText']);
        $t->contains('srcdoc="&lt;article data-review=&quot;srcdoc&quot;&gt;', $html);
    },
    'summarizes html hyperlinks and image-map areas for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>See <a href="chapter.html#intro" target="_blank" rel="noopener noreferrer tag" download="packet.html" hreflang="en" type="text/html" ping="/audit /log" referrerpolicy="no-referrer">Chapter <span>one</span></a></p>'
                . '<p><img src="diagram.png" alt="Diagram" usemap="#figures"><img src="bad.png" alt="Bad" usemap="bad target"></p>'
                . '<map name="figures"><area shape="rect" coords="0,0,10,10" href="diagram.png#hotspot" alt="Diagram hotspot" target="_self" rel="help external"></map>',
            'hyperlink review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $anchor = $summary[0]['children'][1];
        $image = $summary[1]['children'][0];
        $invalidImage = $summary[1]['children'][1];
        $map = $summary[2];
        $area = $map['children'][0];

        $t->same('a', $anchor['name']);
        $t->same('a', $anchor['hyperlink']);
        $t->same('chapter.html#intro', $anchor['href']);
        $t->same('_blank', $anchor['target']);
        $t->same('noopener noreferrer tag', $anchor['relRaw']);
        $t->same(['noopener', 'noreferrer', 'tag'], $anchor['relTokens']);
        $t->same('packet.html', $anchor['download']);
        $t->same('en', $anchor['hreflang']);
        $t->same('text/html', $anchor['mimeType']);
        $t->same('/audit /log', $anchor['pingRaw']);
        $t->same(['/audit', '/log'], $anchor['pingUrls']);
        $t->same('no-referrer', $anchor['referrerpolicy']);
        $t->same('Chapter one', $anchor['label']);
        $t->same('image', $image['embeddedResource']);
        $t->same('diagram.png', $image['src']);
        $t->same('Diagram', $image['alt']);
        $t->same('#figures', $image['useMapRaw']);
        $t->same('figures', $image['useMapName']);
        $t->same(true, $image['useMapValid']);
        $t->same('bad target', $invalidImage['useMapRaw']);
        $t->same('bad target', $invalidImage['useMapName']);
        $t->same(false, $invalidImage['useMapValid']);
        $t->same('map', $map['name']);
        $t->same(['name' => 'figures'], $map['attributes']);
        $t->same('map', $map['imageMap']);
        $t->same('figures', $map['mapNameRaw']);
        $t->same('figures', $map['mapName']);
        $t->same(true, $map['mapNameValid']);
        $t->same(1, $map['areaCount']);
        $t->same(['diagram.png#hotspot'], $map['areaHrefs']);
        $t->same(['Diagram hotspot'], $map['areaLabels']);
        $t->same('diagram.png#hotspot', $map['areas'][0]['href']);
        $t->same('area', $area['name']);
        $t->same('area', $area['hyperlink']);
        $t->same('diagram.png#hotspot', $area['href']);
        $t->same('Diagram hotspot', $area['label']);
        $t->same('rect', $area['shape']);
        $t->same('0,0,10,10', $area['coords']);
        $t->same(['help', 'external'], $area['relTokens']);
        $t->same('<p>See <a download="packet.html" href="chapter.html#intro" hreflang="en" ping="/audit /log" referrerpolicy="no-referrer" rel="noopener noreferrer tag" target="_blank" type="text/html">Chapter <span>one</span></a></p><p><img alt="Diagram" src="diagram.png" usemap="#figures"><img alt="Bad" src="bad.png" usemap="bad target"></p><map name="figures"><area alt="Diagram hotspot" coords="0,0,10,10" href="diagram.png#hotspot" rel="help external" shape="rect" target="_self"></map>', $html);
    },
    'summarizes html base link and meta metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<base href="https://example.test/docs/" target="_blank">'
                . '<link rel="preload stylesheet modulepreload" href="review.css" as="style" type="text/css" media="screen and (min-width: 40em)" hreflang="en" crossorigin="anonymous" integrity="sha384-review" referrerpolicy="no-referrer" sizes="any" imagesrcset="cover.avif 1x, cover@2x.avif 2x" imagesizes="100vw" fetchpriority="high">'
                . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta property="og:title" content="Review Packet"><meta http-equiv="refresh" content="5; url=https://example.test/next?stage=review"><p>Body</p>',
            'document metadata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/document-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $base = $summary[0];
        $link = $summary[1];
        $charsetMeta = $summary[2];
        $viewportMeta = $summary[3];
        $propertyMeta = $summary[4];
        $refreshMeta = $summary[5];
        $paragraph = $summary[6];

        $t->same('base', $base['documentMetadata']);
        $t->same('https://example.test/docs/', $base['href']);
        $t->same('_blank', $base['target']);
        $t->same('link', $link['documentMetadata']);
        $t->same('review.css', $link['href']);
        $t->same('preload stylesheet modulepreload', $link['relRaw']);
        $t->same(['preload', 'stylesheet', 'modulepreload'], $link['relTokens']);
        $t->same('style', $link['as']);
        $t->same('screen and (min-width: 40em)', $link['media']);
        $t->same('en', $link['hreflang']);
        $t->same('text/css', $link['mimeType']);
        $t->same('anonymous', $link['crossorigin']);
        $t->same('sha384-review', $link['integrity']);
        $t->same('no-referrer', $link['referrerpolicy']);
        $t->same('any', $link['sizes']);
        $t->same('cover.avif 1x, cover@2x.avif 2x', $link['imageSrcset']);
        $t->same('cover.avif', $link['imageSrcsetCandidates'][0]['url']);
        $t->same(['2x'], $link['imageSrcsetCandidates'][1]['descriptors']);
        $t->same('100vw', $link['imageSizes']);
        $t->same('high', $link['fetchpriority']);
        $t->same('meta', $charsetMeta['documentMetadata']);
        $t->same('UTF-8', $charsetMeta['charset']);
        $t->same('viewport', $viewportMeta['nameAttribute']);
        $t->same('width=device-width, initial-scale=1', $viewportMeta['content']);
        $t->same('og:title', $propertyMeta['property']);
        $t->same('Review Packet', $propertyMeta['content']);
        $t->same('refresh', $refreshMeta['httpEquivRaw']);
        $t->same('refresh', $refreshMeta['httpEquiv']);
        $t->same('5; url=https://example.test/next?stage=review', $refreshMeta['content']);
        $t->same([
            'contentRaw' => '5; url=https://example.test/next?stage=review',
            'delayRaw' => '5',
            'delay' => 5.0,
            'urlRaw' => 'https://example.test/next?stage=review',
            'url' => 'https://example.test/next?stage=review',
        ], $refreshMeta['refresh']);
        $t->same('Body', $paragraph['text']);
        $t->same('<base href="https://example.test/docs/" target="_blank"><link as="style" crossorigin="anonymous" fetchpriority="high" href="review.css" hreflang="en" imagesizes="100vw" imagesrcset="cover.avif 1x, cover@2x.avif 2x" integrity="sha384-review" media="screen and (min-width: 40em)" referrerpolicy="no-referrer" rel="preload stylesheet modulepreload" sizes="any" type="text/css"><meta charset="UTF-8"><meta content="width=device-width, initial-scale=1" name="viewport"><meta content="Review Packet" property="og:title"><meta content="5; url=https://example.test/next?stage=review" http-equiv="refresh"><p>Body</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/document-metadata-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html link resource hint and preload provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link rel="preload modulepreload dns-prefetch preload custom-rel bad&lt;tag" href="app.js" as="Script" crossorigin="anonymous" integrity="sha384-app" fetchpriority="High">'
                . '<link rel="preconnect preload" as="bogus">'
                . '<link rel="stylesheet icon canonical" href="/site.css"><p>Body</p>',
            'link resource review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-resource-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $preload = $summary[0];
        $missingHref = $summary[1];
        $stylesheet = $summary[2];

        $t->same('link', $preload['linkResourceReview']);
        $t->same(['preload', 'modulepreload', 'dns-prefetch', 'custom-rel'], $preload['linkRelTokens']);
        $t->same(['preload' => 2, 'modulepreload' => 1, 'dns-prefetch' => 1, 'custom-rel' => 1], $preload['linkRelTokenCounts']);
        $t->same(['preload'], $preload['duplicateLinkRelTokens']);
        $t->same(['bad<tag'], $preload['invalidLinkRelTokens']);
        $t->same(['custom-rel'], $preload['customLinkRelTokens']);
        $t->same(['preload', 'modulepreload', 'dns-prefetch'], $preload['linkResourceRelTokens']);
        $t->same(['preload', 'modulepreload', 'resource-hint'], $preload['linkResourceKinds']);
        $t->same('preload', $preload['linkPrimaryResourceKind']);
        $t->same(['dns-prefetch'], $preload['linkResourceHintTokens']);
        $t->same(true, $preload['linkHrefRequired']);
        $t->same(true, $preload['linkHrefPresent']);
        $t->same('Script', $preload['preloadAsRaw']);
        $t->same('script', $preload['preloadAs']);
        $t->same(true, $preload['preloadAsRequired']);
        $t->same(true, $preload['preloadAsValid']);
        $t->same([
            ['code' => 'invalid-link-rel-token', 'relToken' => 'bad<tag'],
            ['code' => 'duplicate-link-rel-token', 'relToken' => 'preload', 'count' => 2],
        ], $preload['linkIssues']);
        $t->same('anonymous', $preload['crossorigin']);
        $t->same('sha384-app', $preload['integrity']);
        $t->same('High', $preload['fetchpriority']);

        $t->same(['preconnect', 'preload'], $missingHref['linkResourceRelTokens']);
        $t->same(['resource-hint', 'preload'], $missingHref['linkResourceKinds']);
        $t->same('resource-hint', $missingHref['linkPrimaryResourceKind']);
        $t->same(true, $missingHref['linkHrefRequired']);
        $t->same(false, $missingHref['linkHrefPresent']);
        $t->same('bogus', $missingHref['preloadAs']);
        $t->same(false, $missingHref['preloadAsValid']);
        $t->same([
            ['code' => 'missing-link-href', 'relTokens' => ['preconnect', 'preload']],
            ['code' => 'invalid-preload-as', 'asRaw' => 'bogus'],
        ], $missingHref['linkIssues']);

        $t->same(['stylesheet', 'icon', 'canonical'], $stylesheet['linkResourceRelTokens']);
        $t->same(['stylesheet', 'icon', 'canonical'], $stylesheet['linkResourceKinds']);
        $t->same('stylesheet', $stylesheet['linkPrimaryResourceKind']);
        $t->same(true, $stylesheet['linkHrefRequired']);
        $t->same([], $stylesheet['linkIssues']);
        $t->same(false, $stylesheet['preloadAsRequired']);
        $t->same(true, $stylesheet['preloadAsValid']);
        $t->same('<link as="Script" crossorigin="anonymous" fetchpriority="High" href="app.js" integrity="sha384-app" rel="preload modulepreload dns-prefetch preload custom-rel bad&lt;tag"><link as="bogus" rel="preconnect preload"><link href="/site.css" rel="stylesheet icon canonical"><p>Body</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-resource-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html figure caption state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<figure id="fig-review"><img src="chart.png" alt="Quarterly chart"><figcaption>Figure <strong>one</strong>: imports</figcaption><p>Fallback note</p><figcaption>Extra caption</figcaption></figure>'
                . '<figcaption data-review="orphan">Orphan caption</figcaption>',
            'figure caption review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $figure = $summary[0];
        $image = $figure['children'][0];
        $caption = $figure['children'][1];
        $extraCaption = $figure['children'][3];
        $orphanCaption = $summary[1];

        $t->same('figure', $figure['name']);
        $t->same('figure', $figure['figurePart']);
        $t->same('Figure one: imports', $figure['captionText']);
        $t->same(2, $figure['captionCount']);
        $t->same('image', $image['embeddedResource']);
        $t->same('chart.png', $image['src']);
        $t->same('Quarterly chart', $image['alt']);
        $t->same('figcaption', $caption['name']);
        $t->same('caption', $caption['figurePart']);
        $t->same('Figure one: imports', $caption['captionText']);
        $t->same('Extra caption', $extraCaption['captionText']);
        $t->same('figcaption', $orphanCaption['name']);
        $t->same('caption', $orphanCaption['figurePart']);
        $t->same('Orphan caption', $orphanCaption['captionText']);
        $t->same(['review' => 'orphan'], $orphanCaption['dataset']);
        $t->same('<figure id="fig-review"><img alt="Quarterly chart" src="chart.png"><figcaption>Figure <strong>one</strong>: imports</figcaption><p>Fallback note</p><figcaption>Extra caption</figcaption></figure><figcaption data-review="orphan">Orphan caption</figcaption>', $html);
    },
    'summarizes html table structure spans and header references for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<table id="review"><caption>Quarterly <strong>review</strong></caption><colgroup span="2"><col span="3"><col span="0"></colgroup><thead><tr><th id="h1" scope="col" abbr="Q1">Quarter</th><th id="h2" scope="bad" colspan="2">Status</th></tr></thead><tbody><tr><th id="r1" scope="row">Batch A</th><td headers="h1 r1" rowspan="0" colspan="3">Ready</td><td colspan="2000" rowspan="-1">Overflow</td></tr></tbody></table>',
            'table structure review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $table = $summary[0];
        $caption = $table['children'][0];
        $colgroup = $table['children'][1];
        $firstColumn = $colgroup['children'][0];
        $invalidColumn = $colgroup['children'][1];
        $thead = $table['children'][2];
        $headRow = $thead['children'][0];
        $quarterHeader = $headRow['children'][0];
        $statusHeader = $headRow['children'][1];
        $tbody = $table['children'][3];
        $bodyRow = $tbody['children'][0];
        $rowHeader = $bodyRow['children'][0];
        $readyCell = $bodyRow['children'][1];
        $overflowCell = $bodyRow['children'][2];

        $t->same('table', $table['tablePart']);
        $t->same('Quarterly review', $table['captionText']);
        $t->same(1, $table['captionCount']);
        $t->same('caption', $caption['tablePart']);
        $t->same('Quarterly review', $caption['captionText']);
        $t->same('column-group', $colgroup['tablePart']);
        $t->same('2', $colgroup['spanRaw']);
        $t->same(2, $colgroup['span']);
        $t->same('column', $firstColumn['tablePart']);
        $t->same('3', $firstColumn['spanRaw']);
        $t->same(3, $firstColumn['span']);
        $t->same('0', $invalidColumn['spanRaw']);
        $t->same(1, $invalidColumn['span']);

        $t->same('header-group', $thead['tablePart']);
        $t->same('body-group', $tbody['tablePart']);
        $t->same('row', $headRow['tablePart']);
        $t->same('row', $bodyRow['tablePart']);

        $t->same('cell', $quarterHeader['tablePart']);
        $t->same('header', $quarterHeader['tableCell']);
        $t->same(1, $quarterHeader['colSpan']);
        $t->same(1, $quarterHeader['rowSpan']);
        $t->same('col', $quarterHeader['scopeRaw']);
        $t->same('col', $quarterHeader['scope']);
        $t->same('Q1', $quarterHeader['abbr']);
        $t->same([], $quarterHeader['headers']);
        $t->same('bad', $statusHeader['scopeRaw']);
        $t->same(null, $statusHeader['scope']);
        $t->same('2', $statusHeader['colSpanRaw']);
        $t->same(2, $statusHeader['colSpan']);

        $t->same('header', $rowHeader['tableCell']);
        $t->same('row', $rowHeader['scope']);
        $t->same('data', $readyCell['tableCell']);
        $t->same('h1 r1', $readyCell['headersRaw']);
        $t->same(['h1', 'r1'], $readyCell['headers']);
        $t->same('3', $readyCell['colSpanRaw']);
        $t->same(3, $readyCell['colSpan']);
        $t->same('0', $readyCell['rowSpanRaw']);
        $t->same(0, $readyCell['rowSpan']);
        $t->same('2000', $overflowCell['colSpanRaw']);
        $t->same(1000, $overflowCell['colSpan']);
        $t->same('-1', $overflowCell['rowSpanRaw']);
        $t->same(1, $overflowCell['rowSpan']);
        $t->same('<table id="review"><caption>Quarterly <strong>review</strong></caption><colgroup span="2"><col span="3"><col span="0"></colgroup><thead><tr><th abbr="Q1" id="h1" scope="col">Quarter</th><th colspan="2" id="h2" scope="bad">Status</th></tr></thead><tbody><tr><th id="r1" scope="row">Batch A</th><td colspan="3" headers="h1 r1" rowspan="0">Ready</td><td colspan="2000" rowspan="-1">Overflow</td></tr></tbody></table>', $html);
    },
    'serializes detached dom nodes and children for reader handoff' => static function (TestRunner $t): void {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $fragment = $dom->createDocumentFragment();
        $section = $dom->createElement('section');
        $section->setAttribute('hidden', 'hidden');
        $paragraph = $dom->createElement('p');
        $paragraph->appendChild($dom->createTextNode('Detached <text> & notes'));
        $section->appendChild($paragraph);
        $section->appendChild($dom->createElement('br'));
        $section->appendChild($dom->createComment('review -- source'));
        $fragment->appendChild($section);

        $t->same('<section hidden><p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source--></section>', XmlHtmlDom::serializeHtmlNode($fragment));
        $t->same('<p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source-->', XmlHtmlDom::serializeHtmlChildren($section));
        $t->same('<section hidden><p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source--></section>', XmlHtmlDom::serializeHtmlNode($section));
        $t->same('<!--detached- -->', XmlHtmlDom::serializeHtmlNode($dom->createComment('detached-')));
        $t->same('<!--detached- - -tail- -->', XmlHtmlDom::serializeHtmlNode($dom->createComment('detached---tail-')));
    },
    'preserves svg and mathml foreign content names in deterministic html serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>'
                . '<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>',
            'foreign content HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('svg', $summary[0]['name']);
        $t->same([
            'preserveAspectRatio' => 'xMidYMid meet',
            'viewBox' => '0 0 10 10',
        ], $summary[0]['attributes']);
        $t->same('linearGradient', $summary[0]['children'][0]['name']);
        $t->same('textPath', $summary[0]['children'][1]['name']);
        $t->same('math', $summary[1]['name']);
        $t->same('definitionURL', array_key_first($summary[1]['children'][0]['attributes']));
        $t->same('<svg preserveAspectRatio="xMidYMid meet" viewBox="0 0 10 10"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg><math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>', $html);
    },
    'keeps svg element-name casing scoped to svg foreign content' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<math><lineargradient data-review="math">m</lineargradient><mtext><linearGradient viewBox="html">html</linearGradient></mtext><svg><linearGradient id="g"></linearGradient></svg></math>',
            'mixed MathML and SVG foreign content fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $mathUnknown = $summary[0]['children'][0];
        $mathHtmlText = $summary[0]['children'][1]['children'][0];
        $nestedSvg = $summary[0]['children'][2];

        $t->same('math', $summary[0]['name']);
        $t->same('lineargradient', $mathUnknown['name']);
        $t->same(['data-review' => 'math'], $mathUnknown['attributes']);
        $t->same('lineargradient', $mathHtmlText['name']);
        $t->same(['viewbox' => 'html'], $mathHtmlText['attributes']);
        $t->same('svg', $nestedSvg['name']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('<math><lineargradient data-review="math">m</lineargradient><mtext><lineargradient viewbox="html">html</lineargradient></mtext><svg><linearGradient id="g"></linearGradient></svg></math>', $html);
        $t->true(!str_contains($html, '<math><linearGradient'), 'Expected MathML non-SVG descendants to keep their parsed names');
    },
    'keeps html integration point descendants out of foreign-content casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><foreignObject><div viewBox="html attr"><linearGradient data-review="html child">HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg>'
                . '<math><annotation-xml encoding="text/html"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>',
            'foreign content integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $foreignObject = $summary[0]['children'][0];
        $foreignDiv = $foreignObject['children'][0];
        $nestedSvg = $foreignDiv['children'][1];
        $mathHtmlAnnotation = $summary[1]['children'][0];
        $mathHtmlDiv = $mathHtmlAnnotation['children'][0];
        $mathContentAnnotation = $summary[1]['children'][1];

        $t->same('foreignObject', $foreignObject['name']);
        $t->same('div', $foreignDiv['name']);
        $t->same(['viewbox' => 'html attr'], $foreignDiv['attributes']);
        $t->same('lineargradient', $foreignDiv['children'][0]['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('annotation-xml', $mathHtmlAnnotation['name']);
        $t->same(['encoding' => 'text/html'], $mathHtmlAnnotation['attributes']);
        $t->same('div', $mathHtmlDiv['name']);
        $t->same(['viewbox' => 'math html'], $mathHtmlDiv['attributes']);
        $t->same('textpath', $mathHtmlDiv['children'][0]['name']);
        $t->same(['definitionURL' => '#x'], $mathContentAnnotation['children'][0]['attributes']);
        $t->same('<svg><foreignObject><div viewbox="html attr"><lineargradient data-review="html child">HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg><math><annotation-xml encoding="text/html"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>', $html);
    },
    'treats svg desc descendants as html integration point content' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><desc><p viewBox="html attr"><textPath>HTML fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>',
            'svg desc integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $svg = $summary[0];
        $desc = $svg['children'][0];
        $paragraph = $desc['children'][0];
        $textPath = $paragraph['children'][0];
        $nestedSvg = $paragraph['children'][1];

        $t->same('svg', $svg['name']);
        $t->same('desc', $desc['name']);
        $t->same('p', $paragraph['name']);
        $t->same(['viewbox' => 'html attr'], $paragraph['attributes']);
        $t->same('textpath', $textPath['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attributes']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('<svg><desc><p viewbox="html attr"><textpath>HTML fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>', $html);
    },
    'treats svg title descendants as html integration point content' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><title><p viewBox="html attr"><textPath>Title fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>',
            'svg title integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $svg = $summary[0];
        $title = $svg['children'][0];
        $paragraph = $title['children'][0];
        $textPath = $paragraph['children'][0];
        $nestedSvg = $paragraph['children'][1];

        $t->same('svg', $svg['name']);
        $t->same('title', $title['name']);
        $t->same('p', $paragraph['name']);
        $t->same(['viewbox' => 'html attr'], $paragraph['attributes']);
        $t->same('textpath', $textPath['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attributes']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('<svg><title><p viewbox="html attr"><textpath>Title fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>', $html);
        $t->true(!str_contains($html, '&lt;p viewBox'), 'Expected SVG title fallback markup to stay parsed instead of escaped as RCDATA');
    },
    'keeps mathml token text integration descendants in html casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<math><mtext><span viewBox="html attr"><textPath>HTML text</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext>'
                . '<mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math>',
            'mathml text integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $math = $summary[0];
        $mtext = $math['children'][0];
        $span = $mtext['children'][0];
        $nestedSvg = $span['children'][1];
        $mi = $math['children'][1];
        $mo = $math['children'][2];

        $t->same('math', $math['name']);
        $t->same('mtext', $mtext['name']);
        $t->same(['viewbox' => 'html attr'], $span['attributes']);
        $t->same('textpath', $span['children'][0]['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attributes']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('a', $mi['children'][0]['name']);
        $t->same(['href' => '/review'], $mi['children'][0]['attributes']);
        $t->same('mglyph', $mo['children'][0]['name']);
        $t->same('<math><mtext><span viewbox="html attr"><textpath>HTML text</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext><mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math>', $html);
    },
    'keeps mathml mglyph and malignmark exceptions in foreign casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionURL="#html">HTML</span></mi></math>',
            'mathml text integration-point exception fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $mi = $summary[0]['children'][0];
        $malignmark = $mi['children'][0];
        $mglyph = $mi['children'][1];
        $span = $mi['children'][2];

        $t->same(['definitionURL' => '#mark'], $malignmark['attributes']);
        $t->same('svg', $malignmark['children'][0]['name']);
        $t->same('linearGradient', $malignmark['children'][0]['children'][0]['name']);
        $t->same(['definitionURL' => '#glyph'], $mglyph['attributes']);
        $t->same(['definitionurl' => '#html'], $span['attributes']);
        $t->same('<math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionurl="#html">HTML</span></mi></math>', $html);
    },
    'preserves html foreign-content cdata sections as escaped text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><desc><![CDATA[Reviewer <source> & notes]]></desc><text><![CDATA[A < B & C]]></text></svg>'
                . '<math><annotation encoding="application/x-tex"><![CDATA[x < y & z]]></annotation></math>',
            'foreign content CDATA fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('svg', $summary[0]['name']);
        $t->same('desc', $summary[0]['children'][0]['name']);
        $t->same('Reviewer <source> & notes', $summary[0]['children'][0]['text']);
        $t->same('text', $summary[0]['children'][1]['name']);
        $t->same('A < B & C', $summary[0]['children'][1]['text']);
        $t->same('math', $summary[1]['name']);
        $t->same('annotation', $summary[1]['children'][0]['name']);
        $t->same(['encoding' => 'application/x-tex'], $summary[1]['children'][0]['attributes']);
        $t->same('x < y & z', $summary[1]['children'][0]['text']);
        $t->same('<svg><desc>Reviewer &lt;source&gt; &amp; notes</desc><text>A &lt; B &amp; C</text></svg><math><annotation encoding="application/x-tex">x &lt; y &amp; z</annotation></math>', $html);
        $t->true(!str_contains($html, '<![CDATA['), 'Expected CDATA delimiters to be normalized away before HTML handoff');
        $t->true(!str_contains($html, '<source>'), 'Expected CDATA tag-looking text to stay escaped');
    },
    'serializes html rcdata elements as escaped text not parsed child markup' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea>'
                . '<title>Packet <em>literal</em> &amp; title</title>',
            'rcdata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('textarea', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $summary[0]['children'][0]['text']);
        $t->same('title', $summary[1]['name']);
        $t->same('Packet <em>literal</em> & title', $summary[1]['text']);
        $t->same('text', $summary[1]['children'][0]['type']);
        $t->same(
            '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;</textarea><title>Packet &lt;em&gt;literal&lt;/em&gt; &amp; title</title>',
            $html
        );
    },
    'keeps unterminated html rcdata source as escaped text through fragment end' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b><p>after</p>',
            'unterminated rcdata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $expectedText = 'Reviewer <script>alert(1)</script> & <b>note</b><p>after</p>';
        $expectedHtml = '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;&lt;p&gt;after&lt;/p&gt;</textarea>';

        $t->same(1, count($summary));
        $t->same('textarea', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedText, $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedText, $summary[0]['children'][0]['text']);
        $t->same($expectedHtml, $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected unterminated textarea script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>after</p>'), 'Expected unterminated textarea following source to stay escaped');
    },
    'serializes obsolete html raw text fallback elements as escaped source text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<xmp data-source="legacy">Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea></xmp>'
                . '<noembed>Fallback <img src=x> & source</noembed>'
                . '<noframes>Frame fallback <a href="/edit">edit</a></noframes><p>after</p>',
            'obsolete raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('xmp', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $summary[0]['children'][0]['text']);
        $t->same('noembed', $summary[1]['name']);
        $t->same('Fallback <img src=x> & source', $summary[1]['text']);
        $t->same('noframes', $summary[2]['name']);
        $t->same('Frame fallback <a href="/edit">edit</a>', $summary[2]['text']);
        $t->same('<xmp data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;</xmp><noembed>Fallback &lt;img src=x&gt; &amp; source</noembed><noframes>Frame fallback &lt;a href="/edit"&gt;edit&lt;/a&gt;</noframes><p>after</p>', $html);
        $t->true(!str_contains($html, '<textarea>'), 'Expected raw text textarea-looking source to stay escaped');
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected raw text script-looking source to stay escaped');
    },
    'treats html noscript fallback as escaped raw text for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<noscript data-source="legacy">Fallback <script>alert(1)</script> & source <img src=x></noscript><p>after</p>',
            'noscript raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('noscript', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Fallback <script>alert(1)</script> & source <img src=x>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Fallback <script>alert(1)</script> & source <img src=x>', $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('after', $summary[1]['text']);
        $t->same('<noscript data-source="legacy">Fallback &lt;script&gt;alert(1)&lt;/script&gt; &amp; source &lt;img src=x&gt;</noscript><p>after</p>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected noscript script-looking source to stay escaped');
        $t->true(!str_contains($html, '<img src=x>'), 'Expected noscript image-looking source to stay escaped');
    },
    'treats html iframe fallback as escaped raw text for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe data-source="legacy"><p>Fallback <script>alert(1)</script> &amp; note</p></iframe><p>after</p>',
            'iframe raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('iframe', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('<p>Fallback <script>alert(1)</script> &amp; note</p>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('<p>Fallback <script>alert(1)</script> &amp; note</p>', $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('<iframe data-source="legacy">&lt;p&gt;Fallback &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; note&lt;/p&gt;</iframe><p>after</p>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected iframe fallback script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>Fallback'), 'Expected iframe fallback paragraph markup to stay escaped');
    },
    'treats html plaintext as escaped source text through end of fragment' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<plaintext data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>',
            'plaintext review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $expectedText = 'Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>';

        $t->same(1, count($summary));
        $t->same('plaintext', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedText, $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedText, $summary[0]['children'][0]['text']);
        $t->same('<plaintext data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;after&lt;/p&gt;</plaintext>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected plaintext script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>after</p>'), 'Expected following paragraph source to stay plaintext text');
    },
    'treats html template contents as inert escaped source text for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<template data-source="legacy"><p>Template <script>drop()</script> &amp; <b>note</b></p></template><p>after</p>',
            'template review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', ['source' => 'xml-html5-dom'], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/template-source-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expectedTemplateText = '<p>Template <script>drop()</script> &amp; <b>note</b></p>';
        $expectedHtml = '<template data-source="legacy">&lt;p&gt;Template &lt;script&gt;drop()&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/p&gt;</template><p>after</p>';

        $t->same(2, count($summary));
        $t->same('template', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedTemplateText, $summary[0]['text']);
        $t->same('inert-source', $summary[0]['template']);
        $t->same($expectedTemplateText, $summary[0]['templateText']);
        $t->same(strlen($expectedTemplateText), $summary[0]['templateTextLength']);
        $t->same(hash('sha256', $expectedTemplateText), $summary[0]['templateTextSha256']);
        $t->same(true, $summary[0]['templateContainsMarkupLikeText']);
        $t->same(true, $summary[0]['templateContainsActiveLikeText']);
        $t->same('template-inert-escaped-source', $summary[0]['templateReviewPolicy']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedTemplateText, $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('after', $summary[1]['text']);
        $t->same($expectedHtml, $html);
        $t->contains($expectedHtml, $blocks);
        $t->same('/migration/template-source-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<script>drop()</script>'), 'Expected script-looking template source to stay escaped');
        $t->true(!str_contains($html, '<b>note</b>'), 'Expected inline tag-looking template source to stay escaped');
    },
    'summarizes html template content review provenance for reviewer handoff' => static function (TestRunner $t): void {
        $templateSource = '<article id="card"><h2>Title</h2><a href="/more">More</a><img src="cover.png" alt="Cover"><form action="/submit"><input name="q" value="search"></form><script>ignored()</script><iframe src="frame.html"></iframe></article>';
        $unsafeSource = '<!doctype html><p>Blocked</p>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<template id="card-template">' . $templateSource . '</template>'
                . '<template id="unsafe-template">' . $unsafeSource . '</template>',
            'template content review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $card = $summary[0];
        $unsafe = $summary[1];

        $t->same(2, count($summary));
        $t->same('template', $card['name']);
        $t->same('template-content-inert-fragment-review', $card['templateContentReviewPolicy']);
        $t->same(strlen($templateSource), $card['templateContentByteLength']);
        $t->same(hash('sha256', $templateSource), $card['templateContentSha256']);
        $t->same(true, $card['templateContentParsed']);
        $t->same([], $card['templateContentDiagnostics']);
        $t->same(['article'], $card['templateContentTopLevelElementNames']);
        $t->same(1, $card['templateContentTopLevelElementCount']);
        $t->same('TitleMoreignored()', $card['templateContentText']);
        $t->same(strlen('TitleMoreignored()'), $card['templateContentTextLength']);
        $t->same(hash('sha256', 'TitleMoreignored()'), $card['templateContentTextSha256']);
        $t->same(['/more'], $card['templateContentLinkHrefs']);
        $t->same(['cover.png'], $card['templateContentImageSources']);
        $t->same(1, $card['templateContentFormCount']);
        $t->same(['/submit'], $card['templateContentFormActions']);
        $t->same(['script'], $card['templateContentActiveElementNames']);
        $t->same(['iframe'], $card['templateContentEmbeddedElementNames']);
        $t->true(!str_contains($html, '<script>ignored()</script>'), 'Expected template script source to stay escaped in raw handoff');

        $t->same('template', $unsafe['name']);
        $t->same($unsafeSource, $unsafe['templateText']);
        $t->same('template-content-inert-fragment-review', $unsafe['templateContentReviewPolicy']);
        $t->same(false, $unsafe['templateContentParsed']);
        $t->same(['template-content-unsafe-or-unparseable'], $unsafe['templateContentDiagnostics']);
        $t->contains('document type', $unsafe['templateContentError']);
    },
    'summarizes html noscript fallback review provenance for reviewer handoff' => static function (TestRunner $t): void {
        $noscriptSource = '<article id="fallback"><h2>Fallback</h2><a href="/static">Static</a><img src="fallback.png" alt="Fallback"><form action="/offline"><input name="q" value="term"></form><script>blocked()</script><iframe src="fallback-frame.html"></iframe></article>';
        $unsafeSource = '<!doctype html><p>Blocked</p>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<noscript id="fallback-source">' . $noscriptSource . '</noscript>'
                . '<noscript id="unsafe-source">' . $unsafeSource . '</noscript>',
            'noscript fallback review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', ['source' => 'xml-html5-dom'], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/noscript-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $fallback = $summary[0];
        $unsafe = $summary[1];

        $t->same(2, count($summary));
        $t->same('noscript', $fallback['name']);
        $t->same('fallback-source', $fallback['noscript']);
        $t->same($noscriptSource, $fallback['noscriptText']);
        $t->same(strlen($noscriptSource), $fallback['noscriptTextLength']);
        $t->same(hash('sha256', $noscriptSource), $fallback['noscriptTextSha256']);
        $t->same(true, $fallback['noscriptContainsMarkupLikeText']);
        $t->same(true, $fallback['noscriptContainsActiveLikeText']);
        $t->same('noscript-inert-escaped-source', $fallback['noscriptReviewPolicy']);
        $t->same('noscript-content-inert-fragment-review', $fallback['noscriptContentReviewPolicy']);
        $t->same(strlen($noscriptSource), $fallback['noscriptContentByteLength']);
        $t->same(hash('sha256', $noscriptSource), $fallback['noscriptContentSha256']);
        $t->same(true, $fallback['noscriptContentParsed']);
        $t->same([], $fallback['noscriptContentDiagnostics']);
        $t->same(['article'], $fallback['noscriptContentTopLevelElementNames']);
        $t->same(1, $fallback['noscriptContentTopLevelElementCount']);
        $t->same('FallbackStaticblocked()', $fallback['noscriptContentText']);
        $t->same(strlen('FallbackStaticblocked()'), $fallback['noscriptContentTextLength']);
        $t->same(hash('sha256', 'FallbackStaticblocked()'), $fallback['noscriptContentTextSha256']);
        $t->same(['/static'], $fallback['noscriptContentLinkHrefs']);
        $t->same(['fallback.png'], $fallback['noscriptContentImageSources']);
        $t->same(1, $fallback['noscriptContentFormCount']);
        $t->same(['/offline'], $fallback['noscriptContentFormActions']);
        $t->same(['script'], $fallback['noscriptContentActiveElementNames']);
        $t->same(['iframe'], $fallback['noscriptContentEmbeddedElementNames']);

        $t->same('noscript', $unsafe['name']);
        $t->same($unsafeSource, $unsafe['noscriptText']);
        $t->same('noscript-content-inert-fragment-review', $unsafe['noscriptContentReviewPolicy']);
        $t->same(false, $unsafe['noscriptContentParsed']);
        $t->same(['noscript-content-unsafe-or-unparseable'], $unsafe['noscriptContentDiagnostics']);
        $t->contains('document type', $unsafe['noscriptContentError']);

        $t->contains('&lt;article id="fallback"&gt;', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/noscript-fallback-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<script>blocked()</script>'), 'Expected noscript script source to stay escaped in raw handoff');
        $t->true(!str_contains($html, '<iframe src="fallback-frame.html">'), 'Expected noscript iframe source to stay escaped in raw handoff');
    },
    'foster-parents invalid table children before deterministic html serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<table class="legacy"><caption>Review rows</caption><p>Loose note</p><tr><td>A</td></tr>orphan text<tr><td>B</td></tr></table><p>after</p>',
            'table foster-parenting review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('p', $summary[0]['name']);
        $t->same('Loose note', $summary[0]['text']);
        $t->same('text', $summary[1]['type']);
        $t->same('orphan text', $summary[1]['text']);
        $t->same('table', $summary[2]['name']);
        $t->same(['class' => 'legacy'], $summary[2]['attributes']);
        $t->same('caption', $summary[2]['children'][0]['name']);
        $t->same('tr', $summary[2]['children'][1]['name']);
        $t->same('tr', $summary[2]['children'][2]['name']);
        $t->same('<p>Loose note</p>orphan text<table class="legacy"><caption>Review rows</caption><tr><td>A</td></tr><tr><td>B</td></tr></table><p>after</p>', $html);
    },
    'hands serialized HTML fragments to WordPress raw HTML blocks' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<aside data-review="source"><p>Imported<br>line &amp; reviewer notes</p></aside>',
            'WordPress review fragment'
        );
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html]),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('<aside data-review="source"><p>Imported<br>line &amp; reviewer notes</p></aside>', $html);
        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<aside data-review="source">', $blocks);
        $t->contains('Imported<br>line &amp; reviewer notes', $blocks);
        $t->contains('<!-- /wp:html -->', $blocks);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment("<p>bad\0html</p>", 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<!DOCTYPE html><p>bad</p>', 'unsafe HTML fragment'));
    },
    'rejects unsafe HTML fragment declarations before serialization handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment('<p data-review="ok">Safe</p>', 'safe HTML fragment');

        $t->same('<p data-review="ok">Safe</p>', XmlHtmlDom::serializeHtmlFragment($dom));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>', 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>', 'unsafe HTML fragment'));
    },
];
