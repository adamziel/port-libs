<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX LaTeX Punctuation Review

Punctuation source [@punctuation-source] keeps TeX title punctuation readable.
MARKDOWN;

$bibtex = <<<'BIB'
@article{punctuation-source,
  author       = {Smith, Ada},
  title        = {\textquotedblleft Source Review\textquotedblright{} \textemdash{} import notes\ldots},
  journaltitle = {Review \textquoteleft Desk\textquoteright},
  note         = {\textquoteleft queued\textquoteright{} \textemdash{} source \textellipsis{}},
  date         = {2026},
  url          = {https://example.test/punctuation-source}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress BibTeX CSL LaTeX Punctuation Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-latex-punctuation-review</id>
    <updated>2026-06-09T02:57:39+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="container-title"/>
        <text variable="note"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="container-title"/>
      <text variable="note"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('punctuation-source');
    if (($item['title'] ?? null) !== '“Source Review” — import notes…') {
        throw new RuntimeException('BibTeX CSL LaTeX punctuation handoff did not decode title punctuation');
    }
    if (($item['containerTitle'] ?? null) !== 'Review ‘Desk’') {
        throw new RuntimeException('BibTeX CSL LaTeX punctuation handoff did not decode container punctuation');
    }
    if (($item['note'] ?? null) !== '‘queued’ — source …') {
        throw new RuntimeException('BibTeX CSL LaTeX punctuation handoff did not decode note punctuation');
    }

    foreach ([
        '<p>Punctuation source [“Source Review” — import notes… | Review ‘Desk’ | ‘queued’ — source …] keeps TeX title punctuation readable.</p>',
        '<dt>Smith 2026</dt><dd>“Source Review” — import notes… :: Review ‘Desk’ :: ‘queued’ — source …</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL LaTeX punctuation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-latex-punctuation-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
