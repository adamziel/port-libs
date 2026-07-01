<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$citation = static function (string $id, string $text): AstNode {
    return new AstNode('citation', [
        'id' => $id,
        'text' => $text,
    ], [
        new AstNode('text', ['text' => $text]),
    ]);
};

return [
    'maps compact biblatex containerauthortype into csl metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@incollection{compact-container-type,
  author              = {Ng, Nia},
  bookauthor          = {{Source Volume Desk}},
  containerauthortype = {compact source volume author},
  title               = {Compact Container Type Chapter},
  booktitle           = {Migration Sourcebook},
  date                = {2026},
  pages               = {12--15}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(1, count($items));
        $t->same('compact source volume author', $items[0]['container-author-type'] ?? null);
        $t->same('compact source volume author', $items[0]['rawBibtex']['fields']['containerauthortype'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $item = $processor->item('compact-container-type');
        $t->same('compact source volume author', $item['containerAuthorType'] ?? null);
        $t->same('Source Volume Desk', $item['containerAuthors'][0]['literal'] ?? null);
        $t->same(
            'Ng, Nia. Compact Container Type Chapter. Migration Sourcebook. 2026. 12-15. Container author type: compact source volume author. Container author: Source Volume Desk.',
            $processor->renderBibliographyEntry('compact-container-type')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="container-author"/>
        <text variable="containerauthortype"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="containerauthortype"/>
      <names variable="container-author"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('[Source Volume Desk | compact source volume author]', $styled->renderCitationCluster([
            $citation('compact-container-type', '[@compact-container-type]'),
        ]));
        $t->same(
            'Compact Container Type Chapter :: compact source volume author :: Source Volume Desk',
            $styled->renderBibliographyEntry('compact-container-type')
        );

        $direct = CitationCslProcessor::fromItems([[
            'id' => 'direct-compact-container-type',
            'title' => 'Direct Compact Container Type',
            'containerauthortype' => 'direct compact source author',
        ]])->item('direct-compact-container-type');
        $t->same('direct compact source author', $direct['containerAuthorType'] ?? null);

        $document = (new MarkdownReader())->read('Compact author type [@compact-container-type] remains visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>Compact author type [Source Volume Desk | compact source volume author] remains visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Compact Container Type Chapter :: compact source volume author :: Source Volume Desk</dd>', $blocks);
    },
];
