# Pandoc Citation/CSL MathSciNet Alias Handoff

Slice: `plib-pkdn9`
Base: current main `0b4dca730`
Area: CSL/BibTeX/BibLaTeX/csljson citation and bibliography support

## Scope

- `BibtexCslParser` now treats bounded BibLaTeX `mathscinet` fields as CSL
  `MRNumber`, aligned with existing `mrnumber`, `mr-number`, and `mr` aliases.
- `CitationCslProcessor` direct CSL item normalization now accepts
  `mathscinet` as an `mrNumber` source, matching existing CSL style rendering
  support for `<text variable="mathscinet"/>`.
- The registry identifier coverage now verifies BibLaTeX parsing, raw field
  provenance, normalized item metadata, style rendering, bibliography output,
  and WordPress bibliography handoff for the MathSciNet alias.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed: 1 test file, 4681 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files,
  64623 assertions, 0 failures.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer,
external validator, online service, or live provider test was invoked for this
slice.
