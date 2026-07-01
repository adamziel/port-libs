<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries singular original source metadata into direct bibliography text' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{source-edition,
  author        = {Garcia, Gia},
  title         = {Migration Manual Reissue},
  origtitle     = {Manual de Migracion},
  origsubtitle  = {Archivo Appendix},
  origdate      = {2020-05},
  origpublisher = {Archivo Press},
  origlocation  = {Madrid},
  origlanguage  = {spanish},
  publisher     = {Review Press},
  date          = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $item = $processor->cslItems($source)['source-edition'];
        $bibliography = $processor->renderBibliographyText($item);

        $t->same('Manual de Migracion: Archivo Appendix', $item['original-title'] ?? null);
        $t->same([2020, 5], $item['original-date']['date-parts'][0] ?? null);
        $t->same('Archivo Press', $item['original-publisher'] ?? null);
        $t->same('Madrid', $item['original-publisher-place'] ?? null);
        $t->same('spanish', $item['original-language'] ?? null);
        $t->contains('Original title: Manual de Migracion: Archivo Appendix', $bibliography);
        $t->contains('Original work published 2020-05', $bibliography);
        $t->contains('Original publisher: Archivo Press, Madrid', $bibliography);
        $t->contains('Original language: spanish', $bibliography);

        $styled = CitationCslProcessor::fromItems([$item])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="original-title"/>
        <text variable="original-publisher"/>
        <text variable="original-publisher-place"/>
        <text variable="original-language"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-title"/>
      <date variable="original-date"/>
      <text variable="origpublisher"/>
      <text variable="origlocation"/>
      <text variable="origlanguage"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledItem = $styled->item('source-edition');
        $t->same('Archivo Press', $styledItem['originalPublisher'] ?? null);
        $t->same('Madrid', $styledItem['originalPublisherPlace'] ?? null);
        $t->same('spanish', $styledItem['originalLanguage'] ?? null);
        $t->same('[Migration Manual Reissue | Manual de Migracion: Archivo Appendix | Archivo Press | Madrid | spanish]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'source-edition', 'text' => '[@source-edition]']),
        ]));
        $t->same('Migration Manual Reissue :: Manual de Migracion: Archivo Appendix :: 2020-05 :: Archivo Press :: Madrid :: spanish', $styled->renderBibliographyEntry('source-edition'));

        $document = (new MarkdownReader())->read('Original source metadata [@source-edition] survives bibliography handoff.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['source-edition'], $handoff['citedKeys']);
        $t->same('Archivo Press', $handoff['items'][0]['original-publisher'] ?? null);
        $t->contains('Original publisher: Archivo Press, Madrid', $blocks);
        $t->contains('Original language: spanish', $blocks);
    },
];
