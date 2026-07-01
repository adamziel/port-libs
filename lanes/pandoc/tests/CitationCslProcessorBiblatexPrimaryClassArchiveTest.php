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
    'records mapped legacy biblatex primaryclass archive case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedLegacyBiblatexPrimaryClassArchiveCases'] ?? null);
        $t->same(38, $manifest['legacyBiblatexPrimaryClassArchiveAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedLegacyBiblatexPrimaryClassArchiveCases'] ?? null);
        $t->same(38, $manifest['benchmarkDenominator']['breakdown']['legacyBiblatexPrimaryClassArchiveAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedLegacyBiblatexPrimaryClassArchiveCases'] ?? null);
        $t->same(38, $manifest['benchmarkDenominator']['inventory']['legacyBiblatexPrimaryClassArchiveAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedLegacyBiblatexPrimaryClassArchiveCases'] ?? null);
        $t->same(38, $manifest['inventory']['legacyBiblatexPrimaryClassArchiveAssertions'] ?? null);
    },

    'maps biblatex primaryclass archive aliases into csl review metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@online{primaryclass-source,
  author        = {Ng, Nia},
  title         = {Primary Class Archive Packet},
  year          = {2026},
  archiveprefix = {arXiv},
  eprint        = {2601.00001},
  primaryclass  = {cs.DL}
}

@online{primary-class-source,
  author        = {Roe, Pat},
  title         = {Hyphen Primary Class Packet},
  year          = {2025},
  eprinttype    = {arXiv},
  eprint        = {2601.00002},
  primary-class = {stat.ML}
}
BIB;
        $items = BibtexCslParser::parse($bibtex);
        $legacyItems = (new BibtexCslProcessor())->cslItems($bibtex);
        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $primary = $processor->item('primaryclass-source');
        $hyphen = $processor->item('primary-class-source');

        $t->same('cs.DL', $items[0]['archive-place'] ?? null);
        $t->same('arXiv:2601.00001 [cs.DL]', $items[0]['archive-summary'] ?? null);
        $t->same('stat.ML', $items[1]['archive-place'] ?? null);
        $t->same('arXiv:2601.00002 [stat.ML]', $items[1]['archive-summary'] ?? null);
        $t->same('cs.DL', $items[0]['rawBibtex']['fields']['primaryclass'] ?? null);
        $t->same('stat.ML', $items[1]['rawBibtex']['fields']['primary-class'] ?? null);
        $t->same('cs.DL', $legacyItems['primaryclass-source']['archive-place'] ?? null);
        $t->same('arXiv:cs.DL:2601.00001', $legacyItems['primaryclass-source']['archive-summary'] ?? null);
        $t->same('stat.ML', $legacyItems['primary-class-source']['archive-place'] ?? null);
        $t->same('cs.DL', $primary['archivePlace'] ?? null);
        $t->same('arXiv:2601.00001 [cs.DL]', $primary['archiveSummary'] ?? null);
        $t->same('stat.ML', $hyphen['archivePlace'] ?? null);
        $t->same('arXiv:2601.00002 [stat.ML]', $hyphen['archiveSummary'] ?? null);
        $t->same('Ng, Nia. Primary Class Archive Packet. 2026. Archive: arXiv cs.DL 2601.00001.', $processor->renderBibliographyEntry('primaryclass-source'));
        $t->same('Roe, Pat. Hyphen Primary Class Packet. 2025. Archive: arXiv stat.ML 2601.00002.', $processor->renderBibliographyEntry('primary-class-source'));

        $styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded BibLaTeX Primary Class Archive Review</title>
    <id>https://example.test/styles/bounded-biblatex-primary-class-archive-review</id>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="primaryclass"/>
        <text variable="archive-place"/>
        <text variable="archive-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="primary-class"/>
      <text variable="archive-summary"/>
    </layout>
  </bibliography>
</style>
XML
        ;
        $styled = $processor->withCslStyle($styleXml);
        $summary = $styled->cslStyleSummary();

        $t->same('Bounded BibLaTeX Primary Class Archive Review', $summary['title'] ?? null);
        $t->same('primaryclass', $summary['citationRendering'][0]['children'][1]['variable'] ?? null);
        $t->same('primary-class', $summary['bibliographyRendering'][1]['variable'] ?? null);
        $t->same('[Ng | cs.DL | cs.DL | arXiv:2601.00001 [cs.DL]; Roe | stat.ML | stat.ML | arXiv:2601.00002 [stat.ML]]', $styled->renderCitationCluster([
            $citation('primaryclass-source'),
            $citation('primary-class-source'),
        ]));
        $t->same('Primary Class Archive Packet :: cs.DL :: arXiv:2601.00001 [cs.DL]', $styled->renderBibliographyEntry('primaryclass-source'));
        $t->same('Hyphen Primary Class Packet :: stat.ML :: arXiv:2601.00002 [stat.ML]', $styled->renderBibliographyEntry('primary-class-source'));

        $direct = CitationCslProcessor::fromItems([
            [
                'id' => 'direct-primaryclass',
                'type' => 'article',
                'title' => 'Direct Primary Class Packet',
                'author' => [['family' => 'Ames', 'given' => 'Ara']],
                'issued' => ['date-parts' => [[2024]]],
                'archivePrefix' => 'arXiv',
                'eprint' => '2601.00003',
                'primaryClass' => 'math.AG',
            ],
            [
                'id' => 'direct-primary-class',
                'type' => 'article',
                'title' => 'Direct Hyphen Primary Class Packet',
                'author' => [['family' => 'Bell', 'given' => 'Bea']],
                'issued' => ['date-parts' => [[2023]]],
                'eprint-type' => 'arXiv',
                'eprint' => '2601.00004',
                'primary-class' => 'astro-ph.IM',
            ],
        ])->withCslStyle($styleXml);
        $directCamel = $direct->item('direct-primaryclass');
        $directHyphen = $direct->item('direct-primary-class');

        $t->same('math.AG', $directCamel['archivePlace'] ?? null);
        $t->same('arXiv:2601.00003 [math.AG]', $directCamel['archiveSummary'] ?? null);
        $t->same('astro-ph.IM', $directHyphen['archivePlace'] ?? null);
        $t->same('arXiv:2601.00004 [astro-ph.IM]', $directHyphen['archiveSummary'] ?? null);
        $t->same('[Ames | math.AG | math.AG | arXiv:2601.00003 [math.AG]; Bell | astro-ph.IM | astro-ph.IM | arXiv:2601.00004 [astro-ph.IM]]', $direct->renderCitationCluster([
            $citation('direct-primaryclass'),
            $citation('direct-primary-class'),
        ]));
        $t->same('Direct Primary Class Packet :: math.AG :: arXiv:2601.00003 [math.AG]', $direct->renderBibliographyEntry('direct-primaryclass'));

        $document = (new MarkdownReader())->read('Archive classes [@primaryclass-source; @primary-class-source] stay visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Archive classes [Ng | cs.DL | cs.DL | arXiv:2601.00001 [cs.DL]; Roe | stat.ML | stat.ML | arXiv:2601.00002 [stat.ML]] stay visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Primary Class Archive Packet :: cs.DL :: arXiv:2601.00001 [cs.DL]</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Hyphen Primary Class Packet :: stat.ML :: arXiv:2601.00002 [stat.ML]</dd>', $blocks);
    },
];
