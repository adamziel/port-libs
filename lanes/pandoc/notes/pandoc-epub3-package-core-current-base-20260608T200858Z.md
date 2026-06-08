# EPUB3 Package Conditional Stylesheet Handoff

## Scope

Implemented one bounded EPUB3 package-core behavior cluster: stylesheet
condition metadata for package review. `EpubReader` now preserves:

- CSS `@media` block conditions and comma-separated media condition items.
- CSS `@supports` block conditions.
- CSS `@import` trailing `layer(...)`, `supports(...)`, and media conditions.
- Per-stylesheet and package-level conditional rule counts, conditions, and
  `conditional-styles` review flags in `cssResourceReport`.

The report is carried through `importReport.cssResourceReport` and the EPUB AST
document attributes for WordPress import review. The implementation does not
evaluate CSS cascade, layout, media queries, supports expressions, font
selection, or browser rendering.

## Source-Truth Boundary

The pinned Pandoc upstream checkout was not present in this isolated worktree or
at `/home/claude/port-libs/.upstream-cache/pandoc`, matching current lane
dependency-audit history. This slice therefore uses the native EPUB package
contract already present in the lane: static package/CSS metadata handoff for
review, with no Pandoc, Cabal, Haskell runner, browser/CSS renderer, external
CSS engine, online service, live provider test, or live-service provider test.

## Verification

Baseline:

```sh
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
```

Result: `1 test files, 2648 assertions, 0 failures`.

Red-first after adding the focused conditional stylesheet case:

```sh
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
```

Result: failed with `1 test files, 2653 assertions, 1 failures` because
`cssResourceReport.conditionalRuleCount` was missing.

Final focused verification:

```sh
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
```

Result: `1 test files, 2693 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
```

Result: `epub3 package handoff self-test ok`.

Focused delta: `+1` PHP PASS case and `+45` focused assertions over the prior
EPUB reader baseline.

## Dependency Closure

No new native support component is needed. This reuses `EpubReader` CSS asset
scanning, `ZipPackage` fixture bytes, package reference resolution, existing CSS
URL/font/image-set helpers, AST metadata handoff, focused `EpubReaderTest.php`,
and the existing WordPress EPUB3 package handoff example.

## Non-Overlap

This does not repeat accepted EPUB3 OCF/container/rootfile parsing, OPF
metadata/vendor/refinement handling, manifest/spine/nav/NCX/page-breaks,
fallback chains, bindings, media overlays, CFI/media fragments, XHTML resource
scans, scripted/switch/trigger/semantic XHTML metadata, CSS URL references,
CSS `@font-face`, CSS `image-set()`, remote-resource reconciliation,
cover/assets, encryption/font preflight, OCF sidecars, or mimetype preflight.
It specifically owns static CSS conditional stylesheet metadata.

## Follow-Up

Keep full CSS cascade/export policy, EPUBCheck-style validation, encrypted
resource decryption policy, active media-overlay playback semantics, and deeper
XHTML-to-AST conversion as separate bounded EPUB slices.
