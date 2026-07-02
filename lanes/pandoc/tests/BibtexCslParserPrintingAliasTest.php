<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex printing alias through direct csl parser handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{printing-alias-biblatex,
  author = {Smith, Ada},
  title = {Printing Alias Packet},
  date = {2026},
  printing = {2},
  supplementnumber = {1}
}

@report{print-number-biblatex,
  author = {Ng, Nia},
  title = {Print Number Packet},
  date = {2025},
  print-number = {4},
  supplement-number = {2}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($biblatex);
        $t->same(2, count($items));
        $t->same('2', $items[0]['printing-number'] ?? null);
        $t->same('1', $items[0]['supplement-number'] ?? null);
        $t->same('4', $items[1]['printing-number'] ?? null);
        $t->same('2', $items[1]['supplement-number'] ?? null);
        $t->same('2', $items[0]['rawBibtex']['fields']['printing'] ?? null);
        $t->same('4', $items[1]['rawBibtex']['fields']['print-number'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($biblatex)->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Direct BibLaTeX Printing Alias Review</title>
    <id>https://example.test/styles/bounded-direct-biblatex-printing-alias-review</id>
    <updated>2026-07-02T03:05:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <label variable="printing-number" form="short"/>
        <number variable="printing-number" form="ordinal"/>
        <label variable="supplement-number" form="short"/>
        <number variable="supplement-number" form="roman"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="printing-number" plural="contextual"/>
        <text variable="printing-number" form="long-ordinal"/>
      </group>
      <group delimiter=" ">
        <label variable="supplement-number" plural="contextual"/>
        <text variable="supplement-number" form="roman"/>
      </group>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $processor->cslStyleSummary();
        $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
        $bibliographyPrinting = $summary['bibliographyRendering'][1]['children'] ?? [];
        $t->same('Bounded Direct BibLaTeX Printing Alias Review', $summary['title'] ?? null);
        $t->same('printing-number', $citationChildren[1]['variable'] ?? null);
        $t->same('long-ordinal', $bibliographyPrinting[1]['form'] ?? null);

        $t->same('2', $processor->item('printing-alias-biblatex')['printingNumber'] ?? null);
        $t->same('1', $processor->item('printing-alias-biblatex')['supplementNumber'] ?? null);
        $t->same('4', $processor->item('print-number-biblatex')['printingNumber'] ?? null);
        $t->same('(Smith printing no. 2nd supp. no. i; Ng printing no. 4th supp. no. ii)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'printing-alias-biblatex', 'text' => '[@printing-alias-biblatex]']),
            new AstNode('citation', ['id' => 'print-number-biblatex', 'text' => '[@print-number-biblatex]']),
        ]));
        $t->same('Printing Alias Packet :: printing number second :: supplement number i', $processor->renderBibliographyEntry('printing-alias-biblatex'));
        $t->same('Print Number Packet :: printing number fourth :: supplement number ii', $processor->renderBibliographyEntry('print-number-biblatex'));

        $document = (new MarkdownReader())->read('BibLaTeX printing aliases [@printing-alias-biblatex; @print-number-biblatex] stay reviewable.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>BibLaTeX printing aliases (Smith printing no. 2nd supp. no. i; Ng printing no. 4th supp. no. ii) stay reviewable.</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Printing Alias Packet :: printing number second :: supplement number i</dd>', $blocks);
        $t->contains('<dt>Ng 2025</dt><dd>Print Number Packet :: printing number fourth :: supplement number ii</dd>', $blocks);
    },
];
