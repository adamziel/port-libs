<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Camel Alias Review

Camel alias sources [@camel-chapter; @camel-article] preserve direct publication metadata.
MARKDOWN;

$items = [
    [
        'id' => 'camel-chapter',
        'type' => 'chapter',
        'title' => 'Direct Alias Chapter',
        'author' => [
            ['family' => 'Smith', 'given' => 'Ada'],
        ],
        'issued' => ['date-parts' => [[2026]]],
        'titleAddon' => 'review copy',
        'containerTitle' => 'Migration Handbook',
        'containerTitleAddon' => 'editor packet',
        'publisherPlace' => 'Portland',
    ],
    [
        'id' => 'camel-article',
        'type' => 'article-journal',
        'title' => 'Direct Alias Article',
        'author' => [
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'issued' => ['date-parts' => [[2025]]],
        'titleAddon' => 'metadata appendix',
        'containerTitle' => 'Review Journal',
        'containerTitleAddon' => 'online packet',
        'publisherPlace' => 'Remote',
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Camel Alias Review</title>
    <id>https://example.test/styles/wordpress-citation-camel-alias-review</id>
    <updated>2026-06-09T06:16:50+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="title-addon"/>
        <text variable="container-title"/>
        <text variable="container-title-addon"/>
        <text variable="publisher-place"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="title-addon"/>
      <text variable="container-title"/>
      <text variable="container-title-addon"/>
      <text variable="publisher-place"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($styleXml);
$document = (new MarkdownReader())->read($markdown);
$blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));

if (($argv[1] ?? '') === '--self-test') {
    $chapter = $processor->item('camel-chapter');
    if (($chapter['titleAddon'] ?? null) !== 'review copy') {
        throw new RuntimeException('CSL camel alias handoff did not preserve titleAddon metadata');
    }
    if (($chapter['containerTitle'] ?? null) !== 'Migration Handbook') {
        throw new RuntimeException('CSL camel alias handoff did not preserve containerTitle metadata');
    }
    if (($chapter['containerTitleAddon'] ?? null) !== 'editor packet') {
        throw new RuntimeException('CSL camel alias handoff did not preserve containerTitleAddon metadata');
    }
    if (($chapter['publisherPlace'] ?? null) !== 'Portland') {
        throw new RuntimeException('CSL camel alias handoff did not preserve publisherPlace metadata');
    }

    foreach ([
        '<p>Camel alias sources (Smith | review copy | Migration Handbook | editor packet | Portland; Ng | metadata appendix | Review Journal | online packet | Remote) preserve direct publication metadata.</p>',
        '<dt>Smith 2026</dt><dd>Direct Alias Chapter :: review copy :: Migration Handbook :: editor packet :: Portland</dd>',
        '<dt>Ng 2025</dt><dd>Direct Alias Article :: metadata appendix :: Review Journal :: online packet :: Remote</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL camel alias handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-camel-alias-handoff self-test passed\n";
    return;
}

echo $blocks;
