# pandoc-epub3-package-core-current-base-20260609T013229Z

Slice: EPUB3 XHTML inline CSS resource handoff on accepted base `800b696344a9bf658321def4bebfd04d22ba2df2`.

## Behavior

EPUB3 package scans already preserved external stylesheet resources and XHTML element URL attributes, but package references embedded inside XHTML `<style>` elements and `style=""` attributes were not visible to import review.

This slice reuses the existing bounded CSS reference tokenizer for XHTML inline CSS. `EpubReader` now scans `url()`, `@import`, and `image-set()` references in style elements and style attributes, resolves them relative to the XHTML package part, reports local/external/missing/encrypted package targets, and marks `inline-styles` in XHTML content review flags. The raw HTML AST handoff now exposes `contentStyles` and `contentStyleDiagnostics` alongside existing `contentReferences`, so WordPress review packets can audit inline CSS dependencies without running a CSS cascade, browser layout, or external renderer.

## Focused Evidence

- No current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` rework note existed for this lane.
- Baseline before the slice: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 3286 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 3355 assertions, 0 failures`.
- Added `+1` focused PHP PASS case and `+69` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` passed with `epub3 package handoff self-test ok`.
- PHP lint passed for changed PHP files.
- JSON validation for lane status/manifest passed.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `EpubReader`, `ZipPackage`, package reference resolution, existing CSS reference token scanning, DOM/libxml `NONET` parser paths, `AstNode` raw HTML handoff, `WordPressBlockWriter`, and the focused lane test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, Word, LibreOffice, external converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted EPUB3 OCF container parsing, OPF metadata/manifest/spine parsing, nav/NCX target handling, guide/collection handling, fallback chains, bindings, media overlays, remote-resource reconciliation, encryption/obfuscation review, CFI reporting, external stylesheet CSS scanning, CSS font-face/image-set/conditional/page-rule scanning, XHTML src/srcset/script/link/meta-refresh/form/ping/switch/trigger/semantic scans, or ZIP package primitives.

The bounded gap covered here is XHTML inline CSS resource discovery and raw-block handoff metadata for EPUB package review.

## Follow-Up

A next non-overlapping EPUB3 slice could cover XHTML-to-AST conversion, encrypted-resource review policy, remote-resource policy, CSS cascade/export policy, or EPUB validation diagnostics without executing external tools.
