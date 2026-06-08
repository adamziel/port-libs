<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Date Addendum Review

Date addendum source @date-addon-source keeps BibLaTeX date qualifiers visible.
MARKDOWN;

$bibtex = <<<'BIB'
@online{date-addon-source,
  author        = {Ng, Nia},
  title         = {Date Addendum Source Packet},
  date          = {2026-06-05},
  dateaddon     = {first source capture},
  origdate      = {2020},
  origdateaddon = {legacy packet date},
  publisher     = {Review Press},
  url           = {https://example.test/date-addon-source},
  urldate       = {2026-06-06},
  urldateaddon  = {reviewer accessed archive}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('date-addon-source');
    if (($item['dateAddon'] ?? null) !== 'first source capture') {
        throw new RuntimeException('BibTeX CSL date addendum handoff did not preserve issued date addendum metadata');
    }
    if (($item['originalDateAddon'] ?? null) !== 'legacy packet date') {
        throw new RuntimeException('BibTeX CSL date addendum handoff did not preserve original date addendum metadata');
    }
    if (($item['accessedDateAddon'] ?? null) !== 'reviewer accessed archive') {
        throw new RuntimeException('BibTeX CSL date addendum handoff did not preserve accessed date addendum metadata');
    }
    if (($item['raw']['date-addon'] ?? null) !== 'first source capture') {
        throw new RuntimeException('BibTeX CSL date addendum handoff did not preserve raw CSL metadata');
    }

    foreach ([
        '<p>Date addendum source Ng (2026) keeps BibLaTeX date qualifiers visible.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Date Addendum Source Packet. Review Press, 2026. Date addendum: first source capture. Original date addendum: legacy packet date. Accessed date addendum: reviewer accessed archive. Original work published 2020. https://example.test/date-addon-source. Accessed 2026-06-06.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL date addendum self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-date-addon-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
