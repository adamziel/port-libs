# Pandoc CSL JSON Exporter Envelope Handoff

Slice: `plib-iauje`

## Behavior

- `CitationCslProcessor::fromJson()` now routes through a shared native CSL JSON item extractor.
- Direct CSL JSON still accepts the standard top-level item list.
- Bounded exporter envelopes are accepted when the item list is under `items`, `references`, or `bibliography`.
- `BibliographyReader` now uses the same extractor for `csljson` converter dispatch, so direct processor and registered reader paths agree.
- Top-level JSON objects without a supported item-list wrapper remain rejected, including the direct `{}` ambiguity that PHP decodes as an empty array.

## Evidence

- PHP lint passed for `CitationCslProcessor.php`, `BibliographyReader.php`, `CitationCslProcessorTest.php`, and `BibliographyReaderTest.php`.
- Focused gate: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php` -> `3 test files, 6700 assertions, 0 failures`.
- No Pandoc, citeproc, BibTeX, Biber, office suite, TeX engine, browser, Node, zip/unzip, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice is limited to native CSL JSON bibliography item extraction and validation before existing CSL normalization. It does not change CSL style rendering, BibTeX/BibLaTeX parsing, RIS parsing, EndNote XML parsing, citation disambiguation, package readers, archive readers, or external engine handoff behavior.
