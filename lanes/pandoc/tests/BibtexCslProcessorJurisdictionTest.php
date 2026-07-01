<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex jurisdiction metadata through legacy csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@patent{legacy-patent,
  author   = {Muller, Mia},
  holder   = {{WordPress Foundation}},
  title    = {Legacy Import Patent},
  number   = {US-123456},
  type     = {patentus},
  location = {US},
  date     = {2026}
}

@legislation{legacy-statute,
  title     = {Legacy Import Review Act},
  number    = {HB 42},
  authority = {Assembly, Migration},
  location  = {Oregon},
  date      = {2025}
}

@jurisdiction{legacy-case,
  title        = {Import Queue v. Source Packet},
  number       = {No. 24-100},
  authority    = {Court, Migration Review},
  jurisdiction = {9th Cir.},
  date         = {2024}
}

@report{location-report,
  author   = {Roe, Pat},
  title    = {Ordinary Location Report},
  location = {Portland},
  date     = {2023}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);

        $t->same('patent', $items['legacy-patent']['type']);
        $t->same('US', $items['legacy-patent']['jurisdiction']);
        $t->same('US', $items['legacy-patent']['publisher-place']);
        $t->same('legislation', $items['legacy-statute']['type']);
        $t->same('Oregon', $items['legacy-statute']['jurisdiction']);
        $t->same('legal_case', $items['legacy-case']['type']);
        $t->same('9th Cir.', $items['legacy-case']['jurisdiction']);
        $t->same(false, array_key_exists('jurisdiction', $items['location-report']));
        $t->contains('Jurisdiction: US.', $processor->renderBibliographyText($items['legacy-patent']));
        $t->contains('Jurisdiction: Oregon.', $processor->renderBibliographyText($items['legacy-statute']));
        $t->contains('Jurisdiction: 9th Cir.', $processor->renderBibliographyText($items['legacy-case']));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Jurisdiction Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-jurisdiction-review</id>
    <updated>2026-07-01T19:05:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="jurisdiction"/>
        <text variable="publisher-place"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="jurisdiction"/>
      <text variable="publisher-place"/>
      <names variable="authority"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('US', $styled->item('legacy-patent')['jurisdiction'] ?? null);
        $t->same('Oregon', $styled->item('legacy-statute')['jurisdiction'] ?? null);
        $t->same('9th Cir.', $styled->item('legacy-case')['jurisdiction'] ?? null);
        $t->same('Legacy Import Patent :: US :: US', $styled->renderBibliographyEntry('legacy-patent'));
        $t->same('Legacy Import Review Act :: Oregon :: Oregon :: Assembly, Migration', $styled->renderBibliographyEntry('legacy-statute'));
        $t->same('Import Queue v. Source Packet :: 9th Cir. :: Court, Migration Review', $styled->renderBibliographyEntry('legacy-case'));

        $document = (new MarkdownReader())->read('Legal import cites @legacy-patent and [@legacy-statute; @legacy-case].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['legacy-patent', 'legacy-statute', 'legacy-case'], $handoff['citedKeys']);
        $t->same('US', $handoff['items'][0]['jurisdiction']);
        $t->same('Oregon', $handoff['items'][1]['jurisdiction']);
        $t->same('9th Cir.', $handoff['bibliography']->children[2]->attr('cslItem')['jurisdiction'] ?? null);
        $t->contains('Jurisdiction: US.', $blocks);
        $t->contains('Jurisdiction: Oregon.', $blocks);
        $t->contains('Jurisdiction: 9th Cir.', $blocks);
    },
];
