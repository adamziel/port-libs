# Pandoc CSL BibLaTeX Date Range Handoff

Slice: `plib-72rk3`
Date: 2026-07-01

`BibtexCslProcessor` now preserves BibLaTeX ISO date ranges as CSL date
objects instead of collapsing them to the first date endpoint. The bounded
handoff covers `date`, `urldate`, `eventdate`, `origdate`, and `reprintdate`,
including open-ended ranges such as `2025-11/`.

The focused fixture verifies raw CSL item metadata, normalized
`CitationCslProcessor` date displays and range parts, explicit CSL style
rendering, bibliography rendering, and WordPress block output.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite,
TeX/browser engine, Node tooling, zip/unzip, Jupyter, network service, or
external validator was invoked.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with `1 test files, 855 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with `3 test files, 7185 assertions, 0 failures`.
