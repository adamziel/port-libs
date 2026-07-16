<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'BibTeX review [@available-bibtex; @submitted-bibtex; @literal-available-bibtex] keeps CSL availability dates.';

$bibtex = <<<'BIB'
@online{available-bibtex,
  author            = {Smith, Ada},
  title             = {Available BibTeX Packet},
  date              = {2026},
  url               = {https://example.test/available-bibtex},
  availabledate     = {2026-06?},
  submittedyear     = {2026},
  submittedmonth    = {5},
  submittedday      = {28},
  submittedhour     = {9},
  submittedminute   = {30},
  submittedtimezone = {Z}
}

@report{submitted-bibtex,
  author         = {Raman, Ira},
  title          = {Submitted Split BibTeX Packet},
  date           = {2025},
  availableyear  = {2025},
  availablemonth = mar,
  availableday   = {11},
  submitteddate  = {2024~}
}

@article{literal-available-bibtex,
  author        = {Doe, Jae},
  title         = {Literal Available BibTeX Packet},
  date          = {2024},
  availabledate = {early access queue},
  submitted     = {2024-02}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress BibTeX CSL Available Submitted Date Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-available-submitted-date-review</id>
    <updated>2026-06-09T02:44:31+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <choose>
          <if is-uncertain-date="available-date">
            <text value="available?"/>
          </if>
          <else-if is-circa-date="submitted">
            <text value="submitted circa"/>
          </else-if>
          <else>
            <text value="dated"/>
          </else>
        </choose>
        <date variable="available-date" form="text" date-parts="year-month"/>
        <date variable="submitted"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="submitted"/>
    </sort>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="available-date"/>
      <text variable="available-date-status"/>
      <date variable="submitted"/>
      <text variable="submitted-status"/>
      <text variable="date-marker-summary"/>
      <text variable="date-time-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$items = CitationCslProcessor::bibtexItems($bibtex);
$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Availability Sources');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    if (($items[0]['available-date']['uncertain'] ?? null) !== true) {
        throw new RuntimeException('BibTeX available/submitted date handoff did not import availabledate uncertainty');
    }
    if (($items[0]['submitted']['time'] ?? null) !== '09:30Z') {
        throw new RuntimeException('BibTeX available/submitted date handoff did not import submitted split time');
    }
    if (($items[1]['available-date']['date-parts'][0] ?? null) !== [2025, 3, 11]) {
        throw new RuntimeException('BibTeX available/submitted date handoff did not import split available date fields');
    }
    if (($items[2]['available-date']['literal'] ?? null) !== 'early access queue') {
        throw new RuntimeException('BibTeX available/submitted date handoff did not preserve literal availabledate');
    }

    foreach ([
        '<p>BibTeX review (Smith | available? | June 2026 | 2026-05-28; Raman | submitted circa | March 2025 | 2024; Doe | dated | early access queue | 2024-02) keeps CSL availability dates.</p>',
        '<dt>Raman 2025</dt><dd>Submitted Split BibTeX Packet :: 2025-03-11 :: 2024 :: circa :: Date markers: submitted circa (2024~)</dd>',
        '<dt>Doe 2024</dt><dd>Literal Available BibTeX Packet :: early access queue :: 2024-02</dd>',
        '<dt>Smith 2026</dt><dd>Available BibTeX Packet :: 2026-06 :: uncertain :: 2026-05-28 :: Date markers: available-date uncertain (2026-06?) :: Date times: submitted 09:30Z</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX available/submitted date handoff missing expected snippet: ' . $snippet);
        }
    }

    if (strpos($blocks, 'Submitted Split BibTeX Packet') > strpos($blocks, 'Literal Available BibTeX Packet')) {
        throw new RuntimeException('BibTeX available/submitted date handoff did not sort circa submitted year first');
    }
    if (strpos($blocks, 'Literal Available BibTeX Packet') > strpos($blocks, 'Available BibTeX Packet')) {
        throw new RuntimeException('BibTeX available/submitted date handoff did not sort submitted month before split submitted date');
    }

    echo "wordpress-bibtex-csl-available-submitted-date-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
