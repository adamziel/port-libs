<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Named Name Annotation Review

Named annotation source @name-annotation-suffix-review keeps scoped name review notes visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{name-annotation-suffix-review,
  author           = {Smith, Ada and Ng, Nia},
  author+an:source = {1=OCR family verified; 2:given=review desk confirmed},
  editor           = {Curator, Eli},
  editor+an:role   = {1=import reviewer},
  title            = {Named Annotation Source},
  date             = {2026},
  publisher        = {Review Press}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('name-annotation-suffix-review');
    $expectedSummary = 'Author 1 source: OCR family verified; Author 2 given: review desk confirmed; Editor 1 role: import reviewer';

    if (($item['authors'][0]['annotations'][0]['part'] ?? null) !== 'source') {
        throw new RuntimeException('BibTeX CSL named name-annotation handoff did not preserve author suffix annotation part');
    }
    if (($item['authors'][1]['annotations'][0]['part'] ?? null) !== 'given') {
        throw new RuntimeException('BibTeX CSL named name-annotation handoff did not let per-entry name parts override the field suffix');
    }
    if (($item['editors'][0]['annotations'][0]['value'] ?? null) !== 'import reviewer') {
        throw new RuntimeException('BibTeX CSL named name-annotation handoff did not preserve editor annotation value');
    }
if ($processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <text variable="title"/>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML)->renderBibliographyEntry('name-annotation-suffix-review') !== $expectedSummary) {
        throw new RuntimeException('BibTeX CSL named name-annotation handoff did not expose the CSL name-annotation-summary variable');
    }

    foreach ([
        '<p>Named annotation source Smith and Ng (2026) keeps scoped name review notes visible.</p>',
        '<dt>Smith and Ng 2026</dt><dd>Smith, Ada; Ng, Nia. Named Annotation Source. Review Press, 2026. Name annotations: Author 1 source: OCR family verified; Author 2 given: review desk confirmed; Editor 1 role: import reviewer.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL named name-annotation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-name-annotation-suffix-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
