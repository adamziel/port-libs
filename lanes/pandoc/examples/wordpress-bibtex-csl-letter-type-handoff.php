<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Letter Type Review

Letter source [@source-letter] keeps personal-communication routing stable.
MARKDOWN;

$bibtex = <<<'BIB'
@letter{source-letter,
  author    = {Smith, Ada},
  title     = {Legacy Source Letter},
  date      = {2026-06-01},
  recipient = {{Review Desk}},
  note      = {Preserved from migration mailbox}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Letter Type Review</title>
    <id>https://example.test/styles/bounded-letter-type-review</id>
    <updated>2026-06-09T04:25:53+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <choose>
        <if type="personal_communication">
          <group delimiter=" | ">
            <text value="letter"/>
            <text variable="title"/>
            <names variable="recipient"><name/></names>
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
        <if type="personal_communication">
          <group delimiter=" :: ">
            <text value="letter"/>
            <text variable="title"/>
            <names variable="author"><name name-as-sort-order="all"/></names>
            <names variable="recipient"><name/></names>
            <text variable="note"/>
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
    $letter = $processor->item('source-letter');
    $summary = $processor->cslStyleSummary();

    if (($letter['type'] ?? null) !== 'personal_communication') {
        throw new RuntimeException('BibTeX CSL letter handoff did not map @letter to the CSL personal_communication branch');
    }
    if (($letter['raw']['rawBibtex']['type'] ?? null) !== 'letter') {
        throw new RuntimeException('BibTeX CSL letter handoff did not preserve raw @letter provenance');
    }
    if (($letter['recipients'][0]['literal'] ?? null) !== 'Review Desk') {
        throw new RuntimeException('BibTeX CSL letter handoff did not preserve recipient metadata');
    }
    if (($summary['citationRendering'][0]['branches'][0]['types'] ?? null) !== ['personal_communication']) {
        throw new RuntimeException('BibTeX CSL letter handoff did not preserve the personal_communication type condition');
    }

    foreach ([
        '<p>Letter source [letter | Legacy Source Letter | Review Desk | Preserved from migration mailbox] keeps personal-communication routing stable.</p>',
        '<dt>Smith 2026</dt><dd>letter :: Legacy Source Letter :: Smith, Ada :: Review Desk :: Preserved from migration mailbox</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL letter type self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-letter-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
