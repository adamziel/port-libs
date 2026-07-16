<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Supplemental Periodical Type Review

Supplemental periodical source [@journal-supplement] keeps journal-article routing stable.
MARKDOWN;

$bibtex = <<<'BIB'
@suppperiodical{journal-supplement,
  author       = {Roe, Pat},
  title        = {Supplemental Import Notes},
  journaltitle = {Journal of Migration Review},
  date         = {2026},
  number       = {S1},
  pages        = {S3--S9},
  note         = {Published as online supplement}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Supplemental Periodical Type Review</title>
    <id>https://example.test/styles/bounded-suppperiodical-type-review</id>
    <updated>2026-06-09T04:47:12+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <choose>
        <if type="article-journal">
          <group delimiter=" | ">
            <text value="journal supplement"/>
            <text variable="title"/>
            <text variable="container-title"/>
            <text variable="issue"/>
            <text variable="page"/>
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
        <if type="article-journal">
          <group delimiter=" :: ">
            <text value="journal supplement"/>
            <text variable="title"/>
            <text variable="container-title"/>
            <text variable="issue"/>
            <text variable="page"/>
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
    $supplement = $processor->item('journal-supplement');
    $summary = $processor->cslStyleSummary();

    if (($supplement['type'] ?? null) !== 'article-journal') {
        throw new RuntimeException('BibTeX CSL supplemental periodical handoff did not map @suppperiodical to the CSL article-journal branch');
    }
    if (($supplement['raw']['rawBibtex']['type'] ?? null) !== 'suppperiodical') {
        throw new RuntimeException('BibTeX CSL supplemental periodical handoff did not preserve raw @suppperiodical provenance');
    }
    if (($summary['citationRendering'][0]['branches'][0]['types'] ?? null) !== ['article-journal']) {
        throw new RuntimeException('BibTeX CSL supplemental periodical handoff did not preserve the article-journal type condition');
    }

    foreach ([
        '<p>Supplemental periodical source [journal supplement | Supplemental Import Notes | Journal of Migration Review | S1 | S3-S9 | Published as online supplement] keeps journal-article routing stable.</p>',
        '<dt>Roe 2026</dt><dd>journal supplement :: Supplemental Import Notes :: Journal of Migration Review :: S1 :: S3-S9 :: Published as online supplement</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL supplemental periodical type self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-suppperiodical-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
