# Pandoc CSL BibLaTeX Hyphenated Metadata Aliases Current-Base Slice

Issue: `plib-er6g3`
Base: `77ddea8948834d046d1506811ffd5da5c6f4f6cd`

This slice extends the native PHP BibTeX/BibLaTeX to CSL handoff so CSL-style hyphenated metadata aliases normalize at the same boundary as existing compact BibLaTeX field names.

Mapped coverage:

- `short-title`, `title-add-on`, `container-title`, and `container-title-addon` now populate the expected CSL title variables.
- `issued-date` and `url-date` now populate issued and accessed date-parts.
- `original-title-add-on`, `originaldate`, original publisher fields, collection fields, and language remain normalized while raw BibTeX field names stay inspectable.

Verification:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php` -> 1 file, 148 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 62490 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser, external validator, online service, or live provider runner was invoked.
