# Pandoc CSL Direct Original Title Addendum Aliases

Slice: `plib-4gm1b`
Date: 2026-07-01

Direct CSL JSON now normalizes the mixed compact original-title addendum
spellings `origtitle-addon` and `original-titleaddon` into
`originalTitleAddon`. The same value is exposed through CSL text variables and
sort keys for `original-title-addon`, `original-titleaddon`, and
`origtitle-addon`, keeping direct-format parity with the existing canonical and
compact original-title fields.

The focused fixture covers normalized item metadata, fallback bibliography text,
CSL style rendering, and citation-cluster rendering without invoking external
citeproc, BibTeX, Biber, Pandoc, validators, or network services.

Verification:

- Red-first `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed on `origtitle-addon` normalizing to an empty `originalTitleAddon`.
- Focused `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 6187 assertions, 0 failures`.
- Related `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
  passed with `3 test files, 7558 assertions, 0 failures`.
