# CSL Citation Alias Field Spellings

Bead: `plib-ymaye`
Base: `a886765f4`

This slice keeps CSL citation alias handoff canonical across BibTeX/BibLaTeX
and direct csljson inputs.

- `BibtexCslParser` now coalesces `ids`, `citation-aliases`,
  `citation_aliases`, `citationaliases`, `citation-alias`,
  `citation_alias`, and `citationalias` into CSL `citation-aliases`.
- `CitationCslProcessor` now accepts direct csljson singular alias spellings
  (`citation-alias`, `citation_alias`, `citationAlias`, `citationalias`) in
  addition to the existing plural forms.
- The focused test proves alias citations resolve through canonical items and
  WordPress bibliography output stays deduplicated.

Verification on 2026-06-11 UTC:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4737 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 65435 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
