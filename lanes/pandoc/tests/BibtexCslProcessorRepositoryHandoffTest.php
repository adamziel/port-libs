<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslParser;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$citation = static function (string $id): AstNode {
    return new AstNode('citation', [
        'id' => $id,
        'text' => '[@' . $id . ']',
    ], [
        new AstNode('text', ['text' => '[@' . $id . ']']),
    ]);
};

return [
    'records mapped legacy biblatex repository handoff case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedLegacyBiblatexRepositoryHandoffCases'] ?? null);
        $t->same(36, $manifest['legacyBiblatexRepositoryHandoffAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedLegacyBiblatexRepositoryHandoffCases'] ?? null);
        $t->same(36, $manifest['benchmarkDenominator']['breakdown']['legacyBiblatexRepositoryHandoffAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedLegacyBiblatexRepositoryHandoffCases'] ?? null);
        $t->same(36, $manifest['benchmarkDenominator']['inventory']['legacyBiblatexRepositoryHandoffAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedLegacyBiblatexRepositoryHandoffCases'] ?? null);
        $t->same(36, $manifest['inventory']['legacyBiblatexRepositoryHandoffAssertions'] ?? null);
    },

    'carries biblatex repository metadata through legacy csl handoff' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@misc{repository-ledger,
  author            = {Smith, Ada},
  title             = {Repository Ledger Packet},
  date              = {2026},
  repository        = {City Records Office},
  archive           = {City Archive},
  archivecollection = {Migration Papers},
  archive_location  = {Box 9},
  callnumber        = {MS 12}
}

@misc{depository-ledger,
  author           = {{Repository Desk}},
  title            = {Depository Ledger Packet},
  date             = {2025},
  depository       = {State Digital Depository},
  archive          = {State Archive},
  archive-location = {Folder 4}
}
BIB;

        $parserItems = BibtexCslParser::parse($bibtex);
        $legacy = new BibtexCslProcessor();
        $legacyItems = $legacy->cslItems($bibtex);

        $t->same(2, count($parserItems));
        $t->same('City Records Office', $parserItems[0]['repository'] ?? null);
        $t->same('State Digital Depository', $parserItems[1]['repository'] ?? null);
        $t->same('City Records Office', $parserItems[0]['rawBibtex']['fields']['repository'] ?? null);
        $t->same('State Digital Depository', $parserItems[1]['rawBibtex']['fields']['depository'] ?? null);
        $t->same('City Records Office', $legacyItems['repository-ledger']['repository'] ?? null);
        $t->same('State Digital Depository', $legacyItems['depository-ledger']['repository'] ?? null);
        $t->contains('Repository: City Records Office.', $legacy->renderBibliographyText($legacyItems['repository-ledger']));
        $t->contains('Repository: State Digital Depository.', $legacy->renderBibliographyText($legacyItems['depository-ledger']));

        $directParserItems = CitationCslProcessor::bibtexItems($bibtex);
        $core = CitationCslProcessor::fromBibtex($bibtex);
        $repository = $core->item('repository-ledger');
        $depository = $core->item('depository-ledger');

        $t->same('City Records Office', $directParserItems[0]['repository'] ?? null);
        $t->same('State Digital Depository', $directParserItems[1]['repository'] ?? null);
        $t->same('City Records Office', $repository['repository'] ?? null);
        $t->same('State Digital Depository', $depository['repository'] ?? null);
        $t->contains('Repository: City Records Office.', $core->renderBibliographyEntry('repository-ledger'));
        $t->contains('Repository: State Digital Depository.', $core->renderBibliographyEntry('depository-ledger'));

        $styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded BibLaTeX Repository Handoff Review</title>
    <id>https://example.test/styles/bounded-biblatex-repository-handoff-review</id>
    <updated>2026-07-02T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="repository"/>
        <text variable="depository"/>
        <text variable="archive-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="repository"/>
      <text variable="depository"/>
      <text variable="archive-summary"/>
    </layout>
  </bibliography>
</style>
XML;
        $styled = $core->withCslStyle($styleXml);
        $summary = $styled->cslStyleSummary();

        $t->same('Bounded BibLaTeX Repository Handoff Review', $summary['title'] ?? null);
        $t->same('repository', $summary['citationRendering'][0]['children'][1]['variable'] ?? null);
        $t->same('depository', $summary['citationRendering'][0]['children'][2]['variable'] ?? null);
        $t->same('repository', $summary['bibliographyRendering'][1]['variable'] ?? null);
        $t->same('[Smith | City Records Office | City Records Office | City Archive:Migration Papers:Box 9; Repository Desk | State Digital Depository | State Digital Depository | State Archive:Folder 4]', $styled->renderCitationCluster([
            $citation('repository-ledger'),
            $citation('depository-ledger'),
        ]));
        $t->same('Repository Ledger Packet :: City Records Office :: City Records Office :: City Archive:Migration Papers:Box 9', $styled->renderBibliographyEntry('repository-ledger'));
        $t->same('Depository Ledger Packet :: State Digital Depository :: State Digital Depository :: State Archive:Folder 4', $styled->renderBibliographyEntry('depository-ledger'));

        $direct = CitationCslProcessor::fromItems([
            [
                'id' => 'direct-repository-name',
                'type' => 'manuscript',
                'title' => 'Direct Repository Name Packet',
                'author' => [['family' => 'Ng', 'given' => 'Nia']],
                'issued' => ['date-parts' => [[2024]]],
                'repositoryName' => 'Migration Repository Desk',
            ],
            [
                'id' => 'direct-holding-institution',
                'type' => 'manuscript',
                'title' => 'Direct Holding Institution Packet',
                'author' => [['family' => 'Roe', 'given' => 'Pat']],
                'issued' => ['date-parts' => [[2023]]],
                'holding-institution' => 'County Records Vault',
            ],
        ])->withCslStyle($styleXml);

        $t->same('Migration Repository Desk', $direct->item('direct-repository-name')['repository'] ?? null);
        $t->same('County Records Vault', $direct->item('direct-holding-institution')['repository'] ?? null);
        $t->contains('Repository: Migration Repository Desk.', CitationCslProcessor::fromItems([[
            'id' => 'direct-repository-name',
            'title' => 'Direct Repository Name Packet',
            'repositoryName' => 'Migration Repository Desk',
        ]])->renderBibliographyEntry('direct-repository-name'));
        $t->same('[Ng | Migration Repository Desk | Migration Repository Desk; Roe | County Records Vault | County Records Vault]', $direct->renderCitationCluster([
            $citation('direct-repository-name'),
            $citation('direct-holding-institution'),
        ]));
        $t->same('Direct Repository Name Packet :: Migration Repository Desk :: Migration Repository Desk', $direct->renderBibliographyEntry('direct-repository-name'));
        $t->same('Direct Holding Institution Packet :: County Records Vault :: County Records Vault', $direct->renderBibliographyEntry('direct-holding-institution'));

        $document = (new MarkdownReader())->read('Repository review cites @repository-ledger and [@depository-ledger].');
        $legacyHandoff = $legacy->citationHandoff($document, $bibtex);
        $legacyBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$legacyHandoff['bibliography']]));

        $t->same(['repository-ledger', 'depository-ledger'], $legacyHandoff['citedKeys']);
        $t->same('City Records Office', $legacyHandoff['items'][0]['repository'] ?? null);
        $t->same('State Digital Depository', $legacyHandoff['bibliography']->children[1]->attr('cslItem')['repository'] ?? null);
        $t->contains('Repository: City Records Office.', $legacyBlocks);
        $t->contains('Repository: State Digital Depository.', $legacyBlocks);

        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Repository review cites Smith (2026) and [Repository Desk | State Digital Depository | State Digital Depository | State Archive:Folder 4].</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Repository Ledger Packet :: City Records Office :: City Records Office :: City Archive:Migration Papers:Box 9</dd>', $blocks);
        $t->contains('<dt>Repository Desk 2025</dt><dd>Depository Ledger Packet :: State Digital Depository :: State Digital Depository :: State Archive:Folder 4</dd>', $blocks);
    },
];
