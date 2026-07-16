<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Page Range Citation Review

Page ranges cite [@page-range-source, pp. 321-328] for imported source packet review.
MARKDOWN;

$items = [
    [
        'id' => 'page-range-source',
        'type' => 'article-journal',
        'title' => 'Page Range Source',
        'container-title' => 'Import Review',
        'author' => [
            ['family' => 'Smith', 'given' => 'Ada'],
        ],
        'issued' => ['date-parts' => [[2026]]],
        'page' => '321-328, 100-104, 107-108, 1496-1504, A-D',
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" page-range-format="chicago">
  <info>
    <title>WordPress Citation Page Range Review</title>
    <id>https://example.test/styles/wordpress-citation-page-range-review</id>
    <updated>2026-06-07T07:30:45+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <group delimiter=" ">
          <label variable="locator" form="short"/>
          <text variable="locator"/>
        </group>
        <group delimiter=" ">
          <label variable="page" form="short"/>
          <text variable="page"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="page" form="short"/>
        <text variable="page"/>
      </group>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($styleXml);
$document = (new MarkdownReader())->read($markdown);
$blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));

if (($argv[1] ?? '') === '--self-test') {
    $dash = "\u{2013}";
    $expectedCitation = '<p>Page ranges cite (Smith | pp. 321' . $dash . '28 | pp. 321' . $dash . '28, 100' . $dash . '104, 107' . $dash . '8, 1496' . $dash . '1504, A-D) for imported source packet review.</p>';
    $expectedBibliography = '<dt>Smith 2026</dt><dd>Page Range Source. pp. 321' . $dash . '28, 100' . $dash . '104, 107' . $dash . '8, 1496' . $dash . '1504, A-D.</dd>';
    foreach ([$expectedCitation, $expectedBibliography] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL page-range-format handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-page-range-format-handoff self-test passed\n";
    return;
}

echo $blocks;
