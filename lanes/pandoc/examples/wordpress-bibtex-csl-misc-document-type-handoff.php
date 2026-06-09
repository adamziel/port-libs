<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Misc Document Type Review

Misc source [@legacy-misc-packet] keeps generic document routing stable.
MARKDOWN;

$bibtex = <<<'BIB'
@misc{legacy-misc-packet,
  author       = {Smith, Ada},
  title        = {Legacy Misc Source Packet},
  date         = {2026},
  howpublished = {Exported CMS packet},
  note         = {Queued for reviewer import},
  url          = {https://example.test/legacy-misc}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Misc Document Type Review</title>
    <id>https://example.test/styles/bounded-misc-document-type-review</id>
    <updated>2026-06-09T07:02:57+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <choose>
        <if type="document">
          <group delimiter=" | ">
            <text value="document"/>
            <text variable="title"/>
            <text variable="medium"/>
            <text variable="note"/>
          </group>
        </if>
        <else>
          <text value="fallback"/>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <choose>
        <if type="document">
          <group delimiter=" :: ">
            <text value="document"/>
            <text variable="title"/>
            <text variable="medium"/>
            <text variable="note"/>
            <text variable="URL"/>
          </group>
        </if>
      </choose>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $misc = $processor->item('legacy-misc-packet');
    $summary = $processor->cslStyleSummary();

    if (($misc['type'] ?? null) !== 'document') {
        throw new RuntimeException('BibTeX CSL misc handoff did not map @misc to the CSL document branch');
    }
    if (($misc['raw']['rawBibtex']['type'] ?? null) !== 'misc') {
        throw new RuntimeException('BibTeX CSL misc handoff did not preserve raw @misc provenance');
    }
    if (($summary['citationRendering'][0]['branches'][0]['types'] ?? null) !== ['document']) {
        throw new RuntimeException('BibTeX CSL misc handoff did not preserve the document type condition');
    }

    foreach ([
        '<p>Misc source [document | Legacy Misc Source Packet | Exported CMS packet | Queued for reviewer import] keeps generic document routing stable.</p>',
        '<dt>Smith 2026</dt><dd>document :: Legacy Misc Source Packet :: Exported CMS packet :: Queued for reviewer import :: https://example.test/legacy-misc</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL misc document type self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-misc-document-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
