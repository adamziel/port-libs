<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Import Review

Smith says @smith1899 while the import queue cites [@wp-team].

The source archive keeps [@missing-source] visible for reviewer follow-up.
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
  }
]
JSON;

$processor = CitationCslProcessor::fromJson($cslJson);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');

echo (new WordPressBlockWriter())->write($document) . "\n";
