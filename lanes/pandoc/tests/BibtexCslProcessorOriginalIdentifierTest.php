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

    'carries biblatex original link identifier metadata through csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{original-link-identifiers,
  author   = {Ishikawa, Emi},
  title    = {Digital Facsimile Guide},
  origtitle = {Source Facsimile Guide},
  origdoi  = {10.5555/orig.2001},
  origurl  = {https://archive.example.test/source/facsimile},
  date     = {2026}
}

@book{original-hyphen-link-identifiers,
  author        = {Stone, Lee},
  title         = {Hyphen Link Packet},
  original-title = {Source Link Packet},
  original-doi  = {10.7777/packet.1999},
  original-url  = {https://archive.example.test/source/packet},
  date          = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $manual = $items['original-link-identifiers'];
        $hyphen = $items['original-hyphen-link-identifiers'];

        $t->same('10.5555/orig.2001', $manual['original-doi']);
        $t->same('https://archive.example.test/source/facsimile', $manual['original-url']);
        $t->same('10.5555/orig.2001', $manual['rawBibtex']['fields']['origdoi']);
        $t->same('https://archive.example.test/source/facsimile', $manual['rawBibtex']['fields']['origurl']);
        $t->same('10.7777/packet.1999', $hyphen['original-doi']);
        $t->same('https://archive.example.test/source/packet', $hyphen['original-url']);
        $t->same('10.7777/packet.1999', $hyphen['rawBibtex']['fields']['original-doi']);
        $t->contains('Original DOI: 10.5555/orig.2001.', $processor->renderBibliographyText($manual));
        $t->contains('Original URL: https://archive.example.test/source/facsimile.', $processor->renderBibliographyText($manual));

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('10.5555/orig.2001', $parserItems[0]['original-doi'] ?? null);
        $t->same('https://archive.example.test/source/facsimile', $parserItems[0]['original-url'] ?? null);
        $t->same('10.7777/packet.1999', $parserItems[1]['original-doi'] ?? null);
        $t->same('https://archive.example.test/source/packet', $parserItems[1]['original-url'] ?? null);

        $core = CitationCslProcessor::fromBibtex($biblatex);
        $coreManual = $core->item('original-link-identifiers');
        $t->same('10.5555/orig.2001', $coreManual['originalDoi'] ?? null);
        $t->same('https://archive.example.test/source/facsimile', $coreManual['originalUrl'] ?? null);
        $t->contains('Original DOI: 10.5555/orig.2001.', $core->renderBibliographyEntry('original-link-identifiers'));
        $t->contains('Original URL: https://archive.example.test/source/facsimile.', $core->renderBibliographyEntry('original-link-identifiers'));

        $directProcessor = CitationCslProcessor::fromItems([[
            'id' => 'direct-original-link-identifiers',
            'title' => 'Direct Original Link Identifiers',
            'origDOI' => '10.8888/direct.2004',
            'original-URL' => 'https://archive.example.test/source/direct',
        ]]);
        $direct = $directProcessor->item('direct-original-link-identifiers');
        $t->same('10.8888/direct.2004', $direct['originalDoi'] ?? null);
        $t->same('https://archive.example.test/source/direct', $direct['originalUrl'] ?? null);
        $t->contains('Original DOI: 10.8888/direct.2004.', $directProcessor->renderBibliographyEntry('direct-original-link-identifiers'));
        $t->contains('Original URL: https://archive.example.test/source/direct.', $directProcessor->renderBibliographyEntry('direct-original-link-identifiers'));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Original Link Identifier Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-original-link-identifier-review</id>
    <updated>2026-07-01T19:10:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-doi"/>
        <text variable="original-url"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-doi"/>
      <text variable="original-url"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('original-hyphen-link-identifiers');
        $t->same('Bounded Legacy BibLaTeX Original Link Identifier Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('10.7777/packet.1999', $normalized['originalDoi'] ?? null);
        $t->same('https://archive.example.test/source/packet', $normalized['originalUrl'] ?? null);
        $t->same('[Ishikawa | 10.5555/orig.2001 | https://archive.example.test/source/facsimile; Stone | 10.7777/packet.1999 | https://archive.example.test/source/packet]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'original-link-identifiers', 'text' => '[@original-link-identifiers]']),
            new AstNode('citation', ['id' => 'original-hyphen-link-identifiers', 'text' => '[@original-hyphen-link-identifiers]']),
        ]));
        $t->same('Digital Facsimile Guide :: 10.5555/orig.2001 :: https://archive.example.test/source/facsimile', $styled->renderBibliographyEntry('original-link-identifiers'));
        $t->same('Hyphen Link Packet :: 10.7777/packet.1999 :: https://archive.example.test/source/packet', $styled->renderBibliographyEntry('original-hyphen-link-identifiers'));

        $document = (new MarkdownReader())->read('Original link identifiers cite @original-link-identifiers and [@original-hyphen-link-identifiers].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['original-link-identifiers', 'original-hyphen-link-identifiers'], $handoff['citedKeys']);
        $t->same('10.5555/orig.2001', $handoff['items'][0]['original-doi']);
        $t->same('https://archive.example.test/source/packet', $handoff['bibliography']->children[1]->attr('cslItem')['original-url'] ?? null);
        $t->contains('Original DOI: 10.5555/orig.2001.', $blocks);
        $t->contains('Original URL: https://archive.example.test/source/packet.', $blocks);
    },
];
