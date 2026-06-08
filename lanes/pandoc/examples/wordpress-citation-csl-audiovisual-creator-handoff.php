<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Audiovisual source [@migration-film; @migration-podcast] keeps production credits visible.';

$bibtex = <<<'BIB'
@video{migration-film,
  title = {Migration Review Film},
  date = {2026},
  producer = {Producer, Pia},
  performer = {Performer, Pat and {{Archive Ensemble}}},
  narrator = {Narrator, Nia},
  host = {Host, Hugo},
  guest = {Guest, Gia},
  executiveproducer = {Executive, Eli},
  scriptwriter = {Writer, Sam},
  producer+an = {1=source credit verified},
  scriptwriter+an = {1:family=script credit verified}
}

@audio{migration-podcast,
  title = {Migration Review Podcast},
  date = {2025},
  host = {Ng, Nia},
  guest = {Roe, Pat}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Audiovisual Creator Review</title>
    <id>https://example.test/styles/wordpress-audiovisual-creator-review</id>
    <updated>2026-06-08T18:53:44+00:00</updated>
  </info>
  <macro name="creator-route">
    <choose>
      <if is-creator="producer performer">
        <text value="audiovisual"/>
      </if>
      <else-if is-creator="host guest">
        <text value="conversation"/>
      </else-if>
      <else>
        <text value="source"/>
      </else>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <text macro="creator-route"/>
        <names variable="producer"/>
        <names variable="performer"/>
        <names variable="narrator"/>
        <names variable="host"/>
        <names variable="guest"/>
        <names variable="executive-producer"/>
        <names variable="script-writer"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="producer"/>
      <names variable="performer"/>
      <names variable="narrator"/>
      <names variable="host"/>
      <names variable="guest"/>
      <names variable="executive-producer"/>
      <names variable="script-writer"/>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $branches = $processor->cslStyleSummary()['macros']['creator-route'][0]['branches'] ?? [];
    if (($branches[0]['isCreator'] ?? null) !== ['producer', 'performer']) {
        throw new RuntimeException('CSL audiovisual creator handoff did not preserve producer/performer condition metadata');
    }

    foreach ([
        '<p>Audiovisual source (audiovisual | Producer | Performer and Archive Ensemble | Narrator | Host | Guest | Executive | Writer | 2026; conversation | Ng | Roe | 2025) keeps production credits visible.</p>',
        '<dt>Migration Review Film 2026</dt><dd>Migration Review Film :: Producer, Pia :: Performer, Pat; Archive Ensemble :: Narrator, Nia :: Host, Hugo :: Guest, Gia :: Executive, Eli :: Writer, Sam :: Producer 1: source credit verified; Script writer 1 family: script credit verified</dd>',
        '<dt>Migration Review Podcast 2025</dt><dd>Migration Review Podcast :: Ng, Nia :: Roe, Pat</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL audiovisual creator handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-audiovisual-creator-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
