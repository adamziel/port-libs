<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'records mapped legacy biblatex related option value case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedLegacyBiblatexRelatedOptionValueCases'] ?? null);
        $t->same(35, $manifest['legacyBiblatexRelatedOptionValueAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedLegacyBiblatexRelatedOptionValueCases'] ?? null);
        $t->same(35, $manifest['benchmarkDenominator']['breakdown']['legacyBiblatexRelatedOptionValueAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedLegacyBiblatexRelatedOptionValueCases'] ?? null);
        $t->same(35, $manifest['benchmarkDenominator']['inventory']['legacyBiblatexRelatedOptionValueAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedLegacyBiblatexRelatedOptionValueCases'] ?? null);
        $t->same(35, $manifest['inventory']['legacyBiblatexRelatedOptionValueAssertions'] ?? null);
    },

    'carries legacy biblatex related option values through csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@inproceedings{audit-paper,
  options = {dataonly; skipbib=true},
  author  = {Smith, Ada},
  title   = {Packet Audit Trails},
  date    = {2026}
}

@book{related-option-values,
  author         = {Curator, Eli},
  title          = {Related Option Values Manual},
  date           = {2024},
  options        = {skipbib=false; useprefix=true, maxnames=3},
  langidopts     = {variant=mexican; hyphenation=traditional},
  related        = {audit-paper, missing-related},
  relatedtype    = {companion},
  relatedoptions = {skipbib=false, dataonly=false; useeditor=true}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $item = $items['related-option-values'];
        $bibliography = $processor->renderBibliographyText($item);

        $t->same(['audit-paper', 'related-option-values'], array_keys($items));
        $t->same('skipbib=false, dataonly=false; useeditor=true', $item['related-options']);
        $t->same(['skipbib=false', 'dataonly=false', 'useeditor=true'], $item['relatedOptions']);
        $t->same(['skipbib=false', 'useprefix=true', 'maxnames=3'], $item['biblatex-options']);
        $t->same(['variant=mexican', 'hyphenation=traditional'], $item['biblatex-language-options']);
        $t->same(['dataonly', 'skipbib=true'], $items['audit-paper']['biblatex-options']);
        $t->same(['audit-paper', 'missing-related'], $item['related-keys']);
        $t->same(['missing-related'], $item['missing-related-keys']);
        $t->same('Related source (companion): Packet Audit Trails (2026); missing: missing-related', $item['relatedSummary']);
        $t->same('Packet Audit Trails', $item['relatedItems'][0]['title'] ?? null);
        $t->same([2026], $item['relatedItems'][0]['issued']['date-parts'][0] ?? null);
        $t->same('skipbib=false, dataonly=false; useeditor=true', $item['rawBibtex']['fields']['relatedoptions']);
        $t->same('skipbib=false; useprefix=true, maxnames=3', $item['rawBibtex']['fields']['options']);
        $t->contains('Related options: skipbib=false; dataonly=false; useeditor=true.', $bibliography);
        $t->contains('BibLaTeX options: skipbib=false; useprefix=true; maxnames=3.', $bibliography);
        $t->contains('BibLaTeX language options: variant=mexican; hyphenation=traditional.', $bibliography);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Related Option Value Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-related-option-value-review</id>
    <updated>2026-07-01T22:00:00+00:00</updated>
  </info>
  <citation>
    <layout>
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="related-options"/>
        <text variable="biblatex-options"/>
        <text variable="biblatex-language-option-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="related-options"/>
      <text variable="biblatex-options"/>
      <text variable="biblatex-language-option-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('related-option-values');
        $t->same('Bounded Legacy BibLaTeX Related Option Value Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same(['skipbib=false', 'dataonly=false', 'useeditor=true'], $normalized['relatedOptions'] ?? null);
        $t->same(['skipbib=false', 'useprefix=true', 'maxnames=3'], $normalized['biblatexOptions'] ?? null);
        $t->same('variant=mexican; hyphenation=traditional', $normalized['biblatexLanguageOptionSummary'] ?? null);
        $t->same(
            'Related Option Values Manual | skipbib=false, dataonly=false, useeditor=true | skipbib=false, useprefix=true, maxnames=3 | variant=mexican; hyphenation=traditional',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'related-option-values', 'text' => '[@related-option-values]']),
            ])
        );
        $t->same(
            'Related Option Values Manual :: skipbib=false, dataonly=false, useeditor=true :: skipbib=false, useprefix=true, maxnames=3 :: variant=mexican; hyphenation=traditional',
            $styled->renderBibliographyEntry('related-option-values')
        );

        $document = (new MarkdownReader())->read('Related option values @related-option-values remain reviewable.');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['related-option-values'], $handoff['citedKeys']);
        $t->same(['skipbib=false', 'dataonly=false', 'useeditor=true'], $handoff['items'][0]['relatedOptions']);
        $t->same(['skipbib=false', 'dataonly=false', 'useeditor=true'], $handoff['bibliography']->children[0]->attr('cslItem')['relatedOptions'] ?? null);
        $t->contains('Related option values Curator (2024) remain reviewable.', $blocks);
        $t->contains('Related Option Values Manual :: skipbib=false, dataonly=false, useeditor=true :: skipbib=false, useprefix=true, maxnames=3 :: variant=mexican; hyphenation=traditional', $blocks);
    },
];
