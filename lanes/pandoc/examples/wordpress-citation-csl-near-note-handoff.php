<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Note Citation Review

Initial source note.[^a]

Bridge note.[^b]

Nearby source note.[^c]

Spacer note.[^d]

Additional spacer note.[^e]

Far source note.[^f]

[^a]: Initial footnote cites [@source-a].

[^b]: Bridge footnote cites [@source-b].

[^c]: Nearby footnote cites [@source-a].

[^d]: Spacer footnote cites [@source-c].

[^e]: Additional spacer footnote cites [@source-b].

[^f]: Far footnote cites [@source-a].
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "source-a",
    "type": "article-journal",
    "title": "Near Note Source A",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "source-b",
    "type": "report",
    "title": "Near Note Source B",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "source-c",
    "type": "webpage",
    "title": "Spacer Source C",
    "author": [
      {"literal": "Archive Desk"}
    ],
    "issued": {"date-parts": [[2024]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="note" default-locale="en-US">
  <info>
    <title>WordPress Near Note Review</title>
    <id>https://example.test/styles/wordpress-near-note-review</id>
    <updated>2026-06-05T12:48:25+00:00</updated>
  </info>
  <macro name="citation-key">
    <group delimiter=" ">
      <names variable="author editor"/>
      <date variable="issued">
        <date-part name="year"/>
      </date>
    </group>
  </macro>
  <citation near-note-distance="2">
    <layout prefix="(" suffix=")" delimiter="; ">
      <choose>
        <if position="ibid" match="any">
          <text value="ibid"/>
        </if>
        <else-if position="near-note" match="any">
          <group delimiter=" ">
            <text value="near-note"/>
            <text macro="citation-key"/>
          </group>
        </else-if>
        <else-if position="subsequent" match="any">
          <group delimiter=" ">
            <text value="subsequent"/>
            <text macro="citation-key"/>
          </group>
        </else-if>
        <else>
          <text macro="citation-key"/>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author"/>
      <text variable="title"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationOptions']['nearNoteDistance'] ?? null) !== 2) {
        throw new RuntimeException('Citation CSL near-note handoff did not preserve near-note-distance');
    }
    if (($summary['citationRendering'][0]['branches'][1]['positions'][0] ?? null) !== 'near-note') {
        throw new RuntimeException('Citation CSL near-note handoff did not preserve near-note position branch');
    }

    $citations = [];
    $collectCitations = static function (AstNode $node) use (&$collectCitations, &$citations): void {
        if ($node->type === 'citation') {
            $citations[] = $node;
        }

        foreach ($node->children as $child) {
            $collectCitations($child);
        }
    };
    $collectCitations($document);

    if (($citations[2]->attr('cslPositionTests') ?? null) !== ['subsequent', 'near-note']) {
        throw new RuntimeException('Citation CSL near-note handoff did not mark nearby footnote citation');
    }
    if (($citations[5]->attr('cslPositionTests') ?? null) !== ['subsequent']) {
        throw new RuntimeException('Citation CSL near-note handoff did not keep distant footnote citation out of near-note range');
    }

    foreach ([
        '<li id="fn-1"><p>Initial footnote cites (de la Cruz 2026).</p>',
        '<li id="fn-3"><p>Nearby footnote cites (near-note de la Cruz 2026).</p>',
        '<li id="fn-6"><p>Far footnote cites (subsequent de la Cruz 2026).</p>',
        '<dt>de la Cruz 2026</dt><dd>de la Cruz, Ana Maria. Near Note Source A.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Citation CSL near-note handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-near-note-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
