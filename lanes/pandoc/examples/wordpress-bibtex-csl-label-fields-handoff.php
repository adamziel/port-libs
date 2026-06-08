<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Label Field Review

Label metadata @label-field-source keeps imported disambiguation fields visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{label-field-source,
  author     = {Smith, Ada},
  title      = {Migration Label Packet},
  date       = {2026},
  publisher  = {Review Press},
  labelalpha = {Smi26},
  labeltitle = {migration label packet},
  extradate  = {2},
  extratitle = {a}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('label-field-source');
    if (($item['labelAlpha'] ?? null) !== 'Smi26') {
        throw new RuntimeException('BibTeX CSL label-field handoff did not preserve labelalpha');
    }
    if (($item['labelTitle'] ?? null) !== 'migration label packet') {
        throw new RuntimeException('BibTeX CSL label-field handoff did not preserve labeltitle');
    }
    if (($item['extraDate'] ?? null) !== '2' || ($item['extraTitle'] ?? null) !== 'a') {
        throw new RuntimeException('BibTeX CSL label-field handoff did not preserve extra date/title metadata');
    }
    if (($item['raw']['label-alpha'] ?? null) !== 'Smi26') {
        throw new RuntimeException('BibTeX CSL label-field handoff did not preserve raw CSL label-alpha metadata');
    }

    foreach ([
        '<p>Label metadata Smith (2026) keeps imported disambiguation fields visible.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Migration Label Packet. Review Press, 2026. Label alpha: Smi26. Label title: migration label packet. Extra date: 2. Extra title: a.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL label-field self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-label-fields-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
