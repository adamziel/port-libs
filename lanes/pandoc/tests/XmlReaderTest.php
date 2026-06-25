<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlReader;

return [
    'reads generic xml article sections lists links and code into wordpress blocks' => static function (TestRunner $t): void {
        $source = <<<'XML'
<article xmlns="urn:article" xml:lang="en">
  <title>XML <em>Import</em></title>
  <section id="intro">
    <title>Intro</title>
    <p>Lead <strong>bold</strong>, <code>literal</code>, and <a href="https://example.test">a link</a>.</p>
    <list type="ordered" start="3">
      <item>First item</item>
      <item><p>Second item</p></item>
    </list>
    <programlisting language="php">&lt;?php echo "ok";</programlisting>
  </section>
</article>
XML;

        $document = (new XmlReader())->read($source);
        $blocks = (new WordPressBlockWriter())->write($document);
        $meta = $document->attr('meta');

        $t->same('partial', $meta['xmlReaderStatus']);
        $t->same('article', $meta['xmlRootName']);
        $t->same('urn:article', $meta['xmlRootNamespaceUri']);
        $t->same(14, $meta['xmlElementCount']);
        $t->same(2, $meta['xmlHeadingCount']);
        $t->same(3, $meta['xmlParagraphCount']);
        $t->same(1, $meta['xmlListCount']);
        $t->same(1, $meta['xmlCodeBlockCount']);
        $t->same('heading', $document->children[0]->type);
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('xml-import', $document->children[0]->attr('id'));
        $t->same('heading', $document->children[1]->type);
        $t->same(2, $document->children[1]->attr('level'));
        $t->same('paragraph', $document->children[2]->type);
        $t->same('ordered_list', $document->children[3]->type);
        $t->same(3, $document->children[3]->attr('start'));
        $t->same('code_block', $document->children[4]->type);
        $t->contains('<h1 id="xml-import" data-xml-element="title" data-xml-namespace="urn:article">XML <em>Import</em></h1>', $blocks);
        $t->contains('<h2 id="intro" data-xml-element="title" data-xml-namespace="urn:article">Intro</h2>', $blocks);
        $t->contains('Lead <strong>bold</strong>, <code data-xml-element="code" data-xml-namespace="urn:article">literal</code>, and <a href="https://example.test"', $blocks);
        $t->contains('<ol start="3"><li data-xml-element="item" data-xml-namespace="urn:article">First item</li><li data-xml-element="item" data-xml-namespace="urn:article">Second item</li></ol>', $blocks);
        $t->contains('<pre class="wp-block-code" data-xml-element="programlisting" data-xml-namespace="urn:article"><code class="language-php">&lt;?php echo &quot;ok&quot;;</code></pre>', $blocks);
    },
    'reads xml figures images and simple tables through the converter' => static function (TestRunner $t): void {
        $source = <<<'XML'
<doc xmlns:xlink="http://www.w3.org/1999/xlink">
  <figure id="fig-chart">
    <graphic xlink:href="images/chart.png" alt="Chart alt" width="640" height="480"/>
    <caption>Chart <strong>caption</strong>.</caption>
  </figure>
  <table id="inventory">
    <caption>Inventory</caption>
    <thead>
      <tr><th>Name</th><th>Qty</th></tr>
    </thead>
    <tbody>
      <tr><td>Widget</td><td>7</td></tr>
    </tbody>
  </table>
</doc>
XML;

        $document = PandocConverter::read($source, 'xml');
        $blocks = PandocConverter::convert($source, 'xml', 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['xmlFigureCount']);
        $t->same(1, $meta['xmlTableCount']);
        $t->same('figure', $document->children[0]->type);
        $t->same('Chart caption.', $document->children[0]->attr('caption'));
        $t->same('image', $document->children[0]->children[0]->type);
        $t->same('images/chart.png', $document->children[0]->children[0]->attr('url'));
        $t->same('Chart alt', $document->children[0]->children[0]->attr('alt'));
        $t->same('table', $document->children[1]->type);
        $t->same('Inventory', $document->children[1]->attr('caption'));
        $t->same('table_head', $document->children[1]->children[0]->type);
        $t->same('table_body', $document->children[1]->children[1]->type);
        $t->contains('<figure class="wp-block-image xml-image" id="fig-chart">', $blocks);
        $t->contains('<img src="images/chart.png" alt="Chart alt" data-pandoc-width="640" data-pandoc-height="480" data-pandoc-source="xml" data-xml-element="graphic"/>', $blocks);
        $t->contains('<figcaption>Chart <strong>caption</strong>.</figcaption>', $blocks);
        $t->contains('<table id="inventory" data-pandoc-source="xml" data-xml-element="table">', $blocks);
        $t->contains('<th data-xml-element="th">Name</th><th data-xml-element="th">Qty</th>', $blocks);
        $t->contains('<td data-xml-element="td">Widget</td><td data-xml-element="td">7</td>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Inventory</figcaption>', $blocks);
    },
    'preserves unknown xml containers and mathml while rejecting unsafe xml' => static function (TestRunner $t): void {
        $source = <<<'XML'
<doc xmlns:m="http://www.w3.org/1998/Math/MathML">
  <warning role="note">
    <p>Use <m:math><m:mi>x</m:mi></m:math> carefully.<br/>Next line.</p>
  </warning>
  <quote><p>Quoted text.</p></quote>
</doc>
XML;

        $document = (new XmlReader())->read($source);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, $document->attr('meta')['xmlGenericContainerCount']);
        $t->same('div', $document->children[0]->type);
        $t->same(['xml-element', 'xml-warning'], $document->children[0]->attr('classes'));
        $t->same('blockquote', $document->children[1]->type);
        $t->contains('<div data-xml-element="warning" role="note" class="xml-element xml-warning">', $blocks);
        $t->contains('<m:math><m:mi>x</m:mi></m:math> carefully.<br/>Next line.', $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p data-xml-element="p">Quoted text.</p></blockquote>', $blocks);
        $t->throws(InvalidArgumentException::class, static fn (): mixed => (new XmlReader())->read('<!DOCTYPE doc [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><doc>&xxe;</doc>'));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => (new XmlReader())->read('<?xml-stylesheet href="https://example.invalid/style.xsl"?><doc/>'));
    },
];
