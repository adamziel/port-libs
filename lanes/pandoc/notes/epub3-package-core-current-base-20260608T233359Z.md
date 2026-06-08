# EPUB3 NCX Page List Provenance Handoff

Slice: `pandoc-epub3-package-core-current-base-20260608T233359Z`
Base accepted HEAD: `9ded36a0bdf8a38d0d938423ba129d62e7355cba`

## Behavior

Implemented bounded native PHP EPUB3 package support for legacy NCX `pageList` review metadata when the EPUB3 nav document has no `page-list` section.

- `EpubReader` now exposes `ncx.pageListCount`, `ncx.pageListReport`, and `ncx.pageListDiagnostics`.
- NCX `pageTarget` entries now preserve source classes, language, direction, hidden state, `navLabel`/`text`/`content` attributes, resolved target byte length, CRC, manifest/encryption exposure flags, and target diagnostics.
- The unified `pageBreaks` handoff now carries those NCX provenance fields through to WordPress spine block metadata.

## Evidence

Red-first:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3090 assertions, 1 failures`
- Failure: expected NCX page-list report/provenance fields were absent.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3169 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`

## Dependency Closure

No new support component is needed. This reuses the lane's native PHP ZIP/OPC package reader, NCX XML parser, package-reference resolver, and WordPress EPUB page-break handoff.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, Word, LibreOffice, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not touch the already accepted EPUB XHTML form/ping, meta-refresh, language, switch/trigger, viewport, nav page-list, guide/collection, or media-overlay slices. It is restricted to legacy NCX page-list provenance and its WordPress page-break handoff.

