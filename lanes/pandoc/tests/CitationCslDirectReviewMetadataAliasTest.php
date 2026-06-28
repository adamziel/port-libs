<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'normalizes direct csl json review title metadata aliases' => static function (TestRunner $t): void {
        $citation = static fn (string $id): AstNode => new AstNode('citation', [
            'id' => $id,
            'text' => '[@' . $id . ']',
        ]);
        $json = json_encode([
            [
                'id' => 'direct-review-title-hyphen',
                'type' => 'review-book',
                'title' => 'Direct Review Title Hyphen Packet',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'review-title' => 'Migration Source Manual',
                'review-subtitle' => 'Reviewer Appendix',
                'review-genre' => 'archive guide',
            ],
            [
                'id' => 'direct-review-title-camel',
                'type' => 'review-book',
                'title' => 'Direct Review Title Camel Packet',
                'author' => [
                    ['family' => 'Roe', 'given' => 'Ren'],
                ],
                'issued' => ['date-parts' => [[2025]]],
                'reviewTitle' => 'Source Audit Handbook',
                'reviewSubtitle' => 'Field Notes',
                'reviewGenre' => 'migration packet',
            ],
        ], JSON_THROW_ON_ERROR);

        $processor = CitationCslProcessor::fromJson($json);
        $hyphen = $processor->item('direct-review-title-hyphen');
        $camel = $processor->item('direct-review-title-camel');

        $t->same('Migration Source Manual: Reviewer Appendix', $hyphen['reviewedTitle'] ?? null);
        $t->same('archive guide', $hyphen['reviewedGenre'] ?? null);
        $t->same('Source Audit Handbook: Field Notes', $camel['reviewedTitle'] ?? null);
        $t->same('migration packet', $camel['reviewedGenre'] ?? null);
        $t->same('Migration Source Manual', $hyphen['raw']['review-title'] ?? null);
        $t->same('Field Notes', $camel['raw']['reviewSubtitle'] ?? null);
        $t->same(
            'Ng, Nia. Direct Review Title Hyphen Packet. 2026. Reviewed title: Migration Source Manual: Reviewer Appendix. Reviewed genre: archive guide.',
            $processor->renderBibliographyEntry('direct-review-title-hyphen')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Direct CSL Review Metadata Alias Review</title>
    <id>https://example.test/styles/bounded-direct-csl-review-metadata-alias-review</id>
    <updated>2026-06-28T23:50:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="review-title"/>
        <text variable="reviewed-title"/>
        <text variable="review-genre"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="review-title"/>
      <text variable="reviewed-title"/>
      <text variable="review-genre"/>
      <text variable="reviewed-genre"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $children = $summary['citationRendering'][0]['children'] ?? [];

        $t->same('Bounded Direct CSL Review Metadata Alias Review', $summary['title'] ?? null);
        $t->same('review-title', $children[1]['variable'] ?? null);
        $t->same('reviewed-title', $children[2]['variable'] ?? null);
        $t->same('review-genre', $children[3]['variable'] ?? null);
        $t->same(
            '[Ng | Migration Source Manual: Reviewer Appendix | Migration Source Manual: Reviewer Appendix | archive guide; Roe | Source Audit Handbook: Field Notes | Source Audit Handbook: Field Notes | migration packet]',
            $styled->renderCitationCluster([
                $citation('direct-review-title-hyphen'),
                $citation('direct-review-title-camel'),
            ])
        );
        $t->same(
            'Direct Review Title Hyphen Packet :: Migration Source Manual: Reviewer Appendix :: Migration Source Manual: Reviewer Appendix :: archive guide :: archive guide',
            $styled->renderBibliographyEntry('direct-review-title-hyphen')
        );

        $document = (new MarkdownReader())->read('Review title aliases [@direct-review-title-hyphen; @direct-review-title-camel] stay visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review title aliases [Ng | Migration Source Manual: Reviewer Appendix | Migration Source Manual: Reviewer Appendix | archive guide; Roe | Source Audit Handbook: Field Notes | Source Audit Handbook: Field Notes | migration packet] stay visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Direct Review Title Hyphen Packet :: Migration Source Manual: Reviewer Appendix :: Migration Source Manual: Reviewer Appendix :: archive guide :: archive guide</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Direct Review Title Camel Packet :: Source Audit Handbook: Field Notes :: Source Audit Handbook: Field Notes :: migration packet :: migration packet</dd>', $blocks);
    },
];
