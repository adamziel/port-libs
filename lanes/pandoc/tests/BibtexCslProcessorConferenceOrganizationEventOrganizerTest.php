<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries conference organization as event organizer in legacy biblatex handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@inproceedings{org-proceedings,
  author          = {Speaker, Sam},
  title           = {Organization Fallback Paper},
  booktitle       = {Proceedings of Migration Review},
  organization    = {Program Committee and Review Board},
  organization+an = {1=organization promoted to organizer},
  venue           = {Remote Hall},
  date            = {2026},
  pages           = {21--24}
}

@misc{org-publisher,
  title        = {Archive Publisher Packet},
  organization = {Archive Publisher},
  date         = {2025}
}
BIB;

        $directItems = CitationCslProcessor::bibtexItems($source);
        $t->same(2, count($directItems));
        $t->same('org-proceedings', $directItems[0]['id'] ?? null);
        $t->same('Committee', $directItems[0]['event-organizer'][0]['family'] ?? null);
        $t->same('Program', $directItems[0]['event-organizer'][0]['given'] ?? null);
        $t->same('organization promoted to organizer', $directItems[0]['event-organizer'][0]['annotations'][0]['value'] ?? null);
        $t->same('Board', $directItems[0]['event-organizer'][1]['family'] ?? null);
        $t->same(null, $directItems[1]['event-organizer'] ?? null);
        $t->same('Archive Publisher', $directItems[1]['publisher'] ?? null);

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $conference = $items['org-proceedings'];
        $publisher = $items['org-publisher'];

        $t->same('paper-conference', $conference['type']);
        $t->same('Committee', $conference['event-organizer'][0]['family'] ?? null);
        $t->same('Program', $conference['event-organizer'][0]['given'] ?? null);
        $t->same('organization promoted to organizer', $conference['event-organizer'][0]['annotations'][0]['value'] ?? null);
        $t->same('Board', $conference['event-organizer'][1]['family'] ?? null);
        $t->same('Program Committee and Review Board', $conference['rawBibtex']['fields']['organization'] ?? null);
        $t->same(false, isset($publisher['event-organizer']));
        $t->same('Archive Publisher', $publisher['publisher']);

        $citationProcessor = CitationCslProcessor::fromItems(array_values($items));
        $normalizedConference = $citationProcessor->item('org-proceedings');
        $normalizedPublisher = $citationProcessor->item('org-publisher');
        $t->same('Committee', $normalizedConference['eventOrganizers'][0]['family'] ?? null);
        $t->same('organization promoted to organizer', $normalizedConference['eventOrganizers'][0]['annotations'][0]['value'] ?? null);
        $t->same([], $normalizedPublisher['eventOrganizers'] ?? null);

        $styled = $citationProcessor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded BibLaTeX Organization Event Organizer Review</title>
    <id>https://example.test/styles/bounded-biblatex-organization-event-organizer-review</id>
    <updated>2026-07-01T18:20:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="event-organizer"/>
        <text variable="publisher"/>
        <text variable="title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="event-organizer"/>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Bounded BibLaTeX Organization Event Organizer Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Committee and Board | Program Committee and Review Board | Organization Fallback Paper; Archive Publisher | Archive Publisher Packet]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'org-proceedings', 'text' => '[@org-proceedings]']),
            new AstNode('citation', ['id' => 'org-publisher', 'text' => '[@org-publisher]']),
        ]));
        $t->same('Organization Fallback Paper :: Committee, Program; Board, Review :: Event organizer 1: organization promoted to organizer', $styled->renderBibliographyEntry('org-proceedings'));

        $document = (new MarkdownReader())->read('Organization fallback [@org-proceedings; @org-publisher] stays reviewable.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['org-proceedings', 'org-publisher'], $handoff['citedKeys']);
        $t->same('Committee', $handoff['items'][0]['event-organizer'][0]['family'] ?? null);
        $t->same(false, isset($handoff['items'][1]['event-organizer']));
        $t->contains('<p>Organization fallback [Committee and Board | Program Committee and Review Board | Organization Fallback Paper; Archive Publisher | Archive Publisher Packet] stays reviewable.</p>', $blocks);
        $t->contains('<dt>Speaker 2026</dt><dd>Organization Fallback Paper :: Committee, Program; Board, Review :: Event organizer 1: organization promoted to organizer</dd>', $blocks);
    },
];
