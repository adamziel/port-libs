# EPUB3 Package Core Current Base: Primary Nav Target Policy

Date: 2026-06-08 UTC
Micro-slice: `pandoc-epub3-package-core-current-base-20260608T064510Z`
Base accepted HEAD: `2963610daf96767276a1776d5d1df7e0ba0844de`

## Source Truth

- Upstream lane source is bounded EPUB3 package behavior under `lanes/pandoc/**`.
- Official EPUB 3.3 package semantics treat `toc`, `landmarks`, and `page-list` as primary navigation sections whose links should identify publication content documents or fragments. Auxiliary navigation sections such as lists of illustrations are allowed but remain review metadata rather than reading-order coverage.
- No local Pandoc upstream checkout was available in `.upstream-cache/pandoc`; no Pandoc, Cabal/Haskell runner, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.

## Implemented Behavior

- `EpubReader` now adds `nav.primaryNavigationTargetPolicy` after resolving the OPF spine.
- The report covers `toc`, `landmarks`, and `page-list` sections only.
- It records section summaries, flattened item summaries, spine attachment, target validity, source package diagnostics, and policy diagnostics for:
  - external primary nav targets,
  - primary nav links to missing package parts,
  - primary nav links to package parts outside the resolved spine,
  - primary nav items with no target,
  - landmark nav items missing `epub:type`.
- Existing auxiliary nav reporting stays informational and is not counted in the primary policy.
- The WordPress EPUB package handoff example now asserts and prints primary-nav policy item, external-target, and diagnostic counts.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: failed as expected on missing `primaryNavigationTargetPolicy`.
  - Output summary: `1 test files, 2203 assertions, 1 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: pass.
  - Output summary: `1 test files, 2236 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Result: `epub3 package handoff self-test ok`.
- PHP lint:
  - `php -l lanes/pandoc/src/EpubReader.php`
  - `php -l lanes/pandoc/tests/EpubReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  - Result: no syntax errors.

## Status Delta

- Added one focused PHP PASS case.
- Focused EPUB reader assertions increased by 34.
- `lane-status.json` `phpPass`: `1554 -> 1555`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1975 -> 1976`.
- EPUB3 package core cases: `5 -> 6`.
- EPUB3 package core assertions: `78 -> 112`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native EPUB OCF/OPF/nav/XHTML reader, package reference resolution, and spine metadata. Follow-up EPUB3 work can stay native and bounded around OPF link-record refinement coverage, nav landmark role mapping into block metadata, EPUB CFI target validation, or media-overlay/spine handoff behavior.

## Non-Overlap

This does not repeat the recent EPUB3 slices for OPF vendor metadata, identifier/date metadata, spine flow/alignment, fixed-layout overrides, XHTML `epub:type` content semantics, page-list page-break summaries, auxiliary navigation preservation, media type/fallback review, or remote-resource reconciliation.
