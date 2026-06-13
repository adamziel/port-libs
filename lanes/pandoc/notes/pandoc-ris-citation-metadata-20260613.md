# Pandoc RIS Citation Metadata Handoff

Slice: `pandoc-ris-citation-metadata-20260613`

## Behavior

`CitationCslProcessor::risItems()` now preserves bounded RIS review metadata:

- `CN` maps to CSL `call-number`;
- `NV` maps to CSL `number-of-volumes`;
- `TT` maps to CSL `translated-title`;
- `OP` maps to CSL `original-title`;
- `RI` maps to CSL `reviewed-title`.

The focused test starts from RIS source text and verifies raw extracted items,
normalized CSL item fields, CSL text-variable rendering, default bibliography
output, and WordPress bibliography handoff.

## Evidence

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed after rebase onto current main `03d37db7b6`: 1 file, 5527 assertions, 0 failures.

## Metrics

- `phpPass`: `3433 -> 3434`
- `mappedCitationRisParserCases`: `1 -> 2`
- `citationRisParserAssertions`: `23 -> 41`

## Non-Overlap

This does not repeat accepted BibTeX/BibLaTeX metadata, direct CSL JSON alias,
CSL style rendering, citation locator, EPUB, DOCX, ODF, PDF/Typst, XML/HTML5 DOM,
ZIP/OPC, or native AST slices. It only extends the existing native RIS parser
handoff for common RIS metadata tags that were previously dropped before CSL
normalization.

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser renderer,
online service, live provider test, or external validator was invoked.
