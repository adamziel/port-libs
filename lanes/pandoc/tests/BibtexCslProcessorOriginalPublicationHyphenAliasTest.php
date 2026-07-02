<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries compact hyphenated biblatex original publication aliases through csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{orig-hyphen-source,
  author           = {Ng, Nia},
  title            = {Hyphen Orig Alias Packet},
  date             = {2026},
  orig-title       = {Manual Fuente},
  orig-subtitle    = {Archivo Appendix},
  orig-title-addon = {source leaf},
  orig-date        = {1999-03-05},
  orig-publisher   = {{Archivo Press} and {Migration Desk}},
  orig-location    = {{Madrid} and {Barcelona}},
  orig-language    = {{spanish} and {catalan}},
  orig-genre       = {facsimile}
}
BIB;

        $processor = new BibtexCslProcessor();
        $item = $processor->cslItems($biblatex)['orig-hyphen-source'];

        $t->same('Manual Fuente: Archivo Appendix', $item['original-title']);
        $t->same('source leaf', $item['original-title-addon']);
        $t->same([1999, 3, 5], $item['original-date']['date-parts'][0]);
        $t->same('Archivo Press; Migration Desk', $item['original-publisher']);
        $t->same(['Archivo Press', 'Migration Desk'], $item['original-publisher-list']);
        $t->same('Madrid; Barcelona', $item['original-publisher-place']);
        $t->same(['Madrid', 'Barcelona'], $item['original-publisher-place-list']);
        $t->same('spanish; catalan', $item['original-language']);
        $t->same(['spanish', 'catalan'], $item['original-language-list']);
        $t->same('facsimile', $item['original-genre']);
        $t->same('Manual Fuente', $item['rawBibtex']['fields']['orig-title']);
        $t->same('Archivo Press and Migration Desk', $item['rawBibtex']['fields']['orig-publisher']);
        $t->same('Madrid and Barcelona', $item['rawBibtex']['fields']['orig-location']);
        $t->contains('Original genre: facsimile.', $processor->renderBibliographyText($item));

        $styled = CitationCslProcessor::fromItems([$item])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded BibLaTeX Orig Hyphen Alias Review</title>
    <id>https://example.test/styles/bounded-biblatex-orig-hyphen-alias-review</id>
    <updated>2026-07-02T01:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="origtitle"/>
        <date variable="origdate"/>
        <text variable="origpublisher"/>
        <text variable="origlocation"/>
        <text variable="origlanguage"/>
        <text variable="origgenre"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-title"/>
      <date variable="original-date"/>
      <text variable="original-publisher-list"/>
      <text variable="original-publisher-place-list"/>
      <text variable="original-language-list"/>
      <text variable="original-genre"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('orig-hyphen-source');
        $t->same('Manual Fuente: Archivo Appendix', $normalized['originalTitle'] ?? null);
        $t->same('1999-03-05', $normalized['originalDate']['display'] ?? null);
        $t->same(['Archivo Press', 'Migration Desk'], $normalized['originalPublisherList'] ?? null);
        $t->same(['Madrid', 'Barcelona'], $normalized['originalPublisherPlaceList'] ?? null);
        $t->same(['spanish', 'catalan'], $normalized['originalLanguageList'] ?? null);
        $t->same('facsimile', $normalized['originalGenre'] ?? null);
        $t->same('[Ng | Manual Fuente: Archivo Appendix | 1999-03-05 | Archivo Press; Migration Desk | Madrid; Barcelona | spanish; catalan | facsimile]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'orig-hyphen-source', 'text' => '[@orig-hyphen-source]']),
        ]));
        $t->same('Hyphen Orig Alias Packet :: Manual Fuente: Archivo Appendix :: 1999-03-05 :: Archivo Press; Migration Desk :: Madrid; Barcelona :: spanish; catalan :: facsimile', $styled->renderBibliographyEntry('orig-hyphen-source'));

        $document = (new MarkdownReader())->read('Orig aliases cite @orig-hyphen-source.');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['orig-hyphen-source'], $handoff['citedKeys']);
        $t->same('Archivo Press; Migration Desk', $handoff['items'][0]['original-publisher']);
        $t->same('Madrid; Barcelona', $handoff['bibliography']->children[0]->attr('cslItem')['original-publisher-place'] ?? null);
        $t->contains('Hyphen Orig Alias Packet', $blocks);
    },
];
