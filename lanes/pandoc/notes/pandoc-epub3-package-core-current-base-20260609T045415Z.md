# EPUB3 Package Effective Rendition Handoff

Slice: `pandoc-epub3-package-core-current-base-20260609T045415Z`
Base: `e3e201377d66d62da0039dedbb153200e0a6e366`
Date: 2026-06-09 UTC

## Behavior

This slice adds bounded native EPUB3 package-review support for effective
per-spine rendition metadata. `EpubReader` now combines:

- package-level OPF `rendition:layout`, `rendition:orientation`,
  `rendition:spread`, and `rendition:viewport` defaults;
- itemref-level rendition override properties such as
  `rendition:layout-pre-paginated`, `rendition:orientation-landscape`, and
  `rendition:spread-none`;
- itemref-level metadata refinements such as
  `meta refines="#chapter-spine" property="rendition:viewport"`.

Each spine item now exposes `effectiveRendition` with resolved values,
source labels (`package`, `itemref`, or `itemref-refinement`), viewport
dimensions, itemref viewport provenance, and diagnostics. The same report is
attached to emitted WordPress raw HTML block attributes so review packets can
see fixed-layout and viewport context without recalculating OPF defaults.

## Evidence

Red-first behavior:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: failed before source implementation because
  `spine[0].effectiveRendition` was absent; the run reached
  `1 test files, 3619 assertions, 1 failures`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3647 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`

Syntax and diff checks:

- `php -l lanes/pandoc/src/EpubReader.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- Result: no syntax errors
- `php -r 'json_decode(...)'` for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- Result: both JSON files decoded successfully
- `git diff --check -- lanes/pandoc`
- Result: passed

Focused delta: +1 PHP TestRunner PASS case and +33 focused assertions in
`EpubReaderTest.php`. `lane-status.json` moves `phpPass` from `2331` to
`2332`; `UPSTREAM_TEST_MANIFEST.json` moves mapped EPUB/Pandoc support from
`2727` to `2728`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `EpubReader`
OPF metadata parsing, spine itemref parsing, metadata refinement attachment,
the existing WordPress raw HTML block handoff, the focused PHP test runner, and
the lane-local WordPress EPUB package example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
`zip`/`unzip`, ZipArchive, EPUBCheck, browser renderer, external converter,
online service, live provider test, or live-service provider test was executed.
Full upstream Pandoc runner parity remains a separate upstream-runner
dependency task requiring hydrated pinned upstream sources and Haskell test
executables.

## Non-Overlap

This does not repeat accepted EPUB OCF mimetype/container/rootfile validation,
OPF metadata/vendor fields, raw itemref property parsing, alternate
renditions, nav/NCX/page-list parsing, navigation target reconciliation, guide
or collection reporting, fallback chains, bindings, media overlays, remote
resource reconciliation, content feature reconciliation, XHTML/CSS resource
scans, cover/asset reports, encryption/font preflight, sidecar reporting, or
EPUB CFI/media-fragment propagation. It owns only the effective per-spine
rendition report that combines package defaults with itemref overrides for
WordPress review metadata.

Root harness status: not run - isolated micro-slice.
