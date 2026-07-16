# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260606T054116Z`
Base accepted HEAD: `5918e02b2644d9134b3cf328783815ce2823b34a`
Date: 2026-06-06 UTC

No current Pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- `ZipPackage::extraFieldPreflight()` now reports entries whose central and
  local headers carry the same single-use extra-field ID but different field
  bytes.
- The preflight exposes `mismatchedExtraFieldValueEntryCount`,
  `valueMismatchedEntries`, per-entry `mismatchedExtraFieldValueIds`, and
  per-entry `hasMismatchedExtraFieldValues` provenance for package review.
- `ZipPackage::assertMatchingExtraFieldValues()` rejects value-mismatched
  entries before DOCX, ODT, EPUB, or WordPress media handoff code treats the
  payload as importable package bytes.
- Duplicate extra-field IDs remain owned by the existing duplicate-ID policy;
  this slice compares only IDs present exactly once in both central and local
  headers.
- The WordPress ZIP package preflight smoke now builds a bounded same-ID
  central/local extra-field value mismatch fixture and verifies strict
  rejection.

Source truth: ZIP central-directory metadata and local-header metadata both
carry extra fields. A bounded Office-package reader must not silently accept a
package where the same review-relevant extra-field ID advertises different
bytes in those two locations. That mismatch is now explicit review provenance
and can be rejected by strict import policy.

## Evidence

Baseline focused ZIP package verification before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 659 assertions, 0 failures
```

Focused ZIP package verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 688 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package test cases: `66 -> 67`.
- Focused ZIP package assertions: `659 -> 688` (`+29`).
- Manifest mapped checks: `1659 -> 1660`.
- Lane PHP pass count: `1215 -> 1216`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`ZipPackageEntry`, in-process ZIP fixture builders, and the existing WordPress
ZIP package preflight smoke.

No Pandoc, Cabal solver/build/test command, Haskell runner, `ZipArchive`,
zip/unzip, Word, LibreOffice, external archive tool, external office tool,
browser renderer, online sanitizer, online service, or live provider test was
executed.

Full upstream Pandoc runner parity remains gated on hydrating/building the
pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, local entry order,
stored-first mimetype checks, CRC/local-header validation, data-descriptor
placeholder checks, descriptor CRC/size validation, ZIP64 descriptor rejection,
central/local extra-field ID mismatch preflight, duplicate extra-field IDs,
extended or NTFS timestamps, ZIP64 extra-field or EOCD rejection, Unix symlink
or special-file rejection, central-directory digital-signature provenance,
unsupported compression-method preflight, deflate trailing-byte validation,
encrypted-flag rejection, DOS directory-attribute checks, path hierarchy
collision preflight, or archive-compression streams.

It owns only same-ID central/local extra-field value mismatch provenance and
strict rejection.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archives, AES/encrypted payload
support, non-deflate decompressor implementation, cryptographic
central-directory signature validation, and strict package-reader default
policy wiring as separate bounded ZIP/package slices unless a concrete
DOCX/ODT/EPUB fixture requires one earlier.
