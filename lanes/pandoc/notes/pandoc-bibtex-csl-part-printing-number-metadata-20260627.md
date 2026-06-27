# Pandoc BibLaTeX Part/Printing Number Metadata

Slice: `pandoc-bibtex-csl-part-printing-number-metadata-20260627`
Bead: `plib-72rk3`

## Scope

- `BibtexCslProcessor` now preserves legacy BibLaTeX `division`/`subdivision`, `part`, `printing`/`printingnumber`, and `supplementnumber` fields as CSL metadata (`division`, `part`, `printing-number`, `supplement-number`).
- The slice carries those fields through direct bibliography review text, citation handoff, styled CSL variables, and WordPress bibliography output.

## Evidence

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - Result: `1 test files, 712 assertions, 0 failures`.

## Delta

- `lane-status.json` `phpPass`: `461 -> 462`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2305 -> 2306`.
- Added `mappedLegacyBiblatexPartPrintingNumberCases: 1`.

## Non-Overlap

This does not repeat the accepted legacy BibLaTeX literal-list, registry/authority identifier, source-file attachment, custom field/list/name, option/reference-context/annotation, xdata/entryset, or name-annotation slices. It is limited to part/printing/supplement numeric metadata in the active `BibtexCslProcessor` path.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser, package parser, or external validator was invoked.
