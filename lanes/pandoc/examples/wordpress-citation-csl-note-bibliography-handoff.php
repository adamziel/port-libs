<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Note Bibliography Review

Initial source note.[^a]

Bridge source note.[^b]

Repeated source note.[^c]

[^a]: Initial footnote cites [@source-a].

[^b]: Bridge footnote cites [@source-b].

[^c]: Repeated footnote cites [@source-a, p. 9; @source-c].
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "source-a",
    "type": "report",
    "title": "First Note Source A",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "source-b",
    "type": "report",
    "title": "First Note Source B",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "source-c",
    "type": "report",
    "title": "Later Note Source C",
    "author": [
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2024]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="note" default-locale="en-US">
  <info>
    <title>WordPress CSL First Reference Note Bibliography Review</title>
    <id>https://example.test/styles/wordpress-csl-first-reference-note-bibliography-review</id>
    <updated>2026-06-09T04:06:57+00:00</updated>
  </info>
  <macro name="source-key">
    <group delimiter=" ">
      <names variable="author"/>
      <date variable="issued"><date-part name="year"/></date>
    </group>
  </macro>
  <macro name="first-note">
    <group delimiter=" ">
      <text value="first-note"/>
      <number variable="first-reference-note-number" form="ordinal"/>
      <text variable="first-reference-note-number" prefix="raw "/>
    </group>
  </macro>
  <citation>
    <layout delimiter="; ">
      <choose>
        <if position="subsequent" match="any">
          <group delimiter=" ">
            <text macro="first-note"/>
            <text macro="source-key"/>
          </group>
        </if>
        <else>
          <text macro="source-key"/>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="author"/>
    </sort>
    <layout delimiter=". " suffix=".">
      <text macro="first-note"/>
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
    $macroChildren = $summary['macros']['first-note'][0]['children'] ?? [];

    if (($summary['class'] ?? null) !== 'note') {
        throw new RuntimeException('CSL note bibliography handoff did not preserve note-style class');
    }
    if (($summary['bibliographyRendering'][0]['macro'] ?? null) !== 'first-note') {
        throw new RuntimeException('CSL note bibliography handoff did not preserve bibliography macro use');
    }
    if (($macroChildren[1]['variable'] ?? null) !== 'first-reference-note-number' || ($macroChildren[1]['form'] ?? null) !== 'ordinal') {
        throw new RuntimeException('CSL note bibliography handoff did not preserve ordinal first-note variable metadata');
    }

    foreach ([
        '<li id="fn-3"><p>Repeated footnote cites first-note 1st raw 1 Smith 2026, p. 9; Roe 2024.</p>',
        '<dt>Ng 2025</dt><dd>first-note 2nd raw 2. Ng, Nia. First Note Source B.</dd>',
        '<dt>Roe 2024</dt><dd>first-note 3rd raw 3. Roe, Pat. Later Note Source C.</dd>',
        '<dt>Smith 2026</dt><dd>first-note 1st raw 1. Smith, Ada. First Note Source A.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL note bibliography self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-note-bibliography-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
