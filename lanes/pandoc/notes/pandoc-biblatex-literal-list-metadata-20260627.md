# Pandoc BibLaTeX Literal List Metadata

Slice: `pandoc-biblatex-literal-list-metadata-20260627`
Bead: `plib-qdgyk`

## Scope

- `BibtexCslProcessor` now preserves multi-value legacy BibLaTeX literal lists as metadata-only CSL arrays:
  `publisher-list`, `publisher-place-list`, `original-publisher-list`,
  `original-publisher-place-list`, `language-list`, `original-language-list`,
  and `event-place-list`.
- The slice carries those arrays through direct bibliography review text, citation handoff, styled CSL variables, and WordPress bibliography output.
- Raw BibLaTeX fields remain available under `rawBibtex.fields`.

## Evidence

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - Result: `1 test files, 678 assertions, 0 failures`.

## Delta

- `lane-status.json` `phpPass`: `458 -> 459`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2304 -> 2305`.
- Added `mappedLegacyBiblatexLiteralListMetadataCases: 1`.

## Non-Overlap

This does not repeat the accepted custom user/list/name slice, option/reference-context/annotation slice, xdata/entryset slice, identifier/attachment slices, or direct CSL JSON list-alias slices. It is limited to legacy BibLaTeX literal publisher/place/language/event list metadata in the active `BibtexCslProcessor` path.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser, package parser, or external validator was invoked.
