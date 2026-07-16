# Pandoc BibLaTeX identifier alias parity slice

Slice: `plib-nwlqv`

## Summary

BibTeX/BibLaTeX bibliography parsing now maps compact ISBN and ISSN alias fields into canonical CSL `ISBN` and `ISSN` metadata. Covered aliases include `isbn13`, `isbn-13`, `isbn10`, `isbn-10`, `eISBN`/`e-isbn`, `electronicISBN`, `printISSN`, `pISSN`, `eISSN`/`e-issn`, `electronicISSN`, and `onlineISSN`.

This closes the parity gap with direct CSL JSON identifier alias handling while preserving raw BibLaTeX field provenance on the normalized item.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed: 1 test file, 4968 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 68544 assertions, 0 failures

## Lane Status

- Added one focused `CitationCslProcessorTest` case for BibLaTeX identifier alias parity.
- Expected metric movement after merge: `phpPass +1`, `phpFail 0`.
