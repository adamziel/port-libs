# Pandoc BibLaTeX Name/Author-Type Legacy Handoff

## Scope

- `BibtexCslProcessor` carries legacy BibLaTeX `nameaddon`,
  `authortype`, and `bookauthortype` fields into CSL-compatible
  `name-addon`, `author-type`, and `container-author-type` metadata.
- The fallback bibliography renderer surfaces those fields as compact review
  text so non-style handoff keeps the same metadata visible.
- The focused regression covers raw BibTeX provenance, CSL item metadata,
  citation handoff attributes, CSL style text variables, and WordPress
  bibliography output.

## Accounting

- Added `legacyBiblatexNameAuthorTypeQualifierCases`,
  `mappedLegacyBiblatexNameAuthorTypeQualifierCases`, and
  `legacyBiblatexNameAuthorTypeQualifierAssertions` to the Pandoc manifest.
- Direct-format parity remains active; this is a metadata-only CSL/BibLaTeX
  handoff slice and does not claim external citeproc, BibTeX, Biber, Pandoc,
  browser, TeX, office, ZIP, Node, Jupyter, or validator parity.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  `lanes/pandoc/tests/CitationCslProcessorTest.php`
  `lanes/pandoc/tests/BibliographyReaderTest.php`
  (3 files, 7558 assertions, 0 failures)
