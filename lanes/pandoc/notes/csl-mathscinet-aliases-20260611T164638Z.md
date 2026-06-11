# CSL MathSciNet Alias Slice 20260611T164638Z

- Bead: `plib-pde8d`
- Base: `origin/main` `96af5e2be`
- Scope: bounded CSL/BibTeX/BibLaTeX/csljson citation and bibliography support.
- Change: MathSciNet aliases now normalize into canonical `MRNumber`/`mrNumber` metadata for BibLaTeX import and direct CSL JSON item handoff.
- Coverage: added `maps bounded mathscinet aliases into csl mr metadata` in `CitationCslProcessorTest.php`, covering raw BibLaTeX extraction, normalized item metadata, default bibliography MR rendering, direct CSL JSON alias normalization, and custom CSL `mathscinet` variable rendering.
- Verification:
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> 1 test file, 4627 assertions, 0 failures
  - `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 64006 assertions, 0 failures

No Pandoc, citeproc, bibliography manager, browser renderer, external validator, online service, or live provider was invoked.
