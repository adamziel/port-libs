<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\XmlReader;

return [
    'maps generic xml titles paragraphs links lists and tables into shared ast blocks' => static function (TestRunner $t): void {
        $xml = <<<'XML'
<doc xmlns:xlink="http://www.w3.org/1999/xlink" id="fixture">
  <title>XML Intake</title>
  <p>First <bold>bold</bold> paragraph with <xref rid="target-1">a reference</xref>.</p>
  <section id="methods">
    <title>Methods</title>
    <p>Second paragraph.</p>
  </section>
  <list>
    <item>One</item>
    <item>Two</item>
  </list>
  <table>
    <thead>
      <tr><th>Name</th><th>Value</th></tr>
    </thead>
    <tbody>
      <tr><td>Alpha</td><td>10</td></tr>
    </tbody>
  </table>
</doc>
XML;

        $document = (new XmlReader('xml'))->read($xml);
        $blocks = PandocConverter::write($document, 'blocks');
        $markdown = PandocConverter::write($document, 'markdown');
        $meta = $document->attr('meta');

        $t->same('xml', $document->attr('sourceFormat'));
        $t->same('doc', $meta['rootName']);
        $t->same(1, $meta['xmlDetectedTables']);
        $t->same(2, $meta['xmlDetectedHeadings']);
        $t->same('heading', $document->children[0]->type);
        $t->same('XML Intake', $document->children[0]->attr('text'));
        $t->contains('<strong>bold</strong>', $blocks);
        $t->contains('<a href="#target-1">a reference</a>', $blocks);
        $t->contains('<!-- wp:list -->', $blocks);
        $t->contains('<th>Name</th><th>Value</th>', $blocks);
        $t->contains('<td>Alpha</td><td>10</td>', $blocks);
        $t->contains('# XML Intake', $markdown);
        $t->contains('| Name  | Value |', $markdown);
    },
    'retains non-media graphic elements in generic XML text flow' => static function (TestRunner $t): void {
        $document = PandocConverter::read('<doc><p>Before <graphic>fallback graphic text</graphic> after.</p></doc>', 'xml');
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('<p>Before fallback graphic text after.</p>', $blocks);
    },
    'maps common DocBook-style heading and list aliases through generic XML' => static function (TestRunner $t): void {
        $xml = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook">
  <title>Guide</title>
  <section><heading>Checklist</heading><itemizedlist><listitem><para>First item</para></listitem><listitem><para>Second item</para></listitem></itemizedlist></section>
  <section><orderedlist><listitem><para>Step one</para></listitem><listitem><para>Step two</para></listitem></orderedlist></section>
</article>
XML;

        $blocks = PandocConverter::write(PandocConverter::read($xml, 'xml'), 'blocks');

        $t->contains('<h1>Guide</h1>', $blocks);
        $t->contains('<h1>Checklist</h1>', $blocks);
        $t->contains('<!-- wp:list -->', $blocks);
        $t->contains('<!-- wp:list {"ordered":true} -->', $blocks);
        $t->contains('<li>First item</li>', $blocks);
        $t->contains('<li>Step two</li>', $blocks);
    },
    'maps jats article front matter body sections and table wraps into ast blocks' => static function (TestRunner $t): void {
        $jats = <<<'XML'
<article xmlns:xlink="http://www.w3.org/1999/xlink" article-type="research-article" dtd-version="1.3">
  <front>
    <journal-meta><journal-title-group><journal-title>Journal of Fixtures</journal-title></journal-title-group></journal-meta>
    <article-meta>
      <article-id pub-id-type="doi">10.1000/xml-reader</article-id>
      <title-group>
        <article-title>JATS Reader Demo</article-title>
        <subtitle>Structured XML to AST</subtitle>
      </title-group>
      <contrib-group>
        <contrib contrib-type="author"><name><surname>Doe</surname><given-names>Jane</given-names></name></contrib>
      </contrib-group>
      <abstract><p>This abstract becomes body content.</p></abstract>
    </article-meta>
  </front>
  <body>
    <sec id="intro">
      <title>Introduction</title>
      <p>Paragraph with <ext-link xlink:href="https://example.test">external link</ext-link>.</p>
      <table-wrap id="t1">
        <label>Table 1</label>
        <caption><title>Quarterly review</title><p>Table caption details.</p></caption>
        <table>
          <thead><tr><th>Scope</th><th>Status</th></tr></thead>
          <tbody><tr><td>Parser</td><td>Ready</td></tr></tbody>
        </table>
      </table-wrap>
    </sec>
  </body>
</article>
XML;

        $document = PandocConverter::read($jats, 'jats');
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('jats', $document->attr('sourceFormat'));
        $t->same('JATS Reader Demo', $meta['title']);
        $t->same(['Jane Doe'], $meta['authors']);
        $t->same(1, $meta['jatsSectionCount']);
        $t->same(1, $meta['jatsTableWrapCount']);
        $t->same(1, $meta['xmlDetectedTables']);
        $t->true(!str_contains($blocks, 'JATS Reader Demo'));
        $t->true(!str_contains($blocks, 'Structured XML to AST'));
        $t->true(!str_contains($blocks, 'This abstract becomes body content.'));
        $t->contains('<h1 id="intro">Introduction</h1>', $blocks);
        $t->contains('<a href="https://example.test">external link</a>', $blocks);
        $t->contains('<th>Scope</th><th>Status</th>', $blocks);
        $t->contains('<td>Parser</td><td>Ready</td>', $blocks);
    },
    'keeps JATS figures MathML and back-matter xref targets as structured content' => static function (TestRunner $t): void {
        $jats = <<<'XML'
<article xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:mml="http://www.w3.org/1998/Math/MathML">
  <body>
    <sec id="material">
      <title>Material</title>
      <p>See <xref rid="note-1">the note</xref> and <inline-formula><alternatives><tex-math>e=mc^2</tex-math><mml:math display="inline"><mml:mrow><mml:mi>e</mml:mi><mml:mo>=</mml:mo><mml:mi>m</mml:mi><mml:msup><mml:mi>c</mml:mi><mml:mn>2</mml:mn></mml:msup></mml:mrow></mml:math></alternatives></inline-formula>.</p>
      <fig id="figure-1"><caption><p>Diagram caption</p></caption><alt-text>Diagram description</alt-text><graphic id="graphic-1" xlink:href="diagram.png" xlink:title="Diagram title"/></fig>
    </sec>
  </body>
  <back><ref-list><title>Notes</title><ref id="note-1"><note><p>Referenced note.</p></note></ref></ref-list></back>
</article>
XML;

        $document = PandocConverter::read($jats, 'jats');
        $blocks = PandocConverter::write($document, 'blocks', ['writerHTMLMathMethod' => 'mathml']);

        $t->contains('<h1 id="material">Material</h1>', $blocks);
        $t->contains('<a href="#note-1">the note</a>', $blocks);
        $t->contains('<p id="note-1">Referenced note.</p>', $blocks);
        $t->contains('src="diagram.png" alt="Diagram description" title="Diagram title" id="graphic-1"', $blocks);
        $t->contains('<figcaption>Diagram caption</figcaption>', $blocks);
        $t->contains('<math display="inline" xmlns="http://www.w3.org/1998/Math/MathML">', $blocks);
        $t->contains('<msup><mi>c</mi><mn>2</mn></msup>', $blocks);
        $t->true(!str_contains($blocks, 'e=mc^2e=mc2'), 'JATS alternatives must select one math representation');
    },
    'maps JATS definition lists and nested ordered lists to shared list structures' => static function (TestRunner $t): void {
        $jats = <<<'XML'
<article>
  <body>
    <def-list id="terms">
      <def-item id="term-1">
        <term>Violin <bold>family</bold></term>
        <def>
          <p>Stringed musical instrument.</p>
          <p>Used in orchestras.</p>
          <list list-type="order"><list-item><p>Nested detail.</p></list-item></list>
        </def>
      </def-item>
    </def-list>
  </body>
</article>
XML;

        $document = PandocConverter::read($jats, 'jats');
        $html = PandocConverter::write($document, 'html');
        $blocks = PandocConverter::write($document, 'blocks');
        $definitionList = $document->children[0] ?? null;

        $t->same('definition_list', $definitionList?->type);
        $t->contains('<dl>', $html);
        $t->contains('<dt>Violin <strong>family</strong></dt>', $html);
        $t->contains('<p>Stringed musical instrument.</p>', $html);
        $t->contains('<ol>', $html);
        $t->contains('pandoc-definition-list', $blocks);
        $t->contains('<ol><li>Nested detail.</li></ol>', $blocks);
    },
    'accepts bits roots through the xml family reader' => static function (TestRunner $t): void {
        $bits = <<<'XML'
<book>
  <book-meta>
    <book-title-group><book-title>BITS Book Demo</book-title></book-title-group>
    <abstract><p>Book abstract.</p></abstract>
  </book-meta>
  <book-body>
    <book-part>
      <book-part-meta><title-group><title>Part One</title></title-group></book-part-meta>
      <body><sec><title>Book chapter</title><p>Book part body.</p></sec></body>
    </book-part>
  </book-body>
</book>
XML;

        $document = (new XmlReader('bits'))->read($bits);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->true(PandocConverter::canRead('bits'));
        $t->same('bits', $document->attr('sourceFormat'));
        $t->same('BITS Book Demo', $meta['title']);
        $t->same('book', $meta['rootName']);
        $t->true(!str_contains($blocks, 'BITS Book Demo'));
        $t->true(!str_contains($blocks, 'Book abstract.'));
        $t->contains('<h2>Book chapter</h2>', $blocks);
        $t->contains('<p>Book part body.</p>', $blocks);
    },
    'uses level one sections for BITS book-part roots while retaining front matter as metadata' => static function (TestRunner $t): void {
        $bits = <<<'XML'
<book-part>
  <book-part-meta><title-group><title>Standalone part</title></title-group></book-part-meta>
  <body><sec><title>Part section</title><p>Body text.</p></sec></body>
</book-part>
XML;

        $document = PandocConverter::read($bits, 'bits');
        $blocks = PandocConverter::write($document, 'blocks');

        $t->same('Standalone part', $document->attr('meta')['title']);
        $t->contains('<h1>Part section</h1>', $blocks);
        $t->true(!str_contains($blocks, 'Standalone part'));
    },
    'recovers JATS documents with duplicate namespaced metadata attributes' => static function (TestRunner $t): void {
        $jats = <<<'XML'
<article xmlns:xlink="http://www.w3.org/1999/xlink">
  <front><article-meta><title-group><article-title>Recovered JATS</article-title></title-group></article-meta></front>
  <body>
    <p>Readable body text survives the malformed graphic metadata.</p>
    <fig><graphic xlink:href="https://example.test/diagram.png" xlink:href="Diagram title"/></fig>
  </body>
</article>
XML;

        $document = PandocConverter::read($jats, 'jats');
        $blocks = PandocConverter::write($document, 'blocks');

        $t->true(!str_contains($blocks, 'Recovered JATS'));
        $t->contains('<p>Readable body text survives the malformed graphic metadata.</p>', $blocks);
    },
    'rejects malformed xml instead of falling back to text extraction' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('<doc><p>Unclosed', 'xml');
        });
    },
];
