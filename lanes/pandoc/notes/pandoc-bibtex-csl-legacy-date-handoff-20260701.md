# Legacy BibTeX CSL Date Handoff - 2026-07-01

Work item: `plib-1zn2r`

## Scope

- `BibtexCslProcessor` now maps simple legacy BibTeX/BibLaTeX
  `availabledate`, `submitteddate`/split submitted date, and `labeldate`
  fields into CSL `available-date`, `submitted`, and `label-date` metadata.
- The fallback bibliography renderer now emits those dates as reviewable
  metadata so unstyled bibliography handoff does not hide them.
- The focused regression checks raw CSL item metadata, bounded CSL style
  rendering via `CitationCslProcessor::fromItems()`, and WordPress fallback
  bibliography output.

## Boundary

This stays inside native PHP citation/bibliography handling. It does not invoke
Pandoc, citeproc, BibTeX, Biber, TeX/browser engines, Node tooling, online
services, live providers, or external validators.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
