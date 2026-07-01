# Pandoc CSL BibLaTeX Date Shape Review

Slice: `plib-uxc7o`
Date: 2026-07-01

`BibliographyReader('biblatex')` now exposes metadata-only date shape rollups
for strict BibLaTeX bibliography review payloads. The review records date range
endpoint counts, open-ended date variable/direction counts, and literal date
variable counts without exposing the original date or title values.

The focused fixture covers ISO ranges, split end-date fields, open-ended ranges,
and a literal date value. It verifies bibliography-level and per-item review
metadata plus the existing source-value omission policy.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite,
TeX/browser engine, Node tooling, zip/unzip, Jupyter, network service, or
external validator was invoked.

Validation:

- `php -l lanes/pandoc/src/BibliographyReader.php`
- `php -l lanes/pandoc/tests/BibliographyReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
  passed with `1 test files, 346 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with `3 test files, 7551 assertions, 0 failures`.
