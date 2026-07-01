<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps biblatex attachment aliases through source file policy metadata' =>
        static function (TestRunner $t): void {
            $source = <<<'BIB'
@online{attachment-alias-source,
  author       = {Ng, Nia},
  title        = {Attachment Alias Source},
  date         = {2026-07-01},
  attachments  = {Review PDF:attachments/review%20packet.pdf:application/pdf; Remote PDF:https://example.test/review.pdf:application/pdf},
  source-file  = {Source HTML:attachments/source.html:text/html},
  source-files = {Figure:media/figure.png:image/png}
}

@report{files-alias-source,
  author             = {Roe, Pat},
  title              = {Files Alias Source},
  date               = {2025},
  files              = {Notes:notes/review.txt:text/plain},
  sourceattachments  = {Mirror PDF:attachments/mirror.pdf:application/pdf; Traversal PDF:../private/mirror.pdf:application/pdf}
}
BIB;

            $processor = new BibtexCslProcessor();
            $items = $processor->cslItems($source);
            $attachment = $items['attachment-alias-source'];
            $files = $items['files-alias-source'];

            $t->same([
                ['label' => 'Review PDF', 'path' => 'attachments/review packet.pdf', 'mediaType' => 'application/pdf'],
                ['label' => 'Source HTML', 'path' => 'attachments/source.html', 'mediaType' => 'text/html'],
                ['label' => 'Figure', 'path' => 'media/figure.png', 'mediaType' => 'image/png'],
            ], $attachment['sourceFiles'] ?? null);
            $t->same(['remote-uri'], array_column($attachment['sourceFileDiagnostics'] ?? [], 'reason'));
            $t->same('Remote PDF', $attachment['sourceFileDiagnostics'][0]['label'] ?? null);
            $t->same('https://example.test/review.pdf', $attachment['sourceFileDiagnostics'][0]['path'] ?? null);
            $t->same([
                ['label' => 'Notes', 'path' => 'notes/review.txt', 'mediaType' => 'text/plain'],
                ['label' => 'Mirror PDF', 'path' => 'attachments/mirror.pdf', 'mediaType' => 'application/pdf'],
            ], $files['sourceFiles'] ?? null);
            $t->same(['path-traversal'], array_column($files['sourceFileDiagnostics'] ?? [], 'reason'));
            $t->same('Traversal PDF', $files['sourceFileDiagnostics'][0]['label'] ?? null);
            $t->same('Review PDF:attachments/review%20packet.pdf:application/pdf; Remote PDF:https://example.test/review.pdf:application/pdf', $attachment['rawBibtex']['fields']['attachments'] ?? null);
            $t->same('Source HTML:attachments/source.html:text/html', $attachment['rawBibtex']['fields']['source-file'] ?? null);
            $t->same('Notes:notes/review.txt:text/plain', $files['rawBibtex']['fields']['files'] ?? null);
            $t->same('Mirror PDF:attachments/mirror.pdf:application/pdf; Traversal PDF:../private/mirror.pdf:application/pdf', $files['rawBibtex']['fields']['sourceattachments'] ?? null);

            $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded BibLaTeX Attachment Alias Review</title>
    <id>https://example.test/styles/bounded-biblatex-attachment-alias-review</id>
    <updated>2026-07-01T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="source-file-summary"/>
        <text variable="source-file-diagnostic-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="source-file-labels"/>
      <text variable="source-file-paths"/>
      <text variable="source-file-diagnostic-reasons"/>
    </layout>
  </bibliography>
</style>
XML);

            $t->same('Bounded BibLaTeX Attachment Alias Review', $styled->cslStyleSummary()['title'] ?? null);
            $t->same('[Attachment Alias Source | Review PDF: attachments/review packet.pdf (application/pdf); Source HTML: attachments/source.html (text/html); Figure: media/figure.png (image/png) | Remote PDF: remote-uri (https://example.test/review.pdf); Files Alias Source | Notes: notes/review.txt (text/plain); Mirror PDF: attachments/mirror.pdf (application/pdf) | Traversal PDF: path-traversal (../private/mirror.pdf)]', $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'attachment-alias-source', 'text' => '[@attachment-alias-source]']),
                new AstNode('citation', ['id' => 'files-alias-source', 'text' => '[@files-alias-source]']),
            ]));
            $t->same('Attachment Alias Source :: Review PDF; Source HTML; Figure :: attachments/review packet.pdf; attachments/source.html; media/figure.png :: remote-uri', $styled->renderBibliographyEntry('attachment-alias-source'));
            $t->same('Files Alias Source :: Notes; Mirror PDF :: notes/review.txt; attachments/mirror.pdf :: path-traversal', $styled->renderBibliographyEntry('files-alias-source'));

            $document = (new MarkdownReader())->read('Attachment aliases [@attachment-alias-source; @files-alias-source] keep exported file fields visible.');
            $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
            $handoff = $processor->citationHandoff($document, $source);

            $t->same(['attachment-alias-source', 'files-alias-source'], $handoff['citedKeys']);
            $t->same('attachments/review packet.pdf', $handoff['items'][0]['sourceFiles'][0]['path'] ?? null);
            $t->same('attachments/mirror.pdf', $handoff['items'][1]['sourceFiles'][1]['path'] ?? null);
            $t->same('path-traversal', $handoff['items'][1]['sourceFileDiagnostics'][0]['reason'] ?? null);
            $t->contains('<p>Attachment aliases [Attachment Alias Source | Review PDF: attachments/review packet.pdf (application/pdf); Source HTML: attachments/source.html (text/html); Figure: media/figure.png (image/png) | Remote PDF: remote-uri (https://example.test/review.pdf); Files Alias Source | Notes: notes/review.txt (text/plain); Mirror PDF: attachments/mirror.pdf (application/pdf) | Traversal PDF: path-traversal (../private/mirror.pdf)] keep exported file fields visible.</p>', $blocks);
            $t->contains('<dt>Ng 2026</dt><dd>Attachment Alias Source :: Review PDF; Source HTML; Figure :: attachments/review packet.pdf; attachments/source.html; media/figure.png :: remote-uri</dd>', $blocks);
            $t->contains('<dt>Roe 2025</dt><dd>Files Alias Source :: Notes; Mirror PDF :: notes/review.txt; attachments/mirror.pdf :: path-traversal</dd>', $blocks);
        },
];
