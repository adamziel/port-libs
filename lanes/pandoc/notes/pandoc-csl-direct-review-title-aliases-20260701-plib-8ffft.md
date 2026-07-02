# Pandoc Direct CSL Review Title Aliases

Slice: `plib-8ffft`

Implemented one bounded Citation/CSL direct JSON handoff case:

- `CitationCslProcessor` now accepts direct CSL JSON `reviewTitle` /
  `review-title`, `reviewSubtitle` / `review-subtitle`, and `reviewGenre` /
  `review-genre` aliases as sources for the existing `reviewed-title`,
  `reviewed-subtitle`, and `reviewed-genre` renderer variables.
- Focused coverage proves the aliases survive direct JSON normalization, custom
  CSL style rendering, default bibliography text, and WordPress bibliography
  block handoff.

This keeps direct-format citation/bibliography parity aligned with the
already-supported BibLaTeX `reviewtitle` and `reviewsubtitle` handoff without
invoking Pandoc, citeproc, BibTeX, Biber, office suites, TeX/browser engines,
Node tooling, zip/unzip, external validators, or live services.

Validation:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with 1 file, 6,157 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
  passed with 3 files, 7,463 assertions, 0 failures.
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc`
