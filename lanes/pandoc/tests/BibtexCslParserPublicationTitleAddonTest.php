<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex publication title addon aliases through direct csl parser handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@article{publication-addon-hyphen,
  author                 = {Ng, Nia},
  title                  = {Magazine Alias Packet},
  publication-title      = {Migration Monthly},
  publication-subtitle   = {Review Desk},
  publication-title-addon = {field note},
  date                   = {2026}
}

@article{publication-addon-compact,
  author                = {Roe, Pat},
  title                 = {Compact Publication Packet},
  publicationtitle      = {Archive Quarterly},
  publicationsubtitle   = {Source Channel},
  publicationtitleaddon = {editor packet},
  date                  = {2025}
}
BIB;

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same(2, count($parserItems));
        $t->same('Migration Monthly: Review Desk', $parserItems[0]['container-title'] ?? null);
        $t->same('field note', $parserItems[0]['container-title-addon'] ?? null);
        $t->same('Archive Quarterly: Source Channel', $parserItems[1]['container-title'] ?? null);
        $t->same('editor packet', $parserItems[1]['container-title-addon'] ?? null);
        $t->same('field note', $parserItems[0]['rawBibtex']['fields']['publication-title-addon'] ?? null);
        $t->same('editor packet', $parserItems[1]['rawBibtex']['fields']['publicationtitleaddon'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($biblatex);
        $hyphen = $processor->item('publication-addon-hyphen');
        $compact = $processor->item('publication-addon-compact');
        $t->same('field note', $hyphen['containerTitleAddon'] ?? null);
        $t->same('editor packet', $compact['containerTitleAddon'] ?? null);
        $t->contains('field note.', $processor->renderBibliographyEntry('publication-addon-hyphen'));
        $t->contains('editor packet.', $processor->renderBibliographyEntry('publication-addon-compact'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Direct BibLaTeX Publication Title Addon Review</title>
    <id>https://example.test/styles/bounded-direct-biblatex-publication-title-addon-review</id>
    <updated>2026-07-02T02:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="publication-title"/>
        <text variable="publication-title-addon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="publication-title"/>
      <text variable="publication-title-addon"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
        $t->same('Bounded Direct BibLaTeX Publication Title Addon Review', $summary['title'] ?? null);
        $t->same('publication-title-addon', $citationChildren[2]['variable'] ?? null);
        $t->same('[Ng | Migration Monthly: Review Desk | field note; Roe | Archive Quarterly: Source Channel | editor packet]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'publication-addon-hyphen', 'text' => '[@publication-addon-hyphen]']),
            new AstNode('citation', ['id' => 'publication-addon-compact', 'text' => '[@publication-addon-compact]']),
        ]));
        $t->same('Magazine Alias Packet :: Migration Monthly: Review Desk :: field note', $styled->renderBibliographyEntry('publication-addon-hyphen'));
        $t->same('Compact Publication Packet :: Archive Quarterly: Source Channel :: editor packet', $styled->renderBibliographyEntry('publication-addon-compact'));

        $document = (new MarkdownReader())->read('Publication title addenda [@publication-addon-hyphen; @publication-addon-compact] stay visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>Publication title addenda [Ng | Migration Monthly: Review Desk | field note; Roe | Archive Quarterly: Source Channel | editor packet] stay visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Magazine Alias Packet :: Migration Monthly: Review Desk :: field note</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Compact Publication Packet :: Archive Quarterly: Source Channel :: editor packet</dd>', $blocks);
    },
];
