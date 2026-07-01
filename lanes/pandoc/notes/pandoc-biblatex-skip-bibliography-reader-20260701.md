# BibLaTeX skip-bibliography reader path

Slice: `pandoc-biblatex-skip-bibliography-reader`

## Scope

Aligned the native PHP bibliography reader path with the existing CSL processor
behavior for BibLaTeX `options={skipbib}` entries.

- `CitationCslProcessor::bibliographyDefinitionList()` now filters ids whose
  normalized BibLaTeX options mark them as omitted from bibliographies.
- `BibliographyReader` BibTeX/BibLaTeX review metadata now reports
  bibliography visibility counts and normalized option-name counts without
  exposing raw option values or source field values.
- The focused fixture covers `skipbib`, `skipbib=false`, language option names,
  and `dataonly` entries skipped by the BibLaTeX parser.

## Tests

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/src/BibliographyReader.php`
- `php -l lanes/pandoc/tests/BibliographyReaderTest.php`
- Red-first focused test failed because the reader-generated bibliography still
  included the `skipbib` entry.
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
  passed with `1 test files, 213 assertions, 0 failures`.
- Adjacent CSL/BibTeX gate:
  `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed after rebase with `3 test files, 7159 assertions, 0 failures`.

## Boundary

No Pandoc, citeproc, bibliography manager, office suite, TeX/browser engine,
Node tooling, zip/unzip, Jupyter, or external validator was used. Fixtures are
in-memory BibLaTeX strings parsed by the native PHP bibliography reader and CSL
processor.
