# Pandoc BibTeX Printing And Supplement Number Legacy Handoff

Slice: `plib-erxda`

## Scope

- `BibtexCslProcessor` now maps legacy BibLaTeX `printingnumber`,
  `printing-number`, `printnumber`, and `print-number` into CSL
  `printing-number` metadata.
- It also maps `supplementnumber` and `supplement-number` into CSL
  `supplement-number` metadata.
- Direct bibliography text, citation handoff, styled CSL variables, and
  WordPress bibliography output expose these values without invoking citeproc.

## Boundary

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite, TeX/PDF
engine, browser, external validator, online service, or identifier lookup was
invoked. This is metadata-only native PHP handoff.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1 test files, 718 assertions, 0 failures`
