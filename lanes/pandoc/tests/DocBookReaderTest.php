<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocBookReader;
use PortLibs\Pandoc\PandocConverter;

return [
    'maps docbook document metadata sections lists links media tables and bibliography into ast blocks' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2" xml:lang="en" xml:id="article-root">
  <info>
    <title>DocBook Reader Demo</title>
    <subtitle>XML to WordPress Blocks</subtitle>
    <author><personname><firstname>Jane</firstname><surname>Doe</surname></personname></author>
    <abstract><para>This abstract becomes metadata and body content.</para></abstract>
  </info>
  <section xml:id="intro">
    <title>Introduction</title>
    <para>Paragraph with <emphasis role="strong">strong text</emphasis>, <code>inline_code</code>, and <link linkend="table-1">a table link</link>.</para>
    <itemizedlist>
      <listitem><para>First item</para></listitem>
      <listitem><para>Second item</para></listitem>
    </itemizedlist>
    <variablelist>
      <varlistentry><term>Plugin</term><listitem><para>Stable release.</para></listitem></varlistentry>
    </variablelist>
    <figure xml:id="fig-1">
      <title>Architecture</title>
      <mediaobject>
        <imageobject><imagedata fileref="images/architecture.png"/></imageobject>
        <textobject><phrase>Architecture diagram</phrase></textobject>
      </mediaobject>
    </figure>
    <informaltable xml:id="table-1">
      <tgroup cols="2">
        <colspec colname="c1" colwidth="1*"/>
        <colspec colname="c2" colwidth="3*"/>
        <thead><row><entry>Field</entry><entry>Status</entry></row></thead>
        <tbody><row><entry align="left">Parser</entry><entry align="center">Ready</entry></row></tbody>
      </tgroup>
    </informaltable>
  </section>
  <bibliography xml:id="refs">
    <title>References</title>
    <biblioentry xml:id="ref-a">
      <title>DocBook Source</title>
      <author><personname><surname>Smith</surname></personname></author>
      <pubdate>2026</pubdate>
    </biblioentry>
  </bibliography>
</article>
XML;

        $document = PandocConverter::read($docbook, 'docbook');
        $blocks = PandocConverter::write($document, 'blocks');
        $markdown = PandocConverter::write($document, 'markdown');
        $meta = $document->attr('meta');

        $t->same('docbook', $document->attr('sourceFormat'));
        $t->same('article', $meta['rootName']);
        $t->same('DocBook Reader Demo', $meta['title']);
        $t->same('5.2', $meta['docbookVersion']);
        $t->same(1, $meta['docbookSectionCount']);
        $t->same(1, $meta['docbookTableCount']);
        $t->same(1, $meta['docbookFigureCount']);
        $t->same(1, $meta['docbookBibliographyEntryCount']);
        $t->same(1, $meta['xmlDetectedTables']);
        $t->same('docbook-structure-review-only', $meta['docbookStructure']['reviewPolicy']);
        $t->same('docbook-structural-media-review-only', $meta['docbookReviewPacket']['reviewPolicy']);
        $t->same('docbook-bibliography-reference-review-only', $meta['docbookBibliography']['reviewPolicy']);
        $t->contains('<h1>DocBook Reader Demo</h1>', $blocks);
        $t->contains('<h2>Abstract</h2>', $blocks);
        $t->contains('<h2>Introduction</h2>', $blocks);
        $t->contains('<strong>strong text</strong>', $blocks);
        $t->contains('<code>inline_code</code>', $blocks);
        $t->contains('<a href="#table-1">a table link</a>', $blocks);
        $t->contains('<ul><li>First item</li><li>Second item</li></ul>', $blocks);
        $t->contains('<dl><dt>Plugin</dt><dd>Stable release.</dd></dl>', $blocks);
        $t->contains('<img src="images/architecture.png" alt="Architecture diagram" data-docbook-media-source="figure"/>', $blocks);
        $t->contains('<colgroup><col style="width:25%"/><col style="width:75%"/></colgroup>', $blocks);
        $t->contains('<td style="text-align:left">Parser</td><td style="text-align:center">Ready</td>', $blocks);
        $t->contains('<dl><dt>ref-a</dt><dd>DocBook Source. Smith. 2026</dd></dl>', $blocks);
        $t->contains('# DocBook Reader Demo', $markdown);
        $t->contains('Field             Status', $markdown);
        $t->contains('Parser            Ready', $markdown);
    },
    'maps docbook refentry callouts glossary segmented lists qanda and equations' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<refentry xmlns="http://docbook.org/ns/docbook" version="5.2" xml:id="cli-ref">
  <refnamediv>
    <refname>wp-import</refname>
    <refpurpose>import content</refpurpose>
  </refnamediv>
  <refsect1 xml:id="usage">
    <title>Usage</title>
    <para>Inline equation <inlineequation><mathphrase>x^2</mathphrase></inlineequation>.</para>
    <equation><title>Energy</title><mathphrase>E = mc^2</mathphrase></equation>
    <simplelist><member>Alpha</member><member>Beta</member></simplelist>
    <segmentedlist>
      <segtitle>Name</segtitle><segtitle>Status</segtitle>
      <seglistitem><seg>Parser</seg><seg>Ready</seg></seglistitem>
    </segmentedlist>
    <calloutlist><callout arearefs="co1"><para>Review callout.</para></callout></calloutlist>
    <glosslist><glossentry><glossterm>AST</glossterm><glossdef><para>Abstract syntax tree.</para></glossdef></glossentry></glosslist>
    <qandaset><qandaentry><question><para>Ready?</para></question><answer><para>Yes.</para></answer></qandaentry></qandaset>
  </refsect1>
</refentry>
XML;

        $document = PandocConverter::read($docbook, 'docbook');
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('wp-import', $meta['title']);
        $t->same('refentry', $meta['rootName']);
        $t->contains('<h1>wp-import</h1>', $blocks);
        $t->contains('<p>wp-import - import content</p>', $blocks);
        $t->contains('<h2>Usage</h2>', $blocks);
        $t->contains('<span class="math inline">\\(x^2\\)</span>', $blocks);
        $t->contains('<h3>Energy</h3>', $blocks);
        $t->contains('<span class="math display">\\[E = mc^2\\]</span>', $blocks);
        $t->contains('<ul><li>Alpha</li><li>Beta</li></ul>', $blocks);
        $t->contains('<th>Name</th><th>Status</th>', $blocks);
        $t->contains('<td>Parser</td><td>Ready</td>', $blocks);
        $t->contains('<ol><li data-docbook-arearefs="co1">Review callout.</li></ol>', $blocks);
        $t->contains('<dl><dt>AST</dt><dd>Abstract syntax tree.</dd></dl>', $blocks);
        $t->contains('<dl><dt>Ready?</dt><dd>Yes.</dd></dl>', $blocks);
    },
    'preserves docbook sets anchors index terms and callout area specs' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<set xmlns="http://docbook.org/ns/docbook" version="5.2" xml:id="set-root">
  <title>Documentation Set</title>
  <book xml:id="book-one">
    <title>Operator Guide</title>
    <chapter xml:id="install">
      <title>Install</title>
      <para>Target <anchor xml:id="install-anchor"/> and index <indexterm xml:id="idx-install"><primary>Install</primary><secondary>CLI</secondary></indexterm> text plus callout <co xml:id="inline-co" linkends="call-install" label="1"/>.</para>
      <programlistingco>
        <areaspec><area xml:id="co-install" linkends="call-install" coords="1" units="line"/></areaspec>
        <programlisting language="bash">wp import</programlisting>
        <calloutlist><callout xml:id="call-install" arearefs="co-install"><para>Run the import command.</para></callout></calloutlist>
      </programlistingco>
    </chapter>
  </book>
</set>
XML;

        $document = PandocConverter::read($docbook, 'docbook');
        $blocks = PandocConverter::write($document, 'blocks');
        $markdown = PandocConverter::write($document, 'markdown');
        $meta = $document->attr('meta');
        $code = $document->children[4];
        $areas = $code->attr('docbookAreas');

        $t->same('set', $meta['rootName']);
        $t->same('Documentation Set', $meta['title']);
        $t->same('heading', $document->children[0]->type);
        $t->same('Operator Guide', $document->children[1]->attr('text'));
        $t->same(2, $document->children[1]->attr('level'));
        $t->same('Install', $document->children[2]->attr('text'));
        $t->same(3, $document->children[2]->attr('level'));
        $t->same('code_block', $code->type);
        $t->same('1', $code->attr('attributes')['data-docbook-area-count'] ?? null);
        $t->same([[
            'id' => 'co-install',
            'linkends' => 'call-install',
            'coords' => '1',
            'units' => 'line',
        ]], $areas);
        $t->contains('<h1>Documentation Set</h1>', $blocks);
        $t->contains('<h2>Operator Guide</h2>', $blocks);
        $t->contains('<h3>Install</h3>', $blocks);
        $t->contains('<span id="install-anchor" class="anchor docbook-anchor" data-docbook-anchor="true" data-docbook-anchor-id="install-anchor" data-pandoc-anchor="empty-target"></span>', $blocks);
        $t->contains('<span class="indexref docbook-indexterm" data-pandoc-index-entry="Install; CLI"></span>', $blocks);
        $t->contains('<span id="inline-co" class="docbook-callout" data-docbook-callout="true" data-docbook-callout-id="inline-co" data-docbook-callout-label="1" data-docbook-callout-linkends="call-install">1</span>', $blocks);
        $t->contains('<pre class="wp-block-code" data-docbook-area-count="1"><code class="language-bash">wp import</code></pre>', $blocks);
        $t->contains('<ol><li data-docbook-arearefs="co-install">Run the import command.</li></ol>', $blocks);
        $t->contains('[]{#install-anchor .anchor .docbook-anchor data-docbook-anchor="true"', $markdown);
        $t->contains('[]{#idx-install .indexref .docbook-indexterm entry="Install; CLI"', $markdown);
    },
    'preserves standalone docbook table fragment spans widths and alignments' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<informaltable frame="all" rowsep="1" colsep="1">
  <tgroup cols="4">
    <colspec colname="col_1" colwidth="1*"/>
    <colspec colname="col_2" colwidth="1*"/>
    <colspec colname="col_3" colwidth="2*"/>
    <colspec colname="col_4" colwidth="4*"/>
    <thead>
      <row><entry namest="col_1" nameend="col_2">Scope</entry><entry align="right" morerows="1">Count</entry><entry>Status</entry></row>
      <row><entry>Area</entry><entry>Phase</entry><entry>Owner</entry></row>
    </thead>
    <tbody>
      <row><entry align="left">Posts</entry><entry>Import</entry><entry align="center">42</entry><entry><emphasis role="strong">ready</emphasis></entry></row>
    </tbody>
  </tgroup>
</informaltable>
XML;

        $document = (new DocBookReader())->read($docbook);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $blocks = PandocConverter::write($document, 'blocks');

        $t->same('docbook', $document->attr('sourceFormat'));
        $t->same('table', $table->type);
        $t->same(4, count($table->attr('alignments')));
        $t->same([0.125, 0.125, 0.25, 0.5], $table->attr('widths'));
        $t->same(2, $head->children[0]->children[0]->attr('colspan'));
        $t->same(2, $head->children[0]->children[1]->attr('rowspan'));
        $t->same('right', $head->children[0]->children[1]->attr('align'));
        $t->same('left', $body->children[0]->children[0]->attr('align'));
        $t->same('strong', $body->children[0]->children[3]->children[0]->type);
        $t->contains('<col style="width:12.5%"/><col style="width:12.5%"/><col style="width:25%"/><col style="width:50%"/>', $blocks);
        $t->contains('<th colspan="2">Scope</th><th rowspan="2" style="text-align:right">Count</th><th>Status</th>', $blocks);
        $t->contains('<td style="text-align:left">Posts</td><td>Import</td><td style="text-align:center">42</td><td><strong>ready</strong></td>', $blocks);
    },
    'rejects non docbook xml instead of treating it as markdown' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('<topic><title>Nope</title><p>Not DocBook.</p></topic>', 'docbook');
        });
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('<article><title>Unclosed', 'docbook');
        });
    },
];
