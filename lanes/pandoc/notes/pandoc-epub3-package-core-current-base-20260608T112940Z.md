# EPUB3 Package Current-Base Media Fragment Handoff

- Micro-slice: `pandoc-epub3-package-core-current-base-20260608T112940Z`
- Accepted base: `755c39728fec0f9184818a939c6ff56a92152616`
- Scope: bounded native EPUB package target-fragment parsing and handoff metadata for W3C media fragments in package references.

## Behavior

- Extended `EpubReader` target-fragment classification to preserve ordinary ID anchors and EPUB CFI fragments while classifying recognized media-fragment dimensions as `fragmentKind: media-fragment`.
- Added bounded in-process reports for `#t=...`, `#xywh=...`, and `#track=...` dimensions, including time start/end/duration seconds, pixel/percent spatial rectangles, duplicate/unsupported-dimension diagnostics, and source-order dimension metadata.
- Surfaced `mediaFragment` details through OCF container links, OPF manifest/metadata/guide/collection references, EPUB nav TOC/page-list, NCX nav/page targets, primary navigation policy, navigation coverage, XHTML/CSS content references, XHTML semantic links, and SMIL text/audio references.
- Page-list and navigation reports now expose `mediaFragmentPageBreakCount`, `mediaFragmentPageBreaks`, `mediaFragmentTargetCount`, and `mediaFragmentTargets`.
- XHTML same-document semantic fragment resolution now skips `media-fragment` targets, so `#t=...` and `#xywh=...` are not misreported as missing element IDs.

## Evidence

- Baseline focused check before edits: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` -> `1 test files, 2325 assertions, 0 failures`.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` -> `1 test files, 2355 assertions, 0 failures`.
- Added focused PASS case: `reports EPUB navigation media fragments for package review`.
- Assertion delta: `+30` focused assertions.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` -> `epub3 package handoff self-test ok`.
- PHP lint passed for:
  - `lanes/pandoc/src/EpubReader.php`
  - `lanes/pandoc/tests/EpubReaderTest.php`
  - `lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check passed: `git diff --check -- lanes/pandoc`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This slice reuses the existing `EpubReader` package-reference resolver and `ZipPackage` fixtures, with bounded media-fragment parsing implemented locally in PHP. No Pandoc, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat the accepted EPUB OCF, OPF metadata link vocabulary, vendor metadata, spine, nav/NCX/page-list, primary nav policy, guide/collections, alternate rendition, fallback-chain, bindings, remote-resource reconciliation, encryption/font preflight, SMIL media overlay, EPUB CFI, XHTML content-resource scanning, CSS scanning, cover/asset, or auxiliary-navigation slices. It specifically closes the previously noted nav target media-fragment policy gap with bounded metadata handoff only.

## Follow-Up

Keep CSS cascade/resource export metadata, EPUBCheck-style structural validation, encrypted-resource decryption policy, active media-overlay playback semantics, and full XHTML-to-AST conversion as separate bounded EPUB slices.
