# EPUB3 NCX Label Audio Handoff

Slice: `pandoc-epub3-package-core-current-base-20260609T030053Z`
Base accepted HEAD: `05069a2190fe377801777d2d97b726785a631773`

## Behavior

Implemented bounded native PHP EPUB3/NCX label-audio metadata support.

- `EpubReader` now preserves NCX `navLabel` `audio` elements for `navPoint`, `pageList`, `pageTarget`, `navList`, and `navTarget` review handoffs.
- Audio label entries resolve package-relative `src` values through the existing native package reference resolver, carrying target part, OPF manifest id/media type, byte length, CRC, SHA-256, fragment metadata, encrypted/can-expose policy, and remote/missing diagnostics.
- NCX audio `clipBegin` / `clipEnd` values are normalized as bounded clock metadata with NCX-specific invalid and end-before-begin diagnostics.
- Aggregate `ncx.audioLabelReport` metadata is exposed through `importReport`, combined `navigation` report items, supplemental NCX navList items, page-break handoff metadata, and WordPress raw HTML document attributes.
- The WordPress EPUB package handoff example now asserts local NCX label audio provenance without decoding media or fetching remote resources.

## Evidence

Red-first focused verification after adding the NCX label-audio test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3492 assertions, 1 failures`
- Failure: `audioLabelCount` was absent from the NCX report.

Final focused verification:

- `php -l lanes/pandoc/src/EpubReader.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3544 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`
- `git diff --check -- lanes/pandoc`
- Result: passed

Assertion delta: +143 focused assertions in `EpubReaderTest.php` and +1 PHP PASS case.

## Dependency Closure

No new support component is needed. This reuses the lane's native PHP ZIP/OPC package reader, NCX parser, package reference resolver, SMIL-style clock parsing, import-report handoff, and WordPress EPUB package example.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, Word, LibreOffice, browser renderer, media decoder, external XML tool, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not touch the already accepted EPUB vendor metadata, accessibility metadata, OPF manifest property vocabulary, metadata-link vocabulary, nav/NCX page-list, NCX head/navList roles, XHTML viewport/language/meta-refresh/form/ping/link/script/switch/trigger/semantic, media-overlay, remote-resources reconciliation, CSS resource, OCF sidecar, encryption, asset fallback, or XHTML embedded media/object/frame slices. It is restricted to NCX `navLabel` audio metadata provenance and its navigation/page-break/import-report/WordPress handoff.
