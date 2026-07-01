# Pandoc BibLaTeX Math Registry Identifier Slice

Slice: `plib-hogoc`

Bounded native PHP Citation/CSL handoff now preserves legacy BibLaTeX
MathSciNet and Zentralblatt review identifiers through `BibtexCslProcessor`
and `CitationCslProcessor` without invoking external citation tooling.

## Scope

- `mrnumber` and `mathscinet` map to canonical CSL `MRNumber` metadata.
- `mrclass` and `mr-class` map to canonical CSL `MRClass` metadata.
- `zbl` and `zbmath` map to canonical CSL `Zbl` metadata.
- Direct bibliography text exposes MR number, MR class, and Zbl identifiers for
  review queues.
- `CitationCslProcessor` receives normalized `registryIdentifierSummary`
  metadata and renders the new `registry-identifiers` and
  `registry-identifier-summary` variables.
- Citation handoff and WordPress bibliography output preserve the identifiers
  without Pandoc, citeproc, BibTeX, Biber, bibliography-manager lookup, or
  external fetches.

## Validation

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - Result: `1 test files, 1078 assertions, 0 failures`

## Accounting

- `lane-status.json` `phpPass`: `470 -> 471`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2310 -> 2311`
- Added `legacyBiblatexMathRegistryIdentifierCases: 1`
- Added `mappedLegacyBiblatexMathRegistryIdentifierCases: 1`

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser
renderer, external validator, online service, live provider test, or
live-service provider test was invoked.
