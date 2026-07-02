# Pandoc BibLaTeX Reference Alias Handoff

Slice: `plib-hogoc`

## Scope

BibLaTeX reference provenance now resolves `ids` aliases for `crossref`, `xdata`, and `entryset` references before CSL handoff. Alias keys can identify the referenced canonical entry without being reported as missing.

## Handoff

- `BibtexCslProcessor` uses a shared alias-aware raw-entry resolver for crossref inheritance, xdata inheritance, reference summaries, and missing-key checks.
- Referenced summaries keep the canonical `id`; alias lookups additionally record the requested `citationKey`.
- Inherited crossref/xdata fields no longer copy parent citation identity fields such as `ids` into child entries, preventing duplicate citation aliases in downstream CSL processing.
- The focused fixture covers alias-resolved crossref inheritance, xdata inheritance, entryset summaries, missing-key preservation, direct bibliography text, and styled `CitationCslProcessor` rendering.

## Manifest

- Added `mappedLegacyBiblatexReferenceAliasCases: 1`.
- Added `legacyBiblatexReferenceAliasAssertions: 38`.
- Updated `benchmarkDenominator.mapped` from `2883` to `2884`.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 1 test file, 1063 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
  - 3 test files, 7596 assertions, 0 failures
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`

No external Pandoc, BibTeX, Biber, citeproc, Office, TeX/browser, Node, zip/unzip, Jupyter, or external validator tooling was used.
