<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Manual Booklet Type Review

Manual and booklet imports [@migration-manual; @review-booklet] keep type conditionals stable.
MARKDOWN;

$bibtex = <<<'BIB'
@manual{migration-manual,
  author       = {{Migration Review Desk}},
  title        = {Migration Import Manual},
  date         = {2026},
  organization = {Migration Review Desk},
  address      = {Portland},
  edition      = {2nd},
  url          = {https://example.test/manual}
}

@booklet{review-booklet,
  author       = {Ng, Nia},
  title        = {Reviewer Booklet},
  date         = {2025},
  address      = {Remote review packet},
  howpublished = {Stapled migration handout},
  note         = {Includes legacy captions}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Manual Booklet Type Review</title>
    <id>https://example.test/styles/bounded-manual-booklet-type-review</id>
    <updated>2026-06-09T04:12:10+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <choose>
        <if type="book">
          <group delimiter=" | ">
            <text value="manual"/>
            <text variable="title"/>
            <text variable="publisher"/>
          </group>
        </if>
        <else-if type="pamphlet">
          <group delimiter=" | ">
            <text value="booklet"/>
            <text variable="title"/>
            <text variable="medium"/>
          </group>
        </else-if>
        <else>
          <text value="fallback"/>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <choose>
        <if type="book">
          <group delimiter=" :: ">
            <text value="manual"/>
            <text variable="title"/>
            <text variable="publisher"/>
            <text variable="publisher-place"/>
            <text variable="edition"/>
          </group>
        </if>
        <else-if type="pamphlet">
          <group delimiter=" :: ">
            <text value="booklet"/>
            <text variable="title"/>
            <text variable="publisher-place"/>
            <text variable="medium"/>
            <text variable="note"/>
          </group>
        </else-if>
      </choose>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $manual = $processor->item('migration-manual');
    $booklet = $processor->item('review-booklet');
    $summary = $processor->cslStyleSummary();

    if (($manual['type'] ?? null) !== 'book') {
        throw new RuntimeException('BibTeX CSL manual/booklet handoff did not map @manual to the CSL book branch');
    }
    if (($booklet['type'] ?? null) !== 'pamphlet') {
        throw new RuntimeException('BibTeX CSL manual/booklet handoff did not map @booklet to the CSL pamphlet branch');
    }
    if (($summary['citationRendering'][0]['branches'][0]['types'] ?? null) !== ['book']) {
        throw new RuntimeException('BibTeX CSL manual/booklet handoff did not preserve the book type condition');
    }
    if (($summary['citationRendering'][0]['branches'][1]['types'] ?? null) !== ['pamphlet']) {
        throw new RuntimeException('BibTeX CSL manual/booklet handoff did not preserve the pamphlet type condition');
    }

    foreach ([
        '<p>Manual and booklet imports [manual | Migration Import Manual | Migration Review Desk; booklet | Reviewer Booklet | Stapled migration handout] keep type conditionals stable.</p>',
        '<dt>Migration Review Desk 2026</dt><dd>manual :: Migration Import Manual :: Migration Review Desk :: Portland :: 2nd</dd>',
        '<dt>Ng 2025</dt><dd>booklet :: Reviewer Booklet :: Remote review packet :: Stapled migration handout :: Includes legacy captions</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL manual/booklet type self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-manual-booklet-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
