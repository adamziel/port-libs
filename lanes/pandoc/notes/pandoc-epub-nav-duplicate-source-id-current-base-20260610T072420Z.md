# Pandoc EPUB Nav Duplicate Source ID Diagnostics Current Base

Timestamp: 2026-06-10T07:24:20Z

## Slice

Mapped one native EpubReader package review case for duplicate EPUB navigation
list-item IDs and label IDs.

## Coverage

- Focused: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with 1 test file, 3855 assertions, 0 failures.
- Full: `php tools/run-tests.php lanes/pandoc/tests` passed with 43 test
  files, 59097 assertions, 0 failures.
- Syntax: `php -l` passed for `lanes/pandoc/src/EpubReader.php` and
  `lanes/pandoc/tests/EpubReaderTest.php`.

## Notes

The slice keeps current EPUB nav label, href, leaf-link, and duplicate-target
diagnostics intact, then adds grouped `duplicate-nav-item-id` and
`duplicate-nav-label-id` report entries. No Pandoc, EPUBCheck, Cabal/Haskell
runner, zip/unzip, browser renderer, external validator, online service, live
provider test, or live-service provider test was executed.
