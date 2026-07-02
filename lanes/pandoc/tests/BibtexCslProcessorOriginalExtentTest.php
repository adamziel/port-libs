<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex original extent metadata through csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@article{original-extents,
  author          = {Ng, Nia},
  title           = {Current Review},
  origtitle       = {Source Article},
  origvolume      = {7},
  orignumber      = {2},
  origpages       = {55--68},
  origpagination  = {column},
  origpagetotal   = {14},
  date            = {2026}
}

@book{original-hyphen-extents,
  author                     = {Roe, Pat},
  title                      = {Current Book},
  original-title             = {Source Book},
  original-volume            = {3},
  original-number-of-volumes = {4},
  original-number            = {1},
  original-page              = {101--140},
  original-number-of-pages   = {40},
  date                       = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $article = $items['original-extents'];
        $book = $items['original-hyphen-extents'];

        $t->same('7', $article['original-volume']);
        $t->same('2', $article['original-number']);
        $t->same('55-68', $article['original-page']);
        $t->same('column', $article['original-pagination']);
        $t->same('14', $article['original-number-of-pages']);
        $t->same('55--68', $article['rawBibtex']['fields']['origpages']);
        $t->same('4', $book['original-number-of-volumes']);
        $t->same('101-140', $book['original-page']);
        $t->contains('Original volume: 7.', $processor->renderBibliographyText($article));
        $t->contains('Original pages: 55-68.', $processor->renderBibliographyText($article));
        $t->contains('Original number of pages: 14.', $processor->renderBibliographyText($article));

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('55-68', $parserItems[0]['original-page'] ?? null);
        $t->same('2', $parserItems[0]['original-number'] ?? null);
        $t->same('4', $parserItems[1]['original-number-of-volumes'] ?? null);

        $core = CitationCslProcessor::fromBibtex($biblatex);
        $coreArticle = $core->item('original-extents');
        $t->same('7', $coreArticle['originalVolume'] ?? null);
        $t->same('55-68', $coreArticle['originalPage'] ?? null);
        $t->same('14', $coreArticle['originalNumberOfPages'] ?? null);
        $t->contains('Original pages: 55-68.', $core->renderBibliographyEntry('original-extents'));
        $t->contains('Original number of volumes: 4.', $core->renderBibliographyEntry('original-hyphen-extents'));

        $directProcessor = CitationCslProcessor::fromItems([[
            'id' => 'direct-original-extents',
            'title' => 'Direct Original Extents',
            'originalVolume' => '9',
            'originalPage' => '200--210',
            'originalNumberOfPages' => '11',
        ]]);
        $direct = $directProcessor->item('direct-original-extents');
        $t->same('200-210', $direct['originalPage'] ?? null);
        $t->contains('Original pages: 200-210.', $directProcessor->renderBibliographyEntry('direct-original-extents'));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Original Extent Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-original-extent-review</id>
    <updated>2026-07-02T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="origvolume"/>
        <text variable="orignumber"/>
        <text variable="origpages"/>
        <text variable="origpagetotal"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <number variable="original-volume"/>
      <number variable="original-number-of-volumes"/>
      <text variable="original-page"/>
      <number variable="original-number-of-pages"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Bounded Legacy BibLaTeX Original Extent Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('14', $styled->item('original-extents')['originalNumberOfPages'] ?? null);
        $t->same('[Ng | 7 | 2 | 55-68 | 14; Roe | 3 | 1 | 101-140 | 40]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'original-extents', 'text' => '[@original-extents]']),
            new AstNode('citation', ['id' => 'original-hyphen-extents', 'text' => '[@original-hyphen-extents]']),
        ]));
        $t->same('Current Book :: 3 :: 4 :: 101-140 :: 40', $styled->renderBibliographyEntry('original-hyphen-extents'));

        $document = (new MarkdownReader())->read('Original extents cite @original-extents and [@original-hyphen-extents].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['original-extents', 'original-hyphen-extents'], $handoff['citedKeys']);
        $t->same('55-68', $handoff['items'][0]['original-page']);
        $t->same('40', $handoff['bibliography']->children[1]->attr('cslItem')['original-number-of-pages'] ?? null);
        $t->contains('Original pages: 55-68.', $blocks);
        $t->contains('Original number of volumes: 4.', $blocks);
    },
];
