<?php

declare(strict_types=1);

use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries legacy biblatex name annotations for auxiliary roles and organization organizers' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@proceedings{organization-event,
  title           = {Organization Event Packet},
  date            = {2026},
  organization    = {Board, Program and Desk, Review},
  organization+an = {1=host committee verified; 2:literal=desk name verified}
}

@book{auxiliary-role-annotations,
  author              = {Smith, Ada},
  title               = {Auxiliary Role Annotation Packet},
  date                = {2025},
  editortranslator    = {Garcia, Gia},
  editortranslator+an = {1=dual role verified},
  interviewer         = {Interviewer, Inez},
  interviewer+an:family = {1=interview family verified},
  annotator           = {Annotator, Ana},
  annotator+an        = {1=annotation owner verified},
  afterword           = {Afterword, Ari},
  afterword+an:role   = {1=closing essay verified}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $event = $items['organization-event'];
        $auxiliary = $items['auxiliary-role-annotations'];

        $t->same('Program', $event['event-organizer'][0]['given']);
        $t->same('Board', $event['event-organizer'][0]['family']);
        $t->same('host committee verified', $event['event-organizer'][0]['annotations'][0]['value'] ?? null);
        $t->same('literal', $event['event-organizer'][1]['annotations'][0]['part'] ?? null);
        $t->same('desk name verified', $event['event-organizer'][1]['annotations'][0]['value'] ?? null);
        $t->same(false, isset($event['biblatex-field-annotations']['organization']));

        $t->same('Garcia', $auxiliary['editor-translator'][0]['family']);
        $t->same('dual role verified', $auxiliary['editor-translator'][0]['annotations'][0]['value'] ?? null);
        $t->same('family', $auxiliary['interviewer'][0]['annotations'][0]['part'] ?? null);
        $t->same('interview family verified', $auxiliary['interviewer'][0]['annotations'][0]['value'] ?? null);
        $t->same('annotation owner verified', $auxiliary['annotator'][0]['annotations'][0]['value'] ?? null);
        $t->same('role', $auxiliary['afterword'][0]['annotations'][0]['part'] ?? null);
        $t->same('closing essay verified', $auxiliary['afterword'][0]['annotations'][0]['value'] ?? null);
        $t->same(false, isset($auxiliary['biblatex-field-annotations']['editortranslator']));
        $t->same(false, isset($auxiliary['biblatex-field-annotations']['afterword']));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Name Annotation Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-name-annotation-review</id>
    <updated>2026-07-01T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="event-organizer"/>
        <names variable="editor-translator"/>
        <names variable="interviewer"/>
        <names variable="annotator"/>
        <names variable="afterword"/>
        <text variable="name-annotation-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="event-organizer"/>
      <names variable="editor-translator"/>
      <names variable="interviewer"/>
      <names variable="annotator"/>
      <names variable="afterword"/>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalizedEvent = $styled->item('organization-event');
        $normalizedAuxiliary = $styled->item('auxiliary-role-annotations');
        $t->same('host committee verified', $normalizedEvent['eventOrganizers'][0]['annotations'][0]['value'] ?? null);
        $t->same('desk name verified', $normalizedEvent['eventOrganizers'][1]['annotations'][0]['value'] ?? null);
        $t->same('dual role verified', $normalizedAuxiliary['editorTranslators'][0]['annotations'][0]['value'] ?? null);
        $t->same('interview family verified', $normalizedAuxiliary['interviewers'][0]['annotations'][0]['value'] ?? null);
        $t->same('annotation owner verified', $normalizedAuxiliary['annotators'][0]['annotations'][0]['value'] ?? null);
        $t->same('closing essay verified', $normalizedAuxiliary['afterwordAuthors'][0]['annotations'][0]['value'] ?? null);

        $t->contains('host committee verified', $styled->renderBibliographyEntry('organization-event'));
        $t->contains('desk name verified', $styled->renderBibliographyEntry('organization-event'));
        $t->contains('dual role verified', $styled->renderBibliographyEntry('auxiliary-role-annotations'));
        $t->contains('closing essay verified', $styled->renderBibliographyEntry('auxiliary-role-annotations'));

        $document = (new MarkdownReader())->read('Annotated organizers [@organization-event; @auxiliary-role-annotations] keep review notes.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('host committee verified', $blocks);
        $t->contains('dual role verified', $blocks);
        $t->contains('closing essay verified', $blocks);
    },
];
