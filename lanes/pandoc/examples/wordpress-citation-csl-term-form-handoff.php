<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Term Form Review

Reviewer packets cite [@term-form-source] with localized CSL term forms.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "term-form-source",
    "type": "review",
    "title": "Term Form Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "editor": [
      {"family": "Ng", "given": "Nia"},
      {"family": "Roe", "given": "Pat"}
    ],
    "reviewed-author": [
      {"literal": "Archive Desk"}
    ],
    "issued": {"date-parts": [[2026]]},
    "accessed": {"date-parts": [[2026, 6, 9]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Term Form Review</title>
    <id>https://example.test/styles/wordpress-citation-term-form-review</id>
    <updated>2026-06-09T00:10:21+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="editor" form="verb-short"><single>ed.</single><multiple>eds.</multiple></term>
      <term name="reviewed-author" form="verb">review of</term>
      <term name="section" form="symbol"><single>§</single><multiple>§§</multiple></term>
      <term name="accessed" form="short">acc.</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <group delimiter=" ">
          <text term="editor" form="verb-short" plural="true"/>
          <names variable="editor"/>
        </group>
        <group delimiter=" ">
          <text term="reviewed-author" form="verb-short"/>
          <names variable="reviewed-author"/>
        </group>
        <group delimiter=" ">
          <text term="section" form="symbol" plural="true"/>
          <text value="4-5"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <text term="accessed" form="short"/>
        <date variable="accessed"/>
      </group>
      <group delimiter=" ">
        <text term="section" form="symbol"/>
        <text value="review appendix"/>
      </group>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $children = $summary['citationRendering'][0]['children'] ?? [];
    if (($children[1]['children'][0]['form'] ?? null) !== 'verb-short') {
        throw new RuntimeException('CSL term-form handoff did not preserve editor verb-short metadata');
    }
    if (($children[3]['children'][0]['form'] ?? null) !== 'symbol') {
        throw new RuntimeException('CSL term-form handoff did not preserve section symbol metadata');
    }

    foreach ([
        '<p>Reviewer packets cite (Smith | eds. Ng and Roe | review of Archive Desk | §§ 4-5) with localized CSL term forms.</p>',
        '<dt>Smith 2026</dt><dd>Term Form Packet :: acc. 2026-06-09 :: § review appendix</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL term-form self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-term-form-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
