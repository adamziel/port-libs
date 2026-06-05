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

The position style cites [@particle-source, p. 2], then [@particle-source, p. 3], and then [@particle-source, p. 3].

The local style renders @committee-source when source dates are missing.

The numbered style preserves @numbered-source issue ranges for reviewer packets.

The title-case style reviews @title-case-source source titles.

The quoted style checks @quoted-source title punctuation.

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
    "id": "numbered-source",
    "type": "report",
    "title": "Numbered Review Packet",
    "author": [
      {"literal": "Review Board"}
    ],
    "issued": {"date-parts": [[2025]]},
    "number": "2 - 4"
  },
  {
    "id": "title-case-source",
    "type": "report",
    "title": "migration review: source import and API",
    "author": [
      {"literal": "Migration Desk"}
    ],
    "issued": {"date-parts": [[2026]]},
    "publisher": "Review Press"
  },
  {
    "id": "quoted-source",
    "type": "dataset",
    "title": "source packet.",
    "author": [
      {"literal": "Quote Desk"}
    ],
    "issued": {"date-parts": [[2026, 6, 3]]},
    "publisher": "Review Press"
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
      <term name="open-quote">“</term>
      <term name="close-quote">”</term>
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
  <macro name="review-locator">
    <choose>
      <if variable="locator" match="any">
        <group delimiter=" ">
          <label variable="locator" form="short"/>
          <text variable="locator"/>
        </group>
      </if>
    </choose>
  </macro>
  <macro name="review-normal-citation">
    <group delimiter=", ">
      <text macro="review-citation"/>
      <text macro="review-locator"/>
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
  <macro name="review-number">
    <choose>
      <if variable="number" match="any">
        <group delimiter=" ">
          <label variable="number" form="short"/>
          <number variable="number" form="ordinal"/>
        </group>
      </if>
    </choose>
  </macro>
  <macro name="review-title-case">
    <text variable="title" text-case="title"/>
  </macro>
  <macro name="review-quoted-title">
    <text variable="title" quotes="true" strip-periods="true" text-case="title"/>
  </macro>
  <macro name="review-source-locator">
    <choose>
      <if variable="DOI" match="any">
        <text variable="DOI" prefix="DOI "/>
      </if>
      <else-if variable="URL" match="any">
        <text variable="URL"/>
      </else-if>
      <else>
        <text value="No stable source locator"/>
      </else>
    </choose>
  </macro>
  <macro name="review-bibliography-entry">
    <group delimiter=". " suffix=".">
      <names variable="author editor" delimiter="; ">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <choose>
        <if type="dataset" match="any">
          <text macro="review-quoted-title"/>
        </if>
        <else>
          <text macro="review-title-case"/>
        </else>
      </choose>
      <text macro="review-publication"/>
      <text macro="review-number"/>
      <text macro="review-source-locator"/>
      <text macro="review-accessed"/>
    </group>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <choose>
        <if position="ibid-with-locator" match="any">
          <group delimiter=", ">
            <text value="ibid"/>
            <text macro="review-locator"/>
          </group>
        </if>
        <else-if position="ibid" match="any">
          <text value="ibid"/>
        </else-if>
        <else>
          <text macro="review-normal-citation"/>
        </else>
      </choose>
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
$parsed = (new MarkdownReader())->read($markdown);
$document = $processor->appendBibliography($parsed, 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['branches'][0]['positions'][0] ?? null) !== 'ibid-with-locator') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the ibid-with-locator position branch');
    }
    if (($summary['citationRendering'][0]['else'][0]['macro'] ?? null) !== 'review-normal-citation') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the normal citation macro branch');
    }
    if (($summary['macros']['review-normal-citation'][0]['children'][0]['macro'] ?? null) !== 'review-citation') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the citation macro reference');
    }
    if (($summary['macros']['review-normal-citation'][0]['children'][1]['macro'] ?? null) !== 'review-locator') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the locator macro reference');
    }
    if (($summary['macros']['review-number'][0]['branches'][0]['children'][0]['children'][1]['type'] ?? null) !== 'number') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the number rendering element');
    }
    if (($summary['macros']['review-number'][0]['branches'][0]['children'][0]['children'][1]['form'] ?? null) !== 'ordinal') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the ordinal number form');
    }
    if (($summary['macros']['review-title-case'][0]['textCase'] ?? null) !== 'title') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the title text-case transform');
    }
    if (($summary['macros']['review-quoted-title'][0]['quotes'] ?? null) !== true) {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the title quotes transform');
    }
    if (($summary['macros']['review-quoted-title'][0]['stripPeriods'] ?? null) !== true) {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the strip-periods transform');
    }
    if (($summary['macros']['review-bibliography-entry'][0]['children'][1]['branches'][0]['types'][0] ?? null) !== 'dataset') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the quoted dataset branch');
    }
    if (($summary['bibliographyRendering'][0]['macro'] ?? null) !== 'review-bibliography-entry') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve the bibliography macro reference');
    }
    $queueCitation = $parsed->children[1]->children[3]->children[0] ?? null;
    if (!$queueCitation instanceof PortLibs\Pandoc\AstNode || $queueCitation->attr('locatorLabel') !== 'section' || $queueCitation->attr('locatorValue') !== '2') {
        throw new RuntimeException('Citation CSL handoff self-test did not preserve parsed section locator metadata');
    }

    foreach ([
        '<p>Smith says Smith (1899) while the import queue cites (see WordPress Migration Team 2024, sec. 2; 1899, pp. 8-9).</p>',
        '<p>The reviewer packet cites de la Cruz (2026) for imported source access dates.</p>',
        '<p>The position style cites (ibid, p. 2), then (ibid, p. 3), and then (ibid).</p>',
        '<p>The local style renders Adams, Baker, and others (undated) when source dates are missing.</p>',
        '<p>The numbered style preserves Review Board (2025) issue ranges for reviewer packets.</p>',
        '<p>The title-case style reviews Migration Desk (2026) source titles.</p>',
        '<p>The quoted style checks Quote Desk (2026) title punctuation.</p>',
        '<dt>Migration Desk 2026</dt><dd>[Migration Desk. Migration Review: Source Import and API. Review Press, 2026. No stable source locator.]</dd>',
        '<dt>Quote Desk 2026</dt><dd>[Quote Desk. “Source Packet”. Review Press, 2026. No stable source locator.]</dd>',
        '<dt>de la Cruz 2026</dt><dd>[de la Cruz, A. M., Jr. Source Packet. 2026. https://example.test/source-packet. Retrieved 2026-06-05.]</dd>',
        '<dt>Review Board 2025</dt><dd>[Review Board. Numbered Review Packet. 2025. nos. 2nd-4th. No stable source locator.]</dd>',
        '<dt>Adams, Baker, and others undated</dt><dd>[Adams, A.; Baker, B.; Clark, C. Undated Committee Packet. No stable source locator.]</dd>',
        '<p>The source archive keeps (see @missing-source; URL Key Source 2000, p. 33) visible for reviewer follow-up.</p>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Citation CSL handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    $sortedTerms = [
        '<dt>de la Cruz 2026</dt>',
        '<dt>Quote Desk 2026</dt>',
        '<dt>Migration Desk 2026</dt>',
        '<dt>Review Board 2025</dt>',
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
