# BibLaTeX Issuing Authority CSL Handoff

Slice: `plib-kc92n`
Date: 2026-07-01

## Scope

- `BibtexCslProcessor` now maps legacy BibLaTeX `authority`, `authority-list`, `issuing-authority`, and `issuing-authority-list` aliases into CSL `authority` name metadata.
- Direct fallback bibliography text uses report authorities as creator fallback when no author/editor is present.
- Non-report entries keep authority names as review-visible authority metadata.
- The slice does not invoke Pandoc, citeproc, BibTeX, Biber, network lookup, or external validators.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 1 file, 937 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
  - 3 files, 7,387 assertions, 0 failures
