<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Xdata Provenance Review

Source @xdata-provenance-source keeps inherited packet provenance visible for review.
MARKDOWN;

$bibtex = <<<'BIB'
@xdata{shared-review-packet,
  title     = {Shared Review Packet},
  publisher = {Migration Desk},
  date      = {2026-06-05}
}

@xdata{attachment-review-packet,
  title = {Attachment Review Packet},
  file  = {Review PDF:attachments/source-audit.pdf:application/pdf}
}

@online{xdata-provenance-source,
  author = {Ng, Nia},
  title  = {Xdata Provenance Source},
  url    = {https://example.test/xdata-provenance},
  xdata  = {shared-review-packet, attachment-review-packet, missing-xdata-packet}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('xdata-provenance-source');
    if (($item['xdataKeys'] ?? null) !== ['shared-review-packet', 'attachment-review-packet', 'missing-xdata-packet']) {
        throw new RuntimeException('BibTeX CSL xdata provenance handoff did not preserve xdata key order');
    }
    if (($item['xdataItems'][0]['title'] ?? null) !== 'Shared Review Packet') {
        throw new RuntimeException('BibTeX CSL xdata provenance handoff did not preserve known xdata summaries');
    }
    if (($item['missingXdataKeys'] ?? null) !== ['missing-xdata-packet']) {
        throw new RuntimeException('BibTeX CSL xdata provenance handoff did not preserve missing xdata keys');
    }
    if (($item['xdataSummary'] ?? null) !== 'Shared Review Packet (2026-06-05); Attachment Review Packet; missing: missing-xdata-packet') {
        throw new RuntimeException('BibTeX CSL xdata provenance handoff did not expose the expected summary');
    }

    foreach ([
        '<p>Source Ng (2026) keeps inherited packet provenance visible for review.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Xdata Provenance Source. Migration Desk, 2026. Xdata packets: Shared Review Packet (2026-06-05); Attachment Review Packet; missing: missing-xdata-packet. https://example.test/xdata-provenance.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL xdata provenance self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-xdata-provenance-handoff self-test passed\n";
    exit(0);
}

echo $blocks;
