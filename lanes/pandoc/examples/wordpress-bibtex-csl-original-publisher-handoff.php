<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Original Publisher Review

Original publisher handoff [@translated-archive] keeps source imprint metadata visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{translated-archive,
  author        = {Garcia, Gia},
  title         = {Migration Manual},
  date          = {2026},
  publisher     = {Review Press},
  origpublisher = {{Archivo Press} and {Migration Desk}},
  origlocation  = {{Madrid} and {Barcelona}},
  origdate      = {2020}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-publisher"/>
        <text variable="original-publisher-place"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="origpublisher"/>
      <text variable="origlocation"/>
      <text variable="original-publisher-list"/>
      <text variable="original-publisher-place-list"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('translated-archive');
    if (($item['originalPublisher'] ?? null) !== 'Archivo Press; Migration Desk') {
        throw new RuntimeException('BibTeX CSL original publisher handoff did not preserve normalized publisher metadata');
    }
    if (($item['originalPublisherPlace'] ?? null) !== 'Madrid; Barcelona') {
        throw new RuntimeException('BibTeX CSL original publisher handoff did not preserve normalized place metadata');
    }
    if (($item['raw']['original-publisher-list'] ?? null) !== ['Archivo Press', 'Migration Desk']) {
        throw new RuntimeException('BibTeX CSL original publisher handoff did not preserve raw publisher lists');
    }

    foreach ([
        '<p>Original publisher handoff [Garcia | Archivo Press; Migration Desk | Madrid; Barcelona] keeps source imprint metadata visible.</p>',
        '<dt>Garcia 2026</dt><dd>Migration Manual :: Archivo Press; Migration Desk :: Madrid; Barcelona :: Archivo Press; Migration Desk :: Madrid; Barcelona</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL original publisher self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-original-publisher-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
