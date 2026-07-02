<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries hyphenated biblatex url description aliases through legacy csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@online{hyphen-url-description,
  author          = {Ng, Nia},
  title           = {Hyphen URL Description Packet},
  date            = {2026},
  url             = {https://example.test/hyphen-url-description},
  url-description = {Reviewer mirror label}
}

@online{legacy-url-label,
  author    = {Roe, Pat},
  title     = {Legacy URL Label Packet},
  date      = {2025},
  url       = {https://example.test/legacy-url-label},
  url-label = {Legacy review label}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);

        $t->same('Reviewer mirror label', $items['hyphen-url-description']['URL-label'] ?? null);
        $t->same('Legacy review label', $items['legacy-url-label']['URL-label'] ?? null);
        $t->same('Reviewer mirror label', $items['hyphen-url-description']['rawBibtex']['fields']['url-description'] ?? null);
        $t->same('Legacy review label', $items['legacy-url-label']['rawBibtex']['fields']['url-label'] ?? null);
        $t->contains('URL label: Reviewer mirror label.', $processor->renderBibliographyText($items['hyphen-url-description']));

        $strictItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('Reviewer mirror label', $strictItems[0]['URL-label'] ?? null);
        $t->same('Legacy review label', $strictItems[1]['URL-label'] ?? null);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="url-description"/>
        <text variable="URL"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="url-label"/>
      <text variable="url"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Reviewer mirror label', $styled->item('hyphen-url-description')['urlLabel'] ?? null);
        $t->same('[Ng | Reviewer mirror label | https://example.test/hyphen-url-description; Roe | Legacy review label | https://example.test/legacy-url-label]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'hyphen-url-description', 'text' => '[@hyphen-url-description]']),
            new AstNode('citation', ['id' => 'legacy-url-label', 'text' => '[@legacy-url-label]']),
        ]));
        $t->same('Hyphen URL Description Packet :: Reviewer mirror label :: https://example.test/hyphen-url-description', $styled->renderBibliographyEntry('hyphen-url-description'));

        $document = (new MarkdownReader())->read('URL description aliases cite @hyphen-url-description and [@legacy-url-label].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['hyphen-url-description', 'legacy-url-label'], $handoff['citedKeys']);
        $t->same('Reviewer mirror label', $handoff['items'][0]['URL-label'] ?? null);
        $t->contains('URL label: Reviewer mirror label.', $blocks);
        $t->contains('URL label: Legacy review label.', $blocks);
    },
];
