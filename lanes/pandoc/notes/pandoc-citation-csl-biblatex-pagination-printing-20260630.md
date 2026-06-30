# Pandoc citation CSL BibLaTeX pagination and printing metadata

Slice: `plib-dma8o`, citation/bibliography CSL core blocker.

`BibtexCslProcessor` now carries legacy BibLaTeX pagination-unit and
printing/supplement number metadata through native CSL item handoff:
`pagination`, `bookpagination`, `part`, `printingnumber`, and
`supplement-number` aliases are preserved as CSL review variables and direct
bibliography text.

The focused fixture exercises native PHP parsing, CSL style rendering,
Markdown citation collection, and WordPress bibliography output. It does not
invoke Pandoc, BibTeX, Biber, citeproc, office suites, browser renderers,
external validators, or network services.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  (`1` file, `671` assertions, `0` failures)
