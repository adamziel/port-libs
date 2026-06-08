<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Field Annotation Review

Annotated source @field-annotations-manual keeps BibLaTeX field annotations visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{field-annotations-manual,
  author        = {Smith, Ada},
  title         = {Field Annotation Review Manual},
  title+an      = {=title verified; source=OCR headline normalized},
  url           = {https://example.test/field-annotation-review},
  url+an:source = {=archived before WordPress import},
  date          = {2026},
  publisher     = {Review Press}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('field-annotations-manual');
    $expected = [
        'title' => [
            ['name' => 'default', 'value' => 'title verified'],
            ['name' => 'source', 'value' => 'OCR headline normalized'],
        ],
        'url' => [
            ['name' => 'source', 'value' => 'archived before WordPress import'],
        ],
    ];

    if (($item['biblatexFieldAnnotations'] ?? null) !== $expected) {
        throw new RuntimeException('BibTeX CSL field-annotation handoff did not preserve normalized annotations');
    }
    if (($item['biblatexFieldAnnotationSummary'] ?? null) !== 'title default: title verified; title source: OCR headline normalized; url source: archived before WordPress import') {
        throw new RuntimeException('BibTeX CSL field-annotation handoff did not preserve the display summary');
    }
    if (($item['raw']['biblatex-field-annotations'] ?? null) !== $expected) {
        throw new RuntimeException('BibTeX CSL field-annotation handoff did not preserve raw CSL metadata');
    }

    foreach ([
        '<p>Annotated source Smith (2026) keeps BibLaTeX field annotations visible.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Field Annotation Review Manual. Review Press, 2026. BibLaTeX field annotations: title default: title verified; title source: OCR headline normalized; url source: archived before WordPress import. https://example.test/field-annotation-review.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL field-annotation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-field-annotations-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
