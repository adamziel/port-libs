<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'normalizes direct and bibtex subdivision spelling aliases into csl division metadata' => static function (TestRunner $t): void {
        $directJson = json_encode([
            [
                'id' => 'direct-subdivision-camel',
                'type' => 'report',
                'title' => 'Direct Camel Subdivision Packet',
                'author' => [['literal' => 'Camel Desk']],
                'issued' => ['date-parts' => [[2026]]],
                'subDivision' => 'Camel Migration Unit',
            ],
            [
                'id' => 'direct-subdivision-hyphen',
                'type' => 'report',
                'title' => 'Direct Hyphen Subdivision Packet',
                'author' => [['literal' => 'Hyphen Desk']],
                'issued' => ['date-parts' => [[2025]]],
                'sub-division' => 'Hyphen Migration Unit',
            ],
            [
                'id' => 'direct-subdivision-underscore',
                'type' => 'report',
                'title' => 'Direct Underscore Subdivision Packet',
                'author' => [['literal' => 'Underscore Desk']],
                'issued' => ['date-parts' => [[2024]]],
                'sub_division' => 'Underscore Migration Unit',
            ],
        ], JSON_THROW_ON_ERROR);

        $direct = CitationCslProcessor::fromJson($directJson);
        $camel = $direct->item('direct-subdivision-camel');
        $hyphen = $direct->item('direct-subdivision-hyphen');
        $underscore = $direct->item('direct-subdivision-underscore');

        $t->same('Camel Migration Unit', $camel['division'] ?? null);
        $t->same('Hyphen Migration Unit', $hyphen['division'] ?? null);
        $t->same('Underscore Migration Unit', $underscore['division'] ?? null);
        $t->same('Camel Migration Unit', $camel['raw']['subDivision'] ?? null);
        $t->same('Hyphen Migration Unit', $hyphen['raw']['sub-division'] ?? null);
        $t->same('Underscore Migration Unit', $underscore['raw']['sub_division'] ?? null);
        $t->contains('Division: Camel Migration Unit.', $direct->renderBibliographyEntry('direct-subdivision-camel'));
        $t->contains('Division: Hyphen Migration Unit.', $direct->renderBibliographyEntry('direct-subdivision-hyphen'));
        $t->contains('Division: Underscore Migration Unit.', $direct->renderBibliographyEntry('direct-subdivision-underscore'));

        $styled = $direct->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="subDivision"/>
        <text variable="sub-division"/>
        <text variable="sub_division"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="subDivision"/>
      <text variable="sub-division"/>
      <text variable="sub_division"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('[Camel Desk | Camel Migration Unit | Camel Migration Unit | Camel Migration Unit; Hyphen Desk | Hyphen Migration Unit | Hyphen Migration Unit | Hyphen Migration Unit; Underscore Desk | Underscore Migration Unit | Underscore Migration Unit | Underscore Migration Unit]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'direct-subdivision-camel', 'text' => '[@direct-subdivision-camel]']),
            new AstNode('citation', ['id' => 'direct-subdivision-hyphen', 'text' => '[@direct-subdivision-hyphen]']),
            new AstNode('citation', ['id' => 'direct-subdivision-underscore', 'text' => '[@direct-subdivision-underscore]']),
        ]));
        $t->same('Direct Camel Subdivision Packet :: Camel Migration Unit :: Camel Migration Unit :: Camel Migration Unit', $styled->renderBibliographyEntry('direct-subdivision-camel'));

        $bibtex = <<<'BIB'
@report{bibtex-hyphen-subdivision,
  author       = {Ng, Nia},
  title        = {BibTeX Hyphen Subdivision Packet},
  date         = {2026},
  sub-division = {BibTeX Hyphen Unit}
}

@report{bibtex-underscore-subdivision,
  author       = {Roe, Pat},
  title        = {BibTeX Underscore Subdivision Packet},
  date         = {2025},
  sub_division = {BibTeX Underscore Unit}
}
BIB;

        $bibtexProcessor = new BibtexCslProcessor();
        $items = $bibtexProcessor->cslItems($bibtex);
        $t->same('BibTeX Hyphen Unit', $items['bibtex-hyphen-subdivision']['division'] ?? null);
        $t->same('BibTeX Underscore Unit', $items['bibtex-underscore-subdivision']['division'] ?? null);
        $t->same('BibTeX Hyphen Unit', $items['bibtex-hyphen-subdivision']['rawBibtex']['fields']['sub-division'] ?? null);
        $t->same('BibTeX Underscore Unit', $items['bibtex-underscore-subdivision']['rawBibtex']['fields']['sub_division'] ?? null);

        $bibtexStyled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="sub-division"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="sub_division"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('[Ng | BibTeX Hyphen Unit; Roe | BibTeX Underscore Unit]', $bibtexStyled->renderCitationCluster([
            new AstNode('citation', ['id' => 'bibtex-hyphen-subdivision', 'text' => '[@bibtex-hyphen-subdivision]']),
            new AstNode('citation', ['id' => 'bibtex-underscore-subdivision', 'text' => '[@bibtex-underscore-subdivision]']),
        ]));
        $t->same('BibTeX Underscore Subdivision Packet :: BibTeX Underscore Unit', $bibtexStyled->renderBibliographyEntry('bibtex-underscore-subdivision'));

        $document = (new MarkdownReader())->read('Subdivision aliases cite @bibtex-hyphen-subdivision and [@bibtex-underscore-subdivision].');
        $handoff = $bibtexProcessor->citationHandoff($document, $bibtex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['bibtex-hyphen-subdivision', 'bibtex-underscore-subdivision'], $handoff['citedKeys']);
        $t->same('BibTeX Hyphen Unit', $handoff['items'][0]['division'] ?? null);
        $t->same('BibTeX Underscore Unit', $handoff['items'][1]['division'] ?? null);
        $t->contains('Division: BibTeX Hyphen Unit.', $blocks);
        $t->contains('Division: BibTeX Underscore Unit.', $blocks);
    },
];
