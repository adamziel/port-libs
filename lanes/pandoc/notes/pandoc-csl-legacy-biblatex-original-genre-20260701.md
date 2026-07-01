# Pandoc CSL Legacy BibLaTeX Original Genre Handoff

Slice: `plib-0li06`
Date: 2026-07-01

Legacy `BibtexCslProcessor` now maps BibLaTeX original-work type aliases
(`origtype`, `origgenre`, `originaltype`, and hyphenated CSL forms) into the
CSL `original-genre` variable. This aligns the legacy processor with the strict
`BibtexCslParser` path, which already preserved `origtype` for
`CitationCslProcessor::fromBibtex()`.

The fallback bibliography text now exposes original genre metadata, and the
focused legacy BibLaTeX original-publication fixture verifies raw CSL item
metadata plus CSL style rendering through both `original-genre` and `origtype`.

No external Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite,
TeX/browser engine, Node tooling, zip/unzip, Jupyter, network service, or
external validator was invoked.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed post-rebase with `1 test files, 1010 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed post-rebase with `1 test files, 6180 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
  passed post-rebase with `1 test files, 307 assertions, 0 failures`.
