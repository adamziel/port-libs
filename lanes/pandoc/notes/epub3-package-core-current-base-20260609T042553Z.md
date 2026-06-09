# EPUB3 Stylesheet Export Policy Handoff

Slice: `pandoc-epub3-package-core-current-base-20260609T042553Z`
Base accepted HEAD: `11fc57ec36d6cc974a7a65f55020cfb9f1af6d59`

## Behavior

Implemented bounded native PHP EPUB3 stylesheet export-policy metadata.

- `EpubReader` now exposes `cssResourceReport.exportPolicy` with aggregate status counts for `exportable`, `review-required`, and `blocked` package stylesheets.
- Each scanned CSS manifest item now carries `exportPolicy` metadata with `canExport`, `requiresReview`, separated `reviewReasons` and `blockingReasons`, dependency counts, conditional-rule counts, paged-media counts, and font-face counts.
- Remote CSS dependencies, conditional at-rules, import conditions, and paged-media rules are classified as review reasons.
- Missing CSS dependencies, encrypted package dependencies, and unavailable stylesheet bytes are classified as blockers.
- The WordPress EPUB package handoff example now asserts that an encrypted font-dependent stylesheet is blocked while conditional and paged-media metadata remain reviewable.

## Evidence

Baseline focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3544 assertions, 0 failures`

Red-first focused verification after adding the stylesheet export-policy test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3546 assertions, 1 failures`
- Failure: `cssResourceReport` lacked `exportPolicy`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3582 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`

Assertion delta: +36 focused assertions in `EpubReaderTest.php` and +1 PHP PASS case.

## Dependency Closure

No new support component is needed. This reuses the lane's native PHP EPUB package reader, CSS resource scanner, package-reference resolver, OCF encryption metadata, `ZipPackage` fixtures, import-report handoff, and WordPress EPUB package example.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, Word, LibreOffice, browser renderer, external CSS engine, external converter, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat the accepted EPUB container/rootfile, OPF metadata, fixed layout/rendition, nav/NCX target/page-list/audio, XHTML resource scans, remote-resource reconciliation, CSS reference extraction, font-face parsing, conditional CSS parsing, paged-media parsing, OCF sidecar, encryption, asset fallback, SMIL media overlay, or XHTML embedded media/object/frame slices. It is restricted to the stylesheet export decision layer that consumes those existing native reports.
