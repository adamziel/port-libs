# Pandoc BibTeX CSL URL description alias

Work item: `plib-5e0ux`

## Scope

`BibtexCslProcessor` now maps the hyphenated BibLaTeX `url-description`
field into CSL-like `URL-label` metadata, matching the strict
`BibtexCslParser` and `CitationCslProcessor` alias surface. Existing
`urldescription`, `urltitle`, `urllabel`, and `url-label` precedence is
unchanged.

The handoff remains metadata-only: URL labels are preserved for bibliography
review, CSL style variables, citation handoff, and WordPress bibliography
output without fetching URLs or invoking an external citation processor.

## Validation

- Red-first `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorUrlDescriptionAliasTest.php`
  - Result before implementation: `1 test files, 1 assertions, 1 failures`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorUrlDescriptionAliasTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorUrlDescriptionAliasTest.php`
  - Result: `1 test files, 14 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorUrlDescriptionAliasTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `3 test files, 7226 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser/Typst engine,
Jupyter, Node tooling, `zip`/`unzip`, external validator, online service, or
live provider test was invoked.
