# Pandoc CSL MathSciNet Alias Current Base 20260611T164638Z

## Scope

- Bead: plib-pde8d, Pandoc citation/bibliography CSL core blocker slice.
- Current base: 2cea4fa785.
- Change: BibLaTeX `mathscinet` and direct CSL `mathscinet` now populate
  the normalized `MRNumber` / `mrNumber` registry identifier metadata.
- Handoff behavior: MathSciNet aliases render through the existing MR
  bibliography output, CSL `mathscinet` style variable, direct `mr-number`
  alias, and WordPress bibliography review handoff.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed 1 file / 4673 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 64398
  assertions / 0 failures.

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser
renderer, external validator, online service, live provider test, or
live-service provider test was executed.
