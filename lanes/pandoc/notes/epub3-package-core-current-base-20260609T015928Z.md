# EPUB3 XHTML Embedded Resource Handoff

Slice: `pandoc-epub3-package-core-current-base-20260609T015928Z`
Base accepted HEAD: `afefe2709cd2d600e733f14d1a2c7daf937774dc`

## Behavior

Implemented bounded native PHP EPUB3 package support for XHTML embedded media/object/frame resource review.

- `EpubReader` now summarizes embedded `audio`, `video`, `poster`, `source`, `track`, `object`, `embed`, and `iframe` references separately from the existing generic XHTML `contentReferences` list.
- Embedded resources carry OPF manifest id/media-type provenance, package part, byte length, CRC, fragment metadata, exposure policy, and remote/missing/encrypted diagnostics.
- Aggregate `xhtmlResourceReport` metadata now includes embedded-resource counts, kinds, per-kind indexes, diagnostics, and the same handoff through `importReport` and WordPress raw HTML AST block attributes.
- The WordPress EPUB example self-test now asserts that embedded XHTML audio resolves to the OPF audio manifest item and is exposed on the spine block.

## Evidence

Baseline focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3355 assertions, 0 failures`

Final focused verification:

- `php -l lanes/pandoc/src/EpubReader.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3401 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`
- `git diff --check -- lanes/pandoc`
- Result: passed

Assertion delta: +46 focused assertions in `EpubReaderTest.php`.

## Dependency Closure

No new support component is needed. This reuses the lane's native PHP ZIP/OPC package reader, OPF manifest resolution, XHTML XML scanner, package-reference resolver, import-report handoff, and WordPress EPUB package example.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, Word, LibreOffice, browser renderer, media decoder, external XML tool, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not touch the already accepted EPUB vendor metadata, accessibility metadata, OPF manifest property vocabulary, metadata-link vocabulary, nav/NCX page-list, XHTML viewport/language/meta-refresh/form/ping/link/script/switch/trigger/semantic, media-overlay, remote-resources reconciliation, CSS resource, OCF sidecar, encryption, or asset fallback slices. It is restricted to embedded XHTML media/object/frame resource provenance and the WordPress AST/import-report handoff.
