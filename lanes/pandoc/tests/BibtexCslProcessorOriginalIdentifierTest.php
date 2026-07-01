<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex original identifier metadata through legacy csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{original-identifiers,
  author   = {Ng, Nia},
  title    = {Archive Identifier Manual},
  origtitle = {Manual Fuente},
  origisbn  = {978-1-4028-9462-6},
  origissn  = {2049-3630},
  date     = {2026}
}

@book{original-hyphen-identifiers,
  author        = {Roe, Pat},
  title         = {Hyphen Identifier Packet},
  original-title = {Source Identifier Packet},
  original-isbn  = {978-0-321-14653-0},
  original-issn  = {1234-567X},
  date          = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $manual = $items['original-identifiers'];
        $hyphen = $items['original-hyphen-identifiers'];

        $t->same('Manual Fuente', $manual['original-title']);
        $t->same('978-1-4028-9462-6', $manual['original-isbn']);
        $t->same('2049-3630', $manual['original-issn']);
        $t->same('978-1-4028-9462-6', $manual['rawBibtex']['fields']['origisbn']);
        $t->same('2049-3630', $manual['rawBibtex']['fields']['origissn']);
        $t->same('978-0-321-14653-0', $hyphen['original-isbn']);
        $t->same('1234-567X', $hyphen['original-issn']);
        $t->same('978-0-321-14653-0', $hyphen['rawBibtex']['fields']['original-isbn']);
        $t->contains('Original ISBN: 978-1-4028-9462-6.', $processor->renderBibliographyText($manual));
        $t->contains('Original ISSN: 2049-3630.', $processor->renderBibliographyText($manual));

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('978-1-4028-9462-6', $parserItems[0]['original-isbn'] ?? null);
        $t->same('2049-3630', $parserItems[0]['original-issn'] ?? null);
        $t->same('978-0-321-14653-0', $parserItems[1]['original-isbn'] ?? null);
        $t->same('1234-567X', $parserItems[1]['original-issn'] ?? null);

        $core = CitationCslProcessor::fromBibtex($biblatex);
        $coreManual = $core->item('original-identifiers');
        $t->same('978-1-4028-9462-6', $coreManual['originalIsbn'] ?? null);
        $t->same('2049-3630', $coreManual['originalIssn'] ?? null);
        $t->contains('Original ISBN: 978-1-4028-9462-6.', $core->renderBibliographyEntry('original-identifiers'));
        $t->contains('Original ISSN: 2049-3630.', $core->renderBibliographyEntry('original-identifiers'));

        $directProcessor = CitationCslProcessor::fromItems([[
            'id' => 'direct-original-identifiers',
            'title' => 'Direct Original Identifiers',
            'origISBN' => '978-1-1188-2222-3',
            'original-ISSN' => '1357-2468',
        ]]);
        $direct = $directProcessor->item('direct-original-identifiers');
        $t->same('978-1-1188-2222-3', $direct['originalIsbn'] ?? null);
        $t->same('1357-2468', $direct['originalIssn'] ?? null);
        $t->contains('Original ISBN: 978-1-1188-2222-3.', $directProcessor->renderBibliographyEntry('direct-original-identifiers'));
        $t->contains('Original ISSN: 1357-2468.', $directProcessor->renderBibliographyEntry('direct-original-identifiers'));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Original Identifier Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-original-identifier-review</id>
    <updated>2026-07-01T18:35:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-isbn"/>
        <text variable="original-issn"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-isbn"/>
      <text variable="original-issn"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('original-hyphen-identifiers');
        $t->same('Bounded Legacy BibLaTeX Original Identifier Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('978-0-321-14653-0', $normalized['originalIsbn'] ?? null);
        $t->same('1234-567X', $normalized['originalIssn'] ?? null);
        $t->same('[Ng | 978-1-4028-9462-6 | 2049-3630; Roe | 978-0-321-14653-0 | 1234-567X]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'original-identifiers', 'text' => '[@original-identifiers]']),
            new AstNode('citation', ['id' => 'original-hyphen-identifiers', 'text' => '[@original-hyphen-identifiers]']),
        ]));
        $t->same('Archive Identifier Manual :: 978-1-4028-9462-6 :: 2049-3630', $styled->renderBibliographyEntry('original-identifiers'));
        $t->same('Hyphen Identifier Packet :: 978-0-321-14653-0 :: 1234-567X', $styled->renderBibliographyEntry('original-hyphen-identifiers'));

        $document = (new MarkdownReader())->read('Original identifiers cite @original-identifiers and [@original-hyphen-identifiers].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['original-identifiers', 'original-hyphen-identifiers'], $handoff['citedKeys']);
        $t->same('978-1-4028-9462-6', $handoff['items'][0]['original-isbn']);
        $t->same('1234-567X', $handoff['bibliography']->children[1]->attr('cslItem')['original-issn'] ?? null);
        $t->contains('Original ISBN: 978-1-4028-9462-6.', $blocks);
        $t->contains('Original ISSN: 1234-567X.', $blocks);
    },
];
