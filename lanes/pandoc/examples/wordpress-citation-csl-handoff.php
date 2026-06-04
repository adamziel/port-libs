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
    "id": "https://example.com/bib?name=foobar&date=2000",
    "type": "webpage",
    "title": "URL Key Source",
    "issued": {"date-parts": [[2000]]},
    "URL": "https://example.com/bib?name=foobar&date=2000"
  }
]
JSON;

$processor = CitationCslProcessor::fromJson($cslJson);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<p>The reviewer packet cites de la Cruz (2026) for imported source access dates.</p>',
        '<dt>de la Cruz 2026</dt><dd>de la Cruz, Ana Maria, Jr. Source Packet. 2026. https://example.test/source-packet. Accessed 2026-06-05.</dd>',
        '<p>The source archive keeps (see @missing-source; URL Key Source 2000, p. 33) visible for reviewer follow-up.</p>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Citation CSL handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
