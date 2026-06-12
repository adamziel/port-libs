# CSL/BibLaTeX Number Of Volumes Aliases - 2026-06-12

Slice: `plib-nwlqv`

This slice maps bounded BibLaTeX number-of-volumes aliases into CSL `number-of-volumes` metadata for citation and bibliography handoff. Covered aliases are `numberofvolumes`, `number-of-volumes`, `volume-count`, `volumecount`, `numvolumes`, and `num-volumes`, alongside existing `volumes` support.

The focused case preserves raw BibLaTeX field provenance, keeps page extent metadata intact, renders the normalized values through CSL citation and bibliography layouts, and carries the output into WordPress citation plus definition-list bibliography blocks.

Verification on current main `2a569e4541`:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - 1 file, 4973 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 68332 assertions, 0 failures
