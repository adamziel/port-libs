<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX CSL Creator Role Review

Imported bibliography [@participant-fields-source; @editorial-fields-source] keeps direct CSL participant roles visible.
MARKDOWN;

$bibtex = <<<'BIB'
@misc{participant-fields-source,
  title = {Participant Fields Packet},
  date = {2026},
  chair = {{Program Committee}},
  collectioneditor = {Curator, Eli},
  composer = {Morton, Mia},
  contributor = {{Migration Contributors}},
  editortranslator = {Garcia, Gia},
  recipient = {Reader, Rhea},
  chair+an = {1=agenda verified},
  recipient+an:family = {1=recipient family verified}
}

@book{editorial-fields-source,
  title = {Editorial Fields Packet},
  date = {2025},
  compiler = {Roe, Pat and {{Migration Desk}}},
  curator = {Curator, Eli},
  editorialdirector = {Editorial, Eden},
  illustrator = {Illustrator, Iris},
  interviewer = {Interviewer, Inez},
  reviewedauthor = {Reviewed, Riley},
  reviewedauthor+an = {1=review context verified}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress BibTeX CSL Direct Creator Role Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-direct-creator-role-review</id>
    <updated>2026-06-09T01:49:07+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="chair"/>
        <names variable="collection-editor"/>
        <names variable="composer"/>
        <names variable="contributor"/>
        <names variable="editor-translator"/>
        <names variable="recipient"/>
        <names variable="compiler"/>
        <names variable="curator"/>
        <names variable="editorial-director"/>
        <names variable="illustrator"/>
        <names variable="interviewer"/>
        <names variable="reviewed-author"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="chair"/>
      <names variable="collection-editor"/>
      <names variable="composer"/>
      <names variable="contributor"/>
      <names variable="editor-translator"/>
      <names variable="recipient"/>
      <names variable="compiler"/>
      <names variable="curator"/>
      <names variable="editorial-director"/>
      <names variable="illustrator"/>
      <names variable="interviewer"/>
      <names variable="reviewed-author"/>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $participant = $processor->item('participant-fields-source');
    if (($participant['chairs'][0]['literal'] ?? null) !== 'Program Committee') {
        throw new RuntimeException('BibTeX direct creator role handoff did not preserve chair literal');
    }
    if (($participant['collectionEditors'][0]['family'] ?? null) !== 'Curator') {
        throw new RuntimeException('BibTeX direct creator role handoff did not preserve collection editor');
    }
    if (($participant['recipients'][0]['annotations'][0]['part'] ?? null) !== 'family') {
        throw new RuntimeException('BibTeX direct creator role handoff did not preserve recipient annotation part');
    }

    $editorial = $processor->item('editorial-fields-source');
    if (($editorial['compilers'][1]['literal'] ?? null) !== 'Migration Desk') {
        throw new RuntimeException('BibTeX direct creator role handoff did not preserve compiler literal');
    }
    if (($editorial['editorialDirectors'][0]['family'] ?? null) !== 'Editorial') {
        throw new RuntimeException('BibTeX direct creator role handoff did not preserve editorial director alias');
    }
    if (($editorial['reviewedAuthors'][0]['annotations'][0]['value'] ?? null) !== 'review context verified') {
        throw new RuntimeException('BibTeX direct creator role handoff did not preserve reviewed-author annotation');
    }

    foreach ([
        '<p>Imported bibliography (Program Committee | Curator | Morton | Migration Contributors | Garcia | Reader; Roe and Migration Desk | Curator | Editorial | Illustrator | Interviewer | Reviewed) keeps direct CSL participant roles visible.</p>',
        '<dt>Participant Fields Packet 2026</dt><dd>Participant Fields Packet :: Program Committee :: Curator, Eli :: Morton, Mia :: Migration Contributors :: Garcia, Gia :: Reader, Rhea :: Chair 1: agenda verified; Recipient 1 family: recipient family verified</dd>',
        '<dt>Editorial Fields Packet 2025</dt><dd>Editorial Fields Packet :: Roe, Pat; Migration Desk :: Curator, Eli :: Editorial, Eden :: Illustrator, Iris :: Interviewer, Inez :: Reviewed, Riley :: Reviewed author 1: review context verified</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX direct creator role self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-direct-creator-roles-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
