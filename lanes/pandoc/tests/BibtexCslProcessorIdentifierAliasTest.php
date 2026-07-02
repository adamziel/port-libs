<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$citation = static function (string $id): AstNode {
    return new AstNode('citation', [
        'id' => $id,
        'text' => '[@' . $id . ']',
    ]);
};

return [
    'records mapped legacy biblatex primary identifier alias case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['legacyBiblatexPrimaryIdentifierAliasCases'] ?? null);
        $t->same(1, $manifest['mappedLegacyBiblatexPrimaryIdentifierAliasCases'] ?? null);
        $t->same(34, $manifest['legacyBiblatexPrimaryIdentifierAliasAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['legacyBiblatexPrimaryIdentifierAliasCases'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedLegacyBiblatexPrimaryIdentifierAliasCases'] ?? null);
        $t->same(34, $manifest['benchmarkDenominator']['breakdown']['legacyBiblatexPrimaryIdentifierAliasAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedLegacyBiblatexPrimaryIdentifierAliasCases'] ?? null);
        $t->same(34, $manifest['inventory']['legacyBiblatexPrimaryIdentifierAliasAssertions'] ?? null);
    },

    'maps legacy biblatex primary identifier aliases through direct csl handoff' => static function (TestRunner $t) use ($citation): void {
        $biblatex = <<<'BIB'
@book{legacy-isbn13,
  author = {Ng, Nia},
  title  = {Legacy ISBN13 Manual},
  date   = {2026},
  isbn13 = {978-1-4028-9462-6}
}

@book{legacy-eisbn,
  author = {Roe, Pat},
  title  = {Legacy Electronic ISBN Manual},
  date   = {2025},
  eISBN  = {978-0-596-52068-7}
}

@article{legacy-print-issn,
  author       = {Doe, Jane},
  title        = {Legacy Print ISSN Packet},
  journaltitle = {Journal of Direct Imports},
  date         = {2024},
  printISSN    = {1234-5678}
}

@article{legacy-eissn,
  author       = {Ames, Ara},
  title        = {Legacy Electronic ISSN Packet},
  journaltitle = {Online Direct Imports},
  date         = {2023},
  eISSN        = {2468-1357}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $isbn13 = $items['legacy-isbn13'];
        $eisbn = $items['legacy-eisbn'];
        $printIssn = $items['legacy-print-issn'];
        $eissn = $items['legacy-eissn'];

        $t->same(['legacy-isbn13', 'legacy-eisbn', 'legacy-print-issn', 'legacy-eissn'], array_keys($items));
        $t->same('978-1-4028-9462-6', $isbn13['ISBN'] ?? null);
        $t->same('978-1-4028-9462-6', $isbn13['rawBibtex']['fields']['isbn13'] ?? null);
        $t->same('978-0-596-52068-7', $eisbn['ISBN'] ?? null);
        $t->same('978-0-596-52068-7', $eisbn['rawBibtex']['fields']['eisbn'] ?? null);
        $t->same('1234-5678', $printIssn['ISSN'] ?? null);
        $t->same('1234-5678', $printIssn['rawBibtex']['fields']['printissn'] ?? null);
        $t->same('2468-1357', $eissn['ISSN'] ?? null);
        $t->same('2468-1357', $eissn['rawBibtex']['fields']['eissn'] ?? null);

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('978-1-4028-9462-6', $parserItems[0]['ISBN'] ?? null);
        $t->same('978-0-596-52068-7', $parserItems[1]['ISBN'] ?? null);
        $t->same('1234-5678', $parserItems[2]['ISSN'] ?? null);
        $t->same('2468-1357', $parserItems[3]['ISSN'] ?? null);

        $core = CitationCslProcessor::fromItems(array_values($items));
        $coreIsbn13 = $core->item('legacy-isbn13');
        $coreEissn = $core->item('legacy-eissn');
        $t->same('978-1-4028-9462-6', $coreIsbn13['isbn'] ?? null);
        $t->same('2468-1357', $coreEissn['issn'] ?? null);
        $t->same('Ng, Nia. Legacy ISBN13 Manual. 2026. ISBN 978-1-4028-9462-6.', $core->renderBibliographyEntry('legacy-isbn13'));
        $t->same('Ames, Ara. Legacy Electronic ISSN Packet. Online Direct Imports. 2023. ISSN 2468-1357.', $core->renderBibliographyEntry('legacy-eissn'));

        $styled = $core->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Primary Identifier Alias Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-primary-identifier-alias-review</id>
    <updated>2026-07-02T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="ISBN-13"/>
        <text variable="printISSN"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="eISBN"/>
      <text variable="eISSN"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('Bounded Legacy BibLaTeX Primary Identifier Alias Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Ng | 978-1-4028-9462-6; Roe | 978-0-596-52068-7; Doe | 1234-5678; Ames | 2468-1357]', $styled->renderCitationCluster([
            $citation('legacy-isbn13'),
            $citation('legacy-eisbn'),
            $citation('legacy-print-issn'),
            $citation('legacy-eissn'),
        ]));
        $t->same('Legacy ISBN13 Manual :: 978-1-4028-9462-6', $styled->renderBibliographyEntry('legacy-isbn13'));
        $t->same('Legacy Electronic ISSN Packet :: 2468-1357', $styled->renderBibliographyEntry('legacy-eissn'));

        $document = (new MarkdownReader())->read('Identifier aliases cite @legacy-isbn13 and [@legacy-eissn].');
        $blocks = (new WordPressBlockWriter())->write($core->appendBibliography($document, 'Works Cited'));
        $handoff = $processor->citationHandoff($document, $biblatex);

        $t->contains('<dt>Ng 2026</dt><dd>Ng, Nia. Legacy ISBN13 Manual. 2026. ISBN 978-1-4028-9462-6.</dd>', $blocks);
        $t->contains('<dt>Ames 2023</dt><dd>Ames, Ara. Legacy Electronic ISSN Packet. Online Direct Imports. 2023. ISSN 2468-1357.</dd>', $blocks);
        $t->same(['legacy-isbn13', 'legacy-eissn'], $handoff['citedKeys']);
        $t->same('978-1-4028-9462-6', $handoff['items'][0]['ISBN'] ?? null);
        $t->same('2468-1357', $handoff['bibliography']->children[1]->attr('cslItem')['ISSN'] ?? null);
    },
];
