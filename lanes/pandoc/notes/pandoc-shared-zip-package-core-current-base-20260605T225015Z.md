# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T225015Z`
Base accepted HEAD: `718fd27c4bd5c5cf3bb2a77e5061b76a630e07d5`
Date: 2026-06-05 UTC

No current Pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- Extended `ZipPackage::extraFieldPreflight()` with central-only and
  local-only extra-field ID summaries per entry.
- Added `ZipPackage::assertMatchingExtraFieldIds()` so strict DOCX, ODT, EPUB,
  and WordPress media review paths can reject package entries whose
  central-directory extra-field inventory differs from the local-header
  extra-field inventory.
- Preserved permissive package inspection: local-only provenance such as access
  or creation timestamp metadata remains parseable unless a strict caller opts
  into the new parity assertion.
- Updated the WordPress ZIP package preflight smoke to report
  `zipExtraFieldIdMismatchPolicy` and the mismatched package entry.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed packages before document
conversion. ZIP local headers and central-directory records can both carry
extra fields with timestamp, Unicode, or vendor provenance. A bounded native
package reader should keep those fields inspectable while making ambiguous
central/local inventory differences rejectable for strict import policies,
without invoking external archive tools.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, zip/unzip, Word,
LibreOffice, external archive tool, external office tool, external converter,
browser renderer, online sanitizer, online service, or live provider test was
executed.

## Verification

Baseline focused ZIP package verification before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 578 assertions, 0 failures
```

Focused ZIP package verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 603 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package test cases: `62 -> 63`.
- Focused ZIP package assertions: `578 -> 603` (`+25`).
- Manifest mapped checks: `1554 -> 1555`.
- `zipPackageCoreSupportCases`: `21 -> 22`.
- `zipPackageCoreAssertions`: `131 -> 156`.
- Lane PHP pass count: `1102 -> 1103`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`ZipPackageEntry`, package fixture builders, and the existing WordPress ZIP
package preflight smoke. Full upstream runner parity remains gated on hydrating
the Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty executable builds.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, local entry order,
data descriptors, stored-first mimetype checks, CRC/local-header integrity,
central/local extra-field parsing, duplicate extra-field IDs, extended or NTFS
timestamp mismatch rejection, ZIP64 extra-field or descriptor rejection, Unix
symlink/special-file rejection, central-directory digital-signature provenance,
unsupported compression-method preflight, deflate trailing-byte validation,
encrypted-flag rejection, or DOS directory-attribute checks.

It owns only strict central/local ZIP extra-field ID mismatch reporting and
assertion policy.

## Follow-Up

Keep AES/encrypted payload support, spanning archives, cryptographic
central-directory signature validation, full ZIP64 large-archive support,
non-deflate decompressor implementation, and package-reader wiring for strict
extra-field parity policies as separate bounded slices.
