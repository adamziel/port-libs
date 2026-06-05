# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T162834Z`
Base accepted HEAD: `c0e71447bb6ce34af94a2d4d96a552f5aa1446a1`
Date: 2026-06-05 UTC

No current pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- Extended `ZipPackage::commentPreflight()` with package/entry comment presence
  booleans and `commentedEntryNames` for strict package policy review.
- Added `ZipPackage::assertNoPackageOrEntryComments()` so DOCX/EPUB/ODT and
  WordPress media handoff paths can reject package-level or entry-level ZIP
  comments before trusting embedded media bytes.
- Updated the WordPress ZIP package preflight smoke to expose the strict ZIP
  comment policy as `zipCommentPolicy=rejected`.

Source truth: Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers
before conversion. ZIP archive comments and per-entry comments are metadata
outside the OPC/ODF/EPUB document graph, so a bounded native package reader
should keep them inspectable and make strict import policies rejectable without
invoking external archive tools.

## Evidence

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 451 assertions, 0 failures`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 466 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed with `zip package writer preflight self-test passed`.
- Syntax checks passed for `lanes/pandoc/src/ZipPackage.php`,
  `lanes/pandoc/tests/ZipPackageTest.php`, and
  `lanes/pandoc/examples/wordpress-zip-package-preflight.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package PASS cases: `56 -> 57`.
- Focused ZIP package assertions: `451 -> 466` (`+15`).
- Manifest mapped checks: `1453 -> 1454`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 146`.
- Lane PHP pass count: `998 -> 999`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, CRC/DEFLATE handling, package preflight
example, and focused PHP test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, `ZipArchive`,
`zip`/`unzip`, Word, LibreOffice, external archive tool, external office tool,
online sanitizer, or online service was executed.

Full upstream runner parity remains gated on hydrating the Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and preparing non-mutating Cabal
runner evidence for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat existing ZIP central/local metadata, extra-field
duplicate-ID, timestamp, data-descriptor, unsupported compression, deflate
option flag, encryption, Unix symlink, drive-letter, ZIP64, local-layout,
central-directory signature, creator host-system, or archive-compression
slices. It owns only strict package/entry ZIP comment policy preflight.

## Follow-Up

Keep reader-level default strict-comment policy wiring, full ZIP64
large-archive support, spanning archives, non-deflate decompressor
implementation, AES/encrypted payload handling, and cryptographic
central-directory signature validation as separate bounded ZIP package slices
unless a concrete DOCX/ODT/EPUB fixture requires one earlier.
