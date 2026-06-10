# Pandoc EPUB3 Package Rendition Flow Current Base

Timestamp: 2026-06-10T12:08:16Z

## Slice

Mapped one native EPUB3 package ingestion case for package-level OPF
`rendition:flow` metadata and effective spine rendition handoff.

## Coverage

- Focused: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with 1 test file, 3936 assertions, 0 failures.
- Full: `php tools/run-tests.php lanes/pandoc/tests` passed with 44 test
  files, 59910 assertions, 0 failures.
- Syntax: `php -l` passed for `lanes/pandoc/src/EpubReader.php` and
  `lanes/pandoc/tests/EpubReaderTest.php`.

## Metrics

- `phpPass`: `2960 -> 2961`
- `phpFail`: `0`

## Notes

`EpubReader` now records package-level `rendition:flow` metadata, invalid and
conflicting package flow diagnostics, and effective spine rendition defaults.
Itemref `rendition:flow-*` properties still override the package default in the
effective spine report, with both package and itemref provenance retained for
review packets.

No Pandoc, EPUBCheck, Cabal/Haskell runner, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.
