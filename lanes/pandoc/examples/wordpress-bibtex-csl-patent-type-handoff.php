<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Patent Type Review

Patent review [@eu-patent-request; @us-import-patent; @custom-patent-type] keeps localized patent type metadata visible.
MARKDOWN;

$bibtex = <<<'BIB'
@patent{eu-patent-request,
  author    = {Ng, Nia},
  title     = {Block Pattern Patent Request},
  date      = {2026},
  type      = {patreqeu},
  number    = {EP-2026-42},
  holder    = {{Review Lab}},
  eventdate = {2026-05-01},
  url       = {https://example.test/patents/ep-2026-42}
}

@patent{us-import-patent,
  author   = {Smith, Ada},
  title    = {Import Matcher Patent},
  date     = {2025},
  type     = {patentus},
  number   = {US-777},
  location = {US},
  status   = {granted}
}

@patent{custom-patent-type,
  author = {Roe, Pat},
  title  = {Custom Type Patent},
  date   = {2024},
  type   = {utility model},
  number = {CA-999}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'eu-patent-request' => ['patreqeu', 'European patent request'],
        'us-import-patent' => ['patentus', 'U.S. patent'],
        'custom-patent-type' => ['utility model', 'Utility model'],
    ] as $id => [$type, $label]) {
        $item = $processor->item($id);
        if (($item['patentType'] ?? null) !== $type) {
            throw new RuntimeException('BibTeX patent type handoff lost patent type for ' . $id);
        }
        if (($item['patentTypeLabel'] ?? null) !== $label) {
            throw new RuntimeException('BibTeX patent type handoff lost patent label for ' . $id);
        }
    }

    foreach ([
        '<p>Patent review (Ng 2026; Smith 2025; Roe 2024) keeps localized patent type metadata visible.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Block Pattern Patent Request. 2026. European patent request EP-2026-42. Holder: Review Lab. Event date 2026-05-01. https://example.test/patents/ep-2026-42.</dd>',
        '<dt>Smith 2025</dt><dd>Smith, Ada. Import Matcher Patent. 2025. U.S. patent US-777. Jurisdiction: US. Status: granted.</dd>',
        '<dt>Roe 2024</dt><dd>Roe, Pat. Custom Type Patent. 2024. Utility model CA-999.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX patent type self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-patent-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
