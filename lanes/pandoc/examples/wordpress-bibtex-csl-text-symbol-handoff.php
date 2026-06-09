<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Text Symbol Review

Text symbol source [@text-symbol-source] keeps BibTeX path and rights symbols readable.
MARKDOWN;

$bibtex = <<<'BIB'
@book{text-symbol-source,
  author    = {Ng, Nia},
  title     = {Path \textbackslash{} assets \textless{}review\textgreater{}},
  publisher = {Audit \textcopyright{} Team},
  note      = {packet\textasciitilde{}draft \textregistered{} \texttrademark{} \textnumero{}7 \textdegree{}C \textbar{} phase \textasciicircum{}2},
  date      = {2026},
  url       = {https://example.test/text-symbol-source}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress BibTeX CSL Text Symbol Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-text-symbol-review</id>
    <updated>2026-06-09T05:41:34+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="publisher"/>
        <text variable="note"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="publisher"/>
      <text variable="note"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('text-symbol-source');
    if (($item['title'] ?? null) !== 'Path \\ assets <review>') {
        throw new RuntimeException('BibTeX CSL text symbol handoff did not decode path/title symbols');
    }
    if (($item['publisher'] ?? null) !== 'Audit © Team') {
        throw new RuntimeException('BibTeX CSL text symbol handoff did not decode publisher symbols');
    }
    if (($item['note'] ?? null) !== 'packet~draft ® ™ №7 °C | phase ^2') {
        throw new RuntimeException('BibTeX CSL text symbol handoff did not decode note symbols');
    }
    if (($item['raw']['rawBibtex']['fields']['title'] ?? null) !== 'Path \\textbackslash{} assets \\textless{}review\\textgreater{}') {
        throw new RuntimeException('BibTeX CSL text symbol handoff did not preserve raw BibTeX provenance');
    }

    foreach ([
        '<p>Text symbol source [Path \ assets &lt;review&gt; | Audit © Team | packet~draft ® ™ №7 °C | phase ^2] keeps BibTeX path and rights symbols readable.</p>',
        '<dt>Ng 2026</dt><dd>Path \ assets &lt;review&gt; :: Audit © Team :: packet~draft ® ™ №7 °C | phase ^2</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL text symbol self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-text-symbol-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
