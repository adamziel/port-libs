<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Director Credit Review

Imported media source [@film-credit-source] keeps BibLaTeX director credits visible for review.
MARKDOWN;

$bibtex = <<<'BIB'
@movie{film-credit-source,
  director          = {Doe, Jane and {{Migration Film Unit}}},
  director+an:credit = {1=restored credit; 2:name=literal credit verified},
  title             = {Restored Import Film},
  date              = {2026},
  howpublished      = {Restored film packet},
  url               = {https://example.test/restored-import-film}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress BibTeX CSL Director Credit Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-director-credit-review</id>
    <updated>2026-06-09T01:19:51+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="director"/>
        <text variable="title"/>
        <text variable="type"/>
        <text variable="medium"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="director"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <text variable="name-annotation-summary"/>
      <text variable="medium"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('film-credit-source');
    if (($item['type'] ?? null) !== 'motion_picture') {
        throw new RuntimeException('BibTeX CSL director handoff produced unexpected CSL media type');
    }
    if (($item['directors'][0]['family'] ?? null) !== 'Doe') {
        throw new RuntimeException('BibTeX CSL director handoff did not preserve family name');
    }
    if (($item['directors'][1]['literal'] ?? null) !== 'Migration Film Unit') {
        throw new RuntimeException('BibTeX CSL director handoff did not preserve literal director name');
    }
    if (($item['directors'][0]['annotations'][0]['part'] ?? null) !== 'credit') {
        throw new RuntimeException('BibTeX CSL director handoff did not preserve director annotation suffix');
    }

    foreach ([
        '<p>Imported media source [Doe and Migration Film Unit | Restored Import Film | motion_picture | Restored film packet] keeps BibLaTeX director credits visible for review.</p>',
        '<dt>Restored Import Film 2026</dt><dd>Restored Import Film :: directed by Doe, J.; Migration Film Unit :: Director 1 credit: restored credit; Director 2: literal credit verified :: Restored film packet</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL director self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-director-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
