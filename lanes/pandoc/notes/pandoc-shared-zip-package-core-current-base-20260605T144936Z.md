# pandoc-shared-zip-package-core-current-base-20260605T144936Z

Base accepted HEAD: `ab0579d2d089b95ff0a65136decc676646ae544e`

Date: 2026-06-05 UTC

No current pandoc rework note was present under `.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- `ZipPackage` now rejects deflate-specific ZIP general-purpose compression option flag bits on stored or otherwise non-deflated entries during central-directory preflight.
- Valid deflated entries with those flag bits still read through the existing native PHP inflate path.
- Unsupported compression methods without the impossible deflate option flags remain preflightable so reader-specific policy can still report the original unsupported-method boundary.
- The WordPress ZIP preflight smoke now reports `zipDeflateOptionFlagPolicy=rejected`.

Source truth: Pandoc DOCX, ODT, and EPUB readers depend on ZIP/OPC package semantics before document conversion. ZIP general-purpose bits 1 and 2 are compression option bits for deflated entries, not stored-entry metadata, so a native bounded reader should reject method/flag combinations that cannot represent a valid stored payload before exposing package bytes to WordPress import paths.

## Evidence

- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 399 assertions, 0 failures`.
- Red-first after adding the focused case: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` failed at `rejects deflate option flags on non-deflated zip entries before package import` because the expected `RuntimeException` was not thrown.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 405 assertions, 0 failures`.
- Syntax checks passed for `lanes/pandoc/src/ZipPackage.php`, `lanes/pandoc/tests/ZipPackageTest.php`, and `lanes/pandoc/examples/wordpress-zip-package-preflight.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Example smoke `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` passed with `zip package writer preflight self-test passed`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Delta

- Focused ZIP package test cases: `53 -> 54`.
- Focused ZIP package assertions: `399 -> 405` (`+6`).
- Manifest mapped checks: `1411 -> 1412`.
- `zipPackageCoreSupportCases`: `21 -> 22`.
- `zipPackageCoreAssertions`: `131 -> 137`.
- Lane PHP pass count: `956 -> 957`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `ZipPackage`, `ZipPackageEntry`, CRC/DEFLATE path, package preflight smoke, and lane test harness. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, external archive tool, external office tool, external converter, or online service was executed.

Full upstream runner parity remains gated on hydrating the Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and preparing non-mutating Cabal runner evidence for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat existing ZIP central/local metadata, timestamp, data-descriptor, unsupported compression, encryption, Unix symlink, drive-letter, ZIP64, or central-directory signature slices. It owns only deflate option flag and compression-method consistency in the shared ZIP package primitive.

## Follow-Up

Keep ZIP64 large-archive support, spanning archives, non-deflate decompressor implementation, AES/encrypted payload handling, cryptographic central-directory signature validation, and reader-level default archive policy wiring as separate bounded slices unless a concrete DOCX/ODT/EPUB fixture requires one earlier.
