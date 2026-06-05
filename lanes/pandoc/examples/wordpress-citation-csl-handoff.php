<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Import Review

Smith says @smith1899 while the import queue cites [see @wp-team, sec. 2; -@smith1899, pp. 8-9].

The reviewer packet cites @particle-source for imported source access dates.

The local style renders @committee-source when source dates are missing.

The source archive keeps [see @missing-source; @{https://example.com/bib?name=foobar&date=2000}, p. 33] visible for reviewer follow-up.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "smith1899",
    "type": "book",
    "title": "Migration Patterns",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[1899]]},
    "publisher": "Archive Press",
    "DOI": "10.1234/source"
  },
  {
    "id": "wp-team",
    "type": "webpage",
    "title": "Reviewer Log",
    "author": [
      {"literal": "WordPress Migration Team"}
    ],
    "issued": {"date-parts": [[2024]]},
    "URL": "https://example.test/reviewer-log"
  },
  {
    "id": "particle-source",
    "type": "webpage",
    "title": "Source Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la", "suffix": "Jr.", "comma-suffix": true}
    ],
    "issued": {"date-parts": [[2026, 6, 4]]},
    "accessed": {"date-parts": [[2026, 6, 5]]},
    "URL": "https://example.test/source-packet"
  },
  {
    "id": "committee-source",
    "type": "report",
    "title": "Undated Committee Packet",
    "author": [
      {"family": "Adams", "given": "Ari"},
      {"family": "Baker", "given": "Bea"},
      {"family": "Clark", "given": "Cy"}
    ]
  },
  {
    "id": "https://example.com/bib?name=foobar&date=2000",
    "type": "webpage",
    "title": "URL Key Source",
    "issued": {"date-parts": [[2000]]},
    "URL": "https://example.com/bib?name=foobar&date=2000"
  }
]
JSON;

$cslStyleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Review Author Date</title>
    <id>https://example.test/styles/wordpress-review-author-date</id>
    <updated>2026-06-04T00:00:00+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="et-al">and others</term>
      <term name="no date">undated</term>
      <term name="accessed">Retrieved</term>
    </terms>
  </locale>
  <macro name="review-citation">
    <group delimiter=" ">
      <names variable="author editor" delimiter=", " et-al-min="3" et-al-use-first="2">
        <name/>
      </names>
      <date variable="issued">
        <date-part name="year"/>
      </date>
    </group>
  </macro>
  <macro name="review-publication">
    <group delimiter=", ">
      <text variable="publisher"/>
      <date variable="issued">
        <date-part name="year"/>
      </date>
    </group>
  </macro>
  <macro name="review-accessed">
    <group delimiter=" ">
      <text term="accessed"/>
      <date variable="accessed"/>
    </group>
  </macro>
  <macro name="review-bibliography-entry">
    <group delimiter=". " suffix=".">
      <names variable="author editor" delimiter="; ">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <text variable="title"/>
      <text macro="review-publication"/>
      <text variable="DOI" prefix="DOI "/>
      <text variable="URL"/>
      <text macro="review-accessed"/>
    </group>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <text macro="review-citation"/>
    </layout>
  </citation>
  <bibliography hanging-indent="true" entry-spacing="0" line-spacing="1">
    <sort>
      <key variable="issued" sort="descending"/>
      <key variable="title"/>
    </sort>
    <layout prefix="[" suffix="]" delimiter=" ">
      <text macro="review-bibliography-entry"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($cslStyleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['macro'] ?? null) !== 'review-citation') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the citation macro reference');
    }
    if (($summary['bibliographyRendering'][0]['macro'] ?? null) !== 'review-bibliography-entry') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the bibliography macro reference');
    }

    foreach ([
        '<p>The reviewer packet cites de la Cruz (2026) for imported source access dates.</p>',
        '<p>The local style renders Adams, Baker, and others (undated) when source dates are missing.</p>',
        '<dt>de la Cruz 2026</dt><dd>[de la Cruz, A. M., Jr. Source Packet. 2026. https://example.test/source-packet. Retrieved 2026-06-05.]</dd>',
        '<dt>Adams, Baker, and others undated</dt><dd>[Adams, A.; Baker, B.; Clark, C. Undated Committee Packet.]</dd>',
        '<p>The source archive keeps (see @missing-source; URL Key Source 2000, p. 33) visible for reviewer follow-up.</p>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Citation CSL handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    $sortedTerms = [
        '<dt>de la Cruz 2026</dt>',
        '<dt>WordPress Migration Team 2024</dt>',
        '<dt>URL Key Source 2000</dt>',
        '<dt>Smith 1899</dt>',
        '<dt>Adams, Baker, and others undated</dt>',
    ];
    $previousPosition = -1;
    foreach ($sortedTerms as $term) {
        $position = strpos($blocks, $term);
        if (!is_int($position) || $position <= $previousPosition) {
            throw new RuntimeException('Citation CSL handoff self-test bibliography sort order mismatch at ' . $term);
        }
        $previousPosition = $position;
    }

    echo "wordpress-citation-csl-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
