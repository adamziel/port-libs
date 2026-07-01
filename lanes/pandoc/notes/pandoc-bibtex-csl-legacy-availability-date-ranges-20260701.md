# Legacy BibTeX CSL Availability Date Ranges - 2026-07-01

Work item: `plib-8k0yp`

## Scope

- `BibtexCslProcessor` now preserves raw BibLaTeX date range objects for
  `available-date`, `submitted`, and `label-date` instead of collapsing those
  variables to single endpoint date parts.
- The fallback bibliography renderer now displays range and open-ended values
  such as `2026-06-15/2026-07-01`, `2026-05/`, and `/2025-12`.
- The focused regression verifies raw CSL metadata, explicit CSL style
  rendering, direct bibliography text, citation handoff, and WordPress fallback
  bibliography output.

## Boundary

This stays inside native PHP citation/bibliography handling. It does not invoke
Pandoc, citeproc, BibTeX, Biber, bibliography managers, office suites,
TeX/browser engines, Node tooling, `zip`/`unzip`, Jupyter, online services,
live providers, or external validators.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with `1 test files, 882 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with `3 test files, 7283 assertions, 0 failures`.
