<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Production credits [@production-credit-packet; @conversation-credit-packet] keep CSL role labels visible.';

$bibtex = <<<'BIB'
@video{production-credit-packet,
  title = {Production Credit Packet},
  date = {2026},
  producer = {Producer, Pia},
  performer = {Performer, Pat and {{Archive Ensemble}}},
  narrator = {Narrator, Nia},
  executiveproducer = {Executive, Eli},
  scriptwriter = {Writer, Sam}
}

@audio{conversation-credit-packet,
  title = {Conversation Credit Packet},
  date = {2025},
  host = {Host, Hugo},
  guest = {Guest, Gia and Roe, Pat}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Audiovisual Creator Label Review</title>
    <id>https://example.test/styles/wordpress-audiovisual-creator-label-review</id>
    <updated>2026-06-08T22:23:36+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="producer"><label form="verb" suffix=" "/><name/></names>
        <names variable="performer"><label form="verb-short" suffix=" "/><name/></names>
        <names variable="host"><label form="verb" suffix=" "/><name/></names>
        <names variable="guest"><label form="verb-short" suffix=" "/><name/></names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="producer"><name initialize-with=". " name-as-sort-order="all"/><label form="short" plural="never" prefix=", "/></names>
      <names variable="performer"><label form="long" plural="always" suffix=": "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="narrator"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="executive-producer"><name initialize-with=". " name-as-sort-order="all"/><label form="short" prefix=", "/></names>
      <names variable="script-writer"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="host"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="guest"><name initialize-with=". " name-as-sort-order="all"/><label form="short" plural="always" prefix=", "/></names>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
    if (($citationChildren[0]['nameRendering']['label']['form'] ?? null) !== 'verb') {
        throw new RuntimeException('CSL audiovisual creator label handoff did not preserve producer verb label metadata');
    }

    if (($citationChildren[1]['nameRendering']['label']['form'] ?? null) !== 'verb-short') {
        throw new RuntimeException('CSL audiovisual creator label handoff did not preserve performer verb-short label metadata');
    }

    foreach ([
        '<p>Production credits (produced by Producer | perf. by Performer and Archive Ensemble | 2026; hosted by Host | feat. Guest and Roe | 2025) keep CSL role labels visible.</p>',
        '<dt>Production Credit Packet 2026</dt><dd>Production Credit Packet :: Producer, P., prod. :: performers: Performer, P.; Archive Ensemble :: narrated by Narrator, N. :: Executive, E., exec. prod. :: written by Writer, S.</dd>',
        '<dt>Conversation Credit Packet 2025</dt><dd>Conversation Credit Packet :: hosted by Host, H. :: Guest, G.; Roe, P., guests</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL audiovisual creator label handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-audiovisual-label-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
