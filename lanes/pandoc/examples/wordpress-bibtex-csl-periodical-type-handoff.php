<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Periodical Issue Type Review

Periodical issue source [@review-issue] keeps journal-article routing stable.
MARKDOWN;

$bibtex = <<<'BIB'
@periodical{review-issue,
  editor       = {Curator, Eli},
  title        = {Migration Review Issue},
  journaltitle = {Journal of Migration Review},
  date         = {2026},
  number       = {42},
  pages        = {1--96},
  note         = {Complete issue queued for import}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Periodical Issue Type Review</title>
    <id>https://example.test/styles/bounded-periodical-type-review</id>
    <updated>2026-06-09T05:01:11+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <choose>
        <if type="article-journal">
          <group delimiter=" | ">
            <text value="periodical issue"/>
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
            <text value="periodical issue"/>
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
    $periodical = $processor->item('review-issue');
    $summary = $processor->cslStyleSummary();

    if (($periodical['type'] ?? null) !== 'article-journal') {
        throw new RuntimeException('BibTeX CSL periodical handoff did not map @periodical to the CSL article-journal branch');
    }
    if (($periodical['raw']['rawBibtex']['type'] ?? null) !== 'periodical') {
        throw new RuntimeException('BibTeX CSL periodical handoff did not preserve raw @periodical provenance');
    }
    if (($summary['citationRendering'][0]['branches'][0]['types'] ?? null) !== ['article-journal']) {
        throw new RuntimeException('BibTeX CSL periodical handoff did not preserve the article-journal type condition');
    }

    foreach ([
        '<p>Periodical issue source [periodical issue | Migration Review Issue | Journal of Migration Review | 42 | 1-96 | Complete issue queued for import] keeps journal-article routing stable.</p>',
        '<dt>Curator 2026</dt><dd>periodical issue :: Migration Review Issue :: Journal of Migration Review :: 42 :: 1-96 :: Complete issue queued for import</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL periodical type self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-periodical-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
