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
        $t->true(!str_contains($blocks, 'DocBook Reader Demo'));
        $t->true(!str_contains($blocks, 'This abstract becomes metadata and body content.'));
        $t->contains('<h1>Introduction</h1>', $blocks);
        $t->contains('<strong>strong text</strong>', $blocks);
        $t->contains('<code>inline_code</code>', $blocks);
        $t->contains('<a href="#table-1">a table link</a>', $blocks);
        $t->contains('<ul><li>First item</li><li>Second item</li></ul>', $blocks);
        $t->contains('pandoc-definition-list', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>Plugin</strong></p>', $blocks);
        $t->contains('<ul class="pandoc-definition-values"><li>Stable release.</li></ul>', $blocks);
        $t->contains('<img src="images/architecture.png" alt="Architecture diagram" data-docbook-media-source="figure" data-docbook-media-selected-object="imageobject"/>', $blocks);
        $t->contains('<colgroup><col style="width:25%"/><col style="width:75%"/></colgroup>', $blocks);
        $t->contains('<td style="text-align:left">Parser</td><td style="text-align:center">Ready</td>', $blocks);
        $t->contains('pandoc-definition-list docbook-bibliography', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>ref-a</strong></p>', $blocks);
        $t->contains('<ul class="pandoc-definition-values"><li>DocBook Source. Smith. 2026</li></ul>', $blocks);
    },
    'resolves docbook media resources through media bag extraction' => static function (TestRunner $t): void {
        $svgBytes = "<svg><text>hero diagram</text></svg>\n";
        $docbook = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2">
  <title>Media Resource Demo</title>
  <section>
    <title>Body</title>
    <figure xml:id="fig-hero">
      <title>Hero figure</title>
      <mediaobject>
        <imageobject><imagedata fileref="assets/hero.svg?rev=1#view" format="SVG" contentwidth="320px" depth="240px"/></imageobject>
        <textobject><phrase>Hero diagram</phrase></textobject>
      </mediaobject>
    </figure>
    <para>Inline <inlinemediaobject><imageobject><imagedata fileref="assets/missing.png"/></imageobject><textobject><phrase>Missing icon</phrase></textobject></inlinemediaobject> done.</para>
  </section>
</article>
XML;

        $document = PandocConverter::read($docbook, 'docbook', [
            'mediaResources' => [
                'assets/hero.svg' => [
                    'contents' => $svgBytes,
                    'mimeType' => 'image/svg+xml',
                ],
            ],
            'extractMediaTo' => 'docbook-media',
        ]);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $missingPlaceholder = $document->children[2]->children[1];
        $mediaPath = sha1($svgBytes) . '.svg';
        $mappedPath = 'docbook-media/' . $mediaPath;

        $t->same([
            'media-resource-loaded:assets/hero.svg?rev=1#view',
            'media-resource-missing:assets/missing.png',
            'media-resource-mapped:assets/hero.svg?rev=1#view',
        ], $meta['docbookMediaResourceDiagnostics']);
        $t->same('media-bag-option-resolved', $meta['docbookMediaResourcePolicy']);
        $t->same(1, $meta['docbookMediaResourceCount']);
        $t->same(1, $meta['docbookMediaResourceLoadedCount']);
        $t->same(1, $meta['docbookMediaResourceMissingCount']);
        $t->same(1, $meta['docbookMediaResourceMappedCount']);
        $t->same($mediaPath, $meta['docbookMediaResourceDirectory'][0]['path']);
        $t->same('image/svg+xml', $meta['docbookMediaResourceDirectory'][0]['mimeType']);
        $t->same(strlen($svgBytes), $meta['docbookMediaResourceDirectory'][0]['byteLength']);
        $t->same('docbook-media', $meta['docbookMediaExtractionDestination']);
        $t->same(1, $meta['docbookMediaExtractionCount']);
        $t->same($mappedPath, $meta['docbookMediaExtractionDirectory'][0]['path']);
        $t->same($mediaPath, $meta['docbookMediaExtractionDirectory'][0]['mediaPath']);
        $t->true(!array_key_exists('contents', $meta['docbookMediaExtractionDirectory'][0]), 'DocBook media extraction metadata must not expose raw bytes');
        $t->contains('<img src="' . $mappedPath . '" alt="Hero diagram"', $blocks);
        $t->contains('data-docbook-imagedata-format="SVG"', $blocks);
        $t->contains('data-docbook-imagedata-contentwidth="320px"', $blocks);
        $t->contains('data-docbook-imagedata-depth="240px"', $blocks);
        $t->contains('data-pandoc-width="320px"', $blocks);
        $t->contains('data-pandoc-height="240px"', $blocks);
        $t->contains('data-pandoc-media-source="assets/hero.svg?rev=1#view"', $blocks);
        $t->contains('data-pandoc-media-target="' . $mappedPath . '"', $blocks);
        $t->same('span', $missingPlaceholder->type);
        $t->same('assets/missing.png', $missingPlaceholder->attr('attributes')['original-image-src']);
        $t->contains('Missing icon', $blocks);
    },
    'preserves docbook media alternatives and non image fallbacks' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2">
  <title>Media Alternatives</title>
  <section>
    <title>Body</title>
    <mediaobject xml:id="text-media"><textobject><phrase>Transcript fallback text</phrase></textobject></mediaobject>
    <mediaobject><videoobject><videodata fileref="media/demo.mp4"/></videoobject></mediaobject>
    <para>Inline <inlinemediaobject><audioobject><audiodata fileref="media/beep.mp3"/></audioobject><textobject><phrase>Audio beep</phrase></textobject></inlinemediaobject> done.</para>
    <mediaobject>
      <imageobject role="print"><imagedata fileref="images/print.png"/></imageobject>
      <imageobject role="screen"><imagedata fileref="images/screen.png"/></imageobject>
      <textobject><phrase>Alternate diagram</phrase></textobject>
    </mediaobject>
  </section>
</article>
XML;

        $document = PandocConverter::read($docbook, 'docbook');
        $blocks = PandocConverter::write($document, 'blocks');
        $textFallback = $document->children[1];
        $videoFallback = $document->children[2];
        $inlineParagraph = $document->children[3];
        $imageFigure = $document->children[4];

        $t->same('div', $textFallback->type);
        $t->same('docbook-media-fallback', $textFallback->attr('classes')[0]);
        $t->same('paragraph', $textFallback->children[0]->type);
        $t->same('span', $textFallback->children[0]->children[0]->type);
        $t->same('Transcript fallback text', $textFallback->children[0]->children[0]->children[0]->attr('text'));
        $t->same('div', $videoFallback->type);
        $t->same('link', $videoFallback->children[0]->children[0]->type);
        $t->same('media/demo.mp4', $videoFallback->children[0]->children[0]->attr('url'));
        $t->same('span', $inlineParagraph->children[1]->type);
        $t->same('Audio beep', $inlineParagraph->children[1]->children[0]->attr('text'));
        $t->same('figure', $imageFigure->type);
        $t->same('images/print.png', $imageFigure->children[0]->attr('url'));
        $t->same('imageobject', $imageFigure->children[0]->attr('attributes')['data-docbook-media-selected-object']);
        $t->contains('Transcript fallback text', $blocks);
        $t->contains('<a href="media/demo.mp4"', $blocks);
        $t->contains('data-docbook-media-fallback="videodata"', $blocks);
        $t->contains('Audio beep', $blocks);
        $t->contains('<img src="images/print.png" alt="Alternate diagram"', $blocks);
        $t->contains('data-docbook-media-selected-object="imageobject"', $blocks);
        $t->true(!str_contains($blocks, 'images/screen.png'));
    },
    'uses docbook xreflabel attributes for xref targets' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2">
  <title>Xref Label Demo</title>
  <section>
    <title>Body</title>
    <para>See <xref linkend="api-contract"/> for details.</para>
    <section xml:id="api-contract" xreflabel="API contract">
      <title>Internal Section Title</title>
      <para>Contract body.</para>
    </section>
  </section>
</article>
XML;

        $document = PandocConverter::read($docbook, 'docbook');
        $blocks = PandocConverter::write($document, 'blocks');
        $paragraph = $document->children[1];

        $t->same('API contract', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('API contract', $paragraph->children[1]->attr('attributes')['data-docbook-xref-target-label']);
        $t->contains('<a href="#api-contract" data-docbook-xref-target="api-contract" data-docbook-xref-target-label="API contract" data-docbook-xref-target-element="section">API contract</a>', $blocks);
        $t->true(!str_contains($blocks, '>Internal Section Title</a>'));
    },
    'resolves docbook xref labels from target titles and endterms' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2">
  <title>Xref Demo</title>
  <section xml:id="body">
    <title>Body</title>
    <para>See <xref linkend="fig-arch"/>, <xref linkend="table-fields"/>, <xref linkend="intro" endterm="intro-label"/>, <xref linkend="fig-arch">explicit figure text</xref>, and <xref linkend="missing-target"/>.</para>
    <para><phrase xml:id="intro-label">the introduction section</phrase></para>
    <figure xml:id="fig-arch">
      <label>Figure 1</label>
      <title>Architecture</title>
      <mediaobject><textobject><phrase>Architecture fallback</phrase></textobject></mediaobject>
    </figure>
    <informaltable xml:id="table-fields">
      <title>Field Status</title>
      <tgroup cols="1"><tbody><row><entry>Ready</entry></row></tbody></tgroup>
    </informaltable>
    <section xml:id="intro"><title>Introduction</title><para>Intro body.</para></section>
  </section>
</article>
XML;

        $document = PandocConverter::read($docbook, 'docbook');
        $blocks = PandocConverter::write($document, 'blocks');
        $paragraph = $document->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'link', 'text', 'link', 'text', 'link', 'text', 'link', 'text', 'link', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('Figure 1: Architecture', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('fig-arch', $paragraph->children[1]->attr('attributes')['data-docbook-xref-target']);
        $t->same('Figure 1: Architecture', $paragraph->children[1]->attr('attributes')['data-docbook-xref-target-label']);
        $t->same('figure', $paragraph->children[1]->attr('attributes')['data-docbook-xref-target-element']);
        $t->same('Field Status', $paragraph->children[3]->children[0]->attr('text'));
        $t->same('informaltable', $paragraph->children[3]->attr('attributes')['data-docbook-xref-target-element']);
        $t->same('the introduction section', $paragraph->children[5]->children[0]->attr('text'));
        $t->same('Introduction', $paragraph->children[5]->attr('attributes')['data-docbook-xref-target-label']);
        $t->same('intro-label', $paragraph->children[5]->attr('attributes')['data-docbook-xref-endterm']);
        $t->same('the introduction section', $paragraph->children[5]->attr('attributes')['data-docbook-xref-endterm-label']);
        $t->same('explicit figure text', $paragraph->children[7]->children[0]->attr('text'));
        $t->same('Figure 1: Architecture', $paragraph->children[7]->attr('attributes')['data-docbook-xref-target-label']);
        $t->same('missing-target', $paragraph->children[9]->children[0]->attr('text'));
        $t->same([], $paragraph->children[9]->attr('attributes', []));
        $t->contains('<a href="#fig-arch" data-docbook-xref-target="fig-arch" data-docbook-xref-target-label="Figure 1: Architecture" data-docbook-xref-target-element="figure">Figure 1: Architecture</a>', $blocks);
        $t->contains('<a href="#table-fields" data-docbook-xref-target="table-fields" data-docbook-xref-target-label="Field Status" data-docbook-xref-target-element="informaltable">Field Status</a>', $blocks);
        $t->contains('<a href="#intro" data-docbook-xref-target="intro" data-docbook-xref-target-label="Introduction" data-docbook-xref-target-element="section" data-docbook-xref-endterm="intro-label" data-docbook-xref-endterm-label="the introduction section">the introduction section</a>', $blocks);
        $t->contains('<a href="#missing-target">missing-target</a>', $blocks);
    },
    'maps docbook citations and bibliorefs into citation ast nodes' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.2">
  <title>Citation Demo</title>
  <section>
    <title>Body</title>
    <para>Bibliography cites <citation>[ref-a]</citation>, keeps <citation role="review">free form source note</citation>, links <biblioref linkend="ref-a">DocBook Source</biblioref>, links by fragment <biblioref xlink:href="#ref-b">Second Source</biblioref>, and keeps external <biblioref xlink:href="https://example.test/ref">external ref</biblioref>.</para>
  </section>
  <bibliography>
    <biblioentry xml:id="ref-a"><title>DocBook Source</title><pubdate>2026</pubdate></biblioentry>
    <biblioentry xml:id="ref-b"><title>Second Source</title><pubdate>2025</pubdate></biblioentry>
  </bibliography>
</article>
XML;

        $document = PandocConverter::read($docbook, 'docbook');
        $blocks = PandocConverter::write($document, 'blocks');
        $paragraph = $document->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'citation', 'text', 'span', 'text', 'citation', 'text', 'citation', 'text', 'link', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('ref-a', $paragraph->children[1]->attr('id'));
        $t->same('citation', $paragraph->children[1]->attr('sourceElement'));
        $t->same('ref-a', $paragraph->children[5]->attr('id'));
        $t->same('biblioref', $paragraph->children[5]->attr('sourceElement'));
        $t->same('ref-b', $paragraph->children[7]->attr('id'));
        $t->same('biblioref', $paragraph->children[7]->attr('sourceElement'));
        $t->same('https://example.test/ref', $paragraph->children[9]->attr('url'));
        $t->contains('data-pandoc-citation-id="ref-a"', $blocks);
        $t->contains('data-pandoc-citation-id="ref-b"', $blocks);
        $t->contains('<span class="docbook-review docbook-citation-text" data-docbook-role="review">free form source note</span>', $blocks);
        $t->contains('>DocBook Source</span>', $blocks);
        $t->contains('>Second Source</span>', $blocks);
        $t->contains('<a href="https://example.test/ref">external ref</a>', $blocks);
    },
    'maps docbook grouped citations with affixes into citation ast payloads' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2">
  <title>Grouped Citation Demo</title>
  <section>
    <title>Body</title>
    <para>Grouped <citation>[see @ref-a, p. 9; -@ref-b]</citation> done.</para>
  </section>
  <bibliography>
    <biblioentry xml:id="ref-a"><title>DocBook Source</title></biblioentry>
    <biblioentry xml:id="ref-b"><title>Second Source</title></biblioentry>
  </bibliography>
</article>
XML;

        $document = PandocConverter::read($docbook, 'docbook');
        $blocks = PandocConverter::write($document, 'blocks');
        $paragraph = $document->children[1];
        $citation = $paragraph->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'citation', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('citation', $citation->attr('sourceElement'));
        $t->same(2, count($citation->attr('citations')));
        $t->same('ref-a', $citation->attr('citations')[0]['id']);
        $t->same('see', $citation->attr('citations')[0]['prefix'][0]->attr('text'));
        $t->same('p. 9', $citation->attr('citations')[0]['suffix'][0]->attr('text'));
        $t->same('ref-b', $citation->attr('citations')[1]['id']);
        $t->same('suppress_author', $citation->attr('citations')[1]['mode']);
        $t->contains('data-pandoc-citation-count="2"', $blocks);
        $t->contains('data-pandoc-citation-ids="[&quot;ref-a&quot;,&quot;ref-b&quot;]"', $blocks);
        $t->contains('&quot;prefix&quot;:&quot;see&quot;', $blocks);
        $t->contains('&quot;suffix&quot;:&quot;p. 9&quot;', $blocks);
        $t->contains('&quot;mode&quot;:&quot;suppress_author&quot;', $blocks);
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
        $t->contains('pandoc-definition-list docbook-glossary', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>AST</strong></p>', $blocks);
        $t->contains('<ul class="pandoc-definition-values"><li>Abstract syntax tree.</li></ul>', $blocks);
        $t->contains('pandoc-definition-list docbook-qanda', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>Ready?</strong></p>', $blocks);
        $t->contains('<ul class="pandoc-definition-values"><li>Yes.</li></ul>', $blocks);
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
        $meta = $document->attr('meta');
        $code = $document->children[3];
        $areas = $code->attr('docbookAreas');

        $t->same('set', $meta['rootName']);
        $t->same('Documentation Set', $meta['title']);
        $t->same('heading', $document->children[0]->type);
        $t->same('Operator Guide', $document->children[0]->attr('text'));
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('Install', $document->children[1]->attr('text'));
        $t->same(2, $document->children[1]->attr('level'));
        $t->same('code_block', $code->type);
        $t->same('1', $code->attr('attributes')['data-docbook-area-count'] ?? null);
        $t->same([[
            'id' => 'co-install',
            'linkends' => 'call-install',
            'coords' => '1',
            'units' => 'line',
            'label' => '2',
        ]], $areas);
        $t->true(!str_contains($blocks, 'Documentation Set'));
        $t->contains('<h1>Operator Guide</h1>', $blocks);
        $t->contains('<h2>Install</h2>', $blocks);
        $t->contains('<span id="install-anchor" class="anchor docbook-anchor" data-docbook-anchor="true" data-docbook-anchor-id="install-anchor" data-pandoc-anchor="empty-target"></span>', $blocks);
        $t->contains('<span class="indexref docbook-indexterm" data-pandoc-index-entry="Install; CLI"></span>', $blocks);
        $t->contains('<span id="inline-co" class="docbook-callout" data-docbook-callout="true" data-docbook-callout-id="inline-co" data-docbook-callout-linkends="call-install" data-docbook-callout-label="1">1</span>', $blocks);
        $t->contains('<pre class="wp-block-code" data-docbook-area-count="1"><code class="language-bash">wp import</code></pre>', $blocks);
        $t->contains('<ol><li data-docbook-arearefs="co-install" data-docbook-callout-label="1">Run the import command.</li></ol>', $blocks);
    },
    'generates docbook callout labels for markers areas and callout lists' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2">
  <title>Generated Callouts</title>
  <section>
    <title>Body</title>
    <para>Marker <co xml:id="co-auto" linkends="call-auto"/> done.</para>
    <calloutlist><callout xml:id="call-auto" arearefs="co-auto"><para>Generated callout text.</para></callout></calloutlist>
    <programlistingco>
      <areaspec>
        <area xml:id="area-auto" coords="1"/>
        <area xml:id="area-explicit" coords="2" label="B"/>
      </areaspec>
      <programlisting>one
two</programlisting>
      <calloutlist>
        <callout arearefs="area-auto"><para>Area generated.</para></callout>
        <callout arearefs="area-explicit"><para>Area explicit.</para></callout>
      </calloutlist>
    </programlistingco>
  </section>
</article>
XML;

        $document = PandocConverter::read($docbook, 'docbook');
        $blocks = PandocConverter::write($document, 'blocks');
        $paragraph = $document->children[1];
        $code = $document->children[3];
        $areas = $code->attr('docbookAreas');

        $t->same('paragraph', $paragraph->type);
        $t->same('span', $paragraph->children[1]->type);
        $t->same('1', $paragraph->children[1]->attr('attributes')['data-docbook-callout-label']);
        $t->same('1', $paragraph->children[1]->children[0]->attr('text'));
        $t->same([[
            'id' => 'area-auto',
            'coords' => '1',
            'label' => '2',
        ], [
            'id' => 'area-explicit',
            'coords' => '2',
            'label' => 'B',
        ]], $areas);
        $t->contains('data-docbook-callout-id="co-auto" data-docbook-callout-linkends="call-auto" data-docbook-callout-label="1">1</span>', $blocks);
        $t->contains('<ol><li data-docbook-arearefs="co-auto" data-docbook-callout-label="1">Generated callout text.</li></ol>', $blocks);
        $t->contains('<ol><li data-docbook-arearefs="area-auto" data-docbook-callout-label="2">Area generated.</li><li data-docbook-arearefs="area-explicit" data-docbook-callout-label="B">Area explicit.</li></ol>', $blocks);
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
    'imports namespaced docbook fragment roots with fallback review metadata' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<legalnotice xmlns="http://docbook.org/ns/docbook" version="5.0" xml:id="warranty">
  <title>Warranty</title>
  <para>THE SOFTWARE IS PROVIDED <quote>AS IS</quote>, WITHOUT WARRANTY.</para>
</legalnotice>
XML;

        $document = (new DocBookReader())->read($docbook);
        $meta = $document->attr('meta');
        $blocks = PandocConverter::write($document, 'blocks');

        $t->same('docbook', $document->attr('sourceFormat'));
        $t->same('docbook-fragment-structure-fallback', $meta['docbookStructure']['reviewPolicy']);
        $t->same('docbook-structural-media-review-only', $meta['docbookReviewPacket']['reviewPolicy']);
        $t->contains('DocBook review packet root must be a DocBook structural element', $meta['docbookStructureFallbackReason']);
        $t->same(false, array_key_exists('docbookReviewPacketFallbackReason', $meta));
        $t->same('legalnotice', $meta['rootName']);
        $t->same('Warranty', $document->children[0]->attr('text'));
        $t->contains('<h1>Warranty</h1>', $blocks);
        $t->contains('<p>THE SOFTWARE IS PROVIDED AS IS, WITHOUT WARRANTY.</p>', $blocks);
    },
    'recovers malformed but safe docbook producer xml' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.0">
  <title>Recovered DocBook</title>
  <section><title>Body</title><para>Producer emitted mismatched tags.</p></section>
</article>
XML;

        $document = (new DocBookReader())->read($docbook);
        $blocks = PandocConverter::write($document, 'blocks');

        $t->same('docbook', $document->attr('sourceFormat'));
        $t->same('Recovered DocBook', $document->attr('meta')['title']);
        $t->same('Body', $document->children[0]->attr('text'));
        $t->true(!str_contains($blocks, 'Recovered DocBook'));
        $t->contains('<h1>Body</h1>', $blocks);
        $t->contains('Producer emitted mismatched tags.', $blocks);
    },
    'rejects non docbook xml instead of treating it as markdown' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('<topic><title>Nope</title><p>Not DocBook.</p></topic>', 'docbook');
        });
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('<topic><title>Unclosed', 'docbook');
        });
    },
];
