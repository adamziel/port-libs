<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex volume and part title addenda through csl bibliography handoff' => static function (TestRunner $t): void {
        $bibtex = <<<'BIB'
@book{volume-part-addendum,
  author           = {Ng, Nia},
  title            = {Bounded Source Packet},
  date             = {2026},
  volumetitle      = {Archive Volume},
  volumesubtitle   = {Catalog Supplement},
  volumetitleaddon = {legacy volume note},
  parttitle        = {Evidence Part},
  partsubtitle     = {Audit Sheet},
  parttitleaddon   = {legacy part note},
  publisher        = {Migration Desk}
}

@book{hyphen-volume-part-addendum,
  author             = {Roe, Riley},
  title              = {Hyphen Source Packet},
  date               = {2025},
  volume-title       = {Hyphen Volume},
  volume-subtitle    = {Register},
  volume-title-addon = {hyphen volume note},
  part-title         = {Hyphen Part},
  part-subtitle      = {Ledger},
  part-title-addon   = {hyphen part note},
  publisher          = {Review Desk}
}
BIB;

        $legacy = new BibtexCslProcessor();
        $items = $legacy->cslItems($bibtex);
        $compact = $items['volume-part-addendum'];
        $hyphen = $items['hyphen-volume-part-addendum'];

        $t->same('Archive Volume: Catalog Supplement', $compact['volume-title']);
        $t->same('legacy volume note', $compact['volume-title-addon']);
        $t->same('Evidence Part: Audit Sheet', $compact['part-title']);
        $t->same('legacy part note', $compact['part-title-addon']);
        $t->same('legacy volume note', $compact['rawBibtex']['fields']['volumetitleaddon']);
        $t->same('legacy part note', $compact['rawBibtex']['fields']['parttitleaddon']);
        $t->same('Hyphen Volume: Register', $hyphen['volume-title']);
        $t->same('hyphen volume note', $hyphen['volume-title-addon']);
        $t->same('Hyphen Part: Ledger', $hyphen['part-title']);
        $t->same('hyphen part note', $hyphen['part-title-addon']);
        $t->same(
            'Nia Ng. Bounded Source Packet. Migration Desk. 2026. Volume title: Archive Volume: Catalog Supplement. Volume title addendum: legacy volume note. Part title: Evidence Part: Audit Sheet. Part title addendum: legacy part note.',
            $legacy->renderBibliographyText($compact)
        );

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $normalized = $processor->item('volume-part-addendum');
        $t->same('Archive Volume: Catalog Supplement', $normalized['volumeTitle'] ?? null);
        $t->same('legacy volume note', $normalized['volumeTitleAddon'] ?? null);
        $t->same('Evidence Part: Audit Sheet', $normalized['partTitle'] ?? null);
        $t->same('legacy part note', $normalized['partTitleAddon'] ?? null);
        $t->same(
            'Ng, Nia. Bounded Source Packet. Volume title: Archive Volume: Catalog Supplement. Volume title addendum: legacy volume note. Part title: Evidence Part: Audit Sheet. Part title addendum: legacy part note. Migration Desk, 2026.',
            $processor->renderBibliographyEntry('volume-part-addendum')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <sort>
      <key variable="volume-title-addon"/>
      <key variable="part-title-addon"/>
    </sort>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="volume-title-addon"/>
        <text variable="part-title-addon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="volume-title"/>
      <text variable="volume-title-addon"/>
      <text variable="part-title"/>
      <text variable="part-title-addon"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
        $bibliographyRendering = $summary['bibliographyRendering'] ?? [];
        $t->same('volume-title-addon', $summary['citationSort'][0]['variable'] ?? null);
        $t->same('part-title-addon', $summary['citationSort'][1]['variable'] ?? null);
        $t->same('volume-title-addon', $citationChildren[1]['variable'] ?? null);
        $t->same('part-title-addon', $citationChildren[2]['variable'] ?? null);
        $t->same('volume-title-addon', $bibliographyRendering[2]['variable'] ?? null);
        $t->same('part-title-addon', $bibliographyRendering[4]['variable'] ?? null);
        $t->same('[Hyphen Source Packet | hyphen volume note | hyphen part note; Bounded Source Packet | legacy volume note | legacy part note]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'volume-part-addendum', 'text' => '[@volume-part-addendum]']),
            new AstNode('citation', ['id' => 'hyphen-volume-part-addendum', 'text' => '[@hyphen-volume-part-addendum]']),
        ]));
        $t->same(
            'Bounded Source Packet :: Archive Volume: Catalog Supplement :: legacy volume note :: Evidence Part: Audit Sheet :: legacy part note',
            $styled->renderBibliographyEntry('volume-part-addendum')
        );

        $direct = CitationCslProcessor::fromItems([[
            'id' => 'direct-volume-part-addendum',
            'title' => 'Direct Volume Part Packet',
            'volumeTitle' => 'Direct Volume',
            'volumeTitleAddon' => 'direct volume note',
            'partTitle' => 'Direct Part',
            'partTitleAddon' => 'direct part note',
        ]]);
        $directItem = $direct->item('direct-volume-part-addendum');
        $t->same('direct volume note', $directItem['volumeTitleAddon'] ?? null);
        $t->same('direct part note', $directItem['partTitleAddon'] ?? null);
        $t->same(
            'Direct Volume Part Packet. Volume title: Direct Volume. Volume title addendum: direct volume note. Part title: Direct Part. Part title addendum: direct part note.',
            $direct->renderBibliographyEntry('direct-volume-part-addendum')
        );

        $document = (new MarkdownReader())->read('Volume addenda cite @volume-part-addendum.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Volume addenda cite Ng (2026).</p>', $blocks);
        $t->contains('Volume title addendum: legacy volume note.', $blocks);
        $t->contains('Part title addendum: legacy part note.', $blocks);
    },
];
