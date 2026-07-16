# Pandoc BibTeX CSL Title-Family Alias Slice

Bead: `plib-hknk4`

## Behavior

- `BibtexCslParser` now accepts CSL-style hyphenated title-family field
  aliases in `.bib` input:
  - `main-title`
  - `main-subtitle`
  - `main-title-addon`
  - `volume-title`
  - `part-title`
- The aliases feed the existing CSL item fields and renderer paths for
  `main-title`, `main-title-addon`, `volume-title`, and `part-title`.

## Focused Coverage

- Added `maps bounded bibtex csl style title family aliases into csl output`
  to `lanes/pandoc/tests/CitationCslProcessorTest.php`.
- The fixture verifies raw BibTeX provenance, normalized processor item fields,
  default bibliography output, explicit CSL style rendering, and WordPress block
  bibliography handoff.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4392 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 61109 assertions, 0 failures
