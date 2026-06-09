<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Direct extended source [@direct-extended-roles] preserves direct revision creator fields.';

$bibtex = <<<'BIB'
@collection{direct-extended-roles,
  author       = {Smith, Ada},
  title        = {Direct Extended Role Packet},
  date         = {2026},
  publisher    = {Review Press},
  redactor     = {Roe, Pat and {{Migration Desk}}},
  founder      = {{Founding Review Board}},
  continuator  = {Ng, Nia},
  reviser      = {Curator, Eli},
  collaborator = {{Source Review Desk}},
  redactor+an  = {1=redaction verified; 2:name=literal redactor verified},
  founder+an:role = {1=founding source owner},
  collaborator+an = {1=collaboration queue verified}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded BibTeX Direct Extended Creator Review Style</title>
    <id>https://example.test/styles/bounded-bibtex-direct-extended-creator-review</id>
    <updated>2026-06-09T02:08:11+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="redactor"/>
        <names variable="founder"/>
        <names variable="continuator"/>
        <names variable="reviser"/>
        <names variable="collaborator"/>
        <text variable="name-annotation-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="redactor"/>
      <names variable="founder"/>
      <names variable="continuator"/>
      <names variable="reviser"/>
      <names variable="collaborator"/>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('direct-extended-roles');
    if (($item['redactors'][0]['family'] ?? null) !== 'Roe') {
        throw new RuntimeException('Direct BibLaTeX redactor field did not reach CSL metadata');
    }
    if (($item['founders'][0]['literal'] ?? null) !== 'Founding Review Board') {
        throw new RuntimeException('Direct BibLaTeX founder field did not reach CSL metadata');
    }
    if (($item['continuators'][0]['family'] ?? null) !== 'Ng') {
        throw new RuntimeException('Direct BibLaTeX continuator field did not reach CSL metadata');
    }
    if (($item['revisers'][0]['family'] ?? null) !== 'Curator') {
        throw new RuntimeException('Direct BibLaTeX reviser field did not reach CSL metadata');
    }
    if (($item['collaborators'][0]['literal'] ?? null) !== 'Source Review Desk') {
        throw new RuntimeException('Direct BibLaTeX collaborator field did not reach CSL metadata');
    }

    $annotationSummary = 'Redactor 1: redaction verified; Redactor 2: literal redactor verified; Founder 1 role: founding source owner; Collaborator 1: collaboration queue verified';
    foreach ([
        '<p>Direct extended source [Roe and Migration Desk | Founding Review Board | Ng | Curator | Source Review Desk | ' . $annotationSummary . '] preserves direct revision creator fields.</p>',
        '<dt>Smith 2026</dt><dd>Direct Extended Role Packet :: Roe, Pat; Migration Desk :: Founding Review Board :: Ng, Nia :: Curator, Eli :: Source Review Desk :: ' . $annotationSummary . '</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX direct extended creator handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-direct-extended-creator-fields self-test passed\n";
    return;
}

echo $blocks . "\n";
