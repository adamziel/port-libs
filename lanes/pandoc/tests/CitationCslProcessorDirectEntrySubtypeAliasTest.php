<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'normalizes direct csl entry subtype aliases into genre metadata' => static function (TestRunner $t): void {
        $json = json_encode([
            [
                'id' => 'direct-entrysubtype-compact',
                'type' => 'report',
                'title' => 'Direct Compact Entry Subtype Packet',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'entrysubtype' => 'migration source audit',
            ],
            [
                'id' => 'direct-entry-subtype-explicit',
                'type' => 'webpage',
                'title' => 'Direct Explicit Genre Packet',
                'author' => [
                    ['literal' => 'Review Desk'],
                ],
                'issued' => ['date-parts' => [[2025]]],
                'genre' => 'source packet',
                'entry-subtype' => 'review snapshot',
            ],
        ], JSON_THROW_ON_ERROR);

        $processor = CitationCslProcessor::fromJson($json);
        $compact = $processor->item('direct-entrysubtype-compact');
        $explicit = $processor->item('direct-entry-subtype-explicit');

        $t->same('migration source audit', $compact['genre'] ?? null);
        $t->same('migration source audit', $compact['entrySubtype'] ?? null);
        $t->same('source packet', $explicit['genre'] ?? null);
        $t->same('review snapshot', $explicit['entrySubtype'] ?? null);
        $t->same('migration source audit', $compact['raw']['entrysubtype'] ?? null);
        $t->same('review snapshot', $explicit['raw']['entry-subtype'] ?? null);

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Direct CSL Entry Subtype Alias Review</title>
    <id>https://example.test/styles/bounded-direct-csl-entry-subtype-alias-review</id>
    <updated>2026-07-01T19:35:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="genre"/>
        <text variable="entry-subtype"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="genre"/>
      <text variable="entry-subtype"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
        $t->same('Bounded Direct CSL Entry Subtype Alias Review', $summary['title'] ?? null);
        $t->same('genre', $citationChildren[1]['variable'] ?? null);
        $t->same('entry-subtype', $citationChildren[2]['variable'] ?? null);
        $t->same('[Ng | migration source audit | migration source audit; Review Desk | source packet | review snapshot]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'direct-entrysubtype-compact', 'text' => '[@direct-entrysubtype-compact]']),
            new AstNode('citation', ['id' => 'direct-entry-subtype-explicit', 'text' => '[@direct-entry-subtype-explicit]']),
        ]));
        $t->same('Direct Compact Entry Subtype Packet :: migration source audit :: migration source audit', $styled->renderBibliographyEntry('direct-entrysubtype-compact'));
        $t->same('Direct Explicit Genre Packet :: source packet :: review snapshot', $styled->renderBibliographyEntry('direct-entry-subtype-explicit'));

        $document = (new MarkdownReader())->read('Direct entry subtype aliases [@direct-entrysubtype-compact; @direct-entry-subtype-explicit] stay reviewable.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>Direct entry subtype aliases [Ng | migration source audit | migration source audit; Review Desk | source packet | review snapshot] stay reviewable.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Direct Compact Entry Subtype Packet :: migration source audit :: migration source audit</dd>', $blocks);
        $t->contains('<dt>Review Desk 2025</dt><dd>Direct Explicit Genre Packet :: source packet :: review snapshot</dd>', $blocks);
    },
];
