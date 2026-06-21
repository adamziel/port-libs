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
        $t->contains('<h1>JATS Reader Demo</h1>', $blocks);
        $t->contains('<p>Structured XML to AST</p>', $blocks);
        $t->contains('<h2>Abstract</h2>', $blocks);
        $t->contains('<p>This abstract becomes body content.</p>', $blocks);
        $t->contains('<h2>Introduction</h2>', $blocks);
        $t->contains('<a href="https://example.test">external link</a>', $blocks);
        $t->contains('<th>Scope</th><th>Status</th>', $blocks);
        $t->contains('<td>Parser</td><td>Ready</td>', $blocks);
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
      <body><p>Book part body.</p></body>
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
        $t->contains('<h1>BITS Book Demo</h1>', $blocks);
        $t->contains('<p>Book abstract.</p>', $blocks);
        $t->contains('<p>Book part body.</p>', $blocks);
    },
    'rejects malformed xml instead of falling back to text extraction' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('<doc><p>Unclosed', 'xml');
        });
    },
];
