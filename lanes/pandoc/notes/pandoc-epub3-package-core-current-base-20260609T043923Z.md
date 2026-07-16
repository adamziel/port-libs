# EPUB3 Package Content Feature Reconciliation

Slice: `pandoc-epub3-package-core-current-base-20260609T043923Z`
Base: `751070fca2ca1c3ef7b50b0753a60f0f2fcd712e`
Date: 2026-06-09 UTC

## Behavior

This slice adds a bounded EPUB package report that reconciles OPF manifest item
content feature properties with the already-scanned XHTML content features.
The report covers `mathml`, `svg`, `scripted`, and `switch`, and records
declared, observed, matched, undeclared, and declared-but-unobserved feature
sets per XHTML asset.

The data is exposed at:

- `resourceProperties.contentFeatureReconciliation`
- `importReport.resourceProperties.contentFeatureReconciliation`
- WordPress document metadata via `document->attr('resourceProperties')`

This complements the existing `remoteResources` reconciliation without changing
remote-resource behavior.

## Evidence

Red-first behavior:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Failed before source implementation because
  `resourceProperties.contentFeatureReconciliation` was absent.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3576 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`

The focused EPUB test file baseline for this worktree was `3544` assertions, so
this slice adds 32 focused assertions and one focused PHP PASS case. The lane
status `phpPass` moved from `2312` to `2313`, and the manifest mapped count
moved from `2712` to `2713`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `EpubReader`
OPF manifest parsing, the existing bounded XHTML content scanner, the existing
WordPress document metadata handoff, the focused PHP runner, and the lane-local
EPUB WordPress example.

No Pandoc, Haskell runner, Word, LibreOffice, `zip`/`unzip`, browser renderer,
external converter, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This patch does not modify prior EPUB remote-resource reconciliation, vendor
metadata, spine/nav/NCX, asset fallback, media-type, page-break, CSS,
encryption, media-overlay, or sidecar behavior. It is limited to OPF/XHTML
content feature declaration reconciliation.

Root harness status: not run - isolated micro-slice.
