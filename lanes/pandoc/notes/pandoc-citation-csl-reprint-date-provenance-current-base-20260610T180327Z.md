# Pandoc CSL/BibLaTeX Reprint Date Provenance Slice

Bead: `plib-fzyq`

This slice keeps the citation/bibliography work inside the native PHP Pandoc
lane. It extends the bounded CSL handoff for BibLaTeX reprint provenance so
`reprintdateaddendum` and `reprint-date-addendum` aliases normalize to the CSL
`reprint-date-addon` review field already used by bibliography output and style
text variables.

The focused fixture now covers a BibLaTeX reprint packet carrying
`reprinttitle`, `reprintdate`, and `reprintdateaddendum`, verifies the raw
BibTeX field provenance, normalized processor item fields, default bibliography
review text, CSL style rendering for `reprint-date` and `reprint-date-addon`,
direct CSL item parity, and WordPress bibliography handoff.

No external citeproc, Pandoc binary, TeX, browser, office suite, unzip/zip, or
external validator was used.

Verification:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4364 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60953 assertions, 0 failures
