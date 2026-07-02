# BibTeX/CSL language option aliases

Work item: `plib-n8f4m`

## Summary

`BibtexCslProcessor` now accepts legacy BibLaTeX language option aliases in
addition to `langidopts`. The parser maps `langid-options`, `langidoptions`,
`language-options`, `languageoptions`, `hyphenation-options`, and
`hyphenationoptions` into the existing `biblatex-language-options` CSL review
metadata.

The focused test covers `langid`, `language`, and `hyphenation` entries with
alias-specific option fields, then carries those options through direct CSL
items, `renderBibliographyText()`, styled `CitationCslProcessor` rendering,
`citationHandoff()`, and WordPress bibliography output. Raw BibLaTeX fields
remain preserved for audit, and no external citation tooling is invoked.

## Non-overlap

This slice does not change BibTeX parsing grammar, CSL style evaluation,
BibLaTeX option splitting, name parsing, date handling, or citation locator
behavior. It only broadens the alias list used to locate BibLaTeX language
option metadata.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorLanguageOptionAliasTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorLanguageOptionAliasTest.php`
  - 1 file, 22 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorLanguageOptionAliasTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 3 test files, 7,234 assertions, 0 failures
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

Direct-format parity remains active for the Pandoc lane. No Pandoc binary,
citeproc, BibTeX, Biber, browser/TeX engine, office suite, online service, or
external validator was invoked.
