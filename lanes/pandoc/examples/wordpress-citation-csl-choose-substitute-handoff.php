<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Choose Substitute Review

Choose substitute citations [@title-source; @url-source] keep review links branch-aware.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "title-source",
    "type": "webpage",
    "title": "Title Only Packet",
    "issued": {"date-parts": [[2025]]},
    "URL": "https://example.test/title-source"
  },
  {
    "id": "url-source",
    "type": "webpage",
    "issued": {"date-parts": [[2024]]},
    "URL": "https://example.test/url-source"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Choose Substitute Review</title>
    <id>https://example.test/styles/wordpress-citation-choose-substitute-review</id>
    <updated>2026-06-08T15:14:46+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author">
          <substitute>
            <choose>
              <if variable="title">
                <text variable="title"/>
              </if>
              <else-if variable="URL">
                <text variable="URL"/>
              </else-if>
            </choose>
          </substitute>
        </names>
        <text variable="URL"/>
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography second-field-align="flush">
    <layout delimiter=" :: ">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
        <substitute>
          <choose>
            <if variable="title">
              <text variable="title" display="left-margin" font-weight="bold"/>
            </if>
            <else-if variable="URL">
              <text variable="URL" display="left-margin" prefix="Source: "/>
            </else-if>
          </choose>
        </substitute>
      </names>
      <text variable="URL" display="right-inline" prefix="URL "/>
      <text variable="title" display="right-inline" prefix="Title "/>
      <date variable="issued" display="block" prefix="Year "><date-part name="year"/></date>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['title'] ?? null) !== 'WordPress Citation Choose Substitute Review') {
        throw new RuntimeException('CSL choose substitute handoff did not preserve style title metadata');
    }
    if (($summary['citationRendering'][0]['children'][0]['substitute'][0]['branches'][0]['variables'] ?? null) !== ['title']) {
        throw new RuntimeException('CSL choose substitute handoff did not expose title substitute branch metadata');
    }
    if (($summary['citationRendering'][0]['children'][0]['substitute'][0]['branches'][1]['variables'] ?? null) !== ['URL']) {
        throw new RuntimeException('CSL choose substitute handoff did not expose URL substitute branch metadata');
    }

    foreach ([
        '<p>Choose substitute citations (Title Only Packet | https://example.test/title-source | 2025; https://example.test/url-source | 2024) keep review links branch-aware.</p>',
        '<dt>Title Only Packet 2025</dt><dd><div class="csl-entry"><div class="csl-left-margin csl-font-weight-bold" style="font-weight:bold">Title Only Packet</div><div class="csl-right-inline">URL https://example.test/title-source</div><div class="csl-block">Year 2025</div></div></dd>',
        '<dt>url-source 2024</dt><dd><div class="csl-entry"><div class="csl-left-margin">Source: https://example.test/url-source</div><div class="csl-block">Year 2024</div></div></dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL choose substitute handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-choose-substitute-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
