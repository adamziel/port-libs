<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex original container title metadata through csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@article{original-container-legacy,
  author                = {Ng, Nia},
  title                 = {Review of Migrated Article},
  journaltitle          = {Modern Review},
  origjournaltitle      = {Journal of Source Imports},
  origjournalsubtitle   = {Archive Series},
  origjournaltitleaddon = {bound source issue},
  date                  = {2026}
}

@incollection{original-container-hyphen,
  author                         = {Roe, Pat},
  title                          = {Hyphen Packet Chapter},
  booktitle                      = {Modern Collection},
  original-container-title       = {Source Proceedings},
  original-container-subtitle    = {Recovered Panels},
  original-container-title-addon = {catalog copy},
  date                           = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $legacy = $items['original-container-legacy'];
        $hyphen = $items['original-container-hyphen'];

        $t->same('Journal of Source Imports: Archive Series', $legacy['original-container-title']);
        $t->same('bound source issue', $legacy['original-container-title-addon']);
        $t->same('Journal of Source Imports', $legacy['rawBibtex']['fields']['origjournaltitle']);
        $t->same('Archive Series', $legacy['rawBibtex']['fields']['origjournalsubtitle']);
        $t->same('Source Proceedings: Recovered Panels', $hyphen['original-container-title']);
        $t->same('catalog copy', $hyphen['original-container-title-addon']);
        $t->same('Source Proceedings', $hyphen['rawBibtex']['fields']['original-container-title']);
        $t->contains('Original container title: Journal of Source Imports: Archive Series.', $processor->renderBibliographyText($legacy));
        $t->contains('Original container title addendum: bound source issue.', $processor->renderBibliographyText($legacy));

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('Journal of Source Imports: Archive Series', $parserItems[0]['original-container-title'] ?? null);
        $t->same('bound source issue', $parserItems[0]['original-container-title-addon'] ?? null);
        $t->same('Source Proceedings: Recovered Panels', $parserItems[1]['original-container-title'] ?? null);
        $t->same('catalog copy', $parserItems[1]['original-container-title-addon'] ?? null);

        $core = CitationCslProcessor::fromBibtex($biblatex);
        $coreLegacy = $core->item('original-container-legacy');
        $t->same('Journal of Source Imports: Archive Series', $coreLegacy['originalContainerTitle'] ?? null);
        $t->same('Archive Series', $coreLegacy['originalContainerSubtitle'] ?? null);
        $t->same('bound source issue', $coreLegacy['originalContainerTitleAddon'] ?? null);
        $t->contains('Original container title: Journal of Source Imports: Archive Series.', $core->renderBibliographyEntry('original-container-legacy'));
        $t->contains('Original container title addendum: bound source issue.', $core->renderBibliographyEntry('original-container-legacy'));

        $directProcessor = CitationCslProcessor::fromItems([[
            'id' => 'direct-original-container',
            'title' => 'Direct Original Container',
            'originalContainerTitle' => 'Direct Source Journal',
            'originalContainerTitleAddon' => 'direct catalog note',
        ]]);
        $direct = $directProcessor->item('direct-original-container');
        $t->same('Direct Source Journal', $direct['originalContainerTitle'] ?? null);
        $t->same('direct catalog note', $direct['originalContainerTitleAddon'] ?? null);
        $t->contains('Original container title: Direct Source Journal.', $directProcessor->renderBibliographyEntry('direct-original-container'));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Original Container Title Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-original-container-title-review</id>
    <updated>2026-07-02T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="origjournaltitle"/>
        <text variable="origjournaltitleaddon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-container-title"/>
      <text variable="original-container-title-addon"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Bounded Legacy BibLaTeX Original Container Title Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('Journal of Source Imports: Archive Series', $styled->item('original-container-legacy')['originalContainerTitle'] ?? null);
        $t->same('[Ng | Journal of Source Imports: Archive Series | bound source issue; Roe | Source Proceedings: Recovered Panels | catalog copy]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'original-container-legacy', 'text' => '[@original-container-legacy]']),
            new AstNode('citation', ['id' => 'original-container-hyphen', 'text' => '[@original-container-hyphen]']),
        ]));
        $t->same('Review of Migrated Article :: Journal of Source Imports: Archive Series :: bound source issue', $styled->renderBibliographyEntry('original-container-legacy'));
        $t->same('Hyphen Packet Chapter :: Source Proceedings: Recovered Panels :: catalog copy', $styled->renderBibliographyEntry('original-container-hyphen'));

        $document = (new MarkdownReader())->read('Original containers cite @original-container-legacy and [@original-container-hyphen].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['original-container-legacy', 'original-container-hyphen'], $handoff['citedKeys']);
        $t->same('Journal of Source Imports: Archive Series', $handoff['items'][0]['original-container-title']);
        $t->same('catalog copy', $handoff['bibliography']->children[1]->attr('cslItem')['original-container-title-addon'] ?? null);
        $t->contains('Original container title: Journal of Source Imports: Archive Series.', $blocks);
        $t->contains('Original container title addendum: catalog copy.', $blocks);
    },
];
